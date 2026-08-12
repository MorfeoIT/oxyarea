<?php
/**
 * Plugin Name: OxyArea test bed — capture outgoing mail
 * Description: Writes every outgoing message to wp-content/mail.log instead of sending it. Belongs on the test bed and nowhere else.
 * Version: 1.0.0
 *
 * The test users have @example.test addresses, which by design resolve to
 * nothing: no SMTP configuration could ever deliver to them. Capturing is
 * therefore not a way around testing the password reset flow, it is the only
 * way to test it — and what needs proving is the link WordPress puts in the
 * message and what happens when somebody follows it, neither of which is a
 * question about a mail server.
 *
 * Install as wp-content/mu-plugins/oxyarea-mail-capture.php on the test bed.
 *
 * @package OxyArea
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	/**
	 * Write the message down and tell WordPress it was sent.
	 *
	 * @param mixed                $short_circuit Null unless somebody else has already handled it.
	 * @param array<string, mixed> $attributes    The message.
	 * @return bool
	 */
	static function ( $short_circuit, $attributes ) {
		unset( $short_circuit );

		$to      = isset( $attributes['to'] ) ? $attributes['to'] : '';
		$to      = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$subject = isset( $attributes['subject'] ) ? (string) $attributes['subject'] : '';
		$message = isset( $attributes['message'] ) ? (string) $attributes['message'] : '';

		$record = sprintf(
			"=== MAIL %s\nTo: %s\nSubject: %s\n\n%s\n=== END\n",
			gmdate( 'c' ),
			$to,
			$subject,
			$message
		);

		file_put_contents( WP_CONTENT_DIR . '/mail.log', $record, FILE_APPEND | LOCK_EX );

		// Short-circuit: wp_mail returns true and nothing is handed to sendmail.
		return true;
	},
	10,
	2
);
