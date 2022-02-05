# Module Level 2
from distutils import command
import json, traceback, time
from datetime import datetime
from typing import Callable
from pymongo import MongoClient
from pymongo.database import Database
from bunch import Bunch
from . import bot
from .bot import ChatData, ChatStats
from .system import ArgumentParser, Config, generate_random_string, write_log
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
        def __init__(self, event: dict):
            self.raw = event
            self.type = event['type']
            self.group_id = event['group_id']
            self.event_id = event['event_id']
            self.object = self.__dict2bunch(event['object'])

        def __bunchingList(self, l: list) -> list:
            nl = []
            for i in l:
                if(isinstance(i, dict)):
                    nl.append(self.__dict2bunch(i))
                elif(isinstance(i, list)):
                    nl.append(self.__bunchingList(i))
                else:
                    nl.append(i)
            return nl

        def __dict2bunch(self, d: dict) -> Bunch:
            b = {}
            for k, v in d.items():
                if(isinstance(v, dict)):
                    b[k] = self.__dict2bunch(v)
                elif(isinstance(v, list)):
                    b[k] = self.__bunchingList(v)
                else:
                    b[k] = v
            return Bunch(b)

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

            database_info = Config.get('DATABASE')
            self.mongo_client = MongoClient(database_info['HOST'], database_info['PORT'])
            self.db = self.mongo_client[database_info['NAME']]

            self.chat_data = ChatData(self.db, self.event.object.peer_id)
            self.chat_stats = ChatStats(self.db, self.event.object.peer_id)
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
            args = ArgumentParser(event.object.text)
            command = args.str(0, '').lower()
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
            payload = event.object.payload
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
    # Метод ведения стастики

    def __stats_commit(self, user_id):
        self.chat_stats.commit(user_id)

    def __stats_command(self):
        self.chat_stats.update('command_used_count', 1)

    def __stats_button(self):
        self.chat_stats.update('button_pressed_count', 1)

    def __stats_message_new(self):
        if(self.event.object.from_id > 0):
            self.chat_stats.updateIfCommitedByLastUser('msg_count_in_succession', 1)
            self.chat_stats.update('msg_count', 1)
            self.chat_stats.update('simbol_count', len(self.event.object.text))

            attachment_update = {}
            for attachment in self.event.object.attachments:
                if(attachment.type == 'sticker'):
                    if('sticker_count' in attachment_update):
                        attachment_update['sticker_count'] += 1
                    else:
                        attachment_update['sticker_count'] = 1
                elif(attachment.type == 'photo'):
                    if('photo_count' in attachment_update):
                        attachment_update['photo_count'] += 1
                    else:
                        attachment_update['photo_count'] = 1
                elif(attachment.type == 'video'):
                    if('video_count' in attachment_update):
                        attachment_update['video_count'] += 1
                    else:
                        attachment_update['video_count'] = 1
                elif(attachment.type == 'audio_message'):
                    if('audio_msg_count' in attachment_update):
                        attachment_update['audio_msg_count'] += 1
                    else:
                        attachment_update['audio_msg_count'] = 1
                elif(attachment.type == 'audio'):
                    if('audio_count' in attachment_update):
                        attachment_update['audio_count'] += 1
                    else:
                        attachment_update['audio_count'] = 1
            for k, v in attachment_update.items():
                self.chat_stats.update(k, v)


    #############################
    #############################
    # Метод обработки события

    def handle(self) -> bool:
        if self.event.type == 'message_new':
            if(self.event.object.from_id <= 0):
                return False

            output = ChatOutput(self.vk_api, self.db, self.event)
            self.__stats_message_new() # Система отслеживания статистики

            try:
                command_result = self.runMessageCommand(self.event, output)

                # Система отслеживания статистики
                self.__stats_command()
                self.__stats_commit(self.event.object.from_id)

                return command_result
            except ChatEventManager.DatabaseException:
                keyboard = keyboard_inline([[callback_button('Зарегистрировать', ['bot_reg'], 'positive')]])
                output.messages_send(peer_id=self.event.object.peer_id, message='⛔Беседа не зарегистрирована.', forward=bot.reply_to_message_by_event(self.event), keyboard=keyboard)
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                write_log(SYSTEM_PATHS.EXEC_LOG_DIR+"{}.log".format(logname), "Event:\n{}\n\n{}".format(json.dumps(self.event.raw, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_send(peer_id=self.event.object.peer_id, message='🆘Ошибка выполнения!\n🆔Журнал: {}.'.format(logname))
                return False

            try:
                command_result = self.runMessageCommand(self.event, output)

                # Система отслеживания статистики
                self.__stats_button()
                self.__stats_commit(self.event.object.from_id)

                return command_result
            except ChatEventManager.DatabaseException:
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                return False

            # Обработка сообщений вне командного пространства
            handler_result = False
            if(self.chat_data.exists_in_database):
                if(len(self.message_handler_list) > 0):
                    # Подготовка объекта входных данных Callback'а
                    callin = ChatEventManager.CallbackInputObject()
                    callin.event = self.event
                    callin.vk_api = self.vk_api
                    callin.manager = self
                    callin.db = self.db
                    callin.output = output

                    for handler in self.message_handler_list:
                        if(handler(callin)):
                            handler_result = True
                            break
                self.__stats_commit(self.event.object.from_id)
            return handler_result

        elif self.event.type == 'message_event':
            output = ChatOutput(self.vk_api, self.db, self.event)
            try:
                command_result = self.runCallbackButtonCommand(self.event, output)

                # Система отслеживания статистики
                self.__stats_button()
                self.__stats_commit(self.event.object.user_id)

                return command_result
            except ChatEventManager.DatabaseException:
                result = output.show_snackbar(self.event.object.event_id, self.event.object.user_id, self.event.object.peer_id, '⛔ Беседа не зарегистрирована.')
                write_log(SYSTEM_PATHS.ERROR_LOG_FILE, "{} {}".format(result.error, result.execute_errors))
                return False
            except ChatEventManager.UnknownCommandException:
                output.show_snackbar(self.event.object.event_id, self.event.object.user_id, self.event.object.peer_id, '⛔ Неизвестная команда.')
                return False
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                write_log(SYSTEM_PATHS.EXEC_LOG_DIR+"{}.log".format(logname), "Event:\n{}\n\n{}".format(json.dumps(self.event.raw, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_edit(peer_id=self.event.object.peer_id, conversation_message_id=self.event.object.conversation_message_id, message='🆘Ошибка выполнения!\n🆔Журнал: {}.'.format(logname))
                return False

class ChatOutput:
    #############################
    #############################
    # Внутренние классы

    # Класс Единой системы вывода
    class UOSMessage:
        def __init__(self, output):
            self.output = output
            self.current_prefs = {
                'message_support': True,
                'button_support': True,
                'reply_to_message': True,
                'appeal_to_user': True
            }
            self.send_object = {}
            self.edit_object = {}

        def __call__(self):
            if(self.output.event.type == 'message_new'):
                if(self.current_prefs['message_support']):
                    reqm = self.send_object

                    reqm['peer_id'] = self.output.event.object.peer_id
                    if(self.current_prefs['reply_to_message']):
                        forward = {
                            'peer_id': self.output.event.object.peer_id,
                            'conversation_message_ids': [self.output.event.object.conversation_message_id],
                            'is_reply': True
                        }
                        reqm['forward'] = json.dumps(forward, ensure_ascii=False, separators=(',', ':'))

                    result = self.output.messages_send(**reqm)
                else:
                    return False
            elif(self.output.event.type == 'message_event'):
                reqm = {}
                if(self.v['button_support']):
                    reqm = self.edit_object

                    reqm['peer_id'] = self.output.event.object.peer_id
                    reqm['conversation_message_id'] = self.output.event.object.conversation_message_id
                    reqm['keep_forward_messages'] = self.current_prefs['reply_to_message']

                    result = self.output.messages_edit(**reqm)
                else:
                    return False

        def prefs(self, **kwargs):
            for k, v in kwargs.items():
                self.current_prefs[k] = v

        def send(self, **kwargs):
            self.send_object = kwargs

        def edit(self, **kwargs):
            self.edit_object = kwargs

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

    def __init__(self, vk_api: VK_API, db: Database, event: ChatEventManager.EventObject):
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

        vk_vars1.var('var reqm', reqm)
        req_code += vk_vars1() + vk_vars2() + 'API.messages.edit(reqm);return true;'

        return ChatOutput.OutputResult(self.vk_api.execute(req_code))

    def show_snackbar(self, event_id: str, user_id: int, peer_id: int, text: str, script: str = '') -> OutputResult:
        event_data = json.dumps({'type': 'show_snackbar', 'text': text}, ensure_ascii=False, separators=(',', ':'))
        reqm = json.dumps({'event_id': event_id, 'user_id': user_id, 'peer_id': peer_id, 'event_data': event_data},  ensure_ascii=False, separators=(',', ':'))
        return ChatOutput.OutputResult(self.vk_api.execute('{}API.messages.sendMessageEventAnswer({});return true;'.format(script, reqm)))