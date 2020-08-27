<?php

/////////////////////////////////////////////
/// API

// Rank API

class RankSystem{ // Класс управления рангами
	const RANKS_ARRAY = array("Владелец", "Администратор", "Президент");
	const MINRANK_NAME = "Участник";

	private $db;

	function __construct(&$database){
		$this->db = &$database;
	}

	public static function getRankNameByID($rank){
		if(array_key_exists($rank, self::RANKS_ARRAY))
			return self::RANKS_ARRAY[$rank];
		elseif($rank == self::getMinRankValue())
			return self::MINRANK_NAME;
		else
			return "rank_{$rank}";
	}

	public static function getMinRankValue(){
		return count(self::RANKS_ARRAY);
	}

	public function getUserRank($user_id){
		return $this->db->getValue(array("bot_manager", "user_ranks", "id{$user_id}"), self::getMinRankValue());
	}

	public function setUserRank($user_id, $rank){
		if($this->checkRank($user_id, 0)) //Запрет на изменение ранга пользователям с самым максимальным рангом
			return false;

		if($rank == 0){
			$this->db->unsetValue(array("bot_manager", "user_ranks", "id{$user_id}"));
			return true;
		}
		elseif($rank+1 <= self::getMinRankValue()){
			$this->db->setValue(array("bot_manager", "user_ranks", "id{$user_id}"), $rank);
			return true;
		}
		else{
			return false;
		}
	}

	public static function cmpRanks($rank1, $rank2){
		if($rank1 == $rank2)
			return 0;
		elseif($rank1 < $rank2)
			return -1;
		elseif($rank1 > $rank2)
			return 1;
	}

	public function checkRank($user_id, $minRank){
		$user_rank = $this->getUserRank($user_id);

		if(!is_null($user_rank) && $user_rank <= $minRank)
			return true;
		else
			return false;
	}
}

class ChatModes{
	const MODES = array( // Константа всех Режимов
		// Template - array('name' => name, 'default_state' => state)
		array('name' => 'allow_memes', 'default_state' => true),
		array('name' => 'antiflood_enabled', 'default_state' => true),
		array('name' => 'auto_referendum', 'default_state' => false),
		array('name' => 'economy_enabled', 'default_state' => false)
	);

	private $db;

	function __construct($db){
		$this->db = $db;
		if(is_null($this->db))
			$this->db = array();
	}

	public function getModeValue($name){
		if(gettype($name) != "string")
			return null;

		$modeID = -1;
		for($i = 0; $i < count(self::MODES); $i++){
			if($name == self::MODES[$i]["name"]){
				$modeID = $i;
				break;
			}
		}

		if($modeID != -1){
			return $this->db->getValue(array("bot_manager", "chat_modes", $name), self::MODES[$modeID]["default_state"]);
		}
		else
			return null;
	}

	public function setModeValue($name, $value){
		if(gettype($name) != "string" || gettype($value) != "boolean")
			return false;

		$modeID = -1;
		for($i = 0; $i < count(self::MODES); $i++){
			if($name == self::MODES[$i]["name"]){
				$modeID = $i;
				break;
			}
		}

		if($modeID != -1){
			$this->db->setValue(array("bot_manager", "chat_modes", $name), $value);
			return true;
		}
		else
			return false;
	}

	public function getModeList(){
		$list = array();
		for($i = 0; $i < count(self::MODES); $i++){
			$list[] = array(
				'name' => self::MODES[$i]["name"],
				'value' => $this->getModeValue(self::MODES[$i]["name"])
			);
		}

		return $list;
	}
}

class BanSystem{
	public static function getBanList($db){
		return array_values($db->getValue(array("bot_manager", "banned_users"), array()));
	}

	public static function getUserBanInfo($db, $user_id){
		return $db->getValue(array("bot_manager", "banned_users", "id{$user_id}"), false);
	}

