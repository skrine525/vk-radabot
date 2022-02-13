import subprocess, json, time
import radabot.core.bot as bot
from radabot.core.io import ChatEventManager, ChatOutput
from radabot.core.manager import UserPermission
from radabot.core.system import ManagerData, PHPCommandIntegration, PageBuilder, ValueExtractor, int2emoji
from radabot.core.vk import VKVariable, callback_button, keyboard, keyboard_inline

def handle_event(vk_api, event):
	manager = ChatEventManager(vk_api, event)

	manager.addMessageCommand("!стата", StatsMessageCommand.main)
	manager.addMessageCommand('!cmdlist', ShowCommandListMessageCommand.main)
	manager.addMessageCommand('!метки', PermissionMessageCommand.main)

	manager.addMessageCommand('!error', error)

	manager.addCallbackButtonCommand('bot_cancel', CancelCallbackButtonCommand.main)

	initcmd_php(manager)
	manager.handle()

# Команда !стата
class StatsMessageCommand:
	@staticmethod
	def main(callin: ChatEventManager.CallbackInputObject):
		event = callin.event
		args = callin.args
		db = callin.db
		output = callin.output

		member_id = args.int(2, 0)
		if(member_id <= 0):
			member_id = event.from_id
		if('fwd_messages' in event):
			try:
				member_id = event.fwd_messages[0].from_id
			except IndexError:
				pass

		if(member_id <= 0):
			StatsMessageCommand.print_error_invalid_userid(output)
			return False

		subcommand = args.str(1, '').lower()
		if(subcommand == 'дня'):
			current_time = time.time()											# Переменная текущего времени
			current_day = int(current_time - (current_time % 86400));			# Переменная текущей даты (00:00 GMT)

			if(member_id == event.from_id):
				pre_msg = "Cтатистика дня:"
			else:
				pre_msg = "Стастика дня @id{} (пользователя):".format(member_id)

			chats_collection = db['chats']
			projection = {'_id': 0, 'chat_stats.users_daily.time{}'.format(current_day): 1}
			result = chats_collection.find_one(bot.get_chat_db_query(event.peer_id), projection=projection)
			extractor = ValueExtractor(result)

			all_stats = extractor.get('chat_stats.users_daily.time{}'.format(current_day), {})
			stats = extractor.get('chat_stats.users_daily.time{}.id{}'.format(current_day, member_id).format(member_id), {})
			stats = {**bot.ChatStats.STATS_DEFAULT, **stats}

			rating = []
			for k, v in all_stats.items():
				u = {**bot.ChatStats.STATS_DEFAULT, **v}
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

			StatsMessageCommand.print_info(output, info, event.from_id, member_id, True)
			return True
		elif(subcommand == ''):
			chats_collection = db['chats']
			projection = {'_id': 0, 'chat_stats.users': 1}
			result = chats_collection.find_one(bot.get_chat_db_query(event.peer_id), projection=projection)
			extractor = ValueExtractor(result)

			if(member_id == event.from_id):
				pre_msg = "Cтатистика:"
			else:
				pre_msg = "Стастика @id{} (пользователя):".format(member_id)

			all_stats = extractor.get('chat_stats.users', {})
			stats = extractor.get('chat_stats.users.id{}'.format(member_id), {})
			stats = {**bot.ChatStats.STATS_DEFAULT, **stats}

			rating = []
			for k, v in all_stats.items():
				u = {**bot.ChatStats.STATS_DEFAULT, **v}
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

			StatsMessageCommand.print_info(output, info, event.from_id, member_id, False)
			return True
		else:
			StatsMessageCommand.print_error_unknown_subcommand(output, event.from_id)
			return False
	
	@staticmethod
	def print_info(output: ChatOutput, info: str, from_id: int, member_id: int, daily: bool):
		if(daily):
			keyboard = keyboard_inline([[callback_button('Полная стата', ['run', '!стата "" {}'.format(member_id), from_id], 'primary')]])
		else:
			keyboard = keyboard_inline([[callback_button('Дневная стата', ['run', '!стата дня {}'.format(member_id), from_id], 'primary')]])

		uos = output.uos()
		uos.messages_send(message=info, keyboard=keyboard)
		uos.messages_edit(message=info, keyboard=keyboard)

	@staticmethod
	def print_error_unknown_subcommand(output: ChatOutput, user_id: int):
		keyboard = keyboard_inline([
			[callback_button('Полная стата', ['run', '!стата', user_id], 'primary')],
			[callback_button('Дневная стата', ['run', '!стата дня', user_id], 'secondary')]
		])

		uos = output.uos()
		uos.messages_send(message='⛔Неизвестная команда. Используйте:\n• !стата\n• !cтата дня', keyboard=keyboard)
		uos.messages_edit(message='⛔Неизвестная команда.', keyboard=keyboard)

	@staticmethod
	def print_error_invalid_userid(output: ChatOutput):
		uos = output.uos()
		uos.messages_send(message='⛔Неверный идентификатор пользователя.')
		uos.show_snackbar(text='⛔ Неверное идентификатор пользователя.')

