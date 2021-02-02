<?php

namespace Bot{
	class Event{
		// Переменные
		private $data;
		private $db;
		private $textMessageCommands;			// Массив текстовых команд
		private $textButtonCommands;			// Массив команд Text-кнопок
		private $callbackButtonCommands;		// Массив команд Callback-кнопок

		// Константы
		const COMMAND_RESULT_OK = 0;			// Константа результата выполнения команды без ошибок
		const COMMAND_RESULT_NO_DB = 1;			// Константа результата выполнения команды с ошибкой, которая не способна работать без Базы данных
		const COMMAND_RESULT_UNKNOWN = 2;		// Константа результата выполнения команды с другими ошибками

		function __construct($data) {
			$this->data = $data;
			$this->textMessageCommands = array();
			$this->textButtonCommands = array();
			$this->callbackButtonCommands = array();

			if($this->data->object->peer_id > 2000000000){
				// Если идентификатор назначения группового чата, то подгружаем Базу данных группового чата
				$chat_id = $this->data->object->peer_id - 2000000000;
				$this->db = new \Database(BOT_DBDIR."/chat{$chat_id}.json");
			}
		}

		public function getData(){
			return $this->data;
		}

	  	public function getDatabase(){
	  		return $this->db;
	  	}

	  	public function addTextMessageCommand($command, $callback, $ignore_db = false){
	  		if(!$this->isTextMessageCommand($command) && is_callable($callback)){
	  			$this->textMessageCommands[$command] = (object) array(
	  				'callback' => $callback,
	  				'ignore_db' => $ignore_db
	  			);
	  			return true;
	  		}
	  		else
	  			return false;
	  	}

	  	public function isTextMessageCommand($command){
	  		return array_key_exists($command, $this->textMessageCommands);
	  	}

	  	public function addTextButtonCommand($command, $callback, $ignore_db = false){
	  		if(!$this->isTextButtonCommand($command) && is_callable($callback)){
	  			$this->textButtonCommands[$command] = (object) array(
	  				'callback' => $callback,
	  				'ignore_db' => $ignore_db
	  			);
	  			return true;
	  		}
	  		else
	  			return false;
	  	}

	  	public function isTextButtonCommand($command){
	  		return array_key_exists($command, $this->textButtonCommands);
	  	}

	  	public function addCallbackButtonCommand($command, $callback, $ignore_db = false){
	  		if(!$this->isCallbackButtonCommand($command) && is_callable($callback)){
	  			$this->callbackButtonCommands[$command] = (object) array(
	  				'callback' => $callback,
	  				'ignore_db' => $ignore_db
	  			);
	  			return true;
	  		}
	  		else
	  			return false;
	  	}

	  	public function isCallbackButtonCommand($command){
	  		return array_key_exists($command, $this->callbackButtonCommands);
	  	}

	  	public function getTextMessageCommandList(){
	  		$list = array();
	  		foreach ($this->textMessageCommands as $key => $value) {
	  			$list[] = $key;
	  		}
	  		return $list;
	  	}

	  	public function exit(){
	  		unset($this);
	  	}

	  	public function runTextMessageCommand($data){
	  		if(gettype($data) == "object"){
	  			$argv = bot_parse_argv($data->object->text); // Извлекаем аргументы из сообщения
				$command = mb_strtolower(bot_get_array_value($argv, 0, "")); // Переводим команду в нижний регистр

				if($this->isTextMessageCommand($command)){
					$command_data = $this->textMessageCommands[$command];

					// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
					if(!$command_data->ignore_db && !$this->db->isExists())
						return Event::COMMAND_RESULT_NO_DB;

					$finput = (object) array(
						'data' => $data,
						'argv' => $argv,
						'db' => $this->db,
						'event' => $this
					);
					$callback = $command_data->callback; // Получение Callback'а
					call_user_func_array($callback, array($finput)); // Выполнение Callback'а
					return Event::COMMAND_RESULT_OK;
				}
	  		}
	  		return Event::COMMAND_RESULT_UNKNOWN;
	  	}

	  	public function runTextButtonCommand($data){
	  		if(gettype($data) == "object"){
	  			if(property_exists($data->object, "payload")){
					$payload = (object) json_decode($data->object->payload);
					if(!is_null($payload) && property_exists($payload, "command") && $this->isTextButtonCommand($payload->command)){
						$command_data = $this->textButtonCommands[$payload->command];

						// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
						if(!$command_data->ignore_db && !$this->db->isExists())
							return Event::COMMAND_RESULT_NO_DB;

						$finput = (object) array(
							'data' => $data,
							'payload' => $payload,
							'db' => $this->db,
							'event' => $this
						);

						$callback = $command_data->callback; // Получение Callback'а
						call_user_func_array($callback, array($finput)); // Выполнение Callback'а
						return Event::COMMAND_RESULT_OK;
					}
	  			}
	  		}
	  		return Event::COMMAND_RESULT_UNKNOWN;
	  	}

	  	public function runCallbackButtonCommand($data){
	  		if(gettype($data) == "object"){
	  			if(property_exists($data->object, "payload") && gettype($data->object->payload) == 'array'){
					$payload = $data->object->payload;
					if(array_key_exists(0, $payload) && $this->isCallbackButtonCommand($payload[0])){
						$command_data = $this->callbackButtonCommands[$payload[0]];
						
						// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
						if(!$command_data->ignore_db && !$this->db->isExists())
							return Event::COMMAND_RESULT_NO_DB;

						$finput = (object) array(
							'data' => $data,
							'payload' => $payload,
							'db' => $this->db,
							'event' => $this
						);

						$callback = $command_data->callback; // Получение Callback'а
						call_user_func_array($callback, array($finput)); // Выполнение Callback'а
						return Event::COMMAND_RESULT_OK;
					}
	  			}
	  		}
	  		return Event::COMMAND_RESULT_UNKNOWN;
	  	}

	  	public function handle($defaultFunc = null){
	  		switch($this->data->type){
				case 'message_new':
				if($this->data->object->from_id <= 0){ // Игнорирование сообщений других чат-ботов
					return false;
				}

				// Обработка клавиатурных команд
				$result = $this->runTextButtonCommand($this->data);
				if($result == Event::COMMAND_RESULT_OK)
					return true;
				elseif($result == Event::COMMAND_RESULT_NO_DB){
					bot_message_not_reg($this->data, $this->db);
					return false;
				}

				// Обработка тектовых команд
				$result = $this->runTextMessageCommand($this->data);
				if($result == Event::COMMAND_RESULT_OK)
					return true;
				elseif($result == Event::COMMAND_RESULT_NO_DB){
					bot_message_not_reg($this->data, $this->db);
					return false;
				}

				// Обработка не командный сообщений
				if(!is_null($defaultFunc) && is_callable($defaultFunc)){
					if(!$this->db->isExists()){ // Проверка на регистрацию в системе
						return false;
					}
					$finput = (object) array(
						'data' => $this->data,
						'db' => $this->db,
						'event' => $this
					);
					call_user_func_array($defaultFunc, array($finput)); // Выполнение Callback'а
					return true;
				}
				break;

				case 'message_event':
				if($this->data->object->user_id <= 0){ // Игнорирование действий сообщений других чат-ботов
					return false;
				}

				// Обработка клавиатурных команд
				$result = $this->runCallbackButtonCommand($this->data);
				if($result == Event::COMMAND_RESULT_OK)
					return true;
				elseif($result == Event::COMMAND_RESULT_NO_DB){
					bot_message_not_reg($this->data, $this->db);
					return false;
				}
				else{
					bot_show_snackbar($this->data->object->event_id, $this->data->object->user_id, $this->data->object->peer_id, '⛔ Неизвестная команда.');
					return false;
				}
				break;
			}
			return false;
	  	}
	}

