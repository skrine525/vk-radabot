import subprocess, json, time
import radabot.core.bot as bot
from radabot.core.io import ChatEventManager, ChatOutput
from radabot.core.vk import VKVariable, callback_button, keyboard, keyboard_inline

def handle_event(vk_api, event):
	manager = ChatEventManager(vk_api, event)

	manager.addMessageCommand("!стата", StatsCMD.main)

	manager.addMessageCommand("!тест", test, args=['Сука'])
	manager.addMessageCommand("!тест2", test2)
	manager.addMessageCommand('!error', error)

	initcmd_php(manager)
	manager.handle()

# Команда !стата
class StatsCMD:
	# Стандартное состояние параметров статистики
	STATS_DEFAULT = {
		'msg_count': 0,
		'msg_count_in_succession': 0,
		'simbol_count': 0,
		'audio_msg_count': 0,
		'photo_count': 0,
		'audio_count': 0,
		'video_count': 0,
		'sticker_count': 0,
		# Статистика команд
		'command_used_count': 0,
		'button_pressed_count': 0
	}

	@staticmethod
	def main(callin: ChatEventManager.CallbackInputObject):
		event = callin.event
		args = callin.args
		db = callin.db
		output = callin.output

		member_id = args.int(2, 0)
		if(member_id <= 0):
			member_id = event.bunch.object.from_id
		if('fwd_messages' in event.bunch.object):
			try:
				member_id = event.bunch.object.fwd_messages[0].from_id
			except IndexError:
				pass

		if(member_id <= 0):
			pass

		subcommand = args.str(1, '').lower()
		if(subcommand == 'дня'):
			current_time = time.time()										# Переменная текущего времени
			current_day = int(current_time - (current_time % 86400));			# Переменная текущей даты (00:00 GMT)

			if(member_id == event.bunch.object.from_id):
				pre_msg = "Cтатистика дня:"
			else:
				pre_msg = "Стастика дня @id{} (пользователя):".format(member_id)

			chats_collection = db['chats']
			projection = {'_id': 0, 'chat_stats.users_daily.time{}'.format(current_day): 1}
			result = chats_collection.find_one(bot.get_chat_db_query(event.bunch.object.peer_id), projection=projection)
			extractor = bot.ValueExtractor(result)

			all_stats = extractor.get('chat_stats.users_daily.time{}'.format(current_day), {})
			stats = extractor.get('chat_stats.users_daily.time{}.id{}'.format(current_day, member_id).format(member_id), {})
			stats = {**StatsCMD.STATS_DEFAULT, **stats}

			rating = []
			for k, v in all_stats.items():
				u = {**StatsCMD.STATS_DEFAULT, **v}
				rating.append({'u': k, 'v': u['msg_count'] - u['msg_count_in_succession']})
			rating.sort(reverse=True, key=lambda e: e['v'])

			position = 0
			for user in rating:
				position += 1
				if(user['u'] == "id{}".format(member_id)):
					break
			rating_text = "{} место".format(position)
			
			basic_info = "\n📧Сообщений: {msg_count}\n&#12288;📝Подряд: {msg_count_in_succession}\n🔍Символов: {simbol_count}\n📟Гол. сообщений: {audio_msg_count}"
			attachment_info = "\n\n📷Фотографий: {photo_count}\n📹Видео: {video_count}\n🎧Аудиозаписей: {audio_count}\n🤡Стикеров: {sticker_count}"
			cmd_info = "\n\n🛠Команд выполнено: {command_used_count}\n🔘Нажато кнопок: {button_pressed_count}"
			rating_info = "\n\n👑Активность: {rating_text}"
			
			info = pre_msg + basic_info + attachment_info + cmd_info + rating_info
			info = info.format(msg_count=stats['msg_count'], msg_count_in_succession=stats['msg_count_in_succession'], 
								simbol_count=stats['simbol_count'], audio_msg_count=stats['audio_msg_count'],
								photo_count=stats['photo_count'], video_count=stats['video_count'],
								audio_count=stats['audio_count'], sticker_count=stats['sticker_count'],
								command_used_count=stats['command_used_count'], button_pressed_count=stats['button_pressed_count'],
								rating_text=rating_text)

			StatsCMD.print_info(output, info, event.bunch.object.from_id, True)
		elif(subcommand == ''):
			chats_collection = db['chats']
			projection = {'_id': 0, 'chat_stats.users': 1}
			result = chats_collection.find_one(bot.get_chat_db_query(event.bunch.object.peer_id), projection=projection)
			extractor = bot.ValueExtractor(result)

			if(member_id == event.bunch.object.from_id):
				pre_msg = "Cтатистика:"
			else:
				pre_msg = "Стастика @id{} (пользователя):".format(member_id)

			all_stats = extractor.get('chat_stats.users', {})
			stats = extractor.get('chat_stats.users.id{}'.format(member_id), {})
			stats = {**StatsCMD.STATS_DEFAULT, **stats}

			rating = []
			for k, v in all_stats.items():
				u = {**StatsCMD.STATS_DEFAULT, **v}
				rating.append({'u': k, 'v': u['msg_count'] - u['msg_count_in_succession']})
			rating.sort(reverse=True, key=lambda e: e['v'])

			position = 0
			for user in rating:
				position += 1
				if(user['u'] == "id{}".format(member_id)):
					break
			rating_text = "{} место".format(position)
			
			basic_info = "\n📧Сообщений: {msg_count}\n&#12288;📝Подряд: {msg_count_in_succession}\n🔍Символов: {simbol_count}\n📟Гол. сообщений: {audio_msg_count}"
			attachment_info = "\n\n📷Фотографий: {photo_count}\n📹Видео: {video_count}\n🎧Аудиозаписей: {audio_count}\n🤡Стикеров: {sticker_count}"
			cmd_info = "\n\n🛠Команд выполнено: {command_used_count}\n🔘Нажато кнопок: {button_pressed_count}"
			rating_info = "\n\n👑Активность: {rating_text}"
			
			info = pre_msg + basic_info + attachment_info + cmd_info + rating_info
			info = info.format(msg_count=stats['msg_count'], msg_count_in_succession=stats['msg_count_in_succession'], 
								simbol_count=stats['simbol_count'], audio_msg_count=stats['audio_msg_count'],
								photo_count=stats['photo_count'], video_count=stats['video_count'],
								audio_count=stats['audio_count'], sticker_count=stats['sticker_count'],
								command_used_count=stats['command_used_count'], button_pressed_count=stats['button_pressed_count'],
								rating_text=rating_text)

			StatsCMD.print_info(output, info, event.bunch.object.from_id, False)
		else:
			StatsCMD.print_error_unknow_sub(output, event.bunch.object.from_id)
	
	def print_info(output: ChatOutput, info: str, user_id: int, daily: bool):
		if(daily):
			keyboard = keyboard_inline([[callback_button('Полная стата', ['run', '!стата "" {}'.format(user_id), user_id], 'primary')]])
		else:
			keyboard = keyboard_inline([[callback_button('Дневная стата', ['run', '!стата дня {}'.format(user_id), user_id], 'primary')]])

		message = ChatOutput.UOSMessage(output)
		message.send(message=info, keyboard=keyboard)
		message.edit(message=info, keyboard=keyboard)
		message()

	def print_error_unknow_sub(output: ChatOutput, user_id: int):
		keyboard = keyboard_inline([
			[callback_button('Полная стата', ['run', '!стата', user_id], 'primary')],
			[callback_button('Дневная стата', ['run', '!стата дня', user_id], 'secondary')]
		])

		message = ChatOutput.UOSMessage(output)
		message.send(message='⛔Неизвестная команда. Используйте:\n• !стата\n• !cтата дня', keyboard=keyboard)
		message.edit(message='⛔Неизвестная команда.', keyboard=keyboard)
		message()

