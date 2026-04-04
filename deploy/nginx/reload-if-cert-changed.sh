#!/bin/bash

set -euo pipefail

SERVER_NAME="${SERVER_NAME:-localhost}"
RUN_CERTBOT="${RUN_CERTBOT:-auto}"
STATE_FILE="/var/www/tmp/live-cert-state.sha256"
CONFIG_NGINX_FILE="/usr/local/nginx/conf/nginx.conf"

if [ "_${RUN_CERTBOT}_" = "_no_" ]; then
  exit 0
fi

if [[ "${SERVER_NAME}" = "localhost" ]] || [[ "${SERVER_NAME}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  exit 0
fi

CERT_FILE="/etc/letsencrypt/live/${SERVER_NAME}/fullchain.pem"
KEY_FILE="/etc/letsencrypt/live/${SERVER_NAME}/privkey.pem"

if [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
  exit 0
fi

if ! openssl x509 -checkend 0 -noout -in "${CERT_FILE}" >/dev/null 2>&1; then
  exit 0
fi

mkdir -p "$(dirname "${STATE_FILE}")"
current_hash="$(cat "${CERT_FILE}" "${KEY_FILE}" | sha256sum | awk '{print $1}')"
previous_hash=""

if [ -f "${STATE_FILE}" ]; then
  previous_hash="$(cat "${STATE_FILE}")"
fi

if [ "${current_hash}" != "${previous_hash}" ]; then
  echo "nginx-cert-watch -- Certificate change detected"
  /usr/local/bin/nginx-sync-certificates.sh
  /usr/local/nginx/sbin/nginx -s reload
  printf '%s' "${current_hash}" > "${STATE_FILE}"
fi
