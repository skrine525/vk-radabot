<?php

require(__DIR__."/../bot/system/database.php"); // Подгружаем модуль управления базой данных
define('BOT_DBDIR', __DIR__."/../bot/data/database"); // Директория базы данных

$scanned_directory = scandir(BOT_DBDIR); // Подгружаем файлы базы данных

$updated = 0;
$amount = count($scanned_directory)-2;

print("\n");
for($i = 2; $i < count($scanned_directory); $i++){
	$db = new Database(BOT_DBDIR."/{$scanned_directory[$i]}");
	if($db->isExists()){
		print("Подгружена База данных {$scanned_directory[$i]} ");
		update_file($db);
		if($db->save()){
			print("— Обновлено.\n");
			$updated++;
		}
		else
			print("— Ошибка.\n");
	}
}

print("\nОбновлено {$updated} из {$amount} файлов Базы данных.\n\n");

// Вызывается для обновления каждого файла Базы данных
function update_file($db){
	$gov = $db->getValue(array("goverment"), false);
	if($gov !== false){
		$db->unsetValue(array("goverment"));
		$db->setValue(array("government"), $gov);
	}
}

?>