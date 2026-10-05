#!/bin/sh
set -e

rm -f /tmp/ready

for dir in storage/logs storage/framework/views storage/framework/cache bootstrap/cache; do
    if [ -d "$dir" ] && [ ! -w "$dir" ]; then
        echo "ERRO: $dir não tem permissão de escrita para o usuário $(id -un) (uid $(id -u))." >&2
        echo "Corrija com: docker compose run --rm -u root app chown -R $(id -u):$(id -g) storage bootstrap/cache" >&2
        exit 1
    fi
done

# Só o container "app" prepara o projeto; os demais (fila) esperam ele ficar
# saudável no docker compose, evitando dois "composer install" simultâneos.
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist --no-progress
    fi

    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    if ! grep -Eq '^APP_KEY=.+' .env; then
        php artisan key:generate --force
    fi

    php artisan migrate --force
fi

touch /tmp/ready

exec "$@"
