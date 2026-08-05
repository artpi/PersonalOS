#!/usr/bin/env node
/* eslint-disable no-console */
import { execFileSync } from 'node:child_process';
import { mkdir, readdir, rm, stat, copyFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const packagesDir = path.join( root, 'packages' );
const sharedPhpDir = path.join( root, 'shared', 'php' );
const distDir = path.join( root, 'dist' );

const packages = [
	'personal-notes',
	'personal-readwise-sync',
	'personal-evernote-sync',
	'personal-todo',
	'personal-ai-chat',
];
const wpAppVendorCopies = [
	{
		source: 'vendor/akirk/wp-app/src',
		target: 'vendor/akirk/wp-app/src',
	},
	{
		source: 'vendor/akirk/wp-app/LICENSE',
		target: 'vendor/akirk/wp-app/LICENSE',
	},
	{
		source: 'vendor/akirk/wp-app/README.md',
		target: 'vendor/akirk/wp-app/README.md',
	},
	{
		source: 'vendor/akirk/wp-app/composer.json',
		target: 'vendor/akirk/wp-app/composer.json',
	},
];
const packageVendorCopies = {
	'personal-notes': wpAppVendorCopies,
	'personal-readwise-sync': wpAppVendorCopies,
	'personal-evernote-sync': [
		...wpAppVendorCopies,
		{
			source: 'vendor/evernote/evernote-cloud-sdk-php/src',
			target: 'vendor/evernote/evernote-cloud-sdk-php/src',
		},
		{
			source: 'vendor/evernote/evernote-cloud-sdk-php/LICENSE',
			target: 'vendor/evernote/evernote-cloud-sdk-php/LICENSE',
		},
		{
			source: 'vendor/evernote/evernote-cloud-sdk-php/APACHE-LICENSE-2.0.txt',
			target: 'vendor/evernote/evernote-cloud-sdk-php/APACHE-LICENSE-2.0.txt',
		},
		{
			source: 'vendor/evernote/evernote-cloud-sdk-php/NOTICE',
			target: 'vendor/evernote/evernote-cloud-sdk-php/NOTICE',
		},
		{
			source: 'vendor/psr/log/Psr/Log',
			target: 'vendor/psr/log/Psr/Log',
			predicate: ( entry ) => 'Test' !== entry,
		},
		{
			source: 'vendor/psr/log/LICENSE',
			target: 'vendor/psr/log/LICENSE',
		},
	],
	'personal-todo': wpAppVendorCopies,
	'personal-ai-chat': wpAppVendorCopies,
};

const args = process.argv.slice( 2 );
const all = args.includes( '--all' );
const packageArg = args.find( ( arg ) => arg.startsWith( '--package=' ) );
const selected = all
	? packages
	: [ packageArg ? packageArg.split( '=' )[ 1 ] : packages[ 0 ] ];

for ( const slug of selected ) {
	if ( ! packages.includes( slug ) ) {
		throw new Error( `Unknown package: ${ slug }` );
	}

	await packagePlugin( slug );
}

async function packagePlugin( slug ) {
	const sourceDir = path.join( packagesDir, slug );
	const buildDir = path.join( distDir, slug );
	const zipPath = path.join( root, `${ slug }.zip` );

	await rm( buildDir, { recursive: true, force: true } );
	await rm( zipPath, { force: true } );
	await mkdir( buildDir, { recursive: true } );

	await copyDirectory( sourceDir, buildDir, shouldCopyPackageFile );
	await copyDirectory(
		sharedPhpDir,
		path.join( buildDir, 'includes', 'shared' ),
		() => true
	);

	const packageBuildDir = path.join( root, 'build', slug );
	if ( existsSync( packageBuildDir ) ) {
		await copyDirectory(
			packageBuildDir,
			path.join( buildDir, 'build' ),
			() => true
		);
	}
	await copyBlockMetadata( sourceDir, path.join( buildDir, 'build' ) );
	await copyPackageVendorFiles( slug, buildDir );

	execFileSync( 'zip', [ '-qr', zipPath, slug ], {
		cwd: distDir,
		stdio: 'inherit',
	} );

	console.log( `${ slug }.zip` );
}

async function copyPackageVendorFiles( slug, buildDir ) {
	for ( const copy of packageVendorCopies[ slug ] || [] ) {
		const source = path.join( root, copy.source );
		const target = path.join( buildDir, copy.target );

		if ( ! existsSync( source ) ) {
			throw new Error(
				`${ slug }: missing vendor source ${ copy.source }`
			);
		}

		const sourceStat = await stat( source );
		if ( sourceStat.isDirectory() ) {
			await copyDirectory(
				source,
				target,
				copy.predicate || ( () => true )
			);
			continue;
		}

		await mkdir( path.dirname( target ), { recursive: true } );
		await copyFile( source, target );
	}
}

async function copyDirectory( sourceDir, targetDir, predicate ) {
	await mkdir( targetDir, { recursive: true } );

	for ( const entry of await readdir( sourceDir ) ) {
		const source = path.join( sourceDir, entry );
		const target = path.join( targetDir, entry );
		const sourceStat = await stat( source );

		if ( ! predicate( entry, source, sourceStat ) ) {
			continue;
		}

		if ( sourceStat.isDirectory() ) {
			await copyDirectory( source, target, predicate );
			continue;
		}

		await mkdir( path.dirname( target ), { recursive: true } );
		await copyFile( source, target );
	}
}

function shouldCopyPackageFile( entry, source, sourceStat ) {
	if (
		[ 'node_modules', 'vendor', 'build', '.git', '.DS_Store' ].includes(
			entry
		)
	) {
		return false;
	}

	if ( source.endsWith( '.map' ) || source.endsWith( '.log' ) ) {
		return false;
	}

	if ( sourceStat.isDirectory() ) {
		return true;
	}

	return existsSync( source );
}

async function copyBlockMetadata( packageDir, targetBuildDir ) {
	const blocksDir = path.join( packageDir, 'src', 'blocks' );

	if ( ! existsSync( blocksDir ) ) {
		return;
	}

	await copyDirectory(
		blocksDir,
		path.join( targetBuildDir, 'blocks' ),
		( entry, source, sourceStat ) =>
			sourceStat.isDirectory() || 'block.json' === entry
	);
}
