<?php

// Инициализация команд
function government_initcmd($event){
	// Правительство
	$event->addTextMessageCommand("!конституция", 'government_constitution');
	$event->addTextMessageCommand("!президент", 'government_president');
	$event->addTextMessageCommand("!строй", 'government_socorder');
	$event->addTextMessageCommand("!стройлист", 'government_socorderlist');
	$event->addTextMessageCommand("!законы", 'government_show_laws');
	$event->addTextMessageCommand("!закон", 'government_laws_cpanel');
	$event->addTextMessageCommand("!партия", 'government_batch');
	$event->addTextMessageCommand("!столица", 'government_capital');
	$event->addTextMessageCommand("!гимн", 'government_anthem');
	$event->addTextMessageCommand("!флаг", 'government_flag');
	$event->addTextMessageCommand("!митинг", 'government_rally');

	// Система выборов
	$event->addTextMessageCommand("!votestart", 'government_referendum_start');
	//$event->addTextMessageCommand("!votestop", 'government_referendum_stop');
	$event->addTextMessageCommand("!candidate", 'government_referendum_candidate');
	$event->addTextMessageCommand("!vote", 'government_referendum_vote_cmd');
	$event->addCallbackButtonCommand("referendum_vote", "government_referendum_vote_cb");
}

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
// Goverment API

// Получение статистики пользователя
function government_api_getdata($db){
	// Стандартные значения гос. параметров
	define('DB_GOVERNMENT_DEFAULT', array(
		'soc_order' => 1,
		'president_id' => 0,
		'previous_president_id' => 0,
		'presidential_power' => 100,
		'parliament_id' => $db->getValue(array("owner_id")),
		'batch_name' => "Нет данных",
		'laws' => array(),
		'anthem' => "null",
		'flag' => "null",
		'capital' => 'г. Мда',
		'rally' => array(
			'for' => false,
			'against' => false
		),
		'referendum' => false,
		'last_referendum_time' => 0
	));

	$db_data = $db->getValue(array("government"), array());
	$data = array();
	foreach (DB_GOVERNMENT_DEFAULT as $key => $value) {
		if(array_key_exists($key, $db_data))
			$data[$key] = $db_data[$key];
		else
			$data[$key] = $value;
	}
	return $data;
}

// Сохранение статистики пользователя
function government_api_setdata($db, $value){
	return $db->setValue(array("government"), $value);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Handlers

function government_constitution($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$gov = government_api_getdata($db);

	$current_soc_order_desc = SocOrderClass::getSocOrderDesc($gov["soc_order"]);
	if($gov["president_id"] != 0){
		$msg = "%__appeal__%, 📰информация о текущем государстве:\n🏛%__confa_name__% - {$current_soc_order_desc}.\n&#128104;&#8205;&#9878;Глава государства: %__president_name__%.\n📖Правящая партия: {$gov["batch_name"]}.\n🏢Столица: {$gov["capital"]}.\n";

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_vars($request, array("__president_name__", "__confa_name__", "__appeal__"));

		vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var confa_info=API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}]}).items[0];var president_info=API.users.get({'user_ids':[{$gov["president_id"]}],'fields':'screen_name'})[0];var __president_name__='@'+president_info.screen_name+' ('+president_info.first_name+' '+president_info.last_name+')';var __confa_name__=confa_info.chat_settings.title;var __appeal__=appeal;appeal=null;return API.messages.send({$request});");
	}
	else{
		$msg = "%__appeal__%, 📰информация о текущем государстве:\n🏛%__confa_name__% - {$current_soc_order_desc}.\n&#128104;&#8205;&#9878;Глава государства: ⛔Не назначен.\n📖Правящая партия: {$gov["batch_name"]}.\n🏢Столица: {$gov["capital"]}.\n";

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_vars($request, array("__president_name__", "__confa_name__", "__appeal__"));

		vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var confa_info = API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}]}).items[0];var __confa_name__ = confa_info.chat_settings.title;var __appeal__ = appeal; appeal = null;return API.messages.send({$request});");
	}
}

