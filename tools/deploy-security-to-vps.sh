#!/bin/bash
# Запускать НА веб-сервере, где крутится https://studiowest.ru (сейчас DNS → 176.126.103.96).
# Пример:
#   scp -r studio-west/tools/security-bundle.tar.gz user@176.126.103.96:/tmp/
#   ssh user@176.126.103.96 'cd /path/to/studiowest && tar xzf /tmp/security-bundle.tar.gz && bash studio-west/tools/deploy-security-to-vps.sh'
set -euo pipefail
ROOT="${1:-}"
if [[ -z "$ROOT" ]]; then
  if [[ -f ./index.php && -d ./assets ]]; then ROOT="$(pwd)"
  elif [[ -f /home/srv86525/htdocs/studiowest/index.php ]]; then ROOT=/home/srv86525/htdocs/studiowest
  else echo "Usage: $0 /path/to/studiowest"; exit 1; fi
fi
CORE="$(dirname "$ROOT")/../core"
[[ -d "$CORE" ]] || CORE=/home/srv86525/htdocs/core
echo "ROOT=$ROOT CORE=$CORE"

cp -a "$ROOT/../" 2>/dev/null || true
# files expected relative to repo after untar into site or from cwd
SRC="$(cd "$(dirname "$0")/../.." && pwd)"
if [[ ! -f "$SRC/studio-west/snippet/request.php" ]]; then SRC="$(pwd)"; fi

install -m 644 "$SRC/studio-west/snippet/request.php" "$ROOT/assets/teme_sw/snippet/request.php"
install -m 644 "$SRC/assets/teme_sw/snippet/PaymentHandler.php" "$ROOT/assets/teme_sw/snippet/PaymentHandler.php"
install -m 644 "$SRC/studio-west/js/sw.js" "$ROOT/assets/teme_sw/js/sw.js"
install -m 644 "$SRC/studio-west/js/payment.js" "$ROOT/assets/teme_sw/js/payment.js"
install -m 644 "$SRC/studio-west/js/sw.js" "$ROOT/sw.js"
mkdir -p "$ROOT/assets/attach" "$ROOT/studio-west/nginx"
install -m 644 "$SRC/assets/attach/.htaccess" "$ROOT/assets/attach/.htaccess"
install -m 644 "$SRC/studio-west/nginx/security-include.conf" "$ROOT/studio-west/nginx/security-include.conf"
rm -f "$ROOT/ht.access" "$ROOT/htaccess" || true

# Stender gate
if [[ -d "$ROOT/stender" && -f "$ROOT/stender/index.html" ]]; then
  cp -a "$ROOT/stender/index.html" "$ROOT/stender/app.html"
  cat > "$ROOT/stender/index.php" <<'PHP'
<?php
declare(strict_types=1);
$user=''; $pass='';
$cfgCandidates=[dirname(__DIR__).'/../core/config/config.inc.php','/home/srv86525/htdocs/core/config/config.inc.php'];
foreach ($cfgCandidates as $cfg) {
  if (!is_file($cfg)) continue;
  include $cfg;
  try {
    $pdo=new PDO($database_dsn,$database_user,$database_password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $st=$pdo->prepare('SELECT `key`, value FROM modx_system_settings WHERE `key` IN (?,?)');
    $st->execute(['sw.admin_basic_user','sw.admin_basic_pass']);
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k=>$v) {
      if ($k==='sw.admin_basic_user') $user=(string)$v;
      if ($k==='sw.admin_basic_pass') $pass=(string)$v;
    }
  } catch (Throwable $e) { http_response_code(500); echo 'Config error'; exit; }
  break;
}
if ($user!=='' && $pass!=='') {
  $ru=(string)($_SERVER['PHP_AUTH_USER']??'');
  $rp=(string)($_SERVER['PHP_AUTH_PW']??'');
  if (!hash_equals($user,$ru) || !hash_equals($pass,$rp)) {
    header('WWW-Authenticate: Basic realm="Stender"');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    http_response_code(401); echo 'Authentication required'; exit;
  }
}
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
readfile(__DIR__.'/app.html');
PHP
  rm -f "$ROOT/stender/index.html"
  echo "stender gate installed"
fi

# DB deploy
cp "$SRC/studio-west/snippet/plugin.swSecurityHeaders.php" /tmp/plugin.swSecurityHeaders.php
cp "$SRC/studio-west/snippet/plugin.swHtmlCache.php" /tmp/plugin.swHtmlCache.php
cp "$SRC/studio-west/tools/deploy-security-hardening.php" /tmp/deploy-security-hardening.php
# patch include path in deploy script
sed -i "s#/home/srv86525/htdocs/core/config/config.inc.php#$CORE/config/config.inc.php#" /tmp/deploy-security-hardening.php
: "${SW_ADMIN_BASIC_USER:=swadmin}"
: "${SW_ADMIN_BASIC_PASS:?Set SW_ADMIN_BASIC_PASS}"
php81 /tmp/deploy-security-hardening.php || php /tmp/deploy-security-hardening.php

# Update request snippet in DB
php81 -r '
include "'$CORE'/config/config.inc.php";
$pdo=new PDO($database_dsn,$database_user,$database_password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$code=file_get_contents("'"$SRC"'/studio-west/snippet/request.php");
$code=preg_replace("/^<\?php\s*/","",$code);
$pdo->prepare("UPDATE modx_site_snippets SET snippet=? WHERE name=?")->execute([$code,"request"]);
echo "snippet request updated\n";
' || true

rm -rf "$CORE/cache/"*
echo "DONE. Include nginx snippet: $ROOT/studio-west/nginx/security-include.conf"
echo "Basic auth user: $SW_ADMIN_BASIC_USER"
