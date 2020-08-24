<?php

require(__DIR__."/../bot/system/loader.php"); // Подгружаем загрузчик PHP кода

set_time_limit(5); // Время жизни скрипта - 5 секунд

$data = json_decode($argv[1]);
if($data !== false)
	event_handle($data);

?>