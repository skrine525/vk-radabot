<?php

function riddlegame_cmd($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";

	switch ($command) {
		case 'старт':
			$session = riddlegame_get_session($data->object->peer_id);
			if(array_key_exists("riddle_game", $session)){
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Сессия уже активна.");
				return;
			}

			$session = riddlegame_init_session($data, $db);

			if($session === false){
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Не удалось запустить сессию.");
			}
			else{
				$keyboard = vk_keyboard(false, array(
					array(
						vk_text_button("Сдаться", array('command'=>'riddle_game','act'=>2), "negative")
					),
					array(
						vk_text_button("Остановить", array('command'=>'riddle_game','act'=>3), "primary")
					)
				));
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки]\n\n{$session["riddle_game"]["current_question"]}", null, array('keyboard' => $keyboard));
			}

			break;

		case 'стоп':
			$session = riddlegame_get_session($data->object->peer_id);
			if(!array_key_exists("riddle_game", $session)){
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Сессия не активна.");
				return;
			}
			$chat_id = $data->object->peer_id - 2000000000;
			unlink(BOT_DATADIR."/riddle_sessions/chat{$chat_id}_session.json");
			$keyboard = vk_keyboard(true, array());
			$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Сессия остановлена.", null, array('keyboard' => $keyboard));
			break;

		case 'рейтинг':
			if(array_key_exists("games", $db) && array_key_exists("riddle_game_rating", $db["games"]))
				$array = $db["games"]["riddle_game_rating"];
			else
				$array = array();

			if(count($array) > 0){
				$stats = array();
				foreach ($array as $key => $val) {
		    		$stats[] = array('id' => mb_substr($key, 2, mb_strlen($key)), 'score' => $val);;
				}

				for($i = 0; $i < sizeof($stats); $i++){
					for($j = 0; $j < sizeof($stats); $j++){
						if ($stats[$i]["score"] > $stats[$j]["score"]){
							$temp = $stats[$j];
							$stats[$j] = $stats[$i];
							$stats[$i] = $temp;
							unset($temp);
						}
					}
				}

				$stats_for_vk = "[";
				$j = 10;
				if (sizeof($stats) < 10)
					$j = sizeof($stats);
				for($i = 0; $i < $j; $i++){
					if(!is_null($stats[$i])){
						$stats_for_vk = $stats_for_vk . "{\"id\":{$stats[$i]["id"]},\"score\":{$stats[$i]["score"]}}";

					if ($i < $j - 1)
						$stats_for_vk = $stats_for_vk . ",";
				}

				}
				$stats_for_vk = $stats_for_vk . "]";

				vk_execute("
					var rating = {$stats_for_vk};
					var user_ids = rating@.id;
					var users = API.users.get({'user_ids':user_ids});
					var msg = '[Загадки] 📈Рейтинг беседы:\\n';
					var i = 0; while(i < users.length){
						msg = msg+(i+1)+'. @id'+users[i].id+' ('+users[i].first_name+' '+users[i].last_name+') — '+rating[i].score+' балл(а/ов)\\n';
						i = i + 1;
					}
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
					");
			}
			else {
				$msg = "[Загадки] 📈Рейтинг пуст.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
			}
			break;
		
		default:
			$botModule->sendCommandListFromArray($data, ", ⛔используйте:", array(
			'Загадки старт - Запускает игру "Загадки"',
			'Загадки стоп - Остановка текущей сессии загадок',
			'Загадки рейтинг - Рейтинг в игре "Загадки"'
			));
			break;
	}
}

function riddlegame_init_session($data, $db){
	$work_dir = BOT_DATADIR."/riddle_sessions";
	if(!file_exists($work_dir))
		mkdir($work_dir);


	$riddles = json_decode(file_get_contents("https://engine.lifeis.porn/api/riddles.php"), true);
	if($riddles["ok"]){
		$rnd = mt_rand(0, 65535);
		$riddle = $riddles["data"][$rnd%count($riddles["data"])];
	}
	else{
		return false;
	}

	$question = $riddle["question"];
	$question = mb_eregi_replace("⁣", "", $question);
	$answer = $riddle["answer"];
	$answer = mb_eregi_replace("⁣", "", $answer);

	$session = array(
		'riddle_game' => array(
			'current_question' => $question,
			'answer' => mb_strtolower($answer),
			'question_start_time' => time(),
			'is_completed' => false
		)
	);
	riddlegame_set_session($data->object->peer_id, $session);
	return $session;
}

function riddlegame_set_session($peer_id, $data){
	$work_dir = BOT_DATADIR."/riddle_sessions";
	$chat_id = $peer_id - 2000000000;
	file_put_contents("{$work_dir}/chat{$chat_id}_session.json", json_encode($data, JSON_UNESCAPED_UNICODE));
}

