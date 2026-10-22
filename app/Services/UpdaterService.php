<?php
// app/Services/UpdaterService.php

require_once __DIR__ . '/../Core/Config.php';

class UpdaterService {
    private string $currentVersion;
    private string $repo;

    public function __construct() {
        $this->currentVersion = Config::APP_VERSION ?? '1.0.0';
        $this->repo           = Config::GITHUB_REPO ?? 'OskarCosimo/My-Social-Manager';
    }

    public function getCurrentVersion(): string {
        return $this->currentVersion;
    }

    /**
     * Pre-flight check: verify write permissions on critical project directories before updating
     */
    public function checkWritePermissions(): array {
        $rootDir = dirname(__DIR__, 2);

        $criticalPaths = [
            'Root Directory' => $rootDir,
            'migrate.php'    => $rootDir . '/migrate.php',
            'app/'           => $rootDir . '/app',
            'app/Core/'      => $rootDir . '/app/Core',
            'public/'        => $rootDir . '/public',
            'database/'      => $rootDir . '/database'
        ];

        $failed = [];
        foreach ($criticalPaths as $label => $path) {
            if (file_exists($path)) {
                if (!is_writable($path)) {
                    $failed[] = "{$label} (" . basename($path) . ")";
                }
            } else {
                if (!is_writable(dirname($path))) {
                    $failed[] = "{$label} (parent directory not writable)";
                }
            }
        }

        return [
            'is_ready'     => empty($failed),
            'failed_paths' => $failed
        ];
    }

    /**
     * Check GitHub API for releases newer than current version
     */
    public function checkForUpdates(): ?array {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'MySocialManager-Updater',
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $release = json_decode($response, true);
        if (empty($release['tag_name'])) {
            return null;
        }

        $latestVersion = ltrim($release['tag_name'], 'v');

        if (version_compare($latestVersion, $this->currentVersion, '>')) {
            return [
                'has_update'    => true,
                'version'       => $latestVersion,
                'zip_url'       => $release['zipball_url'],
                'release_notes' => $release['body'] ?? 'No release notes provided.',
                'published_at'  => $release['published_at'] ?? null
            ];
        }

        return [
            'has_update'    => false,
            'version'       => $latestVersion
        ];
    }

    /**
     * Download, unpack, overwrite core files, and execute database migrations
     */
    public function applyUpdate(string $zipUrl): bool {
        $rootDir = dirname(__DIR__, 2);

        // 1. Verify write permissions
        $permCheck = $this->checkWritePermissions();
        if (!$permCheck['is_ready']) {
            $pathsList = implode(', ', $permCheck['failed_paths']);
            throw new Exception("Update aborted due to permissions: [{$pathsList}]. Please run: sudo chown -R www-data:www-data {$rootDir}");
        }

        $tempZip = sys_get_temp_dir() . '/msm_update_' . time() . '.zip';

        // 2. Download package
        $fp = fopen($tempZip, 'wb');
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'MySocialManager-Updater',
            CURLOPT_TIMEOUT        => 120
        ]);
        $success = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success) {
            @unlink($tempZip);
            throw new Exception("Failed to download update package from GitHub.");
        }

        // 3. Extract ZIP package
        $zip = new ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            throw new Exception("Unable to open update ZIP file.");
        }

        $tempExtractDir = sys_get_temp_dir() . '/msm_extract_' . time();
        $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempZip);

        // GitHub zipball creates a top-level folder
        $extractedItems = scandir($tempExtractDir);
        $innerFolder = null;
        foreach ($extractedItems as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($tempExtractDir . '/' . $item)) {
                $innerFolder = $tempExtractDir . '/' . $item;
                break;
            }
        }

        $sourceDir = $innerFolder ?: $tempExtractDir;

        // 4. Overwrite files preserving Config.php and lock files
        $this->copyRecursive($sourceDir, $rootDir);
        $this->deleteDirectory($tempExtractDir);

        // 5. Run database migrations
        $this->runMigrations();

        return true;
    }

    private function copyRecursive(string $source, string $dest): void {
        $dir = opendir($source);
        @mkdir($dest, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.git') {
                continue;
            }

            // Protect existing configuration and installation lock
            if ($file === 'Config.php' && basename($dest) === 'Core') {
                continue;
            }
            if ($file === 'installed.lock' && basename($dest) === 'install') {
                continue;
            }

            $srcPath = $source . '/' . $file;
            $dstPath = $dest . '/' . $file;

            if (is_dir($srcPath)) {
                $this->copyRecursive($srcPath, $dstPath);
            } else {
                if (!@copy($srcPath, $dstPath)) {
                    throw new Exception("Permission denied: Unable to overwrite {$dstPath}. Make sure it is owned by webserver user.");
                }
            }
        }
        closedir($dir);
    }

    /**
     * Run unified incremental migrations with OPcache flushing
     */
    private function runMigrations(): void {
        $migrateScript = dirname(__DIR__, 2) . '/migrate.php';
        
        if (file_exists($migrateScript)) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($migrateScript, true);
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            try {
                require $migrateScript;
            } catch (\Throwable $e) {
                error_log("Updater Migration Execution Error: " . $e->getMessage());
            }
        }
    }

    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }
}