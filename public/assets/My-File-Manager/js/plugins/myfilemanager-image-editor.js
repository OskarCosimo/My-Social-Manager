/**
 * MyFileManager Image Editor Plugin
 * Adds image editing functionality to the file manager
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
        // Check if styles already injected
        if (document.getElementById('mfm-image-editor-styles')) {
            return;
        }

        var css = `
            /* Image Editor Plugin Styles */
            .mfm-image-editor-modal .mfm-modal-dialog {
                max-width: 90%;
                max-height: 90vh;
            }

            .mfm-image-editor-container {
                display: flex;
                flex-direction: column;
                height: 100%;
            }

            .mfm-image-editor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                border-bottom: 1px solid #ddd;
            }

            .mfm-image-editor-header h3 {
                margin: 0;
                font-size: 18px;
            }

            .mfm-image-editor-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 30px;
                height: 30px;
                line-height: 1;
            }

            .mfm-image-editor-close:hover {
                color: #d32f2f;
            }

            .mfm-image-editor-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .mfm-image-editor-toolbar {
                display: flex;
                gap: 10px;
                padding: 10px;
                border-bottom: 1px solid #ddd;
                background: #f5f5f5;
                flex-wrap: wrap;
            }

            .mfm-image-editor-toolbar .mfm-btn {
                padding: 5px 10px;
                font-size: 16px;
            }

            .mfm-image-editor-canvas-container {
                flex: 1;
                overflow: auto;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #f9f9f9;
                padding: 20px;
            }

            .mfm-image-editor-canvas {
                max-width: 100%;
                max-height: 100%;
                border: 1px solid #ddd;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                background: #fff;
            }

            .mfm-image-editor-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 15px;
                border-top: 1px solid #ddd;
                background: #f9f9f9;
            }
        `;

        var style = document.createElement('style');
        style.id = 'mfm-image-editor-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Image Editor Plugin initializer
     * @param {MyFileManager} fm - File manager instance
     * @returns {Object} Plugin instance
     */
    function ImageEditorPlugin(fm) {
        // Inject CSS styles
        injectStyles();

        var plugin = {
            name: 'ImageEditorPlugin',
            version: '1.0.0',
            fileManager: fm
        };

        /**
         * Check if file is an image
         * @param {Array} files - Selected files
         * @param {MyFileManager} fileManager - File manager instance
         * @returns {boolean}
         */
        function isImage(files, fileManager) {
            if (!files || files.length !== 1) {
                return false;
            }

            var file = files[0];

            // Check if it's a file (not directory)
            if (file.mime === 'directory') {
                return false;
            }

            // Check MIME type
            if (file.mime && file.mime.startsWith('image/')) {
                return true;
            }

            // Check extension as fallback
            var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
            var ext = file.name.split('.').pop().toLowerCase();
            return imageExtensions.indexOf(ext) !== -1;
        }

        /**
        * Open image editor
        * @param {Array} files - Selected files
        * @param {MyFileManager} fileManager - File manager instance
        */
        function openImageEditor(files, fileManager) {
            if (!files || files.length === 0) {
                alert('Please select an image file');
                return;
            }

            var file = files[0];

            // Build image URL - use downloadUrl if available, otherwise use url
            var baseUrl = fileManager.options.downloadUrl || fileManager.options.url;

            // Clean URL (remove query string)
            var cleanBaseUrl = baseUrl.split('?')[0];

            // Extract existing params (token1, token2) from original URL
            var existingParams = [];
            if (baseUrl.indexOf('?') !== -1) {
                var queryString = baseUrl.split('?')[1];
                var pairs = queryString.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    var key = pair[0];
                    var value = pair[1];
                    // Keep token1, regenerate token2
                    if (key === 'token1') {
                        existingParams.push('token1=' + value);
                    }
                }
            }

            // Add fresh token2 (timestamp-based)
            existingParams.push('token2=' + Date.now());

            // Build download params
            var downloadParams = [
                'cmd=download',
                'target=' + encodeURIComponent(file.hash)
            ];

            // Add main token if present
            if (fileManager.options.token) {
                downloadParams.push('token=' + encodeURIComponent(fileManager.options.token));
            }

            // Combine all params
            var allParams = existingParams.concat(downloadParams);
            var imageUrl = cleanBaseUrl + '?' + allParams.join('&');

            fileManager._debug('log', 'Loading image from:', imageUrl);

            // Create modal for image editor
            var modalHtml = '<div class="mfm-image-editor-modal">';
            modalHtml += '  <div class="mfm-image-editor-container">';
            modalHtml += '    <div class="mfm-image-editor-header">';
            modalHtml += '      <h3>Edit Image: ' + fileManager.escapeHtml(file.name) + '</h3>';
            modalHtml += '      <button class="mfm-image-editor-close">&times;</button>';
            modalHtml += '    </div>';
            modalHtml += '    <div class="mfm-image-editor-body">';
            modalHtml += '      <div class="mfm-image-editor-toolbar">';
            modalHtml += '        <button class="mfm-btn" data-action="rotate-left" title="Rotate Left">&#8634; Rotate Left</button>';
            modalHtml += '        <button class="mfm-btn" data-action="rotate-right" title="Rotate Right">&#8635; Rotate Right</button>';
            modalHtml += '        <button class="mfm-btn" data-action="flip-horizontal" title="Flip Horizontal">&#8646; Flip H</button>';
            modalHtml += '        <button class="mfm-btn" data-action="flip-vertical" title="Flip Vertical">&#8597; Flip V</button>';
            modalHtml += '        <button class="mfm-btn" data-action="reset" title="Reset">&#8634; Reset</button>';
            modalHtml += '<button class="mfm-btn" data-action="crop-free" title="Free Crop ✂️">Crop Free</button>';
            modalHtml += '      </div>';
            modalHtml += '      <div class="mfm-image-editor-canvas-container">';
            modalHtml += '        <canvas class="mfm-image-editor-canvas"></canvas>';
            modalHtml += '      </div>';
            modalHtml += '    </div>';
            modalHtml += '<div class="mfm-image-editor-footer">';
            modalHtml += '<label style="margin-right: 15px; font-size: 14px;"><input type="checkbox" id="image-overwrite" checked style="margin-right: 5px;"> Overwrite original</label>';
            modalHtml += '<button class="mfm-btn" data-action="save">Save Changes</button>';
            modalHtml += '<button class="mfm-btn" data-action="cancel">Cancel</button>';
            modalHtml += '</div>';
            modalHtml += '  </div>';
            modalHtml += '</div>';

            // Show modal
            fileManager.showModal('Image Editor', modalHtml);

            // Initialize editor
            initializeEditor(fileManager, file, imageUrl);
        }

        /**
         * Initialize image editor with canvas
         * @param {MyFileManager} fileManager - File manager instance
         * @param {Object} file - File object
         * @param {string} imageUrl - Image URL
         */
        function initializeEditor(fileManager, file, imageUrl) {
            // Get canvas element
            var canvas = document.querySelector('.mfm-image-editor-canvas');
            if (!canvas) {
                // Safety check: canvas not found
                fileManager._debug('error', 'Canvas element .mfm-image-editor-canvas not found');
                alert('Canvas not found');
                return;
            }

            var ctx = canvas.getContext('2d');
            var img = new Image();
            var rotation = 0;
            var flipH = 1;
            var flipV = 1;

            // remove crossOrigin to avoid CORS issues with protected downloads
            // img.crossOrigin = 'anonymous'; // Handle CORS

            img.onload = function () {
                // Log for debug
                fileManager._debug('log', 'Image loaded', img.width + 'x' + img.height);

                // Set canvas size
                canvas.width = img.width;
                canvas.height = img.height;

                // Draw initial image
                drawImage();
            };

            img.onerror = function (e) {
                // Better error logging
                fileManager._debug('error', 'Failed to load image from URL:', imageUrl);
                fileManager._debug('error', 'Image onerror event:', e);
                alert('Failed to load image. Please check console for details.');
                fileManager.closeModal();
            };

            // Log URL and start loading
            fileManager._debug('log', 'Loading image from URL:', imageUrl);
            img.src = imageUrl;

            /**
             * Draw image on canvas with current transformations
             */
            function drawImage() {
                // Clear canvas
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Save context
                ctx.save();

                // Move to center
                ctx.translate(canvas.width / 2, canvas.height / 2);

                // Apply transformations
                ctx.rotate(rotation * Math.PI / 180);
                ctx.scale(flipH, flipV);

                // Draw image centered
                ctx.drawImage(img, -img.width / 2, -img.height / 2, img.width, img.height);

                // Restore context
                ctx.restore();
            }

            /**
             * Reset all transformations
             */
            function reset() {
                rotation = 0;
                flipH = 1;
                flipV = 1;
                canvas.width = img.width;
                canvas.height = img.height;
                drawImage();
            }

            // Bind toolbar events
            document.querySelector('.mfm-image-editor-toolbar').addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;

                var action = btn.getAttribute('data-action');

                switch (action) {
                    case 'rotate-left':
                        rotation -= 90;
                        break;
                    case 'rotate-right':
                        rotation += 90;
                        break;
                    case 'flip-horizontal':
                        flipH *= -1;
                        break;
                    case 'flip-vertical':
                        flipV *= -1;
                        break;
                    case 'reset':
                        reset();
                        return;
                    case 'crop-free':
                        enterCropMode('free');
                        break;
                }

                drawImage();
            });

            // Variabiles crop
            var cropMode = false;
            var cropStartX = 0, cropStartY = 0;
            var cropEndX = 0, cropEndY = 0;
            var isCropping = false;
            var cropBox = null;

            // percision crop mode
            function enterCropMode() {
                cropMode = true;
                canvas.style.cursor = 'crosshair';
                fileManager.debuglog('Crop mode ON');
            }

            function drawImageWithCropPreview() {
                // write image
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.save();
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate(rotation * Math.PI / 180);
                ctx.scale(flipH, flipV);
                ctx.drawImage(img, -img.width / 2, -img.height / 2);
                ctx.restore();

                // ⭐ CROP BOX preview
                var x = Math.min(cropStartX, cropEndX);
                var y = Math.min(cropStartY, cropEndY);
                var w = Math.abs(cropEndX - cropStartX);
                var h = Math.abs(cropEndY - cropStartY);

                // background shadow
                ctx.fillStyle = 'rgba(0,0,0,0.5)';
                ctx.fillRect(0, 0, canvas.width, y);
                ctx.fillRect(0, y + h, canvas.width, canvas.height - y - h);
                ctx.fillRect(0, y, x, h);
                ctx.fillRect(x + w, y, canvas.width - x - w, h);

                // ⭐ border crop
                ctx.strokeStyle = '#00ff00';
                ctx.lineWidth = 3;
                ctx.strokeRect(x + 1.5, y + 1.5, w - 3, h - 3);

                // angular handles
                ctx.fillStyle = '#00ff00';
                ctx.fillRect(x - 6, y - 6, 12, 12);    // Top-left
                ctx.fillRect(x + w - 6, y - 6, 12, 12);  // Top-right
                ctx.fillRect(x - 6, y + h - 6, 12, 12);  // Bottom-left
                ctx.fillRect(x + w - 6, y + h - 6, 12, 12); // Bottom-right
            }

            // ⭐ SNAP broders during crop
            canvas.addEventListener('mousedown', function (e) {
                if (!cropMode) return;
                isCropping = true;
                var rect = canvas.getBoundingClientRect();
                cropStartX = Math.max(0, Math.min(canvas.width, e.clientX - rect.left));
                cropStartY = Math.max(0, Math.min(canvas.height, e.clientY - rect.top));
            });

            canvas.addEventListener('mousemove', function (e) {
                if (!isCropping || !cropMode) return;
                var rect = canvas.getBoundingClientRect();
                cropEndX = Math.max(0, Math.min(canvas.width, e.clientX - rect.left));
                cropEndY = Math.max(0, Math.min(canvas.height, e.clientY - rect.top));
                drawImageWithCropPreview();
            });

            canvas.addEventListener('mouseup', function (e) {
                if (!isCropping || !cropMode) return;
                isCropping = false;

                // snap borders and apply crop
                var w = Math.abs(cropEndX - cropStartX);
                var h = Math.abs(cropEndY - cropStartY);
                if (w < 20 || h < 20) {
                    cropMode = false;
                    canvas.style.cursor = 'default';
                    return;
                }

                applyCrop();
            });

            function applyCrop() {
                var x = Math.min(cropStartX, cropEndX);
                var y = Math.min(cropStartY, cropEndY);
                var w = Math.abs(cropEndX - cropStartX);
                var h = Math.abs(cropEndY - cropStartY);

                // canvas only for crop
                var cropCanvas = document.createElement('canvas');
                var cropCtx = cropCanvas.getContext('2d');
                cropCanvas.width = w;
                cropCanvas.height = h;

                cropCtx.drawImage(canvas, x, y, w, h, 0, 0, w, h);

                // update main canvas
                canvas.width = w;
                canvas.height = h;
                ctx.drawImage(cropCanvas, 0, 0);

                cropMode = false;
                canvas.style.cursor = 'default';
                fileManager.debuglog('Crop applied:', w + 'x' + h);
            }

            // ⭐ ESC to exit crop mode
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && cropMode) {
                    cropMode = false;
                    canvas.style.cursor = 'default';
                    drawImage();
                }
            });

            // Bind footer events
            document.querySelector('.mfm-image-editor-footer').addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;

                var action = btn.getAttribute('data-action');

                if (action === 'save') {
                    var overwrite = document.getElementById('image-overwrite').checked;
                    var file = fileManager.state.selectedFiles[0];

                    var originalWidth = img.naturalWidth || img.width;
                    var originalHeight = img.naturalHeight || img.height;

                    canvas.width = originalWidth;
                    canvas.height = originalHeight;
                    drawImage();

                    canvas.toBlob(function (blob) {
                        var container = document.querySelector('.mfm-image-editor-canvas-container');
                        if (container) container.style.opacity = '0.5';

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
                                    uploadNewImage(fileManager, file, blob, container);
                                })
                                .catch(e => handleImageError(e, container));
                        } else {
                            uploadNewImage(fileManager, file, blob, container);
                        }
                    }, file.mime === 'image/png' ? 'image/png' : 'image/jpeg', 0.9);
                } else if (action === 'cancel') {
                    fileManager.closeModal();
                }
            });

            // Bind close button
            document.querySelector('.mfm-image-editor-close').addEventListener('click', function () {
                fileManager.closeModal();
            });
        }

        function uploadNewImage(fileManager, file, blob, container) {
            var parentDir = file.hash.substring(0, file.hash.lastIndexOf('/'));
            var formData = new FormData();

            formData.append('cmd', 'upload');
            formData.append('target', btoa(parentDir));
            formData.append('upload[]', blob, file.name);

            if (fileManager.options.token) {
                formData.append('token', fileManager.options.token);
            }

            fetch(fileManager.options.url, { method: 'POST', credentials: 'same-origin', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    alert('Image saved!');
                    fileManager.closeModal();
                    fileManager.refresh();
                })
                .catch(e => handleImageError(e, container));
        }

        function handleImageError(e, container) {
            console.error('Image save error:', e);
            alert('Image save error: ' + e.message);
            if (container) container.style.opacity = '1';
        }

        // Register context menu item
        plugin.contextMenuItems = [
            {
                action: 'edit-image',
                label: '✏️ Edit Image',
                condition: isImage,
                handler: openImageEditor
            }
        ];

        // Register plugin with file manager
        fm.registerPlugin(plugin);

        return plugin;
    }

    // Register plugin in global registry
    if (!window.MyFileManagerPlugins) {
        window.MyFileManagerPlugins = [];
    }

    window.MyFileManagerPlugins.push(ImageEditorPlugin);

    console.log('MyFileManager Image Editor Plugin loaded');

})(window);
