# Module Level 2
import json, traceback, time, os
from datetime import datetime
from typing import Callable

from .manager import ChatModes
from . import bot
from .bot import DEFAULT_MESSAGES, ChatStats
from .system import ArgumentParser, Config, PayloadParser, ValueExtractor, dict2bunch, generate_random_string, write_log, ChatDatabase
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

    class EventObject:
        # Исключения
        class InvalidEventType(Exception):
            def __init__(self, message: str):
                self.message = message

        # Конструктор
        def __init__(self, event: dict = {}):
            self.__event = event

            if self.event_type == 'message_new':
                obj = {
                    'group_id': self.__event['group_id'],
                    'date': self.__event['object']['date'],
                    'from_id': self.__event['object']['from_id'],
                    'peer_id': self.__event['object']['peer_id'],
                    'text': self.__event['object']['text'],
                    'conversation_message_id': self.__event['object']['conversation_message_id'],
                    'attachments': self.__event['object']['attachments'],
                    'fwd_messages': self.__event['object']['fwd_messages']
                }

                self.__object = dict2bunch(obj)
            elif self.event_type == 'message_event':
                obj = {
                    'group_id': self.__event['group_id'],
                    'user_id': self.__event['object']['user_id'],
                    'peer_id': self.__event['object']['peer_id'],
                    'payload': self.__event['object']['payload'],
                    'conversation_message_id': self.__event['object']['conversation_message_id'],
                    'event_id': self.__event['object']['event_id'],
                }

                self.__object = dict2bunch(obj)
            else:
                raise ChatEventManager.EventObject.InvalidEventType('object property must be used for message_new or message_event type')

        @property
        def raw(self):
            return self.__event

        @property
        def event_type(self):
            return self.__event["type"]

        @property
        def event_object(self):
            return self.__object

    # Класс системных команд
    class IntenalCommands:
        # Команда репорта ошибки
        class ErrorReportCommand:
            @staticmethod
            def callback_button_command(callin):
                event = callin.event
                payload = callin.payload
                output = callin.output
                db = callin.db

                aos = AdvancedOutputSystem(output, event, db)

                logname = payload.get_str(1, None)

                if logname is None:
                    aos.show_snackbar(text='⛔ Ошибка названия журнала.')
                    return
                elif not os.path.isfile(os.path.join(SYSTEM_PATHS.EXEC_LOG_DIR, "{}.log".format(logname))):
                    aos.show_snackbar(text='⛔ Указанного журнала не существует.')
                    return

                reports_collection = db.get_collection("error_reports")

                find_result = reports_collection.find_one({"log_name": logname})
                if find_result is None:
                    report = {
                        'log_name': logname,
                        'chat_id': event.event_object.peer_id - 2000000000,
                        'user_id': event.event_object.user_id,
                        'date': datetime.now(),
                        'is_solved': False
                    }
                    reports_collection.insert_one(report)

                    # Генерация скрипта отправки соощбения суперпользователю
                    superuser_message_text = 'Репорт ошибки:\n🆔Чат: {}\n📝Журнал: {}'.format(report["chat_id"], logname)
                    send_params = {'peer_id': Config.get("SUPERUSER_ID"), 'random_id': 0, 'message': superuser_message_text}
                    superuser_message_script = "API.messages.send({});".format(json.dumps(send_params, ensure_ascii=False, separators=(',', ':')))

                    message_text = "✅Репорт отправлен.\n\n" + DEFAULT_MESSAGES.MESSAGE_EXECUTION_ERROR.format(logname=logname)
                    aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', message_text), script=superuser_message_script)
                else:
                    aos.show_snackbar(text='⛔ Репорт уже отправлен.')

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

    #############################
    #############################
    # Конструктор

    def __init__(self, vk_api: VK_API, event: dict):
        if event["type"] == "message_new" or event["type"] == "message_event":
            self.__vk_api = vk_api
            self.__event = ChatEventManager.EventObject(event)
            self.__message_commands = {}
            self.__text_button_commands = {}
            self.__callback_button_commands = {}
            self.__message_handlers = []

            self.__db = ChatDatabase(Config.get('DATABASE_HOST'), Config.get('DATABASE_PORT'), Config.get('DATABASE_NAME'), self.__event.event_object.peer_id)

            self.__chat_stats = ChatStats(self.__db)        # Инициализация менеджера ведения статисики
            self.__chat_modes = ChatModes(self.__db)          # Инициализация менеджера режимов беседы

            # Добавление Callback команды для репорта ошибок
            self.add_callback_button_command('report_error', ChatEventManager.IntenalCommands.ErrorReportCommand.callback_button_command)
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
    def add_message_handler(self, callback: Callable, ignore_db: bool = False) -> bool:
        if callback in self.__message_handlers:
            return False
        else:
            handler = {
                'callback': callback,
                'ignore_db': ignore_db
            }
            self.__message_handlers.append(handler)
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
    def run_message_command(self, event: EventObject, output):
        args = ArgumentParser(event.event_object.text)
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
    def run_callback_button_command(self, event: EventObject, output):
        payload = PayloadParser(event.event_object.payload)
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
        if self.__db.is_exists:
            self.__chat_stats.commit(user_id)
        else:
            self.__db.recheck()
            if self.__db.is_exists:
                self.__chat_stats.commit(user_id)

    def __stats_command(self):
        self.__chat_stats.update('command_used_count', 1)

    def __stats_button(self):
        self.__chat_stats.update('button_pressed_count', 1)

    def __stats_message_new(self):
        if self.__event.event_object.from_id > 0:
            self.__chat_stats.update_if_commited_by_last_user('msg_count_in_succession', 1)
            self.__chat_stats.update('msg_count', 1)
            self.__chat_stats.update('symbol_count', len(self.__event.event_object.text))

            attachment_update = {}
            for attachment in self.__event.event_object.attachments:
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
        output = OutputSystem(self.__vk_api)

        if self.__event.event_type == 'message_new':
            if self.__event.event_object.from_id <= 0:
                return False

            self.__stats_message_new()  # Система отслеживания статистики

            try:
                command_result = self.run_message_command(self.__event, output)

                # Система отслеживания статистики
                self.__stats_command()
                # Делаем коммит статистики, если беседа зарегистрирована
                self.__stats_commit(self.__event.event_object.from_id)

                return command_result
            except ChatEventManager.DatabaseException:
                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                keyboard.callback_button('Зарегистрировать', ['bot_reg'], KeyboardBuilder.POSITIVE_COLOR)
                keyboard = keyboard.build()
                output.messages_send(peer_id=self.__event.event_object.peer_id, message=DEFAULT_MESSAGES.MESSAGE_NOT_REGISTERED, forward=bot.reply_to_message_by_event(self.__event), keyboard=keyboard)
                return False
            except ChatEventManager.UnknownCommandException:
                pass
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                logpath = os.path.join(SYSTEM_PATHS.EXEC_LOG_DIR, "{}.log".format(logname))
                write_log(logpath, "Event:\n{}\n\n{}".format(json.dumps(self.__event.raw, indent=4, ensure_ascii=False), trace[:-1]))
                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                keyboard.callback_button("Репорт", ['report_error', logname], KeyboardBuilder.POSITIVE_COLOR)
                output.messages_send(peer_id=self.__event.event_object.peer_id,
                                        message=DEFAULT_MESSAGES.MESSAGE_EXECUTION_ERROR.format(logname=logname),
                                        keyboard=keyboard.build())
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
            if len(self.__message_handlers) > 0:
                    # Подготовка объекта входных данных Callback
                    callin = ChatEventManager.CallbackInputObject()
                    callin.event = self.__event
                    callin.vk_api = self.__vk_api
                    callin.manager = self
                    callin.db = self.__db
                    callin.output = output

                    for handler in self.__message_handlers:
                        if handler['ignore_db'] or self.__db.is_exists:
                            if handler['callback'](callin):
                                handler_result = True
                                break

            # Делаем коммит статистики, если беседа зарегистрирована
            self.__stats_commit(self.__event.event_object.from_id)

            return handler_result

        elif self.__event.event_type == 'message_event':
            try:
                command_result = self.run_callback_button_command(self.__event, output)

                # Система отслеживания статистики
                self.__stats_button()
                # Делаем коммит статистики, если беседа зарегистрирована
                self.__stats_commit(self.__event.event_object.user_id)

                return command_result
            except ChatEventManager.DatabaseException:
                result = output.show_snackbar(self.__event.event_object.event_id, self.__event.event_object.user_id, self.__event.event_object.peer_id, DEFAULT_MESSAGES.SNACKBAR_NOT_REGISTERED)
                return False
            except ChatEventManager.UnknownCommandException:
                output.show_snackbar(self.__event.event_object.event_id, self.__event.event_object.user_id, self.__event.event_object.peer_id, DEFAULT_MESSAGES.SNACKBAR_UNKNOWN_COMMAND)
                return False
            except:
                # Логирование непредвиденной ошибки в файл
                logname = datetime.utcfromtimestamp(time.time() + 10800).strftime("%d%m%Y-{}".format(generate_random_string(5, uppercase=False)))
                trace = traceback.format_exc()
                logpath = os.path.join(SYSTEM_PATHS.EXEC_LOG_DIR, "{}.log".format(logname))
                write_log(logpath, "Event:\n{}\n\n{}".format(json.dumps(self.__event.raw, indent=4, ensure_ascii=False), trace[:-1]))
                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                keyboard.callback_button("Репорт", ['report_error', logname], KeyboardBuilder.POSITIVE_COLOR)
                output.messages_edit(peer_id=self.__event.event_object.peer_id,
                                        conversation_message_id=self.__event.event_object.conversation_message_id,
                                        message=DEFAULT_MESSAGES.MESSAGE_EXECUTION_ERROR.format(logname=logname),
                                        keyboard=keyboard.build())
                return False


