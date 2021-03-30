<?php

// Модуля для размещения Legacy функций
namespace Legacy{
	function fun_limnum(&$num, $min, $max){
		$num = $num > $max ? $max : $num;
		$num = $num < $min ? $min : $num;
	}

	function fun_pet_dbcheck(&$pet){
		// Ограничение переменных

		fun_limnum($pet["hungry"], 0, 100);
		fun_limnum($pet["thirst"], 0, 100);
		fun_limnum($pet["happiness"], 0, 100);
		fun_limnum($pet["cheerfulness"], 0, 100);
	}

	function fun_pet_dbget($db){
		$pet_database = $db->getValue(['fun', 'pet'], []);
		$pet = array();

		$time = time(); // Переменная времени

		// Стандартные значения
		$petdb_default = [
			"hungry" => 50,
			"thirst" => 50,
			"happiness" => 50,
			"cheerfulness" => 50,
			"sleeping" => false,
			"last_update_time" => $time
		];

		foreach ($petdb_default as $key => $value) {
			if(array_key_exists($key, $pet_database))
				$pet[$key] = $pet_database[$key];
			else
				$pet[$key] = $value;
		}

		$time_passed = $time - $pet["last_update_time"];
		if($time_passed >= 600){
			$passed_times = intdiv($time_passed, 600);

			$pet["hungry"] -= 2 * $passed_times;
			$pet["thirst"] -= 4 * $passed_times;
			$pet["happiness"] -= 3 * $passed_times;

			if($pet["sleeping"])
				$pet["cheerfulness"] += 8 * $passed_times;
			else
				$pet["cheerfulness"] -= 4 * $passed_times;
		}
		fun_pet_dbcheck($pet);

		return $pet;
	}

	function fun_pet_dbset($db, $pet){
		$db->setValue(['fun', 'pet'], $pet);
	}

