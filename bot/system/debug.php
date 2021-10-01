<?php

// Инициализация команд
function debug_cmdinit($event)
{
	// Добавление DEBUG-команд специальному пользователю

	// Проверка на доступ
	$data = $event->getData();
	$debug_userid = bot_getconfig('DEBUG_USER_ID');
	if ($data->type == "message_new" && $data->object->from_id === $debug_userid)
		$access = true;
	elseif ($data->type == "message_event" && $data->object->user_id === $debug_userid)
		$access = true;
	else
		$access = false;

	// Если разрешен доступ, инициализируем команды
	if ($access) {
		$event->addTextMessageCommand("!docmd", 'debug_docmd');
		$event->addTextMessageCommand("!test-template", 'debug_testtemplate');
		$event->addTextMessageCommand('!runcb', 'debug_runcb_tc');
		$event->addTextMessageCommand('!kick-all', 'debug_kickall');
		$event->addTextMessageCommand('!debug-info', 'debug_info');
		$event->addTextMessageCommand('!db-edit', 'debug_dbedit_tc');
		$event->addTextMessageCommand('!special-permits', 'debug_specialpermissions_menu');
		$event->addTextMessageCommand('!test-cmd', 'debug_testcmd');
		$event->addTextMessageCommand('!cmd-search', 'debug_cmdsearch');
		$event->addTextMessageCommand('!test-parser', 'debug_parser');

		$event->addCallbackButtonCommand('bot_runcb', 'debug_runcb_cb');
		$event->addCallbackButtonCommand('debug_dbedit', 'debug_dbedit_cb');
		$event->addCallbackButtonCommand('debug_spermits', 'debug_specialpermissions_menu_cb');
	}
}

function debug_docmd($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$member = bot_get_array_value($argv, 1, "");

	if (is_numeric($member)) {
		$member_id = intval($member);
	} elseif (bot_get_userid_by_mention($member, $member_id)) {
	} elseif (bot_get_userid_by_nick($db, $member, $member_id)) {
	} else {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !docmd <пользователь> <команда>");
		return;
	}

	$command = bot_get_text_by_argv($argv, 2);

	if ($command == "") {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !docmd <пользователь> <команда>");
		return;
	}
	$modified_data = clone $data;
	$modified_data->object->from_id = $member_id;
	$modified_data->object->text = $command;
	$result = $finput->event->runTextMessageCommand($modified_data);
	if ($result->code == Bot\ChatEvent::COMMAND_RESULT_UNKNOWN)
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ошибка. Данной команды не существует."); // Вывод ошибки
}

function debug_testcmd($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = bot_get_text_by_argv($argv, 1);

	if ($command == "") {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !test-cmd <команда>");
		return;
	}
	$modified_data = $data;
	$modified_data->object->text = $command;
	$result = $finput->event->runTextMessageCommand($modified_data);
	if ($result->code == Bot\ChatEvent::COMMAND_RESULT_OK) {
		$execution_time = round($result->execution_time, 2);
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, 📊Данные тестирования:\n📝Команда: {$result->command}\n🕒Время: {$execution_time} мс.");
	}
	if ($result->code == Bot\ChatEvent::COMMAND_RESULT_UNKNOWN)
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ошибка. Данной команды не существует."); // Вывод ошибки
}

