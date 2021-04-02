<?php

require(__DIR__."/../bot/system/database.php");						// Подгружаем модуль управления базой данных
define('BOTPATH_DB', __DIR__."/../bot/data/database");				// Директория базы данных
mb_internal_encoding("UTF-8");										// UTF-8 как основная кодировка для mbstring

$scanned_directory = scandir(BOTPATH_DB);							// Подгружаем файлы базы данных

$updated = 0;
$amount = count($scanned_directory)-2;

print("\n");
for($i = 2; $i < count($scanned_directory); $i++){
	$db = new Database(BOTPATH_DB."/{$scanned_directory[$i]}");
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
	$all_stats = $db->getValue(['chat_stats', 'users'], []);
	foreach ($all_stats as $key => $value) {
		$member_id = intval(mb_substr($key, 2));
		if($member_id <= 0)
			$db->unsetValue(['chat_stats', 'users', $key]);
	}
}

?>