from radabot.core.io import ChatEventManager

from .manager import initcmd as initcmd_manager
from .debug import initcmd as initcmd_debug
from .basic import initcmd as initcmd_basic, initcmd_php


def handle_event(vk_api, event):
	manager = ChatEventManager(vk_api, event)

	initcmd_debug(manager)
	initcmd_basic(manager)
	initcmd_manager(manager)
	initcmd_php(manager)

	manager.handle()