	public static function banUser(&$db, $user_id, $reason, $banned_by, $time){
		if(BanSystem::getUserBanInfo($db, $user_id) !== false)
			return false;
		else{
			$data = array(
				'user_id' => intval($user_id),
				'reason' => $reason,
				'banned_by' => $banned_by,
				'time' => $time
			);
			$db->setValue(array("bot_manager", "banned_users", "id{$user_id}"), $data);

			return true;
		}
	}

	public static function unbanUser(&$db, $user_id){
		if(BanSystem::getUserBanInfo($db, $user_id) !== false){
			$db->unsetValue(array("bot_manager", "banned_users", "id{$user_id}"));
			return true;
		}
		else
			return false;
	}
}

class AntiFlood{
	private $antiflood_database;
	private $chat_id;

	const TIME_INTERVAL = 10; // Промежуток времени в секундах
	const MSG_COUNT_MAX = 5; // Максимальное количество сообщений в промежуток времени
	const MSG_LENGTH_MAX = 2048; // Максимальная длинна сообщения

	function __construct($peer_id){
		$this->chat_id = $peer_id - 2000000000;

		if(!file_exists(BOT_DATADIR."/antiflood"))
		mkdir(BOT_DATADIR."/antiflood");

		if(file_exists(BOT_DATADIR."/antiflood/chat{$this->chat_id}.json"))
			$this->antiflood_database = json_decode(file_get_contents(BOT_DATADIR."/antiflood/chat{$this->chat_id}.json"), true);
		else
			$this->antiflood_database = array();
	}

	public function save(){
		file_put_contents(BOT_DATADIR."/antiflood/chat{$this->chat_id}.json", json_encode($this->antiflood_database, JSON_UNESCAPED_UNICODE));
	}

	public function checkMember($data){
		$date = $data->object->date;
		$member_id = $data->object->from_id;
		$text = $data->object->text;

		if(mb_strlen($text) > self::MSG_LENGTH_MAX) // Ограничение на длинну сообщения
			return true;

		if(array_key_exists("member{$member_id}", $this->antiflood_database)){ // Ограничение на частоту сообщений
			$user_data = &$this->antiflood_database["member{$member_id}"];
			foreach ($user_data as $key => $value){
				if($date - $value >= AntiFlood::TIME_INTERVAL)
					unset($user_data[$key]);
			}
			$user_data = array_filter($user_data);
			$user_data[] = $date;
			if(count($user_data) > AntiFlood::MSG_COUNT_MAX)
				return true;
			else
				return false;
		}
		else{
			$this->antiflood_database["member{$member_id}"] = array($date);
			return false;
		}
	}

	public static function handler($data, $db){
		$chatModes = new ChatModes($db);
		if(!$chatModes->getModeValue('antiflood_enabled')){
			if(file_exists(BOT_DATADIR."/antiflood/chat{$data->object->peer_id}.json"))
				unlink(BOT_DATADIR."/antiflood/chat{$data->object->peer_id}.json");
			return false;
		}

		$returnValue = false;
		$floodSystem = new AntiFlood($data->object->peer_id);
		if($floodSystem->checkMember($data)){
			$messagesModule = new Bot\Messages($db);
			$ranksys = new RankSystem($db);

			if($ranksys->checkRank($data->object->from_id, 2)) // Проверка ранга (Президент)
				return false;

			$r = json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var member_id={$data->object->from_id};var user=API.users.get({'user_ids':member_id})[0];var members=API.messages.getConversationMembers({'peer_id':peer_id});var user_index=-1;var i=0;while(i<members.items.length){if(members.items[i].member_id==user.id){user_index=i;i=members.items.length;};i=i+1;};if(!members.items[user_index].is_admin&&user_index!=-1){var msg='Пользователь '+appeal+' был кикнут. Причина: Флуд.';API.messages.send({'peer_id':peer_id,'message':msg});API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user.id});return true;}return false;"));

