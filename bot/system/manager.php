<?php

/////////////////////////////////////////////
/// API

// Permission API
class PermissionSystem{
	// Список всех режимов.
	// Типы режимов: 0 - Стандартный (Отключенный), 1 - Стандартный (Включенный), 2 - Особый (Отключенный), 3 - Особый (Включенный)
	const PERMISSION_LIST = [
		'change_nick' => ['label' => 'Управлять никами', 'type' => 0],
		'customize_chat' => ['label' => 'Управлять чатом', 'type' => 0],
		'manage_punishments' => ['label' => 'Управлять наказаниями', 'type' => 0],
		'prohibit_autokick' => ['label' => 'Игнорировать автокик', 'type' => 0],
		'prohibit_antiflood' => ['label' => 'Игнорировать антифлуд', 'type' => 0],
		'set_permits' => ['label' => 'Управлять правами', 'type' => 0],
		'drink_tea' => ['label' => 'Пить чай', 'type' => 2],
		'use_chat_messanger' => ['label' => 'Использовать Чат-мессенджер', 'type' => 0],
		'manage_cmd' => ['label' => 'Редактировать команды', 'type' => 0]
	];

	private $db;
	private $owner_id;

	function __construct($db){
		$this->db = $db;

		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "owner_id" => 1]]);
		$extractor = $this->db->executeQuery($query);
		$this->owner_id = $extractor->getValue('0.owner_id');
	}

	public function getChatOwnerID(){
		return $this->owner_id;
	}

	public function isPermissionExists(string $permission_id){
		return array_key_exists($permission_id, self::PERMISSION_LIST);
	}

	public function getUserPermissions(int $user_id){
		$permissions = [];
		if($user_id == $this->owner_id){
			$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_permissions.id{$user_id}" => 1]]);
			$extractor = $this->db->executeQuery($query);
			$db_permissions = $extractor->getValue([0, 'chat_settings', 'user_permissions', "id{$user_id}"], []);
			foreach (self::PERMISSION_LIST as $key => $value) {
				if($value['type'] == 0 || $value['type'] == 1)
					$permissions[] = $key;
				elseif(array_key_exists($key, $db_permissions) && $db_permissions->$key)
					$permissions[] = $key;
				elseif($value['type'] == 3)
					$permissions[] = $key;
			}
		}
		else{
			$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_permissions.id{$user_id}" => 1]]);
			$extractor = $this->db->executeQuery($query);
			$db_permissions = $extractor->getValue([0, 'chat_settings', 'user_permissions', "id{$user_id}"], []);
			foreach (self::PERMISSION_LIST as $key => $value) {
				if(array_key_exists($key, $db_permissions) && $db_permissions->$key)
					$permissions[] = $key;
				elseif($value['type'] == 1 || $value['type'] == 3)
					$permissions[] = $key;
			}
		}
		return $permissions;
	}

	public function checkUserPermission(int $user_id, string $permission_id){
		if(!$this->isPermissionExists($permission_id))
			return null;

		if($user_id == $this->owner_id){
			if(self::PERMISSION_LIST[$permission_id]['type'] == 2)
				$default_state = false;
			elseif(self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 1 || self::PERMISSION_LIST[$permission_id]['type'] == 3)
				$default_state = true;
		}
		else{
			if(self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 2)
				$default_state = false;
			elseif(self::PERMISSION_LIST[$permission_id]['type'] == 1 || self::PERMISSION_LIST[$permission_id]['type'] == 3)
				$default_state = true;
		}

		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_permissions.id{$user_id}" => 1]]);
		$extractor = $this->db->executeQuery($query);
		return $extractor->getValue([0, 'chat_settings', 'user_permissions', "id{$user_id}", $permission_id], $default_state);
	}

	public function addUserPermission(int $user_id, string $permission_id){
		// Проверка на владельца и существования разрешения
		if(!$this->isPermissionExists($permission_id) || $user_id <= 0 || ($user_id == $this->owner_id && (self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 1)))
			return false;

		if(!$this->checkUserPermission($user_id, $permission_id)){
			$bulk = new MongoDB\Driver\BulkWrite;
			if(self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 2)
				$bulk->update(['_id' => $this->db->getDocumentID()], ['$set' => ["chat_settings.user_permissions.id{$user_id}.{$permission_id}" => true]]);
			elseif(self::PERMISSION_LIST[$permission_id]['type'] == 1 || self::PERMISSION_LIST[$permission_id]['type'] == 3)
				$bulk->update(['_id' => $this->db->getDocumentID()], ['$unset' => ["chat_settings.user_permissions.id{$user_id}.{$permission_id}" => 0]]);

			$this->db->executeBulkWrite($bulk);
			return true;
		}
		else
			return false;
	}

	public function deleteUserPermission($user_id, $permission_id){
		// Проверка на владельца и существования разрешения
		if(!$this->isPermissionExists($permission_id) || $user_id <= 0 || ($user_id == $this->owner_id && (self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 1)))
			return false;

		if($this->checkUserPermission($user_id, $permission_id)){
			$bulk = new MongoDB\Driver\BulkWrite;
			if(self::PERMISSION_LIST[$permission_id]['type'] == 0 || self::PERMISSION_LIST[$permission_id]['type'] == 2)
				$bulk->update(['_id' => $this->db->getDocumentID()], ['$unset' => ["chat_settings.user_permissions.id{$user_id}.{$permission_id}" => 0]]);
			elseif(self::PERMISSION_LIST[$permission_id]['type'] == 1 || self::PERMISSION_LIST[$permission_id]['type'] == 3)
				$bulk->update(['_id' => $this->db->getDocumentID()], ['$set' => ["chat_settings.user_permissions.id{$user_id}.{$permission_id}" => false]]);
			
			$this->db->executeBulkWrite($bulk);
			return true;
		}
		else
			return false;
	}
}

class ChatModes{
	// Список всех режимов
	const MODE_LIST = [
		'allow_memes' => ['label' => 'Мемы', 'default_state' => true],
		'antiflood_enabled' => ['label' => 'Антифлуд', 'default_state' => true],
		'auto_referendum' => ['label' => 'Авто выборы', 'default_state' => false],
		'economy_enabled' => ['label' => 'Экономика', 'default_state' => false],
		'roleplay_enabled' => ['label' => 'РП', 'default_state' => true],
		'games_enabled' => ['label' => 'Игры', 'default_state' => true],
		'legacy_enabled' => ['label' => 'Legacy', 'default_state' => false],
		'chat_messanger' => ['label' => 'Чат-мессенджер', 'default_state' => true],
		'custom_cmd' => ['label' => 'Кастомные команды', 'default_state' => true]
	];

	private $db;
	private $modes;

	function __construct($db){
		if(is_null($db))
			return false;
		else{
			$this->db = $db;

			$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.chat_modes" => 1]]);
			$extractor = $this->db->executeQuery($query);
			$db_modes = $extractor->getValue("0.chat_settings.chat_modes", []);

			$this->modes = array();
			foreach(self::MODE_LIST as $key => $value) {
				if(array_key_exists($key, $db_modes))
					$this->modes[$key] = $db_modes->$key;
				else
					$this->modes[$key] = $value["default_state"];
			}
		}
	}

