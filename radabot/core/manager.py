# Module Level 1
from email.policy import default
from pymongo.database import Database
from radabot.core.bot import get_chat_db_query
from radabot.core.system import ManagerData, ValueExtractor

class UserPermission:
    default_states = {}

    # Исключние неправильного аргумента
    class InvalidArgumentException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Исключние неизвестного разрешения
    class UnknownPermissionException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Исключение изменения разрешений владельца чата
    class OwnerPermissionException(Exception):
        def __init__(self, message: str):
            self.message = message

    @staticmethod
    def initDefaultStates():
        default = {}
        for k, v in ManagerData.getUserPermissions().items():
            default[k] = v['default']
        UserPermission.default_states = default

    def __init__(self, db: Database, user_id: int, peer_id: int):
        self.db_collection = db['chats']
        self.db_query = get_chat_db_query(peer_id)
        self.user_id = user_id

        if(user_id > 0):
            query = self.db_collection.find_one(self.db_query, projection={'_id': 0, 'owner_id': 1, 'chat_settings.user_permissions.id{}'.format(user_id): 1})
            extractor = ValueExtractor(query)

            self.commit = {'$set': {}, '$unset': {}}
            
            self.owner_id = extractor.get('owner_id', 0)
            if(user_id == self.owner_id):
                # Если пользователь является владельцем чата
                self.user_permissions = UserPermission.default_states
                for k in list(self.user_permissions.keys()):
                    self.user_permissions[k] = True
            else:
                # Если пользователь является участником чата
                self.user_permissions = {**default, **extractor.get('chat_settings.user_permissions.id{}'.format(user_id), {})}
                for k in list(self.user_permissions.keys()):
                    if(not (k in UserPermission.default_states)):
                        self.user_permissions.pop(k)
                        self.commit['$unset']['chat_settings.user_permissions.id{}.{}'.format(user_id, k)] = 0
        else:
            raise UserPermission.InvalidArgumentException("Invalid 'user_id' parameter")

    def getAll(self):
        return self.user_permissions

    def set(self, id: str, state: bool):
        if(id in self.user_permissions):
            if(self.owner_id == self.user_id):
                raise UserPermission.OwnerPermissionException("User 'id{}' is owner".format(self.user_id))
            else:
                self.user_permissions[id] = state
                self.commit['$set']['chat_settings.user_permissions.id{}.{}'.format(self.user_id, id)] = state
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def get(self, id: str):
        if(id in self.user_permissions):
            return self.user_permissions[id]
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def commit(self):
        if(len(self.commit['$set']) == 0):
            self.commit.pop('$set')
        if(len(self.commit['$unset']) == 0):
            self.commit.pop('$unset')
        
        if(len(self.commit) > 0):
            result = self.db_collection.update_one(self.db_query, self.commit)
            if(result.modified_count > 0):
                return True
            else:
                return False
        else:
            return False
