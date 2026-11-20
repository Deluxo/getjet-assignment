#!/bin/sh
set -eu

# Keep the schema up to date before the app starts serving requests.
php bin/console doctrine:migrations:migrate --no-interaction

exec "$@"
