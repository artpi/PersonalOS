#!/usr/bin/env node
/* eslint-disable no-console */
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const packages = [
	'personal-notes',
	'personal-readwise-sync',
	'personal-evernote-sync',
	'personal-todo',
	'personal-ai-chat',
];
const appPackages = [ 'personal-notes', 'personal-todo', 'personal-ai-chat' ];
const packageBlocks = {
	'personal-notes': [ 'note' ],
	'personal-readwise-sync': [ 'readwise', 'book-summary' ],
	'personal-ai-chat': [ 'message', 'tool' ],
};
const syncPackages = [ 'personal-readwise-sync', 'personal-evernote-sync' ];
const networkedPackages = {
	'personal-readwise-sync': {
		requiredText: [ 'Readwise terms:', 'Readwise privacy policy:' ],
	},
	'personal-evernote-sync': {
		requiredText: [ 'Evernote terms:', 'Evernote privacy policy:' ],
	},
	'personal-ai-chat': {
		requiredText: [ 'WordPress AI Client', 'Connectors', 'provider' ],
	},
};
const nonNetworkedPackages = [ 'personal-notes', 'personal-todo' ];
const wpAppVendorFiles = [
	'vendor/akirk/wp-app/src/class-registry.php',
	'vendor/akirk/wp-app/src/class-wpapp.php',
	'vendor/akirk/wp-app/src/functions.php',
	'vendor/akirk/wp-app/LICENSE',
	'vendor/akirk/wp-app/README.md',
	'vendor/akirk/wp-app/composer.json',
];
const packageVendorFiles = {
	'personal-evernote-sync': [
		'vendor/evernote/evernote-cloud-sdk-php/src/Evernote/AdvancedClient.php',
		'vendor/evernote/evernote-cloud-sdk-php/src/EDAM/NoteStore/NoteStore.php',
		'vendor/evernote/evernote-cloud-sdk-php/LICENSE',
		'vendor/psr/log/Psr/Log/NullLogger.php',
		'vendor/psr/log/LICENSE',
	],
};
const sourceExtensions = new Set( [
	'.php',
	'.js',
	'.jsx',
	'.ts',
	'.tsx',
	'.json',
	'.css',
] );

const args = process.argv.slice( 2 );
const packageArg = args.find( ( arg ) => arg.startsWith( '--package=' ) );
const selected = packageArg ? [ packageArg.split( '=' )[ 1 ] ] : packages;
const errors = [];

verifyWpAppDependency();

for ( const slug of selected ) {
	verifyPackage( slug );
}

if ( errors.length > 0 ) {
	for ( const error of errors ) {
		console.error( error );
	}
	process.exit( 1 );
}

console.log(
	`Verified ${ selected.length } split package${
		selected.length === 1 ? '' : 's'
	}.`
);

