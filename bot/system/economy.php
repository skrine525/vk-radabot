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
		private $user_id;

		function __construct(&$db, $user_id){
			if($user_id <= 0)
				return false;
			$this->db = &$db;
			$this->user_id = $user_id;
		}

		public function getMoney(){
			return $this->getMeta("money", 0);
		}

		public function changeMoney($value){
			$money = $this->getMoney();
			if($money + $value >= 0){
				$value = round($value, 0);
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
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			$items = array();
			for($i = 0; $i < count($user_items); $i++){
				$a = explode(":", $user_items[$i]);
				$items[] = (object) array(
					'type' => $a[0],
					'id' => $a[1],
					'count' => $a[2]
				);
			}
			return $items;
		}

		public function getItemByIndex($index){
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			if(array_key_exists($index, $user_items)){
				$a = explode(":", $user_items[$index]);
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
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			$items = array();
			for($i = 0; $i < count($user_items); $i++){
				$a = $this->getItemByIndex($i);
				if($a->type == $type)
					$items[] = $a;
			}
			return $items;
		}

		public function checkItem($type, $id){
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			if(gettype($type) == "string" && gettype($id) == "string"){
				for($i = 0; $i < count($user_items); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						return $i;
					}
				}
			}
			return false;
		}

		public function changeItem($type, $id, $count){
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			if(gettype($type) == "string" && gettype($id) == "string" && gettype($count) == "integer"){
				for($i = 0; $i < count($user_items); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						$new_count = $r->count + $count;
						if($new_count < 0)
							return false;
						elseif($new_count == 0){
							$this->deleteItem($type, $id);
						}
						else{
							$this->db->setValue(array("economy", "users", "id{$this->user_id}", "items", $i), "{$r->type}:{$r->id}:{$new_count}");
						}
						return true;
					}
				}
				if($count > 0){
					$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
					$user_items[] = "{$type}:{$id}:{$count}";
					$this->db->setValue(array("economy", "users", "id{$this->user_id}", "items"), $user_items);
					return true;
				}
				else
					return false;
			}
			else
				return false;
		}

		public function deleteItem($type, $id){
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			if(gettype($type) == "string" && gettype($id) == "string"){
				for($i = 0; $i < count($user_items); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						unset($user_items[$i]);
						$user_items = array_values($user_items);
						$this->db->setValue(array("economy", "users", "id{$this->user_id}", "items"), $user_items);
						return true;
					}
				}
			}
			return false;
		}

		public function setMeta($name, $value){
			$this->db->setValue(array("economy", "users", "id{$this->user_id}", "meta", $name), $value);
		}

		public function getMeta($name, $default = false){
			return $this->db->getValue(array("economy", "users", "id{$this->user_id}", "meta", $name), $default);
		}

		public function deleteMeta($name){
			return $this->db->unsetValue(array("economy", "users", "id{$this->user_id}", "meta", $name));
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
			$item = self::getItemInfo($type, $id);
			return $item->name;
		}

		public static function isHidden($type, $id){
			$item = self::getItemInfo($type, $id);
			return $item->hidden;
		}

		public static function getItemInfo($type, $id){
			EconomyFiles::readDataFiles();
			$items = EconomyFiles::getEconomyFileData("items");
			if(array_key_exists($type, $items) && array_key_exists($id, $items[$type])){
				return (object) $items[$type][$id];
			}
			else{
				$item = array(
					'name' => 'Неизвестный предмет',
					'price' => 0,
					'can_sell' => true,
					'can_buy' => false,
					'hidden' => true
				);
				return (object) $item;
			}
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
				if(!array_key_exists(0, $a))
					$a[0] = null;
				if(!array_key_exists(1, $a))
					$a[1] = null;
				if(!array_key_exists(2, $a))
					$a[2] = null;
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
		private static $economy_data;
		//private static $jobs_data;
		private static $is_read = false;

		public static function readDataFiles(){
			if(!self::$is_read){
				self::$economy_data = json_decode(file_get_contents(BOT_DATADIR."/economy/economy.json"), true);
				//self::$jobs_data = json_decode(file_get_contents(BOT_DATADIR."/economy/jobs.json"), true);
				if(is_null(self::$economy_data)/* || self::$jobs_data === false*/){
					error_log("Invalid economy.json file");
					exit;
				}
				self::$is_read = true;
			}
		}

		public static function getEconomyFileData($section){
			if(array_key_exists($section, self::$economy_data)){
				return self::$economy_data[$section];
			}
			else
				return false;
		}
	}

	class EnterpriseEconomyManager{
		private $db;

		static private function generateRandomString($length) {
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
			$this->db = &$db;
		}

		public static function getTypeName($type){
			if($type == "nil"){
				return "Не указано";
			}
			else {

			}
		}

		public function createEnterprise($type, $owner_id){
			$id = '';
			$attempts = 0;
			$enterprise_ids = array_keys($this->db->getValue(array("economy", "enterprises"), array()));
			while(true){
				$id = self::generateRandomString(5+intdiv($attempts, 10));
				if(array_search($id, $enterprise_ids) === false)
					break;
				$attempts++;
			}
			unset($attempts);

			EconomyFiles::readDataFiles();
			$types = array_keys(EconomyFiles::getEconomyFileData("enterprise_types"));
			if(array_search($type, $types) === false)
				return false;

			if(gettype($owner_id) != "integer")
				return false;

			$time = time();

			$enterprise = array(
				'id' => $id,
				'name' => $id,
				'type' => $type,
				'created_time' => $time,
				'owner_id' => $owner_id,
				'workers' => 5,
				'involved_workers' => 0,
				'capital' => 0,
				'exp' => 0,
				'max_contracts' => 1,
				'improvment' => array(
					'workers' => 0,
					'contracts' => 0
				),
				'contracts' => array()
			);

			$this->db->setValue(array("economy", "enterprises", $id), $enterprise);
			return $id;
		}

		public function getEnterprise($id){
			$enterprise = $this->db->getValue(array("economy", "enterprises", $id), false);
			if($enterprise !== false){
				$time = time();
				$contract_count = count($enterprise["contracts"]);
				for($i = 0; $i < $contract_count; $i++){
					if($time - $enterprise["contracts"][$i]["start_time"] >= $enterprise["contracts"][$i]["contract_info"]["duration"]){
						if($enterprise["contracts"][$i]["type"] == "contract"){
							$enterprise["capital"] += $enterprise["contracts"][$i]["contract_info"]["income"];
							$enterprise["involved_workers"] -= $enterprise["contracts"][$i]["contract_info"]["workers_required"];
							$enterprise["exp"] += $enterprise["contracts"][$i]["contract_info"]["exp"];
							unset($enterprise["contracts"][$i]);
						}
						elseif($enterprise["contracts"][$i]["type"] == "workers_improvment"){
							$enterprise["involved_workers"] -= $enterprise["contracts"][$i]["contract_info"]["workers_required"];
							$enterprise["workers"] += $enterprise["contracts"][$i]["contract_info"]["new_workers"];
							$enterprise["improvment"]["workers"]++;
							unset($enterprise["contracts"][$i]);
						}
						elseif($enterprise["contracts"][$i]["type"] == "contracts_improvment"){
							$enterprise["involved_workers"] -= $enterprise["contracts"][$i]["contract_info"]["workers_required"];
							$enterprise["max_contracts"]++;
							$enterprise["improvment"]["contracts"]++;
							unset($enterprise["contracts"][$i]);
						}
					}
				}
				$enterprise["contracts"] = array_values($enterprise["contracts"]); // Заменяем несуществующие на существующие элементы массива
				return $enterprise;
			}
			else
				return false;
		}

		public function saveEnterprise($id, $data){
			if(gettype($id) == "string" && $id != "")
				return $this->db->setValue(array("economy", "enterprises", $id), $data);
			else
				return false;
		}

		public function changeEnterpriseCapital(&$enterprise, $value){
			if($enterprise["capital"] + $value >= 0){
				$enterprise["capital"] += $value;
				return true;
			}
			else
				return false;
		}
	}

	class Main{
		private $db;

		function __construct(&$db){
			$this->db = &$db;
		}

		function getUserArray(){
			return $this->db->getValue(array("economy", "users"), array());
		}

		function getUser($user_id){
			return new UserEconomyManager($this->db, $user_id);
		}

		function initEnterpriseSystem(){
			return new EnterpriseEconomyManager($this->db);
		}

		function checkUser($user_id){
			$user_info = $this->db->getValue(array("economy", "users", "id{$user_id}"), false);
			if($user_info !== false)
				return true;
			else
				return false;
		}

		/////////////////////////////////////////////////////////////////////////
		/// Статические методы

		function getFormatedMoney($money){
			$money = round($money, 2);
			return number_format($money, 0, '.', ',');
		}
	}
}

