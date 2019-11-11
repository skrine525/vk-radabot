import requests
import json
import base64
import subprocess

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

longpoll_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
ts = longpoll_data["ts"]

while True:
	data = json.loads(vk_longpoll(longpoll_data, ts))
	failed = data.get('failed', None)

	if(failed == 1):
		ts = data["ts"]
	elif(failed == 2 or failed == 3):
		longpoll_data = json.loads(vk_call('groups.getLongPollServer', {'group_id': config_get('VK_GROUP_ID')}))["response"]
		ts = longpoll_data["ts"]
	else:
		base64_updates = base64.b64encode(bytes(json.dumps(data["updates"]).encode('utf-8')))
		subprocess.call(["/usr/bin/php", "handle-php-bot-core-request.php", base64_updates])
		ts = data["ts"]