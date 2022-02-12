# Module Level 0
import string, random, json, os, time, shlex
from datetime import datetime

# Переменная системных путей
class SYSTEM_PATHS:
    # Директории
    DATA_DIR = 'data/'
    LOG_DIR = 'log/'
    TMP_DIR = 'tmp/'
    EXEC_LOG_DIR =  LOG_DIR + 'exec/'

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

class ArgumentParser:
    def __init__(self, line: str):
        self.args = shlex.split(line)

    def count(self):
        return len(self.args)

    def str(self, index: int, default: str = None) -> str:
        try:
            return str(self.args[index])
        except IndexError:
            return default

    def int(self, index: int, default: int = None) -> int:
        try:
            return int(self.args[index])
        except IndexError:
            return default

    def float(self, index: int, default: float = None) -> float:
        try:
            return float(self.args[index])
        except IndexError:
            return default

class PayloadParser:
    def __init__(self, payload: list):
        self.payload = payload

    def count(self):
        return len(self.payload)

    def str(self, index: int, default: str = None) -> str:
        try:
            return str(self.payload[index])
        except IndexError:
            return default

    def int(self, index: int, default: int = None) -> int:
        try:
            return int(self.payload[index])
        except IndexError:
            return default

    def float(self, index: int, default: float = None) -> float:
        try:
            return float(self.payload[index])
        except IndexError:
            return default

class PageBuilder:
    # Исключение
    class PageNumberException(Exception):
        def __init__(self, message: str):
            self.message = message

    def __init__(self, data: list, page_size: int):
        self.data = data
        self.size = page_size

        self.max_number = len(self.data) // self.size
        if((len(self.data) % self.size) != 0):
            self.max_number += 1

    def __call__(self, number: int):
        page = []

        min_index = self.size * number - self.size
        max_index = self.size * number
        if(self.size * number >= len(self.data)):
            max_index = len(self.data)

        if(number <= self.max_number and number > 0):
            for i in range(min_index, max_index):
                page.append(self.data[i])
        else:
            raise PageBuilder.PageNumberException("Page number out of range [1..{}]".format(self.max_number))

        return page

# Класс для работы с Config файлом
class Config:
    data = {}

    @staticmethod
    def readFile():
        f = open(SYSTEM_PATHS.CONFIG_FILE, encoding='utf-8')
        Config.data = json.loads(f.read())
        f.close()

    @staticmethod
    def get(name):
        return Config.data.get(name, None)

class ManagerData:
    data = {}

    @staticmethod
    def readFile():
        f = open(SYSTEM_PATHS.MANAGER_DATA_FILE, encoding='utf-8')
        ManagerData.data = json.loads(f.read())
        f.close()

    @staticmethod
    def getUserPermissions():
        return ManagerData.data.get('user_permissions', {})

# Функция конвертирования числа в эмодзи
def int2emoji(number: int):
        numbers = []
        while(number > 0):
            numbers.append(number % 10)
            number = number // 10
        numbers.reverse()

        emoji = ['0&#8419;', '1&#8419;', '2&#8419;', '3&#8419;', '4&#8419;', '5&#8419;', '6&#8419;', '7&#8419;', '8&#8419;', '9&#8419;']
        emoji_str = ""
        for i in numbers:
            emoji_str += emoji[i]

        return emoji_str

# Генерация случайной строки
def generate_random_string(length, uppercase = True, lowercase = True, numbers = True):
    letters = ''
    if uppercase and lowercase:
        letters = string.ascii_letters
    elif uppercase:
        letters = string.ascii_uppercase
    elif lowercase:
        letters = string.ascii_lowercase
    if numbers:
        letters += '0123456789'

    return ''.join(random.choice(letters) for i in range(length))

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