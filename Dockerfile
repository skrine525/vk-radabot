FROM ubuntu:20.04

WORKDIR /bot

# Установка PHP
RUN apt update && apt install -y software-properties-common
RUN add-apt-repository ppa:ondrej/php && apt update
RUN apt install -y php7.0 php7.0-mbstring php7.0-curl php7.0-gd php7.0-simplexml php7.0-mongodb

# Установка Python
RUN apt install -y python3 python3-pip
RUN pip3 install requests pymongo bunch seam_carving pillow

# Копирование исходного кода
COPY ./radabot ./radabot
COPY ./radabot-php.php ./radabot-php.php
COPY ./radabot.py ./radabot.py