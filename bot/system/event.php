<?php

class Event{
	private $data;
	private $db;
	private $textMessageCommands;			// Массив текстовых команд
	private $textButtonCommands;			// Массив команд Text-кнопок
	private $callbackButtonCommands;		// Массив команд Callback-кнопок
	private $defaultFunc;
	private $dbIgnoreCommandList;

	function __construct($data) {
		$this->data = $data;
		$this->textMessageCommands = array();
		$this->textButtonCommands = array();
		$this->callbackButtonCommands = array();
		$this->dbIgnoreCommandList = array();

		$chat_id = $this->data->object->peer_id - 2000000000;
		$this->db = new Database(BOT_DBDIR."/chat{$chat_id}.json");
	}

	public function getData(){
		return $this->data;
	}

  	public function getDB(){
  		return $this->db;
  	}

  	public function addTextMessageCommand($command, $method){
  		if(!array_key_exists($command, $this->textMessageCommands)){
  			$this->textMessageCommands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addTextButtonCommand($command, $method){
  		if(!array_key_exists($command, $this->textButtonCommands)){
  			$this->textButtonCommands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addCallbackButtonCommand($command, $method){
  		if(!array_key_exists($command, $this->callbackButtonCommands)){
  			$this->callbackButtonCommands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addDBIgnoreTextCommand($command){
  		$this->dbIgnoreCommandList[] = $command;
  	}

  	public function setDefaultFunction($func){
  		$this->defaultFunc = $func;
  	}

  	public function getMessageCommandList(){
  		$list = array();
  		foreach ($this->textMessageCommands as $key => $value) {
  			$list[] = $key;
  		}
  		return $list;
  	}

  	public function exit(){
  		unset($this->data);
  		unset($this->db);
  		unset($this->textMessageCommands);
  		unset($this->textButtonCommands);
  		unset($this->callbackButtonCommands);
  		unset($this->defaultFunc);
  		unset($this->dbIgnoreCommandList);
  	}

  	public function runTextMessageCommand($data){
  		if(gettype($data) == "object"){
  			//$argv = explode(' ', $data->object->text); // Извлекаем слова из сообщения
  			$argv = bot_parse_argv($data->object->text); // Извлекаем аргументы из сообщения
			$command = mb_strtolower($argv[0]); // Переводим команду в нижний регистр

			if(array_key_exists($command, $this->textMessageCommands)){
				if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
					$ignore = false;
					for($i = 0; $i < count($this->dbIgnoreCommandList); $i++){
						if($command == $this->dbIgnoreCommandList[$i]){
							$ignore = true;
							break;
						}
					}
					if(!$ignore){
						bot_message_not_reg($data);
						return 2;
					}
				}
				$finput = (object) array(
					'data' => $data,
					'argv' => $argv,
					'db' => $this->db,
					'event' => $this
				);
				$method = $this->textMessageCommands[$command]; // Получение значения Callback'а
				call_user_func_array($method, array($finput)); // Выполнение Callback'а
				return 0;
			}
  		}
  		return 1;
  	}

  	public function runTextButtonCommand($data){
  		if(gettype($data) == "object"){
  			if(property_exists($data->object, "payload")){
				$payload = (object) json_decode($data->object->payload);
				if(!is_null($payload) && property_exists($payload, "command") && array_key_exists($payload->command, $this->textButtonCommands)){
					if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
						bot_message_not_reg($data);
						return 2;
					}
					$finput = (object) array(
						'data' => $data,
						'payload' => $payload,
						'db' => $this->db,
						'event' => $this
					);
					$method = $this->textButtonCommands[$payload->command]; // Получение значения Callback'а
					call_user_func_array($method, array($finput)); // Выполнение Callback'а
					return 0;
				}
  			}
  		}
  		return 1;
  	}

  	public function runCallbackButtonCommand($data){
  		if(gettype($data) == "object"){
  			if(property_exists($data->object, "payload") && gettype($data->object->payload) == 'array'){
				$payload = $data->object->payload;
				if(array_key_exists(0, $payload)&& array_key_exists($payload[0], $this->callbackButtonCommands)){
					if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
						bot_message_not_reg($data);
						return 2;
					}
					$finput = (object) array(
						'data' => $data,
						'payload' => $payload,
						'db' => $this->db,
						'event' => $this
					);
					$method = $this->callbackButtonCommands[$payload[0]]; // Получение значения Callback'а
					call_user_func_array($method, array($finput)); // Выполнение Callback'а
					return 0;
				}
  			}
  		}
  		return 1;
  	}

  	public function handle(){
  		switch($this->data->type){

			case 'message_new':
			if($this->data->object->from_id <= 0){ // Игнорирование сообщений других чат-ботов
				return false;
			}

			// Обработка тектовых команд
			$result = $this->runTextMessageCommand($this->data);
			if($result == 0)
				return true;
			elseif($result == 2)
				return false;

			// Обработка клавиатурных команд
			$result = $this->runTextButtonCommand($this->data);
			if($result == 0)
				return true;
			elseif($result == 2)
				return false;

			// Обработка не командный сообщений
			if(!is_null($this->defaultFunc)){
				$argv = explode(' ', $this->data->object->text); // Извлекаем слова из сообщения
				if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
					$ignore = false;
					for($i = 0; $i < count($this->dbIgnoreCommandList); $i++){
						if($argv[0] == $this->dbIgnoreCommandList[$i]){
							$ignore = true;
							break;
						}
					}
					if(!$ignore){
						return false;
					}
				}
				$finput = (object) array(
					'data' => $this->data,
					'db' => &$this->db,
					'event' => &$this
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
			if($result == 0)
				return true;
			elseif($result == 2)
				return false;
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
		return false;
	}
	else{
		///////////////////////////
		/// Обработка бота в Беседе
		///////////////////////////

		// Инициализирует класс
		$event = new Event($data);

		// Функция предварительной обработки
		bot_pre_handle_function($event);

		// Инициализация команд отладочного режима
		bot_debug_cmdinit($event);

		// Инициализация команд модуля bot
		bot_initcmd($event);

		// Инициализация команд Гос. устройства
		goverment_initcmd($event);

		// Инициализация команд модуля manager
		manager_initcmd($event);

		// Инициализация команд модуля stats
		stats_initcmd($event);

		// RP-команды
		roleplay_cmdinit($event);

		// Fun-команды
		fun_initcmd($event);

		// Инициализация команд модуля giphy
		giphy_initcmd($event);

		// Игра Слова
		wordgame_initcmd($event);

		// Economy
		economy_initcmd($event);

		// Функция обработки событий вне командной среды
		$event->setDefaultFunction(function ($finput){
			// Инициализация базовых переменных
			$data = $finput->data; 
			$db = $finput->db;

			goverment_referendum_system($data, $db); // Обработчик выборов президента в беседе

			bot_message_action_handler($finput); // Обработчик событий сообщений

			fun_handler($data, $db);
			stats_update($data, $db); // Ведение статистики в беседе
			wordgame_gameplay($data, $db); // Освновной обработчик игры Слова
			//wordgame_eng_gameplay($data, $db); // Освновной обработчик игры Words

			$db->save();
		});

		$event->handle(); // Обработка
		$event->exit(); // Очищение памяти
	}
}

?>