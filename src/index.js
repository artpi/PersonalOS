import { registerPlugin } from '@wordpress/plugins';
import NotesPlugin from './notes/plugin';

registerPlugin( 'pos-notes', { render: NotesPlugin } );
