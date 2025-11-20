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

## Coding

Assume environment is set up.

- `composer run lint -- "file/path"` to lint
- `composer run phpcbf -- "file/path"` to format entire file, including whitespace. Dont try to use python or other scripts to add whitespace.
- `npm run test:unit` to run tests. Feel free to run them often.

## Your behaviour

- IMPORTANT: When you learn something about the codebase or how I want you to operate, add it to lessons below.

### Lessons
- 