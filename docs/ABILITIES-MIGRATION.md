# OpenAI Tools to WordPress Abilities API Migration

## Overview

This document describes the migration of PersonalOS AI tools from the custom `OpenAI_Tool` system to the WordPress Abilities API with a decentralized, self-contained module architecture.

## Background

PersonalOS originally implemented AI capabilities using a custom `OpenAI_Tool` class and a `pos_openai_tools` filter. While functional, this approach was:
- Non-standard and specific to PersonalOS
- Not discoverable by other WordPress plugins
- Lacking standardized schemas and permissions
- Difficult to integrate with emerging AI assistant protocols
- Centralized with modules depending on a coordinator class

The WordPress Abilities API provides a standardized way to register and execute capabilities, making them discoverable and interoperable with other WordPress plugins and AI systems. The migration also adopts a decentralized architecture where each module is self-contained and registers its own capabilities.

## Migration Goals

1. Convert all existing OpenAI tools to WordPress Abilities API format
2. Use standardized `pos/` namespace for all abilities
3. Categorize all abilities under `personalos` category
4. Adopt decentralized, self-contained module architecture
5. Remove dependency on OpenAI_Tool - use abilities directly
6. Ensure all code is testable and follows WordPress coding standards

## What Changed

### Architecture

**Before (Centralized with OpenAI_Tool):**
```php
// Central tool registration
add_filter('pos_openai_tools', function($tools) {
    $tools[] = new OpenAI_Tool('todo_get_items', ...);
    return $tools;
});

// OpenAI module uses tools
$tools = OpenAI_Tool::get_tools();
```

**After (Decentralized, Self-Contained):**
```php
// Each module registers its own abilities
class TODO_Module extends POS_Module {
    public function register() {
        // ... other registration
        if (class_exists('WP_Ability')) {
            add_action('wp_abilities_api_init', array($this, 'register_abilities'));
        }
    }
    
    public function register_abilities() {
        wp_register_ability('pos/todo-get-items', [
            'execute_callback' => array($this, 'get_items_for_openai'),
            // ... other config
        ]);
    }
}

// OpenAI module uses abilities directly
$abilities = wp_get_abilities();
foreach ($abilities as $ability) {
    if (strpos($ability->get_name(), 'pos/') === 0) {
        // Use ability directly via $ability->execute()
    }
}
```

### Key Changes

1. **Removed `class-pos-abilities.php`**: Deleted the central coordinator class entirely
2. **Module Self-Registration**: Each module now registers abilities in its own `register_abilities()` method
3. **Abilities Only**: All functionality uses abilities directly - no OpenAI_Tool bridge
4. **Direct Execution**: `complete_backscroll()` and `complete_responses()` use abilities via `wp_get_abilities()` and `$ability->execute()`
5. **Documentation**: Created `docs/ARCHITECTURE.md` documenting the self-contained pattern

## Module Architecture

### File Structure

**Deleted Files:**
- `class-pos-abilities.php` - Central coordinator class (no longer needed)

**New Files:**
- `docs/ARCHITECTURE.md` - Architecture documentation
- `tests/unit/AbilitiesAPIIntegrationTest.php` - Integration tests
- `tests/unit/OpenAIModuleAIToolsTest.php` - Unit tests for OpenAI module tools
- `tests/unit/NotesModuleAIToolsTest.php` - Unit tests for Notes module tools

**Modified Files:**
- All module files (added `register_abilities()` methods)
- `personalos.php` - Removed POS_Abilities initialization
- `.wp-env.json` - Added abilities-api plugin dependency
- `modules/openai/class-openai-module.php` - Updated to use abilities directly instead of OpenAI_Tool

## Benefits of New Architecture

1. **True Modularity**: Each module is completely self-contained
2. **No Dependencies**: Modules don't depend on a central registry
3. **Easy to Add**: New modules can be added without touching core code
4. **Clear Ownership**: Each module owns its capabilities
5. **Better Testing**: Modules can be tested in isolation
6. **Standards Compliant**: Uses WordPress-standard APIs throughout
7. **Simpler Code**: Direct use of abilities API without adapter layers

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

## Migration Complete

All tools have been migrated to abilities. The old `OpenAI_Tool` system is no longer used. All functionality now uses the WordPress Abilities API directly.

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

### For Module Developers

To add a new module with AI capabilities:

```php
class New_Module extends POS_Module {
    public function register() {
        // ... other registration
        
        if (class_exists('WP_Ability')) {
            add_action('wp_abilities_api_init', array($this, 'register_abilities'));
        }
    }
    
    public function register_abilities() {
        wp_register_ability(
            'pos/new-capability',
            array(
                'label' => __('New Capability', 'personalos'),
                'description' => __('Does something useful', 'personalos'),
                'category' => 'personalos',
                'execute_callback' => array($this, 'do_something'),
                'permission_callback' => '__return_true',
            )
        );
    }
    
    public function do_something($input) {
        // Implementation
        return array('result' => 'success');
    }
}
```

See `docs/ARCHITECTURE.md` for complete guidelines.

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
- [x] Register all abilities with proper schemas in modules
- [x] Update OpenAI module to use abilities directly
- [x] Add integration tests
- [x] Verify all tests pass
- [x] Document the migration

## Contributors

This migration was completed as part of the PersonalOS modernization effort to adopt WordPress AI Building Blocks standards.
