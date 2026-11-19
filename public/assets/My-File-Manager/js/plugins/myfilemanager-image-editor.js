/**
 * // js/plugins/myfilemanager-image-editor.js
 * MyFileManager Image Editor Plugin
 * Adds image editing functionality to the file manager
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
        if (document.getElementById('mfm-image-editor-styles')) {
            return;
        }

        var css = `
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
                font-size: 14px;
            }
            .mfm-image-editor-toolbar .mfm-btn.active {
                background: #007bff;
                color: #fff;
                border-color: #0069d9;
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
                max-height: 60vh;
                border: 1px solid #ddd;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                background: #fff;
            }
            .mfm-image-editor-footer {
                display: flex;
                justify-content: flex-end;
                align-items: center;
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
        injectStyles();

        var plugin = {
            name: 'ImageEditorPlugin',
            version: '1.1.0',
            fileManager: fm
        };

        /**
         * Check if file is a raster image editable via canvas
         */
        function isImage(files) {
            if (!files || files.length !== 1) {
                return false;
            }

            var file = files[0];
            if (file.mime === 'directory') {
                return false;
            }

            // Exclude vector SVG from raster canvas editor
            var ext = file.name.split('.').pop().toLowerCase();
            if (ext === 'svg' || file.mime === 'image/svg+xml') {
                return false;
            }

            if (file.mime && file.mime.startsWith('image/')) {
                return true;
            }

            var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            return imageExtensions.indexOf(ext) !== -1;
        }

        /**
         * Open image editor
         */
        function openImageEditor(files, fileManager) {
            if (!files || files.length === 0) {
                alert('Please select an image file');
                return;
            }

            var file = files[0];

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
            modalHtml += '        <button class="mfm-btn" data-action="crop-free" title="Free Crop">&#9986; Crop Free</button>';
            modalHtml += '      </div>';
            modalHtml += '      <div class="mfm-image-editor-canvas-container">';
            modalHtml += '        <canvas class="mfm-image-editor-canvas"></canvas>';
            modalHtml += '      </div>';
            modalHtml += '    </div>';
            modalHtml += '    <div class="mfm-image-editor-footer">';
            modalHtml += '      <label style="margin-right: auto; font-size: 14px;"><input type="checkbox" id="image-overwrite" checked style="margin-right: 5px;"> Overwrite original</label>';
            modalHtml += '      <button class="mfm-btn" data-action="save">Save Changes</button>';
            modalHtml += '      <button class="mfm-btn" data-action="cancel">Cancel</button>';
            modalHtml += '    </div>';
            modalHtml += '  </div>';
            modalHtml += '</div>';

            fileManager.showModal('Image Editor', modalHtml);
            initializeEditor(fileManager, file);
        }

        /**
         * Initialize image editor with canvas
         */
        function initializeEditor(fileManager, file) {
            var canvas = document.querySelector('.mfm-image-editor-canvas');
            if (!canvas) {
                fileManager._debug('error', 'Canvas element .mfm-image-editor-canvas not found');
                alert('Canvas not found');
                return;
            }

            var ctx = canvas.getContext('2d');
            var originalImg = new Image();
            var sourceImg = null; // Can be originalImg or a cropped offscreen canvas
            var blobUrl = null;
            var rotation = 0;
            var flipH = 1;
            var flipV = 1;

            var cropMode = false;
            var cropStartX = 0, cropStartY = 0;
            var cropEndX = 0, cropEndY = 0;
            var isCropping = false;

            function cleanupAndClose() {
                document.removeEventListener('keydown', onKeyDown);
                if (fileManager.elements && fileManager.elements.modal) {
                    fileManager.elements.modal.removeEventListener('click', onOuterModalClick);
                }
                if (blobUrl) {
                    URL.revokeObjectURL(blobUrl);
                    blobUrl = null;
                }
                fileManager.closeModal();
            }

            function onOuterModalClick(e) {
                if (e.target === fileManager.elements.modal ||
                    e.target.classList.contains('mfm-modal-overlay') ||
                    e.target.classList.contains('mfm-modal-close')) {
                    cleanupAndClose();
                }
            }

            if (fileManager.elements && fileManager.elements.modal) {
                fileManager.elements.modal.addEventListener('click', onOuterModalClick);
            }

            function drawImage() {
                if (!sourceImg) return;

                var srcW = sourceImg.width;
                var srcH = sourceImg.height;
                var normalizedRot = ((rotation % 360) + 360) % 360;
                var swapDims = (normalizedRot === 90 || normalizedRot === 270);

                canvas.width = swapDims ? srcH : srcW;
                canvas.height = swapDims ? srcW : srcH;

                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.save();
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate(rotation * Math.PI / 180);
                ctx.scale(flipH, flipV);
                ctx.drawImage(sourceImg, -srcW / 2, -srcH / 2, srcW, srcH);
                ctx.restore();
            }

            function reset() {
                rotation = 0;
                flipH = 1;
                flipV = 1;
                cropMode = false;
                isCropping = false;
                sourceImg = originalImg;
                canvas.style.cursor = 'default';
                var cropBtn = document.querySelector('.mfm-image-editor-toolbar [data-action="crop-free"]');
                if (cropBtn) cropBtn.classList.remove('active');
                drawImage();
            }

            function enterCropMode() {
                cropMode = !cropMode;
                canvas.style.cursor = cropMode ? 'crosshair' : 'default';
                var cropBtn = document.querySelector('.mfm-image-editor-toolbar [data-action="crop-free"]');
                if (cropBtn) cropBtn.classList.toggle('active', cropMode);
                if (!cropMode) {
                    drawImage();
                }
                fileManager._debug('log', 'Crop mode:', cropMode ? 'ON' : 'OFF');
            }

            function getCanvasCoords(e) {
                var rect = canvas.getBoundingClientRect();
                var scaleX = rect.width > 0 ? (canvas.width / rect.width) : 1;
                var scaleY = rect.height > 0 ? (canvas.height / rect.height) : 1;
                return {
                    x: Math.max(0, Math.min(canvas.width, (e.clientX - rect.left) * scaleX)),
                    y: Math.max(0, Math.min(canvas.height, (e.clientY - rect.top) * scaleY))
                };
            }

            function drawImageWithCropPreview() {
                drawImage();

                var x = Math.min(cropStartX, cropEndX);
                var y = Math.min(cropStartY, cropEndY);
                var w = Math.abs(cropEndX - cropStartX);
                var h = Math.abs(cropEndY - cropStartY);

                ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.fillRect(0, 0, canvas.width, y);
                ctx.fillRect(0, y + h, canvas.width, canvas.height - y - h);
                ctx.fillRect(0, y, x, h);
                ctx.fillRect(x + w, y, canvas.width - x - w, h);

                var lineWidth = Math.max(2, Math.round(Math.max(canvas.width, canvas.height) / 300));
                var handleSize = Math.max(8, lineWidth * 3);

                ctx.strokeStyle = '#00ff00';
                ctx.lineWidth = lineWidth;
                ctx.strokeRect(x, y, w, h);

                ctx.fillStyle = '#00ff00';
                ctx.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
                ctx.fillRect(x + w - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
                ctx.fillRect(x - handleSize / 2, y + h - handleSize / 2, handleSize, handleSize);
                ctx.fillRect(x + w - handleSize / 2, y + h - handleSize / 2, handleSize, handleSize);
            }

            function applyCrop() {
                var x = Math.round(Math.min(cropStartX, cropEndX));
                var y = Math.round(Math.min(cropStartY, cropEndY));
                var w = Math.round(Math.abs(cropEndX - cropStartX));
                var h = Math.round(Math.abs(cropEndY - cropStartY));

                // Redraw clean image without overlay before copying pixels
                drawImage();

                var cropCanvas = document.createElement('canvas');
                cropCanvas.width = w;
                cropCanvas.height = h;
                var cropCtx = cropCanvas.getContext('2d');
                cropCtx.drawImage(canvas, x, y, w, h, 0, 0, w, h);

                // Promote cropped canvas to new sourceImg so subsequent rotations/flips/crops compose properly
                sourceImg = cropCanvas;
                rotation = 0;
                flipH = 1;
                flipV = 1;
                cropMode = false;
                canvas.style.cursor = 'default';

                var cropBtn = document.querySelector('.mfm-image-editor-toolbar [data-action="crop-free"]');
                if (cropBtn) cropBtn.classList.remove('active');

                drawImage();
                fileManager._debug('log', 'Crop applied:', w + 'x' + h);
            }

            // Load image securely via POST FormData + Authorization Bearer header
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
                    return r.blob();
                })
                .then(function (blob) {
                    blobUrl = URL.createObjectURL(blob);
                    originalImg.onload = function () {
                        fileManager._debug('log', 'Image loaded', originalImg.width + 'x' + originalImg.height);
                        sourceImg = originalImg;
                        drawImage();
                    };
                    originalImg.onerror = function () {
                        alert('Failed to load image.');
                        cleanupAndClose();
                    };
                    originalImg.src = blobUrl;
                })
                .catch(function (err) {
                    fileManager._debug('error', 'Failed to fetch image:', err);
                    alert('Failed to load image: ' + err.message);
                    cleanupAndClose();
                });

            document.querySelector('.mfm-image-editor-toolbar').addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;

                var action = btn.getAttribute('data-action');
                switch (action) {
                    case 'rotate-left':
                        rotation -= 90;
                        drawImage();
                        break;
                    case 'rotate-right':
                        rotation += 90;
                        drawImage();
                        break;
                    case 'flip-horizontal':
                        flipH *= -1;
                        drawImage();
                        break;
                    case 'flip-vertical':
                        flipV *= -1;
                        drawImage();
                        break;
                    case 'reset':
                        reset();
                        break;
                    case 'crop-free':
                        enterCropMode();
                        break;
                }
            });

            canvas.addEventListener('mousedown', function (e) {
                if (!cropMode) return;
                isCropping = true;
                var pt = getCanvasCoords(e);
                cropStartX = pt.x;
                cropStartY = pt.y;
                cropEndX = pt.x;
                cropEndY = pt.y;
            });

            canvas.addEventListener('mousemove', function (e) {
                if (!isCropping || !cropMode) return;
                var pt = getCanvasCoords(e);
                cropEndX = pt.x;
                cropEndY = pt.y;
                drawImageWithCropPreview();
            });

            canvas.addEventListener('mouseup', function () {
                if (!isCropping || !cropMode) return;
                isCropping = false;

                var w = Math.abs(cropEndX - cropStartX);
                var h = Math.abs(cropEndY - cropStartY);
                if (w < 10 || h < 10) {
                    drawImage();
                    return;
                }

                applyCrop();
            });

            function onKeyDown(e) {
                if (e.key === 'Escape' && cropMode) {
                    cropMode = false;
                    isCropping = false;
                    canvas.style.cursor = 'default';
                    var cropBtn = document.querySelector('.mfm-image-editor-toolbar [data-action="crop-free"]');
                    if (cropBtn) cropBtn.classList.remove('active');
                    drawImage();
                }
            }
            document.addEventListener('keydown', onKeyDown);

            document.querySelector('.mfm-image-editor-footer').addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;

                var action = btn.getAttribute('data-action');

                if (action === 'save') {
                    if (!sourceImg) return;
                    var overwrite = document.getElementById('image-overwrite').checked;

                    // Ensure no crop preview overlay is baked into saved pixels
                    cropMode = false;
                    drawImage();

                    var outputMime = 'image/jpeg';
                    if (file.mime === 'image/png') outputMime = 'image/png';
                    else if (file.mime === 'image/webp') outputMime = 'image/webp';

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            alert('Failed to encode image.');
                            return;
                        }

                        var container = document.querySelector('.mfm-image-editor-canvas-container');
                        if (container) container.style.opacity = '0.5';

                        var saveHeaders = {};
                        if (fileManager.options.token) {
                            saveHeaders['Authorization'] = 'Bearer ' + fileManager.options.token;
                        }

                        if (overwrite) {
                            var deleteFormData = new FormData();
                            deleteFormData.append('cmd', 'delete');
                            deleteFormData.append('targets[]', file.hash);
                            if (fileManager.options.token) {
                                deleteFormData.append('token', fileManager.options.token);
                            }

                            fetch(fileManager.options.url, {
                                method: 'POST',
                                headers: saveHeaders,
                                body: deleteFormData,
                                credentials: 'same-origin'
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (deleteData) {
                                    if (deleteData.error) throw new Error(deleteData.error);
                                    return uploadNewImage(fileManager, file, blob, container, saveHeaders);
                                })
                                .catch(function (err) { handleImageError(err, container); });
                        } else {
                            uploadNewImage(fileManager, file, blob, container, saveHeaders)
                                .catch(function (err) { handleImageError(err, container); });
                        }
                    }, outputMime, 0.92);
                } else if (action === 'cancel') {
                    cleanupAndClose();
                }
            });

            document.querySelector('.mfm-image-editor-close').addEventListener('click', cleanupAndClose);

            function uploadNewImage(fmInstance, targetFile, blob, container, headers) {
                var parentHash = getParentHashFromFile(targetFile, fmInstance);
                var uploadForm = new FormData();

                uploadForm.append('cmd', 'upload');
                uploadForm.append('target', parentHash);
                uploadForm.append('upload', blob, targetFile.name);
                uploadForm.append('part', '0');
                uploadForm.append('parts', '1');
                uploadForm.append('id', 'imgedit_' + Date.now());

                if (fmInstance.options.token) {
                    uploadForm.append('token', fmInstance.options.token);
                }

                return fetch(fmInstance.options.url, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: uploadForm
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) throw new Error(data.error);
                        alert('Image saved!');
                        cleanupAndClose();
                        fmInstance.refresh();
                    });
            }

            function handleImageError(err, container) {
                console.error('Image save error:', err);
                alert('Image save error: ' + err.message);
                if (container) container.style.opacity = '1';
            }
        }

        plugin.contextMenuItems = [
            {
                action: 'edit-image',
                label: '✏️ Edit Image',
                condition: isImage,
                handler: openImageEditor
            }
        ];

        fm.registerPlugin(plugin);
        return plugin;
    }

    if (!window.MyFileManagerPlugins) {
        window.MyFileManagerPlugins = [];
    }

    window.MyFileManagerPlugins.push(ImageEditorPlugin);

})(window);