	class Messages{
		private $db;
		private $appeal_id;

		// Константы шаблонных сообщений
		const MESSAGE_NO_RIGHTS = "%appeal%, ⛔У вас нет прав для использования этой команды.";

		public function __construct($db = null){
			$this->db = $db;
			$this->appeal_id = null;
		}

		public function setAppealID($appeal_id){
			$this->appeal_id = $appeal_id;
		}

		public function getAppealID(){
			return $this->appeal_id;
		}

		public function makeExeAppealByID($user_id, $varname = "appeal"){ // Создание переменной appeal с обращением к пользователю, посредством VKScript и vk_execute()
			if(!is_null($this->db))
				$user_nick = $this->db->getValue(array("chat_settings", "user_nicknames", "id{$user_id}"), false);
			else
				$user_nick = false;

			if($user_nick !== false){
				return "var user=API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname}='@'+user.screen_name+' ({$user_nick})'; user=null;";
			}
			else{
				return "var user=API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname}='@'+user.screen_name+' ('+user.first_name.substr(0, 2)+'. '+user.last_name+')'; user =null;";
			}
		}

		public function makeExeAppeal($varname = "appeal"){
			return $this->makeExeAppealByID($this->appeal_id, $varname);
		}

		function sendMessage($peer_id, $message, $params = array()){ // Отправка сообщений
			$appeal_code = "";
			if(gettype($this->appeal_id) == "integer")
				$appeal_code = $this->makeExeAppealByID($this->appeal_id);
			$request_array = array('peer_id' => $peer_id, 'message' => $message);
			foreach ($params as $key => $value) {
				$request_array[$key] = $value;
			}
			$json_request = json_encode($request_array, JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			return vk_execute("{$appeal_code}return API.messages.send({$json_request});");
		}

		function editMessage($peer_id, $conversation_message_id, $message, $params = array()){
			$appeal_code = "";
			if(gettype($this->appeal_id) == "integer")
				$appeal_code = $this->makeExeAppealByID($this->appeal_id);
			$request_array = array('peer_id' => $peer_id, 'conversation_message_id' => $conversation_message_id, 'message' => $message);
			foreach ($params as $key => $value) {
				$request_array[$key] = $value;
			}
			$json_request = json_encode($request_array, JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			return vk_execute("{$appeal_code}return API.messages.edit({$json_request});");
		}

		function sendSilentMessage($peer_id, $message, $params = array()){ // Отправка сообщений без упоминаний
			if(gettype($params) == "array")
				$params['disable_mentions'] = true;
			else
				$params = array('disable_mentions' => true);
			return $this->sendMessage($peer_id, $message, $params);
		}

		function sendSilentMessageWithListFromArray($peer_id, $message = "", $list = array(), $keyboard = null){ // Legacy
			for($i = 0; $i < count($list); $i++){
				$message = $message . "\n• " . $list[$i];
			}
			if(is_null($keyboard))
				$this->sendSilentMessage($peer_id, $message);
			else
				$this->sendSilentMessage($peer_id, $message, array("keyboard" => $keyboard));
		}
	}

	class ListBuilder{
		private $list;
		private $size;

		function __construct($list, $size){
			if(gettype($list) == "array" && gettype($size) == "integer"){
				$this->list = $list;
				$this->size = $size;
			}
			else
				return false;
		}

		public function build($list_number){
			$list_out = array(); // Выходной список
			
			if(count($this->list) % $this->size == 0)
				$list_max_number = intdiv(count($this->list), $this->size);
			else
				$list_max_number = intdiv(count($this->list), $this->size)+1;
			$list_min_index = ($this->size*$list_number)-$this->size;
			if($this->size*$list_number >= count($this->list))	
				$list_max_index = count($this->list)-1;
			else
				$list_max_index = $this->size*$list_number-1;
			if($list_number <= $list_max_number && $list_number > 0){
				for($i = $list_min_index; $i <= $list_max_index; $i++){
					$list_out[] = $this->list[$i];
				}
			}
			else
				return (object) array('result' => false);

			return (object) array(
				'result' => true,
				'list' => (object) array(
					'number' => $list_number,
					'max_number' => $list_max_number,
					'out' => $list_out
				)
			);
		}
	}
}

namespace{
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// Базовые константы бота
	define('BOT_DIR', dirname(__DIR__)); 								// Корневая директория бота
	define('BOT_DATADIR', BOT_DIR."/data"); 							// Директория данных
	define('BOT_DBDIR', BOT_DIR."/data/database"); 						// Директория базы данных
	define('BOT_TMPDIR', dirname(BOT_DIR)."/tmp"); 						// Директория временных файлов
	define('BOT_CONFIG_FILE_PATH', BOT_DATADIR."/config.json"); 		// Путь к главному файлу конфигураций бота

	mb_internal_encoding("UTF-8");										// UTF-8 как основная кодировка для mbstring

	$GLOBALS['modules_importtime_start'] = microtime(true);				// Время подключения модулей: Начало

	// Составные модули бота
	require_once(__DIR__."/vk.php"); 									// Модуль, отвечающий за все взаимодействия с VK API
	require_once(__DIR__."/database.php"); 								// Модуль, отвечающий за взаимодействие основной базы данных бота
	require_once(__DIR__."/government.php");	 						// Модуль, отвечающий за работу гос. устройства беседы
	require_once(__DIR__."/economy.php"); 								// Модуль, отвечающий за систему Экономики
	require_once(__DIR__."/fun.php"); 									// Модуль, отвечающий за развлечения
	require_once(__DIR__."/roleplay.php"); 								// Модуль, отвечающий за Roleplay команды
	require_once(__DIR__."/manager.php"); 								// Модуль, отвечающий за управление беседой
	require_once(__DIR__."/giphy.php"); 								// Модуль, отвечающий за функции взаимодействия с GIPHY API
	require_once(__DIR__."/word_game.php"); 							// Модуль, отвечающий за игры Слова и Words
	require_once(__DIR__."/stats.php"); 								// Модуль, отвечающий за ведение статистики в беседах
	require_once(__DIR__."/legacy.php");								// Модуль, отвечающий за Legacy функции

