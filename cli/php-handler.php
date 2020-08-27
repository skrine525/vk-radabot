<?php

require("../bot/system/bot.php"); // Подгружаем PHP код бота

set_time_limit(5); // Время жизни скрипта - 5 секунд

$data = json_decode($argv[1]);
if($data !== false)
	bot_handle_event($data);

?>