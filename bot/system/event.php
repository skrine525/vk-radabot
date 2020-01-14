<?php

class Event{
	private $data;
	private $db;
	private $messageCommands; // Массив текстовых команд
	private $keyboardCommands; // Массив команд клавиатуры
	private $defaultFunc;
	private $dbIgnoreCommandList;

	function __construct($data) {
		$this->data = $data;
		$this->messageCommands = array();
		$this->keyboardCommands = array();
		$this->dbIgnoreCommandList = array();

		$chat_id = $this->data->object->peer_id - 2000000000;
		$this->db = new Database(BOT_DBDIR."/chat{$chat_id}.json");
	}

	public function getData(){
		return $this->data;
	}

  	public function &getDB(){
  		return $this->db;
  	}

  	public function addTextCommand($command, $method){
  		if(!array_key_exists($command, $this->messageCommands)){
  			$this->messageCommands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addKeyboardCommand($command, $method){
  		if(!array_key_exists($command, $this->keyboardCommands)){
  			$this->keyboardCommands[$command] = $method;
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
  		foreach ($this->messageCommands as $key => $value) {
  			$list[] = $key;
  		}
  		return $list;
  	}

  	public function exit(){
  		unset($this->data);
  		unset($this->db);
  		unset($this->messageCommands);
  		unset($this->keyboardCommands);
  		unset($this->defaultFunc);
  		unset($this->dbIgnoreCommandList);
  	}

  	public function runTextCommand($data){
  		if(gettype($data) == "object"){
  			$words = explode(' ', $data->object->text); // Извлекаем слова из сообщения
			$command = mb_strtolower($words[0]); // Переводим команду в нижний регистр

			if(array_key_exists($command, $this->messageCommands)){
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
					'db' => &$this->db,
					'event' => &$this
				);
				$method = $this->messageCommands[$command]; // Получение значения Callback'а
				call_user_func_array($method, array($finput)); // Выполнение Callback'а
				return 0;
			}
  		}
  		return 1;
  	}

  	public function runKeyboardCommand($data){
  		if(gettype($data) == "object"){
  			if(property_exists($data->object, "payload")){
				$payload = (object) json_decode($data->object->payload);
				if(!is_null($payload) && property_exists($payload, "command") && array_key_exists($payload->command, $this->keyboardCommands)){
					if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
						bot_message_not_reg($data);
						return 2;
					}
					$finput = (object) array(
						'data' => $data,
						'payload' => $payload,
						'db' => &$this->db,
						'event' => &$this
					);
					$method = $this->keyboardCommands[$payload->command]; // Получение значения Callback'а
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
			if($this->data->object->peer_id < 2000000000){ // Запрет использование бота в лс
				vk_call('messages.send', array('peer_id'=>$this->data->object->peer_id,'message'=>'Бот работает только в беседах. Вы можете добавить бота в беседу соответствующей кнопкой в меню бота на главной странице.'));
				return false;
			}
			if($this->data->object->from_id <= 0){ // Игнорирование сообщений других чат-ботов
				return false;
			}

			// Обработка тектовых команд
			$result = $this->runTextCommand($this->data);
			if($result == 0)
				return true;
			elseif($result == 2)
				return false;

			// Обработка клавиатурных команд
			$result = $this->runKeyboardCommand($this->data);
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
		}
		return false;
  	}
}

function event_handle($data){
	$event = new Event($data); // Инициализирует класс

	bot_pre_handle_function($event); // Функция предварительной обработки

	///// Игнорирование отсутствие базы данных для следующих комманд
	$event->addDBIgnoreTextCommand("!reg");

	///// Комманды

	// Template - $event->addTextCommand("command", "callback");

	// Основное
	$event->addTextCommand("!cmdlist", 'bot_cmdlist');
	$event->addTextCommand("!reg", 'bot_register');
	$event->addTextCommand("!help", 'bot_help');

	// Правительство
	$event->addTextCommand("!конституция", 'goverment_constitution');
	$event->addTextCommand("!президент", 'goverment_president');
	$event->addTextCommand("!строй", 'goverment_socorder');
	$event->addTextCommand("!стройлист", 'goverment_socorderlist');
	$event->addTextCommand("!законы", 'goverment_show_laws');
	$event->addTextCommand("!закон", 'goverment_laws_cpanel');
	$event->addTextCommand("!партия", 'goverment_batch');
	$event->addTextCommand("!столица", 'goverment_capital');
	$event->addTextCommand("!гимн", 'goverment_anthem');
	$event->addTextCommand("!флаг", 'goverment_flag');

	// Система выборов
	$event->addTextCommand("!votestart", 'goverment_referendum_start');
	$event->addTextCommand("!votestop", 'goverment_referendum_stop');
	$event->addTextCommand("!candidate", 'goverment_referendum_candidate');
	$event->addTextCommand("!vote", 'goverment_referendum_vote_cmd');
	$event->addKeyboardCommand("referendum_vote", "goverment_referendum_vote");

	// Система управления беседой
	$event->addTextCommand("онлайн", 'manager_online_list');
	$event->addTextCommand("!ban", 'manager_ban_user');
	$event->addTextCommand("!unban", 'manager_unban_user');
	$event->addTextCommand("!baninfo", 'manager_baninfo_user');
	$event->addTextCommand("!banlist", 'manager_banlist_user');
	$event->addTextCommand("!kick", 'manager_kick_user');
	$event->addTextCommand("!ник", 'manager_nick');
	$event->addTextCommand("!ранг", 'manager_rank');
	$event->addTextCommand("!ранглист", 'manager_rank_list');
	$event->addTextCommand("!ранги", 'manager_show_user_ranks');
	$event->addTextCommand("!приветствие", 'manager_greeting');
	$event->addTextCommand("стата", 'stats_cmd_handler');
	$event->addTextCommand("!modes", "manager_mode_list");
	$event->addTextCommand("!mode", "manager_mode_cpanel");
	$event->addTextCommand("!панель", "manager_panel_control");
	$event->addTextCommand("панель", "manager_panel_show");

	// RP-команды
	roleplay_cmdinit($event);

	// Fun
	$event->addTextCommand("выбери", 'fun_choose');
	$event->addTextCommand("сколько", 'fun_howmuch');
	$event->addTextCommand("кто", 'fun_whois');
	$event->addTextCommand("инфа", "fun_info");
	$event->addTextCommand("!бузова", 'fun_buzova');
	$event->addTextCommand("!карина", 'fun_karina_cmd');
	$event->addTextCommand("!амина", 'fun_amina_cmd');
	$event->addTextCommand("!memes", 'fun_memes_control_panel');
	$event->addTextCommand("!чулки", 'fun_stockings_cmd');
	$event->addTextCommand("бутылочка", 'fun_bottle');
	//$event->addTextCommand("!tts", 'fun_tts');
	$event->addTextCommand("!say", "fun_say");
	$event->addTextCommand("брак", "fun_marriage");
	$event->addTextCommand("браки", "fun_show_marriage_list");

	// Прочее
	$event->addTextCommand("лайк", 'bot_like_handler');
	$event->addTextCommand("убрать", 'bot_remove_handler');
	$event->addTextCommand("!id", 'bot_getid');
	$event->addTextCommand("!ники", 'manager_show_nicknames');
	$event->addTextCommand("!base64", 'bot_base64');
	$event->addTextCommand("!shrug", 'fun_shrug');
	$event->addTextCommand("!tableflip", 'fun_tableflip');
	$event->addTextCommand("!unflip", 'fun_unflip');
	$event->addTextCommand("!giphy", 'giphy_handler');
	$event->addTextCommand("!зов", 'bot_call_all');
	$event->addTextCommand("слова", 'wordgame_cmd');
	//$event->addTextCommand("words", 'wordgame_eng_cmd');
	//$event->addTextCommand("загадки", "riddlegame_cmd");

	// Обработчик для запуска текстовых команд из под аргумента кнопки
	$event->addKeyboardCommand("bot_run_text_command", 'bot_keyboard_run_message_command_handler');
	$event->addKeyboardCommand("manager_panel", 'manager_panel_keyboard_handler'); // Обработка персональной панели

	// Economy
	economy_initcmd($event);

	// Для тестирование плюшек
	//bot_test_initcmd($event);

	// Функция обработки событий вне командной среды
	$event->setDefaultFunction(function ($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$db = &$finput->db;

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

?>