/**
 * // myfilemanager.js
 * My File Manager - Enhanced JavaScript UI
 * MyFileManager - Secure File Manager with Universal Crypto Support
 * WebCrypto AES-256-GCM with CryptoJS CTR+HMAC fallback
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

/**
 * Load CryptoJS ONLY if WebCrypto is not available (without eval)
 */
async function loadCryptoJSIfNeeded() {
    // Skip if WebCrypto is available (modern browsers in secure context)
    if (typeof crypto !== 'undefined' && crypto.subtle && typeof crypto.subtle.importKey === 'function') {
        return;
    }

    // WebCrypto not available - load CryptoJS via script tag as fallback
    if (window.CryptoJS) return;

    return new Promise(function (resolve) {
        var existing = document.querySelector('script[data-mfm-cryptojs="true"]');
        if (existing) {
            existing.addEventListener('load', function () { resolve(); });
            existing.addEventListener('error', function () { resolve(); });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js';
        script.integrity = 'sha512-a+SUDuwNzXDvz4XrIcXHuCf089/iJAoN4lmrXJg18XnduKK6YlDHNRalv4yd1N40OKI80tFidF+rqTFKGPoWFQ==';
        script.crossOrigin = 'anonymous';
        script.referrerPolicy = 'no-referrer';
        script.setAttribute('data-mfm-cryptojs', 'true');
        script.onload = function () { resolve(); };
        script.onerror = function (err) {
            console.warn('CryptoJS load failed - WebCrypto required:', err);
            resolve();
        };
        document.head.appendChild(script);
    });
}

// Auto-init crypto fallback check on page load
loadCryptoJSIfNeeded();

// Check Web Crypto API availability
function hasWebCrypto() {
    return typeof crypto !== 'undefined' &&
        crypto.subtle &&
        typeof crypto.subtle.importKey === 'function';
}

// Convert Uint8Array to CryptoJS WordArray
function uint8ArrayToWordArray(u8arr) {
    var words = [];
    for (var i = 0; i < u8arr.length; i += 4) {
        words.push(
            ((u8arr[i] || 0) << 24) |
            ((u8arr[i + 1] || 0) << 16) |
            ((u8arr[i + 2] || 0) << 8) |
            (u8arr[i + 3] || 0)
        );
    }
    return window.CryptoJS.lib.WordArray.create(words, u8arr.length);
}

// Convert CryptoJS WordArray to Uint8Array
function wordArrayToUint8Array(wordArray) {
    var words = wordArray.words;
    var sigBytes = wordArray.sigBytes;
    var u8 = new Uint8Array(sigBytes);
    for (var i = 0; i < sigBytes; i++) {
        u8[i] = (words[i >>> 2] >>> (24 - (i % 4) * 8)) & 0xff;
    }
    return u8;
}

// Derive secure key from password (PBKDF2 100k iterations)
async function deriveKeyFromPassword(password, salt) {
    if (hasWebCrypto()) {
        // WebCrypto PBKDF2 (native, fastest)
        var encoder = new TextEncoder();
        var keyMaterial = await crypto.subtle.importKey(
            'raw',
            encoder.encode(password),
            { name: 'PBKDF2' },
            false,
            ['deriveKey']
        );
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt: salt, iterations: 100000, hash: 'SHA-256' },
            keyMaterial,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    } else if (window.CryptoJS) {
        // CryptoJS PBKDF2 fallback (512 bits: 256-bit enc key + 256-bit HMAC key)
        var saltWA = uint8ArrayToWordArray(salt);
        return window.CryptoJS.PBKDF2(password, saltWA, {
            keySize: 16, // 16 * 32 = 512 bits
            iterations: 100000,
            hasher: window.CryptoJS.algo.SHA256
        });
    }
    throw new Error('No crypto engine available');
}

// Encrypt file data (AES-256-GCM on WebCrypto, AES-256-CTR + HMAC-SHA256 on CryptoJS)
async function encryptFileData(fileData, key) {
    var bytes = fileData instanceof Uint8Array ? fileData : new Uint8Array(fileData);

    if (hasWebCrypto()) {
        var iv = crypto.getRandomValues(new Uint8Array(12));
        var encrypted = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            bytes
        );
        // Prepend IV (12 bytes) + encrypted data
        var result = new Uint8Array(12 + encrypted.byteLength);
        result.set(iv, 0);
        result.set(new Uint8Array(encrypted), 12);
        return result;
    } else if (window.CryptoJS) {
        // Split 512-bit derived key into 256-bit AES key + 256-bit HMAC key
        var fullKeyWA = key;
        var encKeyWA = window.CryptoJS.lib.WordArray.create(fullKeyWA.words.slice(0, 8), 32);
        var macKeyWA = window.CryptoJS.lib.WordArray.create(fullKeyWA.words.slice(8, 16), 32);

        var ivBytes = generateSalt(); // 16 bytes IV for CTR
        var ivWA = uint8ArrayToWordArray(ivBytes);
        var dataWA = uint8ArrayToWordArray(bytes);

        var encryptedCTR = window.CryptoJS.AES.encrypt(dataWA, encKeyWA, {
            iv: ivWA,
            mode: window.CryptoJS.mode.CTR,
            padding: window.CryptoJS.pad.NoPadding
        });

        var cipherBytes = wordArrayToUint8Array(encryptedCTR.ciphertext);
        var macInput = ivWA.clone().concat(encryptedCTR.ciphertext);
        var macWA = window.CryptoJS.HmacSHA256(macInput, macKeyWA);
        var macBytes = wordArrayToUint8Array(macWA); // 32 bytes

        // Output: [16 bytes IV][32 bytes HMAC][ciphertext]
        var out = new Uint8Array(16 + 32 + cipherBytes.length);
        out.set(ivBytes, 0);
        out.set(macBytes, 16);
        out.set(cipherBytes, 48);
        return out;
    }
    throw new Error('Encryption not available');
}

// Decrypt file data
async function decryptFileData(encryptedData, key) {
    var bytes = encryptedData instanceof Uint8Array ? encryptedData : new Uint8Array(encryptedData);

    if (hasWebCrypto()) {
        if (bytes.length < 13) {
            throw new Error('Corrupted encrypted data');
        }
        var iv = bytes.slice(0, 12);
        var data = bytes.slice(12);
        return await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            data
        );
    } else if (window.CryptoJS) {
        if (bytes.length < 48) {
            throw new Error('Corrupted encrypted data');
        }
        var fullKeyWA = key;
        var encKeyWA = window.CryptoJS.lib.WordArray.create(fullKeyWA.words.slice(0, 8), 32);
        var macKeyWA = window.CryptoJS.lib.WordArray.create(fullKeyWA.words.slice(8, 16), 32);

        var ivBytes = bytes.slice(0, 16);
        var macBytes = bytes.slice(16, 48);
        var cipherBytes = bytes.slice(48);

        var ivWA = uint8ArrayToWordArray(ivBytes);
        var cipherWA = uint8ArrayToWordArray(cipherBytes);
        var macInput = ivWA.clone().concat(cipherWA);
        var expectedMac = wordArrayToUint8Array(window.CryptoJS.HmacSHA256(macInput, macKeyWA));

        // Constant-time MAC verification
        var diff = 0;
        for (var i = 0; i < 32; i++) {
            diff |= (macBytes[i] ^ expectedMac[i]);
        }
        if (diff !== 0) {
            throw new Error('Decryption failed: invalid password or corrupted data');
        }

        var decrypted = window.CryptoJS.AES.decrypt(
            { ciphertext: cipherWA },
            encKeyWA,
            { iv: ivWA, mode: window.CryptoJS.mode.CTR, padding: window.CryptoJS.pad.NoPadding }
        );
        return wordArrayToUint8Array(decrypted);
    }
    throw new Error('Decryption not available');
}

// Generate random salt (16 bytes)
function generateSalt() {
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        return crypto.getRandomValues(new Uint8Array(16));
    }
    throw new Error('Secure random generator not available');
}

