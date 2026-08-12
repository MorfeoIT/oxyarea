/**
 * OxyArea's blocks, in the editor.
 *
 * Written by hand in plain ES5 and shipped as it is read. There is no build
 * step, no JSX and no bundle, which means the file in the plugin directory is
 * the file the author wrote — the thing the review guidelines ask for, achieved
 * by not creating the problem.
 *
 * Every block renders on the server, so `save` returns null and there is nothing
 * to keep in sync between two implementations of the same form. What the editor
 * shows is a labelled placeholder rather than a live preview: most of these are
 * forms, and a form that half works inside the editor invites somebody to type
 * their password into it.
 *
 * Titles and descriptions come from each block.json through the server
 * registration, so they are translated once, in PHP, and not repeated here.
 */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;

	/**
	 * A placeholder that names the block and says what it will do.
	 *
	 * @param {string} note What the visitor will see, in one sentence.
	 * @return {Function} The edit component.
	 */
	function placeholder( note ) {
		return function ( props ) {
			var name = props.name.replace( 'oxyarea/', '' );

			return el(
				'div',
				useBlockProps( { className: 'oxyarea-editor-placeholder' } ),
				el(
					'span',
					{ className: 'oxyarea-editor-placeholder__badge' },
					'OxyArea'
				),
				el( 'strong', { className: 'oxyarea-editor-placeholder__name' }, name ),
				el( 'p', { className: 'oxyarea-editor-placeholder__note' }, note )
			);
		};
	}

	/**
	 * Register one server-rendered block with a placeholder for an editor.
	 *
	 * @param {string} name The block name.
	 * @param {string} note What the visitor will see.
	 */
	function register( name, note ) {
		blocks.registerBlockType( name, {
			edit: placeholder( note ),
			save: function () {
				return null;
			}
		} );
	}

	register(
		'oxyarea/login',
		__( 'A sign-in form appears here. People who are already signed in see who they are signed in as.', 'oxyarea' )
	);

	register(
		'oxyarea/logout',
		__( 'A sign-out button appears here, and nothing at all for visitors who are not signed in.', 'oxyarea' )
	);

	register(
		'oxyarea/lost-password',
		__( 'A form for asking to set a new password. It gives the same answer whether or not the account exists.', 'oxyarea' )
	);

	register(
		'oxyarea/reset-password',
		__( 'The form people reach from the reset email. Put it on the same page as the sign-in form.', 'oxyarea' )
	);

	register(
		'oxyarea/profile',
		__( 'People can change their own name, email address and password here.', 'oxyarea' )
	);

	register(
		'oxyarea/dashboard',
		__( 'The dashboard belonging to whoever is reading. Which one that is depends on their role.', 'oxyarea' )
	);

	register(
		'oxyarea/profile-summary',
		__( 'The reader’s name, email address and account type. Read only.', 'oxyarea' )
	);

	/**
	 * The welcome line is the one block with something to edit, so it gets a
	 * field rather than a placeholder. A plain input: enough to type a sentence
	 * with a placeholder in it, and nothing that pretends to be a live preview of
	 * a value only the visitor's own account can supply.
	 */
	blocks.registerBlockType( 'oxyarea/welcome', {
		edit: function ( props ) {
			var value = props.attributes.text || '';

			return el(
				'div',
				useBlockProps( { className: 'oxyarea-editor-placeholder' } ),
				el(
					'span',
					{ className: 'oxyarea-editor-placeholder__badge' },
					'OxyArea'
				),
				el(
					'label',
					{
						className: 'oxyarea-editor-placeholder__name',
						htmlFor: 'oxyarea-welcome-' + props.clientId
					},
					__( 'Welcome line', 'oxyarea' )
				),
				el( 'input', {
					id: 'oxyarea-welcome-' + props.clientId,
					type: 'text',
					className: 'oxyarea-editor-placeholder__field',
					value: value,
					placeholder: __( 'Welcome, {{display_name}}.', 'oxyarea' ),
					onChange: function ( event ) {
						props.setAttributes( { text: event.target.value } );
					}
				} ),
				el(
					'p',
					{ className: 'oxyarea-editor-placeholder__note' },
					__( 'Placeholders: {{display_name}} {{first_name}} {{last_name}} {{username}} {{user_email}} {{user_id}}', 'oxyarea' )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
