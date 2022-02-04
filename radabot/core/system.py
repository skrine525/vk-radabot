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
    LONGPOLL_LOG_FILE = LOG_DIR + 'longpoll.log'
    ERROR_LOG_FILE = LOG_DIR + 'error.log'

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

# Метод получения config'а
config_data = None
def get_config(name):
    if config_data == None:
        f = open(SYSTEM_PATHS.CONFIG_FILE)
        globals()["config_data"] = json.loads(f.read())
        f.close()
    return config_data.get(name, None)

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

def parse_args(cmdline: str) -> list:
    return shlex.split(cmdline)