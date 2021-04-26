<?php

// Инициализация команд
function wordgame_initcmd($event){
	$event->addTextMessageCommand("!слова", 'wordgame_cmd');
	$event->addCallbackButtonCommand('word_game', 'wordgame_gameplay_cb');
}

function wordgame_cmd($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$session = wordgame_get_session($data->object->peer_id);
	if(array_key_exists(1, $argv) && mb_strtolower($argv[1]) == 'старт'){
		$chatModes = $finput->event->getChatModes();
		if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
			$messagesModule = new Bot\Messages($db);
			$messagesModule->setAppealID($data->object->from_id);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔В чате отключены игры!");
			return;
		}

		if(!array_key_exists('word_game', $session)){
			$date = time(); // Переменная времени
			wordgame_reset_word($session, $date, $data->object->from_id);
			wordgame_set_session($data->object->peer_id, $session);
			$new_word = wordgame_get_encoded_word($session);
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			$explanation = $session["word_game"]["current_word"]["explanation"];
			$msg = "[Слова] Игра запущена. Слово ({$wordlen} б.): {$new_word}.\nОписание: {$explanation}.";
			$keyboard = vk_keyboard(false, array(
				array(
					vk_callback_button("Подсказка", array('word_game', 2), "positive")
				),
				array(
					vk_callback_button("Слово", array('word_game', 3), "primary")
				),
				array(
					vk_callback_button("Остановить игру", array('word_game', 1), "negative")
				)
			));
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
			vk_execute("
				return API.messages.send({$json_request});
				");
		}
		else{
			$messagesModule = new Bot\Messages($db);
			$messagesModule->sendMessage($data->object->peer_id, "[Слова] Игра уже запущена.");
		}
	} elseif (mb_strtolower($argv[1]) == 'стоп') {
		if(array_key_exists('word_game', $session)){
			if($session["word_game"]["started_by"] != $data->object->from_id){
				$permissionSystem = $finput->event->getPermissionSystem();
				if(!$permissionSystem->checkUserPermission($data->object->from_id, 'customize_chat')){ // Проверка разрешения
					$messagesModule = new Bot\Messages($db);
					$messagesModule->sendMessage($data->object->peer_id, "[Слова] Вы не имеете права останавливать игру, запущенную другим пользователем.");
					return;
				}
			}
			$empty_keyboard = vk_keyboard(true, array());
			wordgame_del_session($data->object->peer_id);
			$msg = "[Слова] Игра остановлена.";
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $empty_keyboard), JSON_UNESCAPED_UNICODE);
			vk_execute("
				return API.messages.send({$json_request});
				");
		}
		else{
			$messagesModule = new Bot\Messages($db);
			$messagesModule->sendMessage($data->object->peer_id, "[Слова] Игра не запущена.");
		}
	} elseif (mb_strtolower($argv[1]) == 'рейтинг') {
		$array = $db->getValueLegacy(array("games", "word_game_rating"), array());

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

			vk_execute("var rating={$stats_for_vk};var user_ids=rating@.id;var users=API.users.get({'user_ids':user_ids});var msg='[Слова] 📈Рейтинг беседы:\\n';var i=0;while(i<users.length){msg=msg+(i+1)+'. @id'+users[i].id+' ('+users[i].first_name+' '+users[i].last_name+') — '+rating[i].score+' очка(ов)\\n';i=i+1;}return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});");
		}
		else {
			$msg = "[Слова] 📈Рейтинг пуст.";
			vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
		}
	} else {
		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);
		$msg = "%appeal%, используйте \"Слова старт/стоп/рейтинг\".";
		$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
	}
}

function wordgame_get_session($peer_id){
	$chat_id = $peer_id - 2000000000;
	$session = GameController::getSession($chat_id);
	if($session !== false && $session->id == 'word_game')
		return $session->object;
	else
		return array();
}

function wordgame_set_session($peer_id, $data){
	$chat_id = $peer_id - 2000000000;
	GameController::setSession($chat_id, 'word_game', $data);
}

function wordgame_del_session($peer_id){
	$chat_id = $peer_id - 2000000000;
	return GameController::deleteSession($chat_id, 'word_game');
}