	$GLOBALS['modules_importtime_end'] = microtime(true);				// Время подключения модулей: Конец

	function bot_handle_event($data){
		if($data->object->peer_id < 2000000000){ // Запрет использование бота в лс
			///////////////////////////
			/// Обработка бота в Личном
			///////////////////////////
			vk_call('messages.send', array('peer_id'=>$data->object->peer_id,'message'=>'Бот работает только в беседах. Вы можете добавить бота в беседу соответствующей кнопкой в меню бота на главной странице.'));
		}
		else{
			///////////////////////////
			/// Обработка бота в Беседе
			///////////////////////////

			// Инициализируем класс
			$event = new Bot\Event($data);

			bot_debug_cmdinit($event);						// Инициализация команд отладочного режима

			$GLOBALS['cmd_initime_start'] = microtime(true);// Время инициализации команд: Начало

			bot_initcmd($event);							// Инициализация команд модуля bot
			government_initcmd($event);						// Инициализация команд Гос. устройства
			manager_initcmd($event);						// Инициализация команд модуля manager
			stats_initcmd($event);							// Инициализация команд модуля stats
			roleplay_initcmd($event);						// RP-команды
			fun_initcmd($event);							// Fun-команды
			giphy_initcmd($event);							// Инициализация команд модуля giphy
			wordgame_initcmd($event);						// Игра Слова
			economy_initcmd($event);						// Economy

			$GLOBALS['cmd_initime_end'] = microtime(true);	// Время инициализации команд: Конец

			// Обработка

			bot_pre_handle($event);							// Функция предварительной обработки
			$event->handle(function ($finput){
				// Инициализация базовых переменных
				$data = $finput->data; 
				$db = $finput->db;

				government_referendum_system($data, $db); 	// Обработчик выборов президента в беседе

				bot_message_action_handler($finput); 		// Обработчик событий сообщений

				fun_handler($data, $db);
				wordgame_gameplay($data, $db); 				// Освновной обработчик игры Слова

				$db->save();
			}); 											// Обработка события бота
			bot_post_handle($event);						// Функция вторичной обработки

			$event->exit(); 								// Очищение памяти
		}
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// Legacy Module
	class BotModule{
		private $db;

		public function __construct(&$db = null){
			$this->db = &$db;
		}

		public function makeExeAppealByID($user_id, $varname = "appeal"){ // Создание переменной appeal с обращением к пользователю, посредством VKScript и vk_execute()
			if(!is_null($this->db))
				$user_nick = $this->db->getValue(array("chat_settings", "user_nicknames", "id{$user_id}"), false);
			else
				$user_nick = false;

			if($user_nick !== false){
				return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ({$user_nick})'; user = null;";
			}
			else{
				return "var user = API.users.get({'user_ids':[{$user_id}],'fields':'screen_name'})[0]; var {$varname} = '@'+user.screen_name+' ('+user.first_name.substr(0, 2)+'. '+user.last_name+')';user = null;";
			}
		}

		function sendMessage($peer_id, $message, $from_id = null, $params = array()){ // Отправка сообщений
			$appeal_code = "";
			if(gettype($from_id) == "integer"){
				$appeal_code = $this->makeExeAppealByID($from_id);
				$message = "%appeal%{$message}";
			}
			$request_array = array('peer_id' => $peer_id, 'message' => $message);
			foreach ($params as $key => $value) {
				$request_array[$key] = $value;
			}
			$json_request = json_encode($request_array, JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			return vk_execute("{$appeal_code}return API.messages.send({$json_request});");
		}

		function editMessage($peer_id, $conversation_message_id, $from_id = null, $message, $params = array()){
			$appeal_code = "";
			if(gettype($from_id) == "integer"){
				$appeal_code = $this->makeExeAppealByID($from_id);
				$message = "%appeal%{$message}";
			}
			$request_array = array('peer_id' => $peer_id, 'conversation_message_id' => $conversation_message_id, 'message' => $message);
			foreach ($params as $key => $value) {
				$request_array[$key] = $value;
			}
			$json_request = json_encode($request_array, JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			return vk_execute("{$appeal_code}return API.messages.edit({$json_request});");
		}

		function sendSilentMessage($peer_id, $message, $from_id = null, $params = array()){ // Отправка сообщений без упоминаний
			if(gettype($params) == "array")
				$params['disable_mentions'] = true;
			else
				$params = array('disable_mentions' => true);
			return $this->sendMessage($peer_id, $message, $from_id, $params);
		}

		function sendSystemMsg_NoRights($data){
			$this->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет прав для использования этой команды.", $data->object->from_id);
		}

		function sendCommandListFromArray($data, $message = "", $list = array(), $keyboard = null){ // Legacy
			$msg = $message;
			for($i = 0; $i < count($list); $i++){
				$msg = $msg . "\n• " . $list[$i];
			}
			if(is_null($keyboard))
				$this->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
			else
				$this->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
		}
	}

	class RandomOrg{ // Класс для работы с Random.org
		public static function generateIntegers($min, $max, $n, $id = 0, $replacement = true){
			$options = array(
		   		'http' => array(  
		            'method'  => 'POST',
		            'header'  => 'Content-type: application/json', 
		            'content' => json_encode(array(
		            	'jsonrpc' => '2.0',
		            	'method' => 'generateIntegers',
		            	'params' => array(
		            		'apiKey' => bot_getconfig('RANDOMORG_API_KEY'),
		            		'n' => $n,
		            		'min' => $min,
		            		'max' => $max,
		            		'replacement' => $replacement
		            	),
		            	'id' => $id
		            ))
		        )  
			);
			$recieved_data = file_get_contents('https://api.random.org/json-rpc/2/invoke', false, stream_context_create($options));
			if($recieved_data !== false)
				return json_decode($recieved_data, true);
			return false;
		}
	}

	class GameController{
		const GAME_SESSIONS_DIRECTORY = BOT_DATADIR."/game_sessions";

		private static function initGameSessionsDirectory(){
			if(!file_exists(self::GAME_SESSIONS_DIRECTORY))
				mkdir(self::GAME_SESSIONS_DIRECTORY);
		}

		public static function getSession($chat_id){
			self::initGameSessionsDirectory();
			if(file_exists(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json")){
				$data = json_decode(file_get_contents(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json"), true);
				if($data !== false)
					return (object) $data;
			}
			return false;
		}

		public static function setSession($chat_id, $id, $object){
			self::initGameSessionsDirectory();
			if(file_exists(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json")){
				$data = json_decode(file_get_contents(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json"), true);
				if($data !== false && $data["id"] == $id){
					$data["object"] = $object;
					if(file_put_contents(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json", json_encode($data, JSON_UNESCAPED_UNICODE)) === false)
						return false;
					else
						return true;
				}
				else{
					return false;
				}
			}
			else{
				$data = array(
					'id' => $id,
					'object' => $object
				);
				if(file_put_contents(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json", json_encode($data, JSON_UNESCAPED_UNICODE)) === false)
					return false;
				else
					return true;
			}
		}

		public static function deleteSession($chat_id, $id){
			self::initGameSessionsDirectory();
			if(file_exists(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json")){
				$data = json_decode(file_get_contents(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json"), true);
				if($data !== false && $data["id"] == $id)
					return unlink(self::GAME_SESSIONS_DIRECTORY."/chat{$chat_id}.json");
			}
			return false;
		}
	}

	// Инициализация команд
	function bot_initcmd($event){
		// Игнорирование отсутствие базы данных для следующих команд

		// Основное
		$event->addTextMessageCommand("!cmdlist", 'bot_cmdlist');
		$event->addTextMessageCommand("!reg", 'bot_register', true);
		$event->addTextMessageCommand("!помощь", 'bot_help');

		// Система управления беседой
		$event->addTextMessageCommand("!меню", 'bot_menu_tc');

		// Прочее
		$event->addTextMessageCommand("!лайк", 'bot_like_handler');
		$event->addTextMessageCommand("!убрать", 'bot_remove_handler');
		$event->addTextMessageCommand("!id", 'bot_getid');
		$event->addTextMessageCommand("!base64", 'bot_base64');
		$event->addTextMessageCommand("!зов", 'bot_call_all');
		$event->addTextMessageCommand("!крестики-нолики", 'bot_tictactoe');

		// Обработчик для запуска текстовых команд из под аргумента кнопки
		$event->addTextButtonCommand("bot_runtc", 'bot_keyboard_rtct_handler'); // Запуск текстовых команд из под Text-кнопки

		// Callback-кнопки
		$event->addCallbackButtonCommand("bot_menu", 'bot_menu_cb');
		$event->addCallbackButtonCommand("bot_cmdlist", 'bot_cmdlist_cb');
		$event->addCallbackButtonCommand('bot_tictactoe', 'bot_tictactoe_cb');
		$event->addCallbackButtonCommand('bot_reg', 'bot_register_cb', true);
	}

	function bot_register($finput){ // Регистрация чата
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		if (!$db->isExists()){
			$response = json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->from_id).bot_test_rights_exe($data->object->peer_id, $data->object->from_id, "API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', &#9940;У вас нет прав для этой команды.','disable_mentions':true});return 0;", true)."var chat=API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}],'extended':1}).items[0];
				if(chat.peer.type!='chat'){API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', эта беседа не является групповым чатом.','disable_mentions':true});return{'result':0};}API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ✅Беседа успешно зарегистрирована.','disable_mentions':true});return 1;"))->response;
			if($response == 1){
				$chat_id = $data->object->peer_id - 2000000000;
				$db->setValue(array("chat_id"), $chat_id);
				$db->setValue(array("owner_id"), $data->object->from_id);
				$db->save(true);
			}	
		}
		else{
			$msg = ", данная беседа уже зарегистрирована.";
			vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."return API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+'{$msg}','disable_mentions':true});");
		}
	}

	function bot_register_cb($finput){ // Регистрация чата
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		if (!$db->isExists()){
			$snackbar1_json = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "&#9940; У вас нет прав для этой команды."), JSON_UNESCAPED_UNICODE)));
			$snackbar2_json = json_encode(array('event_id' => $data->object->event_id, 'user_id' => $data->object->user_id, 'peer_id' => $data->object->peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => "&#9940; Эта беседа не является групповым."), JSON_UNESCAPED_UNICODE)));
			$response = json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->user_id).bot_test_rights_exe($data->object->peer_id, $data->object->user_id, "API.messages.sendMessageEventAnswer({$snackbar1_json});return 0;", true)."var chat=API.messages.getConversationsById({'peer_ids':[{$data->object->peer_id}],'extended':1}).items[0];
				if(chat.peer.type!='chat'){API.messages.sendMessageEventAnswer({$snackbar2_json});return 0;}API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':appeal+', ✅Беседа успешно зарегистрирована.','disable_mentions':true});return 1;"))->response;
			if($response == 1){
				$chat_id = $data->object->peer_id - 2000000000;
				$db->setValue(array("chat_id"), $chat_id);
				$db->setValue(array("owner_id"), $data->object->user_id);
				$db->save();
			}	
		}
		else
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '&#9940; Данная беседа уже зарегистрирована.');
	}


	function bot_parse_argv($text){
		$argv = array();
		foreach (str_getcsv($text, ' ') as $v) {
			if($v != "")
				$argv[] = $v;
		}
		return $argv;
	}

	function bot_pre_handle($event){
		$db = $event->getDatabase();
		$data = $event->getData();
		

		if($data->object->peer_id > 2000000000 && $db->isExists()){
			switch ($data->type) {
				case 'message_new':

				// Антифлуд
				if(AntiFlood::handler($data, $db)){
					$event->exit();
					exit;
				}

				// Статистика
				stats_update_messagenew($event, $data, $db); 	// Ведение статистики в беседе
				break;

				case 'message_event':
				stats_update_messageevent($event, $data, $db); 	// Ведение статистики в беседе
				break;
			}
		}
	}

	function bot_post_handle($event){
		$db = $event->getDatabase();

		if($db->getSavesCount() == 0){
			$db->save();
		}
	}

	// Функция для отправки Snackbar'а
	function bot_show_snackbar($event_id, $user_id, $peer_id, $text){
		return vk_call('messages.sendMessageEventAnswer', array('event_id' => $event_id, 'user_id' => $user_id, 'peer_id' => $peer_id, 'event_data' => json_encode(array('type' => 'show_snackbar', 'text' => $text), JSON_UNESCAPED_UNICODE)));
	}

	function bot_is_mention($msg){ // Проверка упоминания пользователя
		if(mb_substr($msg, 0, 3) == "[id" && mb_substr($msg, mb_strlen($msg) - 1, mb_strlen($msg) - 1) == "]"){
			if(sizeof(explode("|", $msg)) >= 2){
				return true;
			}
		}
		return false;
	}

	function bot_get_id_from_mention($msg){ // Получение ID из упоминания
		if(bot_is_mention($msg)){
			return intval(explode('|', mb_substr($msg, 3, mb_strlen($msg)))[0]);
		}
		return null;
	}

	function bot_debug($str){ // Debug function
		$messagesModule = new Bot\Module();
		$messagesModule->sendMessage(bot_getconfig('DEBUG_USER_ID'), "DEBUG: {$str}");
	}

	// Инициализация команд
	function bot_debug_cmdinit($event){ // Добавление DEBUG-команд специальному пользователю
		// Проверка на доступ
		$data = $event->getData();
		if($data->type == "message_new" && $data->object->from_id === bot_getconfig('DEBUG_USER_ID'))
			$access = true;
		elseif($data->type == "message_event" && $data->object->user_id === bot_getconfig('DEBUG_USER_ID'))
			$access = true;
		else
			$access = false;

		if($access){
			$event->addTextMessageCommand("!docmd", function ($finput){
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
			});

			$event->addTextMessageCommand("!test-template", function ($finput){
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
			});

			$event->addTextMessageCommand('!runcb', function ($finput){
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
			});

			$event->addCallbackButtonCommand('bot_runcb', function ($finput){
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
			});

			$event->addTextMessageCommand("!kick-all", function ($finput){
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
			});

			$event->addTextMessageCommand("!debug-info", function ($finput){
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
			});
		}
	}

	function bot_test_rights_exe($peer_id, $member_id, $action_code, $check_owner = false){ // Тестирование прав через VKScript
		$code = "var members=API.messages.getConversationMembers({'peer_id':{$peer_id}});var member={};var i=0;while(i<members.items.length){if(members.items[i].member_id=={$member_id}){member=members.items[i];i=members.items.length;};i=i+1;};";
		if($check_owner)
			$code .= "if(!member.is_owner){{$action_code}}";
		else
			$code .= "if(!member.is_admin){{$action_code}}";
		return $code;
	}

	function bot_int_to_emoji_str($number){
		$array = array();
		while ($number > 0) {
		    $array[] = $number % 10;
		    $number = intval($number / 10); 
		}
		$array = array_reverse($array);

		$emoji = array('0&#8419;', '1&#8419;', '2&#8419;', '3&#8419;', '4&#8419;', '5&#8419;', '6&#8419;', '7&#8419;', '8&#8419;', '9&#8419;');

		$string = "";

		foreach ($array as $key => $value) {
			$string .= $emoji[$value];
		}

		return $string;
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Прочее
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	function bot_get_array_value($array, $index, $default = null){ // Будут баги, изменить null на ""
		if(array_key_exists($index, $array))
			return $array[$index];
		else
			return $default;

	}

	function bot_message_not_reg($data, $db){ // Legacy
		$messagesModule = new Bot\Messages($db);
		$keyboard = vk_keyboard_inline([[vk_callback_button("Зарегистировать", ['bot_reg'], 'positive')]]);
		if($data->type == 'message_new'){
			$messagesModule->setAppealID($data->object->from_id);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔беседа не зарегистрирована. Используйте кнопку ниже.", ['keyboard' => $keyboard]);
		}
		else if($data->type == 'message_event')
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Беседа не зарегистрирована.");
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
		$messagesModule = new Bot\Messages();
		$messagesModule->sendSilentMessage($data->object->peer_id, '✅Клавиатура убрана.', array('keyboard' => $keyboard));
	}

	function bot_like_handler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		if(array_key_exists(1, $argv))
			$command = mb_strtolower($argv[1]);
		else
			$command = "";
		if($command == "аву")
			fun_like_avatar($data, $db);
		else{
			$commands = array(
				'Лайк аву - Лайкает аву'
			);

			$messagesModule = new Bot\Messages($db);
			$messagesModule->setAppealID($data->object->from_id);
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, используйте:', $commands);
		}
	}

	function bot_remove_handler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		if(array_key_exists(1, $argv))
			$command = mb_strtolower($argv[1]);
		else
			$command = "";
		if($command == "кнопки")
			bot_keyboard_remove($data);
		elseif($command == "ник")
			manager_remove_nick($data, $db);
		else{
			$commands = array(
				'!убрать кнопки - Убирает кнопки',
				'!убрать ник - Убирает ник пользователя'
			);

			$messagesModule = new Bot\Messages($db);
			$messagesModule->setAppealID($data->object->from_id);
			$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, используйте:', $commands);
		}
	}

	function bot_getid($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$member_id = 0;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);

		if(array_key_exists(0, $data->object->fwd_messages)){
			$member_id = $data->object->fwd_messages[0]->from_id;
		} elseif(array_key_exists(1, $argv) && bot_is_mention($argv[1])){
			$member_id = bot_get_id_from_mention($argv[1]);
		} else {
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Ваш ID: {$data->object->from_id}.");
			return;
		}

		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ID: {$member_id}.");
	}

	function bot_base64($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$str_data = mb_substr($data->object->text, 8);
		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);

		$CHARS_LIMIT = 300; // Переменная ограничения символов

		if($str_data == ""){
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Используйте !base64 <data>.");
			return;
		}

		$decoded_data = base64_decode($str_data);

		if(!$decoded_data){
			$encoded_data = base64_encode($str_data);
			if(strlen($encoded_data) > $CHARS_LIMIT){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔Зашифрованный текст превышает {$CHARS_LIMIT} симоволов.");
				return;
			}
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Зашифрованный текст:\n{$encoded_data}");
		}
		else{
			if(strlen($decoded_data) > $CHARS_LIMIT){
				$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Дешифрованный текст превышает {$CHARS_LIMIT} симоволов.");
				return;
			}
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Дешифрованный текст:\n{$decoded_data}");
		}
	}

	function bot_cmdlist($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;
		$event = $finput->event;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);
		if(array_key_exists(1, $argv))
			$list_number_from_word = intval($argv[1]);
		else
			$list_number_from_word = 1;

		/////////////////////////////////////////////////////
		////////////////////////////////////////////////////
		$list_in = $event->getTextMessageCommandList(); // Входной список
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
		////////////////////////////////////////////////////
		////////////////////////////////////////////////////

		$buttons = array();
		if($list_max_number > 1){
			if($list_number != 1){
				$previous_list = $list_number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$buttons[] = vk_callback_button("{$emoji_str} ⬅", array('bot_cmdlist', $data->object->from_id, $previous_list), 'secondary');
			}
			if($list_number != $list_max_number){
				$next_list = $list_number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$buttons[] = vk_callback_button("➡ {$emoji_str}", array('bot_cmdlist', $data->object->from_id, $next_list), 'secondary');
			}
		}
		$keyboard = vk_keyboard_inline(array(
			$buttons,
			array(
				vk_callback_button("Меню", array('bot_menu', $data->object->from_id), "secondary"),
				vk_callback_button("Закрыть", array('bot_menu', $data->object->from_id, 0), "negative")
			)
		));

		$msg = "%appeal%, Список команд [$list_number/$list_max_number]:";
		for($i = 0; $i < count($list_out); $i++){
			$msg = $msg . "\n• " . $list_out[$i];
		}

		$messagesModule->sendSilentMessage($data->object->peer_id, $msg, array('keyboard' => $keyboard));
	}

