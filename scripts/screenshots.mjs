/**
 * Takes the plugin directory's screenshots.
 *
 * Drives a headless Chromium against the test bed. Run from a directory where
 * `npm install puppeteer` has been done — it is a tool for producing release
 * assets, not a dependency of the plugin, so it is not in composer.json or
 * package.json and nothing ships it.
 *
 * The site sits behind HTTP basic auth *and* needs a WordPress session, which is
 * why this is a browser script rather than a curl loop: the block editor is the
 * one screen that cannot be photographed without JavaScript.
 *
 * Usage:
 *   node screenshots.mjs <output-directory>
 *
 * Credentials come from the environment so none of them is in the repository:
 *   OXYAREA_BASIC=user:pass  OXYAREA_ADMIN=user:pass  OXYAREA_CUSTOMER=user:pass
 */

import puppeteer from 'puppeteer';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const SITE = process.env.OXYAREA_SITE ?? 'https://test.44123.it/oxyarea';
const OUT = process.argv[2] ?? '.';

const WIDTH = 1280;
const HEIGHT = 800;

const pair = ( value, what ) => {
	if ( ! value || ! value.includes( ':' ) ) {
		throw new Error( `Set ${ what } to "user:password".` );
	}

	const at = value.indexOf( ':' );

	return { username: value.slice( 0, at ), password: value.slice( at + 1 ) };
};

const basic = pair( process.env.OXYAREA_BASIC, 'OXYAREA_BASIC' );
const admin = pair( process.env.OXYAREA_ADMIN, 'OXYAREA_ADMIN' );
const customer = pair( process.env.OXYAREA_CUSTOMER, 'OXYAREA_CUSTOMER' );

/**
 * The shots, in the order the directory will show them.
 *
 * The first is the payoff — what a customer actually sees — because that is the
 * one somebody scrolling a list of plugins looks at.
 */
const SHOTS = [
	{ n: 1, as: 'customer', url: '/my-area/', wait: '.oxyarea-dashboard' },
	{ n: 2, as: 'anonymous', url: '/sign-in/', wait: '.oxyarea-form--login' },
	{ n: 3, as: 'admin', url: '/wp-admin/admin.php?page=oxyarea', wait: '.wp-list-table' },
	{ n: 4, as: 'admin', url: '/wp-admin/admin.php?page=oxyarea-redirects', wait: '.wp-list-table' },
	{ n: 5, as: 'admin', url: '/wp-admin/edit.php?post_type=oxyarea_dashboard', wait: '.wp-list-table' },
	{ n: 6, as: 'admin', url: '/wp-admin/admin.php?page=oxyarea-dashboard-preview&oxyarea_role=customer', wait: '.oxyarea-preview' },
	{ n: 7, as: 'admin', url: '/wp-admin/admin.php?page=oxyarea-settings', wait: '.form-table' },
];

const settle = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

/**
 * A page in a browser context of its own, through basic auth, at the right size.
 *
 * The context matters. Pages of one browser share cookies, so the visitor who is
 * supposed to be signed out would be signed in as the administrator, and the
 * sign-in form would photograph as "you are signed in as oxysoft" — which is
 * exactly what happened the first time this ran.
 *
 * @param {import('puppeteer').Browser} browser The browser.
 * @return {Promise<import('puppeteer').Page>} The page.
 */
async function open( browser ) {
	const context = await browser.createBrowserContext();
	const page = await context.newPage();

	await page.setViewport( { width: WIDTH, height: HEIGHT, deviceScaleFactor: 1 } );
	await page.authenticate( basic );

	return page;
}

/**
 * Sign in to wp-admin.
 *
 * @param {import('puppeteer').Page} page The page.
 */
async function signInToAdmin( page ) {
	await page.goto( `${ SITE }/wp-login.php`, { waitUntil: 'networkidle2' } );
	await page.type( '#user_login', admin.username );
	await page.type( '#user_pass', admin.password );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle2' } ),
		page.click( '#wp-submit' ),
	] );
}

/**
 * Sign in on the front of the site, through OxyArea's own form.
 *
 * @param {import('puppeteer').Page} page The page.
 */
async function signInAsCustomer( page ) {
	await page.goto( `${ SITE }/sign-in/`, { waitUntil: 'networkidle2' } );
	await page.type( '#oxyarea-user-login', customer.username );
	await page.type( '#oxyarea-user-password', customer.password );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle2' } ),
		page.click( '.oxyarea-form--login button[type="submit"]' ),
	] );
}

/**
 * Close anything WordPress puts in front of a screen the first time.
 *
 * @param {import('puppeteer').Page} page The page.
 */
async function dismissOverlays( page ) {
	const closers = [
		'.components-modal__header button[aria-label]',
		'.edit-post-welcome-guide button[aria-label]',
		'.notice-dismiss',
	];

	for ( const selector of closers ) {
		try {
			const handles = await page.$$( selector );

			for ( const handle of handles ) {
				await handle.click().catch( () => {} );
			}
		} catch {
			// Nothing to close is the ordinary case.
		}
	}
}

const browser = await puppeteer.launch( {
	headless: true,
	args: [ '--no-sandbox', `--window-size=${ WIDTH },${ HEIGHT }` ],
} );

await mkdir( OUT, { recursive: true } );

const sessions = {};

sessions.anonymous = await open( browser );

sessions.admin = await open( browser );
await signInToAdmin( sessions.admin );

sessions.customer = await open( browser );
await signInAsCustomer( sessions.customer );

let taken = 0;

for ( const shot of SHOTS ) {
	const page = sessions[ shot.as ];
	const file = path.join( OUT, `screenshot-${ shot.n }.png` );

	await page.goto( `${ SITE }${ shot.url }`, { waitUntil: 'networkidle2' } );
	await dismissOverlays( page );

	// WordPress's own toolbar belongs to WordPress, not to this plugin, and a
	// screenshot of somebody else's furniture teaches a reader nothing.
	await page.addStyleTag( {
		content: '#wpadminbar{display:none!important}html{margin-top:0!important}',
	} ).catch( () => {} );

	await settle( 600 );

	let found = true;

	try {
		await page.waitForSelector( shot.wait, { timeout: 8000 } );
	} catch {
		found = false;
	}

	await page.screenshot( { path: file, captureBeyondViewport: false } );

	taken += 1;
	console.log( `${ found ? 'ok  ' : 'WARN' }  ${ shot.n }  ${ shot.url }  (${ shot.as })${ found ? '' : ` — ${ shot.wait } not found` }` );
}

await browser.close();

console.log( `\n${ taken } screenshots in ${ OUT }` );
