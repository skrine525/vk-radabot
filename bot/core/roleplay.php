<?php

///////////////////////////////////////////////////////////
/// API

function rp_api_act_with($db, $data, $words, $msgMale, $msgFemale, $msgMyselfMale, $msgMyselfFemale, $sexOnly = 0, $sexErrorMsg = "невозможно выполнить действие с указанным пользователем (пользователь не того пола)."){
	$member_id = 0;

	$botModule = new botModule($db);
	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Обнять <имя/фамилия/id/упоминание/перес. сообщение>\".";
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%__appeal__%, используйте \"{$words[0]} <имя/фамилия/id/упоминание/перес. сообщение>\""), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "__appeal__");
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var __appeal__ = appeal;
			appeal = null;
			return API.messages.send({$request});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	$messagesJson = json_encode(array('male' => $msgMale, 'female' => $msgFemale, 'myselfMale' => $msgMyselfMale, 'myselfFemale' => $myselfFemale, 'sexErrorMsg' => $sexErrorMsg), JSON_UNESCAPED_UNICODE);

	$messagesJson = vk_parse_vars($messagesJson, array("FROM_USERNAME", "MEMBER_USERNAME", "MEMBER_USERNAME_GEN", "MEMBER_USERNAME_DAT", "MEMBER_USERNAME_ACC", "MEMBER_USERNAME_INS", "MEMBER_USERNAME_ABL", "appeal"));

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var FROM_USERNAME = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+')';

			var MEMBER_USERNAME = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+')';
			var MEMBER_USERNAME_GEN = '@'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+')';
			var MEMBER_USERNAME_DAT = '@'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+')';
			var MEMBER_USERNAME_ACC = '@'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+')';
			var MEMBER_USERNAME_INS = '@'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+')';
			var MEMBER_USERNAME_ABL = '@'+member.screen_name+' ('+member.first_name_abl+' '+member.last_name_abl+')';

			var messages = {$messagesJson};

			if({$sexOnly} != 0){
				if(member.sex != {$sexOnly}){
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':messages.sexErrorMsg});
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

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];

			var FROM_USERNAME = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+')';

			var MEMBER_USERNAME = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+')';
			var MEMBER_USERNAME_GEN = '@'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+')';
			var MEMBER_USERNAME_DAT = '@'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+')';
			var MEMBER_USERNAME_ACC = '@'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+')';
			var MEMBER_USERNAME_INS = '@'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+')';
			var MEMBER_USERNAME_ABL = '@'+member.screen_name+' ('+member.first_name_abl+' '+member.last_name_abl+')';

			var messages = {$messagesJson};

			if({$sexOnly} != 0){
				if(member.sex != {$sexOnly}){
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':messages.sexErrorMsg});
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

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

///////////////////////////////////////////////////////////
/// Handlers

function rp_me($finput){
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
		mb_internal_encoding("UTF-8");
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

function rp_try($finput){
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
		mb_internal_encoding("UTF-8");
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

function rp_do($finput){
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
		mb_internal_encoding("UTF-8");
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

function rp_shout($finput){
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
		mb_internal_encoding("UTF-8");
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

function rp_sex($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Секс <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_ins,last_name_ins'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				if(member.sex == 1){
					msg = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+') подрочила.🤗';
				} else {
					msg = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+') подрочил.🤗';
				}
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') занялась сексом с @'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+').😍';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') занялся сексом с @'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+').😍';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_ins,last_name_ins'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				if(from_user.sex == 1){
					msg = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+') подрочила.🤗';
				} else {
					msg = '@'+member.screen_name+' ('+member.first_name+' '+member.last_name+') подрочил.🤗';
				}
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') занялась сексом с @'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+').😍';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') занялся сексом с @'+member.screen_name+' ('+member.first_name_ins+' '+member.last_name_ins+').😍';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_hug($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Обнять <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_acc,last_name_acc'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя обнять себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обняла @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🤗';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обнял @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🤗';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_acc,last_name_acc'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя обнять себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обняла @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🤗';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обнял @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🤗';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_bump($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Уебать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_dat,last_name_dat'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя уебать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') уебала @'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+').👊🏻';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') уебал @'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+').👊🏻';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_dat,last_name_dat'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя уебать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') уебала @'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+').👊🏻';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') уебал @'+member.screen_name+' ('+member.first_name_dat+' '+member.last_name_dat+').👊🏻';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_pissof($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Обоссать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_gen,last_name_gen'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя обоссать себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обоссала @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').💦';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обоссал @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').💦';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_gen,last_name_gen'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя обоссать себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обоссала @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').💦';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') обоссал @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').💦';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_kiss($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Поцеловать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_acc,last_name_acc'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя поцеловать себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') поцеловала @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😘';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') поцеловал @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😘';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_acc,last_name_acc'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя поцеловать себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') поцеловала @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😘';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') поцеловал @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😘';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_hark($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Харкнуть <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_acc,last_name_acc'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя харкнуть в себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') харкнула в @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😈';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') харкнул в @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😈';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_acc,last_name_acc'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя харкнуть в себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') харкнула в @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😈';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') харкнул в @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').😈';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_suck($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Отсосать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_gen,last_name_gen'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			if(member.sex == 1){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', нельзя отсосать девочке!😂'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя отсосать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отсосала у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍌';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отсосал у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍌';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_gen,last_name_gen,sex'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			if(members.profiles[member_index].sex == 1){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', нельзя отсосать девочке!😂'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя отсосать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отсосала у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍌';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отсосал у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍌';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_lick($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$msg = ", используйте \"Отлизать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_gen,last_name_gen'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			if(member.sex == 2){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', нельзя отлизать мальчику!😂'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя отлизать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отлизала у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍑';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отлизал у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍑';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_gen,last_name_gen,sex'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			if(members.profiles[member_index].sex == 2){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', нельзя отлизать мальчику!😂'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя отлизать себе.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отлизала у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍑';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') отлизал у @'+member.screen_name+' ('+member.first_name_gen+' '+member.last_name_gen+').🍑';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_gofuck($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	$member_id = 0;
	$botModule = new botModule($db);

	if(is_null($words[1]) && is_null($data->object->fwd_messages[0]->from_id)){
		$botModule = new botModule($db);
		$msg = ", используйте \"Послать <имя/фамилия/id/упоминание/перес. сообщение>\".";
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		return 0;
	}

	if(!is_null($data->object->fwd_messages[0]->from_id)){
		$member_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$member_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$member_id = intval($words[1]);
	}

	if($member_id > 0){
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var users = API.users.get({'user_ids':[{$member_id},{$data->object->from_id}],'fields':'sex,screen_name,first_name_acc,last_name_acc'});
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id}});
			var from_user = users[1];
			var member = users[0];

			var isContinue = false;
			var i = 0; while(i < members.profiles.length){
				if(members.profiles[i].id == {$member_id}){
					isContinue = true;
				}
				i = i + 1;
			}
			if(!isContinue){
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var msg = '';

			if ({$member_id} == {$data->object->from_id}){
				msg = appeal+', нельзя послать нахуй себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') послала нахуй @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🖕🏻';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') послал нахуй @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🖕🏻';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");

	} else {
		$word1_array = preg_split('//u', strval($words[1]), null, PREG_SPLIT_NO_EMPTY);
		$word2_array = preg_split('//u', strval($words[2]), null, PREG_SPLIT_NO_EMPTY);
		$word1 = mb_strtoupper($word1_array[0]) . mb_substr(strval($words[1]), 1);
		$word2 = mb_strtoupper($word2_array[0]) . mb_substr(strval($words[2]), 1);
		vk_execute($botModule->makeExeAppeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'screen_name,first_name_acc,last_name_acc'});
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
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', ❗указанного человека нет в беседе!'});
			}

			var member = members.profiles[member_index];
			var msg = '';

			if (member.id == {$data->object->from_id}){
				msg = appeal+', нельзя послать нахуй себя.😐';
			} else {
				if(from_user.sex == 1){
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') послала нахуй @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🖕🏻';
				} else {
					msg = '@'+from_user.screen_name+' ('+from_user.first_name+' '+from_user.last_name+') послал нахуй @'+member.screen_name+' ('+member.first_name_acc+' '+member.last_name_acc+').🖕🏻';
				};
			};

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
			");
	}
}

function rp_castrate($finput){ // Test
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	rp_api_act_with($db, $data, $words, "%FROM_USERNAME% кастрировал %MEMBER_USERNAME_ACC%.", "%FROM_USERNAME% кастрировала %MEMBER_USERNAME_ACC%.", "%appeal%, нельзя кастрировать себя.😐", "%appeal%, нельзя кастрировать себя.😐");
}

function rp_sit($finput){
	// Инициализация базовых переменных
	$data = $finput->data; 
	$words = $finput->words;
	$db = &$finput->db;

	rp_api_act_with($db, $data, $words, "%FROM_USERNAME% посадил на бутылку %MEMBER_USERNAME_ACC%.🍾", "%FROM_USERNAME% посадила на бутылку %MEMBER_USERNAME_ACC%.🍾", "%FROM_USERNAME% сел на бутылку.🍾", "%FROM_USERNAME% села на бутылку.🍾");
}

?>