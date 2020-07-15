import requests
import json
import base64
import subprocess
import threading
import time

VK_VERSION = 5.84

def config_get(name):
	f = open("../bot/data/config.json")
	env = json.loads(f.read())
	f.close()
	return env.get(name, None)

def vk_call(method, parametres):
	headers = {'Content-type': 'application/x-www-form-urlencoded'}
	parametres["access_token"] = config_get("VK_GROUP_TOKEN")
	parametres["v"] = VK_VERSION
	r = requests.post("https://api.vk.com/method/"+method, data=parametres, headers=headers)
	return r.text

def vk_longpoll(data, ts, wait = 25):
	parametres = {'act': 'a_check', 'key': data['key'], 'ts': ts, 'wait': wait}
	r = requests.post(data["server"], data=parametres)
	return r.text

Processes = {}
Queue = []

def queue_handler():
	while True:
		for i in Processes.copy():
			if Processes[i].poll() != None:
				Processes.pop(i)

		for update in Queue:
			if update["type"] == "message_new" or update["type"] == "message_event":
				peer_id = update["object"]["peer_id"]
				process_name = "message{peer_id}".format(peer_id=peer_id)
				process = Processes.get(process_name)
				if process == None:
					base64_update = base64.b64encode(bytes(json.dumps(update).encode('utf-8')))
					Processes[process_name] = subprocess.Popen(["/usr/bin/php", "php-handler.php", base64_update])
					Queue.remove(update)
			else:
				Queue.remove(update)
		time.sleep(0.05)


longpoll_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
ts = longpoll_data["ts"]

queue_thread = threading.Thread(target=queue_handler)
queue_thread.start()

while True:
	data = json.loads(vk_longpoll(longpoll_data, ts))
	failed = data.get('failed', None)

	if(failed == 1):
		ts = data["ts"]
	elif(failed == 2 or failed == 3):
		longpoll_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
		ts = longpoll_data["ts"]
	else:
		for update in data["updates"]:
			Queue.append(update)
		ts = data["ts"]