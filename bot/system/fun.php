<?php

// Инициалихация команд
function fun_initcmd($event){
	$event->addTextMessageCommand("!выбери", 'fun_choose');
	$event->addTextMessageCommand("!сколько", 'fun_howmuch');
	$event->addTextMessageCommand("!инфа", "fun_info");
	$event->addTextMessageCommand("!бузова", 'fun_buzova');
	$event->addTextMessageCommand("!карина", 'fun_karina_cmd');
	$event->addTextMessageCommand("!амина", 'fun_amina_cmd');
	$event->addTextMessageCommand("!memes", 'fun_memes_control_panel');
	$event->addTextMessageCommand("!чулки", 'fun_stockings_cmd');
	$event->addTextMessageCommand("!бутылочка", 'fun_bottle');
	$event->addTextMessageCommand("!tts", 'fun_tts');
	$event->addTextMessageCommand("!say", "fun_say");
	$event->addTextMessageCommand("!брак", "fun_marriage");
	$event->addTextMessageCommand("!браки", "fun_show_marriage_list");
	$event->addTextMessageCommand("!shrug", 'fun_shrug');
	$event->addTextMessageCommand("!tableflip", 'fun_tableflip');
	$event->addTextMessageCommand("!unflip", 'fun_unflip');

	// Инициализация команд [кто/кого/кому]
	$event->addTextMessageCommand("!кто", 'fun_whois_nom');
	$event->addTextMessageCommand("!кого", 'fun_whois_gen');
	$event->addTextMessageCommand("!кому", 'fun_whois_dat');
}

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

