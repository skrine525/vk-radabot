<?php

function economy_check_db(&$db){
	if (is_null($db["economy"])){
		$array = array('gov_money' => 1000,
				'gov_tax' => 5 //Налог
				);
		$db["economy"] = $array;
	}
}

function economy_check_user(&$db, $user_id){
	if(is_null($db["economy"]["users"]["id".$user_id])){
		$db["economy"]["users"]["id".$user_id] = array('money' => 0,
		'job' => 0,
		'last_work_time' => 0,
		'last_job_rest_time' => 0,
		'edu' => 0,
		'own' => array(),
		'tax' => array('last_payment' => 0, 'penalty' => -1)

	);
	}
}

function economy_gov_money_add(&$db, $count){
	$db["economy"]["gov_money"] = $db["economy"]["gov_money"] + $count;
}

function economy_gov_money_delete(&$db, $count){
	if($db["economy"]["gov_money"] - $count >= 0){
		$db["economy"]["gov_money"] = $db["economy"]["gov_money"] - $count;
		return true;
	} else {
		return false;
	}
}

function economy_gov_res_add(&$db, $res_type, $count){
	$db["economy"]["gov_resources"][$res_type] = $db["economy"]["gov_resources"][$res_type] + $count;
}

function economy_gov_res_delete(&$db, $res_type, $count){
	if($db["economy"]["gov_resources"][$res_type] - $count >= 0){
		$db["economy"]["gov_resources"][$res_type] = $db["economy"]["gov_resources"][$res_type] - $count;
		return true;
	} else {
		return false;
	}
}

function economy_get_gov_res($db, $res_type){
	if(!is_null($db["economy"]["gov_resources"][$res_type])){
		return $db["economy"]["gov_resources"][$res_type];
	} else {
		return 0;
	}
}

function economy_user_money_add(&$db, $user_id, $count){
	$db["economy"]["users"]["id".$user_id]["money"] = $db["economy"]["users"]["id".$user_id]["money"] + $count;
}

function economy_user_money_delete(&$db, $user_id, $count){
	if($db["economy"]["users"]["id".$user_id]["money"] - $count >= 0){
		$db["economy"]["users"]["id".$user_id]["money"] = $db["economy"]["users"]["id".$user_id]["money"] - $count;
		return true;
	} else {
		return false;
	}
}

function economy_user_transfer(&$db, $user1_id, $user2_id, $count){
	if (economy_user_money_delete($db, $user1_id, $count)){
		economy_user_money_add($db, $user2_id, $count);
		return true;
	} else {
		return false;
	}
}

function economy_get_gov_money($db){
	return $db["economy"]["gov_money"];
}

function economy_get_user_money($db, $user_id){
	return $db["economy"]["users"]["id".$user_id]["money"];
}

function economy_get_user_job($db, $user_id){
	return $db["economy"]["users"]["id".$user_id]["job"];
}
function economy_set_user_job(&$db, $user_id, $job){
	if(!is_null(economy_get_jobs_array()[$job])){
		$db["economy"]["users"]["id".$user_id]["job"] = $job;
		return true;
	} else {
		return false;
	}
}

function economy_get_user_lwt($db, $user_id){
	return $db["economy"]["users"]["id".$user_id]["last_work_time"];
}

function economy_set_user_lwt(&$db, $user_id, $time){
	$db["economy"]["users"]["id".$user_id]["last_work_time"] = $time;
}

function economy_get_current_str_time($timestamp){
	$hours = intdiv($timestamp, 3600);
	$minutes = intdiv(($timestamp - $hours*3600), 60);
	$seconds = $timestamp - ($minutes * 60) - ($hours * 3600);
	return array("h" => $hours, "m" => $minutes, "s" => $seconds);
}

function economy_get_job_salary($db, $job_id){
	if (!is_null($db["economy"]["job_params"][$job_id]["salary"])){
		return $db["economy"]["job_params"][$job_id]["salary"];
	} else {
		return economy_get_jobs_array()[$job_id]["base_salary"];
	}
}

function economy_set_job_salary(&$db, $job_id, $salary){
	if ($salary >= 10){
		$db["economy"]["job_params"][$job_id]["salary"] = $salary;
		return true;
	} else {
		return false;
	}
}

function economy_get_job_level($db, $job_id){
	if (!is_null($db["economy"]["job_params"][$job_id]["level"])){
		return $db["economy"]["job_params"][$job_id]["level"];
	} else {
		return 1;
	}
}

function economy_set_job_level(&$db, $job_id, $level){
	$db["economy"]["job_params"][$job_id]["level"] = $level;;
}

function economy_user_money_to_gov(&$db, $user_id, $count){
	if(economy_user_money_delete($db, $user_id, $count)){
		economy_gov_money_add($db, $count);
		return true;
	} else {
		return false;
	}
}

function economy_gov_money_to_user(&$db, $user_id, $count){
	if(economy_gov_money_delete($db, $count)){
		economy_user_money_add($db, $user_id, $count);
		return true;
	} else {
		return false;
	}
}

function economy_get_user_ljrt($db, $user_id){
	return $db["economy"]["users"]["id".$user_id]["last_job_rest_time"];
}

function economy_set_user_ljrt(&$db, $user_id, $time){
	$db["economy"]["users"]["id".$user_id]["last_job_rest_time"] = $time;
}

function economy_set_gov_tax(&$db, $count){
	$db["economy"]["gov_tax"] = $count;
}

function economy_get_gov_tax($db){
	return $db["economy"]["gov_tax"];
}

function economy_get_user_edu($db, $user_id){
	return $db["economy"]["users"]["id".$user_id]["edu"];
}

function economy_add_user_edu(&$db, $user_id, $value){
	$db["economy"]["users"]["id".$user_id]["edu"] = $db["economy"]["users"]["id".$user_id]["edu"] + $value;
}

function economy_get_job_workplaces($db, $job_id){
	if (!is_null($db["economy"]["job_params"][$job_id]["workplaces"])){
		return $db["economy"]["job_params"][$job_id]["workplaces"];
	} else {
		return economy_get_jobs_array()[$job_id]["base_workplaces"];
	}
}

function economy_set_job_workplaces(&$db, $job_id, $value){
	$db["economy"]["job_params"][$job_id]["workplaces"] = $value;
}

function economy_get_job_busy_workplaces($db, $job_id){
	if (!is_null($db["economy"]["job_params"][$job_id]["busy_workplaces"])){
		return $db["economy"]["job_params"][$job_id]["busy_workplaces"];
	} else {
		return 0;
	}
}

function economy_occupy_job_workplace(&$db, $job_id){
	$max_workplaces = economy_get_job_workplaces($db, $job_id);
	if ($db["economy"]["job_params"][$job_id]["busy_workplaces"] + 1 <= $max_workplaces){
		$db["economy"]["job_params"][$job_id]["busy_workplaces"] = $db["economy"]["job_params"][$job_id]["busy_workplaces"] + 1;
		return true;
	} else {
		return false;
	}
}

function economy_release_job_workplace(&$db, $job_id){
	$db["economy"]["job_params"][$job_id]["busy_workplaces"] = $db["economy"]["job_params"][$job_id]["busy_workplaces"] - 1;
	if($db["economy"]["job_params"][$job_id]["busy_workplaces"] < 0) {
		$db["economy"]["job_params"][$job_id]["busy_workplaces"] = 0;
	}
}

?>