function wordgame_get_encoded_word($session){
	$word = preg_split('//u', $session["word_game"]["current_word"]["word"], null, PREG_SPLIT_NO_EMPTY);
	$en_word = "";
	for($i = 0; $i < sizeof($word); $i++){
		$is_symbol_encrypted = true;
		for($j = 0; $j < sizeof($session["word_game"]["current_word"]["opened_symbols"]); $j++){
			if($i == $session["word_game"]["current_word"]["opened_symbols"][$j]){
				$en_word = $en_word . $word[$i];
				$is_symbol_encrypted = false;
			}
		}
		if ($is_symbol_encrypted)
			$en_word = $en_word . "&#9679;";
	}
	return $en_word;
}

function wordgame_reset_word(&$session, $date, $user_id){
	while (true){
		$database = json_decode(file_get_contents("https://engine.lifeis.porn/api/words.php?rus=true"), true);
		if($database["ok"]){
			$word = $database["data"]["word"];
			$explanation = $database["data"]["explanation"];
			//$explanation = mb_strtoupper(mb_substr($explanation, 0, 1)) . mb_strtolower(mb_substr($explanation, 1));
			$session["word_game"]["current_word"]["word"] = $word;
			$session["word_game"]["current_word"]["explanation"] = $explanation;
			$session["word_game"]["current_word"]["word_guessing_time"] = $date;
			$session["word_game"]["current_word"]["opened_symbols"] = array();
			$session["word_game"]["current_word"]["can_reset"] = false;
			$session["word_game"]["current_word"]["used_hints"] = 0;
			$session["word_game"]["current_word"]["last_using_hints_time"] = 0;
			$session["word_game"]["started_by"] = $user_id;

			if (mb_strlen($session["word_game"]["current_word"]["word"]) > 3)
				break;
			}
	}
}

