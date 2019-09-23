<?php

function stats_update($data, $words_tmp, &$db){
	$stats = &$db["stats"];

	$chatModes = new ChatModes($db);
	if(!$chatModes->getModeValue("stats_enabled"))
		return 0;

	if(is_null($stats))
		$stats = array();

	if($data->object->text == "") // Отключение ведения статистики, если текст сообщения пустой
		return 0;

	define('SWEAR_WORDS', array("педик","гандон","идиот","ебл","ёб","ублюд","шлюх","шалав","твар","дерьмо","хуе","урод","еба","ёба","сук","пидр","пидар","бля","пизд","хуи","хуй","манд")); // Константа корней матных слов

	$words = array();

	for($i = 0; $i < count($words_tmp); $i++){
		$exploded_words = explode("\n", $words_tmp[$i]);
		for($j = 0; $j < count($exploded_words); $j++){
			if($exploded_words[$j] != "")
				$words[] = mb_strtolower($exploded_words[$j]);
		}
	}
	unset($words_tmp);

	for($i = 0; $i < count($words); $i++){ // Общая ститистика по каждому написанному слову в беседе
		$stats["word_stats"][$words[$i]] = $stats["word_stats"][$words[$i]] + 1;
	}
	$stats["user_word_count"]["id{$data->object->from_id}"] = $stats["user_word_count"]["id{$data->object->from_id}"] + count($words); // Кол-ва написанных слов пользователем в беседе
	$stats["user_msg_count"]["id{$data->object->from_id}"] = $stats["user_msg_count"]["id{$data->object->from_id}"] + 1; // Кол-во написанных сообщений пользователем в беседе
	$stats["total_word_count"] = $stats["total_word_count"] + count($words); // Кол-во всего слов в беседе

	$swear_word_count = 0;
	for($i = 0; $i < count(SWEAR_WORDS); $i++){
		$swear_word_count = $swear_word_count + mb_substr_count(mb_strtolower($data->object->text), SWEAR_WORDS[$i]);
	}
	$stats["swear_word_count"] = $stats["swear_word_count"] + $swear_word_count;
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	$command = mb_strtolower($words[1]);
	$botModule = new BotModule($db);

	switch ($command) {
		case 'get':
			if(array_key_exists("stats", $db)){
				$word_stats_db = $db["stats"]["word_stats"];
				$user_word_count_db = $db["stats"]["user_word_count"];
				$user_msg_count_db = $db["stats"]["user_msg_count"];
				$total_word_count = $db["stats"]["total_word_count"];
				$swear_word_count = $db["stats"]["swear_word_count"];

				if(is_null($total_word_count))
					$total_word_count = 0;
				if(is_null($swear_word_count))
					$swear_word_count = 0;

				if($total_word_count != 0)
					$swear_percent = round(100*$swear_word_count/$total_word_count, 2);
				else
					$swear_percent = 0;

				arsort($word_stats_db);
				arsort($user_word_count_db);
				arsort($user_msg_count_db);

				$word_stats_tmp = array();
				foreach ($word_stats_db as $key => $value) {
					$word_stats_tmp[] = array(
						'word' => $key,
						'count' => $value
					);
				}
				$word_stats = array();
				for($i = 0; $i < count($word_stats_tmp) && $i < 10; $i++){
					$word_stats[] = $word_stats_tmp[$i];
				}
				unset($word_stats_tmp);

				$user_word_count_tmp = array();
				foreach ($user_word_count_db as $key => $value) {
					$user_word_count_tmp[] = array(
						'id' => mb_substr($key, 2),
						'count' => $value
					);
				}
				$user_word_count = array();
				for($i = 0; $i < count($user_word_count_tmp) && $i < 5; $i++){
					$user_word_count[] = $user_word_count_tmp[$i];
				}
				unset($user_word_count_tmp);

				$user_msg_count_tmp = array();
				foreach ($user_msg_count_db as $key => $value) {
					$user_msg_count_tmp[] = array(
						'id' => mb_substr($key, 2),
						'count' => $value
					);
				}
				$user_msg_count = array();
				for($i = 0; $i < count($user_msg_count_tmp) && $i < 5; $i++){
					$user_msg_count[] = $user_msg_count_tmp[$i];
				}
				unset($user_msg_count_tmp);

				$word_stats_json = json_encode($word_stats, JSON_UNESCAPED_UNICODE);
				$user_msg_count_json = json_encode($user_msg_count, JSON_UNESCAPED_UNICODE);
				$user_word_count_json = json_encode($user_word_count, JSON_UNESCAPED_UNICODE);

				vk_execute($botModule->makeExeAppeal($data->object->from_id)."
					var word_stats = {$word_stats_json};
					var user_word_count = {$user_word_count_json};
					var user_msg_count = {$user_msg_count_json};
					var total_word_count = {$total_word_count};
					var swear_word_count = {$swear_word_count};
					var swear_percent = {$swear_percent};

					var msg = appeal+', статистика беседы:';

					msg = msg + '\\n\\n✅Всего слов в беседе: '+total_word_count+' слов(а)\\n&#12288;• Из них '+swear_word_count+' ('+swear_percent+'%) мат. слов(а)';

					msg = msg + '\\n\\n✅Топ 10 популярных слов беседы:';
					if(word_stats.length != 0){
						var i = 0; while(i < word_stats.length){
							msg = msg + '\\n&#12288;• '+word_stats[i].word+' — '+word_stats[i].count+' раз(а)';
							i = i + 1;
						}
					}
					else{
						msg = msg + '\\n&#12288;⛔В беседе нет популярных слов!';
					}

					msg = msg + '\\n\\n✅Топ 5 пользователя по количеству слов:';

					if(user_word_count.length != 0){
						var users = API.users.get({'user_ids':user_word_count@.id});
						var i = 0; while(i < user_word_count.length){
							msg = msg + '\\n&#12288;• @id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') — '+user_word_count[i].count+' слов(а)';
							i = i + 1;
						}
					}
					else{
						msg = msg + '\\n&#12288;⛔В беседе нет данных пользователей!';
					}

					msg = msg + '\\n\\n✅Топ 5 пользователя по количеству сообщений:';

					if(user_msg_count.length != 0){
						var users = API.users.get({'user_ids':user_msg_count@.id});
						var i = 0; while(i < user_msg_count.length){
							msg = msg + '\\n&#12288;• @id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') — '+user_msg_count[i].count+' сообщений(я)';
							i = i + 1;
						}
					}
					else{
						msg = msg + '\\n&#12288;⛔В беседе нет данных пользователей!';
					}

					API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
					");
			}
			else
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔В беседе пока что нет статистики.", $data->object->from_id);
			break;
		
		default:
			$botModule->sendCommandListFromArray($data, ", ⛔используйте:", array(
				'!stats get - Показывает статистику'
			));
			break;
	}
}

?>