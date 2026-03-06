FROM php:8.2-cli

WORKDIR /app

# install mysqli
RUN docker-php-ext-install mysqli

# copy project
COPY . /app

# start php server
CMD php -S 0.0.0.0:$PORT
