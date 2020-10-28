from datetime import datetime
import time
import requests
import json
import subprocess
import threading

VK_VERSION = 5.84

def log(text):
	f = open("radabot.log", "a")
	ny_time = time.time() - 14400
	ny_date = datetime.utcfromtimestamp(ny_time).strftime("%d-%b-%Y %X America/New_York")
	f.write("[{}] {}\n".format(ny_date, text))
	f.close()

def config_get(name):
	f = open("../bot/data/config.json")
	env = json.loads(f.read())
	f.close()
	return env.get(name, None)

def vk_call(method, parametres):
	headers = {'Content-type': 'application/x-www-form-urlencoded'}
	parametres["access_token"] = config_get("VK_GROUP_TOKEN")
	parametres["v"] = VK_VERSION
	r = requests.post("https://api.vk.com/method/{}".format(method), data=parametres, headers=headers)
	return r.text

def vk_longpoll(server, key, ts, wait = 25):
	parametres = {'act': 'a_check', 'key': key, 'ts': ts, 'wait': wait}
	r = requests.post(server, data=parametres)
	return r.text

Processes = {}
EventQueue = []

def queue_handler():
	while True:
		for i in Processes.copy():
			if Processes[i].poll() != None:
				Processes.pop(i)

		for event in EventQueue:
			if event["type"] == "message_new" or event["type"] == "message_event":
				peer_id = event["object"]["peer_id"]
				process_name = "chat{}".format(peer_id)
				process = Processes.get(process_name)
				if process == None:
					Processes[process_name] = subprocess.Popen(["/usr/bin/php", "radabot-system.php", json.dumps(event).encode('utf-8')])
					EventQueue.remove(event)
			else:
				EventQueue.remove(event)
		time.sleep(0.05)


lp_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
lp_server = lp_data["server"]
lp_key = lp_data["key"]
lp_ts = lp_data["ts"]
del lp_data

queue_thread = threading.Thread(target=queue_handler)
queue_thread.start()

log("Radabot is started")

while True:
	try:
		data_text = vk_longpoll(lp_server, lp_key, lp_ts)
		data = json.loads(data_text)
		failed = data.get('failed', None)

		if(failed == 1):
			lp_ts = data["ts"]
			log("VK Failed 1")
		elif(failed == 2):
			lp_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
			lp_key = lp_data["key"]
			del lp_data
			log("VK Failed 2")
		elif(failed == 3):
			lp_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
			lp_key = lp_data["key"]
			lp_ts = lp_data["ts"]
			del lp_data
			log("VK Failed 3")
		else:
			for event in data["updates"]:
				EventQueue.append(event)
			lp_ts = data["ts"]
	except requests.exceptions.ConnectionError:
		log("Connection Error")