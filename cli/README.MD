# cli
## Главная директория бота для развертывания на Debian/Ubuntu через cli. 
### Установка бота:
#### PHP Обработчик.
1. Переместить "vk-radabot" в "/root"
2. Переместить "vk-radabot/cli/service/php/bot-handler.service" в "/etc/systemd/system"
3. Установить пакеты "php", "php-mbstring", "php-curl" и "php-simplexml"
4. Прописать команду "systemctl daemon-reload" - Обновляем службы
5. Прописать команду "systemctl enable bot-handler" - Добавляем службу бота в автозагрузку
6. Прописать команду "systemctl restart bot-handler" - Перезапускаем службу бота

#### Python Обработчик.
1. Переместить "vk-radabot" в "/root"
2. Переместить "vk-radabot/cli/service/python/bot-handler.service" в "/etc/systemd/system"
3. Установить пакеты "php", "php-mbstring", "php-curl" и "php-simplexml"
4. Установить пакет python и python-pip
5. Прописать команду "pip install requests" - Установка библиотеки requests для python
6. Прописать команду "systemctl daemon-reload" - Обновляем службы
7. Прописать команду "systemctl enable bot-handler" - Добавляем службу бота в автозагрузку
8. Прописать команду "systemctl restart bot-handler" - Перезапускаем службу бота