#!/usr/bin/env bash
# Пример серверного скрипта после успешной оплаты (pay_script_path).
# Аргументы: $1=target (IP/домен) $2=years $3=orderId $4=email
set -euo pipefail
TARGET="${1:-}"
YEARS="${2:-}"
ORDER="${3:-}"
EMAIL="${4:-}"

echo "OK"
echo "target=${TARGET}"
echo "years=${YEARS}"
echo "order=${ORDER}"
echo "email=${EMAIL}"
echo "Обработка завершена $(date '+%Y-%m-%d %H:%M:%S')"
