import subprocess, json, time
import radabot.core.bot as bot
from radabot.core.io import AdvancedOutputSystem, ChatEventManager, OutputSystem
from radabot.core.manager import UserPermissions
from radabot.core.system import ChatDatabase, PHPCommandIntegration, PageBuilder, ValueExtractor, Config, get_id_by_atlink, get_reply_message_from_event, int2emoji
from radabot.core.vk import VK_API, KeyboardBuilder, VKVariable
from radabot.core.bot import DEFAULT_MESSAGES

def initcmd(manager: ChatEventManager):
	manager.add_message_command('!cmdlist', ShowCommandListCommand.message_command)
	manager.add_message_command('!стата', StatsCommand.message_command)
	manager.add_message_command('!чит', CheatMenuCommand.message_command)
	manager.add_message_command('!подписка', SubscriptionCommand.subscribe_message_command)
	manager.add_message_command('!подписки', SubscriptionCommand.showsubscribes_message_command)

	manager.add_callback_button_command('bot_cancel', CancelCallbackButtonCommand.callback_button_command)
	manager.add_callback_button_command('bot_cmdlist', ShowCommandListCommand.callback_button_command)
	manager.add_callback_button_command('bot_stats', StatsCommand.callback_button_command)
	manager.add_callback_button_command('bot_cheat', CheatMenuCommand.callback_button_command)

	manager.add_message_handler(InviteMessageHandler.handler, ignore_db=True)

