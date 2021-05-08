<?php

require(__DIR__."/../bot/system/bot.php");						// Подгружаем весь PHP код бота

$database_info = bot_getconfig("DATABASE");
$mongodb = new MongoDB\Driver\Manager("mongodb://{$database_info['HOST']}:{$database_info['PORT']}");

$query = new MongoDB\Driver\Query([], ['projection' => ['_id' => 1]]);
$cursor = $mongodb->executeQuery("{$database_info["NAME"]}.chats", $query);
$chats = $cursor->toArray();

$amount = count($chats);
$updated = 0;
print("\n");
foreach ($chats as $chat) {
	$query = new MongoDB\Driver\Query(['_id' => $chat->_id], ['projection' => ['_id' => 0]]);
	$cursor = $mongodb->executeQuery("{$database_info["NAME"]}.chats", $query);
	print("Подгружен документ {$chat->_id} коллекции chats");
	$extractor = new Database\CursorValueExtractor($cursor);
	$writeArray = [];
	update_document($extractor, $writeArray);
	if(count($writeArray) > 0){
		$bulk = new MongoDB\Driver\BulkWrite;
		$bulk->update(['_id' => $chat->_id], $writeArray);
		$writeResult = $mongodb->executeBulkWrite("{$database_info["NAME"]}.chats", $bulk);
		if($writeResult->getModifiedCount() > 0){
			print(" — Обновлено.\n");
			$updated++;
		}
		else
			print(" — Не обновлено.\n");
	}
	else
		print(" — Не обновлено.\n");
}

print("\nОбновлено {$updated} из {$amount} документов коллекции chats.\n\n");

// Вызывается для обновления каждого документа коллекции chats Базы данных
function update_document($extractor, &$writeArray){
	$list = $extractor->getValue("0.fun.marriages.list", false);
	if($list !== false){
		$count = count($list);
		$writeArray['$set']['fun.marriages.list_count'] = $count;
	}
}

?>