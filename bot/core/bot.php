<?php

class BotModule{
	private $db;

	public function __construct(&$db = null){
		$this->db = &$db;
	}

	public function makeExeAppeal($user_id, $varname = "appeal"){ // Создание переменной appeal с обращением к пользователю, посредством VKScript и vk_execute()
		if(isset($this->db) && array_key_exists('user_nicknames', $this->db["bot_manager"]) && array_key_exists("id{$user_id}", $this->db["bot_manager"]["user_nicknames"])){
			$user_nick = $this->db["bot_manager"]["user_nicknames"]["id{$user_id}"];

			return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ({$user_nick})'; user = null;";
		}
		else{
			return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ('+user.first_name.substr(0, 2)+'. '+user.last_name+')'; user = null;";
		}
	}

	public function makeExeAppeals($user_ids, $varname = "appeals"){ // Создание переменной appeal с обращением к пользователю, посредством VKScript и vk_execute()
		if(gettype($user_ids) != "array")
			return "";
		$user_ids = array_values(array_unique($user_ids));

		$from_db = array();
		for($i = 0; $i < count($user_ids); $i++){
			if(isset($this->db) && array_key_exists('user_nicknames', $this->db["bot_manager"]) && array_key_exists("id{$user_ids[$i]}", $this->db["bot_manager"]["user_nicknames"])){
				$from_db[] = array(
					'id' => $user_ids[$i],
					'nick' => $this->db["bot_manager"]["user_nicknames"]["id{$user_ids[$i]}"]
				);
			}
			else{
				$from_db[] = array(
					'id' => $user_ids[$i],
					'nick' => ""
				);
			}
		}

		$from_db_json = json_encode($from_db, JSON_UNESCAPED_UNICODE);

		$code = "var from_db = {$from_db_json};
			var users = API.users.get({'user_ids':from_db@.id});
			var {$varname} = [];
			var i = 0; while(i < from_db.length){
				if(from_db[i].nick == ''){
					var nick = '@id'+from_db[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+')';
					{$varname} = {$varname} + [{user_id:from_db[i].id,nick:nick}];
				}
				else{
					var nick = '@id'+from_db[i].id+' ('+from_db[i].nick+')';
					{$varname} = {$varname} + [{user_id:from_db[i].id,nick:nick}];
				}
				i = i + 1;
			}";

		return $code;
	}

	function sendSimpleMessage($peer_id, $message, $from_id = null, $params = array()){ // Отправка простых сообщений
		$appeal_code = "";
		if(!is_null($from_id)){
			$appeal_code = $this->makeExeAppeal($from_id);
			$message = "%appeal%{$message}";
		}
		$request_array = array('peer_id' => $peer_id, 'message' => $message);
		foreach ($params as $key => $value) {
			$request_array[$key] = $value;
		}
		$json_request = json_encode($request_array, JSON_UNESCAPED_UNICODE);
		$json_request = vk_parse_var($json_request, "appeal");
		return vk_execute($appeal_code."return API.messages.send({$json_request});");
	}

	function sendSystemMsg_NoRights($data){
		$this->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет прав для использования этой команды.", $data->object->from_id);
	}

	function sendCommandListFromArray($data, $message = "", $commands = array()){ // Legacy
		$msg = $message;
		for($i = 0; $i < count($commands); $i++){
			$msg = $msg . "\n• " . $commands[$i];
		}
			$this->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
	}
}

function bot_register($finput){ // Регистрация чата
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if (bot_check_reg($db) == false){
		$response = json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id).bot_test_rights_exe($data->object->peer_id, $data->object->from_id, true, "%appeal%, &#9940;У вас нет прав для этой команды.")."
			var chat = API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}],'extended':1}).items[0];

			if(chat.peer.type != 'chat'){
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', эта беседа не является групповым чатом.'});
				return {'result':0};
			}
			var owner = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'first_name_gen,last_name_gen'})[0];
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', беседа успешно зарегистрирована.'});
			return {'result':1,'batch_name':'Полит. партия '+owner.first_name_gen+' '+owner.last_name_gen};
			"))->response;
		if ($response->result == 1){
			$gov_data = array('soc_order' => 1,
			'president_id' => 0,
			'parliament_id' => $data->object->from_id,
			'batch_name' => "Нет данных",
			'laws' => array(),
			'anthem' => "nil",
			'flag' => "nil",
			'capital' => 'г. Мда');
			$db["goverment"] = $gov_data;
			$db["bot_manager"] = array(
				'user_ranks' => array("id{$data->object->from_id}" => 0)
			);
		}	
	} else {
		$msg = ", данная беседа уже зарегистрирована.";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+'{$msg}'});
			");
	}
}

