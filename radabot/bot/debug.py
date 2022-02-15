from radabot.core.io import ChatEventManager
from radabot.core.vk import KeyboardBuilder


def initcmd(manager: ChatEventManager):
    manager.addMessageCommand('!error', ErrorCommand.message_command)
    manager.addMessageCommand('!test-keyboard', TestKeyboardCommand.message_command)

class ErrorCommand:
    @staticmethod
    def message_command(callin: ChatEventManager.CallbackInputObject):
        raise Exception()

class TestKeyboardCommand:
    @staticmethod
    def message_command(callin: ChatEventManager.CallbackInputObject):
        event = callin.event
        args = callin.args
        db = callin.db
        output = callin.output
        
        count = args.int(1, 1)

        keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
        for i in range(count):
            keyboard.callback_button('Кнопка #{}'.format(i), [], KeyboardBuilder.PRIMARY_COLOR)
        keyboard = keyboard.build()
        output.messages_send(peer_id=event.peer_id, message='Тестирование новой системы клавиатур', keyboard=keyboard)
