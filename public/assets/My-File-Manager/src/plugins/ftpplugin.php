<?php
/**
 * My File Manager - FTP Plugin
 * 
 * Storage plugin for FTP connections
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

require_once __DIR__ . '/plugininterface.php';

class ftppluginPlugin implements PluginInterface {
    private $config;
    private $connection;
    private $enabled;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct($config) {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        
        // ✅ Skip connection if disabled
        if (!$this->enabled) {
            error_log("⏸️ FTP plugin disabled - skipping connection");
            return;
        }
        
        $this->connect();
    }
    
    /**
     * Check if plugin is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Connect to FTP server (only if enabled)
     * 
     * @throws Exception
     */
    private function connect() {
        if (!$this->enabled) return;
        
        if (empty($this->config['host'])) {
            throw new Exception('FTP host not configured', 500);
        }
        
        $this->connection = ftp_connect($this->config['host'], $this->config['port'] ?? 21);
        
        if (!$this->connection) {
            throw new Exception('Could not connect to FTP server', 500);
        }
        
        if (empty($this->config['username']) || empty($this->config['password'])) {
            throw new Exception('FTP credentials not configured', 401);
        }
        
        $login = ftp_login($this->connection, $this->config['username'], $this->config['password']);
        
        if (!$login) {
            throw new Exception('FTP login failed', 401);
        }
        
        // Enable passive mode if configured
        if ($this->config['passive'] ?? true) {
            ftp_pasv($this->connection, true);
        }
        
        error_log("✅ FTP connected to {$this->config['host']}");
    }
    
    /**
     * List files and directories
     */
    public function listFiles($path) {
        if (!$this->enabled || !$this->connection) {
            return [];
        }
        $list = ftp_nlist($this->connection, $path);
        $files = [];
        
        foreach ($list as $item) {
            $files[] = $this->getFileInfo($item);
        }
        
        return $files;
    }
    
    /**
     * Read file content
     */
    public function readFile($path) {
        if (!$this->enabled || !$this->connection) {
            throw new Exception('FTP plugin not available', 503);
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        
        if (!ftp_get($this->connection, $tempFile, $path, FTP_BINARY)) {
            unlink($tempFile);
            throw new Exception('Could not download file from FTP', 500);
        }
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        return $content;
    }
    
    /**
     * Write file content
     */
    public function writeFile($path, $content) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tempFile, $content);
        
        $result = ftp_put($this->connection, $path, $tempFile, FTP_BINARY);
        unlink($tempFile);
        
        return $result;
    }
    
    /**
     * Delete file or directory
     */
    public function delete($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }
        $size = ftp_size($this->connection, $path);
        
        if ($size === -1) {
            return ftp_rmdir($this->connection, $path);
        } else {
            return ftp_delete($this->connection, $path);
        }
    }
    
    /**
     * Create directory
     */
    public function createDirectory($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }
        return ftp_mkdir($this->connection, $path) !== false;
    }
    
    /**
     * Rename/move file or directory
     */
    public function rename($oldPath, $newPath) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }
        return ftp_rename($this->connection, $oldPath, $newPath);
    }
    
    /**
     * Check if path exists
     */
    public function exists($path) {
        if (!$this->enabled || !$this->connection) {
            return false;
        }
        $list = ftp_nlist($this->connection, dirname($path));
        return in_array($path, $list);
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($path) {
        if (!$this->enabled || !$this->connection) {
            return null;
        }
        $size = ftp_size($this->connection, $path);
        $mdtm = ftp_mdtm($this->connection, $path);
        
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
            ftp_close($this->connection);
        }
    }
}
