<?php
/**
 * // plugins/ftpplugin.php
 * My File Manager - FTP Plugin
 *
 * Storage plugin for FTP/FTPS connections
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

require_once __DIR__ . '/plugininterface.php';

class ftppluginPlugin implements PluginInterface {
    private $config;
    private $pluginConfig;
    private $connection = null;
    private $enabled;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];
        $this->pluginConfig = $this->config['plugins']['ftpplugin'] ?? $this->config;
        $this->enabled = $this->pluginConfig['enabled'] ?? true;

        // Skip connection if disabled
        if (!$this->enabled) {
            error_log('FTP plugin disabled - skipping connection');
            return;
        }

        $this->connect();
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
     * Connect to FTP server (only if enabled)
     *
     * @throws Exception
     */
    private function connect() {
        if (!$this->enabled) {
            return;
        }

        $host = $this->pluginConfig['host'] ?? '';
        $port = (int)($this->pluginConfig['port'] ?? 21);
        $timeout = (int)($this->pluginConfig['timeout'] ?? 30);
        $useSsl = !empty($this->pluginConfig['ssl']);

        if (empty($host)) {
            throw new Exception('FTP host not configured', 500);
        }

        if ($useSsl && function_exists('ftp_ssl_connect')) {
            $this->connection = @ftp_ssl_connect($host, $port, $timeout);
        } else {
            $this->connection = @ftp_connect($host, $port, $timeout);
        }

        if (!$this->connection) {
            throw new Exception('Could not connect to FTP server', 500);
        }

        $username = $this->pluginConfig['username'] ?? '';
        $password = $this->pluginConfig['password'] ?? '';

        if ($username === '' || $password === '') {
            @ftp_close($this->connection);
            $this->connection = null;
            throw new Exception('FTP credentials not configured', 401);
        }

        $login = @ftp_login($this->connection, $username, $password);
        if (!$login) {
            @ftp_close($this->connection);
            $this->connection = null;
            throw new Exception('FTP login failed', 401);
        }

        if ($this->pluginConfig['passive'] ?? true) {
            @ftp_pasv($this->connection, true);
        }

        error_log("FTP connected to {$host}");
    }

    /**
     * List files and directories
     *
     * @param string $path
     * @return array
     */
    public function listFiles($path) {
        if (!$this->enabled || !$this->connection) {
            return [];
        }

        $list = @ftp_nlist($this->connection, $path);
        if (!is_array($list)) {
            return [];
        }

        $files = [];
        foreach ($list as $item) {
            $name = basename($item);
            if ($name === '.' || $name === '..') {
                continue;
            }
            $info = $this->getFileInfo($item);
            if ($info !== null) {
                $files[] = $info;
            }
        }

        return $files;
    }

    /**
     * Read file content
     *
     * @param string $path
     * @return string
     * @throws Exception
     */
    public function readFile($path) {
        if (!$this->enabled || !$this->connection) {
            throw new Exception('FTP plugin not available', 503);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        if ($tempFile === false) {
            throw new Exception('Failed to create temporary file', 500);
        }

        try {
            if (!@ftp_get($this->connection, $tempFile, $path, FTP_BINARY)) {
                throw new Exception('Could not download file from FTP', 500);
            }

            $content = @file_get_contents($tempFile);
            if ($content === false) {
                throw new Exception('Could not read downloaded temporary file', 500);
            }

            return $content;
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Write file content
     *
     * @param string $path
     * @param string $content
     * @return bool
     */
    public function writeFile($path, $content) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        if ($tempFile === false) {
            return false;
        }

        try {
            if (@file_put_contents($tempFile, $content) === false) {
                return false;
            }

            return @ftp_put($this->connection, $path, $tempFile, FTP_BINARY);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Delete file or directory
     *
     * @param string $path
     * @return bool
     */
    public function delete($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }

        $size = @ftp_size($this->connection, $path);
        if ($size === -1) {
            return @ftp_rmdir($this->connection, $path);
        }

        return @ftp_delete($this->connection, $path);
    }

    /**
     * Create directory
     *
     * @param string $path
     * @return bool
     */
    public function createDirectory($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }

        return @ftp_mkdir($this->connection, $path) !== false;
    }

    /**
     * Rename/move file or directory
     *
     * @param string $oldPath
     * @param string $newPath
     * @return bool
     */
    public function rename($oldPath, $newPath) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }

        return @ftp_rename($this->connection, $oldPath, $newPath);
    }

    /**
     * Check if path exists
     *
     * @param string $path
     * @return bool
     */
    public function exists($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }

        $list = @ftp_nlist($this->connection, dirname($path));
        if (!is_array($list)) {
            return false;
        }

        $targetBase = basename($path);
        foreach ($list as $entry) {
            if ($entry === $path || basename($entry) === $targetBase) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file info
     *
     * @param string $path
     * @return array|null
     */
    public function getFileInfo($path) {
        if (!$this->enabled || !$this->connection) {
            return null;
        }

        $size = @ftp_size($this->connection, $path);
        $mdtm = @ftp_mdtm($this->connection, $path);

        return [
            'name' => basename($path),
            'path' => $path,
            'size' => $size === -1 ? 0 : $size,
            'mime' => $size === -1 ? 'directory' : 'application/octet-stream',
            'ts' => $mdtm === -1 ? time() : $mdtm,
            'read' => 1,
            'write' => 1
        ];
    }

    /**
     * Destructor - close connection
     */
    public function __destruct() {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }
}