#!/usr/bin/env bash
#
# Build a WordPress test environment for the integration and security suites.
#
# The two suites need three things the unit suite does not: a copy of WordPress
# core, a database of its own, and a wp-tests-config.php pointing at both. This
# script produces all three, and is the same script CI runs and a person runs,
# so a green pipeline means something reproducible rather than something only
# GitHub knows how to do.
#
# THE DATABASE IS DESTROYED. The WordPress test library drops and recreates
# every table on every run. Never point this at a database that holds anything.
#
# Usage:
#   scripts/wordpress-test-env.sh [target-directory]
#
# Environment:
#   WP_DB_NAME      database name        (default oxyarea_tests)
#   WP_DB_USER      database user        (default root)
#   WP_DB_PASSWORD  database password    (default empty)
#   WP_DB_HOST      database host        (default 127.0.0.1)
#   WP_VERSION      WordPress to fetch   (default latest)
#
# It prints the path of the config file it wrote. Pass that path to PHPUnit as
# WP_PHPUNIT__TESTS_CONFIG.

set -euo pipefail

target="${1:-${RUNNER_TEMP:-/tmp}/oxyarea-wp}"

db_name="${WP_DB_NAME:-oxyarea_tests}"
db_user="${WP_DB_USER:-root}"
db_password="${WP_DB_PASSWORD:-}"
db_host="${WP_DB_HOST:-127.0.0.1}"
wp_version="${WP_VERSION:-latest}"

core="${target}/wordpress"
config="${target}/wp-tests-config.php"

mkdir -p "${target}"

if [ ! -f "${core}/wp-load.php" ]; then
	# latest.tar.gz rather than the version-check API: parsing that API wrongly
	# is how this once installed WordPress 4.7 and spent an afternoon proving
	# the plugin needs PHP 8.
	if [ "${wp_version}" = "latest" ]; then
		archive="https://wordpress.org/latest.tar.gz"
	else
		archive="https://wordpress.org/wordpress-${wp_version}.tar.gz"
	fi

	echo "Fetching ${archive}"

	mkdir -p "${core}"
	curl -fsSL "${archive}" | tar -xz --strip-components=1 -C "${core}"
fi

cat >"${config}" <<PHP
<?php
define( 'ABSPATH', '${core}/' );

define( 'DB_NAME', '${db_name}' );
define( 'DB_USER', '${db_user}' );
define( 'DB_PASSWORD', '${db_password}' );
define( 'DB_HOST', '${db_host}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'OxyArea integration tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );

define( 'AUTH_KEY', 'tests-only-not-a-secret-1' );
define( 'SECURE_AUTH_KEY', 'tests-only-not-a-secret-2' );
define( 'LOGGED_IN_KEY', 'tests-only-not-a-secret-3' );
define( 'NONCE_KEY', 'tests-only-not-a-secret-4' );
define( 'AUTH_SALT', 'tests-only-not-a-secret-5' );
define( 'SECURE_AUTH_SALT', 'tests-only-not-a-secret-6' );
define( 'LOGGED_IN_SALT', 'tests-only-not-a-secret-7' );
define( 'NONCE_SALT', 'tests-only-not-a-secret-8' );
PHP

# The config is world-readable and holds a database password, so on a shared
# machine it should not be.
chmod 600 "${config}"

echo "${config}"
