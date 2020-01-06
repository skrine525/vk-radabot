import subprocess
import time

IS_STARTED = False
LAST_STARTING_TIME = 0

START_TIME = 1577700000
STOP_TIME = START_TIME + 345600

while True:
	if(not IS_STARTED):
		if(time.time() >= START_TIME):
			IS_STARTED = True
			LAST_STARTING_TIME = time.time()
			subprocess.call(["/usr/bin/php", "php-handler.php", "start"])
		else:
			time.sleep(1)
	else:
		if(time.time() >= STOP_TIME):
			subprocess.call(["/usr/bin/php", "php-handler.php", "stop"])
			break
		else:
			if(time.time() - LAST_STARTING_TIME >= 10):
				LAST_STARTING_TIME = time.time()
				subprocess.call(["/usr/bin/php", "php-handler.php", "start"])
			time.sleep(1)
