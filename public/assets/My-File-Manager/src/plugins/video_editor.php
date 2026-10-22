<?php
/**
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
    
    public function __construct($config) {
    $this->config = $config;
    
    // Configurable paths from plugin config
    $plugin_config = $config['plugins']['video_editor'] ?? [];  // ✅ CAMBIA video_processor → video_editor
    $this->ffmpeg_path = $plugin_config['ffmpeg_path'] ?? '/usr/bin/ffmpeg';
    
    // relative path of png
    $default_png = dirname(dirname(__DIR__)) . '/assets/black_blur.png';
    $this->blur_png_path = $plugin_config['blur_png_path'] ?? $default_png;
    
    // Verify FFmpeg exists
    if (!file_exists($this->ffmpeg_path)) {
        throw new Exception("FFmpeg not found at: {$this->ffmpeg_path}");
    }
    
    // Verify blur PNG exists
    if (!file_exists($this->blur_png_path)) {
        error_log("WARNING: Blur PNG not found at {$this->blur_png_path} - blur feature disabled");
    }
}
    
    /**
     * Process video with trim, crop, resize, blur/censor
     */
    public function processVideo($file_hash, $params, $root_path) {
        if (empty($file_hash)) {
            return ['success' => false, 'error' => 'Missing target file hash', 'code' => 400];
        }
        
        $decoded_filename = base64_decode($file_hash);
        $input_file = $root_path . DIRECTORY_SEPARATOR . $decoded_filename;
        $input_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input_file);
        
        // File validation
        if (!file_exists($input_file)) {
            return [
                'success' => false,
                'error' => 'Target file not found',
                'debug_path' => $input_file,
                'code' => 404
            ];
        }
        
        if (!is_readable($input_file)) {
            return ['success' => false, 'error' => 'File not readable', 'code' => 403];
        }
        
        $input_file = realpath($input_file);
        $root_realpath = realpath($root_path);
        
        if (strpos($input_file, $root_realpath) !== 0) {
            return ['success' => false, 'error' => 'Security check failed', 'code' => 403];
        }
        
        $output_file = preg_replace('/\.[^.]+$/', '_edited.' . ($params['format'] ?? 'mp4'), $input_file);
        if (!empty($params['overwrite'])) {
            $output_file = $input_file;
        }
        
        // Build FFmpeg command
        $command_line = $this->buildFFmpegCommand($input_file, $output_file, $params);
        
        //error_log("=== VIDEO PROCESSOR PLUGIN DEBUG ===");
        //error_log("Command: " . $command_line);
        //error_log("==============================");
        
        $output_lines = [];
        $return_code = 0;
        $start_time = microtime(true);
        
        exec($command_line, $output_lines, $return_code);
        
        if ($return_code !== 0) {
            $error_msg = implode("\n", $output_lines);
            error_log("FFmpeg ERROR [{$return_code}]: " . substr($error_msg, 0, 1000));
            
            return [
                'success' => false,
                'error' => 'Video processing failed (exit code: ' . $return_code . ')',
                'debug_output' => substr($error_msg, 0, 500),
                'code' => 500
            ];
        }
        
        if (!file_exists($output_file) || filesize($output_file) === 0) {
            return ['success' => false, 'error' => 'Output file not created', 'code' => 500];
        }
        
        return [
            'success' => true,
            'message' => 'Video processed successfully',
            'input_file' => basename($input_file),
            'output_file' => basename($output_file),
            'output_size' => filesize($output_file),
            'input_hash' => $file_hash,
            'processing_time' => round(microtime(true) - $start_time, 2)
        ];
    }
    
    /**
     * Build FFmpeg command string
     */
    private function buildFFmpegCommand($input_file, $output_file, $params) {
        $blur_regions = $params['blur_regions'] ?? [];
        $trim_start = (float)($params['trim_start'] ?? 0);
        $trim_end = (float)($params['trim_end'] ?? 0);
        $duration = $trim_end > 0 ? ($trim_end - $trim_start) : 0;
        
        // With blur regions - use filter_complex
        if (!empty($blur_regions) && file_exists($this->blur_png_path)) {
            return $this->buildBlurCommand($input_file, $output_file, $params, $blur_regions, $trim_start, $duration);
        }
        
        // Without blur - simple filters
        return $this->buildSimpleCommand($input_file, $output_file, $params, $trim_start, $duration);
    }
    
    /**
     * Build command with blur/censor overlay
     */
    private function buildBlurCommand($input_file, $output_file, $params, $blur_regions, $trim_start, $duration) {
    $blur = $blur_regions[0]; // First region only
    
    $x = max(0, (int)$blur['x']);
    $y = max(0, (int)$blur['y']);
    $start_time = max(0, (float)$blur['start_time'] - $trim_start);
    $end_time = max(0, (float)$blur['end_time'] - $trim_start);
    
    $crop_w = (int)$params['crop_w'];
    $crop_h = (int)$params['crop_h'];
    $resize_w = (int)$params['resize_w'] + ((int)$params['resize_w'] % 2);
    $resize_h = (int)$params['resize_h'] + ((int)$params['resize_h'] % 2);
    
    // DEBUG LOG
    //error_log("=== BLUR DEBUG ===");
    //error_log("Original blur time: {$blur['start_time']} - {$blur['end_time']}");
    //error_log("Trim start: $trim_start, Duration: $duration");
    //error_log("Adjusted blur time: $start_time - $end_time");
    //error_log("Blur position: x=$x, y=$y");
    //error_log("Video size after crop/resize: {$resize_w}x{$resize_h}");
    //error_log("PNG path: {$this->blur_png_path}");
    //error_log("PNG exists: " . (file_exists($this->blur_png_path) ? 'YES' : 'NO'));
    //error_log("==================");
    
    return sprintf(
        '%s -y -ss %0.6f -t %0.6f -i %s -i %s ' .
        '-filter_complex "[0:v]crop=%d:%d:0:0,scale=%d:%d[base];[base][1:v]overlay=x=%d:y=%d:enable=\'between(t,%0.2f,%0.2f)\'[outv]" ' .
        '-map "[outv]" -map "0:a?" -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k -movflags +faststart %s 2>&1',
        $this->ffmpeg_path,
        $trim_start,
        $duration,
        escapeshellarg($input_file),
        escapeshellarg($this->blur_png_path),
        $crop_w, $crop_h,
        $resize_w, $resize_h,
        $x, $y,
        $start_time, $end_time,
        escapeshellarg($output_file)
    );
}
    
    /**
     * Build simple command without blur
     */
    private function buildSimpleCommand($input_file, $output_file, $params, $trim_start, $duration) {
        $ffmpeg_cmd = [$this->ffmpeg_path, '-y'];
        
        if ($trim_start > 0) {
            $ffmpeg_cmd[] = '-ss';
            $ffmpeg_cmd[] = $trim_start;
        }
        
        if ($duration > 0) {
            $ffmpeg_cmd[] = '-t';
            $ffmpeg_cmd[] = $duration;
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
        
        if (!empty($params['resize_w']) && (int)$params['resize_w'] > 0) {
            $resize_w = (int)$params['resize_w'] + ((int)$params['resize_w'] % 2);
            $resize_h = (int)$params['resize_h'] + ((int)$params['resize_h'] % 2);
            $video_filters[] = "scale={$resize_w}:{$resize_h}";
        }
        
        if (!empty($video_filters)) {
            $ffmpeg_cmd[] = '-vf';
            $ffmpeg_cmd[] = implode(',', $video_filters);
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
