# cli
## Главная директория бота для развертывания на Debian/Ubuntu через cli. 
### Установка бота:
#### PHP Обработчик.
1. Переместить "vk-radabot" в "/root"
2. Переместить "vk-radabot/cli/service/php/radabot.service" в "/etc/systemd/system"
3. Установить пакеты "php", "php-mbstring", "php-curl" и "php-simplexml"
4. Выполнить команду "systemctl daemon-reload" - Обновляем службы
5. Выполнить команду "systemctl enable radabot" - Добавляем службу бота в автозагрузку
6. Выполнить команду "systemctl restart radabot" - Перезапускаем службу бота

#### Python Обработчик.
1. Переместить "vk-radabot" в "/root"
2. Переместить "vk-radabot/cli/service/python/radabot.service" в "/etc/systemd/system"
3. Установить пакеты "php", "php-mbstring", "php-curl" и "php-simplexml"
4. Установить пакет python и python-pip
5. Выполнить команду "pip install requests" - Установка библиотеки requests для python
6. Выполнить команду "systemctl daemon-reload" - Обновляем службы
7. Выполнить команду "systemctl enable radabot" - Добавляем службу бота в автозагрузку
8. Выполнить команду "systemctl restart radabot" - Перезапускаем службу бота