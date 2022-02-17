from radabot.core.io import ChatEventManager, ChatOutput
from radabot.core.manager import UserPermission
from radabot.core.system import ManagerData, SelectedUserParser, ArgumentParser, CommandHelpBuilder
from radabot.core.vk import VKVariable
from radabot.core.bot import DEFAULT_MESSAGES


def initcmd(manager: ChatEventManager):
    manager.add_message_command('!метки', PermissionCommand.message_command)


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
        if subcommand == 'показ':
            member_parser = SelectedUserParser()
            member_parser.set_fwd_messages(event.fwd_messages)
            member_parser.set_argument_parser(args, 2)
            member_id = member_parser.member_id()

            if member_id == 0:
                permits_text = "Ваши метки:"
                no_permits_text = "❗У вас нет меток."
                member_id = event.from_id
            else:
                permits_text = "Метки @id{} (пользователя):".format(member_id)
                no_permits_text = "❗У @id{} (пользователя) нет меток.".format(member_id)

            user_permission = UserPermission(db, member_id)
            permission_list = user_permission.get_all()
            user_permissions_data = ManagerData.get_user_permissions_data()
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
        elif subcommand == 'упр':
            member_parser = SelectedUserParser()
            member_parser.set_fwd_messages(event.fwd_messages)
            member_parser.set_argument_parser(args, 2)
            member_id = member_parser.member_id()

            from_id_permissions = UserPermission(db, event.from_id)
            if from_id_permissions.get('set_permits'):
                if member_id > 0:
                    permissions_data = ManagerData.get_user_permissions_data()

                    # Просчитываем метки, которыми может управлять пользователь
                    can_manage_list = []
                    for k, v in from_id_permissions.get_all().items():
                        if not permissions_data[k]['is_special'] and v and (event.from_id == db.owner_id or k != 'set_permits'):
                            can_manage_list.append(k)
                    
                    if args.count > 2:
                        member_permission = UserPermission(db, member_id)

                        if not member_permission.get('set_permits') or (event.from_id == db.owner_id and member_id != db.owner_id):
                            message_text = 'Метки @id{} (пользователя):'.format(member_id)
                            for index in range(2, min(args.count, 12)):
                                permission_name = args.get_str(index, '').lower()
                                try:
                                    permission_state = member_permission.get(permission_name)
                                    permission_label = permissions_data[permission_name]['label']
                                    member_permission.set(permission_name, not permission_state)

                                    if permission_name in can_manage_list:
                                        if permission_state:
                                            message_text += '\n⛔ {}'.format(permission_label)
                                        else:
                                            message_text += '\n✅ {}'.format(permission_label)
                                    else:
                                        message_text += '\n🚫 {}'.format(permission_label)
                                except UserPermission.UnknownPermissionException:
                                    message_text += '\n❓ {}'.format(permission_name)

                            message_text += '\n\nОбозначения:\n✅ - Метка выдана\n⛔ - Метка отозвана\n🚫 - Запрещено управлять\n❓ - Неизвестная метка'

                            member_permission.commit()
                            message = VKVariable.Multi('var', 'appeal', 'str', message_text)
                            uos.messages_send(message=message)
                        else:
                            message_text = '⛔Невозможно управлять метками @id{member_id} (пользователя).'.format(member_id=member_id)
                            message = VKVariable.Multi('var', 'appeal', 'str', message_text)
                            uos.messages_send(message=message)
                    else:
                        permits_text = "Пусто"
                        if len(can_manage_list) > 0:
                            permits_text = ', '.join(can_manage_list)
                        message = VKVariable.Multi('var', 'appeal', 'str', '⛔Укажите метки (не больше 10 штук).\n\nМетки, которыми вы можете управлять: {}.'.format(permits_text))
                        uos.messages_send(message=message)
                else:
                    PermissionCommand.__print_error_select_user(uos, args)
            else:
                message = VKVariable.Multi('var', 'appeal', 'str', DEFAULT_MESSAGES.MESSAGE_YOU_HAVE_NO_RIGHTS)
                uos.messages_send(message=message)
        elif subcommand == 'инфа':
            permissions_data = ManagerData.get_user_permissions_data()
            permission_name = args.get_str(2, '').lower()

            if permission_name == '':
                message_text = 'Список меток:'
                for i in permissions_data:
                    message_text += '\n• ' + i
                message_text += "\n\nПодробная информация:\n➡️ !метки инфа [метка]"
                message = VKVariable.Multi('var', 'appeal', 'str', message_text)
                uos.messages_send(message=message)
            else:
                try:
                    permission_data = permissions_data[permission_name]
                    message = VKVariable.Multi('var', 'appeal', 'str', permission_data['label'])
                    uos.messages_send(message=message)
                except KeyError:
                    permits_text = '\n\nСписок меток:'
                    for i in permissions_data:
                        permits_text += '\n• ' + i
                    hint = '\n\nПодробная информация:\n➡️ !метки инфа [метка]'
                    message = VKVariable.Multi('var', 'appeal', 'str', "⛔Метка '{}' не существует.{}{}".format(permission_name, permits_text, hint))
                    uos.messages_send(message=message)
        else:
            PermissionCommand.__print_error_unknown_subcommand(uos, args)

    @staticmethod
    def __print_error_select_user(uos: ChatOutput.UOS, args: ArgumentParser):
        first_permission = list(ManagerData.get_user_permissions_data())[0]
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
        help_builder.command('{} показ', args.get_str(0).lower())
        help_builder.command('{} инфа', args.get_str(0).lower())
        help_builder.command('{} упр', args.get_str(0).lower())

        message = VKVariable.Multi('var', 'appeal', 'str', help_builder.build())
        uos.messages_send(message=message)
