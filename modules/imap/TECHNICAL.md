# IMAP Module - Technical Documentation

This document provides technical details for developers who want to extend or customize the IMAP email module.

## Action Hooks

The module provides two action hooks that fire when emails are received:

### `pos_imap_new_email`

Fires when a verified/authenticated email is received (DMARC, DKIM, or SPF pass).

**Parameters:**
- `$email_data` (array): Email data with authentication passed
- `$imap_module` (object): IMAP module instance for sending replies

**Email Data Structure:**
```php
array(
    'id'         => int,           // Email ID from IMAP
    'subject'    => string,        // Sanitized subject
    'from'       => string,        // Sender email (sanitized)
    'from_name'  => string,        // Sender display name
    'date'       => string,        // Email date
    'body'       => string,        // Plain text body
    'reply_to'   => array,         // Reply-To addresses
    'message_id' => string,        // Message-ID header
    'references' => string,        // References header
    'is_trusted' => bool,          // Always true in this hook
    'auth'       => array(         // Authentication details
        'is_trusted'          => bool,
        'summary'             => string,
        'authserv'            => string,
        'dmarc'               => string,
        'dkim'                => string,
        'dkim_domain'         => string,
        'spf'                 => string,
        'spf_domain'          => string,
        'return_path'         => string,
        'return_path_domain'  => string,
        'return_path_aligned' => bool,
        'auth_headers'        => array,
    ),
)
```

**Example Usage:**
```php
add_action( 'pos_imap_new_email', function( $email_data, $imap_module ) {
    // Process verified email
    $from = $email_data['from'];
    $subject = $email_data['subject'];
    
    // Send a reply
    $imap_module->send_email(
        $from,
        'Re: ' . $subject,
        'Your response here'
    );
}, 10, 2 );
```

### `pos_imap_new_email_unverified`

Fires when an unverified/unauthenticated email is received (authentication failed).

**Parameters:**
- `$email_data` (array): Same structure as above, but `is_trusted` is always false
- `$imap_module` (object): IMAP module instance

**Example Usage:**
```php
add_action( 'pos_imap_new_email_unverified', function( $email_data, $imap_module ) {
    // Log unverified email
    error_log( 'Unverified email from: ' . $email_data['from'] );
}, 10, 2 );
```

## Security Features

### Email Authentication Verification

The module automatically validates:
- **DMARC** (Domain-based Message Authentication, Reporting & Conformance)
- **DKIM** (DomainKeys Identified Mail) 
- **SPF** (Sender Policy Framework)

Authentication results are included in the `auth` array of email data.

### Loop Detection

The module uses WordPress transients to track processed Message-IDs for 24 hours. This prevents:
- Processing the same email multiple times
- Infinite loops if your code sends emails back to the monitored inbox

Transient keys follow the pattern: `pos_imap_processed_{md5_of_message_id}`

## Module Methods

### Public Methods

#### `send_email( $to, $subject, $body, $headers = array() )`

Send an email via SMTP.

**Parameters:**
- `$to` (string): Recipient email address
- `$subject` (string): Email subject
- `$body` (string): Email body (plain text or HTML)
- `$headers` (array): Optional email headers

**Returns:** bool - True on success, false on failure

**Example:**
```php
$imap_module = POS::get_module_by_id( 'imap' );
$imap_module->send_email(
    'user@example.com',
    'Hello',
    'Email body',
    array(
        'Reply-To: noreply@example.com',
        'X-Custom-Header: value'
    )
);
```

#### `get_default_from_address()`

Get the configured From address for outgoing emails.

**Returns:** string - Email address

## AI Email Responder

The OpenAI Email Responder (`OpenAI_Email_Responder` class) automatically responds to verified emails.

### Features

1. **AI Classification**: Uses GPT-4.1-mini to classify emails and skip:
   - Auto-responders (out of office, vacation replies)
   - Spam and marketing emails
   - Delivery failure notifications
   - Automated system messages

2. **User Matching**: Only responds to emails from registered WordPress users

3. **Conversation Persistence**: Email conversations are saved as notes in the `ai-chats` notebook:
   - Uses the OpenAI Responses API with `store => true`
   - Maintains `pos_last_response_id` for conversation context
   - Conversations can be viewed and continued in the web chat interface

4. **Proper Threading**: Maintains email thread with:
   - "Re:" prefix in subject
   - `[post_id]` in subject for conversation tracking
   - In-Reply-To and References headers
   - Quoted original message in body