namespace{

	function economy_initcmd(&$event){ // Инициализация тексовых комманд модуля экономики
		$event->addTextCommand("!счет", "economy_show_user_stats");
		$event->addTextCommand("!счёт", "economy_show_user_stats");
		$event->addTextCommand("!работать", "economy_work");
		$event->addTextCommand("!профессии", "economy_joblist");
		$event->addTextCommand("!профессия", "economy_jobinfo");
		$event->addTextCommand("!купить", "economy_buy");
		$event->addTextCommand("!продать", "economy_sell");
		$event->addTextCommand("!имущество", "economy_myprops");
		$event->addTextCommand("!награды", "economy_mypawards");
		$event->addTextCommand("!банк", "economy_bank");
		$event->addTextCommand("!образование", "economy_education");
		$event->addTextCommand("!forbes", "economy_most_rich_users");
		$event->addTextCommand("!бизнес", "economy_company");
		$event->addTextCommand("подарить", "economy_give");

		$event->addKeyboardCommand("economy_contract", "economy_keyboard_contract_handler");
		$event->addKeyboardCommand("economy_getjob", "economy_keyboard_getjob");
		$event->addKeyboardCommand("economy_improve", "economy_keyboard_improve_handler");

		// Test
		//$event->addTextCommand("!invlist", "economy_test1");
		//$event->addTextCommand("!invadd", "economy_test2");
		//$event->addTextCommand("!invtype", "economy_test3");
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

		if(!$economy->checkUser($member_id)){
			if(!$other_user){
				$db->setValue(array("economy", "users", "id{$member_id}"), array(
					'meta' => array(),
					'items' => array()
				));
				$db->save();
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", пользователь еще не зарегистрирован.", $data->object->from_id);
				return;
			}
		}

		$user_economy = $economy->getUser($member_id);

		$money = Economy\Main::getFormatedMoney($user_economy->getMoney());

		$job_id = $user_economy->getJob();;
		if($job_id !== false)
			$job_name = Economy\Job::getNameByID($job_id);
		else
			$job_name = "Без работы";

		$cars = $user_economy->getItemsByType("car");
		if(count($cars) > 0){
			$levels = array();
			for($i = 0; $i < count($cars); $i++){
				$levels[] = intval(mb_substr($cars[$i]->id, 6));
			}
			rsort($levels);
			$car_text = Economy\Item::getItemName("car", "level_{$levels[0]}");
		}
		else
			$car_text = "Нет";

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

		$user_enterprises = $user_economy->getEnterprises();
		if(count($user_enterprises) > 0){
			$enterprise_info = "\n🏭Предприятия:";
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$number = 1;
			foreach ($user_enterprises as $enterprise_id) {
				$enterprise = $enterpriseSystem->getEnterprise($enterprise_id);
				$emoji = bot_int_to_emoji_str($number);
				$number++;
				$enterprise_info .= "\n&#12288;{$emoji} {$enterprise["name"]}";
			}
		}
		else
			$enterprise_info = "";

		$msg = ", {$pre_msg}:\n💰Деньги: \${$money}\n\n👥Профессия: {$job_name}\n📚Образование: {$edu_text}\n\n🚗Транспорт: {$car_text}\n🏡Недвижимость: {$immovables_text}\n📱Телефон: {$phone_text}{$enterprise_info}";

		$keyboard = vk_keyboard_inline(array(
			array(
				vk_text_button("Работать", array("command" => "bot_run_text_command", "text_command" => "!работать"), "positive")
			)
		));

