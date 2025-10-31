# IMAP Email Module

This module provides IMAP email checking and sending functionality for PersonalOS.

## Requirements

This module requires the PHP IMAP extension to be installed.

## Features

- **Automatic Email Checking**: Checks your inbox every minute via wp-cron
- **Email Actions**: Triggers a `pos_imap_new_email` action for each email retrieved
- **Email Sending**: Send emails using PHPMailer with SMTP
- **Email Logging**: Logs email subject, sender, and body to error_log

## Configuration

Configure the module settings in PersonalOS Settings:

### Security Notes

- **Credentials Storage**: Credentials are stored in WordPress options. Use app-specific passwords when available (e.g., Gmail App Passwords) instead of your main account password.
- **SSL/TLS**: Always use SSL/TLS for IMAP and SMTP connections to protect credentials in transit.
- **Sensitive Data**: Email bodies may contain sensitive information. Be cautious when processing or storing email content.
- **Email Authentication**: The module checks DMARC, DKIM, and SPF authentication headers to verify sender identity.
- **Access Control**: Enable "Require Trusted Sender" to block emails that fail authentication checks.
- **Loop Prevention**: Automatic detection prevents processing the same email multiple times within 24 hours.

### IMAP Settings (for receiving emails)

- **IMAP Server**: Your IMAP server hostname (e.g., `imap.gmail.com`)
- **IMAP Port**: Usually `993` for SSL
- **IMAP Username**: Your email address
- **IMAP Password**: Your email password or app-specific password
- **Use SSL**: Enable SSL/TLS connection (recommended)

### SMTP Settings (for sending emails)

- **SMTP Server**: Your SMTP server hostname (e.g., `smtp.gmail.com`)
- **SMTP Port**: Usually `587` for TLS or `465` for SSL
- **SMTP Username**: Usually same as IMAP username
- **SMTP Password**: Usually same as IMAP password

### Activation

- **IMAP Sync Active**: Enable to start automatic email checking

## Security Features

### Email Authentication Verification

The module automatically checks email authentication headers:
- **DMARC**: Domain-based Message Authentication, Reporting & Conformance
- **DKIM**: DomainKeys Identified Mail
- **SPF**: Sender Policy Framework

Each processed email includes an `is_trusted` flag and detailed authentication data. Emails are routed to different action hooks based on their authentication status.

### Loop Detection

The module tracks processed Message-IDs using WordPress transients (24-hour expiration) to prevent infinite loops if your email processing sends emails back to the monitored inbox.

### Auto-Responder Detection

The AI email responder automatically detects and skips auto-responders based on:
- Common auto-responder subject patterns (out of office, automatic reply, etc.)
- Auto-responder email addresses (noreply@, mailer-daemon@, etc.)
- Auto-responder headers (Auto-Submitted, X-Autoresponse, etc.)

### Action Hook Security

The module provides separate action hooks based on email authentication status:

**`pos_imap_new_email`** - Triggered only for verified/authenticated emails (DMARC/DKIM/SPF pass)

```php
add_action( 'pos_imap_new_email', function( $email_data ) {
    // This email has passed authentication checks
    // Safe to process sensitive actions
    // Hook handlers decide what security they need
    // ...
}, 10, 1 );
```

**`pos_imap_new_email_unverified`** - Triggered only for unverified/unauthenticated emails

```php
add_action( 'pos_imap_new_email_unverified', function( $email_data ) {
    // This email failed authentication checks
    // Be cautious - may be spoofed
    // Only use for logging, notifications, or non-sensitive actions
    error_log( 'Unverified email from: ' . $email_data['from'] );
}, 10, 1 );
```

Both hooks receive the same email data structure with authentication details included. It's up to the hook handlers to decide what additional security checks they need.

## Usage

### Receiving Emails

Once activated, the module will check your inbox every minute. For verified emails, it triggers the `pos_imap_new_email` action. For unverified emails, it triggers `pos_imap_new_email_unverified`:

```php
// Handle verified emails only (recommended for sensitive operations)
add_action( 'pos_imap_new_email', function( $email_data ) {
    // $email_data contains:
    // - 'subject': Email subject (sanitized)
    // - 'from': Sender email address (sanitized)
    // - 'from_name': Sender display name
    // - 'body': Email body (plain text)
    // - 'date': Email date
    // - 'id': Email ID
    // - 'reply_to': Array of Reply-To addresses
    // - 'message_id': Message-ID header
    // - 'references': References header
    // - 'is_trusted': Boolean - always true in this hook
    // - 'auth': Array with detailed authentication info
    //   - 'summary': Human-readable auth status
    //   - 'dmarc', 'dkim', 'spf': Authentication results
    //   - 'dkim_domain', 'spf_domain': Authenticated domains
    
    // Process verified email...
}, 10, 1 );

// Handle unverified emails separately (optional - for logging/monitoring)
add_action( 'pos_imap_new_email_unverified', function( $email_data ) {
    // Same structure as above, but is_trusted is always false
    // Use with caution - email may be spoofed
}, 10, 1 );
```

### Sending Emails

To send an email:

```php
$imap_module = POS::get_module_by_id( 'imap' );
$imap_module->send_email(
    'recipient@example.com',
    'Subject',
    'Email body content'
);
```

## Gmail Configuration

For Gmail, you'll need to:

1. Enable IMAP in Gmail settings
2. Generate an App Password (if using 2FA)
3. Use these settings:
   - IMAP Server: `imap.gmail.com`
   - IMAP Port: `993`
   - SMTP Server: `smtp.gmail.com`
   - SMTP Port: `587`

## Future Enhancements

- Email attachment handling
- Email filtering and processing rules
- Integration with other PersonalOS modules
