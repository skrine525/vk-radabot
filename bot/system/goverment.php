<?php

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Data

class SocOrderClass{ // Класс данных социальных строёв
	const TYPES = array('Капитализм', 'Социализм', 'Коммунизм', 'Фашизм');
	const ORDERS_DESC = array(
		"это капиталистическое федеративное государство с республиканской формой правления",
		"это социалистическая унитарная республика с демократической диктатурой народа",
		"это коммунистическое унитарное государство с тоталитарным политическим режимом",
		"это фашисткая унитарная империя с диктаторской формой правления и тоталитарным политическим режимом"
	);

	public static function socOrderEncode($id){
		mb_internal_encoding("UTF-8");
		$array = self::TYPES;
		for($i = 0; $i < count($array); $i++){
			if(mb_strtoupper($array[$i]) == mb_strtoupper($id)){
				return $i+1;
			}
		}
		return 0;
	}

	public static function socOrderDecode($id){
		$array = self::TYPES;
		return $array[$id-1];
	}

	public static function getSocOrderDesc($id){
		return self::ORDERS_DESC[$id-1];
	}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Handlers

function goverment_constitution($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];

	$current_soc_order_desc = SocOrderClass::getSocOrderDesc($gov["soc_order"]);
	$msg = "%__appeal__%, 📰информация о текущем государстве:\n🏛%__confa_name__% - {$current_soc_order_desc}.\n&#128104;&#8205;&#9878;Глава государства: %__president_name__%.\n📖Правящая партия: {$gov["batch_name"]}.\n🏢Столица: {$gov["capital"]}.\n";

	$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg), JSON_UNESCAPED_UNICODE);
	$request = vk_parse_vars($request, array("__president_name__", "__confa_name__", "__appeal__"));

	vk_execute($botModule->makeExeAppeal($data->object->from_id)."
		var confa_info = API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}]}).items[0];
		var president_info = API.users.get({'user_ids':[{$gov["president_id"]}],'fields':'screen_name'})[0];

		var __president_name__ = '@'+president_info.screen_name+' ('+president_info.first_name+' '+president_info.last_name+')';
		var __confa_name__ = confa_info.chat_settings.title;
		var __appeal__ = appeal; appeal = null;

		return API.messages.send({$request});
		");
}

function goverment_show_laws($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);

	$laws = $db["goverment"]["laws"];
	if(array_key_exists(1, $words))
		$number = intval($words[1]);
	else
		$number = 1;

	if(count($laws) == 0){
		$botModule->sendSimpleMessage($data->object->peer_id, ", ❗Пока нет действующих законов!", $data->object->from_id);
		return;
	}

	$laws_content = array();
	for($i = 0; $i < count($laws); $i++){
		$laws_content[] = $laws[$i]["content"];
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$laws_content; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $number; // Номер текущего списка
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
		$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	$laws_content = $list_out;

	$msg = ", 📌законы [{$list_number}/{$list_max_number}]:";
	for($i = 0; $i < count($laws_content); $i++){
		$law_id = ($i+1)+10*($list_number-1);
		$msg = $msg . "\n{$law_id}. {$laws_content[$i]}";
	}

	$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
}