# Команда !стата
class StatsCommand:
	@staticmethod
	def message_command(callback_object):
		event = callback_object["event"]
		args = callback_object["args"]
		db = callback_object["db"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		member_id = args.get_int(2, 0)
		if member_id <= 0:
			member_id = event["object"]["message"]["from_id"]
		
		rep_msg = get_reply_message_from_event(event)
		if rep_msg is not None:
			member_id = rep_msg["from_id"]

		if(member_id <= 0):
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔Неверный идентификатор пользователя.'))
			return False

		subcommand = args.get_str(1, '').lower()
		if(subcommand == 'дня'):
			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if(member_id == event["object"]["message"]["from_id"]):
				pre_msg = "Cтатистика дня:"
				keyboard.callback_button('Полная стата', ['bot_stats', event["object"]["message"]["from_id"], 1], KeyboardBuilder.PRIMARY_COLOR)
			else:
				pre_msg = "Стастика дня @id{} (пользователя):".format(member_id)
				keyboard.callback_button('Полная стата', ['bot_stats', event["object"]["message"]["from_id"], 1, member_id], KeyboardBuilder.PRIMARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["message"]["from_id"]], KeyboardBuilder.NEGATIVE_COLOR)
			keyboard = keyboard.build()

			info = StatsCommand.__get_user_text_of_user_stats(db, member_id, True)
			message_text = pre_msg + info
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)

			return True
		elif(subcommand == ''):
			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if(member_id == event["object"]["message"]["from_id"]):
				pre_msg = "Cтатистика:"
				keyboard.callback_button('Дневная стата', ['bot_stats', event["object"]["message"]["from_id"], 2], KeyboardBuilder.PRIMARY_COLOR)
			else:
				pre_msg = "Стастика @id{} (пользователя):".format(member_id)
				keyboard.callback_button('Дневная стата', ['bot_stats', event["object"]["message"]["from_id"], 2, member_id], KeyboardBuilder.PRIMARY_COLOR)

			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["message"]["from_id"]], KeyboardBuilder.NEGATIVE_COLOR)
			keyboard = keyboard.build()

			info = StatsCommand.__get_user_text_of_user_stats(db, member_id, False)
			message_text = pre_msg + info
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)

			return True
		else:
			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			keyboard.callback_button('Полная стата', ['bot_stats', event["object"]["message"]["from_id"], 1], KeyboardBuilder.PRIMARY_COLOR)
			keyboard.callback_button('Дневная стата', ['bot_stats', event["object"]["message"]["from_id"], 2], KeyboardBuilder.SECONDARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["message"]["from_id"]], KeyboardBuilder.NEGATIVE_COLOR)
			keyboard = keyboard.build()

			message_text = '⛔Неизвестная команда. Используйте:\n• !стата\n• !cтата дня'
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)
			return False

	@staticmethod
	def callback_button_command(callback_object: dict):
		event = callback_object["event"]
		payload = callback_object["payload"]
		db = callback_object["db"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		testing_user_id = payload.get_int(1, event["object"]["user_id"])
		if testing_user_id != event["object"]["user_id"]:
			aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)
			return

		subcommand = payload.get_int(2, 1)
		member_id = payload.get_int(3, event["object"]["user_id"])

		if member_id <= 0:
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔Неверный идентификатор пользователя.'))
			return

		if subcommand == 2:
			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if(member_id == event["object"]["user_id"]):
				pre_msg = "Cтатистика дня:"
				keyboard.callback_button('Полная стата', ['bot_stats', event["object"]["user_id"], 1], KeyboardBuilder.PRIMARY_COLOR)
			else:
				pre_msg = "Стастика дня @id{} (пользователя):".format(member_id)
				keyboard.callback_button('Полная стата', ['bot_stats', event["object"]["user_id"], 1, member_id], KeyboardBuilder.PRIMARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["user_id"]], KeyboardBuilder.NEGATIVE_COLOR)
			keyboard = keyboard.build()

			info = StatsCommand.__get_user_text_of_user_stats(db, member_id, True)
			message_text = pre_msg + info
			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)

			return True
		elif subcommand == 1:
			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if(member_id == event["object"]["user_id"]):
				pre_msg = "Cтатистика:"
				keyboard.callback_button('Дневная стата', ['bot_stats', event["object"]["user_id"], 2], KeyboardBuilder.PRIMARY_COLOR)
			else:
				pre_msg = "Стастика @id{} (пользователя):".format(member_id)
				keyboard.callback_button('Дневная стата', ['bot_stats', event["object"]["user_id"], 2, member_id], KeyboardBuilder.PRIMARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["user_id"]], KeyboardBuilder.NEGATIVE_COLOR)
			keyboard = keyboard.build()

			info = StatsCommand.__get_user_text_of_user_stats(db, member_id, False)
			message_text = pre_msg + info
			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)

			return True
		else:
			message_text = '⛔ Неизвестное действие.'
			aos.show_snackbar(text=message_text)
			return False

	@staticmethod
	def __get_user_text_of_user_stats(db: ChatDatabase, user_id: int, is_daily: bool):
		if is_daily:
			result = db.find(projection={'_id': 0, 'chat_stats.users_daily': 1})
			extractor = ValueExtractor(result)

			current_time = time.time()											# Переменная текущего времени
			current_day = int(current_time - (current_time % 86400));			# Переменная текущей даты (00:00 GMT)

			all_stats = extractor.get('chat_stats.users_daily.time{}'.format(current_day), {})
			stats = extractor.get('chat_stats.users_daily.time{}.id{}'.format(current_day, user_id).format(user_id), {})
			stats = {**bot.ChatStats.STATS_DEFAULT, **stats}
		else:
			result = db.find(projection={'_id': 0, 'chat_stats.users': 1})
			extractor = ValueExtractor(result)

			all_stats = extractor.get('chat_stats.users', {})
			stats = extractor.get('chat_stats.users.id{}'.format(user_id), {})
			stats = {**bot.ChatStats.STATS_DEFAULT, **stats}

		rating = []
		for k, v in all_stats.items():
			u = {**bot.ChatStats.STATS_DEFAULT, **v}
			rating.append({'u': k, 'v': u['msg_count'] - u['msg_count_in_succession']})
		rating.sort(reverse=True, key=lambda e: e['v'])

		position = 0
		for user in rating:
			position += 1
			if(user['u'] == "id{}".format(user_id)):
				break
		rating_text = "{} место".format(position)

		basic_info = "\n📧Сообщений: {msg_count}\n&#12288;📝Подряд: {msg_count_in_succession}\n🔍Символов: {symbol_count}\n📟Гол. сообщений: {audio_msg_count}"
		attachment_info = "\n\n📷Фотографий: {photo_count}\n📹Видео: {video_count}\n🎧Аудиозаписей: {audio_count}\n🤡Стикеров: {sticker_count}"
		cmd_info = "\n\n🛠Команд выполнено: {command_used_count}\n🔘Нажато кнопок: {button_pressed_count}"
		rating_info = "\n\n👑Активность: {rating_text}"

		info = basic_info + attachment_info + cmd_info + rating_info
		info = info.format(msg_count=stats['msg_count'], msg_count_in_succession=stats['msg_count_in_succession'],
							symbol_count=stats['symbol_count'], audio_msg_count=stats['audio_msg_count'],
							photo_count=stats['photo_count'], video_count=stats['video_count'],
							audio_count=stats['audio_count'], sticker_count=stats['sticker_count'],
							command_used_count=stats['command_used_count'], button_pressed_count=stats['button_pressed_count'],
							rating_text=rating_text)
		
		return info

