# Slack Module - Technical Documentation

This document provides technical details for developers who want to extend or customize the Slack integration module.

## Architecture Overview

The Slack module follows a webhook-based architecture:

1. **Incoming Webhook**: Slack sends events to `/wp-json/pos/v1/slack/callback`
2. **Immediate Response**: The handler responds quickly to Slack (avoiding timeout)
3. **Background Processing**: Actual AI processing happens via WordPress cron
4. **Outgoing API Call**: Bot responds in the thread via Slack's `chat.postMessage` API

## REST API Endpoints

### POST `/wp-json/pos/v1/slack/callback`

Main webhook endpoint for Slack events.

**Authentication**: Validates `token` field against stored verification token.

**Supported Event Types**:
- `url_verification` - Slack URL verification challenge
- `event_callback` - Standard event wrapper containing:
  - `app_mention` - Bot was mentioned
  - `message.im` - Direct message to bot

**Response**: Immediate `200 OK` with `{ "text": "Processing your request..." }`

**Example Payload**:
```json
{
  "token": "verification-token",
  "type": "event_callback",
  "event": {
    "type": "app_mention",
    "user": "U1234567890",
    "text": "<@U0BOT1234> Hello!",
    "ts": "1234567890.123456",
    "channel": "C1234567890",
    "thread_ts": "1234567890.123456"
  }
}
```

## Action Hooks

### `pos_process_slack_callback`

Fires during background processing of a Slack event. This is scheduled via `wp_schedule_single_event()`.

**Parameters**:
- `$payload` (array): Full Slack event payload

**Example Usage**:
```php
add_action( 'pos_process_slack_callback', function( $payload ) {
    // Custom processing logic
    $channel = $payload['event']['channel'];
    $text = $payload['event']['text'];
    
    // Your custom handling here
}, 5 ); // Priority 5 runs before default handler (10)
```

## Channel-Specific Prompts

### How It Works

The module queries the Notes module for prompts that have a `slack_channel_id` meta field matching the incoming channel.

```php
$matching_prompts = $notes->list( 
    array( 
        'meta_query' => array( 
            array( 
                'key' => 'slack_channel_id', 
                'value' => $payload['event']['channel'] 
            ) 
        ) 
    ), 
    'prompts' 
);
```

### Meta Field Setup

To link a prompt to a Slack channel:

1. Create a note in the `prompts` taxonomy
2. Add post meta:
   ```php
   update_post_meta( $prompt_id, 'slack_channel_id', 'C0123456789' );
   ```

### Multiple Prompts per Channel

If multiple prompts match a channel, one is randomly selected:

```php
$prompt = $matching_prompts[ array_rand( $matching_prompts ) ];
```

This allows for varied responses when multiple prompts are configured.

## Public Methods

### `slack_gpt_retrieve_backscroll( $thread, $channel )`

Retrieves conversation history from a Slack thread.

**Parameters**:
- `$thread` (string): Thread timestamp (`ts` or `thread_ts`)
- `$channel` (string): Channel ID

**Returns**: array - Array of messages formatted for GPT:
```php
array(
    array(
        'role'    => 'user',      // or 'assistant' for bot messages
        'content' => 'Message text without @mentions'
    ),
    // ... more messages
)
```

### `slack_gpt_respond_in_thread( $ts, $channel, $response )`

Posts a response to a Slack thread with automatic markdown conversion.

**Parameters**:
- `$ts` (string): Thread timestamp to reply to
- `$channel` (string): Channel ID
- `$response` (string): Message content (markdown supported)

**Markdown Conversion**:
- `[text](url)` → `<url|text>` (Slack link format)
- `**bold**` or `__bold__` → `*bold*`
- `` `code` `` → `` `code` `` (preserved)
- `*italic*` or `_italic_` → `_italic_`

### `slack_message_to_gpt_message( $message )`

Converts a Slack message object to GPT message format.

**Parameters**:
- `$message` (object): Slack message object

**Returns**: array
```php
array(
    'role'    => 'user',     // 'assistant' if message has bot_id
    'content' => 'text'      // @mentions stripped
)
```

## Integration with OpenAI Module

The Slack module calls the OpenAI module's `complete_backscroll()` method:

```php
$openai = POS::get_module_by_id( 'openai' );
$response = $openai->complete_backscroll( $backscroll, null, $prompt );
```

**Parameters**:
- `$backscroll` (array): Conversation history with `old => true` flag
- `$callback` (callable|null): Not used for Slack
- `$prompt` (WP_Post|null): Optional custom prompt post

The `old => true` flag marks messages as historical context, not requiring a response.

## Configuration

Settings are stored in WordPress options with the prefix `pos_slack_`.

### Available Settings

- `pos_slack_slack_token` - Slack verification token for webhook validation
- `pos_slack_api_token` - Bot User OAuth Token (`xoxb-...`) for API calls

## Bot Filtering

The module skips processing for bot messages to prevent loops:

```php
if ( ! empty( $payload['event']['bot_id'] ) ) {
    return;
}
```

## Background Processing

To avoid Slack's 3-second timeout, processing is deferred:

1. Webhook immediately returns `200 OK`
2. Event is scheduled via `wp_schedule_single_event()`
3. Cron is triggered immediately via `wp_remote_post()` to `/wp-cron.php`

```php
wp_schedule_single_event( time(), 'pos_process_slack_callback', array( $payload ) );
wp_remote_post(
    site_url( '/wp-cron.php' ),
    array(
        'timeout'   => 0.01,
        'blocking'  => false,
        'sslverify' => false,
    )
);
```

## Debugging

Enable WordPress debug logging to see Slack module activity:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

The module logs:
- Callback payloads: `pos_process_slack_callback:{json}`
- Token validation failures

Check `wp-content/debug.log` for details.

## Extending the Module

### Custom Response Handler

To customize how responses are generated:

```php
// Remove default handler
remove_action( 'pos_process_slack_callback', array( $slack_module, 'pos_process_slack_callback' ) );

// Add custom handler
add_action( 'pos_process_slack_callback', 'my_custom_slack_handler' );

function my_custom_slack_handler( $payload ) {
    $slack = POS::get_module_by_id( 'slack' );
    
    // Your custom logic here
    $response = "Custom response";
    
    $slack->slack_gpt_respond_in_thread(
        $payload['event']['thread_ts'] ?? $payload['event']['ts'],
        $payload['event']['channel'],
        $response
    );
}
```

### Adding Prompt Selection Logic

To implement custom prompt selection (e.g., based on user or message content):

```php
add_action( 'pos_process_slack_callback', 'my_prompt_selector', 5 );

function my_prompt_selector( $payload ) {
    // Store selected prompt in a global or option
    // The default handler will use it
}
```

## Security Considerations

1. **Token Validation**: All incoming webhooks validate the verification token
2. **User Context**: Processing switches to the appropriate WordPress user via `$notes->switch_to_user()`
3. **Bot Filtering**: Bot messages are ignored to prevent loops

## Future Improvements (TODOs)

The following enhancements are planned:

1. **Frontend UX**: Admin interface for managing channel-prompt associations
2. **Starter Content**: Default prompts for common Slack use cases
3. **OAuth Integration**: Replace hardcoded user switching with proper Slack-to-WordPress user mapping

