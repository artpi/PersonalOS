This is a WordPress plugin serving as Personal Operating System with todos, notes, ai integration etc.

## Other files you should read

- .cursor/rules/layout.mdc to learn about the layout of the repository, what dir does what
- .cursor/rules/dev-environment.mdc to learn about dev environment and testing environment
- .cursor/rules/wordpress-coding-standards/wordpress-coding-standards-php.mdc to learn about standards for php
- .cursor/rules/wordpress-coding-standards/wordpress-coding-standards-javascript.mdc to learn about standards for js.

## Module Technical Documentation

Modules can have TECHNICAL.md files that document their technical implementation details, architecture, and design decisions. These files are valuable for understanding how specific modules work.

### Current TECHNICAL.md files

- `modules/imap/TECHNICAL.md` - Technical documentation for the IMAP module
- `modules/openai/TECHNICAL.md` - Technical documentation for the OpenAI module
- `modules/slack/TECHNICAL.md` - Technical documentation for the Slack integration module

## Coding

Assume environment is set up.

- `composer run lint -- "file/path"` to lint
- `composer run phpcbf -- "file/path"` to format entire file, including whitespace. Dont try to use python or other scripts to add whitespace.
- `npm run test:unit:backend` to run tests. Feel free to run them often.

## Your behaviour

- IMPORTANT: When you learn something about the codebase or how I want you to operate, add it to lessons below.

### Lessons
- For the plugin split, prefer a lean Guidelines-centered target: Notes UI, Readwise importer, Evernote importer, TODO+ICS, and AI Chat; remove/defer OpenAI, Perplexity, IMAP/email responder, podcast/ElevenLabs, transcription, and voice/realtime instead of extracting every old module.
- TODO should store each task as its own `wp_guideline` post tagged `artifact` and `todo` so non-PersonalOS Guidelines consumers can discover tasks; do not use `plan` or `personalos` for ordinary TODO rows.
- Current GitHub Actions build one monolithic `wp-personal-os.zip`; the plugin split needs a package matrix that builds and uploads separate ZIPs for Notes, Readwise, Evernote, TODO, and AI Chat.
- The current NPM build is monolithic (`src/index.js` -> `build/index.js`); split plugins need package-local JS/CSS builds, block registration, and sidebars so no package depends on the root `pos` editor bundle.
