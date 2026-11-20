FROM php:8.4-fpm-bookworm AS app
RUN apt-get update \
    && apt-get install -y --no-install-recommends libmagickwand-dev \
    && docker-php-ext-install bcmath pdo_mysql \
    && pecl install imagick-3.8.0 \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
WORKDIR /app
COPY app/ app/
COPY public/ public/
COPY deploy/php.ini /usr/local/etc/php/conf.d/imge.ini
RUN install -d -o www-data -g www-data -m 0755 storage storage/i storage/t

FROM nginx:1.28-alpine AS web
COPY deploy/nginx/imge.conf /etc/nginx/conf.d/default.conf
COPY public/assets/ /app/public/assets/
