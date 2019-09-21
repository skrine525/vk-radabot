<?php

require_once("economy_api.php");

function economy_user_status($data){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);
	$money = strval(economy_get_user_money($db, $data->object->from_id));
	$job = "Без работы";
	$edu = economy_get_user_edu($db, $data->object->from_id);
	if(economy_get_user_job($db, $data->object->from_id) != 0){
		$job = economy_get_jobs_array()[economy_get_user_job($db, $data->object->from_id)]["name"];
	}
	$next_tax_payment_time = $data->object->date - $db["economy"]["users"]["id".$data->object->from_id]["tax"]["last_payment"];
	$next_tax_payment_text = "";
	if($next_tax_payment_time >= 7200){
		$next_tax_payment_text = "Cейчас";
	} else {
		$time_array = economy_get_current_str_time($next_tax_payment_time);
		$hours = 1 - $time_array["h"];
		$minutes = 60 - $time_array["m"];
		$seconds = 60 - $time_array["s"];
		$next_tax_payment_text = "";
		if ($hours != 0) {
			$next_tax_payment_text = $next_tax_payment_text."{$hours} ч. ";
		}
		if ($minutes != 0){
			$next_tax_payment_text = $next_tax_payment_text."{$minutes} мин. ";
		}
		$next_tax_payment_text = $next_tax_payment_text."{$seconds} сек.";
	}
	$msg = ",\\n&#128176;Твой счёт: \${$money}\\n&#128101;Твоя профессия: {$job}\\n&#128218;Твой уровень образования: {$edu}\\n&#128221;Следующая оплата налога: {$next_tax_payment_text}";
	vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_work($data){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);
	if(economy_get_user_job($db, $data->object->from_id) != 0){
		$time = $data->object->date - economy_get_user_lwt($db, $data->object->from_id);
		if($time >= economy_get_user_ljrt($db, $data->object->from_id)){
			$jobs = economy_get_jobs_array();
			$max_salary = economy_get_job_salary($db, economy_get_user_job($db, $data->object->from_id));
			$max_res = $jobs[economy_get_user_job($db, $data->object->from_id)]["res"]+($jobs[economy_get_user_job($db, $data->object->from_id)]["res_levelup"]*(economy_get_job_level($db, economy_get_user_job($db, $data->object->from_id))-1));
			$salary = mt_rand($max_salary-5, $max_salary);
			$res = mt_rand($max_res-5, $max_res);
			if(economy_gov_money_to_user($db, $data->object->from_id, $salary)){
				economy_gov_res_add($db, $jobs[economy_get_user_job($db, $data->object->from_id)]["res_type"], $res);
				$res_name = economy_get_res_name($jobs[economy_get_user_job($db, $data->object->from_id)]["res_type"]);
				$msg = ", ты заработал \${$salary} и произвел {$res} ресурсов ({$res_name}).";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
				economy_set_user_lwt($db, $data->object->from_id, $data->object->date);
				economy_set_user_ljrt($db, $data->object->from_id, economy_get_jobs_array()[economy_get_user_job($db, $data->object->from_id)]["rest_time"]);
			} else {
				$msg = ", у нашего государства нет денег, чтобы выплатить тебе зарплату!";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
			}
		} else {
			$jobs = economy_get_jobs_array();
			$minutes = economy_get_current_str_time(economy_get_user_ljrt($db, $data->object->from_id))["m"] - economy_get_current_str_time($time)["m"] - 1;
			if($munutes < 0){
				$minutes = 0;
			}
			$seconds = 60 - economy_get_current_str_time($time)["s"];
			$msg = ", ты слишком устал! Приходи через ".$minutes." мин. ".$seconds." сек.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		}
	} else {
		$msg = ", ты безработный! Чтобы устроиться на работу, используй \\'!устроиться\\'.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_gov_status($data){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);
	if($db["president_id"] == $data->object->from_id || $db["parliament_id"] == $data->object->from_id){
		$money = $db["economy"]["gov_money"];
		$res_product = economy_get_gov_res($db, "product");
		$res_services = economy_get_gov_res($db, "services");
		$res_edu = economy_get_gov_res($db, "edu");
		$msg = ",\\n&#128176;Казна государства: \${$money}\\n\\n&#128295;Ресурсы государства:\\n&#9989;Продукты: {$res_product}\\n&#9989;Услуги: {$res_services}\\n&#9989;Образование: {$res_edu}";
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
	} else {
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', у тебя нет прав для использование этой команды.'});
				");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_jobs_list($data, $words){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);

	if(is_null($words[1])){
		$msg = ", профессии:";
		$jobs = economy_get_jobs_array();
		for($i = 1; $i < count($jobs); $i++){
			$busy_workplaces = economy_get_job_busy_workplaces($db, $i);
			$max_workplaces = economy_get_job_workplaces($db, $i);
			$msg = $msg."\\n&#9989;".$jobs[$i]["name"]." ({$busy_workplaces}/{$max_workplaces})";
		}
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			return 'ok';
			");
	} else {
		$jobs = economy_get_jobs_array();
		$job_id = economy_get_job_id($words[1]);
		$rest_time = economy_get_current_str_time($jobs[$job_id]["rest_time"]);
		$res_type = economy_get_res_name($jobs[$job_id]["res_type"]);
		if($job_id != 0){
			$msg = ", информация:\\n&#128204;Название: ".$jobs[$job_id]["name"]."\\n&#128178;Зарплата: ".economy_get_job_salary($db, $job_id)."\\n&#128218;Образование: ".$jobs[$job_id]["edu"]."\\n&#128142;Тип ресурса: {$res_type}\\n&#128295;Ресурсы: {$jobs[$job_id]["res"]}\\n&#8987;Время отдыха: ".$rest_time["m"]." мин. ".$rest_time["s"]." сек.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		} else {
			$msg = ", такой профессии нет!";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		}
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_select_job($data, $words){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);
	if(!is_null($words[1])){
		$job_id = economy_get_job_id($words[1]);
		if($job_id != 0){
			if(economy_get_jobs_array()[$job_id]["edu"] <= economy_get_user_edu($db, $data->object->from_id)){
				if (economy_occupy_job_workplace($db, $job_id)){
					$job = economy_get_jobs_array()[$job_id]["name"];
					economy_release_job_workplace($db, economy_get_user_job($db, $data->object->from_id));
					economy_set_user_job($db, $data->object->from_id, $job_id);
					$msg = ", ты устроился на профессию {$job}.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
						");
				} else {
					$msg = ", на выбранной профессии нет свободных рабочих мест.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
				}
			} else {
				$job = economy_get_jobs_array()[$job_id]["name"];
				$msg = ", вы недостаточно образованы для профессии {$job}.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
			}
		} else {
			$msg = ", такой профессии нет. Используй \\'!профессии\\' для отображения професиий!";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		}
	} else {
		$msg = ", ты не указал профессию. Используй \\'!профессии\\' для отображения професиий!";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_tax_payment_notification(&$db, $data){
	$last_tax_payment = $db["economy"]["users"]["id".$data->object->from_id]["tax"]["last_payment"];
	if(($data->object->date - $last_tax_payment) > 7200){
		if($db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] == -1 ){
			$db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] = 3;
		} else {
			$db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] = $db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] - 1;
		}

		if($db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] == 0){
			$db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] = 3;
			$msg = ", пока. Нужно вовремя платить налоги.";
			$chat_id = $data->object->peer_id-2000000000;
			$user_id = $data->object->from_id;
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				API.messages.removeChatUser({'chat_id':{$chat_id},'user_id':{$user_id}});
				return 'ok';
				");
		} else {
			$penalty = $db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"];
			$tax = $db["economy"]["gov_tax"];
			$word_message = "сообщения";
			if($penalty == 1){
				$word_message = "сообщение";
			}
			$msg = ", время платить налоги. Чтобы оплатить налог, напишите \\'!налог\\'. В случае если вы не оплатите налог, мы будем вынуждены вас отправить в тюрьму. Ты будешь отправлен в тюрьму через {$penalty} {$word_message}. Стоимость налога: \${$tax}.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		}
	}
}

