<?php

class Event{
	private $data;
	private $db;
	private $commands;
	private $defaultFunc;
	private $dbIgnoreCommandList;

	function __construct($data) {
		$this->data = $data;
		$this->commands = array();
		$this->dbIgnoreCommandList = array();
	}

  	public function loadDB(){
  		$peer_id = $this->data->object->peer_id - 2000000000;
   		$this->db = db_get("{$peer_id}_database");
  	}

  	public function saveDB(){
  		if(bot_check_reg($this->db)){
  			$peer_id = $this->data->object->peer_id - 2000000000;
   			db_set("{$peer_id}_database", $this->db);
  		}
  	}

  	public function addMessageCommand($command, $method){
  		if(!array_key_exists($command, $this->commands)){
  			$this->commands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addDBIgnoreMessageCommand($command){
  		$this->dbIgnoreCommandList[] = $command;
  	}

  	public function setDefaultFunction($func){
  		$this->defaultFunc = $func;
  	}

  	public function getMessageCommandList(){
  		$list = array();
  		foreach ($this->commands as $key => $value) {
  			$list[] = $key;
  		}
  		return $list;
  	}

  	public function exit(){
  		unset($this->data);
  		unset($this->db);
  		unset($this->commands);
  		unset($this->defaultFunc);
  		unset($this->dbIgnoreCommandList);
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

			$words = explode(' ', $this->data->object->text); // Извлекаем слова из сообщения

			mb_internal_encoding("UTF-8"); 
			$command = mb_strtolower($words[0]); // Переводим команду в нижний регистр

			if(array_key_exists($command, $this->commands)){
				if(!bot_check_reg($this->db)){ // Проверка на регистрацию в системе
					$ignore = false;
					for($i = 0; $i < count($this->dbIgnoreCommandList); $i++){
						if($command == $this->dbIgnoreCommandList[$i]){
							$ignore = true;
							break;
						}
					}
					if(!$ignore){
						bot_message_not_reg($this->data);
						return false;
					}
				}

				$finput = (object) array(
					'data' => $this->data,
					'words' => $words,
					'db' => &$this->db,
					'event' => &$this
				);
		
				$method = $this->commands[$command]; // Получение значения Callback'а
				$method($finput); // Выполнение Callback'а

				return true;
			}
			else{
				if(!is_null($this->defaultFunc)){
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
						'words' => $words,
						'db' => &$this->db,
						'event' => &$this
					);
					$method = $this->defaultFunc; // Получение значения Callback'а
					$method($finput); // Выполнение Callback'а

					return true;
				}
				else
					return false;
			}

			break;
		}
  	}
}