function verifyPackage( slug ) {
	if ( ! packages.includes( slug ) ) {
		errors.push( `Unknown package: ${ slug }` );
		return;
	}

	const packageDir = path.join( root, 'packages', slug );
	const mainFile = path.join( packageDir, `${ slug }.php` );
	const readme = path.join( packageDir, 'readme.txt' );
	const license = path.join( packageDir, 'LICENSE' );
	const zipPath = path.join( root, `${ slug }.zip` );
	const sourceFiles = existsSync( packageDir )
		? listFiles( packageDir ).filter( isSourceFile )
		: [];

	requireFile( mainFile );
	requireFile( readme );
	requireFile( license );
	requireFile( path.join( packageDir, 'src', 'index.js' ) );
	if ( appPackages.includes( slug ) ) {
		requireFile( path.join( packageDir, 'templates', 'index.php' ) );
	}
	requireFile( path.join( root, 'build', slug, 'index.js' ) );
	requireFile( path.join( root, 'build', slug, 'index.asset.php' ) );
	requireFile( path.join( root, 'build', slug, 'style-index.css' ) );
	requireFile( path.join( packageDir, 'build', 'index.js' ) );
	requireFile( path.join( packageDir, 'build', 'index.asset.php' ) );
	requireFile( path.join( packageDir, 'build', 'style-index.css' ) );
	requireFile( zipPath );

	for ( const block of packageBlocks[ slug ] || [] ) {
		requireFile(
			path.join( packageDir, 'src', 'blocks', block, 'block.json' )
		);
		requireFile(
			path.join( packageDir, 'build', 'blocks', block, 'block.json' )
		);
		requireFile(
			path.join( packageDir, 'build', 'blocks', block, 'index.js' )
		);
		requireFile(
			path.join( packageDir, 'build', 'blocks', block, 'index.asset.php' )
		);
	}

	if ( existsSync( mainFile ) ) {
		const header = readFileSync( mainFile, 'utf8' );
		const version = parseHeaderField( header, 'Version' );
		for ( const field of [
			'Plugin Name:',
			'Description:',
			'Version:',
			'Requires at least:',
			'Requires PHP:',
			'Author:',
			'License:',
			'License URI:',
			'Text Domain:',
		] ) {
			if ( ! header.includes( field ) ) {
				errors.push(
					`${ slug }: missing plugin header field ${ field }`
				);
			}
		}

		if ( header.includes( 'Requires Plugins:' ) ) {
			errors.push( `${ slug }: must not declare Requires Plugins` );
		}

		const requiredPhp = appPackages.includes( slug ) ? '7.4' : '7.2.24';
		if ( ! header.includes( `Requires PHP:      ${ requiredPhp }` ) ) {
			errors.push( `${ slug }: must require PHP ${ requiredPhp }` );
		}

		if ( header.includes( 'wp_remote_' ) ) {
			errors.push(
				`${ slug }: main plugin file must not make outbound HTTP requests at load or activation time`
			);
		}

		if ( existsSync( readme ) ) {
			const readmeText = readFileSync( readme, 'utf8' );
			const stableTag = parseReadmeField( readmeText, 'Stable tag' );
			if ( version && stableTag && version !== stableTag ) {
				errors.push(
					`${ slug }: plugin Version ${ version } does not match readme Stable tag ${ stableTag }`
				);
			}
		}
	}

	if ( existsSync( readme ) ) {
		const readmeText = readFileSync( readme, 'utf8' );
		for ( const field of [
			'Stable tag:',
			'License:',
			'Requires at least:',
			'Requires PHP:',
		] ) {
			if ( ! readmeText.includes( field ) ) {
				errors.push( `${ slug }: readme missing ${ field }` );
			}
		}

		if ( ! readmeText.includes( 'Source and Build' ) ) {
			errors.push( `${ slug }: readme missing Source and Build section` );
		}

		if ( ! readmeText.includes( '== Privacy ==' ) ) {
			errors.push( `${ slug }: readme missing Privacy section` );
		}

		if ( ! readmeText.includes( '== Data Retention ==' ) ) {
			errors.push( `${ slug }: readme missing Data Retention section` );
		}

		const requiredPhp = appPackages.includes( slug ) ? '7.4' : '7.2.24';
		if ( ! readmeText.includes( `Requires PHP: ${ requiredPhp }` ) ) {
			errors.push(
				`${ slug }: readme must require PHP ${ requiredPhp }`
			);
		}

		if (
			appPackages.includes( slug ) &&
			! readmeText.includes( 'WpApp 1.3.2' )
		) {
			errors.push(
				`${ slug }: readme must disclose bundled WpApp 1.3.2`
			);
		}

		if (
			! appPackages.includes( slug ) &&
			readmeText.includes( 'WpApp' )
		) {
			errors.push(
				`${ slug }: sync integration must not advertise WpApp`
			);
		}

		if ( readmeText.includes( 'Requires Plugins:' ) ) {
			errors.push(
				`${ slug }: readme must not declare Requires Plugins`
			);
		}

		if (
			/TODO:|FIXME|TBD|Lorem ipsum|Plugin Name Here|REPLACE/i.test(
				readmeText
			)
		) {
			errors.push( `${ slug }: readme contains placeholder text` );
		}

		if ( networkedPackages[ slug ] ) {
			for ( const requiredText of networkedPackages[ slug ]
				.requiredText ) {
				if ( ! readmeText.includes( requiredText ) ) {
					errors.push(
						`${ slug }: readme missing service/privacy disclosure text "${ requiredText }"`
					);
				}
			}
		}

		if (
			nonNetworkedPackages.includes( slug ) &&
			! readmeText.includes( 'does not contact external services' )
		) {
			errors.push(
				`${ slug }: readme must disclose that it does not contact external services`
			);
		}
	}

	scanSourceFiles( slug, sourceFiles );

	if ( existsSync( zipPath ) ) {
		const entries = execFileSync( 'zipinfo', [ '-1', zipPath ], {
			encoding: 'utf8',
		} )
			.trim()
			.split( '\n' )
			.filter( Boolean );
		const topLevel = new Set(
			entries.map( ( entry ) => entry.split( '/' )[ 0 ] )
		);

		if ( topLevel.size !== 1 || ! topLevel.has( slug ) ) {
			errors.push(
				`${ slug }: ZIP must contain exactly one top-level ${ slug } directory`
			);
		}

		const requiredEntries = [
			`${ slug }/${ slug }.php`,
			`${ slug }/readme.txt`,
			`${ slug }/LICENSE`,
			`${ slug }/build/index.js`,
			`${ slug }/build/index.asset.php`,
			`${ slug }/build/style-index.css`,
			`${ slug }/includes/shared/class-personalos-plugin-base.php`,
			`${ slug }/includes/shared/class-personalos-knowledge-bridge.php`,
		];

		if ( appPackages.includes( slug ) ) {
			requiredEntries.push(
				`${ slug }/includes/shared/class-personalos-wp-app.php`,
				`${ slug }/templates/index.php`
			);
		}

		for ( const entry of requiredEntries ) {
			if ( ! entries.includes( entry ) ) {
				errors.push( `${ slug }: ZIP missing ${ entry }` );
			}
		}

		if ( ! appPackages.includes( slug ) ) {
			for ( const entry of entries ) {
				if (
					entry ===
						`${ slug }/includes/shared/class-personalos-wp-app.php` ||
					entry.startsWith( `${ slug }/vendor/akirk/wp-app/` ) ||
					entry.startsWith( `${ slug }/templates/` )
				) {
					errors.push(
						`${ slug }: sync integration ZIP contains WpApp file ${ entry }`
					);
				}
			}
		}

		if (
			syncPackages.includes( slug ) &&
			! entries.includes(
				`${ slug }/includes/shared/class-personalos-sync-plugin-base.php`
			)
		) {
			errors.push(
				`${ slug }: ZIP missing ${ slug }/includes/shared/class-personalos-sync-plugin-base.php`
			);
		}

		for ( const block of packageBlocks[ slug ] || [] ) {
			for ( const entry of [
				`${ slug }/build/blocks/${ block }/block.json`,
				`${ slug }/build/blocks/${ block }/index.js`,
				`${ slug }/build/blocks/${ block }/index.asset.php`,
			] ) {
				if ( ! entries.includes( entry ) ) {
					errors.push( `${ slug }: ZIP missing ${ entry }` );
				}
			}
		}

		for ( const vendorFile of [
			...( appPackages.includes( slug ) ? wpAppVendorFiles : [] ),
			...( packageVendorFiles[ slug ] || [] ),
		] ) {
			const entry = `${ slug }/${ vendorFile }`;
			if ( ! entries.includes( entry ) ) {
				errors.push(
					`${ slug }: ZIP missing bundled vendor file ${ entry }`
				);
			}
		}

		if ( appPackages.includes( slug ) ) {
			const packagedWpApp = execFileSync(
				'unzip',
				[
					'-p',
					zipPath,
					`${ slug }/vendor/akirk/wp-app/src/class-wpapp.php`,
				],
				{ encoding: 'utf8' }
			);
			const installedWpApp = readFileSync(
				path.join( root, 'vendor/akirk/wp-app/src/class-wpapp.php' ),
				'utf8'
			);

			if ( packagedWpApp !== installedWpApp ) {
				errors.push(
					`${ slug }: bundled WpApp runtime does not match Composer`
				);
			}
		}

		for ( const entry of entries ) {
			if (
				entry.includes( '/node_modules/' ) ||
				entry.includes( '/.git/' ) ||
				entry.endsWith( '.map' ) ||
				entry.endsWith( '.log' )
			) {
				errors.push(
					`${ slug }: ZIP contains excluded file ${ entry }`
				);
			}

			for ( const sibling of packages.filter(
				( item ) => item !== slug
			) ) {
				if ( entry.startsWith( `${ slug }/${ sibling }/` ) ) {
					errors.push(
						`${ slug }: ZIP contains sibling package source ${ entry }`
					);
				}
			}
		}
	}
}

