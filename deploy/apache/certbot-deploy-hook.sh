#!/bin/bash

set -euo pipefail

echo "certbot-deploy-hook -- Syncing Apache certificate paths"
/usr/local/bin/apache-sync-certificates.sh

echo "certbot-deploy-hook -- Reloading Apache"
apachectl -k graceful

echo "certbot-deploy-hook -- Restarting YPTSocket"
php /var/www/html/AVideo/plugin/YPTSocket/serverRestart.php force > /proc/1/fd/1 2>/proc/1/fd/2
