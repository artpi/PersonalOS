// THIS SHOULD FAIL LINTING - unused variable and double quotes
const unusedVariable = "test";
import { registerPlugin } from '@wordpress/plugins';
import NotesPlugin from './notes/plugin';
import './notebooks/notebooks';
import './todo/todo';
registerPlugin( 'pos-notes', { render: NotesPlugin } );