function verifyWpAppDependency() {
	const lockPath = path.join( root, 'composer.lock' );
	if ( ! existsSync( lockPath ) ) {
		errors.push( 'Missing composer.lock for pinned WpApp runtime' );
		return;
	}

	const lock = JSON.parse( readFileSync( lockPath, 'utf8' ) );
	const dependency = ( lock.packages || [] ).find(
		( item ) => 'akirk/wp-app' === item.name
	);

	if ( ! dependency || 'v1.3.2' !== dependency.version ) {
		errors.push( 'Composer must lock akirk/wp-app at v1.3.2' );
	}
}

function requireFile( file ) {
	if ( ! existsSync( file ) ) {
		errors.push( `Missing ${ path.relative( root, file ) }` );
	}
}

function listFiles( dir ) {
	const files = [];

	for ( const entry of readdirSync( dir ) ) {
		if ( [ 'node_modules', '.git' ].includes( entry ) ) {
			continue;
		}

		const file = path.join( dir, entry );
		const fileStat = statSync( file );

		if ( fileStat.isDirectory() ) {
			files.push( ...listFiles( file ) );
			continue;
		}

		files.push( file );
	}

	return files;
}

function isSourceFile( file ) {
	if ( file.includes( `${ path.sep }build${ path.sep }` ) ) {
		return false;
	}

	if ( file.endsWith( 'readme.txt' ) || file.endsWith( 'LICENSE' ) ) {
		return false;
	}

	const extension = path.extname( file );

	return sourceExtensions.has( extension );
}

