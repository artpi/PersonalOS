import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

// Gutenberg may upload without a parent. Identify this saved Stuff item so
// both multipart and raw-body Media uploads receive the server filename rule.
apiFetch.use( ( options, next ) => {
	if (
		/^\/wp\/v2\/media\/?(?:\?|$)/.test( options.path || '' ) &&
		options.method?.toUpperCase() === 'POST'
	) {
		options = {
			...options,
			path: addQueryArgs( options.path, {
				post: window.personalStuffEditor.postId,
			} ),
		};
	}
	return next( options );
} );
