<?php

// Инициализация команд
function government_initcmd($event){
	// Правительство
	$event->addTextMessageCommand("!конституция", 'government_constitution');
	$event->addTextMessageCommand("!президент", 'government_president');
	$event->addTextMessageCommand("!законы", 'government_show_laws');
	$event->addTextMessageCommand("!закон", 'government_laws_cpanel');
	$event->addTextMessageCommand("!партия", 'government_batch');
	//$event->addTextMessageCommand("!столица", 'government_capital');
	$event->addTextMessageCommand("!гимн", 'government_anthem');
	//$event->addTextMessageCommand("!флаг", 'government_flag');
	//$event->addTextMessageCommand("!митинг", 'government_rally');

	// Система выборов
	$event->addTextMessageCommand("!выборы", 'government_election_start');
	$event->addTextMessageCommand("!баллотироваться", 'government_election_candidate');
	$event->addTextMessageCommand("!голосовать", 'government_election_vote');

	// Callback-кнопки
	$event->addCallbackButtonCommand('government_batch', 'government_batch_cb');
	$event->addCallbackButtonCommand('government_vote', 'government_election_vote_cb');
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Data

class Goverment{ // Класс констант и работы с ними для модуля government

	// 'ID_идеологии' => ['name' => 'Название_идеологии', 'gov' => ТИП_ПРАВИТЕЛЬСТВА]
	// Типы правительств: 0 - Демократическое, 1 - Авторитаритарное, 2 - Тотальтарное
	const IDEOLOGY = [
		'liberalism' => ['name' => 'Либерализм', 'gov' => 0],
		'socialism' => ['name' => 'Социализм', 'gov' => 0], 
		'monarchism' => ['name' => 'Монархизм', 'gov' => 1],
		'anarchism' => ['name' => 'Анархизм', 'gov' => 1],
		'communism' => ['name' => 'Коммунизм', 'gov' => 2],
		'fascism' => ['name' => 'Фашизм', 'gov' => 2]

	];
	const GOV_TYPES = ['Демократическое', 'Авторитаритарное', 'Тотальтарное'];
	const TERM_DURATION = 604800;
	const ELECTION_DURATION = 21600;

	public static function getIdeologyNameByID($id){
		if(array_key_exists($id, self::IDEOLOGY))
			return self::IDEOLOGY[$id];
		else
			return null;
	}

	public static function getIdeologyIDByName($name){
		$name = mb_strtolower($name);
		foreach (self::IDEOLOGY as $key => $value) {
			if(mb_strtolower($value['name']) == $name)
				return $key;
		}
		return null;
	}

	public static function getPresidentID($gov){
		if(!is_null($gov['ruling_batch']['id']) && array_key_exists($gov['ruling_batch']['id'], $gov['batches']))
			return $gov['batches'][$gov['ruling_batch']['id']]['leader_id'];
		return 0;
	}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Goverment API

// Получение статистики пользователя
function government_api_getdata($db){
	// Стандартные значения гос. параметров
	$DB_GOVERNMENT_DEFAULT = [
		'batches' => [],
		'ruling_batch' => [
			'id' => null,
			'legitimacy' => 0,
			'terms_count' => 0,
			'elected_time' => 0
		],
		'laws' => [],
		'anthem' => "null",
		'flag' => "null",
		'capital' => null,
		'rally' => false,
		'election' => false
	];

	$query = new MongoDB\Driver\Query(['_id' => $db->getID()], ['projection' => ["_id" => 0, 'government' => 1]]);
	$cursor = $db->getMongoDB()->executeQuery("{$db->getDatabaseName()}.chats", $query);
	$extractor = new Database\CursorValueExtractor($cursor);
	$db_data = Database\CursorValueExtractor::objectToArray($extractor->getValue("0.government", []));
	return array_merge($DB_GOVERNMENT_DEFAULT, $db_data);
	/*
	$data = array();
	foreach ($DB_GOVERNMENT_DEFAULT as $key => $value) {
		if(array_key_exists($key, $db_data))
			$data[$key] = $db_data[$key];
		else
			$data[$key] = $value;
	}
	return $data;
	*/
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

	$president_id = Goverment::getPresidentID($gov);

	if($president_id != 0){
		$ruling_batch_data = $gov['batches'][$gov['ruling_batch']['id']];
		$ideology = Goverment::IDEOLOGY[$ruling_batch_data['ideology']];
		$gov_type = Goverment::GOV_TYPES[$ideology['gov']];
		$msg = "%__appeal__%, 📰информация о текущем государстве:\n📝Название: %__confa_name__%.\n\n&#128104;&#8205;&#9878;Глава государства: %__president_name__%.\n📖Партия: {$ruling_batch_data['name']}\n🗿Идеология: {$ideology['name']}\n🏛Правительство: {$gov_type}";

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_vars($request, array("__president_name__", "__confa_name__", "__appeal__"));

		vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var confa_info=API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}]}).items[0];var president_info=API.users.get({'user_ids':{$president_id},'fields':'screen_name'})[0];var __president_name__='@'+president_info.screen_name+' ('+president_info.first_name+' '+president_info.last_name+')';var __confa_name__=confa_info.chats_settings.title;var __appeal__=appeal;appeal=null;return API.messages.send({$request});");
	}
	else{
		$msg = "%__appeal__%, 📰информация о текущем государстве:\n📝Название: %__confa_name__%.\n\n&#128104;&#8205;&#9878;Глава государства: ⛔Не назначен.";

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_vars($request, array("__president_name__", "__confa_name__", "__appeal__"));

		vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var confa_info = API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}]}).items[0];var __confa_name__ = confa_info.chats_settings.title;var __appeal__ = appeal; appeal = null;return API.messages.send({$request});");
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

	$president_id = Goverment::getPresidentID($gov);

	if(array_key_exists(1, $argv))
		$command = mb_strtolower($argv[1]);
	else
		$command = "";

	if($command == "добавить"){
		if($data->object->from_id == $president_id){
			$time = time();
			$content = mb_substr($data->object->text, 16);

			$gov["laws"][] = array(
				'time' => $time,
				'publisher_id' => $data->object->from_id,
				'content' => $content
			);
			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getID()], ['$set' => ["government.laws" => $gov['laws']]]);
			$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
			$messagesModule->sendSilentMessage($data->object->peer_id, "@id{$data->object->from_id} (Правительство) обновило законы.");
		}
		else{
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		}
	}
	elseif($command == "отменить"){
		if($data->object->from_id == $president_id){
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

				unset($gov["laws"][$law_id-1]);
				$laws_tmp = array_values($gov["laws"]);
				$laws = array();
				for($i = 0; $i < count($laws_tmp); $i++){
					$laws[] = $laws_tmp[$i];
				}
				$gov["laws"] = $laws;
				$bulk = new MongoDB\Driver\BulkWrite;
				$bulk->update(['_id' => $db->getID()], ['$set' => ["government.laws" => $gov['laws']]]);
				$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы отменили закон №{$law_id}.");
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

			if($law["publisher_id"] == $president_id)
				$publisher_type_str = "Президент";
			else
				$publisher_type_str = "Экc-пpeзидeнт";

			$date = gmdate("d.m.Y", $law["time"]+10800);

			$msg = "%__appeal__%, информация о законе:\n✅Указан: %__publisher_name__% ({$publisher_type_str})\n✅Дата указа: {$date}\n✅Содержание закона: {$law["content"]}";

			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("__publisher_name__", "__appeal__"));

			vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var publisher = API.users.get({'user_ids':[{$law['publisher_id']}],'fields':'screen_name,first_name_ins,last_name_ins'})[0];var __publisher_name__ = '@'+publisher.screen_name+' ('+publisher.first_name_ins+' '+publisher.last_name_ins+')';var __appeal__ = appeal; appeal = null;return API.messages.send({$request});");
		}
		else{
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Закона с таким ID не существует!");
		}
	}
	elseif($command == "переместить"){
		if($data->object->from_id != $president_id){
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
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ["government.laws" => $gov['laws']]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
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

	$time = time();

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$gov = government_api_getdata($db);
	$president_id = Goverment::getPresidentID($gov);
	$legitimacy = round($gov['ruling_batch']['legitimacy']);
	if(!array_key_exists(1, $argv)){
		if($president_id != 0){
			$expiration_time = $gov['ruling_batch']['elected_time']+Goverment::TERM_DURATION;
			if($time >= $expiration_time)
				$expiration_info = "❗Превышен срок полномочий";
			else
				$expiration_info = "📅Полномочия: до " . gmdate("d.m.Y", $expiration_time+10800);
			$msg = "%appeal%,\n&#128104;&#8205;&#9878;Президент: %president_name%.\n💪🏻Легитимность: {$legitimacy}%\n{$expiration_info}";
			$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $msg, 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$request = vk_parse_vars($request, array("appeal", "president_name"));
			vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var president=API.users.get({'user_ids':{$president_id}})[0];var president_name='@id{$president_id} ('+president.first_name+' '+president.last_name+')';return API.messages.send({$request});");
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

	$user_batch = bot_get_array_value($gov["batches"], "batch{$data->object->from_id}", null);
	if(is_null($user_batch)){
		$name = mb_substr($data->object->text, 8); 
		if($name == '')
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, У вас нет партии. Создайте, используя:', ['!партия <название>']);
		else{
			if(mb_strlen($name) > 30){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Название больше 30 символов.");
				return;
			}

			$ideology = [];
			foreach (Goverment::IDEOLOGY as $key => $value) {
				$ideology[] = vk_callback_button($value['name'], ['government_batch', $data->object->from_id, $name, 2, $key], 'positive');
			}

			$keyboard_buttons = [];
			$listBuiler = new Bot\ListBuilder($ideology, 6);
			$build = $listBuiler->build(1);
			if($build->result){
				for($i = 0; $i < count($build->list->out); $i++){
					$keyboard_buttons[intdiv($i, 2)][$i % 2] = $build->list->out[$i];
				}
				
				if($build->list->max_number > 1){
					$list_buttons = array();
					if($build->list->number != 1){
						$previous_list = $build->list->number - 1;
						$emoji_str = bot_int_to_emoji_str($previous_list);
						$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('government_batch', $data->object->from_id, $name, 1, $previous_list), 'secondary');
					}
					if($build->list->number != $build->list->max_number){
						$next_list = $build->list->number + 1;
						$emoji_str = bot_int_to_emoji_str($next_list);
						$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('government_batch', $data->object->from_id, $name, 1, $next_list), 'secondary');
					}
					$keyboard_buttons[] = $list_buttons;
				}
			}
			else{
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Не удалось отобразить список идеологий.");
				return;
			}
			
			$keyboard_buttons[] = array(vk_callback_button("Закрыть", array('bot_menu', $data->object->from_id, 0), 'negative'));
			$keyboard = vk_keyboard_inline($keyboard_buttons);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Выберите идеологию партии.", ['keyboard' => $keyboard]);
		}
	}
	else{
		$argv1 = mb_strtolower(bot_get_array_value($argv, 1, ""));
		switch ($argv1) {
			case 'удалить':
			$user_batch_id = "batch{$data->object->from_id}";

			// Проверка на возможность удаления
			if($gov['ruling_batch']['id'] === $user_batch_id){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ваша партия является правящей.");
				return;
			}
			elseif($gov['election'] !== false && ($gov['election']['candidate1']['batch_id'] === $user_batch_id || $gov['election']['candidate2']['batch_id'] === $user_batch_id)){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ваша партия участвует в выборах.");
				return;
			}

			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getID()], ['$unset' => ["government.batches.batch{$data->object->from_id}" => 0]]);
			$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Партия удалена.");
			break;
			
			default:
			$date = gmdate("d.m.Y", $user_batch['created_time']+10800);
			$can_be_elected = ($user_batch['can_be_elected'] ? "Да" : "Нет");
			$ideology = Goverment::IDEOLOGY[$user_batch["ideology"]]["name"];
			$message = "%appeal%, Ваша партия: \n📝Название: {$user_batch["name"]}\n🗿Идеология: {$ideology}\n📈Количество сроков: {$user_batch['terms_count']}\n⏳Создана: {$date}\n💡Может быть избрана: {$can_be_elected}";
			$messagesModule->sendSilentMessage($data->object->peer_id, $message);
			break;
		}
	}
}

