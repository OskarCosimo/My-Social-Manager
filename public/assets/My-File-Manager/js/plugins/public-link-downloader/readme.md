# Public Link Generator Plugin for MyFileManager

A powerful plugin for MyFileManager that enables users to generate secure, time-limited public download links for files with customizable options including wait times, download limits, and access restrictions.

## Features

- 🔗 **Generate Public Download Links** - Create shareable links for any file
- ⏰ **Configurable Expiration** - Set link expiration from 5 minutes to 3 hours
- 🛡️ **Anti-spam Wait Time** - Force users to wait before downloading (30 seconds to 5 minutes)
- 📊 **Download Limits** - Optionally limit the number of downloads per link
- 🔒 **Access Control** - Choose between public access or registered users only
- 📋 **Link Management** - View, copy, and delete all your active links
- 🎯 **Auto-cleanup** - Expired links are automatically removed
- 🔐 **Secure Tokens** - 64-character cryptographically secure tokens

## Installation

### 1. Copy Plugin Files

Copy the following files to your MyFileManager installation:

```
myfilemanager/
├── src/
│   ├── plugins/
│   │   └── publiclinks.php          # Backend plugin
│   ├── js/
│   │   └── public-link-plugin.js    # Frontend plugin
│   └── download.php                  # Public download page
```

### 2. Configure Backend

In your `connector.php`, add the plugin configuration:

```php
'plugins' => [
    'publiclinks' => [
        'enabled' => true,
        'download_url' => '/path/to/download.php'  // Optional: custom download page URL
    ],
],
```

In `plugins/publiclinks.php`, configure the storage directory for the json files:

```php
class PubliclinksPlugin {
    // Configure this path to store link data
    const PUBLIC_LINKS_DIR = '/path/to/your/storage/public_links'; //the path for the store location of the json files
    const DOWNLOAD_URL = '/download.php';  // Default download URL

    // ... rest of the code
}
```

### 3. Configure Download Page

In `download.php`, set the storage directory for the json files:

```php
// Configure this path (must match the plugin configuration)
define('PUBLIC_LINKS_DIR', '/path/to/your/storage/public_links'); //the path for the store location of the json files
define('DISPLAY_TIMEZONE', 'UTC');  // Or 'Europe/Rome', 'America/New_York', etc.
```

### 4. Configure Frontend

Include the plugin JavaScript in your HTML:

```html
<script src="js/public-link-plugin.js"></script>
```

Initialize MyFileManager with optional download URL configuration:

```javascript
var fm = new MyFileManager('#file-manager', {
    url: '/path/to/connector.php',
    token: 'your-auth-token',

    // Optional: Specify custom download URL
    // If not specified, auto-detects by replacing 'connector.php' with 'download.php'
    publicLinksDownloadUrl: '/path/to/download.php',
    publicLinksCancelJsonFile: true, // delete the json files when links expired, default true allowed options: true/false
    // limit the option for link expiration (in this example from 30 minutes to 2 days), specified in minutes
    publicLinksExpirations: [30, 60, 120, 180, 360, 720, 1440, 2160, 2880],
    // limit the option for the wait time in the download page (0 is no wait), specified in seconds
    publicLinksWaitTimes: [0, 10, 30, 60, 120, 300],
    // ... other options
});
```

## Usage

### Generating a Link

1. Right-click on any file in the file manager
2. Select **"🔗 Generate Public Link"** from the context menu
3. Configure link options:
   - **Access Type**: Public (anyone) or Registered Users Only
   - **Link Expiration**: 5 minutes to 3 hours
   - **Wait Time**: 30 seconds to 5 minutes (anti-spam protection)
   - **Download Limit**: Optional maximum number of downloads
4. Click **"Generate Link"**
5. Copy the generated link and share it

### Managing Links

1. Go to **File → Manage Public Links** in the menu bar
2. View all your active links with:
   - File name
   - Access type (Public/Registered)
   - Download count and limit
   - Expiration date
3. Actions:
   - **Copy**: Copy link to clipboard
   - **Delete**: Remove the link immediately

### Download Page Features

When users visit a public link, they see:
- File information (name, size, downloads, expiration)
- Countdown timer with progress bar
- Advertisement space (configurable)
- Automatic download after wait time
- Download count tracking
- Timezone-aware expiration display

## Configuration Options

### Frontend (JavaScript)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `publicLinksDownloadUrl` | string | auto-detect | Custom URL for download.php |

**Auto-detect behavior**: If not specified, the plugin automatically replaces `connector.php` with `download.php` in the connector URL.

**Example**:
```javascript
var fm = new MyFileManager('#file-manager', {
    url: '/myapp/src/connector.php',
    // Will auto-detect: /myapp/src/download.php
});
```

### Backend (PHP)

#### publiclinks.php

```php
const PUBLIC_LINKS_DIR = '/path/to/storage/public_links';
```

- `PUBLIC_LINKS_DIR`: Directory where link JSON files are stored
- `DOWNLOAD_URL`: URL path to the download page

#### download.php

```php
define('PUBLIC_LINKS_DIR', '/path/to/storage/public_links');
define('DISPLAY_TIMEZONE', 'UTC');
```

- `PUBLIC_LINKS_DIR`: Must match the plugin configuration
- `DISPLAY_TIMEZONE`: Timezone for expiration display (e.g., 'UTC', 'Europe/Rome', 'America/New_York')

## Link Options

### Access Types

- **Public**: Anyone with the link can download
- **Registered**: Only authenticated users can download

### Expiration Times

