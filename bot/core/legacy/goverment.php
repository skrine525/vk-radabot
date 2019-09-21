<?php

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Add part
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function goverment_soc_order_types(){ 
	return array('Капитализм', 'Социализм', 'Коммунизм', 'Фашизм');
}

function goverment_soc_order_encode($id){
	mb_internal_encoding("UTF-8");
	$array = goverment_soc_order_types();
	for($i = 0; $i < count($array); $i++){
		if(mb_strtoupper($array[$i]) == mb_strtoupper($id)){
			return $i+1;
		}
	}
	return 0;
}

function goverment_soc_order_decode($id){
	$array = goverment_soc_order_types();
	return $array[$id-1];
}

function goverment_soc_order_desc($id){
	switch ($id) {
		case 1:
		return "это капиталистическое федеративное государство с республиканской формой правления";
		break;

		case 2:
		return "это социалистическая унитарная республика с демократической диктатурой народа";
		break;

		case 3:
		return "это коммунистическое унитарное государство с тоталитарным политическим режимом";
		break;

		case 4:
		return "это фашисткая унитарная империя с диктаторской формой правления и тоталитарным политическим режимом";
		break;
	}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Main part
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function goverment_constitution($data, $words, &$gov){
	if(is_null($words[1])){
		$confa_info = json_decode(vk_send('messages.getConversationsById', array('peer_ids' => $data->object->peer_id)));
		$msg = ", &#128204;Основная Конституция:\n&#9989;".$confa_info->response->items[0]->chat_settings->title." - ".goverment_soc_order_desc($gov["soc_order"]).".\n&#9989;Глава государства: @id".$gov["president_id"]." (".$gov["president_first_name"]." ".$gov["president_last_name"].").\n&#9989;Правящая партия: ".$gov["batch_name"].".\n&#9989;Столица: ".$gov["capital"].".\n\n&#128204;Парламентская Конституция:\n".$gov["parliament_constitution"]."\n\n&#128204;Президентская Конституция:\n".$gov["president_constitution"]."";
		bot_send_simple_message($data->object->peer_id, $msg, $data->object->from_id);
	} else {
		if($gov["president_id"] == $data->object->from_id){
			mb_internal_encoding("UTF-8");
			$str = mb_substr($data->object->text, 13, mb_strlen($data->object->text));
			$gov["president_constitution"] = $str;
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президент) обновил свою часть конституции."));
		} elseif($gov["parliament_id"] == $data->object->from_id){
			mb_internal_encoding("UTF-8");
			$str = mb_substr($data->object->text, 13, mb_strlen($data->object->text));
			$gov['parliament_constitution'] = $str;
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламент) обновил свою часть конституции."));
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_laws($data, $words, &$gov){
	if(is_null($words[1])){
		$msg = ", &#128204;Законы Парламента:\n".$gov["parliament_laws"]."\n\n&#128204;Законы Президента:\n".$gov["president_laws"]."";
		bot_send_simple_message($data->object->peer_id, $msg, $data->object->from_id);
	} else {
		if($gov["president_id"] == $data->object->from_id){
			mb_internal_encoding("UTF-8");
			$str = mb_substr($data->object->text, 8, mb_strlen($data->object->text));
			$gov["president_laws"] = $str;
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президент) обновил свою часть законов."));
		} elseif($gov["parliament_id"] == $data->object->from_id){
			mb_internal_encoding("UTF-8");
			$str = mb_substr($data->object->text, 8, mb_strlen($data->object->text));
			$gov['parliament_laws'] = $str;
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламент) обновил свою часть законов."));
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_president($data, $words, &$gov){
	if(is_null($words[1])){
		bot_send_simple_message($data->object->peer_id, ", &#128104;&#8205;&#9878;Действующий президент: @id".$gov["president_id"]." (".$gov["president_first_name"]." ".$gov["president_last_name"].").", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["parliament_id"]){
			$new_president_id = bot_get_id_from_mention($words[1]);
			$new_president_data = json_decode(vk_send('users.get', array('user_ids' => $new_president_id, 'fields' => 'first_name_gen,last_name_gen')));
			$gov["president_id"] = $new_president_id;
			$gov["president_first_name"] = $new_president_data->response[0]->first_name;
			$gov["president_last_name"] = $new_president_data->response[0]->last_name;
			$gov["batch_name"] = "Полит. партия ".$new_president_data->response[0]->first_name_gen." ".$new_president_data->response[0]->last_name_gen;
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламентом) назначен новый президент: @id".$gov["president_id"]." (".$gov["president_first_name"]." ".$gov["president_last_name"].")."));
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_batch($data, $words, &$gov){
	if(is_null($words[1])){
		bot_send_simple_message($data->object->peer_id, ", &#128214;Действующая партия: ".$gov["batch_name"].".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["president_id"]){
			mb_internal_encoding("UTF-8");
			$gov["batch_name"] = mb_substr($data->object->text, 8, mb_strlen($data->object->text));
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президент) переименовал действующуу партию."));
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_capital($data, $words, &$gov){
	if(is_null($words[1])){
		bot_send_simple_message($data->object->peer_id, ", &#127970;Текущая столица: ".$gov["capital"].".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["president_id"]){
			mb_internal_encoding("UTF-8");
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президент) изменил столицу государства."));
		} elseif($data->object->from_id == $gov["parliament_id"]){
			mb_internal_encoding("UTF-8");
			$gov["capital"] = mb_substr($data->object->text, 9, mb_strlen($data->object->text));
			vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламент) изменил столицу государства."));
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_socorder($data, $words, &$gov){
	if(is_null($words[1])){
		bot_send_simple_message($data->object->peer_id, ", ⚔Текущий политический строй государства: ".goverment_soc_order_decode($gov["soc_order"]).".", $data->object->from_id);
	} else {
		if($data->object->from_id == $gov["parliament_id"]){
			$id = goverment_soc_order_encode($words[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламентом) был изменён политический строй."));
			} else {
				bot_send_simple_message($data->object->peer_id, ", Такого политического строя нет! Смотрите !стройлист.", $data->object->from_id);
			}
		} elseif ($data->object->from_id == $gov["president_id"]) {
			$id = goverment_soc_order_encode($words[1]);
			if ($id != 0){
				$gov["soc_order"] = $id;
				vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президентом) был изменён политический строй."));
			} else {
				bot_send_simple_message($data->object->peer_id, ", Такого политического строя нет! Смотрите !стройлист.", $data->object->from_id);
			}
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_socorderlist($data){
	$array = goverment_soc_order_types();
	$msg = "";
	for($i = 0; $i < count($array); $i++){
		$msg = $msg."\n&#127381;".$array[$i];
	}

	bot_send_simple_message($data->object->peer_id, ", Список политических строев: ".$msg, $data->object->from_id);
}

function goverment_anthem($data, &$gov){
	if(count($data->object->attachments) == 0){
		if($gov["anthem"] != "nil"){
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', &#129345;Наш гимн: ','attachment':'{$gov["anthem"]}'});
				");
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#129345;У нас нет гимна!", $data->object->from_id);
		}
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$first_audio_id = -1;
			$audio = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "audio"){
					$first_audio_id = $i;
					break;
				}
			}
			if ($first_audio_id != -1){
				$audio = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				$gov["anthem"] = $audio;
				vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["president_id"]." (Президент) изменил гимн государства."));
			} else {
				bot_send_simple_message($data->object->peer_id, ", &#9940;Аудиозаписи не найдены!", $data->object->from_id);
			}
		} elseif($data->object->from_id == $gov["parliament_id"]){
			$first_audio_id = -1;
			$audio = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "audio"){
					$first_audio_id = $i;
					break;
				}
			}
			if ($first_audio_id != -1){
				$audio = "audio".$data->object->attachments[$first_audio_id]->audio->owner_id."_".$data->object->attachments[$first_audio_id]->audio->id;
				$gov["anthem"] = $audio;
				vk_send('messages.send', array('peer_id' => $data->object->peer_id, 'message' => "@id".$gov["parliament_id"]." (Парламент) изменил гимн государства."));
			} else {
				bot_send_simple_message($data->object->peer_id, ", &#9940;Аудиозаписи не найдены!", $data->object->from_id);
			}
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_flag($data, &$gov){
	if(count($data->object->attachments) == 0){
		if($gov["flag"] != "nil"){
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', &#127987;Наш флаг: ','attachment':'{$gov["flag"]}'});
				");
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#127987;У нас нет флага!", $data->object->from_id);
		}
	} else {
		if($data->object->from_id == $gov["president_id"]){
			$first_photo_id = -1;
			$photo = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "photo"){
					$first_photo_id = $i;
					break;
				}
			}
			if ($first_photo_id != -1){
				$photo_sizes = $data->object->attachments[$first_photo_id]->photo->sizes;
				$photo_url_index = 0;
				for($i = 0; $i < count($photo_sizes); $i++){
					if($photo_sizes[$i]->height > $photo_sizes[$photo_url_index]->height){
						$photo_url_index = $i;
					}
				}
				$photo_url = $photo_sizes[$photo_url_index]->url;
				$path = "../tmp/photo".mt_rand(0, 65500).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["president_id"]." (Президент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});
					API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					return doc;
					"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
			} else {
				bot_send_simple_message($data->object->peer_id, ", &#9940;Фотографии не найдены!", $data->object->from_id);
			}
		} elseif($data->object->from_id == $gov["parliament_id"]){
			$first_photo_id = -1;
			$photo = "";
			for($i = 0; $i < count($data->object->attachments); $i++){
				if($data->object->attachments[$i]->type == "photo"){
					$first_photo_id = $i;
					break;
				}
			}
			if ($first_photo_id != -1){
				$photo_sizes = $data->object->attachments[$first_photo_id]->photo->sizes;
				$photo_url_index = 0;
				for($i = 0; $i < count($photo_sizes); $i++){
					if($photo_sizes[$i]->height > $photo_sizes[$photo_url_index]->height){
						$photo_url_index = $i;
					}
				}
				$photo_url = $photo_sizes[$photo_url_index]->url;
				$path = "../tmp/photo".mt_rand(0, 65500).".jpg";
				file_put_contents($path, file_get_contents($photo_url));
				$response =  json_decode(vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.photos.getMessagesUploadServer({'peer_id':{$data->object->peer_id}});"));
				$res = json_decode(vk_uploadDocs(array('photo' => new CURLFile($path)), $response->response->upload_url));
				unlink($path);
				$msg = "@id".$gov["parliament_id"]." (Парламент) изменил флаг государства.";
				$res_json = json_encode(array('photo' => $res->photo, 'server' => $res->server, 'hash' => $res->hash));
				$photo = json_decode(vk_execute("var doc = API.photos.saveMessagesPhoto({$res_json});
					API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});
					return doc;
					"))->response[0];
				$gov["flag"] = "photo{$photo->owner_id}_{$photo->id}";
			} else {
				bot_send_simple_message($data->object->peer_id, ", &#9940;Фотографии не найдены!", $data->object->from_id);
			}
		} else {
			bot_send_simple_message($data->object->peer_id, ", &#9940;У вас нет прав на использование этой команды с аргументами!", $data->object->from_id);
		}
	}
}

function goverment_referendum_start($data, &$db){
	if(!bot_check_reg($db)){
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
			return 'error';
	}

	if($data->object->from_id == $db["parliament_id"]){
		if(is_null($db["goverment"]["referendum"])){
			$db["goverment"]["referendum"]["candidate1"] = array('id' => 0, "voters_count" => 0);
			$db["goverment"]["referendum"]["candidate2"] = array('id' => 0, "voters_count" => 0);
			$db["goverment"]["referendum"]["all_voters"] = array();
			$db["goverment"]["referendum"]["start_time"] = $data->object->date;
			$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
			$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \\\"!candidate\\\".";
			vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
		} else {
			$msg = ", выборы уже проходят.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", &#9940;у вас нет прав для использования данной команды.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_stop($data, &$db){
	if(!bot_check_reg($db)){
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована!'});
				");
			return 'error';
	}

	if($data->object->from_id == $db["parliament_id"]){
		if(is_null($db["goverment"]["referendum"])){
			$msg = ", сейчас не проходят выборы.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		} else {
			unset($db["goverment"]["referendum"]);
			$msg = ", выборы остановлены.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", &#9940;у вас нет прав для использования данной команды.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_candidate($data, &$db){
	if(!is_null($db["goverment"]["referendum"])){
		if($db["goverment"]["referendum"]["candidate1"]["id"] != $data->object->from_id && $db["goverment"]["referendum"]["candidate2"]["id"] != $data->object->from_id){
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0){
				$db["goverment"]["referendum"]["candidate1"]["id"] = $data->object->from_id;
				$msg = ", вы зарегистрировались как кандидат №1.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
			} elseif($db["goverment"]["referendum"]["candidate2"]["id"] == 0) {
				$db["goverment"]["referendum"]["candidate2"]["id"] = $data->object->from_id;
				$msg1 = ", вы зарегистрировались как кандидат №2.";
				$msg2 = "Кандидаты набраны, самое время голосовать. Используй \\\"!vote\\\", чтобы учавствовать в голосовании.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg1}'});
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg2}'});");
				$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
			} else {
				$msg = ", кандидаты уже набраны.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
			}
		} else {
			$msg = ", вы уже зарегистрированы как кандидат в  президенты.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", сейчас не проходят выборы.";
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

function goverment_referendum_system($data, &$db){
	if(!is_null($db["goverment"]["referendum"])){
		if($data->object->date - $db["goverment"]["referendum"]["last_notification_time"] >= 600){
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0 || $db["goverment"]["referendum"]["candidate2"]["id"] == 0){
				$msg = "Начались выборы в президенты беседы. Чтобы зарегистрироваться, как кандидат, используйте команду \\\"!candidate\\\".";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			} else {
				$msg = "Кандидаты набраны, самое время голосовать. Используй \\\"!vote\\\", чтобы учавствовать в голосовании.";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
			}
			$db["goverment"]["referendum"]["last_notification_time"] = $data->object->date;
		} elseif($data->object->date - $db["goverment"]["referendum"]["start_time"] >= 18000) {
			if($db["goverment"]["referendum"]["candidate1"]["id"] == 0 || $db["goverment"]["referendum"]["candidate2"]["id"] == 0){
				$msg = "❗Выборы прерваны. Причина: не набрано нужно количество кандидатов.";
				vk_execute("
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				unset($db["goverment"]["referendum"]);
			} else {
				$candidate1_voters_count = $db["goverment"]["referendum"]["candidate1"]["voters_count"];
				$candidate2_voters_count = $db["goverment"]["referendum"]["candidate2"]["voters_count"];
				$all_voters_count = sizeof($db["goverment"]["referendum"]["all_voters"]);
				if($candidate1_voters_count > $candidate2_voters_count){
					$candidate_id = $db["goverment"]["referendum"]["candidate1"]["id"];
					$candidate_percent = round($candidate1_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("
						var users = API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});
						var sex_word = 'Он';
						if(users[0].sex == 1){
							sex_word = 'Она';
						}
						var msg = '✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen,'first_name':users[0].first_name,'last_name':users[0].last_name};"));
					$db["president_id"] = $candidate_id;
					$db["president_first_name"] = $res->response->first_name;
					$db["president_last_name"] = $res->response->last_name;
					$db["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					unset($db["goverment"]["referendum"]);

				} elseif($candidate1_voters_count < $candidate2_voters_count) {
					$candidate_id = $db["goverment"]["referendum"]["candidate2"]["id"];
					$candidate_percent = round($candidate2_voters_count/$all_voters_count*100, 1);
					$res = json_decode(vk_execute("
						var users = API.users.get({'user_ids':[{$candidate_id}],'fields':'first_name_gen,last_name_gen,sex'});
						var sex_word = 'Он';
						if(users[0].sex == 1){
							sex_word = 'Она';
						}
						var msg = '✅На выборах побеждает @id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+'). '+sex_word+' побеждает, набрав {$candidate_percent}% голосов избирателей. Поздравляем!';
						API.messages.send({'peer_id':{$data->object->peer_id},'message':msg});
						return {'first_name_gen':users[0].first_name_gen,'last_name_gen':users[0].last_name_gen,'first_name':users[0].first_name,'last_name':users[0].last_name};"));
					$db["president_id"] = $candidate_id;
					$db["president_first_name"] = $res->response->first_name;
					$db["president_last_name"] = $res->response->last_name;
					$db["batch_name"] = "Полит. партия ".$res->response->first_name_gen." ".$res->response->last_name_gen;
					unset($db["goverment"]["referendum"]);
				} else {
				$msg = "❗Выборы прерваны. Причина: оба кандидата набрали одинаковое количество голосов.";
				vk_execute("
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}'});");
				unset($db["goverment"]["referendum"]);
				}
			}
		}

		if(!is_null($data->object->payload)){
			$payload = json_decode($data->object->payload);
			if($payload->command == "referendum_vote"){
				if(is_numeric($payload->vote_candidate_id)){
					if ($payload->vote_candidate_id == 0){
						$msg = "❗Меню голосования закрыто.";
						vk_execute("return API.messages.send({'peer_id':{$data->object->peer_id},'message':'{$msg}','keyboard':'{\\\"one_time\\\":true,\\\"buttons\\\":[]}'});");
						return 0;
					}

					for($i = 0; $i < sizeof($db["goverment"]["referendum"]["all_voters"]); $i++){
						if($db["goverment"]["referendum"]["all_voters"][$i] == $data->object->from_id){
							$msg = ", ⛔вы уже голосовали.";
							vk_execute(bot_make_exeappeal($data->object->from_id)."
								return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
							return 0;
						}
					}

					if($payload->vote_candidate_id == 1){
						$db["goverment"]["referendum"]["all_voters"][] = $data->object->from_id;
						$db["goverment"]["referendum"]["candidate1"]["voters_count"] = $db["goverment"]["referendum"]["candidate1"]["voters_count"] + 1;
						$candidate_id = $db["goverment"]["referendum"]["candidate1"]["id"];
						vk_execute(bot_make_exeappeal($data->object->from_id)."
							var user = API.users.get({'user_ids':[{$candidate_id}]});
							var msg = ', 📝вы проголосовали за @id'+user[0].id+' ('+user[0].first_name+' '+user[0].last_name+')';
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});");

					} elseif ($payload->vote_candidate_id == 2){
						$db["goverment"]["referendum"]["all_voters"][] = $data->object->from_id;
						$db["goverment"]["referendum"]["candidate2"]["voters_count"] = $db["goverment"]["referendum"]["candidate2"]["voters_count"] + 1;
						$candidate_id = $db["goverment"]["referendum"]["candidate2"]["id"];
						vk_execute(bot_make_exeappeal($data->object->from_id)."
							var user = API.users.get({'user_ids':[{$candidate_id}]});
							var msg = ', 📝вы проголосовали за @id'+user[0].id+' ('+user[0].first_name+' '+user[0].last_name+')';
							return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg});");
					}
				}
			}
		}
	}
}

function goverment_referendum_vote($data, &$db){
	if(!is_null($db["goverment"]["referendum"])){
		if($db["goverment"]["referendum"]["candidate1"]["id"] != 0 && $db["goverment"]["referendum"]["candidate2"]["id"] != 0){
			for($i = 0; $i < sizeof($db["goverment"]["referendum"]["all_voters"]); $i++){
				if($db["goverment"]["referendum"]["all_voters"][$i] == $data->object->from_id){
					$msg = ", ⛔вы уже голосовали.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
					return 0;
				}
			}

			$candidate1_id = $db["goverment"]["referendum"]["candidate1"]["id"];
			$candidate2_id = $db["goverment"]["referendum"]["candidate2"]["id"];

			vk_execute(bot_make_exeappeal($data->object->from_id)."
				var users = API.users.get({'user_ids':[{$candidate1_id},{$candidate2_id}]});

				var button_candidate1 = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"📝'+users[0].first_name+' '+users[0].last_name+'\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"1\\\\\"}\\\"},\\\"color\\\":\\\"primary\\\"}';
				var button_candidate2 = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"📝'+users[1].first_name+' '+users[1].last_name+'\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"2\\\\\"}\\\"},\\\"color\\\":\\\"primary\\\"}';
				var button_cancel = '{\\\"action\\\":{\\\"type\\\":\\\"text\\\",\\\"label\\\":\\\"Закрыть\\\",\\\"payload\\\":\\\"{\\\\\"command\\\\\":\\\\\"referendum_vote\\\\\",\\\\\"vote_candidate_id\\\\\":\\\\\"0\\\\\"}\\\"},\\\"color\\\":\\\"negative\\\"}';

				var keyboard = '{\\\"one_time\\\":false,\\\"buttons\\\":[['+button_candidate1+','+button_candidate2+'],['+button_cancel+']]}';

				var msg = ', учавствуй в выборах президента. Просто нажми на кнопку понравившегося тебе кандидата и ты отдашь за него свой голос. Список кандидатов:\\n✅@id'+users[0].id+' ('+users[0].first_name+' '+users[0].last_name+')\\n✅@id'+users[1].id+' ('+users[1].first_name+' '+users[1].last_name+')';

				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+msg,'keyboard':keyboard});
				");
		} else {
			$msg = ", кандидаты еще не набраны. Вы можете балотироваться в президенты, использовав команду \\\"!candidate\\\".";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
		}
	} else {
		$msg = ", сейчас не проходят выборы.";
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});");
	}
}

?>