#!/usr/bin/env bash
# Exercise Apache's .htaccess against disposable fictional files only.
set -euo pipefail
cd "$(dirname "$0")/.."
command -v apache2 >/dev/null || { echo 'Apache 2.4 is required' >&2; exit 2; }
command -v curl >/dev/null || { echo 'curl is required' >&2; exit 2; }
if [[ "$(id -u)" == 0 ]]; then APACHE_CMD=(apache2); else APACHE_CMD=(sudo apache2); fi
root="$(mktemp -d)"
web="$root/web"
port="$((20000 + $$ % 20000))"
mkdir -p "$web"/{config,app,tools,database,docs,tests,views,storage,.git,.github,css,assets/images,admin,.well-known/acme-challenge}
chmod 755 "$root" "$web"
cp .htaccess "$web/.htaccess"
for file in index.html css/base.css admin/login.php .well-known/acme-challenge/example; do
    printf 'synthetic public marker\n' > "$web/$file"
done
for file in config/config.php app/auth.php tools/seed.php database/schema.sql docs/readme.md tests/example.php views/sample.php storage/test.dat .git/config .github/workflows.yml backup.sql .env README.md; do
    printf 'synthetic private marker\n' > "$web/$file"
done
cat > "$root/httpd.conf" <<CONFIG
ServerRoot "/etc/apache2"
LoadModule mpm_event_module /usr/lib/apache2/modules/mod_mpm_event.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so
LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so
LoadModule mime_module /usr/lib/apache2/modules/mod_mime.so
ServerName localhost
Listen 127.0.0.1:$port
PidFile "$root/apache.pid"
ErrorLog "$root/error.log"
LogLevel warn
TypesConfig /etc/mime.types
DocumentRoot "$web"
<Directory "$web">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
DirectoryIndex index.html
CONFIG
started=0
cleanup() {
    if [[ "$started" == 1 ]]; then "${APACHE_CMD[@]}" -f "$root/httpd.conf" -k stop >/dev/null 2>&1 || :; fi
    rm -rf -- "$root"
}
trap cleanup EXIT
"${APACHE_CMD[@]}" -f "$root/httpd.conf" -t
"${APACHE_CMD[@]}" -f "$root/httpd.conf" -k start
started=1
assert_code() {
    local expected="$1" relative="$2" received
    received=$(curl --silent --show-error --max-time 6 --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$port/$relative")
    printf '%s %s\n' "$received" "$relative"
    if [[ "$received" != "$expected" ]]; then echo "Expected HTTP $expected for $relative, got $received" >&2; exit 1; fi
}
for path in index.html css/base.css admin/login.php .well-known/acme-challenge/example; do assert_code 200 "$path"; done
for path in config/config.php app/auth.php tools/seed.php database/schema.sql docs/readme.md tests/example.php views/sample.php storage/test.dat .git/config .github/workflows.yml backup.sql .env README.md; do assert_code 403 "$path"; done
directory_code=$(curl --silent --show-error --max-time 6 --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$port/assets/images/")
printf '%s assets/images/ (directory listing blocked)\n' "$directory_code"
[[ "$directory_code" == 403 || "$directory_code" == 404 ]] || { echo 'Directory listing was not blocked' >&2; exit 1; }
echo 'PASS: Apache-only synthetic allow/deny web-root regression.'
