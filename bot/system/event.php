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
  			$words = explode(' ', $data->object->text); // Извлекаем слова из сообщения
			$command = mb_strtolower($words[0]); // Переводим команду в нижний регистр

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
					'words' => $words,
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
				$words = explode(' ', $this->data->object->text); // Извлекаем слова из сообщения
				if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
					$ignore = false;
					for($i = 0; $i < count($this->dbIgnoreCommandList); $i++){
						if($words[0] == $this->dbIgnoreCommandList[$i]){
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

		$event = new Event($data); // Инициализирует класс

		bot_pre_handle_function($event); // Функция предварительной обработки

		///// Игнорирование отсутствие базы данных для следующих комманд
		$event->addDBIgnoreTextCommand("!reg");

		///// Комманды

		// Template - $event->addTextMessageCommand("command", "callback");

		// Команды отладочного режима
		bot_debug_cmdinit($event);

		// Основное
		$event->addTextMessageCommand("!cmdlist", 'bot_cmdlist');
		$event->addTextMessageCommand("!reg", 'bot_register');
		$event->addTextMessageCommand("!помощь", 'bot_help');

		// Правительство
		$event->addTextMessageCommand("!конституция", 'goverment_constitution');
		$event->addTextMessageCommand("!президент", 'goverment_president');
		$event->addTextMessageCommand("!строй", 'goverment_socorder');
		$event->addTextMessageCommand("!стройлист", 'goverment_socorderlist');
		$event->addTextMessageCommand("!законы", 'goverment_show_laws');
		$event->addTextMessageCommand("!закон", 'goverment_laws_cpanel');
		$event->addTextMessageCommand("!партия", 'goverment_batch');
		$event->addTextMessageCommand("!столица", 'goverment_capital');
		$event->addTextMessageCommand("!гимн", 'goverment_anthem');
		$event->addTextMessageCommand("!флаг", 'goverment_flag');

		// Система выборов
		$event->addTextMessageCommand("!votestart", 'goverment_referendum_start');
		$event->addTextMessageCommand("!votestop", 'goverment_referendum_stop');
		$event->addTextMessageCommand("!candidate", 'goverment_referendum_candidate');
		$event->addTextMessageCommand("!vote", 'goverment_referendum_vote_cmd');
		$event->addTextButtonCommand("referendum_vote", "goverment_referendum_vote");

		// Система управления беседой
		$event->addTextMessageCommand("!меню", 'bot_menu_tc');
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
		$event->addTextMessageCommand("!стата", 'stats_cmd_handler');
		$event->addTextMessageCommand("!modes", "manager_mode_list");
		$event->addTextMessageCommand("!mode", "manager_mode_cpanel");
		$event->addTextMessageCommand("!панель", "manager_panel_control");
		$event->addTextMessageCommand("панель", "manager_panel_show");

		// RP-команды
		roleplay_cmdinit($event);

		// Fun
		$event->addTextMessageCommand("!выбери", 'fun_choose');
		$event->addTextMessageCommand("!сколько", 'fun_howmuch');
		fun_whois_initcmd($event); // Инициализация команд [кто/кого/кому]
		$event->addTextMessageCommand("!инфа", "fun_info");
		$event->addTextMessageCommand("!бузова", 'fun_buzova');
		$event->addTextMessageCommand("!карина", 'fun_karina_cmd');
		$event->addTextMessageCommand("!амина", 'fun_amina_cmd');
		$event->addTextMessageCommand("!memes", 'fun_memes_control_panel');
		$event->addTextMessageCommand("!чулки", 'fun_stockings_cmd');
		$event->addTextMessageCommand("!бутылочка", 'fun_bottle');
		$event->addTextMessageCommand("!tts", 'fun_tts');
		$event->addTextMessageCommand("!say", "fun_say");
		$event->addTextMessageCommand("!брак", "fun_marriage");
		$event->addTextMessageCommand("!браки", "fun_show_marriage_list");

		// Прочее
		$event->addTextMessageCommand("!лайк", 'bot_like_handler');
		$event->addTextMessageCommand("!убрать", 'bot_remove_handler');
		$event->addTextMessageCommand("!id", 'bot_getid');
		$event->addTextMessageCommand("!ники", 'manager_show_nicknames');
		$event->addTextMessageCommand("!base64", 'bot_base64');
		$event->addTextMessageCommand("!shrug", 'fun_shrug');
		$event->addTextMessageCommand("!tableflip", 'fun_tableflip');
		$event->addTextMessageCommand("!unflip", 'fun_unflip');
		$event->addTextMessageCommand("!giphy", 'giphy_handler');
		$event->addTextMessageCommand("!зов", 'bot_call_all');
		$event->addTextMessageCommand("!слова", 'wordgame_cmd');
		$event->addTextMessageCommand("!крестики-нолики", 'bot_tictactoe');
		//$event->addTextMessageCommand("words", 'wordgame_eng_cmd');
		//$event->addTextMessageCommand("загадки", "riddlegame_cmd");

		// Обработчик для запуска текстовых команд из под аргумента кнопки
		$event->addTextButtonCommand("bot_runtc", 'bot_keyboard_rtct_handler'); // Запуск текстовых команд из под Text-кнопки
		$event->addCallbackButtonCommand("manager_panel", 'manager_panel_keyboard_handler'); // Обработка персональной панели

		// Callback-кнопки
		$event->addCallbackButtonCommand("bot_menu", 'bot_menu_cb');
		$event->addCallbackButtonCommand("bot_cmdlist", 'bot_cmdlist_cb');
		$event->addCallbackButtonCommand('bot_tictactoe', 'bot_tictactoe_cb');
		//$event->addCallbackButtonCommand("bot_runtc", 'bot_keyboard_rtcc_handler'); // Запуск текстовых команд из под Callback-кнопки

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
			//riddlegame_gameplay($data, $db); // Основной обработчик игры Загадки

			$db->save();
		});

		$event->handle(); // Обработка
		$event->exit(); // Очищение памяти
	}
}

?>