function event_handle($data){
	$event = new Event($data); // Инициализирует класс
	$event->loadDB(); // Подключаем базу данных

	///// Игнорирование отсутствие базы данных для следующих комманд
	//$event->addDBIgnoreMessageCommand("!test");
	$event->addDBIgnoreMessageCommand("!reg");

	///// Комманды

	// Template - $event->addMessageCommand("", function($finput){  });

	// Основное
	$event->addMessageCommand("!cmdlist", 'bot_cmdlist');
	$event->addMessageCommand("!reg", 'bot_register');
	$event->addMessageCommand("!help", 'bot_help');

	// Правительство
	$event->addMessageCommand("!конституция", 'goverment_constitution');
	$event->addMessageCommand("!президент", 'goverment_president');
	$event->addMessageCommand("!строй", 'goverment_socorder');
	$event->addMessageCommand("!стройлист", 'goverment_socorderlist');
	$event->addMessageCommand("!законы", 'goverment_show_laws');
	$event->addMessageCommand("!закон", 'goverment_laws_cpanel');
	$event->addMessageCommand("!партия", 'goverment_batch');
	$event->addMessageCommand("!столица", 'goverment_capital');
	$event->addMessageCommand("!гимн", 'goverment_anthem');
	$event->addMessageCommand("!флаг", 'goverment_flag');

	// Система выборов
	$event->addMessageCommand("!votestart", 'goverment_referendum_start');
	$event->addMessageCommand("!votestop", 'goverment_referendum_stop');
	$event->addMessageCommand("!candidate", 'goverment_referendum_candidate');
	$event->addMessageCommand("!vote", 'goverment_referendum_vote');

	// Система управления беседой
	$event->addMessageCommand("онлайн", 'manager_online_list');
	$event->addMessageCommand("!ban", 'manager_ban_user');
	$event->addMessageCommand("!unban", 'manager_unban_user');
	$event->addMessageCommand("!banlist", 'manager_banlist_user');
	$event->addMessageCommand("!kick", 'manager_kick_user');
	$event->addMessageCommand("!ник", 'manager_nick');
	$event->addMessageCommand("!ранг", 'manager_rank');
	$event->addMessageCommand("!ранглист", 'manager_rank_list');
	$event->addMessageCommand("!ранги", 'manager_show_user_ranks');
	$event->addMessageCommand("!приветствие", 'manager_greeting');
	$event->addMessageCommand("!stats", 'stats_cmd_handler');
	$event->addMessageCommand("!modes", "manager_mode_list");
	$event->addMessageCommand("!mode", "manager_mode_cpanel");

	// RP-команды
	$event->addMessageCommand("!me", 'rp_me');
	$event->addMessageCommand("!do", 'rp_do');
	$event->addMessageCommand("!try", 'rp_try');
	$event->addMessageCommand("!s", 'rp_shout');
	$event->addMessageCommand("секс", 'rp_sex');
	$event->addMessageCommand("обнять", 'rp_hug');
	$event->addMessageCommand("уебать", 'rp_bump');
	$event->addMessageCommand("обоссать", 'rp_pissof');
	$event->addMessageCommand("поцеловать", 'rp_kiss');
	$event->addMessageCommand("харкнуть", 'rp_hark');
	$event->addMessageCommand("отсосать", 'rp_suck');
	$event->addMessageCommand("отлизать", 'rp_lick');
	$event->addMessageCommand("послать", 'rp_gofuck');
	$event->addMessageCommand("кастрировать", 'rp_castrate');
	$event->addMessageCommand("посадить", "rp_sit");
	$event->addMessageCommand("пожать", "rp_shake");

	// Fun
	$event->addMessageCommand("выбери", 'fun_choose');
	$event->addMessageCommand("сколько", 'fun_howmuch');
	$event->addMessageCommand("инфа", "fun_info");
	$event->addMessageCommand("!бузова", 'fun_buzova');
	$event->addMessageCommand("!карина", 'fun_karina_cmd');
	$event->addMessageCommand("!амина", 'fun_amina_cmd');
	$event->addMessageCommand("!memes", 'fun_memes_control_panel');
	$event->addMessageCommand("!чулки", 'fun_stockings_cmd');
	$event->addMessageCommand("бутылочка", 'fun_bottle');
	$event->addMessageCommand("!tts", 'fun_tts');
	$event->addMessageCommand("!say", "fun_say");

	// Прочее
	$event->addMessageCommand("лайк", 'bot_like_handler');
	$event->addMessageCommand("убрать", 'bot_remove_handler');
	$event->addMessageCommand("!id", 'bot_getid');
	$event->addMessageCommand("!ники", 'manager_show_nicknames');
	$event->addMessageCommand("!base64", 'bot_base64');
	$event->addMessageCommand("!shrug", 'fun_shrug');
	$event->addMessageCommand("!tableflip", 'fun_tableflip');
	$event->addMessageCommand("!unflip", 'fun_unflip');
	$event->addMessageCommand("!giphy", 'giphy_handler');
	$event->addMessageCommand("слова", 'wordgame_cmd');
	$event->addMessageCommand("words", 'wordgame_eng_cmd');
	$event->addMessageCommand("загадки", "riddlegame_cmd_handler");

	// Функция обработки событий вне командной среды
	$event->setDefaultFunction(function ($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		bot_leave_autokick($data);
		if(bot_banned_kick($data, $db))
			manager_show_invited_greetings($data, $db); // Обработчик приветствия для новый пользователей в беседе
		goverment_referendum_system($data, $db); // Обработчик выборов президента в беседе

		fun_handler($data, $db);
		stats_update($data, $words, $db); // Ведение статистики в беседе
		wordgame_gameplay($data, $db); // Освновной обработчик игры Слова
		wordgame_eng_gameplay($data, $db); // Освновной обработчик игры Words
		riddlegame_gameplay($data, $db); // Основной обработчик игры Загадки
	});

	$event->handle(); // Обработка
	$event->saveDB(); // Сохранение базы данных
	$event->exit(); // Очищение памяти
}

?>