function debug_testtemplate($finput)
{
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

function debug_runcb_tc($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = bot_get_text_by_argv($argv, 1);

	if ($command == "") {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !runcb <команда>");
		return;
	}

	$keyboard = vk_keyboard_inline(array(
		array(
			vk_callback_button('Запусить команду', array('bot_runcb', $command), 'negative')
		)
	));

	$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Чтобы запустить команду [{$command}] используйте кнопку ниже.", array('keyboard' => $keyboard)); // Вывод ошибки
}

function debug_runcb_cb($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$payload = $finput->payload;
	$db = $finput->db;
	$event = $finput->event;

	$command = bot_get_array_value($payload, 1, "");
	if ($command == "") {
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ [bot_runcb]: Требуется аргумент.");
		return;
	}

	$modified_data = $data;
	$modified_data->object->payload = array($command);

	$result = $event->runCallbackButtonCommand($modified_data);
	if ($result->code != Bot\ChatEvent::COMMAND_RESULT_OK) {
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ [bot_runcb]: Команды [$command] не существует.");
	}
}

function debug_kickall($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule  = new BotModule($db);

	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id) . "
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

function debug_info($finput)
{
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

function debug_dbedit_tc($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = mb_strtolower(bot_get_array_value($argv, 1, "editor"));

	switch ($command) {
		case "editor":
			$keyboard = vk_keyboard_inline(array(array(vk_callback_button('Open editor', ["debug_dbedit"], 'negative'), vk_callback_button('Close', ["bot_menu", $data->object->from_id, 0, "💘Умничка!"], 'positive'))));
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Если ты - далбаеб, который магическим образом получил доступ к этой команде, прошу нажать кнопку Close, ибо эта хуйня способна сломать все к хуям. Имей ввиду.", array('keyboard' => $keyboard));
			break;

		case 'set':
			$path_base64 = bot_get_array_value($argv, 2, "");
			$value_type = mb_strtolower(bot_get_array_value($argv, 3, ""));
			$value = bot_get_array_value($argv, 4, "");

			if ($value_type == "" || $value == "") {
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
			if ($path_json === false) {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Неверно сгенерированная команда.");
				return;
			}
			$path = json_decode($path_json, true);
			if ($path === false) {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Неверно сгенерированная команда.");
				return;
			}

			$imploded_path = implode('.', $path);
			$db_data = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, $imploded_path => 1]]))->getValue("0.{$imploded_path}");
			if (is_null($db_data)) {
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Заданного ключа не существует.");
				return;
			} else {
				$path_count = count($path);
				$path_text = "/";
				for ($i = 0; $i <= $path_count - 2; $i++) {
					$path_text .= "{$path[$i]}/";
				}
				$path_text .= $path[$path_count - 1];

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

function debug_dbedit_cb($finput)
{
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

			$projection = ['_id' => 0];
			$getvalue_path = "0";
			if (count($path) > 0) {
				$imploded_path = implode('.', $path);
				$projection[$imploded_path] = 1;
				$getvalue_path = "0.{$imploded_path}";
			}
			$db_data = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => $projection]))->getValue($getvalue_path);
			$db_data = Database\CursorValueExtractor::objectToArray($db_data);
			if (array_key_exists("_id", $db_data))
				unset($db_data["_id"]);
			if (is_null($db_data)) {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный путь БД.");
				return;
			}

			$elements = [];
			foreach ($db_data as $key => $value) {
				$value_type = gettype($value);
				$new_path = $path;
				$new_path[] = $key;
				if ($value_type == "array")
					$elements[] = vk_callback_button($key, ["debug_dbedit", 1, 1, $new_path], "primary");
				else
					$elements[] = vk_callback_button($key, ["debug_dbedit", 2, $new_path], "positive");
			}

			$listBuiler = new Bot\ListBuilder($elements, 6);
			$build = $listBuiler->build($list_number);
			if ($build->result) {
				for ($i = 0; $i < count($build->list->out); $i++) {
					$keyboard_buttons[intdiv($i, 2)][$i % 2] = $build->list->out[$i];
				}

				if ($build->list->max_number > 1) {
					$list_buttons = array();
					if ($build->list->number != 1) {
						$previous_list = $build->list->number - 1;
						$emoji_str = bot_int_to_emoji_str($previous_list);
						$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('debug_dbedit', 1, $previous_list, $path), 'secondary');
					}
					if ($build->list->number != $build->list->max_number) {
						$next_list = $build->list->number + 1;
						$emoji_str = bot_int_to_emoji_str($next_list);
						$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('debug_dbedit', 1, $next_list, $path), 'secondary');
					}
					$keyboard_buttons[] = $list_buttons;
				}
			} else {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный номер списка.");
				return;
			}

			$last_layer = [];
			if (count($path) > 0)
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

			if (gettype($path) != "array") {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный формат пути БД.");
				return;
			}

			$imploded_path = implode('.', $path);
			$db_data = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, $imploded_path => 1]]))->getValue("0.{$imploded_path}");

			$data_type = gettype($db_data);
			if ($data_type == "array") {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный тип данных.");
				return;
			}

			if ($data_type == "boolean") {
				if ($db_data)
					$db_value = "true";
				else
					$db_value = "false";
			} else
				$db_value = $db_data;

			$path_count = count($path);
			$path_text = "/";
			for ($i = 0; $i <= $path_count - 2; $i++) {
				$path_text .= "{$path[$i]}/";
			}
			$path_text .= $path[$path_count - 1];

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
			if (gettype($path) != "array") {
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

			if (gettype($path) != "array" || is_null($value)) {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Невернный формат данных.");
				return;
			}

			$path_count = count($path);
			$path_text = "/";
			for ($i = 0; $i <= $path_count - 2; $i++) {
				$path_text .= "{$path[$i]}/";
			}
			$path_text .= $path[$path_count - 1];

			if (array_search($path_text, ["/chat_id", "/chat_owner"]) !== false) {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Этот ключ запрещено редактировать.");
				return;
			}

			$imploded_path = implode('.', $path);
			$db_data = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, $imploded_path => 1]]))->getValue("0.{$imploded_path}");
			if (is_null($db_data))
				$message = "%appeal%, ⛔Заданного ключа не существует.";
			else {
				$bulk = new MongoDB\Driver\BulkWrite;
				$bulk->update(['_id' => $db->getDocumentID()], ['$set' => [$imploded_path => $value]]);
				$db->executeBulkWrite($bulk);
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

function debug_specialpermissions_menu($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$permissionSystem = $finput->event->getPermissionSystem();

	$member = bot_get_array_value($argv, 1, "");
	if (array_key_exists(0, $data->object->fwd_messages))
		$member_id = $data->object->fwd_messages[0]->from_id;
	elseif (bot_get_userid_by_mention($member, $member_id)) {
	} elseif (bot_get_userid_by_nick($db, $member, $member_id)) {
	} elseif (is_numeric($member))
		$member_id = intval($member);
	else $member_id = 0;

	if ($member_id == 0) {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Пользователь не указан.");
		return;
	} elseif ($member_id <= 0) {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Редактировать разрешения можно только пользователям.");
		return;
	}

	$elements = array();
	foreach (PermissionSystem::PERMISSION_LIST as $key => $value) {
		if ($value['type'] == 2 || $value['type'] == 3)
			$elements[] = ['id' => $key, 'label' => $value['label']];
	}

	$list_size = 3;
	$list_number = 1;
	$listBuilder = new Bot\ListBuilder($elements, $list_size);
	$list = $listBuilder->build($list_number);
	$keyboard_buttons = [];
	if ($list->result) {
		for ($i = 0; $i < $list_size; $i++) {
			if (array_key_exists($i, $list->list->out)) {
				if ($permissionSystem->checkUserPermission($member_id, $list->list->out[$i]["id"]))
					$color = 'positive';
				else
					$color = 'negative';
				$keyboard_buttons[] = [vk_callback_button($list->list->out[$i]["label"], ["debug_spermits", $data->object->from_id, $member_id, $list_number, $list->list->out[$i]["id"]], $color)];
			} else
				$keyboard_buttons[] = [vk_callback_button("&#12288;", ["debug_spermits", $data->object->from_id, $member_id, $list_number, false], 'primary')];
		}

		if ($list->list->max_number > 1) {
			$list_buttons = array();
			if ($list->list->number != 1) {
				$previous_list = $list->list->number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('debug_spermits', $data->object->from_id, $member_id, $previous_list), 'secondary');
			}
			if ($list->list->number != $list->list->max_number) {
				$next_list = $list->list->number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('debug_spermits', $data->object->from_id, $member_id, $next_list), 'secondary');
			}
			$keyboard_buttons[] = $list_buttons;
		}
	} else {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Произошла ошибка: Не удалось сгенерировать список.");
		return;
	}
	$keyboard_buttons[] = [vk_callback_button("Закрыть", ['bot_menu', $data->object->from_id, 0], "negative")];

	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$exe_json = json_encode(['keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id) . "
		var member=API.users.get({'user_id':{$member_id},'fields':'first_name_dat,last_name_dat'})[0];
		var json={$exe_json};
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', Настройка специальных прав @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+').','disable_mentions':true,'keyboard':json.keyboard});");
}

