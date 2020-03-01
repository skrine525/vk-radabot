<?php

define('STATS_SWEAR_WORDS', array("педик","гандон","идиот","ебл","ёб","ублюд","шлюх","шалав","твар","дерьмо","хуе","урод","еба","ёба","сук","пидр","пидар","бля","пизд","хуи","хуй","манд")); // Константа корней матных слов

// Стандартное значение статистики пользователя
define('STATS_DEFAULT',array(
		'msg_count' => 0,
		'msg_count_in_succession' => 0,
		'simbol_count' => 0,
		'audio_msg_count' => 0,
		'photo_count' => 0,
		'audio_count' => 0,
		'video_count' => 0,
		'sticker_count' => 0
	));

function stats_update($data, &$db){
	$db->unsetValue(array("stats")); // Удаление старой статы 1
	$db->unsetValue(array("user_stats")); // Удаление старой статы 2
	$db->unsetValue(array("bot_manager", "chat_modes", "stats_enabled"));

	$stats = $db->getValue(array("chat_stats", "users", "id{$data->object->from_id}"), STATS_DEFAULT);
	$last_message_user_id = $db->getValue(array("chat_stats", "last_message_user_id"), 0);

	foreach (STATS_DEFAULT as $key => $value) {
		if(!array_key_exists($key, $stats))
			$stats[$key] = $value;
	}

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

	$db->setValue(array("chat_stats", "users", "id{$data->object->from_id}"), $stats);
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(array_key_exists(1, $words) && bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(array_key_exists(1, $words) && is_numeric($words[1])) {
		$member_id = intval($words[1]);
	} else $member_id = $data->object->from_id;

	$stats = $db->getValue(array("chat_stats", "users", "id{$member_id}"), STATS_DEFAULT);

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
		$msg = ", статистика:\n📧Сообщений: {$stats["msg_count"]}\n&#12288;📝Подряд: {$stats["msg_count_in_succession"]}\n🔍Символов: {$stats["simbol_count"]}\n📟Гол. сообщений: {$stats["audio_msg_count"]}\n\n📷Фотографий: {$stats["photo_count"]}\n📹Видео: {$stats["video_count"]}\n🎧Аудиозаписей: {$stats["audio_count"]}\n🤡Стикеров: {$stats["sticker_count"]}\n\n👑Активность: {$rating_text}";
	else
		$msg = ", статистика @id{$member_id} (пользователя):\n📧Сообщений: {$stats["msg_count"]}\n🔍Символов: {$stats["simbol_count"]}\n📟Гол. сообщений: {$stats["audio_msg_count"]}\n\n📷Фотографий: {$stats["photo_count"]}\n📹Видео: {$stats["video_count"]}\n🎧Аудиозаписей: {$stats["audio_count"]}\n🤡Стикеров: {$stats["sticker_count"]}\n\n👑Активность: {$rating_text}";

	$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
}

?>