# Python модули
import time, requests, json, threading, traceback
from radabot.core.manager import ChatModes, UserPermissions

# Части бота
from radabot.core.vk import VK_API, longpoll
from radabot.core.system import SYSTEM_PATHS, Config, ManagerData, PHPCommandIntegration, prestart, write_log
from radabot.bot.main import handle_event

# Инициализация разных данных
Config.read_file()										# Считываем файл config.json
ManagerData.read_file()									# Считываем файл manager.json
UserPermissions.init_default_states()					# Инициализируем стандартные состояния UserPermissions
ChatModes.init_default_states()							# Инициализируем стандартные состояния Chat
PHPCommandIntegration.init()							# Инициализация команд php

# Базовые переменные
vk_api = VK_API(Config.get('VK_GROUP_TOKEN'))			# VK API
event_queue = []										# Очередь событий на обработку
active_workers = []										# Массив потоков обработчика
WORKERS_MAX_COUNT = 1									# Максимальное количество одновременных обработчиков

# def queue_handler():
# 	while True:
# 		for _event in event_queue:
# 			try:
# 				handle_event(vk_api, _event)
# 			except:
# 				trace = traceback.format_exc()
# 				write_log(SYSTEM_PATHS.ERROR_LOG_FILE, trace[:-1])
# 			finally:
# 				event_queue.remove(_event)
# 		time.sleep(0.05)

# Функция обработки события
def event_worker_def(event):
	try:
		handle_event(vk_api, event)
	except:
		trace = traceback.format_exc()
		write_log(SYSTEM_PATHS.ERROR_LOG_FILE, trace[:-1])

# Поток обработки очереди
def queue_handler():
	while True:
		for event_worker in active_workers:
			if not event_worker.is_alive():
				active_workers.remove(event_worker)
				del event_worker

		for event in event_queue:
			if len(active_workers) < WORKERS_MAX_COUNT:
				event_worker = threading.Thread(target=event_worker_def, daemon=False, args=[event])
				active_workers.append(event_worker)
				event_worker.start()
				event_queue.remove(event)
			else:
				break
		time.sleep(0.05)


# Основной код запуска
if __name__ == "__main__":
	prestart()
	write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Radabot is started")
	active = False

	lp_server = ''
	lp_key = ''
	lp_ts = 0

	attempts_count = 1
	while True:
		vk_response_dict = json.loads(vk_api.call('groups.getLongPollServer', {'group_id': Config.get('VK_GROUP_ID')}))
		response_data = vk_response_dict.get('response', None)
		if response_data is not None:
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
		time.sleep(3)
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
				if response_data is not None:
					lp_key = response_data['key']
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "New lp_key received")
				else:
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Failed to receive new lp_key successfully")
				del vk_response_dict
				del response_data
			elif failed == 3:
				vk_response_dict = json.loads(vk_api.call('groups.getLongPollServer', {'group_id': Config.get('VK_GROUP_ID')}))
				response_data = vk_response_dict.get('response', None)
				if response_data is not None:
					lp_key = response_data["key"]
					lp_ts = response_data["ts"]
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "New lp_key & lp_ts received successfully")
				else:
					write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Failed to receive new lp_key & lp_ts")
				del vk_response_dict
				del response_data
			else:
				for event in data_dict["updates"]:
					event_queue.append(event)
				lp_ts = data_dict["ts"]
		except requests.exceptions.ConnectionError:
			write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Connection Error")

	write_log(SYSTEM_PATHS.LONGPOLL_LOG_FILE, "Radabot is stopped")
