# Module Level 1
from fcntl import LOCK_NB
import requests, json
from .system import generate_random_string

class VK_API:
	def __init__(self, access_token: str):
		self.access_token = access_token

	def call(self, method: str, parametres: dict, api_version: float = 5.131) -> str:
		headers = {'Content-type': 'application/x-www-form-urlencoded'}
		parametres["access_token"] = self.access_token
		parametres["v"] = api_version
		r = requests.post("https://api.vk.com/method/{}".format(method), data=parametres, headers=headers)
		return r.text

	def execute(self, code: str) -> str:
		return self.call('execute', {'code': code})

class VKVariable:
	class Multi:
		def __init__(self, *args):
			self.__vars = list(args)
		
		def __call__(self) -> list:
			return self.__vars

	def __init__(self):
		self.__tmpvar_name = generate_random_string(3, uppercase=False, numbers=False)
		self.__tmpvar_list = []
		self.__var_code = ''

	def __call__(self):
		tmpvar_code = ''
		if(len(self.__tmpvar_list) > 0):
			tmpvar_code = 'var {}={};'.format(self.__tmpvar_name, json.dumps(self.__tmpvar_list, ensure_ascii=False, separators=(',', ':')))
		return tmpvar_code + self.__var_code

	def var(self, name: str, value):
		if(isinstance(value, bool) or isinstance(value, int) or isinstance(value, float)):
			self.__var_code += '{}={};'.format(name, str(value))
		elif(isinstance(value, str)):
			tmpvar_index = len(self.__tmpvar_list)
			self.__tmpvar_list.append(value)
			self.__var_code += '{}={}[{}]'.format(name, self.__tmpvar_name, tmpvar_index)
		elif(isinstance(value, list) or isinstance(value, dict)):
			self.__var_code += '{}={};'.format(name, json.dumps(value, ensure_ascii=False, separators=(',', ':')))
		elif(isinstance(value, VKVariable.Multi)):
			last_type = ''
			plus = ''
			object_code = ''
			for val in value():
				if(last_type == ''):
					if(isinstance(val, str)):
						last_type = val 
				else:
					if((last_type == 'int') or (last_type == 'bool') or (last_type == 'float') or (last_type == 'var')):
						object_code += plus+str(val)
					elif(last_type == 'str'):
						tmpvar_index = len(self.__tmpvar_list)
						self.__tmpvar_list.append(str(val))
						object_code += '{}{}[{}]'.format(plus, self.__tmpvar_name, tmpvar_index)
					if(plus == ''):
						plus = '+'
					last_type = ''
			if(object_code != ''):
				self.__var_code += '{}={};'.format(name, object_code)

class KeyboardBuilder:
	#############################
    #############################
    # Константы

	# Типы клавиатур
	DEFAULT_TYPE = 0
	INLINE_TYPE = 1

	# Цвета кнопок
	POSITIVE_COLOR = 'positive'
	NEGATIVE_COLOR = 'negative'
	PRIMARY_COLOR = 'primary'
	SECONDARY_COLOR = 'secondary'

	#############################
    #############################
	# Исключения

	# Неизвестный тип клавиатуры
	class UnknownTypeException(Exception):
		def __init__(self, message: str):
			self.message = message

	# Привешение ограничения количества кнопок\высоты клавиатуры
	class KeyboardLimitException(Exception):
		def __init__(self, message: str):
			self.message = message

	#############################
    #############################
    # Методы

	# Конструктор
	def __init__(self, keyboard_type: int):
		if(keyboard_type == KeyboardBuilder.DEFAULT_TYPE):
			self.__keyboard_type = keyboard_type
			self.width_max = 5
			self.height_max = 10
			self.buttons_max = 40

			self.__buttons = []
			self.__current_height = 0
			self.__buttons_count = 0
		elif(keyboard_type == KeyboardBuilder.INLINE_TYPE):
			self.__keyboard_type = keyboard_type
			self.width_max = 5
			self.height_max = 6
			self.buttons_max = 10

			self.__buttons = []
			self.__current_height = 0
			self.__buttons_count = 0
		else:
			raise KeyboardBuilder.UnknownTypeException('Unknown keyboard type')

	def new_line(self):
		try:
			if(len(self.__buttons[self.__current_height]) > 0):
				new_heigth = self.__current_height + 1
				if(new_heigth < self.height_max):
					self.__buttons.append([])
					self.__current_height = new_heigth
					return True
				else:
					raise KeyboardBuilder.KeyboardLimitException('Keyboard height limit exceeded')
			else:
				return False
		except IndexError:
			self.__buttons.append([])
			return True

	def callback_button(self, label: str, payload: list, color: str) -> bool:
		if(self.__buttons_count < self.buttons_max):
			try:
				if(len(self.__buttons[self.__current_height]) < self.width_max):
					payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
					self.__buttons[self.__current_height].append({"action":{"type": "callback", "payload": payload_json, "label": label}, "color": color})
					self.__buttons_count += 1
					return True
				else:
					if(self.new_line()):
						payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
						self.__buttons[self.__current_height].append({"action":{"type": "callback", "payload": payload_json, "label": label}, "color": color})
						self.__buttons_count += 1
						return True
					else:
						return False
			except IndexError:
				if(self.new_line()):
					payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
					self.__buttons[self.__current_height].append({"action":{"type": "callback", "payload": payload_json, "label": label}, "color": color})
					self.__buttons_count += 1
					return True
				else:
					return False
		else:
			raise KeyboardBuilder.KeyboardLimitException('Button limit exceeded')

	def text_button(self, label: str, payload: list, color: str) -> bool:
		if(self.__buttons_count < self.buttons_max):
			try:
				if(len(self.__buttons[self.__current_height]) < self.width_max):
					payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
					self.__buttons[self.__current_height].append({"action":{"type": "text", "payload": payload_json, "label": label}, "color": color})
					self.__buttons_count += 1
					return True
				else:
					if(self.new_line()):
						payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
						self.__buttons[self.__current_height].append({"action":{"type": "text", "payload": payload_json, "label": label}, "color": color})
						self.__buttons_count += 1
						return True
					else:
						return False
			except IndexError:
				if(self.new_line()):
					payload_json = json.dumps(payload, ensure_ascii=False, separators=(',', ':'))
					self.__buttons[self.__current_height].append({"action":{"type": "text", "payload": payload_json, "label": label}, "color": color})
					self.__buttons_count += 1
					return True
				else:
					return False
		else:
			raise KeyboardBuilder.KeyboardLimitException('Button limit exceeded')

	def build(self, **kwargs) -> str:
		if(self.__keyboard_type == KeyboardBuilder.DEFAULT_TYPE):
			one_time = bool(kwargs.get('one_time', True))
			keyboard = {"one_time": one_time, "buttons": self.__buttons}
			return json.dumps(keyboard, ensure_ascii=False, separators=(',', ':'))
		elif(self.__keyboard_type == KeyboardBuilder.INLINE_TYPE):
			keyboard = {"inline": True, "buttons": self.__buttons}
			return json.dumps(keyboard, ensure_ascii=False, separators=(',', ':'))

def longpoll(server: str, key: str, ts: int, wait: int = 25) -> str:
	parametres = {'act': 'a_check', 'key': key, 'ts': ts, 'wait': wait}
	r = requests.post(server, data=parametres)
	return r.text