	function fun_pet_menu($data, $pet, $msg, $messagesModule, $testing_user_id){
		$keyboard_array = array();
		if(!$pet["sleeping"]){
			$b1 = array(
				vk_callback_button("Покормить", ['fun_pet', $testing_user_id, 0], "primary"),
				vk_callback_button("Дать попить", ['fun_pet', $testing_user_id, 1], "primary"),

			);
			$b2 = array(
				vk_callback_button("Поиграть", ['fun_pet', $testing_user_id, 4], "primary"),
				vk_callback_button("Погладить", ['fun_pet', $testing_user_id, 5], "primary"),
			);
			$b3 = array(
				vk_callback_button("Спать", ['fun_pet', $testing_user_id, 2], "positive"),
				vk_callback_button("Закрыть", ['bot_menu', $testing_user_id, 0, "%appeal%, 😸Возвращайся поскорей."], "negative")
			);
			$keyboard_array = array($b1, $b2, $b3);
		} else {
			$b1 = array(vk_callback_button("Разбудить", ['fun_pet', $testing_user_id, 2], "positive"));
			$b2 = array(vk_callback_button("Закрыть", ['bot_menu', $testing_user_id, 0, "%appeal%, 😸Возвращайся поскорей."], "negative"));
			$keyboard_array = array($b1, $b2);
		}
		$keyboard = vk_keyboard_inline($keyboard_array);

		fun_pet_dbcheck($pet);
		$hungry = $pet["hungry"];
		$thirst = $pet["thirst"];
		$happiness = $pet["happiness"];
		$cheerfulness = $pet["cheerfulness"];

		switch ($data->type) {
			case 'message_new':
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%{$msg}\n✅Сытость: {$hungry}/100\n✅Жажда: {$thirst}/100\n✅Счастье: {$happiness}/100\n✅Бодрость: {$cheerfulness}/100", ['keyboard' => $keyboard]);
			break;

			case 'message_event':
			$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, "%appeal%{$msg}\n✅Сытость: {$hungry}/100\n✅Жажда: {$thirst}/100\n✅Счастье: {$happiness}/100\n✅Бодрость: {$cheerfulness}/100", ['keyboard' => $keyboard]);
			break;
		}
	}

	function fun_pet_keyhandler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$testing_user_id = bot_get_array_value($payload, 1, 0);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '😔 К сожаленю, вам это не доступно!');
			return;
		}

		$command = bot_get_array_value($payload, 2, -1);
		$messagesModule = new \Bot\Messages($db);
		$messagesModule->setAppealID($data->object->user_id);

		$pet = fun_pet_dbget($db);
		switch ($command) {
			case 2:
			$msg = "";
			if($pet["sleeping"]){
				$msg = ", вы разбудили @id317258850 (Любу).😘";
			} else {
				$msg = ", вы уложили @id317258850 (Любу) спать.😴";
			}
			$pet["sleeping"] = !$pet["sleeping"];
			fun_pet_menu($data, $pet, $msg, $messagesModule, $testing_user_id);
			break;

			case 0:
			if($pet["hungry"] <= 80){
				$pet["hungry"] = 100;
				fun_pet_menu($data, $pet, ", вы покормили @id317258850 (Любу).😸", $messagesModule, $testing_user_id);
			} else {
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) не хочет кушать.🙄", $messagesModule, $testing_user_id);
			}
			break;

			case 1:
			if($pet["thirst"] <= 80){
				$pet["thirst"] = 100;
				fun_pet_menu($data, $pet, ", вы дали попить @id317258850 (Любе).😸", $messagesModule, $testing_user_id);
			} else {
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) не хочет пить.🙄", $messagesModule, $testing_user_id);
			}
			break;

			case 4:
			if($pet["hungry"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет кушать.🥺 Покормите её!", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["thirst"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет пить.🥺 Помогите ей!", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["cheerfulness"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет спать. Уложите ее в кроватку.😴", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["happiness"] > 50){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) не хочет играть.🙄", $messagesModule, $testing_user_id);
				break;
			}
				$pet["happiness"] += 50;
				$pet["hungry"] -= 10;
				$pet["thirst"] -= 10;
				$pet["cheerfulness"] -= 15;
				fun_pet_menu($data, $pet, ", вы поиграли с @id317258850 (Любой).🤗", $messagesModule, $testing_user_id);
			break;

			case 5:
			if($pet["hungry"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет кушать.🥺 Покормите её!", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["thirst"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет пить.🥺 Помогите ей!", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["cheerfulness"] < 20){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) хочет спать. Уложите ее в кроватку.😴", $messagesModule, $testing_user_id);
				break;
			} elseif($pet["happiness"] > 80){
				fun_pet_menu($data, $pet, ", @id317258850 (Люба) не хочет, чтобы её гладили.🙄", $messagesModule, $testing_user_id);
				break;
			}
			$pet["happiness"] += 20;
			fun_pet_menu($data, $pet, ", вы погладили @id317258850 (Любу).🤗", $messagesModule, $testing_user_id);
			break;
		}
		fun_pet_dbset($db, $pet);
	}

	class SysMemes{
		const MEMES = array('мемы', 'f', 'topa', 'андрей', 'олег', 'ябловод', 'люба', 'керил', 'юля', 'олды тут?', 'кб', 'некита', 'егор', 'ксюша', 'дрочить', 'саня', 'аля', 'дрочить на чулки', 'дрочить на карину', 'дрочить на амину', 'оффники', 'пашел нахуй', 'лохи беседы', 'дата регистрации', 'попить чай');

		public static function isExists($meme_name){
			$exists = false;
			for($i = 0; $i < count(self::MEMES); $i++){
				if(self::MEMES[$i] == $meme_name){
					$exists = true;
					break;
				}
			}

			return $exists;
		}

		public static function handler($data, $meme_name, &$db){
			$chatModes = new \ChatModes($db);
			if(!$chatModes->getModeValue("allow_memes") || !$chatModes->getModeValue("legacy_enabled"))
				return;

			if(!self::isExists($meme_name))
				return false;
			$botModule = new \BotModule($db);

			switch ($meme_name) {
				case 'мемы';
				$meme_str_list = "";
				for($i = 0; $i < count(self::MEMES); $i++){
					$name = self::MEMES[$i];
					if($meme_str_list == "")
						$meme_str_list = "[{$name}]";
					else
						$meme_str_list = $meme_str_list . ", [{$name}]";
				}
				$botModule->sendSilentMessage($data->object->peer_id, ", 📝список СИСТЕМНЫХ мемов:\n".$meme_str_list, $data->object->from_id);
				break;

				case 'f':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => 'F', 'attachment' => 'photo-161901831_456239025'));
				break;

				case 'topa':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'attachment' => 'photo-161901831_456239028'));
				break;

				case 'mem1':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'attachment' => 'photo-161901831_456239029'));
				break;

				case 'mem2':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'attachment' => 'photo-161901831_456239031'));
				break;

				case 'андрей':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id202643466 (Гоооондооооооооооооооон!)"));
				return 'ok';
				break;

				case 'олег':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id278561962 (Пиииидоооор!)", 'attachment' => 'photo-161901831_456239033'));
				return 'ok';
				break;

				case 'ябловод':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "IT'S REVOLUTION JOHNY!"));
				return 'ok';
				break;

				case '-люба':
				$s1 = array(vk_text_button("Люба❤", array('command'=>'fun','meme_id'=>1), "positive"), vk_text_button("Люба🖤", array('command'=>'fun','meme_id'=>1), "primary"), vk_text_button("Люба💙", array('command'=>'fun','meme_id'=>1), "positive"));
				$s2 = array(vk_text_button("Люба💚", array('command'=>'fun','meme_id'=>1), "primary"), vk_text_button("Люба💛", array('command'=>'fun','meme_id'=>1), "positive"), vk_text_button("Люба💖", array('command'=>'fun','meme_id'=>1), "primary"));
				$keyboard = vk_keyboard(true, array($s1, $s2));
				$msg = "Обана, кнопочки!";
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({$json_request});
					");
				//vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => '@id317258850 (<3)', 'attachment' => 'photo-161901831_456239030'));
				//vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id278561962 (Олежа) +"." @id317258850 (Люба) = &#10084;&#128420;&#128154;&#128155;&#128156;&#128153;"));
				//$code = bot_draw_luba($data);
				//vk_execute($code);
				return 'ok';
				break;

				case 'люба':
				$pet = fun_pet_dbget($db);
				$messagesModule = new \Bot\Messages($db);
				$messagesModule->setAppealID($data->object->from_id);
				$msg = ", @id317258850 (Люба) - это котеночек😺. Ухаживайте за ней и делайте ее счастливой.";
				fun_pet_menu($data, $pet, $msg, $messagesModule, $data->object->from_id);
				fun_pet_dbset($db, $pet);
				return;
				break;

				case 'керил':
				$keyboard = vk_keyboard(true, array(array(vk_text_button("Кирилл", array('command'=>'fun','meme_id'=>3,'selected'=>1), "positive")), array(vk_text_button("Керил", array('command'=>'fun','meme_id'=>3,'selected'=>1), "negative"))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '🌚', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				return 'ok';
				break;

				case 'влад':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id368814064 (Далбааааааааёёёёёёёёёёёёёёб!)"));
				return 'ok';

				case 'юля':
				vk_call('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id477530202 (Доскаааааааааааааааааааааааа)"));
				return 'ok';

				case 'олды тут?':
				$msg = ", ТУТ!";
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
				return 'ok';
				break;

				case 'кб':
				$msg = "СОСАТЬ!";
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				return 'ok';
				break;

				case 'некита':
				$msg = "@id438333657 (Корееееееееееееееец)";
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				return 'ok';
				break;

				case 'егор':
				$msg = " - задрот.";
				vk_execute($botModule->makeExeAppealByID(458598210)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
				return 'ok';
				break;

				case 'данил':
				$msg = "@midas325 (бан)";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				return 'ok';

				case 'вова':
				$msg = "@e_t_e_r_n_a_l_28 (Муууууууууудаааааааааааааааааак)";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				return 'ok';

				case 'ксюша':
				$msg = "@id332831736 (ШЛЮША)";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','attachment':'photo-161901831_456239032'});");
				return 'ok';

				case 'дрочить':
				$keyboard = vk_keyboard(true, array(array(vk_text_button("Дрочить", array('command'=>'fun','meme_id'=>2,'act'=>1,'napkin'=>0), "primary")), array(vk_text_button("Взять салфетку", array('command'=>'fun','meme_id'=>2,'act'=>2), "positive"))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '🌚', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				return 'ok';
				break;

				case 'саня':
				$msg = "@id244486535 (Саша), это для тебя💜💜💜";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','attachment':'audio219011658_456239231'});");
				return 'ok';
				break;

				case 'аля':
				$a1 = array(
					vk_text_button("Погладить", array('command'=>'fun','meme_id'=>4,'act'=>1), "primary"),
					vk_text_button("Покормить", array('command'=>'fun','meme_id'=>4,'act'=>2), "primary")
				);
				$a2 = array(
					vk_text_button("Поиграть", array('command'=>'fun','meme_id'=>4,'act'=>3), "primary"),
					vk_text_button("Расчесать", array('command'=>'fun','meme_id'=>4,'act'=>4), "primary")
				);
				$a3 = array(
					vk_text_button("Погулять", array('command'=>'fun','meme_id'=>4,'act'=>5), "positive"),
					vk_text_button("Купить одежду", array('command'=>'fun','meme_id'=>4,'act'=>6), "positive")
				);
				$a4 = array(
					vk_text_button("Убрать лоток", array('command'=>'fun','meme_id'=>4,'act'=>8), "positive"),
					vk_text_button("Стерилизовать", array('command'=>'fun','meme_id'=>4,'act'=>7), "negative")
				);
				$keyboard = vk_keyboard(true, array($a1, $a2, $a3, $a4));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => 'Алечка - котеночек😺! Поухаживайте за ней!😻😻😻', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				break;

				case 'дрочить на чулки':
				$keyboard = vk_keyboard(false, array(array(vk_text_button("Чулки", array('command'=>'fun','meme_id'=>6), "positive")), array(vk_text_button("Убрать клавиатуру", array('command'=>'fun','meme_id'=>-1), "negative"))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => 'Режим "Дрочить на чулки" активирован. Чтобы закрыть клавиатуру, напишите Убрать клавиатуру.', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				break;

				case 'дрочить на карину':
				$keyboard = vk_keyboard(false, array(array(vk_text_button("Карина", array('command'=>'fun','meme_id'=>7), "positive")), array(vk_text_button("Убрать клавиатуру", array('command'=>'fun','meme_id'=>-1), "negative"))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => 'Режим "Дрочить на Карину" активирован. Чтобы закрыть клавиатуру, напишите Убрать клавиатуру.', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				break;

				case 'дрочить на амину':
				$keyboard = vk_keyboard(false, array(array(vk_text_button("Амина", array('command'=>'fun','meme_id'=>8), "positive")), array(vk_text_button("Убрать клавиатуру", array('command'=>'fun','meme_id'=>-1), "negative"))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => 'Режим "Дрочить на Амину" активирован. Чтобы закрыть клавиатуру, напишите Убрать клавиатуру.', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				break;

				case 'оффники':
				$keyboard = vk_keyboard(true, array(array(vk_text_button("Убрать оффников", array('command'=>'fun','meme_id'=>9), 'positive'))));
				$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '🖕🏻', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
				vk_execute("API.messages.send({$json_request});");
				break;

				case 'пашел нахуй':
				$botModule->sendSilentMessage($data->object->peer_id, "Сам иди нахуй!");
				break;

				case 'лохи беседы':
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}}).profiles;
					var msg = appeal+', список лохов беседы:';

					var i = 0; while(i < members.length){
						if(members[i].id > 300000000){
							msg = msg + '\\n✅@id'+members[i].id+' ('+members[i].first_name+' '+members[i].last_name+') - id'+members[i].id;
						}
						i = i + 1;
					}

					return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
					");
				break;

				case 'дата регистрации':
				$user_info = simplexml_load_file("https://vk.com/foaf.php?id={$data->object->from_id}");
				$created_date_unformed = $user_info->xpath('//ya:created/@dc:date')[0];
				unset($user_info);
				$formating = explode("T", $created_date_unformed);
				$date = $formating[0];
				$time = $formating[1];
				$formating = explode("-", $date);
				$date = "{$formating[2]}.{$formating[1]}.{$formating[0]}";
				$msg = ", Ваша страница была зарегистрирована {$date}.";
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
				break;

				case 'попить чай':
				$permissionSystem = new \PermissionSystem($db);
				if($permissionSystem->checkUserPermission($data->object->from_id, 'drink_tea')){
					vk_execute("var user=API.users.get({'user_id':{$data->object->from_id},'fields':'screen_name'})[0];var msg='@'+user.screen_name+' ('+user.first_name+' '+user.last_name+') попил чай.☕';return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
				}
				else
					$botModule->sendSilentMessage($data->object->peer_id, ", У вас нет права пить чай!", $data->object->from_id);
				break;
			}

			return true;
		}

		public static function payloadHandler($data, &$db){
			if(property_exists($data->object, 'payload')){
				$payload = json_decode($data->object->payload);
				if($payload->command == "fun"){
					$botModule = new \BotModule($db);
					switch ($payload->meme_id) {
						case -1:
						$keyboard = vk_keyboard(false, array());
						$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => 'Клавиатура убрана.', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
						vk_execute("return API.messages.send({$json_request});");
						break;

						case 1:
						$msg = ", Ты только что нажал'+a_char+' самую @id317258850 (охуенную) кнопку в мире.❤🖤💙💚💛💖";
						vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
							var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex'})[0];
							var a_char = '';
							if(user.sex == 1){
								a_char = 'а';
							}
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','attachment':'photo-161901831_456239030'});");
						break;

						case 2:
						if($payload->act == 1){
							$random_number = mt_rand(0, 65535);
							vk_execute("
								var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'first_name_gen,last_name_gen,sex'});
								var members_count = members.profiles.length;
								var rand_index = {$random_number} % members_count;
								var napkin = {$payload->napkin};

								var from_id = {$data->object->from_id};
								var from_id_index = -1;
								var i = 0; while (i < members.items.length){
								if(members.profiles[i].id == from_id){
								from_id_index = i;
								i = members.profiles.length;
								}
								i = i + 1;
								};

								var a_char = '';
								if(members.profiles[from_id_index].sex == 1){
									a_char = 'а';
								}

								var msg = '';

								if(napkin == 0){
									msg = '@id'+from_id+' ('+members.profiles[from_id_index].first_name+' '+members.profiles[from_id_index].last_name+') подрочил'+a_char+' и был'+a_char+' удовлетворен'+a_char+' настолько, что аж кончил'+a_char+' на лицо @id'+members.profiles[rand_index].id+' ('+members.profiles[rand_index].first_name_gen+' '+members.profiles[rand_index].last_name_gen+').';
								} else {
									msg = '@id'+from_id+' ('+members.profiles[from_id_index].first_name+' '+members.profiles[from_id_index].last_name+') подрочил'+a_char+' и был'+a_char+' удовлетворен'+a_char+' настолько, что аж кончил'+a_char+' на салфетку.';
								}

								return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
						} else {
							$keyboard = vk_keyboard(true, array(array(vk_text_button("Дрочить", array('command'=>'fun','meme_id'=>2,'act'=>1,'napkin'=>1), "primary"))));
							$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '%appeal%, на, держи салфеточку!', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
							$json_request = vk_parse_var($json_request, "appeal");
							vk_execute($botModule->makeExeAppealByID($data->object->from_id)."API.messages.send({$json_request});");
						}
						break;

						case 3:
						if($payload->selected == 1){
							vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
							var peer_id = {$data->object->peer_id};
							var from_id = {$data->object->from_id};
							var msg = ', Кирилл? Ну и хорошо!';
							API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});
							return 0;
							");
						} else {
							vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
							var peer_id = {$data->object->peer_id};
							var from_id = {$data->object->from_id};
							var msg = ', Что? Керил? Бан, нахой!';
							API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});
							API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':from_id});
							return 0;
							");
						}
						break;

						case 4:
						$id = "@id243123791";

						$base = array(
							", вы погладили {$id} (Алечку😺). Ей понравилось.😻😻😻",
							", вы покормили {$id} (Алечку😺). Теперь она сытая и счастливая.😻😻😻",
							", вы поиграли с {$id} (Алечкой😺). Она счастливо мяукает!😸😸😸",
							", вы расчесали {$id} (Алечку😺). Теперь она еще больше красива!",
							", вы погуляли с {$id} (Алечкой😺). На улице, она встретила кота, возможно, она влюбилась в него.😻😻😻",
							", вы купили новый комбинизончик для {$id} (Алечки😺). Он очень удобный, ей нравится.😽😽😽",
							", {$id} (Алечка😺) разочарована в тебе. Она думала, ты ее любишь, а ты...🙀🙀🙀",
							", вы убрали говно {$id} (Алечки😺). Теперь в квартире не воняет кошачьим дерьмом.🤣🤣🤣"
						);

						$msg = $base[$payload->act-1];

						vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
							");
						break;

						case 6:
						fun_stockings($data, $db);
						break;

						case 7:
						fun_karina($data, $db);
						break;

						case 8:
						fun_amina($data, $db);
						break;

						case 9:
						$photos = array("photo219011658_457244124", "photo219011658_457244126", "photo219011658_457244128");
						$i = mt_rand(0, 65535) % count($photos);
						$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'attachment' => $photos[$i]), JSON_UNESCAPED_UNICODE);
						vk_execute("API.messages.send({$json_request});");
						break;

						case 10:
						$botModule->sendSilentMessage($data->object->peer_id, "@id477530202 (Самая офигенная!)", null, array('attachment' => 'photo477530202_457244949,photo219011658_457244383'));
						break;
					}
				}
			}
		}
	}

	function imgoingsleeping($data, $db, $text){
		$chatModes = new \ChatModes($db);
		if(!$chatModes->getModeValue("legacy_enabled"))
			return;

		if(mb_substr_count($text, "я спать") > 0){
			$arr = array(
				array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '😋Спокойной ночи, %user_id% (дорогая)❤.'),
				array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '😴Сладких снов, %user_id% (красотка)😍.'),
				array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '☺Желаю тебе счастливых снов! Ты %user_id% (лучшая)😍, люблю💞.'),
				array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '☺Спокойной ночи, %user_id% (моя любимая девочка)😘.')
			);
			$botModule = new \BotModule($db);
			$curr = $arr[mt_rand(0, 65535) % count($arr)];
			$curr_json = json_encode($curr, JSON_UNESCAPED_UNICODE);
			$curr_json = vk_parse_vars($curr_json, array("appeal", "user_id"));
			vk_execute("
				var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex'})[0];
				var user_id = '@id'+user.id;
				var curr = {$curr_json};
				var msg = '';
				if(user.sex == 1){
					msg = curr.f;
				}
				else{
					msg = curr.m;
				}
				API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
				");
		}
	}
}

?>