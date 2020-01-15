<?php

function giphy_get_api_key(){
	return "4vfgNLnHpU6grBT8o6GKHXLRFdXghgDR";
}

function giphy_random($parametres){
	$sys = array(
		'api_key' => giphy_get_api_key()
	);
	$content = '?'.http_build_query($parametres).'&'.http_build_query($sys);

	return file_get_contents('https://api.giphy.com/v1/gifs/random'.$content);
}

function giphy_translate($parametres){
	$sys = array(
		'api_key' => giphy_get_api_key()
	);
	$content = '?'.http_build_query($parametres).'&'.http_build_query($sys);

	return file_get_contents('https://api.giphy.com/v1/gifs/translate'.$content);
}

function giphy_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$name = mb_substr($data->object->text, 7, mb_strlen($data->object->text));
	$gif = json_decode(giphy_translate(array('s' => $name)));
	$botModule = new BotModule($db);
	if (sizeof($gif->data) > 0){
		$response =  json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', загружаем гифку...'});
			API.messages.setActivity({'peer_id':{$data->object->peer_id},'type':'typing'});
			return API.docs.getMessagesUploadServer({'peer_id':{$data->object->peer_id},'type':'doc'});"));

		$path = BOT_TMPDIR."/gif".mt_rand(0, 65535).".gif";

		file_put_contents($path, file_get_contents($gif->data->images->fixed_width->url));
		$res = json_decode(vk_uploadDocs(array('file' => new CURLFile($path)), $response->response->upload_url));
		unlink($path);

		vk_execute("var doc = API.docs.save({'file':'{$res->file}'});
			API.messages.send({'peer_id':{$data->object->peer_id},'message':'Powered by GIPHY.@id{$data->object->from_id} (&#12288;)','attachment':'doc'+doc[0].owner_id+'_'+doc[0].id});
			");
	} else {
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ничего не найдено!😢'});
			");
	}
}

?>