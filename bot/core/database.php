<?php

function db_get($doc_id){
	$path = "../bot/data/database/{$doc_id}.json";
	if(db_exists($doc_id))
		return json_decode(file_get_contents($path), true);
	else
		return null;
}

function db_set($doc_id, $doc_data){
	if(!is_null($doc_id)){
		$path = "../bot/data/database/{$doc_id}.json";
		file_put_contents($path, json_encode($doc_data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
		return true;
	} else {
		return false;
	}
}

function db_exists($doc_id){
	$path = "../bot/data/database/{$doc_id}.json";
	return file_exists($path);
}

function db_web_handler($method){
	$data = json_decode(file_get_contents("php://input"));
	$database = $data->database;

	if($data->access_token != vars_get("SERVER_DEV_ACCESS_TOKEN"))
		return json_encode(array('result' => 'error', 'error_message' => 'Invalid access token'), JSON_UNESCAPED_UNICODE);

	if($method == "set"){
		if(is_null($data->data))
			return json_encode(array('result' => 'error', 'error_message' => 'Parameter data is null'), JSON_UNESCAPED_UNICODE);
		json_decode($data->data);
		if(json_last_error() != JSON_ERROR_NONE)
			return json_encode(array('result' => 'error', 'error_message' => 'Parameter data is invalid'), JSON_UNESCAPED_UNICODE);
		if (file_put_contents("../bot/data/database/{$database}.json", $data->data) != false)
			return json_encode(array('result' => 'ok'));
		else
			return json_encode(array('result' => 'error', 'error_message' => 'Failed to write data'), JSON_UNESCAPED_UNICODE);
	} elseif($method == "get"){
		if(db_exists($database)){
			$db_data = file_get_contents("../bot/data/database/{$database}.json");
			if ($db_data != false){
				return json_encode(array('result' => 'ok', 'data' => $db_data), JSON_UNESCAPED_UNICODE);
			} else {
				return json_encode(array('result' => 'error', 'error_message' => 'Failed to read data'), JSON_UNESCAPED_UNICODE);
			}
		} else {
			return json_encode(array('result' => 'error', 'error_message' => 'Database is not exists'), JSON_UNESCAPED_UNICODE);
		}
	} elseif($method == "del"){
		if(db_exists($database)){
			if(unlink("../bot/data/database/{$database}.json")){
				return json_encode(array('result' => 'ok'));
			} else {
				return json_encode(array('result' => 'error', 'error_message' => 'Failed to delete database'), JSON_UNESCAPED_UNICODE);
			}
		} else {
			return json_encode(array('result' => 'error', 'error_message' => 'Database is not exists', JSON_UNESCAPED_UNICODE));
		}
	} elseif($method == "list"){
		$files = scandir("../bot/data/database/");
		$databases = array();
		for($i = 0; $i < count($files); $i++){
			if(pathinfo("../bot/data/database/{$files[$i]}", PATHINFO_EXTENSION) == "json"){
				$databases[] = substr($files[$i], 0, strlen($files[$i])-5);
			}
		}
		return json_encode(array('result' => 'ok', 'databases_list' => $databases));
	} else {
		return json_encode(array('result' => 'error', 'error_message' => 'Invalid method'), JSON_UNESCAPED_UNICODE);
	}
}

?>