	public function getModeLabel($name){
		if(gettype($name) != "string" || !array_key_exists($name, self::MODE_LIST))
			return null;

		return self::MODE_LIST[$name]["label"];
	}

	public function getModeValue($name){
		if(gettype($name) != "string" || !array_key_exists($name, self::MODE_LIST))
			return null;

		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.chat_modes.{$name}" => 1]]);
		$extractor = $this->db->executeQuery($query);
		return $extractor->getValue([0, 'chat_settings', 'chat_modes', $name], self::MODE_LIST[$name]["default_state"]);
	}

	public function setModeValue($name, $value){
		if(gettype($name) != "string" || gettype($value) != "boolean" || !array_key_exists($name, self::MODE_LIST))
			return false;

		$bulk = new MongoDB\Driver\BulkWrite;
		if($value === self::MODE_LIST[$name]["default_state"])
			$bulk->update(['_id' => $this->db->getDocumentID()], ['$unset' => ["chat_settings.chat_modes.{$name}" => 0]]);
		else
			$bulk->update(['_id' => $this->db->getDocumentID()], ['$set' => ["chat_settings.chat_modes.{$name}" => $value]]);

		$this->db->executeBulkWrite($bulk);
		return true;
	}

	public function getModeList(){
		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.chat_modes" => 1]]);
		$extractor = $this->db->executeQuery($query);
		$db_modes = $extractor->getValue("0.chat_settings.chat_modes", []);

		$list = array();
		foreach (self::MODE_LIST as $key => $value) {
			if(array_key_exists($key, $db_modes))
				$mode_value = $db_modes->$key;
			else
				$mode_value = $value["default_state"];

			$list[] = array(
				'name' => $key,
				'label' => $value["label"],
				'value' => $mode_value
			);
		}
		return $list;
	}
}

class BanSystem{
	public static function getBanList($db){
		$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, "chat_settings.banned_users" => 1]]);
		$extractor = $db->executeQuery($query);
		$banned_users = $extractor->getValue([0, 'chat_settings', 'banned_users'], []);
		$ban_list = [];
		foreach ($banned_users as $user){
			$ban_list[] = $user;
		}
		return $ban_list;
	}

	public static function getUserBanInfo($db, $user_id){
		$query = new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, "chat_settings.banned_users.id{$user_id}" => 1]]);
		$extractor = $db->executeQuery($query);
		$ban_info = $extractor->getValue([0, 'chat_settings', 'banned_users', "id{$user_id}"], false);
		return $ban_info;
	}

	public static function banUser($db, $user_id, $reason, $banned_by, $time){
		if(BanSystem::getUserBanInfo($db, $user_id) !== false)
			return false;
		else{
			$ban_info = array(
				'user_id' => intval($user_id),
				'reason' => $reason,
				'banned_by' => $banned_by,
				'time' => $time
			);
			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.banned_users.id{$user_id}" => $ban_info]]);
			$db->executeBulkWrite($bulk);
			return true;
		}
	}

	public static function unbanUser($db, $user_id){
		if(BanSystem::getUserBanInfo($db, $user_id) !== false){
			$bulk = new MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $db->getDocumentID()], ['$unset' => ["chat_settings.banned_users.id{$user_id}" => 0]]);
			$db->executeBulkWrite($bulk);
			return true;
		}
		else
			return false;
	}
}

class AntiFlood{
	private $db;

	const TIME_INTERVAL = 10; 				// Промежуток времени в секундах
	const MSG_COUNT_MAX = 5; 				// Максимальное количество сообщений в промежуток времени
	const MSG_LENGTH_MAX = 2048; 			// Максимальная длинна сообщения

	function __construct($db){
		$this->db = $db;
	}

	public function checkMember($data){
		$date = $data->object->date;
		$member_id = $data->object->from_id;
		$text = $data->object->text;

		if(mb_strlen($text) > self::MSG_LENGTH_MAX) // Ограничение на длинну сообщения
			return true;

		$query = new MongoDB\Driver\Query(['_id' => $this->db->getDocumentID()], ['projection' => ['_id' => 0, "member{$member_id}" => 1]]);
		$extractor = $this->db->executeQuery($query, 'antiflood');
		$user_data = (array) $extractor->getValue([0, "member{$member_id}"], []);

		// Ограничение на частоту сообщений
		foreach ($user_data as $key => $value){
			if($date - $value >= AntiFlood::TIME_INTERVAL)
				unset($user_data[$key]);
		}
		$user_data = array_filter($user_data);
		$user_data[] = $date;
		// Сохранение новых данных
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $this->db->getDocumentID()], ['$set' => ["member{$member_id}" => $user_data]], ['upsert' => true]);
		$this->db->executeBulkWrite($bulk, 'antiflood');

		if(count($user_data) > AntiFlood::MSG_COUNT_MAX)
			return true;
		else
			return false;
	}

	public static function handler($data, $db, $chatModes, $permissionSystem){
		if(!$chatModes->getModeValue('antiflood_enabled'))
			return false;

		$returnValue = false;
		$floodSystem = new AntiFlood($db);
		if($floodSystem->checkMember($data)){
			$messagesModule = new Bot\Messages($db);

			if($permissionSystem->checkUserPermission($data->object->from_id, 'prohibit_antiflood')) // Проверка разрешения
				return false;

			$r = json_decode(vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var member_id={$data->object->from_id};var user=API.users.get({'user_ids':member_id})[0];var members=API.messages.getConversationMembers({'peer_id':peer_id});var user_index=-1;var i=0;while(i<members.items.length){if(members.items[i].member_id==user.id){user_index=i;i=members.items.length;};i=i+1;};if(!members.items[user_index].is_admin&&user_index!=-1){var msg='Пользователь '+appeal+' был кикнут. Причина: Флуд.';API.messages.send({'peer_id':peer_id,'message':msg});API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user.id});return true;}return false;"));

			if(gettype($r) == "object" && property_exists($r, 'response'))
				$returnValue = $r->response;
		}
		return $returnValue;
	}
}

/////////////////////////////////////////////
/// Handlers

function manager_initcmd($event){
	// Управление беседой
	$event->addTextMessageCommand("!онлайн", 'manager_online_list');
	$event->addTextMessageCommand("!ban", 'manager_ban_user');
	$event->addTextMessageCommand("!unban", 'manager_unban_user');
	$event->addTextMessageCommand("!baninfo", 'manager_baninfo_user');
	$event->addTextMessageCommand("!banlist", 'manager_banlist_user');
	$event->addTextMessageCommand("!kick", 'manager_kick_user');
	$event->addTextMessageCommand("!ник", 'manager_nick');
	//$event->addTextMessageCommand("!ранг", 'manager_rank');
	//$event->addTextMessageCommand("!ранглист", 'manager_rank_list');
	//$event->addTextMessageCommand("!ранги", 'manager_show_user_ranks');
	$event->addTextMessageCommand("!приветствие", 'manager_greeting');
	$event->addTextMessageCommand("!modes", "manager_mode_list");
	$event->addTextMessageCommand("!панель", "manager_panel_control");
	$event->addTextMessageCommand("панель", "manager_panel_show");
	$event->addTextMessageCommand("!права", 'manager_permissions_menu');

	// Прочее
	$event->addTextMessageCommand("!ники", 'manager_show_nicknames');

	// Callback-кнопки
	$event->addCallbackButtonCommand("manager_panel", 'manager_panel_keyboard_handler');
	$event->addCallbackButtonCommand("manager_mode", 'manager_mode_cpanel_cb');
	$event->addCallbackButtonCommand("manager_permits", 'manager_permissions_menu_cb');
}

