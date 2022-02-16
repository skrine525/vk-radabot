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
        d = {}
        for k, v in ManagerData.getUserPermissions().items():
            d[k] = v['default']
        UserPermission.default_states = d

    def __init__(self, db: Database, user_id: int, peer_id: int):
        self.__db_collection = db['chats']
        self.__db_query = get_chat_db_query(peer_id)
        self.__user_id = user_id

        if user_id > 0:
            query = self.__db_collection.find_one(self.__db_query, projection={'_id': 0, 'owner_id': 1, 'chat_settings.user_permissions.id{}'.format(user_id): 1})
            extractor = ValueExtractor(query)

            self.__commit_data = {'$set': {}, '$unset': {}}
            
            self.__owner_id = extractor.get('owner_id', 0)
            if user_id == self.__owner_id:
                # Если пользователь является владельцем чата
                self.__user_permissions = UserPermission.default_states
                for k in list(self.__user_permissions.keys()):
                    self.__user_permissions[k] = True
            else:
                # Если пользователь является участником чата
                self.__user_permissions = {**default, **extractor.get('chat_settings.user_permissions.id{}'.format(user_id), {})}
                for k in list(self.__user_permissions.keys()):
                    if not (k in UserPermission.default_states):
                        self.__user_permissions.pop(k)
                        self.__commit_data['$unset']['chat_settings.user_permissions.id{}.{}'.format(user_id, k)] = 0
        else:
            raise UserPermission.InvalidArgumentException("Invalid 'user_id' parameter")

    def getAll(self):
        return self.__user_permissions

    def set(self, _id: str, state: bool):
        if _id in self.__user_permissions:
            if self.__owner_id == self.__user_id:
                raise UserPermission.OwnerPermissionException("User 'id{}' is owner".format(self.__user_id))
            else:
                self.__user_permissions[_id] = state
                self.__commit_data['$set']['chat_settings.user_permissions.id{}.{}'.format(self.__user_id, id)] = state
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def get(self, _id: str):
        if _id in self.__user_permissions:
            return self.__user_permissions[_id]
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def commit(self):
        if len(self.__commit_data['$set']) == 0:
            self.__commit_data.pop('$set')
        if len(self.__commit_data['$unset']) == 0:
            self.__commit_data.pop('$unset')
        
        if len(self.__commit_data) > 0:
            result = self.__db_collection.update_one(self.__db_query, self.__commit_data)
            if result.modified_count > 0:
                return True
            else:
                return False
        else:
            return False
