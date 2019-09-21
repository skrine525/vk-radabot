<?php

function amina_execute($code){
	$sys = array(
		'access_token' => config_get("AMINA_TOKEN"),
		'v' => VK_API_VERSION
	);

	$options = array(
   		'http' => array(  
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded', 
            'content' => http_build_query(array('code' => $code)).'&'.http_build_query($sys)
        )  
	);
	return file_get_contents('https://api.vk.com/method/execute', false, stream_context_create($options));
}

?>