function debug_specialpermissions_menu_cb($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$payload = $finput->payload;
	$db = $finput->db;

	$permissionSystem = $finput->event->getPermissionSystem();

	$message = "";
	$keyboard_buttons = [];

	/*
	// Функция тестирования пользователя
	$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
	if($testing_user_id !== $data->object->user_id){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
		return;
	}
	*/

	$member_id = intval(bot_get_array_value($payload, 2, 0));
	if ($member_id <= 0) {
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неверной указан ID пользователя!');
		return;
	}

	$list_number = bot_get_array_value($payload, 3, 1);

	$permission_id = bot_get_array_value($payload, 4, null);
	if (!is_null($permission_id)) {
		if (gettype($permission_id) != "string") {
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Этот элемент пусто!');
			return;
		}
		$current_state = $permissionSystem->checkUserPermission($member_id, $permission_id);
		if (is_null($current_state) || PermissionSystem::PERMISSION_LIST[$permission_id]['type'] == 0 || PermissionSystem::PERMISSION_LIST[$permission_id]['type'] == 1) {
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неверной указан ID разрешения!');
			return;
		} else {
			if ($current_state)
				$result = $permissionSystem->deleteUserPermission($member_id, $permission_id);
			else
				$result = $permissionSystem->addUserPermission($member_id, $permission_id);

			if (!$result) {
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неудалось изменить разрешение!');
				return;
			}
		}
	}

	$elements = array();
	foreach (PermissionSystem::PERMISSION_LIST as $key => $value) {
		if ($value['type'] == 2 || $value['type'] == 3)
			$elements[] = ['id' => $key, 'label' => $value['label']];
	}

	$list_size = 3;
	$listBuilder = new Bot\ListBuilder($elements, $list_size);
	$list = $listBuilder->build($list_number);
	if ($list->result) {
		for ($i = 0; $i < $list_size; $i++) {
			if (array_key_exists($i, $list->list->out)) {
				if ($permissionSystem->checkUserPermission($member_id, $list->list->out[$i]["id"]))
					$color = 'positive';
				else
					$color = 'negative';
				$keyboard_buttons[] = [vk_callback_button($list->list->out[$i]["label"], ["debug_spermits", $data->object->user_id, $member_id, $list_number, $list->list->out[$i]["id"]], $color)];
			} else
				$keyboard_buttons[] = [vk_callback_button("&#12288;", ["debug_spermits", $data->object->user_id, $member_id, $list_number, 0], 'primary')];
		}

		if ($list->list->max_number > 1) {
			$list_buttons = array();
			if ($list->list->number != 1) {
				$previous_list = $list->list->number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('debug_spermits', $data->object->user_id, $member_id, $previous_list), 'secondary');
			}
			if ($list->list->number != $list->list->max_number) {
				$next_list = $list->list->number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('debug_spermits', $data->object->user_id, $member_id, $next_list), 'secondary');
			}
			$keyboard_buttons[] = $list_buttons;
		}
	} else {
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Не удалось сгенерировать список!');
		return;
	}
	$keyboard_buttons[] = [vk_callback_button("Закрыть", ['bot_menu', $data->object->user_id, 0], "negative")];

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->user_id);
	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$exe_json = json_encode(['keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
	$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, ['keyboard' => $keyboard]);
	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->user_id) . "
		var member=API.users.get({'user_id':{$member_id},'fields':'first_name_dat,last_name_dat'})[0];
		var json={$exe_json};
		return API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':appeal+', Настройка специальных прав @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+').','disable_mentions':true,'keyboard':json.keyboard});
		");
}

function debug_cmdsearch($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;
	$event = $finput->event;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$command = bot_get_text_by_argv($argv, 1);

	if ($command == "") {
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !test-cmd <команда>");
		return;
	}

	$commands = $event->getTextMessageCommandList();
	$commands_data = [];
	foreach ($commands as $key => $value) {
		$c = mb_substr_count($value, $command);
		if ($c > 0) {
			$commands_data[$value] = $c;
		}
	}
	arsort($commands_data);

	$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, Возможно вы имели ввиду:", array_keys($commands_data));
}

function debug_parser($finput)
{
	// Инициализация базовых переменных
	$data = $finput->data;
	$argv = $finput->argv;
	$db = $finput->db;
	$event = $finput->event;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$text = bot_get_text_by_argv($argv, 1);
	$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Полученные аргументы: {$text}");
}
