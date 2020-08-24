<?php

class Event{
	// Переменные
	private $data;
	private $db;
	private $textMessageCommands;			// Массив текстовых команд
	private $textButtonCommands;			// Массив команд Text-кнопок
	private $callbackButtonCommands;		// Массив команд Callback-кнопок
	private $defaultFunc;

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
			$this->db = new Database(BOT_DBDIR."/chat{$chat_id}.json");
		}
	}

	public function getData(){
		return $this->data;
	}

  	public function getDatabase(){
  		return $this->db;
  	}

  	public function addTextMessageCommand($command, $callback, $ignore_db = false){
  		if(!array_key_exists($command, $this->textMessageCommands)){
  			$this->textMessageCommands[$command] = (object) array(
  				'callback' => $callback,
  				'ignore_db' => $ignore_db
  			);
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addTextButtonCommand($command, $callback, $ignore_db = false){
  		if(!array_key_exists($command, $this->textButtonCommands)){
  			$this->textButtonCommands[$command] = (object) array(
  				'callback' => $callback,
  				'ignore_db' => $ignore_db
  			);
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addCallbackButtonCommand($command, $callback, $ignore_db = false){
  		if(!array_key_exists($command, $this->callbackButtonCommands)){
  			$this->callbackButtonCommands[$command] = (object) array(
  				'callback' => $callback,
  				'ignore_db' => $ignore_db
  			);
  			return true;
  		}
  		else
  			return false;
  	}

  	public function setDefaultFunction($func){
  		$this->defaultFunc = $func;
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

			if(array_key_exists($command, $this->textMessageCommands)){
				$command_data = $this->textMessageCommands[$command];

				// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
				if(!$command_data->ignore_db && !bot_check_reg($this->db))
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
				if(!is_null($payload) && property_exists($payload, "command") && array_key_exists($payload->command, $this->textButtonCommands)){
					$command_data = $this->textButtonCommands[$payload->command];

					// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
					if(!$command_data->ignore_db && !bot_check_reg($this->db))
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
				if(array_key_exists(0, $payload)&& array_key_exists($payload[0], $this->callbackButtonCommands)){
					$command_data = $this->callbackButtonCommands[$payload[0]];
					
					// Проверка на существование беседы в Базе данных, если команда не способна игнорировать это
					if(!$command_data->ignore_db && !bot_check_reg($this->db))
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

  	public function handle(){
  		switch($this->data->type){

			case 'message_new':
			if($this->data->object->from_id <= 0){ // Игнорирование сообщений других чат-ботов
				return false;
			}

			// Обработка тектовых команд
			$result = $this->runTextMessageCommand($this->data);
			if($result == Event::COMMAND_RESULT_OK)
				return true;
			elseif($result == Event::COMMAND_RESULT_NO_DB){
				bot_message_not_reg($this->data);
				return false;
			}

			// Обработка клавиатурных команд
			$result = $this->runTextButtonCommand($this->data);
			if($result == Event::COMMAND_RESULT_OK)
				return true;
			elseif($result == Event::COMMAND_RESULT_NO_DB){
				bot_message_not_reg($data);
				return false;
			}

			// Обработка не командный сообщений
			if(!is_null($this->defaultFunc)){
				if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
					return false;
				}
				$finput = (object) array(
					'data' => $this->data,
					'db' => $this->db,
					'event' => $this
				);
				$method = $this->defaultFunc; // Получение значения Callback'а
				call_user_func_array($method, array($finput)); // Выполнение Callback'а
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
				bot_message_not_reg($this->data);
				return false;
			}
			break;
		}
		return false;
  	}
}

function event_handle($data){
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

		// Инициализирует класс
		$event = new Event($data);

		bot_pre_handle_function($event);				// Функция предварительной обработки
		bot_debug_cmdinit($event);						// Инициализация команд отладочного режима

		bot_initcmd($event);							// Инициализация команд модуля bot
		government_initcmd($event);						// Инициализация команд Гос. устройства
		manager_initcmd($event);						// Инициализация команд модуля manager
		stats_initcmd($event);							// Инициализация команд модуля stats
		roleplay_cmdinit($event);						// RP-команды
		fun_initcmd($event);							// Fun-команды
		giphy_initcmd($event);							// Инициализация команд модуля giphy
		wordgame_initcmd($event);						// Игра Слова
		economy_initcmd($event);						// Economy

		// Функция обработки событий вне командной среды
		$event->setDefaultFunction(function ($finput){
			// Инициализация базовых переменных
			$data = $finput->data; 
			$db = $finput->db;

			government_referendum_system($data, $db); // Обработчик выборов президента в беседе

			bot_message_action_handler($finput); // Обработчик событий сообщений

			fun_handler($data, $db);
			stats_update($data, $db); // Ведение статистики в беседе
			wordgame_gameplay($data, $db); // Освновной обработчик игры Слова

			$db->save();
		});

		$event->handle(); // Обработка
		$event->exit(); // Очищение памяти
	}
}

?>