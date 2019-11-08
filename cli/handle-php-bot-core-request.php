<?php

require(__DIR__."/../bot/system/loader.php");

$data = json_decode(base64_decode($argv[1]));

if($data != false && gettype($data) == "array"){
	for($i = 0; $i < count($data); $i++){
		event_handle($data[$i]);
	}
}

?>