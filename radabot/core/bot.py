# Module Level 1
import json, time
from pymongo.database import Database

from radabot.core.system import ValueExtractor

class DEFAULT_MESSAGES:
    NO_RIGHTS_TO_USE_THIS_BUTTON = '⛔ У вас нет прав использовать эту кнопку.'
    MENU_CANCELED = '✅Меню закрыто.'

class ChatData:
    def __init__(self, db: Database, peer_id: int):
        self.__db = db
        self.__peer_id = peer_id

        chats_collection = self.__db['chats']
        projection = {
            '_id': 0,
            'owner_id': 1,
            'chat_settings.chat_modes': 1,
            'chat_settings.banned_users': 1,
            'chat_settings.custom_cmds': 1,
            'chat_settings.invited_greeting': 1
        }
        result = chats_collection.find_one(get_chat_db_query(self.__peer_id), projection=projection)
        if(result == None):
            self.exists_in_database = False
        else:
            self.exists_in_database = True

            extractor = ValueExtractor(result)
            self.owner_id = extractor.get('owner_id', 0)
            self.chat_modes = extractor.get('chat_settings.chat_modes', None)
            self.banned_users = extractor.get('chat_settings.banned_users', [])
            self.custom_cmds = extractor.get('chat_settings.custom_cmds', {})
            self.invited_greeting = extractor.get('chat_settings.invited_greeting', '')
        
class ChatStats:
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

    def __init__(self, db: Database, peer_id: int):
        self.__db = db['chats']
        self.__db_query = get_chat_db_query(peer_id)
        self.__update_object = {'$inc': {}, '$set': {}, '$unset': {}}
        self.__update_stats_last_user = {}
        self.__update_stats = {}

    def updateIfCommitedByLastUser(self, name: str, inc: int):
        self.__update_stats_last_user[name] = inc

    def update(self, name: str, inc: int):
        self.__update_stats[name] = inc
    
    def commit(self, user_id: int) -> bool:
        if(user_id > 0):
            query = self.__db.find_one(self.__db_query, projection={'_id': 0, 'chat_stats.last_message_user_id': 1, 'chat_stats.last_daily_time': 1})
            extractor = ValueExtractor(query)

            current_time = time.time()
            current_day = int(current_time - (current_time % 86400))
            last_daily_time = extractor.get('chat_stats.last_daily_time', 0)
            if(current_time - last_daily_time >= 86400):
                self.__update_object['$set']['chat_stats.last_daily_time'] = current_day
                if(last_daily_time > 0):
                    self.__update_object['$unset']["chat_stats.users_daily.time{}".format(last_daily_time)] = 0

            last_message_user_id = extractor.get('chat_stats.last_message_user_id', 0)
            if(user_id != last_message_user_id):
                self.__update_object['$set']['chat_stats.last_message_user_id'] = user_id
            else:
                for key, value in self.__update_stats_last_user.items():
                    self.__update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                    self.__update_object['$inc']['chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            for key, value in self.__update_stats.items():
                self.__update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                self.__update_object['$inc']['chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            query_object = {}
            for key, value in self.__update_object.items():
                if(len(value) > 0):
                    query_object[key] = value
            result = self.__db.update_one(self.__db_query, query_object)
            if(result.modified_count > 0):
                self.__update_object = {'$inc': {}, '$set': {}, '$unset': {}}
                self.__update_stats_last_user = {}
                self.__update_stats = {}
                return True
            else:
                return False
        else:
            return False

def get_chat_db_query(id: int) -> dict:
    if id > 2000000000:
        id = id - 2000000000
    return {'_id': 'chat{}'.format(id)}

def reply_to_message_by_event(event) -> str:
    forward = {'peer_id': event.bunch.object.peer_id, 'conversation_message_ids': [event.bunch.object.conversation_message_id], 'is_reply': True}
    return json.dumps(forward, ensure_ascii=False, separators=(',', ':'))