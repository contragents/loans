FROM yiisoftware/yii2-php:8.2-fpm

RUN apt-get update

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Объявляем аргумент, который придет из docker-compose.yml
ARG YII_ENV

# Копируем конфиг. Он будет удален, если YII_ENV != dev
COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# Устанавливаем Xdebug ИЛИ удаляем его конфиг
RUN if [ "$YII_ENV" = "dev" ]; then \
      docker-php-ext-enable xdebug; \
    else \
      rm /usr/local/etc/php/conf.d/99-xdebug.ini; \
    fi