function goverment_laws_cpanel($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];

	mb_internal_encoding("UTF-8");
	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";

	if($command == "добавить"){
		if($data->object->from_id == $gov["president_id"] || $data->object->from_id == $gov["parliament_id"]){
			$time = $data->object->date;
			$content = mb_substr($data->object->text, 16);
			$publisher_type = 1;
			if($data->object->from_id == $gov["parliament_id"])
				$publisher_type = 2;
			$publisher_id = $data->object->from_id;

			$gov["laws"][] = array(
				'time' => $time,
				'publisher_type' => $publisher_type,
				'publisher_id' => $publisher_id,
				'content' => $content
			);

			$botModule->sendSimpleMessage($data->object->peer_id, "@id{$data->object->from_id} (Правительство) обновило законы.");
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды!", $data->object->from_id);
		}
	}
	elseif($command == "отменить"){
		if($data->object->from_id == $gov["president_id"] || $data->object->from_id == $gov["parliament_id"]){
			if(array_key_exists(2, $words))
				$law_id = intval($words[2]);
			else
				$law_id = 0;
			if($law_id == 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Укажите ID закона!", $data->object->from_id);
				return;
			}

			if(!is_null($gov["laws"][$law_id-1])){
				$law = $gov["laws"][$law_id-1];

				if($law["publisher_type"] == 1){
					if($gov["president_id"] == $data->object->from_id){
						//$gov["laws"][$law_id-1] = $gov["laws"][count($gov["laws"])-1];
						unset($gov["laws"][$law_id-1]);
						$laws_tmp = array_values($gov["laws"]);
						$laws = array();
						for($i = 0; $i < count($laws_tmp); $i++){
							$laws[] = $laws_tmp[$i];
						}
						$gov["laws"] = $laws;
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Вы отменили закон №{$law_id}.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Вы не можете отменить закон президента!", $data->object->from_id);
					}
				}
				else{
					if($gov["parliament_id"] == $data->object->from_id){
						//$gov["laws"][$law_id-1] = $gov["laws"][count($gov["laws"])-1];
						unset($gov["laws"][$law_id-1]);
						$laws_tmp = array_values($gov["laws"]);
						$laws = array();
						for($i = 0; $i < count($laws_tmp); $i++){
							$laws[] = $laws_tmp[$i];
						}
						$gov["laws"] = $laws;
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Вы отменили закон №{$law_id}.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Вы не можете отменить закон парламента!", $data->object->from_id);
					}
				}
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Закона с таким ID не существует!", $data->object->from_id);
			}
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды!", $data->object->from_id);
		}
	}
	elseif($command == "инфа"){
		if(array_key_exists(2, $words))
			$law_id = intval($words[2]);
		else
			$law_id = 0;
		if($law_id == 0){
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Укажите ID закона!", $data->object->from_id);
			return;
		}

		if(!is_null($gov["laws"][$law_id-1])){
			$law = $gov["laws"][$law_id-1];

			$publisher_type_str = "Парламент";

			if($law["publisher_type"] == 1){
				if($law["publisher_id"] == $gov["president_id"])
					$publisher_type_str = "Президент";
				else
					$publisher_type_str = "Экc-пpeзидeнт";
			}

			$date = gmdate("d.m.Y H:i:s (по МСК)", $law["time"]+10800);

			$msg = "%__appeal__%, информация о законе:\n✅Указан: %__publisher_name__% ({$publisher_type_str})\n✅Дата указа: {$date}\n✅Содержание закона: {$law["content"]}";

			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("__publisher_name__", "__appeal__"));

			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				var publisher = API.users.get({'user_ids':[{$law['publisher_id']}],'fields':'screen_name,first_name_ins,last_name_ins'})[0];

				var __publisher_name__ = '@'+publisher.screen_name+' ('+publisher.first_name_ins+' '+publisher.last_name_ins+')';
				var __appeal__ = appeal; appeal = null;

				return API.messages.send({$request});
				");
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Закона с таким ID не существует!", $data->object->from_id);
		}
	}
	elseif($command == "переместить"){
		if($data->object->from_id != $gov["president_id"] && $data->object->from_id != $gov["parliament_id"]){
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды!", $data->object->from_id);
			return;
		}
		if(array_key_exists(2, $words))
			$from = intval($words[2]);
		else
			$from = 0;

		if(array_key_exists(3, $words))
			$to = intval($words[3]);
		else
			$to = 0;

		if($from == $to){
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Нельзя переместить закон в одно и тоже место.", $data->object->from_id);
			return;
		}

		if(is_null($gov["laws"][$from-1])){
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Закона №{$from} не существует.", $data->object->from_id);
			return;
		}
		if(is_null($gov["laws"][$to-1])){
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Закона №{$to} не существует.", $data->object->from_id);
			return;
		}

		$tmp = $gov["laws"][$to-1];
		$gov["laws"][$to-1] = $gov["laws"][$from-1];
		$gov["laws"][$from-1] = $tmp;
		$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Закон №{$from} перемещен на место закона №{$to}.", $data->object->from_id);

	}
	else{
		$commands = array(
			'!закон добавить <текст> - Добавление закона',
			'!закон отменить <id> - Отмена закона',
			'!закон переместить <from> <to> - Перемещение закона из позиции from в позицию to',
			'!закон инфа <id> - Информация о законе'

		);
		$botModule->sendCommandListFromArray($data, ", &#9940;используйте:", $commands);
	}
}