	function bot_cmdlist_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;
		$event = $finput->event;

		// Функция тестирования пользователя
		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->user_id);

		/////////////////////////////////////////////////////
		////////////////////////////////////////////////////
		$list_in = $event->getTextMessageCommandList(); // Входной список
		$list_out = array(); // Выходной список

		$list_number = intval(bot_get_array_value($payload, 2, 1)); // Номер текущего списка
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
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Указан неверный номер списка!');
			return;
		}
		////////////////////////////////////////////////////
		////////////////////////////////////////////////////

		$buttons = array();
		if($list_max_number > 1){
			if($list_number != 1){
				$previous_list = $list_number - 1;
				$emoji_str = bot_int_to_emoji_str($previous_list);
				$buttons[] = vk_callback_button("{$emoji_str} ⬅", array('bot_cmdlist', $testing_user_id, $previous_list), 'secondary');
			}
			if($list_number != $list_max_number){
				$next_list = $list_number + 1;
				$emoji_str = bot_int_to_emoji_str($next_list);
				$buttons[] = vk_callback_button("➡ {$emoji_str}", array('bot_cmdlist', $testing_user_id, $next_list), 'secondary');
			}
		}
		$keyboard = vk_keyboard_inline(array(
			$buttons,
			array(
				vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
				vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
			)
		));