function wordgame_gameplay_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$session = wordgame_get_session($data->object->peer_id);

	$date = time(); // Переменная времени

	if(array_key_exists('word_game', $session)){
		$date = time(); // Переменная времени
		if($date - $session["word_game"]["current_word"]["word_guessing_time"] >= 600 && !$session["word_game"]["current_word"]["can_reset"]){
			$empty_keyboard = vk_keyboard(true, array());
			wordgame_del_session($data->object->peer_id);
			$msg = "[Слова] Игра остановлена.";
			$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Выполнено!"), JSON_UNESCAPED_UNICODE)));
			vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{$empty_keyboard}'});");
		}
		else{
			$act = bot_get_array_value($payload, 1, 0);

			switch ($act) {
				case 1:
				if($session["word_game"]["started_by"] != $data->object->user_id){
					$permissionSystem = $finput->event->getPermissionSystem();
					if(!$permissionSystem->checkUserPermission($data->object->user_id, 'customize_chat')){ // Проверка разрешения
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Вы не имеете права останавливать игру, запущенную другим пользователем.');
						return;
					}
				}
				$empty_keyboard = vk_keyboard(true, array());
				wordgame_del_session($data->object->peer_id);
				$msg = "[Слова] Игра остановлена.";
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $empty_keyboard), JSON_UNESCAPED_UNICODE);
				$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Выполнено!"), JSON_UNESCAPED_UNICODE)));
				vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{$empty_keyboard}'});");
				break;

				case 2:
				$chatModes = $finput->event->getChatModes();
				if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
					return;
				}

				if(!$session["word_game"]["current_word"]["can_reset"]){
					$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
					if($wordlen - $session["word_game"]["current_word"]["used_hints"] > 3){
						if(($date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
							$session["word_game"]["current_word"]["used_hints"] = $session["word_game"]["current_word"]["used_hints"] + 1;
							$session["word_game"]["current_word"]["last_using_hints_time"] = $date;
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
							$explanation = $session["word_game"]["current_word"]["explanation"];
							if ($wordlen - $session["word_game"]["current_word"]["used_hints"] <= 3){
								$keyboard = vk_keyboard(false, array(
									array(
										vk_callback_button("Сдаться", array('word_game', 4), "negative")
									),
									array(
										vk_callback_button("Слово", array('word_game', 3), "primary")
									),
									array(
										vk_callback_button("Остановить игру", array('word_game', 1), "negative")
									)
								));
								$msg = "[Слова] Больше подсказок нет! Слово ({$wordlen} б.): {$word}.\nОписание: {$explanation}.";
								$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
								$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Подсказка использована!"), JSON_UNESCAPED_UNICODE)));
								vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({$json_request});");
							}
							else {
								$msg = "[Слова] Подсказка использована. Слово ({$wordlen} б.): {$word}.\nОписание: {$explanation}.";
								$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg), JSON_UNESCAPED_UNICODE);
								$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Подсказка использована!"), JSON_UNESCAPED_UNICODE)));
								vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({$json_request});");
							}
						}
						else {
							$left_time = 20 - ($date - $session["word_game"]["current_word"]["last_using_hints_time"]);
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Подсказку можно использовать повторно через {$left_time} с.");
						}
					}
					else
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Больше подсказок нет.');
				}
				else
				 	bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Необходимо Продолжить игру!');
				break;

				case 3:
				$chatModes = $finput->event->getChatModes();
				if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
					return;
				}

				if(!$session["word_game"]["current_word"]["can_reset"]){
					$word = wordgame_get_encoded_word($session);
					$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
					$explanation = $session["word_game"]["current_word"]["explanation"];
					$msg = "[Слова] Слово ({$wordlen} б.): {$word}.\nОписание: {$explanation}.";
					$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg), JSON_UNESCAPED_UNICODE);
					$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Слово отображено!"), JSON_UNESCAPED_UNICODE)));
					vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({$json_request});");
				}
				else
				 	bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Необходимо Продолжить игру!');
				break;

				case 4:
				$chatModes = $finput->event->getChatModes();
				if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
					return;
				}

				if(!$session["word_game"]["current_word"]["can_reset"]){
					$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
					if($wordlen - $session["word_game"]["current_word"]["used_hints"] <= 3){
						if(($date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
							$msg = "[Слова] Слово \"{$session["word_game"]["current_word"]["word"]}\" не было откадано!\nЕсли желаете дальше играть, напишите \"Продолжить\".";
							$session["word_game"]["current_word"]["can_reset"] = true;
							wordgame_set_session($data->object->peer_id, $session);
							$keyboard = vk_keyboard(false, array(
								array(
									vk_callback_button("Продолжить", array('word_game', 5), "primary")
								),
								array(
									vk_callback_button("Остановить игру", array('word_game', 1), "negative")
								)
							));
							$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
							$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Выполнено."), JSON_UNESCAPED_UNICODE)));
							vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({$json_request});");
						}
						else
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Нельзя сдаться раньше 20 секунд после использования всех подсказок');
					}
					else
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Пока нельзя сдаться.');
				}
				else
				 	bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Необходимо Продолжить партию!');
				break;

				case 5:
				$chatModes = $finput->event->getChatModes();
				if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
					return;
				}
				
				if($session["word_game"]["current_word"]["can_reset"]){
					wordgame_reset_word($session, $date, $session["word_game"]["started_by"]);
					wordgame_set_session($data->object->peer_id, $session);
					$new_word = wordgame_get_encoded_word($session);
					$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
					$explanation = $session["word_game"]["current_word"]["explanation"];
					$msg = "[Слова] Новое слово ({$wordlen} б.): {$new_word}.\nОписание: {$explanation}.";
					$keyboard = vk_keyboard(false, array(
						array(
							vk_callback_button("Подсказка", array('word_game', 2), "positive")
						),
						array(
							vk_callback_button("Слово", array('word_game', 3), "primary")
						),
						array(
							vk_callback_button("Остановить игру", array('word_game', 1), "negative")
						)
					));
					$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
					$snackbar_json_request = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "✅ Выполнено."), JSON_UNESCAPED_UNICODE)));
					vk_execute("API.messages.sendMessageChatEventAnswer({$snackbar_json_request});return API.messages.send({$json_request});");
				}
				else
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Необходимо Завершить партию!');
				break;

				default:
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
				break;
			}
		}
	}
	else
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "[Слова] Игра не запущена!");

}

