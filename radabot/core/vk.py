# Module Level 1
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
			self.vars = list(args)
		
		def __call__(self) -> list:
			return self.vars

	def __init__(self):
		self.tmpvar_name = generate_random_string(3, uppercase=False, numbers=False)
		self.tmpvar_list = []
		self.var_code = ''

	def __call__(self):
		tmpvar_code = ''
		if(len(self.tmpvar_list) > 0):
			tmpvar_code = 'var {}={};'.format(self.tmpvar_name, json.dumps(self.tmpvar_list, ensure_ascii=False, separators=(',', ':')))
		return tmpvar_code + self.var_code

	def var(self, name: str, value):
		if(isinstance(value, bool) or isinstance(value, int) or isinstance(value, float)):
			self.var_code += '{}={};'.format(name, str(value))
		elif(isinstance(value, str)):
			tmpvar_index = len(self.tmpvar_list)
			self.tmpvar_list.append(value)
			self.var_code += '{}={}[{}]'.format(name, self.tmpvar_name, tmpvar_index)
		elif(isinstance(value, list) or isinstance(value, dict)):
			self.var_code += '{}={};'.format(name, json.dumps(value, ensure_ascii=False, separators=(',', ':')))
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
						tmpvar_index = len(self.tmpvar_list)
						self.tmpvar_list.append(str(val))
						object_code += '{}{}[{}]'.format(plus, self.tmpvar_name, tmpvar_index)
					if(plus == ''):
						plus = '+'
					last_type = ''
			if(object_code != ''):
				self.var_code += '{}={};'.format(name, object_code)

def longpoll(server: str, key: str, ts: int, wait: int = 25) -> str:
	parametres = {'act': 'a_check', 'key': key, 'ts': ts, 'wait': wait}
	r = requests.post(server, data=parametres)
	return r.text

def text_button(label: str, payload: list, color: str) -> str:
	payload_json = json.dumps(payload, separators=(',', ':'))
	return {"action":{"type": "text", "payload": payload_json, "label": label}, "color": color}

def callback_button(label: str, payload: list, color: str) -> str:
	payload_json = json.dumps(payload, separators=(',', ':'))
	return {"action":{"type": "callback", "payload": payload_json, "label": label}, "color": color}

def keyboard(one_time: bool, buttons: list = {}) -> str:
	keyboard = {"one_time": one_time, "buttons": buttons}
	return json.dumps(keyboard, ensure_ascii=False, separators=(',', ':'))

def keyboard_inline(buttons: list = []) -> str:
	keyboard = {"inline": True, "buttons": buttons}
	return json.dumps(keyboard, ensure_ascii=False, separators=(',', ':'))