<?php
/**
 * Which capabilities the role editor offers, and which it warns about.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Roles;

/**
 * The capabilities a role editor may reasonably show, grouped.
 *
 * Plain arrays and no translation calls, so the list is a fact the tests can
 * check rather than a rendering detail. The labels belong to the screen.
 *
 * The important distinction here is not "core versus plugin" but **whether
 * granting it hands over the site**. install_plugins is, in practice,
 * arbitrary code execution. edit_users is the ability to make yourself an
 * administrator. A role editor that lists those beside "upload files", in the
 * same plain checkbox, is how a site ends up with a customer role that can
 * install a plugin.
 */
final class CapabilityCatalogue {

	/**
	 * Reading.
	 */
	public const GROUP_READING = 'reading';

	/**
	 * Posts.
	 */
	public const GROUP_POSTS = 'posts';

	/**
	 * Pages.
	 */
	public const GROUP_PAGES = 'pages';

	/**
	 * Media, comments and taxonomy.
	 */
	public const GROUP_SITE = 'site';

	/**
	 * OxyArea's own administrative capabilities.
	 */
	public const GROUP_OXYAREA = 'oxyarea';

	/**
	 * The ones that hand over the site.
	 */
	public const GROUP_DANGEROUS = 'dangerous';

	/**
	 * Every capability the editor offers, grouped, in display order.
	 *
	 * @return array<string, list<string>>
	 */
	public static function groups(): array {
		return array(
			self::GROUP_READING   => array(
				'read',
				'read_private_posts',
				'read_private_pages',
			),
			self::GROUP_POSTS     => array(
				'edit_posts',
				'edit_others_posts',
				'edit_published_posts',
				'edit_private_posts',
				'publish_posts',
				'delete_posts',
				'delete_others_posts',
				'delete_published_posts',
				'delete_private_posts',
			),
			self::GROUP_PAGES     => array(
				'edit_pages',
				'edit_others_pages',
				'edit_published_pages',
				'edit_private_pages',
				'publish_pages',
				'delete_pages',
				'delete_others_pages',
				'delete_published_pages',
				'delete_private_pages',
			),
			self::GROUP_SITE      => array(
				'upload_files',
				'moderate_comments',
				'manage_categories',
			),
			self::GROUP_OXYAREA   => Capabilities::all(),
			self::GROUP_DANGEROUS => self::dangerous(),
		);
	}

	/**
	 * The capabilities that amount to handing over the site.
	 *
	 * The editor still offers them — refusing outright would only send people to
	 * a worse tool — but they are grouped, labelled and, crucially, cannot be
	 * granted by somebody who does not already hold them.
	 *
	 * @return list<string>
	 */
	public static function dangerous(): array {
		return array(
			'manage_options',
			'edit_dashboard',
			'list_users',
			'add_users',
			'create_users',
			'edit_users',
			'delete_users',
			'promote_users',
			'remove_users',
			'install_plugins',
			'activate_plugins',
			'edit_plugins',
			'update_plugins',
			'delete_plugins',
			'install_themes',
			'switch_themes',
			'edit_themes',
			'update_themes',
			'delete_themes',
			'edit_theme_options',
			'edit_files',
			'unfiltered_html',
			'unfiltered_upload',
			'import',
			'export',
			'update_core',
			'manage_links',
		);
	}

	/**
	 * Whether granting this capability hands over the site.
	 *
	 * Unknown capabilities count as dangerous. A capability the catalogue has
	 * never heard of is one nobody has assessed, and the safe reading of an
	 * unassessed grant is the cautious one.
	 *
	 * @param string $capability The capability.
	 * @return bool
	 */
	public static function is_dangerous( string $capability ): bool {
		if ( in_array( $capability, self::dangerous(), true ) ) {
			return true;
		}

		return ! in_array( $capability, self::offered(), true );
	}

	/**
	 * Every capability the editor offers, flattened.
	 *
	 * @return list<string>
	 */
	public static function offered(): array {
		$flat = array();

		foreach ( self::groups() as $capabilities ) {
			foreach ( $capabilities as $capability ) {
				$flat[] = $capability;
			}
		}

		return array_values( array_unique( $flat ) );
	}
}
