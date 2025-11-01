# IMAP Email Module - User Guide

Welcome to the IMAP Email Module for PersonalOS! This module allows you to receive and automatically respond to emails using AI.

## What This Module Does

The IMAP module connects to your email inbox and:
- **Checks for new emails** every minute automatically
- **Verifies sender authenticity** using email authentication (DMARC/DKIM/SPF)
- **Responds automatically** to verified emails using AI
- **Prevents loops** by tracking which emails have been processed

## Requirements

Your web server needs the PHP IMAP extension installed. Most hosting providers have this enabled by default.

## Setup Instructions

### Step 1: Get Your Email Settings

You'll need to collect these details from your email provider:

**For Gmail:**
- IMAP Server: `imap.gmail.com`
- IMAP Port: `993`
- SMTP Server: `smtp.gmail.com`
- SMTP Port: `587`
- **Important**: You need to generate an "App Password" (not your regular Gmail password)
  - Go to Google Account → Security → 2-Step Verification → App Passwords
  - Generate a new app password for "Mail"
  - Use this password in the settings below

**For Other Providers:**
Contact your email provider or check their documentation for IMAP/SMTP settings.

### Step 2: Configure the Module

1. Go to **PersonalOS Settings** in your WordPress admin
2. Find the **IMAP Email** section
3. Fill in the following settings:

**IMAP Settings (for receiving emails):**
- **IMAP Server**: Your email provider's IMAP server address
- **IMAP Port**: Usually `993` for secure connections
- **IMAP Username**: Your full email address
- **IMAP Password**: Your email password or app-specific password
- **Use SSL**: Keep this checked (recommended for security)

**SMTP Settings (for sending emails):**
- **SMTP Server**: Your email provider's SMTP server address
- **SMTP Port**: Usually `587` for TLS or `465` for SSL
- **SMTP Username**: Usually the same as your IMAP username
- **SMTP Password**: Usually the same as your IMAP password

**Activation:**
- **IMAP Sync Active**: Check this box to start checking your inbox

4. Click **Save Changes**

### Step 3: Test It

Send a test email to the email address you configured. Within a minute, the system should:
1. Detect the new email
2. Verify it's from a trusted sender
3. Generate an AI response
4. Send the reply back to you

## How the AI Responder Works

When a verified email arrives:

1. **Classification**: The AI first checks if it's an auto-responder, spam, or system message. These are automatically skipped.

2. **User Matching**: The system looks for a WordPress user account matching the sender's email address. Only emails from registered users get auto-replies.

3. **AI Generation**: The AI reads your email and generates a personalized response based on the content.

4. **Threading**: Replies maintain proper email threading (using "Re:" prefix and email headers) so conversations stay organized in your inbox.

5. **No Loops**: The system tracks processed emails and won't reply to the same message twice or respond to auto-responders.

## Security Notes

- **Use App-Specific Passwords**: For Gmail and other providers with 2-factor authentication, always use app-specific passwords instead of your main password.

- **Verified Senders Only**: The AI only responds to emails from senders that pass email authentication checks (DMARC, DKIM, or SPF). This prevents spoofed emails.

- **User Account Required**: Auto-replies only go to email addresses registered in your WordPress user accounts.

- **SSL/TLS Encryption**: Always use SSL/TLS for both IMAP and SMTP connections to protect your credentials and email content.

## Troubleshooting

**"No emails are being processed"**
- Check that "IMAP Sync Active" is enabled
- Verify your IMAP credentials are correct
- Make sure your server has the PHP IMAP extension
- Check error logs for connection issues

**"AI isn't responding to emails"**
- Verify the sender's email matches a WordPress user account
- Check that the email passes authentication (look for verification logs)
- Ensure OpenAI API is configured and has credits available

**"Getting authentication errors"**
- For Gmail, make sure you're using an App Password, not your regular password
- Check that IMAP access is enabled in your email provider settings
- Verify the port numbers are correct (993 for IMAP, 587/465 for SMTP)

---

## Technical Details

For developers who want to extend or customize the IMAP module, see [TECHNICAL.md](TECHNICAL.md) for hook documentation and advanced configuration options.
