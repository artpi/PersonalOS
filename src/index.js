import { registerPlugin } from '@wordpress/plugins';
import NotesPlugin from './notes/plugin';
import './notebooks/notebooks';
import './todo/todo';
registerPlugin( 'pos-notes', { render: NotesPlugin } );