			if(gettype($r) == "object" && property_exists($r, 'response'))
				$returnValue = $r->response;
		}
		$floodSystem->save();
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
	$event->addTextMessageCommand("!ранг", 'manager_rank');
	$event->addTextMessageCommand("!ранглист", 'manager_rank_list');
	$event->addTextMessageCommand("!ранги", 'manager_show_user_ranks');
	$event->addTextMessageCommand("!приветствие", 'manager_greeting');
	$event->addTextMessageCommand("!modes", "manager_mode_list");
	$event->addTextMessageCommand("!mode", "manager_mode_cpanel");
	$event->addTextMessageCommand("!панель", "manager_panel_control");
	$event->addTextMessageCommand("панель", "manager_panel_show");

	// Прочее
	$event->addTextMessageCommand("!ники", 'manager_show_nicknames');

	// Обработка персональной панели
	$event->addCallbackButtonCommand("manager_panel", 'manager_panel_keyboard_handler');
}

function manager_mode_list($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$chatModes = new ChatModes($db);

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
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}

	$message = ", список режимов беседы:";
	for($i = 0; $i < count($list_out); $i++){
		$name = $list_out[$i]["name"];
		$value = "true";
		if(!$list_out[$i]["value"])
			$value = "false";
		$message = $message . "\n• {$name} — {$value}";
	}

	$botModule->sendSilentMessage($data->object->peer_id, $message, $data->object->from_id);
}

function manager_mode_cpanel($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$ranksys = new RankSystem($db);
	$chatModes = new ChatModes($db);

	if(!$ranksys->checkRank($data->object->from_id, 1)){ // Проверка ранга (Администратор)
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	if(array_key_exists(1, $argv))
		$modeName = mb_strtolower($argv[1]);
	else
		$modeName = "";

	if(array_key_exists(2, $argv))
		$modeValue = mb_strtolower($argv[2]);
	else
		$modeValue = "";

	if($modeName == ""){
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔используйте \"!mode <name> <value>\".", $data->object->from_id);
		return;
	}
	elseif($modeValue == ""){
		$value = $chatModes->getModeValue($modeName);
		if($value)
			$value = "true";
		else
			$value = "false";
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Режим {$modeName} — {$value}.", $data->object->from_id);
		return;
	}
	elseif($modeValue != "true" && $modeValue != "false"){
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Ошибка! Параметр <value> должен состоять из одного значения: true или false.", $data->object->from_id);
		return;
	}

	$modeValueBoolean = true;
	if($modeValue == "false")
		$modeValueBoolean = false;

	if($chatModes->setModeValue($modeName, $modeValueBoolean)){
		$db->save();
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Режим {$modeName} изменен на {$modeValue}.", $data->object->from_id);
	}
	else
		$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Ошибка! Возможно Режима {$modeName} не существует!", $data->object->from_id);

}

function manager_ban_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$ranksys = new RankSystem($db);
	$botModule = new BotModule($db);

	if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
		$reason = mb_substr($data->object->text, 5);
	} elseif(array_key_exists(1, $argv) && bot_is_mention($argv[1])){
		$member_id = bot_get_id_from_mention($argv[1]);
		$reason = mb_substr($data->object->text, 6 + mb_strlen($argv[1]));
	} elseif(array_key_exists(1, $argv) && is_numeric($argv[1])) {
		$member_id = intval($argv[1]);
		$reason = mb_substr($data->object->text, 6 + mb_strlen($argv[1]));
	} else $member_id = 0;

	if($member_id == 0){
		$msg = ", используйте \"!ban <пользователь> <причина>\".";
		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		return;
	}

	if($ranksys->checkRank($member_id, 2)){  // Проверка ранга (Президент)
		$rank_name = RankSystem::getRankNameByID($ranksys->getUserRank($member_id));
		$msg = ", @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь имеет ранг {$rank_name}.";
		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		return;
	}
	elseif(BanSystem::getUserBanInfo($db, $member_id) !== false){
		$msg = ", @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь уже забанен.";
		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		return;
	}

	if($reason == "")
		$reason = "Не указано";
	else{
		$reason = mb_eregi_replace("\n", " ", $reason);
	}

	$ban_info = json_encode(array("user_id" => $member_id, "reason" => $reason), JSON_UNESCAPED_UNICODE);

	$res = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var ban_info = {$ban_info};
		var users = API.users.get({'user_ids':[{$member_id}]});
		var members = API.messages.getConversationMembers({'peer_id':peer_id});

		var user = 0;
		if(users.length > 0){
			user = users[0];
		}
		else{
			var msg = ', указанного пользователя не существует.';
			API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});
			return 'nioh';
		}

		var user_id = ban_info.user_id;
		var user_id_index = -1;
		var i = 0; while (i < members.items.length){
			if(members.items[i].member_id == user_id){
				if(members.items[i].is_admin){
					var msg = ', @id{$member_id} (Пользователя) нельзя забанить. Причина: Пользователь является администратором беседы.';
					API.messages.send({'peer_id':peer_id,'message':appeal+msg,'disable_mentions':true});
					return 'nioh';
				}
			};
			i = i + 1;
		};
		var msg = appeal+', пользователь @id{$member_id} ('+user.first_name.substr(0, 2)+'. '+user.last_name+') был забанен.\\nПричина: '+ban_info.reason+'.';
		API.messages.send({'peer_id':peer_id,'message':msg});
		API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});
		return 'ok';
		"), false);
	if($res->response == 'ok'){
		BanSystem::banUser($db, $member_id, $reason, $data->object->from_id, time());
		$db->save();
	}
}

