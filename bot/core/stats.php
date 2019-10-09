<?php

define('STATS_SWEAR_WORDS', array("педик","гандон","идиот","ебл","ёб","ублюд","шлюх","шалав","твар","дерьмо","хуе","урод","еба","ёба","сук","пидр","пидар","бля","пизд","хуи","хуй","манд")); // Константа корней матных слов

function stats_update($data, $words_tmp, &$db){
	if(!array_key_exists('stats', $db)){
		$db["stats"] = array(
			//'word_stats' => array(),
			'user_word_count' => array(),
			'user_msg_count' => array(),
			'total_word_count' => 0,
			'swear_word_count' => 0
		);
	}

	$stats = &$db["stats"];

	$chatModes = new ChatModes($db);
	if(!$chatModes->getModeValue("stats_enabled"))
		return 0;

	if(is_null($stats))
		$stats = array();

	if($data->object->text == "") // Отключение ведения статистики, если текст сообщения пустой
		return 0;

	$words = array();

	for($i = 0; $i < count($words_tmp); $i++){
		$exploded_words = explode("\n", $words_tmp[$i]);
		for($j = 0; $j < count($exploded_words); $j++){
			if($exploded_words[$j] != "")
				$words[] = mb_strtolower($exploded_words[$j]);
		}
	}
	unset($words_tmp);

	/*
	if(array_key_exists("word_stats", $stats)){
		$indexing_words = array_keys($stats["word_stats"]);
		for($i = 0; $i < count($indexing_words); $i++){
			for($j = 0; $j < count($words); $j++){
				if($indexing_words[$i] == $words[$j]){
					$stats["word_stats"][$indexing_words[$i]] = $stats["word_stats"][$indexing_words[$i]] + 1;
				}
			}
		}
	}
	*/

	if(!array_key_exists("id{$data->object->from_id}", $stats["user_word_count"]))
		$stats["user_word_count"]["id{$data->object->from_id}"] = 0;
	$stats["user_word_count"]["id{$data->object->from_id}"] = $stats["user_word_count"]["id{$data->object->from_id}"] + count($words); // Кол-ва написанных слов пользователем в беседе

	if(!array_key_exists("id{$data->object->from_id}", $stats["user_msg_count"]))
		$stats["user_msg_count"]["id{$data->object->from_id}"] = 0;
	$stats["user_msg_count"]["id{$data->object->from_id}"] = $stats["user_msg_count"]["id{$data->object->from_id}"] + 1; // Кол-во написанных сообщений пользователем в беседе

	$stats["total_word_count"] = $stats["total_word_count"] + count($words); // Кол-во всего слов в беседе

	$swear_word_count = 0;
	for($i = 0; $i < count(STATS_SWEAR_WORDS); $i++){
		$swear_word_count = $swear_word_count + mb_substr_count(mb_strtolower($data->object->text), STATS_SWEAR_WORDS[$i]);
	}
	$stats["swear_word_count"] = $stats["swear_word_count"] + $swear_word_count;
}

