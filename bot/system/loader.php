<?php

// Базовые константы бота
define('BOT_DIR', dirname(__DIR__)); // Корневая директория бота
define('BOT_DATADIR', BOT_DIR."/data"); // Директория данных
define('BOT_DBDIR', BOT_DIR."/data/database"); // Директория базы данных
define('BOT_TMPDIR', dirname(BOT_DIR)."/tmp"); // Директория временных файлов
define('BOT_CONFIG_FILE_PATH', BOT_DATADIR."/config.json"); // Путь к главному файлу конфигураций бота

// UTF-8 как основная кодировка для mbstring
mb_internal_encoding("UTF-8");

// Составные модули бота
require_once(BOT_DIR."/system/vk.php"); // Модуль, отвечающий за все взаимодействия с VK API
require_once(BOT_DIR."/system/database.php"); // Модуль, отвечающий за взаимодействие основной базы данных бота
require_once(BOT_DIR."/system/bot.php"); // Модуль, отвечающий за некоторые API методы бота и основные функции
require_once(BOT_DIR."/system/goverment.php"); // Модуль, отвечающий за работу гос. устройства беседы
require_once(BOT_DIR."/system/economy.php"); // Модуль, отвечающий за систему Экономики
require_once(BOT_DIR."/system/fun.php"); // Модуль, отвечающий за развлечения
require_once(BOT_DIR."/system/roleplay.php"); // Модуль, отвечающий за Roleplay комнды
require_once(BOT_DIR."/system/manager.php"); // Модуль, отвечающий за управление беседой
require_once(BOT_DIR."/system/giphy.php"); // Модуль, отвечающий за функции взаимодействия с GIPHY API
require_once(BOT_DIR."/system/word_game.php"); // Модуль, отвечающий за игры Слова и Words
require_once(BOT_DIR."/system/riddle_game.php"); // Модуль, отвечающий за игру Загадки
require_once(BOT_DIR."/system/event.php"); // Модуль, отвечающий за обработку всех событий бота
require_once(BOT_DIR."/system/stats.php"); // Модуль, отвечающий за ведение статистики в беседах

?>