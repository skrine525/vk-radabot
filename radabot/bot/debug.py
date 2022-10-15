from radabot.core.io import ChatEventManager
from ..core.system import Config
from radabot.core.vk import KeyboardBuilder


def initcmd(manager: ChatEventManager):
    # Если пользователь не является суперпользователем, то не инициализируем отладочные команды
    if (manager.event["type"] == 'message_new' and manager.event["object"]["message"]["from_id"] != Config.get("SUPERUSER_ID")) or (manager.event["type"] == 'message_event' and manager.event["object"]["user_id"] != Config.get("SUPERUSER_ID")):
        return

    manager.add_message_command('!error', ErrorCommand.message_command)


class ErrorCommand:
    @staticmethod
    def message_command(callback_object: dict):
        raise Exception()