class OutputSystem:
    # Класс результата выполнения
    class Result:
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

    def __init__(self, vk_api: VK_API):
        self.__vk_api = vk_api

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

    # Метод messages.send
    def messages_send(self, **kwargs) -> Result:
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        script = ''
        pscript = ''
        for key, value in kwargs.items():
            if key == 'script':
                script = value
            elif key == 'pscript':
                pscript = value
            else:
                if isinstance(value, VKVariable.Multi):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        reqm['random_id'] = 0   # Устаналивам random_id

        vk_vars1.var('var reqm', reqm)

        self.__messages_send_request_count += 1  # Увеличиваем счетчик вызовов
        return OutputSystem.Result(self.__vk_api.execute("{}{}{}API.messages.send(reqm);{}return true;".format(script, vk_vars1(), vk_vars2(), pscript)))

    # Метод messages.edit
    def messages_edit(self, **kwargs) -> Result:
        reqm = {}
        vk_vars1 = VKVariable()
        vk_vars2 = VKVariable()

        script = ''
        pscript = ''
        for key, value in kwargs.items():
            if key == 'script':
                script = value
            elif key == 'pscript':
                pscript = value
            else:
                if isinstance(value, VKVariable.Multi):
                    vk_vars2.var('reqm.'+key, value)
                else:
                    reqm[key] = value

        vk_vars1.var('var reqm', reqm)

        self.__messages_edit_request_count += 1     # Увеличиваем счетчик вызовов
        return OutputSystem.Result(self.__vk_api.execute("{}{}{}API.messages.edit(reqm);{}return true;".format(script, vk_vars1(), vk_vars2(), pscript)))

    def show_snackbar(self, event_id: str, user_id: int, peer_id: int, text: str, script: str = '', pscript: str = '') -> Result:
        event_data = json.dumps({'type': 'show_snackbar', 'text': text}, ensure_ascii=False, separators=(',', ':'))
        reqm = json.dumps({'event_id': event_id, 'user_id': user_id, 'peer_id': peer_id, 'event_data': event_data},  ensure_ascii=False, separators=(',', ':'))

        self.__show_snackbar_request_count += 1     # Увеличиваем счетчик вызовов
        return OutputSystem.Result(self.__vk_api.execute('{}API.messages.sendMessageEventAnswer({});{}return true;'.format(script, reqm, pscript)))


