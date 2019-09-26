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

  	public function addCommand($command, $method){
  		if(!array_key_exists($command, $this->commands)){
  			$this->commands[$command] = $method;
  			return true;
  		}
  		else
  			return false;
  	}

  	public function addDBIgnoreCommand($command){
  		$this->dbIgnoreCommandList[] = $command;
  	}

  	public function setDefaultFunction($func){
  		$this->defaultFunc = $func;
  	}

  	public function getCommandList(){
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

function event_update_without_commands($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	bot_leave_autokick($data);
	bot_banned_kick($data, $db);
	manager_show_invited_greetings($data, $db); // Обработчик приветствия для новый пользователей в беседе
	goverment_referendum_system($data, $db);

	fun_handler($data, $db);
	stats_update($data, $words, $db); // Ведение статистики в беседе
	wordgame_gameplay($data, $db); // Освновной обработчик игры в слова

}

function event_update($data){
	$event = new Event($data); // Инициализирует класс
	$event->loadDB(); // Подключаем базу данных

	///// Игнорирование отсутствие базы данных для следующих комманд
	//$event->addDBIgnoreCommand("!test");
	$event->addDBIgnoreCommand("!reg");

	///// Комманды

	// Template - $event->addCommand("", function($finput){  });

	// Основное
	$event->addCommand("!cmdlist", 'bot_cmdlist');
	$event->addCommand("!reg", 'bot_register');
	$event->addCommand("!help", 'bot_help');

	// Правительство
	$event->addCommand("!конституция", 'goverment_constitution');
	$event->addCommand("!президент", 'goverment_president');
	$event->addCommand("!строй", 'goverment_socorder');
	$event->addCommand("!стройлист", 'goverment_socorderlist');
	$event->addCommand("!законы", 'goverment_show_laws');
	$event->addCommand("!закон", 'goverment_laws_cpanel');
	$event->addCommand("!партия", 'goverment_batch');
	$event->addCommand("!столица", 'goverment_capital');
	$event->addCommand("!гимн", 'goverment_anthem');
	$event->addCommand("!флаг", 'goverment_flag');

	// Система выборов
	$event->addCommand("!votestart", 'goverment_referendum_start');
	$event->addCommand("!votestop", 'goverment_referendum_stop');
	$event->addCommand("!candidate", 'goverment_referendum_candidate');
	$event->addCommand("!vote", 'goverment_referendum_vote');

	// Система управления беседой
	$event->addCommand("онлайн", 'manager_online_list');
	$event->addCommand("!ban", 'manager_ban_user');
	$event->addCommand("!unban", 'manager_unban_user');
	$event->addCommand("!banlist", 'manager_banlist_user');
	$event->addCommand("!kick", 'manager_kick_user');
	$event->addCommand("!ник", 'manager_nick');
	$event->addCommand("!ранг", 'manager_rank');
	$event->addCommand("!ранглист", 'manager_rank_list');
	$event->addCommand("!ранги", 'manager_show_user_ranks');
	$event->addCommand("!приветствие", 'manager_greeting');
	$event->addCommand("!stats", 'stats_cmd_handler');
	$event->addCommand("!modes", "manager_mode_list");
	$event->addCommand("!mode", "manager_mode_cpanel");

	// RP-команды
	$event->addCommand("!me", 'rp_me');
	$event->addCommand("!do", 'rp_do');
	$event->addCommand("!try", 'rp_try');
	$event->addCommand("!s", 'rp_shout');
	$event->addCommand("секс", 'rp_sex');
	$event->addCommand("обнять", 'rp_hug');
	$event->addCommand("уебать", 'rp_bump');
	$event->addCommand("обоссать", 'rp_pissof');
	$event->addCommand("поцеловать", 'rp_kiss');
	$event->addCommand("харкнуть", 'rp_hark');
	$event->addCommand("отсосать", 'rp_suck');
	$event->addCommand("отлизать", 'rp_lick');
	$event->addCommand("послать", 'rp_gofuck');
	$event->addCommand("кастрировать", 'rp_castrate');
	$event->addCommand("посадить", "rp_sit");

	// Fun
	$event->addCommand("выбери", 'fun_choose');
	$event->addCommand("сколько", 'fun_howmuch');
	$event->addCommand("инфа", "fun_info");
	$event->addCommand("!бузова", 'fun_buzova');
	$event->addCommand("!карина", 'fun_karina_cmd');
	$event->addCommand("!амина", 'fun_amina_cmd');
	$event->addCommand("!memes", 'fun_memes_control_panel');
	$event->addCommand("!чулки", 'fun_stockings_cmd');
	$event->addCommand("бутылочка", 'fun_bottle');
	$event->addCommand("!tts", 'fun_tts');
	$event->addCommand("!say", "fun_say");

	// Прочее
	$event->addCommand("лайк", 'bot_like_handler');
	$event->addCommand("убрать", 'bot_remove_handler');
	$event->addCommand("!id", 'bot_getid');
	$event->addCommand("!ники", 'manager_show_nicknames');
	$event->addCommand("!base64", 'bot_base64');
	$event->addCommand("!shrug", 'fun_shrug');
	$event->addCommand("!tableflip", 'fun_tableflip');
	$event->addCommand("!unflip", 'fun_unflip');
	$event->addCommand("!giphy", 'giphy_handler');
	$event->addCommand("слова", 'wordgame_cmd');

	$event->setDefaultFunction('event_update_without_commands'); // Определение стандартной функции обработки событий
	$event->handle(); // Обработка
	$event->saveDB(); // Сохранение базы данных
	$event->exit(); // Очищение памяти
}

?>