<?php

// Инициализация команд
function debug_cmdinit($event){
	// Добавление DEBUG-команд специальному пользователю

	// Проверка на доступ
	$data = $event->getData();
	if($data->type == "message_new" && $data->object->from_id === bot_getconfig('DEBUG_USER_ID'))
		$access = true;
	elseif($data->type == "message_event" && $data->object->user_id === bot_getconfig('DEBUG_USER_ID'))
		$access = true;
	else
		$access = false;

	// Если разрешен доступ, инициализируем команды
	if($access){
		$event->addTextMessageCommand("!docmd", 'debug_docmd');
		$event->addTextMessageCommand("!test-template", 'debug_testtemplate');
		$event->addTextMessageCommand('!runcb', 'debug_runcb_tc');
		$event->addTextMessageCommand('!kick-all', 'debug_kickall');
		$event->addTextMessageCommand('!debug-info', 'debug_info');
		$event->addTextMessageCommand('!db-edit', 'debug_dbedit_tc');

		$event->addCallbackButtonCommand('bot_runcb', 'debug_runcb_cb');
		$event->addCallbackButtonCommand('debug_dbedit', 'debug_dbedit_cb');
	}
}

function debug_docmd($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$member = bot_get_array_value($argv, 1 , "");

	if(is_numeric($member)){
		$member_id = intval($member);
	}
	elseif(bot_is_mention($member)){
		$member_id = bot_get_id_from_mention($member);
	}
	else{
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте: !docmd <пользователь> <команда>");
		return;
	}

	$command = mb_substr($data->object->text, 8 + mb_strlen($member));

	if($command == ""){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте: !docmd <пользователь> <команда>");
		return;
	}
	$modified_data = $data;
	$modified_data->object->from_id = $member_id;
	$modified_data->object->text = $command;
	$result = $finput->event->runTextMessageCommand($modified_data);
	if($result == Bot\Event::COMMAND_RESULT_UNKNOWN)
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ошибка. Данной команды не существует."); // Вывод ошибки
}

function debug_testtemplate($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$template = json_encode(array(
		'type' => 'carousel',
		'elements' => array(
			array(
				'title' => "Назавание 1",
				'description' => "Описание 1",
				'buttons' => array(vk_callback_button("Кнопка 1", array('bot_menu', $data->object->from_id), 'positive'))
			),
			array(
				'title' => "Назавание 2",
				'description' => "Описание 2",
				'buttons' => array(vk_callback_button("Кнопка 1", array('bot_menu', $data->object->from_id), 'positive'))
			),
			array(
				'title' => "Назавание 3",
				'description' => "Описание 3",
				'buttons' => array(vk_callback_button("Кнопка 1", array('bot_menu', $data->object->from_id), 'positive'))
			)
		)
	), JSON_UNESCAPED_UNICODE);

	$messagesModule->sendSilentMessage($data->object->peer_id, "Template test!", array('template' => $template));
}

function debug_runcb_tc($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = mb_substr($data->object->text, 7);

	if($command == ""){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте: !runcb <команда>");
		return;
	}

	$keyboard = vk_keyboard_inline(array(
		array(
			vk_callback_button('Запусить команду', array('bot_runcb', $command), 'negative')
		)
	));

	$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Чтобы запустить команду [{$command}] используйте кнопку ниже.", array('keyboard' => $keyboard)); // Вывод ошибки
}

function debug_runcb_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;
	$event = $finput->event;

	$command = bot_get_array_value($payload, 1, "");
	if($command == ""){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ [bot_runcb]: Требуется аргумент.");
		return;
	}

	$modified_data = $data;
	$modified_data->object->payload = array($command);

	$result = $event->runCallbackButtonCommand($modified_data);
	if($result != Bot\Event::COMMAND_RESULT_OK){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ [bot_runcb]: Команды [$command] не существует.");
	}
}

function debug_kickall($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new BotModule($db);

	vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var chat_id = peer_id - 2000000000;
		var members = API.messages.getConversationMembers({'peer_id':peer_id});
		API.messages.send({'peer_id':peer_id,'message':appeal+', запущен процесс удаления всех пользователей из беседы.','disable_mentions':true});
		var i = 0;
		while(i < members.profiles.length){
			API.messages.removeChatUser({'chat_id':chat_id,'member_id':members.profiles[i].id});
			i = i + 1;
		};
		");
}

