# OpenAI Tools to WordPress Abilities API Migration

## Overview

This document describes the migration of PersonalOS AI tools from the custom `OpenAI_Tool` system to the WordPress Abilities API.

## Background

PersonalOS originally implemented AI capabilities using a custom `OpenAI_Tool` class and a `pos_openai_tools` filter. While functional, this approach was:
- Non-standard and specific to PersonalOS
- Not discoverable by other WordPress plugins
- Lacking standardized schemas and permissions
- Difficult to integrate with emerging AI assistant protocols

The WordPress Abilities API provides a standardized way to register and execute capabilities, making them discoverable and interoperable with other WordPress plugins and AI systems.

## Migration Goals

1. Convert all existing OpenAI tools to WordPress Abilities API format
2. Use standardized `pos/` namespace for all abilities
3. Categorize all abilities under `personalos` category
4. Maintain backward compatibility with existing OpenAI integrations
5. Ensure all code is testable and follows WordPress coding standards

## What Changed

### Architecture

**Before:**
```php
add_filter( 'pos_openai_tools', function( $tools ) {
    $tools[] = new OpenAI_Tool(
        'todo_get_items',
        'Description',
        array( 'parameters' ),
        function( $args ) { /* implementation */ }
    );
    return $tools;
} );
```

**After:**
```php
wp_register_ability(
    'pos/todo-get-items',
    array(
        'label' => 'Get TODO Items',
        'description' => 'Description',
        'category' => 'personalos',
        'input_schema' => array( /* JSON Schema */ ),
        'output_schema' => array( /* JSON Schema */ ),
        'execute_callback' => array( $module, 'method' ),
        'permission_callback' => function() { return current_user_can( 'manage_options' ); },
    )
);
```

### File Structure

**New Files:**
- `class-pos-abilities.php` - Central abilities registration and bridge adapter
- `tests/unit/AbilitiesAPIIntegrationTest.php` - Integration tests
- `tests/unit/OpenAIModuleAIToolsTest.php` - Unit tests for OpenAI module tools
- `tests/unit/NotesModuleAIToolsTest.php` - Unit tests for Notes module tools

**Modified Files:**
- All module files with tool registrations (extracted inline callbacks to testable methods)
- `personalos.php` - Added POS_Abilities initialization
- `.wp-env.json` - Added abilities-api plugin dependency

## Migrated Tools

All 7 tools have been migrated:

| Original Tool Name | Ability Name | Module | Type | Category |
|-------------------|--------------|--------|------|----------|
| `todo_get_items` | `pos/todo-get-items` | TODO | Read-only | personalos |
| `todo_create_item` | `pos/todo-create-item` | TODO | Writeable | personalos |
| `list_posts` | `pos/list-posts` | OpenAI | Read-only | personalos |
| `ai_memory` | `pos/ai-memory` | OpenAI | Writeable | personalos |
| `get_notebooks` | `pos/get-notebooks` | Notes | Read-only | personalos |
| `perplexity_search` | `pos/perplexity-search` | Perplexity | Read-only | personalos |
| `evernote_search_notes` | `pos/evernote-search-notes` | Evernote | Read-only | personalos |

## Code Improvements

### Testability

All tool callbacks were extracted from inline anonymous functions into proper class methods:

**Before:**
```php
function( $args ) use ( $self ) {
    // Complex logic here
}
```

**After:**
```php
public function create_item_for_openai( $args ) {
    // Complex logic here
    // Now testable!
}
```

### Tests

Added comprehensive test coverage:
- **TodoAIToolsTest** - Already existed, now tests extracted methods
- **OpenAIModuleAIToolsTest** - 6 tests for list_posts and ai_memory
- **NotesModuleAIToolsTest** - 3 tests for get_notebooks
- **AbilitiesAPIIntegrationTest** - 11 integration tests

## Backward Compatibility

The `POS_Abilities::bridge_tools_to_abilities()` method ensures existing code continues to work:

1. Listens to `pos_openai_tools` filter
2. Creates `OpenAI_Tool` instances from registered abilities
3. Maintains tool names and parameters
4. Executes abilities when tools are invoked

This means existing OpenAI integrations continue to work without modification.

## How to Use

### For Plugin Developers

To use PersonalOS abilities in your plugin:

```php
// Check if abilities are available
if ( ! function_exists( 'wp_get_ability' ) ) {
    return;
}

// Get an ability
$ability = wp_get_ability( 'pos/todo-get-items' );

if ( $ability ) {
    // Execute it
    $result = $ability->execute( array( 'notebook' => 'inbox' ) );
    
    if ( is_wp_error( $result ) ) {
        // Handle error
    } else {
        // Use result
        foreach ( $result as $todo ) {
            echo $todo['title'];
        }
    }
}
```

### For AI Assistant Integrations

All PersonalOS abilities are exposed via the Abilities API REST endpoints (if `show_in_rest` is true). They can be discovered and invoked by:

- AI assistant tools
- Automation platforms
- Other WordPress plugins
- External applications via REST API

### Discovering Abilities

```php
// Get all PersonalOS abilities
$abilities = wp_get_abilities();
$pos_abilities = array_filter(
    $abilities,
    function( $ability ) {
        return $ability->get_meta( 'category' ) === 'personalos';
    }
);

// Inspect an ability
foreach ( $pos_abilities as $ability ) {
    echo $ability->get_name() . ': ' . $ability->get_description() . "\n";
    print_r( $ability->get_input_schema() );
}
```

## Requirements

- WordPress 6.4+
- Abilities API plugin (loaded automatically in development via wp-env)
- PHP 7.4+

## Installation

The abilities-api plugin is automatically loaded in the development environment via `.wp-env.json`:

```json
{
  "plugins": [".", "WordPress/abilities-api"]
}
```

For production, install the abilities-api plugin separately until it's merged into WordPress core.

## Testing

Run unit tests:
```bash
npm run test:unit:backend
```

Test specific suites:
```bash
vendor/bin/phpunit --testsuite=unit --filter TodoAIToolsTest
vendor/bin/phpunit --testsuite=unit --filter AbilitiesAPIIntegrationTest
```

## Future Considerations

1. **Core Merge**: When Abilities API is merged into WordPress core, remove the plugin dependency
2. **REST API**: Consider exposing abilities via custom REST endpoints for advanced use cases
3. **Permissions**: May want more granular permissions per ability
4. **Streaming**: Future abilities may support streaming responses
5. **Caching**: Consider caching ability results where appropriate

## Related Links

- [WordPress Abilities API](https://github.com/WordPress/abilities-api)
- [Abilities API Handbook](https://make.wordpress.org/ai/handbook/projects/abilities-api/)
- [AI Building Blocks for WordPress](https://make.wordpress.org/ai/2025/07/17/ai-building-blocks)

## Migration Checklist

- [x] Add abilities-api to wp-env configuration
- [x] Extract inline callbacks to testable methods
- [x] Write unit tests for extracted methods
- [x] Create POS_Abilities class for registration
- [x] Register all abilities with proper schemas
- [x] Implement backward compatibility bridge
- [x] Add integration tests
- [x] Verify all tests pass
- [x] Document the migration

## Contributors

This migration was completed as part of the PersonalOS modernization effort to adopt WordPress AI Building Blocks standards.
