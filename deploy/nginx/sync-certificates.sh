#!/bin/bash

set -euo pipefail

CONFIG_NGINX_FILE="${CONFIG_NGINX_FILE:-/usr/local/nginx/conf/nginx.conf}"
SERVER_NAME="${SERVER_NAME:-localhost}"
TLS_CERTIFICATE_FILE="${TLS_CERTIFICATE_FILE:-/etc/apache2/ssl/localhost.crt}"
TLS_CERTIFICATE_KEY="${TLS_CERTIFICATE_KEY:-/etc/apache2/ssl/localhost.key}"

is_local_server_name() {
  [[ "${SERVER_NAME}" = "localhost" ]] || [[ "${SERVER_NAME}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

is_certificate_valid() {
  local cert_file="$1"
  [ -f "${cert_file}" ] && openssl x509 -checkend 0 -noout -in "${cert_file}" >/dev/null 2>&1
}

get_effective_certificates() {
  local letsencrypt_dir="/etc/letsencrypt/live/${SERVER_NAME}"
  local cert_file="${TLS_CERTIFICATE_FILE}"
  local key_file="${TLS_CERTIFICATE_KEY}"

  if ! is_local_server_name \
    && [ -f "${letsencrypt_dir}/fullchain.pem" ] \
    && [ -f "${letsencrypt_dir}/privkey.pem" ] \
    && is_certificate_valid "${letsencrypt_dir}/fullchain.pem"; then
    cert_file="${letsencrypt_dir}/fullchain.pem"
    key_file="${letsencrypt_dir}/privkey.pem"
  fi

  printf '%s\n%s\n' "${cert_file}" "${key_file}"
}

mapfile -t effective_paths < <(get_effective_certificates)
EFFECTIVE_TLS_CERTIFICATE_FILE="${effective_paths[0]}"
EFFECTIVE_TLS_CERTIFICATE_KEY="${effective_paths[1]}"

echo "nginx-sync -- Using certificate ${EFFECTIVE_TLS_CERTIFICATE_FILE}"
echo "nginx-sync -- Using key ${EFFECTIVE_TLS_CERTIFICATE_KEY}"

sed -i "s#^\\s*ssl_certificate .*#                ssl_certificate ${EFFECTIVE_TLS_CERTIFICATE_FILE};#" "${CONFIG_NGINX_FILE}"
sed -i "s#^\\s*ssl_certificate_key .*#                ssl_certificate_key ${EFFECTIVE_TLS_CERTIFICATE_KEY};#" "${CONFIG_NGINX_FILE}"

mkdir -p /etc/letsencrypt/live/localhost/
cp "${EFFECTIVE_TLS_CERTIFICATE_FILE}" /etc/letsencrypt/live/localhost/fullchain.pem
cp "${EFFECTIVE_TLS_CERTIFICATE_KEY}" /etc/letsencrypt/live/localhost/privkey.pem
