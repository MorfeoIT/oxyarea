#!/usr/bin/env bash
#
# The password reset, from "I have forgotten it" to signing in with the new one.
#
# The one step of the definition of done that was unproven, and the step
# somebody uses when they are already annoyed. It needs the mail-capture
# mu-plugin, because the test users have @example.test addresses that by design
# resolve to nothing.
#
# Run as root on the test bed server.

set -euo pipefail

USERNAME=webtest
ROOT=/home/${USERNAME}/web/test.44123.it/public_html/oxyarea
SITE=https://test.44123.it/oxyarea
CREDS=/home/${USERNAME}/oxyarea-testbed-credentials.txt
MAILLOG=${ROOT}/wp-content/mail.log
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

# The page carries three forms, so a nonce cannot be picked by position: with a
# valid key the reset form is the third, without one it is not there at all.
# Each form emits exactly one nonce and one action, in that order, so the two
# lists line up and the nonce can be matched to the form it belongs to.
nonce_for() {
  local file=$1 want=$2 i=1
  local -a nonces actions
  mapfile -t nonces < <(grep -o 'name="_wpnonce" value="[^"]*"' "${file}" | sed 's/.*value="//; s/"//')
  mapfile -t actions < <(grep -o 'name="oxyarea_action" value="[^"]*"' "${file}" | sed 's/.*value="//; s/"//')
  for a in "${actions[@]}"; do
    if [ "${a}" = "${want}" ]; then
      echo "${nonces[$((i - 1))]}"
      return 0
    fi
    i=$((i + 1))
  done
  echo ""
}

password_for() { sed -n "s|^$1 *[/] *||p" "${CREDS}" | head -1; }

echo "== the sign-in page, and the setting that brings the link back to it =="

SETUP=$(sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp eval-file /tmp/testbed-reset-setup.php")
echo "  ${SETUP}"
URL="${SITE}/oxyarea-reset-page/"

ORIGINAL_PASS=$(password_for alice)
[ -n "${ORIGINAL_PASS}" ] || { echo "STOP: nessuna password per alice"; exit 1; }

[ -f "${ROOT}/wp-content/mu-plugins/oxyarea-mail-capture.php" ] \
  && check "the mail capture plugin is installed" yes \
  || { check "the mail capture plugin is installed" no; exit 1; }

: > "${MAILLOG}"
chown "${USERNAME}:${USERNAME}" "${MAILLOG}"

echo "== asking for a reset, for somebody who does not exist =="

curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(nonce_for "${PAGE}" lost-password)

curl -sSL -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" \
  --data-urlencode "oxyarea_action=lost-password" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=nobody-at-all" \
  "${URL}"

grep -q "an email is on its way" "${PAGE}" \
  && check "the answer is the reassuring one" yes \
  || check "the answer is the reassuring one" no

[ ! -s "${MAILLOG}" ] \
  && check "and no email was sent, which the visitor cannot tell" yes \
  || check "and no email was sent, which the visitor cannot tell" no

SAID_FOR_STRANGER=$(grep -o "an email is on its way[^<]*" "${PAGE}" | head -1)

echo "== asking for a reset, for Alice =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(nonce_for "${PAGE}" lost-password)

curl -sSL -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" \
  --data-urlencode "oxyarea_action=lost-password" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  "${URL}"

SAID_FOR_ALICE=$(grep -o "an email is on its way[^<]*" "${PAGE}" | head -1)

[ "${SAID_FOR_STRANGER}" = "${SAID_FOR_ALICE}" ] \
  && check "the answer is word for word the same as for the stranger" yes \
  || check "the answer is word for word the same as for the stranger" no

grep -q "alice@example.test" "${MAILLOG}" \
  && check "this time an email was written" yes \
  || check "this time an email was written" no

RESET_URL=$(grep -o "https\?://[^ ]*oxyarea-key=[^ ]*" "${MAILLOG}" | tail -1 | tr -d '\r')
echo "  link: ${RESET_URL}"

[ -n "${RESET_URL}" ] \
  && check "it carries a reset link" yes \
  || { check "it carries a reset link" no; exit 1; }

case "${RESET_URL}" in
  *oxyarea-reset-page*) check "which points at the site's own page, not wp-login.php" yes ;;
  *)                    check "which points at the site's own page, not wp-login.php" no ;;
esac

echo "== following the link =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${RESET_URL}"

grep -q 'name="pass1"' "${PAGE}" \
  && check "the form to set a new password is there" yes \
  || check "the form to set a new password is there" no

grep -q "cannot be used any more" "${PAGE}" \
  && check "and it is not the expired message" no \
  || check "and it is not the expired message" yes

KEY=$(echo "${RESET_URL}" | sed -n 's/.*oxyarea-key=\([^&]*\).*/\1/p')
LOGIN=$(echo "${RESET_URL}" | sed -n 's/.*oxyarea-login=\([^&]*\).*/\1/p')
NONCE=$(nonce_for "${PAGE}" reset-password)

echo "== a tampered key is refused =="

curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" \
  --data-urlencode "oxyarea_action=reset-password" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "key=${KEY}xyz" \
  --data-urlencode "login=${LOGIN}" \
  --data-urlencode "pass1=NotTheOne-9134" \
  --data-urlencode "pass2=NotTheOne-9134" \
  "${URL}"

grep -q "cannot be used any more" "${PAGE}" \
  && check "changing one character invalidates the link" yes \
  || check "changing one character invalidates the link" no

echo "== two passwords that do not match are refused =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${RESET_URL}"
NONCE=$(nonce_for "${PAGE}" reset-password)

curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" \
  --data-urlencode "oxyarea_action=reset-password" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "key=${KEY}" \
  --data-urlencode "login=${LOGIN}" \
  --data-urlencode "pass1=Mismatched-1111" \
  --data-urlencode "pass2=Mismatched-2222" \
  "${URL}"

grep -q "not the same" "${PAGE}" \
  && check "and it says so" yes \
  || check "and it says so" no

echo "== setting the new password =="

NEW_PASS="Rimessa-A-Posto-7781"

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${RESET_URL}"
NONCE=$(nonce_for "${PAGE}" reset-password)

CODE=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -w '%{http_code}' \
  --data-urlencode "oxyarea_action=reset-password" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "key=${KEY}" \
  --data-urlencode "login=${LOGIN}" \
  --data-urlencode "pass1=${NEW_PASS}" \
  --data-urlencode "pass2=${NEW_PASS}" \
  "${URL}")

[ "${CODE}" = "302" ] && check "it redirects" yes || check "it redirects (got ${CODE})" no

curl -sS -u "${BASIC}" -b "${JAR}" -o "${PAGE}" "${URL}?oxyarea-notice=password-changed"
grep -q "password has been changed" "${PAGE}" \
  && check "and says the password has been changed" yes \
  || check "and says the password has been changed" no

echo "== signing in with it =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(nonce_for "${PAGE}" login)

CODE=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -w '%{http_code}' \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${NEW_PASS}" \
  "${URL}")

[ "${CODE}" = "302" ] && check "the new password works" yes || check "the new password works (got ${CODE})" no
grep -q 'wordpress_logged_in' "${JAR}" && check "and leaves a session" yes || check "and leaves a session" no

echo "== while she is signed in, her own details ==

  (the other step of the definition of done that had never been exercised)"

curl -sSL -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}" "${URL}"

grep -q 'name="user_email"' "${PAGE}"   && check "the profile form is there for her" yes   || check "the profile form is there for her" no

grep -q "alice@example.test" "${PAGE}"   && check "showing her own address" yes   || check "showing her own address" no

NONCE=$(nonce_for "${PAGE}" profile)

curl -sSL -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}"   --data-urlencode "oxyarea_action=profile"   --data-urlencode "_wpnonce=${NONCE}"   --data-urlencode "first_name=Alice"   --data-urlencode "last_name=Rossi"   --data-urlencode "display_name=Alice Rossi"   --data-urlencode "user_email=alice@example.test"   "${URL}"

grep -q "details have been saved" "${PAGE}"   && check "changing her name needs no password" yes   || check "changing her name needs no password" no

NONCE=$(nonce_for "${PAGE}" profile)

curl -sSL -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "${PAGE}"   --data-urlencode "oxyarea_action=profile"   --data-urlencode "_wpnonce=${NONCE}"   --data-urlencode "display_name=Alice Rossi"   --data-urlencode "user_email=somebody-else@example.test"   "${URL}"

grep -q "Enter your current password" "${PAGE}"   && check "changing her address does" yes   || check "changing her address does" no

CURRENT_EMAIL=$(wp "user get alice --field=user_email")
[ "${CURRENT_EMAIL}" = "alice@example.test" ]   && check "and the address was not changed" yes   || check "and the address was not changed (now ${CURRENT_EMAIL})" no

echo "== the link cannot be used twice =="

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${RESET_URL}"

grep -q "cannot be used any more" "${PAGE}" \
  && check "a spent link is refused" yes \
  || check "a spent link is refused" no

echo "== putting Alice back =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && OXYAREA_RESTORE='${ORIGINAL_PASS}' wp eval-file /tmp/testbed-reset-setup.php" > /dev/null

rm -f "${JAR}"
curl -sS -u "${BASIC}" -c "${JAR}" -o "${PAGE}" "${URL}"
NONCE=$(nonce_for "${PAGE}" login)

CODE=$(curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null -w '%{http_code}' \
  --data-urlencode "oxyarea_action=login" \
  --data-urlencode "_wpnonce=${NONCE}" \
  --data-urlencode "user_login=alice" \
  --data-urlencode "user_password=${ORIGINAL_PASS}" \
  "${URL}")

[ "${CODE}" = "302" ] && check "her original password works again" yes || check "her original password works again (got ${CODE})" no

wp "post delete $(wp "post list --post_type=page --name=oxyarea-reset-page --field=ID --format=ids") --force" > /dev/null
: > "${MAILLOG}"
rm -f "${JAR}" "${PAGE}"

echo
echo "== result =="
echo "  passed: ${passed}"
echo "  failed: ${failed}"

[ "${failed}" -eq 0 ]
