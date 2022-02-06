# Module Level 1
from email.policy import default
from pymongo.database import Database
from radabot.core.system import ManagerData, ValueExtractor

class UserPermission:
    DEFAULT_STATES = {}

    # Исключние неправильного аргумента
    class InvalidArgumentException(Exception):
        def __init__(self, message: str):
            self.message = message

    @staticmethod
    def initConstants():
        default = {}
        for k, v in ManagerData.getUserPermissions().items():
            default[k] = v['default']
        UserPermission.DEFAULT_STATES = default

    def __init__(self, db: Database, user_id: int):
        self.db_collection = db['chats']
        self.user_id = user_id

        if(user_id > 0):
            query = self.db.find_one(self.db_query, projection={'_id': 0, 'owner_id': 1, 'chat_settings.user_permissions.id{}'.format(user_id): 1})
            extractor = ValueExtractor(query)

            self.commit = {'$set': {}, '$unset': {}}
            
            self.owner_id = extractor.get('owner_id', 0)
            self.user_permissions = {**default, **extractor.get('chat_settings.user_permissions.id{}'.format(user_id), {})}

            for k in list(self.user_permissions.keys()):
                if(not (k in UserPermission.DEFAULT_STATES)):
                    self.user_permissions.pop(k)
                    self.commit['$unset']['chat_settings.user_permissions.id{}.{}'.format(user_id, k)] = 0
        else:
            raise UserPermission.InvalidArgumentException("Invalid 'user_id' parameter")

    def getAll(self):
        return self.user_permissions

    def set(self, id: str, state: bool):
        pass
