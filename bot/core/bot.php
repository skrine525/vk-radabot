<?php

class BotModule{
	private $db;

	public function __construct(&$db){
		$this->db = &$db;
	}

	public function makeExeAppeal($user_id, $varname = "appeal"){ // Создание переменной appeal с обращением к пользователю, посредством VKScript и vk_execute()
		if(array_key_exists("id{$user_id}", $this->db["bot_manager"]["user_nicknames"])){
			$user_nick = $this->db["bot_manager"]["user_nicknames"]["id{$user_id}"];

			return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ({$user_nick})'; user = null;";
		}
		else{
			return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ('+user.first_name.substr(0, 2)+'. '+user.last_name+')'; user = null;";
		}
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
		//$confa_info = json_decode(vk_call('messages.getConversationsById', array('peer_ids' => $data->object->peer_id, 'extended' => 1, 'fields' => 'first_name_gen,last_name_gen')));
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
			//$president_data = json_decode(vk_call('users.get', array('user_ids' => $data->object->from_id, 'fields' => 'first_name_gen,last_name_gen')));
			$gov_data = array('soc_order' => 1,
			'president_id' => $data->object->from_id,
			'parliament_id' => $data->object->from_id,
			'batch_name' => $response->batch_name,
			'laws' => array(),
			'anthem' => "nil",
			'flag' => "nil",
			'capital' => 'г. Мда');
			$db["goverment"] = $gov_data;
			$db["bot_manager"]["user_ranks"] = array(
				"id{$data->object->from_id}" => 0
			);
		}	
	} else {
		$msg = ", данная беседа уже зарегистрирована.";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+'{$msg}'});
			");
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

