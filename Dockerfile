FROM php:8.2-cli

WORKDIR /app

# install mysqli
RUN docker-php-ext-install mysqli
RUN printf 'session.upload_progress.enabled=0\n' > /usr/local/etc/php/conf.d/zz-security.ini

# copy project
COPY . /app

# start php server
CMD php -S 0.0.0.0:$PORT
