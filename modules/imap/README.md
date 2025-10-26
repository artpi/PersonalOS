# IMAP Email Module

This module provides IMAP email checking and sending functionality for PersonalOS.

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

## Usage

### Receiving Emails

Once activated, the module will check your inbox every minute. For each new email, it triggers the `pos_imap_new_email` action with email data:

```php
add_action( 'pos_imap_new_email', function( $email_data ) {
    // $email_data contains:
    // - 'subject': Email subject
    // - 'from': Sender email address
    // - 'body': Email body
    // - 'date': Email date
    // - 'id': Email ID
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
