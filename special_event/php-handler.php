<?php

require(__DIR__."/../bot/system/loader.php");

$databases = scandir(BOT_DBDIR);

$command = $argv[1];

if($command == "start"){
	foreach ($databases as $filename) {
		if($filename != "." && $filename != ".."){
			$db = new Database(BOT_DBDIR."/{$filename}");
			FunSpecialEvent::startEvent($db);
		}
	}
}
elseif($command == "stop"){
	foreach ($databases as $filename) {
		if($filename != "." && $filename != ".."){
			$db = new Database(BOT_DBDIR."/{$filename}");
			FunSpecialEvent::stopEvent($db);
		}
	}
}

?>