function stats_cmd_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";

	$botModule = new BotModule($db);

	switch ($command) {
		case 'get':
			if(array_key_exists("stats", $db)){
				//$word_stats_db = $db["stats"]["word_stats"];
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

				//arsort($word_stats_db);
				arsort($user_word_count_db);
				arsort($user_msg_count_db);

				/*$word_stats_tmp = array();
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
				unset($word_stats_tmp);*/

				$user_word_count_tmp = array();
				foreach ($user_word_count_db as $key => $value) {
					$user_word_count_tmp[] = array(
						'id' => mb_substr($key, 2),
						'count' => $value
					);
				}
				$user_word_count = array();
				for($i = 0; $i < count($user_word_count_tmp) && $i < 10; $i++){
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
				for($i = 0; $i < count($user_msg_count_tmp) && $i < 10; $i++){
					$user_msg_count[] = $user_msg_count_tmp[$i];
				}
				unset($user_msg_count_tmp);

				//$word_stats_json = json_encode($word_stats, JSON_UNESCAPED_UNICODE);
				$user_msg_count_json = json_encode($user_msg_count, JSON_UNESCAPED_UNICODE);
				$user_word_count_json = json_encode($user_word_count, JSON_UNESCAPED_UNICODE);

				vk_execute($botModule->makeExeAppeal($data->object->from_id).""
					//var word_stats = {$word_stats_json};
					."var user_word_count = {$user_word_count_json};
					var user_msg_count = {$user_msg_count_json};
					var total_word_count = {$total_word_count};
					var swear_word_count = {$swear_word_count};
					var swear_percent = {$swear_percent};

					var msg = appeal+', статистика беседы:';

					msg = msg + '\\n\\n✅Всего слов в беседе: '+total_word_count+' слов(а)\\n&#12288;• Из них '+swear_word_count+' ('+swear_percent+'%) мат. слов(а)';"

					//msg = msg + '\\n\\n✅Топ 10 популярных индексируемых слов:';
					//if(word_stats.length != 0){
					//	var i = 0; while(i < word_stats.length){
					//		msg = msg + '\\n&#12288;• '+word_stats[i].word+' — '+word_stats[i].count+' раз(а)';
					//		i = i + 1;
					//	}
					//}
					//else{
					//	msg = msg + '\\n&#12288;⛔В беседе нет популярных слов!';
					//}

					."msg = msg + '\\n\\n✅Топ 10 пользователя по количеству слов:';

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

					msg = msg + '\\n\\n✅Топ 10 пользователя по количеству сообщений:';

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

		/*case 'indexing-words':
			$stats = &$db["stats"];
			$ranksys = new RankSystem($db);

			if(array_key_exists(2, $words)){
				$command = mb_strtolower($words[2]);
			}
			else{
				$command = "";
			}

			switch ($command) {
				case 'add':
					if(!$ranksys->checkRank($data->object->from_id, 1)){
						$botModule->sendSystemMsg_NoRights($data);
						return 0;
					}

					if(array_key_exists(3, $words)){
						$new_words = array();
						for($i = 3; $i < count($words); $i++){
							$new_words[] = $words[$i];
						}
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите слово(а).", $data->object->from_id);
						return 0;
					}

					$added_words = array();
					for($i = 0; $i < count($new_words); $i++){
						$new_word = $new_words[$i];
						if(!array_key_exists($new_word, $stats["word_stats"])){
							$stats["word_stats"][$new_word] = 0;
							$added_words[] = $new_word;
						}
					}
					$str_list = "";
					for($i = 0; $i < count($added_words); $i++){
						if($str_list == "")
							$str_list = "[{$added_words[$i]}]";
						else
							$str_list = $str_list . ", [{$added_words[$i]}]";
					}

					if(count($added_words) > 0)
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Следующие слова теперь индексируются:\n{$str_list}", $data->object->from_id);
					else
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Ни одно слово не было добавлено.", $data->object->from_id);

					break;

				case 'del':
					if(array_key_exists(3, $words)){
						$del_words = array();
						for($i = 3; $i < count($words); $i++){
							$del_words[] = $words[$i];
						}
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите слово(а).", $data->object->from_id);
						return 0;
					}

					$deleted_words = array();
					for($i = 0; $i < count($del_words); $i++){
						$del_word = $del_words[$i];
						if(array_key_exists($del_word, $stats["word_stats"])){
							unset($stats["word_stats"][$del_word]);
							$deleted_words[] = $del_word;
						}
					}
					$str_list = "";
					for($i = 0; $i < count($deleted_words); $i++){
						if($str_list == "")
							$str_list = "[{$deleted_words[$i]}]";
						else
							$str_list = $str_list . ", [{$deleted_words[$i]}]";
					}

					if(count($deleted_words) > 0)
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Следующие слова больше не индексируются:\n{$str_list}", $data->object->from_id);
					else
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Ни одно слово не было удалено.", $data->object->from_id);

					break;

				case 'list':
					$indexing_words_list = array_keys($stats["word_stats"]);

					if(count($indexing_words_list) == 0){
						$botModule->sendSimpleMessage($data->object->peer_id, ", в беседе нет индекируемымых слов.", $data->object->from_id);
						return 0;
					}
					$str_list = "";
					for($i = 0; $i < count($indexing_words_list); $i++){
						if($str_list == "")
							$str_list = "[{$indexing_words_list[$i]}]";
						else
							$str_list = $str_list . ", [{$indexing_words_list[$i]}]";
					}
					$botModule->sendSimpleMessage($data->object->peer_id, ", 📝список индекируемымых слов:\n".$str_list, $data->object->from_id);
					break;

				case 'info':
					if(array_key_exists(3, $words)){
						$word = $words[3];
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите слово.", $data->object->from_id);
						return 0;
					}

					if(array_key_exists($word, $stats["word_stats"])){
						$used_count = $stats["word_stats"][$word];
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Слово \"{$word}\" было использовано {$used_count} раз(а).", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Указанное слово не индексируется.", $data->object->from_id);
						return 0;
					}
					break;
				
				default:
					$botModule->sendCommandListFromArray($data, ", ⛔используйте:", array(
					'!stats indexing-words add [w1] [w2] [w3]... - Добавление слов в список индекируемымых',
					'!stats indexing-words del [w1] [w2] [w3]... - Удаление слов из списка индекируемымых',
					'!stats indexing-words info [word] - Информация о слове',
					'!stats indexing-words list - Список индекируемымых слов'
					));
					break;
			}
			break;
		*/
		
		default:
			$botModule->sendCommandListFromArray($data, ", ⛔используйте:", array(
				'!stats get - Показывает статистику'//,
				//'!stats indexing-words - Управление индекируемыми словами'
			));
			break;
	}
}

?>