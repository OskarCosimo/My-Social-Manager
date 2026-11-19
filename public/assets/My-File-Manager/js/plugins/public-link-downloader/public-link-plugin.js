/**
 * // js/plugins/public-link-plugin.js
 * Public Link Generator Plugin for MyFileManager
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */
(function () {
    'use strict';

    const PublicLinkPlugin = {
        name: 'PublicLinkGenerator',
        version: '1.1.0',
        activeModal: null,
        downloadUrl: '/download.php',

        init: function (fileManager) {
            this.fm = fileManager;
            this.apiUrl = fileManager.options.url;

            this.cancelJsonFile = fileManager.options.publicLinksCancelJsonFile !== undefined
                ? fileManager.options.publicLinksCancelJsonFile
                : true;

            this.availableExpirations = fileManager.options.publicLinksExpirations || [
                30, 60, 120, 180, 360, 720, 1440, 2160, 2880
            ];

            this.availableWaitTimes = fileManager.options.publicLinksWaitTimes || [
                0, 10, 30, 60, 120, 300
            ];

            if (fileManager.options.publicLinksDownloadUrl) {
                this.downloadUrl = fileManager.options.publicLinksDownloadUrl;
            } else {
                this.downloadUrl = this.apiUrl.replace(/connector(_example)?\.php.*$/, 'download.php');
            }

            if (!this.fm.options.customContextMenu) {
                this.fm.options.customContextMenu = [];
            }

            // Avoid duplicate context menu registration
            const alreadyRegistered = this.fm.options.customContextMenu.some(function (item) {
                return item.id === 'generate-public-link';
            });

            if (!alreadyRegistered) {
                this.fm.options.customContextMenu.push({
                    id: 'generate-public-link',
                    label: '🔗 Generate Public Link',
                    action: 'generatePublicLink',
                    icon: '🔗',
                    condition: function (selectedFiles) {
                        return selectedFiles.length === 1 && selectedFiles[0].mime !== 'directory';
                    },
                    handler: this.showLinkGenerator.bind(this)
                });
            }

            this.addManageLinksToFileMenu();
        },

        /**
         * Build fetch headers with Bearer token if available
         */
        _buildHeaders: function () {
            const headers = {};
            if (this.fm && this.fm.options && this.fm.options.token) {
                headers['Authorization'] = 'Bearer ' + this.fm.options.token;
            }
            return headers;
        },

        /**
         * Add "Manage Public Links" to File menu without strict-mode issues
         */
        addManageLinksToFileMenu: function (attempts) {
            const self = this;
            const retryCount = attempts || 0;

            setTimeout(function () {
                const menubar = (self.fm && self.fm.elements && self.fm.elements.menubar)
                    ? self.fm.elements.menubar
                    : document;
                const fileMenu = menubar.querySelector('[data-menu="file"]');

                if (!fileMenu) {
                    if (retryCount < 10) {
                        self.addManageLinksToFileMenu(retryCount + 1);
                    }
                    return;
                }

                const fileMenuDropdown = fileMenu.querySelector('.mfm-menu-dropdown');
                if (!fileMenuDropdown || fileMenuDropdown.querySelector('[data-action="managePublicLinks"]')) {
                    return;
                }

                const separator = document.createElement('div');
                separator.className = 'mfm-menu-separator';

                const menuItem = document.createElement('div');
                menuItem.className = 'mfm-menu-item';
                menuItem.setAttribute('data-action', 'managePublicLinks');
                menuItem.title = 'Manage Public Links';
                menuItem.innerHTML = '<span class="mfm-menu-item-icon">📊</span><span>Manage Public Links</span>';

                menuItem.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (self.fm && typeof self.fm.closeAllMenus === 'function') {
                        self.fm.closeAllMenus();
                    }

                    const formData = new FormData();
                    formData.append('cmd', 'publiclink_list');
                    if (self.fm.options.token) {
                        formData.append('token', self.fm.options.token);
                    }

                    const loadingModal = self.createLoadingModal();
                    document.body.appendChild(loadingModal);
                    self.activeModal = loadingModal;
                    document.body.classList.add('modal-open');

                    fetch(self.apiUrl, {
                        method: 'POST',
                        headers: self._buildHeaders(),
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(function (res) {
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            return res.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                self.closeActiveModal();
                                self.renderLinkManager(data.links || []);
                            } else {
                                throw new Error(data.error || 'Failed to load links');
                            }
                        })
                        .catch(function (err) {
                            console.error('Load links error:', err);
                            self.closeActiveModal();
                            alert('Failed to load links: ' + err.message);
                        });
                });

                fileMenuDropdown.appendChild(separator);
                fileMenuDropdown.appendChild(menuItem);
            }, 150);
        },

        /**
         * Close any existing modal
         */
        closeActiveModal: function () {
            if (this.activeModal) {
                this.activeModal.remove();
                this.activeModal = null;
                document.body.classList.remove('modal-open');
            }
        },

        /**
         * Show link generator modal
         */
        showLinkGenerator: function (selectedFiles) {
            this.closeActiveModal();
            const file = selectedFiles[0];
            const self = this;

            const expirationLabels = {
                30: '30 minutes',
                60: '1 hour',
                120: '2 hours',
                180: '3 hours',
                360: '6 hours',
                720: '12 hours',
                1440: '24 hours (1 day)',
                2160: '36 hours',
                2880: '48 hours (2 days)'
            };

            let expirationOptionsHTML = '';
            this.availableExpirations.forEach(function (minutes, index) {
                const label = expirationLabels[minutes] || (minutes + ' minutes');
                const selected = index === 0 ? 'selected' : '';
                expirationOptionsHTML += '<option value="' + parseInt(minutes, 10) + '" ' + selected + '>' + self.escapeHtml(label) + '</option>';
            });

            const waitTimeLabels = {
                0: 'No wait',
                10: '10 seconds',
                30: '30 seconds',
                60: '1 minute',
                120: '2 minutes',
                300: '5 minutes'
            };

            let waitTimeOptionsHTML = '';
            this.availableWaitTimes.forEach(function (seconds) {
                const label = waitTimeLabels[seconds] || (seconds + ' seconds');
                const selected = (seconds === 30) ? 'selected' : '';
                waitTimeOptionsHTML += '<option value="' + parseInt(seconds, 10) + '" ' + selected + '>' + self.escapeHtml(label) + '</option>';
            });

            const modalHTML = `
        <div class="mfm-modal-overlay" id="public-link-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 99999; display: flex; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
            <div class="mfm-modal-dialog" style="position: relative; top: auto; left: auto; transform: none; max-width: 650px; width: 100%; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); max-height: 90vh; display: flex; flex-direction: column;">
                <div class="mfm-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600;">🔗 Generate Public Link</h3>
                    <button class="mfm-modal-close" id="close-link-modal" style="color: white; font-size: 32px; background: transparent; border: none; cursor: pointer; opacity: 0.9; line-height: 1; padding: 0; width: 32px; height: 32px;">×</button>
                </div>
                <div class="mfm-modal-body" style="padding: 20px; background: #f8f9fa; overflow-y: auto; flex: 1;">
                    <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #667eea;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px; flex-shrink: 0;">📁</span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Selected File</div>
                                <div style="font-weight: 600; color: #333; word-break: break-all; font-size: 14px;">${this.escapeHtml(file.name)}</div>
                            </div>
                        </div>
                    </div>

                    <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; font-weight: 600;">
                            🔒 Link Access Type
                        </div>
                        <div class="link-type-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <label style="display: flex; align-items: center; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="link-type-option">
                                <input type="radio" name="linkType" value="public" checked style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                                <div style="min-width: 0;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 2px; font-size: 13px;">🌐 Public</div>
                                    <div style="font-size: 10px; color: #888;">Anyone with link</div>
                                </div>
                            </label>
                            <label style="display: flex; align-items: center; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" class="link-type-option">
                                <input type="radio" name="linkType" value="registered" style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                                <div style="min-width: 0;">
                                    <div style="font-weight: 600; color: #333; margin-bottom: 2px; font-size: 13px;">🔐 Registered</div>
                                    <div style="font-size: 10px; color: #888;">Login required</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; font-weight: 600;">
                            ⚙️ Link Settings
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label for="link-expiration" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏱️ Link Expiration
                            </label>
                            <select id="link-expiration" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer;">
                                ${expirationOptionsHTML}
                            </select>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label for="wait-time" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏳ Wait Time Before Download
                            </label>
                            <select id="wait-time" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer;">
                                ${waitTimeOptionsHTML}
                            </select>
                        </div>

                        <div>
                            <label style="display: flex; align-items: center; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" id="max-downloads" style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                                📊 Limit Maximum Downloads
                            </label>
                            <input type="number" id="max-downloads-count" value="10" min="1" max="1000" disabled
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.2s;">
                        </div>
                    </div>

                    <div id="generated-link-container" style="display: none; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 16px; border-radius: 8px; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);">
                        <div style="font-size: 12px; color: rgba(255,255,255,0.95); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-weight: 600;">
                            ✅ Link Generated Successfully
                        </div>
                        <input type="text" id="generated-link-url" readonly
                               style="width: 100%; padding: 12px; border: none; border-radius: 6px; font-size: 12px; background: rgba(255,255,255,0.95); color: #333; margin-bottom: 12px; font-family: monospace; word-break: break-all;">
                        <button id="copy-link-btn" style="width: 100%; padding: 12px; background: white; color: #11998e; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s;">
                            📋 Copy Link to Clipboard
                        </button>
                    </div>
                </div>

                <div class="mfm-modal-footer" style="padding: 16px 20px; background: white; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end; border-radius: 0 0 12px 12px; flex-shrink: 0; flex-wrap: wrap;">
                    <button id="cancel-link-btn" style="padding: 12px 24px; background: #f5f5f5; color: #666; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px;">
                        Cancel
                    </button>
                    <button id="generate-link-btn" style="padding: 12px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); font-size: 14px;">
                        Generate Link
                    </button>
                </div>
            </div>
        </div>
        <style>
            body.modal-open { overflow: hidden; }
            .link-type-option:hover { border-color: #667eea !important; background: #f8f9ff !important; }
            .link-type-option:has(input:checked) { border-color: #667eea !important; background: #f0f2ff !important; }
            #max-downloads-count:disabled { background: #f5f5f5; cursor: not-allowed; opacity: 0.6; }
            @media (max-width: 768px) {
                #public-link-modal { padding: 10px !important; }
                .link-type-grid { grid-template-columns: 1fr !important; }
                .mfm-modal-footer { flex-direction: column !important; }
                #cancel-link-btn, #generate-link-btn { width: 100% !important; }
            }
        </style>`;

            this.currentFile = file;
            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;
            document.body.classList.add('modal-open');

            document.getElementById('close-link-modal').addEventListener('click', () => this.closeActiveModal());
            document.getElementById('cancel-link-btn').addEventListener('click', () => this.closeActiveModal());
            document.getElementById('generate-link-btn').addEventListener('click', () => this.generateLink());
            document.getElementById('copy-link-btn').addEventListener('click', () => this.copyLink());
            document.getElementById('max-downloads').addEventListener('change', function (e) {
                document.getElementById('max-downloads-count').disabled = !e.target.checked;
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    self.closeActiveModal();
                }
            });
        },

        /**
         * Generate public link
         */
        generateLink: async function () {
            const linkType = document.querySelector('input[name="linkType"]:checked').value;
            const expiration = parseInt(document.getElementById('link-expiration').value, 10);
            const waitTime = parseInt(document.getElementById('wait-time').value, 10);
            const maxDownloadsEnabled = document.getElementById('max-downloads').checked;
            let maxDownloads = maxDownloadsEnabled
                ? parseInt(document.getElementById('max-downloads-count').value, 10)
                : 0;

            if (isNaN(maxDownloads) || maxDownloads < 0) {
                maxDownloads = 0;
            }

            const generateBtn = document.getElementById('generate-link-btn');
            generateBtn.disabled = true;
            generateBtn.textContent = 'Generating...';

            try {
                const formData = new FormData();
                formData.append('cmd', 'publiclink_create');
                formData.append('file_hash', this.currentFile.hash);
                formData.append('file_name', this.currentFile.name);
                formData.append('file_size', this.currentFile.size);
                formData.append('link_type', linkType);
                formData.append('expiration_minutes', expiration);
                formData.append('wait_seconds', waitTime);
                formData.append('max_downloads', maxDownloads);
                formData.append('cancel_json_file', this.cancelJsonFile ? '1' : '0');

                if (this.fm.options.token) {
                    formData.append('token', this.fm.options.token);
                }

                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: this._buildHeaders(),
                    body: formData,
                    credentials: 'same-origin'
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response.');
                }

                if (result.success) {
                    const linkUrl = window.location.origin + this.downloadUrl + '?t=' + encodeURIComponent(result.token);
                    document.getElementById('generated-link-url').value = linkUrl;
                    document.getElementById('generated-link-container').style.display = 'block';
                    generateBtn.style.display = 'none';
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Generation error:', error);
                alert('Failed to generate link: ' + error.message);
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate Link';
            }
        },

        /**
         * Copy generated link to clipboard
         */
        copyLink: function () {
            const input = document.getElementById('generated-link-url');
            this.copyLinkToClipboard(input.value);
        },

        /**
         * Render links table with DataTables
         */
        renderLinksTable: function (links) {
            const container = document.getElementById('links-table-container');
            const self = this;

            if (!links || links.length === 0) {
                container.innerHTML =
                    '<div class="empty-state">' +
                    '<div class="empty-state-icon">🔗</div>' +
                    '<div class="empty-state-text">No public links created yet</div>' +
                    '</div>';
                return;
            }

            const rows = [];
            rows.push('<table id="links-table" class="display"><thead><tr>' +
                '<th>File</th><th>Type</th><th>Downloads</th><th>Created</th><th>Expires</th><th>Actions</th>' +
                '</tr></thead><tbody>');

            links.forEach(function (link) {
                const linkUrl = window.location.origin + self.downloadUrl + '?t=' + encodeURIComponent(link.token);
                const expiresDate = new Date(link.expires_at * 1000);
                const createdDate = new Date(link.created_at * 1000);
                const isExpired = !!link.is_expired;
                const linkTypeLabel = link.link_type === 'public' ? '🌐 Public' : '🔒 Registered';
                const linkTypeBadgeClass = link.link_type === 'public' ? 'link-badge-public' : 'link-badge-registered';
                const rowClass = isExpired ? 'expired-row' : '';
                const expiredBadge = isExpired ? '<span class="link-badge link-badge-expired">EXPIRED</span>' : '';

                rows.push('<tr class="' + rowClass + '">');
                rows.push('<td><div class="file-name-cell" title="' + self.escapeHtml(link.file_name) + '">' + self.escapeHtml(link.file_name) + expiredBadge + '</div></td>');
                rows.push('<td><span class="link-badge ' + linkTypeBadgeClass + '">' + linkTypeLabel + '</span></td>');
                rows.push('<td>' + parseInt(link.download_count || 0, 10) + (link.max_downloads > 0 ? '/' + parseInt(link.max_downloads, 10) : '') + '</td>');
                rows.push('<td>' + self.escapeHtml(createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString()) + '</td>');
                rows.push('<td style="color: ' + (isExpired ? '#c62828' : 'inherit') + '; font-weight: ' + (isExpired ? 'bold' : 'normal') + ';">' + self.escapeHtml(expiresDate.toLocaleDateString() + ' ' + expiresDate.toLocaleTimeString()) + '</td>');
                rows.push('<td><div class="link-actions">');

                if (!isExpired) {
                    rows.push('<button class="link-btn link-btn-copy" data-url="' + self.escapeHtml(linkUrl) + '">📋 Copy</button>');
                }

                rows.push('<button class="link-btn link-btn-delete" data-token="' + self.escapeHtml(link.token) + '">🗑️ Delete</button>');
                rows.push('</div></td>');
                rows.push('</tr>');
            });

            rows.push('</tbody></table>');
            container.innerHTML = rows.join('');

            if (!document.getElementById('datatables-css')) {
                const link = document.createElement('link');
                link.id = 'datatables-css';
                link.rel = 'stylesheet';
                link.href = 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css';
                document.head.appendChild(link);
            }

            this.loadDataTables(function () {
                const $table = window.jQuery('#links-table');
                $table.DataTable({
                    order: [[3, 'desc']],
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
                    language: {
                        search: '🔍 Search:',
                        lengthMenu: 'Show _MENU_ links',
                        info: 'Showing _START_ to _END_ of _TOTAL_ links',
                        infoEmpty: 'No links available',
                        infoFiltered: '(filtered from _MAX_ total links)',
                        zeroRecords: 'No matching links found',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: 'Next',
                            previous: 'Previous'
                        }
                    }
                });

                $table.off('click.mfmPublicLinks');
                $table.on('click.mfmPublicLinks', '.link-btn-copy', function () {
                    const url = window.jQuery(this).attr('data-url');
                    self.copyLinkToClipboard(url);
                });

                $table.on('click.mfmPublicLinks', '.link-btn-delete', function () {
                    const token = window.jQuery(this).attr('data-token');
                    if (confirm('Are you sure you want to delete this link?')) {
                        self.deleteLink(token);
                    }
                });
            });
        },

        loadDataTables: function (callback) {
            const self = this;
            if (typeof window.jQuery === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
                script.onload = function () {
                    self.loadDataTablesPlugin(callback);
                };
                document.head.appendChild(script);
            } else {
                this.loadDataTablesPlugin(callback);
            }
        },

        loadDataTablesPlugin: function (callback) {
            if (typeof window.jQuery.fn.DataTable === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
                script.onload = function () {
                    callback();
                };
                document.head.appendChild(script);
            } else {
                callback();
            }
        },

        copyLinkToClipboard: function (url) {
            const self = this;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    alert('✅ Link copied to clipboard!');
                }).catch(function () {
                    self.fallbackCopyToClipboard(url);
                });
            } else {
                this.fallbackCopyToClipboard(url);
            }
        },

        fallbackCopyToClipboard: function (url) {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('✅ Link copied to clipboard!');
            } catch (err) {
                alert('❌ Failed to copy. Please copy manually: ' + url);
            }
            document.body.removeChild(textarea);
        },

        /**
         * Delete a link
         */
        deleteLink: function (token) {
            const self = this;
            const formData = new FormData();
            formData.append('cmd', 'publiclink_delete');
            formData.append('link_token', token);

            if (this.fm.options.token) {
                formData.append('token', this.fm.options.token);
            }

            fetch(this.apiUrl, {
                method: 'POST',
                headers: this._buildHeaders(),
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (data.success) {
                        alert('✅ Link deleted successfully');

                        const listData = new FormData();
                        listData.append('cmd', 'publiclink_list');
                        if (self.fm.options.token) {
                            listData.append('token', self.fm.options.token);
                        }

                        return fetch(self.apiUrl, {
                            method: 'POST',
                            headers: self._buildHeaders(),
                            body: listData,
                            credentials: 'same-origin'
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (listRes) {
                                if (listRes.success) {
                                    self.closeActiveModal();
                                    self.renderLinkManager(listRes.links || []);
                                }
                            });
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                })
                .catch(function (err) {
                    console.error('Delete error:', err);
                    alert('❌ Failed to delete link: ' + err.message);
                });
        },

        /**
         * Create loading modal
         */
        createLoadingModal: function () {
            const modalHTML = `
                <div class="mfm-modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 99999; display: flex; align-items: center; justify-content: center;">
                    <div class="mfm-modal-dialog" style="position: relative; top: auto; left: auto; transform: none; max-width: 360px; width: 90%; background: white; border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                        <p style="font-size: 40px; margin: 0 0 10px 0;">⏳</p>
                        <p style="margin: 0; font-weight: 500; color: #333;">Loading links...</p>
                    </div>
                </div>
            `;
            const div = document.createElement('div');
            div.innerHTML = modalHTML;
            return div.firstElementChild;
        },

        /**
         * Render link manager modal
         */
        renderLinkManager: function (links) {
            this.closeActiveModal();
            const self = this;

            const modalHTML = `
        <div class="mfm-modal-overlay" id="link-manager-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 99999; display: flex; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
            <div class="mfm-modal-dialog" style="position: relative; top: auto; left: auto; transform: none; max-width: 1200px; width: 100%; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); max-height: 90vh; display: flex; flex-direction: column;">
                <div class="mfm-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600;">📊 Manage Public Links</h3>
                    <button class="mfm-modal-close" id="close-manager-modal" style="color: white; font-size: 32px; background: transparent; border: none; cursor: pointer; opacity: 0.9; line-height: 1; padding: 0; width: 32px; height: 32px;">×</button>
                </div>
                <div class="mfm-modal-body" style="padding: 25px; background: #f8f9fa; overflow-y: auto; flex: 1;">
                    <div id="links-table-container"></div>
                </div>
            </div>
        </div>
        <style>
            .dataTables_wrapper { background: white; padding: 20px; border-radius: 8px; }
            .dataTables_filter { margin-bottom: 15px; }
            .dataTables_filter input { padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; margin-left: 8px; font-size: 14px; }
            .dataTables_length select { padding: 6px 10px; border: 2px solid #e0e0e0; border-radius: 6px; margin: 0 8px; font-size: 14px; }
            #links-table { width: 100%; border-collapse: collapse; }
            #links-table thead th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
            #links-table tbody td { padding: 12px; border-bottom: 1px solid #eee; color: #555; font-size: 14px; }
            #links-table tbody tr:hover { background: #f8f9ff; }
            #links-table tbody tr.expired-row { background: #fff3cd; opacity: 0.75; }
            .link-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .link-badge-public { background: #e3f2fd; color: #1976d2; }
            .link-badge-registered { background: #fff3e0; color: #f57c00; }
            .link-badge-expired { background: #ffebee; color: #c62828; margin-left: 6px; }
            .link-actions { display: flex; gap: 8px; flex-wrap: wrap; }
            .link-btn { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
            .link-btn-copy { background: #667eea; color: white; }
            .link-btn-delete { background: #dc3545; color: white; }
            .file-name-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .empty-state { text-align: center; padding: 60px 20px; color: #999; }
            .empty-state-icon { font-size: 48px; margin-bottom: 15px; }
            .empty-state-text { font-size: 16px; color: #666; }
        </style>`;

            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;
            document.body.classList.add('modal-open');

            document.getElementById('close-manager-modal').addEventListener('click', () => this.closeActiveModal());
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    self.closeActiveModal();
                }
            });

            this.renderLinksTable(links);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function (text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
    };

    window.PublicLinkPlugin = PublicLinkPlugin;

    // Auto-register with MyFileManager
    if (window.MyFileManager) {
        const originalInit = MyFileManager.prototype.init;
        MyFileManager.prototype.init = function () {
            originalInit.call(this);
            PublicLinkPlugin.init(this);
        };
    }
})();