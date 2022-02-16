# Module Level 1
from email.policy import default
from radabot.core.system import ManagerData, ValueExtractor, ChatDatabase


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
    def init_default_states():
        d = {}
        for k, v in ManagerData.get_user_permissions().items():
            d[k] = v['default']
        UserPermission.default_states = d

    def __init__(self, db: ChatDatabase, user_id: int):
        self.__db = db
        self.__user_id = user_id

        if user_id > 0:
            query = self.__db.find(projection={'_id': 0, 'chat_settings.user_permissions.id{}'.format(user_id): 1})
            extractor = ValueExtractor(query)

            self.__commit_data = {'$set': {}, '$unset': {}}

            if user_id == self.__db.owner_id:
                # Если пользователь является владельцем чата
                self.__user_permissions = UserPermission.default_states.copy()
                for k in list(self.__user_permissions.keys()):
                    self.__user_permissions[k] = True
            else:
                # Если пользователь является участником чата
                self.__user_permissions = {**UserPermission.default_states, **extractor.get('chat_settings.user_permissions.id{}'.format(user_id), {})}
                for k in list(self.__user_permissions.keys()):
                    if not (k in UserPermission.default_states):
                        self.__user_permissions.pop(k)
                        self.__commit_data['$unset']['chat_settings.user_permissions.id{}.{}'.format(user_id, k)] = 0
        else:
            raise UserPermission.InvalidArgumentException("Invalid 'user_id' parameter")

    def get_all(self):
        return self.__user_permissions

    def set(self, name: str, state: bool):
        if name in self.__user_permissions:
            if self.__db.owner_id == self.__user_id:
                raise UserPermission.OwnerPermissionException("User 'id{}' is owner".format(self.__user_id))
            else:
                self.__user_permissions[name] = state
                self.__commit_data['$set']['chat_settings.user_permissions.id{}.{}'.format(self.__user_id, name)] = state
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def get(self, name: str):
        if name in self.__user_permissions:
            return self.__user_permissions[name]
        else:
            raise UserPermission.UnknownPermissionException("Unknown '{}' permission".format(id))

    def commit(self):
        if self.__db.owner_id == self.__user_id:
            raise UserPermission.OwnerPermissionException("User 'id{}' is owner".format(self.__user_id))
        else:
            if len(self.__commit_data['$set']) == 0:
                self.__commit_data.pop('$set')
            if len(self.__commit_data['$unset']) == 0:
                self.__commit_data.pop('$unset')

            if len(self.__commit_data) > 0:
                result = self.__db.update(self.__commit_data)
                if result.modified_count > 0:
                    return True
                else:
                    return False
            else:
                return False
