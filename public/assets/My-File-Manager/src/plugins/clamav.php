<?php
/**
 * // plugins/clamav.php
 * ClamAV Antivirus Scanner Plugin
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class clamavPlugin {
    private $config;
    private $pluginConfig;

    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];
        $this->pluginConfig = $this->config['plugins']['clamav'] ?? $this->config;
    }

    /**
     * Scans a file using ClamAV daemon
     *
     * @param string $filePath Absolute path of the file to scan
     * @return array Scan result
     */
    public function scanFile($filePath) {
        if (!is_string($filePath) || $filePath === '' || !is_file($filePath)) {
            return ['success' => false, 'is_safe' => false, 'error' => 'File not found for scanning.'];
        }

        // Support both nested plugin config and direct config array
        $clamdscanPath = $this->pluginConfig['clamdscan_path'] ?? 'clamdscan';

        // --fdpass allows clamd to read the file even with restrictive permissions
        // --no-summary gives a clean single-line output
        $cmd = escapeshellcmd($clamdscanPath) . ' --no-summary --fdpass ' . escapeshellarg($filePath) . ' 2>&1';

        $outputLines = [];
        $exitCode = 0;
        exec($cmd, $outputLines, $exitCode);
        $output = trim(implode("\n", $outputLines));

        // clamdscan exit codes: 0 = no virus found, 1 = virus(es) found, 2 = error
        if ($exitCode === 0 && preg_match('/:\s*OK$/m', $output) === 1 && strpos($output, 'FOUND') === false) {
            return ['success' => true, 'is_safe' => true];
        }

        if ($exitCode === 1 || strpos($output, 'FOUND') !== false) {
            return ['success' => true, 'is_safe' => false, 'details' => $output];
        }

        return [
            'success' => false,
            'is_safe' => false,
            'error' => 'ClamAV scan error (exit code: ' . $exitCode . ')',
            'details' => $output
        ];
    }
}