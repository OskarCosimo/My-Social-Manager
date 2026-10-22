# My File Manager Opensource

<div align="center">

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Version](https://img.shields.io/badge/version-1.x-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript)

[![CodeQL](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/codeql.yml/badge.svg)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/codeql.yml)
[![PHP Security Analysis](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/php-security.yml/badge.svg)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/php-security.yml)
[![Node.js Build](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/npm-publish.yml/badge.svg?logo=node.js&style=flat-square)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/npm-publish.yml)

**A modern, secure, and feature-rich file manager with end-to-end encryption support**

[Features](#features) • [Installation](#installation) • [Documentation](#documentation) • [NPM Download](https://www.npmjs.com/package/my-file-manager-opensource) • [License](#license)

</div>

---
# Documentation

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Screenshot](#screenshot)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration Options](#configuration-options)
  - [JavaScript Initialization Options](#javascript-initialization-options)
  - [PHP Configuration](#php-configuration)
- [Embedding in HTML](#embedding-in-html)
- [API Reference](#api-reference)
  - [Core Methods](#core-methods)
  - [Event Callbacks](#event-callbacks)
- [JavaScript Plugins](#javascript-plugins)
  - [Creating Custom JavaScript Plugins](#creating-custom-javascript-plugins)
  - [Built-in JavaScript Plugins](#built-in-javascript-plugins)
- [PHP Backend System](#php-backend-system)
  - [Core PHP Files](#core-php-files)
  - [PHP Plugin System](#php-plugin-system)
  - [Creating Custom PHP Plugins](#creating-custom-php-plugins)
- [Security Features](#security-features)
  - [End-to-End Encryption](#end-to-end-encryption)
  - [Custom Authentication](#custom-authentication)
  - [Token-Based Security](#token-based-security)
- [Customization](#customization)
  - [Custom Menus and Context Menus](#custom-menus-and-context-menus)
  - [Custom Icons](#custom-icons)
  - [Brand Logo](#brand-logo)
  - [Themes](#themes)
- [Internationalization (i18n)](#internationalization-i18n)
- [Browser Compatibility](#browser-compatibility)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

---

## Overview

**My File Manager** is a powerful, open-source web-based file manager built with vanilla JavaScript and PHP. It provides a modern user interface similar to desktop file managers, with advanced features like end-to-end encryption, chunked file uploads, plugin architecture, and extensive customization options.

### Why Choose My File Manager?

- 🔐 **Security First**: Built-in encryption, authentication, and comprehensive security validations
- 🚀 **Modern Architecture**: Vanilla JavaScript (no dependencies), modular PHP backend
- 🔌 **Plugin System**: Extensible with both JavaScript and PHP plugins
- 🎨 **Fully Customizable**: Themes, icons, menus, branding
- 📱 **Responsive Design**: Works seamlessly on desktop, tablet, and mobile
- 🌍 **Multilingual**: Built-in i18n support with easy translation
- ⚡ **Performance Optimized**: Chunked uploads for large files, efficient file handling

---
## Screenshot

<img width="1866" height="492" alt="screenshot-myfilemanager-opensource" src="https://github.com/user-attachments/assets/c0733d15-5c15-4afb-a587-bee37d510ed3" />

## Features

### Core Features

- **File Operations**: Upload, download, delete, rename, copy, cut, paste, create folders
- **Advanced Upload**: Chunked upload for large files with progress tracking
- **File Preview**: Built-in viewers for images and text files
- **Search**: Fast file search with recursive directory scanning
- **Trash System**: Soft delete with restore functionality
- **Drag & Drop**: Intuitive drag-and-drop file operations
- **Keyboard Shortcuts**: Efficient navigation with keyboard support
- **Multi-selection**: Select multiple files with Ctrl/Shift/Cmd
- **Context Menu**: Right-click context menus for quick actions
- **Breadcrumb Navigation**: Easy directory navigation with clickable breadcrumbs
- **List/Grid View**: Toggle between list and grid view modes
- **Sorting**: Sort by name, size, date, or type
- **Quota Management**: Display storage usage and limits

### Security Features

- **End-to-End Encryption**: AES-256-GCM encryption with WebCrypto API
- **Custom Authentication**: Flexible authentication system with callbacks
- **Token-Based Security**: JWT-like token authentication with HMAC-SHA256
- **IP Whitelist**: Restrict access by IP address
- **Extension Filtering**: Block dangerous file extensions
- **MIME Type Validation**: Server-side MIME type verification
- **Path Traversal Protection**: Secure path resolution
- **XSS Protection**: Sanitized inputs and outputs

### Advanced Features

- **Plugin Architecture**: Extend functionality with JavaScript and PHP plugins
- **Custom Menus**: Add custom menu items and context menu entries
- **Brand Customization**: Add your logo and customize the appearance
- **Custom Icons**: Replace default icons with your own PNG files
- **Internationalization**: Multi-language support (English, Italian, Spanish, German, French, Portuguese, Chinese, Japanese, Russian)
- **Auto-Refresh**: Optional automatic file list refresh
- **Hidden Files**: Show/hide hidden files (dotfiles)
- **Custom File Opener**: Override default file open behavior

---

## Requirements

### Server Requirements

- **PHP**: 7.4 or higher (8.0+ recommended)
- **PHP Extensions**:
  - `fileinfo` (for MIME type detection)
  - `mbstring` (for filename handling)
  - `openssl` (for encryption)
- **Web Server**: 
  - **Linux/Unix**: Apache (with mod_rewrite), Nginx, LiteSpeed, Others
  - **Windows**: IIS, Apache (XAMPP/WAMP), Nginx
  - **macOS**: Apache (MAMP/Homebrew), Nginx (Valet), Docker
- **Disk Space**: Depends on your usage

### Client Requirements

- **Modern Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Enabled
- **WebCrypto API**: For encryption features (supported by all modern browsers)

---

## Installation

### 1. Clone or Download

```bash
git clone https://github.com/OskarCosimo/My-File-Manager-Opensource.git
cd my-file-manager-opensource
```

### 2. Directory Structure

Ensure your directory structure matches:

```
my-file-manager-opensource/
├── js/
│   ├── i18n.js                          # Internationalization translations
│   ├── myfilemanager.js                 # Core file manager JavaScript
│   ├── i18n.min.js                      # Internationalization translations - Minifed ready for production
│   ├── myfilemanager.min.js             # Core file manager JavaScript - Minifed ready for production
│   └── plugins/
│       ├── myfilemanager-image-editor.js # Image editor plugin
│       ├── myfilemanager-text-editor.js  # Text editor plugin
│       ├── myfilemanager-video-editor.js  # Text editor plugin
│       └── [your-custom-plugins].js      # Your custom plugins
├── css/
│   └── myfilemanager.css                # Core styles
│   └── myfilemanager.min.css            # Core styles - Minifed ready for production
├── src/
│   ├── connector.php                    # Main API endpoint
│   ├── .htaccess                        # .htaccess for security with apache2 webserver (ready for production)
│   ├── nginx.conf.example               # nginx.conf for an example configuration with nginx webserver
│   ├── myfilemanager.php                # File manager core class
│   ├── chunkuploader.php                # Chunked upload handler
│   ├── security.php                     # Security utilities
│   └── plugins/
│       ├── plugininterface.php          # Plugin interface
│       ├── ftpplugin.php                # FTP plugin
│       ├── rate_limiter.php             # Rate limit for video editor plugin
│       ├── video_editor.php             # Video editor plugin
│       └── [your-custom-plugins].php    # Your custom plugins
├── assets/                              # PNG icon files and other static assets
├── files/                               # Default file storage directory
├── package.json                         # NPM package info (optional)
└── README.md                            # This file
```

#### Apache (Linux/Windows/macOS)
The included `.htaccess` file in `/src/` works out of the box.
Only `connector.php` is accessible via HTTP.

#### Nginx (Linux/macOS)
Add the configuration from `src/nginx.conf.example` to your server block:
`sudo nano /etc/nginx/sites-available/your-site`
Add the location block from nginx.conf.example
`sudo nginx -t
sudo systemctl reload nginx`

#### Windows IIS
IIS doesn't support file-level access control without additional modules, you could made a web.config file but you need url rewrite module to make it work.

**Recommended solution:**
Move `connector.php` outside the `/src/` folder.

Update paths in `connector.php`.

Then in IIS Manager, deny access to the `/src/` folder.

### 3. Set Permissions

```bash
# Create file storage directory
mkdir -p files
chmod 755 files

# Set permissions for upload directory (adjust based on your server)
chown www-data:www-data files
chmod 750 files
```

### 4. Configure PHP

Edit `src/connector.php` to configure your file manager:

```php
<?php
require_once 'myfilemanager.php';
require_once 'chunkuploader.php';
require_once 'security.php';
...
// Your configuration
$PATHTOFILES = __DIR__ . '/../files/';  // Absolute path to file storage
// Verify if exist
    if (!is_dir($PATHTOFILES)) {
        mkdir($PATHTOFILES, 0755, true);
        error_log("Created files folder: {$PATHTOFILES}");
    }
    // Configuration array
    $config = [
        // Root path for file operations
        'rootPath' => $PATHTOFILES,
        
        // URL path for accessing files, this url automatically adds the file name after it with a querystring ?filename=... it is usefull for custom download scripts
        'rootUrl' => '', // https://... or leave blank to use the default
        
        // Maximum quota (supports units: B, KB, MB, GB, TB, PB)
        // Examples: 5368709120, "5GB", "5000MB"
        // Default (without unit) is bytes
        'maxQuota' => parseFileSize('5GB'),
    
        // Maximum file size for upload (supports units: B, KB, MB, GB, TB, PB)
        // Examples: 524288000, "500MB", "0.5GB"
        // Default (without unit) is bytes
        'maxFileSize' => parseFileSize('0.5GB'),
        
        // Allowed mime types
        'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],
...
// See Configuration Options section for full config
```

### 5. Install Dependencies (Optional)

If you want to use the build tools for minification:

```bash
npm install
```

---

## Quick Start

### Basic HTML Integration

Create an HTML file with the file manager:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My File Manager</title>

    <!-- File Manager CSS -->
    <link rel="stylesheet" href="css/myfilemanager.css">
</head>
<body>
    <!-- File Manager Container -->
    <div id="filemanager" style="height: 100vh;"></div>

    <!-- Load Dependencies -->
    <script src="js/i18n.js"></script>
    <script src="js/myfilemanager.js"></script>

    <!-- Load Javascript Plugins (Optional) -->
    <script src="js/plugins/myfilemanager-text-editor.js"></script>
    <script src="js/plugins/myfilemanager-image-editor.js"></script>
    <script src="js/plugins/myfilemanager-video-editor.js"></script>

    <!-- Initialize File Manager -->
    <script>
        var fm = new MyFileManager('#filemanager', {
            url: 'src/connector.php',
            lang: 'en',
            viewMode: 'list',
            theme: 'light'
        });
    </script>
</body>
</html>
```

---

## Configuration Options

### JavaScript Initialization Options

The following table lists all available options when initializing the file manager:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `url` | String | `/connector.php` | Backend API endpoint URL |
| `downloadUrl` | String | `null` | Custom download URL (leave it blank to use direct file download script) |
| `token` | String | `null` | Authentication token for API requests |
| `lang` | String | Browser language | UI language (`en`, `it`, `es`, `de`, `fr`, `pt`, `zh`, `ja`, `ru`) |
| `viewMode` | String | `list` | Default view mode (`list` or `grid`) |
| `theme` | String | `light` | UI theme (`light` or `dark`) |
| `maxFileSize` | Number | `524288000` | Maximum file size in bytes (500MB) |
| `chunkSize` | Number | `1048576` | Chunk size for uploads in bytes (1MB) |
| `trashPath` | String | `.trash` | Trash folder name (relative to root) - it is reccomended to not change it |
| `debug` | Boolean | `false` | Enable console debug messages |
| `brandLogo` | String | `null` | URL to brand logo image |
| `brandLink` | String | `null` | Link URL when clicking logo |
| `brandTarget` | String | `_blank` | Link target (`_blank`, `_self`, etc.) |
| `homeLabel` | String | `Home` | Label for home/root directory |
| `cryptFiles` | Boolean | `false` | Enable client-side encryption |
| `encryptionKey` | CryptoKey | `null` | Encryption key (derived from password) - if not provided the key will be asked to the user (end-to-end encryption) |
| `encryptionSalt` | Uint8Array | `null` | Salt for key derivation |
| `cryptExclude` | Array | `[]` | MIME patterns to exclude from encryption (e.g., `['video/*', 'audio/*']`) |
| `showUrlOnProperties` | Boolean | `false` | Show file URL in properties dialog |
| `showHidden` | Boolean | `false` | Show hidden files (starting with `.`) |
| `sortBy` | String | `name` | Default sort field (`name`, `size`, `date`, `type`) |
| `sortOrder` | String | `asc` | Default sort order (`asc` or `desc`) |
| `autoRefresh` | Boolean/Number | `false` | Auto-refresh interval in seconds (e.g., `30`) |
| `hideExtensions` | Array | `[]` | File extensions hided from the view |
| `banExtensions` | Array | See below | File extensions blocked for upload/rename (see below) |
| `features` | Object | All enabled | Enable/disable specific features (read below) |
| `customMenus` | Array | `[]` | Custom menu items in menu bar |
| `customContextMenu` | Array | `[]` | Custom context menu entries |
| `onInit` | Function | `null` | Callback when file manager initializes |
| `onUploadStart` | Function | `null` | Callback when upload starts |
| `onUploadProgress` | Function | `null` | Callback during upload progress |
| `onUploadComplete` | Function | `null` | Callback when upload completes |
| `onUploadError` | Function | `null` | Callback on upload error |
| `onFileOpen` | Function | `null` | Callback when file is opened |
| `onFileSelect` | Function | `null` | Callback when file is selected |
| `onError` | Function | `null` | Callback on error |
| `onChange` | Function | `null` | Callback on any state change |
| `customFileOpener` | Function | `null` | Custom file open handler (what happens when double click any files) |

#### Default Banned Extensions

For security purposes, the following extensions are blocked by default (you can customize it for your needs but remember to customize it in both php and javascript):

```javascript
banExtensions: [
    // Unix/Linux executables
    'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
    'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
    'rb', 'rbw', 'cgi', 'fcgi',
    // Windows executables
    'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
    'msi', 'msp', 'dll', 'ocx', 'sys',
    // Web server scripts
    'php', 'php3', 'php4', 'php5', 'phtml', 'php7',
    'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
    'jsp', 'jspx', 'jsw', 'jssp', 'do',
    'cfm', 'cfml', 'cfc',
    // Dangerous scripts
    'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',
    // macOS dangerous
    'app', 'dmg', 'pkg', 'mpkg',
    // Config files
    'conf', 'cnf', 'ini', 'cfg', 'config',
    'htaccess', 'htpasswd', 'htgroup',
    // Macro virus (Office)
    'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
]
```

#### Features Object

Control which features are enabled:

```javascript
features: {
    upload: true,       // File upload
    download: true,     // File download
    delete: true,       // Delete files/folders
    rename: true,       // Rename files/folders
    copy: true,         // Copy files/folders
    cut: true,          // Cut/move files/folders
    paste: true,        // Paste clipboard
    mkdir: true,        // Create new folder
    search: true,       // Search functionality
    info: true,         // File properties/info
    contextMenu: true   // Right-click context menu
}
```

### PHP Configuration

Configure the backend in `src/connector.php`:

```php
$config = [
    // Storage paths
    'rootPath' => '/absolute/path/to/files/',
    'rootUrl' => '/files/',

    // Quota settings
    'maxQuota' => '5GB',           // User storage quota
    'maxFileSize' => '500MB',      // Max single file size

    // Allowed MIME types (wildcard supported)
    'allowedMimeTypes' => [
        'image/*', 'video/*', 'audio/*', 
        'text/*', 'application/pdf'
    ],

    // Banned extensions (same as JavaScript)
    'banExtensions' => ['php', 'exe', 'sh', ...],

    // Disabled operations
    'disabledOperations' => [], // e.g., ['delete', 'rename']

    // Chunk upload settings
    'chunkSize' => 1048576, // 1MB chunks

    // Trash functionality
    'enableTrash' => true,
    'trashPath' => $config['rootPath'] . '.trash/',

    // Security settings
    'security' => [
        'enableTokenAuth' => false,
        'tokenSecret' => 'your-secret-key',
        'enableIPWhitelist' => false,
        'allowedIPs' => ['127.0.0.1'],
    ],

    // Custom authentication callback
    'authCallback' => function() {
        // Your authentication logic
        return [
            'id' => 'user123',
            'username' => 'john_doe',
            'quota' => 5368709120, // bytes
            'permissions' => [
                'read', 'write', 'delete', 'upload', 
                'download', 'rename', 'copy', 'move', 
                'mkdir', 'search', 'quota', 'info'
            ]
        ];
    },

    // Plugins
    'plugins' => []
];
```
The authentication script, in production use, can also be integrated with a database variable or a session

Variables:
```
// Custom authentication callback - Version with USE
    'authCallback' => function() use ($userId, $username, $userQuota) {
        // Variable are already available with 'use'
        return [
            'id' => $userId,
            'username' => $username,
            'quota' => $userQuota,
            'permissions' => [
                'read', 'write', 'delete', 'upload', 
                'download', 'rename', 'copy', 'move', 
                'mkdir', 'search', 'quota', 'info'
            ]
        ];
    }
```
Session:
```
    // Custom authentication callback - Version with SESSION (remember to start the session before use it)
    'authCallback' => function() {
        // check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
            return false; // Not authenticated
        }
        
        // Read data from session
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'quota' => $_SESSION['user_quota'] ?? 5368709120, // Default 5GB
            'permissions' => [
                'read', 'write', 'delete', 'upload', 
                'download', 'rename', 'copy', 'move', 
                'mkdir', 'search', 'quota', 'info'
            ]
        ];
    }
```
---

## Embedding in HTML

### Basic Embedding

The simplest way to embed the file manager:

```html
<div id="filemanager" style="height: 600px;"></div>

<script>
    var fm = new MyFileManager('#filemanager', {
        url: 'src/connector.php'
    });
</script>
```

### With Custom Configuration

```html
<div id="filemanager"></div>

<script>
    var fm = new MyFileManager('#filemanager', {
        url: 'src/connector.php',
        lang: 'en',
        theme: 'dark',
        viewMode: 'grid',
        brandLogo: 'assets/logo.png',
        brandLink: 'https://yoursite.com',
        cryptFiles: true, // Enable encryption
        showHidden: true,
        autoRefresh: 30000, // Refresh every 30 seconds

        // Custom menu items
        customMenus: [
            {
                label: 'My Custom Action',
                icon: '⚙️',
                action: function(fm) {
                    alert('Custom action executed!');
                }
            }
        ],

        // Event callbacks
        onFileSelect: function(files) {
            console.log('Selected files:', files);
        },

        onUploadComplete: function(file) {
            console.log('Upload complete:', file);
        }
    });
</script>
```

### Fullscreen Integration

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        #filemanager { height: 100vh; }
    </style>
    <link rel="stylesheet" href="css/myfilemanager.css">
</head>
<body>
    <div id="filemanager"></div>
    <script src="js/i18n.js"></script>
    <script src="js/myfilemanager.js"></script>
    <script>
        new MyFileManager('#filemanager', {
            url: 'src/connector.php'
        });
    </script>
</body>
</html>
```

### Multiple Instances

You can embed multiple file managers on the same page:

```html
<div id="filemanager1" style="height: 400px;"></div>
<div id="filemanager2" style="height: 400px;"></div>

<script>
    var fm1 = new MyFileManager('#filemanager1', {
        url: 'src/connector.php?user=user1'
    });

    var fm2 = new MyFileManager('#filemanager2', {
        url: 'src/connector.php?user=user2'
    });
</script>
```

---

## API Reference

### Core Methods

#### `open(path)`
Open a directory.

```javascript
fm.open(''); // Open root directory
fm.open('L3N1YmZvbGRlcg=='); // Open subdirectory (base64 hash)
```

#### `refresh()`
Refresh the current directory.

```javascript
fm.refresh();
```

#### `upload(files)`
Upload files programmatically.

```javascript
var fileInput = document.getElementById('myFileInput');
fm.upload(fileInput.files);
```

#### `download(files)`
Download selected files.

```javascript
fm.download(fm.getSelectedFiles());
```

#### `delete(files)`
Delete files/folders.

```javascript
fm.delete(fm.getSelectedFiles());
```

#### `rename(file, newName)`
Rename a file or folder.

```javascript
fm.rename(file, 'newname.txt');
```

#### `copy(files, destination)`
Copy files to destination.

```javascript
fm.copy(selectedFiles, destinationHash);
```

#### `cut(files, destination)`
Move files to destination.

```javascript
fm.cut(selectedFiles, destinationHash);
```

#### `mkdir(name)`
Create a new directory.

```javascript
fm.mkdir('New Folder');
```

#### `search(query)`
Search for files.

```javascript
fm.search('document');
```

#### `getSelectedFiles()`
Get currently selected files.

```javascript
var selected = fm.getSelectedFiles();
console.log(selected);
```

#### `selectAll()`
Select all files in current directory.

```javascript
fm.selectAll();
```

#### `deselectAll()`
Deselect all files.

```javascript
fm.deselectAll();
```

#### `setViewMode(mode)`
Change view mode.

```javascript
fm.setViewMode('grid'); // or 'list'
```

#### `setTheme(theme)`
Change theme.

```javascript
fm.setTheme('dark'); // or 'light'
```

#### `registerPlugin(plugin)`
Register a custom plugin.

```javascript
fm.registerPlugin(MyCustomPlugin);
```

#### `destroy()`
Destroy file manager instance.

```javascript
fm.destroy();
```

### Event Callbacks

#### `onInit`
Called when file manager initializes.

```javascript
onInit: function(fm) {
    console.log('File manager initialized');
}
```

#### `onFileSelect`
Called when files are selected.

```javascript
onFileSelect: function(files) {
    console.log('Selected:', files);
}
```

#### `onFileOpen`
Called when a file is opened.

```javascript
onFileOpen: function(file) {
    console.log('Opened:', file);
}
```

#### `onUploadStart`
Called when upload starts.

```javascript
onUploadStart: function(files) {
    console.log('Starting upload:', files);
}
```

#### `onUploadProgress`
Called during upload progress.

```javascript
onUploadProgress: function(progress) {
    console.log('Progress:', progress + '%');
}
```

#### `onUploadComplete`
Called when upload completes.

```javascript
onUploadComplete: function(file) {
    console.log('Upload complete:', file);
}
```

#### `onUploadError`
Called on upload error.

```javascript
onUploadError: function(error) {
    console.error('Upload error:', error);
}
```

#### `onError`
Called on any error.

```javascript
onError: function(error) {
    console.error('Error:', error);
}
```

#### `onChange`
Called on any state change.

```javascript
onChange: function(state) {
    console.log('State changed:', state);
}
```

---

## JavaScript Plugins

### Creating Custom JavaScript Plugins

JavaScript plugins extend the file manager's functionality on the client side. Here's a comprehensive guide:

#### Plugin Structure

```javascript
(function(window) {
    'use strict';

    /**
     * My Custom Plugin
     * @param {MyFileManager} fm - File manager instance
     * @returns {Object} Plugin instance
     */
    function MyCustomPlugin(fm) {
        var plugin = {
            name: 'MyCustomPlugin',
            version: '1.0.0',
            fileManager: fm
        };

        /**
         * Initialize plugin
         */
        plugin.init = function() {
            console.log('Plugin initialized');

            // Access file manager
            var currentPath = fm.state.currentPath;

            // Register context menu item
            fm.addContextMenuItem({
                label: 'My Action',
                icon: '⚡',
                condition: function(files) {
                    // Show only for single file selection
                    return files.length === 1;
                },
                action: function(files) {
                    plugin.doSomething(files[0]);
                }
            });
        };

        /**
         * Plugin method
         */
        plugin.doSomething = function(file) {
            alert('Action on: ' + file.name);
        };

        // Auto-initialize
        plugin.init();

        return plugin;
    }

    // Register plugin globally
    if (window.MyFileManager) {
        window.MyFileManager.plugins = window.MyFileManager.plugins || [];
        window.MyFileManager.plugins.push(MyCustomPlugin);
    }

})(window);
```

#### Plugin Features

**1. Context Menu Integration**

Add custom items to the context menu:

```javascript
fm.addContextMenuItem({
    label: 'Open in External Editor',
    icon: '📝',
    condition: function(files) {
        // Show only for text files
        return files.length === 1 && files[0].mime.startsWith('text/');
    },
    action: function(files) {
        window.open('https://editor.example.com?file=' + files[0].hash);
    }
});
```

**2. Menu Bar Integration**

Add items to the top menu bar:

```javascript
customMenus: [
    {
        label: 'Tools',
        icon: '🔧',
        action: function(fm) {
            // Your action
        }
    }
]
```

**3. Custom Modals**

Create custom modals:

```javascript
var modal = document.createElement('div');
modal.className = 'mfm-modal';
modal.innerHTML = `
    <div class="mfm-modal-dialog">
        <div class="mfm-modal-header">
            <h3>Custom Dialog</h3>
            <button class="mfm-modal-close">&times;</button>
        </div>
        <div class="mfm-modal-body">
            <p>Your content here</p>
        </div>
    </div>
`;
document.body.appendChild(modal);
```

**4. File Operations**

Access and modify files:

```javascript
plugin.processFile = function(file) {
    // Read file
    fm.request('download', { target: file.hash }).then(function(data) {
        // Process data
        console.log('File data:', data);
    });
};
```

**5. Event Listeners**

Listen to file manager events:

```javascript
plugin.init = function() {
    fm.on('fileSelect', function(files) {
        console.log('Files selected:', files);
    });

    fm.on('directoryChange', function(path) {
        console.log('Directory changed:', path);
    });
};
```

### Built-in JavaScript Plugins

#### Text Editor Plugin

Provides in-browser text file editing with syntax highlighting and line numbers.

**Features:**
- Line numbers
- Syntax highlighting (basic)
- Find & replace
- Save/overwrite functionality
- Character/word/line count

**Usage:**
```html
<script src="js/plugins/myfilemanager-text-editor.js"></script>
```

**Supported Extensions:**
`txt`, `md`, `log`, `json`, `xml`, `csv`, `html`, `css`, `js`, `php`, `py`, `java`, etc.

#### Image Editor Plugin

Provides basic image editing capabilities.

**Features:**
- Rotate (90°, 180°, 270°)
- Flip (horizontal/vertical)
- Crop
- Resize
- Brightness/Contrast adjustments
- Save edited image

**Usage:**
```html
<script src="js/plugins/myfilemanager-image-editor.js"></script>
```

**Supported Formats:**
`jpg`, `jpeg`, `png`, `gif`, `webp`, `bmp`

---

## PHP Backend System

### Core PHP Files

#### `connector.php`
Main API endpoint that handles all requests.

**Responsibilities:**
- Request routing
- Authentication validation
- Security checks (token, IP whitelist)
- Command execution
- Response formatting

**API Commands:**
- `open` - Open directory
- `upload` - Upload file
- `download` - Download file
- `delete` - Delete file/folder
- `rename` - Rename file/folder
- `copy` - Copy files
- `cut` - Move files
- `mkdir` - Create directory
- `search` - Search files
- `info` - Get file information
- `restore` - Restore from trash

**Request Format:**
```
POST/GET: connector.php?cmd=open&target=base64Hash
```

**Response Format:**
```json
{
    "cwd": {
        "hash": "...",
        "name": "Root",
        "mime": "directory",
        "read": true,
        "write": true
    },
    "files": [
        {
            "name": "document.pdf",
            "hash": "ZG9jdW1lbnQucGRm",
            "mime": "application/pdf",
            "size": 12345,
            "ts": 1638360000,
            "read": true,
            "write": true
        }
    ]
}
```

#### `myfilemanager.php`
Core file manager class with all file operations.

**Key Methods:**
- `execute($cmd, $params)` - Execute command
- `cmdOpen($params)` - Open directory
- `cmdUpload($params)` - Handle upload
- `cmdDownload($params)` - Download file
- `cmdDelete($params)` - Delete files
- `cmdRename($params)` - Rename file
- `cmdCopy($params)` - Copy files
- `cmdCut($params)` - Move files
- `cmdMkdir($params)` - Create directory
- `cmdSearch($params)` - Search files
- `cmdInfo($params)` - Get file info
- `cmdRestore($params)` - Restore from trash

**Security Features:**
- Path traversal protection
- Permission checking
- Quota enforcement
- Extension filtering
- MIME type validation

#### `chunkuploader.php`
Handles chunked file uploads for large files.

**Features:**
- Automatic chunk assembly
- Temporary chunk storage
- MIME type validation after merge
- Unique filename generation
- Cleanup of old chunks

**Chunk Upload Flow:**
1. Client splits file into chunks
2. Each chunk uploaded with part index
3. Server stores chunks in temp directory
4. When all chunks received, merge into final file
5. Validate MIME type
6. Move to destination
7. Clean up temp chunks

#### `security.php`
Security utilities and validation.

**Methods:**
- `isPathSafe($path, $rootPath)` - Check path traversal
- `sanitizeFilename($filename)` - Sanitize filename
- `generateToken($secret, $userId)` - Generate auth token
- `validateToken($token, $secret, $userId)` - Validate token
- `isAllowedMimeType($mime, $allowed)` - Check MIME type
- `hasBlockedExtension($filename, $blocked)` - Check extension

---

### PHP Plugin System

#### Plugin Interface

All PHP plugins must implement the `FileManagerPlugin` interface:

```php
<?php
/**
 * File Manager Plugin Interface
 */
interface FileManagerPlugin {
    /**
     * Get plugin name
     * @return string
     */
    public function getName();

    /**
     * Initialize plugin
     * @param array $config Configuration
     */
    public function init($config);

    /**
     * List files in path
     * @param string $path Directory path
     * @return array Files array
     */
    public function listFiles($path);

    /**
     * Read file content
     * @param string $path File path
     * @return string File content
     */
    public function readFile($path);

    /**
     * Write file content
     * @param string $path File path
     * @param string $content File content
     * @return bool Success
     */
    public function writeFile($path, $content);

    /**
     * Delete file or directory
     * @param string $path Path to delete
     * @return bool Success
     */
    public function delete($path);

    /**
     * Create directory
     * @param string $path Directory path
     * @return bool Success
     */
    public function createDirectory($path);

    /**
     * Rename/move file or directory
     * @param string $oldPath Old path
     * @param string $newPath New path
     * @return bool Success
     */
    public function rename($oldPath, $newPath);

    /**
     * Check if path exists
     * @param string $path Path to check
     * @return bool Exists
     */
    public function exists($path);
}
```

### Creating Custom PHP Plugins

#### Example: FTP Plugin

The included FTP plugin demonstrates how to create a storage backend plugin:

```php
<?php
require_once 'plugininterface.php';

/**
 * FTP Storage Plugin
 * Allows file manager to work with FTP servers
 */
class FTPPlugin implements FileManagerPlugin {
    private $connection;
    private $config;

    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }

    public function getName() {
        return 'FTPPlugin';
    }

    public function init($config) {
        // Initialization logic
    }

    private function connect() {
        $this->connection = ftp_connect(
            $this->config['host'], 
            $this->config['port'] ?? 21
        );

        if (!$this->connection) {
            throw new Exception('Could not connect to FTP server');
        }

        ftp_login(
            $this->connection, 
            $this->config['username'], 
            $this->config['password']
        );

        if ($this->config['passive'] ?? true) {
            ftp_pasv($this->connection, true);
        }
    }

    public function listFiles($path) {
        $list = ftp_nlist($this->connection, $path);
        $files = [];

        foreach ($list as $item) {
            $files[] = $this->getFileInfo($item);
        }

        return $files;
    }

    public function readFile($path) {
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

        if (!ftp_get($this->connection, $tempFile, $path, FTP_BINARY)) {
            throw new Exception('Could not download file from FTP');
        }

        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return $content;
    }

    public function writeFile($path, $content) {
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tempFile, $content);

        $result = ftp_put($this->connection, $path, $tempFile, FTP_BINARY);
        unlink($tempFile);

        return $result;
    }

    // Implement other interface methods...
}
```

#### Using the Plugin

```php
// In connector.php
require_once 'src/plugins/ftpplugin.php';

$ftpConfig = [
    'host' => 'ftp.example.com',
    'port' => 21,
    'username' => 'ftpuser',
    'password' => 'ftppass',
    'passive' => true
];

$ftpPlugin = new FTPPlugin($ftpConfig);

$config['plugins'][] = $ftpPlugin;
```

#### Plugin Use Cases

**1. Cloud Storage Integration**
- Amazon S3 plugin
- Google Drive plugin
- Dropbox plugin

**2. Database Storage**
- Store files in database (BLOB)
- Metadata management

**3. Virtual Filesystems**
- Git repository browser
- Archive browser (ZIP, TAR)

**4. Custom Processing**
- Automatic image optimization
- Document conversion
- Virus scanning

---

## Security Features

### End-to-End Encryption

My File Manager supports client-side encryption using **AES-256-GCM** with **PBKDF2** key derivation.

#### How It Works

1. **Key Derivation**: User password → PBKDF2 (100,000 iterations) → 256-bit key
2. **Encryption**: File data → AES-256-GCM → Encrypted data
3. **Storage**: Encrypted files stored on server
4. **Decryption**: Download → Decrypt in browser → Original file

#### Enabling Encryption

**Generate Encryption Key:**

```javascript
// Derive key from user password
var password = 'user-secure-password';
var salt = window.MyFileManagerCrypto.generateSalt();

window.MyFileManagerCrypto.deriveKeyFromPassword(password, salt)
    .then(function(key) {
        // Initialize file manager with encryption
        var fm = new MyFileManager('#filemanager', {
            url: 'src/connector.php',
            cryptFiles: true,
            encryptionKey: key,
            encryptionSalt: salt,
            // Exclude large media files from encryption
            cryptExclude: ['video/*', 'audio/*']
        });
    });
```

#### Selective Encryption

Exclude specific MIME types from encryption:

```javascript
cryptExclude: [
    'video/*',           // All videos
    'audio/*',           // All audio
    'image/jpeg',        // JPEG images only
    'application/pdf'    // PDF files
]
```

#### Security Notes

- **WebCrypto API**: Used in modern browsers for native performance
- **CryptoJS Fallback**: Loaded automatically for older browsers
- **Zero Knowledge**: Server never sees unencrypted data or keys
- **PBKDF2**: 100,000 iterations protect against brute force
- **GCM Mode**: Authenticated encryption prevents tampering

---

### Custom Authentication

Implement your own authentication logic with the `authCallback` function.

#### Basic Authentication

```php
'authCallback' => function() {
    // Check if user is logged in (your session logic)
    if (!isset($_SESSION['user_id'])) {
        return false; // Not authenticated
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'quota' => 5368709120, // 5GB in bytes
        'permissions' => ['read', 'write', 'delete', 'upload', 'download']
    ];
}
```

#### Database Authentication

```php
'authCallback' => function() use ($db) {
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        return false;
    }

    // Fetch user from database
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'quota' => $user['quota_bytes'],
        'permissions' => json_decode($user['permissions'])
    ];
}
```

#### API Token Authentication

```php
'authCallback' => function() use ($db) {
    // Get token from header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token)) {
        return false;
    }

    // Validate token
    $stmt = $db->prepare("SELECT * FROM api_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        return false;
    }

    // Get user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$tokenData['user_id']]);
    $user = $stmt->fetch();

    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'quota' => $user['quota_bytes'],
        'permissions' => json_decode($user['permissions'])
    ];
}
```

---

### Token-Based Security

Enable HMAC-SHA256 token authentication for enhanced security.

#### Enable Token Auth
This will send the token in header (bearer authentication)
```php
'security' => [
    'enableTokenAuth' => true,
    'tokenSecret' => 'your-long-random-secret-key-change-this',
    'enableIPWhitelist' => false,
    'allowedIPs' => []
]
```

#### Generate Token (PHP HMAC-SHA256)
Enable the token auth in the connector.php and then generate the hmac-sha256 token with expiriation configurable in security.php (default 12 hours)

```php
require_once 'src/security.php';

$userId = 'user123';
$secret = 'your-long-random-secret-key-change-this';

$token = Security::generateToken($secret, $userId);
echo "Token: " . $token;
```

#### Use Token in JavaScript

```javascript
var fm = new MyFileManager('#filemanager', {
    url: 'src/connector.php',
    token: '<?php echo $token ?>' //generated-token-here
});
```

#### IP Whitelist

Restrict access to specific IP addresses:

```php
'security' => [
    'enableIPWhitelist' => true,
    'allowedIPs' => [
        '127.0.0.1',
        '192.168.1.100',
        '10.0.0.0/8' // CIDR notation supported
    ]
]
```

---

## Customization

### Custom Menus and Context Menus

#### Custom Menu Bar Items

Add items to the Windows-style menu bar:

```javascript
var fm = new MyFileManager('#filemanager', {
    customMenus: [
        {
            label: 'Export',
            icon: '📤',
            action: function(fm) {
                var selected = fm.getSelectedFiles();
                if (selected.length === 0) {
                    alert('Please select files to export');
                    return;
                }
                // Your export logic
                console.log('Exporting:', selected);
            }
        },
        {
            label: 'Settings',
            icon: '⚙️',
            action: function(fm) {
                // Open settings dialog
            }
        }
    ]
});
```

#### Custom Context Menu Items

Add right-click context menu entries:

```javascript
var fm = new MyFileManager('#filemanager', {
    customContextMenu: [
        {
            label: 'Open in New Tab',
            icon: '🔗',
            condition: function(files) {
                // Show only for single file
                return files.length === 1 && files[0].mime !== 'directory';
            },
            action: function(files, fm) {
                var file = files[0];
                window.open(file.url, '_blank');
            }
        },
        {
            label: 'Share via Email',
            icon: '📧',
            condition: function(files) {
                return files.length > 0;
            },
            action: function(files, fm) {
                var fileNames = files.map(f => f.name).join(', ');
                window.location.href = 'mailto:?subject=Shared Files&body=' + 
                    encodeURIComponent('I shared: ' + fileNames);
            }
        }
    ]
});
```

### Custom Icons

Replace default PNG icons with your own:

1. **Icon Locations**: `assets/`
2. **Icon Size**: 48x48px PNG (responsive design)
3. **Icon Names**:
   - `folder.png` - Directory icon
   - `file.png` - Default file icon
   - `image.png` - Image files
   - `video.png` - Video files
   - `audio.png` - Audio files
   - `document.png` - Document files
   - `archive.png` - Archive files
   - `code.png` - Code files

Simply replace the PNG files in `assets/icons/` directory with your custom icons.

### Brand Logo

Add your company logo to the file manager:

```javascript
var fm = new MyFileManager('#filemanager', {
    brandLogo: 'assets/logo.png',
    brandLink: 'https://yourcompany.com',
    brandTarget: '_blank',
    homeLabel: 'My Files'
});
```

The logo appears in the toolbar and can be clicked to navigate to your website.

### Themes

#### Using Built-in Themes

```javascript
var fm = new MyFileManager('#filemanager', {
    theme: 'dark' // or 'light'
});

// Change theme dynamically
fm.setTheme('dark');
```

#### Custom Theme CSS

Create your own theme by overriding CSS variables:

```css
/* custom-theme.css */
.myfilemanager {
    --mfm-bg-color: #1a1a1a;
    --mfm-text-color: #e0e0e0;
    --mfm-border-color: #333;
    --mfm-hover-bg: #2a2a2a;
    --mfm-selected-bg: #0066cc;
    --mfm-toolbar-bg: #252525;
    --mfm-modal-bg: #2a2a2a;
}
```

Load your custom theme:

```html
<link rel="stylesheet" href="css/myfilemanager.css">
<link rel="stylesheet" href="css/custom-theme.css">
```

---

## Internationalization (i18n)

My File Manager includes built-in translations for multiple languages.

### Supported Languages

- **en** - English
- **it** - Italian (Italiano)
- **es** - Spanish (Español)
- **de** - German (Deutsch)
- **fr** - French (Français)
- **pt** - Portuguese (Português)
- **zh** - Chinese (中文)
- **ja** - Japanese (日本語)
- **ru** - Russian (Русский)
- **ar** - Arabic
- **hi** - Hindi
- **ko** - Korean
- **nl** - Dutch
- **tr** - Turkish
- **pl** - Polish
- **sv** - Swedish
- **el** - Greek
- **vi** - Vietnamese

### Set Language

```javascript
var fm = new MyFileManager('#filemanager', {
    lang: 'it' // Italian
});
```

### Add Custom Translation

Edit `js/i18n.js` to add your language:

```javascript
window.MyFileManagerI18n['nl'] = { // Dutch
    fileManager: 'Bestandsbeheerder',
    file: 'Bestand',
    folder: 'Map',
    upload: 'Uploaden',
    download: 'Downloaden',
    delete: 'Verwijderen',
    // ... add all translations
};
```

Use the new language:

```javascript
var fm = new MyFileManager('#filemanager', {
    lang: 'nl'
});
```

---

## Browser Compatibility

### Supported Browsers

| Browser | Version | Encryption Support |
|---------|---------|-------------------|
| Chrome | 90+ | ✅ WebCrypto |
| Firefox | 88+ | ✅ WebCrypto |
| Safari | 14+ | ✅ WebCrypto |
| Edge | 90+ | ✅ WebCrypto |
| Opera | 76+ | ✅ WebCrypto |
| Chrome Mobile | Latest | ✅ WebCrypto |
| Safari iOS | 14+ | ✅ WebCrypto |
| Samsung Internet | Latest | ✅ WebCrypto |

### Legacy Browser Support

For older browsers without WebCrypto API:
- CryptoJS automatically loaded as fallback
- Encryption still works but slightly slower
- All other features fully supported

### Required Features

- JavaScript enabled
- Cookies/LocalStorage (for preferences)
- XMLHttpRequest or Fetch API
- File API (for uploads)
- Blob API (for downloads)

---

## Contributing

We welcome contributions! Here's how you can help:

### Reporting Bugs

Open an issue on GitHub with:
- Browser and version
- PHP version
- Steps to reproduce
- Error messages
- Screenshots (if applicable)

### Suggesting Features

Open a feature request with:
- Clear description
- Use case
- Proposed implementation (optional)

### Pull Requests

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open Pull Request

#### Development Guidelines

- Follow existing code style
- Add comments in English
- Test thoroughly before submitting
- Update documentation if needed

---

## License

This project is licensed under the **MIT License**.

```
MIT License

Copyright (c) 2025 Oscar Cosimo & MYETV Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Credits

**Developed by:**
- Oscar Cosimo
- MYETV Team

**Special Thanks:**
- Contributors and testers
- Open source community

**Built With:**
- Vanilla JavaScript (no frameworks)
- PHP 7.4+
- WebCrypto API
- CryptoJS (fallback)

---

## Support

- **Documentation**: [README.md](README.md)
- **Issues**: [GitHub Issues](https://github.com/OskarCosimo/My-File-Manager-Opensource/issues)
- **Discussions**: [GitHub Discussions](https://github.com/OskarCosimo/My-File-Manager-Opensource/discussions)

---

Made with ❤️ by Oscar Cosimo & MYETV Team

[⭐ Star us on GitHub](https://github.com/yourusername/my-file-manager-opensource)
