<?php

///////////////////////////////////////////////////////////
/// API

function roleplay_api_act_with($db, $data, $command, $user_info = "", $params){
	// Переменные параметров РП действия
	if(array_key_exists("msgMale", $params) && gettype($params["msgMale"]) == "string")
		$msgMale = $params["msgMale"];
	else{
		$debug_backtrace = debug_backtrace();
		error_log("Invalid parameter msgMale in function {$debug_backtrace[1]["function"]} in {$debug_backtrace[1]["file"]} on line {$debug_backtrace[1]["line"]}");
		exit;
	}

	if(array_key_exists("msgFemale", $params) && gettype($params["msgFemale"]) == "string")
		$msgFemale = $params["msgFemale"];
	else{
		$debug_backtrace = debug_backtrace();
		error_log("Invalid parameter msgFemale msgFemale in function {$debug_backtrace[1]["function"]} in {$debug_backtrace[1]["file"]} on line {$debug_backtrace[1]["line"]}");
		exit;
	}

	if(array_key_exists("msgMyselfMale", $params) && gettype($params["msgMyselfMale"]) == "string")
		$msgMyselfMale = $params["msgMyselfMale"];
	else{
		$debug_backtrace = debug_backtrace();
		error_log("Invalid parameter msgMyselfMale in function {$debug_backtrace[1]["function"]} in {$debug_backtrace[1]["file"]} on line {$debug_backtrace[1]["line"]}");
		exit;
	}

	if(array_key_exists("msgMyselfFemale", $params) && gettype($params["msgMyselfFemale"]) == "string")
		$msgMyselfFemale = $params["msgMyselfFemale"];
	else{
		$debug_backtrace = debug_backtrace();
		error_log("Invalid parameter msgMyselfFemale in function {$debug_backtrace[1]["function"]} in {$debug_backtrace[1]["file"]} on line {$debug_backtrace[1]["line"]}");
		exit;
	}

	if(array_key_exists("msgToAll", $params) && gettype($params["msgToAll"]) == "array")
		$msgToAll = $params["msgToAll"];

	if(array_key_exists("sexOnly", $params) && gettype($params["sexOnly"]) == "integer")
		$sexOnly = $params["sexOnly"];
	else
		$sexOnly = 0;

	if(array_key_exists("sexErrorMsg", $params) && gettype($params["sexErrorMsg"]) == "string")
		$sexErrorMsg = $params["sexErrorMsg"];
	else
		$sexErrorMsg = "невозможно выполнить действие с указанным пользователем (пользователь не того пола).";


	// Логика РП действия
	$member_id = 0;

	$botModule = new botModule($db);
	if($user_info == "" && !array_key_exists(0, $data->object->fwd_messages)){
		$msg = ", используйте \"{$command} <имя/фамилия/id/упоминание/перес. сообщение>\".";
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%__appeal__%, используйте \"{$command} <имя/фамилия/id/упоминание/перес. сообщение>\"."), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "__appeal__");
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var __appeal__ = appeal;
			appeal = null;
			return API.messages.send({$request});");
		return false;
	}

	if(array_key_exists(0, $data->object->fwd_messages)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(!is_null($user_info) && bot_is_mention($user_info)){
		$member_id = bot_get_id_from_mention($user_info);
	} elseif(!is_null($user_info) && is_numeric($user_info)) {
		$member_id = intval($user_info);
	}

	if($member_id > 0){
		$messagesJson = json_encode(array('male' => $msgMale, 'female' => $msgFemale, 'myselfMale' => $msgMyselfMale, 'myselfFemale' => $msgMyselfFemale, 'sexErrorMsg' => $sexErrorMsg), JSON_UNESCAPED_UNICODE);
		$messagesJson = vk_parse_vars($messagesJson, array("FROM_USERNAME", "MEMBER_USERNAME", "MEMBER_USERNAME_GEN", "MEMBER_USERNAME_DAT", "MEMBER_USERNAME_ACC", "MEMBER_USERNAME_INS", "MEMBER_USERNAME_ABL", "appeal"));

		$res = json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_gen,first_name_dat,first_name_acc,first_name_ins,first_name_abl,last_name_gen,last_name_dat,last_name_acc,last_name_ins,last_name_abl'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];
			if({$member_id} == {$data->object->from_id}){ from_user = users[0]; }

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
				return {'result':false};
			}

			var FROM_USERNAME = '@'+from_user.screen_name+' ('+from_user.first_name.substr(0, 2)+'. '+from_user.last_name+')';

			var MEMBER_USERNAME = '@'+member.screen_name+' ('+member.first_name.substr(0, 2)+'. '+member.last_name+')';
			var MEMBER_USERNAME_GEN = '@'+member.screen_name+' ('+member.first_name_gen.substr(0, 2)+'. '+member.last_name_gen+')';
			var MEMBER_USERNAME_DAT = '@'+member.screen_name+' ('+member.first_name_dat.substr(0, 2)+'. '+member.last_name_dat+')';
			var MEMBER_USERNAME_ACC = '@'+member.screen_name+' ('+member.first_name_acc.substr(0, 2)+'. '+member.last_name_acc+')';
			var MEMBER_USERNAME_INS = '@'+member.screen_name+' ('+member.first_name_ins.substr(0, 2)+'. '+member.last_name_ins+')';
			var MEMBER_USERNAME_ABL = '@'+member.screen_name+' ('+member.first_name_abl.substr(0, 2)+'. '+member.last_name_abl+')';

			var messages = {$messagesJson};

			if({$sexOnly} != 0){
				if(member.sex != {$sexOnly}){
					API.messages.send({'peer_id':{$data->object->peer_id},'message':messages.sexErrorMsg});
					return {'result':false};
				}
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				if(member.sex == 1){
					msg = messages.myselfFemale;
				} else {
					msg = messages.myselfMale;
				}
			} else {
				if(from_user.sex == 1){
					msg = messages.female;
				} else {
					msg = messages.male;
				};
			};

			API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			return {'result':true,'member_id':member.id};
			"))->response;
		return (object) $res;

	} else {
		if(isset($msgToAll) && array_search(mb_strtolower($user_info), array('всем', 'всех', 'у всех', 'со всеми', 'на всех')) !== false){ // Выполнение действия над всеми
			$msgToAllMale = vk_parse_var($msgToAll["male"], "FROM_USERNAME");
			$msgToAllFemale = vk_parse_var($msgToAll["female"], "FROM_USERNAME");
			$res = json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
				var from_user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex,screen_name'})[0];

				var FROM_USERNAME = '@'+from_user.screen_name+' ('+from_user.first_name.substr(0, 2)+'. '+from_user.last_name+')';

				var msg = '';
				if(from_user.sex == 1){
					msg = \"{$msgToAllFemale}\";
				} else {
					msg = \"{$msgToAllMale}\";
				};

				API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
				return {'result':true,'member_id':0};
			"))->response;
			return (object) $res;
		}

		$messagesJson = json_encode(array('male' => $msgMale, 'female' => $msgFemale, 'myselfMale' => $msgMyselfMale, 'myselfFemale' => $msgMyselfFemale, 'sexErrorMsg' => $sexErrorMsg), JSON_UNESCAPED_UNICODE);
		$messagesJson = vk_parse_vars($messagesJson, array("FROM_USERNAME", "MEMBER_USERNAME", "MEMBER_USERNAME_GEN", "MEMBER_USERNAME_DAT", "MEMBER_USERNAME_ACC", "MEMBER_USERNAME_INS", "MEMBER_USERNAME_ABL", "appeal"));

		$user_info_words = explode(" ", $user_info);
		if(array_key_exists(0, $user_info_words)){
			$word1_array = preg_split('//u', strval($user_info_words[0]), null, PREG_SPLIT_NO_EMPTY);
			$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($user_info_words[0]), 1);
		}
		else
			$word1 = "";

		if(array_key_exists(1, $user_info_words)){
			$word2_array = preg_split('//u', strval($user_info_words[1]), null, PREG_SPLIT_NO_EMPTY);
			$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($user_info_words[1]), 1);
		}
		else
			$word2 = "";
		$res = json_decode(vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'sex,screen_name,first_name_gen,first_name_dat,first_name_acc,first_name_ins,first_name_abl,last_name_gen,last_name_dat,last_name_acc,last_name_ins,last_name_abl'});
			var from_user =  API.users.get({'user_ids':[{$data->object->from_id}],'fields':'sex,screen_name'})[0];
			var word1 = '{$word1}';
			var word2 = '{$word2}';

			var member_index = -1;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].first_name == word1){
					if(word2 == ''){
						member_index = i;
						i = members.profiles.length;
					} else if (members.profiles[i].last_name == word2){
						member_index = i;
						i = members.profiles.length;
					}
				} else if(members.profiles[i].last_name == word1) {
					member_index = i;
					i = members.profiles.length;
				}
				i = i + 1;
			};
			if(member_index == -1){
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
				return {'result':false};
			}

			var member = members.profiles[member_index];

			var FROM_USERNAME = '@'+from_user.screen_name+' ('+from_user.first_name.substr(0, 2)+'. '+from_user.last_name+')';

			var MEMBER_USERNAME = '@'+member.screen_name+' ('+member.first_name.substr(0, 2)+'. '+member.last_name+')';
			var MEMBER_USERNAME_GEN = '@'+member.screen_name+' ('+member.first_name_gen.substr(0, 2)+'. '+member.last_name_gen+')';
			var MEMBER_USERNAME_DAT = '@'+member.screen_name+' ('+member.first_name_dat.substr(0, 2)+'. '+member.last_name_dat+')';
			var MEMBER_USERNAME_ACC = '@'+member.screen_name+' ('+member.first_name_acc.substr(0, 2)+'. '+member.last_name_acc+')';
			var MEMBER_USERNAME_INS = '@'+member.screen_name+' ('+member.first_name_ins.substr(0, 2)+'. '+member.last_name_ins+')';
			var MEMBER_USERNAME_ABL = '@'+member.screen_name+' ('+member.first_name_abl.substr(0, 2)+'. '+member.last_name_abl+')';

			var messages = {$messagesJson};

			if({$sexOnly} != 0){
				if(member.sex != {$sexOnly}){
					API.messages.send({'peer_id':{$data->object->peer_id},'message':messages.sexErrorMsg});
					return {'result':false};
				}
			}

			var msg = '';

			if (member.id == {$data->object->from_id}){
				if(member.sex == 1){
					msg = messages.myselfFemale;
				} else {
					msg = messages.myselfMale;
				}
			} else {
				if(from_user.sex == 1){
					msg = messages.female;
				} else {
					msg = messages.male;
				};
			};

			API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			return {'result':true,'member_id':member.id};
			"))->response;
		return (object) $res;
	}
}