function manager_mode_list($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$chatModes = $finput->event->getChatModes();

	if(array_key_exists(1, $argv))
		$list_number_from_word = intval($argv[1]);
	else
		$list_number_from_word = 1;

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = $chatModes->getModeList(); // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
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

	$message = "%appeal%, список режимов беседы:";
	for($i = 0; $i < count($list_out); $i++){
		$name = $list_out[$i]["name"];
		$value = "true";
		if(!$list_out[$i]["value"])
			$value = "false";
		$message = $message . "\n• {$name} — {$value}";
	}

	$keyboard = vk_keyboard_inline(array(
		array(
			vk_callback_button("Режимы", array('manager_mode', $data->object->from_id), 'positive')
		)
	));

	$messagesModule->sendSilentMessage($data->object->peer_id, $message, array('keyboard' => $keyboard));
}

function manager_mode_cpanel_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
	if($testing_user_id !== $data->object->user_id){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
		return;
	}

	$message = "";
	$keyboard_buttons = array();

	$chatModes = $finput->event->getChatModes();

	$list_number = bot_get_array_value($payload, 2, 1);
	$mode_name = bot_get_array_value($payload, 3, false);

	if($mode_name !== false){
		$permissionSystem = $finput->event->getPermissionSystem();
		if(!$permissionSystem->checkUserPermission($data->object->user_id, 'customize_chat')){ // Проверка разрешения
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ У вас нет прав для использования этой функции.");
			return;
		}
		if($mode_name === 0){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Этот пустой элемент.");
			return;
		}
		$chatModes->setModeValue($mode_name, !$chatModes->getModeValue($mode_name));
	}

	$mode_list = $chatModes->getModeList();

	$list_size = 3;
	$listBuilder = new Bot\ListBuilder($mode_list, $list_size);
	$list = $listBuilder->build($list_number);

	if($list->result){
		$message = "%appeal%, Режимы беседы.";
		for($i = 0; $i < $list_size; $i++){
			if(array_key_exists($i, $list->list->out)){
				if($list->list->out[$i]["value"])
					$color = 'positive';
				else
					$color = 'negative';
				$keyboard_buttons[] = array(vk_callback_button($list->list->out[$i]["label"], array('manager_mode', $testing_user_id, $list_number, $list->list->out[$i]["name"]), $color));
			}
			else
				$keyboard_buttons[] = array(vk_callback_button("&#12288;", array('manager_mode', $testing_user_id, $list_number, 0), 'primary'));
		}

		if($list->list->max_number > 1){
			$list_buttons = array();
			if($list->list->number != 1){
				$previous_list = $list->list->number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('manager_mode', $testing_user_id, $previous_list), 'secondary');
			}
			if($list->list->number != $list->list->max_number){
				$next_list = $list->list->number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('manager_mode', $testing_user_id, $next_list), 'secondary');
			}
			$keyboard_buttons[] = $list_buttons;
		}
		$keyboard_buttons[] = array(
			vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
			vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
		);
	}
	else
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный номер списка.");

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->user_id);
	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
}

function manager_ban_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$permissionSystem = $finput->event->getPermissionSystem();
	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	if(!$permissionSystem->checkUserPermission($data->object->from_id, 'manage_punishments')){ // Проверка разрешения
		$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
		return;
	}

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
		$reason = bot_get_text_by_argv($argv, 1);
	} elseif(array_key_exists(1, $argv) && bot_get_userid_by_mention($argv[1], $member_id)){
		$reason = bot_get_text_by_argv($argv, 2);
	} elseif(array_key_exists(1, $argv) && bot_get_userid_by_nick($db, $argv[1], $member_id)){
		$reason = bot_get_text_by_argv($argv, 2);
	} elseif(array_key_exists(1, $argv) && is_numeric($argv[1])) {
		$member_id = intval($argv[1]);
		$reason = bot_get_text_by_argv($argv, 2);
	} else $member_id = 0;

	if($member_id == 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, используйте \"!ban <пользователь> <причина>\".");
		return;
	}

	if($permissionSystem->checkUserPermission($member_id, 'manage_punishments')){  // Проверка разрешения
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь имеет специальное разрешение.");
		return;
	}
	elseif(BanSystem::getUserBanInfo($db, $member_id) !== false){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь уже забанен.");
		return;
	}

	if($reason == "")
		$reason = "Не указано";
	else{
		$reason = mb_eregi_replace("\n", " ", $reason);
	}

	$ban_info = json_encode(array("user_id" => $member_id, "reason" => $reason), JSON_UNESCAPED_UNICODE);

	$res = json_decode(vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var ban_info={$ban_info};var users=API.users.get({'user_ids':[{$member_id}]});var members=API.messages.getConversationMembers({'peer_id':peer_id});var user=0;if(users.length > 0){user=users[0];}else{var msg=', указанного пользователя не существует.';API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});return 'nioh';}var user_id=ban_info.user_id;var user_id_index=-1;var i=0;while(i<members.items.length){if(members.items[i].member_id == user_id){if(members.items[i].is_admin){var msg=', @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь является администратором беседы.';API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});return 'nioh';}};i=i+1;};var msg=appeal+', пользователь @id{$member_id} ('+user.first_name.substr(0, 2)+'. '+user.last_name+') был забанен.\\nПричина: '+ban_info.reason+'.';API.messages.send({'peer_id':peer_id,'message':msg});API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});return 'ok';"), false);
	if($res->response == 'ok'){
		BanSystem::banUser($db, $member_id, $reason, $data->object->from_id, time());
	}
}