function fun_memes_control_panel($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;
	$event = $finput->event;

	$botModule = new BotModule($db);

	$chatModes = new ChatModes($db);
	if(!$chatModes->getModeValue("allow_memes")){ // Проверка режима
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Панель управления мемами недоступна, так как в беседе отключен Режим allow_memes.", $data->object->from_id);
		return;
	}

	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";
	if($command == "add"){
		$forbidden_names = array("%__appeal__%", "%__ownername__%", "*all", "%appeal%"); // Массив запрещенных наименований мемов
		$meme_name = mb_strtolower(mb_substr($data->object->text, 11));
		if($meme_name == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Не найдено название!", $data->object->from_id);
			return;
		}
		for($i = 0; $i < count($forbidden_names); $i++){ // Массив проверки имя на запрет
			if($meme_name == $forbidden_names[$i]){
				$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Данное имя нельзя использовать!", $data->object->from_id);
				return;
			}
		}
		if(mb_strlen($meme_name) > 15){
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Имя не может быть больше 8 знаков!", $data->object->from_id);
			return;
		}
		if($db->getValue(array("fun", "memes", $meme_name), false) !== false){
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Мем с таким именем уже существует!", $data->object->from_id);
			return;
		}

		if(SysMemes::isExists($meme_name)){ // Запрет на использование названий из СИСТЕМНЫХ мемов
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Данное имя нельзя использовать!", $data->object->from_id);
			return;
		}

		$event_command_list = $event->getMessageCommandList();
		for($i = 0; $i < count($event_command_list); $i++){ // Запрет на использование названий из Командной системы
			if($meme_name == $event_command_list[$i]){
				$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Данное имя нельзя использовать!", $data->object->from_id);
				return;
			}
		}

		if(count($data->object->attachments) == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Вложения не найдены!", $data->object->from_id);
			return;
		}
		$content_attach = "";

		if($data->object->attachments[0]->type == 'photo'){
			$photo_sizes = $data->object->attachments[0]->photo->sizes;
			$photo_url_index = 0;
			for($i = 0; $i < count($photo_sizes); $i++){
				if($photo_sizes[$i]->height > $photo_sizes[$photo_url_index]->height){
					$photo_url_index = $i;
				}
			}
			$photo_url = $photo_sizes[$photo_url_index]->url;
			$path = BOT_TMPDIR."/photo".mt_rand(0, 65535).".jpg";
			file_put_contents($path, file_get_contents($photo_url));
			$response =  json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
			$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
			unlink($path);
			$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
			$photo = json_decode(vk_execute("return API.photos.saveMessagesPhoto({$res_json});
				"))->response[0];
			$content_attach = "photo{$photo->owner_id}_{$photo->id}";
		}
		elseif($data->object->attachments[0]->type == 'audio'){
			$content_attach = "audio{$data->object->attachments[0]->audio->owner_id}_{$data->object->attachments[0]->audio->id}";
		}
		elseif($data->object->attachments[0]->type == 'video'){
			if(property_exists($data->object->attachments[0]->video, "is_private") && $data->object->attachments[0]->video->is_private == 1){
				$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Вложение является приватным!", $data->object->from_id);
				return;
			}
			else {
				$content_attach = "video{$data->object->attachments[0]->video->owner_id}_{$data->object->attachments[0]->video->id}";
			}
		}
		else {
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Тип вложения не поддерживается!", $data->object->from_id);
			return;
		}

		$meme = array(
			'owner_id' => $data->object->from_id,
			'content' => $content_attach,
			'date' => time()
		);
		$db->setValue(array("fun", "memes", $meme_name), $meme);
		$db->save();
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Мем сохранен!", $data->object->from_id);
	}
	elseif($command == "del"){
		$meme_name = mb_strtolower(mb_substr($data->object->text, 11));
		$memes = $db->getValue(array("fun", "memes"), array());
		if($meme_name == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", &#9940;Не найдено название!", $data->object->from_id);
			return;
		}
		if(!array_key_exists($meme_name, $memes) && $meme_name != "*all"){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔мема с именем \"{$meme_name}\" не существует.", $data->object->from_id);
			return;
		}

		if($meme_name == "*all"){
			$ranksys = new RankSystem($db);
			if(!$ranksys->checkRank($data->object->from_id, 0)){ // Проверка ранга (Владелец)
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не можете удалять мемы других пользователей.", $data->object->from_id);
				return;
			}

			$res = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ✅Все мемы в беседе были удалены!','disable_mentions':true});
				return 'ok';
				"))->response;
			$db->unsetValue(array("fun", "memes"));
			$db->save();
		} else {
			if($memes[$meme_name]["owner_id"] == $data->object->from_id){
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Мем \"{$meme_name}\" удален!", $data->object->from_id);
				$db->unsetValue(array("fun", "memes", $meme_name));
				$db->save();
			} else {
				$ranksys = new RankSystem($db);
				if(!$ranksys->checkRank($data->object->from_id, 1)){ // Проверка ранга (Администратор)
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не можете удалять мемы других пользователей.", $data->object->from_id);
					return;
				}

				$res = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ✅Мем \"{$meme_name}\" удален!','disable_mentions':true});
				return 'ok';
				"))->response;
				$db->unsetValue(array("fun", "memes", $meme_name));
				$db->save();
			}
		}
	}
	elseif($command == "list"){
		$meme_names = array();
		foreach ($db->getValue(array("fun", "memes"), array()) as $key => $val) {
    		$meme_names[] = $key;
		}
		if(count($meme_names) == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", в беседе нет мемов.", $data->object->from_id);
			return;
		}
		$meme_str_list = "";
		for($i = 0; $i < count($meme_names); $i++){
			if($meme_str_list == "")
				$meme_str_list = "[{$meme_names[$i]}]";
			else
				$meme_str_list = $meme_str_list . ", [{$meme_names[$i]}]";
		}
		$botModule->sendSilentMessage($data->object->peer_id, ", 📝список мемов в беседе:\n".$meme_str_list, $data->object->from_id);
	}
	elseif($command == "info"){
		$meme_name = mb_strtolower(mb_substr($data->object->text, 12));

		if($meme_name == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔введите имя мема.", $data->object->from_id);
			return;
		}

		$memes = $db->getValue(array("fun", "memes"), array());

		if(!is_null($memes[$meme_name])){
			$added_time = gmdate("d.m.Y H:i:s", $memes[$meme_name]["date"]+10800)." по МСК";
			$msg = "%__APPEAL__%, информация о меме:\n✏Имя: {$meme_name}\n🤵Владелец: %__OWNERNAME__%\n📅Добавлен: {$added_time}\n📂Содержимое: ⬇️⬇️⬇️";
			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'attachment' => $memes[$meme_name]["content"], 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("__OWNERNAME__", "__APPEAL__"));
			vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				var owner = API.users.get({'user_ids':[{$memes[$meme_name]["owner_id"]}]})[0];
				var __APPEAL__ = appeal; appeal = null;
				var __OWNERNAME__ = '@id{$memes[$meme_name]["owner_id"]} ('+owner.first_name+' '+owner.last_name+')';
				return API.messages.send({$request});
				");
		} else {
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔мема с именем \"{$meme_name}\" не существует.", $data->object->from_id);
		}
	}
	else {
		$commands = array(
			'!memes list - Список мемов беседы',
			'!memes add <name> <attachment> - Добавление мема',
			'!memes del <name> - Удаление мема',
			'!memes del *all - Удаление всех мемов из беседы',
			'!memes info <name> - Информация о меме'
		);
		$botModule->sendCommandListFromArray($data, ", ⛔используйте:", $commands);
	}
}

function fun_memes_handler($data, $db){
	$chatModes = new ChatModes($db);
	if(!$chatModes->getModeValue("allow_memes"))
		return;

	$meme_name = mb_strtolower($data->object->text);
	$meme = $db->getValue(array("fun", "memes", $meme_name), false);
	if($meme !== false){
		$botModule = new BotModule($db);
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%,", 'attachment' => $meme["content"], 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "appeal");
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			return API.messages.send({$request});
			");
	}
}

