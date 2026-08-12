#!/usr/bin/env bash
#
# Signs in over HTTP and checks where the browser is actually sent.
#
# The engine is unit tested and exercised inside WordPress. Neither of those
# would notice if the rule were decided correctly and then dropped on the floor
# between the decision and the Location header.
#
# Run as root on the test bed server.

set -euo pipefail

USERNAME=webtest
ROOT=/home/${USERNAME}/web/test.44123.it/public_html/oxyarea
SITE=https://test.44123.it/oxyarea
CREDS=/home/${USERNAME}/oxyarea-testbed-credentials.txt
BASIC=oxysoft:LA-COPPIA-STA-IN-UN-FILE-PROTETTO
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

echo "== two pages: one with the form, one to land on =="

FORM_ID=$(wp "post list --post_type=page --name=oxyarea-redirect-form --field=ID --format=ids")
if [ -z "${FORM_ID}" ]; then
  FORM_ID=$(wp "post create --post_type=page --post_status=publish --post_title='Sign in' --post_name=oxyarea-redirect-form --porcelain --post_content='<!-- wp:oxyarea/login /-->'")
fi

LAND_ID=$(wp "post list --post_type=page --name=oxyarea-redirect-landing --field=ID --format=ids")
if [ -z "${LAND_ID}" ]; then
  LAND_ID=$(wp "post create --post_type=page --post_status=publish --post_title='Customer area' --post_name=oxyarea-redirect-landing --porcelain --post_content='You are in the customer area.'")
fi

FORM_URL="${SITE}/oxyarea-redirect-form/"
LANDING_PATH="/oxyarea/oxyarea-redirect-landing/"

echo "  form:    ${FORM_URL}"
echo "  landing: ${LANDING_PATH}"

echo "== a rule sending subscribers to the landing page =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && OXYAREA_ROLE=subscriber OXYAREA_DEST='${LANDING_PATH}' wp eval-file /tmp/testbed-redirect-rule.php" > /dev/null

check "the rule was stored" yes

echo "== signing in =="

ALICE_PASS=$(grep '^alice / ' "${CREDS}" | sed 's|^alice / ||')

curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${FORM_URL}"
NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')

LOCATION=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -D - \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ALICE_PASS}" \
  "${FORM_URL}" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: //')

echo "  landed on: ${LOCATION}"

case "${LOCATION}" in
  *"${LANDING_PATH}") check "the rule decided where the browser went" yes ;;
  *)                  check "the rule decided where the browser went" no ;;
esac

echo "== and the landing page is really there =="

CODE=$(curl -sS -u "${BASIC}" -b "${JAR}" -o "${PAGE}" -w '%{http_code}' "${SITE}/oxyarea-redirect-landing/")
[ "${CODE}" = "200" ] && check "it loads" yes || check "it loads (got ${CODE})" no
grep -q 'customer area' "${PAGE}" && check "with its content" yes || check "with its content" no

echo "== an explicit destination still wins over the rule =="

# Somebody who followed a "sign in to read this" link keeps their place. The
# rules decide where people go by default, not where they are allowed to go.
rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${FORM_URL}"
NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')

LOCATION=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -D - \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ALICE_PASS}" \
  --data-urlencode "redirect_to=/oxyarea/oxyarea-redirect-form/" \
  "${FORM_URL}" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: //')

echo "  landed on: ${LOCATION}"
case "${LOCATION}" in
  *oxyarea-redirect-form*) check "the requested page was honoured" yes ;;
  *)                       check "the requested page was honoured" no ;;
esac

echo "== cleaning up =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp eval-file /tmp/testbed-redirect-rule.php" > /dev/null

wp "post delete ${FORM_ID} --force" > /dev/null
wp "post delete ${LAND_ID} --force" > /dev/null
rm -f "${JAR}" "${PAGE}"

check "rules and pages removed" yes

echo
echo "== result =="
echo "  passed: ${passed}"
echo "  failed: ${failed}"

[ "${failed}" -eq 0 ]
