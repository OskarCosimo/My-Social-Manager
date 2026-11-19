/**
 * // js/plugins/myfilemanager-text-editor.js
 * MyFileManager Text Editor Plugin
 * Adds text editing functionality to the file manager
 *
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

(function (window) {
    'use strict';

    /**
     * UTF-8 safe Base64 helpers (compatible with PHP base64_encode/decode)
     */
    function utf8ToBase64(str) {
        if (!str) return '';
        var bytes = new TextEncoder().encode(str);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

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

    function getParentHashFromFile(file, fileManager) {
        try {
            var decoded = base64ToUtf8(file.hash || '');
            var lastSlash = decoded.lastIndexOf('/');
            if (lastSlash !== -1) {
                return utf8ToBase64(decoded.substring(0, lastSlash));
            }
            return '';
        } catch (e) {
            return (fileManager && fileManager.state && fileManager.state.currentHash) || '';
        }
    }

    /**
     * Inject CSS styles for the plugin
     */
    function injectStyles() {
        if (document.getElementById('mfm-text-editor-styles')) return;

        var css = `
            .mfm-text-editor-modal .mfm-modal-dialog { max-width: 95%; max-height: 95vh; height: 95vh; }
            .mfm-text-editor-container { display: flex; flex-direction: column; height: 100%; min-height: 500px; }
            .mfm-text-editor-header { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #ddd; }
            .mfm-text-editor-header h3 { margin: 0; font-size: 18px; }
            .mfm-text-editor-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 0; width: 30px; height: 30px; line-height: 1; }
            .mfm-text-editor-close:hover { color: #d32f2f; }
            .mfm-text-editor-body { flex: 1; display: flex; flex-direction: row; overflow: hidden; }
            .mfm-text-editor-lines { background: #f8f8f8; border-right: 1px solid #ddd; padding: 15px 0; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.5; color: #999; user-select: none; overflow: hidden; min-width: 60px; }
            .mfm-text-editor-lines span { display: block; text-align: right; padding-right: 10px; }
            .mfm-text-editor-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
            .mfm-text-editor-toolbar { display: flex; gap: 10px; align-items: center; padding: 10px; border-bottom: 1px solid #ddd; background: #f5f5f5; flex-wrap: wrap; }
            .mfm-text-editor-toolbar .mfm-btn { padding: 5px 10px; }
            .mfm-text-editor-separator { color: #ddd; margin: 0 5px; }
            .mfm-text-editor-info { margin-left: auto; font-size: 12px; color: #666; }
            .mfm-text-editor-textarea { flex: 1; width: 100%; padding: 15px; border: none; resize: none; font-family: 'Courier New', Consolas, monospace; font-size: 14px; line-height: 1.5; white-space: pre; overflow: auto; background: #fff; }
            .mfm-text-editor-textarea:focus { outline: none; }
            .mfm-text-editor-textarea:disabled { opacity: 0.6; cursor: not-allowed; }
            .mfm-text-editor-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 15px; border-top: 1px solid #ddd; background: #f9f9f9; align-items: center; }
            .mfm-overwrite-container { display: flex; align-items: center; gap: 8px; margin-right: auto; font-size: 14px; }
            .mfm-overwrite-checkbox { margin: 0; }
        `;

        var style = document.createElement('style');
        style.id = 'mfm-text-editor-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Check if file is a text file
     */
    function isTextFile(files) {
        if (!files || files.length !== 1) return false;
        var file = files[0];
        if (file.mime === 'directory') return false;
        if (file.mime && file.mime.startsWith('text/')) return true;
        var textExtensions = ['txt', 'md', 'log', 'json', 'xml', 'csv', 'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sh', 'bat', 'yaml', 'yml', 'ini', 'conf', 'cfg'];
        var ext = file.name.split('.').pop().toLowerCase();
        return textExtensions.indexOf(ext) !== -1;
    }

    /**
     * Open text editor
     */
    function openTextEditor(files, fileManager) {
        if (!files || files.length === 0) {
            alert('Please select a text file');
            return;
        }
        var file = files[0];
        if (file.size && file.size > 1048576) {
            alert('File is too large to edit (max 1MB)');
            return;
        }

        injectStyles();

        var modalHtml = '<div class="mfm-text-editor-modal"><div class="mfm-text-editor-container">';
        modalHtml += '<div class="mfm-text-editor-header"><h3>Edit Text: ' + fileManager.escapeHtml(file.name) + '</h3><button class="mfm-text-editor-close">&times;</button></div>';
        modalHtml += '<div class="mfm-text-editor-body">';
        modalHtml += '<div class="mfm-text-editor-lines"><div class="mfm-lines-container"></div></div>';
        modalHtml += '<div class="mfm-text-editor-content">';
        modalHtml += '<div class="mfm-text-editor-toolbar">';
        modalHtml += '<button class="mfm-btn" data-action="undo" title="Undo">↶ Undo</button>';
        modalHtml += '<button class="mfm-btn" data-action="redo" title="Redo">↷ Redo</button>';
        modalHtml += '<span class="mfm-text-editor-separator">|</span>';
        modalHtml += '<button class="mfm-btn" data-action="wrap" title="Toggle Word Wrap">Word Wrap</button>';
        modalHtml += '<span class="mfm-text-editor-info">Lines: <span class="mfm-text-editor-line-count">0</span></span>';
        modalHtml += '</div>';
        modalHtml += '<textarea class="mfm-text-editor-textarea" placeholder="Loading..." disabled></textarea>';
        modalHtml += '</div></div>';
        modalHtml += '<div class="mfm-text-editor-footer">';
        modalHtml += '<div class="mfm-overwrite-container">';
        modalHtml += '<input type="checkbox" id="mfm-overwrite" class="mfm-overwrite-checkbox" checked>';
        modalHtml += '<label for="mfm-overwrite">Overwrite existing file</label>';
        modalHtml += '</div>';
        modalHtml += '<button class="mfm-btn" data-action="save">Save Changes</button>';
        modalHtml += '<button class="mfm-btn" data-action="cancel">Cancel</button>';
        modalHtml += '</div>';
        modalHtml += '</div></div>';

        fileManager.showModal('Text Editor', modalHtml);
        loadFileContent(fileManager, file);
    }

    function loadFileContent(fileManager, file) {
        var textarea = document.querySelector('.mfm-text-editor-textarea');
        var lineCount = document.querySelector('.mfm-text-editor-line-count');
        var linesContainer = document.querySelector('.mfm-lines-container');
        var history = [];
        var historyIndex = -1;
        var initialContent = '';
        var isLoaded = false;
        var wordWrap = false;

        function updateLineNumbers() {
            var lines = textarea.value.split('\n').length;
            var parts = [];
            for (var i = 1; i <= lines; i++) {
                parts.push('<span>' + i + '</span>');
            }
            linesContainer.innerHTML = parts.join('');
        }

        function updateLineCount() {
            lineCount.textContent = textarea.value.split('\n').length;
            updateLineNumbers();
        }

        function toggleWordWrap() {
            wordWrap = !wordWrap;
            textarea.style.whiteSpace = wordWrap ? 'pre-wrap' : 'pre';
        }

        function undo() {
            if (historyIndex > 0) {
                historyIndex--;
                textarea.value = history[historyIndex];
                updateLineCount();
            }
        }

        function redo() {
            if (historyIndex < history.length - 1) {
                historyIndex++;
                textarea.value = history[historyIndex];
                updateLineCount();
            }
        }

        function addToHistory() {
            if (history[historyIndex] === textarea.value) return;
            history = history.slice(0, historyIndex + 1);
            history.push(textarea.value);
            historyIndex = history.length - 1;
            if (history.length > 50) {
                history.shift();
                historyIndex--;
            }
        }

        // Fetch file via POST FormData with Authorization header
        var downloadUrl = fileManager.options.downloadUrl || fileManager.options.url;
        var cleanBaseUrl = downloadUrl.split('?')[0];
        var formData = new FormData();
        formData.append('cmd', 'download');
        formData.append('target', file.hash);

        if (downloadUrl.indexOf('?') !== -1) {
            var pairs = downloadUrl.split('?')[1].split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                if (pair[0] === 'token1' && pair[1]) {
                    formData.append('token1', decodeURIComponent(pair[1]));
                }
            }
        }
        formData.append('token2', Date.now().toString());
        if (fileManager.options.token) {
            formData.append('token', fileManager.options.token);
        }

        var fetchHeaders = {};
        if (fileManager.options.token) {
            fetchHeaders['Authorization'] = 'Bearer ' + fileManager.options.token;
        }

        fetch(cleanBaseUrl, {
            method: 'POST',
            headers: fetchHeaders,
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (content) {
                initialContent = content;
                textarea.value = content;
                textarea.placeholder = '';
                textarea.disabled = false;
                isLoaded = true;
                updateLineCount();
                history.push(content);
                historyIndex = 0;
            })
            .catch(function (e) {
                alert('Failed to load file: ' + e.message);
                fileManager.closeModal();
            });

        textarea.addEventListener('input', updateLineCount);
        textarea.addEventListener('scroll', function () {
            linesContainer.parentElement.scrollTop = textarea.scrollTop;
        });

        var historyTimeout;
        textarea.addEventListener('input', function () {
            clearTimeout(historyTimeout);
            historyTimeout = setTimeout(addToHistory, 600);
        });

        document.querySelector('.mfm-text-editor-toolbar').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            var action = btn ? btn.getAttribute('data-action') : null;
            switch (action) {
                case 'undo': undo(); break;
                case 'redo': redo(); break;
                case 'wrap': toggleWordWrap(); break;
            }
        });

        function handleCloseRequest() {
            if (isLoaded && textarea.value !== initialContent && !confirm('You have unsaved changes. Are you sure?')) {
                return;
            }
            fileManager.closeModal();
        }

        document.querySelector('.mfm-text-editor-footer').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            var action = btn ? btn.getAttribute('data-action') : null;
            if (action === 'save') {
                if (!isLoaded) return;
                var overwrite = document.getElementById('mfm-overwrite').checked;
                saveFileContent(fileManager, file, textarea.value, overwrite);
            } else if (action === 'cancel') {
                handleCloseRequest();
            }
        });

        document.querySelector('.mfm-text-editor-close').addEventListener('click', handleCloseRequest);

        textarea.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                if (!isLoaded) return;
                saveFileContent(fileManager, file, textarea.value, document.getElementById('mfm-overwrite').checked);
            }
        });
    }

    function saveFileContent(fileManager, file, content, overwrite) {
        var textarea = document.querySelector('.mfm-text-editor-textarea');
        if (content.length === 0) {
            alert('Cannot save an empty (0-byte) file.');
            return;
        }

        textarea.disabled = true;

        var fetchHeaders = {};
        if (fileManager.options.token) {
            fetchHeaders['Authorization'] = 'Bearer ' + fileManager.options.token;
        }

        function uploadNewFile() {
            var parentHash = getParentHashFromFile(file, fileManager);
            var blob = new Blob([content], { type: file.mime || 'text/plain' });
            var formData = new FormData();

            formData.append('cmd', 'upload');
            formData.append('target', parentHash);
            formData.append('upload', blob, file.name);
            formData.append('part', '0');
            formData.append('parts', '1');
            formData.append('id', 'textedit_' + Date.now());

            if (fileManager.options.token) {
                formData.append('token', fileManager.options.token);
            }

            return fetch(fileManager.options.url, {
                method: 'POST',
                headers: fetchHeaders,
                credentials: 'same-origin',
                body: formData
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) throw new Error(data.error);
                    alert('File saved!');
                    fileManager.closeModal();
                    fileManager.refresh();
                });
        }

        var savePromise;
        if (overwrite) {
            var deleteFormData = new FormData();
            deleteFormData.append('cmd', 'delete');
            deleteFormData.append('targets[]', file.hash);
            if (fileManager.options.token) {
                deleteFormData.append('token', fileManager.options.token);
            }

            savePromise = fetch(fileManager.options.url, {
                method: 'POST',
                headers: fetchHeaders,
                credentials: 'same-origin',
                body: deleteFormData
            })
                .then(function (r) { return r.json(); })
                .then(function (deleteData) {
                    if (deleteData.error) throw new Error(deleteData.error);
                    return uploadNewFile();
                });
        } else {
            savePromise = uploadNewFile();
        }

        savePromise.catch(function (err) {
            console.error('Text editor save error:', err);
            textarea.disabled = false;
            alert('Failed to save file: ' + err.message);
        });
    }

    // Plugin registration
    window.MyFileManagerTextEditorPlugin = function (fm) {
        injectStyles();
        var plugin = {
            name: 'Text Editor',
            version: '1.1.0',
            contextMenuItems: [{
                action: 'edit-text',
                label: '📝 Edit Text',
                condition: isTextFile,
                handler: openTextEditor
            }]
        };
        if (fm && typeof fm.registerPlugin === 'function') {
            fm.registerPlugin(plugin);
        }
        return plugin;
    };

    if (!window.MyFileManagerPlugins) window.MyFileManagerPlugins = [];
    window.MyFileManagerPlugins.push(window.MyFileManagerTextEditorPlugin);

})(window);