function debug_info($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$modules_importtime = round($GLOBALS['modules_importtime_end'] - $GLOBALS['modules_importtime_start'], 4);
	$cmd_inittime = round($GLOBALS['cmd_initime_end'] - $GLOBALS['cmd_initime_start'], 4);
	$php_memory_usage = round(memory_get_usage() / 1024, 2);

	$msg = "%appeal%,\n⌛Время импорта модулей: {$modules_importtime} сек.\n⌛Время cmdinit: {$cmd_inittime} сек.\n📊Выделено памяти PHP: {$php_memory_usage} КБ";

	$messagesModule->sendSilentMessage($data->object->peer_id, $msg);
}

function debug_dbedit_tc($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = mb_strtolower(bot_get_array_value($argv, 1, "editor"));

	switch ($command) {
		case "editor":
		$keyboard = vk_keyboard_inline(array(array(vk_callback_button('Open editor', ["debug_dbedit"], 'negative'),vk_callback_button('Close', ["bot_menu", $data->object->from_id, 0, "💘Умничка!"], 'positive'))));
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Если ты - далбаеб, который магическим образом получил доступ к этой команде, прошу нажать кнопку Close, ибо эта хуйня способна сломать все к хуям. Имей ввиду.", array('keyboard' => $keyboard));
		break;

		case 'set':
		$path_base64 = bot_get_array_value($argv, 2, "");
		$value_type = mb_strtolower(bot_get_array_value($argv, 3, ""));
		$value = bot_get_array_value($argv, 4, "");

		if($value_type == "" || $value == ""){
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, Используйте:", [
				'!db-edit set <base64> int <value>',
				'!db-edit set <base64> float <value>',
				'!db-edit set <base64> double <value>',
				'!db-edit set <base64> string <value>',
				'!db-edit set <base64> boolean <value>'
			]);
			return;
		}

		switch ($value_type) {
			case 'int':
			$value = intval($value);
			break;

			case 'float':
			$value = floatval($value);
			break;

			case 'string':
			$value = strval($value);
			break;

			case 'boolean':
			$value = boolval($value);
			break;
			
			default:
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, Используйте:", [
				'!db-edit set <base64> int <value>',
				'!db-edit set <base64> float <value>',
				'!db-edit set <base64> string <value>',
				'!db-edit set <base64> boolean <value>',
			]);
			return;
			break;
		}

		$path_json = base64_decode($path_base64);
		if($path_json === false){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Неверно сгенерированная команда.");
			return;
		}
		$path = json_decode($path_json, true);
		if($path === false){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Неверно сгенерированная команда.");
			return;
		}

		$db_data = $db->getValue($path, null);
		if(is_null($db_data)){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Заданного ключа не существует.");
			return;
		}
		else{
			$path_count = count($path);
			$path_text = "/";
			for($i = 0; $i <= $path_count - 2; $i++){
				$path_text .= "{$path[$i]}/";
			}
			$path_text .= $path[$path_count-1];

			$keyboard = vk_keyboard_inline([
				[vk_callback_button("Так точно! Ебашь!", ['debug_dbedit', 4, $path, $value], 'negative')],
				[vk_callback_button("Нет!", ['bot_menu', $data->object->from_id, 0, "%appeal%, 😉Хорошо!"], 'positive')]
			]);

			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Вы уверены?.\n\n📝Путь: {$path_text}\n🔑Новый тип: {$value_type}\n🏷Новое значение: {$value}", ['keyboard' => $keyboard]);
		}

		break;
		
		default:
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Неизвестная команда.");
		break;
	}
}