function bot_pre_handle_function($event){
	$db = &$event->getDB();
	$data = $event->getData();

	if($data->type != "message_new" || $data->object->peer_id < 2000000000)
		return;

	if(!bot_check_reg($db))
		return;

	if(AntiFlood::handler($data, $db)){
		$event->saveDB();
		$event->exit();
		exit;
	}
}

function bot_is_mention($msg){ // Проверка упоминания пользователя
	mb_internal_encoding("UTF-8");
	if(mb_substr($msg, 0, 3) == "[id" && mb_substr($msg, mb_strlen($msg) - 1, mb_strlen($msg) - 1) == "]"){
		if(sizeof(explode("|", $msg)) >= 2){
			return true;
		}
	}
	return false;
}

function bot_get_id_from_mention($msg){ // Получение ID из упоминания
	mb_internal_encoding("UTF-8");
	if(bot_is_mention($msg)){
		return explode('|', mb_substr($msg, 3, mb_strlen($msg)))[0];
	}
	return null;
}

function bot_leave_autokick($data){ // Автокик пользователя, вышедшего из беседы
	if(property_exists($data->object, 'action')){
		if ($data->object->action->type == "chat_kick_user" && $data->object->action->member_id == $data->object->from_id){
			$chat_id = $data->object->peer_id - 2000000000;
			vk_execute("
				var user = API.users.get({'user_ids':[{$data->object->from_id}]})[0];
				var msg = 'Пока, @id{$data->object->from_id} ('+user.first_name+' '+user.last_name+'). Больше ты сюда не вернешься!';
				API.messages.send({'peer_id':{$data->object->peer_id}, 'message':msg});
				API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$data->object->action->member_id}});
				return 'ok';
				");
		}
	}
}

function bot_debug($str){ // Debug function
	$botModule = new BotModule();
	$botModule->sendSimpleMessage(219011658, "DEBUG: {$str}");
}

function bot_banned_kick($data, &$db){ // Кик забаненных пользователей после приглашения
	$banned_users = BanSystem::getBanList($db);

	if(property_exists($data->object, 'action')){
		if ($data->object->action->type == "chat_invite_user"){
			$botModule = new BotModule($db);
			for($i = 0; $i < sizeof($banned_users); $i++){
				if ($banned_users[$i]["user_id"] == $data->object->action->member_id){
					$chat_id = $data->object->peer_id - 2000000000;
					$ranksys = new RankSystem($db);
					if($ranksys->checkRank($data->object->from_id, 1)){
						vk_execute("
							API.messages.send({'peer_id':{$data->object->peer_id},'message':'@id{$data->object->action->member_id} (Пользователь) был приглашен @id{$data->object->from_id} (администратором) беседы и автоматически разбанен.'});
							");
						BanSystem::unbanUser($db, $data->object->action->member_id);
					}
					else{
						$ban_info = BanSystem::getUserBanInfo($db, $data->object->action->member_id);
						json_decode(vk_execute($botModule->makeExeAppeal($data->object->action->member_id)."
							API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+', вы забанены в этой беседе!\\nПричина: {$ban_info["reason"]}.'});
							API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$data->object->action->member_id}});
							"));
						return false;
					}
				}
			}
			return true;
		}
	}
}

