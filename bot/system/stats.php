<?php

////////////////////////////////////////////////////////////////////////////////////////////////////
// Stats API

class StatsManager{
	private $db;
	private $last_message_user_id;
	private $updateObject;
	private $updateStats;
	private $current_day;

	const STATS_DEFAULT = [
		// Статистика сообщений
		'msg_count' => 0,
		'msg_count_in_succession' => 0,
		'simbol_count' => 0,
		'audio_msg_count' => 0,
		'photo_count' => 0,
		'audio_count' => 0,
		'video_count' => 0,
		'sticker_count' => 0,
		// Статистика команд
		'command_used_count' => 0,
		'button_pressed_count' => 0,
		// РП статистика
		'bump_count' => 0,
		//'pee_count' => 0,
		//'crap_count' => 0,
		//'hark_count' => 0,
		//'gofuck_count' => 0,
		//'puckingup_count' => 0,
		//'castrate_count' => 0
	];

	function __construct(Database\Manager $db){
		$this->db = $db;
		$this->updateObject = [];

		$time = time();
		$this->current_day = $time - ($time % 86400);

		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, 'chat_stats.last_message_user_id' => 1, 'chat_stats.last_daily_time' => 1]]);
		$extractor = $db->executeQuery($query);
	  	$this->last_message_user_id = $extractor->getValue("0.chat_stats.last_message_user_id", 0);
	  	$last_daily_time = $extractor->getValue("0.chat_stats.last_daily_time", 0);

	  	if($time - $last_daily_time >= 86400){
			$this->updateObject['$set']['chat_stats.last_daily_time'] = $this->current_day;
			if($last_daily_time > 0)
				$this->updateObject['$unset']["chat_stats.users_daily.time{$last_daily_time}"] = 0;
		}
	}

	public function getLastMessageUserID(){
		return $this->last_message_user_id;
	}

	public function update(string $stat_name, int $inc_number){
		if($stat_name == "" || $inc_number == 0)
			return false;

		$this->updateStats[$stat_name] = $inc_number;
		return true;
	}

	public function commit(int $user_id){
		if($user_id <= 0)
			return false;

		foreach (self::STATS_DEFAULT as $key => $value){
			if(array_key_exists($key, $this->updateStats)){
				$this->updateObject['$inc']["chat_stats.users.id{$user_id}.{$key}"] = $this->updateStats[$key];
				$this->updateObject['$inc']["chat_stats.users_daily.time{$this->current_day}.id{$user_id}.{$key}"] = $this->updateStats[$key];
			}
			else{
				$this->updateObject['$inc']["chat_stats.users.id{$user_id}.{$key}"] = 0;
				$this->updateObject['$inc']["chat_stats.users_daily.time{$this->current_day}.id{$user_id}.{$key}"] = 0;
			}
		}
		if($user_id != $this->last_message_user_id)
			$this->updateObject['$set']['chat_stats.last_message_user_id'] = $user_id;

		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $this->db->getDocumentID()], $this->updateObject);
		$this->db->executeBulkWrite($bulk);
		return true;
	}
}

////////////////////////////////////////////////////////////////////////////////////////////////////

// Инициализация команд
function stats_initcmd($event){
	$event->addTextMessageCommand("!стата", 'stats_cmd_handler');
	$event->addTextMessageCommand("!рейтинг", 'stats_rating_cmd_handler');
}

function stats_update_messageevent($event, $data, $db){
	if(property_exists($data->object, "payload") && gettype($data->object->payload) == 'array' && array_key_exists(0, $data->object->payload) && $event->isCallbackButtonCommand($data->object->payload[0])){
		$statsManager = new StatsManager($db);
		$statsManager->update("button_pressed_count", 1);
		$statsManager->commit($data->object->user_id);
	}
}

