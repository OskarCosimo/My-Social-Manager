/**
 * Public Link Generator Plugin for MyFileManager
 */
(function () {
    'use strict';

    const PublicLinkPlugin = {
        name: 'PublicLinkGenerator',
        version: '1.0.0',
        activeModal: null,
        downloadUrl: '/download.php', // Default fallback

        init: function (fileManager) {
            this.fm = fileManager;
            this.apiUrl = fileManager.options.url;

            // Configure cancelJsonFile option (default: true for backward compatibility)
            this.cancelJsonFile = fileManager.options.publicLinksCancelJsonFile !== undefined
                ? fileManager.options.publicLinksCancelJsonFile
                : true;

            // Configure available expiration times (in minutes)
            // Default: all options available
            this.availableExpirations = fileManager.options.publicLinksExpirations || [
                30,    // 30 minutes
                60,    // 1 hour
                120,   // 2 hours
                180,   // 3 hours
                360,   // 6 hours
                720,   // 12 hours
                1440,  // 24 hours (1 day)
                2160,  // 36 hours
                2880   // 48 hours (2 days)
            ];

            // Configure available wait times (in seconds)
            // Default: all options available
            this.availableWaitTimes = fileManager.options.publicLinksWaitTimes || [
                0,     // No wait
                10,    // 10 seconds
                30,    // 30 seconds
                60,    // 1 minute
                120,   // 2 minutes
                300    // 5 minutes
            ];

            // Configure download URL
            if (fileManager.options.publicLinksDownloadUrl) {
                this.downloadUrl = fileManager.options.publicLinksDownloadUrl;
            } else {
                this.downloadUrl = this.apiUrl.replace('connector.php', 'download.php');
            }

            console.log('✅ Public Link Plugin initialized');
            console.log('🔗 Download URL:', this.downloadUrl);
            console.log('🗑️ Cancel JSON on expiration:', this.cancelJsonFile);
            console.log('⏱️ Available expirations:', this.availableExpirations);
            console.log('⏳ Available wait times:', this.availableWaitTimes);

            // ensure customContextMenu exists
            if (!this.fm.options.customContextMenu) {
                this.fm.options.customContextMenu = [];
            }

            // add "Generate Public Link" to context menu
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

            // add "Manage Links" to File menu
            this.addManageLinksToFileMenu();
        },

        /**
         * Add "Manage Links" to File menu
         */
        addManageLinksToFileMenu: function () {
            const self = this;

            // Delay to ensure menu is rendered
            setTimeout(function () {
                const fileMenu = document.querySelector('[data-menu="file"]');
                if (!fileMenu) {
                    console.warn('⚠️ File menu not found, retrying...');
                    setTimeout(arguments.callee, 100);
                    return;
                }

                const fileMenuDropdown = fileMenu.querySelector('.mfm-menu-dropdown');
                if (!fileMenuDropdown) {
                    console.warn('⚠️ File menu dropdown not found');
                    return;
                }

                // create separator and menu item
                const separator = document.createElement('div');
                separator.className = 'mfm-menu-separator';

                const menuItem = document.createElement('div');
                menuItem.className = 'mfm-menu-item';
                menuItem.setAttribute('data-action', 'managePublicLinks');
                menuItem.title = 'Manage Public Links';
                menuItem.innerHTML = '<span class="mfm-menu-item-icon">📊</span><span>Manage Public Links</span>';

                // add event listener
                menuItem.addEventListener('click', function (e) {
                    e.preventDefault();

                    // Call the correct method to show link manager
                    const formData = new FormData();
                    formData.append('cmd', 'publiclink_list');
                    if (self.fm.options.token) {
                        formData.append('token', self.fm.options.token);
                    }

                    // Show loading modal
                    const loadingModal = self.createLoadingModal();
                    document.body.appendChild(loadingModal);
                    self.activeModal = loadingModal;
                    document.body.classList.add('modal-open');

                    // Fetch links from server
                    fetch(self.apiUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                self.closeActiveModal();
                                self.renderLinkManager(data.links);
                            } else {
                                throw new Error(data.error || 'Failed to load links');
                            }
                        })
                        .catch(err => {
                            console.error('Load links error:', err);
                            self.closeActiveModal();
                            alert('Failed to load links: ' + err.message);
                        });

                    // close all dropdowns
                    const allDropdowns = document.querySelectorAll('.mfm-menu-dropdown');
                    allDropdowns.forEach(dd => dd.style.display = 'none');
                });

                // append to menu
                fileMenuDropdown.appendChild(separator);
                fileMenuDropdown.appendChild(menuItem);

                console.log('✅ "Manage Links" added to File menu');
            }, 500);
        },

        /**
         * Close any existing modal
         */
        closeActiveModal: function () {
            if (this.activeModal) {
                this.activeModal.remove();
                this.activeModal = null;
                // Re-enable body scroll
                document.body.classList.remove('modal-open');
            }
        },

        /**
         * Show link generator modal
         */
        showLinkGenerator: function (selectedFiles, fm) {
            this.closeActiveModal();
            const file = selectedFiles[0];
            const self = this;

            // Generate expiration options dynamically
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
            this.availableExpirations.forEach((minutes, index) => {
                const label = expirationLabels[minutes] || `${minutes} minutes`;
                const selected = index === 0 ? 'selected' : '';
                expirationOptionsHTML += `<option value="${minutes}" ${selected}>${label}</option>`;
            });

            // Generate wait time options dynamically
            const waitTimeLabels = {
                0: 'No wait',
                10: '10 seconds',
                30: '30 seconds',
                60: '1 minute',
                120: '2 minutes',
                300: '5 minutes'
            };

            let waitTimeOptionsHTML = '';
            this.availableWaitTimes.forEach((seconds, index) => {
                const label = waitTimeLabels[seconds] || `${seconds} seconds`;
                const selected = (seconds === 30) ? 'selected' : '';
                waitTimeOptionsHTML += `<option value="${seconds}" ${selected}>${label}</option>`;
            });

            const modalHTML = `
        <div class="mfm-modal-overlay" id="public-link-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 99999; display: flex; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
            <div class="mfm-modal-dialog" style="max-width: 650px; width: 100%; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); max-height: 90vh; display: flex; flex-direction: column;">
                <div class="mfm-modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600;">🔗 Generate Public Link</h3>
                    <button class="mfm-modal-close" id="close-link-modal" style="color: white; font-size: 32px; background: transparent; border: none; cursor: pointer; opacity: 0.9; line-height: 1; padding: 0; width: 32px; height: 32px;">×</button>
                </div>
                <div class="mfm-modal-body" style="padding: 20px; background: #f8f9fa; overflow-y: auto; flex: 1;">
                    
                    <!-- File Info Section -->
                    <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #667eea;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 24px; flex-shrink: 0;">📁</span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Selected File</div>
                                <div style="font-weight: 600; color: #333; word-break: break-all; font-size: 14px;">${this.escapeHtml(file.name)}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Link Type Section -->
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

                    <!-- Settings Section -->
                    <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; font-weight: 600;">
                            ⚙️ Link Settings
                        </div>
                        
                        <!-- Expiration -->
                        <div style="margin-bottom: 16px;">
                            <label for="link-expiration" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏱️ Link Expiration
                            </label>
                            <div style="position: relative;">
                                <select id="link-expiration" style="width: 100%; padding: 12px 40px 12px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px; transition: all 0.2s;">
                                    ${expirationOptionsHTML}
                                </select>
                            </div>
                        </div>
                        
                        <!-- Wait Time -->
                        <div style="margin-bottom: 16px;">
                            <label for="wait-time" style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px;">
                                ⏳ Wait Time Before Download
                            </label>
                            <div style="position: relative;">
                                <select id="wait-time" style="width: 100%; padding: 12px 40px 12px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px; transition: all 0.2s;">
                                    ${waitTimeOptionsHTML}
                                </select>
                            </div>
                        </div>
                        
                        <!-- Max Downloads -->
                        <div>
                            <label style="display: flex; align-items: center; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" id="max-downloads" style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                                📊 Limit Maximum Downloads
                            </label>
                            <input type="number" id="max-downloads-count" value="10" min="1" disabled 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.2s;">
                        </div>
                    </div>

                    <!-- Generated Link Section -->
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
            /* Prevent body scroll when modal is open */
            body.modal-open {
                overflow: hidden;
            }
            
            .link-type-option:hover {
                border-color: #667eea !important;
                background: #f8f9ff !important;
            }
            
            .link-type-option:has(input:checked) {
                border-color: #667eea !important;
                background: #f0f2ff !important;
            }
            
            #link-expiration:hover,
            #wait-time:hover,
            #max-downloads-count:not(:disabled):hover {
                border-color: #667eea !important;
            }
            
            #link-expiration:focus,
            #wait-time:focus,
            #max-downloads-count:focus {
                outline: none;
                border-color: #667eea !important;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            #max-downloads-count:disabled {
                background: #f5f5f5;
                cursor: not-allowed;
                opacity: 0.6;
            }
            
            #cancel-link-btn:hover {
                background: #e8e8e8 !important;
            }
            
            #generate-link-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5) !important;
            }
            
            #generate-link-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }
            
            #copy-link-btn:hover {
                background: rgba(255,255,255,0.9) !important;
                transform: translateY(-1px);
            }
            
            /* Mobile responsive styles */
            @media (max-width: 768px) {
                #public-link-modal {
                    padding: 10px !important;
                }
                
                .mfm-modal-dialog {
                    max-height: 95vh !important;
                    border-radius: 8px !important;
                }
                
                .mfm-modal-header {
                    padding: 16px !important;
                    border-radius: 8px 8px 0 0 !important;
                }
                
                .mfm-modal-header h3 {
                    font-size: 18px !important;
                }
                
                .mfm-modal-body {
                    padding: 16px !important;
                }
                
                .link-type-grid {
                    grid-template-columns: 1fr !important;
                }
                
                .mfm-modal-footer {
                    padding: 12px 16px !important;
                    flex-direction: column !important;
                }
                
                #cancel-link-btn,
                #generate-link-btn {
                    width: 100% !important;
                    padding: 14px !important;
                }
            }
            
            @media (max-width: 480px) {
                .mfm-modal-header h3 {
                    font-size: 16px !important;
                }
                
                .mfm-modal-body {
                    padding: 12px !important;
                }
                
                #link-expiration,
                #wait-time,
                #max-downloads-count {
                    font-size: 16px !important; /* Prevents zoom on iOS */
                }
            }
        </style>
    `;

            this.currentFile = file;
            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;

            // Prevent body scroll
            document.body.classList.add('modal-open');

            // Bind events
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
            const expiration = parseInt(document.getElementById('link-expiration').value);
            const waitTime = parseInt(document.getElementById('wait-time').value);
            const maxDownloadsEnabled = document.getElementById('max-downloads').checked;
            const maxDownloads = maxDownloadsEnabled ? parseInt(document.getElementById('max-downloads-count').value) : 0;

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
                    body: formData,
                    credentials: 'same-origin'
                });

                const text = await response.text();
                console.log('Raw response:', text);

                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid server response. Check console for details.');
                }

                if (result.success) {
                    // Use configured downloadUrl with token from server response
                    const linkUrl = window.location.origin + this.downloadUrl + '?t=' + result.token;
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
            input.select();
            input.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');
                alert('✅ Link copied to clipboard!');
            } catch (err) {
                navigator.clipboard.writeText(input.value).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(() => {
                    alert('❌ Failed to copy. Please copy manually.');
                });
            }
        },

        /**
 * Render links table with DataTables
 */
        renderLinksTable: function (links) {
            const container = document.getElementById('links-table-container');
            const self = this;

            if (links.length === 0) {
                container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🔗</div>
                <div class="empty-state-text">No public links created yet</div>
            </div>
        `;
                return;
            }

            // Create table HTML
            let tableHTML = `
        <table id="links-table" class="display">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Type</th>
                    <th>Downloads</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;

            links.forEach(link => {
                const linkUrl = window.location.origin + this.downloadUrl + '?t=' + link.token;
                const expiresDate = new Date(link.expires_at * 1000);
                const createdDate = new Date(link.created_at * 1000);
                const isExpired = link.is_expired || false;
                const linkTypeLabel = link.link_type === 'public' ? '🌐 Public' : '🔒 Registered';
                const linkTypeBadgeClass = link.link_type === 'public' ? 'link-badge-public' : 'link-badge-registered';

                const rowClass = isExpired ? 'expired-row' : '';
                const expiredBadge = isExpired ? '<span class="link-badge link-badge-expired">EXPIRED</span>' : '';

                tableHTML += `<tr class="${rowClass}">`;
                tableHTML += `<td><div class="file-name-cell" title="${self.escapeHtml(link.file_name)}">${self.escapeHtml(link.file_name)}${expiredBadge}</div></td>`;
                tableHTML += `<td><span class="link-badge ${linkTypeBadgeClass}">${linkTypeLabel}</span></td>`;
                tableHTML += `<td>${link.download_count}${link.max_downloads > 0 ? '/' + link.max_downloads : ''}</td>`;
                tableHTML += `<td>${createdDate.toLocaleDateString()} ${createdDate.toLocaleTimeString()}</td>`;
                tableHTML += `<td style="color: ${isExpired ? '#c62828' : 'inherit'}; font-weight: ${isExpired ? 'bold' : 'normal'};">${expiresDate.toLocaleDateString()} ${expiresDate.toLocaleTimeString()}</td>`;
                tableHTML += `<td><div class="link-actions">`;

                if (!isExpired) {
                    tableHTML += `<button class="link-btn link-btn-copy" data-url="${self.escapeHtml(linkUrl)}">📋 Copy</button>`;
                }

                tableHTML += `<button class="link-btn link-btn-delete" data-token="${link.token}">🗑️ Delete</button>`;
                tableHTML += `</div></td>`;
                tableHTML += `</tr>`;
            });

            tableHTML += `
            </tbody>
        </table>
    `;

            container.innerHTML = tableHTML;

            // Load DataTables CSS if not already loaded
            if (!document.getElementById('datatables-css')) {
                const link = document.createElement('link');
                link.id = 'datatables-css';
                link.rel = 'stylesheet';
                link.href = 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css';
                document.head.appendChild(link);
            }

            // Load jQuery and DataTables if not already loaded
            this.loadDataTables(() => {
                // Initialize DataTables
                const table = window.jQuery('#links-table').DataTable({
                    order: [[3, 'desc']], // Sort by created date descending
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                    language: {
                        search: "🔍 Search:",
                        lengthMenu: "Show _MENU_ links",
                        info: "Showing _START_ to _END_ of _TOTAL_ links",
                        infoEmpty: "No links available",
                        infoFiltered: "(filtered from _MAX_ total links)",
                        zeroRecords: "No matching links found",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });

                // Event handlers for buttons
                window.jQuery('#links-table').on('click', '.link-btn-copy', function () {
                    const url = window.jQuery(this).data('url');
                    self.copyLinkToClipboard(url);
                });

                window.jQuery('#links-table').on('click', '.link-btn-delete', function () {
                    const token = window.jQuery(this).data('token');
                    if (confirm('Are you sure you want to delete this link?')) {
                        self.deleteLink(token);
                    }
                });
            });
        },

        loadDataTables: function (callback) {
            // Check if jQuery is loaded
            if (typeof window.jQuery === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
                script.onload = () => {
                    console.log('✅ jQuery loaded');
                    this.loadDataTablesPlugin(callback);
                };
                document.head.appendChild(script);
            } else {
                this.loadDataTablesPlugin(callback);
            }
        },

        loadDataTablesPlugin: function (callback) {
            // Check if DataTables is loaded
            if (typeof window.jQuery.fn.DataTable === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
                script.onload = () => {
                    console.log('✅ DataTables loaded');
                    callback();
                };
                document.head.appendChild(script);
            } else {
                callback();
            }
        },

        copyLinkToClipboard: function (url) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(() => {
                    this.fallbackCopyToClipboard(url);
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
            const self = this; // ← Importante!

            const formData = new FormData();
            formData.append('cmd', 'publiclink_delete');
            formData.append('link_token', token);

            if (this.fm.options.token) {
                formData.append('token', this.fm.options.token);
            }

            fetch(this.apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Link deleted successfully');

                        // Reload link manager - usa self
                        const formData = new FormData();
                        formData.append('cmd', 'publiclink_list');
                        if (self.fm.options.token) {
                            formData.append('token', self.fm.options.token);
                        }

                        fetch(self.apiUrl, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    self.closeActiveModal();
                                    self.renderLinkManager(data.links);
                                }
                            });
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    alert('❌ Failed to delete link: ' + err.message);
                });
        },

        /**
         * Create loading modal
         */
        createLoadingModal: function () {
            const modalHTML = `
                <div class="mfm-modal-overlay">
                    <div class="mfm-modal-dialog" style="max-width: 400px; text-align: center;">
                        <div class="mfm-modal-body">
                            <p style="font-size: 48px;">⏳</p>
                            <p>Loading links...</p>
                        </div>
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
            <div class="mfm-modal-dialog" style="max-width: 1200px; width: 100%; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); max-height: 90vh; display: flex; flex-direction: column;">
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
            /* DataTables custom styling */
            .dataTables_wrapper {
                background: white;
                padding: 20px;
                border-radius: 8px;
            }
            
            .dataTables_filter {
                margin-bottom: 15px;
            }
            
            .dataTables_filter input {
                padding: 8px 12px;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                margin-left: 8px;
                font-size: 14px;
                transition: border-color 0.2s;
            }
            
            .dataTables_filter input:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .dataTables_length select {
                padding: 6px 10px;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                margin: 0 8px;
                font-size: 14px;
            }
            
            #links-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            #links-table thead th {
                background: #f8f9fa;
                padding: 12px;
                text-align: left;
                font-weight: 600;
                color: #333;
                border-bottom: 2px solid #667eea;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            #links-table tbody td {
                padding: 12px;
                border-bottom: 1px solid #eee;
                color: #555;
                font-size: 14px;
            }
            
            #links-table tbody tr:hover {
                background: #f8f9ff;
            }
            
            #links-table tbody tr.expired-row {
                background: #fff3cd;
                opacity: 0.75;
            }
            
            #links-table tbody tr.expired-row:hover {
                background: #ffe8a1;
            }
            
            .link-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }
            
            .link-badge-public {
                background: #e3f2fd;
                color: #1976d2;
            }
            
            .link-badge-registered {
                background: #fff3e0;
                color: #f57c00;
            }
            
            .link-badge-expired {
                background: #ffebee;
                color: #c62828;
                margin-left: 6px;
            }
            
            .link-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .link-btn {
                padding: 6px 12px;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .link-btn-copy {
                background: #667eea;
                color: white;
            }
            
            .link-btn-copy:hover {
                background: #5568d3;
                transform: translateY(-1px);
            }
            
            .link-btn-delete {
                background: #dc3545;
                color: white;
            }
            
            .link-btn-delete:hover {
                background: #c82333;
                transform: translateY(-1px);
            }
            
            .file-name-cell {
                max-width: 300px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .dataTables_info {
                padding-top: 15px;
                color: #666;
                font-size: 13px;
            }
            
            .dataTables_paginate {
                padding-top: 15px;
            }
            
            .dataTables_paginate .paginate_button {
                padding: 6px 12px;
                margin: 0 2px;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                background: white;
                color: #333;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .dataTables_paginate .paginate_button:hover {
                background: #667eea;
                color: white;
                border-color: #667eea;
            }
            
            .dataTables_paginate .paginate_button.current {
                background: #667eea;
                color: white;
                border-color: #667eea;
            }
            
            .dataTables_paginate .paginate_button.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #999;
            }
            
            .empty-state-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }
            
            .empty-state-text {
                font-size: 16px;
                color: #666;
            }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                #link-manager-modal {
                    padding: 10px !important;
                }
                
                .mfm-modal-dialog {
                    max-height: 95vh !important;
                }
                
                .mfm-modal-header {
                    padding: 16px !important;
                }
                
                .mfm-modal-body {
                    padding: 16px !important;
                }
                
                .dataTables_wrapper {
                    padding: 15px !important;
                }
                
                #links-table {
                    font-size: 12px;
                }
                
                #links-table thead th,
                #links-table tbody td {
                    padding: 8px !important;
                }
                
                .link-actions {
                    flex-direction: column;
                }
                
                .link-btn {
                    width: 100%;
                }
            }
        </style>
    `;

            const modalDiv = document.createElement('div');
            modalDiv.innerHTML = modalHTML;
            const modal = modalDiv.firstElementChild;
            document.body.appendChild(modal);
            this.activeModal = modal;
            document.body.classList.add('modal-open');

            // Close button handler
            document.getElementById('close-manager-modal').addEventListener('click', () => this.closeActiveModal());

            // Click outside to close
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    self.closeActiveModal();
                }
            });

            // Render table content
            this.renderLinksTable(links);
        },

        /**
         * Copy link from manager to clipboard
         */
        copyLinkFromManager: function (token) {
            // Use configured downloadUrl
            const linkUrl = window.location.origin + this.downloadUrl + '?t=' + token;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkUrl).then(() => {
                    alert('✅ Link copied to clipboard!');
                }).catch(() => {
                    this.fallbackCopy(linkUrl);
                });
            } else {
                this.fallbackCopy(linkUrl);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function (text) {
            const input = document.createElement('input');
            input.value = text;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
                alert('✅ Link copied to clipboard!');
            } catch (err) {
                alert('❌ Failed to copy. Link: ' + text);
            }
            document.body.removeChild(input);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
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