function economy_tax_pay($data){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	$last_tax_payment = $db["economy"]["users"]["id".$data->object->from_id]["tax"]["last_payment"];
	economy_check_user($db, $data->object->from_id);
	if(($data->object->date - $last_tax_payment) > 7200){
		if(economy_user_money_to_gov($db, $data->object->from_id, $db["economy"]["gov_tax"])){
			$msg = ", вы успешно оплатили налог. Следующий налог через 2 часа";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
			$db["economy"]["users"]["id".$data->object->from_id]["tax"]["last_payment"] = $data->object->date;
			$db["economy"]["users"]["id".$data->object->from_id]["tax"]["penalty"] = -1;
		} else {
			$msg = ", у тебя нет денег. Иди поработай хоть. Команда для работы: \\'!работать\\'.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
		}
	} else {
		$msg = ", тебе еще не нужно оплачивать налог.";
			vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_tax($data, $words){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);

	if (!is_null($words[1])){
			if($db["president_id"] == $data->object->from_id || $db["parliament_id"] == $data->object->from_id){
				$tax = abs(intval($words[1]));
				economy_set_gov_tax($db, $tax);
				$msg = ", налог установлен на \${$tax}.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
			} else {
				$tax = economy_get_gov_tax($db);
				$msg = ",\\nТекущий налог: \${$tax}.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
				");
			}
	} else {
		$tax = economy_get_gov_tax($db);
		$msg = ",\\nТекущий налог: \${$tax}.";
		vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

function economy_resources($data, $words){
	$db = mlab_getDocument("chat".$data->object->peer_id."_govset");
	if(!bot_check_gov($db)){
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', данная беседа не зарегистрирована как государство!'});
				");
		return 'error';
	}
	economy_check_db($db);
	economy_check_user($db, $data->object->from_id);

	if($db["president_id"] == $data->object->from_id || $db["parliament_id"] == $data->object->from_id){
		if(!is_null($words[1]) && !is_null($words[2])){
			if($words[1] == "продать"){
				$res_count = intval($words[2]);
				if(economy_gov_res_delete($db, $res_count)){
					$res_price = $res_count * economy_get_res_price();
					economy_gov_money_add($db, $res_price);
					$word_resources = "ресурсов";
					if ($res_count == 1){
						$word_resources = "ресурс";
					} elseif ($res_count == 2 || $res_count == 3 || $res_count == 4) {
						$word_resources = "ресурса";
					}
					$msg = ", вы продали {$res_count} {$word_resources} за \${$res_price}.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
						");
				} else {
					$msg = ", у государства недостаточно ресурсов.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
						");
				}
			} elseif ($words[1] == "купить"){
				$res_count = intval($words[2]);
				$res_price = $res_count * economy_get_res_price();
				if(economy_gov_money_delete($db, $res_price)){
					economy_gov_res_add($db, $res_count);
					$word_resources = "ресурсов";
					if ($res_count == 1){
						$word_resources = "ресурс";
					} elseif ($res_count == 2 || $res_count == 3 || $res_count == 4) {
						$word_resources = "ресурса";
					}
					$msg = ", вы купили {$res_count} {$word_resources} за \${$res_price}.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
						");
				} else {
					$msg = ", у государства недостаточно денег.";
					vk_execute(bot_make_exeappeal($data->object->from_id)."
						return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
						");
				}
			} else {
				$msg = ", используйте \\'!ресурсы купить/продать <количесво>\\'.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
					return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
					");
			}
		} else {
			$msg = ", используйте \\'!ресурсы купить/продать <количесво>\\'.";
				vk_execute(bot_make_exeappeal($data->object->from_id)."
			return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+'{$msg}'});
			");
		}
	} else {
		vk_execute(bot_make_exeappeal($data->object->from_id)."
				return API.messages.send({'peer_id':{$data->object->peer_id},'message':appeal+', у тебя нет прав для использование этой команды.'});
				");
	}

	mlab_updateDocument("chat".$data->object->peer_id."_govset", $db);
}

?>