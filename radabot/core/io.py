# Module Level 2
import json, traceback, time
from datetime import datetime
from typing import Callable
from pymongo import MongoClient
from . import bot
from .bot import ChatData
from .system import generate_random_string, get_config, write_log, parse_args
from .vk import VK_API, VKVariable, keyboard_inline, callback_button
from .system import SYSTEM_PATHS

class ChatEventManager:
    #############################
    #############################
    # Внутренние классы

    # Класс для передачи параметров внутрь функций
    class CallbackInputObject:
        def __init__(self):
            self.event = None										# Поле данных события
            self.args = None										# Поле аргументов текстовой команды
            self.payload = None										# Поле полезной нагрузки кнопки
            self.manager = None										# Объект EventManager'а
            self.vk_api = None										# Объект VK API
            self.db = None											# Объект базы данных
            self.output = None										# Объект Единой системы вывода
            self.chat_data = None                                   # Объект Системы обработки данных чата

    class EventObject:
        class ActionObject:
            def __init__(self, initial_data: dict):
                action = initial_data['object']['action']
                self.type = action['type']

        def __init__(self, event: dict):
            self.initial_data = event								# Поле исходного словаря
            self.type = event['type']
            self.id = event['event_id']								# Аналогичное полю event_id
            self.group_id = event['group_id']

            if event['type'] == 'message_new':
                self.from_id = event['object']['from_id']
                self.peer_id = event['object']['peer_id']
                self.conversation_message_id = event['object']['conversation_message_id']
                self.text = event['object']['text']
                self.attachments = []
                self.date = event['object']['date']
                self.fwd_messages = []
                self.payload = event['object'].get('payload', None)
            elif event['type'] == 'message_event':
                self.peer_id = event['object']['peer_id']
                self.event_id = event['object']['event_id']
                self.conversation_message_id = event['object']['conversation_message_id']
                self.user_id = event['object']['user_id']
                self.payload = event['object']['payload']

    #############################
    #############################
    # Исключения

    # Исключение Базы данных
    class DatabaseException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Исключение Неизвестная команда
    class UnknownCommandException(Exception):
        def __init__(self, message: str, command: str):
            super(ChatEventManager.UnknownCommandException,
                  self).__init__(message)
            self.command = command
            self.message = message

    # Исключние неправильных событий при создании объекта ChatEventManager
    class InvalidEventException(Exception):
        def __init__(self, message: str):
            self.message = message

    #############################
    #############################
    # Конструктор

    def __init__(self, vk_api: VK_API, event: dict):
        if(event["type"] == "message_new" or event["type"] == "message_event"):
            self.vk_api = vk_api
            self.event = ChatEventManager.EventObject(event)
            self.message_command_list = {}
            self.text_button_command_list = {}
            self.callback_button_command_list = {}
            self.message_handler_list = []

            database_info = get_config('DATABASE')
            self.mongo_client = MongoClient(database_info['HOST'], database_info['PORT'])
            self.db = self.mongo_client[database_info['NAME']]

            self.chat_data = ChatData(self.db, self.event.peer_id)
        else:
            raise ChatEventManager.InvalidEventException('ChatEventManager support only message_new & message_event types')

    #############################
    #############################
    # Методы добавления комманд

    def addMessageCommand(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if(command in self.message_command_list):
            return False
        else:
            self.message_command_list[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    def addTextButtonCommand(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if(command in self.button_command_list):
            return False
        else:
            self.message_command_list[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    def addCallbackButtonCommand(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if(command in self.callback_button_command_list):
            return False
        else:
            self.callback_button_command_list[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    def addMessageHandler(self, callback: Callable) -> bool:
        if(callback in self.message_handler_list):
            return False
        else:
            self.message_handler_list.append(callback)
            return True

    #############################
    #############################
    # Методы проверки и вызова команд

    def isMessageCommand(self, command: str) -> bool:
        return command in self.message_command_list

    def isTextButtonCommand(self, command: str) -> bool:
        return command in self.text_button_command_list

    def isCallbackButtonCommand(self, command: str) -> bool:
        return command in self.callback_button_command_list

    def runMessageCommand(self, event: EventObject, output):
        if event.type == 'message_new':
            args = parse_args(event.text)
            command = args[0].lower()
            if(self.isMessageCommand(command)):
                if(not self.message_command_list[command]['ignore_db'] and not self.chat_data.exists_in_database):
                    raise ChatEventManager.DatabaseException('Command \'{}\' requires document in Database'.format(command))

                # Подготовка объекта входных данных Callback'а
                callin = ChatEventManager.CallbackInputObject()
                callin.event = event
                callin.args = args
                callin.vk_api = self.vk_api
                callin.manager = self
                callin.db = self.db
                callin.output = output
                callin.chat_data = self.chat_data

                callback = self.message_command_list[command]["callback"]
                callback_args = [callin] + self.message_command_list[command]["args"]
                callback(*callback_args)
                return True 
            else:
                raise ChatEventManager.UnknownCommandException('Command \'{}\' not found'.format(command), command)

    def runCallbackButtonCommand(self, event: EventObject, output):
        if event.type == 'message_event':
            payload = event.payload
            if(self.isCallbackButtonCommand(payload[0])):
                if(not self.callback_button_command_list[payload[0]]['ignore_db'] and not self.chat_data.exists_in_database):
                    raise ChatEventManager.DatabaseException('Command \'{}\' requires document in Database'.format(payload[0]))

                # Подготовка объекта входных данных Callback'а
                callin = ChatEventManager.CallbackInputObject()
                callin.event = event
                callin.payload = payload
                callin.vk_api = self.vk_api
                callin.manager = self
                callin.db = self.db
                callin.output = output
                callin.chat_data = self.chat_data

                callback = self.callback_button_command_list[payload[0]]["callback"]
                callback_args = [callin] + self.callback_button_command_list[payload[0]]["args"]
                callback(*callback_args)
                return True
            else:
                raise ChatEventManager.UnknownCommandException('Command \'{}\' not found'.format(payload[0]), payload[0])

    #############################
    #############################
    # Метод обработки события

    def handle(self) -> bool:
        if self.event.type == 'message_new':
            if(self.event.from_id <= 0):
                return False

            output = ChatOutput(self.vk_api, self.db, self.event)

            try:
                return self.runMessageCommand(self.event, output)
            except ChatEventManager.DatabaseException:
                keyboard = keyboard_inline([[callback_button('Зарегистрировать', ['bot_reg'], 'positive')]])
                output.messages_send(peer_id=self.event.peer_id, message='⛔Беседа не зарегистрирована.', forward=bot.reply_to_message_by_event(self.event), keyboard=keyboard)
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                write_log(SYSTEM_PATHS.EXEC_LOG_DIR+"{}.log".format(logname), "Event:\n{}\n\n{}".format(json.dumps(self.event.initial_data, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_send(peer_id=self.event.peer_id, message='🆘Ошибка выполнения!\n🆔Журнал: {}.'.format(logname))
                return False

            try:
                return self.runMessageCommand(self.event, output)
            except ChatEventManager.DatabaseException:
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                return False

            if((len(self.message_handler_list) > 0) and self.chat_data.exists_in_database):
                # Подготовка объекта входных данных Callback'а
                callin = ChatEventManager.CallbackInputObject()
                callin.event = self.event
                callin.vk_api = self.vk_api
                callin.manager = self
                callin.db = self.db
                callin.output = output

                for handler in self.message_handler_list:
                    if(handler(callin)):
                        return True
            return False

        elif self.event.type == 'message_event':
            output = ChatOutput(self.vk_api, self.db, self.event)
            try:
                return self.runCallbackButtonCommand(self.event, output)
            except ChatEventManager.DatabaseException:
                result = output.show_snackbar(self.event.event_id, self.event.user_id, self.event.peer_id, '⛔ Беседа не зарегистрирована.')
                write_log(SYSTEM_PATHS.ERROR_LOG_FILE, "{} {}".format(result.error, result.execute_errors))
                return False
            except ChatEventManager.UnknownCommandException:
                output.show_snackbar(self.event.event_id, self.event.user_id, self.event.peer_id, '⛔ Неизвестная команда.')
                return False
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                write_log(SYSTEM_PATHS.EXEC_LOG_DIR+"{}.log".format(logname), "Event:\n{}\n\n{}".format(json.dumps(self.event.initial_data, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_edit(peer_id=self.event.peer_id, conversation_message_id=self.event.conversation_message_id, message='🆘Ошибка выполнения!\n🆔Журнал: {}.'.format(logname))
                return False

class ChatOutput:
    #############################
    #############################
    # Внутренние классы

    # Класс Единой системы вывода
    class UOSMessage:
        def __init__(self, output, message_support: bool = True, button_support: bool = True, reply_to_message: bool = True, appeal_to_user: bool = True):
            self.output = output
            self.data = {
                'prefs': {
                    'message_support': message_support,
                    'button_support': button_support,
                    'reply_to_message': reply_to_message,
					'appeal_to_user': appeal_to_user
                },
                'object': {
                    'send': {},
                    'edit': {}
                }
            }

        def __call__(self):
            if(self.output.event.type == 'message_new'):
                if(self.data['prefs']['message_support']):
                    reqm = self.data['object']['send']

                    reqm['peer_id'] = self.output.event.peer_id
                    if(self.data['prefs']['reply_to_message']):
                        forward = {
                            'peer_id': self.output.event.peer_id,
                            'conversation_message_ids': [self.output.event.conversation_message_id],
                            'is_reply': True
                        }
                        reqm['forward'] = json.dumps(forward, ensure_ascii=False, separators=(',', ':'))

                    result = self.output.messages_send(**reqm)
            elif(self.output.event.type == 'message_event'):
                reqm = {}

        def disable_mentions(self, value):
            self.data['object']['send']['disable_mentions'] = value

        def message(self, value):
            self.data['object']['send']['message'] = value
            self.data['object']['edit']['message'] = value

        def attachment(self, value):
            self.data['object']['send']['attachment'] = value
            self.data['object']['edit']['attachment'] = value

        def keyboard(self, value):
            self.data['object']['send']['keyboard'] = value
            self.data['object']['edit']['keyboard'] = value

    # Класс результата выполнения
    class OutputResult:
        def __init__(self, vk_result: str):
            result = json.loads(vk_result)
            self.response = result.get('response', None)
            self.error = result.get('error', None)
            self.execute_errors = result.get('execute_errors', None)
            self.is_ok = True if((self.error == None) and (self.execute_errors == None)) else False

        def exception():
            pass


    #############################
    #############################
    # Конструктор

    def __init__(self, vk_api: VK_API, db, event: ChatEventManager.EventObject):
        self.vk_api = vk_api
        self.event = event
        self.db = db

    # Метод messages.send
    def messages_send(self, **kwargs) -> OutputResult:
        req_code = ''
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        for key, value in kwargs.items():
            if(key == 'script'):
                req_code = value + req_code
            else:
                if(isinstance(value, VKVariable.Multi)):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        reqm['random_id'] = 0                                               # Устаналивам random_id

        vk_vars1.var('var reqm', reqm)
        req_code += vk_vars1() + vk_vars2() + 'API.messages.send(reqm);return true;'

        return ChatOutput.OutputResult(self.vk_api.execute(req_code))

    # Метод messages.edit
    def messages_edit(self, **kwargs) -> OutputResult:
        req_code = ''
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        for key, value in kwargs.items():
            if(key == 'script'):
                req_code = value + req_code
            else:
                if(isinstance(value, VKVariable.Multi)):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        reqm['random_id'] = 0                                               # Устаналивам random_id

        vk_vars1.var('var reqm', reqm)
        req_code += vk_vars1() + vk_vars2() + 'API.messages.edit(reqm);return true;'

        return ChatOutput.OutputResult(self.vk_api.execute(req_code))

    def show_snackbar(self, event_id: str, user_id: int, peer_id: int, text: str, script: str = '') -> OutputResult:
        event_data = json.dumps({'type': 'show_snackbar', 'text': text}, ensure_ascii=False, separators=(',', ':'))
        reqm = json.dumps({'event_id': event_id, 'user_id': user_id, 'peer_id': peer_id, 'event_data': event_data},  ensure_ascii=False, separators=(',', ':'))
        return ChatOutput.OutputResult(self.vk_api.execute('{}API.messages.sendMessageEventAnswer({});return true;'.format(script, reqm)))


    def uos_message(self, message_support: bool = True, button_support: bool = True, reply_to_message: bool = True, appeal_to_user: bool = True):
        return ChatOutput.UOSMessage(self, message_support=message_support, button_support=button_support, reply_to_message=reply_to_message, appeal_to_user=appeal_to_user)