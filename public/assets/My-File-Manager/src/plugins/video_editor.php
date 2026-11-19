<?php
/**
 * // plugins/video_editor.php
 * Video Processor Plugin
 * Handles video editing: trim, crop, resize, blur/censor with timing
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class video_editorPlugin {
    private $config;
    private $ffmpeg_path;
    private $blur_png_path;

    /**
     * Allowed output video formats
     */
    private const ALLOWED_FORMATS = ['mp4', 'webm'];

    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];

        $plugin_config = $this->config['plugins']['video_editor'] ?? $this->config;
        $this->ffmpeg_path = $plugin_config['ffmpeg_path'] ?? '/usr/bin/ffmpeg';

        $default_png = dirname(dirname(__DIR__)) . '/assets/black_blur.png';
        $this->blur_png_path = !empty($plugin_config['blur_png_path']) ? $plugin_config['blur_png_path'] : $default_png;

        // Verify FFmpeg exists and is executable
        if (!file_exists($this->ffmpeg_path)) {
            throw new Exception("FFmpeg not found at: {$this->ffmpeg_path}");
        }

        if (!file_exists($this->blur_png_path)) {
            error_log("WARNING: Blur PNG not found at {$this->blur_png_path} - blur feature disabled");
        }
    }

    /**
     * Process video with trim, crop, resize, blur/censor
     *
     * @param string $file_hash Base64-encoded relative file path
     * @param array $params Processing parameters
     * @param string $root_path Root directory path
     * @return array Processing result
     */
    public function processVideo($file_hash, $params, $root_path) {
        if (empty($file_hash) || !is_string($file_hash)) {
            return ['success' => false, 'error' => 'Missing target file hash', 'code' => 400];
        }

        $params = is_array($params) ? $params : [];

        // Whitelist output format strictly
        $format = strtolower(trim((string)($params['format'] ?? 'mp4')));
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            return ['success' => false, 'error' => 'Unsupported output format', 'code' => 400];
        }
        $params['format'] = $format;

        // Validate trim range if specified
        $trim_start = max(0.0, (float)($params['trim_start'] ?? 0));
        $trim_end = max(0.0, (float)($params['trim_end'] ?? 0));
        if ($trim_end > 0 && $trim_end <= $trim_start) {
            return ['success' => false, 'error' => 'Invalid trim range', 'code' => 400];
        }

        // Strict base64 decode & path resolution
        $decoded_filename = base64_decode($file_hash, true);
        if ($decoded_filename === false || $decoded_filename === '') {
            return ['success' => false, 'error' => 'Invalid target file hash', 'code' => 400];
        }

        $decoded_filename = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded_filename), DIRECTORY_SEPARATOR);
        $candidate_file = rtrim($root_path, '/\\') . DIRECTORY_SEPARATOR . $decoded_filename;

        if (class_exists('Security')) {
            $security = new Security();
            if (!$security->isPathSafe($decoded_filename, $root_path) || !$security->isPathSafe($candidate_file, $root_path)) {
                return ['success' => false, 'error' => 'Security check failed', 'code' => 403];
            }
        }

        $input_file = realpath($candidate_file);
        $root_realpath = realpath($root_path);

        if (!$input_file || !$root_realpath) {
            return ['success' => false, 'error' => 'Target file not found', 'code' => 404];
        }

        $root_prefix = rtrim($root_realpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($input_file, $root_prefix) !== 0 || !is_file($input_file)) {
            return ['success' => false, 'error' => 'Security check failed', 'code' => 403];
        }

        if (!is_readable($input_file)) {
            return ['success' => false, 'error' => 'File not readable', 'code' => 403];
        }

        // Determine final output path and write to a temporary file first (never overwrite input in-place)
        $path_info = pathinfo($input_file);
        $dir_name = $path_info['dirname'];
        $base_name = $path_info['filename'];

        $final_output_file = !empty($params['overwrite'])
            ? ($dir_name . DIRECTORY_SEPARATOR . $base_name . '.' . $format)
            : ($dir_name . DIRECTORY_SEPARATOR . $base_name . '_edited.' . $format);

        $temp_output_file = $dir_name . DIRECTORY_SEPARATOR . '.' . $base_name . '_tmp_' . bin2hex(random_bytes(6)) . '.' . $format;

        // Build FFmpeg command targeting temporary output file
        $command_line = $this->buildFFmpegCommand($input_file, $temp_output_file, $params);

        $output_lines = [];
        $return_code = 0;
        $start_time = microtime(true);

        exec($command_line, $output_lines, $return_code);

        if ($return_code !== 0 || !file_exists($temp_output_file) || filesize($temp_output_file) === 0) {
            if (file_exists($temp_output_file)) {
                @unlink($temp_output_file);
            }
            $error_msg = implode("\n", $output_lines);
            error_log("FFmpeg ERROR [{$return_code}]: " . substr($error_msg, 0, 1000));

            return [
                'success' => false,
                'error' => 'Video processing failed (exit code: ' . $return_code . ')',
                'code' => 500
            ];
        }

        // Atomically move temp output to final destination
        if (!@rename($temp_output_file, $final_output_file)) {
            @unlink($temp_output_file);
            return ['success' => false, 'error' => 'Failed to save processed video', 'code' => 500];
        }

        // If overwrite was requested and format changed extension, remove original file
        if (!empty($params['overwrite']) && $final_output_file !== $input_file && file_exists($input_file)) {
            @unlink($input_file);
        }

        @chmod($final_output_file, 0640);

        return [
            'success' => true,
            'message' => 'Video processed successfully',
            'input_file' => basename($input_file),
            'output_file' => basename($final_output_file),
            'output_size' => (int)@filesize($final_output_file),
            'input_hash' => $file_hash,
            'processing_time' => round(microtime(true) - $start_time, 2)
        ];
    }

    /**
     * Build FFmpeg command string
     *
     * @param string $input_file
     * @param string $output_file
     * @param array $params
     * @return string
     */
    private function buildFFmpegCommand($input_file, $output_file, $params) {
        $blur_regions = $params['blur_regions'] ?? [];
        $trim_start = max(0.0, (float)($params['trim_start'] ?? 0));
        $trim_end = max(0.0, (float)($params['trim_end'] ?? 0));
        $duration = $trim_end > 0 ? max(0.0, $trim_end - $trim_start) : 0.0;

        if (!empty($blur_regions) && is_array($blur_regions) && file_exists($this->blur_png_path)) {
            return $this->buildBlurCommand($input_file, $output_file, $params, $blur_regions, $trim_start, $duration);
        }

        return $this->buildSimpleCommand($input_file, $output_file, $params, $trim_start, $duration);
    }

    /**
     * Build command with blur/censor overlay
     *
     * @param string $input_file
     * @param string $output_file
     * @param array $params
     * @param array $blur_regions
     * @param float $trim_start
     * @param float $duration
     * @return string
     */
    private function buildBlurCommand($input_file, $output_file, $params, $blur_regions, $trim_start, $duration) {
        $blur = $blur_regions[0];

        $x = max(0, (int)($blur['x'] ?? 0));
        $y = max(0, (int)($blur['y'] ?? 0));
        $start_time = max(0.0, (float)($blur['start_time'] ?? 0) - $trim_start);
        $end_time = max($start_time, (float)($blur['end_time'] ?? 0) - $trim_start);

        $base_filters = [];
        if (!empty($params['crop_w']) && (int)$params['crop_w'] > 0 &&
            !empty($params['crop_h']) && (int)$params['crop_h'] > 0) {
            $base_filters[] = sprintf(
                'crop=%d:%d:%d:%d',
                (int)$params['crop_w'],
                (int)$params['crop_h'],
                max(0, (int)($params['crop_x'] ?? 0)),
                max(0, (int)($params['crop_y'] ?? 0))
            );
        }

        if (!empty($params['resize_w']) && (int)$params['resize_w'] > 0 &&
            !empty($params['resize_h']) && (int)$params['resize_h'] > 0) {
            $resize_w = max(2, (int)$params['resize_w'] + ((int)$params['resize_w'] % 2));
            $resize_h = max(2, (int)$params['resize_h'] + ((int)$params['resize_h'] % 2));
            $base_filters[] = "scale={$resize_w}:{$resize_h}";
        }

        $filter_prefix = !empty($base_filters)
            ? '[0:v]' . implode(',', $base_filters) . '[base];'
            : '[0:v]null[base];';

        $filter_complex = sprintf(
            '%s[base][1:v]overlay=x=%d:y=%d:enable=\'between(t,%0.2f,%0.2f)\'[outv]',
            $filter_prefix,
            $x,
            $y,
            $start_time,
            $end_time
        );

        $ffmpeg_cmd = [escapeshellcmd($this->ffmpeg_path), '-y'];

        if ($trim_start > 0) {
            $ffmpeg_cmd[] = '-ss';
            $ffmpeg_cmd[] = sprintf('%0.6f', $trim_start);
        }

        if ($duration > 0) {
            $ffmpeg_cmd[] = '-t';
            $ffmpeg_cmd[] = sprintf('%0.6f', $duration);
        }

        $ffmpeg_cmd[] = '-i';
        $ffmpeg_cmd[] = escapeshellarg($input_file);
        $ffmpeg_cmd[] = '-i';
        $ffmpeg_cmd[] = escapeshellarg($this->blur_png_path);
        $ffmpeg_cmd[] = '-filter_complex';
        $ffmpeg_cmd[] = escapeshellarg($filter_complex);
        $ffmpeg_cmd[] = '-map';
        $ffmpeg_cmd[] = escapeshellarg('[outv]');
        $ffmpeg_cmd[] = '-map';
        $ffmpeg_cmd[] = escapeshellarg('0:a?');

        $output_format = $params['format'] ?? 'mp4';
        if ($output_format === 'mp4') {
            $ffmpeg_cmd[] = '-c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart';
        } else {
            $ffmpeg_cmd[] = '-c:v libvpx-vp9 -crf 30 -b:v 0 -c:a libvorbis';
        }

        $ffmpeg_cmd[] = escapeshellarg($output_file);

        return implode(' ', $ffmpeg_cmd) . ' 2>&1';
    }

    /**
     * Build simple command without blur
     *
     * @param string $input_file
     * @param string $output_file
     * @param array $params
     * @param float $trim_start
     * @param float $duration
     * @return string
     */
    private function buildSimpleCommand($input_file, $output_file, $params, $trim_start, $duration) {
        $ffmpeg_cmd = [escapeshellcmd($this->ffmpeg_path), '-y'];

        if ($trim_start > 0) {
            $ffmpeg_cmd[] = '-ss';
            $ffmpeg_cmd[] = sprintf('%0.6f', $trim_start);
        }

        if ($duration > 0) {
            $ffmpeg_cmd[] = '-t';
            $ffmpeg_cmd[] = sprintf('%0.6f', $duration);
        }

        $ffmpeg_cmd[] = '-i';
        $ffmpeg_cmd[] = escapeshellarg($input_file);

        $video_filters = [];

        if (!empty($params['crop_w']) && (int)$params['crop_w'] > 0 &&
            !empty($params['crop_h']) && (int)$params['crop_h'] > 0) {
            $video_filters[] = sprintf(
                'crop=%d:%d:%d:%d',
                (int)$params['crop_w'],
                (int)$params['crop_h'],
                max(0, (int)($params['crop_x'] ?? 0)),
                max(0, (int)($params['crop_y'] ?? 0))
            );
        }

        if (!empty($params['resize_w']) && (int)$params['resize_w'] > 0 &&
            !empty($params['resize_h']) && (int)$params['resize_h'] > 0) {
            $resize_w = max(2, (int)$params['resize_w'] + ((int)$params['resize_w'] % 2));
            $resize_h = max(2, (int)$params['resize_h'] + ((int)$params['resize_h'] % 2));
            $video_filters[] = "scale={$resize_w}:{$resize_h}";
        }

        if (!empty($video_filters)) {
            $ffmpeg_cmd[] = '-vf';
            $ffmpeg_cmd[] = escapeshellarg(implode(',', $video_filters));
        }

        $output_format = $params['format'] ?? 'mp4';

        if ($output_format === 'mp4') {
            $ffmpeg_cmd[] = '-c:v libx264 -preset fast -crf 23';
            $ffmpeg_cmd[] = '-c:a aac -b:a 128k -movflags +faststart';
        } else {
            $ffmpeg_cmd[] = '-c:v libvpx-vp9 -crf 30 -b:v 0';
            $ffmpeg_cmd[] = '-c:a libvorbis';
        }

        $ffmpeg_cmd[] = escapeshellarg($output_file);

        return implode(' ', $ffmpeg_cmd) . ' 2>&1';
    }
}