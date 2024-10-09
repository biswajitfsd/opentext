#!/bin/bash
set -e

# Ensure log directories exist and have correct permissions
mkdir -p /var/www/html/var/log /var/log/supervisor
chown -R www-data:www-data /var/www/html/var/log
chown -R root:root /var/log/supervisor

# Ensure Supervisor socket directory exists and has correct permissions
mkdir -p /var/run/supervisor
chmod 755 /var/run/supervisor

# First arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
    set -- supervisord "$@"
fi

# Run the command
exec "$@"