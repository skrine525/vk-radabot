<?php

function db_query_get($keys, $default = null){
	$query = (object) array(
		'keys' => $keys,
		'default' => $default
	);
	return $query;
}

function db_query_set($keys, $value){
	$query = (object) array(
		'keys' => $keys,
		'value' => $value
	);
	return $query;
}

function db_query_unset($keys){
	$query = (object) array(
		'keys' => $keys
	);
	return $query;
}

class Database{
	private $path;
	private $database;

	function __construct($path){
		$this->path = $path;
		$this->database = $this->readDatabaseFile();
	}

	private function readDatabaseFile(){
		if($this->isExists())
			return json_decode(file_get_contents($this->path), true);
		else
			return array();
	}

	private function writeDatabaseFile(){
		return boolval(file_put_contents($this->path, json_encode($this->database,  JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)));
	}

	public function save(){
		return $this->writeDatabaseFile();
	}

	public function getValue($keys, $default = null){
		if(!$this->isExists())
			return $default;

		$data = $this->database;
		foreach ($keys as $key) {
			if(gettype($data) == "array" && array_key_exists($key, $data))
				$data = $data[$key];
			else
				return $default;
		}
		return $data;
	}

	public function getValues(){
		if(!$this->isExists())
			return false;

		$args = func_get_args();
		$result = array();
		$database = $this->database;
		for($i = 0; $i < func_num_args(); $i++){
			$data = $database;
			$query = $args[$i];
			if(gettype($query) == "object" && property_exists($query, "keys") && property_exists($query, "default") && gettype($query->keys) == "array"){
				foreach ($query->keys as $key) {
					if(gettype($data) == "array" && array_key_exists($key, $data))
						$data = $data[$key];
					else{
						$data = $query->default;
						break;
					}
				}
				$result[] = $data;
			}
			else
				$result[] = null;
		}
		return $result;
	}

	public function setValue($keys, $value = null){
		$database = &$this->database;
		$requiredValue = &$database;
		foreach ($keys as $key) {
			if(gettype($requiredValue) == "array" && array_key_exists($key, $requiredValue))
				$requiredValue = &$requiredValue[$key];
			else{
				$requiredValue[$key] = array();
				$requiredValue = &$requiredValue[$key];
			}
		}
		$requiredValue = $value;
	}

	public function setValues(){
		$args = func_get_args();
		$result = array();
		$database = &$this->database;
		for($i = 0; $i < func_num_args(); $i++){
			$requiredValue = &$database;
			$query = $args[$i];
			if(gettype($query) == "object" && property_exists($query, "keys") && property_exists($query, "value") && gettype($query->keys) == "array"){
				foreach ($query->keys as $key) {
					if(gettype($requiredValue) == "array" && array_key_exists($key, $requiredValue))
						$requiredValue = &$requiredValue[$key];
					else{
						$requiredValue[$key] = array();
						$requiredValue = &$requiredValue[$key];
					}
				}
				$requiredValue = $query->value;
			}
		}
	}

	public function unsetValues(){
		if(!$this->isExists())
			return false;

		$args = func_get_args();
		$result = array();
		$database = &$this->database;
		for($i = 0; $i < func_num_args(); $i++){
			$requiredValue = &$database;
			$query = $args[$i];
			if(gettype($query) == "object" && property_exists($query, "keys") && gettype($query->keys) == "array"){
				foreach ($query->keys as $key) {
					if($key == end($query->keys)){
						if(array_key_exists($key, $requiredValue)){
							unset($requiredValue[$key]);
							$result[] = true;
							break;
						}
						else{
							$result[] = false;
							break;
						}
					}
					else{
						if(gettype($requiredValue[$key]) == "array" && array_key_exists($key, $requiredValue))
							$requiredValue = &$requiredValue[$key];
						else{
							$result[] = false;
							break;
						}
					}
				}
			}
		}
		return $result;
	}

	public function unsetValue($keys){
		if(!$this->isExists())
			return false;

		$database = &$this->database;
		$requiredValue = &$database;
		foreach ($keys as $key) {
			if($key == end($keys)){
				if(array_key_exists($key, $requiredValue)){
					unset($requiredValue[$key]);
					return true;
				}
				else
					return false;
			}
			else{
				if(gettype($requiredValue[$key]) == "array" && array_key_exists($key, $requiredValue))
					$requiredValue = &$requiredValue[$key];
				else
					return false;
			}
		}
	}

	public function isExists(){
		return file_exists($this->path);
	}
}

?>