function bot_test_rights_exe($chat_id, $user_id, $check_owner = false, $msgInvalidRights = "%__DEFAULTMSG__%"){ // Тестирование прав через VKScript
	$messageRequest = json_encode(array('peer_id' => $chat_id, 'message' => $msgInvalidRights), JSON_UNESCAPED_UNICODE);
	$messageRequest = vk_parse_vars($messageRequest, array("appeal", "__DEFAULTMSG__"));
	$code = "
		var from_id = {$user_id};
		var peer_id = {$chat_id};
		var members = API.messages.getConversationMembers({'peer_id':peer_id});
		var from_id_index = -1;
		var i = 0; while (i < members.items.length){
			if(members.items[i].member_id == from_id){
				from_id_index = i;
				i = members.items.length;
			};
			i = i + 1;
		};
	";
	if($check_owner){
		$code = $code . "
			if(!members.items[from_id_index].is_owner){
			var user_name = '';
			var i = 0; while(i < members.profiles.length){
				if (from_id == members.profiles[i].id){
					user_name = '@id' + from_id + ' (' + members.profiles[i].first_name + ')';
				}
				i = i + 1;
			};
			var __DEFAULTMSG__ = user_name + ', ⛔ты не создатель беседы.';
			API.messages.send({$messageRequest});
			return 'Error: user have not rights';
		}";
	} else {
		$code = $code . "
			if(!members.items[from_id_index].is_admin){
			var user_name = '';
			var i = 0; while(i < members.profiles.length){
				if (from_id == members.profiles[i].id){
					user_name = '@id' + from_id + ' (' + members.profiles[i].first_name + ')';
				}
				i = i + 1;
			};
			var __DEFAULTMSG__ = user_name + ', ⛔ты не администратор беседы.';
			API.messages.send({$messageRequest});
			return 'Error: user have not rights';
		}";
	}
	return $code;
}

function bot_test_initcmd($event){
	$event->addMessageCommand("тестовая-клава", function($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$rnd = mt_rand(0, 256);
		$buttons = array(
			array(
				vk_text_button("Кнопка", array(
					'command' => 'test_button',
					'params' => array(
						'num' => $rnd
					)
				), "positive")
			)
		);
		$keyboard = vk_keyboard_inline($buttons);
		$botModule = new BotModule($db);
		$botModule->sendSimpleMessage($data->object->peer_id, ", Тестовая кнопка создана. Число внутри полезной нагрузки: {$rnd}.", $data->object->from_id, array("keyboard" => $keyboard));
	});

	$event->addKeyboardCommand("test_button", function($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$botModule->sendSimpleMessage($data->object->peer_id, ", Число внутри полезной нагрузки кнопки: {$payload->params->num}.", $data->object->from_id);
	});

	$event->addMessageCommand("!testtime", function ($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$msg = "VK Time: {$data->object->date}\nPHP Time: ".time();
		$botModule = new BotModule($db);
		$botModule->sendSimpleMessage($data->object->peer_id, $msg);
	});

	$event->addMessageCommand("!test-appeals", function($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$result = vk_execute($botModule->makeExeAppeals(array(1, 2, 558226012))." return appeals;");

		$botModule->sendSimpleMessage($data->object->peer_id, $result);
	});
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Работа с Database
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function bot_check_reg($db){ // Проверка на регистрацию
	if(is_null($db)){
		return false;
	}
	return true;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Прочее
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function bot_get_word_argv($words, $index, $default = ""){
	if(array_key_exists($index, $words))
		return $words[$index];
	else
		return $default;

}

function bot_message_not_reg($data){ // Legacy
	$msg = ", ⛔беседа не зарегистрирована. Используйте \"!reg\".";
	$botModule = new BotModule();
	$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
}

function bot_getconfig($name){
    $env = json_decode(file_get_contents(BOT_CONFIG_FILE_PATH), true);
    if($env == false){
    	error_log("Unable to read config.json file. File not exists or invalid.");
        return null;
    }

    return $env[$name];
}

function bot_keyboard_remove($data){
	$keyboard = vk_keyboard(false, array());
	$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => '✅Клавиатура убрана.', 'keyboard' => $keyboard), JSON_UNESCAPED_UNICODE);
	vk_execute("return API.messages.send({$json_request});");
}

function bot_like_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";
	if($command == "аву")
		fun_like_avatar($data, $db);
	/*elseif($command == "пост")
		fun_like_wallpost($data, $db);*/
	else{
		/*$commands = array(
			'Лайк аву - Лайкает аву',
			'Лайк пост <пост> - Лайкает пост'
		);*/
		$commands = array(
			'Лайк аву - Лайкает аву'
		);

		$botModule = new BotModule($db);
		$botModule->sendCommandListFromArray($data, ', используйте:', $commands);
	}
}

function bot_remove_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	if(array_key_exists(1, $words))
		$command = mb_strtolower($words[1]);
	else
		$command = "";
	if($command == "клавиатуру")
		bot_keyboard_remove($data);
	elseif($command == "ник")
		manager_remove_nick($data, $db);
	else{
		$commands = array(
			'Убрать клавиатуру - Убирает клавиатуру',
			'Убрать ник - Убирает ник пользователя'
		);

		$botModule = new BotModule($db);
		$botModule->sendCommandListFromArray($data, ', используйте:', $commands);
	}
}

