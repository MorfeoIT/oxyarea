<?php
/**
 * What an export file contains, and what an import file is allowed to contain.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Tools;

use InvalidArgumentException;

/*
 * Pure. An import file is a file somebody found on their disk, and the code that
 * decides what a site will accept from one belongs where every branch of it can
 * be tested.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * A site's OxyArea configuration, as a document.
 *
 * The point of the class is the direction of trust. Export is easy: the site
 * knows what it has. Import is the interesting half, because the file arrives
 * from outside — an admin's laptop, a colleague's email, an agency's template —
 * and everything in it is a claim rather than a fact.
 *
 * So nothing here reads a key it has not been told about, nothing is coerced
 * into a type it does not fit, and a document that cannot be made sense of is
 * refused whole rather than half-applied. A partial import is worse than a
 * failed one: it leaves a site in a state nobody chose and nobody can describe.
 *
 * What it deliberately does **not** do is decide whether a destination is safe
 * or whether a role exists. Those are questions about a particular site, and
 * they are asked on the way in, by the code that knows the answers.
 */
final class Blueprint {

	/**
	 * The document format, bumped when the shape changes incompatibly.
	 */
	public const FORMAT = 1;

	/**
	 * The settings.
	 *
	 * @var array<string, scalar>
	 */
	private array $settings;

	/**
	 * The redirect rules.
	 *
	 * @var list<array<string, scalar>>
	 */
	private array $redirects;

	/**
	 * The dashboards.
	 *
	 * @var list<array<string, string>>
	 */
	private array $dashboards;

	/**
	 * Build a blueprint.
	 *
	 * @param array<string, scalar>       $settings   The settings.
	 * @param list<array<string, scalar>> $redirects  The redirect rules.
	 * @param list<array<string, string>> $dashboards The dashboards.
	 */
	public function __construct( array $settings = array(), array $redirects = array(), array $dashboards = array() ) {
		$this->settings   = $settings;
		$this->redirects  = $redirects;
		$this->dashboards = $dashboards;
	}

	/**
	 * Read a document.
	 *
	 * @param string $json The file's contents.
	 * @return self
	 *
	 * @throws InvalidArgumentException If it is not a document this version can read.
	 */
	public static function from_json( string $json ): self {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			throw new InvalidArgumentException( 'That file is not an OxyArea export.' );
		}

		if ( ! isset( $decoded['format'] ) || self::FORMAT !== (int) $decoded['format'] ) {
			throw new InvalidArgumentException(
				sprintf(
					'That file is in format %s, and this version of OxyArea reads format %d.',
					isset( $decoded['format'] ) ? (string) $decoded['format'] : 'none',
					self::FORMAT
				)
			);
		}

		return new self(
			self::clean_settings( $decoded['settings'] ?? array() ),
			self::clean_redirects( $decoded['redirects'] ?? array() ),
			self::clean_dashboards( $decoded['dashboards'] ?? array() )
		);
	}

	/**
	 * The document.
	 *
	 * @param string $version When this was written, for a human reading the file.
	 * @param string $stamp   The moment it was written.
	 * @return string
	 */
	public function to_json( string $version = '', string $stamp = '' ): string {
		$document = array(
			'format'     => self::FORMAT,
			'plugin'     => 'oxyarea',
			'version'    => $version,
			'exported'   => $stamp,
			'settings'   => $this->settings,
			'redirects'  => $this->redirects,
			'dashboards' => $this->dashboards,
		);

		// json_encode and not wp_json_encode: this class stays free of WordPress so
		// that the import rules can be tested, and the difference between the two
		// is a depth limit and a filter, neither of which applies to a document
		// this class built itself.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$json = json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * The settings.
	 *
	 * @return array<string, scalar>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * The redirect rules.
	 *
	 * @return list<array<string, scalar>>
	 */
	public function redirects(): array {
		return $this->redirects;
	}

	/**
	 * The dashboards.
	 *
	 * @return list<array<string, string>>
	 */
	public function dashboards(): array {
		return $this->dashboards;
	}

	/**
	 * How much is in here, for telling somebody what they are about to import.
	 *
	 * @return array{settings: int, redirects: int, dashboards: int}
	 */
	public function summary(): array {
		return array(
			'settings'   => count( $this->settings ),
			'redirects'  => count( $this->redirects ),
			'dashboards' => count( $this->dashboards ),
		);
	}

	/**
	 * Keep the settings that are scalars, and nothing else.
	 *
	 * Which keys are meaningful is the settings class's business, and it drops
	 * what it does not recognise. This only refuses shapes that could not be a
	 * setting at all.
	 *
	 * @param mixed $raw What the file said.
	 * @return array<string, scalar>
	 */
	private static function clean_settings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Keep the redirect rules that have the fields a rule needs.
	 *
	 * @param mixed $raw What the file said.
	 * @return list<array<string, scalar>>
	 */
	private static function clean_redirects( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$event       = isset( $entry['event'] ) && is_string( $entry['event'] ) ? $entry['event'] : '';
			$destination = isset( $entry['destination'] ) && is_string( $entry['destination'] ) ? $entry['destination'] : '';

			// A rule without a moment or a destination is not an incomplete rule,
			// it is not a rule.
			if ( '' === $event || '' === trim( $destination ) ) {
				continue;
			}

			$clean[] = array(
				'event'        => $event,
				'subject_type' => isset( $entry['subject_type'] ) && is_string( $entry['subject_type'] ) ? $entry['subject_type'] : '',
				'subject_id'   => isset( $entry['subject_id'] ) && is_scalar( $entry['subject_id'] ) ? (string) $entry['subject_id'] : '',
				'destination'  => $destination,
				'priority'     => isset( $entry['priority'] ) && is_numeric( $entry['priority'] ) ? (int) $entry['priority'] : 10,
				'enabled'      => ! isset( $entry['enabled'] ) || (bool) $entry['enabled'],
			);
		}

		return $clean;
	}

	/**
	 * Keep the dashboards that have a title.
	 *
	 * @param mixed $raw What the file said.
	 * @return list<array<string, string>>
	 */
	private static function clean_dashboards( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$title = isset( $entry['title'] ) && is_string( $entry['title'] ) ? trim( $entry['title'] ) : '';

			if ( '' === $title ) {
				continue;
			}

			$clean[] = array(
				'title'    => $title,
				'audience' => isset( $entry['audience'] ) && is_string( $entry['audience'] ) ? $entry['audience'] : '',
				'content'  => isset( $entry['content'] ) && is_string( $entry['content'] ) ? $entry['content'] : '',
			);
		}

		return $clean;
	}
}
