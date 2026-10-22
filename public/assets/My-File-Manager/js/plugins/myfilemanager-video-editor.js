/**
 * MyFileManager Video Editor Plugin
 * Video editing with FFmpeg WASM (self-hosted, no external dependencies)
 * 
 * Features:
 * - Video trimming (cut clips from timeline)
 * - Video cropping (trim borders)
 * - Video resizing (change resolution)
 * - Blur regions (obscure parts of video)
 * - File overwrite option
 * 
 * Required files in ./ffmpeg/ folder:
 * - ffmpeg-core.js
 * - ffmpeg-core.wasm
 * 
 * @author Oscar Cosimo / MYETV Team
 * @license MIT
 * @version 3.0.0
 */
(function (window) {
    'use strict';

    // =====================================================
    // CONFIGURATION
    // =====================================================
    var FFMPEG_CONFIG = {
        // Path to FFmpeg files (relative to the HTML page or absolute)
        basePath: null, // Will be auto-detected
        // Enable debug logging
        debug: false
    };

    // =====================================================
    // CSS STYLES - Embedded in JavaScript
    // =====================================================
    function injectStyles() {
        if (document.getElementById('mfm-video-editor-styles')) return;

        var css = `
            /* Video Editor Modal */
            .mfm-video-editor-modal .mfm-modal-dialog {
                max-width: 95%;
                width: 1200px;
                max-height: 95vh;
            }
            .mfm-video-editor-container {
                display: flex;
                flex-direction: column;
                height: 100%;
                min-height: 600px;
            }

            /* Header */
            .mfm-video-editor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                border-bottom: 1px solid #ddd;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
            }
            .mfm-video-editor-header h3 {
                margin: 0;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .mfm-video-editor-close {
                background: rgba(255,255,255,0.2);
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #fff;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            .mfm-video-editor-close:hover {
                background: rgba(255,255,255,0.3);
            }

            /* Body */
            .mfm-video-editor-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                background: #f0f2f5;
            }

            /* Toolbar */
            .mfm-video-editor-toolbar {
                display: flex;
                gap: 8px;
                padding: 12px 15px;
                background: #fff;
                border-bottom: 1px solid #e0e0e0;
                flex-wrap: wrap;
                align-items: center;
            }
            .mfm-video-editor-toolbar .mfm-btn {
                padding: 8px 16px;
                font-size: 14px;
                border: 1px solid #ddd;
                background: #fff;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s;
                font-weight: 500;
            }
            .mfm-video-editor-toolbar .mfm-btn:hover {
                background: #f5f5f5;
                border-color: #bbb;
            }
            .mfm-video-editor-toolbar .mfm-btn.active {
                background: #667eea;
                color: #fff;
                border-color: #5a6fd6;
            }
            .mfm-toolbar-separator {
                width: 1px;
                height: 28px;
                background: #e0e0e0;
                margin: 0 8px;
            }

            /* Main Area */
            .mfm-video-editor-main {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            /* Preview */
            .mfm-video-editor-preview {
                flex: 1;
                display: flex;
                flex-direction: column;
                background: #1a1a2e;
                position: relative;
            }
            .mfm-video-preview-container {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
                position: relative;
            }
            .mfm-video-preview-wrapper {
                position: relative;
                max-width: 100%;
                max-height: 100%;
            }
            .mfm-video-preview-wrapper video {
                max-width: 100%;
                max-height: 380px;
                display: block;
                background: #000;
                border-radius: 4px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            }

            /* Crop Overlay */
            .mfm-crop-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                display: none;
            }
            .mfm-crop-overlay.active {
                display: block;
                pointer-events: auto;
                cursor: crosshair;
            }
            .mfm-crop-box {
                position: absolute;
                border: 2px dashed #00ff88;
                background: rgba(0, 255, 136, 0.1);
                display: none;
            }
            .mfm-crop-box.visible {
                display: block;
            }

            /* Crop Shadow */
            .mfm-crop-shadow {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                display: none;
            }
            .mfm-crop-shadow.visible {
                display: block;
            }
            .mfm-crop-shadow-top,
            .mfm-crop-shadow-bottom,
            .mfm-crop-shadow-left,
            .mfm-crop-shadow-right {
                position: absolute;
                background: rgba(0, 0, 0, 0.6);
            }

            /* Blur Overlay */
            .mfm-blur-overlay {
                position: absolute;
                border: 2px solid #ff6b6b;
                background: repeating-linear-gradient(
                    45deg,
                    rgba(255, 107, 107, 0.2),
                    rgba(255, 107, 107, 0.2) 5px,
                    rgba(255, 107, 107, 0.3) 5px,
                    rgba(255, 107, 107, 0.3) 10px
                );
                cursor: move;
                min-width: 40px;
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
            }
            .mfm-blur-overlay .blur-label {
                background: #ff6b6b;
                color: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            .mfm-blur-overlay .delete-blur {
                position: absolute;
                top: -8px;
                right: -8px;
                width: 18px;
                height: 18px;
                background: #ff4444;
                color: #fff;
                border: 2px solid #fff;
                border-radius: 50%;
                cursor: pointer;
                font-size: 12px;
                line-height: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }

            /* Resize Preview */
            .mfm-resize-preview {
                position: absolute;
                border: 2px dashed #4ecdc4;
                background: rgba(78, 205, 196, 0.1);
                pointer-events: none;
                display: none;
                border-radius: 4px;
            }
            .mfm-resize-preview.visible {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .mfm-resize-label {
                background: #4ecdc4;
                color: #fff;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
            }

            /* Preview Info Banner */
            .mfm-preview-info {
                position: absolute;
                top: 15px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(102, 126, 234, 0.95);
                color: #fff;
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 13px;
                z-index: 50;
                display: none;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
            .mfm-preview-info.visible {
                display: block;
            }

            /* Video Info Bar */
            .mfm-video-info {
                padding: 10px 15px;
                background: #16213e;
                color: #a0a0a0;
                font-size: 12px;
                display: flex;
                gap: 25px;
                flex-wrap: wrap;
            }
            .mfm-video-info span {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .mfm-video-info strong {
                color: #fff;
            }

            /* Timeline */
            .mfm-video-timeline {
                height: 110px;
                background: #0f0f23;
                border-top: 1px solid #2a2a4a;
                padding: 10px 15px;
                display: flex;
                flex-direction: column;
            }
            .mfm-timeline-controls {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 10px;
            }
            .mfm-timeline-controls button {
                padding: 6px 12px;
                background: #2a2a4a;
                border: none;
                color: #fff;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.2s;
            }
            .mfm-timeline-controls button:hover {
                background: #3a3a5a;
            }
            .mfm-time-display {
                color: #fff;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                background: #1a1a3a;
                padding: 5px 10px;
                border-radius: 4px;
            }
            .mfm-timeline-track {
                flex: 1;
                background: #1a1a3a;
                border-radius: 6px;
                position: relative;
                cursor: pointer;
                overflow: hidden;
            }
            .mfm-timeline-progress {
                position: absolute;
                top: 0;
                left: 0;
                height: 100%;
                background: rgba(102, 126, 234, 0.3);
                pointer-events: none;
            }
            .mfm-timeline-playhead {
                position: absolute;
                top: 0;
                width: 3px;
                height: 100%;
                background: #ff6b6b;
                cursor: ew-resize;
                z-index: 15;
                border-radius: 2px;
            }
            .mfm-timeline-playhead::after {
                content: '';
                position: absolute;
                top: -4px;
                left: -5px;
                width: 13px;
                height: 13px;
                background: #ff6b6b;
                border-radius: 50%;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }

            /* Trim Handles */
            .mfm-trim-region {
                position: absolute;
                top: 0;
                height: 100%;
                background: rgba(255, 193, 7, 0.15);
                border-top: 3px solid #ffc107;
                border-bottom: 3px solid #ffc107;
                pointer-events: none;
            }
            .mfm-trim-handle {
                position: absolute;
                top: 0;
                width: 12px;
                height: 100%;
                background: #ffc107;
                cursor: ew-resize;
                z-index: 10;
                transition: background 0.2s;
            }
            .mfm-trim-handle:hover {
                background: #ffca28;
            }
            .mfm-trim-handle.start {
                left: 0;
                border-radius: 6px 0 0 6px;
            }
            .mfm-trim-handle.end {
                right: 0;
                border-radius: 0 6px 6px 0;
            }

            /* Side Panel */
            .mfm-video-editor-panel {
                width: 300px;
                background: #fff;
                border-left: 1px solid #e0e0e0;
                overflow-y: auto;
                display: none;
            }
            .mfm-video-editor-panel.active {
                display: block;
            }
            .mfm-panel-section {
                padding: 20px;
                border-bottom: 1px solid #eee;
            }
            .mfm-panel-section h4 {
                margin: 0 0 15px 0;
                font-size: 15px;
                color: #333;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .mfm-panel-row {
                display: flex;
                align-items: center;
                margin-bottom: 12px;
            }
            .mfm-panel-row label {
                width: 65px;
                font-size: 13px;
                color: #666;
            }
            .mfm-panel-row input[type="number"],
            .mfm-panel-row input[type="text"] {
                flex: 1;
                padding: 8px 10px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 13px;
            }
            .mfm-panel-row input:focus {
                border-color: #667eea;
                outline: none;
            }
            .mfm-panel-row .unit {
                margin-left: 6px;
                font-size: 12px;
                color: #999;
                width: 25px;
            }
            .mfm-panel-btn {
                width: 100%;
                padding: 10px;
                margin-top: 10px;
                background: #667eea;
                color: #fff;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: background 0.2s;
            }
            .mfm-panel-btn:hover {
                background: #5a6fd6;
            }
            .mfm-panel-btn.secondary {
                background: #6c757d;
            }
            .mfm-panel-btn.secondary:hover {
                background: #5a6268;
            }

            /* Preset Buttons */
            .mfm-preset-buttons {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                margin-top: 12px;
            }
            .mfm-preset-btn {
                padding: 6px 12px;
                font-size: 12px;
                background: #f0f2f5;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .mfm-preset-btn:hover {
                background: #e0e0e0;
            }
            .mfm-preset-btn.active {
                background: #667eea;
                color: #fff;
                border-color: #667eea;
            }

            /* Blur List */
            .mfm-blur-list {
                max-height: 150px;
                overflow-y: auto;
                margin-top: 10px;
            }
            .mfm-blur-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
                background: #f8f9fa;
                border-radius: 4px;
                margin-bottom: 6px;
            }
            .mfm-blur-item span {
                font-size: 13px;
            }
            .mfm-blur-item button {
                padding: 4px 8px;
                font-size: 11px;
                background: #dc3545;
                color: #fff;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }

            /* Footer */
            .mfm-video-editor-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: #fff;
                border-top: 1px solid #e0e0e0;
            }
            .mfm-video-editor-footer .footer-left {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .mfm-video-editor-footer .footer-right {
                display: flex;
                gap: 10px;
            }
            .mfm-video-editor-footer label {
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 6px;
                cursor: pointer;
            }
            .mfm-video-editor-footer select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 13px;
            }
            .mfm-video-editor-footer .mfm-btn {
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 500;
            }
            .mfm-video-editor-footer .mfm-btn.primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border: none;
            }
            .mfm-video-editor-footer .mfm-btn.primary:hover {
                opacity: 0.9;
            }

            /* Loading Overlay */
            .mfm-video-loading {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 15, 35, 0.95);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                z-index: 100;
                color: #fff;
            }
            .mfm-video-loading.hidden {
                display: none;
            }
            .mfm-loading-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid rgba(255,255,255,0.1);
                border-top-color: #667eea;
                border-radius: 50%;
                animation: mfm-spin 1s linear infinite;
            }
            @keyframes mfm-spin {
                to { transform: rotate(360deg); }
            }
            .mfm-loading-text {
                margin-top: 20px;
                font-size: 16px;
                text-align: center;
            }
            .mfm-loading-progress {
                width: 250px;
                height: 6px;
                background: rgba(255,255,255,0.1);
                border-radius: 3px;
                margin-top: 15px;
                overflow: hidden;
            }
            .mfm-loading-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #667eea, #764ba2);
                width: 0%;
                transition: width 0.3s;
            }
            .mfm-loading-subtext {
                margin-top: 10px;
                font-size: 12px;
                color: #888;
            }
        `;

        var style = document.createElement('style');
        style.id = 'mfm-video-editor-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    // =====================================================
    // FFMPEG WASM HANDLER (Self-hosted)
    // =====================================================

    var ffmpegInstance = null;
    var ffmpegLoaded = false;
    var ffmpegLoading = false;

    /**
     * Detect the base path for FFmpeg files
     * @returns {string}
     */
    function detectFFmpegPath() {
        if (FFMPEG_CONFIG.basePath) return FFMPEG_CONFIG.basePath;

        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            if (src.indexOf('myfilemanager-video-editor') !== -1) {
                var basePath = src.substring(0, src.lastIndexOf('/') + 1);
                return basePath + 'ffmpeg/';
            }
        }
        return './ffmpeg/';
    }

    /**
     * Convert URL to Blob URL for CORS bypass
     * @param {string} url - URL to fetch
     * @param {string} type - MIME type
     * @returns {Promise<string>}
     */
    async function toBlobURL(url, type) {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to fetch ' + url);
        const blob = await response.blob();
        return URL.createObjectURL(new Blob([await blob.arrayBuffer()], { type }));
    }

    async function loadFFmpeg(onProgress) {
        if (ffmpegLoaded && ffmpegInstance) return ffmpegInstance;

        ffmpegLoading = true;
        onProgress = onProgress || (() => { });
        onProgress(5, 'Loading FFmpegCore...');

        try {
            const basePath = detectFFmpegPath();

            if (!window.createFFmpegCore) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = basePath + 'ffmpeg-core.js';
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            const createFFmpegCore = window.createFFmpegCore;
            if (!createFFmpegCore) throw new Error('createFFmpegCore not found');

            const coreInstance = await createFFmpegCore({
                locateFile: (path) => basePath + path,
                printErr: (message) => console.error('[FFmpeg ERR]', message),
                print: (message) => console.log('[FFmpeg]', message),
            });

            if (!coreInstance.FS) throw new Error('FS not available in FFmpegCore');

            onProgress(30, 'FFmpegCore ready!');

            // Proxy object with wrappers for your saveVideo
            ffmpegInstance = {
                core: coreInstance,
                FS: coreInstance.FS,

                writeFile: (path, data) => coreInstance.FS.writeFile(path, data),
                readFile: (path) => coreInstance.FS.readFile(path),
                deleteFile: (path) => coreInstance.FS.unlink(path),

                run: async function (args) {
                    const DEFAULT_ARGS = ['./ffmpeg', '-nostdin', '-y'];
                    const fullArgs = [...DEFAULT_ARGS, ...args];
                    console.log('FFmpeg full args:', fullArgs);
                    const ptr = coreInstance.stringsToPtr(fullArgs);
                    coreInstance._ffmpeg(fullArgs.length, ptr);
                    if (coreInstance.ret !== 0) {
                        throw new Error('FFmpeg execution failed with exit code: ' + coreInstance.ret);
                    }
                    return coreInstance.ret;
                }
            };

            ffmpegLoaded = true;
            ffmpegLoading = false;
            console.log('FFmpegCore + wrappers ready!');
            return ffmpegInstance;

        } catch (error) {
            ffmpegLoading = false;
            console.error('loadFFmpeg failed:', error);
            throw error;
        }
    }


    // =====================================================
    // VIDEO EDITOR PLUGIN
    // =====================================================

    function VideoEditorPlugin(fm) {
        injectStyles();

        var plugin = {
            name: 'VideoEditorPlugin',
            version: '3.0.0',
            fileManager: fm,
            state: {
                videoFile: null,
                videoUrl: null,
                videoElement: null,
                videoBlob: null,
                duration: 0,
                width: 0,
                height: 0,
                trimStart: 0,
                trimEnd: 0,
                cropX: 0,
                cropY: 0,
                cropWidth: 0,
                cropHeight: 0,
                resizeWidth: 0,
                resizeHeight: 0,
                blurRegions: [],
                currentTool: null,
                processing: false
            }
        };

        /**
         * Check if file is a video
         */
        function isVideo(files, fileManager) {
            if (!files || files.length !== 1) return false;
            var file = files[0];
            if (file.mime === 'directory') return false;
            if (file.mime && file.mime.startsWith('video/')) return true;
            var videoExt = ['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv', 'm4v', 'ogv'];
            var ext = file.name.split('.').pop().toLowerCase();
            return videoExt.indexOf(ext) !== -1;
        }

        /**
         * Open video editor
         */
        // Open video editor - FIXED: Preserve existing URL parameters
        function openVideoEditor(files, fileManager) {
            if (!files || files.length === 0) {
                alert('Please select a video file');
                return;
            }

            var file = files[0];

            // Build URL preserving existing parameters (like downloadFile does)
            var baseUrl = fileManager.options.downloadUrl || fileManager.options.url;

            // DON'T remove existing params! Extract base and existing params
            var urlParts = baseUrl.split('?');
            var cleanBaseUrl = urlParts[0];
            var existingParams = [];

            // Preserve existing URL parameters (token1, token2, etc.)
            if (urlParts.length > 1) {
                var queryString = urlParts[1];
                var pairs = queryString.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    var key = pair[0];
                    // Keep all existing params
                    existingParams.push(pairs[i]);
                }
            }

            // Add new params for download
            var newParams = [
                'cmd=download',
                'target=' + encodeURIComponent(file.hash)
            ];

            if (fileManager.options.token) {
                newParams.push('token=' + encodeURIComponent(fileManager.options.token));
            }

            newParams.push('t=' + Date.now());

            // Combine existing and new params
            var allParams = existingParams.concat(newParams);
            var videoUrl = cleanBaseUrl + '?' + allParams.join('&');

            console.log('VideoEditor: Video URL =', videoUrl); // Debug

            // Reset state
            plugin.state = {
                videoFile: file,
                videoUrl: videoUrl,
                videoElement: null,
                videoBlob: null,
                duration: 0,
                width: 0,
                height: 0,
                trimStart: 0,
                trimEnd: 0,
                cropX: 0,
                cropY: 0,
                cropWidth: 0,
                cropHeight: 0,
                resizeWidth: 0,
                resizeHeight: 0,
                blurRegions: [],
                currentTool: null,
                processing: false
            };

            var modalHtml = buildEditorModal(file, fileManager);
            fileManager.showModal('Video Editor', modalHtml);

            setTimeout(function () {
                initializeEditor(fileManager, file, videoUrl);
            }, 100);
        }

        /**
         * Build modal HTML
         */
        function buildEditorModal(file, fileManager) {
            var html = '<div class="mfm-video-editor-modal">';
            html += '<div class="mfm-video-editor-container">';

            // Header
            html += '<div class="mfm-video-editor-header">';
            html += '<h3>🎬 ' + fileManager.escapeHtml(file.name) + '</h3>';
            html += '<button class="mfm-video-editor-close" title="Close">×</button>';
            html += '</div>';

            // Body
            html += '<div class="mfm-video-editor-body">';

            // Toolbar
            html += '<div class="mfm-video-editor-toolbar">';
            html += '<button class="mfm-btn" data-action="trim">✂️ Trim</button>';
            html += '<button class="mfm-btn" data-action="crop">⬜ Crop</button>';
            html += '<button class="mfm-btn" data-action="resize">↔️ Resize</button>';
            html += '<div class="mfm-toolbar-separator"></div>';
            html += '<button class="mfm-btn" data-action="add-blur">🔲 Blur</button>';
            html += '<div class="mfm-toolbar-separator"></div>';
            html += '<button class="mfm-btn" data-action="reset">🔄 Reset</button>';
            html += '</div>';

            // Main
            html += '<div class="mfm-video-editor-main">';

            // Preview
            html += '<div class="mfm-video-editor-preview">';
            html += '<div class="mfm-video-loading hidden" id="video-loading">';
            html += '<div class="mfm-loading-spinner"></div>';
            html += '<div class="mfm-loading-text">Loading...</div>';
            html += '<div class="mfm-loading-progress"><div class="mfm-loading-progress-bar"></div></div>';
            html += '<div class="mfm-loading-subtext" id="loading-subtext"></div>';
            html += '</div>';
            html += '<div class="mfm-preview-info" id="preview-info"></div>';
            html += '<div class="mfm-video-preview-container">';
            html += '<div class="mfm-video-preview-wrapper" id="video-wrapper">';
            html += '<video id="video-preview" preload="metadata"></video>';
            html += '<div class="mfm-crop-overlay" id="crop-overlay"><div class="mfm-crop-box" id="crop-box"></div></div>';
            html += '<div class="mfm-crop-shadow" id="crop-shadow">';
            html += '<div class="mfm-crop-shadow-top"></div><div class="mfm-crop-shadow-bottom"></div>';
            html += '<div class="mfm-crop-shadow-left"></div><div class="mfm-crop-shadow-right"></div>';
            html += '</div>';
            html += '<div class="mfm-resize-preview" id="resize-preview"><span class="mfm-resize-label"></span></div>';
            html += '</div></div>';

            // Info bar
            html += '<div class="mfm-video-info">';
            html += '<span>⏱️ Duration: <strong id="info-duration">--:--</strong></span>';
            html += '<span>📐 Resolution: <strong id="info-resolution">--</strong></span>';
            html += '<span>💾 Size: <strong id="info-size">--</strong></span>';
            html += '</div>';

            // Timeline
            html += '<div class="mfm-video-timeline">';
            html += '<div class="mfm-timeline-controls">';
            html += '<button id="btn-play-pause" title="Play/Pause">▶</button>';
            html += '<button id="btn-step-back" title="Back">⏪</button>';
            html += '<button id="btn-step-forward" title="Forward">⏩</button>';
            html += '<span class="mfm-time-display"><span id="current-time">00:00</span> / <span id="total-time">00:00</span></span>';
            html += '</div>';
            html += '<div class="mfm-timeline-track" id="timeline-track">';
            html += '<div class="mfm-trim-region" id="trim-region"></div>';
            html += '<div class="mfm-trim-handle start" id="trim-start"></div>';
            html += '<div class="mfm-trim-handle end" id="trim-end"></div>';
            html += '<div class="mfm-timeline-progress" id="timeline-progress"></div>';
            html += '<div class="mfm-timeline-playhead" id="timeline-playhead"></div>';
            html += '</div></div>';
            html += '</div>';

            // Panel
            html += '<div class="mfm-video-editor-panel" id="editor-panel">';

            // Trim settings
            html += '<div class="mfm-panel-section" id="trim-settings" style="display:none">';
            html += '<h4>✂️ Trim Video</h4>';
            html += '<div class="mfm-panel-row"><label>Start:</label><input type="text" id="trim-start-input" value="00:00"></div>';
            html += '<div class="mfm-panel-row"><label>End:</label><input type="text" id="trim-end-input" value="00:00"></div>';
            html += '<div class="mfm-panel-row"><label>Duration:</label><strong id="trim-length">00:00</strong></div>';
            html += '<button class="mfm-panel-btn" id="btn-set-trim-start">▶ Set Start Here</button>';
            html += '<button class="mfm-panel-btn secondary" id="btn-set-trim-end">⏹ Set End Here</button>';
            html += '</div>';

            // Crop settings
            html += '<div class="mfm-panel-section" id="crop-settings" style="display:none">';
            html += '<h4>⬜ Crop Video</h4>';
            html += '<div class="mfm-panel-row"><label>X:</label><input type="number" id="crop-x" value="0" min="0"><span class="unit">px</span></div>';
            html += '<div class="mfm-panel-row"><label>Y:</label><input type="number" id="crop-y" value="0" min="0"><span class="unit">px</span></div>';
            html += '<div class="mfm-panel-row"><label>Width:</label><input type="number" id="crop-width" min="1"><span class="unit">px</span></div>';
            html += '<div class="mfm-panel-row"><label>Height:</label><input type="number" id="crop-height" min="1"><span class="unit">px</span></div>';
            html += '<button class="mfm-panel-btn" id="btn-draw-crop">✏️ Draw Crop Area</button>';
            html += '<div class="mfm-preset-buttons">';
            html += '<button class="mfm-preset-btn" data-crop="16:9">16:9</button>';
            html += '<button class="mfm-preset-btn" data-crop="4:3">4:3</button>';
            html += '<button class="mfm-preset-btn" data-crop="1:1">1:1</button>';
            html += '<button class="mfm-preset-btn" data-crop="9:16">9:16</button>';
            html += '<button class="mfm-preset-btn" data-crop="reset">Reset</button>';
            html += '</div></div>';

            // Resize settings
            html += '<div class="mfm-panel-section" id="resize-settings" style="display:none">';
            html += '<h4>↔️ Resize Video</h4>';
            html += '<div class="mfm-panel-row"><label>Width:</label><input type="number" id="resize-width" min="1"><span class="unit">px</span></div>';
            html += '<div class="mfm-panel-row"><label>Height:</label><input type="number" id="resize-height" min="1"><span class="unit">px</span></div>';
            html += '<div class="mfm-panel-row"><label><input type="checkbox" id="resize-ratio" checked> Keep aspect ratio</label></div>';
            html += '<div class="mfm-preset-buttons">';
            html += '<button class="mfm-preset-btn" data-resize="1920x1080">1080p</button>';
            html += '<button class="mfm-preset-btn" data-resize="1280x720">720p</button>';
            html += '<button class="mfm-preset-btn" data-resize="854x480">480p</button>';
            html += '<button class="mfm-preset-btn" data-resize="640x360">360p</button>';
            html += '<button class="mfm-preset-btn" data-resize="reset">Original</button>';
            html += '</div></div>';

            // Blur settings
            html += '<div class="mfm-panel-section" id="blur-settings" style="display:none">';
            html += '<h4>🎭 Blur Regions</h4>';
            html += '<p style="font-size:12px;color:#888;margin-bottom:10px">Draw rectangles and set timing</p>';

            // timing controls
            html += '<div class="mfm-panel-row"><label>Start</label><input type="text" id="blur-start-time" value="00:00" style="flex:1"></div>';
            html += '<div class="mfm-panel-row"><label>End</label><input type="text" id="blur-end-time" value="00:00" style="flex:1"></div>';
            html += '<button class="mfm-panel-btn" id="btn-set-blur-timing">Set Current Time</button>';

            html += '<div class="mfm-blur-list" id="blur-list"><p style="color:#999;font-size:12px;text-align:center">No blur regions</p></div>';
            html += '<button class="mfm-panel-btn secondary" id="btn-clear-blurs">Clear All</button>';
            html += '</div>';

            html += '</div>'; // panel
            html += '</div>'; // main
            html += '</div>'; // body

            // Footer
            html += '<div class="mfm-video-editor-footer">';
            html += '<div class="footer-left">';
            html += '<label><input type="checkbox" id="video-overwrite" checked> Overwrite original</label>';
            html += '<label>Format: <select id="output-format">';
            html += '<option value="mp4">MP4</option>';
            html += '<option value="webm">WebM</option>';
            html += '</select></label>';
            html += '</div>';
            html += '<div class="footer-right">';
            html += '<button class="mfm-btn primary" data-action="save">💾 Process & Save</button>';
            html += '<button class="mfm-btn" data-action="cancel">Cancel</button>';
            html += '</div></div>';

            html += '</div></div>';
            return html;
        }

        /**
         * Initialize editor
         */
        async function initializeEditor(fileManager, file, videoUrl) {
            var video = document.getElementById('video-preview');
            var loading = document.getElementById('video-loading');
            if (!video) return;

            loading.classList.remove('hidden');
            loading.querySelector('.mfm-loading-text').textContent = 'Loading video...';

            try {
                // Fetch video with proper authentication (same as downloadFile)
                var fetchOptions = {
                    method: 'GET',
                    credentials: 'same-origin'
                };

                // Add Authorization header if token exists
                if (fileManager.options.token) {
                    fetchOptions.headers = {
                        'Authorization': 'Bearer ' + fileManager.options.token
                    };
                }

                var response = await fetch(videoUrl, fetchOptions);

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                // Create Blob URL from response
                var blob = await response.blob();
                var blobUrl = URL.createObjectURL(blob);

                // Store blob for later use in processing
                plugin.state.videoBlob = blob;

                video.onloadedmetadata = function () {
                    plugin.state.videoElement = video;
                    plugin.state.duration = video.duration;
                    plugin.state.width = video.videoWidth;
                    plugin.state.height = video.videoHeight;
                    plugin.state.trimStart = 0;
                    plugin.state.trimEnd = video.duration;
                    plugin.state.cropWidth = video.videoWidth;
                    plugin.state.cropHeight = video.videoHeight;
                    plugin.state.resizeWidth = video.videoWidth;
                    plugin.state.resizeHeight = video.videoHeight;
                    updateVideoInfo(file);
                    updateAllInputs();
                    loading.classList.add('hidden');
                    video.controls = false;
                    video.removeAttribute('controls');
                };

                video.onerror = function () {
                    loading.querySelector('.mfm-loading-text').textContent = 'Failed to load video';
                };

                // Use Blob URL instead of direct URL
                video.src = blobUrl;

                // Store original URL for reference
                plugin.state.videoUrl = videoUrl;
                plugin.state.videoBlobUrl = blobUrl;

            } catch (error) {
                console.error('VideoEditor: Failed to load video', error);
                loading.querySelector('.mfm-loading-text').textContent = 'Failed to load video: ' + error.message;
            }

            bindEvents(fileManager, file);
        }

        /**
         * Update video info display
         */
        function updateVideoInfo(file) {
            document.getElementById('info-duration').textContent = formatTime(plugin.state.duration);
            document.getElementById('info-resolution').textContent = plugin.state.width + '×' + plugin.state.height;
            document.getElementById('info-size').textContent = formatSize(file.size || 0);
            document.getElementById('total-time').textContent = formatTime(plugin.state.duration);
        }

        /**
         * Update all input fields
         */
        function updateAllInputs() {
            document.getElementById('trim-start-input').value = formatTime(plugin.state.trimStart);
            document.getElementById('trim-end-input').value = formatTime(plugin.state.trimEnd);
            document.getElementById('trim-length').textContent = formatTime(plugin.state.trimEnd - plugin.state.trimStart);
            document.getElementById('crop-x').value = plugin.state.cropX;
            document.getElementById('crop-y').value = plugin.state.cropY;
            document.getElementById('crop-width').value = plugin.state.cropWidth;
            document.getElementById('crop-height').value = plugin.state.cropHeight;
            document.getElementById('resize-width').value = plugin.state.resizeWidth;
            document.getElementById('resize-height').value = plugin.state.resizeHeight;
            updateTrimVisual();
        }

        /**
         * Update trim visual
         */
        function updateTrimVisual() {
            var duration = plugin.state.duration;
            if (duration <= 0) return;
            var startPct = (plugin.state.trimStart / duration) * 100;
            var endPct = (plugin.state.trimEnd / duration) * 100;
            document.getElementById('trim-start').style.left = startPct + '%';
            document.getElementById('trim-end').style.left = endPct + '%';
            document.getElementById('trim-region').style.left = startPct + '%';
            document.getElementById('trim-region').style.width = (endPct - startPct) + '%';
        }

        /**
         * Bind all events
         */
        function bindEvents(fileManager, file) {
            var video = document.getElementById('video-preview');
            var timeline = document.getElementById('timeline-track');

            // Close
            document.querySelector('.mfm-video-editor-close').onclick = () => fileManager.closeModal();

            // Toolbar
            document.querySelector('.mfm-video-editor-toolbar').onclick = function (e) {
                var btn = e.target.closest('[data-action]');
                if (btn) handleToolbar(btn.getAttribute('data-action'), btn);
            };

            // Video time update
            video.ontimeupdate = function () {
                var pct = (video.currentTime / video.duration) * 100;
                document.getElementById('timeline-playhead').style.left = pct + '%';
                document.getElementById('timeline-progress').style.width = pct + '%';
                document.getElementById('current-time').textContent = formatTime(video.currentTime);
            };

            // Play/pause sync
            video.onplay = () => document.getElementById('btn-play-pause').textContent = '⏸';
            video.onpause = () => document.getElementById('btn-play-pause').textContent = '▶';

            // Controls
            document.getElementById('btn-play-pause').onclick = () => video.paused ? video.play() : video.pause();
            document.getElementById('btn-step-back').onclick = () => { video.pause(); video.currentTime = Math.max(0, video.currentTime - 1 / 30); };
            document.getElementById('btn-step-forward').onclick = () => { video.pause(); video.currentTime = Math.min(video.duration, video.currentTime + 1 / 30); };

            // Timeline seek
            timeline.onclick = function (e) {
                if (e.target.classList.contains('mfm-trim-handle')) return;
                var rect = timeline.getBoundingClientRect();
                video.currentTime = ((e.clientX - rect.left) / rect.width) * video.duration;
            };

            // Trim handles
            setupTrimHandles();

            // Trim buttons
            document.getElementById('btn-set-trim-start').onclick = function () {
                plugin.state.trimStart = video.currentTime;
                if (plugin.state.trimStart >= plugin.state.trimEnd) plugin.state.trimEnd = Math.min(plugin.state.duration, plugin.state.trimStart + 0.5);
                updateAllInputs();
            };
            document.getElementById('btn-set-trim-end').onclick = function () {
                plugin.state.trimEnd = video.currentTime;
                if (plugin.state.trimEnd <= plugin.state.trimStart) plugin.state.trimStart = Math.max(0, plugin.state.trimEnd - 0.5);
                updateAllInputs();
            };

            // Trim inputs
            document.getElementById('trim-start-input').onchange = function () {
                plugin.state.trimStart = parseTime(this.value);
                updateAllInputs();
            };
            document.getElementById('trim-end-input').onchange = function () {
                plugin.state.trimEnd = parseTime(this.value);
                updateAllInputs();
            };

            // Crop inputs
            ['crop-x', 'crop-y', 'crop-width', 'crop-height'].forEach(id => {
                document.getElementById(id).onchange = function () {
                    var key = id.replace('crop-', 'crop').replace('-', '');
                    if (id === 'crop-x') plugin.state.cropX = parseInt(this.value) || 0;
                    else if (id === 'crop-y') plugin.state.cropY = parseInt(this.value) || 0;
                    else if (id === 'crop-width') plugin.state.cropWidth = parseInt(this.value) || plugin.state.width;
                    else if (id === 'crop-height') plugin.state.cropHeight = parseInt(this.value) || plugin.state.height;
                    updateCropPreview();
                };
            });

            // Crop presets
            document.querySelectorAll('[data-crop]').forEach(btn => {
                btn.onclick = function () {
                    var preset = this.getAttribute('data-crop');
                    if (preset === 'reset') {
                        plugin.state.cropX = plugin.state.cropY = 0;
                        plugin.state.cropWidth = plugin.state.width;
                        plugin.state.cropHeight = plugin.state.height;
                    } else {
                        var [w, h] = preset.split(':').map(Number);
                        applyCropPreset(w, h);
                    }
                    updateAllInputs();
                    updateCropPreview();
                };
            });

            document.getElementById('btn-draw-crop').onclick = enableCropDrawing;

            // Resize inputs
            var resizeW = document.getElementById('resize-width');
            var resizeH = document.getElementById('resize-height');
            var keepRatio = document.getElementById('resize-ratio');

            resizeW.onchange = function () {
                plugin.state.resizeWidth = parseInt(this.value) || plugin.state.width;
                if (keepRatio.checked) {
                    plugin.state.resizeHeight = Math.round(plugin.state.resizeWidth / (plugin.state.width / plugin.state.height));
                    resizeH.value = plugin.state.resizeHeight;
                }
                updateResizePreview();
            };
            resizeH.onchange = function () {
                plugin.state.resizeHeight = parseInt(this.value) || plugin.state.height;
                if (keepRatio.checked) {
                    plugin.state.resizeWidth = Math.round(plugin.state.resizeHeight * (plugin.state.width / plugin.state.height));
                    resizeW.value = plugin.state.resizeWidth;
                }
                updateResizePreview();
            };

            // Resize presets
            document.querySelectorAll('[data-resize]').forEach(btn => {
                btn.onclick = function () {
                    var preset = this.getAttribute('data-resize');
                    if (preset === 'reset') {
                        plugin.state.resizeWidth = plugin.state.width;
                        plugin.state.resizeHeight = plugin.state.height;
                    } else {
                        var [w, h] = preset.split('x').map(Number);
                        plugin.state.resizeWidth = w;
                        plugin.state.resizeHeight = h;
                    }
                    updateAllInputs();
                    updateResizePreview();
                };
            });

            // Blur timing inputs
            document.getElementById('blur-start-time').onchange = function () {
                // time format
            };

            document.getElementById('blur-end-time').onchange = function () {
                // time format
            };

            document.getElementById('btn-set-blur-timing').onclick = function () {
                var currentTime = video.currentTime;
                document.getElementById('blur-start-time').value = formatTime(currentTime);
                document.getElementById('blur-end-time').value = formatTime(currentTime + 5); // 5 sec default
                showInfo('⏱️ Timing set: ' + formatTime(currentTime));
            };

            // Footer
            document.querySelector('.mfm-video-editor-footer').onclick = function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                if (btn.getAttribute('data-action') === 'save') saveVideo(fileManager, file);
                else if (btn.getAttribute('data-action') === 'cancel') fileManager.closeModal();
            };
        }

        /**
         * Handle toolbar actions
         */
        function handleToolbar(action, btn) {
            var panel = document.getElementById('editor-panel');
            var isActive = btn.classList.contains('active');

            // Reset all
            document.querySelectorAll('.mfm-video-editor-toolbar .mfm-btn').forEach(b => b.classList.remove('active'));
            ['trim-settings', 'crop-settings', 'resize-settings', 'blur-settings'].forEach(id => {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            document.getElementById('crop-overlay').classList.remove('active');
            document.getElementById('crop-shadow').classList.remove('visible');
            document.getElementById('resize-preview').classList.remove('visible');

            if (isActive && action !== 'reset') {
                panel.classList.remove('active');
                plugin.state.currentTool = null;
                return;
            }

            if (action === 'reset') {
                resetAll();
                panel.classList.remove('active');
                showInfo('All changes reset');
                return;
            }

            btn.classList.add('active');
            panel.classList.add('active');
            plugin.state.currentTool = action;

            if (action === 'trim') document.getElementById('trim-settings').style.display = 'block';
            else if (action === 'crop') { document.getElementById('crop-settings').style.display = 'block'; updateCropPreview(); }
            else if (action === 'resize') { document.getElementById('resize-settings').style.display = 'block'; updateResizePreview(); }
            else if (action === 'add-blur') { document.getElementById('blur-settings').style.display = 'block'; enableBlurDrawing(); }
        }

        /**
         * Setup trim handles
         */
        function setupTrimHandles() {
            var timeline = document.getElementById('timeline-track');
            var isDragging = null;

            function onMove(e) {
                if (!isDragging) return;
                var rect = timeline.getBoundingClientRect();
                var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                var time = pct * plugin.state.duration;
                if (isDragging === 'start') plugin.state.trimStart = Math.min(time, plugin.state.trimEnd - 0.1);
                else plugin.state.trimEnd = Math.max(time, plugin.state.trimStart + 0.1);
                updateAllInputs();
            }

            function onUp() {
                isDragging = null;
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }

            document.getElementById('trim-start').onmousedown = function (e) {
                e.preventDefault(); e.stopPropagation();
                isDragging = 'start';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            };
            document.getElementById('trim-end').onmousedown = function (e) {
                e.preventDefault(); e.stopPropagation();
                isDragging = 'end';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            };
        }

        /**
         * Apply crop preset
         */
        function applyCropPreset(w, h) {
            var ratio = w / h;
            var videoRatio = plugin.state.width / plugin.state.height;
            if (ratio > videoRatio) {
                plugin.state.cropWidth = plugin.state.width;
                plugin.state.cropHeight = Math.round(plugin.state.width / ratio);
                plugin.state.cropX = 0;
                plugin.state.cropY = Math.round((plugin.state.height - plugin.state.cropHeight) / 2);
            } else {
                plugin.state.cropHeight = plugin.state.height;
                plugin.state.cropWidth = Math.round(plugin.state.height * ratio);
                plugin.state.cropY = 0;
                plugin.state.cropX = Math.round((plugin.state.width - plugin.state.cropWidth) / 2);
            }
        }

        /**
         * Update crop preview
         */
        function updateCropPreview() {
            var video = document.getElementById('video-preview');
            var shadow = document.getElementById('crop-shadow');
            var hasCrop = plugin.state.cropX > 0 || plugin.state.cropY > 0 ||
                plugin.state.cropWidth < plugin.state.width || plugin.state.cropHeight < plugin.state.height;

            if (!hasCrop || plugin.state.currentTool !== 'crop') {
                shadow.classList.remove('visible');
                return;
            }

            shadow.classList.add('visible');
            var scaleX = video.offsetWidth / plugin.state.width;
            var scaleY = video.offsetHeight / plugin.state.height;
            var cx = plugin.state.cropX * scaleX, cy = plugin.state.cropY * scaleY;
            var cw = plugin.state.cropWidth * scaleX, ch = plugin.state.cropHeight * scaleY;
            var vw = video.offsetWidth, vh = video.offsetHeight;

            shadow.querySelector('.mfm-crop-shadow-top').style.cssText = 'top:0;left:0;width:100%;height:' + cy + 'px';
            shadow.querySelector('.mfm-crop-shadow-bottom').style.cssText = 'bottom:0;left:0;width:100%;height:' + (vh - cy - ch) + 'px';
            shadow.querySelector('.mfm-crop-shadow-left').style.cssText = 'top:' + cy + 'px;left:0;width:' + cx + 'px;height:' + ch + 'px';
            shadow.querySelector('.mfm-crop-shadow-right').style.cssText = 'top:' + cy + 'px;right:0;width:' + (vw - cx - cw) + 'px;height:' + ch + 'px';

            showInfo('Crop: ' + plugin.state.cropWidth + '×' + plugin.state.cropHeight);
        }

        /**
         * Update resize preview
         */
        function updateResizePreview() {
            var video = document.getElementById('video-preview');
            var preview = document.getElementById('resize-preview');
            var hasResize = plugin.state.resizeWidth !== plugin.state.width || plugin.state.resizeHeight !== plugin.state.height;

            if (!hasResize || plugin.state.currentTool !== 'resize') {
                preview.classList.remove('visible');
                return;
            }

            preview.classList.add('visible');
            var scale = Math.min(video.offsetWidth / plugin.state.width, video.offsetHeight / plugin.state.height);
            var pw = plugin.state.resizeWidth * scale, ph = plugin.state.resizeHeight * scale;
            preview.style.left = (video.offsetWidth - pw) / 2 + 'px';
            preview.style.top = (video.offsetHeight - ph) / 2 + 'px';
            preview.style.width = pw + 'px';
            preview.style.height = ph + 'px';
            preview.querySelector('.mfm-resize-label').textContent = plugin.state.resizeWidth + '×' + plugin.state.resizeHeight;
            showInfo('Resize: ' + plugin.state.resizeWidth + '×' + plugin.state.resizeHeight);
        }

        /**
         * Enable crop drawing
         */
        function enableCropDrawing() {
            var overlay = document.getElementById('crop-overlay');
            var cropBox = document.getElementById('crop-box');
            var video = document.getElementById('video-preview');
            overlay.classList.add('active');
            var drawing = false, startX, startY;

            overlay.onmousedown = function (e) {
                if (e.target !== overlay) return;
                drawing = true;
                var rect = overlay.getBoundingClientRect();
                startX = e.clientX - rect.left;
                startY = e.clientY - rect.top;
                cropBox.style.left = startX + 'px';
                cropBox.style.top = startY + 'px';
                cropBox.style.width = '0';
                cropBox.style.height = '0';
                cropBox.classList.add('visible');
            };
            overlay.onmousemove = function (e) {
                if (!drawing) return;
                var rect = overlay.getBoundingClientRect();
                var x = Math.min(startX, e.clientX - rect.left);
                var y = Math.min(startY, e.clientY - rect.top);
                var w = Math.abs(e.clientX - rect.left - startX);
                var h = Math.abs(e.clientY - rect.top - startY);
                cropBox.style.left = x + 'px';
                cropBox.style.top = y + 'px';
                cropBox.style.width = w + 'px';
                cropBox.style.height = h + 'px';
            };
            overlay.onmouseup = function () {
                if (!drawing) return;
                drawing = false;
                var scaleX = plugin.state.width / video.offsetWidth;
                var scaleY = plugin.state.height / video.offsetHeight;
                plugin.state.cropX = Math.round(parseInt(cropBox.style.left) * scaleX);
                plugin.state.cropY = Math.round(parseInt(cropBox.style.top) * scaleY);
                plugin.state.cropWidth = Math.max(10, Math.round(parseInt(cropBox.style.width) * scaleX));
                plugin.state.cropHeight = Math.max(10, Math.round(parseInt(cropBox.style.height) * scaleY));
                overlay.classList.remove('active');
                cropBox.classList.remove('visible');
                updateAllInputs();
                updateCropPreview();
            };
            showInfo('Draw crop area on video');
        }

        /**
         * Enable blur drawing
         */
        function enableBlurDrawing() {
            var wrapper = document.getElementById('video-wrapper');
            var video = document.getElementById('video-preview');
            var drawing = false, startX, startY, currentBlur;

            wrapper.onmousedown = function (e) {
                if (plugin.state.currentTool !== 'add-blur') return;
                if (e.target.classList.contains('delete-blur') || e.target.classList.contains('mfm-blur-overlay')) return;
                if (e.target !== video && e.target !== wrapper) return;

                drawing = true;
                var rect = wrapper.getBoundingClientRect();
                startX = e.clientX - rect.left;
                startY = e.clientY - rect.top;
                currentBlur = document.createElement('div');
                currentBlur.className = 'mfm-blur-overlay';
                currentBlur.innerHTML = '<span class="blur-label">BLUR</span><button class="delete-blur">×</button>';
                currentBlur.style.left = startX + 'px';
                currentBlur.style.top = startY + 'px';
                wrapper.appendChild(currentBlur);
            };

            document.addEventListener('mousemove', function (e) {
                if (!drawing || !currentBlur) return;
                var rect = wrapper.getBoundingClientRect();
                var x = Math.min(startX, e.clientX - rect.left);
                var y = Math.min(startY, e.clientY - rect.top);
                var w = Math.max(40, Math.abs(e.clientX - rect.left - startX));
                var h = Math.max(40, Math.abs(e.clientY - rect.top - startY));
                currentBlur.style.left = x + 'px';
                currentBlur.style.top = y + 'px';
                currentBlur.style.width = w + 'px';
                currentBlur.style.height = h + 'px';
            });

            document.addEventListener('mouseup', function () {
                if (!drawing || !currentBlur) return;
                drawing = false;
                var w = parseInt(currentBlur.style.width);
                var h = parseInt(currentBlur.style.height);
                if (w < 40 || h < 40) { currentBlur.remove(); currentBlur = null; return; }

                var scaleX = plugin.state.width / video.offsetWidth;
                var scaleY = plugin.state.height / video.offsetHeight;
                var region = {
                    x: Math.round(parseInt(currentBlur.style.left) * scaleX),
                    y: Math.round(parseInt(currentBlur.style.top) * scaleY),
                    width: Math.round(w * scaleX),
                    height: Math.round(h * scaleY),
                    start_time: parseTime(document.getElementById('blur-start-time').value),
                    end_time: parseTime(document.getElementById('blur-end-time').value)   
                };
                plugin.state.blurRegions.push(region);

                var thisBlur = currentBlur;
                thisBlur.querySelector('.delete-blur').onclick = function (ev) {
                    ev.stopPropagation();
                    var idx = plugin.state.blurRegions.indexOf(region);
                    if (idx > -1) plugin.state.blurRegions.splice(idx, 1);
                    thisBlur.remove();
                    updateBlurList();
                };
                updateBlurList();
                currentBlur = null;
            });

            showInfo('Draw blur areas on video');
        }

        /**
         * Update blur list
         */
        function updateBlurList() {
            var list = document.getElementById('blur-list');
            if (plugin.state.blurRegions.length === 0) {
                list.innerHTML = '<p style="color:#999;font-size:12px;text-align:center">No blur regions</p>';
                return;
            }
            list.innerHTML = plugin.state.blurRegions.map(function (r, i) {
                return '<div class="mfm-blur-item">' +
                    '<span>Blur #' + (i + 1) + ' | ' + r.width + '×' + r.height +
                    ' | ⏱️ ' + formatTime(r.start_time) + ' - ' + formatTime(r.end_time) + '</span>' +
                    '<button onclick="this.parentElement.remove()">Remove</button>' +
                    '</div>';
            }).join('');
        }

        /**
         * Show info banner
         */
        function showInfo(msg) {
            var info = document.getElementById('preview-info');
            info.textContent = msg;
            info.classList.add('visible');
            clearTimeout(info._t);
            info._t = setTimeout(() => info.classList.remove('visible'), 2500);
        }

        /**
         * Reset all
         */
        function resetAll() {
            plugin.state.trimStart = 0;
            plugin.state.trimEnd = plugin.state.duration;
            plugin.state.cropX = plugin.state.cropY = 0;
            plugin.state.cropWidth = plugin.state.width;
            plugin.state.cropHeight = plugin.state.height;
            plugin.state.resizeWidth = plugin.state.width;
            plugin.state.resizeHeight = plugin.state.height;
            plugin.state.blurRegions = [];
            document.querySelectorAll('.mfm-blur-overlay').forEach(el => el.remove());
            updateAllInputs();
            updateBlurList();
            document.getElementById('crop-shadow').classList.remove('visible');
            document.getElementById('resize-preview').classList.remove('visible');
        }

        /**
         * Save video using FFmpeg
         */
        async function saveVideo(fileManager, file) {
            if (plugin.state.processing) return;

            var hasChanges = plugin.state.trimStart > 0 || plugin.state.trimEnd < plugin.state.duration ||
                plugin.state.cropX > 0 || plugin.state.cropY > 0 ||
                plugin.state.cropWidth < plugin.state.width || plugin.state.cropHeight < plugin.state.height ||
                plugin.state.resizeWidth !== plugin.state.width || plugin.state.resizeHeight !== plugin.state.height ||
                plugin.state.blurRegions.length > 0;

            if (!hasChanges) {
                alert('No changes to apply');
                return;
            }

            var overwrite = document.getElementById('video-overwrite').checked;
            var format = document.getElementById('output-format').value;
            var loading = document.getElementById('video-loading');
            var progressBar = loading.querySelector('.mfm-loading-progress-bar');
            var loadingText = loading.querySelector('.mfm-loading-text');
            var subText = document.getElementById('loading-subtext');

            plugin.state.processing = true;
            loading.classList.remove('hidden');

            try {
                progressBar.style.width = '10%';
                loadingText.textContent = 'Preparing video data...';
                subText.textContent = 'Sending to server for processing';

                // Build FFmpeg parameters object
                var params = {
                    input: file.hash,
                    trim_start: plugin.state.trimStart,
                    trim_end: plugin.state.trimEnd,
                    crop_x: plugin.state.cropX,
                    crop_y: plugin.state.cropY,
                    crop_w: plugin.state.cropWidth,
                    crop_h: plugin.state.cropHeight,
                    resize_w: plugin.state.resizeWidth,
                    resize_h: plugin.state.resizeHeight,
                    format: format,
                    overwrite: overwrite ? 1 : 0,
                    blur_regions: plugin.state.blurRegions
                };

                // === DEBUG LOGGING ===
                console.log('=== VIDEO PROCESS DEBUG ===');
                console.log('Target hash:', file.hash);
                console.log('Full file object:', file);
                console.log('Params:', params);
                console.log('Connector URL:', fileManager.options.url);
                console.log('Token:', fileManager.options.token ? 'PRESENT' : 'MISSING');

                // Send processing request to server
                progressBar.style.width = '30%';
                loadingText.textContent = 'Processing on server...';

                var formData = new FormData();
                formData.append('cmd', 'video_process');
                formData.append('target', file.hash);
                formData.append('params', JSON.stringify(params));

                if (fileManager.options.token) {
                    formData.append('token', fileManager.options.token);
                }

                var response = await fetch(fileManager.options.url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                // === FULL RESPONSE DEBUG ===
                console.log('Response status:', response.status);
                console.log('Response statusText:', response.statusText);
                console.log('Response headers:', [...response.headers.entries()]);

                var responseText = await response.text();  // Get RAW response first
                console.log('=== RAW SERVER RESPONSE ===');
                console.log(responseText);

                // Try to parse JSON
                var data;
                try {
                    data = JSON.parse(responseText);
                    console.log('=== PARSED JSON ===');
                    console.log(data);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Server returned invalid JSON: ' + responseText.substring(0, 300));
                }

                if (!data.success) {
                    throw new Error(data.error || 'Server processing failed');
                }

                progressBar.style.width = '90%';
                loadingText.textContent = 'Finalizing...';

                // Refresh file list to show processed video
                fileManager.refresh();

                progressBar.style.width = '100%';
                loadingText.textContent = 'Done!';
                subText.textContent = '';

                setTimeout(() => {
                    alert('Video processed successfully!');
                    fileManager.closeModal();
                }, 500);

            } catch (error) {
                console.error('Video processing error:', error);
                alert('Error: ' + error.message);
            } finally {
                plugin.state.processing = false;
                loading.classList.add('hidden');
            }
        }


        /**
         * Upload processed video
         */
        async function uploadVideo(fileManager, file, blob, format, overwrite) {
            if (overwrite) {
                var delForm = new FormData();
                delForm.append('cmd', 'delete');
                delForm.append('targets[]', file.hash);
                if (fileManager.options.token) delForm.append('token', fileManager.options.token);
                await fetch(fileManager.options.url, { method: 'POST', body: delForm, credentials: 'same-origin' });
            }

            var baseName = file.name.replace(/\.[^.]+$/, '');
            var newName = overwrite ? baseName + '.' + format : baseName + '_edited.' + format;

            var uploadForm = new FormData();
            uploadForm.append('cmd', 'upload');
            uploadForm.append('target', btoa(fileManager.state.currentPath));
            uploadForm.append('upload[]', blob, newName);
            if (fileManager.options.token) uploadForm.append('token', fileManager.options.token);

            var response = await fetch(fileManager.options.url, { method: 'POST', body: uploadForm, credentials: 'same-origin' });
            var data = await response.json();
            if (data.error) throw new Error(data.error);
        }

        // Utilities
        function formatTime(s) {
            if (isNaN(s) || s < 0) s = 0;
            var m = Math.floor(s / 60);
            var sec = Math.floor(s % 60);
            return String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }

        function parseTime(str) {
            var parts = str.split(':');
            return (parseInt(parts[0]) || 0) * 60 + (parseInt(parts[1]) || 0);
        }

        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
        }

        // Register
        plugin.contextMenuItems = [{
            action: 'edit-video',
            label: 'Edit Video',
            condition: isVideo,
            handler: openVideoEditor
        }];

        fm.registerPlugin(plugin);
        return plugin;
    }

    // Register globally
    if (!window.MyFileManagerPlugins) window.MyFileManagerPlugins = [];
    window.MyFileManagerPlugins.push(VideoEditorPlugin);

    // Export config function
    window.VideoEditorConfig = function (opts) {
        if (opts.ffmpegPath) FFMPEG_CONFIG.basePath = opts.ffmpegPath;
        if (opts.debug !== undefined) FFMPEG_CONFIG.debug = opts.debug;
    };

    console.log('MyFileManager Video Editor Plugin v3.0.0 loaded (FFmpeg WASM)');

})(window);