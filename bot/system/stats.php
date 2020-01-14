<?php

define('STATS_SWEAR_WORDS', array("педик","гандон","идиот","ебл","ёб","ублюд","шлюх","шалав","твар","дерьмо","хуе","урод","еба","ёба","сук","пидр","пидар","бля","пизд","хуи","хуй","манд")); // Константа корней матных слов

// Стандартное значение статистики пользователя
define('STATS_DEFAULT',array(
		'msg_count' => 0,
		'simbol_count' => 0,
		'audio_msg_count' => 0,
		'photo_count' => 0,
		'audio_count' => 0,
		'video_count' => 0,
		'sticker_count' => 0
	));

function stats_update($data, &$db){
	$db->unsetValue(array("stats")); // Удаление старой статы
	$db->unsetValue(array("bot_manager", "chat_modes", "stats_enabled"));

	$stats = $db->getValue(array("user_stats", "id{$data->object->from_id}"), STATS_DEFAULT);

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

	$db->setValue(array("user_stats", "id{$data->object->from_id}"), $stats);
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);

	$stats = $db->getValue(array("user_stats", "id{$data->object->from_id}"), STATS_DEFAULT);

	$all_stats = $db->getValue(array("user_stats"), array());

	$rating = array();
	foreach ($all_stats as $key => $value) {
		$rating[$key] = ($value["msg_count"] + $value["photo_count"] + $value["video_count"] + $value["audio_count"] + $value["sticker_count"]) / 5;
	}
	arsort($rating);
	$position = array_search("id{$data->object->from_id}", array_keys($rating));
	if($position !== false){
		$position++;
		$rating_text = "{$position} место";
	}
	else
		$rating_text = "Нет данных";

	$msg = ", статистика:\n📧Сообщений: {$stats["msg_count"]}\n🔍Символов: {$stats["simbol_count"]}\n📟Гол. сообщений: {$stats["audio_msg_count"]}\n\n📷Фотографий: {$stats["photo_count"]}\n📹Видео: {$stats["video_count"]}\n🎧Аудиозаписей: {$stats["audio_count"]}\n🤡Стикеров: {$stats["sticker_count"]}\n\n👑Активность: {$rating_text}";

	$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
}

?>