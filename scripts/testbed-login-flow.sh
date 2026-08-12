#!/usr/bin/env bash
#
# Signs in over HTTP, the way a person would.
#
# Everything else proves the pieces work. This proves the flow works: a real
# page, a real form, a real POST with a real nonce, a real session cookie. It is
# the only check here that would notice if the form rendered perfectly and the
# submission went nowhere.
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

echo "== a page with the sign-in form =="

SLUG=oxyarea-login-flow
PAGE_ID=$(wp "post list --post_type=page --name=${SLUG} --field=ID --format=ids")

if [ -z "${PAGE_ID}" ]; then
  PAGE_ID=$(wp "post create --post_type=page --post_status=publish --post_title='Private area' --post_name=${SLUG} --porcelain --post_content='<!-- wp:oxyarea/login /--><!-- wp:oxyarea/lost-password /-->'")
  echo "  creata pagina ${PAGE_ID}"
else
  echo "  pagina ${PAGE_ID} gia' presente"
fi

URL="${SITE}/${SLUG}/"

echo "== the page renders the form =="

curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" -w '' "${URL}"

grep -q '<form' "${PAGE}" && check "the page contains a form" yes || check "the page contains a form" no
grep -q 'name="oxyarea_action" value="login"' "${PAGE}" && check "it is OxyArea's sign-in form" yes || check "it is OxyArea's sign-in form" no
grep -q 'name="user_password"' "${PAGE}" && check "with a password field" yes || check "with a password field" no
grep -q 'oxyarea-forms-css\|forms.css' "${PAGE}" && check "the stylesheet is enqueued" yes || check "the stylesheet is enqueued" no
grep -q 'oxyarea_action" value="lost-password"' "${PAGE}" && check "and the forgotten-password form beside it" yes || check "and the forgotten-password form beside it" no

NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')
[ -n "${NONCE}" ] && check "the form carries a nonce" yes || check "the form carries a nonce" no

# A button with no words in it renders perfectly and passes every check that
# asks whether a button is there. This asks what it says.
LABEL=$(grep -o '<button type="submit"[^>]*>[^<]*' "${PAGE}" | head -1 | sed 's/.*>//' | tr -d ' 	')
[ -n "${LABEL}" ] && check "and the submit button has words on it (${LABEL})" yes || check "and the submit button has words on it" no

echo "== signing in with the wrong password =="

ALICE_PASS=$(grep '^alice / ' "${CREDS}" | sed 's|^alice / ||')

WRONG=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" -w '%{http_code}' \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=not-the-password" \
  "${URL}")

[ "${WRONG}" = "200" ] && check "stays on the page rather than redirecting" yes || check "stays on the page rather than redirecting (got ${WRONG})" no
grep -q 'oxyarea-errors' "${PAGE}" && check "and shows an error" yes || check "and shows an error" no
grep -qi 'unknown\|not registered' "${PAGE}" && check "without naming the account" no || check "without naming the account" yes

echo "== signing in properly =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')

CODE=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -w '%{http_code}' \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ALICE_PASS}" \
  "${URL}")

[ "${CODE}" = "302" ] && check "it redirects" yes || check "it redirects (got ${CODE})" no
grep -q 'wordpress_logged_in' "${JAR}" && check "and leaves a session cookie" yes || check "and leaves a session cookie" no

echo "== the session works =="

curl -sS -u "${BASIC}" -b "${JAR}" -o "${PAGE}" "${URL}"

grep -q 'You are signed in as' "${PAGE}" && check "the form now says who is signed in" yes || check "the form now says who is signed in" no
grep -q 'name="user_password"' "${PAGE}" && check "and no longer asks for a password" no || check "and no longer asks for a password" yes

echo "== an off-site destination is not followed =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(grep -o 'name="_wpnonce" value="[^"]*"' "${PAGE}" | head -1 | sed 's/.*value="//; s/"//')

LOCATION=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -D - \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ALICE_PASS}" \
  --data-urlencode "redirect_to=https://evil.example/taken" \
  "${URL}" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: //')

echo "  landed on: ${LOCATION}"
case "${LOCATION}" in
  *evil.example*) check "the phishing destination was refused" no ;;
  *)              check "the phishing destination was refused" yes ;;
esac

echo "== cleaning up =="

wp "post delete ${PAGE_ID} --force" >/dev/null
check "the test page is gone" yes
rm -f "${JAR}" "${PAGE}"

echo
echo "== result =="
echo "  passed: ${passed}"
echo "  failed: ${failed}"

[ "${failed}" -eq 0 ]