- 5 minutes
- 10 minutes
- 15 minutes
- 30 minutes (default)
- 1 hour
- 1 hour 30 minutes
- 2 hours
- 3 hours

### Wait Times (Anti-spam)

- 30 seconds
- 1 minute (default)
- 2 minutes
- 3 minutes
- 5 minutes

### Download Limits

- Unlimited (default)
- Custom limit (1-999 downloads)

## Security Features

### Token Generation

- Uses PHP's `random_bytes(32)` for cryptographically secure tokens
- 64-character hexadecimal tokens (128-bit entropy)
- Collision probability: ~1 in 10^38

### Access Control

- User isolation: Users can only see and manage their own links
- Ownership verification: Links can only be deleted by their creator
- `.htaccess` protection: JSON files blocked from direct HTTP access

### Auto-cleanup

- Expired links automatically deleted when:
  - New links are created
  - Link list is viewed
  - Download page attempts to access them

## File Structure

### Link Storage

Links are stored as individual JSON files:

```
public_links/
├── .htaccess                           # Blocks direct HTTP access
├── abc123def456...token1.json          # Link data
├── xyz789uvw012...token2.json
└── ...
```

### Link Data Format

```json
{
    "token": "64-character-hex-token",
    "user_id": "user_folder_name",
    "user_name": "Display Name",
    "file_hash": "base64-encoded-file-path",
    "file_name": "video.mp4",
    "file_size": 16213439,
    "link_type": "public",
    "wait_seconds": 60,
    "max_downloads": 10,
    "download_count": 3,
    "created_at": 1767099519,
    "expires_at": 1767101319,
    "last_download_at": 1767099563,
    "root_path": "/path/to/user/files/"
}
```

## API Commands

The plugin adds the following commands to the MyFileManager API:

### publiclink_create

Create a new public link.

**Parameters**:
- `file_hash`: Base64-encoded file path
- `file_name`: Original filename
- `file_size`: File size in bytes
- `link_type`: "public" or "registered"
- `expiration_minutes`: Link expiration time
- `wait_seconds`: Anti-spam wait time
- `max_downloads`: Maximum download limit (0 = unlimited)

**Response**:
```json
{
    "success": true,
    "token": "64-char-token",
    "download_url": "/download.php?t=token",
    "expires_at": 1767101319
}
```

### publiclink_list

List all active links for the current user.

**Response**:
```json
{
    "success": true,
    "links": [
        {
            "token": "...",
            "file_name": "video.mp4",
            "download_count": 3,
            "max_downloads": 10,
            "expires_at": 1767101319,
            ...
        }
    ]
}
```

### publiclink_delete

Delete a link (owner only).

**Parameters**:
- `link_token`: Token of the link to delete

**Response**:
```json
{
    "success": true
}
```

## Customization

### Download Page Styling

The download page uses inline CSS with configurable colors. You can customize colors:

```css
/* Main gradient background */
background: linear-gradient(135deg, #daa520 0%, #b8860b 100%);

/* Button and progress bar */
background: linear-gradient(135deg, #daa520 0%, #b8860b 100%);
```

To customize, edit the `<style>` section in `download.php`.

### Advertisement Integration

Feel free to insert your ad code in the download page: `download.php`.

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Clipboard API support for copy functionality
- Fallback to `document.execCommand('copy')` for older browsers

## Troubleshooting

### Links not appearing in "Manage Links"

**Problem**: Users see links from other users or no links at all.

**Solution**: Check that `user_id` in the authentication callback matches the user's folder name:

```php
'authCallback' => function() {
    return [
        'id' => 'user_folder_name',  // Must match across sessions
        'username' => 'Display Name',
        // ...
    ];
}
```

### Download URL incorrect

**Problem**: Generated links have wrong path (e.g., `/download.php` instead of `/app/download.php`).

**Solution**: Explicitly configure `publicLinksDownloadUrl`:

```javascript
var fm = new MyFileManager('#file-manager', {
    publicLinksDownloadUrl: '/app/download.php',
    // ...
});
```

### "Link not found or expired" error

**Problem**: Valid links show this error.

**Solution**: Verify that `PUBLIC_LINKS_DIR` is identical in both `publiclinks.php` and `download.php`:

```php
// In publiclinks.php
const PUBLIC_LINKS_DIR = '/path/to/storage/public_links';

// In download.php (must match exactly)
define('PUBLIC_LINKS_DIR', '/path/to/storage/public_links');
```

### File download fails

**Problem**: Download page loads but file doesn't download.

**Solution**: Check that:
1. `root_path` in link JSON is correct in plugin php and download.php
2. PHP has read permissions on the file
3. No output buffering or headers sent before download

## Performance

- **Lightweight**: Minimal overhead on file manager
- **Efficient storage**: JSON files only for active links
- **Auto-cleanup**: Expired links removed automatically
- **No database required**: File-based storage

## Security Considerations

1. **Directory Protection**: Ensure `public_links` directory is outside web root or protected by `.htaccess`
2. **Token Strength**: Uses cryptographically secure random tokens
3. **User Isolation**: Users can only manage their own links
4. **Expiration**: All links have mandatory expiration times
5. **Rate Limiting**: Wait times prevent rapid automated downloads

## License

This plugin is part of the MyFileManager project. Check the main project for license information.

## Support

For issues, questions, or contributions, please visit the main MyFileManager repository.

## Changelog

### Version 1.0.0 (2025-12-30)

- Initial release
- Generate public download links
- Configurable expiration times
- Anti-spam wait times
- Download limits
- Link management interface
- Auto-cleanup of expired links
- Secure token generation
- User isolation
- Customizable download page
- Advertisement integration support
