<?php

namespace Economy{
	class Job{
		public static function getIDByIndex($index){
			$jobs = self::getJobArray();
			$ids = array_keys($jobs);
			if(array_key_exists($index, $ids))
				return $ids[$index];
			return false;
		}

		public static function getIDByName($name){
			$jobs = self::getJobArray();
			foreach ($jobs as $key => $value) {
				if(mb_strtolower($jobs[$key]["name"]) == mb_strtolower($name))
					return $key;
			}
			return false;
		}

		public static function getNameByID($id){
			$jobs = self::getJobArray();
			if(array_key_exists($id, $jobs))
				return $jobs[$id]["name"];
			else
				return "N/A";
		}

		public static function jobExists($id){
			$jobs = self::getJobArray();
			if(array_key_exists($id, $jobs))
				return true;
			else
				return false;
		}

		public static function getJobArray(){
			return json_decode(file_get_contents(BOT_DATADIR."/economy/jobs.json"), true);
		}
	}

	class UserEconomyManager{
		private $db;

		function __construct(&$users_db, $user_id){
			if($user_id <= 0)
				return false;

			if(array_key_exists("id{$user_id}", $users_db)){
				$this->db = &$users_db["id{$user_id}"];

				// Изменение структуры базы данных
				if(array_key_exists('money_rub', $this->db)){
					$this->db["money"] = $this->db["money_rub"];
					unset($this->db["money_rub"]);
				}
				if(array_key_exists('money_eur', $this->db))
					unset($this->db["money_eur"]);
				if(array_key_exists('money_usd', $this->db))
					unset($this->db["money_usd"]);
				if(array_key_exists('money_btc', $this->db))
					unset($this->db["money_btc"]);
				if(array_key_exists('money', $this->db)){
					$this->setMeta("money", $this->db["money"]);
					unset($this->db["money"]);
				}
			}
			else{
				$this->db = array(
					'meta' => array(),
					'items' => array()

				);
				$users_db["id{$user_id}"] = &$this->db;
			}
		}

		public function getMoney(){
			return $this->getMeta("money", 0);
		}

		public function changeMoney($value){
			$money = $this->getMoney();
			if($money + $value >= 0){
				$value = round($value, 2);
				$money = $money + $value;
				$this->setMeta("money", $money);
				return true;
			}
			return false;
		}

		public function canChangeMoney($value){
			$money = $this->getMoney();
			if($money + $value >= 0){
				return true;
			}
			return false;
		}

		public function getItems(){
			$items = array();
			for($i = 0; $i < count($this->db["items"]); $i++){
				$a = explode(":", $this->db["items"][$i]);
				$items[] = (object) array(
					'type' => $a[0],
					'id' => $a[1],
					'count' => $a[2]
				);
			}
			return $items;
		}

		public function getItemByIndex($index){
			if(array_key_exists($index, $this->db["items"])){
				$a = explode(":", $this->db["items"][$index]);
				return (object) array(
					'type' => $a[0],
					'id' => $a[1],
					'count' => $a[2]
				);
			}
			else
				return false;
		}

		public function getItemsByType($type){
			$items = array();
			for($i = 0; $i < count($this->db["items"]); $i++){
				$a = $this->getItemByIndex($i);
				if($a->type == $type)
					$items[] = $a;
			}
			return $items;
		}

		public function checkItem($type, $id){
			if(gettype($type) == "string" && gettype($id) == "string"){
				for($i = 0; $i < count($this->db["items"]); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						return $i;
					}
				}
			}
			return false;
		}