function manager_unban_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$permissionSystem = $finput->event->getPermissionSystem();

	if(!$permissionSystem->checkUserPermission($data->object->from_id, 'manage_punishments')){ // Проверка ранга (Президент)
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	$member_ids = array();
	for($i = 0; $i < sizeof($data->object->fwd_messages); $i++){
		$isContinue = true;
		for($j = 0; $j < sizeof($member_ids); $j++){
			if($member_ids[$j] == $data->object->fwd_messages[$i]->from_id){
				$isContinue = false;
				break;
			}
		}
		if($isContinue){
			$member_ids[] = $data->object->fwd_messages[$i]->from_id;
		}
	}
	for($i = 1; $i < sizeof($argv); $i++){
		if(bot_get_userid_by_mention($argv[$i], $member_id)){
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
		elseif(bot_get_userid_by_nick($db, $argv[$i], $member_id)){
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
		elseif(is_numeric($argv[$i])) {
			$member_id = intval($argv[$i]);
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
	}

	if(sizeof($member_ids) == 0){
		$msg = ", используйте \\\"!unban <упоминание/id>\\\" или перешлите сообщение с командой \\\"!unban\\\".";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя разбанить более 10 участников одновременно.";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	}

	$unbanned_member_ids = array();

	$banned_users = BanSystem::getBanList($db);
	for($i = 0; $i < sizeof($member_ids); $i++){
		for($j = 0; $j < sizeof($banned_users); $j++){
			if($member_ids[$i] == $banned_users[$j]->user_id){
				$unbanned_member_ids[] = $banned_users[$j]->user_id;
			}
		}
	}
	$member_ids_exe_array = implode(',', $unbanned_member_ids);

	$res = json_decode(vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var member_ids = [{$member_ids_exe_array}];
		var users = API.users.get({'user_ids':member_ids});
		var banned_ids = [];

		var msg = ', следующие пользователи были разбанены:\\n';
		var msg_unbanned_users = '';

		var j = 0; while(j < users.length){
			var user_id = users[j].id;
			msg_unbanned_users = msg_unbanned_users + '✅@id'+ user_id + ' (' + users[j].first_name + ' ' + users[j].last_name + ')\\n';
			j = j + 1;
		};
		if(msg_unbanned_users != ''){
			API.messages.send({'peer_id':peer_id,'message':appeal+msg+msg_unbanned_users,'disable_mentions':true});
		} else {
			msg = ', ни один пользователь не был разбанен.';
			API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});
		}

		return 'ok';
		"));

	if($res->response == 'ok'){
		for($i = 0; $i < sizeof($unbanned_member_ids); $i++){
			for($j = 0; $j < sizeof($banned_users); $j++){
				if($unbanned_member_ids[$i] == $banned_users[$j]->user_id){
					BanSystem::unbanUser($db, $unbanned_member_ids[$i]);
				}
			}
		}
	}
}

function manager_banlist_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(1, $argv))
		$list_number_from_word = intval($argv[1]);
	else
		$list_number_from_word = 1;


	$banned_users = BanSystem::getBanList($db);
	if(sizeof($banned_users) == 0){
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', в беседе нет забаненных пользователей.','disable_mentions':true});");
		return;
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$banned_users; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
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
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	for($i = 0; $i < count($list_out); $i++){
		$users_list[] = $list_out[$i]->user_id;
	}

	$users_list = json_encode($users_list, JSON_UNESCAPED_UNICODE);

	//$users_list = json_encode($banned_users, JSON_UNESCAPED_UNICODE);

	vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."var users=API.users.get({'user_ids':{$users_list}});var msg=', список забаненых пользователей [{$list_number}/{$list_max_number}]:';var i=0;while(i<users.length){var user_first_name=users[i].first_name;msg=msg+'\\n🆘@id'+users[i].id+' ('+user_first_name.substr(0, 2)+'. '+users[i].last_name+') (ID: '+users[i].id+');';i=i+1;};return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});");
}

function manager_baninfo_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(0, $data->object->fwd_messages))
		$member_id = $data->object->fwd_messages[0]->from_id;
	elseif(array_key_exists(1, $argv) && bot_get_userid_by_mention($argv[1], $member_id)){}
	elseif(array_key_exists(1, $argv) && bot_get_userid_by_nick($db, $argv[1], $member_id)){}
	elseif(array_key_exists(1, $argv) && is_numeric($argv[1])) {
		$member_id = intval($argv[1]);
	}
	else $member_id = 0;

	if($member_id == 0){
		$msg = ", используйте \"!baninfo <пользователь>\".";
		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		return;
	}

	$user_baninfo = BanSystem::getUserBanInfo($db, $member_id);

	if($user_baninfo !== false){
		$baninfo = json_encode($user_baninfo, JSON_UNESCAPED_UNICODE);
		$strtime = gmdate("d.m.Y", $user_baninfo->time+10800);
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			var baninfo = {$baninfo};
			var users = API.users.get({'user_ids':[baninfo.user_id,baninfo.banned_by],'fields':'first_name_ins,last_name_ins'});
			var user = users[0];
			var banned_by_user = users[1];

			var msg = ', Информация о блокировке:\\n👤Имя пользователя: @id'+user.id+' ('+user.first_name+' '+user.last_name+')\\n🚔Выдан: @id'+banned_by_user.id+' ('+banned_by_user.first_name_ins+' '+banned_by_user.last_name_ins+')\\n📅Время выдачи: {$strtime}\\n✏Причина: '+baninfo.reason+'.';

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});");
	}
	else{
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Указанный @id{$member_id} (пользователь) не заблокирован.", $data->object->from_id);
	}
}

function manager_kick_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$permissionSystem = $finput->event->getPermissionSystem();
	if(!$permissionSystem->checkUserPermission($data->object->from_id, 'manage_punishments')){ // Проверка разрешения
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	$member_ids = array();
	for($i = 0; $i < sizeof($data->object->fwd_messages); $i++){
		$isContinue = true;
		for($j = 0; $j < sizeof($member_ids); $j++){
			if($member_ids[$j] == $data->object->fwd_messages[$i]->from_id){
				$isContinue = false;
				break;
			}
		}
		if($isContinue){
			$member_ids[] = $data->object->fwd_messages[$i]->from_id;
		}
	}
	for($i = 1; $i < sizeof($argv); $i++){
		if(bot_get_userid_by_mention($argv[$i], $member_id)){
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
		elseif(bot_get_userid_by_nick($db, $argv[$i], $member_id)){
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
		elseif(is_numeric($argv[$i])) {
			$member_id = intval($argv[$i]);
			$isContinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContinue = false;
					break;
				}
			}
			if($isContinue){
				$member_ids[] = $member_id;
			}
		}
	}

	if(sizeof($member_ids) == 0){
		$msg = ", используйте \\\"!kick <упоминание/id>\\\" или перешлите сообщение с командой \\\"!kick\\\".";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя кикнуть более 10 участников одновременно.";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	}

	for($i = 0; $i < count($member_ids); $i++){
		if($permissionSystem->checkUserPermission($member_ids[$i], 'manage_punishments'))
			unset($member_ids[$i]);
	}
	sort($member_ids);

	$member_ids_exe_array = implode(',', $member_ids);

	vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var member_ids = [{$member_ids_exe_array}];
		var users = API.users.get({'user_ids':member_ids});
		var members = API.messages.getConversationMembers({'peer_id':peer_id});

		var msg = ', следующие пользователи были кикнуты:\\n';
		var msg_banned_users = '';

		var j = 0; while(j < users.length){
			var user_id = users[j].id;
			var user_id_index = -1;
			var i = 0; while (i < members.items.length){
				if(members.items[i].member_id == user_id){
					user_id_index = i;
					i = members.items.length;
				};
				i = i + 1;
			};

			if(!members.items[user_id_index].is_admin && user_id_index != -1){
				API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});
				msg_banned_users = msg_banned_users + '✅@id'+ user_id + ' (' + users[j].first_name + ' ' + users[j].last_name + ')\\n';
			}
			j = j + 1;
		};
		if(msg_banned_users != ''){
			return API.messages.send({'peer_id':peer_id,'message':appeal+msg+msg_banned_users,'disable_mentions':true});
		} else {
			msg = ', ни один пользователь не был кикнут.';
			return API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});
		}
		");
}

