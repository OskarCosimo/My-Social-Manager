<?php
/**
 * My File Manager - Plugin Interface
 * 
 * Base interface for storage plugins (FTP, Cloud services, etc.)
 * 
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

interface PluginInterface {
    
    /**
     * Initialize plugin with configuration
     * 
     * @param array $config Plugin configuration
     */
    public function __construct($config);
    
    /**
     * List files and directories
     * 
     * @param string $path Path to list
     * @return array Files array
     */
    public function listFiles($path);
    
    /**
     * Read file content
     * 
     * @param string $path File path
     * @return string File content
     */
    public function readFile($path);
    
    /**
     * Write file content
     * 
     * @param string $path File path
     * @param string $content File content
     * @return bool Success
     */
    public function writeFile($path, $content);
    
    /**
     * Delete file or directory
     * 
     * @param string $path Path to delete
     * @return bool Success
     */
    public function delete($path);
    
    /**
     * Create directory
     * 
     * @param string $path Directory path
     * @return bool Success
     */
    public function createDirectory($path);
    
    /**
     * Rename/move file or directory
     * 
     * @param string $oldPath Old path
     * @param string $newPath New path
     * @return bool Success
     */
    public function rename($oldPath, $newPath);
    
    /**
     * Check if path exists
     * 
     * @param string $path Path to check
     * @return bool Exists
     */
    public function exists($path);
    
    /**
     * Get file info
     * 
     * @param string $path File path
     * @return array File information
     */
    public function getFileInfo($path);
}