function government_batch_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	// Переменные для редактирования сообщения
	$keyboard_buttons = array();
	$message = "";

	// Функция тестирования пользователя
	$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
	if($testing_user_id !== $data->object->user_id){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
		return;
	}

	$name = bot_get_array_value($payload, 2, null);
	if(gettype($name) != "string" || $name == ''){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Название партии не указано.");
		return;
	}
	elseif(mb_strlen($name) > 30){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Название больше 30 символов.");
		return;
	}

	// Переменная команды меню
	$code = bot_get_array_value($payload, 3, 0);
	switch ($code) {
		case 1:
		$list_number = bot_get_array_value($payload, 4, 1);
		$ideology = [];
		foreach (Goverment::IDEOLOGY as $key => $value) {
			$ideology[] = vk_callback_button($value['name'], ['government_batch', $testing_user_id, $name, 2, $key], 'positive');
		}

		$keyboard_buttons = [];
		$listBuiler = new Bot\ListBuilder($ideology, 6);
		$build = $listBuiler->build($list_number);
		if($build->result){
			for($i = 0; $i < count($build->list->out); $i++){
				$keyboard_buttons[intdiv($i, 2)][$i % 2] = $build->list->out[$i];
			}
			
			if($build->list->max_number > 1){
				$list_buttons = array();
				if($build->list->number != 1){
					$previous_list = $build->list->number - 1;
					$emoji_str = bot_int_to_emoji_str($previous_list);
					$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('government_batch', $testing_user_id, $name, 1, $previous_list), 'secondary');
				}
				if($build->list->number != $build->list->max_number){
					$next_list = $build->list->number + 1;
					$emoji_str = bot_int_to_emoji_str($next_list);
					$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('government_batch', $testing_user_id, $name, 1, $next_list), 'secondary');
				}
				$keyboard_buttons[] = $list_buttons;
			}
		}
		else{
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Не удалось отобразить список идеологий.");
			return;
		}
		
		$keyboard_buttons[] = array(vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), 'negative'));
		$messege = '%appeal%, Выберите идеологию партии.';
		break;

		case 2:
		$ideology = bot_get_array_value($payload, 4, "");
		if(!array_key_exists($ideology, Goverment::IDEOLOGY)){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Такой идеологии не существует.");
			return;
		}

		$gov = government_api_getdata($db);
		$batch_id = "batch{$data->object->user_id}";
		if(array_key_exists($batch_id, $gov['batches'])){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ У вас уже есть партия.");
			return;
		}
		$batch = [
			'leader_id' => $data->object->user_id,
			'name' => $name,
			'ideology' => $ideology,
			'created_time' => time(),
			'terms_count' => 0,
			'can_be_elected' => true
		];
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ["government.batches.batch{$data->object->user_id}" => $batch]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);

		$message = "%appeal%, ✅Партия создана.";
		break;

		
		default:
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Internal error.");
		return;
		break;
	}


	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->user_id);
	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
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
	}
	else{
		$president_id = Goverment::getPresidentID($gov);
		if($data->object->from_id == $president_id){
			$first_audio_id = -1;
			$audio = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "audio"){
					$first_audio_id = $i;
					break;
				}
			}
			if ($first_audio_id != -1){
				$anthem = "audio{$data->object->attachments[$first_audio_id]->audio->owner_id}_{$data->object->attachments[$first_audio_id]->audio->id}";
				$bulk = new MongoDB\Driver\BulkWrite;
				$bulk->update(['_id' => $db->getID()], ['$set' => ['government.anthem' => $anthem]]);
				$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
				$msg = "@id{$president_id} (Президент) изменил гимн государства.";
				$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
			} else {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, &#9940;Аудиозаписи не найдены!");
			}
		}
		else {
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
	}
	else {
		$president_id = Goverment::getPresidentID($gov);
		if($data->object->from_id == $president_id){
			$photo_url = $photo_sizes[$photo_url_index]->url;
			$path = BOTPATH_TMP."/photo".mt_rand(0, 65535).".jpg";
			file_put_contents($path, file_get_contents($photo_url));
			$response =  json_decode(vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
			$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
			unlink($path);
			$msg = "@id{$president_id} (Президент) изменил флаг государства.";
			$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
			$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','disable_mentions':true});return doc;"))->response[0];
			$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
			government_api_setdata($db, $gov);
		}
		else {
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
		break;
		
		default:
		$rally_for = $gov["rally"]["for"];
		$rally_against = $gov["rally"]["against"];
		$member_key = "id{$data->object->from_id}";
		if($rally_for !== false && array_key_exists($member_key, $rally_for["members"])){
			if($date - $rally_for["members"][$member_key] >= 3600){
				$members_count = count($rally_against["members"]);
				$r = json_decode(vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var presidential_power={$gov["presidential_power"]};var members_in_chat=API.messages.getConversationMembers({'peer_id':peer_id});var members_in_rally={$members_count};var percentage_of_one=(1/members_in_chat.profiles.length)*0.1;var rally_result=percentage_of_one+(members_in_rally-1)*(percentage_of_one*0.25);presidential_power=presidential_power+rally_result*100;if(presidential_power>100){presidential_power=100;}API.messages.send({'peer_id':peer_id,'message':appeal+', ✅Вы поучаствовали в митинге За @id{$gov["president_id"]} (президента).','disable_mentions':true});return presidential_power;"));
				if(gettype($r) == "object" && property_exists($r, 'response')){
					$presidential_power = $r->response;
					$gov["rally"]["for"]["members"][$member_key] = $date;
					$gov["presidential_power"] = $presidential_power;
					government_api_setdata($db, $gov);
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
				$r = json_decode(vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var presidential_power={$gov["presidential_power"]};var users=API.users.get({'user_ids':[{$rally_against["organizer_id"]},{$gov["president_id"]}],'fields':'first_name_ins,last_name_ins,first_name_gen,last_name_gen'});var members_in_chat=API.messages.getConversationMembers({'peer_id':peer_id});var members_in_rally={$members_count};var percentage_of_one=(1/members_in_chat.profiles.length)*0.1;var rally_result=percentage_of_one+(members_in_rally-1)*(percentage_of_one*0.25);presidential_power=presidential_power-rally_result*100;if(presidential_power<=0){presidential_power=0;API.messages.send({'peer_id':peer_id,'message':'❗Митинг, организованный @id'+users[0].id+' ('+users[0].first_name_ins.substr(0, 2)+'. '+users[0].last_name_ins+'), позволил добиться справедливости и правительсво @id'+users[1].id+' ('+users[1].first_name_gen.substr(0, 2)+'. '+users[1].last_name_gen+') подало в отставку. Организованы досрочные выборы президента.','disable_mentions':true});}else{API.messages.send({'peer_id':peer_id,'message':appeal+', ✅Вы поучаствовали в митинге Против @id{$gov["president_id"]} (президента).','disable_mentions':true});}return presidential_power;"));
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
					}
					else{
						$gov["rally"]["against"]["members"][$member_key] = $date;
						$gov["presidential_power"] = $presidential_power;
						government_api_setdata($db, $gov);
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

function government_election_start($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);

	$owner_id = $db->getValueLegacy(['owner_id']);
	if((is_null($gov['ruling_batch']['id']) && $data->object->from_id == $owner_id) || $gov['ruling_batch']['id'] == "batch{$data->object->from_id}"){
		if($gov["election"] === false){
			$time = time(); // Переменная времени
			$election = array(
				'candidate1' => array('batch_id' => 0, "voters_count" => 0),
				'candidate2' => array('batch_id' => 0, "voters_count" => 0),
				'users' => array(),
				'start_time' => $time,
				'last_notification_time' => $time
			);
			$users = json_decode(vk_execute("API.messages.send({peer_id:{$data->object->peer_id},message:'Начались выборы президента государства. Чтобы зарегистрироваться, как кандидат, используйте команду \"!баллотироваться\".'});var members=API.messages.getConversationMembers({peer_id:{$data->object->peer_id}});return members.profiles@.id;"))->response;
			foreach ($users as $key => $value) {
				$election['users']["id{$value}"] = 0;
			}
			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getID()], ['$set' => ['government.rally' => false, 'government.election' => $election]]);
			$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, выборы уже проходят.");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
}

function government_election_candidate($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);

	if($gov["election"] === false){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Сейчас не проходят выборы.");
		return;
	}

	$time = time();
	if($time - $gov['election']['start_time'] >= Goverment::ELECTION_DURATION){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ['government.election' => false]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Время выборов закончено. Неудалось набрать кандидатов.");
		return;
	}

	$user_batch_id = "batch{$data->object->from_id}";
	$user_batch = bot_get_array_value($gov["batches"], $user_batch_id);

	if(is_null($user_batch)){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔У вас нет своей партии. Свою партию можно создать командой !партия.");
		return;
	}

	if(!$user_batch['can_be_elected']){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вашей партии запрещено избираться.");
		return;
	}

	if($gov['ruling_batch']['id'] === $user_batch_id && $gov['ruling_batch']['terms_count'] >= 2){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы не можете переизбраться на 3 срок подряд.");
		return;
	}

	if($gov['election']['candidate1']['batch_id'] === $user_batch_id || $gov['election']['candidate2']['batch_id'] === $user_batch_id){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Вы уже зарегистрированы на выборах.");
		return;
	}

	if($gov['election']['candidate1']['batch_id'] === 0){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ['government.election.candidate1.batch_id' => $user_batch_id]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы зарегистрировались как Кандидат №1.");
	}
	elseif($gov['election']['candidate2']['batch_id'] === 0){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ['government.election.candidate2.batch_id' => $user_batch_id]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Вы зарегистрировались как Кандидат №2.\nИспользуйте !голосовать.");
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Все кандидаты набраны.");
}

function government_election_vote($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$gov = government_api_getdata($db);

	if($gov["election"] === false){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Сейчас не проходят выборы.");
		return;
	}

	if($gov['election']['candidate1']['batch_id'] === 0 || $gov['election']['candidate2']['batch_id'] === 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Кандидаты не набраны.");
		return;
	}

	$time = time();
	if($time - $gov['election']['start_time'] >= Goverment::ELECTION_DURATION){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Время выборов закончено.");
		return;
	}

	$candidate1_batch = $gov['batches'][$gov['election']['candidate1']['batch_id']];
	$candidate2_batch = $gov['batches'][$gov['election']['candidate2']['batch_id']];
	$candidate1_ideology = Goverment::IDEOLOGY[$gov['batches'][$gov['election']['candidate1']['batch_id']]['ideology']]['name'];
	$candidate2_ideology = Goverment::IDEOLOGY[$gov['batches'][$gov['election']['candidate2']['batch_id']]['ideology']]['name'];

	$keyboard = vk_keyboard_inline([
		[vk_callback_button('✏%candidate1_name%', ['government_vote', 1], 'positive')],
		[vk_callback_button('✏%candidate2_name%', ['government_vote', 2], 'positive')]
	]);
	$message = ", Выборы:\n\n👤Кандидат: %candidate1_appeal%\n📝Партия: {$candidate1_batch["name"]}\n🗿Идеология: {$candidate1_ideology}\n\n👤Кандидат: %candidate2_appeal%\n📝Партия: {$candidate2_batch["name"]}\n🗿Идеология: {$candidate2_ideology}";
	$json = json_encode(['m' => $message, 'k' => $keyboard], JSON_UNESCAPED_UNICODE);
	$json = vk_parse_vars($json, ['candidate1_name', 'candidate2_name', 'candidate1_appeal', 'candidate2_appeal']);

	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var members=API.users.get({user_ids:[{$candidate1_batch['leader_id']},{$candidate2_batch['leader_id']}]});var candidate1_name=members[0].first_name.substr(0, 2)+\". \"+members[0].last_name;var candidate2_name=members[1].first_name.substr(0, 2)+\". \"+members[1].last_name;var candidate1_appeal=\"@id\"+members[0].id+\" (\"+candidate1_name+\")\";var candidate2_appeal=\"@id\"+members[1].id+\" (\"+candidate2_name+\")\";var json={$json};API.messages.send({peer_id:{$data->object->peer_id},message:appeal+json.m,keyboard:json.k});");
}

function government_election_vote_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$gov = government_api_getdata($db);

	if($gov["election"] === false){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Сейчас не проходят выборы.');
		return;
	}

	if($gov['election']['candidate1']['batch_id'] === 0 || $gov['election']['candidate2']['batch_id'] === 0){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Кандидаты не набраны.');
		return;
	}

	$time = time();
	if($time - $gov['election']['start_time'] >= Goverment::ELECTION_DURATION){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Время выборов закончено.');
		return;
	}

	if(!array_key_exists("id{$data->object->user_id}", $gov['election']['users'])){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Вы не можете голосовать.');
		return;
	}

	$time_passed = $time - $gov['election']['users']["id{$data->object->user_id}"];
	if($time_passed >= 600){
		$candidate = intval(bot_get_array_value($payload, 1, 0));
		$message = "";
		$bulk = new MongoDB\Driver\BulkWrite;
		switch ($candidate){
			case 1:
			$bulk->update(['_id' => $db->getID()], ['$inc' => ['government.election.candidate1.voters_count' => 1], '$set' => ["government.election.users.id{$data->object->user_id}" => $time]]);
			$message = '✏ Вы проголосовали за Кандидата №1. Вы можете еще проголосовать через 10 минут.';
			break;

			case 2:
			$bulk->update(['_id' => $db->getID()], ['$inc' => ['government.election.candidate2.voters_count' => 1], '$set' => ["government.election.users.id{$data->object->user_id}" => $time]]);
			$message = '✏ Вы проголосовали за Кандидата №2. Вы можете еще проголосовать через 10 минут.';
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Неизвестный кандидат.');
			return;
			break;
		}
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, $message);
	}
	else{
		$time_left = 600 - $time_passed;
		$minutes = intdiv($time_left, 60);
		$seconds = $time_left % 60;
		$info = "";
		if($minutes != 0)
			$info = "{$minutes} мин. ";
		$info .= "{$seconds} сек.";
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы сможете проголосовать через {$info}");
	}
}

function government_election_system($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$db = $finput->db;

	$gov = government_api_getdata($db);
	$time = time();

	if($gov["election"] === false)
		return false;

	if($time - $gov['election']['start_time'] >= Goverment::ELECTION_DURATION){
		$messagesModule = new Bot\Messages($db);
		$bulk = new MongoDB\Driver\BulkWrite;

		if($gov['election']['candidate1']['batch_id'] === 0 || $gov['election']['candidate2']['batch_id'] === 0)
			$messagesModule->sendSilentMessage($data->object->peer_id, "⛔Выборы не состоялись. Причина: Не набрано нужное количество кандидатов.");
		else{
			if($gov['election']['candidate1']['voters_count'] > $gov['election']['candidate2']['voters_count']){
				$batch_id = $gov['election']['candidate1']['batch_id'];
				$gov['batches'][$batch_id]['terms_count']++;
				if($gov['ruling_batch']['id'] === $batch_id)
					$bulk->update(['_id' => $db->getID()], ['$inc' => ['government.ruling_batch.terms_count' => 1, "government.batches.{$batch_id}.terms_count" => 1], '$set' => ['government.ruling_batch.elected_time' => $gov['election']['start_time']+Goverment::ELECTION_DURATION]]);
				else{
					$ruling_batch = ['id' => $batch_id, 'legitimacy' => 100, 'terms_count' => 1, 'elected_time' => $gov['election']['start_time']+Goverment::ELECTION_DURATION];
					$bulk->update(['_id' => $db->getID()], ['$inc' => ["government.batches.{$batch_id}.terms_count" => 1], '$set' => ["government.ruling_batch" => $ruling_batch]]);
				}
				$candidate_id = $gov['batches'][$batch_id]['leader_id'];
				$all_voters_count = $gov['election']['candidate1']['voters_count'] + $gov['election']['candidate2']['voters_count'];
				$candidate_percent = round($gov['election']['candidate1']['voters_count']/$all_voters_count*100, 1);
				vk_execute("var users=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});var sex_word='Он';if(users[0].sex==1){sex_word='Она';}var msg='✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
			}
			elseif($gov['election']['candidate1']['voters_count'] < $gov['election']['candidate2']['voters_count']){
				$batch_id = $gov['election']['candidate2']['batch_id'];
				$gov['batches'][$batch_id]['terms_count']++;
				if($gov['ruling_batch']['id'] === $batch_id)
					$bulk->update(['_id' => $db->getID()], ['$inc' => ['government.ruling_batch.terms_count' => 1, "government.batches.{$batch_id}.terms_count" => 1], '$set' => ['government.ruling_batch.elected_time' => $gov['election']['start_time']+Goverment::ELECTION_DURATION]]);
				else{
					$ruling_batch = ['id' => $batch_id, 'legitimacy' => 100, 'terms_count' => 1, 'elected_time' => $gov['election']['start_time']+Goverment::ELECTION_DURATION];
					$bulk->update(['_id' => $db->getID()], ['$inc' => ["government.batches.{$batch_id}.terms_count" => 1], '$set' => ["government.ruling_batch" => $ruling_batch]]);
				}
				$candidate_id = $gov['batches'][$batch_id]['leader_id'];
				$all_voters_count = $gov['election']['candidate1']['voters_count'] + $gov['election']['candidate2']['voters_count'];
				$candidate_percent = round($gov['election']['candidate2']['voters_count']/$all_voters_count*100, 1);
				vk_execute("var users=API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});var sex_word='Он';if(users[0].sex==1){sex_word='Она';}var msg='✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
			}
			else
				$messagesModule->sendSilentMessage($data->object->peer_id, "⛔Выборы окончены. Президент не выбран, так как оба кандидата набрали одинаковое количество голосов.");
		}

		$bulk->update(['_id' => $db->getID()], ['$set' => ['government.election' => false]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);
		return true;
	}
	elseif($time - $gov['election']["last_notification_time"] >= 600){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getID()], ['$set' => ['government.election.last_notification_time' => $time]]);
		$db->getMongoDB()->executeBulkWrite("{$db->getDatabaseName()}.chats", $bulk);

		if($gov['election']['candidate1']['batch_id'] === 0 || $gov['election']['candidate2']['batch_id'] === 0){
			$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду [!баллотироваться].";
			vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
		}
		else {
			$candidate1_batch = $gov['batches'][$gov['election']['candidate1']['batch_id']];
			$candidate2_batch = $gov['batches'][$gov['election']['candidate2']['batch_id']];
			$candidate1_ideology = Goverment::IDEOLOGY[$gov['batches'][$gov['election']['candidate1']['batch_id']]['ideology']]['name'];
			$candidate2_ideology = Goverment::IDEOLOGY[$gov['batches'][$gov['election']['candidate2']['batch_id']]['ideology']]['name'];

			$keyboard = vk_keyboard_inline([
				[vk_callback_button('✏%candidate1_name%', ['government_vote', 1], 'positive')],
				[vk_callback_button('✏%candidate2_name%', ['government_vote', 2], 'positive')]
			]);
			$message = "Кандидаты набраны, самое время голосовать.\n\n👤Кандидат: %candidate1_appeal%\n📝Партия: {$candidate1_batch["name"]}\n🗿Идеология: {$candidate1_ideology}\n\n👤Кандидат: %candidate2_appeal%\n📝Партия: {$candidate2_batch["name"]}\n🗿Идеология: {$candidate2_ideology}";
			$json = json_encode(['m' => $message, 'k' => $keyboard], JSON_UNESCAPED_UNICODE);
			$json = vk_parse_vars($json, ['candidate1_name', 'candidate2_name', 'candidate1_appeal', 'candidate2_appeal']);

			vk_execute("var members=API.users.get({user_ids:[{$candidate1_batch['leader_id']},{$candidate2_batch['leader_id']}]});var candidate1_name=members[0].first_name.substr(0, 2)+\". \"+members[0].last_name;var candidate2_name=members[1].first_name.substr(0, 2)+\". \"+members[1].last_name;var candidate1_appeal=\"@id\"+members[0].id+\" (\"+candidate1_name+\")\";var candidate2_appeal=\"@id\"+members[1].id+\" (\"+candidate2_name+\")\";var json={$json};API.messages.send({peer_id:{$data->object->peer_id},message:json.m,keyboard:json.k,disable_mentions:true});");
		}
	}
}

?>