function scanSourceFiles( slug, sourceFiles ) {
	for ( const file of sourceFiles ) {
		const relative = path.relative( root, file );
		const content = readFileSync( file, 'utf8' );

		for ( const pattern of [
			/POS::/,
			/get_module_by_id/,
			/class-pos-module\.php/,
			/modules\//,
			/personalos\.php/,
			/build\/index\.js/,
		] ) {
			if ( pattern.test( content ) ) {
				errors.push(
					`${ slug }: ${ relative } contains monolith coupling pattern ${ pattern }`
				);
			}
		}

		for ( const sibling of packages.filter( ( item ) => item !== slug ) ) {
			if (
				content.includes( `../${ sibling }` ) ||
				content.includes( `/${ sibling }/` )
			) {
				errors.push(
					`${ slug }: ${ relative } references sibling package ${ sibling }`
				);
			}
		}

		for ( const pattern of [
			/<script\b[^>]*\bsrc=["']https?:\/\//i,
			/wp_enqueue_script\s*\([^;]*https?:\/\//is,
			/\b(unpkg\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)\b/i,
		] ) {
			if ( pattern.test( content ) ) {
				errors.push(
					`${ slug }: ${ relative } contains remote executable/admin script pattern ${ pattern }`
				);
			}
		}
	}
}

function parseHeaderField( header, field ) {
	const match = header.match( new RegExp( `\\*\\s+${ field }:\\s*(.+)` ) );
	return match ? match[ 1 ].trim() : '';
}

function parseReadmeField( readmeText, field ) {
	const match = readmeText.match(
		new RegExp( `^${ field }:\\s*(.+)$`, 'im' )
	);
	return match ? match[ 1 ].trim() : '';
}
