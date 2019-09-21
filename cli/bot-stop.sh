#!/bin/sh
kill processid php -f handler.php
$! >> handler_pid.txt