import datetime
import math, time
from radabot.core.bot import DEFAULT_MESSAGES
from radabot.core.io import ChatEventManager, AdvancedOutputSystem
from radabot.core.manager import ChatModes, UserPermissions
from radabot.core.system import ArgumentParser, CommandHelpBuilder, ManagerData, PageBuilder, ValueExtractor, int2emoji
from radabot.core.vk import KeyboardBuilder, VKVariable


def initcmd(manager: ChatEventManager):
    manager.add_message_command('!мемы', CustomMemes.message_command)

    manager.add_callback_button_command('fun_memes', CustomMemes.callback_button_command)


class CustomMemes:
    SHOW_SIZE = 10
    MAX_MEMES_COUNT = 100

    def message_command(callback_object: dict):
        event = callback_object["event"]
        args = callback_object["args"]
        db = callback_object["db"]
        output = callback_object["output"]
        manager = callback_object["manager"]

        aos = AdvancedOutputSystem(output, event, db)

        # Проверка режима allow_memes
        chat_modes = manager.chat_modes
        if not chat_modes.get('allow_memes'):
            mode_label = ChatModes.get_label('allow_memes')
            CustomMemes.__print_error_text(aos, 'Режим {} отключен.'.format(mode_label))
            return

        subcommand = args.get_str(1, '').lower()
        if subcommand == 'показ':
            # Подгружаем все мемы базы данных
            db_result = db.find(projection={'_id': 0, 'fun.memes': 1})
            extractor = ValueExtractor(db_result)
            all_memes = extractor.get('fun.memes', [])

            if(len(all_memes) == 0):
                CustomMemes.__print_error_text(aos, 'В беседе нет мемов.')
                return

            names = list(all_memes)
            builder = PageBuilder(names, CustomMemes.SHOW_SIZE)
            number = args.get_int(2, 1)

            try:
                page = builder(number)
                text = 'Список мемов [{}/{}]:'.format(number, builder.max_number)
                for i in page:
                    text += "\n• " + i

                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                if number > 1:
                    prev_number = number - 1
                    keyboard.callback_button("{} ⬅".format(int2emoji(prev_number)), ['fun_memes', event["object"]["message"]["from_id"], 1, prev_number], KeyboardBuilder.SECONDARY_COLOR)
                if number < builder.max_number:
                    next_number = number + 1
                    keyboard.callback_button("➡ {}".format(int2emoji(next_number)), ['fun_memes', event["object"]["message"]["from_id"], 1, next_number], KeyboardBuilder.SECONDARY_COLOR)
                keyboard.new_line()
                keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["message"]["from_id"]], KeyboardBuilder.NEGATIVE_COLOR)

                aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard.build())
            except PageBuilder.PageNumberException:
                aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔Неверный номер страницы.'))
        elif subcommand == 'добав':
            user_permissions = UserPermissions(db, event["object"]["message"]["from_id"])
            if user_permissions.get('customize_memes'):
                meme_name = args.get_words(2)
                if meme_name != '':
                    # Ограничение длинны названия 15 символов
                    if len(meme_name) > 15:
                        CustomMemes.__print_error_text(aos, 'Название превышает 15 символов.')
                        return

                    # Ограничение на использование символа $
                    if meme_name.count('$') > 0:
                        CustomMemes.__print_error_text(aos, 'Символ \'$\' запрещен в названии мема.')
                        return

                    # Ограничение названий, эквивалентных Message командам
                    for command in manager.message_command_list:
                        if command == meme_name:
                            CustomMemes.__print_error_text(aos, 'Название мема не должно являться командой.')
                            return

                    # Подгружаем все мемы базы данных
                    db_result = db.find(projection={'_id': 0, 'fun.memes': 1})
                    extractor = ValueExtractor(db_result)
                    all_memes = extractor.get('fun.memes', [])

                    # Ограничение на количество мемов в беседе
                    if len(all_memes) >= CustomMemes.MAX_MEMES_COUNT:
                        CustomMemes.__print_error_text(aos, 'Максимальное количество мемов в беседе: 100 штук.')
                        return

                    # Ограничение, если мем с таким названием уже существует
                    if meme_name in all_memes:
                        CustomMemes.__print_error_text(aos, 'Мем с таким названием уже существует.')
                        return

                    # Ограничение, если не прикреплено вложение
                    try:
                        attachment = event["object"]["message"]["attachments"][0]
                    except IndexError:
                        CustomMemes.__print_error_text(aos, 'Для добавление мема необходимо прикрепить вложение.')
                        return

                    content = ''

                    if attachment["type"] == 'photo':
                        if 'access_key' in attachment["photo"]:
                            content = 'photo{}_{}_{}'.format(attachment["photo"]["owner_id"], attachment["photo"]["id"], attachment["photo"]["access_key"])
                        else:
                            content = 'photo{}_{}'.format(attachment["photo"]["owner_id"], attachment["photo"]["id"])
                    elif attachment["type"] == 'audio':
                        content = 'audio{}_{}'.format(attachment["audio"]["owner_id"], attachment["audio"]["id"])
                    elif attachment["type"] == 'video':
                        if 'is_private' in attachment["video"]:
                            CustomMemes.__print_error_text(aos, 'Данной видео является приватным и не может быть использовано в меме.')
                            return
                        else:
                            content = 'video{}_{}'.format(attachment["video"]["owner_id"], attachment["video"]["id"])
                    else:
                        CustomMemes.__print_error_text(aos, 'Данный тип вложения не поддерживается.')

                    # Сохраняем мем в базу данных
                    meme = {
                        'owner_id': event["object"]["message"]["from_id"],
                        'content': content,
                        'date': math.trunc(time.time())
                    }
                    meme_path = 'fun.memes.{}'.format(meme_name)
                    db.update({'$set': {meme_path: meme}})

                    # Сообщаем, что мем сохранен
                    aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '✅Мем сохранен!'))
                else:
                    CustomMemes.__print_help_message_add(aos, args)
            else:
                message = VKVariable.Multi('var', 'appeal', 'str', CustomMemes.__get_error_message_you_have_no_rights())
                aos.messages_send(message=message)
        elif subcommand == 'удал':
            user_permissions = UserPermissions(db, event["object"]["message"]["from_id"])
            if user_permissions.get('customize_memes'):
                meme_name = args.get_str(2, '').lower()
                if meme_name != '':
                    # Подгружаем удаляемый мем
                    db_result = db.find(projection={'_id': 0, f'fun.memes.{meme_name}': 1})
                    extractor = ValueExtractor(db_result)
                    meme_data = extractor.get(f'fun.memes.{meme_name}', None)

                    if meme_data is None:
                        CustomMemes.__print_error_text(aos, f"Мема '{meme_name}' не существует.")
                    else:
                        db.update({"$unset": {f'fun.memes.{meme_name}': 0}})

                        # Сообщаем, что мем удален
                        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '✅Мем удален!'))
                else:
                    CustomMemes.__print_help_message_del(aos, args)
            else:
                message = VKVariable.Multi('var', 'appeal', 'str', CustomMemes.__get_error_message_you_have_no_rights())
                aos.messages_send(message=message)
        elif subcommand == 'очис':
            user_permissions = UserPermissions(db, event["object"]["message"]["from_id"])
            if user_permissions.get('customize_memes'):
                # Подгружаем все мемы базы данных
                db_result = db.find(projection={'_id': 0, 'fun.memes': 1})
                extractor = ValueExtractor(db_result)
                all_memes = extractor.get('fun.memes', [])

                meme_count = len(all_memes)
                if meme_count > 0:
                    user_meme_count = args.get_int(2, 0)
                    if user_meme_count != 0:
                        if user_meme_count == meme_count:
                            db.update({"$unset": {f'fun.memes': 0}})

                            # Сообщаем, что мемы удален
                            aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '✅Все мемы удалены!'))
                        else:
                            CustomMemes.__print_help_message_cls(aos, args, meme_count)
                    else:
                        CustomMemes.__print_help_message_cls(aos, args, meme_count)
                else:
                    CustomMemes.__print_error_text(aos, "В беседе нет мемов.")
        elif subcommand == 'инфа':
            meme_name = args.get_str(2, '').lower()
            if meme_name != '':
                # Подгружаем удаляемый мем
                db_result = db.find(projection={'_id': 0, f'fun.memes.{meme_name}': 1})
                extractor = ValueExtractor(db_result)
                meme_data = extractor.get(f'fun.memes.{meme_name}', None)

                if meme_data is None:
                    CustomMemes.__print_error_text(aos, f"Мема '{meme_name}' не существует.")
                else:
                    added_time = datetime.datetime.fromtimestamp(meme_data["date"] + 10800).strftime("%d.%m.%Y")    # Время из БД + часовой пояс Москвы по UTC
                    meme_owner_id = meme_data["owner_id"]
                    message = VKVariable.Multi('var', 'appeal', 'str', f'Информация о меме:\n✏Имя: {meme_name}\n🤵Владелец: ', 'var', 'ownname', 'str', f'\n📅Добавлен: {added_time}\n📂Содержимое: Вложение')
                    script = f"var o=API.users.get({{'user_ids':[{meme_owner_id}]}})[0];var ownname='@id{meme_owner_id} ('+o.first_name+' '+o.last_name+')';"

                    user_permissions = UserPermissions(db, event["object"]["message"]["from_id"])
                    if user_permissions.get('customize_memes'):
                        keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                        keyboard.callback_button("Удалить", ['fun_memes', event["object"]["message"]["from_id"], 2, meme_name], KeyboardBuilder.NEGATIVE_COLOR)
                        keyboard = keyboard.build()

                        aos.messages_send(message=message, attachment=meme_data["content"], script=script, keyboard=keyboard)
                    else:
                        aos.messages_send(message=message, attachment=meme_data["content"], script=script)
            else:
                CustomMemes.__print_help_message_info(aos, args)
        else:
            CustomMemes.__print_error_message_unknown_subcommand(aos, args)

    @staticmethod
    def callback_button_command(callback_object: dict):
        event = callback_object["event"]
        payload = callback_object["payload"]
        db = callback_object["db"]
        output = callback_object["output"]
        manager = callback_object["manager"]

        aos = AdvancedOutputSystem(output, event, db)

        # Проверка режима allow_memes
        chat_modes = manager.chat_modes
        if not chat_modes.get('allow_memes'):
            mode_label = ChatModes.get_label('allow_memes')
            aos.show_snackbar(text=f"⛔ Режим {mode_label} отключен.")
            return
		
        testing_user_id = payload.get_int(1, event["object"]["user_id"])
        if testing_user_id != event["object"]["user_id"]:
            aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_NO_RIGHTS_TO_USE_THIS_BUTTON)
            return

        subcommand = payload.get_int(2, 1)
        if subcommand == 1:
            # Подгружаем все мемы базы данных
            db_result = db.find(projection={'_id': 0, 'fun.memes': 1})
            extractor = ValueExtractor(db_result)
            all_memes = extractor.get('fun.memes', [])

            names = list(all_memes)
            builder = PageBuilder(names, CustomMemes.SHOW_SIZE)
            number = payload.get_int(3, 1)

            try:
                page = builder(number)
                text = 'Список мемов [{}/{}]:'.format(number, builder.max_number)
                for i in page:
                    text += "\n• " + i

                keyboard = KeyboardBuilder(KeyboardBuilder.INLINE_TYPE)
                if number > 1:
                    prev_number = number - 1
                    keyboard.callback_button("{} ⬅".format(int2emoji(prev_number)), ['fun_memes', event["object"]["user_id"], 1, prev_number], KeyboardBuilder.SECONDARY_COLOR)
                if number < builder.max_number:
                    next_number = number + 1
                    keyboard.callback_button("➡ {}".format(int2emoji(next_number)), ['fun_memes', event["object"]["user_id"], 1, next_number], KeyboardBuilder.SECONDARY_COLOR)
                keyboard.new_line()
                keyboard.callback_button('Закрыть', ['bot_cancel', event["object"]["user_id"]], KeyboardBuilder.NEGATIVE_COLOR)

                aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', text), keyboard=keyboard.build())
            except PageBuilder.PageNumberException:
                aos.show_snackbar(text="⛔ Неверный номер страницы.")
        elif subcommand == 2:
            user_permissions = UserPermissions(db, testing_user_id)
            if user_permissions.get('customize_memes'):
                meme_name = payload.get_str(3, '').lower()
                if meme_name != '':
                    # Подгружаем удаляемый мем
                    db_result = db.find(projection={'_id': 0, f'fun.memes.{meme_name}': 1})
                    extractor = ValueExtractor(db_result)
                    meme_data = extractor.get(f'fun.memes.{meme_name}', None)

                    if meme_data is None:
                        aos.show_snackbar(text=f"⛔ Мема '{meme_name}' не существует.")
                    else:
                        db.update({"$unset": {f'fun.memes.{meme_name}': 0}})

                        # Сообщаем, что мем удален
                        aos.messages_edit(message=VKVariable.Multi('var', 'appeal', 'str', '✅Мем удален!'))
                else:
                    aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_INTERNAL_ERROR)
            else:
                aos.messages_send(message=DEFAULT_MESSAGES.SNACKBAR_YOU_HAVE_NO_RIGHTS)
        else:
            aos.show_snackbar(text=DEFAULT_MESSAGES.SNACKBAR_INTERNAL_ERROR)

    def __print_error_text(aos: AdvancedOutputSystem, text: str):
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔{}'.format(text)))

    def __print_help_message_add(aos: AdvancedOutputSystem, args: ArgumentParser):
        message_text = '⚠Позволяет добавлять кастомные мемы в беседу.\n\nИспользуйте с вложением:\n➡️ {} {} [название]'.format(args.get_str(0).lower(), args.get_str(1).lower())
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

    def __print_help_message_del(aos: AdvancedOutputSystem, args: ArgumentParser):
        message_text = '⚠Позволяет удалять кастомные мемы из беседы.\n\nИспользуйте:\n➡️ {} {} [название]'.format(args.get_str(0).lower(), args.get_str(1).lower())
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

    def __print_help_message_cls(aos: AdvancedOutputSystem, args: ArgumentParser, memes_count: int):
        message_text = '⚠Позволяет удалить ВСЕ кастомные мемы.\n\nИспользуйте:\n➡️ {} {} {}'.format(args.get_str(0).lower(), args.get_str(1).lower(), memes_count)
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

    def __print_help_message_info(aos: AdvancedOutputSystem, args: ArgumentParser):
        message_text = '⚠Позволяет узнать информацию о кастомном меме.\n\nИспользуйте:\n➡️ {} {} [название]'.format(args.get_str(0).lower(), args.get_str(1).lower())
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

    def __get_error_message_you_have_no_rights():
        permit_label = ManagerData.get_user_permissions_data()["customize_memes"]["label"]
        text = f"⛔ Для того, чтобы добавлять/удалять мемы необходимо иметь право {permit_label}."
        return text

    def __print_error_message_unknown_subcommand(aos: AdvancedOutputSystem, args: ArgumentParser):
        help_builder = CommandHelpBuilder('⛔Неверная субкоманда.')
        help_builder.command('{} показ', args.get_str(0).lower())
        help_builder.command('{} добав', args.get_str(0).lower())
        help_builder.command('{} удал', args.get_str(0).lower())
        help_builder.command('{} очис', args.get_str(0).lower())
        help_builder.command('{} инфа', args.get_str(0).lower())

        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', help_builder.build()))