function fun_handler($data, &$db){
	$chatModes = new chatModes($db);

	$text = mb_strtolower($data->object->text);

	if(!SysMemes::handler($data, $text, $db))
		fun_memes_handler($data, $db);

	SysMemes::payloadHandler($data, $db);

	if(mb_substr_count($text, "я спать") > 0){
		$arr = array(
			array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '😋Спокойной ночи, %user_id% (дорогая)❤.'),
			array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '😴Сладких снов, %user_id% (красотка)😍.'),
			array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '☺Желаю тебе счастливых снов! Ты %user_id% (лучшая)😍, люблю💞.'),
			array('m' => 'Спокойной ночи, %user_id% (брат)😎.', 'f' => '☺Спокойной ночи, %user_id% (моя любимая девочка)😘.')
		);
		$botModule = new BotModule($db);
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

function fun_stockings_cmd($finput){
	fun_stockings($finput->data, $finput->db);
}

function fun_stockings($data, $db){ // Чулки
	$botModule = new BotModule($db);
	$messages_array = array("дрочи😈", "держи😛", "ух какая сосочка🔥", "что, уже кончил?💦🤣", "какие ножки👌🏻👈🏻");

	$random_number = mt_rand(0, 65535);
	$msg = $messages_array[$random_number % sizeof($messages_array)];
	$photo = json_decode(vk_userexecute("
		var random_number = {$random_number};
		var owner_id = -102853758; var album_id = 'wall';

		var a = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':0});
		var photos_count = a.count;
		var photos_offset = (random_number % photos_count);
		var photo = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':1,'offset':photos_offset });
		return photo;
		"));
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		return API.messages.send({'peer_id':{$data->object->peer_id},'attachment':'photo{$photo->response->items[0]->owner_id}_{$photo->response->items[0]->id}','message':appeal+', {$msg}','disable_mentions':true});
		");
}

function fun_buzova($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$random_number = mt_rand(0, 65535);
	$photo = json_decode(vk_userexecute("
		var random_number = {$random_number};
		var owner_id = 32707600; var album_id = 'wall';

		var a = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':0});
		var photos_count = a.count;
		var photos_offset = (random_number % photos_count);
		var photo = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':1,'offset':photos_offset });
		return photo;
		"));
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		return API.messages.send({'peer_id':{$data->object->peer_id},'attachment':'photo{$photo->response->items[0]->owner_id}_{$photo->response->items[0]->id}'});
		");
}

function fun_karina_cmd($finput){
	fun_karina($finput->data, $finput->db);
}

function fun_karina($data, $db){
	$botModule = new BotModule($db);

	$random_number = mt_rand(0, 65535);
	$photo = json_decode(vk_userexecute("
		var random_number = {$random_number};
		var owner_id = 153162173; var album_id = 'wall';

		var a = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':0});
		var photos_count = a.count;
		var photos_offset = (random_number % photos_count);
		var photo = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':1,'offset':photos_offset });
		return photo;
		"));
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		return API.messages.send({'peer_id':{$data->object->peer_id},'attachment':'photo{$photo->response->items[0]->owner_id}_{$photo->response->items[0]->id}'});
		");
}

function fun_amina_cmd($finput){
	fun_amina($finput->data, $finput->db);
}

function fun_amina($data, $db){
	$botModule = new BotModule($db);
	$random_number = mt_rand(0, 65535);
	$photo = json_decode(vk_userexecute("
		var random_number = {$random_number};
		var owner_id = 363887574; var album_id = 'wall';

		var a = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':0});
		var photos_count = a.count;
		var photos_offset = (random_number % photos_count);
		var photo = API.photos.get({'owner_id':owner_id,'album_id':album_id,'count':1,'offset':photos_offset });
		return photo;
		"));
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		return API.messages.send({'peer_id':{$data->object->peer_id},'attachment':'photo{$photo->response->items[0]->owner_id}_{$photo->response->items[0]->id}'});
		");
}

function fun_like_avatar($data, $db){
	$botModule = new BotModule($db);
	$response = json_decode(vk_userexecute("
		var amina = API.users.get()[0];
		var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'photo_id'})[0];
		var owner_id = '{$data->object->from_id}';
		var id = user.photo_id.substr(owner_id.length+1, user.photo_id.length);
		if(API.likes.isLiked({'user_id':amina.id,'type':'photo','owner_id':owner_id,'item_id':id}).liked == 0){
			var like = API.likes.add({'type':'photo','owner_id':owner_id,'item_id':id});
			return {'result':1,'likes':like.likes};
		}
		else
		{
			return {'result':0};
		}
		"))->response;
	if($response->result == 1)
		$botModule->sendSilentMessage($data->object->peer_id, ", Теперь у тебя {$response->likes} ❤.", $data->object->from_id);
	else
		$botModule->sendSilentMessage($data->object->peer_id, ", Лайк уже стоит.", $data->object->from_id);
}

function fun_like_wallpost($data, $db){
	$botModule = new BotModule($db);
	if($data->object->attachments[0]->type == "wall"){
		$wall_post = $data->object->attachments[0]->wall;
		$response = json_decode(vk_userexecute("
		var amina = API.users.get()[0];
		if(API.likes.isLiked({'user_id':amina.id,'type':'post','owner_id':{$wall_post->to_id},'item_id':{$wall_post->id}}).liked == 0){
			var like = API.likes.add({'type':'post','owner_id':{$wall_post->to_id},'item_id':{$wall_post->id}});
			return {'result':1,'likes':like.likes};
		}
		else
		{
			return {'result':0};
		}
		"))->response;
	if($response->result == 1)
		$botModule->sendSilentMessage($data->object->peer_id, ", Теперь у тебя {$response->likes} ❤.", $data->object->from_id);
	else
		$botModule->sendSilentMessage($data->object->peer_id, ", Лайк уже стоит.", $data->object->from_id);
	}
	else{
		$botModule->sendSilentMessage($data->object->peer_id, ", Не могу найти пост.", $data->object->from_id);
	}
}

function fun_choose($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$options = array();
	$new_str = "";
	for($i = 1; $i <= sizeof($words); $i++){
		$isContinue = true;
		if($i == sizeof($words) || mb_strtolower($words[$i]) == "или"){
			$options[] = $new_str;
			$new_str = "";
			$isContinue = false;
		}
		if($isContinue){
			if($new_str == ""){
				$new_str = $words[$i];
			} else {
				$new_str = $new_str . " " . $words[$i];
			}
		}
	}

	if(sizeof($options) < 2){
		$msg = ", что-то мало вариантов.🤔 Я так не могу.😡";
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});");
		return;
	}

	$random_number = mt_rand(0, 65535) % sizeof($options);
	$print_text = $options[$random_number];
	$msg = ", 🤔я выбираю: " . $print_text;
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});");
}

function fun_howmuch($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$rnd = mt_rand(0, 100);

	if(array_key_exists(1, $words))
		$unitname = $words[1];
	else
		$unitname = "";
	$add = mb_substr($data->object->text, 9+mb_strlen($unitname));

	if($unitname == "" || $add == ""){
		$botModule->sendCommandListFromArray($data, ", используйте:", array("Сколько <ед. измерения> <дополнение>"));
		return;
	}

	$add = mb_eregi_replace("\.", "", $add); // Избавляемся от точек.

	// Изменение местоимений
	/*$add = mb_eregi_replace("я", "ты", $add);
	$add = mb_eregi_replace("мой", "твой", $add);
	$add = mb_eregi_replace("мне", "тебе", $add);
	$add = mb_eregi_replace("моего", "твоего", $add);
	$add = mb_eregi_replace("моему", "твоему", $add);
	$add = mb_eregi_replace("моего", "моего", $add);
	$add = mb_eregi_replace("моём", "твоём", $add);
	$add = mb_eregi_replace("мы", "вы", $add);
	$add = mb_eregi_replace("нам", "вам", $add);
	$add = mb_eregi_replace("наш", "ваш", $add);
	$add = mb_eregi_replace("нашего", "вашего", $add);
	$add = mb_eregi_replace("нашему", "вашему", $add);
	$add = mb_eregi_replace("наш", "ваш", $add);
	$add = mb_eregi_replace("нашим", "вашим", $add);
	$add = mb_eregi_replace("нашем", "вашем", $add);*/

	$add = mb_strtoupper(mb_substr($add, 0, 1)).mb_strtolower(mb_substr($add, 1)); // Делает первую букву верхнего регистра, а остальные - нижнего

	$botModule->sendSilentMessage($data->object->peer_id, ", {$add} {$rnd} {$unitname}.", $data->object->from_id);
}

function fun_bottle($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);
	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";
	if($command == "сесть"){
		$random_number = mt_rand(0, 65535);
		vk_execute("
		var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'first_name_gen,last_name_gen,sex'});
		var members_count = members.profiles.length;
		var rand_index = {$random_number} % members_count;

		var msg = 'Упс! @id'+members.profiles[rand_index].id+' ('+members.profiles[rand_index].first_name+' '+members.profiles[rand_index].last_name+') сел на бутылку.🍾';

		if(members.profiles[rand_index].sex == 1){
			msg = 'Упс! @id'+members.profiles[rand_index].id+' ('+members.profiles[rand_index].first_name+' '+members.profiles[rand_index].last_name+') села на бутылку.🍾';
		}

		return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
	}
	elseif($command == "пара"){
		$random_number1 = mt_rand(0, 65535);
		$random_number2 = mt_rand(0, 65535);
		vk_execute("
		var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'first_name_gen,last_name_gen,sex'});
		var members_count = members.profiles.length;
		var rand_index1 = {$random_number1} % members_count;
		var rand_index2 = {$random_number2} % members_count;

		var rand_user1 = members.profiles[rand_index1];
		var rand_user2 = members.profiles[rand_index2];

		var msg = '@id'+rand_user1.id+' ('+rand_user1.first_name+' '+rand_user1.last_name+') и @id'+rand_user2.id+' ('+rand_user2.first_name+' '+rand_user2.last_name+') - прекрасная пара.😍';

		return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
	}
	else{
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'Бутылочка сесть - Садит на бутылку случайного человека',
			'Бутылочка пара - Выводит идеальную пару беседы'
		));
	}
}

function fun_whois_nom($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$text = mb_substr($data->object->text, 5);
	if($text == ""){
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'!Кто <текст>'
		));
		return;
	}
	$text = mb_eregi_replace("\n", " ", $text); // Убираем символ новой строки

	$random_number = mt_rand(0, 65535);

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var from_id = {$data->object->from_id};
		var random_number = {$random_number};
		var members = API.messages.getConversationMembers({'peer_id':peer_id});
		var member = members.profiles[random_number % members.profiles.length];
		var msg = appeal+', 🤔Я думаю это @id'+ member.id + ' ('+member.first_name+' '+member.last_name+') - {$text}.';
		API.messages.send({'peer_id':peer_id,'message':msg});
	");
}

