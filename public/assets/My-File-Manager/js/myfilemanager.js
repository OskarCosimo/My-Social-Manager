/**
 * My File Manager - Enhanced JavaScript UI
 * MyFileManager - Secure File Manager with Universal Crypto Support
 * Embedded CryptoJS + WebCrypto fallback for ALL browsers/WebViews
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

/**
 * Load CryptoJS ONLY if WebCrypto not available (bandwidth savings)
 */
async function loadCryptoJSIfNeeded() {
    // Skip if WebCrypto available (modern browsers)
    if (typeof crypto !== 'undefined' && crypto.subtle && typeof crypto.subtle.importKey === 'function') {
        //console.log('WebCrypto detected - CryptoJS NOT loaded');
        return;
    }

    // WebCrypto NOT available - load CryptoJS as fallback
    if (window.CryptoJS) return;

    try {
        const response = await fetch('https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js');
        const cryptoJSScript = await response.text();
        eval(cryptoJSScript);
        //console.log('CryptoJS loaded as WebCrypto fallback');
    } catch (error) {
        console.warn('CryptoJS load failed - WebCrypto required:', error);
    }
}

// Auto-init crypto on page load
loadCryptoJSIfNeeded();

// Check Web Crypto API availability
function hasWebCrypto() {
    return typeof crypto !== 'undefined' &&
        crypto.subtle &&
        typeof crypto.subtle.importKey === 'function';
}

// Derive secure key from password (PBKDF2 100k iterations)
async function deriveKeyFromPassword(password, salt) {
    if (hasWebCrypto()) {
        // WebCrypto PBKDF2 (native, fastest)
        const encoder = new TextEncoder();
        const keyMaterial = await crypto.subtle.importKey(
            'raw', encoder.encode(password),
            { name: 'PBKDF2' }, false, ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: 100000, hash: 'SHA-256' },
            keyMaterial,
            { name: 'AES-GCM', length: 256 },
            false, ['encrypt', 'decrypt']
        );
    } else if (window.CryptoJS) {
        // CryptoJS PBKDF2 fallback (secure)
        return CryptoJS.PBKDF2(password, salt, {
            keySize: 8, // 256 bits
            iterations: 100000,
            hasher: CryptoJS.algo.SHA256
        });
    }
    throw new Error('No crypto engine available');
}

// Encrypt file data (AES-256-GCM authenticated encryption)
async function encryptFileData(fileData, key) {
    if (hasWebCrypto()) {
        // WebCrypto AES-GCM
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv }, key, fileData
        );
        // Prepend IV (12 bytes) + encrypted data
        const result = new Uint8Array(12 + encrypted.byteLength);
        result.set(iv, 0);
        result.set(new Uint8Array(encrypted), 12);
        return result;
    } else if (window.CryptoJS) {
        // CryptoJS AES-GCM fallback
        const keyStr = CryptoJS.enc.Utf8.parse(key.key || key.toString(CryptoJS.enc.Hex));
        const iv = CryptoJS.lib.WordArray.random(12);
        const encrypted = CryptoJS.AES.encrypt(fileData, keyStr, {
            iv: iv,
            mode: CryptoJS.mode.GCM,
            padding: CryptoJS.pad.NoPadding
        });
        // Prepend IV + ciphertext
        const result = iv.concat(encrypted.ciphertext);
        return new Uint8Array(result.sigBytes);
    }
    throw new Error('Encryption not available');
}

// Decrypt file data (AES-256-GCM)
async function decryptFileData(encryptedData, key) {
    if (hasWebCrypto()) {
        // WebCrypto AES-GCM
        const iv = encryptedData.slice(0, 12);
        const data = encryptedData.slice(12);
        return await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv }, key, data
        );
    } else if (window.CryptoJS) {
        // CryptoJS AES-GCM
        const iv = CryptoJS.lib.WordArray.create(encryptedData.slice(0, 12));
        const ciphertext = CryptoJS.lib.WordArray.create(encryptedData.slice(12));
        const keyStr = CryptoJS.enc.Utf8.parse(key.key || key.toString(CryptoJS.enc.Hex));

        const decrypted = CryptoJS.AES.decrypt(
            { ciphertext: ciphertext },
            keyStr,
            { iv: iv, mode: CryptoJS.mode.GCM, padding: CryptoJS.pad.NoPadding }
        );
        return decrypted.sigBytes ? new Uint8Array(decrypted.sigBytes) : new Uint8Array(0);
    }
    throw new Error('Decryption not available');
}

// Generate random salt (16 bytes)
function generateSalt() {
    if (hasWebCrypto()) {
        return crypto.getRandomValues(new Uint8Array(16));
    }
    // Fallback random salt
    const salt = new Uint8Array(16);
    const randomValues = new Uint32Array(4);
    crypto.getRandomValues(randomValues);
    for (let i = 0; i < 16; i++) {
        salt[i] = randomValues[i % 4] >> (i * 2) & 0xFF;
    }
    return salt;
}

// Convert ArrayBuffer to base64 (for storage)
function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

