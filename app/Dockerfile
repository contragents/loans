# Используем официальный образ Yii2, который уже содержит fpm
FROM yiisoftware/yii2-php:8.2-fpm

# Устанавливаем расширение для работы с PostgreSQL
#RUN docker-php-ext-install pdo pdo_pgsql
# Install Postgre PDO
RUN apt-get update

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# --- Условная установка Xdebug ---

# 1. Объявляем аргумент, который придет из docker-compose.yml
ARG YII_ENV

# 2. Копируем конфиг. Он будет удален, если YII_ENV != dev
#    (Сам файл app/docker/xdebug.ini остается неизменным)
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# 3. Устанавливаем Xdebug ИЛИ удаляем его конфиг
RUN if [ "$YII_ENV" = "dev" ]; then \
      echo ">>> YII_ENV=dev: Установка Xdebug..."; \
      docker-php-ext-enable xdebug; \
    else \
      echo ">>> YII_ENV is not 'dev': Пропуск Xdebug и удаление 99-xdebug.ini..."; \
      rm /usr/local/etc/php/conf.d/99-xdebug.ini; \
    fi
# --- Конец секции Xdebug ---