function manager_unban_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);
	$ranksys = new RankSystem($db);

	if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
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
		if(bot_is_mention($argv[$i])){
			$member_id = bot_get_id_from_mention($argv[$i]);
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
		} elseif(is_numeric($argv[$i])) {
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
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя разбанить более 10 участников одновременно.";
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	}

	$unbanned_member_ids = array();

	$banned_users = BanSystem::getBanList($db);
	for($i = 0; $i < sizeof($member_ids); $i++){
		for($j = 0; $j < sizeof($banned_users); $j++){
			if($member_ids[$i] == $banned_users[$j]["user_id"]){
				$unbanned_member_ids[] = $banned_users[$j]["user_id"];
			}
		}
	}

	$member_ids_exe_array = $unbanned_member_ids[0];
	for($i = 1; $i < sizeof($unbanned_member_ids); $i++){
		$member_ids_exe_array = $member_ids_exe_array.','.$unbanned_member_ids[$i];
	}

	$res = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
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
				if($unbanned_member_ids[$i] == $banned_users[$j]["user_id"]){
					BanSystem::unbanUser($db, $unbanned_member_ids[$i]);
				}
			}
		}
		$db->save();
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
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', в беседе нет забаненных пользователей.','disable_mentions':true});");
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
		$users_list[] = $list_out[$i]["user_id"];
	}

	$users_list = json_encode($users_list, JSON_UNESCAPED_UNICODE);

	//$users_list = json_encode($banned_users, JSON_UNESCAPED_UNICODE);

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
		var users = API.users.get({'user_ids':{$users_list}});
		var msg = ', список забаненых пользователей [{$list_number}/{$list_max_number}]:';
		var i = 0; while(i < users.length){
			var user_first_name = users[i].first_name;
			msg = msg + '\\n🆘@id' + users[i].id + ' (' + user_first_name.substr(0, 2) + '. ' + users[i].last_name + ') (ID: ' + users[i].id + ');';
			i = i + 1;
		};
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});
		");
}