		$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
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
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите номер профессии.", $data->object->from_id);
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
				$db->save();
				$job_name = Economy\Job::getNameByID($job_id);
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Работать", array("command" => "bot_run_text_command", "text_command" => "!работать"), "positive")
					)
				));
				$botModule->sendSimpleMessage($data->object->peer_id, ", Вы устроились на работу {$job_name}.", $data->object->from_id, array('keyboard' => $keyboard));
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
					$db->save();
					$salary_text = "\$".Economy\Main::getFormatedMoney($salary);

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
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Профессии", array("command" => "bot_run_text_command", "text_command" => "!профессии"), "primary")
					)
				));
				$botModule->sendSimpleMessage($data->object->peer_id, ", вы нигде не работаете. !работать <профессия> - устройство на работу, !профессии - список профессий.", $data->object->from_id, array("keyboard" => $keyboard));
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
				$item_dependencies_text = "";
				if(count($item_dependencies) > 0){
					$economy = new Economy\Main($db);
					$user_economy = $economy->getUser($data->object->from_id);
					$item = Economy\Item::getItemObjectFromString($item_dependencies[0]);
					$status_char = "⛔";
					if($user_economy->checkItem($item->type, $item->id) !== false)
						$status_char = "✅";
					for($i = 0; $i < count($item_dependencies); $i++){
						$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
						$status_char = "⛔";
						if($user_economy->checkItem($item->type, $item->id) !== false)
							$status_char = "✅";
						$item_dependencies_text .= "\n&#12288;{$status_char}".Economy\Item::getItemName($item->type, $item->id);
					}
				}
				if(!isset($item_dependencies_text))
					$item_dependencies_text = "Ничего";
				$salary = Economy\Main::getFormatedMoney($jobs[$job_id]["salary"]);
				$msg = ",\n✏Название: {$jobs[$job_id]["name"]}\n💰Зарплата: \${$salary}\n📅Время отдыха: {$left_time_text}\n💼Необходимо: {$item_dependencies_text}";
				$jobs_count = count($jobs);
				if($jobs_count > 1){
					if($job_index <= 1){
						$next_index = $job_index + 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($next_index)." ➡", array('command' => "bot_run_text_command", 'text_command' => "!профессия {$next_index}"), "secondary")
						);
					}
					elseif($job_index >= $jobs_count){
						$previous_index = $job_index - 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($previous_index)." ⬅", array('command' => "bot_run_text_command", 'text_command' => "!профессия {$previous_index}"), "secondary")
						);
					}
					else{
						$previous_index = $job_index - 1;
						$next_index = $job_index + 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($previous_index)." ⬅", array('command' => "bot_run_text_command", 'text_command' => "!профессия {$previous_index}"), "secondary"),
							vk_text_button(bot_int_to_emoji_str($next_index)." ➡", array('command' => "bot_run_text_command", 'text_command' => "!профессия {$next_index}"), "secondary")
						);
					}
				}
				else
					$controlButtons = array();
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Устроиться", array('command' => "economy_getjob", 'params' => array('job_id' => $job_id)), "positive")
					),
					$controlButtons
				));
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array('keyboard' => $keyboard));
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

	function economy_keyboard_getjob($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = &$finput->db;

		$date = time(); // Переменная времени

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);
		$jobs = Economy\Job::getJobArray();

		if(array_key_exists($payload->params->job_id, $jobs)){
			$job_id = $payload->params->job_id;
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
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Работать", array("command" => "bot_run_text_command", "text_command" => "!работать"), "positive")
				)
			));
			$botModule->sendSimpleMessage($data->object->peer_id, ", Вы устроились на работу {$job_name}.", $data->object->from_id, array('keyboard' => $keyboard));
			$db->save();
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", Такой профессии нет!", $data->object->from_id);
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
			switch ($section["type"]) {
				case 'item':
					$all_items = Economy\EconomyFiles::getEconomyFileData("items");
					$items_for_buy = array(); // Предметы на продажу
					if(gettype($section["items"]) == "string"){
						$all_items_by_type = Economy\Item::getItemListByType($section["items"]); // Все предметы по по типу
						foreach ($all_items_by_type as $key => $value) {
							if($value["can_buy"])
								$items_for_buy[] = array(
									'type' => $section["items"],
									'id' => $key
								);
						}
						unset($all_items_by_type);
					}
					elseif(gettype($section["items"]) == "array"){
						foreach ($section["items"] as $value) {
						$item_data = explode(":", $value);
						$item = $all_items[$item_data[0]][$item_data[1]];
						if($item["can_buy"])
							$items_for_buy[] = array(
								'type' => $item_data[0],
								'id' => $item_data[1]
							);
						}
					}

					$economy = new Economy\Main($db);
					$user_economy = $economy->getUser($data->object->from_id);

					$argv2 = intval(bot_get_word_argv($words, 2));
					if($argv2 >= 1){
						$index = $argv2-1;
						if(count($items_for_buy) <= $index){
							$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Товара под номером {$argv2} не существует.", $data->object->from_id);
							return;
						}

						$item_for_buy = $items_for_buy[$index];

						if($user_economy->checkItem($item_for_buy["type"], $item_for_buy["id"]) !== false){
							$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас уже есть товар под номером {$argv2}.", $data->object->from_id);
							return;
						}

						$price = $all_items[$item_for_buy["type"]][$item_for_buy["id"]]["price"];
						$transaction_result = $user_economy->changeMoney(-$price);

						if($transaction_result){
							$user_economy->changeItem($item_for_buy["type"], $item_for_buy["id"], 1);
							$db->save();
							$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Вы приобрели {$all_items[$item_for_buy["type"]][$item_for_buy["id"]]["name"]}.", $data->object->from_id);
						}
						else{
							$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас недостаточно ".mb_strtoupper($price["currency"])." на счету.", $data->object->from_id);
						}
					}
					else{
						$msg = ", используйте \"!купить ".mb_strtolower($sections[$i]["name"])." <номер>\".\n📄Доступно для покупки:";
						$items_for_buy_count = count($items_for_buy);
						$user_items = $db->getValue(array("economy", "users", "id{$data->object->from_id}", "items"), array());
						for($i = 0; $i < $items_for_buy_count; $i++){
							$price = $all_items[$items_for_buy[$i]["type"]][$items_for_buy[$i]["id"]]["price"];
							
							$status = "⛔";
							for($j = 0; $j < count($user_items); $j++){
								$r = explode(":", $user_items[$j]);
								if($r[0] == $items_for_buy[$i]["type"] && $r[1] == $items_for_buy[$i]["id"]){
									$status = "✅";
								}
							}

							$price_text = "\$".Economy\Main::getFormatedMoney($price);
							if($items_for_buy_count >= 10){
								$index_num = $i + 1;
								if($index_num < 10)
									$index = "0".$index_num;
								else
									$index = $index_num;
							}
							else
								$index = $i + 1;
							$msg = $msg . "\n{$index}. {$status}" . $all_items[$items_for_buy[$i]["type"]][$items_for_buy[$i]["id"]]["name"] . " — {$price_text}";
						}
						$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
					}
					break;

				case 'enterprise':
					$economy = new Economy\Main($db);
					$user_economy = $economy->getUser($data->object->from_id);
					Economy\EconomyFiles::readDataFiles();
					if($user_economy->checkItem("edu", "level_4") === false){
						$edu_name = Economy\Item::getItemName("edu", "level_4");
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вы не можете купить бизнес. У вас должно быть {$edu_name}.", $data->object->from_id);
						return;
					}
					if(count($user_economy->getEnterprises()) >= 3){
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔вы уже имеете максимальное количество бизнесов (3).", $data->object->from_id);
						return;
					}
					$type_index = bot_get_word_argv($words, 2, 0);
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
							$db->save();
							$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Бизнес успешно куплен. Его ID: {$enterprise_id}.", $data->object->from_id);
						}
						else{
							$enterprise_price = Economy\Main::getFormatedMoney($enterprise_price);
							$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На вашем счету нет \${$enterprise_price} для покупки бизнеса.", $data->object->from_id);
						}
					}
					else{
						$msg = ", доступные типы бизнесов: ";
						for($i = 0; $i < count($types); $i++){
							$index = $i + 1;
							$price = Economy\Main::getFormatedMoney($enterprise_types[$types[$i]]["price"]);
							$msg .= "\n{$index}. {$enterprise_types[$types[$i]]["name"]} — \${$price}";
						}
						$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
					}
					break;
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

			// Скрываем предметы с истиным параметром hidden
			$items = array();
			for($i = 0; $i < count($user_items); $i++){
				if(!Economy\Item::isHidden($user_items[$i]->type, $user_items[$i]->id))
					$items[] = $user_items[$i];
			}

			$index = $argv1 - 1;

			if(count($items) < $argv1){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственности под номером {$argv1} у вас нет.", $data->object->from_id);
				return;
			}

			if($argv2 <= 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Количество не может быть отрицательным числом или быть равным 0.", $data->object->from_id);
				return;
			}

			$selling_item_info = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

			if(!$selling_item_info->can_sell){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственность \"{$selling_item_info->name}\" невозможно продать.", $data->object->from_id);
				return;
			}

			if($user_economy->changeItem($items[$index]->type, $items[$index]->id, -$argv2)){
				$value = $selling_item_info->price * 0.7 * $argv2;
				$user_economy->changeMoney($value); // Добавляем к счету пользователя 70% от начальной стоимости товара
				$db->save();
				$value = Economy\Main::getFormatedMoney($value);
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Собственность \"{$selling_item_info->name}\" продана в количестве {$argv2} за \${$value}.", $data->object->from_id);
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас в наличии только {$items[$index]->count} {$selling_item_info->name}.", $data->object->from_id);
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'!продать <номер> <кол-во> - Продать имущество',
				'!имущество <список> - Список имущества'
			));
		}
	}

	function economy_mypawards($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);
		$user_items = $user_economy->getItemsByType("special");

		// Скрываем предметы с истиным параметром hidden
		$items = array();
		for($i = 0; $i < count($user_items); $i++){
			if(!Economy\Item::isHidden($user_items[$i]->type, $user_items[$i]->id))
				$items[] = $user_items[$i];
		}

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

			$msg = ", ⚡Ваши награды: [$list_number/$list_max_number]:";
			for($i = 0; $i < count($list_out); $i++){
				$name = Economy\Item::getItemName($list_out[$i]->type, $list_out[$i]->id);
				$index = ($i + 1) + 10 * ($list_number-1);
				$msg = $msg . "\n{$index}. " . $name;
			}
			$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
		}
		else{
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет наград.", $data->object->from_id);
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
		$user_items = $user_economy->getItems();

		// Скрываем предметы с истиным параметром hidden
		$items = array();
		for($i = 0; $i < count($user_items); $i++){
			if(!Economy\Item::isHidden($user_items[$i]->type, $user_items[$i]->id))
				$items[] = $user_items[$i];
		}

		$items_count = count($items);
		if($items_count > 0){
			$argv1 = bot_get_word_argv($words, 1, 1);
			if(is_numeric($argv1)){
				$list_number_from_word = intval($argv1);

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
				$keyboard = vk_keyboard_inline(array(array(vk_text_button("Купить", array("command" => "bot_run_text_command", "text_command" => "!купить"), "positive")),array(vk_text_button("Продать", array("command" => "bot_run_text_command", "text_command" => "!продать"), "negative")),array(vk_text_button("Подарить", array("command" => "bot_run_text_command", "text_command" => "Подарить"), "primary"))));
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
			}
			elseif(mb_strtolower($argv1) == "инфа"){
				$argv2 = intval(bot_get_word_argv($words, 2, 0));
				if($argv2 <= 0){
					$botModule->sendSimpleMessage($data->object->peer_id, ", используйте !имущество инфа <номер>.", $data->object->from_id);
					return;
				}
				if($argv2 > $items_count){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет имущества под номером {$argv2}.", $data->object->from_id);
					return;
				}
				$index = $argv2-1;
				$item = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

				$buying_price = Economy\Main::getFormatedMoney($item->price);
				$selling_price = Economy\Main::getFormatedMoney($item->price*0.7);
				$can_buy = ($item->can_buy ? "Да ✅" : "Нет ⛔");
				$can_sell = ($item->can_sell ? "Да ✅" : "Нет ⛔");
				$msg = ", информация о имуществе:\n📝Название: {$item->name}\n🛒Можно купить: {$can_buy}\n💳Можно продать: {$can_sell}\n💰Цена: \${$buying_price}\n📈Цена продажи: \${$selling_price}";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(array(vk_text_button("Купить", array("command" => "bot_run_text_command", "text_command" => "!купить"), "positive")),array(vk_text_button("Продать", array("command" => "bot_run_text_command", "text_command" => "!продать"), "negative")),array(vk_text_button("Подарить", array("command" => "bot_run_text_command", "text_command" => "Подарить"), "primary"))));
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет имущества.", $data->object->from_id, array("keyboard" => $keyboard));
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

		$time = time();

		$argv1 = bot_get_word_argv($words, 1, "");

		if($argv1 == "перевод"){
			$argv2 = intval(bot_get_word_argv($words, 2, 0));
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
					$db->save();
					$money = Economy\Main::getFormatedMoney($argv2);
					$botModule->sendSimpleMessage($data->object->peer_id, ", ✅\${$money} успешно переведены на счет @id{$member_id} (пользователя).", $data->object->from_id);
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
				$db->save();
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
				$price = Economy\Main::getFormatedMoney($edu_data[$i]["price"]);
				$msg = $msg . "\n{$index}. {$status}" . $edu_data[$i]["name"] . " — \$" . $price;
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

		if($command == "выбрать"){
			$argv = bot_get_word_argv($words, 2, "");
			if($argv == "0"){
				$user_economy->deleteMeta("selected_enterprise_index");
				$db->save();
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Информация о выбранном бизнесе очищена.", $data->object->from_id);
			}
			elseif($argv == ""){
				$enterpriseSystem = $economy->initEnterpriseSystem();
				$user_enterprises = $user_economy->getEnterprises();
				$enterprises = array();
				foreach ($user_enterprises as $id) {
					$enterprises[] = $db->getValue(array("economy", "enterprises", $id));
				}
				if(count($enterprises) == 0){
					$keyboard = vk_keyboard_inline(array(
						array(
							vk_text_button("Купить бизнес", array("command" => "bot_run_text_command", "text_command" => "!бизнес купить"), "positive")
						)
					));
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет ни одного бизнеса.", $data->object->from_id, array('keyboard' => $keyboard));
					return;
				}
				$msg = ", Используйте:\n• !бизнес выбрать <номер> - Выбрать бизнес\n• !бизнес выбрать 0 - Убрать выбранный бизнес\n\nСписок ваших бизнесов:";
				$selected_enterprise_index = $user_economy->getMeta("selected_enterprise_index", 0) - 1;
				$enterprise_buttons = array();
				for($i = 0; $i < count($enterprises); $i++){
					$j = $i + 1;
					if($i == $selected_enterprise_index){
						$msg .= "\n➡{$j}. {$enterprises[$i]["name"]}";
						$enterprise_buttons[] = vk_text_button($j, array("command" => "bot_run_text_command", "text_command" => "!бизнес выбрать {$j}"), "primary");
					}
					else{
						$msg .= "\n{$j}. {$enterprises[$i]["name"]}";
						$enterprise_buttons[] = vk_text_button($j, array("command" => "bot_run_text_command", "text_command" => "!бизнес выбрать {$j}"), "secondary");
					}
				}
				$keyboard = vk_keyboard_inline(array(
					$enterprise_buttons,
					array(
						vk_text_button("Убрать", array("command" => "bot_run_text_command", "text_command" => "!бизнес выбрать 0"), "negative")
					)
				));
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
			}
			elseif(is_numeric($argv)){
				$index = intval($argv);
				$user_enterprises = $user_economy->getEnterprises();
				if($index > 0 && count($user_enterprises) >= $index){
					$enterpriseSystem = $economy->initEnterpriseSystem();
					$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);
					$user_economy->setMeta("selected_enterprise_index", $index);
					$db->save();
					$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Выбран бизнес под названием \"{$enterprise["name"]}\".", $data->object->from_id);
				}
				else{
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Бизнеса под номером {$index} не существует.", $data->object->from_id);
				}
			}
		}
		elseif($command == "инфа"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$current_contracts_count = count($enterprise["contracts"]);
				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$type = $enterprise_types[$enterprise["type"]]["name"];
				$capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
				$msg = ", информация о бизнесе:\n📎ID: {$enterprise["id"]}\n📝Название: {$enterprise["name"]}\n🔒Тип: {$type}\n💰Бюджет: \${$capital}\n👥Рабочие: {$enterprise["involved_workers"]}/{$enterprise["workers"]}\n📊Опыт: {$enterprise["exp"]}\n📄Контракты: {$current_contracts_count}/{$enterprise["max_contracts"]}";
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
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$command = mb_strtolower(bot_get_word_argv($words, 2, ""));
				$value = round(abs(intval(bot_get_word_argv($words, 3, 0))), 2);

				if($command == "пополнить"){
					if($value == 0){
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите сумму операции.", $data->object->from_id);
						return;
					}

					if($user_economy->changeMoney(-$value)){
						$enterpriseSystem->changeEnterpriseCapital($enterprise, $value);
						$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
						$db->save();
						$value = Economy\Main::getFormatedMoney($value);
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

					if($enterpriseSystem->changeEnterpriseCapital($enterprise, -$value)){
						$user_economy->changeMoney($value);
						$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
						$db->save();
						$value = Economy\Main::getFormatedMoney($value);
						$botModule->sendSimpleMessage($data->object->peer_id, ", ✅{$value} успешно переведены на ваш счет.", $data->object->from_id);
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					}
				}
				else{
					$keyboard = vk_keyboard_inline(array(
						array(
							vk_text_button("⬆ 1К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет пополнить 1000"), "negative"),
							vk_text_button("⬆ 5К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет пополнить 5000"), "negative")
						),
						array(
							vk_text_button("⬆ 10К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет пополнить 10000"), "negative"),
							vk_text_button("⬆ 100К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет пополнить 100000"), "negative")
						),
						array(
							vk_text_button("⬇ 1К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет снять 1000"), "positive"),
							vk_text_button("⬇ 5К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет снять 5000"), "positive")
						),
						array(
							vk_text_button("⬇ 10К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет снять 10000"), "positive"),
							vk_text_button("⬇ 100К", array('command' => 'bot_run_text_command', 'text_command' => "!бизнес бюджет снять 100000"), "positive")
						),
					));
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						"!бизнес бюджет пополнить <сумма> - Попоплнение бюджета",
						"!бизнес бюджет снять <сумма> - Снятие средств с бюджета"
					), $keyboard);
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
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$name = mb_substr($data->object->text, 17);
				if($name == ""){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Укажите название.", $data->object->from_id);
					return;
				}
				if(mb_strlen($name) > 20){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Название не может быть больше 20 символов.", $data->object->from_id);
					return;
				}
				$enterprise["name"] = $name;
				$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
				$db->save();
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
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argv = intval(bot_get_word_argv($words, 2, 0));

				if($argv > 0 && count($contracts) >= $argv){
					$index = $argv-1;
					$contract = $contracts[$index];
					
					$time = $contract["duration"];
					$hours = intdiv($time, 3600);
					$minutes = intdiv($time-3600*$hours, 60);
					$seconds = $time % 60;
					$duration = "";
					if($hours != 0)
						$duration = "{$hours} ч. ";
					if($minutes != 0)
						$duration .= "{$minutes} мин. ";
					if($seconds != 0)
						$duration .= "{$seconds} сек.";

					$cost = Economy\Main::getFormatedMoney($contract["cost"]);
					$income = Economy\Main::getFormatedMoney($contract["income"]);
					$net_income = Economy\Main::getFormatedMoney($contract["income"] - $contract["cost"]);
					$msg = ", информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$cost}\n📈Доход: \${$income}\n💰Чистый доход: \${$net_income}\n📊Получаемый опыт: {$contract["exp"]}\n👥Необходимо рабочих: {$contract["workers_required"]}";

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
					$elements = array(array());
					$current_element_index = 0;
					$msg = ", список контрактов для вашего бизнеса:";
					for($i = 0; $i < count($contracts); $i++){
						$j = $i + 1;
						$contract = $contracts[$i];
						$cps = round(($contract["income"] - $contract["cost"]) / ($contract["duration"] / 60), 2);
						$msg .= "\n{$j}. ".$contract["name"]."  — \${$cps}/мин";
						if(count($elements[$current_element_index]) >= 5){
							$elements[] = array();
							$current_element_index++;
						}
						$elements[$current_element_index][] = vk_text_button(bot_int_to_emoji_str($j), array('command' => "economy_contract", 'params' => array("action" => 4, "enterprise_id" => $enterprise["id"], "contract_id" => $i, "user_id" => $data->object->from_id)), "secondary");
					}
					$keyboard = vk_keyboard_inline($elements);
					$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array('keyboard' => $keyboard));
				}
				else{
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Контракта под номером {$argv} не существует.", $data->object->from_id);
				}
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "очередь"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);
				$contracts = $enterprise["contracts"];
				$argv = intval(bot_get_word_argv($words, 2, 0));

				$time = time();
				$msg = ", активные контракты:";
				for($i = 0; $i < count($contracts) || $i < $enterprise["max_contracts"]; $i++){
					$j = $i + 1;
					if(array_key_exists($i, $contracts)){
						$contract = $contracts[$i];
						$left_time = $contract["contract_info"]["duration"] - ($time - $contract["start_time"]);
						$hours = intdiv($left_time, 3600);
						$minutes = intdiv($left_time-3600*$hours, 60);
						$seconds = $left_time % 60;
						$left_info = "";
						if($hours < 10)
							$left_info  .= "0";
						$left_info .= "{$hours}:";
						if($minutes < 10)
							$left_info  .= "0";
						$left_info .= "{$minutes}:";
						if($seconds < 10)
							$left_info  .= "0";
						$left_info .= "{$seconds}";
						$msg .= "\n{$j}. ".$contract["contract_info"]["name"]." ({$left_info})";
					}
					else
						$msg .= "\n{$j}. Свободный слот";
				}
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif ($command == "улучшить") {
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
					return;
				}

				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

				$argv = intval(bot_get_word_argv($words, 2, 0));
				if($argv <= 0 || $argv > 2){
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						'!бизнес улучшить 1 - Увеличение числа рабочих',
						'!бизнес улучшить 2 - Увеличение слотов'
					));
					return;
				}

				if($argv == 1){
					if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"])){
						$type = "workers_improvment";
						$contract = $improvment["workers"][$enterprise["improvment"]["workers"]];
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}
				else{
					if(array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"])){
						$type = "contracts_improvment";
						$contract = $improvment["contracts"][$enterprise["improvment"]["contracts"]];
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}

				$capital_after_start = $enterprise["capital"] - $contract["cost"];
				if($capital_after_start < 0){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					return;
				}
				$exp_after_start = $enterprise["exp"] - $contract["exp_required"];
				if($exp_after_start < 0){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Недостаточно опыта.", $data->object->from_id);
					return;
				}
				$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
				if($involved_workers_after_start > $enterprise["workers"]){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
					return;
				}
				$enterprise["capital"] = $capital_after_start;
				$enterprise["exp"] = $exp_after_start;
				$enterprise["involved_workers"] = $involved_workers_after_start;
				$enterprise["contracts"][] = array (
					"type" => $type,
					"started_by" => $data->object->from_id,
					"start_time" => time(),
					"contract_info" => $contract
				);
				$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
				$db->save();
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "улучшение"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

				$argv = intval(bot_get_word_argv($words, 2, 0));
				if($argv <= 0 || $argv > 2){
					$keyboard = vk_keyboard_inline(array(
						array(
							vk_text_button("Улучшение рабочих", array("command" => "bot_run_text_command", "text_command" => "!бизнес улучшение 1"), "primary")
						),
						array(
							vk_text_button("Улучшение слотов", array("command" => "bot_run_text_command", "text_command" => "!бизнес улучшение 2"), "primary")
						)
					));
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						'!бизнес улучшение 1 - Описание улучшения рабочих',
						'!бизнес улучшение 2 - Описание улучшения слотов'
					), $keyboard);
					return;
				}

				if($argv == 1){
					if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"])){
						$contract = $improvment["workers"][$enterprise["improvment"]["workers"]];

						$time = $contract["duration"];
						$hours = intdiv($time, 3600);
						$minutes = intdiv($time-3600*$hours, 60);
						$seconds = $time % 60;
						$duration = "";
						if($hours != 0)
							$duration = "{$hours} ч. ";
						if($minutes != 0)
							$duration .= "{$minutes} мин. ";
						if($seconds != 0)
							$duration .= "{$seconds} сек.";

						switch ($contract["new_workers"] % 10) {
							case 1:
								$improvment_text = "+{$contract["new_workers"]} рабочий";
								break;
							
							default:
								$improvment_text = "+{$contract["new_workers"]} рабочих";
								break;
						}

						$keyboard = vk_keyboard_inline(array(
							array(
								vk_text_button("Выполнить улучшение", array('command' => "economy_improve", 'params' => array("improvment_type" => 1, "enterprise_id" => $enterprise["id"], "user_id" => $data->object->from_id)), "positive")
							)
						));

					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}
				else{
					if(array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"])){
						$contract = $improvment["contracts"][$enterprise["improvment"]["contracts"]];

						$time = $contract["duration"];
						$hours = intdiv($time, 3600);
						$minutes = intdiv($time-3600*$hours, 60);
						$seconds = $time % 60;
						$duration = "";
						if($hours != 0)
							$duration = "{$hours} ч. ";
						if($minutes != 0)
							$duration .= "{$minutes} мин. ";
						if($seconds != 0)
							$duration .= "{$seconds} сек.";

						$improvment_text = "+1 слот контрактов";
						$keyboard = vk_keyboard_inline(array(
							array(
								vk_text_button("Выполнить улучшение", array('command' => "economy_improve", 'params' => array("improvment_type" => 2, "enterprise_id" => $enterprise["id"], "user_id" => $data->object->from_id)), "positive")
							)
						));
					}
					else{
						$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}

				$cost = Economy\Main::getFormatedMoney($contract["cost"]);
				$msg = ", информация о улучшении:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n💰Стоимость: \${$cost}\n📊Необходимо Опыта: {$contract["exp_required"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n🔓Результат: {$improvment_text}";
				$botModule->sendSimpleMessage($data->object->peer_id, $msg, $data->object->from_id, array('keyboard' => $keyboard));
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		elseif($command == "выполнить"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
					return;
				}

				Economy\EconomyFiles::readDataFiles();
				$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argv = intval(bot_get_word_argv($words, 2, 0));
				if($argv <= 0 || count($contracts) < $argv){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Контракта под #{$argv} не существует.", $data->object->from_id);
					return;
				}
				$contract = $contracts[$argv-1];

				$capital_after_start = $enterprise["capital"] - $contract["cost"];
				if($capital_after_start < 0){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					return;
				}
				$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
				if($involved_workers_after_start > $enterprise["workers"]){
					$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
					return;
				}
				$enterprise["capital"] = $capital_after_start;
				$enterprise["involved_workers"] = $involved_workers_after_start;
				$enterprise["contracts"][] = array (
					"type" => "contract",
					"started_by" => $data->object->from_id,
					"start_time" => time(),
					"contract_info" => $contract
				);
				$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
				$db->save();
				$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Купить", array("command" => "bot_run_text_command", "text_command" => "!купить бизнес"), "positive")
				),
				array(
					vk_text_button("Выбрать", array("command" => "bot_run_text_command", "text_command" => "!бизнес выбрать"), "primary"),
					vk_text_button("Информация", array("command" => "bot_run_text_command", "text_command" => "!бизнес инфа"), "primary")
				),
				array(
					vk_text_button("Контракты", array("command" => "bot_run_text_command", "text_command" => "!бизнес контракты"), "primary"),
					vk_text_button("Очередь", array("command" => "bot_run_text_command", "text_command" => "!бизнес очередь"), "primary"),
				),
				array(
					vk_text_button("Бюджет", array("command" => "bot_run_text_command", "text_command" => "!бизнес бюджет"), "primary"),
					vk_text_button("Улучшение", array("command" => "bot_run_text_command", "text_command" => "!бизнес улучшение"), "primary")
				)
			));
			$botModule->sendCommandListFromArray($data, ", используйте:", array(
				'!купить бизнес <тип> - Покупка бизнеса',
				//'!бизнес продать <id> - Продажа бизнеса',
				'!бизнес выбрать - Список бизнесов/Выбирает управляемый бизнес',
				'!бизнес инфа - Информация о выбранном бизнесе',
				'!бизнес название <название> - Изменение названия бизнеса',
				'!бизнес бюджет - Управление бюджетом бизнеса',
				'!бизнес контракты - Список доступных контрактов',
				'!бизнес контракты <номер> - Детальная информация по контракту',
				'!бизнес очередь - Управление активными контрактами',
				'!бизнес улучшение - Информация о улучшениях бизнеса',
				'!бизнес улучшить - Улучшение бизнеса'
			), $keyboard);
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
			if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
				return;
			}

			Economy\EconomyFiles::readDataFiles();
			$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
			$contracts = $enterprise_types[$enterprise["type"]]["contracts"];
			$contract = $contracts[$payload->params->contract_id];

			$capital_after_start = $enterprise["capital"] - $contract["cost"];
			if($capital_after_start < 0){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
				return;
			}
			$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
			if($involved_workers_after_start > $enterprise["workers"]){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
				return;
			}
			$enterprise["capital"] = $capital_after_start;
			$enterprise["involved_workers"] = $involved_workers_after_start;
			$enterprise["contracts"][] = array (
				"type" => "contract",
				"started_by" => $data->object->from_id,
				"start_time" => time(),
				"contract_info" => $contract
			);
			$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
			$db->save();
			$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
		}
		elseif($payload->params->action === 2 || $payload->params->action === 3 || $payload->params->action === 4){
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
			$hours = intdiv($time, 3600);
			$minutes = intdiv($time-3600*$hours, 60);
			$seconds = $time % 60;
			$duration = "";
			if($hours != 0)
				$duration = "{$hours} ч. ";
			if($minutes != 0)
				$duration .= "{$minutes} мин. ";
			if($seconds != 0)
				$duration .= "{$seconds} сек.";

			$cost = Economy\Main::getFormatedMoney($contract["cost"]);
			$income = Economy\Main::getFormatedMoney($contract["income"]);
			$net_income = Economy\Main::getFormatedMoney($contract["income"] - $contract["cost"]);
			$msg = ", информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$cost}\n📈Доход: \${$income}\n💰Чистый доход: \${$net_income}\n📊Получаемый опыт: {$contract["exp"]}\n👥Необходимо рабочих: {$contract["workers_required"]}";

			$contracts_count = count($contracts);
			if($contracts_count > 1){
				if($index == 0){
					$next_index = bot_int_to_emoji_str($index + 2);
					$controlButtons = array(
						vk_text_button("➡ {$next_index}", array('command' => "economy_contract", 'params' => array("action" => 3, "enterprise_id" => $enterprise["id"], "contract_id" => $index, "user_id" => $data->object->from_id)), "secondary")
					);
				}
				elseif($index >= $contracts_count - 1){
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

	function economy_keyboard_improve_handler($finput){ // Обработчик клавиатурной команды economy_contract
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


		if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
			return;
		}

		Economy\EconomyFiles::readDataFiles();
		$enterprise_types = Economy\EconomyFiles::getEconomyFileData("enterprise_types");
		$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

		if($payload->params->improvment_type == 1){
			if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"])){
				$type = "workers_improvment";
				$contract = $improvment["workers"][$enterprise["improvment"]["workers"]];
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
				return;
			}
		}
		else{
			if(array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"])){
				$type = "contracts_improvment";
				$contract = $improvment["contracts"][$enterprise["improvment"]["contracts"]];
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
				return;
			}
		}

		$capital_after_start = $enterprise["capital"] - $contract["cost"];
		if($capital_after_start < 0){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
			return;
		}
		$exp_after_start = $enterprise["exp"] - $contract["exp_required"];
		if($exp_after_start < 0){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Недостаточно опыта.", $data->object->from_id);
			return;
		}
		$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
		if($involved_workers_after_start > $enterprise["workers"]){
			$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
			return;
		}
		$enterprise["capital"] = $capital_after_start;
		$enterprise["exp"] = $exp_after_start;
		$enterprise["involved_workers"] = $involved_workers_after_start;
		$enterprise["contracts"][] = array (
			"type" => $type,
			"started_by" => $data->object->from_id,
			"start_time" => time(),
			"contract_info" => $contract
		);
		$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
		$db->save();
		$botModule->sendSimpleMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
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
				$a = array(
					'user_id' => $rating[$i]["user_id"],
					'capital' => Economy\Main::getFormatedMoney($rating[$i]["capital"])
				);
				$rating_for_print[] = $a;
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

	function economy_give($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$words = $finput->words;
		$db = &$finput->db;

		$botModule = new BotModule($db);

		$argv1 = intval(bot_get_word_argv($words, 1, 0));
		$argv2 = intval(bot_get_word_argv($words, 2, 0));
		$argv3 = bot_get_word_argv($words, 3, "");
		if(array_key_exists(0, $data->object->fwd_messages)){
			$member_id = $data->object->fwd_messages[0]->from_id;
		} elseif(bot_is_mention($argv3)){
			$member_id = bot_get_id_from_mention($argv3);
		} elseif(is_numeric($argv3)) {
			$member_id = intval($argv3);
		} else{
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Имущество", array("command" => "bot_run_text_command", "text_command" => "!имущество"), "primary")
				)
			));
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'Подарить <номер> <количество> <пользователь> - Дарит пользователю подарок',
				'!имущество - Список доступного для подарка имущества'
			), $keyboard);
			return;
		}

		if($argv1 > 0 && $argv2 > 0){
			$economy = new Economy\Main($db);

			if($economy->checkUser($member_id))
				$member_economy = $economy->getUser($member_id);
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У @id{$member_id} (пользователя) нет счета в беседе.", $data->object->from_id);
				return;
			}

			$user_economy = $economy->getUser($data->object->from_id);
			$user_items = $user_economy->getItems();

			// Скрываем предметы с истиным параметром hidden
			$items = array();
			for($i = 0; $i < count($user_items); $i++){
				if(!Economy\Item::isHidden($user_items[$i]->type, $user_items[$i]->id))
					$items[] = $user_items[$i];
			}

			$index = $argv1 - 1;

			if(count($items) < $argv1){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственности под номером {$argv1} у вас нет.", $data->object->from_id);
				return;
			}

			$giving_item_info = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

			if(!$giving_item_info->can_sell){
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔Собственность \"{$giving_item_info->name}\" невозможно подарить.", $data->object->from_id);
				return;
			}

			if($user_economy->changeItem($items[$index]->type, $items[$index]->id, -$argv2)){
				$member_economy->changeItem($items[$index]->type, $items[$index]->id, $argv2);
				$db->save();
				vk_execute("
					var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'first_name_dat,last_name_dat,sex'});
					var member = users[0];
					var from = users[1];

					var msg = '';
					if(from.sex == 1){
						msg = '@id{$data->object->from_id} ('+from.first_name+' '+from.last_name+') подарила {$giving_item_info->name} x{$argv2} @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+')';
					}
					else{
						msg = '@id{$data->object->from_id} ('+from.first_name+' '+from.last_name+') подарил одну {$giving_item_info->name} x{$argv2} @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+')';
					}
					API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
					");
			}
			else{
				$botModule->sendSimpleMessage($data->object->peer_id, ", ⛔У вас нет столько {$giving_item_info->name}.", $data->object->from_id);
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Имущество", array("command" => "bot_run_text_command", "text_command" => "!имущество"), "primary")
				)
			));
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'Подарить <номер> <количество> <пользователь> - Дарит пользователю подарок',
				'!имущество - Список доступного для подарка имущества'
			), $keyboard);
		}
	}
}

?>