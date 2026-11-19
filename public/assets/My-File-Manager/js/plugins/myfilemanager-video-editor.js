/**
 * // js/plugins/myfilemanager-video-editor.js
 * MyFileManager Video Editor Plugin
 * Server-assisted & FFmpeg-ready video editor UI:
 * - Video trimming (cut clips from timeline)
 * - Video cropping (trim borders)
 * - Video resizing (change resolution)
 * - Blur regions (obscure parts of video with timing)
 * - File overwrite option
 *
 * @author Oscar Cosimo / MYETV Team
 * @license MIT
 * @version 3.1.0
 */
(function (window) {
    'use strict';

    // =====================================================
    // CONFIGURATION
    // =====================================================
    var FFMPEG_CONFIG = {
        basePath: null,
        debug: false
    };

    // =====================================================
    // CSS STYLES - Embedded in JavaScript
    // =====================================================
    function injectStyles() {
        if (document.getElementById('mfm-video-editor-styles')) return;

        var css = `
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
            .mfm-video-editor-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                background: #f0f2f5;
            }
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
            .mfm-video-editor-main {
                flex: 1;
                display: flex;
                overflow: hidden;
            }
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
                gap: 6px;
            }
            .mfm-blur-item span {
                font-size: 12px;
            }
            .mfm-blur-item button {
                padding: 4px 8px;
                font-size: 11px;
                background: #dc3545;
                color: #fff;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                flex-shrink: 0;
            }
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
    // VIDEO EDITOR PLUGIN
    // =====================================================

    function VideoEditorPlugin(fm) {
        injectStyles();

        var plugin = {
            name: 'VideoEditorPlugin',
            version: '3.1.0',
            fileManager: fm,
            state: {
                videoFile: null,
                videoElement: null,
                videoBlob: null,
                videoBlobUrl: null,
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
            },
            _cleanupHandlers: []
        };

        function debugLog() {
            if (FFMPEG_CONFIG.debug || (fm && fm.options && fm.options.debug)) {
                console.log.apply(console, ['[VideoEditor]'].concat(Array.prototype.slice.call(arguments)));
            }
        }

        function cleanupEditor(fileManager) {
            plugin._cleanupHandlers.forEach(function (fn) {
                try { fn(); } catch (e) { }
            });
            plugin._cleanupHandlers = [];

            if (plugin.state.videoBlobUrl) {
                URL.revokeObjectURL(plugin.state.videoBlobUrl);
                plugin.state.videoBlobUrl = null;
            }
            plugin.state.videoBlob = null;
            plugin.state.videoElement = null;

            fileManager.closeModal();
        }

        /**
         * Check if file is a video
         */
        function isVideo(files) {
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
        function openVideoEditor(files, fileManager) {
            if (!files || files.length === 0) {
                alert('Please select a video file');
                return;
            }

            var file = files[0];

            // Clean up any previous state
            if (plugin.state.videoBlobUrl) {
                URL.revokeObjectURL(plugin.state.videoBlobUrl);
            }

            plugin.state = {
                videoFile: file,
                videoElement: null,
                videoBlob: null,
                videoBlobUrl: null,
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
                initializeEditor(fileManager, file);
            }, 50);
        }

        /**
         * Build modal HTML
         */
        function buildEditorModal(file, fileManager) {
            var html = '<div class="mfm-video-editor-modal">';
            html += '<div class="mfm-video-editor-container">';

            html += '<div class="mfm-video-editor-header">';
            html += '<h3>🎬 ' + fileManager.escapeHtml(file.name) + '</h3>';
            html += '<button class="mfm-video-editor-close" title="Close">×</button>';
            html += '</div>';

            html += '<div class="mfm-video-editor-body">';

            html += '<div class="mfm-video-editor-toolbar">';
            html += '<button class="mfm-btn" data-action="trim">✂️ Trim</button>';
            html += '<button class="mfm-btn" data-action="crop">⬜ Crop</button>';
            html += '<button class="mfm-btn" data-action="resize">↔️ Resize</button>';
            html += '<div class="mfm-toolbar-separator"></div>';
            html += '<button class="mfm-btn" data-action="add-blur">🔲 Blur</button>';
            html += '<div class="mfm-toolbar-separator"></div>';
            html += '<button class="mfm-btn" data-action="reset">🔄 Reset</button>';
            html += '</div>';

            html += '<div class="mfm-video-editor-main">';

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

            html += '<div class="mfm-video-info">';
            html += '<span>⏱️ Duration: <strong id="info-duration">--:--</strong></span>';
            html += '<span>📐 Resolution: <strong id="info-resolution">--</strong></span>';
            html += '<span>💾 Size: <strong id="info-size">--</strong></span>';
            html += '</div>';

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

            html += '<div class="mfm-video-editor-panel" id="editor-panel">';

            html += '<div class="mfm-panel-section" id="trim-settings" style="display:none">';
            html += '<h4>✂️ Trim Video</h4>';
            html += '<div class="mfm-panel-row"><label>Start:</label><input type="text" id="trim-start-input" value="00:00"></div>';
            html += '<div class="mfm-panel-row"><label>End:</label><input type="text" id="trim-end-input" value="00:00"></div>';
            html += '<div class="mfm-panel-row"><label>Duration:</label><strong id="trim-length">00:00</strong></div>';
            html += '<button class="mfm-panel-btn" id="btn-set-trim-start">▶ Set Start Here</button>';
            html += '<button class="mfm-panel-btn secondary" id="btn-set-trim-end">⏹ Set End Here</button>';
            html += '</div>';

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

            html += '<div class="mfm-panel-section" id="blur-settings" style="display:none">';
            html += '<h4>🎭 Blur Regions</h4>';
            html += '<p style="font-size:12px;color:#888;margin-bottom:10px">Draw rectangles on the video and set timing</p>';
            html += '<div class="mfm-panel-row"><label>Start</label><input type="text" id="blur-start-time" value="00:00" style="flex:1"></div>';
            html += '<div class="mfm-panel-row"><label>End</label><input type="text" id="blur-end-time" value="00:05" style="flex:1"></div>';
            html += '<button class="mfm-panel-btn" id="btn-set-blur-timing">Set Current Time</button>';
            html += '<div class="mfm-blur-list" id="blur-list"><p style="color:#999;font-size:12px;text-align:center">No blur regions</p></div>';
            html += '<button class="mfm-panel-btn secondary" id="btn-clear-blurs">Clear All</button>';
            html += '</div>';

            html += '</div>';
            html += '</div>';
            html += '</div>';

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
         * Initialize editor by fetching video via POST FormData (keeps token out of URL)
         */
        async function initializeEditor(fileManager, file) {
            var video = document.getElementById('video-preview');
            var loading = document.getElementById('video-loading');
            if (!video) return;

            loading.classList.remove('hidden');
            loading.querySelector('.mfm-loading-text').textContent = 'Loading video...';

            try {
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

                var fetchOptions = {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                };

                if (fileManager.options.token) {
                    fetchOptions.headers = {
                        'Authorization': 'Bearer ' + fileManager.options.token
                    };
                }

                var response = await fetch(cleanBaseUrl, fetchOptions);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                var blob = await response.blob();
                var blobUrl = URL.createObjectURL(blob);

                plugin.state.videoBlob = blob;
                plugin.state.videoBlobUrl = blobUrl;

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

                video.src = blobUrl;
            } catch (error) {
                debugLog('Failed to load video:', error);
                loading.querySelector('.mfm-loading-text').textContent = 'Failed to load video: ' + error.message;
            }

            bindEvents(fileManager, file);
        }

        function updateVideoInfo(file) {
            document.getElementById('info-duration').textContent = formatTime(plugin.state.duration);
            document.getElementById('info-resolution').textContent = plugin.state.width + '×' + plugin.state.height;
            document.getElementById('info-size').textContent = formatSize(file.size || 0);
            document.getElementById('total-time').textContent = formatTime(plugin.state.duration);
        }

        function updateAllInputs() {
            document.getElementById('trim-start-input').value = formatTime(plugin.state.trimStart);
            document.getElementById('trim-end-input').value = formatTime(plugin.state.trimEnd);
            document.getElementById('trim-length').textContent = formatTime(Math.max(0, plugin.state.trimEnd - plugin.state.trimStart));
            document.getElementById('crop-x').value = plugin.state.cropX;
            document.getElementById('crop-y').value = plugin.state.cropY;
            document.getElementById('crop-width').value = plugin.state.cropWidth;
            document.getElementById('crop-height').value = plugin.state.cropHeight;
            document.getElementById('resize-width').value = plugin.state.resizeWidth;
            document.getElementById('resize-height').value = plugin.state.resizeHeight;
            updateTrimVisual();
        }

        function updateTrimVisual() {
            var duration = plugin.state.duration;
            if (duration <= 0) return;
            var startPct = (plugin.state.trimStart / duration) * 100;
            var endPct = (plugin.state.trimEnd / duration) * 100;
            document.getElementById('trim-start').style.left = startPct + '%';
            document.getElementById('trim-end').style.left = endPct + '%';
            document.getElementById('trim-region').style.left = startPct + '%';
            document.getElementById('trim-region').style.width = Math.max(0, endPct - startPct) + '%';
        }

        function bindEvents(fileManager, file) {
            var video = document.getElementById('video-preview');
            var timeline = document.getElementById('timeline-track');

            // Ensure Blob URL & listeners are cleaned up if outer modal overlay/close button is clicked
            if (fileManager.elements && fileManager.elements.modal) {
                var outerModalHandler = function (e) {
                    if (e.target === fileManager.elements.modal ||
                        e.target.classList.contains('mfm-modal-overlay') ||
                        e.target.classList.contains('mfm-modal-close')) {
                        cleanupEditor(fileManager);
                    }
                };
                fileManager.elements.modal.addEventListener('click', outerModalHandler);
                plugin._cleanupHandlers.push(function () {
                    fileManager.elements.modal.removeEventListener('click', outerModalHandler);
                });
            }

            document.querySelector('.mfm-video-editor-close').onclick = function () {
                cleanupEditor(fileManager);
            };

            document.querySelector('.mfm-video-editor-toolbar').onclick = function (e) {
                var btn = e.target.closest('[data-action]');
                if (btn) handleToolbar(btn.getAttribute('data-action'), btn);
            };

            video.ontimeupdate = function () {
                if (!video.duration) return;
                var pct = (video.currentTime / video.duration) * 100;
                document.getElementById('timeline-playhead').style.left = pct + '%';
                document.getElementById('timeline-progress').style.width = pct + '%';
                document.getElementById('current-time').textContent = formatTime(video.currentTime);
            };

            video.onplay = function () { document.getElementById('btn-play-pause').textContent = '⏸'; };
            video.onpause = function () { document.getElementById('btn-play-pause').textContent = '▶'; };

            document.getElementById('btn-play-pause').onclick = function () {
                video.paused ? video.play() : video.pause();
            };
            document.getElementById('btn-step-back').onclick = function () {
                video.pause();
                video.currentTime = Math.max(0, video.currentTime - 1 / 30);
            };
            document.getElementById('btn-step-forward').onclick = function () {
                video.pause();
                video.currentTime = Math.min(video.duration || 0, video.currentTime + 1 / 30);
            };

            timeline.onclick = function (e) {
                if (e.target.classList.contains('mfm-trim-handle') || !video.duration) return;
                var rect = timeline.getBoundingClientRect();
                video.currentTime = ((e.clientX - rect.left) / rect.width) * video.duration;
            };

            setupTrimHandles();
            setupBlurDrawing();

            document.getElementById('btn-set-trim-start').onclick = function () {
                plugin.state.trimStart = video.currentTime;
                if (plugin.state.trimStart >= plugin.state.trimEnd) {
                    plugin.state.trimEnd = Math.min(plugin.state.duration, plugin.state.trimStart + 0.5);
                }
                updateAllInputs();
            };
            document.getElementById('btn-set-trim-end').onclick = function () {
                plugin.state.trimEnd = video.currentTime;
                if (plugin.state.trimEnd <= plugin.state.trimStart) {
                    plugin.state.trimStart = Math.max(0, plugin.state.trimEnd - 0.5);
                }
                updateAllInputs();
            };

            document.getElementById('trim-start-input').onchange = function () {
                plugin.state.trimStart = Math.max(0, Math.min(parseTime(this.value), plugin.state.trimEnd - 0.1));
                updateAllInputs();
            };
            document.getElementById('trim-end-input').onchange = function () {
                plugin.state.trimEnd = Math.min(plugin.state.duration, Math.max(parseTime(this.value), plugin.state.trimStart + 0.1));
                updateAllInputs();
            };

            ['crop-x', 'crop-y', 'crop-width', 'crop-height'].forEach(function (id) {
                document.getElementById(id).onchange = function () {
                    if (id === 'crop-x') plugin.state.cropX = Math.max(0, parseInt(this.value, 10) || 0);
                    else if (id === 'crop-y') plugin.state.cropY = Math.max(0, parseInt(this.value, 10) || 0);
                    else if (id === 'crop-width') plugin.state.cropWidth = Math.max(1, parseInt(this.value, 10) || plugin.state.width);
                    else if (id === 'crop-height') plugin.state.cropHeight = Math.max(1, parseInt(this.value, 10) || plugin.state.height);
                    updateCropPreview();
                };
            });

            document.querySelectorAll('[data-crop]').forEach(function (btn) {
                btn.onclick = function () {
                    var preset = this.getAttribute('data-crop');
                    if (preset === 'reset') {
                        plugin.state.cropX = plugin.state.cropY = 0;
                        plugin.state.cropWidth = plugin.state.width;
                        plugin.state.cropHeight = plugin.state.height;
                    } else {
                        var parts = preset.split(':').map(Number);
                        applyCropPreset(parts[0], parts[1]);
                    }
                    updateAllInputs();
                    updateCropPreview();
                };
            });

            document.getElementById('btn-draw-crop').onclick = enableCropDrawing;

            var resizeW = document.getElementById('resize-width');
            var resizeH = document.getElementById('resize-height');
            var keepRatio = document.getElementById('resize-ratio');

            resizeW.onchange = function () {
                plugin.state.resizeWidth = Math.max(2, parseInt(this.value, 10) || plugin.state.width);
                if (keepRatio.checked && plugin.state.height > 0) {
                    plugin.state.resizeHeight = Math.max(2, Math.round(plugin.state.resizeWidth / (plugin.state.width / plugin.state.height)));
                    resizeH.value = plugin.state.resizeHeight;
                }
                updateResizePreview();
            };
            resizeH.onchange = function () {
                plugin.state.resizeHeight = Math.max(2, parseInt(this.value, 10) || plugin.state.height);
                if (keepRatio.checked && plugin.state.width > 0) {
                    plugin.state.resizeWidth = Math.max(2, Math.round(plugin.state.resizeHeight * (plugin.state.width / plugin.state.height)));
                    resizeW.value = plugin.state.resizeWidth;
                }
                updateResizePreview();
            };

            document.querySelectorAll('[data-resize]').forEach(function (btn) {
                btn.onclick = function () {
                    var preset = this.getAttribute('data-resize');
                    if (preset === 'reset') {
                        plugin.state.resizeWidth = plugin.state.width;
                        plugin.state.resizeHeight = plugin.state.height;
                    } else {
                        var parts = preset.split('x').map(Number);
                        plugin.state.resizeWidth = parts[0];
                        plugin.state.resizeHeight = parts[1];
                    }
                    updateAllInputs();
                    updateResizePreview();
                };
            });

            document.getElementById('btn-set-blur-timing').onclick = function () {
                var currentTime = video.currentTime || 0;
                document.getElementById('blur-start-time').value = formatTime(currentTime);
                document.getElementById('blur-end-time').value = formatTime(Math.min(plugin.state.duration || currentTime + 5, currentTime + 5));
                showInfo('⏱️ Timing set: ' + formatTime(currentTime));
            };

            document.getElementById('btn-clear-blurs').onclick = function () {
                plugin.state.blurRegions = [];
                document.querySelectorAll('.mfm-blur-overlay').forEach(function (el) { el.remove(); });
                updateBlurList();
            };

            // Event delegation for blur sidebar remove buttons
            document.getElementById('blur-list').onclick = function (e) {
                var removeBtn = e.target.closest('[data-remove-blur]');
                if (!removeBtn) return;
                var idx = parseInt(removeBtn.getAttribute('data-remove-blur'), 10);
                if (!isNaN(idx) && plugin.state.blurRegions[idx]) {
                    var region = plugin.state.blurRegions[idx];
                    if (region._overlayEl && region._overlayEl.parentNode) {
                        region._overlayEl.remove();
                    }
                    plugin.state.blurRegions.splice(idx, 1);
                    updateBlurList();
                }
            };

            document.querySelector('.mfm-video-editor-footer').onclick = function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                var act = btn.getAttribute('data-action');
                if (act === 'save') saveVideo(fileManager, file);
                else if (act === 'cancel') cleanupEditor(fileManager);
            };
        }

        function handleToolbar(action, btn) {
            var panel = document.getElementById('editor-panel');
            var isActive = btn.classList.contains('active');

            document.querySelectorAll('.mfm-video-editor-toolbar .mfm-btn').forEach(function (b) { b.classList.remove('active'); });
            ['trim-settings', 'crop-settings', 'resize-settings', 'blur-settings'].forEach(function (id) {
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
            else if (action === 'add-blur') {
                document.getElementById('blur-settings').style.display = 'block';
                showInfo('Draw blur areas on video');
            }
        }

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
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            plugin._cleanupHandlers.push(function () {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            });

            document.getElementById('trim-start').onmousedown = function (e) {
                e.preventDefault(); e.stopPropagation();
                isDragging = 'start';
            };
            document.getElementById('trim-end').onmousedown = function (e) {
                e.preventDefault(); e.stopPropagation();
                isDragging = 'end';
            };
        }

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

        function updateCropPreview() {
            var video = document.getElementById('video-preview');
            var shadow = document.getElementById('crop-shadow');
            var hasCrop = plugin.state.cropX > 0 || plugin.state.cropY > 0 ||
                plugin.state.cropWidth < plugin.state.width || plugin.state.cropHeight < plugin.state.height;

            if (!hasCrop || plugin.state.currentTool !== 'crop' || !video.offsetWidth || !plugin.state.width) {
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
            shadow.querySelector('.mfm-crop-shadow-bottom').style.cssText = 'bottom:0;left:0;width:100%;height:' + Math.max(0, vh - cy - ch) + 'px';
            shadow.querySelector('.mfm-crop-shadow-left').style.cssText = 'top:' + cy + 'px;left:0;width:' + cx + 'px;height:' + ch + 'px';
            shadow.querySelector('.mfm-crop-shadow-right').style.cssText = 'top:' + cy + 'px;right:0;width:' + Math.max(0, vw - cx - cw) + 'px;height:' + ch + 'px';

            showInfo('Crop: ' + plugin.state.cropWidth + '×' + plugin.state.cropHeight);
        }

        function updateResizePreview() {
            var video = document.getElementById('video-preview');
            var preview = document.getElementById('resize-preview');
            var hasResize = plugin.state.resizeWidth !== plugin.state.width || plugin.state.resizeHeight !== plugin.state.height;

            if (!hasResize || plugin.state.currentTool !== 'resize' || !video.offsetWidth || !plugin.state.width) {
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

        function enableCropDrawing() {
            var overlay = document.getElementById('crop-overlay');
            var cropBox = document.getElementById('crop-box');
            var video = document.getElementById('video-preview');
            overlay.classList.add('active');
            var drawing = false, startX = 0, startY = 0;

            overlay.onmousedown = function (e) {
                if (e.target !== overlay) return;
                drawing = true;
                var rect = overlay.getBoundingClientRect();
                startX = e.clientX - rect.left;
                startY = e.clientY - rect.top;
                cropBox.style.left = startX + 'px';
                cropBox.style.top = startY + 'px';
                cropBox.style.width = '0px';
                cropBox.style.height = '0px';
                cropBox.classList.add('visible');
            };
            overlay.onmousemove = function (e) {
                if (!drawing) return;
                var rect = overlay.getBoundingClientRect();
                var x = Math.min(startX, Math.max(0, e.clientX - rect.left));
                var y = Math.min(startY, Math.max(0, e.clientY - rect.top));
                var w = Math.abs(Math.min(rect.width, Math.max(0, e.clientX - rect.left)) - startX);
                var h = Math.abs(Math.min(rect.height, Math.max(0, e.clientY - rect.top)) - startY);
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
                plugin.state.cropX = Math.max(0, Math.round((parseInt(cropBox.style.left, 10) || 0) * scaleX));
                plugin.state.cropY = Math.max(0, Math.round((parseInt(cropBox.style.top, 10) || 0) * scaleY));
                plugin.state.cropWidth = Math.max(10, Math.round((parseInt(cropBox.style.width, 10) || 0) * scaleX));
                plugin.state.cropHeight = Math.max(10, Math.round((parseInt(cropBox.style.height, 10) || 0) * scaleY));
                overlay.classList.remove('active');
                cropBox.classList.remove('visible');
                updateAllInputs();
                updateCropPreview();
            };
            showInfo('Draw crop area on video');
        }

        function setupBlurDrawing() {
            var wrapper = document.getElementById('video-wrapper');
            var video = document.getElementById('video-preview');
            var drawing = false, startX = 0, startY = 0, currentBlur = null;

            wrapper.onmousedown = function (e) {
                if (plugin.state.currentTool !== 'add-blur') return;
                if (e.target.classList.contains('delete-blur') || e.target.closest('.mfm-blur-overlay')) return;
                if (e.target !== video && e.target !== wrapper) return;

                drawing = true;
                var rect = wrapper.getBoundingClientRect();
                startX = e.clientX - rect.left;
                startY = e.clientY - rect.top;
                currentBlur = document.createElement('div');
                currentBlur.className = 'mfm-blur-overlay';
                currentBlur.innerHTML = '<span class="blur-label">BLUR</span><button class="delete-blur" type="button">×</button>';
                currentBlur.style.left = startX + 'px';
                currentBlur.style.top = startY + 'px';
                currentBlur.style.width = '0px';
                currentBlur.style.height = '0px';
                wrapper.appendChild(currentBlur);
            };

            function onBlurMove(e) {
                if (!drawing || !currentBlur) return;
                var rect = wrapper.getBoundingClientRect();
                var cx = Math.min(rect.width, Math.max(0, e.clientX - rect.left));
                var cy = Math.min(rect.height, Math.max(0, e.clientY - rect.top));
                var x = Math.min(startX, cx);
                var y = Math.min(startY, cy);
                var w = Math.abs(cx - startX);
                var h = Math.abs(cy - startY);
                currentBlur.style.left = x + 'px';
                currentBlur.style.top = y + 'px';
                currentBlur.style.width = w + 'px';
                currentBlur.style.height = h + 'px';
            }

            function onBlurUp() {
                if (!drawing || !currentBlur) return;
                drawing = false;
                var w = parseInt(currentBlur.style.width, 10) || 0;
                var h = parseInt(currentBlur.style.height, 10) || 0;
                if (w < 20 || h < 20 || !video.offsetWidth || !video.offsetHeight) {
                    currentBlur.remove();
                    currentBlur = null;
                    return;
                }

                var scaleX = plugin.state.width / video.offsetWidth;
                var scaleY = plugin.state.height / video.offsetHeight;
                var thisBlur = currentBlur;

                var region = {
                    x: Math.round((parseInt(thisBlur.style.left, 10) || 0) * scaleX),
                    y: Math.round((parseInt(thisBlur.style.top, 10) || 0) * scaleY),
                    width: Math.round(w * scaleX),
                    height: Math.round(h * scaleY),
                    start_time: parseTime(document.getElementById('blur-start-time').value),
                    end_time: parseTime(document.getElementById('blur-end-time').value),
                    _overlayEl: thisBlur
                };
                plugin.state.blurRegions.push(region);

                thisBlur.querySelector('.delete-blur').onclick = function (ev) {
                    ev.stopPropagation();
                    var idx = plugin.state.blurRegions.indexOf(region);
                    if (idx > -1) plugin.state.blurRegions.splice(idx, 1);
                    thisBlur.remove();
                    updateBlurList();
                };
                updateBlurList();
                currentBlur = null;
            }

            document.addEventListener('mousemove', onBlurMove);
            document.addEventListener('mouseup', onBlurUp);
            plugin._cleanupHandlers.push(function () {
                document.removeEventListener('mousemove', onBlurMove);
                document.removeEventListener('mouseup', onBlurUp);
            });
        }

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
                    '<button type="button" data-remove-blur="' + i + '">Remove</button>' +
                    '</div>';
            }).join('');
        }

        function showInfo(msg) {
            var info = document.getElementById('preview-info');
            if (!info) return;
            info.textContent = msg;
            info.classList.add('visible');
            clearTimeout(info._t);
            info._t = setTimeout(function () { info.classList.remove('visible'); }, 2500);
        }

        function resetAll() {
            plugin.state.trimStart = 0;
            plugin.state.trimEnd = plugin.state.duration;
            plugin.state.cropX = plugin.state.cropY = 0;
            plugin.state.cropWidth = plugin.state.width;
            plugin.state.cropHeight = plugin.state.height;
            plugin.state.resizeWidth = plugin.state.width;
            plugin.state.resizeHeight = plugin.state.height;
            plugin.state.blurRegions = [];
            document.querySelectorAll('.mfm-blur-overlay').forEach(function (el) { el.remove(); });
            updateAllInputs();
            updateBlurList();
            document.getElementById('crop-shadow').classList.remove('visible');
            document.getElementById('resize-preview').classList.remove('visible');
        }

        /**
         * Save video via server-side processing endpoint
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

                // Strip internal DOM references before serializing blur regions
                var serializedBlurs = plugin.state.blurRegions.map(function (r) {
                    return {
                        x: r.x,
                        y: r.y,
                        width: r.width,
                        height: r.height,
                        start_time: r.start_time,
                        end_time: r.end_time
                    };
                });

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
                    blur_regions: serializedBlurs
                };

                progressBar.style.width = '30%';
                loadingText.textContent = 'Processing on server...';

                var formData = new FormData();
                formData.append('cmd', 'video_process');
                formData.append('target', file.hash);
                formData.append('params', JSON.stringify(params));

                if (fileManager.options.token) {
                    formData.append('token', fileManager.options.token);
                }

                var fetchHeaders = {};
                if (fileManager.options.token) {
                    fetchHeaders['Authorization'] = 'Bearer ' + fileManager.options.token;
                }

                var response = await fetch(fileManager.options.url, {
                    method: 'POST',
                    headers: fetchHeaders,
                    body: formData,
                    credentials: 'same-origin'
                });

                var responseText = await response.text();
                debugLog('Server response:', response.status, responseText);

                var data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Server returned invalid JSON');
                }

                if (!data.success) {
                    throw new Error(data.error || 'Server processing failed');
                }

                progressBar.style.width = '90%';
                loadingText.textContent = 'Finalizing...';

                fileManager.refresh();

                progressBar.style.width = '100%';
                loadingText.textContent = 'Done!';
                subText.textContent = '';

                setTimeout(function () {
                    alert('Video processed successfully!');
                    cleanupEditor(fileManager);
                }, 400);

            } catch (error) {
                debugLog('Video processing error:', error);
                alert('Error: ' + error.message);
            } finally {
                plugin.state.processing = false;
                if (loading) loading.classList.add('hidden');
            }
        }

        function formatTime(s) {
            if (isNaN(s) || s < 0) s = 0;
            var hrs = Math.floor(s / 3600);
            var m = Math.floor((s % 3600) / 60);
            var sec = Math.floor(s % 60);
            if (hrs > 0) {
                return String(hrs).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
            }
            return String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }

        function parseTime(str) {
            if (!str) return 0;
            var parts = String(str).trim().split(':').map(function (p) { return parseInt(p, 10) || 0; });
            if (parts.length === 3) {
                return parts[0] * 3600 + parts[1] * 60 + parts[2];
            }
            if (parts.length === 2) {
                return parts[0] * 60 + parts[1];
            }
            return parts[0] || 0;
        }

        function formatSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
        }

        plugin.contextMenuItems = [{
            action: 'edit-video',
            label: 'Edit Video',
            condition: isVideo,
            handler: openVideoEditor
        }];

        fm.registerPlugin(plugin);
        return plugin;
    }

    if (!window.MyFileManagerPlugins) window.MyFileManagerPlugins = [];
    window.MyFileManagerPlugins.push(VideoEditorPlugin);

    window.VideoEditorConfig = function (opts) {
        if (opts && opts.ffmpegPath) FFMPEG_CONFIG.basePath = opts.ffmpegPath;
        if (opts && opts.debug !== undefined) FFMPEG_CONFIG.debug = opts.debug;
    };

})(window);