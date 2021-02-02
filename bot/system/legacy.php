<?php

// Модуля для размещения Legacy функций
namespace Legacy{
	function fun_db_get($db){
		if(array_key_exists('fun', $db))
			return $db["fun"];
		else
			return array();
	}

	function fun_db_set(&$db, $array){
		$db["fun"] = $array;
	}

	function fun_luba_menu($data, $fun, $msg, $botModule){
		$keyboard_array = array();
		if(!$fun["luba"]["isSleeping"]){
			$b1 = array(
				vk_text_button("Покормить", array('command'=>'fun','meme_id'=>5,'act'=>0), "primary"),
				vk_text_button("Дать попить", array('command'=>'fun','meme_id'=>5,'act'=>1), "primary"),

			);
			$b2 = array(
				vk_text_button("Поиграть", array('command'=>'fun','meme_id'=>5,'act'=>4), "primary"),
				vk_text_button("Погладить", array('command'=>'fun','meme_id'=>5,'act'=>5), "primary"),
			);
			$b3 = array(
				vk_text_button("Спать", array('command'=>'fun','meme_id'=>5,'act'=>2), "positive"),
				vk_text_button("Закрыть", array('command'=>'fun','meme_id'=>5,'act'=>3), "negative")
			);
			$keyboard_array = array($b1, $b2, $b3);
		} else {
			$b1 = array(vk_text_button("Разбудить", array('command'=>'fun','meme_id'=>5,'act'=>2), "positive"));
			$b2 = array(vk_text_button("Закрыть", array('command'=>'fun','meme_id'=>5,'act'=>3), "negative"));
			$keyboard_array = array($b1, $b2);
		}
		$keyboard = vk_keyboard(true, $keyboard_array);
		$hungry = $fun["luba"]["hungry"];
		$thirst = $fun["luba"]["thirst"];
		$happiness = $fun["luba"]["happiness"];
		$cheerfulness = $fun["luba"]["cheerfulness"];
		$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%{$msg}\n✅Сытость: {$hungry}/100\n✅Жажда: {$thirst}/100\n✅Счастье: {$happiness}/100\n✅Бодрость: {$cheerfulness}/100", 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
		$json_request = vk_parse_var($json_request, "appeal");
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."return API.messages.send({$json_request});");
	}

	class SysMemes{
		const MEMES = array('мемы', 'f', 'topa', 'андрей', 'олег', 'ябловод', 'люба', 'керил', 'юля', 'олды тут?', 'кб', 'некита', 'егор', 'ксюша', 'дрочить', 'саня', 'аля', 'дрочить на чулки', 'дрочить на карину', 'дрочить на амину', 'оффники', 'пашел нахуй', 'лохи беседы', 'дата регистрации', 'memory_get_usage', "memory_get_usage_real");

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

				case 'люба':
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

				case '-люба':
				$fun = fun_db_get($db);
				$botModule = new \BotModule($db);
				if(!array_key_exists("luba", $fun)){
					$fun["luba"]["hungry"] = 50;
					$fun["luba"]["thirst"] = 50;
					$fun["luba"]["happiness"] = 50;
					$fun["luba"]["isSleeping"] = false;
					$fun["luba"]["cheerfulness"] = 50;
					$fun["luba"]["last_db_update_date"] = time();
				}
				$hungry = $fun["luba"]["hungry"];
				$thirst = $fun["luba"]["thirst"];
				$happiness = $fun["luba"]["happiness"];
				$cheerfulness = $fun["luba"]["cheerfulness"];
				$msg = ", @id317258850 (Люба) - это котеночек😺. Ухаживайте за ней и делайте ее счастливой.";
				fun_luba_menu($data, $fun, $msg, $botModule);
				fun_db_set($db, $fun);
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

				case 'memory_get_usage':
				$botModule->sendSilentMessage($data->object->peer_id, ", Memory Used: ".memory_get_usage()." B.", $data->object->from_id);
				break;

				case 'memory_get_usage_real':
				$botModule->sendSilentMessage($data->object->peer_id, ", Memory Used: ".memory_get_usage(true)." B.", $data->object->from_id);
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

						case 5:
						$fun = fun_db_get($db);
						switch ($payload->act) {
							case 2:
							$msg = "";
							if($fun["luba"]["isSleeping"]){
								$msg = ", вы разбудили @id317258850 (Любу).😘";
							} else {
								$msg = ", вы уложили @id317258850 (Любу) спать.😴";
							}
							$fun["luba"]["isSleeping"] = !$fun["luba"]["isSleeping"];
							fun_luba_menu($data, $fun, $msg, $botModule);
							break;

							case 0:
							if($fun["luba"]["hungry"] <= 80){
								$fun["luba"]["hungry"] = 100;
								fun_luba_menu($data, $fun, ", вы покормили @id317258850 (Любу).😸", $botModule);
							} else {
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) не хочет кушать.🙄", $botModule);
							}
							break;

							case 1:
							if($fun["luba"]["thirst"] <= 80){
								$fun["luba"]["thirst"] = 100;
								fun_luba_menu($data, $fun, ", вы дали попить @id317258850 (Любе).😸", $botModule);
							} else {
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) не хочет пить.🙄", $botModule);
							}
							break;

							case 4:
							if($fun["luba"]["hungry"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет кушать.🥺 Покормите её!", $botModule);
								break;
							} elseif($fun["luba"]["thirst"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет пить.🥺 Помогите ей!", $botModule);
								break;
							} elseif($fun["luba"]["cheerfulness"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет спать. Уложите ее в кроватку.😴", $botModule);
								break;
							} elseif($fun["luba"]["happiness"] > 50){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) не хочет играть.🙄", $botModule);
								break;
							}
								$fun["luba"]["happiness"] += 50;
								$fun["luba"]["hungry"] -= 10;
								$fun["luba"]["thirst"] -= 10;
								$fun["luba"]["cheerfulness"] -= 15;
								fun_luba_menu($data, $fun, ", вы поиграли с @id317258850 (Любой).🤗", $botModule);
							break;

							case 5:
							if($fun["luba"]["hungry"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет кушать.🥺 Покормите её!", $botModule);
								break;
							} elseif($fun["luba"]["thirst"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет пить.🥺 Помогите ей!", $botModule);
								break;
							} elseif($fun["luba"]["cheerfulness"] < 20){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) хочет спать. Уложите ее в кроватку.😴", $botModule);
								break;
							} elseif($fun["luba"]["happiness"] > 80){
								fun_luba_menu($data, $fun, ", @id317258850 (Люба) не хочет, чтобы её гладили.🙄", $botModule);
								break;
							}
							$fun["luba"]["happiness"] += 20;
							fun_luba_menu($data, $fun, ", вы погладили @id317258850 (Любу).🤗", $botModule);
							break;
						}
						fun_db_set($db, $fun);
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