function bot_getid($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;

	$botModule = new BotModule($db);

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(array_key_exists(1, $words) && bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} else {
		$botModule->sendSimpleMessage($data->object->peer_id, ", Ваш ID: {$data->object->from_id}.", $data->object->from_id);
		return;
	}

	$botModule->sendSimpleMessage($data->object->peer_id, ", ID: {$member_id}.", $data->object->from_id);
}

function bot_base64($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	$str_data = mb_substr($data->object->text, 8);
	$botModule = new BotModule($db);

	$CHARS_LIMIT = 300; // Переменная ограничения символов

	if($str_data == ""){
		$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Используйте !base64 <data>.", $data->object->from_id);
		return;
	}

	$decoded_data = base64_decode($str_data);

	if(!$decoded_data){
		$encoded_data = base64_encode($str_data);
		if(strlen($encoded_data) > $CHARS_LIMIT){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Зашифрованный текст превышает {$CHARS_LIMIT} симоволов.", $data->object->from_id);
			return;
		}
		$botModule->sendSimpleMessage($data->object->peer_id, ", Зашифрованный текст:\n{$encoded_data}", $data->object->from_id);
	}
	else{
		if(strlen($decoded_data) > $CHARS_LIMIT){
			$botModule->sendSimpleMessage($data->object->peer_id, ", Дешифрованный текст превышает {$CHARS_LIMIT} симоволов.", $data->object->from_id);
			return;
		}
		$botModule->sendSimpleMessage($data->object->peer_id, ", Дешифрованный текст:\n{$decoded_data}", $data->object->from_id);
	}
}

function bot_cmdlist($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;
	$event = &$finput->event;

	$botModule = new BotModule($db);
	if(array_key_exists(1, $words))
		$list_number_from_word = intval($words[1]);
	else
		$list_number_from_word = 1;

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = $event->getMessageCommandList(); // Входной список
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
		$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	$botModule->sendCommandListFromArray($data, ", список команд [$list_number/$list_max_number]:", $list_out);
}

