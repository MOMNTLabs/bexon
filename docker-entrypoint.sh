#!/bin/sh
set -eu

cd /app

php /app/scripts/release.php

exec frankenphp run --config /etc/caddy/Caddyfile
