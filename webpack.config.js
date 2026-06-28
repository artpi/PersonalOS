const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		index: './src/index.js',
		'personal-notes/index': './packages/personal-notes/src/index.js',
		'personal-notes/blocks/note/index':
			'./packages/personal-notes/src/blocks/note/index.js',
		'personal-readwise-sync/index':
			'./packages/personal-readwise-sync/src/index.js',
		'personal-readwise-sync/blocks/readwise/index':
			'./packages/personal-readwise-sync/src/blocks/readwise/index.js',
		'personal-readwise-sync/blocks/book-summary/index':
			'./packages/personal-readwise-sync/src/blocks/book-summary/index.js',
		'personal-evernote-sync/index':
			'./packages/personal-evernote-sync/src/index.js',
		'personal-todo/index': './packages/personal-todo/src/index.js',
		'personal-ai-chat/index': './packages/personal-ai-chat/src/index.js',
		'personal-ai-chat/blocks/message/index':
			'./packages/personal-ai-chat/src/blocks/message/index.js',
		'personal-ai-chat/blocks/tool/index':
			'./packages/personal-ai-chat/src/blocks/tool/index.js',
	},
};