		public function changeItem($type, $id, $count){
			if(gettype($type) == "string" && gettype($id) == "string" && gettype($count) == "integer"){
				for($i = 0; $i < count($this->db["items"]); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						$new_count = $r->count + $count;
						if($new_count < 0)
							return false;
						elseif($new_count == 0){
							$this->deleteItem($type, $id);
						}
						else{
							$this->db["items"][$i] = "{$r->type}:{$r->id}:{$new_count}";
						}
						return true;
					}
				}
				if($count != 0){
					$this->db["items"][] = "{$type}:{$id}:{$count}";
					return true;
				}
				else
					return false;
			}
			else
				return false;
		}

		public function deleteItem($type, $id){
			if(gettype($type) == "string" && gettype($id) == "string"){
				for($i = 0; $i < count($this->db["items"]); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						unset($this->db["items"][$i]);
						$this->db["items"] = array_values($this->db["items"]);
						return true;
					}
				}
			}
			return false;
		}

		public function setMeta($name, $value){
			$this->db["meta"][$name] = $value;
		}

		public function getMeta($name, $default = false){
			if(array_key_exists($name, $this->db["meta"])){
				return $this->db["meta"][$name];
			}
			else
				return $default;
		}

		public function deleteMeta($name){
			if(array_key_exists($name, $this->db["meta"])){
				unset($this->db["meta"][$name]);
				return true;
			}
			else
				return false;
		}

		// Работа
		public function setJob($id){
			$this->setMeta("job", $id);
		}
		public function getJob(){
			return $this->getMeta("job");
		}
		public function deleteJob(){
			return $this->deleteMeta("job");
		}

		// Компании
		public function getEnterprises(){
			return $this->getMeta("enterprises", array());
		}
		public function addEnterprise($id){
			$enterprises = $this->getEnterprises();
			$enterprises[] = $id;
			$this->setMeta("enterprises", $enterprises);
			return true;
		}
		public function delEnterprise($id){
			$enterprises = $this->getEnterprises();
			$index = array_search($id, $enterprises);
			if($index === false)
				return false;
			unset($enterprises[$index]);
			$enterprises = array_values($enterprises);
			$this->setMeta("enterprises", $enterprises);
		}
	}

	class Item{
		public static function getItemName($type, $id){
			EconomyFiles::readDataFiles();
			$items = EconomyFiles::getEconomyFileData("items");
			if(array_key_exists($type, $items) && array_key_exists($id, $items[$type])){
				return $items[$type][$id]["name"];
			}
			return false;
		}

		public static function getShopSectionsArray(){
			EconomyFiles::readDataFiles();
			return EconomyFiles::getEconomyFileData("shop_sections");
		}

		public static function getItemListByType($type){
			EconomyFiles::readDataFiles();
			$items = EconomyFiles::getEconomyFileData("items");;
			if(array_key_exists($type, $items)){
				return $items[$type];
			}
			return false;
		}

		public static function getItemObjectFromString($str){
			if(gettype($str) == "string"){
				$a = explode(":", $str);
				if(count($a) == 3){
					return (object) array(
						'type' => $a[0],
						'id' => $a[1],
						'count' => $a[2]
					);
				}
			}
			return false;
		}
	}

	class EconomyFiles{
		private static $file__economy = "";
		private static $file__jobs = "";
		private static $is_read = false;

		public static function readDataFiles(){
			if(!self::$is_read){
				self::$file__economy = file_get_contents(BOT_DATADIR."/economy/economy.json");
				self::$file__jobs = file_get_contents(BOT_DATADIR."/economy/jobs.json");
				self::$is_read = true;
			}
		}

		public static function getEconomyFileData($section){
			$data = json_decode(self::$file__economy, true);
			if(array_key_exists($section, $data)){
				return $data[$section];
			}
			else
				return false;
		}
	}

	class EnterpriseEconomyManager{
		private $db;

		static private function generateRandomString($length = 5) {
		    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		    $charactersLength = strlen($characters);
		    $randomString = '';
		    for ($i = 0; $i < $length; $i++) {
		        $randomString .= $characters[rand(0, $charactersLength - 1)];
		    }
		    return $randomString;
		}

		public static function getNextIncomeStrTime($last_update_time){
			$current_time = time();
			if($current_time - $last_update_time >= self::TIME_UPDATE){
				return "сейчас";
			}
			else{
				$time = self::TIME_UPDATE - ($current_time - $last_update_time);
				$minutes = intdiv($time, 60);
				$seconds = $time % 60;
				$left_time_text = "";
				if($minutes != 0)
					$left_time_text = "{$minutes} мин. ";
				$left_time_text = $left_time_text."{$seconds} сек.";
				return "через ".$left_time_text;
			}
		}

		function __construct(&$db){
			if(array_key_exists("enterprises", $db)){
				$this->db = &$db["enterprises"];
			}
			else{
				$this->db = array();
				$db["enterprises"] = &$this->db;
			}
		}

		public static function getTypeName($type){
			if($type == "nil"){
				return "Не указано";
			}
			else {

			}
		}

		private function update($id){
			$enterprise = &$this->getEnterprise($id);


		}

		public function createEnterprise($type, $owner_id){
			$id = '';
			while(true){
				$id = self::generateRandomString();
				if(!array_key_exists($id, $this->db))
					break;
			}

			EconomyFiles::readDataFiles();
			$types = array_keys(EconomyFiles::getEconomyFileData("enterprise_types"));
			if(array_search($type, $types) === false)
				return false;

			if(gettype($owner_id) != "integer")
				return false;

			$time = time();

			$this->db[$id] = array(
				'id' => $id,
				'name' => $id,
				'type' => $type,
				'created_time' => $time,
				'owner_id' => $owner_id,
				'workers' => 5,
				'capital' => 0,
				'exp' => 0,
				'max_contracts' => 1,
				'contracts' => array()
			);

			return $id;
		}

		public function &getEnterprise($id){
			if(array_key_exists($id, $this->db))
				return $this->db[$id];
			else
				return false;
		}

		public function changetEnterpriseCapital($id, $value){
			if(array_key_exists($id, $this->db)){
				$enterprise = &$this->db[$id];

				if($enterprise["capital"] + $value >= 0){
					$enterprise["capital"] += $value;
					return true;
				}
				else
					return false;
			}
			else
				return false;
		}
	}

	class Main{
		private $db;

		function __construct(&$db){
			if(array_key_exists("economy", $db))
				$this->db = &$db["economy"];
			else{
				$this->db = array(
					'users' => array(),
					'enterprises' => array()
				);
				$db["economy"] = &$this->db;
			}
		}

		function getUser($user_id){
			return new UserEconomyManager($this->db["users"], $user_id);
		}

		function getUserArray(){
			return $this->db["users"];
		}

		function initEnterpriseSystem(){
			return new EnterpriseEconomyManager($this->db);
		}

		function checkUser($user_id){
			if(array_key_exists("id{$user_id}", $this->db["users"]))
				return true;
			else
				return false;
		}
	}
}

namespace{