def message_action_handler(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	args = callin.args
	db = callin.db
	vk_api = callin.vk_api
	output = callin.output

def error(callin: ChatEventManager.CallbackInputObject):
	raise Exception()

MSG_PHP_COMMANDS = ['!docmd', '!test-template', '!runcb', '!kick-all', '!debug-info', '!db-edit', '!special-permits', '!test-cmd', '!cmd-search', '!test-parser', '!testout', '!cmdlist', '!reg', '!помощь', '!чат', '!меню', '!лайк', '!убрать', '!id', '!base64', '!крестики-нолики', '!сообщение', '!addcustom', '!delcustom', '!customlist', 'пожать', 'дать', '!онлайн', '!ban', '!unban', '!baninfo', '!banlist', '!kick', '!ник', '!приветствие', '!modes', '!панель', 'панель', '!права', '!ники', '!стата', '!рейтинг', '!me', '!do', '!try', '!s', 'секс', 'обнять', 'уебать', 'обоссать', 'поцеловать', 'харкнуть', 'отсосать', 'отлизать', 'послать', 'кастрировать', 'посадить', 'лизнуть', 'обосрать', 'облевать', 'отшлёпать', 'отшлепать', 'покашлять', '!выбери', '!сколько', '!инфа', '!rndwall', '!memes', '!бутылочка', '!tts', '!say', '!брак', '!браки', '!shrug', '!tableflip', '!unflip', '!кек', '!кто', '!кого', '!кому', '!счет', '!счет', '!работа', '!работать', '!профессии', '!профессия', '!купить', '!продать', '!имущество', '!награды', '!банк', '!образование', '!forbes', '!бизнес', '!подарить', '!казино', '!ставка']
CB_PHP_COMMANDS = ['bot_menu', 'bot_cmdlist', 'bot_tictactoe', 'bot_reg', 'bot_listcustomcmd', 'bot_run']
def initcmd_php(manager: ChatEventManager):
	for cmd in MSG_PHP_COMMANDS:
		ignore_db = False
		if cmd in ['!reg']:
			ignore_db = True
		manager.addMessageCommand(cmd, handle_phpcmd, ignore_db=ignore_db)

	for cmd in CB_PHP_COMMANDS:
		ignore_db = False
		if cmd in ['bot_reg']:
			ignore_db = True
		manager.addCallbackButtonCommand(cmd, handle_phpcmd, ignore_db=ignore_db)

	manager.addMessageHandler(handle_phphndl)

def handle_phpcmd(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "cmd", json.dumps(event.dict)]).communicate()
	return True

def handle_phphndl(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "hndl", json.dumps(event.dict)]).communicate()
	return True

def test(callin: ChatEventManager.CallbackInputObject, s):
	event = callin.event
	args = callin.args
	output = callin.output

	message = ChatOutput.UOSMessage(output)
	message.send(message='Хуй '+args.str(1, ''))
	message()

	#result = output.messages_send(peer_id=event.peer_id, message=vk.VKVariable.Multi('str', 'Привет, ', 'var', 'appeal', 'str', '!'), script='var appeal=\"хуй\";')

def test2(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	args = callin.args
	db = callin.db
	vk_api = callin.vk_api

	vk_api.call('messages.send', {'peer_id': event.bunch.object.peer_id, 'message': bot.check_chat_in_db(db, int(args[1]))})