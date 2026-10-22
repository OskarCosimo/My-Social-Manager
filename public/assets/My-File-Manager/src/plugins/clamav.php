<?php
/**
 * ClamAV Antivirus Scanner Plugin
 */
class clamavPlugin {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Scans a file using ClamAV daemon
     * @param string $filePath Absolute path of the file to scan
     * @return array Scan result
     */
    public function scanFile($filePath) {
        if (!file_exists($filePath)) {
            return ['success' => false, 'is_safe' => false, 'error' => 'File not found for scanning.'];
        }

        // Get the path from config, or fallback to the standard command
        $clamdscanPath = $this->config['clamdscan_path'] ?? 'clamdscan';

        // Build the command using the exact path
        // --fdpass allows clamd to read the file even with restrictive permissions
        // --no-summary gives us a clean 1-line output
        $cmd = escapeshellcmd($clamdscanPath) . " --no-summary --fdpass " . escapeshellarg($filePath) . " 2>&1";
        
        $output = shell_exec($cmd);

        // If the output contains "OK", the file is clean
        if (strpos($output, 'OK') !== false && strpos($output, 'FOUND') === false) {
            return ['success' => true, 'is_safe' => true];
        }

        // Otherwise, a malware was found (or a daemon error occurred)
        return ['success' => true, 'is_safe' => false, 'details' => trim($output)];
    }
}