function manager_online_list($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(!array_key_exists(1, $argv)){
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."var members=API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'online'});var msg=', 🌐следующие пользователи в сети:\\n';var msg_users='';var i=0;while(i<members.profiles.length){if(members.profiles[i].online==1){var emoji='';if(members.profiles[i].online_mobile==1){emoji='📱';}else{emoji='💻';}msg_users=msg_users+emoji+'@id'+members.profiles[i].id+' ('+members.profiles[i].first_name.substr(0, 2)+'. '+members.profiles[i].last_name+')\\n';}i=i+1;}if(msg_users==''){msg=', 🚫В данный момент нет пользователей в сети!';}else{msg=msg+msg_users;}return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});");
	}
}

function manager_nick($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$nick = bot_get_text_by_argv($argv, 1);
	if($nick !== false){
		$nick = str_ireplace("\n", "", $nick);
		if($nick == ''){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ник не может быть пустой");
			return;
		}

		if(!array_key_exists(0, $data->object->fwd_messages)){
			if(mb_strlen($nick) <= 15){
				$nicknames = (array) $db->executeQuery(new \MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, "chat_settings.user_nicknames" => 1]]))->getValue([0, "chat_settings", "user_nicknames"], []);

				// Проверка ника на уникальность без учета регистра
				foreach ($nicknames as $key => $value) {
					$nicknames[$key] = mb_strtolower($value);
				}
				if(array_search(mb_strtolower($nick), $nicknames) !== false){
					$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник занят!");
					return;
				}

				$bulk = new MongoDB\Driver\BulkWrite;
				$bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_nicknames.id{$data->object->from_id}" => $nick]]);
				$db->executeBulkWrite($bulk);

				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Ник установлен.");
			}
			else
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник больше 15 символов.");
		}
		else{
			if($data->object->fwd_messages[0]->from_id <= 0){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Ник можно установить только пользователю!");
				return;
			}

			if(mb_strlen($nick) <= 15){
				$permissionSystem = $finput->event->getPermissionSystem();
				if(!$permissionSystem->checkUserPermission($data->object->from_id, 'change_nick')){ // Проверка разрешения
					$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
					return;
				}
				$nicknames = (array) $db->executeQuery(new \MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, "chat_settings.user_nicknames" => 1]]))->getValue([0, "chat_settings", "user_nicknames"], []);
				if(array_search($nick, $nicknames) !== false){
					$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник занят!");
					return;
				}

				$bulk = new MongoDB\Driver\BulkWrite;
				$bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_nicknames.id{$data->object->fwd_messages[0]->from_id}" => $nick]]);
				$db->executeBulkWrite($bulk);

				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) изменён!");
			}
			else
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник больше 15 символов.");
		}
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте \"!ник <ник>\" для управления ником.");
}

function manager_remove_nick($data, $db, $finput){
	$botModule = new BotModule($db);

	if(!array_key_exists(0, $data->object->fwd_messages)){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getDocumentID()], ['$unset' => ["chat_settings.user_nicknames.id{$data->object->from_id}" => 0]]);
		$db->executeBulkWrite($bulk);

		$msg = ", ✅Ник убран.";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
			");
	}
	else{
		$permissionSystem = $finput->event->getPermissionSystem();
		if(!$permissionSystem->checkUserPermission($data->object->from_id, 'change_nick')){ // Проверка разрешения
			$botModule->sendSystemMsg_NoRights($data);
			return;
		}

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ✅Ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) убран!", 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "appeal");

		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getDocumentID()], ['$unset' => ["chat_settings.user_nicknames.id{$data->object->fwd_messages[0]->from_id}" => 0]]);
		$db->executeBulkWrite($bulk);

		json_decode(vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			API.messages.send({$request});
			"));
	}
}

function manager_show_nicknames($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(1, $argv))
		$list_number_from_word = intval($argv[1]);
	else
		$list_number_from_word = 1;

	$user_nicknames = (array) $db->executeQuery(new \MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, "chat_settings.user_nicknames" => 1]]))->getValue([0, "chat_settings", "user_nicknames"], []);
	$nicknames = array();
	foreach ($user_nicknames as $key => $val) {
		$nicknames[] = array(
			'user_id' => substr($key, 2),
			'nick' => $val
		);
	}
	if(count($nicknames) == 0){
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ❗в беседе нет пользователей с никами!", 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "appeal");
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."API.messages.send({$request});");
		return;
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$nicknames; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
	$list_size = 20; // Размер списка
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
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
		var nicknames = ".json_encode($list_out, JSON_UNESCAPED_UNICODE).";
		var users = API.users.get({'user_ids':nicknames@.user_id});
		var msg = appeal+', ники [{$list_number}/{$list_max_number}]:';
		var i = 0; while(i < nicknames.length){
			msg = msg + '\\n✅@id'+nicknames[i].user_id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') — '+nicknames[i].nick;
			i = i + 1;
		}
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
		");
}

function manager_greeting($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$permissionSystem = $finput->event->getPermissionSystem();
	$botModule = new BotModule($db);

	if(!$permissionSystem->checkUserPermission($data->object->from_id, 'customize_chat')){ // Проверка разрешения
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	if(array_key_exists(1, $argv))
		$command = mb_strtolower($argv[1]);
	else
		$command = "";
	if($command == 'установить'){
		$invited_greeting = bot_get_text_by_argv($argv, 2);

		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.invited_greeting" => $invited_greeting]]);
		$db->executeBulkWrite($bulk);

		$msg = ", ✅Приветствие установлено.";
		json_decode(vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
			"));
	} elseif($command == 'показать'){
		$invited_greeting = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, 'chat_settings.invited_greeting' => 1]]))->getValue('0.chat_settings.invited_greeting', false);
		if($invited_greeting !== false){
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, Приветствие в беседе:\n{$invited_greeting}", 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				API.messages.send({$json_request});
				return 'ok';
				");
		}
		else {
			$msg = ", ⛔Приветствие не установлено.";
			vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				return 'ok';
				");
		}
	}
	elseif($command == 'убрать'){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $db->getDocumentID()], ['$unset' => ["chat_settings.invited_greeting" => 0]]);
		$writeResult = $db->executeBulkWrite($bulk);
		if($writeResult->getModifiedCount() > 0){
			$msg = ", ✅Приветствие убрано.";
			json_decode(vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});"));
		}
		else{
			$msg = ", ⛔Приветствие не установлено.";
			vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});return 'ok';");
		}
	}
	else{
		$msg = ", ⛔Используйте \"!приветствие установить/показать/убрать\".";
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});return 'ok';");
	}
}