	function economy_initcmd(&$event){ // Инициализация тексовых комманд модуля экономики
		$event->addMessageCommand("!счет", "economy_show_user_stats");
		$event->addMessageCommand("!работать", "economy_work");
		$event->addMessageCommand("!профессии", "economy_joblist");
		$event->addMessageCommand("!профессия", "economy_jobinfo");
		$event->addMessageCommand("!купить", "economy_buy");
		$event->addMessageCommand("!продать", "economy_sell");
		$event->addMessageCommand("!имущество", "economy_myprops");
		$event->addMessageCommand("!банк", "economy_bank");
		$event->addMessageCommand("!образование", "economy_education");
		$event->addMessageCommand("!forbes", "economy_most_rich_users");
		$event->addMessageCommand("!бизнес", "economy_company");

		$event->addKeyboardCommand("economy_contract", "economy_keyboard_contract_handler");

		// Test
		//$event->addMessageCommand("!invlist", "economy_test1");
		//$event->addMessageCommand("!invadd", "economy_test2");
		//$event->addMessageCommand("!invtype", "economy_test3");
	}

	function economy_show_user_stats($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);

		if(array_key_exists(0, $data->object->fwd_messages)){
			$member_id = $data->object->fwd_messages[0]->from_id;
		} elseif(array_key_exists(1, $words) && bot_is_mention($words[1])){
			$member_id = bot_get_id_from_mention($words[1]);
		} elseif(array_key_exists(1, $words) && is_numeric($words[1])) {
			$member_id = intval($words[1]);
		} else $member_id = $data->object->from_id;

		if($data->object->from_id == $member_id)
			$other_user = false;
		else
			$other_user = true;

		if($other_user && !$economy->checkUser($member_id)){
			$botModule->sendSimpleMessage($data->object->peer_id, ", пользователь еще не зарегистрирован.", $data->object->from_id);
			return;
		}

		$user_economy = $economy->getUser($member_id);

		$money = round($user_economy->getMoney(), 2, PHP_ROUND_HALF_DOWN);

		$job_id = $user_economy->getJob();;
		if($job_id !== false)
			$job_name = Economy\Job::getNameByID($job_id);
		else
			$job_name = "Без работы";

		$vehicles = $user_economy->getItemsByType("vehicle");
		if(count($vehicles) > 0){
			$levels = array();
			for($i = 0; $i < count($vehicles); $i++){
				$levels[] = intval(mb_substr($vehicles[$i]->id, 6));
			}
			rsort($levels);
			$vehicle_text = Economy\Item::getItemName("vehicle", "level_{$levels[0]}");
		}
		else
			$vehicle_text = "Нет";

		$immovables = $user_economy->getItemsByType("immovables");
		if(count($immovables) > 0){
			$levels = array();
			for($i = 0; $i < count($immovables); $i++){
				$levels[] = intval(mb_substr($immovables[$i]->id, 6));
			}
			rsort($levels);
			$immovables_text = Economy\Item::getItemName("immovables", "level_{$levels[0]}");
		}
		else
			$immovables_text = "Нет";

		$phone = $user_economy->getItemsByType("phone");
		if(count($phone) > 0){
			$levels = array();
			for($i = 0; $i < count($phone); $i++){
				$levels[] = intval(mb_substr($phone[$i]->id, 6));
			}
			rsort($levels);
			$phone_text = Economy\Item::getItemName("phone", "level_{$levels[0]}");
		}
		else
			$phone_text = "Нет";

		$edu = $user_economy->getItemsByType("edu");
		if(count($edu) > 0){
			$levels = array();
			for($i = 0; $i < count($edu); $i++){
				$levels[] = intval(mb_substr($edu[$i]->id, 6));
			}
			rsort($levels);
			$edu_text = Economy\Item::getItemName("edu", "level_{$levels[0]}");
		}
		else
			$edu_text = "Нет";

		if($other_user)
			$pre_msg = "Счет @id{$member_id} (пользователя)";
		else
			$pre_msg = "Ваш счет";

		$msg = ", {$pre_msg}:\n💰Деньги: \${$money}\n\n👥Профессия: {$job_name}\n📚Образование: {$edu_text}\n\n🚗Транспорт: {$vehicle_text}\n🏡Недвижимость: {$immovables_text}\n📱Телефон: {$phone_text}";

