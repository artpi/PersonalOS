#!/usr/bin/env node
/* eslint-disable no-console */
import { cp, mkdir, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
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

for ( const slug of packages ) {
	const source = path.join( root, 'build', slug );
	const target = path.join( root, 'packages', slug, 'build' );

	await rm( target, { recursive: true, force: true } );

	if ( ! existsSync( source ) ) {
		console.warn( `${ slug }: build output not found` );
		continue;
	}

	await mkdir( path.dirname( target ), { recursive: true } );
	await cp( source, target, { recursive: true } );
	await copyBlockMetadata( slug, target );
	await syncWpAppRuntime( slug );
	console.log( `${ slug }: synced build assets` );
}

async function syncWpAppRuntime( slug ) {
	const target = path.join(
		root,
		'packages',
		slug,
		'vendor',
		'akirk',
		'wp-app'
	);

	await rm( target, { recursive: true, force: true } );

	if ( ! appPackages.includes( slug ) ) {
		return;
	}

	const source = path.join( root, 'vendor', 'akirk', 'wp-app' );

	if ( ! existsSync( source ) ) {
		console.warn( `${ slug }: WpApp Composer runtime not found` );
		return;
	}

	await mkdir( path.dirname( target ), { recursive: true } );
	await cp( source, target, {
		filter: ( entry ) => ! entry.includes( `${ path.sep }.git` ),
		recursive: true,
	} );
}

async function copyBlockMetadata( slug, target ) {
	const source = path.join( root, 'packages', slug, 'src', 'blocks' );

	if ( ! existsSync( source ) ) {
		return;
	}

	await cp( source, path.join( target, 'blocks' ), {
		filter: ( entry ) =>
			entry.endsWith( 'block.json' ) || ! path.extname( entry ),
		recursive: true,
	} );
}
