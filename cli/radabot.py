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

# Delete
def log(text):
	f = open("python-log.log", "a")
	f.write(text)
	f.close()

while True:
	try:
		data_text = vk_longpoll(lp_server, lp_key, lp_ts)
		data = json.loads(data_text)
		log("[{}] Log: {}".format(time.ctime(time.time()), data_text.encode('utf-8')))
		failed = data.get('failed', None)

		if(failed == 1):
			lp_ts = data["ts"]
		elif(failed == 2):
			lp_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
			lp_key = lp_data["key"]
			del lp_data
		elif(failed == 3):
			lp_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
			lp_key = lp_data["key"]
			lp_ts = lp_data["ts"]
			del lp_data
		else:
			for event in data["updates"]:
				EventQueue.append(event)
			lp_ts = data["ts"]
	except requests.exceptions.ConnectionError:
		log("[{}] Log: {}\n".format(time.ctime(time.time()), "Connection Error"))