function goverment_president($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(!array_key_exists(1, $words)){
		$msg = "%appeal%, &#128104;&#8205;&#9878;Действующий президент: %president_name%.";
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_vars($request, array("appeal", "president_name"));
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var president = API.users.get({'user_ids':[{$gov["president_id"]}]})[0];
			var president_name = '@id{$gov["president_id"]} ('+president.first_name+' '+president.last_name+')';
			return API.messages.send({$request});
			");
	} else {
		if($data->object->from_id == $gov["parliament_id"]){
			$new_president_id = bot_get_id_from_mention($words[1]);
			if(!is_null($new_president_id)){
				$batch_name = json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				var president = API.users.get({'user_ids':[{$new_president_id}],'fields':'first_name_gen,last_name_gen'})[0];
				var msg = '@id{$gov["parliament_id"]} (Парламентом) назначен новый президент: @id'+president.id+' ('+president.first_name+' '+president.last_name+').';
				API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
				return 'Полит. партия '+president.first_name_gen+' '+president.last_name_gen;
				"))->response;
				$ranksys = new RankSystem($db);
				$ranksys->setUserRank($gov["president_id"], -1);
				$ranksys->setUserRank($new_president_id, 1);
				$economy = new Economy\Main($db); // Модуль Экономики
				$economy->getUser($gov["president_id"])->deleteItem("govdoc", "presidential_certificate");  // Убираем удостоверение президента у предыдущего
				$economy->getUser($new_president_id)->changeItem("govdoc", "presidential_certificate", 1);  // Выдаем удостоверение президента новому
				$gov["president_id"] = $new_president_id;
				$gov["batch_name"] = $batch_name;
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔данного пользователя не существует.", $data->object->from_id);
			}
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_batch($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(!array_key_exists(1, $words)){
		$botModule->sendSimpleMessage($data->object->peer_id, ", &#128214;Действующая партия: ".$gov["batch_name"].".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["president_id"]){
			mb_internal_encoding("UTF-8");
			$gov["batch_name"] = mb_substr($data->object->text, 8, mb_strlen($data->object->text));
			$msg = "@id".$gov["president_id"]." (Президент) переименовал действующую партию.";
			$botModule->sendSimpleMessage($data->object->peer_id, $msg);
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_capital($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(!array_key_exists(1, $words)){
		$botModule->sendSimpleMessage($data->object->peer_id, ", &#127970;Текущая столица: ".$gov["capital"].".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["president_id"]){
			mb_internal_encoding("UTF-8");
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			$msg = "@id".$gov["president_id"]." (Президент) изменил столицу государства.";
			$botModule->sendSimpleMessage($data->object->peer_id, $msg);
		} elseif($data->object->from_id == $gov["parliament_id"]){
			mb_internal_encoding("UTF-8");
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			$msg = "@id".$gov["parliament_id"]." (Парламент) изменил столицу государства.";
			$botModule->sendSimpleMessage($data->object->peer_id, $msg);
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_socorder($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(!array_key_exists(1, $words)){
		$botModule->sendSimpleMessage($data->object->peer_id, ", ⚔Текущий политический строй государства: ".SocOrderClass::socOrderDecode($gov["soc_order"]).".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["parliament_id"]){
			$id = SocOrderClass::socOrderEncode($words[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				$msg = "@id".$gov["parliament_id"]." (Парламентом) был изменён политический строй.";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg);
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", Такого политического строя нет! Смотрите !стройлист.", $data->object->from_id);
			}
		} elseif ($data->object->from_id == $gov["president_id"]) {
			$id = SocOrderClass::socOrderEncode($words[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				$msg = "@id".$gov["president_id"]." (Президентом) был изменён политический строй.";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg);
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", Такого политического строя нет! Смотрите !стройлист.", $data->object->from_id);
			}
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_socorderlist($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$array = SocOrderClass::TYPES;
	$msg = "";
	for($i = 0; $i < count($array); $i++){
		$msg = $msg."\n&#127381;".$array[$i];
	}

	$botModule->sendSimpleMessage($data->object->peer_id, ", Список политических строев: ".$msg, $data->object->from_id);
}

function goverment_anthem($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(count($data->object->attachments) == 0){
		if($gov["anthem"] != "nil"){
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', &#129345;Наш гимн: ','attachment':'{$gov["anthem"]}'});
				");
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#129345;У нас нет гимна!", $data->object->from_id);
		}
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$first_audio_id = -1;
			$audio = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "audio"){
					$first_audio_id = $i;
					break;
				}
			}
			if ($first_audio_id != -1){
				$audio = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				$gov["anthem"] = $audio;
				$msg = "@id".$gov["president_id"]." (Президент) изменил гимн государства.";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg);
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Аудиозаписи не найдены!", $data->object->from_id);
			}
		} elseif($data->object->from_id == $gov["parliament_id"]){
			$first_audio_id = -1;
			$audio = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "audio"){
					$first_audio_id = $i;
					break;
				}
			}
			if ($first_audio_id != -1){
				$audio = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				$gov["anthem"] = $audio;
				$msg = "@id".$gov["parliament_id"]." (Парламент) изменил гимн государства.";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg);
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Аудиозаписи не найдены!", $data->object->from_id);
			}
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_flag($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$gov = &$db["goverment"];
	if(count($data->object->attachments) == 0){
		if($gov["flag"] != "nil"){
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', &#127987;Наш флаг: ','attachment':'{$gov["flag"]}'});
				");
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#127987;У нас нет флага!", $data->object->from_id);
		}
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$first_photo_id = -1;
			$photo = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "photo"){
					$first_photo_id = $i;
					break;
				}
			}
			if ($first_photo_id != -1){
				$photo_sizes = $data->object->attachments[$first_photo_id]->photo->sizes;
				$photo_url_index = 0;
				for($i = 0; $i < count($photo_sizes); $i++){
					if($photo_sizes[$i]->height > $photo_sizes[$photo_url_index]->height){
						$photo_url_index = $i;
					}
				}
				$photo_url = $photo_sizes[$photo_url_index]->url;
				$path = BOT_TMPDIR."/photo".mt_rand(0, 65500).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["president_id"]." (Президент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});
					API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					return doc;
					"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Фотографии не найдены!", $data->object->from_id);
			}
		} elseif($data->object->from_id == $gov["parliament_id"]){
			$first_photo_id = -1;
			$photo = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "photo"){
					$first_photo_id = $i;
					break;
				}
			}
			if ($first_photo_id != -1){
				$photo_sizes = $data->object->attachments[$first_photo_id]->photo->sizes;
				$photo_url_index = 0;
				for($i = 0; $i < count($photo_sizes); $i++){
					if($photo_sizes[$i]->height > $photo_sizes[$photo_url_index]->height){
						$photo_url_index = $i;
					}
				}
				$photo_url = $photo_sizes[$photo_url_index]->url;
				$path = BOT_TMPDIR."/photo".mt_rand(0, 65500).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["parliament_id"]." (Парламент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});
					API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					return doc;
					"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;Фотографии не найдены!", $data->object->from_id);
			}
		} else {
			$botModule->sendSimpleMessage($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_referendum_start($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if(!bot_check_reg($db)){
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
			return 'error';
	}

	if($data->object->from_id == $db["goverment"]["parliament_id"]){
		if(!array_key_exists("referendum", $db["goverment"])){
			$db["goverment"]["referendum"]["candidate1"] = array('id' => 0, "voters_count" => 0);
			$db["goverment"]["referendum"]["candidate2"] = array('id' => 0, "voters_count" => 0);
			$db["goverment"]["referendum"]["all_voters"] = array();
			$db["goverment"]["referendum"]["start_time"] = $data->object->date;
			$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
			$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \\\"!candidate\\\".";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
		} else {
			$msg = ", выборы уже проходят.";
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", &#9940;у вас нет прав для использования данной команды.";
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_stop($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if(!bot_check_reg($db)){
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
			return 'error';
	}

	if($data->object->from_id == $db["goverment"]["parliament_id"]){
		if(!array_key_exists("referendum", $db["goverment"])){
			$msg = ", сейчас не проходят выборы.";
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		} else {
			unset($db["goverment"]["referendum"]);
			$msg = ", выборы остановлены.";
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", &#9940;у вас нет прав для использования данной команды.";
			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_candidate($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if(array_key_exists("referendum", $db["goverment"])){
		if($db["goverment"]["referendum"]["candidate1"]["id"] != $data->object->from_id && $db["goverment"]["referendum"]["candidate2"]["id"] != $data->object->from_id){
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0){
				$db["goverment"]["referendum"]["candidate1"]["id"] = $data->object->from_id;
				$msg = ", вы зарегистрировались как кандидат №1.";
				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
			} elseif($db["goverment"]["referendum"]["candidate2"]["id"] == 0) {
				$db["goverment"]["referendum"]["candidate2"]["id"] = $data->object->from_id;
				$msg1 = ", вы зарегистрировались как кандидат №2.";
				$msg2 = "Кандидаты набраны, самое время голосовать. Используй \\\"!vote\\\", чтобы учавствовать в голосовании.";
				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg1}'});
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg2}'});");
				$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
			} else {
				$msg = ", кандидаты уже набраны.";
				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
			}
		} else {
			$msg = ", вы уже зарегистрированы как кандидат в президенты.";
				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", сейчас не проходят выборы.";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_system($data, &$db){
	if(array_key_exists("referendum", $db["goverment"])){
		if($data->object->date - $db["goverment"]["referendum"]["last_notification_time"] >= 600){
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0 || $db["goverment"]["referendum"]["candidate2"]["id"] == 0){
				$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \\\"!candidate\\\".";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			} else {
				$msg = "Кандидаты набраны, самое время голосовать. Используй \\\"!vote\\\", чтобы учавствовать в голосовании.";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			}
			$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
		} elseif($data->object->date - $db["goverment"]["referendum"]["start_time"] >= 18000) {
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0 || $db["goverment"]["referendum"]["candidate2"]["id"] == 0){
				$msg = "❗Выборы прерваны. Причина: не набрано нужно количество кандидатов.";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				unset($db["goverment"]["referendum"]);
			} else {
				$candidate1_voters_count = $db["goverment"]["referendum"]["candidate1"]["voters_count"];
				$candidate2_voters_count = $db["goverment"]["referendum"]["candidate2"]["voters_count"];
				$all_voters_count = sizeof($db["goverment"]["referendum"]["all_voters"]);
				if($candidate1_voters_count > $candidate2_voters_count){
					$candidate_id = $db["goverment"]["referendum"]["candidate1"]["id"];
					$candidate_percent = round($candidate1_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("
						var users = API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});
						var sex_word = 'Он';
						if(users[0].sex == 1){
							sex_word = 'Она';
						}
						var msg = '✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen};"));
					$ranksys = new RankSystem($db);
					$ranksys->setUserRank($gov["president_id"], -1);
					$ranksys->setUserRank($candidate_id, 1);
					$economy = new Economy\Main($db); // Модуль Экономики
					$economy->getUser($gov["president_id"])->deleteItem("govdoc", "presidential_certificate"); // Убираем удостоверение президента у предыдущего
					$economy->getUser($candidate_id)->changeItem("govdoc", "presidential_certificate", 1); // Выдаем удостоверение президента новому
					$db["goverment"]["president_id"] = $candidate_id;
					$db["goverment"]["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					unset($db["goverment"]["referendum"]);
				} elseif($candidate1_voters_count < $candidate2_voters_count) {
					$candidate_id = $db["goverment"]["referendum"]["candidate2"]["id"];
					$candidate_percent = round($candidate2_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("
						var users = API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});
						var sex_word = 'Он';
						if(users[0].sex == 1){
							sex_word = 'Она';
						}
						var msg = '✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen};"));
					$ranksys = new RankSystem($db);
					$ranksys->setUserRank($gov["president_id"], -1);
					$ranksys->setUserRank($candidate_id, 1);
					$economy = new Economy\Main($db); // Модуль Экономики
					$economy->getUser($gov["president_id"])->deleteItem("govdoc", "presidential_certificate");  // Убираем удостоверение президента у предыдущего
					$economy->getUser($candidate_id)->changeItem("govdoc", "presidential_certificate", 1);  // Выдаем удостоверение президента новому
					$db["goverment"]["president_id"] = $candidate_id;
					$db["goverment"]["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					unset($db["goverment"]["referendum"]);
				} else {
				$msg = "❗Выборы прерваны. Причина: оба кандидата набрали одинаковое количество голосов.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				unset($db["goverment"]["referendum"]);
				}
			}
		}

		if(!is_null($data->object->payload)){
			$payload = json_decode($data->object->payload);
			if($payload->command == "referendum_vote"){
				$botModule = new BotModule($db);
				if(is_numeric($payload->vote_candidate_id)){
					if ($payload->vote_candidate_id == 0){
						$msg = "❗Меню голосования закрыто.";
						vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{\\\"one_time\\\":true,\\\"buttons\\\":[]}'});");
						return;
					}

					for($i = 0; $i < sizeof($db["goverment"]["referendum"]["all_voters"]); $i++){
						if($db["goverment"]["referendum"]["all_voters"][$i] == $data->object->from_id){
							$msg = ", ⛔вы уже голосовали.";
							vk_execute($botModule->makeExeAppeal($data->object->from_id)."
								return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
							return;
						}
					}

					if($payload->vote_candidate_id == 1){
						$db["goverment"]["referendum"]["all_voters"][] = $data->object->from_id;
						$db["goverment"]["referendum"]["candidate1"]["voters_count"] = $db["goverment"]["referendum"]["candidate1"]["voters_count"] + 1;
						$candidate_id = $db["goverment"]["referendum"]["candidate1"]["id"];
						vk_execute($botModule->makeExeAppeal($data->object->from_id)."
							var user = API.users.get({'user_ids':[{$candidate_id}]});
							var msg = ', 📝вы проголосовали за @id'+user[0].id+' ('+user[0].first_name+' '+user[0].last_name+').';
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});");

					} elseif ($payload->vote_candidate_id == 2){
						$db["goverment"]["referendum"]["all_voters"][] = $data->object->from_id;
						$db["goverment"]["referendum"]["candidate2"]["voters_count"] = $db["goverment"]["referendum"]["candidate2"]["voters_count"] + 1;
						$candidate_id = $db["goverment"]["referendum"]["candidate2"]["id"];
						vk_execute($botModule->makeExeAppeal($data->object->from_id)."
							var user = API.users.get({'user_ids':[{$candidate_id}]});
							var msg = ', 📝вы проголосовали за @id'+user[0].id+' ('+user[0].first_name+' '+user[0].last_name+').';
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});");
					}
				}
			}
		}
	}
}

function goverment_referendum_vote($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if(array_key_exists("referendum", $db["goverment"])){
		if($db["goverment"]["referendum"]["candidate1"]["id"] != 0 && $db["goverment"]["referendum"]["candidate2"]["id"] != 0){
			for($i = 0; $i < sizeof($db["goverment"]["referendum"]["all_voters"]); $i++){
				if($db["goverment"]["referendum"]["all_voters"][$i] == $data->object->from_id){
					$msg = ", ⛔вы уже голосовали.";
					vk_execute($botModule->makeExeAppeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
					return;
				}
			}

			$candidate1_id = $db["goverment"]["referendum"]["candidate1"]["id"];
			$candidate2_id = $db["goverment"]["referendum"]["candidate2"]["id"];

			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				var users = API.users.get({'user_ids':[{$candidate1_id},{$candidate2_id}]});

				var button_candidate1 = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"📝'+users[0].first_name.substr(0, 2)+'. '+users[0].last_name+'\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"1\\\\\"}\\\"},\\\"color\\\":\\\"primary\\\"}';
				var button_candidate2 = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"📝'+users[1].first_name.substr(0, 2)+'. '+users[1].last_name+'\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"2\\\\\"}\\\"},\\\"color\\\":\\\"primary\\\"}';
				var button_cancel = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"Закрыть\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"0\\\\\"}\\\"},\\\"color\\\":\\\"negative\\\"}';

				var keyboard = '{\\\"one_time\\\":false,\\\"buttons\\\":[['+button_candidate1+','+button_candidate2+'],['+button_cancel+']]}';

				var msg = ', учавствуй в выборах президента. Просто нажми на кнопку понравившегося тебе кандидата и ты отдашь за него свой голос. Список кандидатов:\\n✅@id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+')\\n✅@id'+users[1].id+' ('+users[1].first_name+' '+users[1].last_name+')';

				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'keyboard':keyboard});
				");
		} else {
			$msg = ", кандидаты еще не набраны. Вы можете балотироваться в президенты, использовав команду \\\"!candidate\\\".";
				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", сейчас не проходят выборы.";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

?>