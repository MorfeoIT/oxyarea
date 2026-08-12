#!/usr/bin/env bash
#
# Creates a clean WordPress at test.44123.it/oxyarea as a test bed for the
# OxyArea plugin. Deliberately bare: no OceanWP, no page builder, no cookie
# banner. A plugin test bed exists to make it obvious which plugin caused what,
# and every extra plugin on it is a suspect.
#
# Refuses to run if the directory already exists. Touches no other site.

set -euo pipefail

USERNAME=webtest
DOMAIN=test.44123.it
SITE=oxyarea
DOCROOT=/home/${USERNAME}/web/${DOMAIN}/public_html
ROOT=${DOCROOT}/${SITE}
HESTIA=/usr/local/hestia/bin
CREDS=/home/${USERNAME}/oxyarea-testbed-credentials.txt

if [ -e "${ROOT}" ]; then
  echo "STOP: ${ROOT} esiste gia'. Non tocco niente."
  exit 1
fi

if /usr/local/hestia/bin/v-list-databases "${USERNAME}" plain 2>/dev/null | cut -f1 | grep -qx "${USERNAME}_${SITE}"; then
  echo "STOP: il database ${USERNAME}_${SITE} esiste gia'. Non tocco niente."
  exit 1
fi

DBPASS=$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 28)
WPPASS=$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 20)

echo "== database =="
"${HESTIA}/v-add-database" "${USERNAME}" "${SITE}" "${SITE}" "${DBPASS}" mysql
echo "creato ${USERNAME}_${SITE}"

echo "== wordpress =="
sudo -u "${USERNAME}" -H bash -c "cd '${DOCROOT}' && wp core download --path='${SITE}' --locale=en_US"

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp config create \
  --dbname='${USERNAME}_${SITE}' \
  --dbuser='${USERNAME}_${SITE}' \
  --dbpass='${DBPASS}' \
  --dbprefix=wp_ \
  --skip-check"

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp core install \
  --url='https://${DOMAIN}/${SITE}' \
  --title='OxyArea test bed' \
  --admin_user=oxysoft \
  --admin_password='${WPPASS}' \
  --admin_email=info@oxysoft.it \
  --skip-email"

echo "== impostazioni del banco di prova =="
# Notices and deprecations are the point of a test bed: they go to a log rather
# than to the screen, so a stray warning cannot be mistaken for plugin output.
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp config set WP_DEBUG true --raw"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp config set WP_DEBUG_LOG true --raw"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp config set WP_DEBUG_DISPLAY false --raw"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp config set SCRIPT_DEBUG true --raw"

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp option update blog_public 0"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp rewrite structure '/%postname%/'"

# WP-CLI will not write this one: `wp rewrite --hard` needs configuration it does
# not have for an install in a subdirectory, and says so in a warning that is easy
# to read past. Without it every pretty permalink on the test bed is a 404, which
# looks exactly like a plugin that renders nothing.
cat > "${ROOT}/.htaccess" <<HTACCESS
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /${SITE}/
RewriteRule ^index\\.php\$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /${SITE}/index.php [L]
</IfModule>
# END WordPress
HTACCESS
chown "${USERNAME}:${USERNAME}" "${ROOT}/.htaccess"
chmod 644 "${ROOT}/.htaccess"

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp plugin delete akismet hello" || true

echo "== plugin check =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp plugin install plugin-check --activate"

echo "== utenti di prova =="
# The cast the specification names. Same four people in every test, so that a
# release gate written in terms of Alice and Bob means something.
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp user create alice alice@example.test --role=subscriber --user_pass='${WPPASS}alice' --display_name='Alice (ACME)'"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp user create bob bob@example.test --role=subscriber --user_pass='${WPPASS}bob' --display_name='Bob (Beta)'"
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp user create carol carol@example.test --role=subscriber --user_pass='${WPPASS}carol' --display_name='Carol (agent)'"

umask 077
{
  echo "OxyArea test bed - https://${DOMAIN}/${SITE}"
  echo "creato: $(date -u '+%Y-%m-%d %H:%M UTC')"
  echo
  echo "WordPress admin : oxysoft / ${WPPASS}"
  echo "database        : ${USERNAME}_${SITE}"
  echo "db user         : ${USERNAME}_${SITE} / ${DBPASS}"
  echo
  echo "alice / ${WPPASS}alice"
  echo "bob   / ${WPPASS}bob"
  echo "carol / ${WPPASS}carol"
  echo
  echo "Basic Auth sul dominio: vedi .htpasswd del docroot."
} > "${CREDS}"
chown "${USERNAME}:${USERNAME}" "${CREDS}"

echo
echo "== fatto =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp core version"
echo "credenziali salvate in ${CREDS} (solo lettura per ${USERNAME})"