function manager_show_invited_greetings($data, $db){
	$greetings_text = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, 'chat_settings.invited_greeting' => 1]]))->getValue('0.chat_settings.invited_greeting', false);
	if($greetings_text !== false && $data->object->action->member_id > 0){
		$parsing_vars = array('USERID', 'USERNAME', 'USERNAME_GEN', 'USERNAME_DAT', 'USERNAME_ACC', 'USERNAME_INS', 'USERNAME_ABL');

		$system_code = "var user=API.users.get({'user_ids':[{$data->object->action->member_id}],'fields':'first_name_gen,first_name_dat,first_name_acc,first_name_ins,first_name_abl,last_name_gen,last_name_dat,last_name_acc,last_name_ins,last_name_abl'})[0];var USERID='@id'+user.id;var USERNAME=user.first_name+' '+user.last_name;var USERNAME_GEN=user.first_name_gen+' '+user.last_name_gen;var USERNAME_DAT=user.first_name_dat+' '+user.last_name_dat;var USERNAME_ACC=user.first_name_acc+' '+user.last_name_acc;var USERNAME_INS=user.first_name_ins+' '+user.last_name_ins;var USERNAME_ABL=user.first_name_abl+' '+user.last_name_abl;";

		$message_json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $greetings_text), JSON_UNESCAPED_UNICODE);

		for($i = 0; $i < count($parsing_vars); $i++){
			$message_json_request = vk_parse_var($message_json_request, $parsing_vars[$i]);
		}

		vk_execute($system_code."return API.messages.send({$message_json_request});");
		return true;
	}
	return false;
}

function manager_show_user_ranks($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(1, $argv))
		$list_number_from_word = intval($argv[1]);
	else
		$list_number_from_word = 1;
	$ranksys = new RankSystem($db);
	$users_rank = $ranksys->getUsersRank();
	$ranks = array();
	foreach ($users_rank as $key => $val) {
		$ranks[] = array(
			'user_id' => $val->user_id,
			'rank_name' => $val->name
		);
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$ranks; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
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
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
		var ranks = ".json_encode($list_out, JSON_UNESCAPED_UNICODE).";
		var users = API.users.get({'user_ids':ranks@.user_id});
		var msg = appeal+', ранги [{$list_number}/{$list_max_number}]:';
		var i = 0; while(i < ranks.length){
			msg = msg + '\\n✅@id'+ranks[i].user_id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') - '+ranks[i].rank_name;
			i = i + 1;
		}
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
		");
}

function manager_rank_list($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);
	$ranksys = new RankSystem($db);

	$msg = ", 👑список всех доступных рангов (по мере уменьшения прав):";
	$ranks = $ranksys->getRanksList();
	$msg_list = [];
	foreach ($ranks as $key => $value) {
		$msg_list[] = "rank_{$value->id} - {$value->name}";
	}
	$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, 👑список всех доступных рангов (по мере уменьшения прав):", $msg_list);
}

function manager_panel_show($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$database_path = "chat_settings.user_panels.id{$data->object->from_id}";
	$user_panel = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, $database_path => 1]]))->getValue("0.{$database_path}", []);
	$user_panel = Database\CursorValueExtractor::objectToArray($user_panel);

	if(array_key_exists('elements', $user_panel))
		$element_count = count($user_panel["elements"]);
	else
		$element_count = 0;

	if($element_count > 0){
		$elements = array(array());
		$current_element_index = 0;
		$last_change_time = $user_panel["last_change_time"];
		for($i = 0; $i < $element_count; $i++){
			switch ($user_panel["elements"][$i]["color"]) {
				case 1:
					$color = "secondary";
					break;

				case 2:
					$color = "primary";
					break;

				case 3:
					$color = "positive";
					break;

				case 4:
					$color = "negative";
					break;
			}
			if(count($elements[$current_element_index]) >= 2){
				$elements[] = array();
				$current_element_index++;
			}
			$elements[$current_element_index][] = vk_callback_button($user_panel["elements"][$i]["name"], array("manager_panel", $data->object->from_id, $last_change_time, $i), $color);
		}
		$keyboard = vk_keyboard_inline($elements);
		$botModule->sendSilentMessage($data->object->peer_id, ", Ваша персональная панель. Используйте [!панель] для управления панелью.", $data->object->from_id, array('keyboard' => $keyboard));
	}
	else{
		$keyboard = vk_keyboard_inline(array(
			array(
				vk_text_button("Помощь", array("command" => "bot_runtc", "text_command" => "!панель"), "positive")
			)
		));
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет элементов в персональной панели.", $data->object->from_id, array('keyboard' => $keyboard));
	}
}