function fun_whois_gen($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$text = mb_substr($data->object->text, 6);
	if($text == ""){
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'!Кого <текст>'
		));
		return;
	}
	$text = mb_eregi_replace("\n", " ", $text); // Убираем символ новой строки

	$random_number = mt_rand(0, 65535);

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var from_id = {$data->object->from_id};
		var random_number = {$random_number};
		var members = API.messages.getConversationMembers({'peer_id':peer_id,'fields':'first_name_gen,last_name_gen'});
		var member = members.profiles[random_number % members.profiles.length];
		var msg = appeal+', 🤔Я думаю это @id'+ member.id + ' ('+member.first_name_gen+' '+member.last_name_gen+') - {$text}.';
		API.messages.send({'peer_id':peer_id,'message':msg});
	");
}

function fun_whois_dat($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$text = mb_substr($data->object->text, 6);
	if($text == ""){
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'!Кому <текст>'
		));
		return;
	}
	$text = mb_eregi_replace("\n", " ", $text); // Убираем символ новой строки

	$random_number = mt_rand(0, 65535);

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var from_id = {$data->object->from_id};
		var random_number = {$random_number};
		var members = API.messages.getConversationMembers({'peer_id':peer_id,'fields':'first_name_dat,last_name_dat'});
		var member = members.profiles[random_number % members.profiles.length];
		var msg = appeal+', 🤔Я думаю это @id'+ member.id + ' ('+member.first_name_dat+' '+member.last_name_dat+') - {$text}.';
		API.messages.send({'peer_id':peer_id,'message':msg});
	");
}