# Команда !cmdlist
class ShowCommandListCommand:
	@staticmethod
	def message_command(callback_object: dict):
		event = callback_object["event"]
		args = callback_object["args"]
		db = callback_object["db"]
		manager = callback_object["manager"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		builder = PageBuilder(manager.message_command_list, 10)
		number = args.get_int(1, 1)

		try:
			page = builder(number)
			text = 'Список команд [{}/{}]:'.format(number, builder.max_number)
			for i in page:
				text += "\n• " + i

			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if number > 1:
				prev_number = number - 1
				keyboard.callback_button("{} ⬅".format(int2emoji(prev_number)), ['bot_cmdlist', event["object"]["message"]["from_id"], prev_number], KeyboardBuilder.SECONDARY_COLOR)
			if number < builder.max_number:
				next_number = number + 1
				keyboard.callback_button("➡ {}".format(int2emoji(next_number)), ['bot_cmdlist', event["object"]["message"]["from_id"], next_number], KeyboardBuilder.SECONDARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["message"]["from_id"]], KeyboardBuilder.NEGATIVE_COLOR)

			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard.build())
		except PageBuilder.PageNumberException:
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔Неверный номер страницы.'))

	@staticmethod
	def callback_button_command(callback_object: dict):
		event = callback_object["event"]
		payload = callback_object["payload"]
		db = callback_object["db"]
		manager = callback_object["manager"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)
		
		testing_user_id = payload.get_int(1, event["object"]["user_id"])
		if testing_user_id != event["object"]["user_id"]:
			aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)
			return

		builder = PageBuilder(manager.message_command_list, 10)
		number = payload.get_int(2, 1)

		try:
			page = builder(number)
			text = 'Список команд [{}/{}]:'.format(number, builder.max_number)
			for i in page:
				text += "\n• " + i

			keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
			if number > 1:
				prev_number = number - 1
				keyboard.callback_button("{} ⬅".format(int2emoji(prev_number)), ['bot_cmdlist', event["object"]["user_id"], prev_number], KeyboardBuilder.SECONDARY_COLOR)
			if number < builder.max_number:
				next_number = number + 1
				keyboard.callback_button("➡ {}".format(int2emoji(next_number)), ['bot_cmdlist', event["object"]["user_id"], next_number], KeyboardBuilder.SECONDARY_COLOR)
			keyboard.new_line()
			keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["user_id"]], KeyboardBuilder.NEGATIVE_COLOR)

			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard.build())
		except PageBuilder.PageNumberException:
			aos.show_snackbar(text="⛔ Неверный номер страницы.")

