---
description: Official WordPress coding standards from https://github.com/WordPress/wpcs-docs/blob/master/wordpress-coding-standards/php.md
globs: *.php
alwaysApply: false
---

# WordPress PHP Coding Standards Summary
Source: https://github.com/WordPress/wpcs-docs/blob/master/wordpress-coding-standards/php.md

## General Rules
- Use full PHP tags (`<?php ?>`, never shorthand)
- Single quotes for strings without variables, double quotes when evaluating content
- No parentheses for `require`/`include` statements; use `require_once` for dependencies
- Files should end without closing PHP tag

## Naming Conventions
- Variables, functions, action/filter hooks: lowercase with underscores (`some_function_name`)
- Classes, traits, interfaces, enums: capitalized words with underscores (`WP_Error`, `Walker_Category`)
- Constants: uppercase with underscores (`DOING_AJAX`)
- File names: lowercase with hyphens (`my-plugin-name.php`)
- Class files: prefix with `class-` (`class-wp-error.php`)

## Whitespace & Indentation
- Use tabs for indentation, spaces for mid-line alignment
- Space after commas and around operators
- Space inside parentheses
- No space between function/method name and opening parenthesis
- No trailing whitespace at line ends
- Array items on new lines for multi-item arrays

## Formatting
- Always use braces for control structures
- Arrays must use long syntax `array()` (not `[]`)
- One space between closing parenthesis and colon in return type declarations
- Always include trailing comma in multi-line arrays

## Object-Oriented Programming
- One class/interface/trait/enum per file
- Always declare visibility (`public`, `protected`, `private`)
- Correct modifier order (e.g., `abstract` then `readonly` for class declarations)
- Always use parentheses for object instantiation

## Control Structures
- Use `elseif` (not `else if`)
- Yoda conditions (`if ( true === $var )` not `if ( $var === true )`)
- Use braces even for single-statement blocks

## Operators
- Ternary operators should test for true, not false
- Avoid error control operator (`@`)
- Prefer pre-increment/decrement (`++$i`) over post-increment/decrement (`$i++`)

## Database
- Avoid direct database queries when possible
- Capitalize SQL keywords (`SELECT`, `UPDATE`, etc.)
- Use `$wpdb->prepare()` for secure queries
- Use placeholders (`%d`, `%f`, `%s`, `%i`) without quotes

## Recommendations
- Use descriptive string values instead of boolean flags
- Prioritize readability over cleverness
- Use strict comparisons (`===` and `!==`)
- No assignments in conditionals
- Avoid `extract()`, `eval()`, `create_function()` and backtick operators
- Use PCRE over POSIX for regular expressions

## Type Declarations
- Use lowercase for scalar types (`int`, `bool`, `string`, etc.)
- Ensure one space before and after type declarations
- No space between nullability operator (`?`) and type
- Obey PHP version compatibility constraints when using types