function stats_update_messagenew($event, $data, $db){
	// Запрет собирать статистику от сообщений других ботов
	if($data->object->from_id < 0)
		return;

	$time = time();

	$statsManager = new StatsManager($db);

	$stats = [];
	if($statsManager->getLastMessageUserID() == $data->object->from_id)
		$stats["msg_count_in_succession"] = 1;

	$stats["msg_count"] = 1; // Увеличиваем количество сообщений
	$stats["simbol_count"] = mb_strlen($data->object->text);

	foreach ($data->object->attachments as $attachment) {
		switch ($attachment->type) {
			case 'sticker':
				$stats["sticker_count"] = 1;
				break;

			case 'photo':
				$stats["photo_count"] = 1;
				break;

			case 'video':
				$stats["video_count"] = 1;
				break;

			case 'audio_message':
				$stats["audio_msg_count"] = 1;
				break;

			case 'audio':
				$stats["audio_count"] = 1;
				break;
		}
	}

	if(property_exists($data->object, "payload")){
		$payload = (object) json_decode($data->object->payload);
		if(property_exists($payload, "command") && $event->isTextButtonCommand($payload->command))
			$stats['button_pressed_count'] = 1;
	}
	else{
		$argv = bot_parse_argv($data->object->text); // Извлекаем аргументы из сообщения
		if(array_key_exists(0, $argv) && $event->isTextMessageCommand($argv[0])){
			$stats["command_used_count"] = 1;
		}
	}

	foreach ($stats as $key => $value) {
		$statsManager->update($key, $value);
	}

	$statsManager->commit($data->object->from_id);
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = mb_strtolower(bot_get_array_value($argv, 1, ""));

	if($command == "" || $command == 'дня'){
		if(array_key_exists(0, $data->object->fwd_messages))
			$member_id = $data->object->fwd_messages[0]->from_id;
		else $member_id = $data->object->from_id;

		if($member_id <= 0){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Пользователь указан неверно.");
			return;
		}

		if($command == ''){
			$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, 'chat_stats.users' => 1]]);
		 	$extractor = $db->executeQuery($query);
		  	$all_stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users", []));
		  	$stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users.id{$member_id}", StatsManager::STATS_DEFAULT));

		  	if($data->object->from_id == $member_id)
				$pre_msg = "%appeal%, статистика:";
			else
				$pre_msg = "%appeal%, статистика @id{$member_id} (пользователя):";
		}
		else{
			$time = time();									// Переменная текущего времени
			$current_day = $time - ($time % 86400);			// Переменная текущей даты (00:00 GMT)

			$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_stats.users_daily.time{$current_day}" => 1]]);
		 	$extractor = $db->executeQuery($query);
		  	$all_stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users_daily.time{$current_day}", []));
		  	$stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users_daily.time{$current_day}.id{$member_id}", StatsManager::STATS_DEFAULT));

		  	if($data->object->from_id == $member_id)
				$pre_msg = "%appeal%, статистика дня:";
			else
				$pre_msg = "%appeal%, статистика дня @id{$member_id} (пользователя):";
		}

		$rating = array();
		foreach ($all_stats as $key => $value){
			$user = array_merge(StatsManager::STATS_DEFAULT, $value);
			$rating[$key] = $user["msg_count"] - $user["msg_count_in_succession"];
		}
		arsort($rating);
		$position = array_search("id{$member_id}", array_keys($rating));
		if($position !== false){
			$position++;
			$rating_text = "{$position} место";
		}
		else
			$rating_text = "Нет данных";

		$basic_info = "\n📧Сообщений: {$stats["msg_count"]}\n&#12288;📝Подряд: {$stats["msg_count_in_succession"]}\n🔍Символов: {$stats["simbol_count"]}\n📟Гол. сообщений: {$stats["audio_msg_count"]}";
		$attachment_info = "\n\n📷Фотографий: {$stats["photo_count"]}\n📹Видео: {$stats["video_count"]}\n🎧Аудиозаписей: {$stats["audio_count"]}\n🤡Стикеров: {$stats["sticker_count"]}";
		$cmd_info = "\n\n🛠Команд выполнено: {$stats["command_used_count"]}\n🔘Нажато кнопок: {$stats["button_pressed_count"]}";
		$rp_info = "\n\n🗣РП:\n&#12288;👊🏻Ударен: {$stats["bump_count"]}";
		//$rp_info = "\n\n🗣РП:\n&#12288;👊🏻Ударен: {$stats["bump_count"]}\n&#12288;💦Обоссан: {$stats["pee_count"]}\n&#12288;💩Обосран: {$stats["crap_count"]}\n&#12288;🤮Обхаркан: {$stats["hark_count"]}\n&#12288;😡Послан: {$stats["gofuck_count"]}\n&#12288;🤢Облёван: {$stats["puckingup_count"]}\n&#12288;🙊Кастрирован: {$stats["castrate_count"]}";
		$rating_info = "\n\n👑Активность: {$rating_text}";
		$messagesModule->sendSilentMessage($data->object->peer_id, "{$pre_msg}{$basic_info}{$attachment_info}{$cmd_info}{$rp_info}{$rating_info}");
	}
	else{
		$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, используйте:", array(
			'!cтата - Статистика за все время',
			'!стата дня - Статистика за день',
			'!cтата <пересланное сообщение> - Статистика пользователя за все время',
			'!стата дня <пересланное сообщение> - Статистика пользователя за день'
		));
	}
}

function stats_rating_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$arg1 = bot_get_array_value($argv, 1, 1);
	if(is_numeric($arg1)){
		$list_number = intval($arg1);
		$day_word = "";

		$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, 'chat_stats.users' => 1]]);
	 	$extractor = $db->executeQuery($query);
	  	$all_stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users", []));
	}
	else{
		$command = mb_strtolower($arg1);
		if($command == 'дня'){
			$list_number = intval(bot_get_array_value($argv, 2, 1));
			$day_word = ' дня';

			$time = time();									// Переменная текущего времени
			$current_day = $time - ($time % 86400);			// Переменная текущей даты (00:00 GMT)

			$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_stats.users_daily.time{$current_day}" => 1]]);
		 	$extractor = $db->executeQuery($query);
		  	$all_stats = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.chat_stats.users_daily.time{$current_day}", []));
		}
		else{
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, используйте:", array(
				'!рейтинг <лист> - Рейтинг за все время',
				'!рейтинг дня <лист> - Рейтинг за день'
			));
			return;
		}
	}

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

	$list_size = 20;
	$listBuilder = new Bot\ListBuilder($rating_list, $list_size);
	$builded_list = $listBuilder->build($list_number);
	$vkjson = json_encode($builded_list->list->out, JSON_UNESCAPED_UNICODE);
	if($builded_list->result){
		vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var rating={$vkjson};var users=API.users.get({user_ids:rating@.u});var msg=appeal+', Рейтинг{$day_word} [{$builded_list->list->number}/{$builded_list->list->max_number}]:';var i=0;while(i<rating.length){var n=i+1+({$builded_list->list->number}-1)*{$list_size};var sign='👤';if(n<=3){sign='👑';}msg=msg+'\\n'+n+'. '+sign+'@id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+')';i=i+1;}API.messages.send({peer_id:{$data->object->peer_id},message:msg,disable_mentions:true});");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Не удалось создать список.");
}

?>