function manager_baninfo_user($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
		$reason = mb_substr($data->object->text, 5);
	} elseif(array_key_exists(1, $argv) && bot_is_mention($argv[1])){
		$member_id = bot_get_id_from_mention($argv[1]);
		$reason = mb_substr($data->object->text, 6 + mb_strlen($argv[1]));
	} elseif(array_key_exists(1, $argv) && is_numeric($argv[1])) {
		$member_id = intval($argv[1]);
		$reason = mb_substr($data->object->text, 6 + mb_strlen($argv[1]));
	} else $member_id = 0;

	if($member_id == 0){
		$msg = ", используйте \"!baninfo <пользователь>\".";
		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		return;
	}

	$user_baninfo = BanSystem::getUserBanInfo($db, $member_id);

	if($user_baninfo !== false){
		$baninfo = json_encode($user_baninfo, JSON_UNESCAPED_UNICODE);
		$strtime = gmdate("d.m.Y H:i:s", $user_baninfo["time"]+10800);
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			var baninfo = {$baninfo};
			var users = API.users.get({'user_ids':[baninfo.user_id,baninfo.banned_by],'fields':'first_name_ins,last_name_ins'});
			var user = users[0];
			var banned_by_user = users[1];

			var msg = ', Информация о блокировке:\\n👤Имя пользователя: @id'+user.id+' ('+user.first_name+' '+user.last_name+')\\n🚔Выдан: @id'+banned_by_user.id+' ('+banned_by_user.first_name_ins+' '+banned_by_user.last_name_ins+')\\n📅Время выдачи: {$strtime}\\n✏Причина: '+baninfo.reason+'.';

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});
			");
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

	$ranksys = new RankSystem($db);
	if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
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
		if(bot_is_mention($argv[$i])){
			$member_id = bot_get_id_from_mention($argv[$i]);
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
		} elseif(is_numeric($argv[$i])) {
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
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя кикнуть более 10 участников одновременно.";
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				");
		return;
	}

	for($i = 0; $i < count($member_ids); $i++){
		if($ranksys->checkRank($member_ids[$i], 2)){  // Проверка ранга (Президент)
			//unset($member_ids[$i]);
			$member_ids[$i] = 0;
		}
	}

	$member_ids_exe_array = $member_ids[0];
	for($i = 1; $i < sizeof($member_ids); $i++){
		$member_ids_exe_array = $member_ids_exe_array.','.$member_ids[$i];
	}

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
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
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'online'});
			var msg = ', 🌐следующие пользователи в сети:\\n';
			var msg_users = '';

			var  i = 0; while(i < members.profiles.length){
				if(members.profiles[i].online == 1){
					msg_users = msg_users + '✅@id' + members.profiles[i].id + ' (' + members.profiles[i].first_name.substr(0, 2) + '. ' + members.profiles[i].last_name + ')\\n';
				}
				i = i + 1;
			}

			if(msg_users == ''){
				msg = ', 🚫в данный момент нет пользователей в сети!';
			} else {
				msg = msg + msg_users;
			}

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'disable_mentions':true});
			");
	}
}

function manager_nick($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$messagesModule = new Bot\Messages($db);
	$messagesModule->setAppealID($data->object->from_id);

	if(array_key_exists(1, $argv)){
		$nick = mb_substr($data->object->text, 5);
		$nick = str_ireplace("\n", "", $nick);
		if(!array_key_exists(0, $data->object->fwd_messages)){
			if(mb_strlen($nick) <= 15){
				$nicknames = $db->getValue(array("bot_manager", "user_nicknames"), array());
				if(array_search($nick, $nicknames) !== false){
					$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник занят!");
					return;
				}
				$db->setValue(array("bot_manager", "user_nicknames", "id{$data->object->from_id}"), $nick);
				$db->save();
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
				$ranksys = new RankSystem($db);
				if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
					$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
					return;
				}
				$nicknames = $db->getValue(array("bot_manager", "user_nicknames"), array());
				if(array_search($nick, $nicknames) !== false){
					$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник занят!");
					return;
				}

				$db->setValue(array("bot_manager", "user_nicknames", "id{$data->object->fwd_messages[0]->from_id}"), $nick);
				$db->save();
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ✅Ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) изменён!");
			}
			else
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Указанный ник больше 15 символов.");
		}
	}
	else
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте \"!ник <ник>\" для управления ником.");
}