function riddlegame_get_session($peer_id){
	$work_dir = BOT_DATADIR."/riddle_sessions";
	$chat_id = $peer_id - 2000000000;
	if(file_exists("{$work_dir}/chat{$chat_id}_session.json")){
		$data = json_decode(file_get_contents("{$work_dir}/chat{$chat_id}_session.json"), true);
		if($data != false)
			return $data;
	}

	return array();
}

function riddlegame_gameplay($data, &$db){
	$session = riddlegame_get_session($data->object->peer_id);
	if(array_key_exists("riddle_game", $session)){
		$text_message = mb_strtolower($data->object->text);
		if(property_exists($data->object, "payload")){
			$payload = json_decode($data->object->payload);
			if($payload->command == "riddle_game"){
				switch ($payload->act) {
					case 1:
						$text_message = "продолжить";
						break;

					case 2:
						$text_message = "сдаться";
						break;

					case 3:
						$botModule = new BotModule($db);
						$session = riddlegame_get_session($data->object->peer_id);
						if(!array_key_exists("riddle_game", $session)){
							$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Сессия не активна.");
							return;
						}
						$chat_id = $data->object->peer_id - 2000000000;
						unlink(BOT_DATADIR."/riddle_sessions/chat{$chat_id}_session.json");
						$keyboard = vk_keyboard(true, array());
						$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Сессия остановлена.", null, array('keyboard' => $keyboard));
						return;
						break;
				}
			}
		}

		if($text_message == "продолжить" && $session["riddle_game"]["is_completed"]){
			$botModule = new BotModule($db);
			$session = riddlegame_init_session($data, $db);

			if($session === false){
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Не удалось запустить сессию.");
			}
			else{
				$keyboard = vk_keyboard(false, array(
					array(
						vk_text_button("Сдаться", array('command'=>'riddle_game','act'=>2), "negative")
					),
					array(
						vk_text_button("Остановить", array('command'=>'riddle_game','act'=>3), "primary")
					)
				));

				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки]\n\n{$session["riddle_game"]["current_question"]}", null, array('keyboard' => $keyboard));
			}
		}
		elseif($text_message == "сдаться" && !$session["riddle_game"]["is_completed"]){
			$botModule = new BotModule($db);
			$date = time(); // Переменная времени
			if($date - $session["riddle_game"]["question_start_time"] >= 30){
				$session = riddlegame_init_session($data, $db);

				$session["riddle_game"]["is_completed"] = true;
				riddlegame_set_session($data->object->peer_id, $session);

				$keyboard = vk_keyboard(false, array(
					array(
						vk_text_button("Продолжить", array('command'=>'riddle_game','act'=>1), "positive")
					),
					array(
						vk_text_button("Остановить", array('command'=>'riddle_game','act'=>3), "primary")
					)
				));

				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Правильный ответ: {$session["riddle_game"]["answer"]}.", null, array('keyboard' => $keyboard));
			}
			else
				$botModule->sendSimpleMessage($data->object->peer_id, "[Загадки] Пока что нельзя сдаться!");
		}
		elseif(!$session["riddle_game"]["is_completed"]){
			$botModule = new BotModule($db);
			$answers = explode("/", $session["riddle_game"]["answer"]);
			for($i = 0; $i < count($answers); $i++){
				if(strcmp($answers[$i], $text_message) == 0){
					$session["riddle_game"]["is_completed"] = true;
					riddlegame_set_session($data->object->peer_id, $session);
					$keyboard = vk_keyboard(false, array(
						array(
							vk_text_button("Продолжить", array('command'=>'riddle_game','act'=>1), "positive")
						),
						array(
							vk_text_button("Остановить", array('command'=>'riddle_game','act'=>3), "primary")
						)
					));
					$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "[Загадки] %USERNAME% отгадывает загадку и получает 1 балл. Правильный ответ: {$session["riddle_game"]["answer"]}.", 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
					$request = vk_parse_var($request, "USERNAME");
					vk_execute("
						var user = API.users.get({'user_ids':[{$data->object->from_id}]})[0];
						var USERNAME = '@id{$data->object->from_id} ('+user.first_name+' '+user.last_name+')';

						return API.messages.send({$request});
						");
					if(array_key_exists("games", $db) && array_key_exists("riddle_game_rating", $db["games"]))
						$db["games"]["riddle_game_rating"]["id{$data->object->from_id}"] = $db["games"]["riddle_game_rating"]["id{$data->object->from_id}"] + 1;
					else
						$db["games"]["riddle_game_rating"]["id{$data->object->from_id}"] = 1;
				}
			}
		}
	}
}

?>