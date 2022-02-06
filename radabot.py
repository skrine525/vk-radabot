# Python модули
from multiprocessing import Manager
import time, requests, json, threading, traceback
from radabot.core.manager import ChatUserPermission, UserPermission

# Части бота
from radabot.core.vk import VK_API, longpoll
from radabot.core.system import SYSTEM_PATHS, Config, ManagerData, prestart, write_log
from radabot.bot.main import handle_event

# Инициализация разных данных
Config.readFile()										# Считываем файл config.json
ManagerData.readFile()									# Считываем файл manager.json
UserPermission.initConstants()							# Инициализируем константы ChatUserPermission

# Базовые переменные
vk_api = VK_API(Config.get('VK_GROUP_TOKEN'))

Processes = {}
EventQueue = []

def queue_handler():
	while True:
		"""
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
		"""
		for event in EventQueue:
			try:
				handle_event(vk_api, event)
			except Exception:                                              
				trace = traceback.format_exc()
				write_log(SYSTEM_PATHS.ERROR_LOG_FILE, trace[:-1])
			finally:
				EventQueue.remove(event)
		time.sleep(0.05)

# Основной код запуска
if __name__ == "__main__":
	prestart()
	write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Radabot is started")
	active = False

	attempts_count = 1
	while True:
		vk_response_dict = json.loads(vk_api.call('groups.getLongPollServer', {'group_id': Config.get('VK_GROUP_ID')}))
		response_data = vk_response_dict.get('response', None)
		if response_data != None:
			lp_server = response_data["server"]
			lp_key = response_data["key"]
			lp_ts = response_data["ts"]
			active = True
			del vk_response_dict
			del response_data
			write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Longpoll data received successfully")
			break
		else:
			write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Attempt #{}: Failed to receive longpoll data".format(attempts_count))
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
			data_text = longpoll(lp_server, lp_key, lp_ts)
			data_dict = json.loads(data_text)
			failed = data_dict.get('failed', None)

			if failed == 1:
				lp_ts = data_dict["ts"]
				write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "New lp_ts received successfully")
			elif failed == 2:
				vk_response_dict = json.loads(vk_api.call('groups.getLongPollServer', {'group_id': Config.get('VK_GROUP_ID')}))
				response_data = vk_response_dict.get('response', None)
				if response_data != None:
					lp_key = response_data['key']
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "New lp_key received")
				else:
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Failed to receive new lp_key successfully")
				del vk_response_dict
				del response_data
			elif failed == 3:
				vk_response_dict = json.loads(vk_api.call('groups.getLongPollServer', {'group_id': Config.get('VK_GROUP_ID')}))
				response_data = vk_response_dict.get('response', None)
				if response_data != None:
					lp_key = response_data["key"]
					lp_ts = response_data["ts"]
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "New lp_key & lp_ts received successfully")
				else:
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Failed to receive new lp_key & lp_ts")
				del vk_response_dict
				del response_data
			else:
				for event in data_dict["updates"]:
					EventQueue.append(event)
				lp_ts = data_dict["ts"]
		except requests.exceptions.ConnectionError:
			write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Connection Error")

	write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Radabot is stoped")