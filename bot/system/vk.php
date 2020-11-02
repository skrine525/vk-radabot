<?php

//// Медоты для работы с VK API

define("VK_API_VERSION", 5.84); // Константа версии VK API

function vk_call($method, $parametres){
	// Устанавливаем системные параметры
	$parametres['access_token'] = bot_getconfig('VK_GROUP_TOKEN');
	$parametres['v'] = VK_API_VERSION;

	$options = [
   		'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded', 
            'content' => http_build_query($parametres)
        ]
	];
	return file_get_contents("https://api.vk.com/method/{$method}", false, stream_context_create($options));
}

function vk_execute($code){
	return vk_call('execute', ['code' => $code]);
}

function vk_longpoll($data, $ts, $wait = 25){
	return file_get_contents("{$data->server}?act=a_check&key={$data->key}&ts={$ts}&wait={$wait}");
}

function vk_userexecute($code){
	$sys = [
		'access_token' => bot_getconfig("VK_USER_TOKEN"),
		'v' => VK_API_VERSION
	];

	$options = [
   		'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded', 
            'content' => http_build_query(['code' => $code]).'&'.http_build_query($sys)
        ]
	];
	return file_get_contents('https://api.vk.com/method/execute', false, stream_context_create($options));
}

/// Клавиатура

function vk_text_button($label, $payload, $color){
	$payload_json = "";
	if(gettype($payload) == "array")
		$payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
	return ['action' => ['type' => 'text', 'payload' => $payload_json, 'label' => $label], 'color' => $color];
}

function vk_callback_button($label, $payload, $color){
	$payload_json = "";
	if(gettype($payload) == "array")
		$payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
	return ['action' => ['type' => 'callback', 'payload' => $payload_json, 'label' => $label], 'color' => $color];
}

function vk_keyboard($one_time, $buttons = []){
	$keyboard_json = json_encode(['one_time' => $one_time, 'buttons' => $buttons], JSON_UNESCAPED_UNICODE);
	return $keyboard_json;
}

function vk_keyboard_inline($buttons = []){
	$keyboard_json = json_encode(['inline' => true, 'buttons' => $buttons], JSON_UNESCAPED_UNICODE);
	return $keyboard_json;
}

function vk_parse_var($data, $varname){
	return mb_ereg_replace("%{$varname}%", "\"+{$varname}+\"", $data); // Если будут проблемы, поменять на mb_eregi_replace
}

function vk_parse_vars($data, $varnames){
	if(gettype($varnames) != "array")
		return $data;
	for($i = 0; $i < count($varnames); $i++){
		$data = vk_parse_var($data, $varnames[$i]);
	}
	return $data;
}

/// Загрузка медии на сервер

function vk_uploadDocs($aPost, $url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $aPost);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec ($ch);
	curl_close($ch);
	return $res;
}

?>