# Команда закрытия меню через тектовую кнопку
class CancelCallbackButtonCommand:
	@staticmethod
	def callback_button_command(callback_object: dict):
		event = callback_object["event"]
		payload = callback_object["payload"]
		db = callback_object["db"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		testing_user_id = payload.get_int(1, 0)
		if testing_user_id == event["object"]["user_id"] or testing_user_id == 0:
			text = payload.get_str(2, bot.DEFAULT_MESSAGES.MESSAGE_MENU_CANCELED)
			aos.messages_edit(message=text)
		else:
			aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)

# Команда !чит
class CheatMenuCommand:
	@staticmethod
	def message_command(callback_object: dict):
		event = callback_object["event"]
		db = callback_object["db"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
		keyboard.size(width=3)
		for i in range(1, 10):
			keyboard.callback_button(int2emoji(i), ['bot_cheat', event["object"]["message"]["from_id"], "", i], KeyboardBuilder.SECONDARY_COLOR)
		keyboard.callback_button(int2emoji(0), ['bot_cheat', event["object"]["message"]["from_id"], "", 0], KeyboardBuilder.SECONDARY_COLOR)
		keyboard = keyboard.build()

		message_text = "Введите код:\n\n😐😐😐😐😐"

		aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)

	@staticmethod
	def callback_button_command(callback_object: dict):
		event = callback_object["event"]
		payload = callback_object["payload"]
		db = callback_object["db"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		testing_user_id = payload.get_int(1, event["object"]["user_id"])
		if testing_user_id != event["object"]["user_id"]:
			aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)
			return

		code = payload.get_str(2, "")
		num = payload.get_int(3, -1)

		if 0 <= num and num < 10:
			code += str(num)

			if len(code) >= 5:
				CheatMenuCommand.__check_code(aos, event, db, code)
			else:
				message_text = "Введите код:\n\n"
				for i in range(1, 6):
					if len(code) >= i:
						message_text += '🙂'
					else:
						message_text += '😐'

				keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
				keyboard.size(width=3)
				for i in range(1, 10):
					keyboard.callback_button(int2emoji(i), ['bot_cheat', event["object"]["user_id"], code, i], KeyboardBuilder.SECONDARY_COLOR)
				keyboard.callback_button(int2emoji(0), ['bot_cheat', event["object"]["user_id"], code, 0], KeyboardBuilder.SECONDARY_COLOR)
				keyboard = keyboard.build()

				aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text), keyboard=keyboard)
		else:
			aos.show_snackbar(text="🤨 Не получается.")

	@staticmethod
	def __check_code(aos: AdvancedOutputSystem, event: dict, db: ChatDatabase, code: str):
		if code == '00000':
			# Код 00000 - Кик из группы
			chat_id = event["object"]["peer_id"] - 2000000000
			remove_chat_user_params = {'chat_id': chat_id, 'user_id': event["object"]["user_id"]}
			pscript = 'API.messages.removeChatUser({});'.format(json.dumps(remove_chat_user_params, ensure_ascii=False, separators=(',', ':')))
			message_text = '😡Не стоит шутить со мной!'
			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text), pscript=pscript)
		elif code == '39751':
			user_permissions = UserPermissions(db, event["object"]["user_id"])
			user_permissions.set('drink_tea', True)
			user_permissions.commit()
			message_text = '☕Теперь ты можешь пить чай!'
			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
		else:
			# Просто сообщение о неудаче
			message_text = '😮К сожалению, код не сработал.'
			aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

# Класс обработчика, отправляющего сообщение, когда бот был добавлен в беседу
class InviteMessageHandler:
	@staticmethod
	def handler(callback_object: dict):
		event = callback_object["event"]
		db = callback_object["db"]
		output = callback_object["output"]

		if "action" in event["object"]["message"] and event["object"]["message"]["action"]["type"] == "chat_invite_user" and event["object"]["message"]["action"]["member_id"] == -event["group_id"]:
			if db.is_exists:
				message_text = "😇Привет. 😜Я рад вернуться сюда."
				output.messages_send(peer_id=event["object"]["message"]["peer_id"], message=message_text)
				return True
			else:
				keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
				keyboard.callback_button('Зарегистрировать', ['bot_reg'], KeyboardBuilder.POSITIVE_COLOR)
				keyboard = keyboard.build()
				message_text = "🙂Привет.\n❗Для начала необходимо выдать мне права администратора в беседе (только так я буду работать).\n👇🏻Затем нажмите кнопку ниже."
				output.messages_send(peer_id=event["object"]["message"]["peer_id"], message=message_text, keyboard=keyboard)
				return True

