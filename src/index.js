import { registerPlugin } from '@wordpress/plugins';
import NotesPlugin from './notes/plugin';
import './notebooks/notebooks';

registerPlugin( 'pos-notes', { render: NotesPlugin } );
