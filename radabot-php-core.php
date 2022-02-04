<?php

require("radabot/php-core/bot.php"); // Подгружаем PHP код бота

set_time_limit(5); // Время жизни скрипта - 5 секунд

if(array_key_exists(1, $argv) && array_key_exists(2, $argv)){
	switch($argv[1]){
		case 'cmd':
			$data = json_decode($argv[2]);
			if($data !== false)
				bot_handle_event($data, True, False);
			break;

		case 'hndl':
			$data = json_decode($argv[2]);
			if($data !== false)
				bot_handle_event($data, False, True);
			break;
	}
}

?>