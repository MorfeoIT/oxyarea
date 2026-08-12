#!/usr/bin/env bash
#
# Installs the OxyArea package from /tmp/oxyarea.tar into the test bed,
# replacing whatever was there. The archive comes from `git archive`, so it is
# exactly the distributable plugin: no tests, no docs, no Composer files.

set -euo pipefail

USERNAME=webtest
ROOT=/home/${USERNAME}/web/test.44123.it/public_html/oxyarea
PLUGINS=${ROOT}/wp-content/plugins
ARCHIVE=/tmp/oxyarea.tar

if [ ! -f "${ARCHIVE}" ]; then
  echo "STOP: ${ARCHIVE} non c'e'."
  exit 1
fi

if [ ! -d "${ROOT}/wp-admin" ]; then
  echo "STOP: ${ROOT} non sembra un'installazione WordPress."
  exit 1
fi

rm -rf "${PLUGINS}/oxyarea"
tar -xf "${ARCHIVE}" -C "${PLUGINS}"
chown -R "${USERNAME}:${USERNAME}" "${PLUGINS}/oxyarea"

echo "file installati: $(find "${PLUGINS}/oxyarea" -type f | wc -l)"

sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp plugin activate oxyarea"

echo "== stato =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp plugin list --fields=name,status,version"

echo "== tabelle di oxyarea =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp db query 'SHOW TABLES LIKE \"%oxyarea%\"'" || true

echo "== opzioni di oxyarea =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp option list --search='oxyarea*' --format=table" || true

echo "== capability sull'amministratore =="
sudo -u "${USERNAME}" -H bash -c "cd '${ROOT}' && wp cap list administrator | grep oxyarea" || echo "nessuna!"

echo "== errori PHP registrati =="
if [ -f "${ROOT}/wp-content/debug.log" ]; then
  tail -30 "${ROOT}/wp-content/debug.log"
else
  echo "nessun debug.log: nessun avviso o errore PHP finora"
fi
