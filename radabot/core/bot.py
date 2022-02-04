# Module Level 1
import json
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

        chat_collection = self.db['chats']
        projection = {
            '_id': 0,
            'owner_id': 1,
            'chat_settings.chat_modes': 1,
            'chat_settings.banned_users': 1,
            'chat_settings.custom_cmds': 1,
            'chat_settings.invited_greeting': 1
        }
        result = chat_collection.find_one(get_chat_db_query(self.peer_id), projection=projection)
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
        

def get_chat_db_query(id: int) -> dict:
    if id > 2000000000:
        id = id - 2000000000
    return {'_id': 'chat{}'.format(id)}

def reply_to_message_by_event(event) -> str:
    forward = {'peer_id': event.peer_id, 'conversation_message_ids': [event.conversation_message_id], 'is_reply': True}
    return json.dumps(forward, ensure_ascii=False, separators=(',', ':'))