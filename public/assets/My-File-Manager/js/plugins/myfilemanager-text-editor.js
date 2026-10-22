/**
 * MyFileManager Text Editor Plugin
 * Adds text editing functionality to the file manager
 * 
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

(function (window) {
    'use strict';

    /**
     * Inject CSS styles for the plugin
     */
    function injectStyles() {
        if (document.getElementById('mfm-text-editor-styles')) return;

        var css = `
            /* Text Editor Plugin Styles */
            .mfm-text-editor-modal .mfm-modal-dialog { max-width: 95%; max-height: 95vh; height: 95vh; }
            .mfm-text-editor-container { display: flex; flex-direction: column; height: 100%; }
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
     * Check if file is a text file (GLOBAL)
     */
    function isTextFile(files) {
        if (!files || files.length !== 1) return false;
        var file = files[0];
        if (file.mime && file.mime.startsWith('text/')) return true;
        var textExtensions = ['txt', 'md', 'log', 'json', 'xml', 'csv', 'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sh', 'bat', 'yaml', 'yml', 'ini', 'conf', 'cfg'];
        var ext = file.name.split('.').pop().toLowerCase();
        return textExtensions.indexOf(ext) !== -1;
    }

    /**
     * Open text editor (GLOBAL)
     */
    function openTextEditor(files, fileManager) {
        if (!files || files.length === 0) { alert('Please select a text file'); return; }
        var file = files[0];
        if (file.size && file.size > 1048576) { alert('File is too large to edit (max 1MB)'); return; }

        // Build file URL with tokens
        var baseUrl = fileManager.options.downloadUrl || fileManager.options.url;
        var cleanBaseUrl = baseUrl.split('?')[0];
        var existingParams = [];
        if (baseUrl.indexOf('?') !== -1) {
            var queryString = baseUrl.split('?')[1];
            var pairs = queryString.split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                if (pair[0] === 'token1') existingParams.push('token1=' + pair[1]);
            }
        }
        existingParams.push('token2=' + Date.now());
        var downloadParams = ['cmd=download', 'target=' + encodeURIComponent(file.hash)];
        if (fileManager.options.token) downloadParams.push('token=' + encodeURIComponent(fileManager.options.token));
        var fileUrl = cleanBaseUrl + '?' + existingParams.concat(downloadParams).join('&');

        injectStyles();

        // Modal HTML con numeri riga e checkbox overwrite
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
        modalHtml += '<textarea class="mfm-text-editor-textarea" placeholder="Loading..."></textarea>';
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
        loadFileContent(fileManager, file, fileUrl);
    }

    function loadFileContent(fileManager, file, fileUrl) {
        var textarea = document.querySelector('.mfm-text-editor-textarea');
        var lineCount = document.querySelector('.mfm-text-editor-line-count');
        var linesContainer = document.querySelector('.mfm-lines-container');
        var history = [], historyIndex = -1, wordWrap = false;

        // Update line numbers
        function updateLineNumbers() {
            var lines = textarea.value.split('\n').length;
            var html = '';
            for (var i = 1; i <= lines; i++) {
                html += '<span>' + i + '</span>';
            }
            linesContainer.innerHTML = html;
        }

        fetch(fileUrl, { method: 'GET', credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(content => {
                textarea.value = content; textarea.placeholder = '';
                updateLineCount(); updateLineNumbers();
                history.push(content); historyIndex = 0;
            })
            .catch(e => { alert('Failed to load file: ' + e.message); fileManager.closeModal(); });

        function updateLineCount() { lineCount.textContent = textarea.value.split('\n').length; updateLineNumbers(); }
        function toggleWordWrap() { wordWrap = !wordWrap; textarea.style.whiteSpace = wordWrap ? 'pre-wrap' : 'pre'; }
        function undo() { if (historyIndex > 0) { historyIndex--; textarea.value = history[historyIndex]; updateLineCount(); } }
        function redo() { if (historyIndex < history.length - 1) { historyIndex++; textarea.value = history[historyIndex]; updateLineCount(); } }
        function addToHistory() {
            history = history.slice(0, historyIndex + 1);
            history.push(textarea.value); historyIndex = history.length - 1;
            if (history.length > 50) { history.shift(); historyIndex--; }
        }

        textarea.addEventListener('input', updateLineCount);
        textarea.addEventListener('scroll', () => {
            linesContainer.parentElement.scrollTop = textarea.scrollTop;
        });
        var historyTimeout; textarea.addEventListener('input', () => {
            clearTimeout(historyTimeout); historyTimeout = setTimeout(addToHistory, 1000);
        });

        document.querySelector('.mfm-text-editor-toolbar').addEventListener('click', e => {
            var btn = e.target.closest('[data-action]'), action = btn ? btn.getAttribute('data-action') : null;
            switch (action) { case 'undo': undo(); break; case 'redo': redo(); break; case 'wrap': toggleWordWrap(); break; }
        });

        document.querySelector('.mfm-text-editor-footer').addEventListener('click', e => {
            var btn = e.target.closest('[data-action]'), action = btn ? btn.getAttribute('data-action') : null;
            if (action === 'save') {
                var overwrite = document.getElementById('mfm-overwrite').checked;
                saveFileContent(fileManager, file, textarea.value, overwrite);
            } else if (action === 'cancel') {
                if (textarea.value !== history[0] && !confirm('You have unsaved changes. Are you sure?')) return;
                fileManager.closeModal();
            }
        });

        document.querySelector('.mfm-text-editor-close').addEventListener('click', () => {
            if (textarea.value !== history[0] && !confirm('You have unsaved changes. Are you sure?')) return;
            fileManager.closeModal();
        });

        textarea.addEventListener('keydown', e => { if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveFileContent(fileManager, file, textarea.value, document.getElementById('mfm-overwrite').checked); } });
    }

    function saveFileContent(fileManager, file, content, overwrite) {
        var textarea = document.querySelector('.mfm-text-editor-textarea');
        textarea.disabled = true;

        // if checkbox overwrite is checked
        if (overwrite) {
            var deleteFormData = new FormData();
            deleteFormData.append('cmd', 'delete');
            deleteFormData.append('targets[]', file.hash);

            if (fileManager.options.token) {
                deleteFormData.append('token', fileManager.options.token);
            }

            fetch(fileManager.options.url, { method: 'POST', body: deleteFormData })
                .then(r => r.json())
                .then(deleteData => {
                    if (deleteData.error) throw new Error(deleteData.error);

                    // upload new file after deletion in the same place
                    return uploadNewFile(fileManager, file, content);
                })
        } else {
            // just upload new file without deleting
            return uploadNewFile(fileManager, file, content);
        }

        function uploadNewFile(fileManager, file, content) {
            var parentDir = file.hash.substring(0, file.hash.lastIndexOf('/'));
            var blob = new Blob([content], { type: 'text/plain' });
            var formData = new FormData();

            formData.append('cmd', 'upload');
            formData.append('target', btoa(parentDir));
            formData.append('upload[]', blob, file.name);

            if (fileManager.options.token) {
                formData.append('token', fileManager.options.token);
            }

            return fetch(fileManager.options.url, { method: 'POST', credentials: 'same-origin', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    alert('File saved!');
                    fileManager.closeModal();
                    fileManager.refresh();
                });
        }
    }


    // PLUGIN DUAL MODE
    window.MyFileManagerTextEditorPlugin = function (fm) {
        injectStyles();
        var plugin = {
            name: 'Text Editor',
            version: '1.0.0',
            contextMenuItems: [{ action: 'edit-text', label: '📝 Edit Text', condition: isTextFile, handler: openTextEditor }]
        };
        if (fm && typeof fm.registerPlugin === 'function') fm.registerPlugin(plugin);
        return plugin;
    };

    (function () {
        function autoRegister() {
            document.querySelectorAll('.myfilemanager').forEach(container => {
                if (container.MyFileManagerInstance && typeof container.MyFileManagerInstance.registerPlugin === 'function') {
                    container.MyFileManagerInstance.registerPlugin({
                        name: 'Text Editor',
                        version: '1.0.0',
                        contextMenuItems: [{ action: 'edit-text', label: '📝 Edit Text', condition: isTextFile, handler: openTextEditor }]
                    });
                    console.log('✅ Text Editor auto-registered');
                }
            });
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoRegister);
        else autoRegister();

        if (!window.MyFileManagerPlugins) window.MyFileManagerPlugins = [];
        window.MyFileManagerPlugins.push(window.MyFileManagerTextEditorPlugin);
        console.log('📝 Text Editor Plugin loaded');
    })();

})(window);
