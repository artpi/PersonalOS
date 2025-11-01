# Email to User ID Filter

## Overview

The `pos_resolve_user_from_email` filter allows you to map email addresses to WordPress user IDs, enabling users to associate multiple email addresses with their account without changing their primary email.

This is particularly useful when:
- You want to use different emails with the AI email responder
- You have multiple email addresses (work, personal, aliases) but want them all associated with one account
- You want to override the default email resolution behavior

## Filter Hook

**Hook Name:** `pos_resolve_user_from_email`

**Since:** 0.2.5

**Parameters:**
- `int|null $user_id` - User ID to use, or null for default lookup
- `string $email` - Email address being checked
- `array $email_data` - Full email data from IMAP module

**Returns:** `int|null` - User ID to use, or null to fall back to default behavior

## Usage Examples

### Basic Example: Map a Single Email

Map a work email to your personal account:

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    if ( 'work@example.com' === $email ) {
        return 123; // Your user ID
    }
    return $user_id;
}, 10, 3 );
```

### Map Multiple Emails to One User

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    $email_mapping = array(
        'work@example.com'     => 123,
        'personal@example.com' => 123,
        'alias@example.com'    => 123,
    );
    
    if ( isset( $email_mapping[ $email ] ) ) {
        return $email_mapping[ $email ];
    }
    
    return $user_id;
}, 10, 3 );
```

### Dynamic Mapping from User Meta

Store alternate emails in user meta and map them dynamically:

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    // Query all users with alternate_emails meta
    $users = get_users( array(
        'meta_query' => array(
            array(
                'key'     => 'pos_alternate_emails',
                'value'   => $email,
                'compare' => 'LIKE',
            ),
        ),
    ) );
    
    if ( ! empty( $users ) ) {
        return $users[0]->ID;
    }
    
    return $user_id;
}, 10, 3 );
```

### Domain-Based Mapping

Map all emails from a specific domain to a user:

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    // Check if email ends with @mycompany.com
    $domain = '@mycompany.com';
    if ( substr( $email, -strlen( $domain ) ) === $domain ) {
        return 123; // Your user ID
    }
    return $user_id;
}, 10, 3 );
```

### Conditional Mapping Based on Email Content

Use the full email data to make decisions:

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    // Only map this email if it's marked as trusted
    if ( 'work@example.com' === $email && ! empty( $email_data['is_trusted'] ) ) {
        return 123;
    }
    return $user_id;
}, 10, 3 );
```

## How It Works

1. When an email arrives, the system extracts candidate email addresses from the Reply-To and From headers
2. For each candidate email, the filter is applied
3. If the filter returns a valid user ID, that user is used
4. If the filter returns null or an invalid user ID, the system falls back to the default behavior (looking up the user by their registered email address)
5. This happens for each candidate email until a user is found

## Important Notes

- **Security:** Only map emails to users when you're certain about the association. The IMAP module includes email authentication checks, but additional verification in your filter can provide extra security.
- **Priority:** If you use multiple filters, they will be called in priority order. The first non-null return value will be used.
- **Fallback:** Always return `null` (or the original `$user_id` parameter) if you don't want to override the default behavior for a particular email.
- **Performance:** If using database queries in your filter, consider caching the results to avoid performance issues.

## Debugging

To see which emails are being checked:

```php
add_filter( 'pos_resolve_user_from_email', function( $user_id, $email, $email_data ) {
    error_log( "Checking email: $email" );
    error_log( "Email data: " . print_r( $email_data, true ) );
    return $user_id; // Don't change anything, just log
}, 10, 3 );
```

## Related

- [IMAP Module Documentation](./INSTALL.md)
- [OpenAI Email Responder](./todo.md)
