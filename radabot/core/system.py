# Module Level 0
import subprocess
import string, random, json, os, time, shlex
from datetime import datetime
from bunch import Bunch


# Переменная системных путей
class SYSTEM_PATHS:
    # Директории
    DATA_DIR = 'data/'
    LOG_DIR = 'log/'
    TMP_DIR = 'tmp/'
    EXEC_LOG_DIR = LOG_DIR + 'exec/'

    # Файлы
    CONFIG_FILE = DATA_DIR + 'config.json'
    MANAGER_DATA_FILE = DATA_DIR + 'manager.json'
    LONGPOLL_LOG_FILE = LOG_DIR + 'longpoll.log'
    ERROR_LOG_FILE = LOG_DIR + 'error.log'


class ValueExtractor:
    class InvalidArgumentException(Exception):
        def __init__(self, msg: str):
            self.msg = msg

    def __init__(self, data):
        if isinstance(data, dict) or isinstance(data, list):
            self.__data = data
        else:
            raise ValueExtractor.InvalidArgumentException(r"argument 'data' must be dict or list")

    def get(self, path, default=None):
        if isinstance(path, str):
            path_list = path.split('.')
        elif isinstance(path, list):
            path_list = path
        else:
            raise ValueExtractor.InvalidArgumentException(r"argument 'path' must be list or str")

        value = self.__data
        for key in path_list:
            if isinstance(value, dict):
                value = value.get(key, None)
                if value is None:
                    return default
            elif isinstance(value, list):
                try:
                    value = value[int(key)]
                except (IndexError, ValueError):
                    return default
            else:
                return default
        return value


class ArgumentParser:
    def __init__(self, line: str):
        self.__args = shlex.split(line)

    def count(self):
        return len(self.__args)

    def str(self, index: int, default: str = None) -> str:
        try:
            return str(self.__args[index])
        except IndexError:
            return default

    def int(self, index: int, default: int = None) -> int:
        try:
            return int(self.__args[index])
        except IndexError:
            return default

    def float(self, index: int, default: float = None) -> float:
        try:
            return float(self.__args[index])
        except IndexError:
            return default


class PayloadParser:
    def __init__(self, payload: list):
        self.__payload = payload

    def count(self):
        return len(self.__payload)

    def str(self, index: int, default: str = None) -> str:
        try:
            return str(self.__payload[index])
        except IndexError:
            return default

    def int(self, index: int, default: int = None) -> int:
        try:
            return int(self.__payload[index])
        except IndexError:
            return default

    def float(self, index: int, default: float = None) -> float:
        try:
            return float(self.__payload[index])
        except IndexError:
            return default


class PageBuilder:
    # Исключение
    class PageNumberException(Exception):
        def __init__(self, message: str):
            self.message = message

    def __init__(self, data: list, page_size: int):
        self.__data = data
        self.__size = page_size

        self.__max_number = len(self.__data) // self.__size
        if (len(self.__data) % self.__size) != 0:
            self.__max_number += 1

    def __call__(self, number: int):
        page = []

        min_index = self.__size * number - self.__size
        max_index = self.__size * number
        if self.__size * number >= len(self.__data):
            max_index = len(self.__data)

        if self.__max_number >= number > 0:
            for i in range(min_index, max_index):
                page.append(self.__data[i])
        else:
            raise PageBuilder.PageNumberException("Page number out of range [1..{}]".format(self.__max_number))

        return page

    @property
    def max_number(self):
        return self.__max_number


# Класс для работы с Config файлом
class Config:
    __data = {}

    @staticmethod
    def readFile():
        f = open(SYSTEM_PATHS.CONFIG_FILE, encoding='utf-8')
        Config.__data = json.loads(f.read())
        f.close()

    @staticmethod
    def get(name):
        return Config.__data.get(name, None)


class ManagerData:
    __data = {}

    @staticmethod
    def readFile():
        f = open(SYSTEM_PATHS.MANAGER_DATA_FILE, encoding='utf-8')
        ManagerData.__data = json.loads(f.read())
        f.close()

    @staticmethod
    def getUserPermissions():
        return ManagerData.__data.get('user_permissions', {})


class PHPCommandIntegration:
    message_commands = []
    callback_button_commands = []
    text_button_commands = []

    @staticmethod
    def init():
        subprocess.Popen(["/usr/bin/php7.0", "radabot-php-core.php", "int", ""]).communicate()
        path_to_php_integration = "{}php_integration.txt".format(SYSTEM_PATHS.TMP_DIR)
        if os.path.exists(path_to_php_integration):
            f = open(path_to_php_integration, encoding='utf-8')
            php_integration = f.read()
            f.close()
            os.remove(path_to_php_integration)
            splited_php_integration = php_integration.split("\n")
            PHPCommandIntegration.message_commands = splited_php_integration[0].split(';')
            PHPCommandIntegration.callback_button_commands = splited_php_integration[1].split(';')
            PHPCommandIntegration.text_button_commands = splited_php_integration[2].split(';')


# Функция конвертирования числа в эмодзи
def int2emoji(number: int):
    numbers = []
    while number > 0:
        numbers.append(number % 10)
        number = number // 10
    numbers.reverse()

    emoji = ['0&#8419;', '1&#8419;', '2&#8419;', '3&#8419;', '4&#8419;', '5&#8419;', '6&#8419;', '7&#8419;', '8&#8419;',
             '9&#8419;']
    emoji_str = ""
    for i in numbers:
        emoji_str += emoji[i]

    return emoji_str


# Функция конвертаци элементов списка в объект Bunch (если элемент - словарь)
def bunchingList(_list: list) -> list:
    nl = []
    for i in _list:
        if isinstance(i, dict):
            nl.append(dict2bunch(i))
        elif isinstance(i, list):
            nl.append(bunchingList(i))
        else:
            nl.append(i)
    return nl


# Функция конвертации словаря в объект Bunch
def dict2bunch(_dict: dict) -> Bunch:
    b = {}
    for k, v in _dict.items():
        if isinstance(v, dict):
            b[k] = dict2bunch(v)
        elif isinstance(v, list):
            b[k] = bunchingList(v)
        else:
            b[k] = v
    return Bunch(b)


# Генерация случайной строки
def generate_random_string(length, uppercase=True, lowercase=True, numbers=True):
    letters = ''
    if uppercase and lowercase:
        letters = string.ascii_letters
    elif uppercase:
        letters = string.ascii_uppercase
    elif lowercase:
        letters = string.ascii_lowercase
    if numbers:
        letters += '0123456789'

    return ''.join(random.choice(letters) for _ in range(length))


# Метод журналирования
def write_log(filename: str, text: str):
    f = open(filename, "a", encoding='utf-8')
    tm = time.time() + 10800
    dt = datetime.utcfromtimestamp(tm).strftime("%d-%b-%Y %X Russia/Moscow")
    f.write("[{}] {}\n".format(dt, text))
    f.close()


# Функция предстарта
def prestart():
    if not os.path.exists(SYSTEM_PATHS.TMP_DIR):
        os.mkdir(SYSTEM_PATHS.TMP_DIR)

    if not os.path.exists(SYSTEM_PATHS.LOG_DIR):
        os.mkdir(SYSTEM_PATHS.LOG_DIR)

    if not os.path.exists(SYSTEM_PATHS.EXEC_LOG_DIR):
        os.mkdir(SYSTEM_PATHS.EXEC_LOG_DIR)
