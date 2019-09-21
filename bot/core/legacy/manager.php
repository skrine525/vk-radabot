<?php

/*function manager_ban_user($data, $words){ // Legacy
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_reg($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
		return 'error';
	}


	$user_id = 0;
	if(sizeof($data->object->fwd_messages) >= 1){
		$user_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$user_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$user_id = intval($words[1]);
	} else {
		$msg = ", используйте \\\"!ban <упоминание/id>\\\" или перешлите сообщение с командой \\\"!ban\\\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	if($user_id < 0){
		$msg = ", нельзя забанить сообщество.";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	$banned_users = bot_get_ban_array($db);
	for($i = 0; $i < sizeof($banned_users); $i++){
		if ($banned_users[$i] == $user_id){
			$msg = ", @id".$user_id." (этот) пользователь уже забанен!";
			vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
			return 'error';
		}
	}
	$res_json = vk_execute(bot_make_exeappeal($data->object->from_id)."
		var from_id = {$data->object->from_id};
		var peer_id = {$data->object->peer_id};
		var user_id = {$user_id};
		var members = API.messages.getConversationMembers({'peer_id':peer_id});

		var from_id_index = -1;
		var i = 0; while (i < members.items.length){
			if(members.items[i].member_id == from_id){
				from_id_index = i;
				i = members.items.length;
			};
			i = i + 1;
		};

		var user_id_index = -1;
		var i = 0; while (i < members.items.length){
			if(members.items[i].member_id == user_id){
				user_id_index = i;
				i = members.items.length;
			};
			i = i + 1;
		};

		if(members.items[from_id_index].is_admin){
			if(members.items[user_id_index].is_admin){
				var msg = ', невозможно забанить администратора беседы.';
				API.messages.send({'peer_id':peer_id,'message':appeal+msg});
				return false;
			} else {
				API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});
				var msg = ', @id'+user_id+' (пользователь) успешно забанен!';
				API.messages.send({'peer_id':peer_id,'message':appeal+msg});
				return true;
			}
		} else {
			var msg = appeal+', ты не администратор!'; 
			API.messages.send({'peer_id':peer_id,'message':msg});
			return false;
		};");
	$res = json_decode($res_json);
	if($res->response){
		$banned_users[] = $user_id;
		bot_set_ban_array($db, $banned_users);
		mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
	}
}*/

/*function manager_unban_user($data, $words){ // Legacy
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_reg($db)){
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
		return 'error';
	}

	$user_id = 0;
	if(sizeof($data->object->fwd_messages) >= 1){
		$user_id = $data->object->fwd_messages[0]->from_id;
	} elseif(bot_is_mention($words[1])){
		$user_id = bot_get_id_from_mention($words[1]);
	} elseif(is_numeric($words[1])) {
		$user_id = intval($words[1]);
	} else {
		$msg = ", используйте \\\"!unban <упоминание/id>\\\" или перешлите сообщение с командой \\\"!unban\\\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	if($user_id < 0){
		$msg = ", нельзя разбанить сообщество.";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	$banned_users = bot_get_ban_array($db);
	for($i = 0; $i < sizeof($banned_users); $i++){
		if($user_id == $banned_users[$i]){
			$msg = ", @id{$user_id} (пользователь) успешно разбанен!";
			$res = json_decode(vk_execute(bot_make_exeappeal($data->object->from_id).bot_test_rights_exe($data->object->peer_id, $data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				return 'ok';
				"));
			if($res->response == 'ok'){
				$banned_users[$i] = $banned_users[sizeof($banned_users)-1];
				unset($banned_users[sizeof($banned_users)-1]);
				bot_set_ban_array($db, $banned_users);
				mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
			}
			return 'ok';
		}
	}

	$msg = ", @id{$user_id} (пользователь) не забанен в этой беседе!";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
}*/