// Convert ArrayBuffer to base64 (for storage)
function arrayBufferToBase64(buffer) {
    var binary = '';
    var bytes = new Uint8Array(buffer);
    for (var i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

// Convert base64 to ArrayBuffer
function base64ToArrayBuffer(base64) {
    var binary = atob(base64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

// UTF-8 safe Base64 encoding (compatible with PHP base64_encode)
function utf8ToBase64(str) {
    if (!str) return '';
    var bytes = new TextEncoder().encode(str);
    var binary = '';
    for (var i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

// UTF-8 safe Base64 decoding (compatible with PHP base64_encode)
function base64ToUtf8(b64) {
    if (!b64) return '';
    var normalized = b64.replace(/-/g, '+').replace(/_/g, '/');
    while (normalized.length % 4 !== 0) {
        normalized += '=';
    }
    var binary = atob(normalized);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return new TextDecoder().decode(bytes);
}

// Expose global functions for MyFileManager
window.MyFileManagerCrypto = {
    hasWebCrypto: hasWebCrypto,
    deriveKeyFromPassword: deriveKeyFromPassword,
    encryptFileData: encryptFileData,
    decryptFileData: decryptFileData,
    generateSalt: generateSalt,
    arrayBufferToBase64: arrayBufferToBase64,
    base64ToArrayBuffer: base64ToArrayBuffer
};

(function (window) {
    'use strict';

    // Magic header for self-contained encrypted files (includes 16-byte per-file salt)
    var ENC_MAGIC_BYTES = new Uint8Array([0x4D, 0x46, 0x4D, 0x45, 0x4E, 0x43, 0x30, 0x31]); // "MFMENC01"

    /**
     * MyFileManager Constructor
     */
    function MyFileManager(selector, options) {
        this.container = document.querySelector(selector);

        if (!this.container) {
            throw new Error('Container not found: ' + selector);
        }

        var userOptions = options || {};

        // Default options
        var defaultOptions = {
            url: '/connector.php',
            downloadUrl: null, // Custom download URL (if null, uses connector)
            token: null,
            lang: (navigator.language || 'en').split('-')[0] || 'en',
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
            cryptExclude: [],         // Exclude MIME patterns from encryption; e.g. ['video/*', 'audio/*']
            showUrlOnProperties: false, // Show URL field in properties modal
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
            // Default banned extensions for uploads or rename (also enforce server-side in connector.php)
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

                // Dangerous config files
                'conf', 'cnf', 'ini', 'cfg', 'config',

                // htaccess and config server files
                'htaccess', 'htpasswd', 'htgroup',

                // Macro virus (Office)
                'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
            ]
        };

        // Merge options while preserving nested features and cloning array options
        this.options = Object.assign({}, defaultOptions, userOptions);
        this.options.features = Object.assign({}, defaultOptions.features, userOptions.features || {});
        this.options.customMenus = Array.isArray(userOptions.customMenus) ? userOptions.customMenus.slice() : [];
        this.options.customContextMenu = Array.isArray(userOptions.customContextMenu) ? userOptions.customContextMenu.slice() : [];
        this.options.cryptExclude = Array.isArray(userOptions.cryptExclude) ? userOptions.cryptExclude.slice() : defaultOptions.cryptExclude.slice();
        this.options.hideExtensions = Array.isArray(userOptions.hideExtensions) ? userOptions.hideExtensions.slice() : [];
        this.options.banExtensions = Array.isArray(userOptions.banExtensions) ? userOptions.banExtensions.slice() : defaultOptions.banExtensions.slice();

        // Keep password in closure-like private reference (never exposed in public options)
        this._encryptionPassword = null;

        // Plugin system support
        this.plugins = [];
        this.autoRefreshInterval = null;

        this.hasWebCrypto = window.MyFileManagerCrypto.hasWebCrypto;
        this.deriveKeyFromPassword = window.MyFileManagerCrypto.deriveKeyFromPassword;
        this.encryptFileData = window.MyFileManagerCrypto.encryptFileData;
        this.decryptFileData = window.MyFileManagerCrypto.decryptFileData;

        this.wakeLock = null;
        this.currentUploadXHR = null;
        this.currentDownloadController = null;
        this._uploadCancelled = false;
        this._searchTimer = null;
        this._searchRequestId = 0;
        this._lastSelectedHash = null;
        this._lastProgressUiUpdate = 0;
        this._boundDocHandlers = {};

        // Internal state
        this.state = {
            currentPath: '',
            currentHash: '',
            selectedFiles: [],
            clipboard: null,
            uploadQueue: [],
            isUploading: false,
            files: [],
            quota: null,
            activeMenu: null,
            history: [],
            historyIndex: -1,
            isSearching: false
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
        this.i18n = (window.MyFileManagerI18n && (window.MyFileManagerI18n[this.options.lang] || window.MyFileManagerI18n['en'])) || {};

        // Initialize
        this.init();
    }

    /**
     * Check if file should be encrypted based on MIME type
     */
    MyFileManager.prototype.shouldEncrypt = function (mime) {
        if (!this.options.cryptFiles || (!this.options.encryptionKey && !this._encryptionPassword)) {
            return false;
        }

        var safeMime = mime || '';

        // Check if MIME is in exclusion list
        for (var i = 0; i < this.options.cryptExclude.length; i++) {
            var pattern = this.options.cryptExclude[i];
            if (!pattern) continue;

            if (pattern.endsWith('/*')) {
                // Wildcard match: "video/*" matches "video/mp4"
                var prefix = pattern.slice(0, -1); // keep trailing "/"
                if (safeMime.startsWith(prefix)) {
                    return false;
                }
            } else if (safeMime === pattern) {
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

        this.open('');

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
        toolbar.innerHTML =
            '<div class="mfm-toolbar-left">' +
            '<button class="mfm-btn" data-action="back" title="' + this.escapeHtml(this.i18n.back || 'Back') + '">' +
            '<span class="mfm-icon-back"></span>' +
            '</button>' +
            '<button class="mfm-btn" data-action="forward" title="' + this.escapeHtml(this.i18n.forward || 'Forward') + '">' +
            '<span class="mfm-icon-forward"></span>' +
            '</button>' +
            '<button class="mfm-btn" data-action="up" title="' + this.escapeHtml(this.i18n.up || 'Up') + '">' +
            '<span class="mfm-icon-up"></span>' +
            '</button>' +
            '<button class="mfm-btn" data-action="refresh" title="' + this.escapeHtml(this.i18n.refresh || 'Refresh') + '">' +
            '<span class="mfm-icon-refresh"></span>' +
            '</button>' +
            '</div>' +
            '<div class="mfm-toolbar-right">' +
            '<input type="text" class="mfm-search" placeholder="' + this.escapeHtml(this.i18n.search || 'Search') + '" ' + (!this.options.features.search ? 'disabled' : '') + '>' +
            '</div>';

        // Trash toolbar (shown only when in trash)
        var trashToolbar = document.createElement('div');
        trashToolbar.className = 'mfm-trash-toolbar';
        trashToolbar.style.display = 'none';
        trashToolbar.innerHTML =
            '<button class="mfm-toolbar-btn" data-action="restore-selected">' +
            '♻️ <span>' + this.escapeHtml(this.i18n.restoreSelected || 'Restore Selected') + '</span>' +
            '</button>' +
            '<button class="mfm-toolbar-btn mfm-btn-danger" data-action="empty-trash">' +
            '🗑️ <span>' + this.escapeHtml(this.i18n.emptyTrash || 'Empty Recycle Bin') + '</span>' +
            '</button>';

        // Address Bar
        var addressbar = document.createElement('div');
        addressbar.className = 'mfm-addressbar';
        addressbar.innerHTML =
            '<span class="mfm-addressbar-label">' + this.escapeHtml(this.i18n.address || 'Address') + ':</span>' +
            '<div class="mfm-breadcrumb"></div>';

        // Content area
        var content = document.createElement('div');
        content.className = 'mfm-content';
        content.innerHTML = '<div class="mfm-files mfm-view-' + this.escapeHtml(this.options.viewMode) + '"></div>';

        // Status bar
        var statusbar = document.createElement('div');
        statusbar.className = 'mfm-statusbar';
        statusbar.innerHTML =
            '<div class="mfm-status-left">' +
            '<span class="mfm-status-items">0 ' + this.escapeHtml(this.i18n.items || 'items') + '</span>' +
            '</div>' +
            '<div class="mfm-status-right">' +
            '<span class="mfm-status-quota"></span>' +
            '</div>';

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

        // Upload Progress Modal
        var uploadModal = document.createElement('div');
        uploadModal.className = 'mfm-upload-modal';
        uploadModal.style.display = 'none';
        uploadModal.innerHTML =
            '<div class="mfm-upload-modal-content">' +
            '<div class="mfm-upload-modal-header">' +
            '<h3 class="mfm-upload-modal-title">' + this.escapeHtml(this.i18n.uploadingFile || 'Uploading File') + '</h3>' +
            '<button class="mfm-upload-modal-close">&times;</button>' +
            '</div>' +
            '<div class="mfm-upload-modal-body">' +
            '<div class="mfm-upload-info">' +
            '<div class="mfm-upload-filename"></div>' +
            '<div class="mfm-progress-container">' +
            '<div class="mfm-progress-bar">' +
            '<div class="mfm-progress-fill" style="width: 0%"></div>' +
            '</div>' +
            '<div class="mfm-progress-text">0%</div>' +
            '</div>' +
            '<div class="mfm-upload-stats">' +
            '<span class="mfm-upload-speed">0 MB/s</span>' +
            '<span class="mfm-upload-time">' + this.escapeHtml(this.i18n.calculating || 'Calculating...') + '</span>' +
            '</div>' +
            '</div>' +
            '<div class="mfm-upload-status" style="margin-top: 15px; display: none;">' +
            '<div class="mfm-upload-status-message"></div>' +
            '</div>' +
            '<div class="mfm-upload-actions" style="margin-top: 15px; text-align: right; display: none;">' +
            '<button class="mfm-btn mfm-upload-close-btn">' + this.escapeHtml(this.i18n.close || 'Close') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>';

        // Download Progress Modal
        var downloadModal = document.createElement('div');
        downloadModal.className = 'mfm-download-modal';
        downloadModal.style.display = 'none';
        downloadModal.innerHTML =
            '<div class="mfm-download-modal-content">' +
            '<div class="mfm-download-modal-header">' +
            '<h3 class="mfm-download-modal-title">' + this.escapeHtml(this.i18n.downloadingFile || 'Downloading File') + '</h3>' +
            '<button class="mfm-download-modal-close">&times;</button>' +
            '</div>' +
            '<div class="mfm-download-modal-body">' +
            '<div class="mfm-download-info">' +
            '<div class="mfm-download-filename"></div>' +
            '<div class="mfm-download-filesize"></div>' +
            '</div>' +
            '<div class="mfm-progress-container">' +
            '<div class="mfm-progress-bar">' +
            '<div class="mfm-progress-fill" style="width: 0%"></div>' +
            '</div>' +
            '<div class="mfm-progress-text">0%</div>' +
            '</div>' +
            '<div class="mfm-download-stats">' +
            '<span class="mfm-download-speed">0 MB/s</span>' +
            '<span class="mfm-download-time">' + this.escapeHtml(this.i18n.calculating || 'Calculating...') + '</span>' +
            '</div>' +
            '<div class="mfm-download-status" style="margin-top: 15px; display: none;">' +
            '<div class="mfm-download-status-message"></div>' +
            '</div>' +
            '<div class="mfm-download-actions" style="margin-top: 15px; text-align: right; display: none;">' +
            '<button class="mfm-btn mfm-download-close-btn">' + this.escapeHtml(this.i18n.close || 'Close') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>';

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

        // Populate menubar
        this.elements.menubar.innerHTML = this.buildMenuBar();

        // Bind download modal close buttons
        var downloadCloseBtn = downloadModal.querySelector('.mfm-download-modal-close');
        var downloadCloseBtnAction = downloadModal.querySelector('.mfm-download-close-btn');

        if (downloadCloseBtn) {
            downloadCloseBtn.addEventListener('click', function () {
                if (self.currentDownloadController) {
                    if (confirm(self.i18n.confirmCancelDownload || 'Download in progress. Cancel download?')) {
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

        // Bind upload modal close buttons
        var uploadCloseBtn = uploadModal.querySelector('.mfm-upload-modal-close');
        var uploadCloseBtnAction = uploadModal.querySelector('.mfm-upload-close-btn');

        if (uploadCloseBtn) {
            uploadCloseBtn.addEventListener('click', function () {
                if (self.currentUploadXHR || self.state.isUploading) {
                    if (confirm(self.i18n.confirmCancelUpload || 'Upload in progress. Cancel upload?')) {
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

    // Request wake lock to prevent screen sleep during active transfers
    MyFileManager.prototype.requestWakeLock = async function () {
        if ('wakeLock' in navigator && document.visibilityState === 'visible') {
            try {
                if (!this.wakeLock) {
                    this.wakeLock = await navigator.wakeLock.request('screen');
                    this._debug('log', 'Wake lock acquired');

                    var self = this;
                    this.wakeLock.addEventListener('release', function () {
                        self._debug('log', 'Wake lock released');
                        self.wakeLock = null;
                    });
                }
            } catch (err) {
                this._debug('warn', 'Wake lock failed:', err.name, err.message);
            }
        }
    };

    // Release wake lock
    MyFileManager.prototype.releaseWakeLock = function () {
        if (this.wakeLock !== null) {
            var lock = this.wakeLock;
            this.wakeLock = null;
            lock.release().catch(function () { });
        }
    };

    // Handle visibility change to reacquire lock only if a transfer is active
    MyFileManager.prototype.handleVisibilityChange = async function () {
        if (document.visibilityState === 'visible' && (this.state.isUploading || this.currentDownloadController)) {
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
                    items: customMenu.items || [],
                    custom: true
                });
            });
        }

        var html = [];
        menus.forEach(function (menu) {
            html.push('<div class="mfm-menu" data-menu="' + self.escapeHtml(menu.id) + '">');
            html.push('<button class="mfm-menu-trigger">' + self.escapeHtml(menu.label) + '</button>');
            html.push('<div class="mfm-menu-dropdown">');

            menu.items.forEach(function (item) {
                if (item.type === 'separator') {
                    html.push('<div class="mfm-menu-separator"></div>');
                } else {
                    var action = item.action || item.id;
                    var tooltip = item.shortcut ? item.label + ' (' + item.shortcut + ')' : item.label;

                    html.push('<div class="mfm-menu-item" data-action="' + self.escapeHtml(action) + '" title="' + self.escapeHtml(tooltip) + '">');
                    if (item.icon) {
                        html.push('<span class="mfm-menu-item-icon">' + self.escapeHtml(item.icon) + '</span>');
                    }
                    html.push('<span>' + self.escapeHtml(item.label) + '</span>');
                    html.push('</div>');
                }
            });

            html.push('</div>');
            html.push('</div>');
        });

        // Add brand logo if configured and URL is safe
        if (this.options.brandLogo && this.isSafeUrl(this.options.brandLogo)) {
            var brandUrl = this.isSafeUrl(this.options.brandLink) ? this.options.brandLink : '#';
            var brandTarget = this.options.brandTarget || '_self';
            var brandAlt = this.options.brandAlt || 'Logo';
            var relAttr = brandTarget === '_blank' ? ' rel="noopener noreferrer"' : '';

            html.push('<div class="mfm-menubar-brand">');
            html.push('<a href="' + self.escapeHtml(brandUrl) + '" class="mfm-brand-link" target="' + self.escapeHtml(brandTarget) + '"' + relAttr + '>');
            html.push('<img src="' + self.escapeHtml(this.options.brandLogo) + '" alt="' + self.escapeHtml(brandAlt) + '" class="mfm-brand-logo">');
            html.push('</a></div>');
        }

        return html.join('');
    };

    /**
     * Bind event listeners
     */
    MyFileManager.prototype.bindEvents = function () {
        var self = this;

        // Menu bar click events (unified via .active class)
        this.elements.menubar.addEventListener('click', function (e) {
            var trigger = e.target.closest('.mfm-menu-trigger');
            var menuItem = e.target.closest('.mfm-menu-item');

            if (trigger) {
                var menu = trigger.closest('.mfm-menu');
                var wasActive = menu.classList.contains('active');

                self.closeAllMenus();

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

        // Breadcrumb navigation via event delegation
        this.elements.breadcrumb.addEventListener('click', function (e) {
            var item = e.target.closest('.mfm-breadcrumb-item');
            if (item) {
                var hash = item.getAttribute('data-hash') || '';
                self.open(hash);
            }
        });

        // Toolbar buttons
        this.elements.toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            if (btn && !btn.disabled) {
                self.handleToolbarAction(btn.getAttribute('data-action'));
            }
        });

        // Search input
        this.elements.search.addEventListener('input', function () {
            self.handleSearch(this.value);
        });

        // File list click
        this.elements.filesContainer.addEventListener('click', function (e) {
            var fileEl = e.target.closest('.mfm-file');

            if (fileEl) {
                var hash = fileEl.getAttribute('data-hash');
                var file = self.getFileByHash(hash);
                if (!file) return;

                if (e.ctrlKey || e.metaKey) {
                    self.toggleFileSelection(fileEl, file);
                    self._lastSelectedHash = file.hash;
                } else if (e.shiftKey && (self._lastSelectedHash || self.state.selectedFiles.length > 0)) {
                    self.selectRange(fileEl);
                } else {
                    self.clearSelection();
                    self.selectFile(fileEl, file);
                    self._lastSelectedHash = file.hash;
                }
            } else {
                self.clearSelection();
                self._lastSelectedHash = null;
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

                if (file && !fileEl.classList.contains('mfm-selected')) {
                    self.clearSelection();
                    self.selectFile(fileEl, file);
                    self._lastSelectedHash = file.hash;
                }
            } else {
                self.clearSelection();
                self._lastSelectedHash = null;
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

            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                self.uploadFiles(e.dataTransfer.files);
            }
        });

        // Modal close
        this.elements.modal.addEventListener('click', function (e) {
            if (e.target === self.elements.modal ||
                e.target.classList.contains('mfm-modal-overlay') ||
                e.target.classList.contains('mfm-modal-close')) {
                self.closeModal();
            }
        });

        // Document-level handlers stored for proper cleanup on destroy()
        this._boundDocHandlers.click = function (e) {
            if (!e.target.closest('.mfm-menubar')) {
                self.closeAllMenus();
            }
            if (!self.elements.contextMenu.contains(e.target)) {
                self.elements.contextMenu.style.display = 'none';
            }
        };

        this._boundDocHandlers.visibilitychange = function () {
            self.handleVisibilityChange();
        };

        this._boundDocHandlers.keydown = function (e) {
            // Ignore shortcuts when typing in input/textarea
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }

            // Ignore when a modal is open
            if (self.elements.modal.style.display === 'block' ||
                self.elements.uploadModal.style.display === 'flex' ||
                self.elements.downloadModal.style.display === 'flex') {
                if (e.key === 'Escape' && self.elements.modal.style.display === 'block') {
                    self.closeModal();
                }
                return;
            }

            var fileElements = self.elements.filesContainer.querySelectorAll('.mfm-file');
            var selectedFile = self.state.selectedFiles.length > 0 ? self.state.selectedFiles[0] : null;
            var currentIndex = -1;

            if (selectedFile) {
                for (var i = 0; i < fileElements.length; i++) {
                    if (fileElements[i].getAttribute('data-hash') === selectedFile.hash) {
                        currentIndex = i;
                        break;
                    }
                }
            }

            // Arrow Down - Select next file in rendered DOM order
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (fileElements.length > 0) {
                    var nextIndex = (currentIndex + 1) % fileElements.length;
                    var nextEl = fileElements[nextIndex];
                    var nextFile = self.getFileByHash(nextEl.getAttribute('data-hash'));
                    if (nextFile) {
                        self.clearSelection();
                        self.selectFile(nextEl, nextFile);
                        self._lastSelectedHash = nextFile.hash;
                        nextEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }
                return;
            }

            // Arrow Up - Select previous file in rendered DOM order
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (fileElements.length > 0) {
                    var prevIndex = currentIndex - 1;
                    if (prevIndex < 0) prevIndex = fileElements.length - 1;
                    var prevEl = fileElements[prevIndex];
                    var prevFile = self.getFileByHash(prevEl.getAttribute('data-hash'));
                    if (prevFile) {
                        self.clearSelection();
                        self.selectFile(prevEl, prevFile);
                        self._lastSelectedHash = prevFile.hash;
                        prevEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }
                return;
            }

            // Enter - Open selected file/folder
            if (e.key === 'Enter') {
                var selectedElement = self.elements.filesContainer.querySelector('.mfm-selected');
                if (selectedElement) {
                    e.preventDefault();
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
                return;
            }

            // Ctrl+C - Copy
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c' && !e.shiftKey) {
                e.preventDefault();
                self.copySelected();
                return;
            }

            // Ctrl+X - Cut
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'x' && !e.shiftKey) {
                e.preventDefault();
                self.cutSelected();
                return;
            }

            // Ctrl+V - Paste
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v' && !e.shiftKey) {
                e.preventDefault();
                self.pasteClipboard();
                return;
            }

            // Ctrl+A - Select all
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a' && !e.shiftKey) {
                e.preventDefault();
                self.selectAll();
                return;
            }

            // Ctrl+Shift+U - Upload
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'u') {
                e.preventDefault();
                self.openUploadDialog();
                return;
            }

            // Ctrl+Shift+D - Download
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'd') {
                if (self.state.selectedFiles.length > 0) {
                    e.preventDefault();
                    self.downloadSelected();
                }
                return;
            }

            // Ctrl+Shift+M - New folder
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'm') {
                e.preventDefault();
                self.createFolder();
                return;
            }

            // Ctrl+Shift+I - Properties/Info
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'i') {
                if (self.state.selectedFiles.length === 1) {
                    e.preventDefault();
                    self.showInfo();
                }
                return;
            }

            // Delete key
            if (e.key === 'Delete') {
                if (self.state.selectedFiles.length > 0) {
                    e.preventDefault();
                    self.deleteSelected();
                }
                return;
            }

            // F2 - Rename
            if (e.key === 'F2') {
                if (self.state.selectedFiles.length === 1) {
                    e.preventDefault();
                    self.renameSelected();
                }
                return;
            }

            // F5 - Refresh
            if (e.key === 'F5') {
                e.preventDefault();
                self.refresh();
                return;
            }

            // First-letter navigation with cycle
            if (e.key.length === 1 && /^[a-zA-Z0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                var files = self.state.files;
                if (files.length === 0) return;

                e.preventDefault();
                var firstLetter = e.key.toLowerCase();
                var navState = self._letterNavState;

                if (navState.lastLetter !== firstLetter) {
                    navState.lastLetter = firstLetter;
                    navState.index = -1;
                }

                var candidates = [];
                fileElements.forEach(function (el) {
                    var f = self.getFileByHash(el.getAttribute('data-hash'));
                    if (f && f.name.toLowerCase().startsWith(firstLetter)) {
                        candidates.push({ el: el, file: f });
                    }
                });

                if (candidates.length === 0) return;

                navState.index = (navState.index + 1) % candidates.length;
                var chosen = candidates[navState.index];

                self.clearSelection();
                self.selectFile(chosen.el, chosen.file);
                self._lastSelectedHash = chosen.file.hash;
                chosen.el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        };

        document.addEventListener('click', this._boundDocHandlers.click);
        document.addEventListener('visibilitychange', this._boundDocHandlers.visibilitychange);
        document.addEventListener('keydown', this._boundDocHandlers.keydown);
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

        if (isCustomAction && typeof this.options.onCustomMenuAction === 'function') {
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
                var password = prompt(this.i18n.enterEncryptionPassword || 'Enter encryption password:');
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
                this.setSortBy('name');
                break;
            case 'sortSize':
                this.setSortBy('size');
                break;
            case 'sortDate':
                this.setSortBy('date');
                break;
            case 'sortType':
                this.setSortBy('type');
                break;
            default:
                var handled = false;
                for (var i = 0; i < this.options.customContextMenu.length; i++) {
                    var item = this.options.customContextMenu[i];
                    if (item.action === action && typeof item.handler === 'function') {
                        var canExecute = true;
                        if (typeof item.condition === 'function') {
                            canExecute = item.condition(this.state.selectedFiles, this);
                        }

                        if (canExecute) {
                            item.handler(this.state.selectedFiles, this);
                        } else {
                            alert(this.i18n.actionNotAllowed || 'This action cannot be performed on the selected file(s)');
                        }
                        handled = true;
                        break;
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
        this.elements.uploadInput.value = '';
        this.elements.uploadInput.click();
    };

    /**
     * Update menu item states (enabled/disabled)
     */
    MyFileManager.prototype.updateMenuStates = function () {
        var self = this;
        var hasSelection = this.state.selectedFiles.length > 0;
        var hasClipboard = this.state.clipboard !== null;
        var menubar = this.elements.menubar;

        // Check if trash folder is selected
        var isTrashSelected = false;
        if (hasSelection) {
            isTrashSelected = this.state.selectedFiles.some(function (file) {
                return self.isTrashItem(file);
            });
        }

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

        // Copy, Cut, Rename - disable if trash selected
        ['copy', 'cut', 'rename'].forEach(function (action) {
            var item = menubar.querySelector('[data-action="' + action + '"]');
            if (item) {
                var disabled = !hasSelection || isTrashSelected || (action === 'rename' && self.state.selectedFiles.length !== 1);
                item.classList.toggle('disabled', disabled);
            }
        });

        // Delete - enable for selection
        var deleteItem = menubar.querySelector('[data-action="delete"]');
        if (deleteItem) {
            deleteItem.classList.toggle('disabled', !hasSelection);
        }

        // Paste - disable if inside trash or trash selected
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
                itemIcon.textContent = self.options.sortBy === sortKey ? '✓' : '\u00A0';
            }
        });

        if (this.options.cryptFiles) {
            var encryptItem = menubar.querySelector('[data-action="setEncryptionKey"]');
            if (encryptItem) {
                var hasKey = !!(this.options.encryptionKey || this._encryptionPassword);
                encryptItem.style.display = hasKey ? 'none' : 'flex';
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
    MyFileManager.prototype.downloadSelected = async function () {
        var self = this;

        if (this.hasFolderSelected()) {
            alert(this.i18n.foldersCannotDownload || 'Folders cannot be downloaded. Please select only files.');
            return;
        }

        var filesToDownload = this.state.selectedFiles.filter(function (file) {
            return file.mime !== 'directory';
        });

        for (var i = 0; i < filesToDownload.length; i++) {
            self._debug('log', 'Downloading file:', filesToDownload[i].name);
            await self.downloadFile(filesToDownload[i]);
        }
    };

    /**
     * Check if Uint8Array starts with magic bytes
     */
    MyFileManager.prototype._hasMagicHeader = function (bytes) {
        if (!bytes || bytes.length < ENC_MAGIC_BYTES.length + 16 + 12) {
            return false;
        }
        for (var i = 0; i < ENC_MAGIC_BYTES.length; i++) {
            if (bytes[i] !== ENC_MAGIC_BYTES[i]) {
                return false;
            }
        }
        return true;
    };

    /**
     * Encrypt bytes using per-file salt if password is available, or fallback to fixed encryptionKey
     */
    MyFileManager.prototype._encryptBytes = async function (plainBytes) {
        if (this._encryptionPassword) {
            var salt = window.MyFileManagerCrypto.generateSalt();
            var derivedKey = await this.deriveKeyFromPassword(this._encryptionPassword, salt);
            var cipherPayload = await this.encryptFileData(plainBytes, derivedKey);

            // Header: [8 bytes magic "MFMENC01"][16 bytes salt][cipherPayload]
            var out = new Uint8Array(ENC_MAGIC_BYTES.length + salt.length + cipherPayload.length);
            out.set(ENC_MAGIC_BYTES, 0);
            out.set(salt, ENC_MAGIC_BYTES.length);
            out.set(cipherPayload, ENC_MAGIC_BYTES.length + salt.length);
            return out;
        }
        return await this.encryptFileData(plainBytes, this.options.encryptionKey);
    };

    /**
     * Decrypt bytes supporting both self-contained MFMENC01 header and legacy raw format
     */
    MyFileManager.prototype._decryptBytes = async function (encryptedBytes) {
        if (this._hasMagicHeader(encryptedBytes)) {
            if (!this._encryptionPassword) {
                throw new Error('Encryption password required to decrypt this file.');
            }
            var saltStart = ENC_MAGIC_BYTES.length;
            var saltEnd = saltStart + 16;
            var salt = encryptedBytes.slice(saltStart, saltEnd);
            var payload = encryptedBytes.slice(saltEnd);
            var derivedKey = await this.deriveKeyFromPassword(this._encryptionPassword, salt);
            return await this.decryptFileData(payload, derivedKey);
        }

        if (!this.options.encryptionKey) {
            throw new Error('Encryption key required to decrypt this file.');
        }
        return await this.decryptFileData(encryptedBytes, this.options.encryptionKey);
    };

    /**
     * Download single file with streaming and progress tracking
     */
    MyFileManager.prototype.downloadFile = async function (file) {
        var self = this;
        self._debug('log', 'downloadFile START for', file.name);
        var wasAborted = false;

        try {
            if (file.mime === 'directory') {
                alert(this.i18n.foldersCannotDownload || 'Folders cannot be downloaded.');
                return;
            }

            if (file.name.endsWith('.encrypted') && (!this.options.cryptFiles || (!this.options.encryptionKey && !this._encryptionPassword))) {
                if (this.options.cryptFiles) {
                    var pwd = prompt(this.i18n.enterEncryptionPassword || 'This file is encrypted. Enter encryption password:');
                    if (pwd) {
                        await this.setEncryptionKey(pwd, true);
                    }
                }
            }

            this.showDownloadProgress(file.name, file.size || 0);
            this.currentDownloadController = new AbortController();
            await this.requestWakeLock();

            var downloadUrl = this.options.downloadUrl || this.options.url;
            var baseUrl = downloadUrl.split('?')[0];

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

            var formData = new FormData();
            formData.append('cmd', 'download');
            formData.append('target', file.hash);

            if (this.options.token) {
                formData.append('token', this.options.token);
            }
            if (token1) {
                formData.append('token1', token1);
            }
            formData.append('token2', token2.toString());

            var fetchOptions = {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                signal: this.currentDownloadController.signal
            };

            if (this.options.token) {
                fetchOptions.headers = {
                    'Authorization': 'Bearer ' + this.options.token
                };
            }

            var response = await fetch(baseUrl, fetchOptions);

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var contentLength = parseInt(response.headers.get('Content-Length') || '0', 10);
            var total = file.size || contentLength || 0;

            var reader = response.body.getReader();
            var chunks = [];
            var receivedLength = 0;
            var lastTime = Date.now();
            var lastReceived = 0;
            var speedSamples = [];
            var maxSamples = 10;

            while (true) {
                var step = await reader.read();
                if (step.done) break;

                chunks.push(step.value);
                receivedLength += step.value.length;

                var percent = total > 0 ? Math.min(100, (receivedLength / total) * 100) : 0;
                var now = Date.now();
                var deltaTime = (now - lastTime) / 1000;
                var deltaReceived = receivedLength - lastReceived;

                if (deltaTime >= 0.1) {
                    var instantSpeed = deltaReceived / deltaTime;
                    if (instantSpeed > 0) {
                        speedSamples.push(instantSpeed);
                        if (speedSamples.length > maxSamples) {
                            speedSamples.shift();
                        }
                    }

                    var avgSpeed = 0;
                    if (speedSamples.length > 0) {
                        avgSpeed = speedSamples.reduce(function (a, b) { return a + b; }, 0) / speedSamples.length;
                    }

                    var timeRemaining = Infinity;
                    if (avgSpeed > 0 && total > 0) {
                        timeRemaining = Math.max(0, (total - receivedLength) / avgSpeed);
                    }

                    self.updateDownloadProgress(percent, avgSpeed, timeRemaining);
                    lastTime = now;
                    lastReceived = receivedLength;
                }
            }

            var finalSpeed = 0;
            if (speedSamples.length > 0) {
                finalSpeed = speedSamples.reduce(function (a, b) { return a + b; }, 0) / speedSamples.length;
            }
            self.updateDownloadProgress(100, finalSpeed, 0);

            var blob = new Blob(chunks, { type: 'application/octet-stream' });
            var finalBlob = blob;
            var finalFilename = file.name;

            if (this.options.cryptFiles && (this.options.encryptionKey || this._encryptionPassword) && file.name.endsWith('.encrypted')) {
                self._debug('log', 'Decrypting file...');
                var arrayBuffer = await blob.arrayBuffer();
                var encryptedData = new Uint8Array(arrayBuffer);
                var decryptedData = await this._decryptBytes(encryptedData);
                finalBlob = new Blob([decryptedData], { type: file.mime || 'application/octet-stream' });
                finalFilename = file.name.slice(0, -10);
            }

            var a = document.createElement('a');
            var objectUrl = URL.createObjectURL(finalBlob);
            a.href = objectUrl;
            a.download = finalFilename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () {
                URL.revokeObjectURL(objectUrl);
            }, 1000);

            setTimeout(function () {
                self.hideDownloadProgress();
            }, 1000);

            self._debug('log', 'Download saved:', finalFilename);

        } catch (error) {
            self._debug('error', 'Download failed:', error);

            if (error.name === 'AbortError') {
                wasAborted = true;
                self.hideDownloadProgress();
            } else if (error.name === 'OperationError' || (error.message && error.message.toLowerCase().indexOf('decrypt') !== -1)) {
                self.showDownloadError('Decryption failed. Wrong password or corrupted file.');
            } else {
                self.showDownloadError(error.message || 'Download failed');
            }
        } finally {
            this.currentDownloadController = null;
            if (!this.state.isUploading) {
                this.releaseWakeLock();
            }
            if (wasAborted) {
                this._debug('log', 'Download aborted by user');
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
                if (file.mime === 'directory') return true;

                var fileName = file.name.toLowerCase();
                for (var i = 0; i < self.options.hideExtensions.length; i++) {
                    var ext = self.options.hideExtensions[i].toLowerCase();
                    if (!ext.startsWith('.')) ext = '.' + ext;
                    if (fileName.endsWith(ext)) {
                        return false;
                    }
                }
                return true;
            });
        }

        var parts = [];

        if (files.length === 0) {
            parts.push('<div class="mfm-empty">' + this.escapeHtml(this.i18n.emptyFolder || 'This folder is empty') + '</div>');
        } else {
            var isListView = this.options.viewMode === 'list';

            files.forEach(function (file) {
                var icon = self.getFileIcon(file);
                var size = file.mime === 'directory' ? '' : self.formatSize(file.size);
                var type = file.mime === 'directory' ? (self.i18n.folder || 'Folder') : self.getFileType(file);
                var date = self.formatDate(file.ts);

                var tooltip = '';
                tooltip += (self.i18n.name || 'Name') + ': ' + file.name + '\u000A';
                tooltip += (self.i18n.type || 'Type') + ': ' + type + '\u000A';
                if (size) tooltip += (self.i18n.size || 'Size') + ': ' + size + '\u000A';
                tooltip += (self.i18n.dateModified || 'Date Modified') + ': ' + date;

                var rawDisplayName = file.name;
                if (rawDisplayName.endsWith('.encrypted')) {
                    rawDisplayName = rawDisplayName.slice(0, -10);
                }
                if (self.isTrashItem(file)) {
                    rawDisplayName = self.i18n.trash || 'Recycle Bin';
                }

                parts.push('<div class="mfm-file" data-hash="' + self.escapeHtml(file.hash) + '" title="' + self.escapeHtml(tooltip) + '">');
                parts.push('<div class="mfm-file-icon ' + self.escapeHtml(icon) + '"></div>');
                parts.push('<span class="mfm-file-name">' + self.escapeHtml(rawDisplayName) + '</span>');

                if (isListView) {
                    parts.push('<div class="mfm-file-size">' + self.escapeHtml(size) + '</div>');
                    parts.push('<div class="mfm-file-type">' + self.escapeHtml(type) + '</div>');
                    parts.push('<div class="mfm-file-date">' + self.escapeHtml(date) + '</div>');
                }

                parts.push('</div>');
            });
        }

        this.elements.filesContainer.innerHTML = parts.join('');
        this.updateStatusBar();
    };

    /**
     * Helper to check if a file item represents the trash folder
     */
    MyFileManager.prototype.isTrashItem = function (file) {
        if (!file) return false;
        var cleanTrash = this.options.trashPath.replace(/^\/+|\/+$/g, '');
        return file.name === cleanTrash ||
            file.name === '.trash' ||
            file.name === '.' + cleanTrash.replace(/^\./, '') ||
            file.path === cleanTrash ||
            file.path === '/' + cleanTrash;
    };

    /**
     * Filter hidden files/folders starting with dot (except trash folder)
     */
    MyFileManager.prototype.filterHiddenFolders = function (files) {
        var self = this;
        if (!Array.isArray(files)) return [];

        if (self.options.showHidden) {
            return files;
        }

        return files.filter(function (file) {
            if (file.name && file.name.charAt(0) === '.') {
                if (self.isTrashItem(file)) {
                    return true;
                }
                return false;
            }
            return true;
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
     * Get file/folder icon class
     */
    MyFileManager.prototype.getFileIcon = function (file) {
        if (file.mime === 'directory') {
            if (this.isTrashItem(file)) {
                var trashHasFiles = file.size > 0 || (file.dirs && file.dirs > 0);
                return trashHasFiles ? 'icon-trash-full' : 'icon-trash-empty';
            }
            return 'icon-folder';
        }

        var fileName = file.name.endsWith('.encrypted') ?
            file.name.slice(0, -10) : file.name;
        var ext = fileName.split('.').pop().toLowerCase();

        if (file.mime) {
            if (file.mime.startsWith('image/')) return 'icon-image';
            if (file.mime.startsWith('video/')) return 'icon-video';
            if (file.mime.startsWith('audio/')) return 'icon-audio';
            if (file.mime.startsWith('text/')) return 'icon-text';
        }

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

            default: return 'icon-file';
        }
    };

    /**
     * Check if current path is trash folder
     */
    MyFileManager.prototype.isInTrash = function () {
        var currentPath = (this.state.currentPath || '').replace(/^\/+|\/+$/g, '');
        var trashPath = (this.options.trashPath || '.trash').replace(/^\/+|\/+$/g, '');

        if (!currentPath) return false;
        if (currentPath === trashPath) return true;
        if (currentPath.indexOf(trashPath + '/') === 0) return true;

        return false;
    };

    /**
     * Check if any folder (directory) is in current selection
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

        var cleanName = file.name.endsWith('.encrypted') ? file.name.slice(0, -10) : file.name;
        var parts = cleanName.split('.');
        var ext = parts.length > 1 ? parts.pop().toUpperCase() : '';

        if (file.mime && file.mime.startsWith('image/')) {
            return (ext ? ext + ' ' : '') + this.i18n.image;
        }
        if (file.mime && file.mime.startsWith('video/')) {
            return (ext ? ext + ' ' : '') + this.i18n.video;
        }
        if (file.mime && file.mime.startsWith('audio/')) {
            return (ext ? ext + ' ' : '') + this.i18n.audio;
        }
        if (file.mime && file.mime.startsWith('text/')) {
            return (ext ? ext + ' ' : '') + this.i18n.textFile;
        }

        return (ext ? ext + ' ' : '') + this.i18n.file;
    };

    /**
     * Show context menu
     */
    MyFileManager.prototype.showContextMenu = function (x, y) {
        var self = this;
        var hasSelection = this.state.selectedFiles.length > 0;
        var hasClipboard = this.state.clipboard !== null;
        var html = [];

        if (hasSelection) {
            var isTrashSelected = this.state.selectedFiles.some(function (file) {
                return self.isTrashItem(file);
            });

            var hasFolderSelected = this.hasFolderSelected();

            if (this.options.features.download && !hasFolderSelected) {
                html.push('<div class="mfm-context-menu-item" data-action="download">');
                html.push(this.escapeHtml(this.i18n.download));
                html.push('</div>');
                html.push('<div class="mfm-context-menu-separator"></div>');
            }

            if (this.options.features.copy && !isTrashSelected) {
                html.push('<div class="mfm-context-menu-item" data-action="copy">');
                html.push(this.escapeHtml(this.i18n.copy));
                html.push('</div>');
            }

            if (this.options.features.cut && !isTrashSelected) {
                html.push('<div class="mfm-context-menu-item" data-action="cut">');
                html.push(this.escapeHtml(this.i18n.cut));
                html.push('</div>');
            }

            if (this.state.selectedFiles.length === 1 && this.options.features.rename && !isTrashSelected) {
                html.push('<div class="mfm-context-menu-separator"></div>');
                html.push('<div class="mfm-context-menu-item" data-action="rename">');
                html.push(this.escapeHtml(this.i18n.rename));
                html.push('</div>');
            }

            var file = this.state.selectedFiles.length === 1 ? this.state.selectedFiles[0] : null;
            var inTrashFolder = this.isInTrash();
            var isTrashFolderIcon = file && this.isTrashItem(file);

            if (this.options.features.delete) {
                html.push('<div class="mfm-context-menu-separator"></div>');

                if (isTrashFolderIcon && !inTrashFolder) {
                    html.push('<div class="mfm-context-menu-item" data-action="delete">');
                    html.push(this.escapeHtml(this.i18n.emptyTrash));
                    html.push('</div>');
                } else {
                    html.push('<div class="mfm-context-menu-item" data-action="delete">');
                    html.push(this.escapeHtml(this.i18n.delete));
                    html.push('</div>');
                }
            }

            if (this.state.selectedFiles.length === 1 && this.options.features.info) {
                html.push('<div class="mfm-context-menu-separator"></div>');
                html.push('<div class="mfm-context-menu-item" data-action="info">');
                html.push(this.escapeHtml(this.i18n.info));
                html.push('</div>');
            }

            if (this.options.customContextMenu.length > 0) {
                var addedPluginSeparator = false;

                this.options.customContextMenu.forEach(function (item) {
                    var shouldShow = true;
                    if (typeof item.condition === 'function') {
                        shouldShow = item.condition(self.state.selectedFiles, self);
                    }

                    if (shouldShow) {
                        if (!addedPluginSeparator) {
                            html.push('<div class="mfm-context-menu-separator"></div>');
                            addedPluginSeparator = true;
                        }

                        html.push('<div class="mfm-context-menu-item" data-action="' + self.escapeHtml(item.action) + '">');
                        html.push(self.escapeHtml(item.label));
                        html.push('</div>');
                    }
                });
            }

        } else {
            var inTrash = this.isInTrash();

            if (this.options.features.upload && !inTrash) {
                html.push('<div class="mfm-context-menu-item" data-action="upload">');
                html.push(this.escapeHtml(this.i18n.upload));
                html.push('</div>');
            }

            if (this.options.features.mkdir && !inTrash) {
                html.push('<div class="mfm-context-menu-item" data-action="mkdir">');
                html.push(this.escapeHtml(this.i18n.mkdir));
                html.push('</div>');
            }

            if (hasClipboard && this.options.features.paste && !inTrash) {
                html.push('<div class="mfm-context-menu-separator"></div>');
                html.push('<div class="mfm-context-menu-item" data-action="paste">');
                html.push(this.escapeHtml(this.i18n.paste));
                html.push('</div>');
            }

            html.push('<div class="mfm-context-menu-separator"></div>');
            html.push('<div class="mfm-context-menu-item" data-action="refresh">');
            html.push(this.escapeHtml(this.i18n.refresh));
            html.push('</div>');
        }

        this.elements.contextMenu.innerHTML = html.join('');
        this.elements.contextMenu.style.display = 'block';
        this.elements.contextMenu.style.left = x + 'px';
        this.elements.contextMenu.style.top = y + 'px';

        // Adjust position if out of viewport
        var rect = this.elements.contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            this.elements.contextMenu.style.left = Math.max(0, x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            this.elements.contextMenu.style.top = Math.max(0, y - rect.height) + 'px';
        }
    };

    /**
     * Handle context menu action
     */
    MyFileManager.prototype.handleContextMenuAction = function (action) {
        this.elements.contextMenu.style.display = 'none';

        for (var i = 0; i < this.options.customContextMenu.length; i++) {
            var item = this.options.customContextMenu[i];
            if (item.action === action && typeof item.handler === 'function') {
                var canExecute = true;
                if (typeof item.condition === 'function') {
                    canExecute = item.condition(this.state.selectedFiles, this);
                }

                if (canExecute) {
                    item.handler(this.state.selectedFiles, this);
                    return;
                }
            }
        }

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
                this.openUploadDialog();
                break;
            case 'mkdir':
                this.createFolder();
                break;
            case 'refresh':
                this.refresh();
                break;
            default:
                var handled = false;
                if (action && action.startsWith('custom-')) {
                    for (var j = 0; j < this.options.customContextMenu.length; j++) {
                        if (this.options.customContextMenu[j].action === action) {
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
     * Update breadcrumb navigation (clicks handled by event delegation in bindEvents)
     */
    MyFileManager.prototype.updateBreadcrumb = function () {
        var self = this;
        var html = [];

        html.push('<div class="mfm-breadcrumb-item" data-hash="">');
        html.push(this.escapeHtml(this.options.homeLabel || this.i18n.home || 'Home'));
        html.push('</div>');

        var decodedPath = this.state.currentPath || '';
        var parts = decodedPath ? decodedPath.split('/').filter(Boolean) : [];
        var cleanTrash = (this.options.trashPath || '.trash').replace(/^\/+|\/+$/g, '');

        var pathSoFar = '';
        parts.forEach(function (folderName, index) {
            pathSoFar += (index > 0 ? '/' : '') + folderName;
            var hashForNav = utf8ToBase64(pathSoFar);

            html.push('<span class="mfm-breadcrumb-sep">›</span>');
            html.push('<div class="mfm-breadcrumb-item" data-hash="' + self.escapeHtml(hashForNav) + '">');

            var displayName = folderName;
            if (folderName === cleanTrash || folderName === '.trash' || folderName === 'trash') {
                displayName = self.i18n.trash || folderName;
            }

            html.push(self.escapeHtml(displayName));
            html.push('</div>');
        });

        this.elements.breadcrumb.innerHTML = html.join('');
    };

    /**
     * Update status bar with file count and selection info
     */
    MyFileManager.prototype.updateStatusBar = function () {
        var itemCount = this.state.files.length;
        var selectedCount = this.state.selectedFiles.length;
        var itemText = itemCount === 1 ? this.i18n.item : this.i18n.items;

        var leftText = itemCount + ' ' + itemText;

        if (selectedCount > 0) {
            var selectedText = selectedCount === 1 ? this.i18n.selected : this.i18n.selectedPlural;
            leftText += ': ' + selectedCount + ' ' + selectedText;
        }

        this.container.querySelector('.mfm-status-items').textContent = leftText;

        if (this.state.quota) {
            var usedText = this.formatSize(this.state.quota.used);
            var totalText = this.formatSize(this.state.quota.total);
            var freeText = this.formatSize(this.state.quota.free);
            var maxUploadText = this.formatSizeBinary(this.state.quota.maxUpload);

            var quotaHtml = '';
            quotaHtml += '<span title="' + this.escapeHtml(this.i18n.spaceUsed) + '">' + this.escapeHtml(this.i18n.used + ' ' + usedText) + '</span>';
            quotaHtml += '<span title="' + this.escapeHtml(this.i18n.spaceFree) + '">' + this.escapeHtml(this.i18n.free + ' ' + freeText) + '</span>';
            quotaHtml += '<span title="' + this.escapeHtml(this.i18n.spaceTotal) + '">' + this.escapeHtml(this.i18n.total + ' ' + totalText) + '</span>';
            quotaHtml += '<span title="' + this.escapeHtml(this.i18n.maxUploadSize) + '">' + this.escapeHtml(this.i18n.maxUpload + ' ' + maxUploadText) + '</span>';

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
            if (file) {
                self.selectFile(el, file);
            }
        });
    };

    /**
     * Select range of files (Shift+Click)
     */
    MyFileManager.prototype.selectRange = function (targetEl) {
        var fileElements = Array.prototype.slice.call(
            this.elements.filesContainer.querySelectorAll('.mfm-file')
        );
        if (fileElements.length === 0) return;

        var anchorHash = this._lastSelectedHash || (this.state.selectedFiles[0] && this.state.selectedFiles[0].hash);
        var startIdx = fileElements.findIndex(function (el) {
            return el.getAttribute('data-hash') === anchorHash;
        });
        var endIdx = fileElements.indexOf(targetEl);

        if (startIdx === -1 || endIdx === -1) {
            var singleFile = this.getFileByHash(targetEl.getAttribute('data-hash'));
            if (singleFile) {
                this.clearSelection();
                this.selectFile(targetEl, singleFile);
            }
            return;
        }

        var minIdx = Math.min(startIdx, endIdx);
        var maxIdx = Math.max(startIdx, endIdx);

        this.clearSelection();
        for (var i = minIdx; i <= maxIdx; i++) {
            var el = fileElements[i];
            var file = this.getFileByHash(el.getAttribute('data-hash'));
            if (file) {
                this.selectFile(el, file);
            }
        }
    };

    /**
     * Format file size (Decimal - base 10)
     */
    MyFileManager.prototype.formatSize = function (bytes) {
        if (!bytes || bytes === 0) return '0 Bytes';

        var k = 1000;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
     * Format file size in binary (for max upload from PHP)
     */
    MyFileManager.prototype.formatSizeBinary = function (bytes) {
        if (!bytes || bytes === 0) return '0 Bytes';

        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
        var i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    /**
     * Format date
     */
    MyFileManager.prototype.formatDate = function (timestamp) {
        if (!timestamp) return '';
        var date = new Date(timestamp * 1000);
        var now = new Date();
        var diff = now - date;

        if (diff >= 0 && diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        return date.toLocaleDateString();
    };

    /**
     * Escape HTML
     */
    MyFileManager.prototype.escapeHtml = function (text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };

    /**
     * Validate URL safety (allows relative paths starting with single '/' and http/https)
     */
    MyFileManager.prototype.isSafeUrl = function (url) {
        if (!url || typeof url !== 'string') return false;
        var trimmed = url.trim();
        if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
            return true;
        }
        try {
            var parsed = new URL(trimmed, window.location.origin);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (e) {
            return false;
        }
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
        if (!element || !file) return;
        if (!element.classList.contains('mfm-selected')) {
            element.classList.add('mfm-selected');
            this.state.selectedFiles.push(file);
            this.trigger('onFileSelect', file);
            this.updateMenuStates();
            this.updateStatusBar();
        }
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
            this.updateMenuStates();
            this.updateStatusBar();
        } else {
            this.selectFile(element, file);
        }
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

        if (addToHistory === undefined) {
            addToHistory = true;
        }

        // Clear active search state on navigation
        if (this.elements && this.elements.search && this.elements.search.value) {
            this.elements.search.value = '';
        }
        this.state.isSearching = false;

        this.request({
            cmd: 'open',
            target: target || ''
        }, function (response) {
            self.state.currentHash = response.cwd.hash || '';

            if (response.cwd.hash) {
                try {
                    self.state.currentPath = base64ToUtf8(response.cwd.hash);
                } catch (e) {
                    self.state.currentPath = '';
                }
            } else {
                self.state.currentPath = '';
            }

            if (addToHistory) {
                self.state.history = self.state.history.slice(0, self.state.historyIndex + 1);
                self.state.history.push({
                    hash: self.state.currentHash,
                    path: self.state.currentPath
                });
                self.state.historyIndex = self.state.history.length - 1;
            }

            self.state.files = self.filterHiddenFolders(response.files) || [];
            self.state.quota = response.quota;

            self.updateBreadcrumb();
            self.renderFiles();
            self.clearSelection();
            self._lastSelectedHash = null;
            self.updateNavigationButtons();
            self.updateTrashToolbar();
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
            this.open(location.hash, false);
        }
    };

    /**
     * Navigate forward in history
     */
    MyFileManager.prototype.navigateForward = function () {
        if (this.state.historyIndex < this.state.history.length - 1) {
            this.state.historyIndex++;
            var location = this.state.history[this.state.historyIndex];
            this.open(location.hash, false);
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
        var self = this;
        var selectedHashes = this.state.selectedFiles.map(function (file) {
            return file.hash;
        });

        this.state.isSearching = false;
        if (this.elements && this.elements.search && this.elements.search.value) {
            this.elements.search.value = '';
        }

        this.request({
            cmd: 'open',
            target: this.state.currentHash || ''
        }, function (response) {
            self.state.currentHash = response.cwd.hash || '';
            if (response.cwd.hash) {
                try {
                    self.state.currentPath = base64ToUtf8(response.cwd.hash);
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
            self.clearSelection();

            if (selectedHashes.length > 0) {
                selectedHashes.forEach(function (hash) {
                    var fileEl = self.elements.filesContainer.querySelector('[data-hash="' + CSS.escape(hash) + '"]');
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
        if (!this.state.currentPath || this.state.currentPath === '') {
            return;
        }

        var parts = this.state.currentPath.split('/').filter(Boolean);
        if (parts.length === 0) {
            return;
        }

        parts.pop();
        var parentPath = parts.join('/');
        var parentHash = parentPath ? utf8ToBase64(parentPath) : '';
        this.open(parentHash);
    };

    /**
     * Open file or folder
     */
    MyFileManager.prototype.openFile = function (file) {
        if (!file) {
            return;
        }

        if (file.mime === 'directory') {
            this.open(file.hash);
            return;
        }

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
    MyFileManager.prototype.setEncryptionKey = async function (password, silent) {
        var self = this;

        if (!this.options.cryptFiles) {
            if (!silent) alert('Encryption disabled');
            return;
        }

        try {
            var salt = window.MyFileManagerCrypto.generateSalt();
            this._encryptionPassword = password;
            this.options.encryptionKey = await this.deriveKeyFromPassword(password, salt);
            this.options.encryptionSalt = salt;

            self._debug('log', 'Encryption key set successfully');
            this.updateMenuStates();

            if (!silent) {
                alert(this.i18n.encryptionEnabled || 'Encryption enabled! Files will be automatically encrypted/decrypted.');
            }
        } catch (error) {
            this._encryptionPassword = null;
            alert('Failed to set encryption key: ' + error.message);
        }
    };

    /**
     * Copy selected files
     */
    MyFileManager.prototype.copySelected = function () {
        if (this.state.selectedFiles.length === 0) return;

        var self = this;
        var validFiles = this.state.selectedFiles.filter(function (f) {
            return !self.isTrashItem(f);
        });
        if (validFiles.length === 0) return;

        this.state.clipboard = {
            operation: 'copy',
            files: validFiles.slice()
        };

        this.updateMenuStates();
        this._debug('log', 'Copied ' + validFiles.length + ' files');
    };

    /**
     * Cut selected files
     */
    MyFileManager.prototype.cutSelected = function () {
        if (this.state.selectedFiles.length === 0) return;

        var self = this;
        var validFiles = this.state.selectedFiles.filter(function (f) {
            return !self.isTrashItem(f);
        });
        if (validFiles.length === 0) return;

        this.state.clipboard = {
            operation: 'cut',
            files: validFiles.slice()
        };

        this.updateMenuStates();
        this._debug('log', 'Cut ' + validFiles.length + ' files');
    };

    /**
     * Paste clipboard
     */
    MyFileManager.prototype.pasteClipboard = function () {
        if (!this.state.clipboard || this.isInTrash()) return;

        var self = this;
        var operation = this.state.clipboard.operation;
        var targets = this.state.clipboard.files.map(function (f) { return f.hash; });
        var cmd = operation === 'copy' ? 'copy' : 'cut';

        this.request({
            cmd: cmd,
            targets: targets,
            dst: this.state.currentHash
        }, function () {
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
    MyFileManager.prototype.deleteSelected = function (skipConfirm) {
        if (this.state.selectedFiles.length === 0) return;

        var self = this;
        var message = this.i18n.confirmDelete || 'Are you sure you want to delete the selected items?';

        if (skipConfirm === true || confirm(message)) {
            var targets = this.state.selectedFiles.map(function (f) { return f.hash; });

            var requestData = {
                cmd: 'delete',
                targets: targets
            };

            if (this.options.token) {
                requestData.token = this.options.token;
            }

            this._debug('log', 'DELETE REQUEST:', requestData);

            this.request(requestData, function (response) {
                self._debug('log', 'DELETE RESPONSE:', response);
                self.refresh();
                self.updateTrashIcon();
            });
        }
    };

    /**
     * Update trash icon by checking actual content
     */
    MyFileManager.prototype.updateTrashIcon = function () {
        var self = this;

        var trashFolder = this.state.files.find(function (f) {
            return self.isTrashItem(f);
        });

        if (!trashFolder) return;

        this.request({
            cmd: 'open',
            target: trashFolder.hash
        }, function (response) {
            var hasContent = response.files && response.files.length > 0;
            var trashElement = self.elements.filesContainer.querySelector('[data-hash="' + CSS.escape(trashFolder.hash) + '"]');
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
        if (this.isTrashItem(file)) return;

        var newName = prompt(this.i18n.enterNewName, file.name);

        if (newName && newName.trim() !== '' && newName !== file.name) {
            newName = newName.trim();

            if (this.isBannedExtension(newName)) {
                alert((this.i18n.dangerousExtension || 'Dangerous extension detected! Cannot rename to:') + ' ' + newName);
                return;
            }

            this.request({
                cmd: 'rename',
                target: file.hash,
                name: newName
            }, function () {
                self.refresh();
            });
        }
    };

    /**
     * Security: Check if filename has banned extension (client-side guard; enforce on server too)
     */
    MyFileManager.prototype.isBannedExtension = function (filename) {
        if (!filename) return false;
        var cleanName = filename.trim().toLowerCase();
        var parts = cleanName.split('.');
        if (parts.length < 2) return false;
        var ext = parts.pop();
        return this.options.banExtensions.indexOf(ext) !== -1;
    };

    /**
     * Create new folder
     */
    MyFileManager.prototype.createFolder = function () {
        if (this.isInTrash()) return;

        var self = this;
        var name = prompt(this.i18n.enterFolderName, this.i18n.newFolder);

        if (name) {
            name = name.trim();
            if (!name) return;

            if (name.charAt(0) === '.') {
                alert('Folder names cannot start with a dot (.)');
                return;
            }

            this.request({
                cmd: 'mkdir',
                target: this.state.currentHash,
                name: name
            }, function () {
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
            html += '<p><strong>' + self.escapeHtml(self.i18n.properties) + '</strong></p>';
            html += '<p><strong>' + self.escapeHtml(response.name) + '</strong></p>';
            html += '<p>' + self.escapeHtml(self.i18n.type) + ': ' + self.escapeHtml(response.mime === 'directory' ? self.i18n.folder : response.mime) + '</p>';

            if (response.mime !== 'directory') {
                html += '<p>' + self.escapeHtml(self.i18n.size) + ': ' + self.escapeHtml(self.formatSize(response.size)) + '</p>';
            }

            html += '<p>' + self.escapeHtml(self.i18n.dateModified) + ': ' + self.escapeHtml(self.formatDate(response.ts)) + '</p>';

            // Show URL only if option is enabled and URL protocol is safe
            if (self.options.showUrlOnProperties && response.url && self.isSafeUrl(response.url)) {
                var safeUrl = self.escapeHtml(response.url);
                html += '<p>URL: <a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">' + safeUrl + '</a></p>';
            }

            if (response.dim) {
                html += '<p>' + self.escapeHtml(self.i18n.size) + ': ' + self.escapeHtml(response.dim) + '</p>';
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
        var progressContainer = modal.querySelector('.mfm-progress-container');

        if (filenameEl) {
            filenameEl.textContent = filename;
        }

        if (filesizeEl) {
            filesizeEl.textContent = (this.i18n.size || 'Size') + ': ' + this.formatSize(filesize || 0);
        }

        if (progressContainer) {
            progressContainer.style.display = 'block';
        }
        if (statusDiv) {
            statusDiv.style.display = 'none';
        }
        if (actionsDiv) {
            actionsDiv.style.display = 'none';
        }

        modal.style.display = 'flex';
        this.updateDownloadProgress(0, 0, Infinity);
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

        var clamped = Math.min(100, Math.max(0, percent || 0));

        if (progressFill) {
            progressFill.style.width = clamped + '%';
        }

        if (progressText) {
            progressText.textContent = Math.round(clamped) + '%';
        }

        if (speedEl) {
            speedEl.textContent = this.formatSize(speed || 0) + '/s';
        }

        if (timeEl) {
            if (timeRemaining !== undefined && timeRemaining < Infinity && timeRemaining >= 0 && speed > 0) {
                var minutes = Math.floor(timeRemaining / 60);
                var seconds = Math.floor(timeRemaining % 60);
                timeEl.textContent = (minutes > 0 ? minutes + 'm ' : '') + seconds + 's remaining';
            } else if (clamped >= 100) {
                timeEl.textContent = '0s';
            } else {
                timeEl.textContent = this.i18n.calculating || 'Calculating...';
            }
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
     * Enqueue files for sequential upload
     */
    MyFileManager.prototype.uploadFiles = function (files) {
        if (!this.options.features.upload || this.isInTrash()) {
            return;
        }

        var list = Array.from(files || []);
        if (list.length === 0) return;

        for (var i = 0; i < list.length; i++) {
            this.state.uploadQueue.push(list[i]);
        }

        if (!this.state.isUploading) {
            this.processUploadQueue();
        }
    };

    /**
     * Process upload queue sequentially
     */
    MyFileManager.prototype.processUploadQueue = async function () {
        if (this.state.isUploading) return;

        this.state.isUploading = true;
        this._uploadCancelled = false;
        await this.requestWakeLock();

        var totalQueued = this.state.uploadQueue.length;
        var processed = 0;
        var hadError = false;
        var lastUploadedName = null;

        try {
            while (this.state.uploadQueue.length > 0 && !this._uploadCancelled) {
                var nextFile = this.state.uploadQueue.shift();
                processed++;
                var labelPrefix = totalQueued > 1 ? ('(' + processed + '/' + totalQueued + ') ') : '';

                var uploadedName = await this.uploadFile(nextFile, labelPrefix);
                if (uploadedName) {
                    lastUploadedName = uploadedName;
                } else if (this._lastUploadErrored) {
                    hadError = true;
                }
            }
        } finally {
            this.state.isUploading = false;
            this.currentUploadXHR = null;
            if (!this.currentDownloadController) {
                this.releaseWakeLock();
            }

            if (!hadError && !this._uploadCancelled) {
                this.hideUploadProgress();
                this.refresh();
                if (lastUploadedName) {
                    var self = this;
                    setTimeout(function () {
                        self.selectUploadedFile(lastUploadedName);
                    }, 500);
                }
            }
        }
    };

    /**
     * Sanitize filename preserving extension and avoiding empty/hidden names
     */
    MyFileManager.prototype._sanitizeFilename = function (originalName) {
        var lastDotIndex = originalName.lastIndexOf('.');
        var fileName = originalName;
        var fileExt = '';

        if (lastDotIndex > 0) {
            fileExt = originalName.substring(lastDotIndex);
            fileName = originalName.substring(0, lastDotIndex);
        }

        // Normalize Unicode accents (e.g. "caffè" -> "caffe") before replacing special chars
        var normalized = typeof fileName.normalize === 'function'
            ? fileName.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            : fileName;

        var sanitizedName = normalized
            .replace(/['"]/g, '')
            .replace(/[^\w\s.-]/g, '_')
            .replace(/\.+/g, '_')
            .replace(/\s+/g, '_')
            .replace(/_{2,}/g, '_')
            .replace(/^_+|_+$/g, '');

        // Fallback if filename consists only of stripped characters (prevent ".ext" hidden file)
        if (!sanitizedName) {
            sanitizedName = 'file_' + Date.now();
        }

        return sanitizedName + fileExt;
    };

    /**
     * Chunked file upload function - Compatible with original server
     * @param {File} file - File object to upload
     * @param {string} [labelPrefix] - Optional queue counter prefix
     * @return {Promise<string|null>} Uploaded filename or null on skip/error
     */
    MyFileManager.prototype.uploadFile = async function (file, labelPrefix) {
        var self = this;
        this._lastUploadErrored = false;

        // Reset upload stats for new upload
        this.uploadStats = {
            startTime: 0,
            totalBytes: 0,
            uploadedBytes: 0,
            speedSamples: [],
            avgSpeed: 0,
            maxSamples: 10
        };

        var finalName = this._sanitizeFilename(file.name);

        var sanitizedFile = new File([file], finalName, {
            type: file.type,
            lastModified: file.lastModified
        });

        self._debug('log', 'Original name:', file.name, '-> Sanitized:', finalName, 'MIME:', sanitizedFile.type);

        if (this.isBannedExtension(sanitizedFile.name)) {
            alert('Upload blocked! Dangerous extension: ' + sanitizedFile.name);
            return null;
        }

        if (sanitizedFile.size > this.options.maxFileSize) {
            alert('File too large: ' + sanitizedFile.name);
            return null;
        }

        this.showUploadProgress((labelPrefix || '') + sanitizedFile.name);
        this.trigger('onUploadStart', { file: sanitizedFile });
        self.currentUploadXHR = null;

        try {
            var uploadFileObj = sanitizedFile;

            // Encrypt file if encryption enabled for this file type
            if (this.shouldEncrypt(uploadFileObj.type)) {
                var arrayBuffer = await uploadFileObj.arrayBuffer();
                var encryptedBuffer = await this._encryptBytes(new Uint8Array(arrayBuffer));
                uploadFileObj = new File([encryptedBuffer], uploadFileObj.name + '.encrypted', {
                    type: 'application/octet-stream',
                    lastModified: Date.now()
                });
                self._debug('log', 'Encrypted file before upload:', uploadFileObj.name, uploadFileObj.size);
            }

            // Use configured chunkSize (fallback to 1MB if invalid)
            var chunkSize = (typeof this.options.chunkSize === 'number' && this.options.chunkSize > 0)
                ? this.options.chunkSize
                : 1048576;
            var chunks = Math.max(1, Math.ceil(uploadFileObj.size / chunkSize));
            var currentChunk = 0;
            var uploadId = uploadFileObj.name.replace(/[^a-z0-9]/gi, '') + '_' + Date.now();
            var lastTime = Date.now();
            var lastLoaded = 0;

            var uploadChunk = function () {
                if (self._uploadCancelled) {
                    return Promise.reject(new Error('Upload cancelled'));
                }

                var start = currentChunk * chunkSize;
                var end = Math.min(start + chunkSize, uploadFileObj.size);
                var chunk = uploadFileObj.slice(start, end);

                var formData = new FormData();
                formData.append('cmd', 'upload');
                formData.append('target', self.state.currentHash || utf8ToBase64(self.state.currentPath || ''));
                formData.append('upload', chunk, uploadFileObj.name);
                formData.append('part', currentChunk);
                formData.append('parts', chunks);
                formData.append('id', uploadId);

                if (self.options.token) {
                    formData.append('token', self.options.token);
                }

                var xhr = new XMLHttpRequest();
                self.currentUploadXHR = xhr;

                // Track upload progress with throttled UI updates & smoothed speed
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable && uploadFileObj.size > 0) {
                        var totalLoaded = Math.min(uploadFileObj.size, (currentChunk * chunkSize) + e.loaded);
                        var percent = Math.min(100, Math.round((totalLoaded / uploadFileObj.size) * 100));
                        var now = Date.now();

                        if (self.uploadStats.startTime === 0) {
                            self.uploadStats.startTime = now;
                            self.uploadStats.speedSamples = [];
                        }

                        var deltaTime = (now - lastTime) / 1000;
                        var deltaLoaded = totalLoaded - lastLoaded;

                        if (deltaTime >= 0.1) {
                            var instantSpeed = deltaLoaded > 0 ? (deltaLoaded / deltaTime) : 0;
                            if (instantSpeed > 0) {
                                self.uploadStats.speedSamples.push(instantSpeed);
                                if (self.uploadStats.speedSamples.length > self.uploadStats.maxSamples) {
                                    self.uploadStats.speedSamples.shift();
                                }
                            }

                            var avgSpeed = 0;
                            if (self.uploadStats.speedSamples.length > 0) {
                                avgSpeed = self.uploadStats.speedSamples.reduce(function (a, b) { return a + b; }, 0) /
                                    self.uploadStats.speedSamples.length;
                            }

                            var timeRemaining = Infinity;
                            if (avgSpeed > 0) {
                                timeRemaining = Math.max(0, (uploadFileObj.size - totalLoaded) / avgSpeed);
                            }

                            lastTime = now;
                            lastLoaded = totalLoaded;

                            self.updateUploadProgress(percent, avgSpeed, timeRemaining);
                            self.trigger('onUploadProgress', {
                                file: uploadFileObj,
                                percent: percent,
                                loaded: totalLoaded,
                                total: uploadFileObj.size
                            });
                        }
                    }
                });

                return new Promise(function (resolve, reject) {
                    xhr.addEventListener('load', function () {
                        self._debug('log', 'Chunk ' + currentChunk + '/' + (chunks - 1) + ': ' + xhr.status);

                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);

                                if (response.error) {
                                    self._lastUploadErrored = true;
                                    self.showUploadError(response.error);
                                    self.trigger('onUploadError', { file: uploadFileObj, error: response.error });
                                    reject(new Error(response.error));
                                    return;
                                }

                                if (currentChunk === chunks - 1) {
                                    self.updateUploadProgress(100, 0, 0);
                                    self.trigger('onUploadComplete', { file: uploadFileObj, response: response });
                                    resolve(uploadFileObj.name);
                                } else {
                                    currentChunk++;
                                    uploadChunk().then(resolve).catch(reject);
                                }
                            } catch (e) {
                                self._lastUploadErrored = true;
                                self.showUploadError('Invalid server response');
                                self.trigger('onUploadError', { file: uploadFileObj, error: e });
                                reject(e);
                            }
                        } else {
                            self._lastUploadErrored = true;
                            self.showUploadError('HTTP Error ' + xhr.status);
                            self.trigger('onUploadError', { file: uploadFileObj, error: xhr.status });
                            reject(new Error('HTTP ' + xhr.status));
                        }
                    });

                    xhr.addEventListener('error', function () {
                        self._lastUploadErrored = true;
                        self.showUploadError('Network error');
                        self.trigger('onUploadError', { file: uploadFileObj, error: 'network' });
                        reject(new Error('network'));
                    });

                    xhr.addEventListener('abort', function () {
                        reject(new Error('aborted'));
                    });

                    xhr.open('POST', self.options.url);
                    if (self.options.token) {
                        xhr.setRequestHeader('Authorization', 'Bearer ' + self.options.token);
                        xhr.setRequestHeader('token', self.options.token);
                    }
                    xhr.send(formData);
                });
            };

            return await uploadChunk();

        } catch (error) {
            if (error && error.message !== 'aborted' && !this._uploadCancelled) {
                self._debug('error', 'Upload failed:', error);
                self._lastUploadErrored = true;
                self.showUploadError('Upload failed: ' + (error.message || error));
                self.trigger('onUploadError', { file: sanitizedFile, error: error });
            }
            return null;
        } finally {
            self.currentUploadXHR = null;
        }
    };

    /**
     * Select uploaded file by name after upload completes
     */
    MyFileManager.prototype.selectUploadedFile = function (filename) {
        this.clearSelection();

        var uploadedFile = this.state.files.find(function (f) {
            return f.name === filename;
        });

        if (uploadedFile) {
            var fileElement = this.elements.filesContainer.querySelector('[data-hash="' + CSS.escape(uploadedFile.hash) + '"]');
            if (fileElement) {
                this.selectFile(fileElement, uploadedFile);
                this._lastSelectedHash = uploadedFile.hash;
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

        if (progressContainer) {
            progressContainer.style.display = 'block';
        }

        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = this.i18n.uploadingFile || 'Uploading File';
        }

        modal.style.display = 'flex';
        this.updateUploadProgress(0, 0, Infinity);
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

        var clamped = Math.min(100, Math.max(0, percent || 0));

        if (progressFill) progressFill.style.width = clamped + '%';
        if (progressText) progressText.textContent = Math.round(clamped) + '%';

        if (speedEl) {
            var speedMB = ((speed || 0) / 1024 / 1024).toFixed(2);
            speedEl.textContent = speedMB + ' MB/s';
        }

        if (timeEl) {
            if (timeRemaining > 0 && timeRemaining < Infinity && speed > 0) {
                var minutes = Math.floor(timeRemaining / 60);
                var seconds = Math.floor(timeRemaining % 60);

                if (minutes > 0) {
                    timeEl.textContent = minutes + 'm ' + seconds + 's remaining';
                } else {
                    timeEl.textContent = seconds + 's remaining';
                }
            } else if (clamped >= 100) {
                timeEl.textContent = '0s';
            } else {
                timeEl.textContent = this.i18n.calculating || 'Calculating...';
            }
        }
    };

    /**
     * Hide upload progress modal
     */
    MyFileManager.prototype.hideUploadProgress = function () {
        var modal = this.elements.uploadModal;

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

        this.updateUploadProgress(0, 0, Infinity);
        modal.style.display = 'none';
        this.currentUploadXHR = null;
    };

    /**
     * Cancel current upload and clear pending queue
     */
    MyFileManager.prototype.cancelUpload = function () {
        this._uploadCancelled = true;
        this.state.uploadQueue = [];
        if (this.currentUploadXHR) {
            this.currentUploadXHR.abort();
            this.currentUploadXHR = null;
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

        if (progressContainer) {
            progressContainer.style.display = 'none';
        }

        if (statusDiv && statusMsg) {
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#d32f2f';
            statusDiv.style.padding = '10px';
            statusDiv.style.background = '#ffebee';
            statusDiv.style.borderRadius = '4px';
            statusMsg.innerHTML = '<strong>❌ Error:</strong> ' + this.escapeHtml(errorMessage);
        }

        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }

        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = 'Upload Failed';
        }

        this.currentUploadXHR = null;
    };

    /**
     * Show upload complete
     */
    MyFileManager.prototype.showUploadComplete = function () {
        var modal = this.elements.uploadModal;
        var statusDiv = modal.querySelector('.mfm-upload-status');
        var statusMsg = modal.querySelector('.mfm-upload-status-message');
        var actionsDiv = modal.querySelector('.mfm-upload-actions');

        this.updateUploadProgress(100, 0, 0);

        if (statusDiv && statusMsg) {
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#2e7d32';
            statusDiv.style.padding = '10px';
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.borderRadius = '4px';
            statusMsg.innerHTML = '<strong>Success:</strong> ' + this.escapeHtml(this.i18n.uploadComplete || 'File uploaded successfully!');
        }

        if (actionsDiv) {
            actionsDiv.style.display = 'block';
        }

        var title = modal.querySelector('.mfm-upload-modal-title');
        if (title) {
            title.textContent = this.i18n.uploadComplete || 'Upload Complete';
        }

        this.currentUploadXHR = null;
    };

    /**
     * Handle search with debounce and proper directory restore when cleared
     */
    MyFileManager.prototype.handleSearch = function (query) {
        var self = this;
        var trimmed = (query || '').trim();

        if (this._searchTimer) {
            clearTimeout(this._searchTimer);
            this._searchTimer = null;
        }

        if (!trimmed) {
            this._searchRequestId++;
            if (this.state.isSearching) {
                this.state.isSearching = false;
                this.refresh();
            }
            return;
        }

        this.state.isSearching = true;
        var reqId = ++this._searchRequestId;

        this._searchTimer = setTimeout(function () {
            self.request({
                cmd: 'search',
                q: trimmed,
                target: self.state.currentHash || ''
            }, function (response) {
                // Ignore stale search responses
                if (reqId !== self._searchRequestId || !self.state.isSearching) {
                    return;
                }
                self.state.files = self.filterHiddenFolders(response.files) || [];
                self.clearSelection();
                self.renderFiles();
            });
        }, 250);
    };

    /**
     * Make AJAX request (token sent via Authorization header and POST body, not URL query string)
     */
    MyFileManager.prototype.request = function (data, callback) {
        var self = this;
        var xhr = new XMLHttpRequest();
        var url = this.options.url;

        var payload = Object.assign({}, data || {});
        if (this.options.token && !payload.token) {
            payload.token = this.options.token;
        }

        this._debug('log', '=== REQUEST START ===', url, payload);

        xhr.open('POST', url);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        if (this.options.token) {
            xhr.setRequestHeader('Authorization', 'Bearer ' + this.options.token);
        }

        xhr.onload = function () {
            self._debug('log', '=== RESPONSE RECEIVED ===', xhr.status);

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

        var params = [];
        for (var key in payload) {
            if (Object.prototype.hasOwnProperty.call(payload, key) && payload[key] !== undefined && payload[key] !== null) {
                if (Array.isArray(payload[key])) {
                    payload[key].forEach(function (val) {
                        params.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(val));
                    });
                } else {
                    params.push(encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]));
                }
            }
        }

        xhr.send(params.join('&'));
    };

    /**
     * Restore selected files from trash
     */
    MyFileManager.prototype.restoreSelected = function () {
        var self = this;

        if (this.state.selectedFiles.length === 0) {
            alert('No file selected');
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
     * Empty trash - delete all files permanently (single confirmation)
     */
    MyFileManager.prototype.emptyTrash = function () {
        var self = this;

        if (!confirm(this.i18n.confirmEmptyTrash || this.i18n.confirmDelete)) {
            return;
        }

        this.clearSelection();
        this.state.files.forEach(function (file) {
            var fileEl = self.elements.filesContainer.querySelector('[data-hash="' + CSS.escape(file.hash) + '"]');
            if (fileEl) {
                self.selectFile(fileEl, file);
            }
        });

        // Skip second confirmation dialog
        this.deleteSelected(true);
    };

    /**
     * Update trash toolbar visibility
     */
    MyFileManager.prototype.updateTrashToolbar = function () {
        if (!this.elements || !this.elements.trashToolbar || !this.elements.toolbar) {
            return;
        }

        var inTrash = this.isInTrash();
        this.elements.trashToolbar.style.display = inTrash ? 'flex' : 'none';
        this.elements.toolbar.style.display = 'flex';
    };

    /**
     * Initialize all registered plugins
     */
    MyFileManager.prototype.initPlugins = function () {
        var self = this;

        if (window.MyFileManagerPlugins && Array.isArray(window.MyFileManagerPlugins)) {
            window.MyFileManagerPlugins.forEach(function (pluginInitializer) {
                try {
                    var plugin = pluginInitializer(self);
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
     */
    MyFileManager.prototype.registerPlugin = function (plugin) {
        if (!plugin || typeof plugin !== 'object') {
            this._debug('error', 'Invalid plugin object');
            return;
        }

        var self = this;

        if (plugin.contextMenuItems && Array.isArray(plugin.contextMenuItems)) {
            plugin.contextMenuItems.forEach(function (item) {
                self.options.customContextMenu.push(item);
            });
        }

        if (plugin.toolbarMenuItems && Array.isArray(plugin.toolbarMenuItems)) {
            plugin.toolbarMenuItems.forEach(function (menuConfig) {
                var existingMenuIndex = -1;
                for (var i = 0; i < self.options.customMenus.length; i++) {
                    if (self.options.customMenus[i].id === menuConfig.id) {
                        existingMenuIndex = i;
                        break;
                    }
                }

                if (existingMenuIndex !== -1) {
                    var existingMenu = self.options.customMenus[existingMenuIndex];
                    menuConfig.items.forEach(function (item) {
                        existingMenu.items.push(item);
                    });
                } else {
                    self.options.customMenus.push(menuConfig);
                }
            });

            // Rebuild menubar if DOM is already mounted
            if (this.elements && this.elements.menubar) {
                this.elements.menubar.innerHTML = this.buildMenuBar();
                this.updateMenuStates();
            }
        }

        this.plugins.push(plugin);
        this._debug('log', 'Plugin registered:', plugin.name || 'Unknown');

        return plugin;
    };

    /**
     * Setup auto-refresh functionality (pauses when tab is hidden, searching, or transferring)
     */
    MyFileManager.prototype.setupAutoRefresh = function () {
        var self = this;

        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }

        if (this.options.autoRefresh && typeof this.options.autoRefresh === 'number' && this.options.autoRefresh > 0) {
            this._debug('log', 'Auto-refresh enabled: ' + this.options.autoRefresh + 'ms');

            this.autoRefreshInterval = setInterval(function () {
                if (document.hidden ||
                    self.state.isUploading ||
                    self.currentDownloadController ||
                    self.state.isSearching ||
                    (self.elements && self.elements.modal && self.elements.modal.style.display === 'block')) {
                    return;
                }
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
     */
    MyFileManager.prototype.startAutoRefresh = function (interval) {
        if (interval && typeof interval === 'number' && interval > 0) {
            this.options.autoRefresh = interval;
            this.setupAutoRefresh();
        }
    };

    /**
     * Destroy file manager instance and cleanup listeners/transfers
     */
    MyFileManager.prototype.destroy = function () {
        this.stopAutoRefresh();
        this.cancelUpload();
        this.cancelDownload();
        this.releaseWakeLock();

        if (this._searchTimer) {
            clearTimeout(this._searchTimer);
            this._searchTimer = null;
        }

        if (this._boundDocHandlers.click) {
            document.removeEventListener('click', this._boundDocHandlers.click);
        }
        if (this._boundDocHandlers.visibilitychange) {
            document.removeEventListener('visibilitychange', this._boundDocHandlers.visibilitychange);
        }
        if (this._boundDocHandlers.keydown) {
            document.removeEventListener('keydown', this._boundDocHandlers.keydown);
        }
        this._boundDocHandlers = {};

        this.plugins = [];

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