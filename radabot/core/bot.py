# Module Level 1
import json, time
from .system import ValueExtractor, ChatDatabase


class DEFAULT_MESSAGES:
    SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON = '⛔ У вас нет прав использовать эту кнопку.'
    SNACKBAR_UNKNOWN_COMMAND = '⛔ Неизвестная команда.'
    SNACKBAR_NOT_REGISTERED = '⛔ Беседа не зарегистрирована.'
    SNACKBAR_INTERNAL_ERROR = '⛔ Внутренняя ошибка команды.'
    SNACKBAR_YOU_HAVE_NO_RIGHTS = '⛔ У вас нет прав использовать это.'

    MESSAGE_MENU_CANCELED = '✅Меню закрыто.'
    MESSAGE_EXECUTION_ERROR = '🆘Ошибка выполнения!\n🆔Журнал: {logname}.'
    MESSAGE_NOT_REGISTERED = '⛔Беседа не зарегистрирована.'
    MESSAGE_YOU_HAVE_NO_RIGHTS = '⛔У вас нет прав использовать это.'


class ChatStats:
    # Стандартное состояние параметров статистики
    STATS_DEFAULT = {
        'msg_count': 0,
        'msg_count_in_succession': 0,
        'symbol_count': 0,
        'audio_msg_count': 0,
        'photo_count': 0,
        'audio_count': 0,
        'video_count': 0,
        'sticker_count': 0,
        # Статистика команд
        'command_used_count': 0,
        'button_pressed_count': 0
    }

    def __init__(self, db: ChatDatabase):
        self.__db = db
        self.__update_object = {'$inc': {}, '$set': {}, '$unset': {}}
        self.__update_stats_last_user = {}
        self.__update_stats = {}

    def update_if_commited_by_last_user(self, name: str, inc: int):
        self.__update_stats_last_user[name] = inc

    def update(self, name: str, inc: int):
        self.__update_stats[name] = inc

    def commit(self, user_id: int) -> bool:
        if user_id > 0:
            query = self.__db.find(projection={'_id': 0, 'chat_stats.last_message_user_id': 1, 'chat_stats.last_daily_time': 1})
            extractor = ValueExtractor(query)

            current_time = time.time()
            current_day = int(current_time - (current_time % 86400))
            last_daily_time = extractor.get('chat_stats.last_daily_time', 0)
            if current_time - last_daily_time >= 86400:
                self.__update_object['$set']['chat_stats.last_daily_time'] = current_day
                if last_daily_time > 0:
                    self.__update_object['$unset']["chat_stats.users_daily.time{}".format(last_daily_time)] = 0

            last_message_user_id = extractor.get('chat_stats.last_message_user_id', 0)
            if user_id != last_message_user_id:
                self.__update_object['$set']['chat_stats.last_message_user_id'] = user_id
            else:
                for key, value in self.__update_stats_last_user.items():
                    self.__update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                    self.__update_object['$inc'][
                        'chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            for key, value in self.__update_stats.items():
                self.__update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                self.__update_object['$inc'][
                    'chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            query_object = {}
            for key, value in self.__update_object.items():
                if len(value) > 0:
                    query_object[key] = value
            result = self.__db.update(query_object)
            if result.modified_count > 0:
                self.__update_object = {'$inc': {}, '$set': {}, '$unset': {}}
                self.__update_stats_last_user = {}
                self.__update_stats = {}
                return True
            else:
                return False
        else:
            return False


def reply_to_message_by_event(event) -> str:
    forward = {'peer_id': event.event_object.peer_id,
               'conversation_message_ids': [event.event_object.conversation_message_id], 'is_reply': True}
    return json.dumps(forward, ensure_ascii=False, separators=(',', ':'))
