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
						return $r;
					}
				}
			}
			return false;
		}

		public function changeItem($type, $id, $count){
			$user_items = $this->db->getValue(array("economy", "users", "id{$this->user_id}", "items"), array());
			if(gettype($type) == "string" && gettype($id) == "string" && gettype($count) == "integer"){
				$item_info = Item::getItemInfo($type, $id);
				for($i = 0; $i < count($user_items); $i++){
					$r = $this->getItemByIndex($i);
					if($r->type == $type && $r->id == $id){
						$new_count = $r->count + $count;
						if($new_count < 0 || $new_count > $item_info->max_count)
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
				if($count > 0 && $count <= $item_info->max_count){
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
			$items = EconomyConfigFile::getEconomyConfigFileDataFromSection("items");
			if(array_key_exists($type, $items) && array_key_exists($id, $items[$type])){
				return (object) $items[$type][$id];
			}
			else{
				$item = array(
					'name' => 'Неизвестный предмет',
					'price' => 0,
					'max_count' => 0,
					'can_sell' => true,
					'can_buy' => false,
					'hidden' => true
				);
				return (object) $item;
			}
		}

		public static function getShopSectionsArray(){
			return EconomyConfigFile::getEconomyConfigFileDataFromSection("shop_sections");
		}

		public static function getItemListByType($type){
			$items = EconomyConfigFile::getEconomyConfigFileDataFromSection("items");;
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

	class EconomyConfigFile{
		private static $economy_data;
		private static $is_read = false;

		private static function readDataFiles(){
			if(!self::$is_read){
				self::$economy_data = json_decode(file_get_contents(BOT_DATADIR."/economy/economy.json"), true);
				if(is_null(self::$economy_data)){
					error_log("Invalid economy.json config file");
					exit;
				}
				self::$is_read = true;
			}
		}

		public static function getEconomyConfigFileDataFromSection($section){
			self::readDataFiles();
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
		        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
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
			if($type == "null"){
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

			$types = array_keys(EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types"));
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
							// Расчитываем получаемый опыт
							$enterprise_types = EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
							$improvment = $enterprise_types[$enterprise["type"]]["improvment"];
							// Если предприятие максимального уровня, то не добавляем опыт
							if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"]) || array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"]))
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
	// Инициализация команд
	function economy_initcmd(&$event){ // Инициализация тексовых команд модуля экономики
		$chatModes = new ChatModes($event->getDatabase());
		if(!$chatModes->getModeValue("economy_enabled")) // Отключаем, если в беседе запрещена экономика
			return;

		$event->addTextMessageCommand("!счет", "economy_show_user_stats");
		$event->addTextMessageCommand("!счёт", "economy_show_user_stats");
		$event->addTextMessageCommand("!работа", "economy_work");
		$event->addTextMessageCommand("!профессии", "economy_joblist");
		$event->addTextMessageCommand("!профессия", "economy_jobinfo");
		$event->addTextMessageCommand("!купить", "economy_buy");
		$event->addTextMessageCommand("!продать", "economy_sell");
		$event->addTextMessageCommand("!имущество", "economy_myprops");
		$event->addTextMessageCommand("!награды", "economy_mypawards");
		$event->addTextMessageCommand("!банк", "economy_bank");
		$event->addTextMessageCommand("!образование", "economy_education");
		$event->addTextMessageCommand("!forbes", "economy_most_rich_users");
		$event->addTextMessageCommand("!бизнес", "economy_company");
		$event->addTextMessageCommand("подарить", "economy_give");
		$event->addTextMessageCommand("!казино", "CasinoRouletteGame::main");
		$event->addTextMessageCommand("!ставка", "CasinoRouletteGame::bet");

		$event->addCallbackButtonCommand('economy_company', 'economy_company_cb');
		$event->addCallbackButtonCommand('economy_work', 'economy_work_cb');
		$event->addCallbackButtonCommand('economy_jobcontrol', 'economy_jobcontrol_cb');
		$event->addCallbackButtonCommand('economy_education', 'economy_education_cb');
		$event->addCallbackButtonCommand('economy_shop', 'economy_shop_cb');
	}

	function economy_show_user_stats($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);

		if(array_key_exists(0, $data->object->fwd_messages)){
			$member_id = $data->object->fwd_messages[0]->from_id;
		} elseif(array_key_exists(1, $argv) && bot_is_mention($argv[1])){
			$member_id = bot_get_id_from_mention($argv[1]);
		} elseif(array_key_exists(1, $argv) && is_numeric($argv[1])) {
			$member_id = intval($argv[1]);
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
				$botModule->sendSilentMessage($data->object->peer_id, ", пользователь еще не зарегистрирован.", $data->object->from_id);
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

		$msg = ", {$pre_msg}:\n💰Деньги: \${$money}\n\n👥Профессия: {$job_name}\n📚Образование: {$edu_text}\n\n🚗Транспорт:\n&#12288;🚘Автомобиль: {$car_text}\n🏡Недвижимость: {$immovables_text}\n📱Телефон: {$phone_text}{$enterprise_info}";

		$keyboard = vk_keyboard_inline(array(
			array(
				vk_callback_button("Работать", array("economy_work", $data->object->from_id, 1), "positive")
			)
		));

		$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
	}

	function economy_work($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);
		$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Работа", array("economy_work", $data->object->from_id), "positive"))));
		$messagesModule->sendSilentMessage($data->object->peer_id, "%appeal%, Используйте кнопку ниже для работы.", array('keyboard' => $keyboard));

		/*
		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);

		$date = time(); // Переменная времени

		$user_economy = $economy->getUser($data->object->from_id);

		if(array_key_exists(1, $argv)){
			$job_index = intval(bot_get_array_value($argv, 1, 0));
			if($job_index <= 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите номер профессии.", $data->object->from_id);
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
						$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
						return;
					}
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$dependency_item_name = Economy\Item::getItemName($item->type, $item->id);
						$job_name = Economy\Job::getNameByID($job_id);
						$botModule->sendSilentMessage($data->object->peer_id, ", Вы не можете устроиться на профессию {$job_name}. Вам необходимо иметь {$dependency_item_name}.", $data->object->from_id);
						return;
					}
				}
				$user_economy->setJob($job_id);
				$db->save();
				$job_name = Economy\Job::getNameByID($job_id);
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Работать", array("command" => "bot_runtc", "text_command" => "!работать"), "positive")
					)
				));
				$botModule->sendSilentMessage($data->object->peer_id, ", Вы устроились на работу {$job_name}.", $data->object->from_id, array('keyboard' => $keyboard));
				}
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", Такой профессии не существует.", $data->object->from_id);
			}
		} 
		else{
			$job_id = $user_economy->getJob();
			if($job_id !== false){
				if(!Economy\Job::jobExists($job_id)){
					$botModule->sendSilentMessage($data->object->peer_id, ", вы работаете на несуществующей профессии.", $data->object->from_id);
					return;
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$dependency_item_name = Economy\Item::getItemName($item->type, $item->id);
						$job_name = Economy\Job::getNameByID($job_id);
						$botModule->sendSilentMessage($data->object->peer_id, ", Вы не можете работать по профессии {$job_name}. Вам необходимо иметь {$dependency_item_name}.", $data->object->from_id);
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

					vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
						var msg = {$msg};
						var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex'})[0];

						if(user.sex == 1){
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg.female,'disable_mentions':true});
						}
						else{
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg.male,'disable_mentions':true});
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
					$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
			}
			else{
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Профессии", array("command" => "bot_runtc", "text_command" => "!профессии"), "primary")
					)
				));
				$botModule->sendSilentMessage($data->object->peer_id, ", вы нигде не работаете. !работать <профессия> - устройство на работу, !профессии - список профессий.", $data->object->from_id, array("keyboard" => $keyboard));
			}
		}
		*/
	}

	function economy_work_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$date = time(); // Переменная времени

		// Переменная тестирования пользователя
		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->user_id);

		$command = bot_get_array_value($payload, 2, 0);

		switch ($command) {
			case 0:
			$job_id = $user_economy->getJob();

			if($job_id !== false && Economy\Job::jobExists($job_id)){
				$job_info = Economy\Job::getJobArray()[$job_id];

				$salary_formated = Economy\Main::getFormatedMoney($job_info["salary"]);

				$rest_time = $job_info["rest_time"];
				$minutes = intdiv($rest_time, 60);
				$seconds = $rest_time % 60;
				$rest_time_text = "";
				if($minutes != 0)
					$rest_time_text = "{$minutes} мин. ";
				$rest_time_text .= "{$seconds} сек.";

				$job_text = "👤Ваша профессия: {$job_info["name"]}\n💰Зарплата: \${$salary_formated}\n📅Время отдыха: {$rest_time_text}";
			}
			else{
				$job_text = "📌 Подсказка: Вы нигде не работаете. Устройтесь с помощью кнопки ниже.";
			}

			$message = "%appeal%, Меню управления профессией.\n\n{$job_text}";
			$keyboard = vk_keyboard_inline(array(
				array(vk_callback_button("Работать", array('economy_work', $testing_user_id, 1), 'positive')),
				array(vk_callback_button("Профессии", array("economy_jobcontrol", $testing_user_id), "primary")),
				array(
					vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
					vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
				)
			));

			$messagesModule = new Bot\Messages($db);
			$messagesModule->setAppealID($data->object->user_id);
			$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
			break;

			case 1:
			$job_id = $user_economy->getJob();
			if($job_id !== false){
				if(!Economy\Job::jobExists($job_id)){
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы работаете на несуществующей профессии.");
					return;
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$dependency_item_name = Economy\Item::getItemName($item->type, $item->id);
						$job_name = Economy\Job::getNameByID($job_id);
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы не можете работать по профессии {$job_name}. Вам необходимо иметь {$dependency_item_name}.");
						return;
					}
				}
				$job = Economy\Job::getJobArray()[$job_id];
				$last_working_time = $user_economy->getMeta("last_working_time");
				if($last_working_time === false)
					$last_working_time = 0;

				if($date - $last_working_time >= $job["rest_time"]){
					$user_economy->setMeta("last_working_time", $date);
					$default_salary = $job["salary"];
					$random_number = mt_rand(0, 65535);
					if($random_number % 4 == 0){
						$bonus = $default_salary * 0.25;
						$salary = $default_salary + $bonus;
						$salary_text = Economy\Main::getFormatedMoney($default_salary);
						$bonus_text = Economy\Main::getFormatedMoney($bonus);
						$work_message = "✅ Вы заработали \${$salary_text} и \${$bonus_text} в качестве премии.";
					}
					else{
						$salary = $default_salary;
						$salary_text = Economy\Main::getFormatedMoney($salary);
						$work_message = "✅ Вы заработали \${$salary_text}.";
					}
					$user_economy->changeMoney($salary);
					$db->save();
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, $work_message);
				}
				else{
					$time = $job["rest_time"] - ($date - $last_working_time);
					$minutes = intdiv($time, 60);
					$seconds = $time % 60;
					$left_time_text = "";
					if($minutes != 0)
						$left_time_text = "{$minutes} мин. ";
					$left_time_text = $left_time_text."{$seconds} сек.";
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы сильно устали! Приходите через {$left_time_text}");
				}
			}
			else{
				$messagesModule = new Bot\Messages($db);
				$messagesModule->setAppealID($data->object->user_id);
				$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Устроиться", array('economy_jobcontrol', $data->object->user_id)))));
				$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, "⛔ Вы нигде не работаете. Устройтесь на работу.", array('keyboard' => $keyboard));
			}
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			break;
		}
	}

	function economy_joblist($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->from_id);

		$botModule = new BotModule($db);

		$jobs = Economy\Job::getJobArray();
		$print_jobs = array();

		$msg = "%appeal%, список профессий: ";

		$index = 1;
		foreach ($jobs as $key => $value) {
			$spm = round($value["salary"] / ($value["rest_time"] / 60), 2); // Зарплата в минуту
			$msg .= "\n• {$index}. {$value["name"]} — \${$spm}/мин";
			$index++;
		}

		$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Профессии", array("economy_jobcontrol", $data->object->from_id), "positive"))));
		$messagesModule->sendSilentMessage($data->object->peer_id, $msg, array('keyboard' => $keyboard));
	}

	function economy_jobinfo($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$botModule = new BotModule($db);

		$job_index = intval(bot_get_array_value($argv, 1, 0));

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
				else
					$item_dependencies_text = "Ничего";
				$salary = Economy\Main::getFormatedMoney($jobs[$job_id]["salary"]);
				$msg = ",\n✏Название: {$jobs[$job_id]["name"]}\n💰Зарплата: \${$salary}\n📅Время отдыха: {$left_time_text}\n💼Необходимо: {$item_dependencies_text}";
				$jobs_count = count($jobs);
				if($jobs_count > 1){
					if($job_index <= 1){
						$next_index = $job_index + 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($next_index)." ➡", array('command' => "bot_runtc", 'text_command' => "!профессия {$next_index}"), "secondary")
						);
					}
					elseif($job_index >= $jobs_count){
						$previous_index = $job_index - 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($previous_index)." ⬅", array('command' => "bot_runtc", 'text_command' => "!профессия {$previous_index}"), "secondary")
						);
					}
					else{
						$previous_index = $job_index - 1;
						$next_index = $job_index + 1;
						$controlButtons = array(
							vk_text_button(bot_int_to_emoji_str($previous_index)." ⬅", array('command' => "bot_runtc", 'text_command' => "!профессия {$previous_index}"), "secondary"),
							vk_text_button(bot_int_to_emoji_str($next_index)." ➡", array('command' => "bot_runtc", 'text_command' => "!профессия {$next_index}"), "secondary")
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
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array('keyboard' => $keyboard));
			}
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", Такой профессии нет!", $data->object->from_id);
			}
		}
		else{
			$botModule->sendCommandListFromArray($data, " используйте:", array(
				'!профессия <номер> - Информация о профессии'
			));
		}
	}

	function economy_jobcontrol_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$date = time(); // Переменная времени

		// Переменные для редактирования сообщения
		$keyboard_buttons = array();
		$message = "";

		// Переменная тестирования пользователя
		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$command = bot_get_array_value($payload, 2, 0);

		switch ($command) {
			case 0:
			$job_index = bot_get_array_value($payload, 3, 0);

			$jobs = Economy\Job::getJobArray();
			$job_id = Economy\Job::getIDByIndex($job_index);

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
					$user_economy = $economy->getUser($data->object->user_id);
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
				else
					$item_dependencies_text = "Ничего";
				$salary = Economy\Main::getFormatedMoney($jobs[$job_id]["salary"]);
				$message = "%appeal%,\n✏Название: {$jobs[$job_id]["name"]}\n💰Зарплата: \${$salary}\n📅Время отдыха: {$left_time_text}\n💼Необходимо: {$item_dependencies_text}";
				$jobs_count = count($jobs);
				if($jobs_count > 0){
					if($job_index != 0){
						$previous_index = $job_index - 1;
						$emoji_str = bot_int_to_emoji_str($job_index);
						$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_jobcontrol', $testing_user_id, 0, $previous_index), 'secondary');
					}
					if($job_index != ($jobs_count - 1)){
						$next_index = $job_index + 1;
						$emoji_str = bot_int_to_emoji_str($next_index + 1);
						$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_jobcontrol', $testing_user_id, 0, $next_index), 'secondary');
					}
				}
				else
					$controlButtons = array();
				$keyboard_buttons = array(
					array(
						vk_callback_button("Устроиться", array('economy_jobcontrol', $testing_user_id, 1, $job_index), "positive")
					),
					$controlButtons,
					array(
						vk_callback_button("⬅ Назад", array('economy_work', $testing_user_id), "negative")
					)
				);
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			}
			break;

			case 1:
			$job_index = bot_get_array_value($payload, 3, -1);

			$jobs = Economy\Job::getJobArray();
			$job_id = Economy\Job::getIDByIndex($job_index);

			if($job_id !== false){
				$economy = new Economy\Main($db);
				$user_economy = $economy->getUser($data->object->user_id);

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
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы сильно устали и не можете поменять профессию! Приходите через {$left_time_text}");
						return;
					}
				}

				$item_dependencies = Economy\Job::getJobArray()[$job_id]["item_dependencies"];
				for($i = 0; $i < count($item_dependencies); $i++){
					$item = Economy\Item::getItemObjectFromString($item_dependencies[$i]);
					if($user_economy->checkItem($item->type, $item->id) === false){
						$job_name = Economy\Job::getNameByID($job_id);
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы не можете устроиться на профессию {$job_name}.");
						return;
					}
				}

				$user_economy->setJob($job_id);
				$user_economy->setMeta("last_working_time", 0);
				$job_name = Economy\Job::getNameByID($job_id);
				$keyboard_buttons = array(
					array(
						vk_callback_button("Работа", array("economy_work"), "positive")
					),
					array(
						vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
						vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
					)
				);
				$message = "%appeal%, Вы устроились на работу {$job_name}.";
				$db->save();
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			}
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			break;
		}

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->user_id);
		$keyboard = vk_keyboard_inline($keyboard_buttons);
		$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
	}

	function economy_shop_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		// Переменные для редактирования сообщения
		$keyboard_buttons = array();
		$message = "";

		// Переменная тестирования пользователя
		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$command = bot_get_array_value($payload, 2, 0);

		switch ($command) {
			case 0:
			$section_number = bot_get_array_value($payload, 3, 0);

			$config_sections = Economy\Item::getShopSectionsArray();

			$sections = array();
			// Извлекаем секции магазина из конфига
			foreach ($config_sections as $key => $value) {
				$sections[] = $key;
			}
			// Дополняем секции системными
			$sections[] = 'e';

			$section_code = $sections[$section_number];
			if(is_numeric($section_code)){
				if(array_key_exists($section_code, $config_sections)){
					$section_name = $config_sections[$section_code]["name"];
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
					return;
				}
			}
			elseif($section_code == 'e')
				$section_name = "Бизнес";
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
				return;
			}

			$controlButtons = array();
			$sections_count = count($sections);
			if($sections_count > 0){
				if($section_number != 0){
					$previous_list = $section_number - 1;
					$emoji_str = bot_int_to_emoji_str($section_number);
					$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_shop', $testing_user_id, 0, $previous_list), 'secondary');
				}
				if($section_number != ($sections_count - 1)){
					$next_list = $section_number + 1;
					$emoji_str = bot_int_to_emoji_str($section_number + 2);
					$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_shop', $testing_user_id, 0, $next_list), 'secondary');
				}
			}

			$message = "%appeal%, Магазин.\n\n📝Раздел: {$section_name}";
			$keyboard_buttons = array(
				array(
					vk_callback_button("Каталог", array('economy_shop', $testing_user_id, 1, $section_code), 'positive')
				),
				$controlButtons,
				array(
					vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
					vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")  
				)
			);
			break;

			case 1:
			$section_code = bot_get_array_value($payload, 3, 0);
			$operation_code = bot_get_array_value($payload, 4, 0);
			$product_code = bot_get_array_value($payload, 5, 0);

			$economy = new Economy\Main($db);
			$user_economy = $economy->getUser($data->object->user_id);

			if(is_numeric($section_code)){
				$config_sections = Economy\Item::getShopSectionsArray();
				if(array_key_exists($section_code, $config_sections)){
					$section = $config_sections[$section_code];

					$all_items = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("items");
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

					if(!array_key_exists($product_code, $items_for_buy)){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
						return;
					}
					$item_info = Economy\Item::getItemInfo($items_for_buy[$product_code]["type"], $items_for_buy[$product_code]["id"]);

					if($operation_code === 0){
						$formated_price = Economy\Main::getFormatedMoney($item_info->price);
						$formated_money = Economy\Main::getFormatedMoney($user_economy->getMoney());
						$message = "%appeal%, Магазин.\n\n✏Название: {$item_info->name}\n💲Стоимость: \${$formated_price}\n\n💳Ваш счёт: \${$formated_money}";

						$controlButtons = array();
						$items_for_buy_count = count($items_for_buy);
						if($items_for_buy_count > 0){
							if($product_code != 0){
								$previous_list = $product_code - 1;
								$emoji_str = bot_int_to_emoji_str($product_code);
								$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_shop', $testing_user_id, 1, $section_code, 0, $previous_list), 'secondary');
							}
							else{
								$emoji_str = bot_int_to_emoji_str($items_for_buy_count);
								$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_shop', $testing_user_id, 1, $section_code, 0, ($items_for_buy_count - 1)), 'secondary');
							}
							if($product_code != ($items_for_buy_count - 1)){
								$next_list = $product_code + 1;
								$emoji_str = bot_int_to_emoji_str($product_code + 2);
								$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_shop', $testing_user_id, 1, $section_code, 0, $next_list), 'secondary');
							}
							else{
								$emoji_str = bot_int_to_emoji_str(1);
								$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_shop', $testing_user_id, 1, $section_code, 0, 0), 'secondary');
							}
						}

						$keyboard_buttons = array();

						$user_item_info = $user_economy->checkItem($items_for_buy[$product_code]["type"], $items_for_buy[$product_code]["id"]);

						if($user_item_info === false || ($user_item_info !== false && $user_item_info->count < $item_info->max_count))
							$keyboard_buttons[] = array(vk_callback_button("Купить", array('economy_shop', $testing_user_id, 1, $section_code, 1, $product_code), 'positive'));
						$keyboard_buttons[] = $controlButtons;
						$keyboard_buttons[] = array(vk_callback_button("⬅ Назад", array('economy_shop', $testing_user_id, 0, $section_code), "negative")  );
					}
					elseif($operation_code === 1){
						$user_item_info = $user_economy->checkItem($items_for_buy[$product_code]["type"], $items_for_buy[$product_code]["id"]);
						if($user_item_info === false || ($user_item_info !== false && $user_item_info->count < $item_info->max_count)){
							if($user_economy->canChangeMoney(-$item_info->price)){
								if($user_economy->changeItem($items_for_buy[$product_code]["type"], $items_for_buy[$product_code]["id"], 1)){
									$user_economy->changeMoney(-$item_info->price);
									$db->save();
									$formated_money = Economy\Main::getFormatedMoney($user_economy->getMoney());
									$message = "%appeal%, ✅Вы успешко приобрели:\n{$item_info->name}.\n\n💳Ваш счёт: \${$formated_money}";
									$keyboard_buttons = array(
										array(
											vk_callback_button("Вернуться к Каталогу", array('economy_shop', $testing_user_id, 1, $section_code, 0, $product_code), 'positive')
										)
									);
								}
								else{
									bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Не удалось провести транзакцию.');
									return;
								}
							}
							else{
								bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ На вашем счёту недостаточно средств.');
								return;
							}
						}
						else{
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Невозможно купить ' . $item_info->name . '.');
							return;
						}

					}
					else{
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
						return;
					}
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
					return;
				}
			}
			elseif($section_code == 'e'){
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '❗ Данный раздел находится в разработке!');
				return;
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
				return;
			}
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			return;
			break;
		}

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->user_id);
		$keyboard = vk_keyboard_inline($keyboard_buttons);
		$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
	}

	function economy_buy($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);

		$argvt1 = bot_get_array_value($argv, 1);

		$sections = Economy\Item::getShopSectionsArray();

		$section_id = -1;

		for($i = 0; $i < count($sections); $i++){
			if(mb_strtolower($sections[$i]["name"]) == mb_strtolower($argvt1)){
				$section_id = $i;
				break;
			}
		}

		if($section_id >= 0){
			$section = $sections[$section_id];
			switch ($section["type"]) {
				case 'item':
					$all_items = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("items");
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

					$argvt2 = intval(bot_get_array_value($argv, 2));
					if($argvt2 >= 1){
						$index = $argvt2-1;
						if(count($items_for_buy) <= $index){
							$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Товара под номером {$argvt2} не существует.", $data->object->from_id);
							return;
						}

						$item_for_buy = $items_for_buy[$index];

						if($user_economy->checkItem($item_for_buy["type"], $item_for_buy["id"]) !== false){
							$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас уже есть товар под номером {$argvt2}.", $data->object->from_id);
							return;
						}

						$price = $all_items[$item_for_buy["type"]][$item_for_buy["id"]]["price"];
						$transaction_result = $user_economy->changeMoney(-$price);

						if($transaction_result){
							$user_economy->changeItem($item_for_buy["type"], $item_for_buy["id"], 1);
							$db->save();
							$botModule->sendSilentMessage($data->object->peer_id, ", ✅Вы приобрели {$all_items[$item_for_buy["type"]][$item_for_buy["id"]]["name"]}.", $data->object->from_id);
						}
						else{
							$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас недостаточно ".mb_strtoupper($price["currency"])." на счету.", $data->object->from_id);
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
						$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
					}
					break;

				case 'enterprise':
					$economy = new Economy\Main($db);
					$user_economy = $economy->getUser($data->object->from_id);
					if($user_economy->checkItem("edu", "level_4") === false){
						$edu_name = Economy\Item::getItemName("edu", "level_4");
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы не можете купить бизнес. У вас должно быть {$edu_name}.", $data->object->from_id);
						return;
					}
					if(count($user_economy->getEnterprises()) >= 3){
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔вы уже имеете максимальное количество бизнесов (3).", $data->object->from_id);
						return;
					}
					$type_index = bot_get_array_value($argv, 2, 0);
					$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
					$types = array_keys($enterprise_types);
					if($type_index > 0 && count($types) >= $type_index){
						$enterprise_price = $enterprise_types[$types[$type_index-1]]["price"];
						if($user_economy->canChangeMoney(-$enterprise_price)){
							$enterpriseSystem = $economy->initEnterpriseSystem();
							$enterprise_id = $enterpriseSystem->createEnterprise($types[$type_index-1], $data->object->from_id);
							if($enterprise_id === false){
								$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Не удалось купить бизнес.", $data->object->from_id);
								return;
							}
							$user_economy->addEnterprise($enterprise_id);
							$user_economy->changeMoney(-$enterprise_price);
							$db->save();
							$botModule->sendSilentMessage($data->object->peer_id, ", ✅Бизнес успешно куплен. Его ID: {$enterprise_id}.", $data->object->from_id);
						}
						else{
							$enterprise_price = Economy\Main::getFormatedMoney($enterprise_price);
							$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На вашем счету нет \${$enterprise_price} для покупки бизнеса.", $data->object->from_id);
						}
					}
					else{
						$msg = ", доступные типы бизнесов: ";
						for($i = 0; $i < count($types); $i++){
							$index = $i + 1;
							$price = Economy\Main::getFormatedMoney($enterprise_types[$types[$i]]["price"]);
							$msg .= "\n{$index}. {$enterprise_types[$types[$i]]["name"]} — \${$price}";
						}
						$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
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
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);

		$argvt1 = intval(bot_get_array_value($argv, 1, 0));
		$argvt2 = intval(bot_get_array_value($argv, 2, 1));

		if($argvt1 > 0){
			$economy = new Economy\Main($db);
			$user_economy = $economy->getUser($data->object->from_id);
			$user_items = $user_economy->getItems();

			// Скрываем предметы с истиным параметром hidden
			$items = array();
			for($i = 0; $i < count($user_items); $i++){
				if(!Economy\Item::isHidden($user_items[$i]->type, $user_items[$i]->id))
					$items[] = $user_items[$i];
			}

			$index = $argvt1 - 1;

			if(count($items) < $argvt1){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Собственности под номером {$argvt1} у вас нет.", $data->object->from_id);
				return;
			}

			if($argvt2 <= 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Количество не может быть отрицательным числом или быть равным 0.", $data->object->from_id);
				return;
			}

			$selling_item_info = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

			if(!$selling_item_info->can_sell){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Собственность \"{$selling_item_info->name}\" невозможно продать.", $data->object->from_id);
				return;
			}

			if($user_economy->changeItem($items[$index]->type, $items[$index]->id, -$argvt2)){
				$value = $selling_item_info->price * 0.7 * $argvt2;
				$user_economy->changeMoney($value); // Добавляем к счету пользователя 70% от начальной стоимости товара
				$db->save();
				$value = Economy\Main::getFormatedMoney($value);
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Собственность \"{$selling_item_info->name}\" продана в количестве {$argvt2} за \${$value}.", $data->object->from_id);
			}
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас в наличии только {$items[$index]->count} {$selling_item_info->name}.", $data->object->from_id);
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
		$argv = $finput->argv;
		$db = $finput->db;

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
			$list_number_from_word = intval(bot_get_array_value($argv, 1, 1));

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
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
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
			$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
		}
		else{
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет наград.", $data->object->from_id);
		}
	}

	function economy_myprops($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

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
			$argvt1 = bot_get_array_value($argv, 1, 1);
			if(is_numeric($argvt1)){
				$list_number_from_word = intval($argvt1);

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
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
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
				$keyboard = vk_keyboard_inline(array(array(vk_text_button("Купить", array("command" => "bot_runtc", "text_command" => "!купить"), "positive")),array(vk_text_button("Продать", array("command" => "bot_runtc", "text_command" => "!продать"), "negative")),array(vk_text_button("Подарить", array("command" => "bot_runtc", "text_command" => "Подарить"), "primary"))));
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
			}
			elseif(mb_strtolower($argvt1) == "инфа"){
				$argvt2 = intval(bot_get_array_value($argv, 2, 0));
				if($argvt2 <= 0){
					$botModule->sendSilentMessage($data->object->peer_id, ", используйте !имущество инфа <номер>.", $data->object->from_id);
					return;
				}
				if($argvt2 > $items_count){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет имущества под номером {$argvt2}.", $data->object->from_id);
					return;
				}
				$index = $argvt2-1;
				$item = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

				$buying_price = Economy\Main::getFormatedMoney($item->price);
				$selling_price = Economy\Main::getFormatedMoney($item->price*0.7);
				$can_buy = ($item->can_buy ? "Да ✅" : "Нет ⛔");
				$can_sell = ($item->can_sell ? "Да ✅" : "Нет ⛔");
				$msg = ", информация о имуществе:\n📝Название: {$item->name}\n🛒Можно купить: {$can_buy}\n💳Можно продать: {$can_sell}\n💰Цена: \${$buying_price}\n📈Цена продажи: \${$selling_price}";
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array("keyboard" => $keyboard));
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(array(vk_text_button("Купить", array("command" => "bot_runtc", "text_command" => "!купить"), "positive")),array(vk_text_button("Продать", array("command" => "bot_runtc", "text_command" => "!продать"), "negative")),array(vk_text_button("Подарить", array("command" => "bot_runtc", "text_command" => "Подарить"), "primary"))));
			$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет имущества.", $data->object->from_id, array("keyboard" => $keyboard));
		}
	}

	function economy_bank($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$time = time();

		$argvt1 = bot_get_array_value($argv, 1, "");

		if($argvt1 == "перевод"){
			$argvt2 = intval(bot_get_array_value($argv, 2, 0));
			$argvt3 = bot_get_array_value($argv, 3, "");

			if($argvt2 <= 0){
				$botModule->sendSilentMessage($data->object->peer_id, ", используйте \"!банк перевод <сумма> <пользователь>\".", $data->object->from_id);
				return;
			}

			if(array_key_exists(0, $data->object->fwd_messages)){
				$member_id = $data->object->fwd_messages[0]->from_id;
			} elseif(!is_null($argvt3) && bot_is_mention($argvt3)){
				$member_id = bot_get_id_from_mention($argvt3);
			} elseif(!is_null($argvt3) && is_numeric($argvt3)) {
				$member_id = intval($argvt3);
			} else {
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите пользователя.", $data->object->from_id);
				return;
			}

			if($member_id == $data->object->from_id){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Невозможно перевести деньги самому себе.", $data->object->from_id);
				return;
			}

			if($economy->checkUser($member_id)){
				$member_economy = $economy->getUser($member_id);

				if($user_economy->changeMoney(-$argvt2)){
					$member_economy->changeMoney($argvt2);
					$db->save();
					$money = Economy\Main::getFormatedMoney($argvt2);
					$botModule->sendSilentMessage($data->object->peer_id, ", ✅\${$money} успешно переведены на счет @id{$member_id} (пользователя).", $data->object->from_id);
				}
				else
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На счету недостаточно $.", $data->object->from_id);
			}
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У @id{$member_id} (пользователя) нет счета в беседе.", $data->object->from_id);
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
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$edu = Economy\Item::getItemListByType("edu");
		$edu_ids = array_keys($edu);
		$edu_data = array_values($edu);

		$argvt1 = intval(bot_get_array_value($argv, 1, 0));

		if($argvt1 > 0 && count($edu_ids) >= $argvt1){
			if($argvt1 == 1){
				if($user_economy->checkItem("edu", $edu_ids[$argvt1-1]) !== false){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас уже есть данное образование.", $data->object->from_id);
					return;
				}
				$edu_index = $argvt1 - 1;
			}
			else{
				$previous_level = $argvt1 - 2;
				if($user_economy->checkItem("edu", $edu_ids[$previous_level]) === false){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет уровня \"".$edu_data[$previous_level]["name"]."\".", $data->object->from_id);
					return;
				}
				if($user_economy->checkItem("edu", $edu_ids[$argvt1-1]) !== false){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас уже есть данное образование.", $data->object->from_id);
					return;
				}
				$edu_index = $argvt1 - 1;
			}

			$price = $edu_data[$edu_index]["price"];
			if($user_economy->changeMoney(-$price)){
				$user_economy->changeItem("edu", $edu_ids[$edu_index], 1);
				$db->save();
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Вы успешно получили образование уровня \"{$edu_data[$edu_index]["name"]}\".", $data->object->from_id);
			}
			else
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На счету недостаточно $.", $data->object->from_id);
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
			$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Образование", array("economy_education", $data->object->from_id), "positive"))));
			$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id, array('keyboard' => $keyboard));
		}
	}

	function economy_education_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		// Переменные для редактирования сообщения
		$keyboard_buttons = array();
		$message = "";

		// Переменная тестирования пользователя
		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$command = bot_get_array_value($payload, 2, 0);

		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->user_id);

		$edu = Economy\Item::getItemListByType("edu");

		switch ($command) {
			case 0:
			foreach ($edu as $key => $value) {
				if($user_economy->checkItem("edu", $key) === false){
					$edu_data = $value;
					break;
				}
			}
			if(isset($edu_data)){
				$keyboard_buttons = array(
					array(
						vk_callback_button("Получить", array("economy_education", $testing_user_id, 1), 'positive')
					),
					array(
						vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
						vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
					)
				);
				$formated_price = Economy\Main::getFormatedMoney($edu_data["price"]);
				$formated_money = Economy\Main::getFormatedMoney($user_economy->getMoney());
				$message = "%appeal%,\n📝Название: {$edu_data["name"]}\n💰Стоимость: \${$formated_price}\n\n💳Ваш счёт: \${$formated_money}";
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы имеете максимальное образование!");
				return;
			}
			break;

			case 1:
			foreach ($edu as $key => $value) {
				if($user_economy->checkItem("edu", $key) === false){
					$edu_id = $key;
					break;
				}
			}
			if(isset($edu_id)){
				$price = $edu[$edu_id]["price"];
				if($user_economy->changeMoney(-$price)){
					$user_economy->changeItem("edu", $edu_id, 1);
					$db->save();
					$keyboard_buttons = array(
						array(
							vk_callback_button("Вернуться", array('economy_education', $testing_user_id), "positive")
						),
						array(
							vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
							vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), "negative")
						)
					);
					$message = "%appeal%, ✅Вы успешно получили образование уровня \"{$edu[$edu_id]["name"]}\".";
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ На счету недостаточно $!");
				return;
				}
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вы имеете максимальное образование!");
				return;
			}
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
			break;
		}

		$messagesModule = new Bot\Messages($db);
		$messagesModule->setAppealID($data->object->user_id);
		$keyboard = vk_keyboard_inline($keyboard_buttons);
		$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
	}

	function economy_company($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->from_id);

		$command = mb_strtolower(bot_get_array_value($argv, 1, ""));

		if($command == "выбрать"){
			$argvt = bot_get_array_value($argv, 2, "");
			if($argvt == "0"){
				$user_economy->deleteMeta("selected_enterprise_index");
				$db->save();
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Информация о выбранном бизнесе очищена.", $data->object->from_id);
			}
			elseif($argvt == ""){
				$enterpriseSystem = $economy->initEnterpriseSystem();
				$user_enterprises = $user_economy->getEnterprises();
				$enterprises = array();
				foreach ($user_enterprises as $id) {
					$enterprises[] = $db->getValue(array("economy", "enterprises", $id));
				}
				if(count($enterprises) == 0){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет ни одного бизнеса.", $data->object->from_id);
					return;
				}
				$msg = ", Используйте:\n• !бизнес выбрать <номер> - Выбрать бизнес\n• !бизнес выбрать 0 - Убрать выбранный бизнес\n\nСписок ваших бизнесов:";
				$selected_enterprise_index = $user_economy->getMeta("selected_enterprise_index", 0) - 1;
				for($i = 0; $i < count($enterprises); $i++){
					$j = $i + 1;
					if($i == $selected_enterprise_index){
						$msg .= "\n➡{$j}. {$enterprises[$i]["name"]}";
					}
					else{
						$msg .= "\n{$j}. {$enterprises[$i]["name"]}";
					}
				}
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
			}
			elseif(is_numeric($argvt)){
				$index = intval($argvt);
				$user_enterprises = $user_economy->getEnterprises();
				if($index > 0 && count($user_enterprises) >= $index){
					$enterpriseSystem = $economy->initEnterpriseSystem();
					$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);
					$user_economy->setMeta("selected_enterprise_index", $index);
					$db->save();
					$botModule->sendSilentMessage($data->object->peer_id, ", ✅Выбран бизнес под названием \"{$enterprise["name"]}\".", $data->object->from_id);
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Бизнеса под номером {$index} не существует.", $data->object->from_id);
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
				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$type = $enterprise_types[$enterprise["type"]]["name"];
				$capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
				$msg = ", информация о бизнесе:\n📎ID: {$enterprise["id"]}\n📝Название: {$enterprise["name"]}\n🔒Тип: {$type}\n💰Бюджет: \${$capital}\n👥Рабочие: {$enterprise["involved_workers"]}/{$enterprise["workers"]}\n📊Опыт: {$enterprise["exp"]}\n📄Контракты: {$current_contracts_count}/{$enterprise["max_contracts"]}";
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
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

				$command = mb_strtolower(bot_get_array_value($argv, 2, ""));
				$value = round(abs(intval(bot_get_array_value($argv, 3, 0))), 2);

				if($command == "пополнить"){
					if($value == 0){
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите сумму операции.", $data->object->from_id);
						return;
					}

					if($user_economy->changeMoney(-$value)){
						$enterpriseSystem->changeEnterpriseCapital($enterprise, $value);
						$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
						$db->save();
						$value = Economy\Main::getFormatedMoney($value);
						$botModule->sendSilentMessage($data->object->peer_id, ", ✅\${$value} успешно переведены на счет бизнеса.", $data->object->from_id);
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На вашем счету недостаточно средств.", $data->object->from_id);
					}
				}
				elseif($command == "снять"){
					if($value == 0){
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите сумму операции.", $data->object->from_id);
						return;
					}

					if($enterpriseSystem->changeEnterpriseCapital($enterprise, -$value)){
						$user_economy->changeMoney($value);
						$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
						$db->save();
						$value = Economy\Main::getFormatedMoney($value);
						$botModule->sendSilentMessage($data->object->peer_id, ", ✅\${$value} успешно переведены на ваш счет.", $data->object->from_id);
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
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
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$name = mb_substr($data->object->text, 17);
				if($name == ""){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите название.", $data->object->from_id);
					return;
				}
				if(mb_strlen($name) > 20){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Название не может быть больше 20 символов.", $data->object->from_id);
					return;
				}
				$enterprise["name"] = $name;
				$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
				$db->save();
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Название \"{$name}\" установлено.", $data->object->from_id);
			}
			else{
				$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Выбрать", array('economy_company', $data->object->from_id, 2), 'primary'))));
				$botModule->sendSilentMessage($data->object->peer_id, ', ⛔Бизнес не выбран', $data->object->from_id, array('keyboard' => $keyboard));
			}
		}
		elseif($command == "контракты"){
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argvt = intval(bot_get_array_value($argv, 2, 0));

				if($argvt > 0 && count($contracts) >= $argvt){
					$index = $argvt-1;
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

					$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
				elseif($argvt == 0){
					$msg = ", список контрактов для вашего бизнеса:";
					for($i = 0; $i < count($contracts); $i++){
						$j = $i + 1;
						$contract = $contracts[$i];
						$cps = round(($contract["income"] - $contract["cost"]) / ($contract["duration"] / 60), 2);
						$msg .= "\n{$j}. ".$contract["name"]."  — \${$cps}/мин";
					}
					$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Контракта под номером {$argvt} не существует.", $data->object->from_id);
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
				$argvt = intval(bot_get_array_value($argv, 2, 0));

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
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
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
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
					return;
				}

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

				$argvt = intval(bot_get_array_value($argv, 2, 0));
				if($argvt <= 0 || $argvt > 2){
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						'!бизнес улучшить 1 - Увеличение числа рабочих',
						'!бизнес улучшить 2 - Увеличение слотов'
					));
					return;
				}

				if($argvt == 1){
					if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"])){
						$type = "workers_improvment";
						$contract = $improvment["workers"][$enterprise["improvment"]["workers"]];
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}
				else{
					if(array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"])){
						$type = "contracts_improvment";
						$contract = $improvment["contracts"][$enterprise["improvment"]["contracts"]];
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}

				$capital_after_start = $enterprise["capital"] - $contract["cost"];
				if($capital_after_start < 0){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					return;
				}
				$exp_after_start = $enterprise["exp"] - $contract["exp_required"];
				if($exp_after_start < 0){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Недостаточно опыта.", $data->object->from_id);
					return;
				}
				$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
				if($involved_workers_after_start > $enterprise["workers"]){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
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
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
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

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

				$argvt = intval(bot_get_array_value($argv, 2, 0));
				if($argvt <= 0 || $argvt > 2){
					$botModule->sendCommandListFromArray($data, ", используйте:", array(
						'!бизнес улучшение 1 - Описание улучшения рабочих',
						'!бизнес улучшение 2 - Описание улучшения слотов'
					));
					return;
				}

				if($argvt == 1){
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
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
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
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вами достигнут максимальный уровень.", $data->object->from_id);
						return;
					}
				}

				$cost = Economy\Main::getFormatedMoney($contract["cost"]);
				$msg = ", информация о улучшении:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n💰Стоимость: \${$cost}\n📊Необходимо Опыта: {$contract["exp_required"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n🔓Результат: {$improvment_text}";
				$botModule->sendSilentMessage($data->object->peer_id, $msg, $data->object->from_id);
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
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).", $data->object->from_id);
					return;
				}

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argvt = intval(bot_get_array_value($argv, 2, 0));
				if($argvt <= 0 || count($contracts) < $argvt){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Контракта под #{$argvt} не существует.", $data->object->from_id);
					return;
				}
				$contract = $contracts[$argvt-1];

				$capital_after_start = $enterprise["capital"] - $contract["cost"];
				if($capital_after_start < 0){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔На счету бизнеса недостаточно средств.", $data->object->from_id);
					return;
				}
				$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
				if($involved_workers_after_start > $enterprise["workers"]){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Не хватает рабочих для реализации этого контракта.", $data->object->from_id);
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
				$botModule->sendSilentMessage($data->object->peer_id, ", ✅Контракт \"{$contract["name"]}\" успешно подписан.", $data->object->from_id);
			}
			else{
				$botModule->sendCommandListFromArray($data, ", ⛔Бизнес не выбран. Используйте:", array(
					"!бизнес выбрать - Список бизнесов",
					"!бизнес выбрать <номер> - Выбирает управляемый бизнес"
				));
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(array(vk_callback_button("Меню Управления", array('economy_company', $data->object->from_id), 'primary'))));
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

	function economy_company_cb($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$payload = $finput->payload;
		$db = $finput->db;

		$testing_user_id = bot_get_array_value($payload, 1, $data->object->user_id);
		$code = bot_get_array_value($payload, 2, 0);

		if($testing_user_id !== $data->object->user_id){
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет доступа к этому меню!');
			return;
		}

		$messagesModule = new Bot\Messages($db);
		$economy = new Economy\Main($db);
		$user_economy = $economy->getUser($data->object->user_id);

		// Переменные для обновления сообщения
		$keyboard_buttons = array();
		$message = '';

		switch ($code) {
			case 0:
			$message = "%appeal%, Меню управления бизнесом.";
			$keyboard_buttons = array(
				array(
					vk_callback_button("Купить", array('economy_company', $testing_user_id, 1), "positive")
				),
				array(
					vk_callback_button("Выбрать", array('economy_company', $testing_user_id, 2), "primary"),
					vk_callback_button("Информация", array('economy_company', $testing_user_id, 3), "primary")
				),
				array(
					vk_callback_button("Контракты", array('economy_company', $testing_user_id, 4), "primary"),
					vk_callback_button("Очередь", array('economy_company', $testing_user_id, 5), "primary")
				),
				array(
					vk_callback_button("Бюджет", array('economy_company', $testing_user_id, 6), "primary"),
					vk_callback_button("Улучшение", array('economy_company', $testing_user_id, 7), "primary")
				),
				array(
					vk_callback_button("Меню", array('bot_menu', $testing_user_id), "secondary"),
					vk_callback_button("Закрыть", array('bot_menu', $testing_user_id, 0), 'negative')
				)
			);
			break;

			case 1:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '❗ Функция покупки временно недоступна!');
			return;
			break;

			case 2:
			$argvt = bot_get_array_value($payload, 3, 0);
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises = $user_economy->getEnterprises();
			if($argvt > 0){
				$index = intval($argvt);
				$selected_enterprise_index = $user_economy->getMeta("selected_enterprise_index", 0);
				if($index == $selected_enterprise_index){
					$user_economy->deleteMeta("selected_enterprise_index");
					$db->save();
				}
				else if(count($user_enterprises) >= $index){
					$user_economy->setMeta("selected_enterprise_index", $index);
					$db->save();
				}
				else{
					$index = intval($argvt);
					$n = $index + 1;
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ У вас нет бизнеса #{$n}!");
					return;
				}
			}

			$enterprises = array();
			foreach ($user_enterprises as $id) {
				$enterprises[] = $db->getValue(array("economy", "enterprises", $id));
			}
			if(count($enterprises) == 0){
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ У вас нет ни единого бизнеса! Купите его.');
				return;
			}
			$message = "%appeal%, Список ваших бизнесов:";
			$selected_enterprise_index = $user_economy->getMeta("selected_enterprise_index", -1);
			$enterprise_buttons = array();
			for($i = 0; $i < count($enterprises); $i++){
				$j = $i+1;
				if($j == $selected_enterprise_index){
					$message .= "\n➡{$j}. {$enterprises[$i]["name"]}";
					$enterprise_buttons[] = vk_callback_button(bot_int_to_emoji_str($j), array('economy_company', $testing_user_id, 2, $j), "primary");
				}
				else{
					$message .= "\n{$j}. {$enterprises[$i]["name"]}";
					$enterprise_buttons[] = vk_callback_button(bot_int_to_emoji_str($j), array('economy_company', $testing_user_id, 2, $j), "secondary");
				}
			}
			$keyboard_buttons = array(
				$enterprise_buttons,
				array(
					vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative')
				)
			);
			break;

			case 3:
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$current_contracts_count = count($enterprise["contracts"]);
				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$type = $enterprise_types[$enterprise["type"]]["name"];
				$capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
				$message = "%appeal%, информация о бизнесе:\n📎ID: {$enterprise["id"]}\n📝Название: {$enterprise["name"]}\n🔒Тип: {$type}\n💰Бюджет: \${$capital}\n👥Рабочие: {$enterprise["involved_workers"]}/{$enterprise["workers"]}\n📊Опыт: {$enterprise["exp"]}\n📄Контракты: {$current_contracts_count}/{$enterprise["max_contracts"]}";
				$keyboard_buttons = array(array(vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative')));
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Бизнес не выбран!');
				return;
			}
			break;

			case 4:
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$contracts = $enterprise_types[$enterprise["type"]]["contracts"];

				$argvt1 = bot_get_array_value($payload, 3, 0);
				$argvt2 = bot_get_array_value($payload, 4, 0);

				if($argvt1 == 0){
					$elements = array(array());
					$current_element_index = 0;
					$message = "%appeal%, список контрактов для вашего бизнеса:";
					for($i = 0; $i < count($contracts); $i++){
						$j = $i + 1;
						$contract = $contracts[$i];
						$cps = round(($contract["income"] - $contract["cost"]) / ($contract["duration"] / 60), 2);
						$message .= "\n{$j}. ".$contract["name"]."  — \${$cps}/мин";
						if(count($elements[$current_element_index]) >= 5){
							$elements[] = array();
							$current_element_index++;
						}
						$elements[$current_element_index][] = vk_callback_button(bot_int_to_emoji_str($j), array('economy_company', $testing_user_id, 4, 1, $i), "secondary");
					}
					$elements[][] = vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative');
					$keyboard_buttons = $elements;
				}
				elseif($argvt1 == 1){
					if(count($contracts) >= $argvt2){
						$contract_index = $argvt2;
						$contract = $contracts[$contract_index];
						
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
						$capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
						$current_contracts_count = count($enterprise["contracts"]);
						$message = "%appeal%, информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$cost}\n📈Доход: \${$income}\n💰Чистый доход: \${$net_income}\n📊Получаемый опыт: {$contract["exp"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n\n💰Бюджет: \${$capital}\n👥Рабочие: {$enterprise["involved_workers"]}/{$enterprise["workers"]}\n📄Контракты: {$current_contracts_count}/{$enterprise["max_contracts"]}";

						$contracts_count = count($contracts);
						$controlButtons = array();
						if($contracts_count > 0){
							if($contract_index != 0){
								$previous_index = $contract_index - 1;
								$emoji_str = bot_int_to_emoji_str($contract_index);
								$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_company', $testing_user_id, 4, 1, $previous_index), 'secondary');
							}
							if($contract_index != ($contracts_count - 1)){
								$next_index = $contract_index + 1;
								$emoji_str = bot_int_to_emoji_str($next_index + 1);
								$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_company', $testing_user_id, 4, 1, $next_index), 'secondary');
							}
						}
						$keyboard_buttons = array(
							array(
								vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 4), 'negative'),
								vk_callback_button("Реализовать", array('economy_company', $testing_user_id, 4, 2, $contract_index), "positive")
							),
							$controlButtons
						);
					}
					else{
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Контракта под номером {$argvt} не существует.');
						return;
					}
				}
				elseif($argvt1 == 2){
					if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).");
						return;
					}

					if(!array_key_exists($argvt2, $contracts)){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Контракта под #{$argvt2} не существует.");
						return;
					}
					$contract_index = $argvt2;
					$contract = $contracts[$contract_index];

					$capital_after_start = $enterprise["capital"] - $contract["cost"];
					if($capital_after_start < 0){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ На счету бизнеса недостаточно средств.");
						return;
					}
					$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
					if($involved_workers_after_start > $enterprise["workers"]){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Не хватает рабочих для реализации этого контракта.");
						return;
					}
					$enterprise["capital"] = $capital_after_start;
					$enterprise["involved_workers"] = $involved_workers_after_start;
					$enterprise["contracts"][] = array (
						"type" => "contract",
						"started_by" => $data->object->user_id,
						"start_time" => time(),
						"contract_info" => $contract
					);
					//bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "✅ Контракт \"{$contract["name"]}\" успешно подписан.");
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
					$capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
					$current_contracts_count = count($enterprise["contracts"]);
					$message = "%appeal%, информация о контракте:\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n📉Стоимость: \${$cost}\n📈Доход: \${$income}\n💰Чистый доход: \${$net_income}\n📊Получаемый опыт: {$contract["exp"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n\n💰Бюджет: \${$capital}\n👥Рабочие: {$enterprise["involved_workers"]}/{$enterprise["workers"]}\n📄Контракты: {$current_contracts_count}/{$enterprise["max_contracts"]}";

					$contracts_count = count($contracts);
					$controlButtons = array();
					if($contracts_count > 0){
						if($contract_index != 0){
							$previous_index = $contract_index - 1;
							$emoji_str = bot_int_to_emoji_str($contract_index);
							$controlButtons[] = vk_callback_button("{$emoji_str} ⬅", array('economy_company', $testing_user_id, 4, 1, $previous_index), 'secondary');
						}
						if($contract_index != ($contracts_count - 1)){
							$next_index = $contract_index + 1;
							$emoji_str = bot_int_to_emoji_str($next_index + 1);
							$controlButtons[] = vk_callback_button("➡ {$emoji_str}", array('economy_company', $testing_user_id, 4, 1, $next_index), 'secondary');
						}
					}
					$keyboard_buttons = array(
						array(
							vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 4), 'negative'),
							vk_callback_button("Реализовать", array('economy_company', $testing_user_id, 4, 2, $contract_index), "positive")
						),
						$controlButtons
					);
					$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
					$db->save();
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
					return;
				}
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Бизнес не выбран!');
				return;
			}
			break;

			case 5:
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);
				$contracts = $enterprise["contracts"];

				$time = time();
				$message = "%appeal%, Активные контракты.";
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
						$message .= "\n{$j}. ".$contract["contract_info"]["name"]." ({$left_info})";
					}
					else
						$message .= "\n{$j}. Свободный слот";
				}
				$keyboard_buttons = array(
					array(
						vk_callback_button("🔄 Обновить", array('economy_company', $testing_user_id, 5), 'positive')
					),
					array(
						vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative')
					)
				);
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Бизнес не выбран!');
				return;
			}
			break;

			case 6:
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$argvt = bot_get_array_value($payload, 3, 0);
				if($argvt == 0){
					$message = "%appeal%, Выберите режим операции.";
					$keyboard_buttons = array(
						array(
							vk_callback_button("⬆ Пополнить", array('economy_company', $testing_user_id, 6, 1, 1), 'positive'),
							vk_callback_button("⬇ Снять", array('economy_company', $testing_user_id, 6, 1, 2), 'positive')
						),
						array(
							vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative')
						)
					);
				}
				elseif($argvt == 1 || $argvt == 2){
					$mode = bot_get_array_value($payload, 4, 0);
					$transaction = intval(bot_get_array_value($payload, 5, 0));

					if($argvt == 2){
						switch ($mode) {
							case 1:
							if($transaction <= 0){
								bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Неверная сумма транзакции.');
								return;
							}
							if($user_economy->changeMoney(-$transaction)){
								$enterpriseSystem->changeEnterpriseCapital($enterprise, $transaction);
								$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
								$db->save();
							}
							else{
								bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ На вашем счету недостаточно средств.');
								return;
							}
							break;

							case 2:
							if($transaction <= 0){
								bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Неверная сумма транзакции!');
								return;
							}
							if($enterpriseSystem->changeEnterpriseCapital($enterprise, -$transaction)){
								$user_economy->changeMoney($transaction);
								$enterpriseSystem->saveEnterprise($enterprise["id"], $enterprise);
								$db->save();
							}
							else{
								bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ На счету бизнеса недостаточно средств.');
								return;
							}
							break;

							default:
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
							return;
							break;
						}
					}

					switch ($mode) {
						case 1:
						$transaction_name = "⬆ Пополнить";
						break;

						case 2:
						$transaction_name = "⬇ Снять";
						break;
						
						default:
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
						return;
						break;
					}
					if($transaction < 0)
						$transaction = 0;

					$formated_capital = Economy\Main::getFormatedMoney($enterprise["capital"]);
					$formated_transaction = Economy\Main::getFormatedMoney($transaction);
					$formated_money = Economy\Main::getFormatedMoney($user_economy->getMoney());
					$message = "%appeal%, Информация о текущей транзакции:\n💳Ваш счёт: \${$formated_money}\n💰Бюджет бизнеса: \${$formated_capital}\n\n💲Сумма транзакции: \${$formated_transaction}";

					$keyboard_buttons = array(

						array(
							vk_callback_button("- 1К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction-1000), 'secondary'),
							vk_callback_button("+ 1К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction+1000), 'secondary')
						),
						array(
							vk_callback_button("- 10К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction-10000), 'secondary'),
							vk_callback_button("+ 10К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction+10000), 'secondary')
						),
						array(
							vk_callback_button("- 100К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction-100000), 'secondary'),
							vk_callback_button("+ 100К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction+100000), 'secondary')
						),
						array(
							vk_callback_button("- 500К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction-500000), 'secondary'),
							vk_callback_button("+ 500К", array('economy_company', $testing_user_id, 6, 1, $mode, $transaction+500000), 'secondary')
						),
						array(
							vk_callback_button($transaction_name, array('economy_company', $testing_user_id, 6, 2, $mode, $transaction), 'primary')
						),
						array(
							vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 6), 'negative')
						)
					);
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
					return;
				}
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Бизнес не выбран!');
				return;
			}
			break;

			case 7:
			$index = $user_economy->getMeta("selected_enterprise_index", 0);
			$user_enterprises = $user_economy->getEnterprises();
			$enterpriseSystem = $economy->initEnterpriseSystem();
			$user_enterprises_count = count($user_enterprises);
			if($index > 0 && $user_enterprises_count >= $index){
				$enterprise = $enterpriseSystem->getEnterprise($user_enterprises[$index-1]);

				$enterprise_types = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("enterprise_types");
				$improvment = $enterprise_types[$enterprise["type"]]["improvment"];

				$argvt1 = bot_get_array_value($payload, 3, 0);
				$argvt2 = bot_get_array_value($payload, 4, 0);
				if($argvt1 == 0){
					if($argvt2 == 0){
						$keyboard_buttons = array(
							array(
								vk_callback_button("Улучшение рабочих", array('economy_company', $testing_user_id, 7, 0, 1), "primary")
							),
							array(
								vk_callback_button("Улучшение слотов", array('economy_company', $testing_user_id, 7, 0, 2), "primary")
							),
							array(
								vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 0), 'negative')
							)
						);
						$message = "%appeal%, Улучшение бизнеса.\n📝Бизнес: {$enterprise["name"]}";
					}
					elseif($argvt2 == 1){
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

							$cost = Economy\Main::getFormatedMoney($contract["cost"]);
							$message = "%appeal%, Информация об улучшении.\n📝Бизнес: {$enterprise["name"]}\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n💰Стоимость: \${$cost}\n📊Необходимо Опыта: {$contract["exp_required"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n🔓Результат: {$improvment_text}";

							$keyboard_buttons = array(
								array(
									vk_callback_button("Выполнить улучшение", array('economy_company', $testing_user_id, 7, 1, 1), "positive")
								),
								array(
									vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 7), 'negative')
								)
							);

						}
						else{
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Вами достигнут максимальный уровень.');
							return;
						}
					}
					elseif($argvt2 == 2){
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

							$cost = Economy\Main::getFormatedMoney($contract["cost"]);
							$message = "%appeal%, Информация об улучшении.\n📝Бизнес: {$enterprise["name"]}\n📝Название: {$contract["name"]}\n📅Продолжительность: {$duration}\n💰Стоимость: \${$cost}\n📊Необходимо Опыта: {$contract["exp_required"]}\n👥Необходимо рабочих: {$contract["workers_required"]}\n🔓Результат: {$improvment_text}";

							$keyboard_buttons = array(
								array(
									vk_callback_button("Выполнить улучшение", array('economy_company', $testing_user_id, 7, 1, 2), "positive")
								),
								array(
									vk_callback_button("⬅ Назад", array('economy_company', $testing_user_id, 7), 'negative')
								)
							);
						}
						else{
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Вами достигнут максимальный уровень.');
							return;
						}
					}
					else{
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
						return;
					}
				}
				elseif($argvt1 == 1){
					if($argvt2 == 1 || $argvt2 == 2){
						$improvment_type = $argvt2;
					}
					else{
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
						return;
					}

					if(count($enterprise["contracts"]) >= $enterprise["max_contracts"]){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Нет свободных слотов (Лимит слотов: {$enterprise["max_contracts"]}).");
						return;
					}


					if($improvment_type == 1){
						if(array_key_exists($enterprise["improvment"]["workers"], $improvment["workers"])){
							$type = "workers_improvment";
							$contract = $improvment["workers"][$enterprise["improvment"]["workers"]];
						}
						else{
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вами достигнут максимальный уровень.");
							return;
						}
					}
					else{
						if(array_key_exists($enterprise["improvment"]["contracts"], $improvment["contracts"])){
							$type = "contracts_improvment";
							$contract = $improvment["contracts"][$enterprise["improvment"]["contracts"]];
						}
						else{
							bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Вами достигнут максимальный уровень.");
							return;
						}
					}

					$capital_after_start = $enterprise["capital"] - $contract["cost"];
					if($capital_after_start < 0){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ На счету бизнеса недостаточно средств.");
						return;
					}
					$exp_after_start = $enterprise["exp"] - $contract["exp_required"];
					if($exp_after_start < 0){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Недостаточно опыта.");
						return;
					}
					$involved_workers_after_start = $enterprise["involved_workers"] + $contract["workers_required"];
					if($involved_workers_after_start > $enterprise["workers"]){
						bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "⛔ Не хватает рабочих для реализации этого контракта.");
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
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, "✅ Контракт \"{$contract["name"]}\" успешно подписан.");
					return;
				}
				else{
					bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Internal error!');
					return;
				}
			}
			else{
				bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Бизнес не выбран!');
				return;
			}
			break;

			case 9:
			$message = "✅ Меню управления закрыто!";
			break;
			
			default:
			bot_show_snackbar($data->object->event_id, $data->object->user_id, $data->object->peer_id, '⛔ Неизвестная команда!');
			return;
			break;
		}

		$messagesModule->setAppealID($data->object->user_id);
		$keyboard = vk_keyboard_inline($keyboard_buttons);
		$messagesModule->editMessage($data->object->peer_id, $data->object->conversation_message_id, $message, array('keyboard' => $keyboard));
	}

	function economy_most_rich_users($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

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
				$items = Economy\EconomyConfigFile::getEconomyConfigFileDataFromSection("items");
				for($j = 0; $j < count($user_items); $j++){
					$item_info = Economy\Item::getItemInfo($user_items[$j]->type, $user_items[$j]->id);
					$capital += $item_info->price;
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

			vk_execute($botModule->makeExeAppealByID($data->object->from_id)."
				var rating = {$rating_json};
				var user_ids = rating@.user_id;
				var users = API.users.get({'user_ids':user_ids});
				var msg = appeal+', Список самых богатых людей в беседе по мнению Forbes:\\n';
				var i = 0; while(i < rating.length){
					msg = msg+(i+1)+'. @id'+users[i].id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') — \$'+rating[i].capital+'\\n';
					i = i + 1;
				}
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'disable_mentions':true});
				");

		}
		else{
			$botModule->sendSilentMessage($data->object->peer_id, ", ни один пользователь беседы не попал в этот список.", $data->object->from_id);
		}
	}

	function economy_give($finput){
		// Инициализация базовых переменных
		$data = $finput->data; 
		$argv = $finput->argv;
		$db = $finput->db;

		$botModule = new BotModule($db);

		$argvt1 = intval(bot_get_array_value($argv, 1, 0));
		$argvt2 = intval(bot_get_array_value($argv, 2, 0));
		$argvt3 = bot_get_array_value($argv, 3, "");
		if(array_key_exists(0, $data->object->fwd_messages)){
			$member_id = $data->object->fwd_messages[0]->from_id;
		} elseif(bot_is_mention($argvt3)){
			$member_id = bot_get_id_from_mention($argvt3);
		} elseif(is_numeric($argvt3)) {
			$member_id = intval($argvt3);
		} else{
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Имущество", array("command" => "bot_runtc", "text_command" => "!имущество"), "primary")
				)
			));
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'Подарить <номер> <количество> <пользователь> - Дарит пользователю подарок',
				'!имущество - Список доступного для подарка имущества'
			), $keyboard);
			return;
		}

		if($argvt1 > 0 && $argvt2 > 0){
			$economy = new Economy\Main($db);

			if($economy->checkUser($member_id))
				$member_economy = $economy->getUser($member_id);
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У @id{$member_id} (пользователя) нет счета в беседе.", $data->object->from_id);
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

			$index = $argvt1 - 1;

			if(count($items) < $argvt1){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Собственности под номером {$argvt1} у вас нет.", $data->object->from_id);
				return;
			}

			$giving_item_info = Economy\Item::getItemInfo($items[$index]->type, $items[$index]->id);

			if(!$giving_item_info->can_sell){
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Собственность \"{$giving_item_info->name}\" невозможно подарить.", $data->object->from_id);
				return;
			}

			if($user_economy->changeItem($items[$index]->type, $items[$index]->id, -$argvt2)){
				$member_economy->changeItem($items[$index]->type, $items[$index]->id, $argvt2);
				$db->save();
				vk_execute("
					var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'first_name_dat,last_name_dat,sex'});
					var member = users[0];
					var from = users[1];

					var msg = '';
					if(from.sex == 1){
						msg = '@id{$data->object->from_id} ('+from.first_name+' '+from.last_name+') подарила {$giving_item_info->name} x{$argvt2} @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+')';
					}
					else{
						msg = '@id{$data->object->from_id} ('+from.first_name+' '+from.last_name+') подарил {$giving_item_info->name} x{$argvt2} @id{$member_id} ('+member.first_name_dat+' '+member.last_name_dat+')';
					}
					API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
					");
			}
			else{
				$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет столько {$giving_item_info->name}.", $data->object->from_id);
			}
		}
		else{
			$keyboard = vk_keyboard_inline(array(
				array(
					vk_text_button("Имущество", array("command" => "bot_runtc", "text_command" => "!имущество"), "primary")
				)
			));
			$botModule->sendCommandListFromArray($data, ", используйте: ", array(
				'Подарить <номер> <количество> <пользователь> - Дарит пользователю подарок',
				'!имущество - Список доступного для подарка имущества'
			), $keyboard);
		}
	}

	class CasinoRouletteGame{
		const SPECIAL_BETS = array(
			'красное' => 'red', 'черное' => 'black', 'чёрное' => 'black', 'четное' => 'even', 'чётное' => 'even',
			'нечетное' => 'odd', 'нечётное' => 'odd', '1до18' => '1to18', '19до36' => '19to36', 'первая12' => "1st12",
			'вторая12' => '2nd12', 'третья12' => '3d12', '2к1р1' => '2to1v1', '2к1р2' => '2to1v2', '2к1р3' => '2to1v3'
		);
		const ROULETTE = array(
			'0;null;null;null;null;null', '32;19to36;even;red;3d12;2to1v2', '15;1to18;odd;black;2nd12;2to1v3', '19;19to36;odd;red;2nd12;2to1v1',
			'4;1to18;even;black;1st12;2to1v1', '21;19to36;odd;red;2nd12;2to1v3', '2;1to18;even;black;1st12;2to1v2', '25;19to36;odd;red;3d12;2to1v1',
			'17;1to18;odd;black;2nd12;2to1v2', '34;19to36;even;red;3d12;2to1v1', '6;1to18;even;black;1st12;2to1v3', '27;19to36;odd;red;3d12;2to1v3',
			'13;1to18;odd;black;2nd12;2to1v1', '36;19to36;even;red;3d12;2to1v3', '11;1to18;odd;black;1st12;2to1v2', '30;19to36;even;red;3d12;2to1v3',
			'8;1to18;even;black;1st12;2to1v2', '23;19to36;odd;red;2nd12;2to1v2', '10;1to18;even;black;1st12;2to1v1', '5;1to18;odd;red;1st12;2to1v2',
			'24;19to36;even;black;2nd12;2to1v3', '16;1to18;even;red;2nd12;2to1v1', '33;19to36;odd;black;3d12;2to1v3', '1;1to18;odd;red;1st12;2to1v1',
			'20;19to36;even;black;2nd12;2to1v2', '14;1to18;even;red;2nd12;2to1v2', '31;19to36;odd;black;3d12;2to1v1', '9;1to18;odd;red;1st12;2to1v3',
			'22;19to36;even;black;2nd12;2to1v1', '18;1to18;even;red;2nd12;2to1v3', '29;19to36;odd;black;3d12;2to1v2', '7;1to18;odd;red;1st12;2to1v1',
			'28;19to36;even;black;3d12;2to1v1', '12;1to18;even;red;1st12;2to1v3', '35;19to36;odd;black;3d12;2to1v2', '3;1to18;odd;red;1st12;2to1v3',
			'26;19to36;even;black;3d12;2to1v2'
		);
		const TABLE_ATTACH = "photo-161901831_457240724"; // Константа фотографии игрового стола
		//const TABLE_ATTACH = "photo-101206282_457239301"; // В релизе заменить на верхнюю

		private static function getFinalPayment($bet, $value){
			if(array_search($bet, array('red', 'black', 'even', 'odd', '1to18', '19to36')) !== false){
				return $value * 2;
			}
			elseif(array_search($bet, array('1st12', '2nd12', '3d12', '2to1v1', '2to1v2', '2to1v3')) !== false){
				return $value * 3;
			}
			else{
				return $value * 35;
			}
		}

		private static function doMoneyBack($economy, $session){
			if($session->id == "casino_roulette"){
				foreach ($session->object["bets"] as $bet) {
					$user = $economy->getUser($bet["user_id"]);
					$user->changeMoney($bet["value"]);
				}
			}
		}

		public static function bet($finput){
			// Инициализация базовых переменных
			$data = $finput->data; 
			$argv = $finput->argv;
			$db = $finput->db;

			$botModule = new BotModule($db);

			$chat_id = $data->object->peer_id - 2000000000;
			$session = GameController::getSession($chat_id);
			if($session !== false && $session->id == "casino_roulette"){
				$session_data = $session->object;
				$argvt1 = bot_get_array_value($argv, 1, "");
				$argvt2 = intval(bot_get_array_value($argv, 2, 0));

				if(array_key_exists("id{$data->object->from_id}", $session_data["bets"])){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Вы уже сделали ставку.", $data->object->from_id);
					return;
				}

				if($argvt2 == 0 || $argvt1 == ''){
					$botModule->sendSilentMessage($data->object->peer_id, ", Используйте: [!ставка <ставка> <сумма>]\nЧтобы посмотреть все возможные ставки, используйте кнопку ниже.", $data->object->from_id);
					return;
				}
				elseif($argvt2 < 1000 || $argvt2 > 100000){
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите сумму ставки в правильном формате (от \$1,000 до \$100,000).", $data->object->from_id);
					return;
				}

				if(is_numeric($argvt1)){
					$bet_num = intval($argvt1);
					if($bet_num >= 0 && $bet_num <= 36){
						$bet = "{$bet_num}";
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите правильную ставку.\nЧтобы посмотреть все возможные ставки, используйте кнопку ниже.", $data->object->from_id);
						return;
					}
				}
				else{
					$bet_str = mb_strtolower($argvt1);
					if(array_key_exists($bet_str, self::SPECIAL_BETS)){
						$bet = self::SPECIAL_BETS[$bet_str];
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Укажите правильную ставку.\nЧтобы посмотреть все возможные ставки, используйте кнопку ниже.", $data->object->from_id);
						return;
					}
				}

				$economy = new Economy\Main($db); // Объект экономики
				$user_economy = $economy->getUser($data->object->from_id);
				if($user_economy->changeMoney(-$argvt2)){
					if(count($session_data["bets"]) == 0)
						$session_data["last_twist_time"] = time();
					$session_data["bets"]["id{$data->object->from_id}"] = array(
						'user_id' => $data->object->from_id,
						'bet' => $bet,
						'value' => $argvt2
					);
					if(GameController::setSession($chat_id, "casino_roulette", $session_data)){
						$db->save();
						$botModule->sendSilentMessage($data->object->peer_id, ", ✅Ставка успешно сделана. Используйте кнопку ниже, чтобы крутануть рулетку.", $data->object->from_id);
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, ", ⛔Произошла ошибка. Повторите попытку позже.", $data->object->from_id);
					}
				}
				else{
					$botModule->sendSilentMessage($data->object->peer_id, ", ⛔У вас нет указанной суммы денег.", $data->object->from_id);
				}
			}
			else
				$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Ошибка. Возможно сессия не запущена или запущена другая сессия.");
		}

		function main($finput){
			// Инициализация базовых переменных
			$data = $finput->data; 
			$argv = $finput->argv;
			$db = $finput->db;

			$botModule = new BotModule($db);
			$chat_id = $data->object->peer_id - 2000000000;

			$command = mb_strtolower(bot_get_array_value($argv, 1, ""));

			if($command == "старт"){
				$session = GameController::getSession($chat_id);
				if($session !== false){
					if($session->id == "casino_roulette")
						$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Сессия уже запущена.");
					else
						$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Запущена другая сессия.");
					return;
				}

				$session = array(
					'start_time' => time(),
					'last_twist_time' => 0,
					'bets' => array()
				);
				if(GameController::setSession($chat_id, "casino_roulette", $session)){
					$keyboard = vk_keyboard(false, array(
						array(
							vk_text_button('Крутить рулетку', array('command' => 'bot_runtc', 'text_command' => '!казино крутить'), 'positive'),
							vk_text_button('Стол', array('command' => 'bot_runtc', 'text_command' => '!казино стол'), 'secondary')
						),
						array(
							vk_text_button('Помощь', array('command' => 'bot_runtc', 'text_command' => '!казино помощь'), 'primary'),
							vk_text_button('Ставки', array('command' => 'bot_runtc', 'text_command' => '!казино ставки'), 'primary'),
						),
						array(
							vk_text_button('Остановить', array('command' => 'bot_runtc', 'text_command' => '!казино стоп'), 'negative')
						)
					));
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ✅Сессия запущена. Для справки используйте используйте кнопку Помощь.", null, array('keyboard' => $keyboard, 'attachment' => self::TABLE_ATTACH));
				}
				else
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Ошибка создания сессии.");
			}
			elseif($command == "стоп"){
				$session = GameController::getSession($chat_id);
				if($session === false){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Сессия не запущена.");
					return;
				}
				elseif($session->id != "casino_roulette"){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Запущена другая сессия.");
					return;
				}

				if(count($session->object["bets"]) != 0){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Невозможно остановить сессию, если игроки сделали ставки.");
					return;
				}

				if(GameController::deleteSession($chat_id, "casino_roulette")){
					$keyboard = vk_keyboard(true, array());
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ✅Сессия остановлена.", null, array('keyboard' => $keyboard));
				}
				else
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Ошибка остановки сессии.");
			}
			elseif($command == "крутить"){
				$session = GameController::getSession($chat_id);
				if($session === false){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Сессия не запущена.");
					return;
				}
				elseif($session->id != "casino_roulette"){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Запущена другая сессия.");
					return;
				}
				$time = time();
				$session_data = $session->object;

				if(count($session_data["bets"]) == 0){
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Еще ни один игрок не сделал ставку.");
					return;
				}

				$left_time_to_twist = $time - $session_data["last_twist_time"];
				if($left_time_to_twist >= 60){
					$economy = new Economy\Main($db); // Объект экономики

					$random_data = RandomOrg::generateIntegers(0, 36, 1);
					if($random_data === false || !array_key_exists('result', $random_data)){
						$keyboard = vk_keyboard(true, array());
						$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Произошла ошибка. Не удалось связаться с сервером RANDOM.ORG. Сессия остановлена.", null, array('keyboard' => $keyboard));
						self::doMoneyBack($economy, $session);
						$db->save();
						GameController::deleteSession($chat_id, "casino_roulette");
						return;
					}
					$cell = explode(';', self::ROULETTE[$random_data['result']["random"]["data"][0]]);

					$winners_array = array();
					foreach ($session_data["bets"] as $bet) {
						if(array_search($bet["bet"], $cell) !== false){
							$value = self::getFinalPayment($bet["bet"], $bet["value"]);
							$economy->getUser($bet["user_id"])->changeMoney($value);
							$winners_array[] = array(
								'user_id' => $bet["user_id"],
								'value' => Economy\Main::getFormatedMoney($value)
							);
						}
					}
					$db->save(); //  Сохраняем базу данных

					$attach = self::TABLE_ATTACH;

					if(count($winners_array) > 0){
						$winners_array_vk = json_encode($winners_array, JSON_UNESCAPED_UNICODE);
						vk_execute("
							var winners = {$winners_array_vk};
							var members = API.users.get({'user_ids':winners@.user_id});

							var msg = '[Рулетка] Выпало число {$cell[0]}. Следующие ставки выйграли:';
							var i = 0; while(i < members.length){
								msg = msg + '\\n✅@id'+members[i].id+' ('+members[i].first_name+' '+members[i].last_name+') — \$'+winners[i].value;
								i = i + 1;
							}

							API.messages.send({'peer_id':{$data->object->peer_id},'message':msg,'attachment':'{$attach}'});
							");
					}
					else{
						$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] Выпало число {$cell[0]}. Ни одна ставка не выйграла.", null, array('attachment' => $attach));
					}

					$session = array(
						'start_time' => time(),
						'last_twist_time' => 0,
						'bets' => array()
					);
					GameController::setSession($chat_id, "casino_roulette", $session);
				}
				else{
					$left_time = 60 - $left_time_to_twist;
					$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ⛔Крутануть рулетку можно будет через {$left_time} сек.");
				}
			}
			elseif($command == "помощь"){
				$msg = "[Рулетка] Рулетка — это популярная и всемирно известная азартная игра, суть которой заключается в угадывании числа. На вращающееся колесо с написанными на нем числами в диапазоне от 0 до 36 бросается шарик. После нескольких вращений вокруг колеса шарик останавливается в одном из секторов. Если игрок угадал число, то его ставка увеличивается в 35 раз. Ставить можно не только на число, но и на красное-черное, четное-нечетное, малое-большое, на дюжину, на колонку. Давайте разберемся, как происходит ставку у нас.\n\nИспользуйте следующую команду, чтобы сделать ставку: [!ставка <ставка> <сумма>]\n• Сумма - это количество денег, которые вы ставите. Вы можете поставить от \$1,000 до \$100,000.\n• Ставка - это непосредственно то место, куда вы ставите. Ознакомиться со списком возможных ставок можно с помощью кнопки Ставки.";
				$keyboard = vk_keyboard_inline(array(
					array(
						vk_text_button("Ставки", array('command' => 'bot_runtc', 'text_command' => '!казино ставки'), 'positive')
					)
				));
				$botModule->sendSilentMessage($data->object->peer_id, $msg, null, array('keyboard' => $keyboard));
			}
			elseif($command == "ставки"){
				$msg = "[Рулетка] Доступный следующие ставки:\n✅На число (0-36).\n&#12288;Выплата: 35:1\n&#12288;Например:\n&#12288;• [!ставка 12 1000]\n✅На красное-черное.\n&#12288;Выплата: 2:1\n&#12288;Например:\n&#12288;• [!ставка черное 1000]\n&#12288;• [!ставка красное 1000]\n✅На четное-нечетное.\n&#12288;Выплата: 2:1\n&#12288;Например:\n&#12288;• [!ставка четное 1000]\n&#12288;• [!ставка нечетное 1000]✅На малое-большое.\n&#12288;Выплата: 2:1\n&#12288;Например:\n&#12288;• [!ставка 1до18 1000]\n&#12288;• [!ставка 19до36 1000]\n✅На Дюжину (первая 12: 1-12, вторая 12: 13-14, третья 12: 25-36).\n&#12288;Выплата: 3:1\n&#12288;Например:\n&#12288;• [!ставка первая12 1000] \n&#12288;• [!ставка вторая12 1000]\n&#12288;• [!ставка третья12 1000]\n✅На Колонку (2к1р1: [1, 4, 7...], 2к1р2: [2, 5, 8...], 2к1р3: [3, 6, 9...]).\n&#12288;Выплата: 3:1\n&#12288;Например:\n&#12288;• [!ставка 2к1р1 1000] \n&#12288;• [!ставка 2к1р2 1000]\n&#12288;• [!ставка 2к1р3 1000]";
				$botModule->sendSilentMessage($data->object->peer_id, $msg, null, array('attachment' => self::TABLE_ATTACH));
			}
			elseif($command == "стол"){
				$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] Игровой стол.", null, array('attachment' => self::TABLE_ATTACH));
			}
			elseif($command == "кнопки"){
				$keyboard = vk_keyboard(false, array(
					array(
						vk_text_button('Крутить рулетку', array('command' => 'bot_runtc', 'text_command' => '!казино крутить'), 'positive'),
						vk_text_button('Стол', array('command' => 'bot_runtc', 'text_command' => '!казино стол'), 'secondary')
					),
					array(
						vk_text_button('Помощь', array('command' => 'bot_runtc', 'text_command' => '!казино помощь'), 'primary'),
						vk_text_button('Ставки', array('command' => 'bot_runtc', 'text_command' => '!казино ставки'), 'primary'),
					),
					array(
						vk_text_button('Остановить', array('command' => 'bot_runtc', 'text_command' => '!казино стоп'), 'negative')
					)
				));
				$botModule->sendSilentMessage($data->object->peer_id, "[Рулетка] ✅Кнопки отображены.", null, array('keyboard' => $keyboard));
			}
			else{
				$botModule->sendCommandListFromArray($data, ", используйте:", array(
					'!ставка - Сделать ставку',
					'!казино старт - Запустить сессию Рулетка',
					'!казино стоп - Остановить сессию Рулетка',
					'!казино помощь - Помощь в Рулетке',
					'!казино ставки - Возможные ставки',
					'!казино стол - Изображение игрового стола',
					'!казино кнопки - Отображает кнопки управления'
				));
			}
		}
	}
}

?>