function manager_panel_control($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$command = mb_strtolower(bot_get_array_value($argv, 1, ""));

	if($command == "создать"){
		$text_command = bot_get_text_by_argv($argv, 2);
		if($text_command == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Используйте [!панель создать <команда>], чтобы создать новый элемент.", $data->object->from_id);
			return;
		}
		if(mb_strlen($text_command) > 64){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Команда не может быть больше 64 символов.", $data->object->from_id);
			return;
		}
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		if(array_key_exists('elements', $user_panel))
			$element_count = count($user_panel["elements"]);
		else
			$element_count = 0;
		if($element_count >= 10){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы достили лимита элементов в панели.", $data->object->from_id);
			return;
		}
		$panel_id = $element_count+1;
		if(!array_key_exists('user_id', $user_panel))
			$user_panel['user_id'] = $data->object->from_id;
		$user_panel["last_change_time"] = time();
		$user_panel["elements"][] = array(
			'name' => $panel_id,
			'command' => $text_command,
			'color' => 1
		);
		$bulk = new MongoDB\Driver\BulkWrite; $bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_panels.id{$data->object->from_id}" => $user_panel]]);
		$db->executeBulkWrite($bulk);
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Панель с командой [{$text_command}] успешно создана. Её номер: {$panel_id}.", $data->object->from_id);
	}
	elseif($command == "список"){
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		if(count($user_panel["elements"]) > 0){
			$msg = ', список ваших элементов:';
			$id = 1; foreach ($user_panel["elements"] as $element) {
				$msg .= "\n{$id}. {$element["name"]}: [{$element["command"]}]"; $id++;
			}
			$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		}
		else
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Ваша панель пуста.", $data->object->from_id);
	}
	elseif($command == "название"){
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		$argvt = bot_get_array_value($argv, 2, 0);
		$name = bot_get_text_by_argv($argv, 3);
		if($argvt == "" || !is_numeric($argvt) || $name == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", Используйте [!панель название <номер> <название>], чтобы изменить название элемента.", $data->object->from_id);
			return;
		}
		if(mb_strlen($name) > 15){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Название не может быть больше 15 символов.", $data->object->from_id);
			return;
		}
		$id = intval($argvt) - 1;
		if(!array_key_exists($id, $user_panel["elements"])){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Элемента под номером {$argvt} не существует.", $data->object->from_id);
			return;
		}
		$user_panel["elements"][$id]["name"] = $name;
		$user_panel["last_change_time"] = time();
		$bulk = new MongoDB\Driver\BulkWrite; $bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_panels.id{$data->object->from_id}" => $user_panel]]);
		$db->executeBulkWrite($bulk);
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Название элемента №{$argvt} успешно изменено.", $data->object->from_id);
	}
	elseif($command == "команда"){
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		$argvt = bot_get_array_value($argv, 2, 0);
		$text_command = bot_get_text_by_argv($argv, 2);
		if($argvt == "" || !is_numeric($argvt) || $text_command == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", Используйте [!панель команда <номер> <команда>], чтобы изменить команду элемента.", $data->object->from_id);
			return;
		}
		if(mb_strlen($text_command) > 32){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Команда не может быть больше 32 символов.", $data->object->from_id);
			return;
		}
		$id = intval($argvt) - 1;
		if(!array_key_exists($id, $user_panel["elements"])){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Элемента под номером {$argvt} не существует.", $data->object->from_id);
			return;
		}
		$user_panel["elements"][$id]["command"] = $text_command;
		$user_panel["last_change_time"] = time();
		$bulk = new MongoDB\Driver\BulkWrite; $bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_panels.id{$data->object->from_id}" => $user_panel]]);
		$db->executeBulkWrite($bulk);
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Команда элемента №{$argvt} успешно изменено.", $data->object->from_id);
	}
	elseif($command == "цвет"){
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		$argvt1 = intval(bot_get_array_value($argv, 2, 0));
		$argvt2 = intval(bot_get_array_value($argv, 3, 0));
		if($argvt1 == 0 || $argvt2 == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", Используйте [!панель цвет <номер> <цвет>], чтобы изменить название элемента.\nДоступные цвета: 1 — белый, 2 - синий, 3- зелёный, 4 - красный.", $data->object->from_id);
			return;
		}
		if($argvt2 < 1 || $argvt2 > 4){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Цвета под номером {$argvt2} не существует.\nДоступные цвета: 1 — белый, 2 - синий, 3- зелёный, 4 - красный.", $data->object->from_id);
			return;
		}
		$id = $argvt1 - 1;
		if(!array_key_exists($id, $user_panel["elements"])){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Элемента под номером {$argvt1} не существует.", $data->object->from_id);
			return;
		}
		$user_panel["elements"][$id]["color"] = $argvt2;
		$user_panel["last_change_time"] = time();
		$bulk = new MongoDB\Driver\BulkWrite; $bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_panels.id{$data->object->from_id}" => $user_panel]]);
		$db->executeBulkWrite($bulk);
		switch ($argvt2) {
			case 1:
				$color_name = "Белый";
				break;

			case 2:
				$color_name = "Синий";
				break;

			case 3:
				$color_name = "Зелёный";
				break;

			case 4:
				$color_name = "Красный";
				break;
		}
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Название элемента номер {$argvt1} успешно изменено. Установлен цвет: {$color_name}.", $data->object->from_id);
	}
	elseif($command == "удалить"){
		$user_panel = Database\CursorValueExtractor::objectToArray($db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ["_id" => 0, "chat_settings.user_panels.id{$data->object->from_id}" => 1]]))->getValue([0, 'chat_settings', 'user_panels', "id{$data->object->from_id}"], []));
		$argvt = intval(bot_get_array_value($argv, 2, 0));
		if($argvt == 0){
			$botModule->sendSilentMessage($data->object->peer_id, ", Используйте [!панель удалить <номер>], чтобы удалить элемент.", $data->object->from_id);
			return;
		}
		$id = $argvt - 1;
		if(!array_key_exists($id, $user_panel["elements"])){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Элемента под номером {$argvt} не существует.", $data->object->from_id);
			return;
		}
		unset($user_panel["elements"][$id]);
		$user_panel["elements"] = array_values($user_panel["elements"]);
		$user_panel["last_change_time"] = time();
		$bulk = new MongoDB\Driver\BulkWrite; $bulk->update(['_id' => $db->getDocumentID()], ['$set' => ["chat_settings.user_panels.id{$data->object->from_id}" => $user_panel]]);
		$db->executeBulkWrite($bulk);
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Элемент под номером {$argvt} успешно удален.", $data->object->from_id);
	}
	else{
		$botModule->sendCommandListFromArray($data, ", используйте:", array(
			'Панель - Вызов персональной панели',
			"!панель - Управление панелью",
			"!панель помощь - Помощь по управлению панелью",
			"!панель создать - Создает новый элемент в панели",
			"!панель название - Изменение названия элемента панели",
			"!панель команда - Изменение команды элемента панели",
			"!панель цвет - Управление цветом элемента панели",
			"!панель список - Список элементов панели",
			"!панель удалить - Удаляет элемент панели"
		));
	}
}

function manager_panel_keyboard_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$user_id = bot_get_array_value($payload, 1, null);
	$last_change_time = bot_get_array_value($payload, 2, null);
	$element_id = bot_get_array_value($payload, 3, null);

	if(is_null($user_id) || is_null($last_change_time) || is_null($last_change_time))
		return;

	$database_path = "chat_settings.user_panels.id{$user_id}";
	$user_panel = $db->executeQuery(new MongoDB\Driver\Query(['_id' => $db->getDocumentID()], ['projection' => ['_id' => 0, $database_path => 1]]))->getValue("0.{$database_path}", false);
	$user_panel = Database\CursorValueExtractor::objectToArray($user_panel);
	if($user_panel === false)
		return;

	if($user_panel["user_id"] !== $data->object->user_id){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы не можете использовать панель другого пользователя.");
		return;
	}
	if($user_panel["last_change_time"] !== $last_change_time){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Данная панель является устаревшей.");
		return;
	}
	if(array_key_exists($element_id, $user_panel["elements"])){
		$modified_data = (object) array(
			'type' => 'message_new',
			'object' => (object) array(
				'date' => time(),
				'from_id' => $data->object->user_id,
				'id' => 0,
				'out' => 0,
				'peer_id' => $data->object->peer_id,
				'text' => $user_panel["elements"][$element_id]["command"],
				'conversation_message_id' => $data->object->conversation_message_id,
				'fwd_messages' => array(),
				'important' => false,
				'random_id' => 0,
				'attachments' => array(),
				'is_hidden' => false
			)
		);
		$result = $finput->event->runTextMessageCommand($modified_data);
		if($result->code == Bot\ChatEvent::COMMAND_RESULT_OK)
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "✅ Команда выполнена!");
		elseif($result->code == Bot\ChatEvent::COMMAND_RESULT_UNKNOWN)
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Ошибка. Данной команды не существует.");
	}
	else{
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Данного элемента не существует.");
		return;
	}
}

