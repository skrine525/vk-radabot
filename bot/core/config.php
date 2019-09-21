<?php

define('BOT_CONFIG_FILE_PATH', "../bot/data/config.json");

function config_get($name){
    $env = json_decode(file_get_contents(BOT_CONFIG_FILE_PATH), true);
    if($env == false){
    	error_log("Unable to read config.json file. File not exists or invalid.");
        return null;
    }

    return $env[$name];
}

?>