function manager_ban_user($data, $words, &$db){
	if(!bot_check_reg($db)){
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
		return 'error';
	}

	$member_ids = array();
	for($i = 0; $i < sizeof($data->object->fwd_messages); $i++){
		$isContrinue = true;
		for($j = 0; $j < sizeof($member_ids); $j++){
			if($member_ids[$j] == $data->object->fwd_messages[$i]->from_id){
				$isContrinue = false;
				break;
			}
		}
		if($isContrinue){
			$member_ids[] = $data->object->fwd_messages[$i]->from_id;
		}
	}
	for($i = 1; $i < sizeof($words); $i++){
		if(bot_is_mention($words[$i])){
			$member_id = bot_get_id_from_mention($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		} elseif(is_numeric($words[$i])) {
			$member_id = intval($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		}
	}

	if(sizeof($member_ids) == 0){
		$msg = ", используйте \\\"!ban <упоминание/id>\\\" или перешлите сообщение с командой \\\"!ban\\\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя забанить более 10 участников одновременно.";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	$banned_users = bot_get_ban_array($db);
	for($i = 0; $i < count($member_ids); $i++){
		for($j = 0; $j < count($banned_users); $j++){
			if($member_ids[$i] == $banned_users[$j]){
				$member_ids[$i] = $member_ids[count($member_ids)-1];
				unset($member_ids[count($member_ids)-1]);
				break;
			}
		}
	}

	$member_ids_exe_array = $member_ids[0];
	for($i = 1; $i < sizeof($member_ids); $i++){
		$member_ids_exe_array = $member_ids_exe_array.','.$member_ids[$i];
	}

	$res = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var member_ids = [{$member_ids_exe_array}];
		var users = API.users.get({'user_ids':member_ids});
		var members = API.messages.getConversationMembers({'peer_id':peer_id});
		var banned_ids = [];

		var msg = ', следующие пользователи были забанены:\\n';
		var msg_banned_users = '';

		var j = 0; while(j < users.length){
			var user_id = users[j].id;
			var user_id_index = -1;
			var i = 0; while (i < members.items.length){
				if(members.items[i].member_id == user_id){
					user_id_index = i;
					i = members.items.length;
				};
				i = i + 1;
			};

			if(!members.items[user_id_index].is_admin){
				API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});
				msg_banned_users = msg_banned_users + '✅@id'+ user_id + ' (' + users[j].first_name + ' ' + users[j].last_name + ')\\n';
				banned_ids = banned_ids + [user_id];
			}
			j = j + 1;
		};
		if(msg_banned_users != ''){
			API.messages.send({'peer_id':peer_id,'message':appeal+msg+msg_banned_users});
			return banned_ids;
		} else {
			msg = ', ни один пользователь не был забанен.';
			API.messages.send({'peer_id':peer_id,'message':appeal+msg});
			return banned_ids;
		}
		"));
	if(sizeof($res->response) > 0){
		$banned_users = bot_get_ban_array($db);
		for($i = 0; $i < sizeof($res->response); $i++){
			$isContrinue = true;
			for($j = 0; $j < sizeof($banned_users); $j++){
				if($banned_users[$j] == $res->response[i]){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$banned_users[] = $res->response[$i];
			}
		}
		bot_set_ban_array($db, $banned_users);
	}
}

function manager_unban_user($data, $words, &$db){
	if(!bot_check_reg($db)){
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
		return 'error';
	}

	$member_ids = array();
	for($i = 0; $i < sizeof($data->object->fwd_messages); $i++){
		$isContrinue = true;
		for($j = 0; $j < sizeof($member_ids); $j++){
			if($member_ids[$j] == $data->object->fwd_messages[$i]->from_id){
				$isContrinue = false;
				break;
			}
		}
		if($isContrinue){
			$member_ids[] = $data->object->fwd_messages[$i]->from_id;
		}
	}
	for($i = 1; $i < sizeof($words); $i++){
		if(bot_is_mention($words[$i])){
			$member_id = bot_get_id_from_mention($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		} elseif(is_numeric($words[$i])) {
			$member_id = intval($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		}
	}

	if(sizeof($member_ids) == 0){
		$msg = ", используйте \\\"!unban <упоминание/id>\\\" или перешлите сообщение с командой \\\"!unban\\\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя разбанить более 10 участников одновременно.";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	$unbanned_member_ids = array();

	$banned_users = bot_get_ban_array($db);
	for($i = 0; $i < sizeof($member_ids); $i++){
		for($j = 0; $j < sizeof($banned_users); $j++){
			if($member_ids[$i] == $banned_users[$j]){
				$unbanned_member_ids[] = $banned_users[$j];
				//$banned_users[$j] = $banned_users[sizeof($banned_users)-1];
				//unset($banned_users[sizeof($banned_users)-1]);
			}
		}
	}

	$member_ids_exe_array = $unbanned_member_ids[0];
	for($i = 1; $i < sizeof($unbanned_member_ids); $i++){
		$member_ids_exe_array = $member_ids_exe_array.','.$unbanned_member_ids[$i];
	}

	$res = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var member_ids = [{$member_ids_exe_array}];
		var users = API.users.get({'user_ids':member_ids});
		var banned_ids = [];

		var msg = ', следующие пользователи были разбанены:\\n';
		var msg_unbanned_users = '';

		var j = 0; while(j < users.length){
			var user_id = users[j].id;
			msg_unbanned_users = msg_unbanned_users + '✅@id'+ user_id + ' (' + users[j].first_name + ' ' + users[j].last_name + ')\\n';
			j = j + 1;
		};
		if(msg_unbanned_users != ''){
			API.messages.send({'peer_id':peer_id,'message':appeal+msg+msg_unbanned_users});
		} else {
			msg = ', ни один пользователь не был разбанен.';
			API.messages.send({'peer_id':peer_id,'message':appeal+msg});
		}

		return 'ok';
		"));

	if($res->response == 'ok'){
		for($i = 0; $i < sizeof($unbanned_member_ids); $i++){
			for($j = 0; $j < sizeof($banned_users); $j++){
				if($unbanned_member_ids[$i] == $banned_users[$j]){
					$banned_users[$j] = $banned_users[sizeof($banned_users)-1];
					unset($banned_users[sizeof($banned_users)-1]);
				}
			}
		}
		bot_set_ban_array($db, $banned_users);
	}
}

function manager_banlist_user($data, $words, $db){
	if(!bot_check_reg($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
			");
		return 'error';
	}

	if(!is_null($words[1]))
		$list_number_from_word = intval($words[1]);
	else
		$list_number_from_word = 1;


	$banned_users = bot_get_ban_array($db);
	if(sizeof($banned_users) == 0){
		vk_execute(bot_make_exeappeal($data->object->from_id)."return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', в беседе нет забаненных пользователей.'});");
		return 0;
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$banned_users; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
	$list_size = 10; // Размер списка
	////////////////////////////////////////////////////
	if(count($list_in) % $list_size == 0)
		$list_max_number = intdiv(count($list_in), $list_size);
	else
		$list_max_number = intdiv(count($list_in), $list_size)+1;
	$list_min_index = ($list_size*$list_number)-$list_size;
	if($list_size*$list_number >= count($list_in))	
		$list_max_index = count($list_in)-1;
	else
		$list_max_index = $list_size*$list_number-1;
	if($list_number <= $list_max_number && $list_number > 0){
		// Обработчик списка
		for($i = $list_min_index; $i <= $list_max_index; $i++){
			$list_out[] = $list_in[$i];
		}
	}
	else{
		// Сообщение об ошибке
		bot_send_simple_message($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return 0;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	$users_list = json_encode($list_out, JSON_UNESCAPED_UNICODE);

	//$users_list = json_encode($banned_users, JSON_UNESCAPED_UNICODE);

	vk_execute(bot_make_exeappeal($data->object->from_id)."
		var users = API.users.get({'user_ids':{$users_list}});
		var msg = ', список забаненых пользователей [{$list_number}/{$list_max_number}]:';
		var i = 0; while(i < users.length){
			var user_first_name = users[i].first_name;
			msg = msg + '\\n🆘@id' + users[i].id + ' (' + user_first_name.substr(0, 2) + '. ' + users[i].last_name + ') (ID: ' + users[i].id + ');';
			i = i + 1;
		};
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});
		");
}

function manager_kick_user($data, $words){
	$member_ids = array();
	for($i = 0; $i < sizeof($data->object->fwd_messages); $i++){
		$isContrinue = true;
		for($j = 0; $j < sizeof($member_ids); $j++){
			if($member_ids[$j] == $data->object->fwd_messages[$i]->from_id){
				$isContrinue = false;
				break;
			}
		}
		if($isContrinue){
			$member_ids[] = $data->object->fwd_messages[$i]->from_id;
		}
	}
	for($i = 1; $i < sizeof($words); $i++){
		if(bot_is_mention($words[$i])){
			$member_id = bot_get_id_from_mention($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		} elseif(is_numeric($words[$i])) {
			$member_id = intval($words[$i]);
			$isContrinue = true;
			for($j = 0; $j < sizeof($member_ids); $j++){
				if($member_ids[$j] == $member_id){
					$isContrinue = false;
					break;
				}
			}
			if($isContrinue){
				$member_ids[] = $member_id;
			}
		}
	}

	if(sizeof($member_ids) == 0){
		$msg = ", используйте \\\"!kick <упоминание/id>\\\" или перешлите сообщение с командой \\\"!kick\\\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	} else if(sizeof($member_ids) > 10) {
		$msg = ", нельзя кикнуть более 10 участников одновременно.";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		return 0;
	}

	$member_ids_exe_array = $member_ids[0];
	for($i = 1; $i < sizeof($member_ids); $i++){
		$member_ids_exe_array = $member_ids_exe_array.','.$member_ids[$i];
	}

	vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
		var peer_id = {$data->object->peer_id};
		var member_ids = [{$member_ids_exe_array}];
		var users = API.users.get({'user_ids':member_ids});
		var members = API.messages.getConversationMembers({'peer_id':peer_id});

		var msg = ', следующие пользователи были кикнуты:\\n';
		var msg_banned_users = '';

		var j = 0; while(j < users.length){
			var user_id = users[j].id;
			var user_id_index = -1;
			var i = 0; while (i < members.items.length){
				if(members.items[i].member_id == user_id){
					user_id_index = i;
					i = members.items.length;
				};
				i = i + 1;
			};

			if(!members.items[user_id_index].is_admin && user_id_index != -1){
				API.messages.removeChatUser({'chat_id':peer_id-2000000000,'member_id':user_id});
				msg_banned_users = msg_banned_users + '✅@id'+ user_id + ' (' + users[j].first_name + ' ' + users[j].last_name + ')\\n';
			}
			j = j + 1;
		};
		if(msg_banned_users != ''){
			return API.messages.send({'peer_id':peer_id,'message':appeal+msg+msg_banned_users});
		} else {
			msg = ', ни один пользователь не был кикнут.';
			return API.messages.send({'peer_id':peer_id,'message':appeal+msg});
		}
		");
}

function manager_online_list($data, $words){
	if(is_null($words[1])){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			var members = API.messages.getConversationMembers({'peer_id':{$data->object->peer_id},'fields':'online'});
			var msg = ', 🌐следующие пользователи в сети:\\n';
			var msg_users = '';

			var  i = 0; while(i < members.profiles.length){
				if(members.profiles[i].online == 1){
					msg_users = msg_users + '✅@id' + members.profiles[i].id + ' (' + members.profiles[i].first_name.substr(0, 2) + '. ' + members.profiles[i].last_name + ')\\n';
				}
				i = i + 1;
			}

			if(msg_users == ''){
				msg = ', 🚫в данный момент нет пользователей в сети!';
			} else {
				msg = msg + msg_users;
			}

			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});
			");
	}
}

function manager_nick($data, $words, &$db){
	if(!is_null($words[1])){
		if($words[1] == '-убрать') {
			if(is_null($data->object->fwd_messages[0])){
				unset($db["bot_manager"]["user_nicknames"]["id{$data->object->from_id}"]);
				$msg = ", ✅ник убран.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
			}
			else{
				$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ✅ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) убран!"), JSON_UNESCAPED_UNICODE);
				$request = vk_parse_var($request, "appeal");
				$response = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
					API.messages.send({$request});
					return 'ok';
					"))->response;
				if($response == 'ok')
					unset($db["bot_manager"]["user_nicknames"]["id{$data->object->fwd_messages[0]->from_id}"]);
			}
		} else {
			mb_internal_encoding("UTF-8");
			$nick = mb_substr($data->object->text, 5);
			$nick = str_ireplace("\n", "", $nick);
			if(is_null($data->object->fwd_messages[0])){
				if(mb_strlen($nick) <= 15){
					$db["bot_manager"]["user_nicknames"]["id{$data->object->from_id}"] = $nick;
					$msg = ", ✅ник установлен.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
				} else {
					$msg = ", ⛔указанный ник больше 15 символов.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
				}
			}
			else{
				$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ✅ник @id{$data->object->fwd_messages[0]->from_id} (пользователя) изменён!"), JSON_UNESCAPED_UNICODE);
				$request = vk_parse_var($request, "appeal");
				$response = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
					API.messages.send({$request});
					return 'ok';
					"))->response;
				if($response == 'ok')
					$db["bot_manager"]["user_nicknames"]["id{$data->object->fwd_messages[0]->from_id}"] = $nick;
			}
		}
	} else {
		$msg = ", ⛔используйте\\\"!ник <ник/-убрать>\\\" для управления ником.";
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	}
}

function manager_show_nicknames($data, $words, $db){
	if(!is_null($words[1]))
		$list_number_from_word = intval($words[1]);
	else
		$list_number_from_word = 1;

	$nicknames = array();
	foreach ($db["bot_manager"]["user_nicknames"] as $key => $val) {
		$nicknames[] = array(
			'user_id' => substr($key, 2),
			'nick' => $val
		);
	}
	if(count($nicknames) == 0){
		$request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, ❗в беседе нет пользователей с никами!"), JSON_UNESCAPED_UNICODE);
		$request = vk_parse_var($request, "appeal");
		vk_execute(bot_make_exeappeal($data->object->from_id)."API.messages.send({$request});");
		return 0;
	}

	/////////////////////////////////////////////////////
	////////////////////////////////////////////////////
	$list_in = &$nicknames; // Входной список
	$list_out = array(); // Выходной список

	$list_number = $list_number_from_word; // Номер текущего списка
	$list_size = 10; // Размер списка
	////////////////////////////////////////////////////
	if(count($list_in) % $list_size == 0)
		$list_max_number = intdiv(count($list_in), $list_size);
	else
		$list_max_number = intdiv(count($list_in), $list_size)+1;
	$list_min_index = ($list_size*$list_number)-$list_size;
	if($list_size*$list_number >= count($list_in))	
		$list_max_index = count($list_in)-1;
	else
		$list_max_index = $list_size*$list_number-1;
	if($list_number <= $list_max_number && $list_number > 0){
		// Обработчик списка
		for($i = $list_min_index; $i <= $list_max_index; $i++){
			$list_out[] = $list_in[$i];
		}
	}
	else{
		// Сообщение об ошибке
		bot_send_simple_message($data->object->peer_id, ", ⛔указан неверный номер списка!", $data->object->from_id);
		return 0;
	}
	////////////////////////////////////////////////////
	////////////////////////////////////////////////////

	vk_execute(bot_make_exeappeal($data->object->from_id)."
		var nicknames = ".json_encode($list_out, JSON_UNESCAPED_UNICODE).";
		var users = API.users.get({'user_ids':nicknames@.user_id});
		var msg = appeal+', ники [{$list_number}/{$list_max_number}]:';
		var i = 0; while(i < nicknames.length){
			msg = msg + '\\n✅@id'+nicknames[i].user_id+' ('+users[i].first_name.substr(0, 2)+'. '+users[i].last_name+') - '+nicknames[i].nick;
			i = i + 1;
		}
		return API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
		");
}

function manager_greeting($data, $words, &$db){
	mb_internal_encoding("UTF-8");
	$command = mb_strtolower($words[1]);
	if($command == 'установить'){
		$msg = ", ✅приветствие установлено.";
		$res = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			return 'ok';
			"));
		if($res->response == 'ok')
			$db["bot_manager"]["invited_greeting"] = mb_substr($data->object->text, 24, mb_strlen($data->object->text));
	} elseif($command == 'показать'){
		if(!is_null($db["bot_manager"]["invited_greeting"])){
			$json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => "%appeal%, приветствие в беседе:\n{$db["bot_manager"]["invited_greeting"]}"), JSON_UNESCAPED_UNICODE);
			$json_request = vk_parse_var($json_request, "appeal");
			vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				API.messages.send({$json_request});
				return 'ok';
				");
		} else {
			$msg = ", ⛔приветствие не установлено.";
			vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				return 'ok';
				");
		}
	} elseif($command == 'убрать'){
		if(!is_null($db["bot_manager"]["invited_greeting"])){
			$msg = ", ✅приветствие убрано.";
			$res = json_decode(vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				return 'ok';
				"));
			if($res->response == 'ok')
				unset($db["bot_manager"]["invited_greeting"]);

		} else {
			$msg = ", ⛔приветствие не установлено.";
			vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				return 'ok';
				");
		}
	} else{
		$msg = ", ⛔используйте \"!приветствие установить/показать/убрать\".";
		vk_execute(bot_test_rights_exe($data->object->peer_id, $data->object->from_id).bot_make_exeappeal($data->object->from_id)."
			API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			return 'ok';
			");
	}
}

function manager_show_invited_greetings($data){
	if($data->object->action->type == "chat_invite_user" && !is_null($GLOBALS['db']["bot_manager"]["invited_greeting"]) && $GLOBALS['CAN_SEND_INVITED_GREETING_MESSAGE'] && $data->object->action->member_id > 0){
		$db = $GLOBALS['db'];
		$greetings_text = $db["bot_manager"]["invited_greeting"];
		$parsing_vars = array('USERID', 'USERNAME', 'USERNAME_GEN', 'USERNAME_DAT', 'USERNAME_ACC', 'USERNAME_INS', 'USERNAME_ABL');

		$system_code = "
			var user = API.users.get({'user_ids':[{$data->object->action->member_id}],'fields':'first_name_gen,first_name_dat,first_name_acc,first_name_ins,first_name_abl,last_name_gen,last_name_dat,last_name_acc,last_name_ins,last_name_abl'})[0];
			var USERID = '@id'+user.id;
			var USERNAME = user.first_name+' '+user.last_name;
			var USERNAME_GEN = user.first_name_gen+' '+user.last_name_gen;
			var USERNAME_DAT = user.first_name_dat+' '+user.last_name_dat;
			var USERNAME_ACC = user.first_name_acc+' '+user.last_name_acc;
			var USERNAME_INS = user.first_name_ins+' '+user.last_name_ins;
			var USERNAME_ABL = user.first_name_abl+' '+user.last_name_abl;
		";

		$message_json_request = json_encode(array('peer_id' => $data->object->peer_id, 'message' => $greetings_text), JSON_UNESCAPED_UNICODE);

		for($i = 0; $i < count($parsing_vars); $i++){
			$message_json_request = vk_parse_var($message_json_request, $parsing_vars[$i]);
		}

		vk_execute($system_code."return API.messages.send({$message_json_request});");
	}
	unset($GLOBALS['CAN_SEND_INVITED_GREETING_MESSAGE']); // Очищаем память от ненужной переменной
}

?>