function manager_remove_nick($data, &$db){
	$botModule = new BotModule($db);

	if(!array_key_exists(0, $data->object->fwd_messages)){
		$db->unsetValue(array("bot_manager", "user_nicknames", "id{$data->object->from_id}"));
		$db->save();
		$msg = ", ✅ник убран.";
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
			");
	}
	else{
		$ranksys = new RankSystem($db);
		if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
			$botModule->sendSystemMsg_NoRights($data);
			return;
		}

		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ✅ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) убран!", 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "appeal");
		$db->unsetValue(array("bot_manager", "user_nicknames", "id{$data->object->fwd_messages[0]->from_id}"));
		$db->save();
		json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
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

	$user_nicknames = $db->getValue(array("bot_manager", "user_nicknames"));
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
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."API.messages.send({$request});");
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

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
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

	$ranksys = new RankSystem($db);
	$botModule = new BotModule($db);

	if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	if(array_key_exists(1, $argv))
		$command = mb_strtolower($argv[1]);
	else
		$command = "";
	if($command == 'установить'){
		$invited_greeting = mb_substr($data->object->text, 24, mb_strlen($data->object->text));
		$db->setValue(array("bot_manager", "invited_greeting"), $invited_greeting);
		$db->save();
		$msg = ", ✅приветствие установлено.";
		json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
			"));
	} elseif($command == 'показать'){
		$invited_greeting = $db->getValue(array("bot_manager", "invited_greeting"), false);
		if($invited_greeting !== false){
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, приветствие в беседе:\n{$invited_greeting}", 'disable_mentions' => true), JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({$json_request});
				return 'ok';
				");
		} else {
			$msg = ", ⛔приветствие не установлено.";
			vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				return 'ok';
				");
		}
	} elseif($command == 'убрать'){
		$invited_greeting = $db->getValue(array("bot_manager", "invited_greeting"), false);
		if($invited_greeting !== false){
			$db->unsetValue(array("bot_manager", "invited_greeting"));
			$db->save();
			$msg = ", ✅приветствие убрано.";
			json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				"));

		} else {
			$msg = ", ⛔приветствие не установлено.";
			vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
				return 'ok';
				");
		}
	} else{
		$msg = ", ⛔используйте \"!приветствие установить/показать/убрать\".";
		vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});
			return 'ok';
			");
	}
}

function manager_show_invited_greetings($data, $db){
	$greetings_text = $db->getValue(array("bot_manager", "invited_greeting"), false);
	if($greetings_text !== false && $data->object->action->member_id > 0){
		$parsing_vars = array('USERID', 'USERNAME', 'USERNAME_GEN', 'USERNAME_DAT', 'USERNAME_ACC', 'USERNAME_INS', 'USERNAME_ABL');

		$system_code = "
			var user = API.users.get({'user_ids':[{$data->object->action->member_id}],'fields':'first_name_gen,first_name_dat,first_name_acc,first_name_ins,first_name_abl,last_name_gen,last_name_dat,last_name_acc,last_name_ins,last_name_abl'})[0];
			var USERID = '@id'+user.id;
			var USERNAME = user.first_name+' '+user.last_name;
			var USERNAME_GEN = user.first_name_gen+' '+user.last_name_gen;
			var USERNAME_DAT = user.first_name_dat+' '+user.last_name_dat;
			var USERNAME_ACC = user.first_name_acc+' '+user.last_name_acc;
			var USERNAME_INS = user.first_name_ins+' '+user.last_name_ins;
			var USERNAME_ABL = user.first_name_abl+' '+user.last_name_abl;
		";

		$message_json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $greetings_text), JSON_UNESCAPED_UNICODE);

		for($i = 0; $i < count($parsing_vars); $i++){
			$message_json_request = vk_parse_var($message_json_request, $parsing_vars[$i]);
		}

		vk_execute($system_code."return API.messages.send({$message_json_request});");
		return true;
	}
	return false;
}

