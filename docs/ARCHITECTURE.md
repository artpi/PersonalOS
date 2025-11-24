# PersonalOS Architecture Guidelines

## Module Architecture

PersonalOS is built on a modular architecture where each module is self-contained and responsible for registering its own capabilities, features, and integrations.

### Key Principles

1. **Self-Contained Modules**: Each module should register its own features, hooks, abilities, and integrations within the module itself.
2. **Independent Registration**: Modules should not depend on a central registry or coordinator class. They register their capabilities directly with WordPress and external APIs.
3. **Encapsulation**: All functionality specific to a module should be contained within that module's directory.

### Module Structure

Each module extends `POS_Module` and follows this pattern:

```php
class My_Module extends POS_Module {
    public $id = 'my-module';
    public $name = 'My Module';
    
    public function register() {
        // Register post types, taxonomies, hooks, etc.
        $this->register_post_type(/* ... */);
        add_action('some_hook', array($this, 'handler'));
        
        // Register abilities (if WordPress Abilities API is available)
        if ( class_exists( 'WP_Ability' ) ) {
            add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
        }
    }
    
    public function register_abilities() {
        // Register module-specific abilities
        wp_register_ability('pos/my-ability', array(/* ... */));
    }
}
```

### Abilities Registration

Modules that provide AI capabilities register them using the WordPress Abilities API:

- **Namespace**: All PersonalOS abilities use the `pos/` namespace (e.g., `pos/todo-get-items`)
- **Category**: All abilities are categorized as `personalos`
- **Self-Registration**: Each module registers its own abilities in a `register_abilities()` method
- **Conditional Loading**: Ability registration is conditional on the Abilities API being available

Example from TODO module:

```php
public function register_abilities() {
    wp_register_ability(
        'pos/todo-get-items',
        array(
            'label' => __('Get TODO Items', 'personalos'),
            'description' => __('List TODOs from a specific notebook.', 'personalos'),
            'category' => 'personalos',
            'input_schema' => array(/* JSON Schema */),
            'output_schema' => array(/* JSON Schema */),
            'execute_callback' => array($this, 'get_items_for_openai'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'meta' => array(
                'show_in_rest' => true,
                'annotations' => array(
                    'readonly' => true,
                    'destructive' => false,
                ),
            ),
        )
    );
}
```

### Benefits of This Architecture

1. **Modularity**: Modules can be enabled/disabled independently
2. **Maintainability**: Changes to one module don't affect others
3. **Testability**: Each module's functionality can be tested in isolation
4. **Clarity**: The module's capabilities are defined where they're implemented
5. **Flexibility**: Modules can be added or removed without changing core code

### Module Discovery

Modules are discovered and loaded by the main `POS` class in `personalos.php`. Each module's `register()` method is called during WordPress initialization, allowing modules to hook into the appropriate WordPress actions and filters.

## AI Integration

### OpenAI Module

The OpenAI module (`modules/openai/class-openai-module.php`) handles AI completions and automatically discovers available abilities from all modules. It:

- Uses `wp_get_abilities()` to discover all registered PersonalOS abilities
- Converts abilities into OpenAI function calling format
- Executes abilities when called by the AI
- Does not maintain its own tool registry - relies on the Abilities API

### Tool Execution Flow

1. AI sends a completion request with function calls
2. OpenAI module discovers available abilities via `wp_get_abilities()`
3. Formats abilities for OpenAI function calling
4. When AI wants to use a function, OpenAI module:
   - Finds the corresponding ability
   - Executes it via `$ability->execute($arguments)`
   - Returns result to AI

This decentralized approach means:
- No central "POS_Abilities" coordinator class
- Modules independently register their capabilities
- AI integration happens through standard WordPress APIs
- Easy to add new modules with AI capabilities

## Adding a New Module

To add a new module with AI capabilities:

1. Create a new directory in `modules/`
2. Create a class extending `POS_Module`
3. Implement `register()` method for WordPress integration
4. If providing AI capabilities, implement `register_abilities()` method
5. Register abilities with `pos/` namespace and `personalos` category
6. Add the module to the modules list in `personalos.php`

Example minimal module:

```php
<?php
class New_Module extends POS_Module {
    public $id = 'new-module';
    public $name = 'New Module';
    
    public function register() {
        // WordPress hooks and features
        add_action('init', array($this, 'setup'));
        
        // Register abilities
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

## Testing

Each module should have its own test files in `tests/unit/` and `tests/integration/` that test its specific functionality in isolation.

See `tests/unit/TodoAIToolsTest.php` for an example of testing module abilities.
