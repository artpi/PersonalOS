# Slack Module - User Guide

Welcome to the Slack Module for PersonalOS! This module enables AI-powered conversations directly in your Slack workspace.

## What This Module Does

The Slack module connects PersonalOS to your Slack workspace and:
- **Responds to mentions**: When you @mention your bot, it responds with AI-generated messages
- **Handles direct messages**: DMs to your bot trigger AI responses
- **Maintains conversation context**: Threaded conversations preserve full context
- **Supports channel-specific prompts**: Different Slack channels can use different AI personalities/prompts

## Requirements

1. A Slack workspace where you have permission to install apps
2. The OpenAI module must be configured and active
3. The Notes module must be active (for channel-specific prompts)

## Setup Instructions

### Step 1: Create a Slack App

1. Go to [api.slack.com/apps](https://api.slack.com/apps)
2. Click **Create New App** → **From scratch**
3. Enter a name (e.g., "PersonalOS Bot") and select your workspace
4. Click **Create App**

### Step 2: Configure Bot Permissions

1. In your app settings, go to **OAuth & Permissions**
2. Under **Scopes** → **Bot Token Scopes**, add these permissions:
   - `app_mentions:read` - Read messages that mention the bot
   - `channels:history` - Read messages in public channels
   - `chat:write` - Send messages as the bot
   - `groups:history` - Read messages in private channels
   - `im:history` - Read direct messages

3. Click **Install to Workspace** and authorize the app
4. Copy the **Bot User OAuth Token** (starts with `xoxb-`)

### Step 3: Set Up Event Subscriptions

1. Go to **Event Subscriptions** in your app settings
2. Toggle **Enable Events** to ON
3. For the **Request URL**, enter:
   ```
   https://your-wordpress-site.com/wp-json/pos/v1/slack/callback
   ```
4. Wait for Slack to verify the URL (PersonalOS handles this automatically)

5. Under **Subscribe to bot events**, add:
   - `app_mention` - When someone mentions your bot
   - `message.im` - When someone sends a direct message to your bot

6. Click **Save Changes**

### Step 4: Get Your Verification Token

1. Go to **Basic Information** in your app settings
2. Under **App Credentials**, find the **Verification Token**
3. Copy this token

### Step 5: Configure PersonalOS

1. Go to **PersonalOS Settings** in your WordPress admin
2. Find the **Slack integration** section
3. Enter:
   - **Slack Verification Token**: The verification token from Step 4
   - **Slack API Token**: The Bot User OAuth Token from Step 2
4. Click **Save Changes**

### Step 6: Test It

1. Invite your bot to a channel: `/invite @YourBotName`
2. Send a message mentioning your bot: `@YourBotName Hello!`
3. The bot should respond with an AI-generated message

## Using Channel-Specific Prompts

One of the most powerful features is the ability to assign different AI prompts to different Slack channels. This lets you create specialized bots for different purposes.

### How It Works

1. Create a prompt in PersonalOS (under Notes → Prompts notebook)
2. Add a custom field `slack_channel_id` with the Slack channel ID
3. When messages come from that channel, the bot uses your custom prompt

### Step-by-Step Setup

#### 1. Find Your Slack Channel ID

- Open Slack in a browser
- Navigate to the channel you want to customize
- The channel ID is in the URL: `https://app.slack.com/client/TXXXXXX/CXXXXXXXXX`
- The `CXXXXXXXXX` part is your channel ID

**Or via Slack:**
- Right-click on the channel name
- Select "View channel details"
- Scroll to the bottom to find the Channel ID

#### 2. Create a Custom Prompt

1. In WordPress, go to **Notes** and create a new note
2. Add it to the **Prompts** notebook (taxonomy)
3. Write your custom system prompt, for example:
   ```
   You are a helpful coding assistant. Focus on providing clear, 
   well-documented code examples. Always explain your reasoning.
   ```

#### 3. Link the Prompt to a Channel

1. In the WordPress editor, add a custom field to your prompt:
   - Field name: `slack_channel_id`
   - Field value: Your channel ID (e.g., `C0123456789`)

2. Save the prompt

#### 4. Test It

Send a message in that Slack channel mentioning your bot. It should now respond using your custom prompt!

### Example Use Cases

| Channel | Prompt Purpose |
|---------|----------------|
| #coding-help | Technical assistant focused on code review and debugging |
| #writing | Creative writing assistant with editorial voice |
| #general | Friendly, casual conversational assistant |
| #customer-support | Professional support agent tone |

## Features

### Threaded Conversations

When you reply in a Slack thread, the bot retrieves the full conversation history and uses it as context. This means:
- Follow-up questions work naturally
- The bot remembers what was discussed earlier in the thread
- Long conversations maintain coherence

### Markdown Conversion

The bot automatically converts AI responses from markdown to Slack's formatting:
- `**bold**` becomes `*bold*`
- `[links](url)` become `<url|links>`
- Code blocks are preserved

### Bot Message Filtering

The bot ignores its own messages and messages from other bots to prevent infinite loops.

## Troubleshooting

**"Bot doesn't respond to messages"**
- Verify the bot is invited to the channel
- Check that Event Subscriptions URL is verified
- Ensure both tokens are correctly entered in PersonalOS settings
- Check WordPress debug log for errors

**"Getting 403 errors"**
- Verify the Verification Token matches exactly
- Make sure your WordPress site is accessible from the internet (not localhost)

**"Bot responds but with generic answers"**
- Check if your channel-specific prompt is properly linked
- Verify the `slack_channel_id` custom field is set correctly
- The channel ID must match exactly (case-sensitive)

**"Thread context isn't working"**
- Ensure the bot has `channels:history` and `groups:history` permissions
- The bot must be a member of the channel to read history

---

## Technical Details

For developers who want to extend or customize the Slack module, see [TECHNICAL.md](TECHNICAL.md) for hook documentation and advanced configuration options.