function wordgame_gameplay($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$db = $finput->db;

	$chatModes = $finput->event->getChatModes();
	if(!$chatModes->getModeValue("games_enabled")) // Отключаем, если в беседе запрещены игры
		return;

	$session = wordgame_get_session($data->object->peer_id);

	$date = time(); // Переменная времени

	$message_text = mb_strtolower($data->object->text);

	if(array_key_exists('word_game', $session)){
		$date = time(); // Переменная времени
		if($date - $session["word_game"]["current_word"]["word_guessing_time"] >= 600 && !$session["word_game"]["current_word"]["can_reset"]){
			$empty_keyboard = vk_keyboard(true, array());
			wordgame_del_session($data->object->peer_id);
			$msg = "[Слова] Игра остановлена.";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{$empty_keyboard}'});
				");
			return true;
		}
		elseif ($message_text == "продолжить" && $session["word_game"]["current_word"]["can_reset"]){
			wordgame_reset_word($session, $date, $session["word_game"]["started_by"]);
			wordgame_set_session($data->object->peer_id, $session);
			$new_word = wordgame_get_encoded_word($session);
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			$explanation = $session["word_game"]["current_word"]["explanation"];
			$msg = "[Слова] Новое слово ({$wordlen} б.): {$new_word}.\nОписание: {$explanation}.";
			$keyboard = vk_keyboard(false, array(
				array(
					vk_callback_button("Подсказка", array('word_game', 2), "positive")
				),
				array(
					vk_callback_button("Слово", array('word_game', 3), "primary")
				),
				array(
					vk_callback_button("Остановить игру", array('word_game', 1), "negative")
				)
			));
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
			vk_execute("
				return API.messages.send({$json_request});
			");
			return true;
		}
		elseif ($message_text == "слово" && !$session["word_game"]["current_word"]["can_reset"]){
			$word = wordgame_get_encoded_word($session);
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			$explanation = $session["word_game"]["current_word"]["explanation"];
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "[Слова] Слово ({$wordlen} б.): {$word}.\nОписание: {$explanation}."), JSON_UNESCAPED_UNICODE);
			vk_execute("
				return API.messages.send({$json_request});
				");
			return true;
		}
		elseif ($message_text == 'подсказка' && !$session["word_game"]["current_word"]["can_reset"]) {
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			if($wordlen - $session["word_game"]["current_word"]["used_hints"] > 3){
				if(($date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
					$session["word_game"]["current_word"]["used_hints"] = $session["word_game"]["current_word"]["used_hints"] + 1;
					$session["word_game"]["current_word"]["last_using_hints_time"] = $date;
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
								vk_callback_button("Сдаться", array('word_game', 4), "negative")
							),
							array(
								vk_callback_button("Слово", array('word_game', 3), "primary")
							),
							array(
								vk_callback_button("Остановить игру", array('word_game', 1), "negative")
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
				}
				else {
					$left_time = 20 - ($date - $session["word_game"]["current_word"]["last_using_hints_time"]);
					$msg = "[Слова] Подсказку можно использовать повторно через {$left_time} с.";
					vk_execute("
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
						");
				}
			}
			else {
				$msg = "[Слова] Больше подсказок нет.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
			}
			return true;
		}
		elseif($message_text == 'сдаться' && !$session["word_game"]["current_word"]["can_reset"]){
			$wordlen = mb_strlen($session["word_game"]["current_word"]["word"]);
			if($wordlen - $session["word_game"]["current_word"]["used_hints"] <= 3){
				if(($date - $session["word_game"]["current_word"]["last_using_hints_time"]) >= 20){
					$msg = "[Слова] Слово \"{$session["word_game"]["current_word"]["word"]}\" не было откадано!\nЕсли желаете дальше играть, напишите \"Продолжить\".";
					$session["word_game"]["current_word"]["can_reset"] = true;
					wordgame_set_session($data->object->peer_id, $session);
					$keyboard = vk_keyboard(false, array(
						array(
							vk_callback_button("Продолжить", array('word_game', 5), "primary")
						),
						array(
							vk_callback_button("Остановить игру", array('word_game', 1), "negative")
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
			}
			else {
				$msg = "[Слова] Пока нельзя сдаться.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					");
			}
			return true;
		}
		elseif (strcasecmp($message_text, $session["word_game"]["current_word"]["word"]) == 0 && !$session["word_game"]["current_word"]["can_reset"]){
			$word = $session["word_game"]["current_word"]["word"];
			$session["word_game"]["current_word"]["can_reset"] = true;
			$score = mb_strlen($word) - $session["word_game"]["current_word"]["used_hints"] - 2;
			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getDocumentID()], ['$inc' => ["games.word_game_rating.id{$data->object->from_id}" => $score]]);
			$db->executeBulkWrite($bulk);
			wordgame_set_session($data->object->peer_id, $session);
			$keyboard = vk_keyboard(false, array(
				array(
					vk_callback_button("Продолжить", array('word_game', 5), "primary")
				),
				array(
					vk_callback_button("Остановить игру", array('word_game', 1), "negative")
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
			return true;
		}
	}
	return false;
}

?>