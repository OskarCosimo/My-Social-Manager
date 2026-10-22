# MyFileManager - PHP Configuration Guide

This guide focuses on configuring the `connector.php` file, which is the main entry point for all file operations in MyFileManager.

## Table of Contents

- [Basic Configuration](#basic-configuration)
- [Storage Settings](#storage-settings)
- [Security Settings](#security-settings)
- [File Upload Restrictions](#file-upload-restrictions)
- [Folder Permissions](#folder-permissions)
- [Extension Restrictions](#extension-restrictions)
- [Plugin System](#plugin-system)
- [Authentication](#authentication)

---

## Basic Configuration

### File System Paths

```php
$config = [
    // Root directory where files will be stored
    'rootPath' => $PATHTOFILES,

    // URL path for accessing files
    // Leave blank to use default, or specify custom URL
    'rootUrl' => '', // Example: 'https://yourdomain.com/files/'
];
```

**Note:** The `$PATHTOFILES` variable should be defined before the configuration array. The directory will be created automatically if it doesn't exist.

---

## Storage Settings

### Quota and File Size Limits

MyFileManager supports human-readable size units (B, KB, MB, GB, TB, PB):

```php
$config = [
    // Maximum total storage quota per user
    'maxQuota' => parseFileSize('5GB'),
    // Examples: '5GB', '5000MB', 5368709120 (bytes)

    // Maximum single file size for uploads
    'maxFileSize' => parseFileSize('500MB'),
    // Examples: '500MB', '0.5GB', 524288000 (bytes)

    // Chunk size for large file uploads (in bytes)
    'chunkSize' => 1048576, // 1MB (default)
];
```

### Trash/Recycle Bin

```php
$config = [
    // Enable trash functionality
    'enableTrash' => true,

    // Trash folder path
    'trashPath' => $PATHTOFILES . '.trash/',

    // WARNING: Do not change the '.trash' folder name unless you also
    // update the JavaScript initialization option 'trashPath'
];
```

---

## Security Settings

### Token Authentication

```php
$config = [
    'security' => [
        // Enable Bearer token authentication
        'enableTokenAuth' => false, // Set to true in production

        // Secret key for token validation
        // Generate a unique secret for your application
        'tokenSecret' => base64_encode(hash('sha512', 'user123') . hash('sha256', 'user123')),

        // IP whitelist (optional)
        'enableIPWhitelist' => false,
        'allowedIPs' => [], // Example: ['192.168.1.100', '10.0.0.1']
    ],
];
```

**Security Best Practices:**
- Always enable `enableTokenAuth` in production
- Generate a strong, unique `tokenSecret` for your application
- Use IP whitelist for sensitive environments

---

## File Upload Restrictions

### MIME Types

```php
$config = [
    // Allowed MIME types for uploads
    'allowedMimeTypes' => [
        'image/*',          // All images
        'video/*',          // All videos
        'audio/*',          // All audio
        'text/*',           // All text files
        'application/pdf',  // PDF documents
        'text/plain'        // Plain text
    ],
];
```

### Banned Extensions

For security reasons, dangerous file extensions are blocked by default:

```php
$config = [
    'banExtensions' => [
        // Unix/Linux executables
        'sh', 'bash', 'csh', 'ksh', 'zsh', 'pl', 'py', 'rb', 'cgi',

        // Windows executables
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'dll', 'msi',

        // Web server scripts
        'php', 'php3', 'php4', 'php5', 'asp', 'aspx', 'jsp', 'cfm',

        // Dangerous scripts
        'js', 'vbs', 'wsf', 'wsh', 'ps1',

        // Config files
        'conf', 'ini', 'cfg', 'htaccess', 'htpasswd',

        // Office macros
        'docm', 'xlsm', 'pptm'
    ],
];
```

**Security Warning:** Never remove default banned extensions unless you understand the security implications.

---

## Folder Permissions

### Read-Only Folders

Prevent file uploads and folder creation in specific directories:

```php
$config = [
    // Folders where upload/mkdir operations are blocked
    'readOnlyFolders' => [
        'system',
        'config',
        'archives'
    ],
];
```

### Protected Folders

Prevent folders from being renamed or deleted (contents can still be modified):

```php
$config = [
    // Folders that cannot be renamed or deleted
    'protectedFolders' => [
        '.trash',
        'uploads',
        'backup',
        'public'
    ],
];
```

### Folders excluded from quota calculation

These folders will be excluded from quota calculation allowed them to have infinite GB, comma separate value to specify more folders:

```php
$config = [
    'quotaExcludedFolders' => ['infinitefolder', 'backup'], // Folders excluded from GB quota
];
```

### Folders where upload and mkdir are completely blocked

These folders upload and make new folders are completely disabled (these folders and subfolders too), comma separate value to specify more folders:

```php
$config = [
    'uploadBlockedFolders' => ['nouploadfolder', 'backup'], // No upload/mkdir allowed here and subfolders
];
```

---

## Extension Restrictions

Define granular permissions for specific file extensions:

```php
$config = [
    // Per-extension permissions
    'extensionRestrictions' => [
        'log' => [
            'read' => true,      // Can view/download
            'write' => false,    // Cannot rename
            'open' => false,     // Cannot open in editor
            'delete' => false    // Cannot delete
        ],
        'config' => [
            'read' => true,
            'write' => false,    // Read-only config files
            'open' => true,      // Can be opened for viewing
            'delete' => false
        ],
        'tmp' => [
            'read' => false,     // Hidden from view
            'write' => false,
            'open' => false,
            'delete' => true     // But can be deleted
        ],
    ],

    // Default permissions for unlisted extensions
    'defaultExtensionPermissions' => [
        'read' => true,
        'write' => true,
        'open' => true,
        'delete' => true
    ],
];
```

**Available Permissions:**
- `read` - Can view and download the file
- `write` - Can rename or modify the file
- `open` - Can open in built-in editors
- `delete` - Can move to trash or permanently delete

---

## Plugin System

To enable or disable a plugin or configure its options in the php environment you must edit your connector.php file with the needed plugin. The plugin file (.php) must be in the folder /plugins/ that folder should be in the same folder of connector.php

### Video Editor Plugin (video_editor.php)

Enables FFmpeg-based video processing:

```php
$config = [
    'plugins' => [
        'video_editor' => [
            'enabled' => false,  // Set to true to enable
            'ffmpeg_path' => '/usr/bin/ffmpeg', // Path to FFmpeg binary
            // 'blur_png_path' => '' // Optional: custom blur overlay
        ],

        // Rate limiter for video processing (recommended)
        'rate_limiter' => [
            'enabled' => false  // Enable to prevent abuse
        ],
    ],
];
```

### FTP Plugin (ftpplugin.php)

Connect to remote FTP servers:

```php
$config = [
    'plugins' => [
        'ftpplugin' => [
            'enabled' => false,
            'host' => 'ftp.example.com',
            'username' => 'user',
            'password' => 'pass',
            'port' => 21,
            'passive' => true
        ],
    ],
];
```

### ClamAV Plugin (clamav.php)

Scan uploaded file with ClamAV antivirus for malware or viruses:

```php
$config = [
    'clamav' => [
    'enabled' => true, //enable or disable the plugin
    'clamdscan_path' => '/usr/bin/clamdscan', //setup the folder where the clamav antivirus is installed (for linux this should be the default)
    ], //clamav antivirus scans
];
```

### Public Links Downloader Plugin (publiclinks.php)

Enables and config the public links downloader plugins:

```php
$config = [
    'plugins' => [
       'publiclinks' => [
    'enabled' => true, // ENABLED/DISABLED download public links plugin
],
    ],
];
```

---

## Authentication

### Custom Authentication Callback

Implement your own authentication logic:

```php
$config = [
    'authCallback' => function() {
        // Example: Check session
        if (!isset($_SESSION['user_id'])) {
            return false; // Authentication failed
        }

        // Return user information
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'quota' => '524288000', // 500MB in bytes
            'permissions' => [
                'read',
                'write',
                'delete',
                'upload',
                'download',
                'rename',
                'copy',
                'move',
                'mkdir',
                'search',
                'quota',
                'info'
            ]
        ];
    }
];
```

**Available Permissions:**
- `read` - View files and directories
- `write` - Create, rename, and modify files
- `delete` - Delete files and folders
- `upload` - Upload new files
- `download` - Download files
- `rename` - Rename files and folders
- `copy` - Copy files and folders
- `move` - Move files and folders
- `mkdir` - Create new directories
- `search` - Search functionality
- `quota` - View storage quota information
- `info` - View file properties/metadata

---

## Disabled Operations

Globally disable specific operations:

```php
$config = [
    // Disable specific commands
    'disabledOperations' => [
        // 'upload',
        // 'download',
        // 'delete',
        // 'rename',
        // 'mkdir',
    ],
];
```

---

## Complete Configuration Example

```php
<?php
require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

// Define your storage path
$PATHTOFILES = __DIR__ . '/userfiles/';

// Configuration
$config = [
    'rootPath' => $PATHTOFILES,
    'rootUrl' => '',
    'maxQuota' => parseFileSize('5GB'),
    'maxFileSize' => parseFileSize('500MB'),
    'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*'],
    'banExtensions' => [
        'php', 'exe', 'sh', 'bat', 'cmd'
    ],
    'extensionRestrictions' => [
        'log' => [
            'read' => true,
            'write' => false,
            'open' => false,
            'delete' => false
        ],
    ],
    'readOnlyFolders' => ['system'],
    'protectedFolders' => ['.trash', 'uploads'],
    'enableTrash' => true,
    'trashPath' => $PATHTOFILES . '.trash/',
    'security' => [
        'enableTokenAuth' => true,
        'tokenSecret' => base64_encode(random_bytes(32)),
    ],
    'authCallback' => function() {
        return [
            'id' => 'user123',
            'username' => 'demo_user',
            'quota' => parseFileSize('5GB'),
            'permissions' => ['read', 'write', 'delete', 'upload', 'download']
        ];
    }
];

// ... rest of connector code
?>
```

---

## Troubleshooting

### Common Issues

1. **"Permission denied" errors**
   - Check file system permissions on `rootPath`
   - Verify user has correct permissions in `authCallback`

2. **"Quota exceeded" errors**
   - Increase `maxQuota` value
   - Check actual disk space availability

3. **Upload fails for large files**
   - Increase `maxFileSize`
   - Check PHP settings: `upload_max_filesize`, `post_max_size`, `memory_limit`

4. **Files not appearing**
   - Check `allowedMimeTypes` configuration
   - Verify files aren't in `readOnlyFolders`
   - Check browser console for JavaScript errors

---

## Security Recommendations

✅ **DO:**
- Enable token authentication in production
- Use HTTPS for file transfers
- Implement proper session management
- Regularly update banned extensions list
- Use strong, unique token secrets
- Implement rate limiting for uploads

❌ **DON'T:**
- Remove default banned extensions
- Disable authentication
- Store sensitive files in public directories
- Use weak or default token secrets
- Allow unrestricted file uploads

---

## License

MyFileManager is licensed under the MIT License.

## Support

For issues, questions, or contributions, please visit the project repository.