# Команды !подписка !подписки !отписка
class SubscriptionCommand:
	MAX_SUBSCRIPTION_COUNT = 5			# Максимальное число подписок

	@staticmethod
	def showsubscribes_message_command(callback_object: dict):
		event = callback_object["event"]
		db = callback_object["db"]
		args = callback_object["args"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		# Получаем все подписки чата
		subs_collection = db.get_collection("chat_subs")
		current_subs = subs_collection.find({"subscribers": db.chat_id}, projection={ "_id": 0, "source_id": 1})

		# Формируем массив пользователей и групп
		users = []
		groups = []
		for i in current_subs:
			if i["source_id"] > 0:
				users.append(i["source_id"])
			else:
				groups.append(-i["source_id"])

		if len(users) == 0 and len(groups) == 0:
			message_text = '⛔Беседа не подписана ни на одну страницу.'
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
			return

		# Отправляем список подписок
		vk_var = VKVariable()
		script = "var subs=\"\";var num=1;"
		if len(users) > 0:
			vk_var.var("var users", users)
			script += "var u=API.users.get({user_ids:users});"
			script += "var i=0;while(i<u.length){subs=subs+num+\". [id\"+u[i].id+\"|\"+u[i].first_name+\" \"+u[i].last_name+\"]\\n\";num=num+1;i=i+1;};"
		if len(groups) > 0:
			vk_var.var("var groups", groups)
			script += "var g=API.groups.getById({group_ids:groups});"
			script += "var i=0;while(i<g.length){subs=subs+num+\". [club\"+g[i].id+\"|\"+g[i].name+\"]\\n\";i=i+1;};"
		script = vk_var() + script
		message = VKVariable.Multi('var', 'appeal', 'str', "💡Текущие подписки:\n", 'var', 'subs')
		aos.messages_send(message=message, script=script)

	@staticmethod
	def subscribe_message_command(callback_object: dict):
		event = callback_object["event"]
		db = callback_object["db"]
		args = callback_object["args"]
		output = callback_object["output"]

		aos = AdvancedOutputSystem(output, event, db)

		source_str = args.get_str(1, "")
		if source_str == "":
			message_text = '⚠Позволяет подписаться на посты человека/группы.\n\nИспользуйте:\n➡️ {} [источник]\n\n📝В качестве источника нужно указать ID, полную ссылку или ссылку через @.'.format(args.get_str(0).lower())
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
			return

		# Пытаемся получить ID источника из аргумента
		source_id = None
		try:
			source_id = int(source_str)
		except ValueError:
			source_id = get_id_by_atlink(source_str)

		last_post_date = 0
		if source_id is None:
			# Если не удалось получить ID источника, пытаемся получить его с помощью ссылки, затем получаем информацию о стене источника
			cut_index = source_str.find("vk.com")
			if cut_index != -1:
				domain = source_str[cut_index + 7:]
			cut_index = domain.find("/")
			if cut_index != -1:
				domain = domain[:cut_index]

			service_vk_api = VK_API(Config.get("VK_SERVICE_TOKEN"))
			call_result = service_vk_api.call("wall.get", {"domain": domain, "count": 2})
			try:
				source_id = call_result["response"]["items"][0]["owner_id"]
				for i in call_result["response"]["items"]:
					if i["post_type"] == "post" and i["date"] > last_post_date:
						last_post_date = i["date"]
			except:
				pass
		else:
			# Получаем информацию о стене источника
			service_vk_api = VK_API(Config.get("VK_SERVICE_TOKEN"))
			call_result = service_vk_api.call("wall.get", {"owner_id": source_id, "count": 2})
			try:
				source_id = call_result["response"]["items"][0]["owner_id"]
				for i in call_result["response"]["items"]:
					if i["post_type"] == "post" and i["date"] > last_post_date:
						last_post_date = i["date"]
			except:
				pass

		# Выводим сообщение об ошибке если не получилось получить ID источника
		if source_id is None:
			message_text = '⛔Неверный источник. Убедитесь, что источник указывает на человека/группу ВК, стена указанной страницы доступна для просмотра и на стене страницы есть хотя бы один пост.'
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
			return

		# Получаем все подписки чата
		subs_collection = db.get_collection("chat_subs")
		current_subs = subs_collection.find({"subscribers": db.chat_id}, projection={ "_id": 0, "source_id": 1})
		
		# Проверка: подписан ли уже на этот источник
		subs_count = 0
		for i in current_subs:
			subs_count += 1
			if i["source_id"] == source_id:
				message_text = '⛔Подписка на этот источник уже оформлена.'
				aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
				return

		# Ограничение на количество подписок
		if subs_count >= SubscriptionCommand.MAX_SUBSCRIPTION_COUNT:
			message_text = f'⛔Максимальное число подписок: {SubscriptionCommand.MAX_SUBSCRIPTION_COUNT}.'
			aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))
			return

		# Проверка: есть ли источник уже в базе данных
		if subs_collection.find_one({"source_id": source_id}, projection={ "_id": 1}) is None:
			subs_collection.insert_one({"source_id": source_id, "last_post_date": last_post_date, "subscribers": [db.chat_id]})
		else:
			subs_collection.update_one({"source_id": source_id}, {"$push": {"subscribers": db.chat_id}})
		
		# Сообщаем об удачной подписке
		if source_id < 0:
			script = f"var g=API.groups.getById({{group_id: {-source_id}}})[0];var pagename=\"[club{-source_id}|\"+g.name+\"]\";"
		else:
			script = f"var u=API.users.get({{user_ids: {source_id}}})[0];var pagename=\"[id{source_id}|\"+u.first_name+\" \"+u.last_name+\"]\";"
		message = VKVariable.Multi('var', 'appeal', 'str', "✅Подписка на страницу ", 'var', 'pagename', 'str', ' оформлена.')
		aos.messages_send(message=message, script=script)


