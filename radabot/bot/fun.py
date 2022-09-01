import math
import time
from radabot.core.io import ChatEventManager, AdvancedOutputSystem
from radabot.core.manager import ChatModes
from radabot.core.system import ArgumentParser, CommandHelpBuilder, ValueExtractor
from radabot.core.vk import VKVariable


def initcmd(manager: ChatEventManager):
    manager.add_message_command('!мемы', CustomMemes.message_command)


class CustomMemes:
    def message_command(callin: ChatEventManager.CallbackInputObject):
        event = callin.event
        output = callin.output
        args = callin.args
        db = callin.db
        manager = callin.manager

        aos = AdvancedOutputSystem(output, event, db)

        # Проверка режима allow_memes
        chat_modes = callin.manager.chat_modes
        if not chat_modes.get('allow_memes'):
            mode_label = ChatModes.get_label('allow_memes')
            CustomMemes.__print_error_text(aos, 'Режим {} отключен.'.format(mode_label))
            return

        subcommand = args.get_str(1, '').lower()
        if subcommand == 'показ':
            pass
        elif subcommand == 'добав':
            meme_name = args.get_str(2, '').lower()
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
                if len(all_memes) >= 100:
                    CustomMemes.__print_error_text(aos, 'Максимальное количество мемов в беседе: 100 штук.')
                    return

                # Ограничение, если мем с таким названием уже существует
                if meme_name in all_memes:
                    CustomMemes.__print_error_text(aos, 'Мем с таким названием уже существует.')
                    return

                # Ограничение, если не прикреплено вложение
                if len(event.event_object.attachments) == 0:
                    CustomMemes.__print_error_text(aos, 'Для добавление мема необходимо прикрепить вложение.')
                    return

                attachment = event.event_object.attachments[0]
                content = ''

                if attachment.type == 'photo':
                    if 'access_key' in attachment.photo:
                        content = 'photo{}_{}_{}'.format(attachment.photo.owner_id, attachment.photo.id, attachment.photo.access_key)
                    else:
                        content = 'photo{}_{}'.format(attachment.photo.owner_id, attachment.photo.id)
                elif attachment.type == 'audio':
                    content = 'audio{}_{}'.format(attachment.audio.owner_id, attachment.audio.id)
                elif attachment.type == 'video':
                    if 'is_private' in attachment.video:
                        CustomMemes.__print_error_text(aos, 'Данной видео является приватным и не может быть использовано в меме.')
                        return
                    else:
                        content = 'video{}_{}'.format(attachment.video.owner_id, attachment.video.id)
                else:
                    CustomMemes.__print_error_text(aos, 'Данный тип вложения не поддерживается.')

                # Сохраняем мем в базу данных
                meme = {
                    'owner_id': event.event_object.from_id,
                    'content': content,
                    'date': math.trunc(time.time())
                }
                meme_path = 'fun.memes.{}'.format(meme_name)
                db.update({'$set': {meme_path: meme}})

                # Сообщаем, что мем сохранен
                aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '✅Мем сохранен!'))

            else:
                CustomMemes.__print_help_message_add(aos, args)
        elif subcommand == 'удал':
            pass
        elif subcommand == 'очис':
            pass
        elif subcommand == 'инфа':
            pass
        else:
            CustomMemes.__print_error_message_unknown_subcommand(aos, args)

    def __print_error_text(aos: AdvancedOutputSystem, text: str):
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', '⛔{}'.format(text)))

    def __print_help_message_add(aos: AdvancedOutputSystem, args: ArgumentParser):
        message_text = '⚠Позволяет добавлять кастомные мемы в беседу.\n\nИспользуйте с вложением:\n➡️ {} {} [название]'.format(args.get_str(0).lower(), args.get_str(1).lower())
        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', message_text))

    def __print_error_message_unknown_subcommand(aos: AdvancedOutputSystem, args: ArgumentParser):
        help_builder = CommandHelpBuilder('⛔Неверная субкоманда.')
        help_builder.command('{} показ', args.get_str(0).lower())
        help_builder.command('{} добав', args.get_str(0).lower())
        help_builder.command('{} удал', args.get_str(0).lower())
        help_builder.command('{} инфа', args.get_str(0).lower())

        aos.messages_send(message=VKVariable.Multi('var', 'appeal', 'str', help_builder.build()))