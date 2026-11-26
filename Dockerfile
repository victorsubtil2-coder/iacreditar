FROM php:8.3-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install curl \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY index.php router.php ./
EXPOSE 10000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app /app/router.php"]
