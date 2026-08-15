#!/usr/bin/env bash
#
# Checks that a dashboard reaches the browser of the person it belongs to, and
# reaches nobody else's.
#
# The resolver is unit tested and the rendering is exercised inside WordPress.
# Neither would notice a dashboard that resolved correctly and then arrived on
# the page of somebody who is not signed in.
#
# Run as root on the test bed server.

set -euo pipefail

USERNAME=webtest
ROOT=/home/${USERNAME}/web/test.44123.it/public_html/oxyarea
SITE=https://test.44123.it/oxyarea
CREDS=/home/${USERNAME}/oxyarea-testbed-credentials.txt
# La coppia dell'autenticazione del dominio non sta in questo file: si legge da
# /home/webtest/.basic-44123 (chmod 600), e la variabile d'ambiente la scavalca.
# Un repository non e' il posto di una password, e la storia di git diventa
# pubblica tutta insieme, non solo l'ultimo commit.
BASIC=${BASIC:-$(cat "${BASIC_FILE:-/home/webtest/.basic-44123}" 2>/dev/null)}

if [ -z "$BASIC" ]; then
	echo "non trovo la coppia del dominio: passa BASIC=utente:password" >&2
	exit 1
fi
JAR=$(mktemp)
PAGE=$(mktemp)

passed=0
failed=0

check() {
  if [ "$2" = "yes" ]; then
    passed=$((passed + 1))
    echo "  ok    $1"
  else
    failed=$((failed + 1))
    echo "  FAIL  $1"
  fi
}

wp() { sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp $*"; }

echo "== a dashboard for subscribers, and a page to show it on =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && OXYAREA_ROLE=subscriber wp eval-file /tmp/testbed-dashboard.php" > /dev/null
check "the dashboard was created" yes

PAGE_ID=$(wp "post list --post_type=page --name=oxyarea-dashboard-flow --field=ID --format=ids")
if [ -z "${PAGE_ID}" ]; then
  PAGE_ID=$(wp "post create --post_type=page --post_status=publish --post_title='My area' --post_name=oxyarea-dashboard-flow --porcelain --post_content='<!-- wp:oxyarea/login /--><!-- wp:oxyarea/dashboard /-->'")
fi

URL="${SITE}/oxyarea-dashboard-flow/"
echo "  page: ${URL}"

echo "== a visitor who is not signed in sees none of it =="

curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"

grep -q 'this is your private area' "${PAGE}" && check "the private content stays private" no || check "the private content stays private" yes
grep -q 'oxyarea-profile-summary' "${PAGE}" && check "and so does the account summary" no || check "and so does the account summary" yes
grep -q 'name="user_password"' "${PAGE}" && check "they are offered the sign-in form instead" yes || check "they are offered the sign-in form instead" no

echo "== signing in as Alice, a subscriber =="

ALICE_PASS=$(grep '^alice / ' "${CREDS}" | sed 's|^alice / ||')
NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')

curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ALICE_PASS}" \
  --data-urlencode "redirect_to=/oxyarea/oxyarea-dashboard-flow/" \
  "${URL}"

curl -sS -u "${BASIC}" -b "${JAR}" -o "${PAGE}" "${URL}"

grep -q 'this is your private area' "${PAGE}" && check "now the dashboard is there" yes || check "now the dashboard is there" no
grep -q 'oxyarea-profile-summary' "${PAGE}" && check "with the account summary inside it" yes || check "with the account summary inside it" no
grep -q 'alice@example.test' "${PAGE}" && check "showing her own email address" yes || check "showing her own email address" no
grep -q '{{' "${PAGE}" && check "and no placeholder left on the page" no || check "and no placeholder left on the page" yes

DISPLAY=$(wp "user get alice --field=display_name")
grep -q "Hello ${DISPLAY}," "${PAGE}" && check "greeted by name" yes || check "greeted by name (expected '${DISPLAY}')" no

echo "== cleaning up =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp eval-file /tmp/testbed-dashboard.php" > /dev/null
wp "post delete ${PAGE_ID} --force" > /dev/null
rm -f "${JAR}" "${PAGE}"
check "dashboard and page removed" yes

echo
echo "== result =="
echo "  passed: ${passed}"
echo "  failed: ${failed}"

[ "${failed}" -eq 0 ]
