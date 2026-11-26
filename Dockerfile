
FROM php:8.3-cli

WORKDIR /app

COPY index.php router.php ./

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t /app /app/router.php"]

