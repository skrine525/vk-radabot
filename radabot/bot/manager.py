from radabot.core.io import ChatEventManager, ChatOutput
from radabot.core.manager import UserPermission
from radabot.core.system import ManagerData, SelectedUserParser, ArgumentParser, CommandHelpBuilder
from radabot.core.vk import VKVariable


def initcmd(manager: ChatEventManager):
    manager.addMessageCommand('!права', PermissionCommand.message_command)


# Команда !права
class PermissionCommand:
    @staticmethod
    def message_command(callin: ChatEventManager.CallbackInputObject):
        event = callin.event
        args = callin.args
        db = callin.db
        output = callin.output

        uos = output.uos(db)
        uos.set_appeal(event.from_id)

        subcommand = args.get_str(1, '').lower()
        if subcommand == 'показать':
            member_parser = SelectedUserParser()
            member_parser.set_fwd_messages(event.fwd_messages)
            member_parser.set_argument_parser(args, 2)
            member_id = member_parser.member_id()

            if member_id == 0:
                permits_text = "Ваши права:"
                no_permits_text = "❗У вас нет прав."
                member_id = event.from_id
            else:
                permits_text = "Права @id{} (пользователя):".format(member_id)
                no_permits_text = "❗У @id{} (пользователя) нет прав.".format(member_id)

            user_permission = UserPermission(db, member_id)
            permission_list = user_permission.get_all()
            user_permissions_data = ManagerData.get_user_permissions()
            true_permission_count = 0
            for k, v in permission_list.items():
                if v:
                    label = user_permissions_data[k]['label']
                    permits_text += "\n• {}".format(label)
                    true_permission_count += 1

            if true_permission_count > 0:
                message = VKVariable.Multi('var', 'appeal', 'str', permits_text)
                uos.messages_send(message=message)
            else:
                message = VKVariable.Multi('var', 'appeal', 'str', no_permits_text)
                uos.messages_send(message=message)
        elif subcommand == 'установить':
            member_parser = SelectedUserParser()
            member_parser.set_fwd_messages(event.fwd_messages)
            member_parser.set_argument_parser(args, 2)
            member_id = member_parser.member_id()

            if member_id > 0:
                if args.count > 2:
                    try:
                        user_permission = UserPermission(db, member_id)
                        user_permissions_data = ManagerData.get_user_permissions()

                        message_text = '✅Результат:'
                        for index in range(2, args.count):
                            permission_name = args.get_str(index, '').lower()
                            try:
                                permission_state = user_permission.get(permission_name)
                                permission_label = user_permissions_data[permission_name]['label']
                                user_permission.set(permission_name, not permission_state)

                                if permission_state:
                                    message_text += '\n• {} - Отключено'.format(permission_label)
                                else:
                                    message_text += '\n• {} - Включено'.format(permission_label)
                            except UserPermission.UnknownPermissionException:
                                message_text += '\n• {} - Ошибка'.format(permission_name)

                        user_permission.commit()
                        message = VKVariable.Multi('var', 'appeal', 'str', message_text)
                        uos.messages_send(message=message)
                    except UserPermission.OwnerPermissionException:
                        message = VKVariable.Multi('var', 'appeal', 'str', '⛔Запрещено изменять права владельца.')
                        uos.messages_send(message=message)
                else:
                    message = VKVariable.Multi('var', 'appeal', 'str', '⛔Укажите права.')
                    uos.messages_send(message=message)
            else:
                PermissionCommand.__print_error_select_user(uos, args)
        elif subcommand == 'инфа':
            pass
        else:
            PermissionCommand.__print_error_unknown_subcommand(uos, args)

    @staticmethod
    def __print_error_select_user(uos: ChatOutput.UOS, args: ArgumentParser):
        first_permission = list(ManagerData.get_user_permissions().keys())[0]
        help_builder = CommandHelpBuilder('⛔Укажите пользователя.')
        help_builder.command('{} {} [id] [название]', args.get_str(0).lower(), args.get_str(1).lower())
        help_builder.command('{} {} [пересл. сообщение] [название]', args.get_str(0).lower(), args.get_str(1).lower())
        help_builder.command('{} {} [упоминание] [название]', args.get_str(0).lower(), args.get_str(1).lower())
        help_builder.example('{} {} @durov {}', args.get_str(0).lower(), args.get_str(1).lower(), first_permission)

        message = VKVariable.Multi('var', 'appeal', 'str', help_builder.build())
        uos.messages_send(message=message)

    @staticmethod
    def __print_error_unknown_subcommand(uos: ChatOutput.UOS, args: ArgumentParser):
        help_builder = CommandHelpBuilder('⛔Неверная субкоманда.')
        help_builder.command('{} показать', args.get_str(0).lower())
        help_builder.command('{} инфа', args.get_str(0).lower())
        help_builder.command('{} установить', args.get_str(0).lower())

        message = VKVariable.Multi('var', 'appeal', 'str', help_builder.build())
        uos.messages_send(message=message)