function bot_leave_autokick($data){ // Автокик после выхода из беседы
	if(!is_null($data->object->action)){
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

function bot_execute_api($data){ // API for !exe and !exe_debug commands
	$params = "var peer_id = {$data->object->peer_id};\n
	var from_id = {$data->object->from_id};\n";
	return $params;
}

function bot_banned_kick($data, &$db){ // Кик забаненных пользователей после приглашения
	$banned_users = bot_get_ban_array($db);

	if(!is_null($data->object->action)){
		if ($data->object->action->type == "chat_invite_user"){
			$botModule = new BotModule($db);
			$GLOBALS['CAN_SEND_INVITED_GREETING_MESSAGE'] = true;
			for($i = 0; $i < sizeof($banned_users); $i++){
				if ($banned_users[$i] == $data->object->action->member_id){
					$GLOBALS['CAN_SEND_INVITED_GREETING_MESSAGE'] = false;
					$chat_id = $data->object->peer_id - 2000000000;
					$res = array();
					$ranksys = new RankSystem($db);
					if($ranksys->checkRank($data->object->from_id, 1)){
						$res = json_decode(vk_execute("
							API.messages.send({'peer_id':{$data->object->peer_id},'message':'@id{$data->object->action->member_id} (Пользователь) был приглашен @id{$data->object->from_id} (администратором) беседы и автоматически разбанен.'});
							return 1;
							"));
					}
					else{
						$res = json_decode(vk_execute($botModule->makeExeAppeal($data->object->action->member_id)."
							API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+', таким долбаебам как ты, тут не место!'});
							API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$data->object->action->member_id}});
							return 0;
							"));
					}
					if($res->response == 1){
						$GLOBALS['CAN_SEND_INVITED_GREETING_MESSAGE'] = true;
						$banned_users = bot_get_ban_array($db);
						$user_id = $data->object->action->member_id;
						for($i = 0; $i < sizeof($banned_users); $i++){
							if($user_id == $banned_users[$i]){
								$banned_users[$i] = $banned_users[sizeof($banned_users)-1];
								unset($banned_users[sizeof($banned_users)-1]);
								bot_set_ban_array($db, $banned_users);
								break;
							}
						}
					}
				}
			}
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

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Работа с Database
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function bot_set_ban_array(&$db, $array){
	$db["bot_manager"]["banned_users"] = $array;
}

function bot_get_ban_array($db){
	if (is_null($db["bot_manager"]["banned_users"])){
		return array();
	} else {
		return $db["bot_manager"]["banned_users"];
	}
}

function bot_check_reg($db){ // Проверка на регистрацию
	if(is_null($db)){
		return false;
	}
	return true;
}


function bot_message_not_reg($data){ // Legacy
	$msg = ", ⛔беседа не зарегистрирована. Используйте \"!reg\".";
	$botModule = new BotModule();
	$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Прочее
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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
	$command = mb_strtolower($words[1]);
	if($command == "аву")
		fun_like_avatar($data, $db);
	elseif($command == "пост")
		fun_like_wallpost($data, $db);
	else{
		$commands = array(
			'Лайк аву - Лайкает аву',
			'Лайк пост <пост> - Лайкает пост'
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
	$command = mb_strtolower($words[1]);
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

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} else {
		$botModule->sendSimpleMessage($data->object->peer_id, ", Ваш ID: {$data->object->from_id}.", $data->object->from_id);
		return 0;
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
		return 0;
	}

	$decoded_data = base64_decode($str_data);

	if(!$decoded_data){
		$encoded_data = base64_encode($str_data);
		if(strlen($encoded_data) > $CHARS_LIMIT){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Зашифрованный текст превышает {$CHARS_LIMIT} симоволов.", $data->object->from_id);
			return 0;
		}
		$botModule->sendSimpleMessage($data->object->peer_id, ", Зашифрованный текст:\n{$encoded_data}", $data->object->from_id);
	}
	else{
		if(strlen($decoded_data) > $CHARS_LIMIT){
			$botModule->sendSimpleMessage($data->object->peer_id, ", Дешифрованный текст превышает {$CHARS_LIMIT} симоволов.", $data->object->from_id);
			return 0;
		}
		$botModule->sendSimpleMessage($data->object->peer_id, ", Дешифрованный текст:\n{$decoded_data}", $data->object->from_id);
	}
}

function bot_cmdlist($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$botModule = new BotModule($db);
	if(!is_null($words[1]))
		$list_number_from_word = intval($words[1]);
	else
		$list_number_from_word = 1;

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = $GLOBALS["event_command_list"]; // Входной список
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
		return 0;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	$botModule->sendCommandListFromArray($data, ", список команд [$list_number/$list_max_number]:", $list_out);
}

function bot_help($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	mb_internal_encoding("UTF-8");
	$section = mb_strtolower($words[1]);
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
				'Посадить <пользователь> - Садит пользователя на бутылку'
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
				'!stats - Управление статистикой беседы'
			);

			$botModule->sendCommandListFromArray($data, ', 📰Команды управления беседой:', $commands);
			break;

		case 'other':
			$commands = array(
				'!чулки - Случайная фотография девочек в чулочках',
				'!амина - Случайная фотография со стены @id363887574 (Амины Мирзоевой)',
				'!карина - Случайная фотография со стены @id153162173 (Карины Сычевой)',
				'!бузова - Случайная фотография со стены @olgabuzova (Ольги Бузовой)',
				'!giphy <текст> - Гифка с сервиса giphy.com',
				'!id <пользователь> - Получение VK ID пользователя',
				'!tts <текст> - Озвучивает текст и присылает голос. сообщение',
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
				'Слова старт - Запускает игру "Слова"',
				'Слова рейтинг - Выводит рейтинг игроков в игре "Слова"'
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

/*function bot_keyboard($data, $words){
	mb_internal_encoding("UTF-8");
	$command = mb_strtolower($words[1]);

	if($command == "создать"){
		$one_time = intval($words[2]);
		$array = array();
		$array_index = -1;
		$can_edit_array = false;
		$button_name = "";
		$button_color = "";

		for($i = 0; $i < count($words); $i++){
			$words[$i] = str_ireplace("\n", "", $words[$i]);
		}

		for($i = 3; $i < count($words); $i++){
			if ($words[$i] == "_begin"){
				$can_edit_array = true;
				$array[] = array();
				$array_index = count($array)-1;
			} elseif($words[$i] == "_end"){
				$can_edit_array = false;
			} elseif($words[$i] == "_bt_begin" && $can_edit_array){
				$button_name = "";
				$button_color = "";
			} elseif($words[$i] == "_bt_label" && $can_edit_array){
				$button_name = str_ireplace("%+%", " ", $words[$i+1]);
			} elseif($words[$i] == "_bt_color" && $can_edit_array){
				$button_color = $words[$i+1];
			} elseif($words[$i] == "_bt_end" && $can_edit_array){
				if(count($array[$array_index]) < 4){
					$array[$array_index][] = vk_text_button($button_name, "", $button_color);
				}
			}
		}

		$keyboard = vk_keyboard($one_time, $array);

		bot_debug($keyboard);

		vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'Клавиатура:','keyboard':'{$keyboard}'});");
	} elseif ($command == "убрать"){
		$keyboard = vk_keyboard($one_time, array());
		vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'Клавиатура убрана.','keyboard':'{$keyboard}'});");
	}
}*/

?>