		$msg = "%appeal%, Список команд [$list_number/$list_max_number]:";
		for($i = 0; $i < count($list_out); $i++){
			$msg = $msg . "\n• " . $list_out[$i];
		}

		$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $msg, array('keyboard' => $keyboard));
	}

	function bot_call_all($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);
		$ranksys = new RankSystem($db);

		if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
			$messagesModule->sendSilentMessage($data->object->peer_id, Bot\Messages::MESSAGE_NO_RIGHTS);
			return;
		}

		vk_execute($messagesModule->makeExeAppealByID($data->object->from_id)."var peer_id={$data->object->peer_id};var from_id={$data->object->from_id};var members=API.messages.getConversationMembers({'peer_id':peer_id});var msg=appeal+' созывает всех!';var i=0;while (i<members.profiles.length){if(members.profiles[i].id!=from_id){msg=msg + '@id'+members.profiles[i].id+'(&#12288;)';}i=i+1;};API.messages.send({'peer_id':peer_id,'message':msg});");
	}

	function bot_keyboard_rtcc_handler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		if(property_exists($payload, "text_command") && gettype($payload->text_command) == "string"){
			$modified_data = (object) array(
				'type' => 'message_new',
				'object' => (object) array(
					'date' => time(),
					'from_id' => $data->object->user_id,
					'id' => 0,
					'out' => 0,
					'peer_id' => $data->object->peer_id,
					'text' => $payload->text_command,
					'conversation_message_id' => $data->object->conversation_message_id,
					'fwd_messages' => array(),
					'important' => false,
					'random_id' => 0,
					'attachments' => array(),
					'is_hidden' => false
				)
			);
			$finput->event->runTextMessageCommand($modified_data);
		}
	}

	function bot_keyboard_rtct_handler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		if(property_exists($payload, "text_command") && gettype($payload->text_command) == "string"){
			$modified_data = $data;
			$modified_data->object->text = $payload->text_command;
			unset($modified_data->object->payload);
			$finput->event->runTextMessageCommand($modified_data);
		}
	}

	function bot_message_action_handler($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$db = $finput->db;

		if(property_exists($data->object, 'action')){
			if($data->object->action->type == "chat_kick_user"){
				if($data->object->action->member_id == $data->object->from_id){
					$chat_id = $data->object->peer_id - 2000000000;
					$ranksys = new RankSystem($db);
					if(!$ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
						vk_execute("var user=API.users.get({'user_ids':[{$data->object->from_id}]})[0];var msg='Пока, @id{$data->object->from_id} ('+user.first_name+' '+user.last_name+'). Больше ты сюда не вернешься!';API.messages.send({'peer_id':{$data->object->peer_id}, 'message':msg});API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$data->object->action->member_id}});return 'ok';");
					}
				}
				else{
					vk_execute("var user=API.users.get({'user_ids':[{$data->object->action->member_id}],'fields':'sex'})[0];var msg='';if(user.sex==1){msg='Правильно, она мне никогда не нравилась.';}else{msg='Правильно, он мне никогда не нравился.';}API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});");
				}
			}
			elseif($data->object->action->type == "chat_invite_user"){
				$messagesModule = new Bot\Messages($db);
				if($data->object->action->member_id == -bot_getconfig('VK_GROUP_ID'))
					$messagesModule->sendSilentMessage($data->object->peer_id, "О, привет!");
				else{
					$banned_users = BanSystem::getBanList($db);
					$isBanned = false;
					for($i = 0; $i < sizeof($banned_users); $i++){
						if($banned_users[$i]["user_id"] == $data->object->action->member_id){
							$chat_id = $data->object->peer_id - 2000000000;
							$ranksys = new RankSystem($db);
							if($ranksys->checkRank($data->object->from_id, 2)){ // Проверка ранга (Президент)
								vk_execute("API.messages.send({'peer_id':{$data->object->peer_id},'message':'@id{$data->object->action->member_id} (Пользователь) был приглашен @id{$data->object->from_id} (администратором) беседы и автоматически разбанен.'});");
								BanSystem::unbanUser($db, $data->object->action->member_id);
							}
							else{
								$ban_info = BanSystem::getUserBanInfo($db, $data->object->action->member_id);
								json_decode(vk_execute($messagesModule->makeExeAppealByID($data->object->action->member_id)."API.messages.send({'peer_id':{$data->object->peer_id}, 'message':appeal+', вы забанены в этой беседе!\\nПричина: {$ban_info["reason"]}.'});API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$data->object->action->member_id}});"));
								$isBanned = true;
							}
						}
					}
					if(!$isBanned)
						manager_show_invited_greetings($data, $db);
				}
			}
		}
	}

	function bot_tictactoe($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages();

		$chatModes = new ChatModes($db);
		if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
			$messagesModule->setAppealID($data->object->from_id);
			$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, ⛔В чате отключены игры!");
			return;
		}

		$keyboard = vk_keyboard_inline(array(
			array(vk_callback_button("Играть", array('bot_tictactoe', 10, 0, 0), 'primary')),
			array(vk_callback_button("Закрыть", array('bot_tictactoe', 0), 'negative'))
		));

		$messagesModule->sendSilentMessage($data->object->peer_id, "Крестик-нолики. Чтобы присоединиться, нажмите кнопку \"Играть.\"\n\nИгрок 1: Отсутствует\nИгрок 2: Отсутствует", array('keyboard' => $keyboard));
	}

	function bot_tictactoe_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		if($payload[1] == 0){
			vk_call('messages.edit', array(
				'peer_id' => $data->object->peer_id,
				'conversation_message_id' => $data->object->conversation_message_id,
				'message' => 'Игра остановлена.'
			));
		}
		elseif($payload[1] == 10){
			$chatModes = new ChatModes($db);
			if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
				return;
			}

			$player1 = bot_get_array_value($payload, 2, 0);
			$player2 = bot_get_array_value($payload, 3, 0);
			$messageUpdateRequired = false;
			$playButtonColor = "";
			if($player1 == 0){
				$player1 = $data->object->user_id;
				$messageUpdateRequired = true;
				$playButtonColor = "primary";
			}
			elseif($player2 == 0){
				if($data->object->user_id != $player1){
					$player2 = $data->object->user_id;
					$messageUpdateRequired = true;
					$playButtonColor = "positive";
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Вы уже играете в этой партии!');
				}

			}
			else{
				$buttons = array(array());
				for($i = 0; $i < 9; $i++){
					$buttons[intdiv($i, 3)][$i % 3] = vk_callback_button('&#12288;', array('bot_tictactoe', $i + 1, $player1, $player2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0), 'secondary');
				}
				$keyboard = vk_keyboard_inline($buttons);
				$insertedValues = json_encode(array(
					'player_move' => $player1,
					'keyboard' => $keyboard
				));
				vk_execute("var insertedValues={$insertedValues};var player_move=insertedValues.player_move;var player_data=API.users.get({'user_id':player_move})[0];var message='Ход: @id'+player_data.id+' ('+player_data.first_name+' '+player_data.last_name+')';API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':message,'keyboard':insertedValues.keyboard});");
			}

			if($messageUpdateRequired){
				$keyboard = vk_keyboard_inline(array(
					array(vk_callback_button("Играть", array('bot_tictactoe', 10, $player1, $player2), $playButtonColor)),
					array(vk_callback_button("Закрыть", array('bot_tictactoe', 0), 'negative'))
				));

				$insertedValues = json_encode(array(
					'player1' => $player1,
					'player2' => $player2,
					'keyboard' => $keyboard
				), JSON_UNESCAPED_UNICODE);

				vk_execute("var insertedValues={$insertedValues};var player1=insertedValues.player1;var player2=insertedValues.player2;var players=API.users.get({'user_ids':[player1,player2]});var message='Крестик-нолики. Чтобы присоединиться, нажмите кнопку \"Играть.\"\\n\\n';if(player1!=0){message=message+'Игрок 1: @id'+players[0].id+' ('+players[0].first_name+' '+players[0].last_name+')\\n';}else{message=message+'Игрок 1: Отсутствует\\n';}if(player2!=0){message=message+'Игрок 2: @id'+players[1].id+' ('+players[1].first_name+' '+players[1].last_name+')\\n';}else{message=message+'Игрок 2: Отсутствует\\n';}API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':message,'keyboard':insertedValues.keyboard});");
			}
		}
		elseif($payload[1] >= 1 && $payload[1] <= 9){
			$chatModes = new ChatModes($db);
			if(!$chatModes->getModeValue("games_enabled")){ // Отключаем, если в беседе запрещены игры
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ В чате отключены игры!');
				return;
			}

			if($payload[2 + $payload[4]] == $data->object->user_id){
				if($payload[4 + $payload[1]] == 0){
					$payload[4 + $payload[1]] = $payload[4] + 1;

					for($i = 1; $i <= 2; $i++){
						// 05 06 07
						// 08 09 10
						// 11 12 13
						if($payload[5] == $i && $payload[6] == $i && $payload[7] == $i){
							$winner = $i;
							break;
						}
						if($payload[8] == $i && $payload[9] == $i && $payload[10] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[11] == $i && $payload[12] == $i && $payload[13] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[5] == $i && $payload[8] == $i && $payload[11] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[6] == $i && $payload[9] == $i && $payload[12] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[7] == $i && $payload[10] == $i && $payload[13] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[5] == $i && $payload[9] == $i && $payload[13] == $i){
							$winner = $i;
							break;
						}
						elseif($payload[7] == $i && $payload[9] == $i && $payload[11] == $i){
							$winner = $i;
							break;
						}
					}

					if(isset($winner)){
						$game_result = "";
						for($i = 0; $i < 9; $i++){
							switch ($payload[5 + $i]) {
								case 1:
								$symbol = '&#10060; ';
								break;

								case 2:
								$symbol = '&#11093; ';
								break;

								default:
								$symbol = '&#12288; ';
								break;

							}
							$game_result .= $symbol;
							if(($i+1) % 3 == 0)
								$game_result .= "\n";
						}
						$keyboard = vk_keyboard_inline(array(
							array(vk_callback_button("Играть снова", array('bot_tictactoe', 10), "positive")),
							array(vk_callback_button("Закрыть", array('bot_tictactoe', 0), 'negative'))
						));
						$insertedValues = json_encode(array(
							'player' => $payload[1 + $winner],
							'keyboard' => $keyboard,
							'game_result' => $game_result
						));
						vk_execute("var insertedValues={$insertedValues};var player=insertedValues.player;var player_data=API.users.get({'user_id':player})[0];var message='Победил игрок: @id'+player_data.id+' ('+player_data.first_name+' '+player_data.last_name+')\\nРезультат:\\n'+insertedValues.game_result;API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':message,'keyboard':insertedValues.keyboard});");
					}
					else{
						$isCanMove = false;

						if($payload[4] == 0){
							$payload[4] = 1;
						}
						else{
							$payload[4] = 0;
						}

						$buttons = array(array());
						$symbol = '';
						$isCanMove = false;
						for($i = 0; $i < 9; $i++){
							switch ($payload[5 + $i]) {
								case 1:
								$symbol = '❌';
								break;

								case 2:
								$symbol = '⭕';
								break;

								default:
								$symbol = '&#12288;';
								$isCanMove = true;
								break;

							}
							$buttons[intdiv($i, 3)][$i % 3] = vk_callback_button($symbol, array('bot_tictactoe', $i + 1, $payload[2], $payload[3], $payload[4], $payload[5], $payload[6], $payload[7], $payload[8], $payload[9], $payload[10], $payload[11], $payload[12], $payload[13]), 'secondary');
						}

						if($isCanMove){
							$keyboard = vk_keyboard_inline($buttons);
							$insertedValues = json_encode(array(
								'player_move' => $payload[2 + $payload[4]],
								'keyboard' => $keyboard
							));
							vk_execute("var insertedValues={$insertedValues};var player_move=insertedValues.player_move;var player_data=API.users.get({'user_id':player_move})[0];var message='Ход: @id'+player_data.id+' ('+player_data.first_name+' '+player_data.last_name+')';API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':message,'keyboard':insertedValues.keyboard});");
						}
						else{
							$keyboard = vk_keyboard_inline(array(
							array(vk_callback_button("Играть снова", array('bot_tictactoe', 10), "positive")),
							array(vk_callback_button("Закрыть", array('bot_tictactoe', 0), 'negative'))
						));
						$insertedValues = json_encode(array(
							'keyboard' => $keyboard
						));
						vk_execute("var insertedValues={$insertedValues};var message='Ничья.';API.messages.edit({'peer_id':{$data->object->peer_id},'conversation_message_id':{$data->object->conversation_message_id},'message':message,'keyboard':insertedValues.keyboard});");
						}
					}
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Клетка №' . ($payload[1]) . ' уже занята!');
				}
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Сейчас не ваш ход!');
			}
		}
		else
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Неизвестная команда!');
	}

	function bot_menu_tc($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);

		$keyboard = vk_keyboard_inline(array(
			array(vk_callback_button("Центральное Меню", array('bot_menu', $data->object->from_id), 'positive'))));
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Запустить Центральное Меню можно кнопкой ниже.", array('keyboard' => $keyboard));
	}

	function bot_menu_cb($finput){
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
			$ranksys = new RankSystem($db);
			if(!$ranksys->checkRank($data->object->user_id, 1)){
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
				return;
			}
		}

		// Переменная команды меню
		$code = bot_get_array_value($payload, 2, 1);

		switch ($code) {
			case 0:
			$message = "✅ Меню закрыто.";
			break;

			case 1:
			$list_number = bot_get_array_value($payload, 3, 1);
			$elements = array(); // Массив всех кнопок

			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			/// Элементы в меню

			$elements[] = vk_callback_button("Список команд", array('bot_cmdlist', $testing_user_id), 'primary');

			$chatModes = new ChatModes($db);
			if($chatModes->getModeValue("economy_enabled")){ // Проверка режима экономики
				$elements[] = vk_callback_button("Работа", array('economy_work', $testing_user_id), 'primary');
				$elements[] = vk_callback_button("Бизнес", array('economy_company', $testing_user_id), 'primary');
				$elements[] = vk_callback_button("Образование", array('economy_education', $testing_user_id), 'primary');
				$elements[] = vk_callback_button("Магазин", array('economy_shop', $testing_user_id), 'primary');
			}

			$ranksys = new RankSystem($db);
			if($ranksys->checkRank($data->object->user_id, 1)){ // Проверка ранга (Администратор)
				$elements[] = vk_callback_button("Режимы", array('manager_mode', $testing_user_id), 'primary');
			}

			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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
						$list_buttons[] = vk_callback_button("{$emoji_str} ⬅", array('bot_menu', $testing_user_id, 1, $previous_list), 'secondary');
					}
					if($build->list->number != $build->list->max_number){
						$next_list = $build->list->number + 1;
						$emoji_str = bot_int_to_emoji_str($next_list);
						$list_buttons[] = vk_callback_button("➡ {$emoji_str}", array('bot_menu', $testing_user_id, 1, $next_list), 'secondary');
					}
					$keyboard_buttons[] = $list_buttons;
				}
			}
			else
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Внутренняя ошибка: Неверный номер списка.");
			
			$keyboard_buttons[] = array(vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), 'negative'));
			$message = "%appeal%, Центральное Меню.";
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

	function bot_help($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		if(array_key_exists(1, $argv))
			$section = mb_strtolower($argv[1]);
		else
			$section = "";
		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);
		switch ($section) {
			case 'основное':
				$commands = array(
					'!help <раздел> - Помощь в системе бота',
					'!reg - Регистрация беседы в системе бота',
					'!cmdlist <лист> - Список команд в системе бота',
					'!ник <ник> - Смена ника',
					'!ники - Показать ники пользователей',
					'!ранги - Вывод рангов пользователей в беседе',
					'!Онлайн - Показать online пользователей'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Основные команды:', $commands);
				break;

			case 'рп':
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
					'Пожать руку <пользователь> - Жмет руку пользователю',
					'Лизнуть <пользователь> - Лизнуть пользователя',
					'Обосрать <пользователь> - Обосрать пользователя',
					'Облевать <пользователь> - Испачкать в рвоте пользователя',
					'Отшлёпать <пользователь> - Отшлепать пользователя'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Roleplay команды:', $commands);
				break;

			case 'гос':
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
					//'!votestop - Прерывает выборы президента',
					'!candidate - Регистрация как кандидат на выборы',
					'!vote - Меню голосования',
					'!митинг - Система митингов'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Государственные команды:', $commands);
				break;

			case 'управление':
				$commands = array(
					'!banlist <страница> - Список забаненных пользователей',
					'!ban <пользователь> - Бан пользователя в беседе',
					'!unban <пользователь> - Разбан пользователя в беседе',
					'!kick <пользователь> - Кик пользователя',
					'!ранг - Управление рангами пользователей',
					'!ранглист - Список доступных рангов',
					'!приветствие - Управление приветствием',
					'!стата - Статистика беседы',
					'!modes - Управление режимами беседы',
					'!панель - Управление персональной панелью',
					'Панель - Отобразить персональную панель'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Команды управления беседой:', $commands);
				break;

			case 'экономика':
				$commands = array(
					'!счёт - Основная информация пользователя',
					'!профессии - Список профессий',
					'!профессия <номер> - Информация о профессии',
					'!работать - Работать',
					'!работать <номер> - Устроиться на профессию',
					'!имущество - Список вашего имущества',
					'!купить - Покупка имущества',
					'!продать - Продажа имущества',
					'!банк - Операции с деньгами',
					'!образование - Управление образованием',
					'!бизнес - управление бизнесом',
					'!награды - Список ваших наград',
					'!forbes - Список самых богатых людей беседы',
					'Подарить - Дарит имущество пользователю'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Команды управления беседой:', $commands);
				break;

			case 'другое':
				$commands = array(
					'!зов - Упоминает всех участников беседы',
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
					'!Выбери <v1> или <v2> или <v3>... - Случайный выбор одного из вариантов',
					'!Сколько <ед. измерения> <дополнение> - Сколько чего-то там что-то там',
					'!Кто/!Кого/!Кому <текст> - Выбирает случайного человека беседы',
					'!Инфа <выражение> - Вероятность выражения',
					'!Бутылочка - Мини-игра "Бутылочка"',
					'!Лайк <что-то> - Ставит лайк на что-то',
					'!Убрать <что-то> - Что-то убирает',
					'!Слова - Игра "Слова"',
					//'Words - Игра "Слова" на Английском языке',
					//'Загадки - Игры "Загадки"',
					'!Брак помощь - Помощь по системе браков',
					'!Браки - Список действующих браков беседы',
					'!Браки история - Список всех браков беседы'
				);

				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, 📰Другие команды:', $commands);
				break;
			
			default:
				$messagesModule->sendSilentMessageWithListFromArray($data->object->peer_id, '%appeal%, ✅Используйте:', array(
					'!помощь основное - Базовый раздел',
					'!помощь рп - Roleplay раздел',
					'!помощь гос - Гос. раздел',
					'!помощь управление - Раздел управления',
					'!помощь экономика - Экономика',
					'!помощь другое - Другое'
				));
				break;
		}
	}
}

?>