function manager_rank($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	if(array_key_exists(1, $argv)){
		$command = mb_strtolower($argv[1]);
		if($command == "выдать"){
			$ranksys = new RankSystem($db);
			if(!$ranksys->checkRank($data->object->from_id, 1)){ // Проверка ранга (Администратор)
				$rank_name = RankSystem::getRankNameByID(1);
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Для использования данной функции, ваш ранг должен быть как минимум {$rank_name} (1).", $data->object->from_id);
				return;
			}

			if(!array_key_exists(2, $argv) && !array_key_exists(0, $data->object->fwd_messages)){
				$msg = ", используйте \"!ранг выдать <ранг> <id/упоминание/перес. сообщение>\".";
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});");
				return;
			}

			if(array_key_exists(2, $argv))
				$rank = intval($argv[2]);
			else
				$rank = 0;

			$from_user_rank = $ranksys->getUserRank($data->object->from_id);

			if($rank == 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите ранг.", $data->object->from_id);
				return;
			} elseif($rank <= $from_user_rank){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не можете выдать пользователю такой же ранг, как и у вас или выше.", $data->object->from_id);
				return;
			}

			$member_id = 0;

			if(array_key_exists(0, $data->object->fwd_messages)){
				$member_id = $data->object->fwd_messages[0]->from_id;
			} elseif(array_key_exists(3, $argv) && bot_is_mention($argv[3])){
				$member_id = bot_get_id_from_mention($argv[3]);
			} elseif(array_key_exists(3, $argv) && is_numeric($argv[3])) {
				$member_id = intval($argv[3]);
			} else {
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите пользователя.", $data->object->from_id);
				return;
			}

			$member_rank = $ranksys->getUserRank($member_id);
			if(RankSystem::cmpRanks($from_user_rank, $member_rank) >= 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Пользователь обладает таким же рангом, как и вы, или выше.", $data->object->from_id);
				return;
			}

			if($ranksys->setUserRank($member_id, $rank)){
				$db->save();
				$rank_name = RankSystem::getRankNameByID($rank);
				$botModule->sendMessage($data->object->peer_id, ", @id{$member_id} (Пользователю) установлен ранг: {$rank_name} [rank_{$rank}].", $data->object->from_id);
			} else{
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Такого ранга не существует.", $data->object->from_id);
			}
		}
		elseif($command == "забрать"){
			$ranksys = new RankSystem($db);
			if(!$ranksys->checkRank($data->object->from_id, 1)){ // Проверка ранга (Администратор)
				$rank_name = RankSystem::getRankNameByID(1);
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Для использования данной функции, ваш ранг должен быть как минимум {$rank_name} (1).", $data->object->from_id);
				return;
			}

			if(!array_key_exists(2, $argv) && !array_key_exists(0, $data->object->fwd_messages)){
				$msg = ", используйте \"!ранг забрать <id/упоминание/перес. сообщение>\".";
				vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}','disable_mentions':true});");
				return;
			}

			$member_id = 0;

			if(array_key_exists(0, $data->object->fwd_messages)){
				$member_id = $data->object->fwd_messages[0]->from_id;
			} elseif(array_key_exists(2, $argv) && bot_is_mention($argv[2])){
				$member_id = bot_get_id_from_mention($argv[2]);
			} elseif(array_key_exists(2, $argv) && is_numeric($argv[2])) {
				$member_id = intval($argv[2]);
			} else {
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите пользователя.", $data->object->from_id);
				return;
			}

			$from_user_rank = $ranksys->getUserRank($data->object->from_id);
			$member_rank = $ranksys->getUserRank($member_id);

			if(RankSystem::cmpRanks($from_user_rank, $member_rank) >= 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Пользователь обладает таким же рангом, как и вы, или выше.", $data->object->from_id);
				return;
			}

			$ranksys->setUserRank($member_id, 0);
			$db->save();
			$botModule->sendMessage($data->object->peer_id, ", @id{$member_id} (Пользователь) больше не имеет ранга!", $data->object->from_id);
		}
		elseif($command == "получить"){
			$ranksys = new RankSystem($db);
			if($ranksys->checkRank($data->object->from_id, 1)){ // Проверка ранга (Администратор)
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы уже имеете данный ранг!", $data->object->from_id);
				return;
			}

			$rank_name = RankSystem::getRankNameByID(1);
			$response = json_decode(vk_execute($botModule->makeExeAppealByID($data->object->from_id).bot_test_rights_exe($data->object->peer_id, $data->object->from_id, false, "%appeal%, ⛔Чтобы получить ранг {$rank_name} [rank_1] нужно иметь статус администратора в беседе.")."
					API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ✅Ранг {$rank_name} [rank_1] успешно получен.','disable_mentions':true});
					return 'ok';
				"))->response;

			if($response == 'ok'){
				$ranksys->setUserRank($data->object->from_id, 1);
				$db->save();
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, ", используйте:", array("!ранг выдать <ранг> <пользователь> - Выдача ранга пользователю", "!ранг забрать <пользователь> - Лишение ранга пользователя", "!ранг получить - Получение ранга с помощью статуса в беседе"));
		}
	}
	else{
		$ranksys = new RankSystem($db);
		$user_rank = $ranksys->getUserRank($data->object->from_id);
		$rank_name = RankSystem::getRankNameByID($user_rank);
		$botModule->sendSilentMessage($data->object->peer_id, ", Ваш ранг: {$rank_name} ({$user_rank}).", $data->object->from_id);
	}
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
	$ranks = array();
	$sorted_user_ranks = $db->getValue(array("bot_manager", "user_ranks"), array());
	asort($sorted_user_ranks);
	foreach ($sorted_user_ranks as $key => $val) {
		$user_id = substr($key, 2);
		$rank = $ranksys->getUserRank($user_id);
		$ranks[] = array(
			'user_id' => $user_id,
			'rank_name' => RankSystem::getRankNameByID($rank)." [rank_{$rank}]"
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

	vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
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

	$botModule = new BotModule($db);

	$msg = ", 👑список всех доступных рангов (по мере уменьшения прав):";
	$ranks = RankSystem::RANKS_ARRAY;
	for($i = 0; $i < count($ranks); $i++){
		$msg = $msg . "\n• rank_{$i} - {$ranks[$i]}";
	}
	$min_rank = RankSystem::getMinRankValue();
	$msg = $msg . "\n• rank_{$min_rank} - ".RankSystem::getRankNameByID(RankSystem::getMinRankValue());
	$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
}

function manager_panel_show($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$botModule = new BotModule($db);

	$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());

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
		$text_command = mb_substr($data->object->text, 16);
		if($text_command == ""){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Используйте [!панель создать <команда>], чтобы создать новый элемент.", $data->object->from_id);
			return;
		}
		if(mb_strlen($text_command) > 64){
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Команда не может быть больше 64 символов.", $data->object->from_id);
			return;
		}
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
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
		$db->setValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), $user_panel);
		$db->save();
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Панель с командой [{$text_command}] успешно создана. Её номер: {$panel_id}.", $data->object->from_id);
	}
	elseif($command == "список"){
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
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
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
		$argvt = bot_get_array_value($argv, 2, 0);
		$name = mb_substr($data->object->text, 18+mb_strlen($argvt));
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
		$db->setValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), $user_panel);
		$db->save();
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Название элемента №{$argvt} успешно изменено.", $data->object->from_id);
	}
	elseif($command == "команда"){
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
		$argvt = bot_get_array_value($argv, 2, 0);
		$text_command = mb_substr($data->object->text, 17+mb_strlen($argvt));
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
		$db->setValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), $user_panel);
		$db->save();
		$botModule->sendSilentMessage($data->object->peer_id, ", ✅Команда элемента №{$argvt} успешно изменено.", $data->object->from_id);
	}
	elseif($command == "цвет"){
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
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
		$db->setValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), $user_panel);
		$db->save();
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
		$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), array());
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
		$db->setValue(array("bot_manager", "user_panels", "id{$data->object->from_id}"), $user_panel);
		$db->save();
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

	$user_panel = $db->getValue(array("bot_manager", "user_panels", "id{$user_id}"), false);
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
		if($result == Bot\Bot\Event::COMMAND_RESULT_OK)
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "✅ Команда выполнена!");
		elseif($result == Bot\Bot\Event::COMMAND_RESULT_UNKNOWN)
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Ошибка. Данной команды не существует.");
	}
	else{
		bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Данного элемента не существует.");
		return;
	}
}

?>