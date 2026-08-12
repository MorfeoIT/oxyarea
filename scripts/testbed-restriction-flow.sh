#!/usr/bin/env bash
#
# The release blockers, over HTTP.
#
# The specification refuses a release if private content leaks through search,
# feeds, sitemaps or REST, or if one customer can reach another's page. Those are
# five separate ways of asking WordPress a question, and a plugin that closes
# four of them has a hole in the fifth. This asks all five, as a stranger and
# then as each of two customers.
#
# Run as root on the test bed server.

set -euo pipefail

USERNAME=webtest
ROOT=/home/${USERNAME}/web/test.44123.it/public_html/oxyarea
SITE=https://test.44123.it/oxyarea
CREDS=/home/${USERNAME}/oxyarea-testbed-credentials.txt
BASIC=oxysoft:LA-COPPIA-STA-IN-UN-FILE-PROTETTO
JAR=$(mktemp)
OUT=$(mktemp)

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

absent() { grep -q "$2" "$OUT" && check "$1" no || check "$1" yes; }
present() { grep -q "$2" "$OUT" && check "$1" yes || check "$1" no; }

wp() { sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp $*"; }

fetch() { curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o "$OUT" -w '%{http_code}' "$1"; }

sign_in() {
  rm -f "${JAR}"
  local page nonce
  page=$(mktemp)
  curl -sS -u "${BASIC}" -c "${JAR}" -o "${page}" "${SITE}/oxyarea-restriction-form/"
  nonce=$(grep -o 'name="_wpnonce" value="[^"]*"' "${page}" | head -1 | sed 's/.*value="//; s/"//')
  curl -sS -u "${BASIC}" -b "${JAR}" -c "${JAR}" -o /dev/null \
    --data-urlencode "oxyarea_action=login" \
    --data-urlencode "_wpnonce=${nonce}" \
    --data-urlencode "user_login=$1" \
    --data-urlencode "user_password=$2" \
    "${SITE}/oxyarea-restriction-form/"
  rm -f "${page}"
}

echo "== a public post, a private one, and a role that may read it =="

SETUP=$(sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && OXYAREA_SETUP=1 wp eval-file /tmp/testbed-restriction.php")
echo "  ${SETUP}"
PRIVATE_ID=$(echo "${SETUP}" | sed 's/.*private=\([0-9]*\).*/\1/')

FORM_ID=$(wp "post list --post_type=page --name=oxyarea-restriction-form --field=ID --format=ids")
if [ -z "${FORM_ID}" ]; then
  FORM_ID=$(wp "post create --post_type=page --post_status=publish --post_title='Sign in' --post_name=oxyarea-restriction-form --porcelain --post_content='<!-- wp:oxyarea/login /-->'")
fi

ALICE_PASS=$(grep '^alice / ' "${CREDS}" | sed 's|^alice / ||')
BOB_PASS=$(grep '^bob / ' "${CREDS}" | sed 's|^bob / ||')

echo
echo "== as a stranger =="

rm -f "${JAR}"

fetch "${SITE}/oxyarea-public-post/" > /dev/null
present "the public post is readable" "PUBLICMARKER"

fetch "${SITE}/oxyarea-private-post/" > /dev/null
absent "the private post is not" "SECRETMARKER"

fetch "${SITE}/?s=marker" > /dev/null
present "search finds the public one" "A public announcement"
absent "and does not mention the private one" "quarterly contract"

fetch "${SITE}/feed/" > /dev/null
present "the feed carries the public one" "A public announcement"
absent "and not the private one" "quarterly contract"

CODE=$(fetch "${SITE}/wp-json/wp/v2/posts/${PRIVATE_ID}")
[ "${CODE}" = "404" ] && check "REST answers 404 for the private one" yes || check "REST answers 404 for the private one (got ${CODE})" no
absent "and gives nothing away in the body" "SECRETMARKER"

fetch "${SITE}/wp-json/wp/v2/posts" > /dev/null
absent "the REST collection leaves it out" "quarterly contract"

fetch "${SITE}/wp-sitemap-posts-post-1.xml" > /dev/null
present "the sitemap lists the public one" "oxyarea-public-post"
absent "and not the private one" "oxyarea-private-post"

echo
echo "== as Bob, a customer who may not read it =="

sign_in bob "${BOB_PASS}"

fetch "${SITE}/oxyarea-private-post/" > /dev/null
absent "Bob is refused the page" "SECRETMARKER"

fetch "${SITE}/?s=marker" > /dev/null
absent "and it is not in his search results" "quarterly contract"

CODE=$(fetch "${SITE}/wp-json/wp/v2/posts/${PRIVATE_ID}")
[ "${CODE}" = "404" ] && check "and REST tells him nothing" yes || check "and REST tells him nothing (got ${CODE})" no

echo
echo "== as Alice, who may =="

sign_in alice "${ALICE_PASS}"

fetch "${SITE}/oxyarea-private-post/" > /dev/null
present "Alice reads the page" "SECRETMARKER"

fetch "${SITE}/?s=marker" > /dev/null
present "it appears in her search results" "quarterly contract"

CODE=$(fetch "${SITE}/wp-json/wp/v2/posts/${PRIVATE_ID}")
[ "${CODE}" = "200" ] && check "and REST gives it to her" yes || check "and REST gives it to her (got ${CODE})" no

echo
echo "== cleaning up =="

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp eval-file /tmp/testbed-restriction.php" > /dev/null
wp "post delete ${FORM_ID} --force" > /dev/null
rm -f "${JAR}" "${OUT}"
check "posts, role and page removed" yes

echo
echo "== result =="
echo "  passed: ${passed}"
echo "  failed: ${failed}"

[ "${failed}" -eq 0 ]
