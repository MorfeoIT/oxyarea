<?php
/**
 * Every name, URL and label the product wears.
 *
 * @package OxyArea
 */

declare(strict_types=1);

namespace OxyArea\Infrastructure;

/**
 * The single place where OxyArea is called OxyArea.
 *
 * Two reasons this exists rather than the strings being written where they are
 * shown. The naming clearance is green but not locked until WordPress.org
 * approves the slug, so a pre-launch rename has to stay cheap. And the Agency
 * tier sells the ability to take the vendor's name off screens a client sees,
 * which is a filter here and nothing anywhere else.
 *
 * Note what is *not* filterable: the slug, the text domain, the REST namespace
 * and the table prefix. Those are identifiers, not branding, and letting a site
 * change them would break its own data.
 */
final class Brand {

	/**
	 * The plugin slug, directory name and option/hook prefix.
	 */
	public const SLUG = 'oxyarea';

	/**
	 * The translation text domain.
	 */
	public const TEXT_DOMAIN = 'oxyarea';

	/**
	 * The REST route namespace.
	 */
	public const REST_NAMESPACE = 'oxyarea/v1';

	/**
	 * The top-level admin menu slug.
	 */
	public const MENU_SLUG = 'oxyarea';

	/**
	 * Custom table names are prefixed with this, after the WordPress prefix.
	 */
	public const TABLE_PREFIX = 'oxyarea_';

	/**
	 * The short product name, as shown in admin screens.
	 *
	 * @return string
	 */
	public static function name(): string {
		/**
		 * Filters the short product name shown in the admin.
		 *
		 * @since 0.1.0
		 *
		 * @param string $name The product name.
		 */
		return (string) apply_filters( 'oxyarea_brand_name', 'OxyArea' );
	}

	/**
	 * The full product name, as registered with WordPress.org.
	 *
	 * @return string
	 */
	public static function display_name(): string {
		/**
		 * Filters the full product name.
		 *
		 * @since 0.1.0
		 *
		 * @param string $display_name The full product name.
		 */
		return (string) apply_filters(
			'oxyarea_brand_display_name',
			__( 'OxyArea – Private Client Area & User Portal', 'oxyarea' )
		);
	}

	/**
	 * The vendor.
	 *
	 * @return string
	 */
	public static function vendor(): string {
		/**
		 * Filters the vendor name.
		 *
		 * @since 0.1.0
		 *
		 * @param string $vendor The vendor name.
		 */
		return (string) apply_filters( 'oxyarea_brand_vendor', 'Oxysoft Soluzioni Informatiche' );
	}

	/**
	 * A URL on the product family site.
	 *
	 * OxyArea has no domain of its own and is not to acquire one: it is published,
	 * documented, licensed and sold under oxywp.com.
	 *
	 * @param string $path Path relative to the product family site, without a leading slash.
	 * @return string
	 */
	public static function url( string $path = '' ): string {
		$base = 'https://oxywp.com/';

		/**
		 * Filters a product URL before it is shown.
		 *
		 * White-labelling an installation means returning an empty string here,
		 * which is the signal to the admin screens to show no link at all.
		 *
		 * @since 0.1.0
		 *
		 * @param string $url  The absolute URL.
		 * @param string $path The requested path.
		 */
		return (string) apply_filters( 'oxyarea_brand_url', $base . ltrim( $path, '/' ), $path );
	}

	/**
	 * The product page.
	 *
	 * @return string
	 */
	public static function product_url(): string {
		return self::url( 'oxyarea/' );
	}

	/**
	 * The documentation.
	 *
	 * @return string
	 */
	public static function docs_url(): string {
		return self::url( 'docs/oxyarea/' );
	}

	/**
	 * Support.
	 *
	 * @return string
	 */
	public static function support_url(): string {
		return self::url( 'support/' );
	}

	/**
	 * Whether client-facing screens should carry the vendor's name.
	 *
	 * The Agency tier turns this off. The free plugin never had a "powered by"
	 * link to remove in the first place.
	 *
	 * @return bool
	 */
	public static function is_visible(): bool {
		/**
		 * Filters whether OxyArea identifies itself on client-facing screens.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $visible Whether to show the product name.
		 */
		return (bool) apply_filters( 'oxyarea_brand_visible', true );
	}
}
