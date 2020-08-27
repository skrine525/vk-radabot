<?php

require("../bot/system/bot.php"); // Подгружаем PHP код бота

function main(){
	set_time_limit(0);
	$longpoll = json_decode(vk_call("groups.getLongPollServer", array("group_id" => bot_getconfig("GROUP_ID"))))->response;
	$ts = $longpoll->ts;

	while(true){
		$data = vk_longpoll($longpoll, $ts);
		if($data != false){
			$data = json_decode($data);

			if(property_exists($data, 'failed')){
				if($data->failed == 2 || $data->failed == 3)
					$longpoll = json_decode(vk_call("groups.getLongPollServer", array("group_id" => bot_getconfig("GROUP_ID"))))->response;
					$ts = $longpoll->ts;
			}
			else{
				for($i = 0; $i < count($data->updates); $i++){
					bot_handle_event($data->updates[$i]);
				}
				$ts = $data->ts;
			}	
		}else{
			$longpoll = json_decode(vk_call("groups.getLongPollServer", array("group_id" => bot_getconfig("GROUP_ID"))))->response;
			$ts = $longpoll->ts;
		}
		unset($data);
	}
}

main();
?>