# Класс Продвинутой системы вывода
class AdvancedOutputSystem:
    def __init__(self, output: OutputSystem, event: ChatEventManager.EventObject, db: ChatDatabase):
        self.__output = output
        self.__db = db
        self.__event = event

        self.__prefs = {
            'reply_to_message': True,
            'disable_mentions': True
        }

        self.__prepare_appeal_code()

    def __prepare_appeal_code(self):
        if self.__event.event_type == 'message_new':
            user_id = self.__event.event_object.from_id
        elif self.__event.event_type == 'message_event':
            user_id = self.__event.event_object.user_id
        projection = {'_id': 0, 'chat_settings.user_nicknames.id{}'.format(user_id): 1}
        query = self.__db.find(projection=projection)
        user_nickname = ValueExtractor(query).get('chat_settings.user_nicknames.id{}'.format(user_id), None)
        if user_nickname is not None:
            self.__appeal_code = 'var appeal="@id{userid} ({nickname}), ";'.format(userid=user_id, nickname=user_nickname)
        else:
            self.__appeal_code = 'var appeal="";'

    def prefs(self, **kwargs):
        for k, v in kwargs.items():
            self.__prefs[k] = v

    def messages_send(self, **reqm):
        if self.__event.event_type == 'message_new':
            # Добавление дополнительного кода
            reqm['script'] = self.__appeal_code + reqm.get('script', '')

            # Отключение уведомлений от упоминаний, если так задано в настройках
            if self.__prefs['disable_mentions']:
                reqm['disable_mentions'] = True

            reqm['peer_id'] = self.__event.event_object.peer_id
            if self.__prefs['reply_to_message']:
                forward = {
                    'peer_id': self.__event.event_object.peer_id,
                    'conversation_message_ids': [self.__event.event_object.conversation_message_id],
                    'is_reply': True
                }
                reqm['forward'] = json.dumps(forward, ensure_ascii=False, separators=(',', ':'))

            return self.__output.messages_send(**reqm)

    def messages_edit(self, **reqm):
        if self.__event.event_type == 'message_event':
            # Добавление дополнительного кода
            reqm['script'] = self.__appeal_code + reqm.get('script', '')

            # Отключение уведомлений от упоминаний, если так задано в настройках
            if self.__prefs['disable_mentions']:
                reqm['disable_mentions'] = True

            reqm['peer_id'] = self.__event.event_object.peer_id
            reqm['conversation_message_id'] = self.__event.event_object.conversation_message_id
            reqm['keep_forward_messages'] = self.__prefs['reply_to_message']

            return self.__output.messages_edit(**reqm)

    def show_snackbar(self, **reqm):
        if self.__event.event_type == 'message_event':
            reqm['peer_id'] = self.__event.event_object.peer_id
            reqm['user_id'] = self.__event.event_object.user_id
            reqm['event_id'] = self.__event.event_object.event_id

            return self.__output.show_snackbar(**reqm)