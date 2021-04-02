<?php

////////////////////////////////////////////////////////////////////////////////////////////////////
// Stats API

// Стандартное значение статистики пользователя
define('DB_STATS_DEFAULT',array(
	'msg_count' => 0,
	'msg_count_in_succession' => 0,
	'simbol_count' => 0,
	'audio_msg_count' => 0,
	'photo_count' => 0,
	'audio_count' => 0,
	'video_count' => 0,
	'sticker_count' => 0,
	'bump_count' => 0,
	'command_used_count' => 0,
	'button_pressed_count' => 0
));

// Получение статистики пользователя
function stats_api_getuser($db, $user_id){
	$db_stats = $db->getValue(array("chat_stats", "users", "id{$user_id}"), array());
	$stats = array();
	foreach (DB_STATS_DEFAULT as $key => $value) {
		if(array_key_exists($key, $db_stats))
			$stats[$key] = $db_stats[$key];
		else
			$stats[$key] = $value;
	}
	return $stats;
}

// Сохранение статистики пользователя
function stats_api_setuser($db, $user_id, $value){
	return $db->setValue(array("chat_stats", "users", "id{$user_id}"), $value);
}

////////////////////////////////////////////////////////////////////////////////////////////////////

// Инициализация команд
function stats_initcmd($event){
	$event->addTextMessageCommand("!стата", 'stats_cmd_handler');
	$event->addTextMessageCommand("!рейтинг", 'stats_rating_cmd_handler');
}

function stats_update_messageevent($event, $data, $db){
	if(property_exists($data->object, "payload") && gettype($data->object->payload) == 'array' && array_key_exists(0, $data->object->payload) && $event->isCallbackButtonCommand($data->object->payload[0])){
		$stats = stats_api_getuser($db, $data->object->user_id);
		$stats["button_pressed_count"]++;
		stats_api_setuser($db, $data->object->user_id, $stats);
	}
}

function stats_update_messagenew($event, $data, $db){
	// Запрет собирать статистику от сообщений других ботов
	if($data->object->from_id < 0)
		return;

	$stats = stats_api_getuser($db, $data->object->from_id);
	$last_message_user_id = $db->getValue(array("chat_stats", "last_message_user_id"), 0);

	if($last_message_user_id == $data->object->from_id)
		$stats["msg_count_in_succession"]++;
	else
		$db->setValue(array("chat_stats", "last_message_user_id"), $data->object->from_id);

	$stats["msg_count"]++; // Увеличиваем количество сообщений
	$stats["simbol_count"] += mb_strlen($data->object->text);

	foreach ($data->object->attachments as $attachment) {
		switch ($attachment->type) {
			case 'sticker':
				$stats["sticker_count"]++;
				break;

			case 'photo':
				$stats["photo_count"]++;
				break;

			case 'video':
				$stats["video_count"]++;
				break;

			case 'audio_message':
				$stats["audio_msg_count"]++;
				break;

			case 'audio':
				$stats["audio_count"]++;
				break;
		}
	}

	if(property_exists($data->object, "payload") && !is_null($data->object->payload)){
		$payload = (object) json_decode($data->object->payload);
		if(property_exists($payload, "command") && $event->isTextButtonCommand($payload->command))
			$stats['button_pressed_count']++;
	}
	else{
		$argv = bot_parse_argv($data->object->text); // Извлекаем аргументы из сообщения
		if(array_key_exists(0, $argv) && $event->isTextMessageCommand($argv[0])){
			$stats["command_used_count"]++;
		}
	}

	stats_api_setuser($db, $data->object->from_id, $stats);
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = mb_strtolower(bot_get_array_value($argv, 1, ""));

	if($command == "обнулить"){
		$permissionSystem = new PermissionSystem($db);
		if($permissionSystem->checkUserPermission($data->object->from_id, 'customize_chat')){ // Проверка разрешения
			$db->unsetValue(array('chat_stats'));
			$db->save();
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Статистика обнулена.");
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
	}
	elseif($command == 'помощь'){
		$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, используйте:", array(
			'!cтата <пользователь> - Показать статистику',
			'!cтата <пересланное сообщение> - Показывает статистику пользователя',
			'!cтата обнулить - Обнуляит статистику беседы' 
		));
	}
	else{
		if(array_key_exists(0, $data->object->fwd_messages))
			$member_id = $data->object->fwd_messages[0]->from_id;
		elseif(bot_get_userid_by_mention($command, $member_id)){}
		elseif(bot_get_userid_by_nick($db, $command, $member_id)){}
		elseif(is_numeric($command))
			$member_id = intval($command);
		else $member_id = $data->object->from_id;

		if($member_id <= 0){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Пользователь указан неверно.");
			return;
		}

		$stats = stats_api_getuser($db, $member_id);
		$all_stats = $db->getValue(array("chat_stats", "users"), array());

		$rating = array();
		foreach ($all_stats as $key => $value) {
			$rating[$key] = $value["msg_count"] - $value["msg_count_in_succession"];
		}
		arsort($rating);
		$position = array_search("id{$member_id}", array_keys($rating));
		if($position !== false){
			$position++;
			$rating_text = "{$position} место";
		}
		else
			$rating_text = "Нет данных";

		if($data->object->from_id == $member_id)
			$pre_msg = "%appeal%, статистика:";
		else
			$pre_msg = "%appeal%, статистика @id{$member_id} (пользователя):";
		$msg = "{$pre_msg}\n📧Сообщений: {$stats["msg_count"]}\n&#12288;📝Подряд: {$stats["msg_count_in_succession"]}\n🔍Символов: {$stats["simbol_count"]}\n📟Гол. сообщений: {$stats["audio_msg_count"]}\n\n📷Фотографий: {$stats["photo_count"]}\n📹Видео: {$stats["video_count"]}\n🎧Аудиозаписей: {$stats["audio_count"]}\n🤡Стикеров: {$stats["sticker_count"]}\n\n🛠Команд выполнено: {$stats["command_used_count"]}\n🔘Нажато кнопок: {$stats["button_pressed_count"]}\n👊🏻Получено люлей: {$stats["bump_count"]}\n\n👑Активность: {$rating_text}";
		$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
	}
}

function stats_rating_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$list_number = bot_get_array_value($argv, 1, 1);

	$all_stats = $db->getValue(array("chat_stats", "users"), []);

	if(count($all_stats) == 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Статистика пуста.");
		return;
	}

	$rating = [];
	foreach ($all_stats as $key => $value) {
		$rating[$key] = $value["msg_count"] - $value["msg_count_in_succession"];
	}
	arsort($rating);

	$rating_list = [];
	foreach ($rating as $key => $value) {
		$rating_list[] = [
			'u' => intval(mb_substr($key, 2)),
			'r' => $value
		];
	}

	$listBuilder = new Bot\ListBuilder($rating_list, 20);
	$builded_list = $listBuilder->build($list_number);
	$vkjson = json_encode($builded_list->list->out, JSON_UNESCAPED_UNICODE);
	if($builded_list->result){
		vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var rating={$vkjson};var users=API.users.get({user_ids:rating@.u});var msg=appeal+', Рейтинг [{$builded_list->list->number}/{$builded_list->list->max_number}]:';var i=0;while(i<rating.length){var n=i+1;var sign='👤';if(n<=3){sign='👑';}msg=msg+'\\n'+n+'. '+sign+'@id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+')';i=i+1;}API.messages.send({peer_id:{$data->object->peer_id},message:msg,disable_mentions:true});");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Не удалось создать список.");
}

?>