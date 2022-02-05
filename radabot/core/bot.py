# Module Level 1
import json, time
from pymongo.database import Database

class ValueExtractor:
    class InvalidArgumentException(Exception):
        def __init__(self, msg: str):
            self.msg = msg

    def __init__(self, data):
        if(isinstance(data, dict) or isinstance(data, list)):
            self.data = data
        else:
            raise ValueExtractor.InvalidArgumentException(r"argument 'data' must be dict or list")

    def get(self, path, default = None):
        path_list = []
        if(isinstance(path, str)):
            path_list = path.split('.')
        elif(isinstance(path, list)):
            path_list = path
        else:
            raise ValueExtractor.InvalidArgumentException(r"argument 'path' must be list or str")

        value = self.data
        for key in path_list:
            if(isinstance(value, dict)):
                value = value.get(key, None)
                if(value == None):
                    return default
            elif(isinstance(value, list)):
                try:
                    value = value[int(key)]
                except (IndexError, ValueError):
                    return default
            else:
                return default
        return value

class ChatData:
    def __init__(self, db: Database, peer_id: int):
        self.db = db
        self.peer_id = peer_id

        chats_collection = self.db['chats']
        projection = {
            '_id': 0,
            'owner_id': 1,
            'chat_settings.chat_modes': 1,
            'chat_settings.banned_users': 1,
            'chat_settings.custom_cmds': 1,
            'chat_settings.invited_greeting': 1
        }
        result = chats_collection.find_one(get_chat_db_query(self.peer_id), projection=projection)
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
    def __init__(self, db: Database, peer_id: int):
        self.db = db['chats']
        self.db_query = get_chat_db_query(peer_id)
        self.update_object = {'$inc': {}, '$set': {}, '$unset': {}}
        self.update_stats_last_user = {}
        self.update_stats = {}

    def updateIfCommitedByLastUser(self, name: str, inc: int):
        self.update_stats_last_user[name] = inc

    def update(self, name: str, inc: int):
        self.update_stats[name] = inc
    
    def commit(self, user_id: int) -> bool:
        if(user_id > 0):
            query = self.db.find_one(self.db_query, projection={'_id': 0, 'chat_stats.last_message_user_id': 1, 'chat_stats.last_daily_time': 1})
            extractor = ValueExtractor(query)

            current_time = time.time()
            current_day = int(current_time - (current_time % 86400))
            last_daily_time = extractor.get('chat_stats.last_daily_time', 0)
            if(current_time - last_daily_time >= 86400):
                self.update_object['$set']['chat_stats.last_daily_time'] = current_day
                if(last_daily_time > 0):
                    self.update_object['$unset']["chat_stats.users_daily.time{}".format(last_daily_time)] = 0

            last_message_user_id = extractor.get('chat_stats.last_message_user_id', 0)
            if(user_id != last_message_user_id):
                self.update_object['$set']['chat_stats.last_message_user_id'] = user_id
            else:
                for key, value in self.update_stats_last_user.items():
                    self.update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                    self.update_object['$inc']['chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            for key, value in self.update_stats.items():
                self.update_object['$inc']['chat_stats.users.id{}.{}'.format(user_id, key)] = value
                self.update_object['$inc']['chat_stats.users_daily.time{}.id{}.{}'.format(current_day, user_id, key)] = value

            query_object = {}
            for key, value in self.update_object.items():
                if(len(value) > 0):
                    query_object[key] = value
            result = self.db.update_one(self.db_query, query_object)
            if(result.modified_count > 0):
                self.update_object = {'$inc': {}, '$set': {}, '$unset': {}}
                self.update_stats_last_user = {}
                self.update_stats = {}
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