# Module Level 1
from radabot.core.system import ManagerData, ValueExtractor, ChatDatabase


class ChatModes:
    __default_states = {}

    # Исключние неизвестного режима
    class UnknownModeException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Исключение для несуществующей базы данных чата
    class ChatNotExistsException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Метод инициализации стандартных состояний из файла manager.json
    @staticmethod
    def init_default_states():
        d = {}
        for k, v in ManagerData.get_chat_modes_data().items():
            d[k] = v['default']
        ChatModes.__default_states = d

    def __init__(self, db: ChatDatabase):
        self.__db = db

        query = self.__db.find(projection={'_id': 0, 'chat_settings.chat_modes': 1})

        self.__modes = {**ChatModes.__default_states, **ValueExtractor(query).get('chat_settings.chat_modes', {})}
        self.__commit_data = {'$set': {}, '$unset': {}}

        # Вырезаем из базы данных, если нет данных о режиме в файле
        for k in self.__modes:
            if not (k in ChatModes.__default_states):
                self.__modes.pop(k)
                self.__commit_data['$unset']['chat_settings.chat_modes.{}'.format(k)] = 0

    def get_all(self):
        return self.__modes

    def set(self, name: str, state: bool):
        if name in self.__modes:
            self.__modes[name] = state
            self.__commit_data['$set']['chat_settings.chat_modes.{}'.format(name)] = state
        else:
            raise ChatModes.UnknownModeException("Unknown '{}' permission".format(name))

    def get(self, name: str):
        try:
            return self.__modes[name]
        except KeyError:
            raise UserPermissions.UnknownPermissionException("Unknown '{}' permission".format(name))

    def commit(self):
        if not self.__db.is_exists:
            raise ChatModes.ChatNotExistsException("Chat 'chat{}' does not exists".format(self.__db.chat_id))

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


class UserPermissions:
    # Статическое поле, содержащее стандартные состояния меток
    __default_states = {}

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

    # Исключение для несуществующей базы данных чата
    class ChatNotExistsException(Exception):
        def __init__(self, message: str):
            self.message = message

    # Метод инициализации стандартных состояний из файла manager.json
    @staticmethod
    def init_default_states():
        d = {}
        for k, v in ManagerData.get_user_permissions_data().items():
            d[k] = v['default']
        UserPermissions.__default_states = d

    def __init__(self, db: ChatDatabase, user_id: int):
        self.__db = db
        self.__user_id = user_id

        if not self.__db.is_exists:
            raise UserPermissions.ChatNotExistsException("Chat 'chat{}' does not exists".format(self.__db.chat_id))

        if user_id > 0:
            query = self.__db.find(projection={'_id': 0, 'chat_settings.user_permissions.id{}'.format(user_id): 1})
            extractor = ValueExtractor(query)

            self.__commit_data = {'$set': {}, '$unset': {}}

            if user_id == self.__db.owner_id:
                # Если пользователь является владельцем чата
                self.__user_permissions = UserPermissions.__default_states.copy()
                for k in list(self.__user_permissions):
                    self.__user_permissions[k] = True
            else:
                # Если пользователь является участником чата
                self.__user_permissions = {**UserPermissions.__default_states, **extractor.get('chat_settings.user_permissions.id{}'.format(user_id), {})}
                for k in self.__user_permissions:
                    if not (k in UserPermissions.__default_states):
                        self.__user_permissions.pop(k)
                        self.__commit_data['$unset']['chat_settings.user_permissions.id{}.{}'.format(user_id, k)] = 0
        else:
            raise UserPermissions.InvalidArgumentException("Invalid 'user_id' parameter")

    def get_all(self):
        return self.__user_permissions

    def set(self, name: str, state: bool):
        if name in self.__user_permissions:
            if self.__db.owner_id == self.__user_id:
                raise UserPermissions.OwnerPermissionException("User 'id{}' is owner".format(self.__user_id))
            else:
                self.__user_permissions[name] = state
                self.__commit_data['$set']['chat_settings.user_permissions.id{}.{}'.format(self.__user_id, name)] = state
        else:
            raise UserPermissions.UnknownPermissionException("Unknown '{}' permission".format(name))

    def get(self, name: str):
        try:
            return self.__user_permissions[name]
        except KeyError:
            raise UserPermissions.UnknownPermissionException("Unknown '{}' permission".format(name))

    def commit(self):
        if self.__db.owner_id == self.__user_id:
            raise UserPermissions.OwnerPermissionException("User 'id{}' is owner".format(self.__user_id))
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
