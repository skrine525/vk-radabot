# cli
## Главная директория бота для развертывания на Debian/Ubuntu через cli. 
### Установка бота:
1. Войдите под root пользовтеля:
```shell
$ sudo su
```
2. Создайте нового пользователя radabot и перейдите в его домашний каталог:
```shell
$ adduser --disabled-login radabot
$ cd ~radabot
```
3. Скопируйте код бота в текущий каталог и настройте bot/data/config.json файл.
4. Установите пакеты "php7.0", php7.0-mbstring", "php7.0-curl", "php7.0-gd" и "php7.0-simplexml":
```shell
$ apt install php7.0 php7.0-mbstring php7.0-curl php7.0-gd php7.0-simplexml
```
5. Установите пакет "python" и "python-pip":
```shell
$ apt install python3 python3-pip
```
6. Установите библиотеку requests для python:
```shell
$ pip3 install requests
```
7. Скопируйте файл службы в системную директорию:
```shell
$ cp cli/service/radabot.service /etc/systemd/system
```
8. Выполните настройку службы:
```shell
$ systemctl daemon-reload
$ systemctl enable radabot
$ systemctl restart radabot
```
9. Запустите службу:
```shell
$ systemctl start radabot
```