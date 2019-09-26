<?php

function wordgame_cmd($finput){
	wordgame_main($finput->data, $finput->words, $finput->db);
}

function wordgame_main($data, $words, &$db){
	mb_internal_encoding("UTF-8");
	$session = wordgame_get_session($data->object->peer_id);
	if(array_key_exists(1, $words) && mb_strtolower($words[1]) == 'старт' && !array_key_exists('word_game', $session)){
		wordgame_reset_word($session, $data->object->date);
		wordgame_set_session($data->object->peer_id, $session);
		$new_word = wordgame_get_encoded_word($session);
		$msg = "[Слова] Игра запущена. Слово: {$new_word}.";
		$keyboard = vk_keyboard(false, array(
			array(
				vk_text_button("Подсказка", array('command'=>'word_game','act'=>1), "positive")
			),
			array(
				vk_text_button("Слово", array('command'=>'word_game','act'=>4), "primary")
			),
			array(
				vk_text_button("Остановить игру", array('command'=>'word_game','act'=>2), "negative")
			)
		));
		$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
		vk_execute("
			return API.messages.send({$json_request});
			");
	} elseif (mb_strtolower($words[1]) == 'стоп' && array_key_exists('word_game', $session)) {
		$empty_keyboard = vk_keyboard(true, array());
		wordgame_del_session($data->object->peer_id);
		$msg = "";
		if(!$session["word_game"]["current_word"]["can_reset"]){
			$msg = "[Слова] Игра остановлена. Было загадано слово: {$session["word_game"]["current_word"]["word"]}.";
		} else {
			$msg = "[Слова] Игра остановлена.";
		}
		$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $empty_keyboard), JSON_UNESCAPED_UNICODE);
		vk_execute("
			return API.messages.send({$json_request});
			");
	} elseif (mb_strtolower($words[1]) == 'рейтинг') {
		$array = $db["games"]["word_game"]["rating_by_score"];

		if(!is_null($array)){
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
				var msg = '[Слова] 📈Рейтинг беседы:\\n';
				var i = 0; while(i < users.length){
					msg = msg+(i+1)+'. @id'+users[i].id+' ('+users[i].first_name+' '+users[i].last_name+') - '+rating[i].score+' очка(ов)\\n';
					i = i + 1;
				}
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
				");
		}
		else {
			$msg = "[Слова] 📈Рейтинг пуст.";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
				");
		}
	} else {
		$botModule = new BotModule($db);
		$msg = ", используйте \\\"Слова старт/стоп/рейтинг\\\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	}
}

function wordgame_get_session($peer_id){
	$path = "../bot/data/word_game/sessions/{$peer_id}_session.json";
	if(file_exists($path))
		return json_decode(file_get_contents($path), true);
	else
		return array();
}

function wordgame_set_session($peer_id, $data){
	$data = json_encode($data, JSON_UNESCAPED_UNICODE);
	$path = "../bot/data/word_game/sessions/{$peer_id}_session.json";

	file_put_contents($path, $data);
}

function wordgame_del_session($peer_id){
	return unlink("../bot/data/word_game/sessions/{$peer_id}_session.json");
}

function wordgame_get_encoded_word($db){
	mb_internal_encoding("UTF-8");
	$word = preg_split('//u', $db["word_game"]["current_word"]["word"], null, PREG_SPLIT_NO_EMPTY);
	$en_word = "";
	for($i = 0; $i < sizeof($word); $i++){
		$is_symbol_encrypted = true;
		for($j = 0; $j < sizeof($db["word_game"]["current_word"]["opened_symbols"]); $j++){
			if($i == $db["word_game"]["current_word"]["opened_symbols"][$j]){
				$en_word = $en_word . $word[$i];
				$is_symbol_encrypted = false;
			}
		}
		if ($is_symbol_encrypted)
			$en_word = $en_word . "&#9679;";
	}
	return $en_word;
}

function wordgame_reset_word(&$db, $date){
	mb_internal_encoding("UTF-8");
	while (true){
		$words_data = file("../bot/data/word_game/word_rus_database.txt");
		$rand = mt_rand(0, sizeof($words_data));
		$word = str_ireplace("\n", "", $words_data[$rand]);
		$word = str_ireplace("\r", "", $word);
		$db["word_game"]["current_word"]["word"] = $word;
		$db["word_game"]["current_word"]["word_guessing_time"] = $date;
		$db["word_game"]["current_word"]["opened_symbols"] = array();
		$db["word_game"]["current_word"]["can_reset"] = false;
		$db["word_game"]["current_word"]["used_hints"] = 0;
		$db["word_game"]["current_word"]["last_using_hints_time"] = 0;

		if (mb_strlen($db["word_game"]["current_word"]["word"]) > 3)
			break;
	}
}

function wordgame_get_count_of_hints($number){
	if($number <= 4)
		return 2;
	elseif($number <= 6)
		return 3;
	elseif($number <= 9)
		return 4;
	elseif($number <= 13)
		return 5;
	elseif($number <= 18)
		return 6;
	elseif($number <= 24)
		return 7;
	elseif($number <= 31)
		return 8;
}

function wordgame_gameplay($data, &$db){
	mb_internal_encoding("UTF-8");
	$session = wordgame_get_session($data->object->peer_id);

	$message_text = "";
	if(property_exists($data->object, "payload")){
		$payload = json_decode($data->object->payload);
		if($payload->command == "word_game"){
			switch ($payload->act) {
				case 1:
					$message_text = "подсказка";
					break;

				case 2:
					wordgame_main($data, array('nil', 'стоп'), $db);
					break;

				case 3:
					$message_text = "продолжить";
					break;

				case 4:
					$message_text = "слово";
					break;

				case 5:
					$message_text = "сдаться";
					break;
			}
		} else {
			$message_text = mb_strtolower($data->object->text);
		}
	}
	else{
		$message_text = mb_strtolower($data->object->text);
	}

	if(array_key_exists('word_game', $session)){
		if($data->object->date - $session["word_game"]["current_word"]["word_guessing_time"] >= 600 && !$session["word_game"]["current_word"]["can_reset"]){
			$empty_keyboard = vk_keyboard(1, array());
			wordgame_del_session($data->object->peer_id);
			$msg = "[Слова] Игра остановлена.";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{$empty_keyboard}'});
				");
		}
		elseif ($message_text == "продолжить" && $session["word_game"]["current_word"]["can_reset"]){
			wordgame_reset_word($session, $data->object->date);
			wordgame_set_session($data->object->peer_id, $session);
			$new_word = wordgame_get_encoded_word($session);
			$msg = "[Слова] Новое слово: {$new_word}.";
			$keyboard = vk_keyboard(false, array(
				array(
					vk_text_button("Подсказка", array('command'=>'word_game','act'=>1), "positive")
				),
				array(
					vk_text_button("Слово", array('command'=>'word_game','act'=>4), "primary")
				),
				array(
					vk_text_button("Остановить игру", array('command'=>'word_game','act'=>2), "negative")
			)
			));
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
			vk_execute("
				return API.messages.send({$json_request});
			");
		}
		elseif ($message_text == "слово" && !$session["word_game"]["current_word"]["can_reset"]){
			$word = wordgame_get_encoded_word($session);
			$msg = "[Слова] Слово: {$word}.";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
				");
		} elseif ($message_text == 'подсказка' && !$session["word_game"]["current_word"]["can_reset"]) {
			mb_internal_encoding("UTF-8");
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			if($wordlen - $session["word_game"]["current_word"]["used_hints"] > 3){
				if(($data->object->date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
					$session["word_game"]["current_word"]["used_hints"] = $session["word_game"]["current_word"]["used_hints"] + 1;
					$session["word_game"]["current_word"]["last_using_hints_time"] = $data->object->date;
					while(true){
						$rnd = mt_rand(0, $wordlen-1);
						$end = true;
						for($i = 0; $i < sizeof($session["word_game"]["current_word"]["opened_symbols"]); $i++){
							if($session["word_game"]["current_word"]["opened_symbols"][$i] == $rnd)
								$end = false;
						}
						if($end){
							$session["word_game"]["current_word"]["opened_symbols"][] = $rnd;
							break;
						}
					}
					wordgame_set_session($data->object->peer_id, $session);
					$word = wordgame_get_encoded_word($session);
					if ($wordlen - $session["word_game"]["current_word"]["used_hints"] <= 3){
						$keyboard = vk_keyboard(false, array(
							array(
								vk_text_button("Сдаться", array('command'=>'word_game','act'=>5), "negative")
							),
							array(
								vk_text_button("Слово", array('command'=>'word_game','act'=>4), "primary")
							),
							array(
								vk_text_button("Остановить игру", array('command'=>'word_game','act'=>2), "negative")
							)
						));
						$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "[Слова] Подсказка использована. Больше подсказок нет! Слово: {$word}.", 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
						vk_execute("
							return API.messages.send({$json_request});
							");
					}
					else {
						$msg = "[Слова] Подсказка использована. Слово: {$word}.";
						vk_execute("
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
							");
					}
				} else {
					$msg = "[Слова] Нельзя использовать подсказки чаще 20 секунд.";
					vk_execute("
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
						");
				}
			} else {
				$msg = "[Слова] Больше подсказок нет.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
			}
		} elseif($message_text == 'сдаться' && !$session["word_game"]["current_word"]["can_reset"]){
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			if($wordlen - $session["word_game"]["current_word"]["used_hints"] <= 3){
				if(($data->object->date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
					$msg = "[Слова] Слово \"{$session["word_game"]["current_word"]["word"]}\" не было откадано!\nЕсли желаете дальше играть, напишите \"Продолжить\".";
					$session["word_game"]["current_word"]["can_reset"] = true;
					wordgame_set_session($data->object->peer_id, $session);
					$keyboard = vk_keyboard(false, array(
						array(
							vk_text_button("Продолжить", array('command'=>'word_game','act'=>3), "primary")
						),
						array(
							vk_text_button("Остановить игру", array('command'=>'word_game','act'=>2), "negative")
						)
					));
					$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
					vk_execute("
						return API.messages.send({$json_request});
						");
				}
				else {
					$msg = "[Слова] Нельзя сдаться раньше 20 секунд после использования всех подсказок.";
					vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
				}
			} else {
				$msg = "[Слова] Пока нельзя сдаться.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
			}
		} elseif (strcasecmp($message_text, $session["word_game"]["current_word"]["word"]) == 0 && !$session["word_game"]["current_word"]["can_reset"]){
			$word = $session["word_game"]["current_word"]["word"];
			$session["word_game"]["current_word"]["can_reset"] = true;
			mb_internal_encoding("UTF-8");
			$score = mb_strlen($word) - $session["word_game"]["current_word"]["used_hints"] - 2;
			$db["games"]["word_game"]["rating_by_score"]["id{$data->object->from_id}"] = $db["games"]["word_game"]["rating_by_score"]["id{$data->object->from_id}"] + $score;
			wordgame_set_session($data->object->peer_id, $session);
			$keyboard = vk_keyboard(false, array(
				array(
					vk_text_button("Продолжить", array('command'=>'word_game','act'=>3), "primary")
				),
				array(
					vk_text_button("Остановить игру", array('command'=>'word_game','act'=>2), "negative")
				)
			));
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '%msg%', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "msg");
			vk_execute("
				var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'screen_name,sex'})[0];
				var msg = '[Слова] @' + user.screen_name + ' (' + user.first_name + ' ' + user.last_name + ') ';
				if (user.sex == 1){
					msg = msg + ' отгадала слово \\\"{$word}\\\" и получает {$score} очка(ов).\\nЕсли желаете дальше играть, напишите \\\"Продолжить\\\".';
				} else {
					msg = msg + ' отгадал слово \\\"{$word}\\\" и получает {$score} очка(ов).\\nЕсли желаете дальше играть, напишите \\\"Продолжить\\\".';
				}
				return API.messages.send({$json_request});
				");
		}
	}
}

?>