<?php
/**
 * // plugins/rate_limiter.php
 * Rate Limiter Plugin for MyFileManager
 * Limits video processing requests per IP within a configurable time window
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class rate_limiterPlugin {
    private $config;
    private $pluginConfig;
    private $enabled;

    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];
        $this->pluginConfig = $this->config['plugins']['rate_limiter'] ?? $this->config;
        $this->enabled = $this->pluginConfig['enabled'] ?? true;

        if (!$this->enabled) {
            error_log('Rate limiter plugin disabled');
        }
    }

    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public function isEnabled() {
        return (bool)$this->enabled;
    }

    /**
     * Check video process rate limit
     *
     * @param string $client_ip Client IP address
     * @return array
     */
    public function checkVideoProcessLimit($client_ip) {
        if (!$this->enabled) {
            return ['success' => true];
        }

        $safeIp = preg_replace('/[^a-zA-Z0-9._:-]/', '_', (string)$client_ip);
        $rate_limit_key = "video_process_rate_{$safeIp}";
        $max_requests = isset($this->pluginConfig['max_requests']) ? (int)$this->pluginConfig['max_requests'] : 10;
        $window = isset($this->pluginConfig['window']) ? (int)$this->pluginConfig['window'] : 1800; // 30 minutes

        $current_count = (int)$this->get_cache($rate_limit_key, 0);

        if ($current_count >= $max_requests) {
            return [
                'success' => false,
                'error' => "Rate limit exceeded: max {$max_requests} videos every 30 minutes"
            ];
        }

        $this->set_cache($rate_limit_key, $current_count + 1, $window);
        return ['success' => true];
    }

    /**
     * Read value from APCu or JSON file cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function get_cache($key, $default = null) {
        if (!$this->enabled) {
            return $default;
        }

        if (function_exists('apcu_fetch')) {
            $success = false;
            $val = apcu_fetch($key, $success);
            return $success ? $val : $default;
        }

        $cache_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'myfm_cache';
        $cache_file = $cache_dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';

        if (!is_file($cache_file)) {
            return $default;
        }

        $raw = @file_get_contents($cache_file);
        if ($raw === false) {
            return $default;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['expires_at']) || !array_key_exists('value', $data)) {
            @unlink($cache_file);
            return $default;
        }

        if (time() >= (int)$data['expires_at']) {
            @unlink($cache_file);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Store value in APCu or JSON file cache with explicit expiration timestamp
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     */
    private function set_cache($key, $value, $ttl = 1800) {
        if (!$this->enabled) {
            return;
        }

        $ttl = max(1, (int)$ttl);

        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
            return;
        }

        $cache_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'myfm_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0750, true);
        }

        $cache_file = $cache_dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $payload = json_encode([
            'value' => $value,
            'expires_at' => time() + $ttl
        ]);

        if ($payload !== false) {
            @file_put_contents($cache_file, $payload, LOCK_EX);
            @chmod($cache_file, 0640);
        }
    }
}