function debug_dbedit_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	// Переменные для редактирования сообщения
	$keyboard_buttons = array();
	$message = "";

	$command = bot_get_array_value($payload, 1, 1);

	switch ($command) {
		case 1:
		$list_number = bot_get_array_value($payload, 2, 1);
		$path = bot_get_array_value($payload, 3, []);

		$db_data = $db->getValue($path, null);
		if(is_null($db_data)){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный путь БД.");
			return;
		}

		$elements = [];
		foreach ($db_data as $key => $value) {
			$value_type = gettype($value);
			$new_path = $path;
			$new_path[] = $key;
			if($value_type == "array")
				$elements[] = vk_callback_button($key, ["debug_dbedit", 1, 1, $new_path], "primary");
			else
				$elements[] = vk_callback_button($key, ["debug_dbedit", 2, $new_path], "positive");
		}

		$listBuiler = new Bot\ListBuilder($elements, 6);
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
					$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('debug_dbedit', 1, $previous_list, $path), 'secondary');
				}
				if($build->list->number != $build->list->max_number){
					$next_list = $build->list->number + 1;
					$emoji_str = bot_int_to_emoji_str($next_list);
					$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('debug_dbedit', 1, $next_list, $path), 'secondary');
				}
				$keyboard_buttons[] = $list_buttons;
			}
		}
		else{
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный номер списка.");
			return;
		}
		
		$last_layer = [];
		if(count($path) > 0)
			$last_layer[] = vk_callback_button("↩Назад", array('debug_dbedit', 1, 1, array_slice($path, 0, -1)), 'negative');
		$last_layer[] = vk_callback_button("Закрыть", array('bot_menu', $data->object->user_id, 0), 'negative');
		$keyboard_buttons[] = $last_layer;

		$path_text = "/";
		foreach ($path as $key => $value) {
			$path_text .= "{$value}/";
		}
		$message = "%appeal%, Путь: {$path_text}";
		break;

		case 2:
		$path = bot_get_array_value($payload, 2, false);

		if(gettype($path) != "array"){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный формат пути БД.");
			return;
		}

		$db_data = $db->getValue($path, null);

		$data_type = gettype($db_data);
		if($data_type == "array"){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный тип данных.");
			return;
		}

		if($data_type == "boolean"){
			if($db_data)
				$db_value = "true";
			else
				$db_value = "false";
		}
		else
			$db_value = $db_data;

		$path_count = count($path);
		$path_text = "/";
		for($i = 0; $i <= $path_count - 2; $i++){
			$path_text .= "{$path[$i]}/";
		}
		$path_text .= $path[$path_count-1];

		$message = "%appeal%,\n📝Путь: {$path_text}\n🔑Тип: {$data_type}\n🏷Значение: {$db_value}";

		$keyboard_buttons[] = [vk_callback_button("Изменить", array('debug_dbedit', 3, $path), 'primary')];

		$keyboard_buttons[] = [
			vk_callback_button("↩", array('debug_dbedit', 1, 1, array_slice($path, 0, -1)), 'secondary'),
			vk_callback_button("🔃", array('debug_dbedit', 2, $path), 'secondary'),
			vk_callback_button("❌", array('bot_menu', $data->object->user_id, 0), 'secondary')
		];
		break;

		case 3:
		$path = bot_get_array_value($payload, 2, null);
		if(gettype($path) != "array"){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Невернный формат пути.");
			return;
		}

		$path_base64 = base64_encode(json_encode($path, JSON_UNESCAPED_UNICODE));
		$message = "!db-edit set {$path_base64}";
		$keyboard_buttons[] = [
			vk_callback_button("↩Назад", array('debug_dbedit', 2, $path), 'negative'),
			vk_callback_button("Закрыть", array('bot_menu', $data->object->user_id, 0), 'negative')
		];
		break;

		case 4:
		$path = bot_get_array_value($payload, 2, null);
		$value = bot_get_array_value($payload, 3, null);

		if(gettype($path) != "array" || is_null($value)){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Невернный формат данных.");
			return;
		}

		$path_count = count($path);
		$path_text = "/";
		for($i = 0; $i <= $path_count - 2; $i++){
			$path_text .= "{$path[$i]}/";
		}
		$path_text .= $path[$path_count-1];

		if(array_search($path_text, ["/chat_id", "/chat_owner"]) !== false){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Этот ключ запрещено редактировать.");
			return;
		}

		$db_data = $db->getValue($path, null);
		if(is_null($db_data))
			$message = "%appeal%, ⛔Заданного ключа не существует.";
		else{
			$db->setValue($path, $value);
			$db->save();
			$message = "%appeal%, ✅Значение установлено.";
		}

		break;
		
		default:
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверная команда.");
		return;
		break;
	}

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->user_id);
	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
}

?>