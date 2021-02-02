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
					Processes[process_name] = subprocess.Popen(["/usr/bin/php7.0", "radabot-system.php", json.dumps(event).encode('utf-8')])
					EventQueue.remove(event)
			else:
				EventQueue.remove(event)
		time.sleep(0.05)

if __name__ == "__main__":
	log("Radabot is started")
	active = False

	attempts_count = 1
	while True:
		vk_response_dict = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))
		response_data = vk_response_dict.get('response', None)
		if response_data != None:
			lp_server = response_data["server"]
			lp_key = response_data["key"]
			lp_ts = response_data["ts"]
			active = True
			del vk_response_dict
			del response_data
			log("Longpoll data received successfully")
			break
		else:
			log("Attempt #{}: Failed to receive longpoll data".format(attempts_count))
			attempts_count = attempts_count + 1
		if attempts_count > 5:
			break
		time.sleep(0.25)
	del attempts_count

	if active:
		queue_thread = threading.Thread(target=queue_handler, daemon=True)
		queue_thread.start()

	while active:
		try:
			data_text = vk_longpoll(lp_server, lp_key, lp_ts)
			data_dict = json.loads(data_text)
			failed = data_dict.get('failed', None)

			if failed == 1:
				lp_ts = data_dict["ts"]
				log("New lp_ts received successfully")
			elif failed == 2:
				vk_response_dict = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))
				response_data = vk_response_dict.get('response', None)
				if response_data != None:
					lp_key = response_data['key']
					log("New lp_key received")
				else:
					log("Failed to receive new lp_key successfully")
				del vk_response_dict
				del response_data
			elif failed == 3:
				vk_response_dict = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))
				response_data = vk_response_dict.get('response', None)
				if response_data != None:
					lp_key = response_data["key"]
					lp_ts = response_data["ts"]
					log("New lp_key & lp_ts received successfully")
				else:
					log("Failed to receive new lp_key & lp_ts")
				del vk_response_dict
				del response_data
			else:
				for event in data_dict["updates"]:
					EventQueue.append(event)
				lp_ts = data_dict["ts"]
		except requests.exceptions.ConnectionError:
			log("Connection Error")

	log("Radabot is stoped")