function government_show_laws($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$laws = government_api_getdata($db)["laws"];
	if(array_key_exists(1, $argv))
		$number = intval($argv[1]);
	else
		$number = 1;

	if(count($laws) == 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ❗Пока нет действующих законов!");
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
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔указан неверный номер списка!");
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	$laws_content = $list_out;

	$msg = "%appeal%, 📌законы [{$list_number}/{$list_max_number}]:";
	for($i = 0; $i < count($laws_content); $i++){
		$law_id = ($i+1)+10*($list_number-1);
		$msg = $msg . "\n{$law_id}. {$laws_content[$i]}";
	}

	$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
}

function government_laws_cpanel($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);

	if(array_key_exists(1, $argv))
		$command = mb_strtolower($argv[1]);
	else
		$command = "";

	if($command == "добавить"){
		if($data->object->from_id == $gov["president_id"] || $data->object->from_id == $gov["parliament_id"]){
			$time = time();
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
			government_api_setdata($db, $gov);
			$db->save();
			$messagesModule->sendSilentMessage($data->object->peer_id, "@id{$data->object->from_id} (Правительство) обновило законы.");
		}
		else{
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
	elseif($command == "отменить"){
		if($data->object->from_id == $gov["president_id"] || $data->object->from_id == $gov["parliament_id"]){
			if(array_key_exists(2, $argv))
				$law_id = intval($argv[2]);
			else
				$law_id = 0;
			if($law_id == 0){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Укажите ID закона!");
				return;
			}

			if(!is_null($gov["laws"][$law_id-1])){
				$law = $gov["laws"][$law_id-1];

				if($law["publisher_type"] == 1){
					if($gov["president_id"] == $data->object->from_id){
						unset($gov["laws"][$law_id-1]);
						$laws_tmp = array_values($gov["laws"]);
						$laws = array();
						for($i = 0; $i < count($laws_tmp); $i++){
							$laws[] = $laws_tmp[$i];
						}
						$gov["laws"] = $laws;
						$db->setValue(array("government", "laws"), $gov["laws"]);
						$db->save();
						$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы отменили закон №{$law_id}.");
					}
					else{
						$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Вы не можете отменить закон президента!");
					}
				}
				else{
					if($gov["parliament_id"] == $data->object->from_id){
						unset($gov["laws"][$law_id-1]);
						$laws_tmp = array_values($gov["laws"]);
						$laws = array();
						for($i = 0; $i < count($laws_tmp); $i++){
							$laws[] = $laws_tmp[$i];
						}
						$gov["laws"] = $laws;
						government_api_setdata($db, $gov);
						$db->save();
						$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы отменили закон №{$law_id}.");
					}
					else{
						$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Вы не можете отменить закон парламента!");
					}
				}
			}
			else{
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Закона с таким ID не существует!");
			}
		}
		else{
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
	elseif($command == "инфа"){
		if(array_key_exists(2, $argv))
			$law_id = intval($argv[2]);
		else
			$law_id = 0;
		if($law_id == 0){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Укажите ID закона!");
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

			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("__publisher_name__", "__appeal__"));

			vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var publisher = API.users.get({'user_ids':[{$law['publisher_id']}],'fields':'screen_name,first_name_ins,last_name_ins'})[0];var __publisher_name__ = '@'+publisher.screen_name+' ('+publisher.first_name_ins+' '+publisher.last_name_ins+')';var __appeal__ = appeal; appeal = null;return API.messages.send({$request});");
		}
		else{
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Закона с таким ID не существует!");
		}
	}
	elseif($command == "переместить"){
		if($data->object->from_id != $gov["president_id"] && $data->object->from_id != $gov["parliament_id"]){
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
			return;
		}
		if(array_key_exists(2, $argv))
			$from = intval($argv[2]);
		else
			$from = 0;

		if(array_key_exists(3, $argv))
			$to = intval($argv[3]);
		else
			$to = 0;

		if($from == $to){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Нельзя переместить закон в одно и тоже место.");
			return;
		}

		if(is_null($gov["laws"][$from-1])){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Закона №{$from} не существует.");
			return;
		}
		if(is_null($gov["laws"][$to-1])){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Закона №{$to} не существует.");
			return;
		}

		$tmp = $gov["laws"][$to-1];
		$gov["laws"][$to-1] = $gov["laws"][$from-1];
		$gov["laws"][$from-1] = $tmp;
		government_api_setdata($db, $gov);
		$db->save();
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Закон №{$from} перемещен на место закона №{$to}.");

	}
	else{
		$commands = array(
			'!закон добавить <текст> - Добавление закона',
			'!закон отменить <id> - Отмена закона',
			'!закон переместить <from> <to> - Перемещение закона из позиции from в позицию to',
			'!закон инфа <id> - Информация о законе'

		);
		$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, &#9940;используйте:", $commands);
	}
}

function government_president($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	$presidential_power_text = round($gov["presidential_power"], 2);
	if(!array_key_exists(1, $argv)){
		if($gov["president_id"] != 0){
			$msg = "%appeal%,\n&#128104;&#8205;&#9878;Президент: %president_name%.\n💪🏻Легитимность: {$presidential_power_text}%";
			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("appeal", "president_name"));
			vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var president = API.users.get({'user_ids':[{$gov["president_id"]}]})[0];var president_name = '@id{$gov["president_id"]} ('+president.first_name+' '+president.last_name+')';return API.messages.send({$request});");
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%,\n&#128104;&#8205;&#9878;Президент: ⛔Не назначен.");
	}
}

function government_batch($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	if(!array_key_exists(1, $argv)){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#128214;Действующая партия: ".$gov["batch_name"].".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$gov["batch_name"] = mb_substr($data->object->text, 8, mb_strlen($data->object->text));
			government_api_setdata($db, $gov);
			$db->save();
			$msg = "@id".$gov["president_id"]." (Президент) переименовал действующую партию.";
			$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
}

function government_capital($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	if(!array_key_exists(1, $argv)){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#127970;Текущая столица: ".$gov["capital"].".");
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			government_api_setdata($db, $gov);
			$db->save();
			$msg = "@id".$gov["president_id"]." (Президент) изменил столицу государства.";
			$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
		} elseif($data->object->from_id == $gov["parliament_id"]){
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			government_api_setdata($db, $gov);
			$db->save();
			$msg = "@id".$gov["parliament_id"]." (Парламент) изменил столицу государства.";
			$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
}

function government_socorder($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	if(!array_key_exists(1, $argv)){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⚔Текущий политический строй государства: ".SocOrderClass::socOrderDecode($gov["soc_order"]).".");
	} else {
		if($data->object->from_id == $gov["parliament_id"]){
			$id = SocOrderClass::socOrderEncode($argv[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				government_api_setdata($db, $gov);
				$db->save();
				$msg = "@id".$gov["parliament_id"]." (Парламентом) был изменён политический строй.";
				$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Такого политического строя нет! Смотрите !стройлист.");
			}
		} elseif ($data->object->from_id == $gov["president_id"]) {
			$id = SocOrderClass::socOrderEncode($argv[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				government_api_setdata($db, $gov);
				$db->save();
				$msg = "@id".$gov["president_id"]." (Президентом) был изменён политический строй.";
				$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Такого политического строя нет! Смотрите !стройлист.");
			}
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
}

function government_socorderlist($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$array = SocOrderClass::TYPES;
	$msg = "";
	for($i = 0; $i < count($array); $i++){
		$msg = $msg."\n&#127381;".$array[$i];
	}

	$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Список политических строев: ".$msg);
}

function government_anthem($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	if(count($data->object->attachments) == 0){
		if($gov["anthem"] != "null"){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#129345;Наш гимн: ", array('attachment' => $gov["anthem"]));
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#129345;У нас нет гимна!");
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
				$gov["anthem"] = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				government_api_setdata($db, $gov);
				$db->save();
				$msg = "@id".$gov["president_id"]." (Президент) изменил гимн государства.";
				$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Аудиозаписи не найдены!");
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
				$gov["anthem"] = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				government_api_setdata($db, $gov);
				$db->save();
				$msg = "@id".$gov["parliament_id"]." (Парламент) изменил гимн государства.";
				$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Аудиозаписи не найдены!");
			}
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
}

function government_flag($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	if(count($data->object->attachments) == 0){
		if($gov["flag"] != "null"){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#127987;Наш флаг: ", array('attachment' => $gov["flag"]));
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#127987;У нас нет флага!");
		}
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$first_photo_id = -1;
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
				$path = BOT_TMPDIR."/photo".mt_rand(0, 65535).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["president_id"]." (Президент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','disable_mentions':true});return doc;"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
				government_api_setdata($db, $gov);
				$db->save();
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Фотографии не найдены!");
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
				$path = BOT_TMPDIR."/photo".mt_rand(0, 65535).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["parliament_id"]." (Парламент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','disable_mentions':true});return doc;"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
				government_api_setdata($db, $gov);
				$db->save();
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Фотографии не найдены!", $data->object->from_id);
			}
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
}

function government_rally($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$date = time(); // Переменная времени

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$argvt = mb_strtolower(bot_get_array_value($argv, 1, ""));

	$gov = government_api_getdata($db);

	if($gov["president_id"] == 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Президент не выбран.");
		return;
	}
	elseif($gov["referendum"] !== false){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Сейчас проходят выборы.");
		return;
	}

	switch ($argvt) {
		case 'за':
		$rally_for = $gov["rally"]["for"];
		$rally_against = $gov["rally"]["against"];
		if($rally_for !== false){
			if($rally_against !== false && array_key_exists("id{$data->object->from_id}", $rally_against["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы участвуете в митинге Против @id{$gov["president_id"]} (президента).");
				return;
			}
			elseif(array_key_exists("id{$data->object->from_id}", $rally_for["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы уже участвуете в митинге За @id{$gov["president_id"]} (президента).");
				return;
			}
			$rally_for["members"]["id{$data->object->from_id}"] = 0;
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы присоединились к митингу За @id{$gov["president_id"]} (президента).\nИспользуйте [!митинг], чтобы поддержать его.");
		}
		else{
			if($rally_against !== false && array_key_exists("id{$data->object->from_id}", $rally_against["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы участвуете в митинге Против @id{$gov["president_id"]} (президента).");
				return;
			}
			$rally_for = array(
				'organizer_id' => $data->object->from_id,
				'members' => array(
					"id{$data->object->from_id}" => 0
				)
			);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы организовали митинг За @id{$gov["president_id"]} (президента).\nИспользуйте [!митинг], чтобы поддержать его.");
		}
		$gov["rally"]["for"] = $rally_for;
		government_api_setdata($db, $gov);
		$db->save();
		break;

		case 'против':
		$rally_for = $gov["rally"]["for"];
		$rally_against = $gov["rally"]["against"];
		if($rally_against !== false){
			if($rally_for !== false && array_key_exists("id{$data->object->from_id}", $rally_for["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы участвуете в митинге За @id{$gov["president_id"]} (президента).");
				return;
			}
			elseif($data->object->from_id == $gov["president_id"]){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы не можете учавствовать в митинге против себя.");
				return;
			}
			elseif(array_key_exists("id{$data->object->from_id}", $rally_against["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы уже участвуете в митинге Против @id{$gov["president_id"]} (президента).");
				return;
			}
			$rally_against["members"]["id{$data->object->from_id}"] = 0;
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы присоединились к митингу Против @id{$gov["president_id"]} (президента).\nИспользуйте [!митинг], чтобы свергнуть его.");
		}
		else{
			if($rally_for !== false && array_key_exists("id{$data->object->from_id}", $rally_for["members"])){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы участвуете в митинге За @id{$gov["president_id"]} (президента).");
				return;
			}
			elseif($data->object->from_id == $gov["president_id"]){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы не можете устроить митинг против себя.");
				return;
			}
			$rally_against = array(
				'organizer_id' => $data->object->from_id,
				'members' => array(
					"id{$data->object->from_id}" => 0
				)
			);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы организовали митинг Против @id{$gov["president_id"]} (президента).\nИспользуйте [!митинг], чтобы свергнуть его.");
		}
		$gov["rally"]["against"] = $rally_against;
		government_api_setdata($db, $gov);
		$db->save();
		break;
		
		default:
		$rally_for = $gov["rally"]["for"];
		$rally_against = $gov["rally"]["against"];
		$member_key = "id{$data->object->from_id}";
		if($rally_for !== false && array_key_exists($member_key, $rally_for["members"])){
			if($date - $rally_for["members"][$member_key] >= 3600){
				$members_count = count($rally_against["members"]);
				$r = json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var presidential_power={$gov["presidential_power"]};var members_in_chat=API.messages.getConversationMembers({'peer_id':peer_id});var members_in_rally={$members_count};var percentage_of_one=(1/members_in_chat.profiles.length)*0.1;var rally_result=percentage_of_one+(members_in_rally-1)*(percentage_of_one*0.25);presidential_power=presidential_power+rally_result*100;if(presidential_power>100){presidential_power=100;}API.messages.send({'peer_id':peer_id,'message':appeal+', ✅Вы поучаствовали в митинге За @id{$gov["president_id"]} (президента).','disable_mentions':true});return presidential_power;"));
				if(gettype($r) == "object" && property_exists($r, 'response')){
					$presidential_power = $r->response;
					$gov["rally"]["for"]["members"][$member_key] = $date;
					$gov["presidential_power"] = $presidential_power;
					government_api_setdata($db, $gov);
					$db->save();
				}
			}
			else{
				$left_time = 3600 - ($date - $rally_for["members"][$member_key]);
				$minutes = intdiv($left_time, 60);
				$seconds = $left_time % 60;
				$left_time_text = "";
				if($minutes != 0)
					$left_time_text = "{$minutes} мин. ";
				$left_time_text = $left_time_text."{$seconds} сек.";
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы устали и больше не можете митинговать. Приходите через {$left_time_text}");
			}
		}
		elseif($rally_against !== false && array_key_exists($member_key, $rally_against["members"])){
			if($date - $rally_against["members"][$member_key] >= 3600){
				$members_count = count($rally_against["members"]);
				$r = json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var presidential_power={$gov["presidential_power"]};var users=API.users.get({'user_ids':[{$rally_against["organizer_id"]},{$gov["president_id"]}],'fields':'first_name_ins,last_name_ins,first_name_gen,last_name_gen'});var members_in_chat=API.messages.getConversationMembers({'peer_id':peer_id});var members_in_rally={$members_count};var percentage_of_one=(1/members_in_chat.profiles.length)*0.1;var rally_result=percentage_of_one+(members_in_rally-1)*(percentage_of_one*0.25);presidential_power=presidential_power-rally_result*100;if(presidential_power<=0){presidential_power=0;API.messages.send({'peer_id':peer_id,'message':'❗Митинг, организованный @id'+users[0].id+' ('+users[0].first_name_ins.substr(0, 2)+'. '+users[0].last_name_ins+'), позволил добиться справедливости и правительсво @id'+users[1].id+' ('+users[1].first_name_gen.substr(0, 2)+'. '+users[1].last_name_gen+') подало в отставку. Организованы досрочные выборы президента.','disable_mentions':true});}else{API.messages.send({'peer_id':peer_id,'message':appeal+', ✅Вы поучаствовали в митинге Против @id{$gov["president_id"]} (президента).','disable_mentions':true});}return presidential_power;"));
				if(gettype($r) == "object" && property_exists($r, 'response')){
					$presidential_power = $r->response;
					if($presidential_power == 0){
						$gov["previous_president_id"] = $gov["president_id"];
						$gov["president_id"] = 0;
						$gov["rally"] = DB_GOVERNMENT_DEFAULT["rally"];
						$gov["batch_name"] = DB_GOVERNMENT_DEFAULT["batch_name"];
						$gov["referendum"] = array(
							'candidate1' => array('id' => 0, "voters_count" => 0),
							'candidate2' => array('id' => 0, "voters_count" => 0),
							'all_voters' => array(),
							'start_time' => $date,
							'last_notification_time' => 0
						);
						government_api_setdata($db, $gov);
						$db->save();
					}
					else{
						$gov["rally"]["against"]["members"][$member_key] = $date;
						$gov["presidential_power"] = $presidential_power;
						government_api_setdata($db, $gov);
						$db->save();
					}
				}
			}
			else{
				$left_time = 3600 - ($date - $rally_against["members"][$member_key]);
				$minutes = intdiv($left_time, 60);
				$seconds = $left_time % 60;
				$left_time_text = "";
				if($minutes != 0)
					$left_time_text = "{$minutes} мин. ";
				$left_time_text = $left_time_text."{$seconds} сек.";
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы устали и больше не можете митинговать. Приходите через {$left_time_text}");
			}
		}
		else{
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, используйте:", array(
				'!митинг за - Митинг за Президента',
				'!митинг против - Митинг против Президента'
			));
		}
		break;
	}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Система выборов

function government_referendum_start($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);
	if($data->object->from_id == $gov["parliament_id"]){
		if($gov["referendum"] === false){
			$date = time(); // Переменная времени
			$gov["previous_president_id"] = $gov["president_id"];
			$gov["president_id"] = 0;
			$gov["rally"] = DB_GOVERNMENT_DEFAULT["rally"];
			$gov["referendum"] = array(
				'candidate1' => array('id' => 0, "voters_count" => 0),
				'candidate2' => array('id' => 0, "voters_count" => 0),
				'all_voters' => array(),
				'start_time' => $date,
				'last_notification_time' => $date
			);
			government_api_setdata($db, $gov);
			$db->save();
			$messagesModule->sendSilentMessage($data->object->peer_id, "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \"!candidate\".");
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, выборы уже проходят.");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
}

function government_referendum_stop($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);
	if($data->object->from_id == $gov["parliament_id"]){
		if($gov["referendum"] === false)
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, сейчас не проходят выборы.");
		else{
			$gov["referendum"] = false;
			government_api_setdata($db, $gov);
			$db->save();
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, выборы остановлены.");
		}
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
}

function government_referendum_candidate($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	$referendum = $gov["referendum"];
	if($referendum !== false){
		$date = time(); // Переменная времени

		if($gov["previous_president_id"] == $data->object->from_id){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы не можете балотироваться на второй срок.");
			return;
		}

		if($referendum["candidate1"]["id"] != $data->object->from_id && $referendum["candidate2"]["id"] != $data->object->from_id){
			if($referendum["candidate1"]["id"] == 0){
				$referendum["candidate1"]["id"] = $data->object->from_id;
				$gov["referendum"] = $referendum;
				government_api_setdata($db, $gov);
				$db->save();
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, вы зарегистрировались как кандидат №1.");
			}
			elseif($referendum["candidate2"]["id"] == 0) {
				$referendum["candidate2"]["id"] = $data->object->from_id;
				$referendum["last_notification_time"] = $date;
				$gov["referendum"] = $referendum;
				government_api_setdata($db, $gov);
				$db->save();
				$msg1 = ", вы зарегистрировались как кандидат №2.";
				$msg2 = "Кандидаты набраны, самое время голосовать. Используй [!vote], чтобы учавствовать в голосовании.";
				vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg1}','disable_mentions':true});return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg2}'});");
			}
			else
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, кандидаты уже набраны.");
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, вы уже зарегистрированы как кандидат в президенты.");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, сейчас не проходят выборы.");
}

function government_referendum_system($data, $db){
	$gov = government_api_getdata($db);
	$referendum = $gov["referendum"];
	if($referendum !== false){
		$date = time(); // Переменная времени
		if($date - $referendum["start_time"] >= 18000) {
			if($referendum["candidate1"]["id"] == 0 || $referendum["candidate2"]["id"] == 0){
				$gov["referendum"] = false;
				government_api_setdata($db, $gov);
				$msg = "❗Выборы прерваны. Причина: не набрано нужно количество кандидатов.";
				vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			} else {
				$candidate1_voters_count = $referendum["candidate1"]["voters_count"];
				$candidate2_voters_count = $referendum["candidate2"]["voters_count"];
				$all_voters_count = sizeof($referendum["all_voters"]);
				if($candidate1_voters_count > $candidate2_voters_count){
					$candidate_id = $referendum["candidate1"]["id"];
					$candidate_percent = round($candidate1_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("var users=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});var sex_word='Он';if(users[0].sex==1){sex_word='Она';}var msg='✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen};"));
					$ranksys = new RankSystem($db);
					if($ranksys->getUserRank($gov["president_id"]) == 2)
						$ranksys->setUserRank($gov["president_id"], 0);
					if($ranksys->getUserRank($candidate_id) == $ranksys->getMinRankValue())
						$ranksys->setUserRank($candidate_id, 2);
					$economy = new Economy\Main($db); // Модуль Экономики
					if($gov["president_id"] != 0)
						$economy->getUser($gov["president_id"])->deleteItem("govdoc", "presidential_certificate");  // Убираем удостоверение президента у предыдущего
					$economy->getUser($candidate_id)->changeItem("govdoc", "presidential_certificate", 1); // Выдаем удостоверение президента новому
					$gov["president_id"] = $candidate_id;
					$gov["presidential_power"] = 100;
					$gov["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					$gov["last_referendum_time"] = time();
					$gov["referendum"] = false;
					government_api_setdata($db, $gov);
				} elseif($candidate1_voters_count < $candidate2_voters_count) {
					$candidate_id = $referendum["candidate2"]["id"];
					$candidate_percent = round($candidate2_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("var users=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});var sex_word='Он';if(users[0].sex==1){sex_word='Она';}var msg='✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen};"));
					$ranksys = new RankSystem($db);
					if($ranksys->getUserRank($gov["president_id"]) == 2)
						$ranksys->setUserRank($gov["president_id"], 0);
					if($ranksys->getUserRank($candidate_id) == $ranksys->getMinRankValue())
						$ranksys->setUserRank($candidate_id, 2);
					$economy = new Economy\Main($db); // Модуль Экономики
					if($gov["president_id"] != 0)
						$economy->getUser($gov["president_id"])->deleteItem("govdoc", "presidential_certificate");  // Убираем удостоверение президента у предыдущего
					$economy->getUser($candidate_id)->changeItem("govdoc", "presidential_certificate", 1);  // Выдаем удостоверение президента новому
					$gov["president_id"] = $candidate_id;
					$gov["presidential_power"] = 100;
					$gov["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					$gov["last_referendum_time"] = time();
					$gov["referendum"] = false;
					government_api_setdata($db, $gov);
				} else {
				$msg = "❗Выборы прерваны. Причина: оба кандидата набрали одинаковое количество голосов.";
				vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				$db->unsetValue(array("government", "referendum"));
				}
			}
		}
		elseif($date - $referendum["last_notification_time"] >= 600){
			$db->setValue(array("government", "referendum", "last_notification_time"), $date);
			if($referendum["candidate1"]["id"] == 0 || $referendum["candidate2"]["id"] == 0){
				$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду [!candidate].";
				vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			} else {
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_callback_button("📝%CANDIDATE1_NAME%", array('referendum_vote', 1), 'primary'),
						vk_callback_button("📝%CANDIDATE2_NAME%", array('referendum_vote', 2), 'primary')
					)
				));
				$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "Кандидаты набраны, самое время голосовать. Используй [!vote], чтобы учавствовать в голосовании или выберите своего кандидата ниже.", 'keyboard' => $keyboard, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
				$request = vk_parse_vars($request, array("CANDIDATE1_NAME", "CANDIDATE2_NAME"));
				$candidate1_id = $referendum["candidate1"]["id"];
				$candidate2_id = $referendum["candidate2"]["id"];
				vk_execute("var users=API.users.get({'user_ids':[{$candidate1_id},{$candidate2_id}]});var CANDIDATE1_NAME=users[0].first_name.substr(0, 2)+'. '+users[0].last_name;var CANDIDATE2_NAME=users[1].first_name.substr(0, 2)+'. '+users[1].last_name;return API.messages.send({$request});");
			}
		}
	}
	else{
		$chatModes = new ChatModes($db);
		if(!$chatModes->getModeValue("auto_referendum"))
			return;
		$date = time();
		$last_referendum_time = $db->getValue(array("government", "last_referendum_time"), 0);
		if($date - $last_referendum_time >= 432000){
			$gov["previous_president_id"] = $gov["president_id"];
			$gov["president_id"] = 0;
			$gov["rally"] = DB_GOVERNMENT_DEFAULT["rally"];
			$gov["referendum"] = array(
				'candidate1' => array('id' => 0, "voters_count" => 0),
				'candidate2' => array('id' => 0, "voters_count" => 0),
				'all_voters' => array(),
				'start_time' => $date,
				'last_notification_time' => $date
			);
			government_api_setdata($db, $gov);
			$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \\\"!candidate\\\".";
			vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
		}
	}
}

function government_referendum_vote_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$date = time(); // Переменная времени

	$gov = government_api_getdata($db);
	$referendum = $gov["referendum"];

	$candidate = bot_get_array_value($payload, 1, 0);
	if(is_numeric($candidate)){
		if($candidate == 0){
			$keyboard = vk_keyboard_inline(array());
			$messagesModule = new Bot\Messages($db);
			$messagesModule->setAppealID($data->object->user_id);
			$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, "❗Меню голосования закрыто.", array('keyboard' => $keyboard));
			return;
		}

		if($referendum === false){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Сейчас не проходят выборы.");
			return;
		}

		if($date - $referendum["start_time"] >= 18000){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Истекло время голосования.");
			return;
		}

		for($i = 0; $i < sizeof($referendum["all_voters"]); $i++){
			if($referendum["all_voters"][$i] == $data->object->user_id){
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы уже голосовали.");
				return;
			}
		}

		if($candidate == 1){
			$referendum["all_voters"][] = $data->object->user_id;
			$referendum["candidate1"]["voters_count"] = $referendum["candidate1"]["voters_count"] + 1;
			$gov["referendum"] = $referendum;
			government_api_setdata($db, $gov);
			$db->save();
			$candidate_id = $referendum["candidate1"]["id"];
			vk_execute("var user=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_acc,last_name_acc'});var msg='📝 Вы проголосовали за '+user[0].first_name_acc+' '+user[0].last_name_acc+'.';return API.messages.sendMessageEventAnswer({'event_id':'{$data->object->event_id}','user_id':{$data->object->user_id},'peer_id':{$data->object->peer_id},'event_data':'{\"type\":\"show_snackbar\",\"text\":\"'+msg+'\"}'});");

		}
		elseif($candidate == 2){
			$referendum["all_voters"][] = $data->object->user_id;
			$referendum["candidate2"]["voters_count"] = $referendum["candidate2"]["voters_count"] + 1;
			$gov["referendum"] = $referendum;
			government_api_setdata($db, $gov);
			$db->save();
			$candidate_id = $referendum["candidate2"]["id"];
			vk_execute("var user=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_acc,last_name_acc'});var msg='📝 Вы проголосовали за '+user[0].first_name_acc+' '+user[0].last_name_acc+'.';return API.messages.sendMessageEventAnswer({'event_id':'{$data->object->event_id}','user_id':{$data->object->user_id},'peer_id':{$data->object->peer_id},'event_data':'{\"type\":\"show_snackbar\",\"text\":\"'+msg+'\"}'});");
		}
		else
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
	}
}

function government_referendum_vote_cmd($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);
	$referendum = $gov["referendum"];
	if($referendum !== false){
		if($referendum["candidate1"]["id"] != 0 && $referendum["candidate2"]["id"] != 0){
			$candidate1_id = $referendum["candidate1"]["id"];
			$candidate2_id = $referendum["candidate2"]["id"];

			$keyboard = vk_keyboard_inline(array(
				array(
					vk_callback_button("📝%CANDIDATE1_NAME%", array('referendum_vote', 1), 'primary'),
					vk_callback_button("📝%CANDIDATE2_NAME%", array('referendum_vote', 2), 'primary')
				),
				array(
					vk_callback_button("Закрыть", array('referendum_vote'), 'negative')
				)
			));

			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%msg%", 'keyboard' => $keyboard, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("CANDIDATE1_NAME", "CANDIDATE2_NAME", "msg"));

			vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var users=API.users.get({'user_ids':[{$candidate1_id},{$candidate2_id}]});var CANDIDATE1_NAME=users[0].first_name.substr(0, 2)+'. '+users[0].last_name;var CANDIDATE2_NAME=users[1].first_name.substr(0, 2)+'. '+users[1].last_name;var msg=appeal+', учавствуй в выборах президента. Просто нажми на кнопку понравившегося тебе кандидата и ты отдашь за него свой голос. Список кандидатов:\\n✅@id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+')\\n✅@id'+users[1].id+' ('+users[1].first_name+' '+users[1].last_name+')';return API.messages.send({$request});");
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, кандидаты еще не набраны. Вы можете балотироваться в президенты, использовав команду \"!candidate\".");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, сейчас не проходят выборы.");
}

?>