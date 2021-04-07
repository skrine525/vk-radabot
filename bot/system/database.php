<?php

namespace Database{
	class Manager{
		private $mongo;
		private $db_name;
		private $id;

		private $exists;
		private $writing_force;

		function __construct($mongodb_uri, $db_name, $peer_id){
			$this->mongo = new \MongoDB\Driver\Manager($mongodb_uri);
			$this->db_name = $db_name;
			$this->writing_force = null;

			// Создание идентификатора
			if($peer_id > 2000000000){
				$id = $peer_id - 2000000000;
				$this->document_id = "chat{$id}";
			}
			else
				$this->document_id = "user{$peer_id}";

			// Проверка на существование
			$query = new \MongoDB\Driver\Query(['_id' => $this->document_id], ['projection' => ['_id' => 1]]);
			try{
				$cursor = $this->mongo->executeQuery("{$this->db_name}.chats", $query);
			}
			catch(Exception $e){
				die("Database Error: {$e->getMessage()}");
			}

			$documents = $cursor->toArray();
			if(count($documents) > 0)
				$this->exists = true;
			else
				$this->exists = false;
		}

		public function getDocumentID(){
			return $this->document_id;
		}

		public function isExists(){
			return $this->exists;
		}

		public function getMongoDB(){
			return $this->mongo;
		}

		public function setWritingForce($value){
			$this->writing_force = $value;
		}

		public function executeQuery(\MongoDB\Driver\Query $query, $collection = 'chats'){
			try{
				$cursor = $this->mongo->executeQuery("{$this->db_name}.{$collection}", $query);
				return $cursor;
			}
			catch(Exception $e){
				die("Database Error: {$e->getMessage()}");
			}
		}

		public function executeBulkWrite(\MongoDB\Driver\BulkWrite $bulk, $collection = 'chats'){
			try{
				$result = $this->mongo->executeBulkWrite("{$this->db_name}.{$collection}", $bulk);
				return $result;
			}
			catch(Exception $e){
				die("Database Error: {$e->getMessage()}");
			}
		}

		public function getValueLegacy($keys, $default = null){
			if(is_null($keys) || !$this->exists || !$this->document_id)
				return $default;

			if(count($keys) > 0)
				$query = new \MongoDB\Driver\Query(['_id' => $this->document_id], ['projection' => ["_id" => 0, implode(".", $keys) => 1]]);
			else
				$query = new \MongoDB\Driver\Query(['_id' => $this->document_id]);
			$cursor = $this->executeQuery($query);

			$extractor = new CursorValueExtractor($cursor);
			$path = [0];
			$path = array_merge($path, $keys);
			$value = $extractor->getValue($path, $default);
			return CursorValueExtractor::objectToArray($value);
		}

		public function setValueLegacy($keys, $value = null){
			if(is_null($keys) || (!$this->exists && !$this->writing_force) || !$this->document_id)
				return false;
			$keys_count = count($keys);
			if($keys_count > 0)
				$arr = [implode(".", $keys) => $value];
			else{
				if(gettype($value) == 'array')
					$arr = $value;
				else
					return false;
			}
			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $this->document_id], ['$set' => $arr], ['upsert' => true]);
			$result = $this->executeBulkWrite($bulk);
			return $result;
		}

		public function unsetValueLegacy($keys){
			if(is_null($keys) || !$this->exists || !$this->document_id)
				return false;
			$keys_count = count($keys);
			if($keys_count > 0)
				$arr = [implode(".", $keys) => 0];
			else
				return false;
			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->update(['_id' => $this->document_id], ['$unset' => $arr], ['upsert' => true]);
			$result = $this->executeBulkWrite($bulk);
			return $result->isAcknowledged();
		}
	}

	class CursorValueExtractor{
		private $cursor;
		private $values;
		private $values_count;

		function __construct(\MongoDB\Driver\Cursor $cursor){
			$this->cursor = $cursor;
			$this->values = $cursor->toArray();
			$this->values_count = count($this->values);
		}

		public function getValueCount(){
			return $this->values_count;
		}

		public function getValue($path, $default = null){
			$path_type = gettype($path);
			if($path_type == "string")
				$path = explode('.', $path);
			elseif($path_type != "array")
				return $default;

			$value = $this->values;
			foreach ($path as $key){
				$value_type = gettype($value);
				if($value_type == 'object' && property_exists($value, $key))
					$value = $value->$key;
				elseif($value_type == 'array' && array_key_exists($key, $value))
					$value = $value[$key];
				else{
					$value = $default;
					break;
				}
			}
			return $value;
		}

		public static function objectToArray($object){
			$object_type = gettype($object);
			if($object_type == 'object' || $object_type == 'array'){
				$arr = (array) $object;
				foreach ($arr as $key => $value) {
					$arr[$key] = self::objectToArray($value);
				}
				return $arr;
			}
			else
				return $object;
		}
	}
}

?>