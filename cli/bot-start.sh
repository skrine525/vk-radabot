#!/bin/sh
nohup php -f handler.php &
$! > handler_pid.txt