# Команда !cmdlist
class ShowCommandListMessageCommand:
	@staticmethod
	def main(callin: ChatEventManager.CallbackInputObject):
		event = callin.event
		args = callin.args
		output = callin.output
		manager = callin.manager

		builder = PageBuilder(list(manager.message_command_list.keys()), 10)
		number = args.int(1, 1)

		try:
			page = builder(number)
			text = 'Список команд [{}/{}]:'.format(number, builder.max_number)
			for i in page:
				text += "\n• " + i
			ShowCommandListMessageCommand.print_text(output, text, event.from_id, number, builder.max_number)
		except PageBuilder.PageNumberException:
			ShowCommandListMessageCommand.print_error_out_of_range(output)

	@staticmethod
	def print_text(output: ChatOutput, text: str, from_id: int, number: int, max_number: int):
		uos = output.uos()
		buttons = []
		if(number > 1):
			prev_number = number - 1
			buttons.append(callback_button("{} ⬅".format(int2emoji(prev_number)), ['run', '!cmdlist {}'.format(prev_number), from_id], 'secondary'))
		if(number < max_number):
			next_number = number + 1
			buttons.append(callback_button("➡ {}".format(int2emoji(next_number)), ['run', '!cmdlist {}'.format(next_number), from_id], 'secondary'))
		keyboard = keyboard_inline([buttons, [callback_button('Закрыть', ['bot_cancel', from_id], 'negative')]])
		uos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard)
		uos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard)

	@staticmethod
	def print_error_out_of_range(output: ChatOutput):
		uos = output.uos()
		uos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔Неверный номер страницы.'))
		uos.show_snackbar(text='⛔ Неверный номер страницы.')

class PermissionMessageCommand:
	@staticmethod
	def main(callin: ChatEventManager.CallbackInputObject):
		event = callin.event
		args = callin.args
		db = callin.db
		output = callin.output

		subcommand = args.str(1, '')
		if(subcommand == ''):
			userPermission = UserPermission(db, event.from_id, event.peer_id)
			permission_list = userPermission.getAll()
			PermissionMessageCommand.print_my_list(output, callin, permission_list)
		elif(subcommand == 'установить'):
			pass
		else:
			PermissionMessageCommand.print_error_unknown_subcommand(output)

	@staticmethod
	def print_error_unknown_subcommand(output: ChatOutput):
		uos = output.uos()
		if(uos.is_message_new):
			message = VKVariable.Multi('var', 'appeal', 'str', '⛔Неизвестная команда.')
			uos.messages_send(message=message)
		elif(uos.is_message_event):
			uos.show_snackbar(text='⛔Неизвестная команда.')

	@staticmethod
	def print_my_list(output: ChatOutput, callin: ChatEventManager.CallbackInputObject, perm: dict):
		user_permissions_data = ManagerData.getUserPermissions()

		uos = output.uos()
		if(uos.is_message_new):
			message_text = "Ваши права:"
			for k, v in perm.items():
				if(v):
					label = user_permissions_data[k]['label']
					message_text += "\n• {}".format(label)

			message = VKVariable.Multi('var', 'appeal', 'str', message_text)
			keyboard = keyboard_inline([[callback_button('Подробнее', ['run', callin.args.str(0, '')], 'positive')]])

			uos.messages_send(message=message, keyboard=keyboard)
		elif(uos.is_message_event):
			pass
			#uos.messages_edit(message=message, keyboard=keyboard)


class CancelCallbackButtonCommand:
	@staticmethod
	def main(callin: ChatEventManager.CallbackInputObject):
		event = callin.event
		payload = callin.payload
		output = callin.output

		testing_user_id = payload.int(1, 0)
		if(testing_user_id == event.bunch.object.user_id or testing_user_id == 0):
			text = payload.str(2, bot.DEFAULT_MESSAGES.MENU_CANCELED)
			output.messages_edit(message=text, peer_id=event.bunch.object.peer_id, conversation_message_id=event.bunch.object.conversation_message_id, keep_forward_messages=True)
		else:
			output.show_snackbar(event.bunch.object.event_id, event.bunch.object.user_id, event.bunch.object.peer_id, bot.DEFAULT_MESSAGES.NO_RIGHTS_TO_USE_THIS_BUTTON)

def message_action_handler(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	args = callin.args
	db = callin.db
	vk_api = callin.vk_api
	output = callin.output

def error(callin: ChatEventManager.CallbackInputObject):
	raise Exception()

def initcmd_php(manager: ChatEventManager):
	for cmd in PHPCommandIntegration.message_commands:
		ignore_db = False
		if cmd in ['!reg']:
			ignore_db = True
		manager.addMessageCommand(cmd, handle_phpcmd, ignore_db=ignore_db)

	for cmd in PHPCommandIntegration.callback_button_commands:
		ignore_db = False
		if cmd in ['bot_reg']:
			ignore_db = True
		manager.addCallbackButtonCommand(cmd, handle_phpcmd, ignore_db=ignore_db)

	manager.addMessageHandler(handle_phphndl)

def handle_phpcmd(callin: ChatEventManager.CallbackInputObject):
	manager = callin.manager
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "cmd", json.dumps(manager.event.dict)]).communicate()
	return True

def handle_phphndl(callin: ChatEventManager.CallbackInputObject):
	manager = callin.manager
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "hndl", json.dumps(manager.event.dict)]).communicate()
	return True