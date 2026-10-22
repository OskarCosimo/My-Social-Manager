<?php
/**
 * Rate Limiter Plugin for MyFileManager
 * Limits video processing to 4 videos/30min per IP
 */
class rate_limiterPlugin {
    private $config;
    private $enabled;
    
    public function __construct($config) {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        
        // ✅ Skip init if disabled
        if (!$this->enabled) {
            error_log("⏸️ Rate limiter plugin disabled");
        }
    }
    
    /**
     * Check if plugin is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Check video process rate limit
     */
    public function checkVideoProcessLimit($client_ip) {
        // ✅ Return success if disabled (no rate limiting)
        if (!$this->enabled) {
            error_log("ℹ️ Rate limiter disabled - allowing request");
            return ['success' => true];
        }
        
        $rate_limit_key = "video_process_rate_{$client_ip}";
        $max_requests = 4; // number of times
        $window = 1800; // 30min
        
        $current_count = $this->get_cache($rate_limit_key, 0);
        
        if ($current_count >= $max_requests) {
            return [
                'success' => false,
                'error' => "Rate limit exceeded: max {$max_requests} videos every 30 minutes"
            ];
        }
        
        $this->set_cache($rate_limit_key, $current_count + 1, $window);
        return ['success' => true];
    }
    
    private function get_cache($key, $default = null) {
        if (!$this->enabled) return $default;
        
        if (function_exists('apcu_fetch')) {
            return apcu_fetch($key, $success) ?: $default;
        }
        $cache_dir = sys_get_temp_dir() . '/myfm_cache/';
        if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
        $cache_file = $cache_dir . md5($key) . '.cache';
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
            return unserialize(file_get_contents($cache_file)) ?: $default;
        }
        return $default;
    }
    
    private function set_cache($key, $value, $ttl = 1800) {
        if (!$this->enabled) return;
        
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
            return;
        }
        $cache_dir = sys_get_temp_dir() . '/myfm_cache/';
        if (!is_dir($cache_dir)) mkdir($cache_dir, 0755, true);
        $cache_file = $cache_dir . md5($key) . '.cache';
        file_put_contents($cache_file, serialize($value));
        touch($cache_file, time() + $ttl);
    }
}
