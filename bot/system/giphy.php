<?php

// Инициализация команд
function giphy_initcmd($event){
	$event->addTextMessageCommand("!giphy", 'giphy_handler');
}

function giphy_random($parametres){
	$sys = array(
		'api_key' => bot_getconfig('GIPHY_API_TOKEN')
	);
	$content = '?'.http_build_query($parametres).'&'.http_build_query($sys);

	return file_get_contents('https://api.giphy.com/v1/gifs/random'.$content);
}

function giphy_translate($parametres){
	$sys = array(
		'api_key' => bot_getconfig('GIPHY_API_TOKEN')
	);
	$content = '?'.http_build_query($parametres).'&'.http_build_query($sys);

	return file_get_contents('https://api.giphy.com/v1/gifs/translate'.$content);
}

function giphy_handler($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$argv = $finput->argv;
	$db = $finput->db;

	$name = bot_gettext_by_argv($argv, 1);
	$gif = json_decode(giphy_translate(array('s' => $name)));
	$botModule = new BotModule($db);
	if (sizeof($gif->data) > 0){
		$response =  json_decode(vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', загружаем гифку...','disable_mentions':true});
			API.messages.setActivity({'peer_id':{$data->object->peer_id},'type':'typing'});
			return API.docs.getMessagesUploadServer({'peer_id':{$data->object->peer_id},'type':'doc'});"));

		$path = BOTPATH_TMP."/gif".mt_rand(0, 65535).".gif";

		file_put_contents($path, file_get_contents($gif->data->images->fixed_width->url));
		$res = json_decode(vk_uploadDocs(array('file' => new CURLFile($path)), $response->response->upload_url));
		unlink($path);

		vk_execute("var doc = API.docs.save({'file':'{$res->file}'});
			API.messages.send({'peer_id':{$data->object->peer_id},'message':'Powered by GIPHY.@id{$data->object->from_id} (&#12288;)','attachment':'doc'+doc[0].owner_id+'_'+doc[0].id,'disable_mentions':true});
			");
	} else {
		vk_execute($botModule->buildVKSciptAppealByID($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ничего не найдено!😢','disable_mentions':true});
			");
	}
}

?>