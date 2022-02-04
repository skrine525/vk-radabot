import subprocess, json
import radabot.core.bot as bot
from radabot.core.io import ChatEventManager

def handle_event(vk_api, event):
	manager = ChatEventManager(vk_api, event)
	manager.addMessageCommand("!тест", test, args=['Сука'])
	manager.addMessageCommand("!тест2", test2)

	initcmd_php(manager)
	manager.handle()

def message_action_handler(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	args = callin.args
	db = callin.db
	vk_api = callin.vk_api
	output = callin.output



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
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "cmd", json.dumps(event.initial_data)]).communicate()
	return True

def handle_phphndl(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "hndl", json.dumps(event.initial_data)]).communicate()
	return True

def test(callin: ChatEventManager.CallbackInputObject, s):
	event = callin.event
	args = callin.args
	output = callin.output

	message = output.uos_message()
	message.message('Хуй')
	message()

	#result = output.messages_send(peer_id=event.peer_id, message=vk.VKVariable.Multi('str', 'Привет, ', 'var', 'appeal', 'str', '!'), script='var appeal=\"хуй\";')

def test2(callin: ChatEventManager.CallbackInputObject):
	event = callin.event
	args = callin.args
	db = callin.db
	vk_api = callin.vk_api

	vk_api.call('messages.send', {'peer_id': event.peer_id, 'message': bot.check_chat_in_db(db, int(args[1]))})