// Convert base64 to ArrayBuffer
function base64ToArrayBuffer(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

// Expose global functions for MyFileManager
window.MyFileManagerCrypto = {
    hasWebCrypto,
    deriveKeyFromPassword,
    encryptFileData,
    decryptFileData,
    generateSalt,
    arrayBufferToBase64,
    base64ToArrayBuffer
};
//debug only
//console.log('MyFileManager Crypto ready - WebCrypto:', hasWebCrypto(), 'CryptoJS:', !!window.CryptoJS);

(function (window) {
    'use strict';

    /**
    * MyFileManager Constructor
    */
    function MyFileManager(selector, options) {
        this.container = document.querySelector(selector);

        if (!this.container) {
            throw new Error('Container not found: ' + selector);
        }

        // Default options
        this.options = {
            url: '/connector.php',
            downloadUrl: null, // Custom download URL (if null, uses connector)
            token: null,
            lang: navigator.language.split('-')[0] || 'en',
            viewMode: 'list',
            theme: 'light',
            maxFileSize: 524288000,
            chunkSize: 1048576,
            trashPath: '.trash', // Trash folder name (without trailing slash)
            debug: false, // Enable console debug messages
            // Brand logo
            brandLogo: null,        // URL of logo image
            brandLink: null,        // Link when clicking logo
            brandTarget: '_blank',  // Link target (_blank, _self, etc.)
            homeLabel: 'Home',
            cryptFiles: false,        // Enable client-side file encryption
            encryptionKey: null,      // User-provided encryption key (derived from password)
            encryptionSalt: null,     // Salt for key derivation (Uint8Array)
            cryptExclude: [''], // exclude patterns from encryption; example: ['video/*', 'audio/*']
            showUrlOnProperties: false,  // Show URL field in properties modal
            features: {
                upload: true,
                download: true,
                delete: true,
                rename: true,
                copy: true,
                cut: true,
                paste: true,
                mkdir: true,
                search: true,
                info: true,
                contextMenu: true
            },
            customMenus: [], // Custom menus
            customContextMenu: [],
            onInit: null,
            onUploadStart: null,
            onUploadProgress: null,
            onUploadComplete: null,
            onUploadError: null,
            onFileOpen: null,
            onFileSelect: null,
            onError: null,
            onChange: null,
            customFileOpener: null,
            showHidden: false,
            sortBy: 'name',
            sortOrder: 'asc',
            autoRefresh: false,
            hideExtensions: [], // Extensions to hide from view (e.g., ['.tmp', '.log', '.cache'])
            //these are defualt banned extensions for uploads or rename, if you edit the option at the initization of the file manager, make sure to include the default ones plus your custom ones for security purposes. If you change it, check also connector.php
            banExtensions: [
                // Unix/Linux executables
                'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
                'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
                'rb', 'rbw', 'cgi', 'fcgi',

                // Windows executables
                'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
                'msi', 'msp', 'dll', 'ocx', 'sys', 'scr',

                // Web server scripts
                'php', 'php3', 'php4', 'php5', 'phtml', 'php7',
                'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
                'jsp', 'jspx', 'jsw', 'jssp', 'do',
                'cfm', 'cfml', 'cfc',

                // Dangerous scripts
                'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',

                // MAC/OS dangerous
                'app', 'dmg', 'pkg', 'mpkg',

                // dangerous config files
                'conf', 'cnf', 'ini', 'cfg', 'config',

                // htaccess and config server files
                'htaccess', 'htpasswd', 'htgroup',

                // Macro virus (Office)
                'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
            ],

        };

        this.options = Object.assign(this.options, options);

        // Plugin system support
        this.plugins = [];
        // autorefresh option
        this.autoRefreshInterval = null;

        this.hasWebCrypto = window.MyFileManagerCrypto.hasWebCrypto;
        this.deriveKeyFromPassword = window.MyFileManagerCrypto.deriveKeyFromPassword;
        this.encryptFileData = window.MyFileManagerCrypto.encryptFileData;
        this.decryptFileData = window.MyFileManagerCrypto.decryptFileData;

        this.wakeLock = null;

        // Internal state
        this.state = {
            currentPath: '',
            currentHash: '',
            selectedFiles: [],
            clipboard: null,
            uploadQueue: [],
            files: [],
            quota: null,
            activeMenu: null,
            history: [],
            historyIndex: -1
        };

        this._letterNavState = { lastLetter: null, index: -1 };

        // Upload stats
        this.uploadStats = {
            startTime: 0,
            totalBytes: 0,
            uploadedBytes: 0,
            speedSamples: [],
            avgSpeed: 0,
            maxSamples: 10
        };

        // Load translations
        this.i18n = window.MyFileManagerI18n[this.options.lang] || window.MyFileManagerI18n['en'];

        // Initialize
        this.init();
    }

    /**
    * Check if file should be encrypted based on MIME type
    */
    MyFileManager.prototype.shouldEncrypt = function (mime) {
        if (!this.options.cryptFiles || !this.options.encryptionKey) {
            return false;
        }

        // Check if MIME is in exclusion list
        for (var i = 0; i < this.options.cryptExclude.length; i++) {
            var pattern = this.options.cryptExclude[i];
            if (pattern.endsWith('/*')) {
                // Wildcard match: "video/*" matches "video/mp4"
                var prefix = pattern.slice(0, -2);
                if (mime.startsWith(prefix)) {
                    return false;
                }
            } else if (mime === pattern) {
                // Exact match
                return false;
            }
        }

        return true;
    };

    /**
    * Initialize file manager
    */
    MyFileManager.prototype.init = function () {
        this.cryptoMode = this.hasWebCrypto() ? 'webcrypto' : 'fallback';
        this.applyTheme();
        this.buildUI();
        this.bindEvents();
        this.state.history = [];
        this.state.historyIndex = -1;

        this.requestWakeLock(); // Keep screen on during initial load
        this.open('');

        // Add visibility change listener
        var self = this;
        document.addEventListener('visibilitychange', function () {
            self.handleVisibilityChange();
        });

        this.trigger('onInit');

        // Initialize plugins
        this.initPlugins();

        // Setup auto-refresh if enabled
        this.setupAutoRefresh();
    };

    /**
    * Internal debug logger - only logs when debug option is enabled
    */
    MyFileManager.prototype._debug = function (type) {
        if (this.options.debug) {
            var args = Array.prototype.slice.call(arguments, 1);
            args.unshift('[MyFileManager]');

            if (type === 'error') {
                console.error.apply(console, args);
            } else if (type === 'warn') {
                console.warn.apply(console, args);
            } else {
                console.log.apply(console, args);
            }
        }
    };

    /**
    * Build UI
    */
    MyFileManager.prototype.buildUI = function () {
        var self = this;

        this.container.classList.add('myfilemanager');

        // Menu Bar
        var menubar = document.createElement('div');
        menubar.className = 'mfm-menubar';

        // Toolbar
        var toolbar = document.createElement('div');
        toolbar.className = 'mfm-toolbar';
        toolbar.innerHTML = `
        <div class="mfm-toolbar-left">
            <button class="mfm-btn" data-action="back" title="${this.i18n.back}">
                <span class="mfm-icon-back"></span>
            </button>
            <button class="mfm-btn" data-action="forward" title="${this.i18n.forward}">
                <span class="mfm-icon-forward"></span>
            </button>
            <button class="mfm-btn" data-action="up" title="${this.i18n.up}">
                <span class="mfm-icon-up"></span>
            </button>
            <button class="mfm-btn" data-action="refresh" title="${this.i18n.refresh}">
                <span class="mfm-icon-refresh"></span>
            </button>
        </div>
        <div class="mfm-toolbar-right">
            <input type="text" class="mfm-search" placeholder="${this.i18n.search}" ${!this.options.features.search ? 'disabled' : ''}>
        </div>
    `;

        // Trash toolbar (shown only when in trash)
        var trashToolbar = document.createElement('div');
        trashToolbar.className = 'mfm-trash-toolbar';
        trashToolbar.style.display = 'none';
        trashToolbar.innerHTML = `
        <button class="mfm-toolbar-btn" data-action="restore-selected">
            ♻️ <span>${this.i18n.restoreSelected}</span>
        </button>
        <button class="mfm-toolbar-btn mfm-btn-danger" data-action="empty-trash">
            🗑️ <span>${this.i18n.emptyTrash}</span>
        </button>
    `;

        // Address Bar
        var addressbar = document.createElement('div');
        addressbar.className = 'mfm-addressbar';
        addressbar.innerHTML = `
        <span class="mfm-addressbar-label">${this.i18n.address || 'Address'}:</span>
        <div class="mfm-breadcrumb"></div>
    `;

        // Content area
        var content = document.createElement('div');
        content.className = 'mfm-content';
        content.innerHTML = `
        <div class="mfm-files mfm-view-${this.options.viewMode}"></div>
    `;

        // Status bar
        var statusbar = document.createElement('div');
        statusbar.className = 'mfm-statusbar';
        statusbar.innerHTML = `
        <div class="mfm-status-left">
            <span class="mfm-status-items">0 ${this.i18n.items}</span>
        </div>
        <div class="mfm-status-right">
            <span class="mfm-status-quota"></span>
        </div>
    `;

        // Context menu
        var contextMenu = document.createElement('div');
        contextMenu.className = 'mfm-context-menu';
        contextMenu.style.display = 'none';

        // Upload input (hidden)
        var uploadInput = document.createElement('input');
        uploadInput.type = 'file';
        uploadInput.multiple = true;
        uploadInput.style.display = 'none';
        uploadInput.className = 'mfm-upload-input';

        // Modal container
        var modal = document.createElement('div');
        modal.className = 'mfm-modal';
        modal.style.display = 'none';

        // Upload Progress Modal - Fixed version with proper filename handling and progress bar
        var uploadModal = document.createElement('div');
        uploadModal.className = 'mfm-upload-modal';
        uploadModal.style.display = 'none';
        uploadModal.innerHTML = `
        <div class="mfm-upload-modal-content">
            <div class="mfm-upload-modal-header">
                <h3 class="mfm-upload-modal-title">Uploading File</h3>
                <button class="mfm-upload-modal-close">&times;</button>
            </div>
            <div class="mfm-upload-modal-body">
                <div class="mfm-upload-info">
                    <div class="mfm-upload-filename"></div>
                    
                    <!-- Visual Progress Bar -->
                    <div class="mfm-progress-container">
                        <div class="mfm-progress-bar">
                            <div class="mfm-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="mfm-progress-text">0%</div>
                    </div>
                    
                    <!-- Upload Statistics -->
                    <div class="mfm-upload-stats">
                        <span class="mfm-upload-speed">0 MB/s</span>
                        <span class="mfm-upload-time">Calculating...</span>
                    </div>
                </div>
                
                <!-- Upload Status Message -->
                <div class="mfm-upload-status" style="margin-top: 15px; display: none;">
                    <div class="mfm-upload-status-message"></div>
                </div>
                
                <!-- Upload Actions -->
                <div class="mfm-upload-actions" style="margin-top: 15px; text-align: right; display: none;">
                    <button class="mfm-btn mfm-upload-close-btn">Close</button>
                </div>
            </div>
        </div>
    `;

        // Download Progress Modal
        var downloadModal = document.createElement('div');
        downloadModal.className = 'mfm-download-modal';
        downloadModal.style.display = 'none';
        downloadModal.innerHTML = `
    <div class="mfm-download-modal-content">
        <div class="mfm-download-modal-header">
            <h3 class="mfm-download-modal-title">Downloading File</h3>
            <button class="mfm-download-modal-close">&times;</button>
        </div>
        <div class="mfm-download-modal-body">
            <div class="mfm-download-info">
                <div class="mfm-download-filename"></div>
                <div class="mfm-download-filesize"></div>
            </div>
            <!-- Progress Bar -->
            <div class="mfm-progress-container">
                <div class="mfm-progress-bar">
                    <div class="mfm-progress-fill" style="width: 0%"></div>
                </div>
                <div class="mfm-progress-text">0%</div>
            </div>
            <!-- Download Stats -->
            <div class="mfm-download-stats">
                <span class="mfm-download-speed">0 MB/s</span>
                <span class="mfm-download-time">Calculating...</span>
            </div>
            <!-- Status Message -->
            <div class="mfm-download-status" style="margin-top: 15px; display: none;">
                <div class="mfm-download-status-message"></div>
            </div>
            <!-- Actions -->
            <div class="mfm-download-actions" style="margin-top: 15px; text-align: right; display: none;">
                <button class="mfm-btn mfm-download-close-btn">Close</button>
            </div>
        </div>
    </div>
`;

        // Bind close buttons
        var downloadCloseBtn = downloadModal.querySelector('.mfm-download-modal-close');
        var downloadCloseBtnAction = downloadModal.querySelector('.mfm-download-close-btn');

        var self = this;
        if (downloadCloseBtn) {
            downloadCloseBtn.addEventListener('click', function () {
                if (self.currentDownloadController) {
                    if (confirm('Download in progress. Cancel download?')) {
                        self.cancelDownload();
                        self.hideDownloadProgress();
                    }
                } else {
                    self.hideDownloadProgress();
                }
            });
        }

        if (downloadCloseBtnAction) {
            downloadCloseBtnAction.addEventListener('click', function () {
                self.hideDownloadProgress();
            });
        }

        // Append elements
        this.container.appendChild(menubar);
        this.container.appendChild(toolbar);
        this.container.appendChild(addressbar);
        this.container.appendChild(trashToolbar);
        this.container.appendChild(content);
        this.container.appendChild(statusbar);
        this.container.appendChild(contextMenu);
        this.container.appendChild(uploadInput);
        this.container.appendChild(modal);
        this.container.appendChild(uploadModal);
        this.container.appendChild(downloadModal);

        // Store references
        this.elements = {
            menubar: menubar,
            toolbar: toolbar,
            addressbar: addressbar,
            content: content,
            statusbar: statusbar,
            contextMenu: contextMenu,
            uploadInput: uploadInput,
            modal: modal,
            uploadModal: uploadModal,
            downloadModal: downloadModal,
            filesContainer: content.querySelector('.mfm-files'),
            breadcrumb: addressbar.querySelector('.mfm-breadcrumb'),
            search: toolbar.querySelector('.mfm-search'),
            trashToolbar: trashToolbar
        };

        // Populate menubar and bind events
        this.elements.menubar.innerHTML = this.buildMenuBar();
        this.bindMenuEvents();

        // Bind upload modal close buttons
        var uploadCloseBtn = uploadModal.querySelector('.mfm-upload-modal-close');
        var uploadCloseBtnAction = uploadModal.querySelector('.mfm-upload-close-btn');

        if (uploadCloseBtn) {
            uploadCloseBtn.addEventListener('click', function () {
                if (self.currentUploadXHR) {
                    if (confirm('Upload in progress. Cancel upload?')) {
                        self.cancelUpload();
                        self.hideUploadProgress();
                    }
                } else {
                    self.hideUploadProgress();
                }
            });
        }

        if (uploadCloseBtnAction) {
            uploadCloseBtnAction.addEventListener('click', function () {
                self.hideUploadProgress();
            });
        }

        // Trash toolbar events
        trashToolbar.addEventListener('click', function (e) {
            var target = e.target.closest('[data-action]');
            if (!target) return;

            var action = target.getAttribute('data-action');

            switch (action) {
                case 'restore-selected':
                    self.restoreSelected();
                    break;
                case 'empty-trash':
                    self.emptyTrash();
                    break;
            }
        });
    };

    // Request wake lock to prevent screen sleep
    MyFileManager.prototype.requestWakeLock = async function () {
        if ('wakeLock' in navigator) {
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
                this._debug('log', 'Wake lock acquired');

                // Re-acquire wake lock when page becomes visible again
                this.wakeLock.addEventListener('release', () => {
                    this._debug('log', 'Wake lock released');
                });
            } catch (err) {
                this._debug('warn', `Wake lock failed: ${err.name}, ${err.message}`);
            }
        }
    };

    // Release wake lock
    MyFileManager.prototype.releaseWakeLock = function () {
        if (this.wakeLock !== null) {
            this.wakeLock.release()
                .then(() => {
                    this.wakeLock = null;
                });
        }
    };

    // Handle visibility change to reacquire lock
    MyFileManager.prototype.handleVisibilityChange = async function () {
        if (this.wakeLock !== null && document.visibilityState === 'visible') {
            await this.requestWakeLock();
        }
    };

    /**
    * Apply theme
    */
    MyFileManager.prototype.applyTheme = function () {
        this.container.classList.remove('mfm-theme-light', 'mfm-theme-dark');
        this.container.classList.add('mfm-theme-' + this.options.theme);
    };

    /**
    * Build menu bar
    */
    MyFileManager.prototype.buildMenuBar = function () {
        var self = this;

        // Define file menu items array
        var fileItems = [
            {
                id: 'upload',
                label: this.i18n.uploadFile,
                icon: '📤',
                shortcut: 'Ctrl+Shift+U'
            },
            {
                id: 'download',
                label: this.i18n.downloadFile,
                icon: '📥',
                shortcut: 'Ctrl+Shift+D'
            },
            { type: 'separator' },
            {
                id: 'mkdir',
                label: this.i18n.newFolder,
                icon: '📁',
                shortcut: 'Ctrl+Shift+M'
            },
            { type: 'separator' },
            {
                id: 'info',
                label: this.i18n.properties,
                icon: 'ℹ️',
                shortcut: 'Ctrl+Shift+I'
            }
        ];

        // Add encryption key item if enabled
        if (this.options.cryptFiles) {
            fileItems.push({ type: 'separator' });
            fileItems.push({
                id: 'setEncryptionKey',
                label: this.i18n.setEncryptionKey || 'Set Encryption Key',
                icon: '🔒',
                shortcut: 'Ctrl+Shift+E'
            });
        }

        // Define menus with fileItems as 'file' menu items
        var menus = [
            {
                id: 'file',
                label: this.i18n.menuFile,
                items: fileItems
            },
            {
                id: 'edit',
                label: this.i18n.menuEdit,
                items: [
                    {
                        id: 'copy',
                        label: this.i18n.copy,
                        icon: '📋',
                        shortcut: 'Ctrl+C'
                    },
                    {
                        id: 'cut',
                        label: this.i18n.cut,
                        icon: '✂️',
                        shortcut: 'Ctrl+X'
                    },
                    {
                        id: 'paste',
                        label: this.i18n.paste,
                        icon: '📄',
                        shortcut: 'Ctrl+V'
                    },
                    { type: 'separator' },
                    {
                        id: 'rename',
                        label: this.i18n.rename,
                        icon: '✏️',
                        shortcut: 'F2'
                    },
                    {
                        id: 'delete',
                        label: this.i18n.delete,
                        icon: '🗑️',
                        shortcut: 'Del'
                    },
                    { type: 'separator' },
                    {
                        id: 'selectAll',
                        label: this.i18n.selectAll,
                        icon: '☑️',
                        shortcut: 'Ctrl+A'
                    }
                ]
            },
            {
                id: 'view',
                label: this.i18n.menuView,
                items: [
                    {
                        id: 'list',
                        label: this.i18n.listView,
                        icon: '☐'
                    },
                    {
                        id: 'grid',
                        label: this.i18n.gridView,
                        icon: '⊞'
                    },
                    { type: 'separator' },
                    {
                        id: 'sortName',
                        label: this.i18n.sortByName,
                        icon: '☐'
                    },
                    {
                        id: 'sortSize',
                        label: this.i18n.sortBySize,
                        icon: '☐'
                    },
                    {
                        id: 'sortDate',
                        label: this.i18n.sortByDate,
                        icon: '☐'
                    },
                    {
                        id: 'sortType',
                        label: this.i18n.sortByType,
                        icon: '☐'
                    }
                ]
            }
        ];

        // Add custom menus if provided
        if (this.options.customMenus && Array.isArray(this.options.customMenus)) {
            this.options.customMenus.forEach(function (customMenu) {
                var position = customMenu.position !== undefined ? customMenu.position : menus.length;
                menus.splice(position, 0, {
                    id: customMenu.id,
                    label: customMenu.label,
                    items: customMenu.items,
                    custom: true
                });
            });
        }

        var html = '';
        menus.forEach(function (menu) {
            html += '<div class="mfm-menu" data-menu="' + self.escapeHtml(menu.id) + '">';
            html += '<button class="mfm-menu-trigger">' + self.escapeHtml(menu.label) + '</button>';
            html += '<div class="mfm-menu-dropdown">';

            menu.items.forEach(function (item) {
                if (item.type === 'separator') {
                    html += '<div class="mfm-menu-separator"></div>';
                } else {
                    var action = item.action || item.id;
                    var tooltip = item.shortcut ? item.label + ' (' + item.shortcut + ')' : item.label;

                    html += '<div class="mfm-menu-item" data-action="' + self.escapeHtml(action) + '" title="' + self.escapeHtml(tooltip) + '">';
                    if (item.icon) {
                        html += '<span class="mfm-menu-item-icon">' + item.icon + '</span>';
                    }
                    html += '<span>' + self.escapeHtml(item.label) + '</span>';
                    html += '</div>';
                }
            });

            html += '</div>';
            html += '</div>';
        });

        // Add brand logo if configured
        if (this.options.brandLogo) {
            var brandUrl = this.options.brandLink || '#';
            var brandTarget = this.options.brandTarget || '_self';
            var brandAlt = this.options.brandAlt || 'Logo';

            html += '<div class="mfm-menubar-brand">';
            html += '<a href="' + self.escapeHtml(brandUrl) + '" class="mfm-brand-link" target="' + self.escapeHtml(brandTarget) + '">';
            html += '<img src="' + self.escapeHtml(this.options.brandLogo) + '" alt="' + self.escapeHtml(brandAlt) + '" class="mfm-brand-logo">';
            html += '</a></div>';
        }

        return html;
    };

    /**
    * Bind menu events
    */
    MyFileManager.prototype.bindMenuEvents = function () {
        // Prevent double binding
        if (this._menuEventsBound) {
            return;
        }
        this._menuEventsBound = true;

        var self = this;

        // Menu trigger click (open/close dropdown)
        var menuTriggers = this.elements.menubar.querySelectorAll('.mfm-menu-trigger');
        menuTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var menu = this.parentElement;
                var dropdown = menu.querySelector('.mfm-menu-dropdown');

                // Close all other menus
                var allDropdowns = self.elements.menubar.querySelectorAll('.mfm-menu-dropdown');
                allDropdowns.forEach(function (dd) {
                    if (dd !== dropdown) {
                        dd.style.display = 'none';
                    }
                });

                // Toggle current menu
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                } else {
                    dropdown.style.display = 'block';
                }
            });
        });

        // Close menus when clicking outside
        document.addEventListener('click', function (e) {
            if (!self.elements.menubar.contains(e.target)) {
                var allDropdowns = self.elements.menubar.querySelectorAll('.mfm-menu-dropdown');
                allDropdowns.forEach(function (dd) {
                    dd.style.display = 'none';
                });
            }
        });
    };

    /**
    * Bind event listeners
    */
    MyFileManager.prototype.bindEvents = function () {
        var self = this;

        // Menu bar click events (click only, not hover)
        this.elements.menubar.addEventListener('click', function (e) {
            var trigger = e.target.closest('.mfm-menu-trigger');
            var menuItem = e.target.closest('.mfm-menu-item');

            if (trigger) {
                var menu = trigger.closest('.mfm-menu');
                var wasActive = menu.classList.contains('active');

                // Close all menus
                self.closeAllMenus();

                // Toggle this menu
                if (!wasActive) {
                    menu.classList.add('active');
                    self.state.activeMenu = menu;
                }

                e.stopPropagation();
            } else if (menuItem && !menuItem.classList.contains('disabled')) {
                var action = menuItem.getAttribute('data-action');
                self.handleMenuAction(action);
                self.closeAllMenus();
            }
        });

        // Close menus when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.mfm-menubar')) {
                self.closeAllMenus();
            }
        });

        // Toolbar buttons
        this.elements.toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            if (btn && !btn.disabled) {
                self.handleToolbarAction(btn.getAttribute('data-action'));
            }
        });

        // Search
        this.elements.search.addEventListener('input', function () {
            self.handleSearch(this.value);
        });

        // File list click
        this.elements.filesContainer.addEventListener('click', function (e) {
            var fileEl = e.target.closest('.mfm-file');

            if (fileEl) {
                var hash = fileEl.getAttribute('data-hash');
                var file = self.getFileByHash(hash);

                if (e.ctrlKey || e.metaKey) {
                    self.toggleFileSelection(fileEl, file);
                } else if (e.shiftKey && self.state.selectedFiles.length > 0) {
                    self.selectRange(fileEl);
                } else {
                    self.clearSelection();
                    self.selectFile(fileEl, file);
                }
            } else {
                self.clearSelection();
            }
        });

        // File list double click
        this.elements.filesContainer.addEventListener('dblclick', function (e) {
            var fileEl = e.target.closest('.mfm-file');
            if (fileEl) {
                var hash = fileEl.getAttribute('data-hash');
                var file = self.getFileByHash(hash);
                self.openFile(file);
            }
        });

        // Context menu
        this.elements.filesContainer.addEventListener('contextmenu', function (e) {
            if (!self.options.features.contextMenu) return;

            e.preventDefault();

            var fileEl = e.target.closest('.mfm-file');
            if (fileEl) {
                var hash = fileEl.getAttribute('data-hash');
                var file = self.getFileByHash(hash);

                if (!fileEl.classList.contains('mfm-selected')) {
                    self.clearSelection();
                    self.selectFile(fileEl, file);
                }
            } else {
                self.clearSelection();
            }

            self.showContextMenu(e.clientX, e.clientY);
        });

        // Context menu clicks
        this.elements.contextMenu.addEventListener('click', function (e) {
            var item = e.target.closest('.mfm-context-menu-item');
            if (item && !item.classList.contains('disabled')) {
                var action = item.getAttribute('data-action');
                self.handleContextMenuAction(action);
            }
        });

        // Hide context menu on click outside
        document.addEventListener('click', function () {
            self.elements.contextMenu.style.display = 'none';
        });

        // Upload input change
        this.elements.uploadInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                self.uploadFiles(this.files);
                this.value = '';
            }
        });

        // Drag and drop
        this.elements.filesContainer.addEventListener('dragover', function (e) {
            e.preventDefault();
            self.elements.filesContainer.classList.add('mfm-drag-over');
        });

        this.elements.filesContainer.addEventListener('dragleave', function (e) {
            if (e.target === self.elements.filesContainer) {
                self.elements.filesContainer.classList.remove('mfm-drag-over');
            }
        });

        this.elements.filesContainer.addEventListener('drop', function (e) {
            e.preventDefault();
            self.elements.filesContainer.classList.remove('mfm-drag-over');

            if (e.dataTransfer.files.length > 0) {
                self.uploadFiles(e.dataTransfer.files);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ignore shortcuts when typing in input/textarea
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            // Arrow key navigation and Enter to open files/folders
            var files = self.state.files;
            var selectedFile = self.state.selectedFiles.length > 0 ? self.state.selectedFiles[0] : null;
            var currentIndex = -1;

            // Find current selection index
            if (selectedFile) {
                currentIndex = files.findIndex(function (f) {
                    return f.hash === selectedFile.hash;
                });
            }

            // Arrow Down - Select next file
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (files.length > 0) {
                    var nextIndex = currentIndex + 1;
                    if (nextIndex >= files.length) {
                        nextIndex = 0; // Wrap to first
                    }

                    if (files[nextIndex]) {
                        self.clearSelection();
                        var fileElements = self.elements.filesContainer.querySelectorAll('.mfm-file');
                        var nextElement = fileElements[nextIndex];
                        if (nextElement) {
                            self.selectFile(nextElement, files[nextIndex]);
                            nextElement.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }
                    }
                }
                return; // Exit early
            }

            // Arrow Up - Select previous file
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (files.length > 0) {
                    var prevIndex = currentIndex - 1;
                    if (prevIndex < 0) {
                        prevIndex = files.length - 1; // Wrap to last
                    }

                    if (files[prevIndex]) {
                        self.clearSelection();
                        var fileElements = self.elements.filesContainer.querySelectorAll('.mfm-file');
                        var prevElement = fileElements[prevIndex];
                        if (prevElement) {
                            self.selectFile(prevElement, files[prevIndex]);
                            prevElement.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }
                    }
                }
                return; // Exit early
            }

            // Enter - Open selected file/folder
            if (e.key === 'Enter') {
                e.preventDefault();

                var selectedElement = self.elements.filesContainer.querySelector('.mfm-selected');
                if (selectedElement) {
                    var hash = selectedElement.getAttribute('data-hash');
                    var file = self.getFileByHash(hash);
                    if (file) {
                        self.openFile(file);
                    }
                }
                return;
            }

            // Backspace - Go up one level
            if (e.key === 'Backspace') {
                if (self.state.currentPath) {
                    e.preventDefault();
                    self.navigateUp();
                }
                return; // Exit early
            }

            // Ctrl+C - Copy
            if ((e.ctrlKey || e.metaKey) && e.key === 'c' && !e.shiftKey) {
                e.preventDefault();
                self.copySelected();
            }

            // Ctrl+X - Cut
            if ((e.ctrlKey || e.metaKey) && e.key === 'x' && !e.shiftKey) {
                e.preventDefault();
                self.cutSelected();
            }

            // Ctrl+V - Paste
            if ((e.ctrlKey || e.metaKey) && e.key === 'v' && !e.shiftKey) {
                e.preventDefault();
                self.pasteClipboard();
            }

            // Ctrl+A - Select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.shiftKey) {
                e.preventDefault();
                self.selectAll();
            }

            // Ctrl+Shift+U - Upload
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'U' || e.key === 'u')) {
                e.preventDefault();
                self.openUploadDialog();
            }

            // Ctrl+Shift+D - Download
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'D' || e.key === 'd')) {
                if (self.state.selectedFiles.length > 0) {
                    e.preventDefault();
                    self.downloadSelected();
                }
            }

            // Ctrl+Shift+M - New folder (M for Make)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'M' || e.key === 'm')) {
                e.preventDefault();
                self.createFolder();
            }

            // Ctrl+Shift+I - Properties/Info
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'i')) {
                if (self.state.selectedFiles.length === 1) {
                    e.preventDefault();
                    self.showInfo();
                }
            }

            // Delete key
            if (e.key === 'Delete') {
                if (self.state.selectedFiles.length > 0) {
                    e.preventDefault();
                    self.deleteSelected();
                }
            }

            // F2 - Rename
            if (e.key === 'F2') {
                if (self.state.selectedFiles.length === 1) {
                    e.preventDefault();
                    self.renameSelected();
                }
            }

            // F5 - Refresh
            if (e.key === 'F5') {
                e.preventDefault();
                self.refresh();
            }

            // Keyboard navigation - First letter file selection
            document.addEventListener('keydown', function (e) {
                // Ignore if typing in input/textarea
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                // First letter navigation with cycle (a, a, a...)
                if (e.key.length === 1 && e.key.match(/[a-zA-Z0-9]/) && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                    e.preventDefault();

                    var files = self.state.files;
                    if (files.length === 0) return;

                    var firstLetter = e.key.toLowerCase();

                    // Initialize state variables if not exist
                    if (!self._letterNavState) {
                        self._letterNavState = { lastLetter: null, index: -1 };
                    }

                    var state = self._letterNavState;

                    // Reset index if different letter
                    if (state.lastLetter !== firstLetter) {
                        state.lastLetter = firstLetter;
                        state.index = -1;
                    }

                    // Find all files starting with this letter
                    var candidates = files.filter(function (file) {
                        return file.name.toLowerCase().startsWith(firstLetter);
                    });

                    if (candidates.length === 0) return;

                    // Cycle to next matching file
                    state.index = (state.index + 1) % candidates.length;
                    var fileToSelect = candidates[state.index];

                    var fileEl = self.elements.filesContainer.querySelector('[data-hash="' + fileToSelect.hash + '"]');
                    if (fileEl) {
                        self.clearSelection();
                        self.selectFile(fileEl, fileToSelect);
                        fileEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }

            });

        });

        // Modal close
        this.elements.modal.addEventListener('click', function (e) {
            if (e.target === self.elements.modal || e.target.classList.contains('mfm-modal-close')) {
                self.closeModal();
            }
        });
    };

    /**
    * Close all menus
    */
    MyFileManager.prototype.closeAllMenus = function () {
        var menus = this.elements.menubar.querySelectorAll('.mfm-menu');
        menus.forEach(function (menu) {
            menu.classList.remove('active');
        });
        this.state.activeMenu = null;
    };

    /**
    * Handle menu action
    */
    MyFileManager.prototype.handleMenuAction = function (action) {
        var self = this;

        // Check if it's a custom menu action first
        var isCustomAction = false;
        if (this.options.customMenus) {
            this.options.customMenus.forEach(function (menu) {
                if (menu.items) {
                    menu.items.forEach(function (item) {
                        if ((item.action || item.id) === action) {
                            isCustomAction = true;
                        }
                    });
                }
            });
        }

        if (isCustomAction && this.options.onCustomMenuAction && typeof this.options.onCustomMenuAction === 'function') {
            this.options.onCustomMenuAction(action);
            return;
        }

        // Default menu actions
        switch (action) {
            case 'upload':
                this.openUploadDialog();
                break;
            case 'download':
                this.downloadSelected();
                break;
            case 'mkdir':
                this.createFolder();
                break;
            case 'info':
                this.showInfo();
                break;
            case 'setEncryptionKey':
                const password = prompt('Enter encryption password:');
                if (password) {
                    this.setEncryptionKey(password);
                }
                break;
            case 'copy':
                this.copySelected();
                break;
            case 'cut':
                this.cutSelected();
                break;
            case 'paste':
                this.pasteClipboard();
                break;
            case 'rename':
                this.renameSelected();
                break;
            case 'delete':
                this.deleteSelected();
                break;
            case 'selectAll':
                this.selectAll();
                break;
            case 'list':
                this.setViewMode('list');
                break;
            case 'grid':
                this.setViewMode('grid');
                break;
            case 'sortName':
                this.options.sortBy = 'name';
                this.sortFiles(this.state.files);
                this.renderFiles();
                this.updateMenuStates();
                break;
            case 'sortSize':
                this.options.sortBy = 'size';
                this.sortFiles(this.state.files);
                this.renderFiles();
                this.updateMenuStates();
                break;
            case 'sortDate':
                this.options.sortBy = 'date';
                this.sortFiles(this.state.files);
                this.renderFiles();
                this.updateMenuStates();
                break;
            case 'sortType':
                this.options.sortBy = 'type';
                this.sortFiles(this.state.files);
                this.renderFiles();
                this.updateMenuStates();
                break;

            // PLUGIN ACTIONS - Check custom context menu items
            default:
                var handled = false;
                for (var i = 0; i < this.options.customContextMenu.length; i++) {
                    var item = this.options.customContextMenu[i];
                    if (item.action === action && typeof item.handler === 'function') {
                        // Check condition if present
                        var canExecute = true;
                        if (item.condition && typeof item.condition === 'function') {
                            canExecute = item.condition(this.state.selectedFiles, this);
                        }

                        if (canExecute) {
                            item.handler(this.state.selectedFiles, this);
                            handled = true;
                            break;
                        } else {
                            alert('This action cannot be performed on the selected file(s)');
                            handled = true;
                            break;
                        }
                    }
                }

                if (!handled) {
                    this._debug('warn', 'Unknown menu action:', action);
                }
                break;
        }

        this.closeAllMenus();
    };

    /**
    * Open upload dialog
    */
    MyFileManager.prototype.openUploadDialog = function () {
        if (!this.options.features.upload) {
            return;
        }

        // Reset input value to allow selecting the same file again
        this.elements.uploadInput.value = '';

        // Trigger file selection
        this.elements.uploadInput.click();
    };

    /**
    * Update menu item states (enabled/disabled)
    */
    MyFileManager.prototype.updateMenuStates = function () {
        var hasSelection = this.state.selectedFiles.length > 0;
        var hasClipboard = this.state.clipboard !== null;
        var menubar = this.elements.menubar;

        // Check if trash folder is selected
        var isTrashSelected = false;
        if (this.state.selectedFiles.length > 0) {
            isTrashSelected = this.state.selectedFiles.some(function (file) {
                return file.name === this.options.trashPath ||
                    file.name === '.trash' ||
                    file.path === this.options.trashPath;
            }.bind(this));
        }

        // Check if any folder is selected
        var hasFolderSelected = this.hasFolderSelected();

        // Download - disable if no selection OR if any folder is selected
        var downloadItem = menubar.querySelector('[data-action="download"]');
        if (downloadItem) {
            downloadItem.classList.toggle('disabled', !hasSelection || hasFolderSelected);
        }

        // Info - only enabled for single selection
        var infoItem = menubar.querySelector('[data-action="info"]');
        if (infoItem) {
            infoItem.classList.toggle('disabled', this.state.selectedFiles.length !== 1);
        }

        // Copy, Cut, Rename - DISABLE if trash selected!
        ['copy', 'cut', 'rename'].forEach(function (action) {
            var item = menubar.querySelector('[data-action="' + action + '"]');
            if (item) {
                item.classList.toggle('disabled', !hasSelection || isTrashSelected);
            }
        });

        // Delete - enable for selection (trash can be deleted)
        var deleteItem = menubar.querySelector('[data-action="delete"]');
        if (deleteItem) {
            deleteItem.classList.toggle('disabled', !hasSelection);
        }

        // Paste - DISABLE if inside trash OR trash selected
        var pasteItem = menubar.querySelector('[data-action="paste"]');
        if (pasteItem) {
            var inTrash = this.isInTrash();
            pasteItem.classList.toggle('disabled', !hasClipboard || inTrash || isTrashSelected);
        }

        // Update view mode checkmarks
        var viewList = menubar.querySelector('[data-action="list"] .mfm-menu-item-icon');
        var viewGrid = menubar.querySelector('[data-action="grid"] .mfm-menu-item-icon');
        if (viewList) viewList.textContent = this.options.viewMode === 'list' ? '✓' : '\u00A0';
        if (viewGrid) viewGrid.textContent = this.options.viewMode === 'grid' ? '✓' : '\u00A0';

        // Update sort by checkmarks
        var sortActions = {
            name: 'sortName',
            size: 'sortSize',
            date: 'sortDate',
            type: 'sortType'
        };

        Object.keys(sortActions).forEach(function (sortKey) {
            var actionName = sortActions[sortKey];
            var itemIcon = menubar.querySelector('[data-action="' + actionName + '"] .mfm-menu-item-icon');
            if (itemIcon) {
                itemIcon.textContent = this.options.sortBy === sortKey ? '✓' : '\u00A0';
            }
        }.bind(this));

        if (this.options.cryptFiles) {
            var encryptItem = this.elements.menubar.querySelector('[data-action="setEncryptionKey"]');
            if (encryptItem) {
                // hide or show based on encryptionKey presence
                encryptItem.style.display = this.options.encryptionKey ? 'none' : 'block';
            }
        }

        this.updateStatusBar();
    };

    /**
    * Handle toolbar action
    */
    MyFileManager.prototype.handleToolbarAction = function (action) {
        switch (action) {
            case 'back':
                this.navigateBack();
                break;
            case 'forward':
                this.navigateForward();
                break;
            case 'up':
                this.navigateUp();
                break;
            case 'refresh':
                this.refresh();
                break;
        }
    };

    /**
    * Set view mode
    */
    MyFileManager.prototype.setViewMode = function (mode) {
        this.options.viewMode = mode;
        this.elements.filesContainer.className = 'mfm-files mfm-view-' + mode;
        this.renderFiles();
        this.updateMenuStates();
    };

    /**
    * Set sort by
    */
    MyFileManager.prototype.setSortBy = function (sortBy) {
        if (this.options.sortBy === sortBy) {
            // Toggle sort order
            this.options.sortOrder = this.options.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.options.sortBy = sortBy;
            this.options.sortOrder = 'asc';
        }
        this.renderFiles();
        this.updateMenuStates();
    };

    /**
    * Download selected files (folders cannot be downloaded)
    */
    MyFileManager.prototype.downloadSelected = function () {
        var self = this;

        self._debug('log', 'downloadSelected CALLED!');
        self._debug('log', 'Selected files:', this.state.selectedFiles.length);

        // Prevent downloading folders
        if (this.hasFolderSelected()) {
            alert('Folders cannot be downloaded. Please select only files.');
            return;
        }

        // Download only files
        this.state.selectedFiles.forEach(function (file) {
            self._debug('log', 'Downloading file:', file.name);

            // Double check: skip directories
            if (file.mime === 'directory') return;

            self.downloadFile(file);
        });
    };

    /**
     * Download single file with streaming and progress tracking
     */
    MyFileManager.prototype.downloadFile = async function (file) {
        var self = this;
        self._debug('log', 'downloadFile START for', file.name);

        try {
            // Skip folders
            if (file.mime === 'directory') {
                alert('Folders cannot be downloaded.');
                return;
            }

            // Show progress modal immediately
            this.showDownloadProgress(file.name, file.size);

            // Create AbortController for cancellation
            this.currentDownloadController = new AbortController();

            // Get base URL
            var downloadUrl = this.options.downloadUrl || this.options.url;
            var baseUrl = downloadUrl.split('?')[0];

            // Extract tokens
            var token1 = '';
            var token2 = Date.now();

            if (downloadUrl.indexOf('?') !== -1) {
                var queryString = downloadUrl.split('?')[1];
                var pairs = queryString.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    if (pair[0] === 'token1') {
                        token1 = decodeURIComponent(pair[1]);
                    }
                }
            }

            // Build FormData
            const formData = new FormData();
            formData.append('cmd', 'download');
            formData.append('target', file.hash);

            if (this.options.token) {
                formData.append('token', this.options.token);
            }
            if (token1) {
                formData.append('token1', token1);
            }
            formData.append('token2', token2.toString());

            self._debug('log', 'Starting download with streaming...');

            const response = await fetch(baseUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Authorization': 'Bearer ' + this.options.token
                },
                body: formData,
                signal: this.currentDownloadController.signal
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            // Use file.size from JavaScript (already known!)
            const total = file.size || 0;

            self._debug('log', 'File size from metadata:', total, 'bytes');

            // Stream the response with progress tracking
            const reader = response.body.getReader();
            const chunks = [];
            let receivedLength = 0;
            let lastTime = Date.now();
            let lastReceived = 0;

            // Speed tracking with moving average
            const speedSamples = [];
            const maxSamples = 10;

            while (true) {
                const { done, value } = await reader.read();

                if (done) break;

                chunks.push(value);
                receivedLength += value.length;

                // Calculate progress using file.size
                const percent = total > 0 ? (receivedLength / total) * 100 : 0;

                // Calculate speed with moving average
                const now = Date.now();
                const deltaTime = (now - lastTime) / 1000;
                const deltaReceived = receivedLength - lastReceived;

                if (deltaTime > 0.1) { // Update every 100ms
                    const instantSpeed = deltaReceived / deltaTime;

                    if (instantSpeed > 0) {
                        speedSamples.push(instantSpeed);
                        if (speedSamples.length > maxSamples) {
                            speedSamples.shift();
                        }
                    }

                    // Calculate average speed
                    let avgSpeed = 0;
                    if (speedSamples.length > 0) {
                        const sum = speedSamples.reduce((a, b) => a + b, 0);
                        avgSpeed = sum / speedSamples.length;
                    }

                    // Calculate time remaining using file.size
                    let timeRemaining = Infinity;
                    if (avgSpeed > 0 && total > 0) {
                        const remaining = total - receivedLength;
                        timeRemaining = remaining / avgSpeed;
                    }

                    // Update UI
                    self.updateDownloadProgress(percent, avgSpeed, timeRemaining);

                    lastTime = now;
                    lastReceived = receivedLength;
                }
            }

            self._debug('log', 'Download complete, received:', receivedLength, 'bytes');

            // Force final update to 100% (for small files)
            if (total > 0) {
                // Calculate final average speed for small files
                let finalSpeed = 0;
                if (speedSamples.length > 0) {
                    const sum = speedSamples.reduce((a, b) => a + b, 0);
                    finalSpeed = sum / speedSamples.length;
                }
                self.updateDownloadProgress(100, finalSpeed, 0);
            }

            // Combine chunks into blob
            const blob = new Blob(chunks, { type: 'application/octet-stream' });

            // Handle decryption if needed
            let finalBlob = blob;
            let finalFilename = file.name;

            if (this.options.cryptFiles && this.options.encryptionKey && file.name.endsWith('.encrypted')) {
                self._debug('log', 'Decrypting file...');
                const arrayBuffer = await blob.arrayBuffer();
                const encryptedData = new Uint8Array(arrayBuffer);
                const decryptedData = await this.decryptFileData(encryptedData, this.options.encryptionKey);
                finalBlob = new Blob([decryptedData], { type: file.mime || 'application/octet-stream' });
                finalFilename = file.name.replace('.encrypted', '');
            }

            // Trigger browser download
            const a = document.createElement('a');
            a.href = URL.createObjectURL(finalBlob);
            a.download = finalFilename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);

            // Wait a bit longer for small files so user sees 100%
            setTimeout(function () {
                self.hideDownloadProgress();
            }, 1500);

            self._debug('log', 'Download saved:', finalFilename);

        } catch (error) {
            self._debug('error', 'Download failed:', error);

            if (error.name === 'AbortError') {
                self.showDownloadError('Download cancelled by user');
            } else if (error.name === 'OperationError' || error.message.includes('decryption')) {
                self.showDownloadError('Decryption failed. Wrong password or corrupted file.');
            } else {
                self.showDownloadError(error.message);
            }
        }
    };

    /**
    * Render files in current view mode
    */
    MyFileManager.prototype.renderFiles = function () {
        var self = this;
        var files = this.sortFiles(this.state.files);

        // Filter hidden extensions
        if (self.options.hideExtensions && self.options.hideExtensions.length > 0) {
            files = files.filter(function (file) {
                // Always show directories
                if (file.mime === 'directory') return true;

                // Check if file extension should be hidden
                var fileName = file.name.toLowerCase();
                for (var i = 0; i < self.options.hideExtensions.length; i++) {
                    var ext = self.options.hideExtensions[i].toLowerCase();
                    // Ensure extension starts with dot
                    if (!ext.startsWith('.')) ext = '.' + ext;
                    if (fileName.endsWith(ext)) {
                        return false; // Hide this file
                    }
                }
                return true; // Show this file
            });
        }

        var html = '';

        if (files.length === 0) {
            html = '<div class="mfm-empty">' + this.i18n.emptyFolder + '</div>';
        } else {
            if (this.options.viewMode === 'list') {
                // List view explorer
                files.forEach(function (file) {
                    var icon = self.getFileIcon(file);
                    var size = file.mime === 'directory' ? '' : self.formatSize(file.size);
                    var type = file.mime === 'directory' ? self.i18n.folder : self.getFileType(file);
                    var date = self.formatDate(file.ts);

                    // Create tooltip with file information
                    var tooltip = '';
                    tooltip += self.i18n.name + ': ' + file.name + '\u000A';
                    tooltip += self.i18n.type + ': ' + type + '\u000A';
                    if (size) tooltip += self.i18n.size + ': ' + size + '\u000A';
                    tooltip += self.i18n.dateModified + ': ' + date;

                    html += '<div class="mfm-file" data-hash="' + file.hash + '" title="' + self.escapeHtml(tooltip) + '">';
                    html += '<div class="mfm-file-icon ' + icon + '"></div>';
                    // Display translated name for trash folder
                    var displayName = self.escapeHtml(file.name);
                    if (displayName.endsWith('.encrypted')) {
                        displayName = displayName.slice(0, -10); // remove ".encrypted"
                    }
                    if (file.name === self.options.trashPath || file.name === '.trash') {
                        displayName = self.i18n.trash;
                    }
                    html += '<span class="mfm-file-name">' + displayName + '</span>';
                    html += '<div class="mfm-file-size">' + size + '</div>';
                    html += '<div class="mfm-file-type">' + type + '</div>';
                    html += '<div class="mfm-file-date">' + date + '</div>';
                    html += '</div>';
                });
            } else {
                // Grid view explorer
                files.forEach(function (file) {
                    var icon = self.getFileIcon(file);
                    var size = file.mime === 'directory' ? '' : self.formatSize(file.size);
                    var type = file.mime === 'directory' ? self.i18n.folder : self.getFileType(file);
                    var date = self.formatDate(file.ts);

                    // Create tooltip with file information
                    var tooltip = '';
                    tooltip += self.i18n.name + ': ' + file.name + '\u000A';
                    tooltip += self.i18n.type + ': ' + type + '\u000A';
                    if (size) tooltip += self.i18n.size + ': ' + size + '\u000A';
                    tooltip += self.i18n.dateModified + ': ' + date;

                    html += '<div class="mfm-file" data-hash="' + file.hash + '" title="' + self.escapeHtml(tooltip) + '">';
                    html += '<div class="mfm-file-icon ' + icon + '"></div>';
                    // Display translated name for trash folder
                    var displayName = self.escapeHtml(file.name);
                    if (displayName.endsWith('.encrypted')) {
                        displayName = displayName.slice(0, -10); // remove ".encrypted"
                    }
                    if (file.name === self.options.trashPath || file.name === '.trash') {
                        displayName = self.i18n.trash;
                    }
                    html += '<span class="mfm-file-name">' + displayName + '</span>';
                    html += '</div>';
                });
            }

        }

        this.elements.filesContainer.innerHTML = html;
        this.updateStatusBar();
    };

    /**
    * Filter hidden files/folders starting with dot (except trash folder)
    * Controlled by options.showHidden option
    * @param {Array} files - Array of files to filter
    * @return {Array} Filtered files
    */
    MyFileManager.prototype.filterHiddenFolders = function (files) {
        var self = this;

        // If showHidden is true, return all files without filtering
        if (self.options.showHidden) {
            return files;
        }

        // Otherwise filter hidden files/folders (starting with .) except trash
        return files.filter(function (file) {
            // Skip hidden items starting with dot
            if (file.name.charAt(0) === '.') {
                // Always show trash folder regardless of name format
                if (file.name === self.options.trashPath ||
                    file.name === '.trash' ||
                    file.name === '.' + self.options.trashPath) {
                    return true;
                }
                return false; // Hide other hidden items
            }
            return true; // Show all non-hidden items
        });
    };

    /**
    * Sort files
    */
    MyFileManager.prototype.sortFiles = function (files) {
        var self = this;
        var sorted = files.slice();

        sorted.sort(function (a, b) {
            // Always show folders first
            if (a.mime === 'directory' && b.mime !== 'directory') return -1;
            if (a.mime !== 'directory' && b.mime === 'directory') return 1;

            var result = 0;

            switch (self.options.sortBy) {
                case 'name':
                    result = a.name.toLowerCase().localeCompare(b.name.toLowerCase());
                    break;
                case 'size':
                    result = (a.size || 0) - (b.size || 0);
                    break;
                case 'date':
                    result = (a.ts || 0) - (b.ts || 0);
                    break;
                case 'type':
                    var typeA = self.getFileType(a);
                    var typeB = self.getFileType(b);
                    result = typeA.localeCompare(typeB);
                    break;
            }

            return self.options.sortOrder === 'asc' ? result : -result;
        });

        return sorted;
    };

    /**
    * Get file/folder icon class - PNG icons
    */
    MyFileManager.prototype.getFileIcon = function (file) {
        if (file.mime === 'directory') {
            // Trash folder special case
            if (file.name === this.options.trashPath ||
                file.name === '.trash' ||
                file.path === ('/' + this.options.trashPath)) {
                var trashHasFiles = file.size > 0 || (file.dirs && file.dirs > 0);
                return trashHasFiles ? 'icon-trash-full' : 'icon-trash-empty';
            }
            return 'icon-folder';  // PNG folder
        }

        // Remove .encrypted for icon detection
        var fileName = file.name.endsWith('.encrypted') ?
            file.name.slice(0, -10) : file.name;
        var ext = fileName.split('.').pop().toLowerCase();

        // PNG icons by MIME type (PRIORITY)
        if (file.mime.startsWith('image/')) return 'icon-image';
        if (file.mime.startsWith('video/')) return 'icon-video';
        if (file.mime.startsWith('audio/')) return 'icon-audio';
        if (file.mime.startsWith('text/')) return 'icon-text';

        // PNG icons by extension (FALLBACK)
        switch (ext) {
            case 'jpg': case 'jpeg': case 'png': case 'gif':
            case 'webp': case 'svg': case 'bmp': return 'icon-image';

            case 'mp4': case 'webm': case 'avi': case 'mov':
            case 'mkv': case 'flv': case 'm4v': return 'icon-video';

            case 'mp3': case 'wav': case 'ogg': case 'flac':
            case 'm4a': case 'aac': return 'icon-audio';

            case 'txt': case 'md': case 'log': case 'json':
            case 'xml': case 'csv': case 'html': case 'css': return 'icon-text';

            case 'zip': case 'rar': case '7z': return 'icon-archive';
            case 'pdf': return 'icon-pdf';
            case 'doc': case 'docx': return 'icon-word';
            case 'xls': case 'xlsx': return 'icon-excel';
            case 'ppt': case 'pptx': return 'icon-powerpoint';

            default: return 'icon-file';  // Generic file PNG
        }
    };

    /**
    * Check if current path is trash folder
    */
    MyFileManager.prototype.isInTrash = function () {
        var currentPath = this.state.currentPath;
        var trashPath = this.options.trashPath;

        this._debug('log', 'isInTrash check:', {
            currentPath: currentPath,
            trashPath: trashPath
        });

        // Check various path formats
        if (currentPath === '/' + trashPath) return true;
        if (currentPath === trashPath) return true;
        if (currentPath.indexOf('/' + trashPath + '/') === 0) return true;
        if (currentPath.indexOf(trashPath + '/') === 0) return true;

        return false;
    };

    /**
    * Check if any folder (directory) is in current selection
    * @return {boolean} True if any folder is selected
    */
    MyFileManager.prototype.hasFolderSelected = function () {
        return this.state.selectedFiles.some(function (file) {
            return file.mime === 'directory';
        });
    };

    /**
    * Get file type string
    */
    MyFileManager.prototype.getFileType = function (file) {
        if (file.mime === 'directory') {
            return this.i18n.folder;
        }

        var ext = file.name.split('.').pop().toUpperCase();

        if (file.mime.startsWith('image/')) {
            return ext + ' ' + this.i18n.image;
        }

        if (file.mime.startsWith('video/')) {
            return ext + ' ' + this.i18n.video;
        }

        if (file.mime.startsWith('audio/')) {
            return ext + ' ' + this.i18n.audio;
        }

        if (file.mime.startsWith('text/')) {
            return ext + ' ' + this.i18n.textFile;
        }

        return ext + ' ' + this.i18n.file;
    };

    /**
    * Show context menu
    */
    MyFileManager.prototype.showContextMenu = function (x, y) {
        var hasSelection = this.state.selectedFiles.length > 0;
        var hasClipboard = this.state.clipboard !== null;
        var html = '';

        if (hasSelection) {
            // Check if trash folder is selected
            var isTrashSelected = this.state.selectedFiles.some(function (file) {
                return file.name === this.options.trashPath ||
                    file.name === '.trash' ||
                    file.path === this.options.trashPath;
            }.bind(this));

            // Check if any folder is selected - prevent download for folders
            var hasFolderSelected = this.hasFolderSelected();

            // Show download only if NO folders are selected (only files can be downloaded)
            if (this.options.features.download && !hasFolderSelected) {
                html += '<div class="mfm-context-menu-item" data-action="download">';
                html += this.i18n.download;
                html += '</div>';
                html += '<div class="mfm-context-menu-separator"></div>';
            }

            // Copy option - DISABLED for trash
            if (this.options.features.copy && !isTrashSelected) {
                html += '<div class="mfm-context-menu-item" data-action="copy">';
                html += this.i18n.copy;
                html += '</div>';
            }

            // Cut option - DISABLED for trash
            if (this.options.features.cut && !isTrashSelected) {
                html += '<div class="mfm-context-menu-item" data-action="cut">';
                html += this.i18n.cut;
                html += '</div>';
            }

            // Rename option - DISABLED for trash (only for single selection)
            if (this.state.selectedFiles.length === 1 && this.options.features.rename && !isTrashSelected) {
                html += '<div class="mfm-context-menu-separator"></div>';
                html += '<div class="mfm-context-menu-item" data-action="rename">';
                html += this.i18n.rename;
                html += '</div>';
            }

            // Get selected file if exactly one selected
            var file = this.state.selectedFiles.length === 1 ? this.state.selectedFiles[0] : null;
            var trashFolderName = this.options.trashPath.replace('.', ''); // Remove leading dot

            // Check if current folder is the trash folder
            var inTrashFolder = this.state.currentPath === trashFolderName || this.state.currentPath === ('/' + trashFolderName);

            // Check if the selected file is the trash folder icon itself
            var isTrashFolderIcon = file && (file.name === trashFolderName || file.name === ('.' + trashFolderName));

            // Delete option
            if (this.options.features.delete) {
                html += '<div class="mfm-context-menu-separator"></div>';

                if (isTrashFolderIcon && !inTrashFolder) {
                    // Right-click on the Trash folder icon in main folder list
                    html += '<div class="mfm-context-menu-item" data-action="delete">';
                    html += this.i18n.emptyTrash;
                    html += '</div>';
                } else {
                    // Regular delete for files or folders
                    html += '<div class="mfm-context-menu-item" data-action="delete">';
                    html += this.i18n.delete;
                    html += '</div>';
                }
            }

            // Info option (only for single selection)
            if (this.state.selectedFiles.length === 1 && this.options.features.info) {
                html += '<div class="mfm-context-menu-separator"></div>';
                html += '<div class="mfm-context-menu-item" data-action="info">';
                html += this.i18n.info;
                html += '</div>';
            }

            // CUSTOM CONTEXT MENU ITEMS FROM PLUGINS (with condition checking)
            if (this.options.customContextMenu.length > 0) {
                var self = this;
                var addedPluginSeparator = false;

                this.options.customContextMenu.forEach(function (item) {
                    // Check if item has a condition function
                    var shouldShow = true;
                    if (item.condition && typeof item.condition === 'function') {
                        shouldShow = item.condition(self.state.selectedFiles, self);
                    }

                    if (shouldShow) {
                        // Add separator before first plugin item
                        if (!addedPluginSeparator) {
                            html += '<div class="mfm-context-menu-separator"></div>';
                            addedPluginSeparator = true;
                        }

                        html += '<div class="mfm-context-menu-item" data-action="' + self.escapeHtml(item.action) + '">';
                        html += self.escapeHtml(item.label);
                        html += '</div>';
                    }
                });
            }

        } else {
            // No selection - check if inside trash
            var inTrash = this.isInTrash();

            // No selection - show folder actions
            if (this.options.features.upload) {
                html += '<div class="mfm-context-menu-item" data-action="upload">';
                html += this.i18n.upload;
                html += '</div>';
            }

            if (this.options.features.mkdir) {
                html += '<div class="mfm-context-menu-item" data-action="mkdir">';
                html += this.i18n.mkdir;
                html += '</div>';
            }

            // Paste - DISABLED if inside trash
            if (hasClipboard && this.options.features.paste && !inTrash) {
                html += '<div class="mfm-context-menu-separator"></div>';
                html += '<div class="mfm-context-menu-item" data-action="paste">';
                html += this.i18n.paste;
                html += '</div>';
            }

            // Refresh option
            html += '<div class="mfm-context-menu-separator"></div>';
            html += '<div class="mfm-context-menu-item" data-action="refresh">';
            html += this.i18n.refresh;
            html += '</div>';
        }

        this.elements.contextMenu.innerHTML = html;
        this.elements.contextMenu.style.display = 'block';
        this.elements.contextMenu.style.left = x + 'px';
        this.elements.contextMenu.style.top = y + 'px';

        // Adjust position if out of viewport
        var rect = this.elements.contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            this.elements.contextMenu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            this.elements.contextMenu.style.top = (y - rect.height) + 'px';
        }
    };

    /**
    * Handle context menu action
    */
    MyFileManager.prototype.handleContextMenuAction = function (action) {
        this.elements.contextMenu.style.display = 'none';

        // Check plugin context menu items first
        for (var i = 0; i < this.options.customContextMenu.length; i++) {
            var item = this.options.customContextMenu[i];
            if (item.action === action && typeof item.handler === 'function') {
                // Check condition
                var canExecute = true;
                if (item.condition && typeof item.condition === 'function') {
                    canExecute = item.condition(this.state.selectedFiles, this);
                }

                if (canExecute) {
                    item.handler(this.state.selectedFiles, this);
                    return;
                }
            }
        }

        // Default actions
        switch (action) {
            case 'download':
                this.downloadSelected();
                break;
            case 'copy':
                this.copySelected();
                break;
            case 'cut':
                this.cutSelected();
                break;
            case 'paste':
                this.pasteClipboard();
                break;
            case 'rename':
                this.renameSelected();
                break;
            case 'delete':
                this.deleteSelected();
                break;
            case 'info':
                this.showInfo();
                break;
            case 'upload':
                this.elements.uploadInput.click();
                break;
            case 'mkdir':
                this.createFolder();
                break;
            case 'refresh':
                this.refresh();
                break;
            default:
                // CHECK CUSTOM CONTEXT MENU ACTIONS
                var handled = false;

                // Check if action starts with 'custom-'
                if (action.startsWith('custom-')) {
                    // Find the custom menu item
                    for (var i = 0; i < this.options.customContextMenu.length; i++) {
                        var item = this.options.customContextMenu[i];
                        if (item.action === action) {
                            // Call custom handler if defined
                            if (typeof this.options.onContextMenuAction === 'function') {
                                this.options.onContextMenuAction({
                                    action: action,
                                    files: this.state.selectedFiles,
                                    fileManager: this
                                });
                                handled = true;
                            }
                            break;
                        }
                    }
                }

                if (!handled) {
                    this._debug('warn', 'Unknown context menu action:', action);
                }
                break;
        }
    };

    /**
    * Update breadcrumb navigation
    */
    MyFileManager.prototype.updateBreadcrumb = function () {
        var self = this;
        var html = '';

        // Home button (root) - Use customizable homeLabel
        html += '<div class="mfm-breadcrumb-item" data-hash="">';
        html += this.options.homeLabel || this.i18n.home;
        html += '</div>';

        // Decode currentPath if needed
        var decodedPath = this.state.currentPath;

        // Split path
        var parts = decodedPath ? decodedPath.split('/').filter(Boolean) : [];

        // Build breadcrumb
        var pathSoFar = '';
        parts.forEach(function (folderName, index) {
            pathSoFar += (index > 0 ? '/' : '') + folderName;

            // Encode path to base64 for navigation
            var hashForNav = btoa(pathSoFar);

            html += '<span class="mfm-breadcrumb-sep">›</span>';
            html += '<div class="mfm-breadcrumb-item" data-hash="' + self.escapeHtml(hashForNav) + '">';

            // Check if this is the trash folder and display translated label
            var displayName = folderName;
            if (folderName === self.options.trashPath ||
                folderName === '.trash' ||
                folderName === 'trash') {
                displayName = self.i18n.trash;
            }

            html += self.escapeHtml(displayName);
            html += '</div>';
        });

        this.elements.breadcrumb.innerHTML = html;

        // Bind breadcrumb clicks
        this.elements.breadcrumb.querySelectorAll('.mfm-breadcrumb-item').forEach(function (item) {
            item.addEventListener('click', function () {
                var hash = this.getAttribute('data-hash');
                self.open(hash); // Pass the hash to open
            });
        });
    };

    /**
    * Update status bar with file count and selection info
    */
    MyFileManager.prototype.updateStatusBar = function () {
        var itemCount = this.state.files.length;
        var selectedCount = this.state.selectedFiles.length;
        var itemText = itemCount === 1 ? this.i18n.item : this.i18n.items;

        // Always show total items count
        var leftText = itemCount + ' ' + itemText;

        // Add selection info if items are selected
        if (selectedCount > 0) {
            var selectedText = selectedCount === 1 ? this.i18n.selected : this.i18n.selectedPlural;
            leftText += ': ' + selectedCount + ' ' + selectedText;
        }

        this.container.querySelector('.mfm-status-items').textContent = leftText;

        // Update quota info if available
        if (this.state.quota) {
            var usedText = this.formatSize(this.state.quota.used);
            var totalText = this.formatSize(this.state.quota.total);
            var freeText = this.formatSize(this.state.quota.free);
            var maxUploadText = this.formatSizeBinary(this.state.quota.maxUpload);

            var quotaHtml = '';
            quotaHtml += '<span title="' + this.i18n.spaceUsed + '">' + this.i18n.used + ' ' + usedText + '</span>';
            quotaHtml += '<span title="' + this.i18n.spaceFree + '">' + this.i18n.free + ' ' + freeText + '</span>';
            quotaHtml += '<span title="' + this.i18n.spaceTotal + '">' + this.i18n.total + ' ' + totalText + '</span>';
            quotaHtml += '<span title="' + this.i18n.maxUploadSize + '">' + this.i18n.maxUpload + ' ' + maxUploadText + '</span>';

            this.container.querySelector('.mfm-status-quota').innerHTML = quotaHtml;
        }
    };

    /**
    * Select all files
    */
    MyFileManager.prototype.selectAll = function () {
        var self = this;
        this.clearSelection();

        var fileElements = this.elements.filesContainer.querySelectorAll('.mfm-file');
        fileElements.forEach(function (el) {
            var hash = el.getAttribute('data-hash');
            var file = self.getFileByHash(hash);
            self.selectFile(el, file);
        });
    };

    /**
    * Format file size (Decimal - base 10)
    */
    MyFileManager.prototype.formatSize = function (bytes) {
        if (bytes === 0) return '0 Bytes';

        // Decimal (base 10) - 1 GB = 1,000,000,000 bytes
        var k = 1000;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
    * Format file size in binary (for max upload from PHP)
    */
    MyFileManager.prototype.formatSizeBinary = function (bytes) {
        if (bytes === 0) return '0 Bytes';

        // Binary (base 2) - 1 GiB = 1,073,741,824 bytes
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
    * Format date
    */
    MyFileManager.prototype.formatDate = function (timestamp) {
        var date = new Date(timestamp * 1000);
        var now = new Date();
        var diff = now - date;

        // If less than 24 hours, show time
        if (diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Otherwise show date
        return date.toLocaleDateString();
    };

    /**
    * Escape HTML
    */
    MyFileManager.prototype.escapeHtml = function (text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
    * Get file by hash
    */
    MyFileManager.prototype.getFileByHash = function (hash) {
        return this.state.files.find(function (f) { return f.hash === hash; });
    };

    /**
    * Select file
    */
    MyFileManager.prototype.selectFile = function (element, file) {
        element.classList.add('mfm-selected');
        this.state.selectedFiles.push(file);
        this.trigger('onFileSelect', file);
        this.updateMenuStates();
        this.updateStatusBar();
    };

    /**
    * Clear selection
    */
    MyFileManager.prototype.clearSelection = function () {
        this.elements.filesContainer.querySelectorAll('.mfm-selected').forEach(function (el) {
            el.classList.remove('mfm-selected');
        });
        this.state.selectedFiles = [];
        this.updateMenuStates();
        this.updateStatusBar();
    };

    /**
    * Toggle file selection
    */
    MyFileManager.prototype.toggleFileSelection = function (element, file) {
        if (element.classList.contains('mfm-selected')) {
            element.classList.remove('mfm-selected');
            this.state.selectedFiles = this.state.selectedFiles.filter(function (f) {
                return f.hash !== file.hash;
            });
        } else {
            this.selectFile(element, file);
        }
        this.updateMenuStates();
        this.updateStatusBar();
    };

    /**
    * Trigger callback
    */
    MyFileManager.prototype.trigger = function (event, data) {
        if (typeof this.options[event] === 'function') {
            this.options[event].call(this, data);
        }
    };

    /**
    * Open directory/file
    */
    MyFileManager.prototype.open = function (target, addToHistory) {
        var self = this;

        // Default: add to history unless explicitly false
        if (addToHistory === undefined) {
            addToHistory = true;
        }

        this.request({
            cmd: 'open',
            target: target
        }, function (response) {
            // Save the HASH for navigation
            self.state.currentHash = response.cwd.hash;

            // Decode hash to get the real path for breadcrumb
            if (response.cwd.hash) {
                try {
                    self.state.currentPath = atob(response.cwd.hash);
                } catch (e) {
                    self.state.currentPath = '';
                }
            } else {
                self.state.currentPath = '';
            }

            // Add to history
            if (addToHistory) {
                // Remove any forward history
                self.state.history = self.state.history.slice(0, self.state.historyIndex + 1);

                // Add current location
                self.state.history.push({
                    hash: response.cwd.hash,
                    path: self.state.currentPath
                });

                self.state.historyIndex = self.state.history.length - 1;
            }

            // Filter hidden folders before storing
            self.state.files = self.filterHiddenFolders(response.files) || [];
            self.state.quota = response.quota;

            self.updateBreadcrumb();
            self.renderFiles();
            self.clearSelection();
            self.updateNavigationButtons(); // update button states
            self.updateTrashToolbar();  // Update trash toolbar visibility
            self.trigger('onChange', response);
        });
    };

    /**
    * Navigate back in history
    */
    MyFileManager.prototype.navigateBack = function () {
        if (this.state.historyIndex > 0) {
            this.state.historyIndex--;
            var location = this.state.history[this.state.historyIndex];
            this.open(location.hash, false); // Don't add to history
        }
    };

    /**
    * Navigate forward in history
    */
    MyFileManager.prototype.navigateForward = function () {
        if (this.state.historyIndex < this.state.history.length - 1) {
            this.state.historyIndex++;
            var location = this.state.history[this.state.historyIndex];
            this.open(location.hash, false); // Don't add to history
        }
    };

    /**
    * Update navigation button states
    */
    MyFileManager.prototype.updateNavigationButtons = function () {
        var backBtn = this.container.querySelector('[data-action="back"]');
        var forwardBtn = this.container.querySelector('[data-action="forward"]');

        if (backBtn) {
            backBtn.disabled = this.state.historyIndex <= 0;
        }

        if (forwardBtn) {
            forwardBtn.disabled = this.state.historyIndex >= this.state.history.length - 1;
        }
    };

    /**
    * Refresh current directory
    */
    MyFileManager.prototype.refresh = function () {
        // SAVE selection before refresh
        var selectedHashes = this.state.selectedFiles.map(function (file) {
            return file.hash;
        });

        var self = this;
        this.request({
            cmd: 'open',
            target: this.state.currentHash
        }, function (response) {
            // Update state
            self.state.currentHash = response.cwd.hash;
            if (response.cwd.hash) {
                try {
                    self.state.currentPath = atob(response.cwd.hash);
                } catch (e) {
                    self.state.currentPath = '';
                }
            } else {
                self.state.currentPath = '';
            }

            self.state.files = self.filterHiddenFolders(response.files) || [];
            self.state.quota = response.quota;

            self.updateBreadcrumb();
            self.renderFiles();

            // CLEAR selection FIRST!
            self.clearSelection();

            // RESTORE selection (now no duplicates!)
            if (selectedHashes.length > 0) {
                selectedHashes.forEach(function (hash) {
                    var fileEl = self.elements.filesContainer.querySelector('[data-hash="' + hash + '"]');
                    var file = self.getFileByHash(hash);
                    if (fileEl && file) {
                        self.selectFile(fileEl, file);
                    }
                });
            }

            self.updateNavigationButtons();
            self.updateTrashToolbar();
            self.trigger('onChange', response);
        });
    };

    /**
    * Navigate up
    */
    MyFileManager.prototype.navigateUp = function () {
        // If we're at root, do nothing
        if (!this.state.currentPath || this.state.currentPath === '') {
            return;
        }

        // Split path and remove last folder
        var parts = this.state.currentPath.split('/').filter(Boolean);

        if (parts.length === 0) {
            // Already at root
            return;
        }

        // Remove last part
        parts.pop();

        // Build parent path
        var parentPath = parts.join('/');

        // Encode to base64 for navigation
        var parentHash = parentPath ? btoa(parentPath) : '';

        // Navigate to parent
        this.open(parentHash);
    };

    /**
    * Open file or folder
    * This method is used by both double-click and Enter key.
    */
    MyFileManager.prototype.openFile = function (file) {
        if (!file) {
            return;
        }

        // If it's a directory, navigate into it
        if (file.mime === 'directory') {
            this.open(file.hash);
            return;
        }

        // If it's a file, handle with custom opener or default download
        if (typeof this.options.customFileOpener === 'function') {
            this.options.customFileOpener(file);
        } else {
            this.downloadFile(file);
        }

        this.trigger('onFileOpen', file);
    };

    /**
    * Set encryption key for cryptFiles mode
    */
    MyFileManager.prototype.setEncryptionKey = async function (password) {
        if (!this.options.cryptFiles) {
            alert('Encryption disabled');
            return;
        }

        try {
            const salt = crypto.getRandomValues(new Uint8Array(16));
            this.options.encryptionKey = await deriveKeyFromPassword(password, salt);
            this.options.encryptionSalt = salt;

            self._debug('log', 'Encryption key set successfully');

            this.updateMenuStates();

            alert('Encryption enabled! Files will be automatically encrypted/decrypted.');

        } catch (error) {
            alert('Failed to set encryption key: ' + error.message);
        }
    };


    /**
    * Copy selected files
    */
    MyFileManager.prototype.copySelected = function () {
        if (this.state.selectedFiles.length === 0) return;

        this.state.clipboard = {
            operation: 'copy',
            files: this.state.selectedFiles.slice()
        };

        this.updateMenuStates();
        this._debug('log', 'Copied ' + this.state.selectedFiles.length + ' files');
    };

    /**
    * Cut selected files
    */
    MyFileManager.prototype.cutSelected = function () {
        if (this.state.selectedFiles.length === 0) return;

        this.state.clipboard = {
            operation: 'cut',
            files: this.state.selectedFiles.slice()
        };

        this.updateMenuStates();
        this._debug('log', 'Cut ' + this.state.selectedFiles.length + ' files');
    };

    /**
    * Paste clipboard
    */
    MyFileManager.prototype.pasteClipboard = function () {
        if (!this.state.clipboard) return;

        var self = this;
        var operation = this.state.clipboard.operation;
        var targets = this.state.clipboard.files.map(function (f) { return f.hash; });

        var cmd = operation === 'copy' ? 'copy' : 'cut';

        this.request({
            cmd: cmd,
            targets: targets,
            dst: this.state.currentHash
        }, function (response) {
            self.refresh();

            if (operation === 'cut') {
                self.state.clipboard = null;
                self.updateMenuStates();
            }
        });
    };

    /**
    * Delete selected files
    */
    MyFileManager.prototype.deleteSelected = function () {
        if (this.state.selectedFiles.length === 0) return;

        var self = this;
        var message = this.i18n.confirmDelete;

        if (confirm(message)) {
            var targets = this.state.selectedFiles.map(function (f) { return f.hash; });

            var requestData = {
                cmd: 'delete',
                targets: targets
            };

            // Aggiungi token se presente
            if (this.options.token) {
                requestData.token = this.options.token;
            }

            console.log("DELETE REQUEST:", requestData); // DEBUG

            this.request(requestData, function (response) {
                console.log("DELETE RESPONSE:", response); // DEBUG
                self.refresh();
                self.updateTrashIcon();
            });
        }
    };

    /**
    * Update trash icon by checking actual content (not just size/dirs from server)
    */
    MyFileManager.prototype.updateTrashIcon = function () {
        var self = this;

        // Find trash folder in current view
        var trashFolder = this.state.files.find(function (f) {
            return f.name === '.trash' || f.name === self.options.trashPath;
        });

        if (!trashFolder) return;

        // Request trash folder content to count files
        this.request({
            cmd: 'open',
            target: trashFolder.hash
        }, function (response) {
            var hasContent = response.files && response.files.length > 0;

            // Update icon in DOM directly
            var trashElement = self.elements.filesContainer.querySelector('[data-hash="' + trashFolder.hash + '"]');
            if (trashElement) {
                var iconElement = trashElement.querySelector('.mfm-file-icon');
                if (iconElement) {
                    iconElement.className = hasContent ? 'mfm-file-icon icon-trash-full' : 'mfm-file-icon icon-trash-empty';
                }
            }
        });
    };

    /**
    * Rename selected file
    */
    MyFileManager.prototype.renameSelected = function () {
        if (this.state.selectedFiles.length !== 1) return;

        var self = this;
        var file = this.state.selectedFiles[0];
        var newName = prompt(this.i18n.enterNewName, file.name);

        if (newName && newName !== file.name) {
            // SECURITY: Check banned extensions BEFORE rename
            if (this.isBannedExtension(newName)) {
                alert('Dangerous extension detected! Cannot rename to: ' + newName);
                return;
            }

            this.request({
                cmd: 'rename',
                target: file.hash,
                name: newName
            }, function (response) {
                self.refresh();
            });
        }
    };

    // Security: Check if filename has banned extension
    MyFileManager.prototype.isBannedExtension = function (filename) {
        var ext = filename.split('.').pop().toLowerCase();
        return this.options.banExtensions.includes(ext);
    };


    /**
    * Create new folder
    */
    MyFileManager.prototype.createFolder = function () {
        var self = this;
        var name = prompt(this.i18n.enterFolderName, this.i18n.newFolder);

        if (name) {
            // Prevent creating folders starting with dot
            if (name.charAt(0) === '.') {
                alert('Folder names cannot start with a dot (.)');
                return;
            }

            this.request({
                cmd: 'mkdir',
                target: this.state.currentHash,
                name: name
            }, function (response) {
                self.refresh();
            });
        }
    };

    /**
    * Show file info
    */
    MyFileManager.prototype.showInfo = function () {
        if (this.state.selectedFiles.length !== 1) return;

        var self = this;
        var file = this.state.selectedFiles[0];

        this.request({
            cmd: 'info',
            target: file.hash
        }, function (response) {
            var html = '<div class="mfm-info">';
            html += '<p><strong>' + self.i18n.properties + '</strong></p>';
            html += '<p><strong>' + self.escapeHtml(response.name) + '</strong></p>';
            html += '<p>' + self.i18n.type + ': ' + (response.mime === 'directory' ? self.i18n.folder : response.mime) + '</p>';

            if (response.mime !== 'directory') {
                html += '<p>' + self.i18n.size + ': ' + self.formatSize(response.size) + '</p>';
            }

            html += '<p>' + self.i18n.dateModified + ': ' + self.formatDate(response.ts) + '</p>';

            // Show URL only if option is enabled
            if (self.options.showUrlOnProperties && response.url) {
                html += '<p>URL: <a href="' + response.url + '" target="_blank">' + response.url + '</a></p>';
            }

            if (response.dim) {
                html += '<p>' + self.i18n.size + ': ' + response.dim + '</p>';
            }

            html += '</div>';

            self.showModal(self.i18n.properties, html);
        });
    };

    /**
    * Show modal
    */
    MyFileManager.prototype.showModal = function (title, content) {
        var html = '';
        html += '<div class="mfm-modal-overlay"></div>';
        html += '<div class="mfm-modal-dialog">';
        html += '<div class="mfm-modal-header">';
        html += '<h3>' + this.escapeHtml(title) + '</h3>';
        html += '<button class="mfm-modal-close">&times;</button>';
        html += '</div>';
        html += '<div class="mfm-modal-body">' + content + '</div>';
        html += '</div>';

        this.elements.modal.innerHTML = html;
        this.elements.modal.style.display = 'block';
    };

    /**
     * Close modal
     */
    MyFileManager.prototype.closeModal = function () {
        this.elements.modal.style.display = 'none';
        this.elements.modal.innerHTML = '';
    };

    /**
 * Show download progress modal
 */
    MyFileManager.prototype.showDownloadProgress = function (filename, filesize) {
        var modal = this.elements.downloadModal;
        var filenameEl = modal.querySelector('.mfm-download-filename');
        var filesizeEl = modal.querySelector('.mfm-download-filesize');
        var statusDiv = modal.querySelector('.mfm-download-status');
        var actionsDiv = modal.querySelector('.mfm-download-actions');

        if (filenameEl) {
            filenameEl.textContent = filename;
        }

        if (filesizeEl) {
            filesizeEl.textContent = 'Size: ' + this.formatSize(filesize);
        }

        // Reset status and actions
        if (statusDiv) {
            statusDiv.style.display = 'none';
        }
        if (actionsDiv) {
            actionsDiv.style.display = 'none';
        }

        modal.style.display = 'flex';
        this.updateDownloadProgress(0, 0, 0);
    };

    /**
     * Hide download progress modal
     */
    MyFileManager.prototype.hideDownloadProgress = function () {
        this.elements.downloadModal.style.display = 'none';
        this.currentDownloadController = null;
    };

    /**
     * Update download progress
     */
    MyFileManager.prototype.updateDownloadProgress = function (percent, speed, timeRemaining) {
        var modal = this.elements.downloadModal;
        var progressFill = modal.querySelector('.mfm-progress-fill');
        var progressText = modal.querySelector('.mfm-progress-text');
        var speedEl = modal.querySelector('.mfm-download-speed');
        var timeEl = modal.querySelector('.mfm-download-time');

        if (progressFill) {
            progressFill.style.width = percent + '%';
        }

        if (progressText) {
            progressText.textContent = Math.round(percent) + '%';
        }

        if (speedEl) {
            speedEl.textContent = this.formatSize(speed) + '/s';
        }

        if (timeEl && timeRemaining < Infinity) {
            var minutes = Math.floor(timeRemaining / 60);
            var seconds = Math.floor(timeRemaining % 60);
            timeEl.textContent = minutes + 'm ' + seconds + 's remaining';
        }
    };

    /**
     * Show download error
     */
    MyFileManager.prototype.showDownloadError = function (message) {
        var modal = this.elements.downloadModal;
        var statusDiv = modal.querySelector('.mfm-download-status');
        var statusMsg = modal.querySelector('.mfm-download-status-message');
        var actionsDiv = modal.querySelector('.mfm-download-actions');
        var progressContainer = modal.querySelector('.mfm-progress-container');

        if (progressContainer) {
            progressContainer.style.display = 'none';
        }

        if (statusDiv && statusMsg) {
            statusMsg.innerHTML = '<strong>Error:</strong> ' + this.escapeHtml(message);
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#d32f2f';
        }

        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }
    };

    /**
     * Cancel download
     */
    MyFileManager.prototype.cancelDownload = function () {
        if (this.currentDownloadController) {
            this.currentDownloadController.abort();
            this.currentDownloadController = null;
        }
    };

    /**
     * Upload files
     */
    MyFileManager.prototype.uploadFiles = function (files) {
        var self = this;

        Array.from(files).forEach(function (file) {
            self.uploadFile(file);
        });
    };

    /**
    * Chunked file upload function - Compatible with original server
    * Handles large files by splitting into configurable chunks
    * @param {File} file - File object to upload
    */
    MyFileManager.prototype.uploadFile = async function (file) {
        var self = this;

        // RESET upload stats for new upload
        this.uploadStats = {
            startTime: 0,
            totalBytes: 0,
            uploadedBytes: 0,
            speedSamples: [],
            avgSpeed: 0,
            maxSamples: 10  // Adjust for smoother/faster response (5-15 optimal)
        };

        // Find extension from right (last dot) - DO NOT MODIFY extension!
        const lastDotIndex = file.name.lastIndexOf('.');
        let fileName = file.name;
        let fileExt = '';

        if (lastDotIndex > 0) {
            // Split: keep extension UNTOUCHED, sanitize only filename
            fileExt = file.name.substring(lastDotIndex);  // Keep original extension ".mp4"
            fileName = file.name.substring(0, lastDotIndex);  // Get filename without extension
        }

        // Sanitize ONLY the filename part (leave extension untouched!)
        let sanitizedName = fileName
            .replace(/['"]/g, '')  // Remove quotes/apostrophes
            .replace(/[^\w\s.-]/g, '_')  // Replace special chars with _
            .replace(/\.+/g, '_')  // Replace multiple dots with _
            .replace(/\s+/g, '_')  // Replace spaces with _
            .replace(/_{2,}/g, '_')  // Remove double underscores
            .replace(/^_+|_+$/g, '');  // Remove leading/trailing underscores

        // Rebuild: sanitized name + ORIGINAL extension (never modified!)
        const finalName = sanitizedName + fileExt;

        // Create new file with sanitized name but KEEP original MIME type
        let sanitizedFile = new File([file], finalName, {
            type: file.type,  // Keep original type from browser
            lastModified: file.lastModified
        });

        // Debug logging
        self._debug('log', 'Original name:', file.name);
        self._debug('log', 'Sanitized name:', finalName);
        self._debug('log', 'Extension (untouched):', fileExt);
        self._debug('log', 'MIME type:', sanitizedFile.type);

        if (this.isBannedExtension(sanitizedFile.name)) {
            alert('Upload blocked! Dangerous extension: ' + sanitizedFile.name);
            return;
        }

        // Debug: Check if function is called (use sanitized name)
        self._debug('log', 'uploadFile CALLED!', sanitizedFile.name, sanitizedFile.size);
        self._debug('log', 'URL:', this.options.url);
        self._debug('log', 'Token:', this.options.token ? 'EXISTS' : 'MISSING');
        self._debug('log', 'Target hash:', this.state.currentHash);

        // Validate file size against max limit
        if (sanitizedFile.size > this.options.maxFileSize) {
            alert('File too large: ' + sanitizedFile.name);
            return;
        }

        // Show upload progress modal and trigger start event (use sanitized)
        this.showUploadProgress(sanitizedFile.name);
        this.trigger('onUploadStart', { file: sanitizedFile });
        await this.requestWakeLock(); // Keep screen on during upload
        self.currentUploadXHR = null;

        try {
            let uploadFile = sanitizedFile;  // START with sanitized file

            // Encrypt file if encryption enabled for this file type
            if (this.shouldEncrypt(uploadFile.type)) {
                const arrayBuffer = await uploadFile.arrayBuffer();
                const encryptedBuffer = await this.encryptFileData(new Uint8Array(arrayBuffer), this.options.encryptionKey);
                uploadFile = new File([encryptedBuffer], uploadFile.name + '.encrypted', {
                    type: 'application/octet-stream',
                    lastModified: Date.now()
                });
                self._debug('log', 'Encrypted file before upload:', uploadFile.name, uploadFile.size);
            }

            // Chunk settings - 5MB chunks for better compatibility
            const chunkSize = 5 * 1024 * 1024;  // 5MB chunks
            const chunks = Math.ceil(uploadFile.size / chunkSize);
            let currentChunk = 0;
            const uploadId = uploadFile.name.replace(/[^a-z0-9]/gi, '') + '_' + Date.now();
            let lastTime = new Date().getTime();
            let lastLoaded = 0;

            /**
            * Upload single chunk with authentication token
            */
            async function uploadChunk() {
                const start = currentChunk * chunkSize;
                const end = Math.min(start + chunkSize, uploadFile.size);
                const chunk = uploadFile.slice(start, end);

                // Create FormData with all required parameters including token
                const formData = new FormData();
                formData.append('cmd', 'upload');
                const targetPath = self.state.currentPath || atob(self.state.currentHash || '');
                formData.append('target', btoa(targetPath));
                formData.append('upload', chunk, uploadFile.name);
                formData.append('part', currentChunk);
                formData.append('parts', chunks);
                formData.append('id', uploadId);

                // Add token to FormData for server authentication
                if (self.options.token) {
                    formData.append('token', self.options.token);
                }

                // Debug: Log FormData contents
                if (self.options.debug) {
                    self._debug('log', '📤 FormData contents:');
                    for (let pair of formData.entries()) {
                        self._debug('log', pair[0] + ':', (pair[1] instanceof File ? pair[1].name : pair[1]));
                    }
                }

                const xhr = new XMLHttpRequest();
                self.currentUploadXHR = xhr;

                // Track upload progress with SMOOTHED speed calculation
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const totalLoaded = (currentChunk * chunkSize) + e.loaded;
                        const percent = Math.min(100, Math.round((totalLoaded / uploadFile.size) * 100));

                        const now = new Date().getTime();

                        // Initialize start time on first progress event
                        if (self.uploadStats.startTime === 0) {
                            self.uploadStats.startTime = now;
                            self.uploadStats.uploadedBytes = 0;
                            self.uploadStats.speedSamples = [];
                            self.uploadStats.avgSpeed = 0;
                        }

                        const deltaTime = (now - lastTime) / 1000; // seconds
                        const deltaLoaded = totalLoaded - lastLoaded;

                        // Calculate instantaneous speed
                        let instantSpeed = 0;
                        if (deltaTime > 0 && deltaLoaded > 0) {
                            instantSpeed = deltaLoaded / deltaTime; // bytes/sec
                        }

                        // Add to speed samples array (only if speed is valid)
                        if (instantSpeed > 0) {
                            self.uploadStats.speedSamples.push(instantSpeed);

                            // Keep only last N samples for moving average
                            if (self.uploadStats.speedSamples.length > self.uploadStats.maxSamples) {
                                self.uploadStats.speedSamples.shift(); // Remove oldest
                            }
                        }

                        // Calculate average speed from samples
                        let avgSpeed = 0;
                        if (self.uploadStats.speedSamples.length > 0) {
                            const sum = self.uploadStats.speedSamples.reduce((a, b) => a + b, 0);
                            avgSpeed = sum / self.uploadStats.speedSamples.length;
                        }

                        // Use average speed for time calculation
                        let timeRemaining = Infinity;
                        if (avgSpeed > 0) {
                            const remainingBytes = uploadFile.size - totalLoaded;
                            timeRemaining = remainingBytes / avgSpeed; // seconds
                        }

                        // Update tracking variables
                        lastTime = now;
                        lastLoaded = totalLoaded;

                        // Update UI with AVERAGED speed and time
                        self.updateUploadProgress(percent, avgSpeed, timeRemaining);
                    }
                });

                return new Promise((resolve, reject) => {
                    // Handle successful HTTP response
                    xhr.addEventListener('load', function () {
                        self._debug('log', `Chunk ${currentChunk}/${chunks - 1}: ${xhr.status}`);
                        self._debug('log', 'Response:', xhr.responseText);

                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);

                                // Check for server errors
                                if (response.error) {
                                    self.showUploadError(response.error);
                                    self.trigger('onUploadError', { file: uploadFile, error: response.error });
                                    reject(response.error);
                                    return;
                                }

                                // Last chunk uploaded - complete upload
                                if (currentChunk === chunks - 1) {
                                    self.hideUploadProgress();
                                    self.trigger('onUploadComplete', { file: uploadFile, response: response });
                                    self.refresh();
                                    // AUTO-SELECT uploaded file after refresh
                                    setTimeout(function () {
                                        self.selectUploadedFile(uploadFile.name);
                                    }, 500);
                                    resolve();
                                } else {
                                    // Continue with next chunk
                                    currentChunk++;
                                    uploadChunk().then(resolve).catch(reject);
                                }
                            } catch (e) {
                                self.showUploadError('Invalid server response');
                                self.trigger('onUploadError', { file: uploadFile, error: e });
                                reject(e);
                            }
                        } else {
                            self.showUploadError('HTTP Error ' + xhr.status);
                            self.trigger('onUploadError', { file: uploadFile, error: xhr.status });
                            reject('HTTP ' + xhr.status);
                        }
                    });

                    // Handle network errors
                    xhr.addEventListener('error', function () {
                        self.showUploadError('Network error');
                        self.trigger('onUploadError', { file: uploadFile, error: 'network' });
                        reject('network');
                    });

                    // Send request with Bearer token authentication
                    xhr.open('POST', self.options.url);
                    if (self.options.token) {
                        // Send token as Bearer Authorization header (for myetv_functions.php validation)
                        xhr.setRequestHeader('Authorization', 'Bearer ' + self.options.token);
                        xhr.setRequestHeader('token', self.options.token);  // Keep for compatibility
                    }
                    xhr.send(formData);
                });
            }

            // Start the chunked upload sequence
            await uploadChunk();

        } catch (error) {
            self._debug('error', 'Upload failed:', error);
            self.showUploadError('Upload failed: ' + error);
            self.trigger('onUploadError', { file: sanitizedFile, error: error });
        } finally {
            self.releaseWakeLock(); // Release when done (success or error)
            // Always cleanup XHR reference
            self.currentUploadXHR = null;
        }
    };

    /**
    * Select uploaded file by name after upload completes
    */
    MyFileManager.prototype.selectUploadedFile = function (filename) {
        var self = this;

        // Clear current selection
        this.clearSelection();

        // Find uploaded file by name
        var uploadedFile = this.state.files.find(function (f) {
            return f.name === filename;
        });

        if (uploadedFile) {
            // Find DOM element
            var fileElement = this.elements.filesContainer.querySelector('[data-hash="' + uploadedFile.hash + '"]');
            if (fileElement) {
                // Select file
                this.selectFile(fileElement, uploadedFile);

                // Scroll into view
                fileElement.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

                this._debug('log', 'Auto-selected uploaded file:', filename);
            }
        }
    };

    /**
    * Show upload progress modal
    */
    MyFileManager.prototype.showUploadProgress = function (filename) {
        var modal = this.elements.uploadModal;
        var filenameEl = modal.querySelector('.mfm-upload-filename');
        var statusDiv = modal.querySelector('.mfm-upload-status');
        var progressContainer = modal.querySelector('.mfm-progress-container');
        var actionsDiv = modal.querySelector('.mfm-upload-actions');

        if (filenameEl) {
            filenameEl.textContent = filename;
        }

        // RESET: Hide status messages and actions from previous upload
        if (statusDiv) {
            statusDiv.style.display = 'none';
            statusDiv.style.color = '';
            statusDiv.style.padding = '';
            statusDiv.style.background = '';
            statusDiv.style.borderRadius = '';
            var statusMsg = modal.querySelector('.mfm-upload-status-message');
            if (statusMsg) {
                statusMsg.innerHTML = '';
            }
        }

        if (actionsDiv) {
            actionsDiv.style.display = 'none';
        }

        // RESET: Show and reset progress bar
        if (progressContainer) {
            progressContainer.style.display = 'block';
        }

        // Reset title to "Uploading Files"
        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = 'Uploading File';
        }

        modal.style.display = 'flex';

        // Reset progress to 0
        this.updateUploadProgress(0, 0, 0);
    };

    /**
    * Update upload progress
    */
    MyFileManager.prototype.updateUploadProgress = function (percent, speed, timeRemaining) {
        var modal = this.elements.uploadModal;
        var progressFill = modal.querySelector('.mfm-progress-fill');
        var progressText = modal.querySelector('.mfm-progress-text');
        var speedEl = modal.querySelector('.mfm-upload-speed');
        var timeEl = modal.querySelector('.mfm-upload-time');

        if (progressFill) progressFill.style.width = percent + '%';
        if (progressText) progressText.textContent = percent + '%';

        // Format speed (MB/s)
        if (speedEl) {
            var speedMB = (speed / 1024 / 1024).toFixed(2);
            speedEl.textContent = speedMB + ' MB/s';
        }

        // Format time remaining
        if (timeEl) {
            if (timeRemaining > 0 && timeRemaining < Infinity) {
                var minutes = Math.floor(timeRemaining / 60);
                var seconds = Math.floor(timeRemaining % 60);

                if (minutes > 0) {
                    timeEl.textContent = minutes + 'm ' + seconds + 's remaining';
                } else {
                    timeEl.textContent = seconds + 's remaining';
                }
            } else {
                timeEl.textContent = 'Calculating...';
            }
        }
    };

    /**
    * Hide upload progress modal
    */
    MyFileManager.prototype.hideUploadProgress = function () {
        var modal = this.elements.uploadModal;

        // Reset all modal state
        var statusDiv = modal.querySelector('.mfm-upload-status');
        if (statusDiv) {
            statusDiv.style.display = 'none';
            var statusMsg = modal.querySelector('.mfm-upload-status-message');
            if (statusMsg) {
                statusMsg.innerHTML = '';
            }
        }

        var actionsDiv = modal.querySelector('.mfm-upload-actions');
        if (actionsDiv) {
            actionsDiv.style.display = 'none';
        }

        var progressContainer = modal.querySelector('.mfm-progress-container');
        if (progressContainer) {
            progressContainer.style.display = 'block';
        }

        // Reset progress bar
        this.updateUploadProgress(0, 0, 0);

        this.elements.uploadModal.style.display = 'none';
        this.currentUploadXHR = null;
    };

    /**
    * Cancel current upload
    */
    MyFileManager.prototype.cancelUpload = function () {
        if (this.currentUploadXHR) {
            this.currentUploadXHR.abort();
        }
    };

    /**
    * Show upload error
    */
    MyFileManager.prototype.showUploadError = function (errorMessage) {
        var modal = this.elements.uploadModal;
        var statusDiv = modal.querySelector('.mfm-upload-status');
        var statusMsg = modal.querySelector('.mfm-upload-status-message');
        var actionsDiv = modal.querySelector('.mfm-upload-actions');
        var progressContainer = modal.querySelector('.mfm-progress-container');

        // Hide progress
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }

        // Show error message
        if (statusDiv && statusMsg) {
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#d32f2f';
            statusDiv.style.padding = '10px';
            statusDiv.style.background = '#ffebee';
            statusDiv.style.borderRadius = '4px';
            statusMsg.innerHTML = '<strong>❌ Error:</strong> ' + this.escapeHtml(errorMessage);
        }

        // Show close button
        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }

        // Update title
        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = 'Upload Failed';
        }

        this.currentUploadXHR = null;
    };

    /**
    * Show upload complete
    */
    MyFileManager.prototype.showUploadComplete = function (filename) {
        var modal = this.elements.uploadModal;
        var statusDiv = modal.querySelector('.mfm-upload-status');
        var statusMsg = modal.querySelector('.mfm-upload-status-message');
        var actionsDiv = modal.querySelector('.mfm-upload-actions');
        var progressContainer = modal.querySelector('.mfm-progress-container');

        // Keep progress visible at 100%
        this.updateUploadProgress(100, 0, 0);

        // Show success message
        if (statusDiv && statusMsg) {
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#2e7d32';
            statusDiv.style.padding = '10px';
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.borderRadius = '4px';
            statusMsg.innerHTML = '<strong>Success:</strong> File uploaded successfully!';
        }

        // Show close button
        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }

        // Update title
        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = 'Upload Complete';
        }

        this.currentUploadXHR = null;
    };

    /**
    * Handle search
    */
    MyFileManager.prototype.handleSearch = function (query) {
        var self = this;

        if (!query) {
            this.renderFiles();
            return;
        }

        this.request({
            cmd: 'search',
            q: query,
            target: this.state.currentHash
        }, function (response) {
            // Filter hidden folders before storing
            self.state.files = self.filterHiddenFolders(response.files) || [];
            self.renderFiles();
        });
    };

    /**
     * Make AJAX request
     */
    MyFileManager.prototype.request = function (data, callback) {
        var self = this;
        var xhr = new XMLHttpRequest();
        var url = this.options.url;

        // Add token to URL if present
        if (this.options.token) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(this.options.token);
        }

        // DEBUG: Log request details
        this._debug('log', '=== REQUEST START ===');
        this._debug('log', 'URL:', url);
        this._debug('log', 'Data:', data);

        xhr.open('POST', url);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        if (this.options.token) {
            xhr.setRequestHeader('Authorization', 'Bearer ' + this.options.token);
        }

        xhr.onload = function () {
            self._debug('log', '=== RESPONSE RECEIVED ===');
            self._debug('log', 'Status:', xhr.status);
            self._debug('log', 'Response Text:', xhr.responseText);

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    self._debug('log', 'Parsed Response:', response);

                    if (response.error) {
                        self.handleError(response.error);
                    } else {
                        callback(response);
                    }
                } catch (e) {
                    self._debug('error', 'JSON Parse Error:', e);
                    self._debug('error', 'Raw Response:', xhr.responseText);
                    self.handleError('Invalid response - Check console for details');
                }
            } else {
                self._debug('error', 'HTTP Error:', xhr.status);
                self.handleError('HTTP ' + xhr.status);
            }
        };

        xhr.onerror = function () {
            self._debug('error', 'Network error');
            self.handleError('Network error');
        };

        // Convert data to URL-encoded string, handling arrays correctly for PHP backend
        var params = [];
        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                if (Array.isArray(data[key])) {
                    data[key].forEach(function (val) {
                        // Append [] to key for array serialization required by PHP
                        params.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(val));
                    });
                } else {
                    params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                }
            }
        }

        self._debug('log', 'Sending params:', params.join('&'));
        xhr.send(params.join('&'));
    };

    /**
    * Restore selected files from trash
    */
    MyFileManager.prototype.restoreSelected = function () {
        var self = this;

        if (this.state.selectedFiles.length === 0) {
            alert("No file selected");
            return;
        }

        var hashes = this.state.selectedFiles.map(function (file) {
            return file.hash;
        });

        this.request({ cmd: 'restore', hashes: hashes }, function (response) {
            if (response.error) {
                self._debug('error', 'Restore error:', response.error);
                alert('Error: ' + response.error);
            } else {
                self._debug('log', 'Files restored successfully');
                self.refresh();
            }
        });
    };

    /**
     * Empty trash - delete all files permanently
     */
    MyFileManager.prototype.emptyTrash = function () {
        var self = this;

        if (!confirm(this.i18n.confirmEmptyTrash || this.i18n.confirmDelete)) {
            return;
        }

        // Select ALL files in current folder (trash)
        this.clearSelection();
        this.state.files.forEach(function (file) {
            var fileEl = self.elements.filesContainer.querySelector('[data-hash="' + file.hash + '"]');
            if (fileEl) {
                self.selectFile(fileEl, file);
            }
        });

        // Reuse existing deleteSelected()
        this.deleteSelected();
    };

    /**
     * Update trash toolbar visibility
     */
    MyFileManager.prototype.updateTrashToolbar = function () {
        if (!this.elements || !this.elements.trashToolbar || !this.elements.toolbar) {
            return;
        }

        var inTrash = this.isInTrash();
        this._debug('log', 'updateTrashToolbar', inTrash, 'currentPath', this.state.currentPath);

        if (inTrash) {
            this.elements.trashToolbar.style.display = 'flex';
            this.elements.toolbar.style.display = 'flex';
        } else {
            this.elements.trashToolbar.style.display = 'none';
            this.elements.toolbar.style.display = 'flex';
        }
    };

    /**
    * Initialize all registered plugins
    */
    MyFileManager.prototype.initPlugins = function () {
        var self = this;

        // Check if global plugin registry exists
        if (window.MyFileManagerPlugins && Array.isArray(window.MyFileManagerPlugins)) {
            window.MyFileManagerPlugins.forEach(function (pluginInitializer) {
                try {
                    // Call plugin initializer with file manager instance
                    var plugin = pluginInitializer(self);

                    // Store plugin instance
                    if (plugin) {
                        self.plugins.push(plugin);
                        self._debug('log', 'Plugin initialized:', plugin.name || 'Unknown');
                    }
                } catch (error) {
                    self._debug('error', 'Plugin initialization failed:', error);
                }
            });
        }
    };

    /**
    * Register a plugin
    * Plugins can add context menu items and toolbar menu items
    * @param {Object} plugin - Plugin configuration
    */
    MyFileManager.prototype.registerPlugin = function (plugin) {
        if (!plugin || typeof plugin !== 'object') {
            this._debug('error', 'Invalid plugin object');
            return;
        }

        var self = this;

        // Add context menu items from plugin
        if (plugin.contextMenuItems && Array.isArray(plugin.contextMenuItems)) {
            plugin.contextMenuItems.forEach(function (item) {
                self.options.customContextMenu.push(item);
            });
        }

        // Add toolbar menu items from plugin
        if (plugin.toolbarMenuItems && Array.isArray(plugin.toolbarMenuItems)) {
            plugin.toolbarMenuItems.forEach(function (menuConfig) {
                // Check if menu already exists (e.g., "Plugins" menu)
                var existingMenuIndex = -1;
                for (var i = 0; i < self.options.customMenus.length; i++) {
                    if (self.options.customMenus[i].id === menuConfig.id) {
                        existingMenuIndex = i;
                        break;
                    }
                }

                if (existingMenuIndex !== -1) {
                    // Merge items into existing menu
                    var existingMenu = self.options.customMenus[existingMenuIndex];
                    menuConfig.items.forEach(function (item) {
                        existingMenu.items.push(item);
                    });
                } else {
                    // Add new menu
                    self.options.customMenus.push(menuConfig);
                }
            });
        }

        // Store plugin
        this.plugins.push(plugin);

        this._debug('log', 'Plugin registered:', plugin.name || 'Unknown');

        return plugin;
    };


    /**
     * Setup auto-refresh functionality
     * Refreshes the file view at specified intervals
     */
    MyFileManager.prototype.setupAutoRefresh = function () {
        var self = this;

        // Clear any existing interval
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }

        // Check if autoRefresh is enabled and is a number
        if (this.options.autoRefresh && typeof this.options.autoRefresh === 'number' && this.options.autoRefresh > 0) {
            this._debug('log', 'Auto-refresh enabled: ' + this.options.autoRefresh + 'ms');

            // Setup interval
            this.autoRefreshInterval = setInterval(function () {
                self._debug('log', 'Auto-refresh triggered');
                self.refresh();
            }, this.options.autoRefresh);
        }
    };

    /**
     * Stop auto-refresh
     */
    MyFileManager.prototype.stopAutoRefresh = function () {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
            this._debug('log', 'Auto-refresh stopped');
        }
    };

    /**
     * Start or restart auto-refresh with new interval
     * @param {number} interval - Interval in milliseconds
     */
    MyFileManager.prototype.startAutoRefresh = function (interval) {
        if (interval && typeof interval === 'number' && interval > 0) {
            this.options.autoRefresh = interval;
            this.setupAutoRefresh();
        }
    };

    /**
    * Destroy file manager instance and cleanup
    */
    MyFileManager.prototype.destroy = function () {
        // Stop auto-refresh
        this.stopAutoRefresh();

        // Clear plugins
        this.plugins = [];

        // Clear DOM
        if (this.container) {
            this.container.innerHTML = '';
        }

        this._debug('log', 'File manager destroyed');
    };

    /**
     * Handle error
     */
    MyFileManager.prototype.handleError = function (error) {
        this._debug('error', 'File Manager Error:', error);
        alert('Error: ' + error);
        this.trigger('onError', error);
    };

    // Export to window
    window.MyFileManager = MyFileManager;

})(window);