///////////////////////////////////////////////////////////
/// CMD init

function roleplay_cmdinit(&$event){
	$event->addTextCommand("!me", 'roleplay_me');
	$event->addTextCommand("!do", 'roleplay_do');
	$event->addTextCommand("!try", 'roleplay_try');
	$event->addTextCommand("!s", 'roleplay_shout');
	$event->addTextCommand("секс", 'roleplay_sex');
	$event->addTextCommand("обнять", 'roleplay_hug');
	$event->addTextCommand("уебать", 'roleplay_bump');
	$event->addTextCommand("обоссать", 'roleplay_pissof');
	$event->addTextCommand("поцеловать", 'roleplay_kiss');
	$event->addTextCommand("харкнуть", 'roleplay_hark');
	$event->addTextCommand("отсосать", 'roleplay_suck');
	$event->addTextCommand("отлизать", 'roleplay_lick');
	$event->addTextCommand("послать", 'roleplay_gofuck');
	$event->addTextCommand("кастрировать", 'roleplay_castrate');
	$event->addTextCommand("посадить", "roleplay_sit");
	$event->addTextCommand("пожать", "roleplay_shake");
}

///////////////////////////////////////////////////////////
/// Handlers

function roleplay_me($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	if(is_null($words[1])){
		$botModule = new botModule($db);
		$msg = ", используйте \\\"!me <действие>\\\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	} else {
		$act = mb_substr($data->object->text, 4, mb_strlen($data->object->text)-1);
		if(mb_substr($act, mb_strlen($act)-1, mb_strlen($act)-1) != "."){
			$act = $act . ".";
		}
		vk_execute("
			var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'screen_name'})[0];
			var msg = '@'+user.screen_name+' ('+user.first_name+' '+user.last_name+') '+'{$act}';
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function roleplay_try($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	if(is_null($words[1])){
		$botModule = new botModule($db);
		$msg = ", используйте \\\"!try <действие>\\\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	} else {
		$act = mb_substr($data->object->text, 5, mb_strlen($data->object->text)-1);
		if(mb_substr($act, mb_strlen($act)-1, mb_strlen($act)-1) != "."){
			$act = $act . ".";
		}
		$random_number = mt_rand(0, 65535);
		if($random_number % 2 == 1){
			$act = $act . " (Неудачно)";
		} else {
			$act = $act . " (Удачно)";
		}
		vk_execute("
			var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'screen_name'})[0];
			var msg = '@'+user.screen_name+' ('+user.first_name+' '+user.last_name+') '+'{$act}';
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function roleplay_do($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	if(is_null($words[1])){
		$botModule = new botModule($db);
		$msg = ", используйте \\\"!do <действие>\\\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	} else {
		$act = mb_substr($data->object->text, 4, mb_strlen($data->object->text)-1);
		$act = mb_strtoupper(mb_substr($act, 0, 1)) . mb_substr($act, 1, mb_strlen($act)-1);
		if(mb_substr($act, mb_strlen($act)-1, mb_strlen($act)-1) != "."){
			$act = $act . ".";
		}
		vk_execute("
			var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'screen_name'})[0];
			var msg = '{$act} (( @'+user.screen_name+' ('+user.first_name+' '+user.last_name+') ))';
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function roleplay_shout($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	if(is_null($words[1])){
		$botModule = new botModule($db);
		$msg = ", используйте \\\"!s <текст>\\\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	} else {
		$text = mb_substr($data->object->text, 3, mb_strlen($data->object->text)-1);
		$vowels_letters = array('а', 'о', 'и', 'е', 'ё', 'э', 'ы', 'у', 'ю', 'я'/*, 'a', 'e', 'i', 'o', 'u'*/);
		$new_text = "";
		$symbols = preg_split('//u', $text, null, PREG_SPLIT_NO_EMPTY);
		for($i = 0; $i < sizeof($symbols); $i++){
			$letter = "";
			for($j = 0; $j < sizeof($vowels_letters); $j++){
				if(mb_strtolower($symbols[$i]) == $vowels_letters[$j]){
					$letter = $symbols[$i];
					break;
				}
			}
			if($letter != ""){
				$random_number = mt_rand(3, 10);
				for($j = 0; $j < $random_number; $j++){
					$new_text = $new_text . $letter;
				}
			} else {
				$new_text = $new_text . $symbols[$i];
			}
		}
		$text = $new_text;
		if(mb_substr($text, mb_strlen($text)-1, mb_strlen($text)-1) != "."){
			$text = $text . ".";
		}
		vk_execute("
			var user = API.users.get({'user_ids':[{$data->object->from_id}],'fields':'screen_name,sex'})[0];
			var shout_text = 'крикнул';
			if(user.sex == 1){
				shout_text = 'крикнула';
			}
			var msg = '@'+user.screen_name+' ('+user.first_name+' '+user.last_name+') '+shout_text+': {$text}';
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function roleplay_sex($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% занялся сексом с %MEMBER_USERNAME_INS%.😍",
		"msgFemale" => "%FROM_USERNAME% занялась сексом с %MEMBER_USERNAME_INS%.😍",
		"msgMyselfMale" => "%FROM_USERNAME% подрочил.🤗",
		"msgMyselfFemale" => "%FROM_USERNAME% помастурбировала.🤗",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% занялся сексом со всем.😍",
			"female" => "%FROM_USERNAME% занялась сексом со всем.😍"
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Секс", $user_info, $params);
}

function roleplay_hug($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% обнял %MEMBER_USERNAME_ACC%.🤗",
		"msgFemale" => "%FROM_USERNAME% обняла %MEMBER_USERNAME_ACC%.🤗",
		"msgMyselfMale" => "%FROM_USERNAME% обнял сам себя.🤗",
		"msgMyselfFemale" => "%FROM_USERNAME% обняла сама себя.🤗",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% обнял всех.🤗",
			"female" => "%FROM_USERNAME% обняла всех.🤗"
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Обнять", $user_info, $params);
}

function roleplay_bump($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% уебал %MEMBER_USERNAME_DAT%.👊🏻",
		"msgFemale" => "%FROM_USERNAME% уебала %MEMBER_USERNAME_DAT%.👊🏻",
		"msgMyselfMale" => "%FROM_USERNAME% уебал сам себе.👊🏻",
		"msgMyselfFemale" => "%FROM_USERNAME% уебала сама себе.👊🏻",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% уебал всем.👊🏻",
			"female" => "%FROM_USERNAME% уебал всем.👊🏻"
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Уебать", $user_info, $params);
}

function roleplay_pissof($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% обоссал %MEMBER_USERNAME_GEN%.💦",
		"msgFemale" => "%FROM_USERNAME% обоссала %MEMBER_USERNAME_GEN%.💦",
		"msgMyselfMale" => "%FROM_USERNAME% обоссал сам себя.💦",
		"msgMyselfFemale" => "%FROM_USERNAME% обоссал сама себя.💦",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% обоссал всех.💦",
			"female" => "%FROM_USERNAME% обоссала всех.💦"
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Обоссать", $user_info, $params);
}

function roleplay_kiss($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% поцеловал %MEMBER_USERNAME_ACC%.😘",
		"msgFemale" => "%FROM_USERNAME% поцеловала %MEMBER_USERNAME_ACC%.😘",
		"msgMyselfMale" => "%FROM_USERNAME% поцеловал сам себя.😘",
		"msgMyselfFemale" => "%FROM_USERNAME% поцеловала сама себя.😘",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% поцеловал всех.😘",
			"female" => "%FROM_USERNAME% поцеловала всех.😘"
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Поцеловать", $user_info, $params);
}

function roleplay_hark($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% харкнул в %MEMBER_USERNAME_ACC%.",
		"msgFemale" => "%FROM_USERNAME% харкнула в %MEMBER_USERNAME_ACC%.",
		"msgMyselfMale" => "%FROM_USERNAME% харкнул сам на себя.",
		"msgMyselfFemale" => "%FROM_USERNAME% харкнула сама на себя.",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% харкнул на всех.",
			"female" => "%FROM_USERNAME% харкнула на всех."
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Харкнуть", $user_info, $params);
}

function roleplay_suck($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% отсосал у %MEMBER_USERNAME_GEN%.🍌",
		"msgFemale" => "%FROM_USERNAME% отсосала у %MEMBER_USERNAME_GEN%.🍌",
		"msgMyselfMale" => "%FROM_USERNAME% попытался отсосать у себя.😂",
		"msgMyselfFemale" => "%FROM_USERNAME% попыталась отсосать у себя.😂",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% отсосал у всех.🍌",
			"female" => "%FROM_USERNAME% отсосала у всех.🍌"
		),
		"sexOnly" => 2,
		"sexErrorMsg" => "%appeal%, нельзя отсосать у девочки.😂"
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Отсосать", $user_info, $params);
}

function roleplay_lick($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% отлизал у %MEMBER_USERNAME_GEN%.🍑",
		"msgFemale" => "%FROM_USERNAME% отлизала у %MEMBER_USERNAME_GEN%.🍑",
		"msgMyselfMale" => "%FROM_USERNAME% попытался отлизать у себя.😂",
		"msgMyselfFemale" => "%FROM_USERNAME% попыталась отлизать у себя.😂",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% отлизал у всех.🍑",
			"female" => "%FROM_USERNAME% отлизал у всех.🍑"
		),
		"sexOnly" => 1,
		"sexErrorMsg" => "%appeal%, нельзя отлизать у мальчика.😂"
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Отсосать", $user_info, $params);
}

function roleplay_gofuck($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% послал %MEMBER_USERNAME_ACC%.",
		"msgFemale" => "%FROM_USERNAME% послала %MEMBER_USERNAME_ACC%.",
		"msgMyselfMale" => "%FROM_USERNAME% послал сам себя.",
		"msgMyselfFemale" => "%FROM_USERNAME% послала сама себя.",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% послал всех.",
			"female" => "%FROM_USERNAME% послала всех."
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Послать", $user_info, $params);
}

function roleplay_castrate($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% кастрировал %MEMBER_USERNAME_ACC%.",
		"msgFemale" => "%FROM_USERNAME% кастрировала %MEMBER_USERNAME_ACC%.",
		"msgMyselfMale" => "%appeal%, нельзя кастрировать себя.😐",
		"msgMyselfFemale" => "%appeal%, нельзя кастрировать себя.😐",
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Кастрировать", $user_info, $params);
}

function roleplay_sit($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$params = array(
		"msgMale" => "%FROM_USERNAME% посадил на бутылку %MEMBER_USERNAME_ACC%.🍾",
		"msgFemale" => "%FROM_USERNAME% посадила на бутылку %MEMBER_USERNAME_ACC%.🍾",
		"msgMyselfMale" => "%FROM_USERNAME% сел на бутылку.🍾",
		"msgMyselfFemale" => "%FROM_USERNAME% села на бутылку.🍾",
		"msgToAll" => array(
			"male" => "%FROM_USERNAME% посадил на бутылку всех.",
			"female" => "%FROM_USERNAME% пасадила на бутылку всех."
		)
	);

	$user_info = bot_get_word_argv($words, 1, "");
	if($user_info != "" && bot_get_word_argv($words, 2, "") != "")
		$user_info = $user_info . " " . bot_get_word_argv($words, 2, "");

	roleplay_api_act_with($db, $data, "Посадить", $user_info, $params);
}

function roleplay_shake($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	switch (mb_strtolower($words[1])) {
		case 'руку':
			$params = array(
				"msgMale" => "%FROM_USERNAME% пожал руку %MEMBER_USERNAME_DAT%.",
				"msgFemale" => "%FROM_USERNAME% пожала руку %MEMBER_USERNAME_DAT%.",
				"msgMyselfMale" => "%FROM_USERNAME% настолько ЧСВ, что пожал руку сам с себе.",
				"msgMyselfFemale" => "%FROM_USERNAME% настолько ЧСВ, что пожала руку сама с себе.",
				"msgToAll" => array(
					"male" => "%FROM_USERNAME% пожал руку всем.",
					"female" => "%FROM_USERNAME% пожала руку всем."
				)
			);

			$user_info = bot_get_word_argv($words, 2, "");
			if($user_info != "" && bot_get_word_argv($words, 3, "") != "")
				$user_info = $user_info . " " . bot_get_word_argv($words, 3, "");

			roleplay_api_act_with($db, $data, "Пожать руку", $user_info, $params);
			break;
		
		default:
			$botModule = new botModule($db);
			$botModule->sendCommandListFromArray($data, ", используйте:", array(
				'Пожать руку <пользователь> - Жмет руку пользователю'
			));
			break;
	}
}

?>