<?php

// Базовые константы бота
define('BOT_DIR', dirname(__DIR__)); // Корневая директория бота
define('BOT_DATADIR', BOT_DIR."/data"); // Директория данных
define('BOT_DBDIR', BOT_DIR."/data/database"); // Директория базы данных
define('BOT_TMPDIR', dirname(BOT_DIR)."/tmp"); // Директория временных файлов

// UTF-8 как основная кодировка для mbstring
mb_internal_encoding("UTF-8");

// Модуль для загрузки других модулей
require_once(BOT_DIR."/core/vk.php");
//require_once(BOT_DIR."/core/mlab.php");
require_once(BOT_DIR."/core/database.php"); // На замену mlab
require_once(BOT_DIR."/core/bot.php");
require_once(BOT_DIR."/core/goverment.php");
//require_once(BOT_DIR."/core/economy.php");
require_once(BOT_DIR."/core/fun.php");
require_once(BOT_DIR."/core/roleplay.php");
require_once(BOT_DIR."/core/manager.php");
require_once(BOT_DIR."/core/giphy.php");
require_once(BOT_DIR."/core/word_game.php");
require_once(BOT_DIR."/core/riddle_game.php");
require_once(BOT_DIR."/core/event.php");
require_once(BOT_DIR."/core/stats.php");

?>