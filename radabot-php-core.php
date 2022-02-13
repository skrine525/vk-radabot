<?php

require("radabot/php-core/bot.php"); // Подгружаем PHP код бота

set_time_limit(5); // Время жизни скрипта - 5 секунд

if(array_key_exists(1, $argv) && array_key_exists(2, $argv)){
	switch($argv[1]){
		case 'cmd':
			$data = json_decode($argv[2]);
			if($data !== false)
				bot_handle_event($data, true, false);
			break;

		case 'hndl':
			$data = json_decode($argv[2]);
			if($data !== false)
				bot_handle_event($data, false, true);
			break;
		
		case 'int':
			$data = (object) array(
				'type' => 'message_new',
				'object' => (object) array(
					'date' => time(),
					'from_id' => 2100000000,
					'id' => 0,
					'out' => 0,
					'peer_id' => 2100000000,
					'text' => '',
					'conversation_message_id' => 0,
					'fwd_messages' => array(),
					'important' => false,
					'random_id' => 0,
					'attachments' => array(),
					'is_hidden' => false
				)
			);
			bot_handle_event($data, true, true, true);
	}
}

?>