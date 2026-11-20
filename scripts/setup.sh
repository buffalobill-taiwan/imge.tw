#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose version >/dev/null

if [ ! -f .env ]; then
    umask 077
    db_password=$(openssl rand -hex 32)
    root_password=$(openssl rand -hex 32)
    (
        set -C
        printf 'DB_PASSWORD=%s\nDB_ROOT_PASSWORD=%s\n' "$db_password" "$root_password" > .env
    )
    printf 'Created .env\n'
fi

docker compose up --build -d