function fun_tts($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$message = mb_substr($data->object->text, 4);
	$botModule = new BotModule($db);

	if($message == ""){
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔используйте \"!tts <текст>\".", $data->object->from_id);
		return;
	}

	$query = array(
		'key' => bot_getconfig("VOICERSS_KEY"),
		'hl' => 'ru-ru',
		'f' => '48khz_16bit_stereo',
		'src' => $message,
		'c' => 'OGG'
	);
	$options = array(
   		'http' => array(  
            'method'  => 'GET',
            'header'  => 'Content-type: application/x-www-form-urlencoded', 
            'content' => http_build_query($query)
        )  
	);
	$path = BOT_TMPDIR."/audio".mt_rand(0, 65535).".ogg";
	file_put_contents($path, file_get_contents('http://api.voicerss.org/?', false, stream_context_create($options)));
	$server = json_decode(vk_execute("return API.docs.getMessagesUploadServer({'peer_id':{$data->object->peer_id},'type':'audio_message'});"))->response->upload_url;
	$audio = json_decode(vk_uploadDocs(array('file' => new CURLFile($path)), $server));
	unlink($path);
	
	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var audio = API.docs.save({'file':'{$audio->file}'})[0];
		API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+',','attachment':'doc'+audio.owner_id+'_'+audio.id,'disable_mentions':true});
		");
}

function fun_shrug($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule();
	$botModule->sendSilentMessage($data->object->peer_id, "¯\_(ツ)_/¯");
}

function fun_tableflip($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule();
	$botModule->sendSilentMessage($data->object->peer_id, "(╯°□°）╯︵ ┻━┻");
}

function fun_unflip($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;
	
	$botModule = new BotModule();
	$botModule->sendSilentMessage($data->object->peer_id, "┬─┬ ノ( ゜-゜ノ)");
}

function fun_info($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$expression = mb_substr($data->object->text, 6);

	if($expression == ""){
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔используйте \"Инфа <выражение>\".", $data->object->from_id);
		return;
	}

	$rnd = mt_rand(0, 100);

	$botModule->sendSilentMessage($data->object->peer_id, ", 📐Инфа, что {$expression} — {$rnd}%.", $data->object->from_id);
}

function fun_say($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$params = mb_substr($data->object->text, 4);

	parse_str($params, $vars);

	$appeal_id = null;

	if(!array_key_exists("msg", $vars)){
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Param <msg> not found!", $data->object->from_id);
		return;
	}

	if(array_key_exists("appeal_id", $vars))
		$appeal_id = $vars["appeal_id"];

	$botModule->sendSilentMessage($data->object->peer_id, $vars["msg"], $appeal_id);
}