function bot_call_all($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	$ranksys = new RankSystem($db);

	if(!$ranksys->checkRank($data->object->from_id, 1)){
		$botModule->sendSystemMsg_NoRights($data);
		return;
	}

	vk_execute($botModule->makeExeAppeal($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var from_id = {$data->object->from_id};
		var members = API.messages.getConversationMembers({'peer_id':peer_id});

		var msg = appeal+' созывает всех!';
		var i = 0; while (i < members.profiles.length){
			if(members.profiles[i].id != from_id){
				msg = msg + '@id'+members.profiles[i].id+'(&#12288;)';
			}
			i = i + 1;
		};
		API.messages.send({'peer_id':peer_id,'message':msg});
		");
}

function bot_help($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	if(array_key_exists(1, $words))
		$section = mb_strtolower($words[1]);
	else
		$section = "";
	$botModule = new BotModule($db);
	switch ($section) {
		case 'base':
			$commands = array(
				'!help <раздел> - Помощь в системе бота',
				'!reg - Регистрация беседы в системе бота',
				'!cmdlist <лист> - Список команд в системе бота',
				'!ник <ник> - Смена ника',
				'!ники - Показать ники пользователей',
				'!ранги - Вывод рангов пользователей в беседе',
				'Онлайн - Показать online пользователей'
			);

			$botModule->sendCommandListFromArray($data, ', 📰Основные команды:', $commands);
			break;

		case 'rp':
			$commands = array(
				'!me <действие> - выполнение действия от первого лица',
				'!do <действие> - выполнение действия от третьего лица',
				'!try <дествие> - выполнение действия с рандомным результатом (Удачно/Неудачно)',
				'!s <текст> - крик',
				'Секс <пользователь> - Секс с указанным пользователем',
				'Обнять <пользователь> - Обнимашки с пользователем',
				'Уебать <пользователь> - Ударить пользователя',
				'Обоссать <пользователь> - Обоссать пользователя',
				'Поцеловать <пользователь> - Поцеловать пользователя',
				'Харкнуть <пользователь> - Харкнуть в пользователя',
				'Отсосать <пользователь> - Отсосать пользователю',
				'Отлизать <пользователь> - Отлизать пользователю',
				'Послать <пользователь> - Отправить пользователя в далекие края',
				'Кастрировать <пользователь> - Лишить пользователя способности плодить себе подобных',
				'Посадить <пользователь> - Садит пользователя на бутылку',
				'Пожать руку <пользователь> - Жмет руку пользователю'
			);

			$botModule->sendCommandListFromArray($data, ', 📰Roleplay команды:', $commands);
			break;

		case 'gov':
			$commands = array(
				'!конституция - Показывает основную информацию государства',
				'!законы - Показывает законы государства',
				'!закон <дествие> <аргумент> - Управление законами',
				'!президент <аргумент> - Показывает и назначает президента государства',
				'!флаг <вложение> - Показывает и назначает гос. флаг',
				'!гимн <вложение> - Назначает и показывает гос. гимн',
				'!партия <название> - Устанавливает и показывает название действующей партии',
				'!столица <название> - Устанавливает и показывает нац. столицу',
				'!строй <название> - Устанавливает и показывает текущий гос. строй',
				'!стройлист - Выводит все доступные полит. строи',
				'!votestart - Запускает выборы президента',
				'!votestop - Прерывает выборы президента',
				'!candidate - Регистрация как кандидат на выборы',
				'!vote - Меню голосования'
			);

			$botModule->sendCommandListFromArray($data, ', 📰Государственные команды:', $commands);
			break;

		case 'manager':
			$commands = array(
				'!banlist <страница> - Список забаненных пользователей',
				'!ban <пользователь> - Бан пользователя в беседе',
				'!unban <пользователь> - Разбан пользователя в беседе',
				'!kick <пользователь> - Кик пользователя',
				'!ранг - Управление рангами пользователей',
				'!ранглист - Список доступных рангов',
				'!приветствие - Управление приветствием',
				'!stats - Управление статистикой беседы',
				'!modes - Список всех Режимов беседы',
				'!mode <name> <value> - Управление Режимом беседы',
			);

			$botModule->sendCommandListFromArray($data, ', 📰Команды управления беседой:', $commands);
			break;

		case 'other':
			$commands = array(
				'!зов - Упоминает всех участников беседы',
				'!чулки - Случайная фотография девочек в чулочках',
				'!амина - Случайная фотография со стены @id363887574 (Амины Мирзоевой)',
				'!карина - Случайная фотография со стены @id153162173 (Карины Сычевой)',
				'!бузова - Случайная фотография со стены @olgabuzova (Ольги Бузовой)',
				'!giphy <текст> - Гифка с сервиса giphy.com',
				'!id <пользователь> - Получение VK ID пользователя',
				//'!tts <текст> - Озвучивает текст и присылает голос. сообщение',
				'!base64 <data> - Шифрует и Дешифрует данные в base64',
				'!shrug - ¯\_(ツ)_/¯',
				'!tableflip - (╯°□°）╯︵ ┻━┻',
				'!unflip - ┬─┬ ノ( ゜-゜ノ)',
				'!say <params> - Отправляет сообщение в текущую беседу с указанными параметрами',
				'Выбери <v1> или <v2> или <v3>... - Случайный выбор одного из вариантов',
				'Сколько <ед. измерения> <дополнение> - Сколько чего-то там что-то там',
				'Инфа <выражение> - Вероятность выражения',
				'Бутылочка - Мини-игра "Бутылочка"',
				'Лайк <что-то> - Ставит лайк на что-то',
				'Убрать <что-то> - Что-то убирает',
				'Слова - Игра "Слова"',
				'Words - Игра "Слова" на Английском языке',
				'Загадки - Игры "Загадки"',
				'Брак помощь - Помощь по системе браков',
				'Браки - Список действующих браков беседы',
				'Браки история - Список всех браков беседы'
			);

			$botModule->sendCommandListFromArray($data, ', 📰Другие команды:', $commands);
			break;
		
		default:
			$botModule->sendCommandListFromArray($data, ', ✅Используйте:', array(
				'!help base - Базовый раздел',
				'!help rp - Roleplay раздел',
				'!help gov - Гос. раздел',
				'!help manager - Раздел управления',
				'!help other - Другое'
			));
			break;
	}
}

?>