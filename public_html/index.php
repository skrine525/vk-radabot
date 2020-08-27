<?php

require('../vendor/autoload.php');

require("../bot/system/bot.php"); // Подгружаем PHP код бота

$app = new Silex\Application();
$app['debug'] = false;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Our web handlers

$app->post('/database/{method}', function($method) { // Обработчик для использование базы данных вне приложения
	return db_web_handler($method);
});

$app->get('/clear-php_logs', function() { // Очистка PHP логов бота
	file_put_contents("../public_html/php_logs.log", "");
	return 'ok';
});

$app->post('/bot', function() { // Обработчик логики бота
	$data = json_decode(file_get_contents('php://input'));

	if (!$data)
		return 'nioh';

	if ($data->secret !== bot_getconfig('VK_SECRET_KEY') && $data->type !== 'confirmation')
		return 'nioh';

	if ($data->type == "confirmation")
		return bot_getconfig('VK_CONFIRMATION_CODE');

	// Возвращаем серверу ok
	ob_end_clean();
	header("Connection: close");
	ignore_user_abort(); // optional
	ob_start();
	echo ('ok');
	$size = ob_get_length();
	header("Content-Length: $size");
	ob_end_flush(); // Strange behaviour, will not work
	flush();            // Unless both are called !
	session_write_close(); // Added a line suggested in the comment
	// Do processing here 

	bot_handle_event($data); // Основная функции обработки событий бота

	return ''; // Ненужная хрень, которая здесь для того, чтобы было меньше ошибок в логе
});

$app->error(function() { // Для неизвестных страниц
	return "error";
});

$app->run();

?>