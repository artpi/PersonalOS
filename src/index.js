import { registerPlugin } from '@wordpress/plugins';
import NotesPlugin from './notes/plugin';
import '../modules/bucketlist/js/src/admin';

registerPlugin( 'pos-notes', { render: NotesPlugin } );