function manager_permissions_menu($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	$permissionSystem = $finput->event->getPermissionSystem();

	if(array_key_exists(0, $data->object->fwd_messages))
		$member_id = $data->object->fwd_messages[0]->from_id;
	elseif(array_key_exists(1, $argv) && bot_get_userid_by_mention($argv[1], $member_id)){}
		elseif(array_key_exists(1, $argv) && bot_get_userid_by_nick($db, $argv[1], $member_id)){}
	elseif(array_key_exists(1, $argv) && is_numeric($argv[1]))
		$member_id = intval($argv[1]);
	else $member_id = 0;

	if($member_id == 0){
		$user_permissions = $permissionSystem->getUserPermissions($data->object->from_id);
		if(count($user_permissions) > 0){
			$names = [];
			foreach ($user_permissions as $key => $value) {
				$names[] = PermissionSystem::PERMISSION_LIST[$value]["label"];
			}
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, "%appeal%, Ваши права:", $names);
		}
		else
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔У вас нет прав.");
		return;
	}
	elseif($member_id <= 0){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Редактировать разрешения можно только пользователям.");
		return;
	}
	elseif($member_id == $permissionSystem->getChatOwnerID()){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Нельзя изменять разрешения владельцу.");
		return;
	}

	if(!$permissionSystem->checkUserPermission($data->object->from_id, 'set_permits')){
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔У вас нет разрешения управлять разрешениями.");
		return;
	}

	$elements = array();
	foreach (PermissionSystem::PERMISSION_LIST as $key => $value) {
		if($value['type'] == 0 || $value['type'] == 1)
			$elements[] = ['id' => $key, 'label' => $value['label']];
	}

	$list_size = 3;
	$list_number = 1;
	$listBuilder = new Bot\ListBuilder($elements, $list_size);
	$list = $listBuilder->build($list_number);
	$keyboard_buttons = [];
	if($list->result){
		for($i = 0; $i < $list_size; $i++){
			if(array_key_exists($i, $list->list->out)){
				if($permissionSystem->checkUserPermission($member_id, $list->list->out[$i]["id"]))
					$color = 'positive';
				else
					$color = 'negative';
				$keyboard_buttons[] = [vk_callback_button($list->list->out[$i]["label"], ["manager_permits", $data->object->from_id, $member_id, $list_number, $list->list->out[$i]["id"]], $color)];
			}
			else
				$keyboard_buttons[] = [vk_callback_button("&#12288;", ["manager_permits", $data->object->from_id, $member_id, $list_number, false], 'primary')];
		}

		if($list->list->max_number > 1){
			$list_buttons = array();
			if($list->list->number != 1){
				$previous_list = $list->list->number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('manager_permits', $data->object->from_id, $member_id, $previous_list), 'secondary');
			}
			if($list->list->number != $list->list->max_number){
				$next_list = $list->list->number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('manager_permits', $data->object->from_id, $member_id, $next_list), 'secondary');
			}
			$keyboard_buttons[] = $list_buttons;
		}
	}
	else{
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Произошла ошибка: Не удалось сгенерировать список.");
		return;
	}
	$keyboard_buttons[] = [vk_callback_button("Закрыть", ['bot_menu', $data->object->from_id, 0], "negative")];

	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$exe_json = json_encode(['keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->from_id)."
		var member=API.users.get({'user_id':{$member_id},'fields':'first_name_dat,last_name_dat'})[0];
		var json={$exe_json};
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', Настройка прав @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+').','disable_mentions':true,'keyboard':json.keyboard});");
}

function manager_permissions_menu_cb($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$payload = $finput->payload;
	$db = $finput->db;

	$permissionSystem = $finput->event->getPermissionSystem();

	$message = "";
	$keyboard_buttons = [];

	// Функция тестирования пользователя
	$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
	if($testing_user_id !== $data->object->user_id){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
		return;
	}

	if(!$permissionSystem->checkUserPermission($data->object->user_id, 'set_permits')){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет разрешения управлять разрешениями.');
		return;
	}

	$member_id = intval(bot_get_array_value($payload, 2, 0));
	if($member_id <= 0){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неверной указан ID пользователя!');
		return;
	}
	elseif($member_id == $permissionSystem->getChatOwnerID()){
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Нельзя изменять разрешения владельцу!');
		return;
	}

	$list_number = bot_get_array_value($payload, 3, 1);

	$permission_id = bot_get_array_value($payload, 4, null);
	if(!is_null($permission_id)){
		if(gettype($permission_id) != "string"){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Этот элемент пусто!');
			return;
		}
		$current_state = $permissionSystem->checkUserPermission($member_id, $permission_id);
		if(is_null($current_state) || PermissionSystem::PERMISSION_LIST[$permission_id]['type'] == 2 || PermissionSystem::PERMISSION_LIST[$permission_id]['type'] == 3){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неверной указан ID разрешения!');
			return;
		}
		else{
			if($current_state)
				$result = $permissionSystem->deleteUserPermission($member_id, $permission_id);
			else
				$result = $permissionSystem->addUserPermission($member_id, $permission_id);

			if(!$result){
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Неудалось изменить разрешение!');
				return;
			}
		}
	}

	$elements = array();
	foreach (PermissionSystem::PERMISSION_LIST as $key => $value) {
		if($value['type'] == 0 || $value['type'] == 1)
			$elements[] = ['id' => $key, 'label' => $value['label']];
	}

	$list_size = 3;
	$listBuilder = new Bot\ListBuilder($elements, $list_size);
	$list = $listBuilder->build($list_number);
	if($list->result){
		for($i = 0; $i < $list_size; $i++){
			if(array_key_exists($i, $list->list->out)){
				if($permissionSystem->checkUserPermission($member_id, $list->list->out[$i]["id"]))
					$color = 'positive';
				else
					$color = 'negative';
				$keyboard_buttons[] = [vk_callback_button($list->list->out[$i]["label"], ["manager_permits", $testing_user_id, $member_id, $list_number, $list->list->out[$i]["id"]], $color)];
			}
			else
				$keyboard_buttons[] = [vk_callback_button("&#12288;", ["manager_permits", $testing_user_id, $member_id, $list_number, 0], 'primary')];
		}

		if($list->list->max_number > 1){
			$list_buttons = array();
			if($list->list->number != 1){
				$previous_list = $list->list->number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('manager_permits', $testing_user_id, $member_id, $previous_list), 'secondary');
			}
			if($list->list->number != $list->list->max_number){
				$next_list = $list->list->number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('manager_permits', $testing_user_id, $member_id, $next_list), 'secondary');
			}
			$keyboard_buttons[] = $list_buttons;
		}
	}
	else{
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Внутренняя ошибка: Не удалось сгенерировать список!');
		return;
	}
	$keyboard_buttons[] = [vk_callback_button("Закрыть", ['bot_menu', $testing_user_id, 0], "negative")];

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->user_id);
	$keyboard = vk_keyboard_inline($keyboard_buttons);
	$exe_json = json_encode(['keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
	$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, ['keyboard' => $keyboard]);
	vk_execute($messagesModule->buildVKSciptAppealByID($data->object->user_id)."
		var member=API.users.get({'user_id':{$member_id},'fields':'first_name_dat,last_name_dat'})[0];
		var json={$exe_json};
		return API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':appeal+', Настройка прав @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+').','disable_mentions':true,'keyboard':json.keyboard});
		");
}

?>