5. **Prompt Selection**: Use `#prompt-slug` in the email subject to select a specific AI prompt from the `prompts-chat` notebook.

### Email Subject Parsing

The responder parses two special patterns from email subjects:

#### Prompt Selection: `#prompt-slug`

Include `#prompt-slug` anywhere in the subject line to use a specific prompt:

```
Subject: Help me with code #coding-assistant
```

This will look up the prompt with slug `coding-assistant` from the `prompts-chat` notebook. If not found, the default prompt is used.

#### Conversation Threading: `[post_id]`

The responder includes `[post_id]` in reply subjects to enable conversation continuation:

```
Subject: Re: Help me with code #coding-assistant [123]
```

When a follow-up email arrives with `[123]` in the subject:
1. The existing conversation note (post ID 123) is loaded
2. `previous_response_id` from post meta provides context to OpenAI
3. New messages are appended to the same conversation

### Customization

To customize the AI responder behavior, you can:

1. **Filter the classification prompt** (hook not yet implemented)
2. **Filter the response generation** (hook not yet implemented)
3. **Filter email-to-user mapping**: Use the `pos_resolve_user_from_email` filter to map additional email addresses to WordPress users
4. **Unhook the default responder and create your own:**

```php
// Remove default responder
remove_action( 'pos_imap_new_email', array( $openai_responder, 'handle_new_email' ), 20 );

// Add custom responder
add_action( 'pos_imap_new_email', 'my_custom_email_handler', 20, 2 );
function my_custom_email_handler( $email_data, $imap_module ) {
    // Your custom logic here
}
```

#### `pos_resolve_user_from_email` Filter

Filter to map email addresses to WordPress users. This allows users to associate additional email addresses with their account without changing their primary email.

**Parameters:**
- `$user` (WP_User|false|null): WP_User object if found by email lookup, false if not found, or null
- `$email` (string): Email address being checked
- `$email_data` (array): Full email data from IMAP module

**Returns:** WP_User|false|null
- Return a WP_User object to override the default email lookup
- Return null, false, or any non-WP_User value to skip this email address (continue to next candidate or skip email)

**Since:** 0.2.5

**Example Usage:**
```php
add_filter( 'pos_resolve_user_from_email', function( $user, $email, $email_data ) {
    // Map custom work email to user ID 5
    if ( 'work@example.com' === $email ) {
        return get_user_by( 'id', 5 );
    }
    
    // Map all @mycompany.com emails to current user
    if ( str_ends_with( $email, '@mycompany.com' ) ) {
        return wp_get_current_user();
    }
    
    // Use default behavior (return original $user)
    return $user;
}, 10, 3 );
```

**Note:** The filter is called for each email candidate (reply-to addresses first, then from address). If the filter returns a valid WP_User object, that user is used. If it returns null, false, or any invalid value, the resolver continues to the next candidate or skips the email entirely if no valid user is found.

## Configuration

All settings are stored in WordPress options with the prefix `pos_imap_`.

### Available Settings

- `pos_imap_imap_host` - IMAP server hostname
- `pos_imap_imap_port` - IMAP port (default: 993)
- `pos_imap_imap_username` - IMAP username
- `pos_imap_imap_password` - IMAP password
- `pos_imap_imap_ssl` - Use SSL/TLS (default: true)
- `pos_imap_smtp_host` - SMTP server hostname
- `pos_imap_smtp_port` - SMTP port (default: 587)
- `pos_imap_smtp_username` - SMTP username
- `pos_imap_smtp_password` - SMTP password
- `pos_imap_active` - Enable/disable sync (default: false)

## Cron Schedule

The module registers a custom "minutely" cron schedule that runs every 60 seconds.

**Cron Hook:** `pos_sync_imap`

## Best Practices

1. **Always check authentication**: Even though `pos_imap_new_email` only fires for verified emails, always validate sender identity for sensitive operations.

2. **Rate limiting**: Consider implementing rate limits on auto-replies to prevent abuse.

3. **User consent**: Ensure users understand that their emails will trigger automated responses.

4. **Error handling**: Always wrap email operations in try-catch blocks and handle failures gracefully.

5. **Testing**: Test thoroughly with various email clients and authentication scenarios.

## Debugging

Enable WordPress debug logging to see IMAP module activity:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

The module logs:
- Connection attempts
- Authentication results
- Email processing
- Send success/failure

Check `wp-content/debug.log` for details.