		$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
	}

	function economy_work($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);

		$date = time(); // Переменная времени

		$user_economy = $economy->getUser($data->object->from_id);

		if(array_key_exists(1, $words)){
			$job_index = intval(bot_get_word_argv($words, 1, 0));
			if($job_index <= 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", Номер профессии не может быть меньше или равен 0.", $data->object->from_id);
				return;
			}
			$job_id = Economy\Job::getIDByIndex($job_index-1);
			if($job_id !== false){
				$jobs = Economy\Job::getJobArray();
				$user_job = $user_economy->getJob();
				if($user_job !== false && Economy\Job::jobExists($user_job)){
					$current_job = Economy\Job::getJobArray()[$user_economy->getJob()];
					$last_working_time = $user_economy->getMeta("last_working_time", 0);
					if($date - $last_working_time < $current_job["rest_time"]){
						$time = $current_job["rest_time"] - ($date - $last_working_time);
						$minutes = intdiv($time, 60);
						$seconds = $time % 60;
						$left_time_text = "";
						if($minutes != 0)
							$left_time_text = "{$minutes} мин. ";
						$left_time_text = $left_time_text."{$seconds} сек.";
						$msg = ", Вы сильно устали и не можете поменять профессию! Приходите через {$left_time_text}";
						$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
						return;
					}
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$dependency_item_name = Economy\Item::getItemName($item->type, $item->id);
						$job_name = Economy\Job::getNameByID($job_id);
						$botModule->sendSimpleMessage($data->object->peer_id, ", Вы не можете устроиться на профессию {$job_name}. Вам необходимо иметь {$dependency_item_name}.", $data->object->from_id);
						return;
					}
				}
				$user_economy->setJob($job_id);
				$job_name = Economy\Job::getNameByID($job_id);
				$botModule->sendSimpleMessage($data->object->peer_id, ", Вы устроились на работу {$job_name}.", $data->object->from_id);
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", Такой профессии не существует.", $data->object->from_id);
			}
		} 
		else{
			$job_id = $user_economy->getJob();
			if($job_id !== false){
				if(!Economy\Job::jobExists($job_id)){
					$botModule->sendSimpleMessage($data->object->peer_id, ", вы работаете на несуществующей профессии.", $data->object->from_id);
					return;
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$dependency_item_name = Economy\Item::getItemName($item->type, $item->id);
						$job_name = Economy\Job::getNameByID($job_id);
						$botModule->sendSimpleMessage($data->object->peer_id, ", Вы не можете работать по профессии {$job_name}. Вам необходимо иметь {$dependency_item_name}.", $data->object->from_id);
						return;
					}
				}
				$job = Economy\Job::getJobArray()[$job_id];
				$last_working_time = $user_economy->getMeta("last_working_time");
				if($last_working_time === false)
					$last_working_time = 0;

				if($date - $last_working_time >= $job["rest_time"]){
					$user_economy->setMeta("last_working_time", $date);
					$salary = $job["salary"];
					$user_economy->changeMoney($salary);
					$salary_text = "\${$salary}";

					$male_msg = mb_eregi_replace("{SALARY}", $salary_text, $job["message"]["male"]);
					$male_msg = mb_eregi_replace("{USERNAME}", "%appeal%", $male_msg);
					$female_msg = mb_eregi_replace("{SALARY}", $salary_text, $job["message"]["female"]);
					$female_msg = mb_eregi_replace("{USERNAME}", "%appeal%", $female_msg);

					$msg_array = array(
						'male' => $male_msg,
						'female' => $female_msg
					);

					$msg = json_encode($msg_array, JSON_UNESCAPED_UNICODE);
					$msg = vk_parse_var($msg, "appeal");

					vk_execute($botModule->makeExeAppeal($data->object->from_id)."
						var msg = {$msg};
						var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex'})[0];

						if(user.sex == 1){
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg.female});
						}
						else{
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg.male});
						}
						");
				}
				else{
					$time = $job["rest_time"] - ($date - $last_working_time);
					$minutes = intdiv($time, 60);
					$seconds = $time % 60;
					$left_time_text = "";
					if($minutes != 0)
						$left_time_text = "{$minutes} мин. ";
					$left_time_text = $left_time_text."{$seconds} сек.";
					$msg = ", Вы сильно устали! Приходите через {$left_time_text}";
					$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", вы нигде не работаете. !работать <профессия> - устройство на работу, !профессии - список профессий.", $data->object->from_id);
			}
		}
	}

	function economy_joblist($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$jobs = Economy\Job::getJobArray();
		$print_jobs = array();

		$msg = ", список профессий: ";

		$index = 1;
		foreach ($jobs as $key => $value) {
			$spm = round($value["salary"] / ($value["rest_time"] / 60), 2); // Зарплата в минуту
			$msg = $msg . "\n• {$index}. {$value["name"]} — \${$spm}/мин";
			//$msg = $msg . "\n• {$index}. {$value["name"]}";
			$index++;
		}

		$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
	}

	function economy_jobinfo($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$job_index = intval(bot_get_word_argv($words, 1, 0));

		if($job_index > 0){
			$jobs = Economy\Job::getJobArray();
			$job_id = Economy\Job::getIDByIndex($job_index-1);

			if($job_id !== false){
				$time = $jobs[$job_id]["rest_time"];
				$minutes = intdiv($time, 60);
				$seconds = $time % 60;
				$left_time_text = "";
				if($minutes != 0)
					$left_time_text = "{$minutes} мин. ";
				$left_time_text = $left_time_text."{$seconds} сек.";
				$item_dependencies = $jobs[$job_id]["item_dependencies"];
				$item_dependencies_text = "Ничего";
				if(count($item_dependencies) > 0){
					$economy = new Economy\Main($db);
					$user_economy = $economy->getUser($data->object->from_id);
					$item = Economy\Item::getItemObjectFromString($item_dependencies[0]);
					$status_char = "⛔";
					if($user_economy->checkItem($item->type, $item->id) !== false)
						$status_char = "✅";
					$item_dependencies_text = "{$status_char}".Economy\Item::getItemName($item->type, $item->id).'';
					for($i = 1; $i < count($item_dependencies); $i++){
						$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
						$status_char = "⛔";
						if($user_economy->checkItem($item->type, $item->id) !== false)
							$status_char = "✅";
						$item_dependencies_text = $item_dependencies_text.", {$status_char}".Economy\Item::getItemName($item->type, $item->id);
					}
				}
				$msg = ",\n✏Название: {$jobs[$job_id]["name"]}\n💰Зарплата: \${$jobs[$job_id]["salary"]}\n📅Время отдыха: {$left_time_text}\n💼Необходимо: {$item_dependencies_text}";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", Такой профессии нет!", $data->object->from_id);
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, " используйте:", array(
				'!профессия <номер> - Информация о профессии'
			));
		}
	}

	function economy_buy($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$argv1 = bot_get_word_argv($words, 1);

		$sections = Economy\Item::getShopSectionsArray();

		$section_id = -1;

		for($i = 0; $i < count($sections); $i++){
			if(mb_strtolower($sections[$i]["name"]) == mb_strtolower($argv1)){
				$section_id = $i;
				break;
			}
		}

		if($section_id >= 0){
			$section = $sections[$section_id];
			$all_items_by_type = Economy\Item::getItemListByType($section["item_type"]); // Все предметы по по типу
			$items = array();
			foreach ($all_items_by_type as $key => $value) {
				if($value["can_buy"])
					$items_for_buy[$key] = $value;
			}
			$item_data = array_values($items_for_buy);
			$item_ids = array_keys($items_for_buy);

			$economy = new Economy\Main($db);
			$user_economy = $economy->getUser($data->object->from_id);

			$argv2 = intval(bot_get_word_argv($words, 2));
			if($argv2 >= 1){
				$index = $argv2-1;
				if(!array_key_exists($index, $item_ids)){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Товара под номером {$argv2} не существует.", $data->object->from_id);
					return;
				}

				if($user_economy->checkItem($section["item_type"], $item_ids[$index]) !== false){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас уже есть товар под номером {$argv2}.", $data->object->from_id);
					return;
				}

				$price = $item_data[$index]["price"];
				$transaction_result = $user_economy->changeMoney(-$price);

				if($transaction_result){
					$user_economy->changeItem($section["item_type"], $item_ids[$index], 1);
					$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Покупка прошла успешно.", $data->object->from_id);
				}
				else{
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас недостаточно ".mb_strtoupper($price["currency"])." на счету.", $data->object->from_id);
				}
			}
			else{
				$msg = ", используйте \"!купить ".mb_strtolower($sections[$i]["name"])." <номер>\".\n📄Доступно для покупки:";
				$items_count = count($item_data);
				for($i = 0; $i < $items_count; $i++){
					$price = $item_data[$i]["price"];
					if($user_economy->checkItem($section["item_type"], $item_ids[$i]) !== false)
						$status = "✅";
					else
						$status = "⛔";
					$price_text = "\${$price}";
					if($items_count >= 10){
						$index_num = $i + 1;
						if($index_num < 10)
							$index = "0".$index_num;
						else
							$index = $index_num;
					}
					else
						$index = $i + 1;
					$msg = $msg . "\n{$index}. {$status}" . $item_data[$i]["name"] . " — {$price_text}";
				}
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
		}
		else{
			$section_names = array();
			for($i = 0; $i < count($sections); $i++){
				$section_names[] = "!купить ".mb_strtolower($sections[$i]["name"]);
			}
			$botModule->sendCommandListFromArray($data, ", используйте: ", $section_names);
		}
	}

	function economy_sell($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$argv1 = intval(bot_get_word_argv($words, 1, 0));
		$argv2 = intval(bot_get_word_argv($words, 2, 1));



		if($argv1 > 0){
			$economy = new Economy\Main($db);
			$user_economy = $economy->getUser($data->object->from_id);
			$user_items = $user_economy->getItems();
			$index = $argv1 - 1;

			if(count($user_items) < $argv1){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственности под номером {$argv1} у вас нет.", $data->object->from_id);
				return;
			}

			if($argv2 <= 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Количество не может быть отрицательным числом или быть равным 0.", $data->object->from_id);
				return;
			}

			Economy\EconomyFiles::readDataFiles();
			$items = Economy\EconomyFiles::getEconomyFileData("items");

			$selling_item_info = $items[$user_items[$index]->type][$user_items[$index]->id];

			if(!$selling_item_info["can_sell"]){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственность \"{$selling_item_info["name"]}\" невозможно продать.", $data->object->from_id);
				return;
			}

			if($user_economy->changeItem($user_items[$index]->type, $user_items[$index]->id, -$argv2)){
				$value = $selling_item_info["price"] * 0.7 * $argv2;
				$user_economy->changeMoney($value); // Добавляем к счету пользователя 70% от начальной стоимости товара
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Собственность \"{$selling_item_info["name"]}\" продана в количестве {$argv2} за \${$value}.", $data->object->from_id);
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас в наличии только {$user_items[$index]->count} {$selling_item_info["name"]}.", $data->object->from_id);
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'!продать <номер> <кол-во> - Продать имущество',
				'!имущество <список> - Список имущества'
			));
		}
	}

	function economy_myprops($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);
		$items = $user_economy->getItems();

		if(count($items) > 0){
			$list_number_from_word = intval(bot_get_word_argv($words, 1, 1));

			/////////////////////////////////////////////////////
			////////////////////////////////////////////////////
			$list_in = &$items; // Входной список
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

			$msg = ", Ваше имущество [$list_number/$list_max_number]:";
			for($i = 0; $i < count($list_out); $i++){
				$name = Economy\Item::getItemName($list_out[$i]->type, $list_out[$i]->id);
				$index = ($i + 1) + 10 * ($list_number-1);
				$msg = $msg . "\n✅ {$index}. " . $name . " — {$list_out[$i]->count} шт.";
			}
			$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет имущества.", $data->object->from_id);
		}
	}

	function economy_bank($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$argv1 = bot_get_word_argv($words, 1, "");

		if($argv1 == "перевод"){
			$argv2 = floatval(bot_get_word_argv($words, 2, 0));
			$argv3 = bot_get_word_argv($words, 3, "");

			if($argv2 <= 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", используйте \"!банк перевод <сумма> <пользователь>\".", $data->object->from_id);
				return;
			}

			if(array_key_exists(0, $data->object->fwd_messages)){
				$member_id = $data->object->fwd_messages[0]->from_id;
			} elseif(!is_null($argv3) && bot_is_mention($argv3)){
				$member_id = bot_get_id_from_mention($argv3);
			} elseif(!is_null($argv3) && is_numeric($argv3)) {
				$member_id = intval($argv3);
			} else {
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите пользователя.", $data->object->from_id);
				return;
			}

			if($member_id == $data->object->from_id){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Невозможно перевести деньги самому себе.", $data->object->from_id);
				return;
			}

			if($economy->checkUser($member_id)){
				$member_economy = $economy->getUser($member_id);

				if($user_economy->changeMoney(-$argv2)){
					$member_economy->changeMoney($argv2);
					$botModule->sendSimpleMessage($data->object->peer_id, ", ✅\${$argv2} успешно переведены на счет @id{$member_id} (пользователя).", $data->object->from_id);
				}
				else
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету недостаточно $.", $data->object->from_id);
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У @id{$member_id} (пользователя) нет счета в беседе.", $data->object->from_id);
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, ", используйте:", array(
				"!банк перевод - Перевод денег на счет другого пользователя"
			));
		}
	}

	function economy_education($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$edu = Economy\Item::getItemListByType("edu");
		$edu_ids = array_keys($edu);
		$edu_data = array_values($edu);

		$argv1 = intval(bot_get_word_argv($words, 1, 0));

		if($argv1 > 0 && count($edu_ids) >= $argv1){
			if($argv1 == 1){
				if($user_economy->checkItem("edu", $edu_ids[$argv1-1]) !== false){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас уже есть данное образование.", $data->object->from_id);
					return;
				}
				$edu_index = $argv1 - 1;
			}
			else{
				$previous_level = $argv1 - 2;
				if($user_economy->checkItem("edu", $edu_ids[$previous_level]) === false){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет уровня \"".$edu_data[$previous_level]["name"]."\".", $data->object->from_id);
					return;
				}
				if($user_economy->checkItem("edu", $edu_ids[$argv1-1]) !== false){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас уже есть данное образование.", $data->object->from_id);
					return;
				}
				$edu_index = $argv1 - 1;
			}

			$price = $edu_data[$edu_index]["price"];
			if($user_economy->changeMoney(-$price)){
				$user_economy->changeItem("edu", $edu_ids[$edu_index], 1);
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Вы успешно получили образование уровня \"{$edu_data[$edu_index]["name"]}\".", $data->object->from_id);
			}
			else
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету недостаточно $.", $data->object->from_id);
		}
		else{
			$msg = ", используйте \"!образование <номер>\". Список доступного образования:";
			$edu_ids = array_keys($edu);
			$edu_data = array_values($edu);
			for($i = 0; $i < count($edu_ids); $i++){
				$index = $i + 1;
				if($user_economy->checkItem("edu", $edu_ids[$i]) !== false)
					$status = "✅";
				else
					$status = "⛔";
				$msg = $msg . "\n{$index}. {$status}" . $edu_data[$i]["name"] . " — \$" . $edu_data[$i]["price"];
			}
			$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
		}
	}

	function economy_company($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$command = mb_strtolower(bot_get_word_argv($words, 1, ""));

		if($command == "купить"){
			$type_index = bot_get_word_argv($words, 2, 0);
			Economy\EconomyFiles::readDataFiles();
			$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
			$types = array_keys($enterprise_types);
				if($type_index > 0 && count($types) >= $type_index){
					$enterprise_price = $enterprise_types[$types[$type_index-1]]["price"];
					if($user_economy->canChangeMoney(-$enterprise_price)){
						$enterpriseSystem = $economy->initEnterpriseSystem();
						$enterprise_id = $enterpriseSystem->createEnterprise($types[$type_index-1], $data->object->from_id);
						if($enterprise_id === false){
							$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Не удалось купить бизнес.", $data->object->from_id);
							return;
						}
						$user_economy->addEnterprise($enterprise_id);
						$user_economy->changeMoney(-$enterprise_price);
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Бизнес успешно куплен. Его ID: {$enterprise_id}.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На вашем счету нет \${$enterprise_price} для покупки бизнеса.", $data->object->from_id);
					}
				}
				else{
					$msg = ", доступные типы бизнесов: ";
					for($i = 0; $i < count($types); $i++){
						$index = $i + 1;
						$msg .= "\n{$index}. {$enterprise_types[$types[$i]]["name"]} — \${$enterprise_types[$types[$i]]["price"]}";
					}
					$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
		}
		elseif($command == "продать"){
			
		}
		elseif($command == "выбрать"){
			$argv = bot_get_word_argv($words, 2, "");
			if($argv == "*"){
				$user_economy->deleteMeta("selected_enterprise_index");
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Информация о выбранном бизнесе очищена.", $data->object->from_id);
			}
			elseif($argv == ""){
				$enterpriseSystem = $economy->initEnterpriseSystem();
				$user_enterprises = $user_economy->getEnterprises();
				$msg = ", список ваших бизнесов:";
				for($i = 0; $i < count($user_enterprises); $i++){
					$j = $i + 1;
					$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$i]);
					$msg .= "\n{$j}. ".$enterprise["name"];
				}
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
			elseif(is_numeric($argv)){
				$index = intval($argv);
				$user_enterprises = $user_economy->getEnterprises();
				if($index > 0 && count($user_enterprises) >= $index){
					$enterpriseSystem = $economy->initEnterpriseSystem();
					$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);
					$user_economy->setMeta("selected_enterprise_index", $index);
					$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Выбран бизнес под названием \"{$enterprise["name"]}\".", $data->object->from_id);
				}
				else{
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Бизнеса под номером {$index} не существует.", $data->object->from_id);
				}
			}
		}
		elseif($command == "информация"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = &$enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$current_contracts_count = count($enterprise["contracts"]);
				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$type = $enterprise_types[$enterprise["type"]]["name"];
				$msg = ", информация о бизнесе:\n📎ID: {$enterprise["id"]}\n📝Название: {$enterprise["name"]}\n🔒Тип: {$type}\n💰Бюджет: \${$enterprise["capital"]}\n👥Рабочих: {$enterprise["workers"]}\n📊Опыт: {$enterprise["exp"]}\n📄Контракты:\n&#12288;• Активных: {$current_contracts_count}\n&#12288;• Лимит: {$enterprise["max_contracts"]}";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "бюджет"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = &$enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$command = mb_strtolower(bot_get_word_argv($words, 2, ""));
				$value = round(abs(floatval(bot_get_word_argv($words, 3, 0))), 2);

				if($command == "пополнить"){
					if($value == 0){
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите сумму операции.", $data->object->from_id);
						return;
					}

					if($user_economy->changeMoney(-$value)){
						$enterpriseSystem->changetEnterpriseCapital($enterprise["id"], $value);
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅{$value} успешно переведены на счет бизнеса.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На вашем счету недостаточно средств.", $data->object->from_id);
					}
				}
				elseif($command == "снять"){
					if($value == 0){
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите сумму операции.", $data->object->from_id);
						return;
					}

					if($enterpriseSystem->changetEnterpriseCapital($enterprise["id"], -$value)){
						$user_economy->changeMoney($value);
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅{$value} успешно переведены на ваш счет.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					}
				}
				else{
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						"!бизнес бюджет пополнить <сумма> - Попоплнение бюджета",
						"!бизнес бюджет снять <сумма> - Снятие средств с бюджета"
					));
				}
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "название"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = &$enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$name = mb_substr($data->object->text, 17);
				if($name == ""){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите название.", $data->object->from_id);
					return;
				}
				$enterprise["name"] = $name;
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Название \"{$name}\" установлено.", $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "контракты"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = &$enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argv = intval(bot_get_word_argv($words, 2, 0));

				if($argv > 0 && count($contracts) >= $argv){
					$index = $argv-1;
					$contract = $contracts[$index];
					
					$time = $contract["duration"];
					$minutes = intdiv($time, 60);
					$seconds = $time % 60;
					$duration = "";
					if($minutes != 0)
						$duration = "{$minutes} мин. ";
					$duration = $duration."{$seconds} сек.";

					$net_income = $contract["income"] - $contract["cost"];
					$msg = ", информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$contract["cost"]}\n📈Доход: \${$contract["income"]}\n💰Чистый доход: \${$net_income}\n👥Необходимо рабочих: {$contract["workers_required"]}";

					$contracts_count = count($contracts);
					if($contracts_count > 1){
						if($index == 0){
							$next_index = bot_int_to_emoji_str($index + 2);
							$controlButtons = array(
								vk_text_button("➡ {$next_index}", array('command' => "economy_contract", 'params' => array("action" => 3, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
							);
						}
						elseif($index == $contracts_count - 1){
							$previous_index = bot_int_to_emoji_str($index);
							$controlButtons = array(
								vk_text_button("{$previous_index} ⬅", array('command' => "economy_contract", 'params' => array("action" => 2, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
							);
						}
						else{
							$next_index = bot_int_to_emoji_str($index + 2);
							$previous_index = bot_int_to_emoji_str($index);
							$controlButtons = array(
								vk_text_button("{$previous_index} ⬅", array('command' => "economy_contract", 'params' => array("action" => 2, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary"),
								vk_text_button("➡ {$next_index}", array('command' => "economy_contract", 'params' => array("action" => 3, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
							);
						}

						$keyboard = vk_keyboard_inline(array(
							array(
								vk_text_button("Реализовать", array('command' => "economy_contract", 'params' => array("action" => 1, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "positive")
							),
							$controlButtons
						));
					}
					else{
						$keyboard = vk_keyboard_inline(array(
							array(
								vk_text_button("Реализовать", array('command' => "economy_contract", 'params' => array("action" => 1, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "positive")
							)
						));
					}

					$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
				}
				elseif($argv == 0){
					$msg = ", список контрактов для вашего бизнеса:";
					for($i = 0; $i < count($contracts); $i++){
						$j = $i + 1;
						$contract = $contracts[$i];
						$cps = round(($contract["income"] - $contract["cost"]) / ($contract["duration"] / 60), 2);
						$msg .= "\n{$j}. ".$contract["name"]."  — \${$cps}/мин";
					}
					$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
				else{
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Контракта под номером {$argv} не существует.", $data->object->from_id);
				}
			}
			else{
				$botModule->sendCommandListFromArray($data, ", используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, ", используйте:", array(
				'!бизнес купить <тип> - Покупка бизнеса',
				'!бизнес продать <id> - Продажа бизнеса',
				'!бизнес выбрать - Список бизнесов/Выбирает управляемый бизнес',
				'!бизнес управлять - Управление бизнесом',
				'!бизнес помощь - Объяснение принципов работы бизнесов'
			));
		}
	}

	function economy_keyboard_contract_handler($finput){ // Обработчик клавиатурной команды economy_contract
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = &$finput->db;

		if($payload->params->user_id != $data->object->from_id)
			return;

		$botModule = new BotModule($db);

		$economy = new Economy\Main($db);
		$enterpriseSystem = $economy->initEnterpriseSystem();

		$enterprise = $enterpriseSystem->getEnterprise($payload->params->enterprise_id);

		if($enterprise === false){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Этот бизнес больше не существует.", $data->object->from_id);
			return;
		}

		if($enterprise["owner_id"] != $data->object->from_id){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вы больше не являетесь владельцем данного бизнеса.", $data->object->from_id);
			return;
		}

		if($payload->params->action == 1){
			$botModule->sendSimpleMessage($data->object->peer_id, ", Типо пока что я не могу этого сделать.", $data->object->from_id);
		}
		elseif($payload->params->action == 2 || $payload->params->action == 3){
			$contract_id = $payload->params->contract_id;
			switch ($payload->params->action) {
				case 2:
					$contract_id--;
					break;

				case 3:
					$contract_id++;
					break;
			}
			Economy\EconomyFiles::readDataFiles();
			$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
			$contracts = $enterprise_types[$enterprise["type"]]["contracts"];
			$index = $contract_id;
			$contract = $contracts[$index];
			
			$time = $contract["duration"];
			$minutes = intdiv($time, 60);
			$seconds = $time % 60;
			$duration = "";
			if($minutes != 0)
				$duration = "{$minutes} мин. ";
			$duration = $duration."{$seconds} сек.";

			$net_income = $contract["income"] - $contract["cost"];
			$msg = ", информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$contract["cost"]}\n📈Доход: \${$contract["income"]}\n💰Чистый доход: \${$net_income}\n👥Необходимо рабочих: {$contract["workers_required"]}";

			$contracts_count = count($contracts);
			if($contracts_count > 1){
				if($index == 0){
					$next_index = bot_int_to_emoji_str($index + 2);
					$controlButtons = array(
						vk_text_button("➡ {$next_index}", array('command' => "economy_contract", 'params' => array("action" => 3, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
					);
				}
				elseif($index == $contracts_count - 1){
					$previous_index = bot_int_to_emoji_str($index);
					$controlButtons = array(
						vk_text_button("{$previous_index} ⬅", array('command' => "economy_contract", 'params' => array("action" => 2, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
					);
				}
				else{
					$next_index = bot_int_to_emoji_str($index + 2);
					$previous_index = bot_int_to_emoji_str($index);
					$controlButtons = array(
						vk_text_button("{$previous_index} ⬅", array('command' => "economy_contract", 'params' => array("action" => 2, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary"),
						vk_text_button("➡ {$next_index}", array('command' => "economy_contract", 'params' => array("action" => 3, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
					);
				}

				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Реализовать", array('command' => "economy_contract", 'params' => array("action" => 1, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "positive")
					),
					$controlButtons
				));
			}
			else{
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Реализовать", array('command' => "economy_contract", 'params' => array("action" => 1, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "positive")
					)
				));
			}

			$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
		}
	}

	function economy_most_rich_users($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);

		$users = $economy->getUserArray();

		if(count($users) > 0){
			$user_ids = array_keys($users);
			$rating = array();
			for($i = 0; $i < count($user_ids); $i++){
				$user_id = intval(mb_substr($user_ids[$i], 2));
				$user_economy = $economy->getUser($user_id);
				$capital = $user_economy->getMoney();
				$user_items = $user_economy->getItems();
				Economy\EconomyFiles::readDataFiles();
				$items = Economy\EconomyFiles::getEconomyFileData("items");
				for($j = 0; $j < count($user_items); $j++){
					$capital = $capital + $items[$user_items[$j]->type][$user_items[$j]->id]["price"];
				}

				if($capital != 0){
					$rating[] = array(
						'capital' => $capital,
						"user_id" => $user_id
					);
				}
			}

			for($i = 0; $i < sizeof($rating); $i++){
				for($j = 0; $j < sizeof($rating); $j++){
					if ($rating[$i]["capital"] > $rating[$j]["capital"]){
						$temp = $rating[$j];
						$rating[$j] = $rating[$i];
						$rating[$i] = $temp;
						unset($temp);
					}
				}
			}

			$rating_for_print = array();

			for($i = 0; $i < count($rating) && $i < 10; $i++){
				$rating_for_print[] = $rating[$i];
			}

			$rating_json = json_encode($rating_for_print, JSON_UNESCAPED_UNICODE);

			vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				var rating = {$rating_json};
				var user_ids = rating@.user_id;
				var users = API.users.get({'user_ids':user_ids});
				var msg = appeal+', Список самых богатых людей в беседе по мнению Forbes:\\n';
				var i = 0; while(i < rating.length){
					msg = msg+(i+1)+'. @id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') — \$'+rating[i].capital+'\\n';
					i = i + 1;
				}
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':1});
				");

		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", ни один пользователь беседы не попал в этот список.", $data->object->from_id);
		}
	}
}

?>