function fun_marriage($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$marriages_db = $db->getValue(array("fun", "marriages"), array(
		'user_info' => array(),
		'list' => array()
	));

	$member_id = 0;

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(array_key_exists(1, $words) && bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(array_key_exists(1, $words) && is_numeric($words[1])) {
		$member_id = intval($words[1]);
	} else {
		if(array_key_exists(1, $words))
			$word1 = mb_strtolower($words[1]);
		else
			$word1 = "";

		switch ($word1) {
			case 'да':
				if(array_key_exists("id{$data->object->from_id}", $marriages_db["user_info"]) && $marriages_db["user_info"]["id{$data->object->from_id}"]["type"] == 0){
					$partner_id = $marriages_db["user_info"]["id{$data->object->from_id}"]["partner_id"];
					if(array_key_exists("id{$partner_id}", $marriages_db["user_info"])){
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔@id{$partner_id} (Пользователь) уже находится в браке.", $data->object->from_id);
						unset($marriages_db["user_info"]["id{$data->object->from_id}"]);
						return;
					}
					$marriages_db["list"][] = array(
						'partner_1' => $partner_id,
						'partner_2' => $data->object->from_id,
						'start_time' => time(),
						'end_time' => 0,
						'terminated' => false
					);
					$marriage_id = count($marriages_db["list"]) - 1; // Получение ID брака
					$marriages_db["user_info"]["id{$partner_id}"] = array(
						'type' => 1,
						'marriage_id' => $marriage_id
					);
					$marriages_db["user_info"]["id{$data->object->from_id}"] = array(
						'type' => 1,
						'marriage_id' => $marriage_id
					);
					vk_execute("
						var users_info = API.users.get({'user_ids':[{$partner_id},{$data->object->from_id}]});
						var partner_1 = users_info[0];
						var partner_2 = users_info[1];
						var msg = '❤@id'+partner_1.id+' ('+partner_1.first_name+' '+partner_1.last_name+') и @id'+partner_2.id+' ('+partner_2.first_name+' '+partner_2.last_name+') теперь семья❤';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						");
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет приглашения о заключении брака.", $data->object->from_id);
				}
				break;

			case 'нет':
				if(array_key_exists("id{$data->object->from_id}", $marriages_db["user_info"]) && $marriages_db["user_info"]["id{$data->object->from_id}"]["type"] == 0){
					$partner_id = $marriages_db["user_info"]["id{$data->object->from_id}"]["partner_id"];
					unset($marriages_db["user_info"]["id{$data->object->from_id}"]);
					vk_execute("
						var users_info = API.users.get({'user_ids':[{$partner_id},{$data->object->from_id}],'fields':'sex,first_name_ins,last_name_ins'});
						var partner_1 = users_info[0];
						var partner_2 = users_info[1];
						var sex_word = 'захотела';
						if(partner_1.sex == 1){ sex_word = 'захотел'; }
						var msg = '@id'+partner_2.id+' ('+partner_2.first_name+' '+partner_2.last_name+') не '+sex_word+' вступать в брак с @id'+partner_1.id+' ('+partner_1.first_name_ins+' '+partner_1.last_name_ins+').';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						");
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет приглашения о заключении брака.", $data->object->from_id);
				}
				break;

			case 'развод':
				if(array_key_exists("id{$data->object->from_id}", $marriages_db["user_info"]) && $marriages_db["user_info"]["id{$data->object->from_id}"]["type"] == 1){
					$marriage_info = &$marriages_db["list"][$marriages_db["user_info"]["id{$data->object->from_id}"]["marriage_id"]];
					$marriage_info["terminated"] = true;
					$marriage_info["end_time"] = time();
					unset($marriages_db["user_info"]["id{$marriage_info["partner_1"]}"]);
					unset($marriages_db["user_info"]["id{$marriage_info["partner_2"]}"]);
					vk_execute("
						var users_info = API.users.get({'user_ids':[{$marriage_info["partner_1"]},{$marriage_info["partner_2"]}]});
						var partner_1 = users_info[0];
						var partner_2 = users_info[1];
						var msg = '💔@id'+partner_1.id+' ('+partner_1.first_name+' '+partner_1.last_name+') и @id'+partner_2.id+' ('+partner_2.first_name+' '+partner_2.last_name+') больше не семья💔';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						");
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не состоите в браке.", $data->object->from_id);
				}
				break;

			case 'помощь':
				$botModule->sendCommandListFromArray($data, ", используйте:", array(
					'Брак - Информация о текущем браке',
					'Брак <пользователь> - Отправление запроса о заключении в брака',
					'Брак да - Одобрение запроса',
					'Брак нет - Отклонение запроса',
					'Брак развод - Развод текущего брака',
					'Брак помощь - Помощь в системе браков'
				));
				break;
			
			default:
				if(array_key_exists("id{$data->object->from_id}", $marriages_db["user_info"]) && $marriages_db["user_info"]["id{$data->object->from_id}"]["type"] == 1){
					$marriage_info = $marriages_db["list"][$marriages_db["user_info"]["id{$data->object->from_id}"]["marriage_id"]];
					vk_execute("
						var users_info = API.users.get({'user_ids':[{$marriage_info["partner_1"]},{$marriage_info["partner_2"]}],'fields':'first_name_ins,last_name_ins'});
						var partner_1 = users_info[0];
						var partner_2 = users_info[1];
						var msg = '❤@id'+partner_1.id+' ('+partner_1.first_name+' '+partner_1.last_name+') находится в счастливом браке с @id'+partner_2.id+' ('+partner_2.first_name_ins+' '+partner_2.last_name_ins+')❤';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
						");
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не состоите в браке.", $data->object->from_id);
				}
				break;
		}
		$db->setValue(array("fun", "marriages"), $marriages_db);
		$db->save();
		return;
	}


	if(!array_key_exists("id{$member_id}", $marriages_db["user_info"])){
		if(array_key_exists("id{$data->object->from_id}", $marriages_db["user_info"])){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы уже состоите в браке или получили приглашение.", $data->object->from_id);
			return;
		}
		$res = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			var member = API.users.get({'user_ids':[{$member_id}],'fields':'first_name_dat,last_name_dat'})[0];
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var member_id = {$member_id};
			if(member_id == {$data->object->from_id}){
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ⛔Нельзя зкалючить брак с самим собой.','disable_mentions':true});
				return false;
			}

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == member_id){
					isContinue = true;
					i = members.profiles.length;
				}
				i = i + 1;
			}
			if(!isContinue){
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗Указанного человека нет в беседе!','disable_mentions':true});
				return false;
			}
			else{
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ✅Приглашение о заключении брака отправлено @id{$member_id} ('+member.first_name_dat.substr(0, 2)+'. '+member.last_name_dat+').'});
				return true;
			}
			"))->response;
		if($res){
			$marriages_db["user_info"]["id{$member_id}"] = array(
				'type' => 0,
				'partner_id' => $data->object->from_id
			);
			$db->setValue(array("fun", "marriages"), $marriages_db);
			$db->save();
		}
	}
	else{
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔@id{$member_id} (Пользователь) уже состоит в браке или получил приглашение.", $data->object->from_id);
	}
}

function fun_show_marriage_list($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = $finput->db;

	$marriages_db = $db->getValue(array("fun", "marriages"), array(
		'user_info' => array(),
		'list' => array()
	));

	$botModule = new BotModule($db);

	$date = time(); // Переменная времени

	if(array_key_exists(1, $words) && !is_numeric($words[1]))
		$word = mb_strtolower($words[1]);
	else
		$word = "";


	if($word == "история"){
		$list = $marriages_db["list"];

		if(count($list) == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", в беседе нет браков!", $data->object->from_id);
			return;
		}

		if(array_key_exists(2, $words) && is_numeric($words[2]))
			$list_number_from_word = intval($words[2]);
		else
			$list_number_from_word = 1;

		/////////////////////////////////////////////////////
		////////////////////////////////////////////////////
		$list_in = &$list; // Входной список
		$list_out = array(); // Выходной список

		$list_number = $list_number_from_word; // Номер текущего списка
		$list_size = 10; // Размер списка
		////////////////////////////////////////////////////
		if(count($list_in) % $list_size == 0)
			$list_max_number = intdiv(count($list_in), $list_size);
		else
			$list_max_number = intdiv(count($list_in), $list_size)+1;
		$list_min_index = ($list_size*$list_number)-$list_size;
		if($list_size*$list_number >= count($list_in))	
			$list_max_index = count($list_in)-1;
		else
			$list_max_index = $list_size*$list_number-1;
		if($list_number <= $list_max_number && $list_number > 0){
			// Обработчик списка
			for($i = $list_min_index; $i <= $list_max_index; $i++){
				$list_out[] = $list_in[$i];
			}
		}
		else{
			// Сообщение об ошибке
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
			return;
		}
		////////////////////////////////////////////////////
		////////////////////////////////////////////////////

		for($i = 0; $i < count($list_out); $i++){
			if($list_out[$i]["terminated"]){
				$days = (($list_out[$i]["end_time"] - $list_out[$i]["start_time"]) - ($list_out[$i]["end_time"] - $list_out[$i]["start_time"]) % 86400) / 86400;
				$str_info = gmdate("d.m.Y", $list_out[$i]["start_time"]+10800)." - ".gmdate("d.m.Y | {$days} д.", $list_out[$i]["end_time"]+10800);
				$list_out[$i]["str_info"] = $str_info;
				unset($list_out[$i]["start_time"]);
				unset($list_out[$i]["end_time"]);
				unset($list_out[$i]["terminated"]);
			}
			else{
				$days = (($date - $list_out[$i]["start_time"]) - ($date - $list_out[$i]["start_time"]) % 86400) / 86400;
				$str_info = gmdate("с d.m.Y | {$days} д.", $list_out[$i]["start_time"]+10800);
				$list_out[$i]["str_info"] = $str_info;
				unset($list_out[$i]["start_time"]);
				unset($list_out[$i]["end_time"]);
				unset($list_out[$i]["terminated"]);
			}
		}

		$marriages_json = json_encode($list_out, JSON_UNESCAPED_UNICODE);

		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			var marriages = {$marriages_json};
			var current_date = {$date};
			var partner_1_info = API.users.get({'user_ids':marriages@.partner_1});
			var partner_2_info = API.users.get({'user_ids':marriages@.partner_2});
			var msg = appeal+', история браков беседы [$list_number/{$list_max_number}]:';
			var i = 0; while(i < marriages.length){
				var partner_1; var partner_2;
				var j = 0; while(j < partner_1_info.length){
					if(partner_1_info[j].id == marriages[i].partner_1){
						partner_1 = partner_1_info[j];
						j = partner_1_info.length;
					}
					j = j + 1;
				}
				var j = 0; while(j < partner_2_info.length){
					if(partner_2_info[j].id == marriages[i].partner_2){
						partner_2 = partner_2_info[j];
						j = partner_2_info.length;
					}
					j = j + 1;
				}
					msg = msg + '\\n✅@id'+marriages[i].partner_1+' ('+partner_1.first_name.substr(0, 2)+'. '+partner_1.last_name+') и @id'+marriages[i].partner_2+' ('+partner_2.first_name.substr(0, 2)+'. '+partner_2.last_name+') ('+marriages[i].str_info+')';
				i = i + 1;
			}
			API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
			");
	}
	elseif($word == ""){
		$list = array();
		for($i = 0; $i < count($marriages_db["list"]); $i++){
			if(!$marriages_db["list"][$i]["terminated"]){
				$list[] = $marriages_db["list"][$i];
			}
		}

		if(count($list) == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", в беседе нет браков!", $data->object->from_id);
			return;
		}

		if(array_key_exists(1, $words) && is_numeric($words[1]))
			$list_number_from_word = intval($words[1]);
		else
			$list_number_from_word = 1;

		/////////////////////////////////////////////////////
		////////////////////////////////////////////////////
		$list_in = &$list; // Входной список
		$list_out = array(); // Выходной список

		$list_number = $list_number_from_word; // Номер текущего списка
		$list_size = 10; // Размер списка
		////////////////////////////////////////////////////
		if(count($list_in) % $list_size == 0)
			$list_max_number = intdiv(count($list_in), $list_size);
		else
			$list_max_number = intdiv(count($list_in), $list_size)+1;
		$list_min_index = ($list_size*$list_number)-$list_size;
		if($list_size*$list_number >= count($list_in))	
			$list_max_index = count($list_in)-1;
		else
			$list_max_index = $list_size*$list_number-1;
		if($list_number <= $list_max_number && $list_number > 0){
			// Обработчик списка
			for($i = $list_min_index; $i <= $list_max_index; $i++){
				$list_out[] = $list_in[$i];
			}
		}
		else{
			// Сообщение об ошибке
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
			return;
		}
		////////////////////////////////////////////////////
		////////////////////////////////////////////////////

		$marriages_json = json_encode($list_out, JSON_UNESCAPED_UNICODE);

		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			var marriages = {$marriages_json};
			var current_date = {$date};
			var partner_1_info = API.users.get({'user_ids':marriages@.partner_1});
			var partner_2_info = API.users.get({'user_ids':marriages@.partner_2});
			var msg = appeal+', 🤵👰браки в беседе [$list_number/{$list_max_number}]:';
			var i = 0; while(i < marriages.length){
				var days = ((current_date - marriages[i].start_time) - (current_date - marriages[i].start_time) % 86400) / 86400;
				msg = msg + '\\n❤@id'+marriages[i].partner_1+' ('+partner_1_info[i].first_name.substr(0, 2)+'. '+partner_1_info[i].last_name+') и @id'+marriages[i].partner_2+' ('+partner_2_info[i].first_name.substr(0, 2)+'. '+partner_2_info[i].last_name+')❤ ('+days+' д.)';
				i = i + 1;
			}
			API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
			");
	}
	else{
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'Браки <список> - Браки в беседе',
			'Браки история <список> - Полная история браков беседы'
		));
	}
}

class SysMemes{
	const MEMES = array('мемы', 'f', 'topa', 'андрей', 'олег', 'ябловод', 'люба', /*'люба',*/ 'керил', 'юля', 'олды тут?', 'кб', 'некита', 'егор', 'ксюша', 'дрочить', 'саня', 'аля', 'дрочить на чулки', 'дрочить на карину', 'дрочить на амину', 'оффники', 'пашел нахуй', 'лохи беседы', 'дата регистрации', 'memory_get_usage', "memory_get_usage_real");

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
		$chatModes = new ChatModes($db);
		if(!$chatModes->getModeValue("allow_memes"))
			return;

		if(!self::isExists($meme_name))
			return false;
		$botModule = new BotModule($db);

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
			$botModule = new BotModule($db);
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
				$botModule = new BotModule($db);
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

?>