# Инициализация PHP команд
def initcmd_php(manager: ChatEventManager):
	for cmd in PHPCommandIntegration.message_commands:
		ignore_db = False
		if cmd in ['!reg']:
			ignore_db = True
		manager.add_message_command(cmd, handle_phpcmd, ignore_db=ignore_db, event_version=ChatEventManager.EventObjectConverter.Version.OLD_5_84)

	for cmd in PHPCommandIntegration.text_button_commands:
		manager.add_text_button_command(cmd, handle_phpcmd, event_version=ChatEventManager.EventObjectConverter.Version.OLD_5_84)

	for cmd in PHPCommandIntegration.callback_button_commands:
		ignore_db = False
		if cmd in ['bot_reg']:
			ignore_db = True
		manager.add_callback_button_command(cmd, handle_phpcmd, ignore_db=ignore_db, event_version=ChatEventManager.EventObjectConverter.Version.OLD_5_84)

	manager.add_message_handler(handle_phphndl, ignore_db=True, event_version=ChatEventManager.EventObjectConverter.Version.OLD_5_84)

# Выполенение PHP команд
def handle_phpcmd(callback_object: dict):
	event = callback_object["event"]
	subprocess.Popen([Config.get("PHP_COMMAND"), "radabot-php.php", "cmd", json.dumps(event)]).communicate()

# Выполенение вне командное пространство PHP
def handle_phphndl(callback_object: dict):
	event = callback_object["event"]
	subprocess.Popen([Config.get("PHP_COMMAND"), "radabot-php.php", "hndl", json.dumps(event)]).communicate()
	return True