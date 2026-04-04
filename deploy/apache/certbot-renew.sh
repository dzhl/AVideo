#!/bin/bash

set -euo pipefail

SERVER_NAME="${SERVER_NAME:-localhost}"
RUN_CERTBOT="${RUN_CERTBOT:-auto}"

should_run_certbot() {
  if [ "_${RUN_CERTBOT}_" = "_yes_" ]; then
    return 0
  fi

  if [ "_${RUN_CERTBOT}_" = "_no_" ]; then
    return 1
  fi

  if [[ "${SERVER_NAME}" = "localhost" ]] || [[ "${SERVER_NAME}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    return 1
  fi

  return 0
}

if ! should_run_certbot; then
  echo "certbot-renew -- Skipping renewal for SERVER_NAME=${SERVER_NAME} RUN_CERTBOT=${RUN_CERTBOT}"
  exit 0
fi

echo "certbot-renew -- Running certbot renew"
certbot renew --quiet --deploy-hook /usr/local/bin/certbot-deploy-hook.sh
