<?php

function economy_get_jobs_array(){
	$array = array(
		array('name' => 'Без работы'),
		array('name' => 'Рабочий', 'rest_time' => 120, 'res' => 20, 'res_type' => "product", 'base_salary' => 10, "edu" => 0, 'max_level' => 5, 'levelup_price' => 50000, 'levelup_res' => 10, 'base_workplaces' => 2),
		array('name' => 'Дворник', 'rest_time' => 120, 'res' => 20, 'res_type' => "services", 'base_salary' => 10, "edu" => 0, 'max_level' => 5, 'levelup_price' => 50000, 'levelup_res' => 10, 'base_workplaces' => 2),
		array('name' => 'Сварщик', 'rest_time' => 900, 'res' => 50, 'res_type' => "product", 'base_salary' => 60, "edu" => 250, 'max_level' => 6, 'levelup_price' => 50000, 'levelup_res' => 5, 'base_workplaces' => 1),
		array('name' => 'Учитель', 'rest_time' => 900, 'res' => 30, 'res_type' => "edu", 'base_salary' => 50, "edu" => 500, 'max_level' => 4, 'levelup_price' => 150000, 'levelup_res' => 5, 'base_workplaces' => 0),
		array('name' => 'Програмист', 'rest_time' => 1200, 'res' => 55, 'res_type' => "services", 'base_salary' => 120, "edu" => 550, 'max_level' => 6, 'levelup_price' => 200000, 'levelup_res' => 12, 'base_workplaces' => 1),
		array('name' => 'Биотехнолог', 'rest_time' => 900, 'res' => 77, 'res_type' => "product", 'base_salary' => 70, "edu" => 700, 'max_level' => 4, 'levelup_price' => 200000, 'levelup_res' => 20, 'base_workplaces' => 1),
		array('name' => 'Инженер', 'rest_time' => 1200, 'res' => 50, 'res_type' => "edu", 'base_salary' => 50, "edu" => 500, 'max_level' => 5, 'levelup_price' => 150000, 'levelup_res' => 5, 'base_workplaces' => 1)
		//array('name' => 'Токарь-Механик', 'rest_time' => 300, 'res' => 15, 'base_salary' => 10, "edu" => 50, 'res_levelup' => 5, 'levelup_price' => 50000),
		//array('name' => 'Шахтёр', 'rest_time' => 600, 'res' => 30, 'base_salary' => 20, "edu" => 100, 'res_levelup' => 5, 'levelup_price' => 50000)
	);
	return $array;
}

function economy_get_job_id($name){
	$jobs = economy_get_jobs_array();
	mb_internal_encoding("UTF-8");
	for($i = 1; $i < count($jobs); $i++){
		if(mb_strtoupper($name) == mb_strtoupper($jobs[$i]["name"])){
			return $i;
		}
	}
	return 0;
}

function economy_get_res_name($res_type){
	switch ($res_type) {
		case 'product':
		return "Продукты";
		break;

		case 'edu':
		return "Образование";
		break;

		case 'services':
		return "Услуги";
		break;
	}
}

function economy_get_res_types(){
	$array = array('product', 'edu', 'services');
	return $array;
}

function economy_get_res_price($res_type){
	switch ($res_type) {
		case 'product':
		return 1;
		break;

		case 'edu':
		return 2;
		break;

		case 'services':
		return 1;
		break;
	}
}

?>