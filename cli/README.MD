# cli
## Главная дериктория бота для развертывания на VPS/VDS (Linux) через cli. 
### Установки бота:
1. Переместить "vk-radabot" в "/root"
2. Переместить "vk-radabot/cli/bot-handler.service" в "/etc/systemd/system"
3. Установить пакеты "php", "php-mbstring", "php-curl" и "php-simplexml"
4. Прописать команду "systemctl daemon-reload" - Обновляем службы
5. Прописать команду "systemctl enable bot-handler" - Добавляем службу бота в автозагрузку
6. Прописать команду "systemctl restart bot-handler" - Перезапускаем службу бота