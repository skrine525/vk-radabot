<?php

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
				if(array_key_exists($key, $requiredValue) && gettype($requiredValue[$key]) == "array")
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