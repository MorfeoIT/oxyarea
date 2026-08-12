<?php
/**
 * Subjects, as they appear in a form and on a screen.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Access;

/**
 * Converts between a subject and the short string a form field carries.
 *
 * Three admin surfaces need this — the restriction box on the editor, the
 * audience box on a dashboard, and the redirect screen — and until this class
 * existed each had its own private copy of the same two methods. Three copies of
 * a parser is three chances to disagree about what `role:editor` means, and it
 * also meant an add-on could not teach any of them a new kind of subject, which
 * is the thing OxyArea PRO exists to do.
 *
 * The encoding is deliberately plain text rather than an identifier scheme:
 * `authenticated`, `role:editor`, `user:12`. It ends up in checkbox values and
 * in URLs, it is read by people debugging a site, and there is nothing in it
 * that needs hiding.
 *
 * ## Extending it
 *
 * An add-on that introduces a subject type implements three filters:
 *
 * - `oxyarea_subject_decode` turns its own values into subjects;
 * - `oxyarea_subject_encode` turns those subjects back into values;
 * - `oxyarea_subject_label` says what to call them on screen.
 *
 * They must agree with each other. A subject that encodes to a value that does
 * not decode back to it will appear unticked on a screen where it is stored, and
 * will be lost the next time somebody presses Update.
 */
final class SubjectCodec {

	/**
	 * Turn a subject into the string a form field carries.
	 *
	 * Returns the empty string for a subject nothing can render, which callers
	 * treat as "leave it alone": a rule naming a subject type whose add-on has
	 * been deactivated must survive being looked at, not be quietly dropped by
	 * the screen that cannot draw it.
	 *
	 * @param Subject $subject The subject.
	 * @return string
	 */
	public function encode( Subject $subject ): string {
		switch ( $subject->type() ) {
			case Subject::ANONYMOUS:
				return 'anonymous';

			case Subject::AUTHENTICATED:
				return 'authenticated';

			case Subject::ROLE:
				return 'role:' . $subject->id();
		}

		/**
		 * Encode a subject this plugin does not know about.
		 *
		 * @since 0.1.0
		 *
		 * @param string  $value   The empty string, so far.
		 * @param Subject $subject The subject to encode.
		 */
		$value = apply_filters( 'oxyarea_subject_encode', '', $subject );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Turn a posted value back into a subject.
	 *
	 * Returns null for anything unrecognised, and callers drop it. This runs on
	 * user input, so "I do not know what this is" must mean nothing happens
	 * rather than something is guessed.
	 *
	 * @param string $value The value from the form.
	 * @return Subject|null
	 */
	public function decode( string $value ): ?Subject {
		$value = trim( $value );

		if ( 'anonymous' === $value ) {
			return Subject::anonymous();
		}

		if ( 'authenticated' === $value ) {
			return Subject::authenticated();
		}

		if ( 0 === strpos( $value, 'role:' ) ) {
			$role = sanitize_key( substr( $value, 5 ) );

			// A role that does not exist would produce a rule nobody can ever
			// match, which is worse than no rule: it looks like protection.
			return '' !== $role && null !== get_role( $role ) ? Subject::role( $role ) : null;
		}

		/**
		 * Decode a value this plugin does not know about.
		 *
		 * An add-on returning something that is not a Subject is ignored rather
		 * than trusted. This filter runs on data posted from a browser.
		 *
		 * @since 0.1.0
		 *
		 * @param Subject|null $subject Null, so far.
		 * @param string       $value   The value from the form.
		 */
		$subject = apply_filters( 'oxyarea_subject_decode', null, $value );

		return $subject instanceof Subject ? $subject : null;
	}

	/**
	 * What to call a subject on screen.
	 *
	 * @param Subject $subject The subject.
	 * @return string
	 */
	public function label( Subject $subject ): string {
		switch ( $subject->type() ) {
			case Subject::ANONYMOUS:
				return __( 'Signed-out visitors', 'oxyarea' );

			case Subject::AUTHENTICATED:
				return __( 'Anybody signed in', 'oxyarea' );

			case Subject::ROLE:
				$role = get_role( $subject->id() );

				if ( null === $role ) {
					return sprintf(
						/* translators: %s: role slug that no longer exists. */
						__( 'Role that no longer exists (%s)', 'oxyarea' ),
						$subject->id()
					);
				}

				$names = wp_roles()->get_names();

				return isset( $names[ $subject->id() ] )
					? translate_user_role( (string) $names[ $subject->id() ] )
					: $subject->id();
		}

		/**
		 * Name a subject this plugin does not know about.
		 *
		 * @since 0.1.0
		 *
		 * @param string  $label   A generic fallback naming the type and id.
		 * @param Subject $subject The subject to name.
		 */
		$label = apply_filters(
			'oxyarea_subject_label',
			sprintf(
				/* translators: 1: subject type, 2: subject identifier. */
				__( '%1$s %2$s', 'oxyarea' ),
				$subject->type(),
				$subject->id()
			),
			$subject
		);

		return is_string( $label ) && '' !== $label ? $label : $subject->type();
	}

	/**
	 * Let add-ons draw their own controls on one of the audience screens.
	 *
	 * Called once per screen, after this plugin has drawn its own choices. An
	 * add-on prints whatever control suits the subjects it adds — a search field
	 * rather than a list of five thousand customers, for instance — and posts
	 * into the same field this plugin reads.
	 *
	 * @param string       $context One of 'restriction', 'dashboard', 'redirect'.
	 * @param list<string> $chosen  The encoded values currently in force.
	 * @return void
	 */
	public function render_extra_controls( string $context, array $chosen ): void {
		/**
		 * Draw additional audience controls.
		 *
		 * @since 0.1.0
		 *
		 * @param string       $context One of 'restriction', 'dashboard', 'redirect'.
		 * @param list<string> $chosen  The encoded values currently in force.
		 */
		do_action( 'oxyarea_subject_controls', $context, $chosen );
	}

	/**
	 * Let add-ons contribute values from their own fields, at save time.
	 *
	 * The add-on's control may post under its own field name; this is where what
	 * it collected joins what this plugin collected, before any of it is decoded.
	 *
	 * @param list<string> $values  What this plugin found in its own field.
	 * @param string       $context One of 'restriction', 'dashboard', 'redirect'.
	 * @return list<string>
	 */
	public function gather( array $values, string $context ): array {
		/**
		 * Add or replace the audience values being saved.
		 *
		 * @since 0.1.0
		 *
		 * @param list<string> $values  The encoded values collected so far.
		 * @param string       $context One of 'restriction', 'dashboard', 'redirect'.
		 */
		$gathered = apply_filters( 'oxyarea_subject_values', $values, $context );

		if ( ! is_array( $gathered ) ) {
			return $values;
		}

		$clean = array();

		foreach ( $gathered as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$clean[] = trim( $value );
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
