# Module Level 2
import json, traceback, time, os
from datetime import datetime
from typing import Callable
from pymongo import MongoClient

from radabot.core.manager import ChatModes
from . import bot
from .bot import DEFAULT_MESSAGES, ChatStats
from .system import ArgumentParser, Config, PayloadParser, ValueExtractor, bunchingList, generate_random_string, write_log, ChatDatabase
from .vk import VK_API, KeyboardBuilder, VKVariable
from .system import SYSTEM_PATHS


class ChatEventManager:
    #############################
    #############################
    # Внутренние классы

    # Класс для передачи параметров внутрь функций
    class CallbackInputObject:
        def __init__(self):
            self.event = None						                    # Поле данных события
            self.args = None										    # Поле аргументов текстовой команды
            self.payload = None									        # Поле полезной нагрузки кнопки
            self.manager = None									        # Объект EventManager's
            self.vk_api = None					                        # Объект VK API
            self.db = None		                                        # Объект базы данных
            self.output = None										    # Объект системы вывода
            self.chat_data = None                                       # Объект Системы обработки данных чата

    class MessageCommandEventObject:
        # Конструктор
        def __init__(self, event: dict = {}):
            extractor = ValueExtractor(event)

            self.group_id = extractor.get('group_id', 0)
            self.date = extractor.get('object.date', time.time())
            self.from_id = extractor.get('object.from_id', 0)
            self.peer_id = extractor.get('object.peer_id', 0)
            self.text = extractor.get('object.text', '')
            self.conversation_message_id = extractor.get('object.conversation_message_id', 0)
            self.attachments = bunchingList(extractor.get('object.attachments', []))
            self.fwd_messages = bunchingList(extractor.get('object.fwd_messages', []))

    class CallbackButtonCommandEventObject:
        # Конструктор
        def __init__(self, event: dict = {}):
            extractor = ValueExtractor(event)

            self.group_id = extractor.get('group_id', 0)
            self.peer_id = extractor.get('object.peer_id', 0)
            self.user_id = extractor.get('object.user_id', 0)
            self.payload = bunchingList(extractor.get('object.payload', []))
            self.conversation_message_id = extractor.get('object.conversation_message_id', 0)
            self.event_id = extractor.get('object.event_id', 0)

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
            super(ChatEventManager.UnknownCommandException, self).__init__(message)
            self.command = command
            self.message = message

    # Исключние неправильных событий при создании объекта ChatEventManager
    class InvalidEventException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Вызов Message команды из Callback кнопки
    @staticmethod
    def __run_from_callback_button(callin: CallbackInputObject):
        manager = callin.manager
        event = callin.event
        payload = callin.payload
        output = callin.output

        testing_user_id = payload.int(2, event.user_id)
        if testing_user_id == event.user_id:
            run_event = ChatEventManager.MessageCommandEventObject()
            run_event.group_id = event.group_id
            run_event.from_id = event.user_id
            run_event.peer_id = event.peer_id
            run_event.text = payload.str(1, '')

            manager.run_message_command(run_event, output)

            if output.messages_edit_request_count == 0 and output.show_snackbar_request_count:
                output.show_snackbar(event['object']['event_id'], event['object']['user_id'], event.bunch.object.peer_id, '✅ Выполнено!.')
        else:
            output.show_snackbar(event['object']['event_id'], event['object']['user_id'], event.bunch.object.peer_id, DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)

    #############################
    #############################
    # Конструктор

    def __init__(self, vk_api: VK_API, event: dict):
        if event["type"] == "message_new" or event["type"] == "message_event":
            self.__vk_api = vk_api
            self.__event = event
            self.__message_commands = {}
            self.__text_button_commands = {}
            self.__callback_button_commands = {}
            self.__message_handlers = []

            self.__db = ChatDatabase(Config.get('DATABASE_HOST'), Config.get('DATABASE_PORT'), Config.get('DATABASE_NAME'), self.__event['object']['peer_id'])

            self.__chat_stats = ChatStats(self.__db)        # Инициализация менеджера ведения статисики
            #self.__chat_modes = ChatModes(self.__)          # Инициализация менеджера режимов беседы

            # Добавление Callback команды запуска Message команды
            self.add_callback_button_command('run', ChatEventManager.__run_from_callback_button, ignore_db=True)
        else:
            raise ChatEventManager.InvalidEventException('ChatEventManager support only message_new & message_event types')

    #############################
    #############################
    # Методы доступа к private полям

    @property
    def event(self):
        return self.__event
    
    @property
    def chat_stats(self):
        return self.__chat_stats

    @property
    def chat_modes(self):
        return self.__chat_modes

    #############################
    #############################
    # Действия над командами

    # Добавление Message команды
    def add_message_command(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if command in self.__message_commands:
            return False
        else:
            self.__message_commands[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    # Добавление Text Button команды
    def add_text_button_command(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if command in self.__text_button_commands:
            return False
        else:
            self.__message_commands[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    # Добавление Callback Button команды
    def add_callback_button_command(self, command: str, callback: Callable, args: list = [], ignore_db: bool = False) -> bool:
        command = command.lower()
        if command in self.__callback_button_commands:
            return False
        else:
            self.__callback_button_commands[command] = {
                'callback': callback,
                'args': args,
                'ignore_db': ignore_db
            }
            return True

    # Добавление Message обработчика (Если не выполнена Message команда)
    def add_message_handler(self, callback: Callable) -> bool:
        if callback in self.__message_handlers:
            return False
        else:
            self.__message_handlers.append(callback)
            return True

    # Проверка существования Message команды
    def is_message_command(self, command: str) -> bool:
        return command in self.__message_commands

    # Проверка существования Text Button команды
    def is_text_button_command(self, command: str) -> bool:
        return command in self.__text_button_commands

    # Проверка существования Callback Button команды
    def is_callback_button_command(self, command: str) -> bool:
        return command in self.__callback_button_commands

    # Запуск обработки Message команды
    def run_message_command(self, event: MessageCommandEventObject, output):
        args = ArgumentParser(event.text)
        command = args.get_str(0, '').lower()
        if self.is_message_command(command):
            if not self.__message_commands[command]['ignore_db'] and not self.__db.is_exists:
                raise ChatEventManager.DatabaseException('Command \'{}\' requires document in Database'.format(command))

            # Подготовка объекта входных данных Callback
            callin = ChatEventManager.CallbackInputObject()
            callin.event = event
            callin.args = args
            callin.vk_api = self.__vk_api
            callin.manager = self
            callin.db = self.__db
            callin.output = output

            callback = self.__message_commands[command]["callback"]
            callback_args = [callin] + self.__message_commands[command]["args"]
            callback(*callback_args)
            return True
        else:
            raise ChatEventManager.UnknownCommandException('Command \'{}\' not found'.format(command), command)

    # Запуск обработки Callback Button команды
    def run_callback_button_command(self, event: CallbackButtonCommandEventObject, output):
        payload = PayloadParser(event.payload)
        command = payload.get_str(0, '')
        if self.is_callback_button_command(command):
            if not self.__callback_button_commands[command]['ignore_db'] and not self.__db.is_exists:
                raise ChatEventManager.DatabaseException('Command \'{}\' requires document in Database'.format(command))

            # Подготовка объекта входных данных Callback
            callin = ChatEventManager.CallbackInputObject()
            callin.event = event
            callin.payload = payload
            callin.vk_api = self.__vk_api
            callin.manager = self
            callin.db = self.__db
            callin.output = output

            callback = self.__callback_button_commands[command]["callback"]
            callback_args = [callin] + self.__callback_button_commands[command]["args"]
            callback(*callback_args)
            return True
        else:
            raise ChatEventManager.UnknownCommandException('Command \'{}\' not found'.format(command), command)

    # Получение списка Message команд
    @property
    def message_command_list(self):
        return list(self.__message_commands)

    # Получение списка Text Button команд
    @property
    def text_button_command_list(self):
        return list(self.__text_button_commands)

    # Получение списка Callback Button команд
    @property
    def callback_button_command_list(self):
        return list(self.__callback_button_commands)

    #############################
    #############################
    # Метод ведения статистики

    def __stats_commit(self, user_id):
        self.__chat_stats.commit(user_id)

    def __stats_command(self):
        self.__chat_stats.update('command_used_count', 1)

    def __stats_button(self):
        self.__chat_stats.update('button_pressed_count', 1)

    def __stats_message_new(self):
        if self.__event['object']['from_id'] > 0:
            self.__chat_stats.update_if_commited_by_last_user('msg_count_in_succession', 1)
            self.__chat_stats.update('msg_count', 1)
            self.__chat_stats.update('symbol_count', len(self.__event['object']['text']))

            attachment_update = {}
            for attachment in self.__event['object']['attachments']:
                if attachment.type == 'sticker':
                    if 'sticker_count' in attachment_update:
                        attachment_update['sticker_count'] += 1
                    else:
                        attachment_update['sticker_count'] = 1
                elif attachment.type == 'photo':
                    if 'photo_count' in attachment_update:
                        attachment_update['photo_count'] += 1
                    else:
                        attachment_update['photo_count'] = 1
                elif attachment.type == 'video':
                    if 'video_count' in attachment_update:
                        attachment_update['video_count'] += 1
                    else:
                        attachment_update['video_count'] = 1
                elif attachment.type == 'audio_message':
                    if 'audio_msg_count' in attachment_update:
                        attachment_update['audio_msg_count'] += 1
                    else:
                        attachment_update['audio_msg_count'] = 1
                elif attachment.type == 'audio':
                    if 'audio_count' in attachment_update:
                        attachment_update['audio_count'] += 1
                    else:
                        attachment_update['audio_count'] = 1
            for k, v in attachment_update.items():
                self.__chat_stats.update(k, v)

    #############################
    #############################
    # Метод обработки события

    def handle(self) -> bool:
        if self.__event['type'] == 'message_new':
            if self.__event['object']['from_id'] <= 0:
                return False

            output = ChatOutput(self.__vk_api, self.__event)
            self.__stats_message_new()  # Система отслеживания статистики

            try:
                event = ChatEventManager.MessageCommandEventObject(self.__event)
                command_result = self.run_message_command(event, output)

                # Система отслеживания статистики
                self.__stats_command()
                self.__stats_commit(self.__event['object']['from_id'])

                return command_result
            except ChatEventManager.DatabaseException:
                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                keyboard.callback_button('Зарегистрировать', ['bot_reg'], KeyboardBuilder.POSITIVE_COLOR)
                keyboard = keyboard.build()
                output.messages_send(peer_id=self.__event['object']['peer_id'], message=DEFAULT_MESSAGES.MESSAGE_NOT_REGISTERED, forward=bot.reply_to_message_by_event(self.__event), keyboard=keyboard)
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                logpath = os.path.join(SYSTEM_PATHS.EXEC_LOG_DIR, "{}.log".format(logname))
                write_log(logpath, "Event:\n{}\n\n{}".format(json.dumps(self.__event, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_send(peer_id=self.__event['object']['peer_id'], message=DEFAULT_MESSAGES.MESSAGE_EXECUTION_ERROR.format(logname=logname))
                return False

            try:
                '''
                command_result = self.run_message_command(self.__event, output)

                # Система отслеживания статистики
                self.__stats_button()
                self.__stats_commit(self.__event['object']['from_id'])

                return command_result
                '''
                raise ChatEventManager.UnknownCommandException('', '')
            except ChatEventManager.DatabaseException:
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                return False

            # Обработка сообщений вне командного пространства
            handler_result = False
            if self.__chat_data.exists_in_database:
                if len(self.__message_handlers) > 0:
                    # Подготовка объекта входных данных Callback
                    callin = ChatEventManager.CallbackInputObject()
                    callin.event = self.__event
                    callin.vk_api = self.__vk_api
                    callin.manager = self
                    callin.db = self.__db
                    callin.output = output

                    for handler in self.__message_handlers:
                        if handler(callin):
                            handler_result = True
                            break
                self.__stats_commit(self.__event['object']['from_id'])
            return handler_result

        elif self.__event['type'] == 'message_event':
            output = ChatOutput(self.__vk_api, self.__event)
            try:
                event = ChatEventManager.CallbackButtonCommandEventObject(self.__event)
                command_result = self.run_callback_button_command(event, output)

                # Система отслеживания статистики
                self.__stats_button()
                self.__stats_commit(self.__event['object']['user_id'])

                return command_result
            except ChatEventManager.DatabaseException:
                result = output.show_snackbar(self.__event['object']['event_id'], self.__event['object']['user_id'], self.__event['object']['peer_id'], DEFAULT_MESSAGES.SNACKBAR_NOT_REGISTERED)
                return False
            except ChatEventManager.UnknownCommandException:
                output.show_snackbar(self.__event['object']['event_id'], self.__event['object']['user_id'], self.__event['object']['peer_id'], DEFAULT_MESSAGES.SNACKBAR_UNKNOWN_COMMAND)
                return False
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                logpath = os.path.join(SYSTEM_PATHS.EXEC_LOG_DIR, "{}.log".format(logname))
                write_log(logpath, "Event:\n{}\n\n{}".format(json.dumps(self.__event, indent=4, ensure_ascii=False), trace[:-1]))
                output.messages_edit(peer_id=self.__event['object']['peer_id'], conversation_message_id=self.__event['object']['conversation_message_id'], message=DEFAULT_MESSAGES.MESSAGE_EXECUTION_ERROR.format(logname=logname))
                return False


class ChatOutput:
    #############################
    #############################
    # Внутренние классы

    # Класс Единой системы вывода
    class UOS:
        def __init__(self, output, db: ChatDatabase):
            self.__output = output
            self.__db = db
            self.__current_prefs = {
                'message_support': True,
                'button_support': True,
                'reply_to_message': True,
                'disable_mentions': True
            }

            if self.__output.event['type'] == 'message_new':
                self.__is_message_new = True
                self.__is_message_event = False
            elif self.__output.event['type'] == 'message_event':
                self.__is_message_new = False
                self.__is_message_event = True

            self.__appeal_code = 'var appeal="";'

        @property
        def is_message_new(self):
            return self.__is_message_new

        @property
        def is_message_event(self):
            return self.__is_message_event

        def set_appeal(self, user_id: int):
            if user_id > 0:
                projection = {'_id': 0, 'chat_settings.user_nicknames.id{}'.format(user_id): 1}
                query = self.__db.find(projection=projection)
                user_nickname = ValueExtractor(query).get('chat_settings.user_nicknames.id{}'.format(user_id), None)
                if user_nickname is not None:
                    self.__appeal_code = 'var appeal="@id{userid} ({nickname}), ";'.format(userid=user_id, nickname=user_nickname)

        def prefs(self, **kwargs):
            for k, v in kwargs.items():
                self.__current_prefs[k] = v

        def messages_send(self, **reqm):
            if self.__is_message_new:
                if self.__current_prefs['message_support']:
                    # Добавление дополнительного кода
                    reqm['script'] = self.__appeal_code + reqm.get('script', '')

                    # Отключение уведомлений от упоминаний, если так задано в настройках
                    if self.__current_prefs['disable_mentions']:
                        reqm['disable_mentions'] = True

                    reqm['peer_id'] = self.__output.event['object']['peer_id']
                    if self.__current_prefs['reply_to_message']:
                        forward = {
                            'peer_id': self.__output.event['object']['peer_id'],
                            'conversation_message_ids': [self.__output.event['object']['conversation_message_id']],
                            'is_reply': True
                        }
                        reqm['forward'] = json.dumps(forward, ensure_ascii=False, separators=(',', ':'))

                    self.__output.messages_send(**reqm)
                else:
                    return False

        def messages_edit(self, **reqm):
            if self.__is_message_event:
                if self.__current_prefs['button_support']:
                    # Добавление дополнительного кода
                    reqm['script'] = self.__appeal_code + reqm.get('script', '')

                    # Отключение уведомлений от упоминаний, если так задано в настройках
                    if self.__current_prefs['disable_mentions']:
                        reqm['disable_mentions'] = True

                    reqm['peer_id'] = self.__output.event['object']['peer_id']
                    reqm['conversation_message_id'] = self.__output.event['object']['conversation_message_id']
                    reqm['keep_forward_messages'] = self.__current_prefs['reply_to_message']

                    self.__output.messages_edit(**reqm)
                else:
                    return False

        def show_snackbar(self, **reqm):
            if self.__is_message_event:
                if self.__current_prefs['button_support']:
                    reqm['peer_id'] = self.__output.event['object']['peer_id']
                    reqm['user_id'] = self.__output.event['object']['user_id']
                    reqm['event_id'] = self.__output.event['object']['event_id']

                    self.__output.show_snackbar(**reqm)
                else:
                    return False

    # Класс результата выполнения
    class OutputResult:
        def __init__(self, vk_result: str):
            result = json.loads(vk_result)
            self.response = result.get('response', None)
            self.error = result.get('error', None)
            self.execute_errors = result.get('execute_errors', None)
            self.is_ok = True if((self.error is None) and (self.execute_errors is None)) else False

        def exception(self):
            pass

    #############################
    #############################
    # Конструктор

    def __init__(self, vk_api: VK_API, event: dict):
        self.__vk_api = vk_api
        self.__event = event

        self.__messages_send_request_count = 0  # Количество вызовов messages_send
        self.__messages_edit_request_count = 0  # Количество вызовов messages_edit
        self.__show_snackbar_request_count = 0  # Количество вызовов show_snackbar

    @property
    def messages_send_request_count(self):
        return self.__messages_send_request_count

    @property
    def messages_edit_request_count(self):
        return self.__messages_edit_request_count

    @property
    def show_snackbar_request_count(self):
        return self.__show_snackbar_request_count

    @property
    def event(self):
        return self.__event

    @property
    def db(self):
        return self.__db

    def uos(self, db: ChatDatabase) -> UOS:
        return ChatOutput.UOS(self, db)

    # Метод messages.send
    def messages_send(self, **kwargs) -> OutputResult:
        req_code = ''
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        for key, value in kwargs.items():
            if key == 'script':
                req_code = value + req_code
            else:
                if isinstance(value, VKVariable.Multi):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        reqm['random_id'] = 0   # Устаналивам random_id

        vk_vars1.var('var reqm', reqm)
        req_code += vk_vars1() + vk_vars2() + 'API.messages.send(reqm);return true;'

        self.__messages_send_request_count += 1  # Увеличиваем счетчик вызовов
        return ChatOutput.OutputResult(self.__vk_api.execute(req_code))

    # Метод messages.edit
    def messages_edit(self, **kwargs) -> OutputResult:
        req_code = ''
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        for key, value in kwargs.items():
            if key == 'script':
                req_code = value + req_code
            else:
                if isinstance(value, VKVariable.Multi):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        vk_vars1.var('var reqm', reqm)
        req_code += vk_vars1() + vk_vars2() + 'API.messages.edit(reqm);return true;'

        self.__messages_edit_request_count += 1     # Увеличиваем счетчик вызовов
        return ChatOutput.OutputResult(self.__vk_api.execute(req_code))

    def show_snackbar(self, event_id: str, user_id: int, peer_id: int, text: str, script: str = '') -> OutputResult:
        event_data = json.dumps({'type': 'show_snackbar', 'text': text}, ensure_ascii=False, separators=(',', ':'))
        reqm = json.dumps({'event_id': event_id, 'user_id': user_id, 'peer_id': peer_id, 'event_data': event_data},  ensure_ascii=False, separators=(',', ':'))

        self.__show_snackbar_request_count += 1     # Увеличиваем счетчик вызовов
        return ChatOutput.OutputResult(self.__vk_api.execute('{}API.messages.sendMessageEventAnswer({});return true;'.format(script, reqm)))
