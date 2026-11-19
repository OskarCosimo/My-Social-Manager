<?php
/**
 * // src/myfilemanager.php
 * My File Manager - Core Class
 *
 * Main class handling all file operations with security checks
 * This file can be safely excluded from HTTP access
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class MyFileManager {
    private $config;
    private $user;
    private $security;
    private $currentPath;
    private $cachedUsedSpace = null;

    /**
     * Allowed commands whitelist
     */
    private const ALLOWED_COMMANDS = [
        'open',
        'upload',
        'download',
        'delete',
        'rename',
        'copy',
        'cut',
        'mkdir',
        'info',
        'search',
        'restore'
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration array
     * @param array $user User information
     */
    public function __construct($config, $user) {
        $this->config = $config;
        $this->user = $user;
        $this->security = new Security();

        // Normalize rootPath and trashPath (avoid trailing slashes causing double separators)
        $this->config['rootPath'] = rtrim($this->config['rootPath'], '/\\');
        if (!empty($this->config['trashPath'])) {
            $this->config['trashPath'] = rtrim($this->config['trashPath'], '/\\');
        }

        $this->currentPath = $this->config['rootPath'];

        // Create root directory if not exists
        if (!is_dir($this->config['rootPath'])) {
            mkdir($this->config['rootPath'], 0750, true);
        }

        // Create trash directory if enabled
        if (!empty($this->config['enableTrash']) && !empty($this->config['trashPath']) && !is_dir($this->config['trashPath'])) {
            mkdir($this->config['trashPath'], 0750, true);
        }
    }

    /**
     * Execute command
     *
     * @param string $cmd Command name
     * @param array $params Request parameters
     * @return array Response data
     * @throws Exception
     */
    public function execute($cmd, $params) {
        $cmd = strtolower(trim((string)$cmd));

        if (!in_array($cmd, self::ALLOWED_COMMANDS, true)) {
            throw new Exception("Unknown command: {$cmd}", 400);
        }

        if (!empty($this->config['disabledOperations']) && in_array($cmd, $this->config['disabledOperations'], true)) {
            throw new Exception('Operation not allowed', 403);
        }

        $method = 'cmd' . ucfirst($cmd);
        if (!method_exists($this, $method)) {
            throw new Exception("Unknown command: {$cmd}", 400);
        }

        return $this->$method($params);
    }

    /**
     * Open directory command
     *
     * @param array $params Parameters
     * @return array Directory contents
     * @throws Exception
     */
    private function cmdOpen($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $path = $this->resolvePath($target);

        if (!is_dir($path)) {
            throw new Exception('Directory not found', 404);
        }

        // Get files in directory
        $files = [];
        $items = @scandir($path);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $path . DIRECTORY_SEPARATOR . $item;
                $fileInfo = $this->getFileInfo($itemPath);

                if ($fileInfo) {
                    $files[] = $fileInfo;
                }
            }
        }

        // Normalize both paths with realpath to ensure proper comparison
        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        $relativePath = '';
        if ($realPath && $realRootPath && strpos($realPath, $realRootPath) === 0) {
            $relativePath = substr($realPath, strlen($realRootPath));
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        $result = [
            'cwd' => [
                'hash' => base64_encode($relativePath),
                'name' => basename($path) ?: 'Root',
                'mime' => 'directory',
                'read' => true,
                'write' => $this->hasPermission('write')
            ],
            'files' => $files
        ];

        if ($this->hasPermission('quota')) {
            $result['quota'] = $this->getQuota();
        }

        return $result;
    }

    /**
     * Restore files from trash folder
     *
     * @param array $params Should include 'hashes' => array of base64 encoded paths in trash
     * @return array Restore result summary
     * @throws Exception
     */
    private function cmdRestore($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $hashes = $params['hashes'] ?? [];
        if (!is_array($hashes) || empty($hashes)) {
            throw new Exception('No files specified for restore', 400);
        }

        $realTrashPath = !empty($this->config['trashPath']) ? realpath($this->config['trashPath']) : false;
        if (!$realTrashPath) {
            throw new Exception('Trash folder not available', 404);
        }

        $trashFolderName = basename($realTrashPath);
        $restored = [];
        $errors = [];

        foreach ($hashes as $hash) {
            $trashPath = $this->resolvePath($hash);
            $realItemTrashPath = realpath($trashPath);

            // Verify item exists and is strictly inside trash folder
            if (!$realItemTrashPath || strpos($realItemTrashPath, $realTrashPath . DIRECTORY_SEPARATOR) !== 0) {
                $errors[] = 'Invalid trash item';
                continue;
            }

            // Compute relative path inside trash folder
            $relativeInsideTrash = ltrim(substr($realItemTrashPath, strlen($realTrashPath)), DIRECTORY_SEPARATOR);
            $relativeInsideTrash = str_replace(DIRECTORY_SEPARATOR, '/', $relativeInsideTrash);

            // Strip any leading trash folder prefix if present
            $originalRelativePath = preg_replace('#^' . preg_quote($trashFolderName, '#') . '/?#', '', $relativeInsideTrash);

            if ($originalRelativePath === '' || !$this->security->isPathSafe($originalRelativePath, $this->config['rootPath'])) {
                $errors[] = 'Invalid restore destination';
                continue;
            }

            $originalPath = $this->config['rootPath'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $originalRelativePath);

            if (file_exists($originalPath)) {
                $errors[] = basename($originalPath) . ' already exists';
                continue;
            }

            $originalDir = dirname($originalPath);
            if (!is_dir($originalDir)) {
                mkdir($originalDir, 0750, true);
            }

            if (!$this->security->isPathSafe($originalPath, $this->config['rootPath'])) {
                $errors[] = 'Invalid restore destination';
                continue;
            }

            if (@rename($realItemTrashPath, $originalPath)) {
                $restored[] = $originalRelativePath;
            } else {
                $errors[] = 'Failed to restore ' . basename($realItemTrashPath);
            }
        }

        $this->cachedUsedSpace = null;

        return [
            'success' => count($restored),
            'restored' => $restored,
            'errors' => $errors
        ];
    }

    /**
     * Upload file command (chunked support)
     *
     * @param array $params Parameters
     * @return array Upload result
     * @throws Exception
     */
    private function cmdUpload($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $path = $this->resolvePath($target);

        if (!is_dir($path)) {
            throw new Exception('Target directory does not exist', 404);
        }

        if ($this->isReadOnlyFolder($path)) {
            throw new Exception('Cannot upload to this folder: read-only', 403);
        }

        if ($this->isUploadBlockedFolder($path)) {
            throw new Exception('Cannot upload to this folder (upload blocked)', 403);
        }

        if (!$this->checkQuota()) {
            throw new Exception('Quota exceeded', 507);
        }

        $uploaderConfig = $this->config;
        if ($this->hasPermission('quota')) {
            $quota = $this->getQuota();
            if ($quota && $quota['total'] > 0) {
                $uploaderConfig['quotaFree'] = $quota['free'];
            }
        }

        $uploader = new ChunkUploader($uploaderConfig);
        $result = $uploader->handleUpload($_FILES['upload'] ?? [], $path, $params);

        if (!isset($result['path']) || empty($result['path'])) {
            return [
                'chunkMerged' => $result['completed'] ?? false,
                'received' => $result['received'] ?? 0,
                'total' => $result['total'] ?? 0
            ];
        }

        if (!file_exists($result['path'])) {
            throw new Exception('Uploaded file not found', 500);
        }

        $this->cachedUsedSpace = null;

        return [
            'added' => [$this->getFileInfo($result['path'])],
            'chunkMerged' => $result['completed'] ?? false
        ];
    }

    /**
     * Download file command
     *
     * @param array $params Parameters
     * @throws Exception
     */
    private function cmdDownload($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $path = $this->resolvePath($target);

        if (!$this->checkExtensionPermission($path, 'read')) {
            throw new Exception('Cannot download this file type: permission denied', 403);
        }

        if (!file_exists($path) || !is_file($path)) {
            throw new Exception('File not found', 404);
        }

        $fileSize = filesize($path);
        $rawName = basename($path);

        // Sanitize filename for Content-Disposition header (prevent header injection)
        $asciiFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawName);
        $utf8Encoded = rawurlencode($rawName);

        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            throw new Exception('Cannot open file', 500);
        }

        // Clean ALL output buffers BEFORE sending binary headers
        while (@ob_end_clean());

        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . $utf8Encoded);
        header('Content-Length: ' . $fileSize);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Stream in 8KB chunks and abort immediately if client disconnects
        while (!feof($fp) && !connection_aborted()) {
            $buffer = fread($fp, 8192);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            flush();
        }

        fclose($fp);
        exit;
    }

    /**
     * Delete file/folder command
     *
     * @param array $params Parameters
     * @return array Deletion result
     * @throws Exception
     */
    private function cmdDelete($params) {
        if (!$this->hasPermission('delete')) {
            error_log('Permission denied for cmdDelete');
            throw new Exception('Permission denied', 403);
        }

        $targets = $params['targets'] ?? [];
        if (!is_array($targets) || empty($targets)) {
            throw new Exception('No targets specified', 400);
        }

        $realRootPath = realpath($this->config['rootPath']);
        $realTrashPath = !empty($this->config['trashPath']) ? realpath($this->config['trashPath']) : false;
        $removed = [];

        foreach ($targets as $target) {
            $path = $this->resolvePath($target);
            $realPath = realpath($path);

            if ($realPath === false) {
                throw new Exception('Target not found', 404);
            }

            // Never allow deleting root directory
            if ($realRootPath && $realPath === $realRootPath) {
                throw new Exception('Cannot delete root directory', 403);
            }

            // Check extension permission for delete
            if (!$this->checkExtensionPermission($realPath, 'delete')) {
                throw new Exception('Cannot delete this file type: permission denied', 403);
            }

            // If user invoked delete directly on the trash directory itself, empty its contents instead of removing .trash
            if ($realTrashPath && $realPath === $realTrashPath) {
                $this->emptyDirectory($realTrashPath);
                $removed[] = $target;
                continue;
            }

            // Check if folder is protected
            if (is_dir($realPath) && $this->isProtectedFolder($realPath)) {
                throw new Exception('Cannot delete protected folder: ' . basename($realPath), 403);
            }

            if ($realTrashPath && strpos($realPath, $realTrashPath . DIRECTORY_SEPARATOR) === 0) {
                // Permanently delete item already inside trash
                if (is_dir($realPath)) {
                    $this->deleteDirectory($realPath);
                } else {
                    unlink($realPath);
                }
            } elseif (!empty($this->config['enableTrash']) && $realTrashPath) {
                // Preserve relative folder path inside trash to avoid overwriting same-named files
                $relativeFromRoot = ltrim(substr($realPath, strlen($realRootPath)), DIRECTORY_SEPARATOR);
                $trashDest = $realTrashPath . DIRECTORY_SEPARATOR . $relativeFromRoot;
                $trashDestDir = dirname($trashDest);

                if (!is_dir($trashDestDir)) {
                    mkdir($trashDestDir, 0750, true);
                }

                // Avoid overwriting an existing item in trash with the same path
                if (file_exists($trashDest)) {
                    $trashDest = $this->getUniqueTrashPath($trashDestDir, basename($trashDest));
                }

                if (!@rename($realPath, $trashDest)) {
                    throw new Exception('Failed to move item to trash', 500);
                }
            } else {
                // Trash disabled: permanently delete
                if (is_dir($realPath)) {
                    $this->deleteDirectory($realPath);
                } else {
                    unlink($realPath);
                }
            }

            $removed[] = $target;
        }

        $this->cachedUsedSpace = null;

        return ['removed' => $removed];
    }

    /**
     * Rename file/folder command
     *
     * @param array $params Parameters
     * @return array Rename result
     * @throws Exception
     */
    private function cmdRename($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $name = $params['name'] ?? '';

        // Sanitize & validate new name
        $name = $this->security->sanitizeFilename($name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new Exception('Invalid filename', 400);
        }

        // Block dangerous extensions (including compound and trailing-dot variants)
        $banExtensions = $this->config['banExtensions'] ?? [];
        if ($this->security->hasBlockedExtension($name, $banExtensions)) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            throw new Exception('Dangerous extension not allowed: ' . $ext, 403);
        }

        $oldPath = $this->resolvePath($target);
        $realOldPath = realpath($oldPath);

        if ($realOldPath === false) {
            throw new Exception('Target not found', 404);
        }

        $realRootPath = realpath($this->config['rootPath']);
        $realTrashPath = !empty($this->config['trashPath']) ? realpath($this->config['trashPath']) : false;

        if (($realRootPath && $realOldPath === $realRootPath) || ($realTrashPath && $realOldPath === $realTrashPath)) {
            throw new Exception('Cannot rename protected folder', 403);
        }

        if (!$this->checkExtensionPermission($realOldPath, 'write')) {
            throw new Exception('Cannot rename this file type: permission denied', 403);
        }

        if (is_dir($realOldPath) && $this->isProtectedFolder($realOldPath)) {
            throw new Exception('Cannot rename protected folder', 403);
        }

        if ($this->isReadOnlyFolder(dirname($realOldPath))) {
            throw new Exception('Cannot rename in read-only folder', 403);
        }

        $newPath = dirname($realOldPath) . DIRECTORY_SEPARATOR . $name;

        if (!$this->security->isPathSafe($newPath, $this->config['rootPath'])) {
            throw new Exception('Invalid destination path', 403);
        }

        if (file_exists($newPath)) {
            throw new Exception('File already exists', 409);
        }

        if (!@rename($realOldPath, $newPath)) {
            throw new Exception('Failed to rename item', 500);
        }

        return [
            'added' => [$this->getFileInfo($newPath)],
            'removed' => [$target]
        ];
    }

    /**
     * Check if folder is protected (cannot be renamed/deleted)
     *
     * @param string $path Folder path to check
     * @return bool
     */
    private function isProtectedFolder($path) {
        if (!isset($this->config['protectedFolders']) || empty($this->config['protectedFolders'])) {
            return false;
        }

        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        if (!$realPath || !$realRootPath) {
            return false;
        }

        $relativePath = substr($realPath, strlen($realRootPath));
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        foreach ($this->config['protectedFolders'] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePath === $folder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copy file/folder command
     *
     * @param array $params Parameters
     * @return array Copy result
     * @throws Exception
     */
    private function cmdCopy($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $targets = $params['targets'] ?? [];
        if (!is_array($targets) || empty($targets)) {
            throw new Exception('No targets specified', 400);
        }

        $destination = $params['dst'] ?? '';
        $dstPath = $this->resolvePath($destination);
        $realDstPath = realpath($dstPath);

        if (!$realDstPath || !is_dir($realDstPath)) {
            throw new Exception('Destination directory not found', 404);
        }

        if ($this->isReadOnlyFolder($realDstPath) || $this->isUploadBlockedFolder($realDstPath)) {
            throw new Exception('Cannot copy into this folder: read-only', 403);
        }

        $realRootPath = realpath($this->config['rootPath']);
        $realTrashPath = !empty($this->config['trashPath']) ? realpath($this->config['trashPath']) : false;
        $added = [];

        foreach ($targets as $target) {
            $srcPath = $this->resolvePath($target);
            $realSrcPath = realpath($srcPath);

            if (!$realSrcPath) {
                throw new Exception('Source not found', 404);
            }

            if (($realRootPath && $realSrcPath === $realRootPath) || ($realTrashPath && $realSrcPath === $realTrashPath)) {
                throw new Exception('Cannot copy protected folder', 403);
            }

            if (is_file($realSrcPath) && $this->security->hasBlockedExtension(basename($realSrcPath), $this->config['banExtensions'] ?? [])) {
                throw new Exception('File type not allowed', 403);
            }

            // Prevent copying a directory into itself or its subdirectory
            if (is_dir($realSrcPath) && ($realDstPath === $realSrcPath || strpos($realDstPath, $realSrcPath . DIRECTORY_SEPARATOR) === 0)) {
                throw new Exception('Cannot copy a folder into itself', 400);
            }

            // Check quota for copy operation
            if ($this->hasPermission('quota')) {
                $quota = $this->getQuota();
                if ($quota && $quota['total'] > 0) {
                    $neededSize = $this->getDirectorySize($realSrcPath);
                    if ($neededSize > $quota['free']) {
                        throw new Exception('Quota exceeded', 507);
                    }
                }
            }

            $baseName = basename($realSrcPath);
            $newPath = $realDstPath . DIRECTORY_SEPARATOR . $baseName;
            if (file_exists($newPath)) {
                $newPath = $this->getUniqueTrashPath($realDstPath, $baseName);
            }

            if (is_dir($realSrcPath)) {
                $this->copyDirectory($realSrcPath, $newPath);
            } else {
                if (!@copy($realSrcPath, $newPath)) {
                    throw new Exception('Failed to copy file', 500);
                }
                @chmod($newPath, 0640);
            }

            $added[] = $this->getFileInfo($newPath);
        }

        $this->cachedUsedSpace = null;

        return ['added' => $added];
    }

    /**
     * Cut (move) file/folder command
     *
     * @param array $params Parameters
     * @return array Move result
     * @throws Exception
     */
    private function cmdCut($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $targets = $params['targets'] ?? [];
        if (!is_array($targets) || empty($targets)) {
            throw new Exception('No targets specified', 400);
        }

        $destination = $params['dst'] ?? '';
        $dstPath = $this->resolvePath($destination);
        $realDstPath = realpath($dstPath);

        if (!$realDstPath || !is_dir($realDstPath)) {
            throw new Exception('Destination directory not found', 404);
        }

        if ($this->isReadOnlyFolder($realDstPath) || $this->isUploadBlockedFolder($realDstPath)) {
            throw new Exception('Cannot move into this folder: read-only', 403);
        }

        $realRootPath = realpath($this->config['rootPath']);
        $realTrashPath = !empty($this->config['trashPath']) ? realpath($this->config['trashPath']) : false;
        $added = [];
        $removed = [];

        foreach ($targets as $target) {
            $srcPath = $this->resolvePath($target);
            $realSrcPath = realpath($srcPath);

            if (!$realSrcPath) {
                throw new Exception('Source not found', 404);
            }

            if (($realRootPath && $realSrcPath === $realRootPath) || ($realTrashPath && $realSrcPath === $realTrashPath)) {
                throw new Exception('Cannot move protected folder', 403);
            }

            if (is_dir($realSrcPath) && $this->isProtectedFolder($realSrcPath)) {
                throw new Exception('Cannot move protected folder: ' . basename($realSrcPath), 403);
            }

            if ($this->isReadOnlyFolder(dirname($realSrcPath))) {
                throw new Exception('Cannot move from read-only folder', 403);
            }

            if (!$this->checkExtensionPermission($realSrcPath, 'write')) {
                throw new Exception('Cannot move this file type: permission denied', 403);
            }

            // Prevent moving a directory into itself or its subdirectory
            if (is_dir($realSrcPath) && ($realDstPath === $realSrcPath || strpos($realDstPath, $realSrcPath . DIRECTORY_SEPARATOR) === 0)) {
                throw new Exception('Cannot move a folder into itself', 400);
            }

            $newPath = $realDstPath . DIRECTORY_SEPARATOR . basename($realSrcPath);
            if ($newPath === $realSrcPath) {
                continue;
            }
            if (file_exists($newPath)) {
                throw new Exception('Destination already exists: ' . basename($realSrcPath), 409);
            }

            if (!@rename($realSrcPath, $newPath)) {
                throw new Exception('Failed to move item', 500);
            }

            $added[] = $this->getFileInfo($newPath);
            $removed[] = $target;
        }

        $this->cachedUsedSpace = null;

        return [
            'added' => $added,
            'removed' => $removed
        ];
    }

    /**
     * Create directory command
     *
     * @param array $params Parameters
     * @return array Directory creation result
     * @throws Exception
     */
    private function cmdMkdir($params) {
        if (!$this->hasPermission('write')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $name = trim((string)($params['name'] ?? 'New Folder'));

        if (substr($name, 0, 1) === '.') {
            throw new Exception('Folder names cannot start with a dot (.)', 400);
        }

        $name = $this->security->sanitizeFilename($name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new Exception('Invalid folder name', 400);
        }

        $parentPath = $this->resolvePath($target);
        if (!is_dir($parentPath)) {
            throw new Exception('Parent directory not found', 404);
        }

        if ($this->isReadOnlyFolder($parentPath)) {
            throw new Exception('Cannot create folder here: read-only', 403);
        }

        if ($this->isUploadBlockedFolder($parentPath)) {
            throw new Exception('Cannot create folder here (upload blocked)', 403);
        }

        $newPath = $parentPath . DIRECTORY_SEPARATOR . $name;

        if (!$this->security->isPathSafe($newPath, $this->config['rootPath'])) {
            throw new Exception('Invalid folder path', 403);
        }

        if (file_exists($newPath)) {
            throw new Exception('Directory already exists', 409);
        }

        if (!mkdir($newPath, 0750, true)) {
            throw new Exception('Failed to create directory', 500);
        }

        return ['added' => [$this->getFileInfo($newPath)]];
    }

    /**
     * Check if folder is read-only (no upload/mkdir allowed)
     *
     * @param string $path Folder path to check
     * @return bool
     */
    private function isReadOnlyFolder($path) {
        if (!isset($this->config['readOnlyFolders']) || empty($this->config['readOnlyFolders'])) {
            return false;
        }

        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        if (!$realPath || !$realRootPath) {
            return false;
        }

        $relativePath = substr($realPath, strlen($realRootPath));
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        foreach ($this->config['readOnlyFolders'] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePath === $folder || strpos($relativePath, $folder . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if folder blocks upload and mkdir operations
     * Applies to folder and ALL its subfolders
     *
     * @param string $path Folder path to check
     * @return bool
     */
    private function isUploadBlockedFolder($path) {
        if (!isset($this->config['uploadBlockedFolders']) || empty($this->config['uploadBlockedFolders'])) {
            return false;
        }

        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        if (!$realPath || !$realRootPath) {
            return false;
        }

        $relativePath = substr($realPath, strlen($realRootPath));
        $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        foreach ($this->config['uploadBlockedFolders'] as $folder) {
            $folder = trim($folder, '/\\');
            if ($relativePath === $folder || strpos($relativePath, $folder . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file/folder info command
     *
     * @param array $params Parameters
     * @return array File information
     * @throws Exception
     */
    private function cmdInfo($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }

        $target = $params['target'] ?? '';
        $path = $this->resolvePath($target);

        $info = $this->getFileInfo($path, true);
        if (!$info) {
            throw new Exception('File not found', 404);
        }

        return $info;
    }

    /**
     * Search files command
     *
     * @param array $params Parameters
     * @return array Search results
     * @throws Exception
     */
    private function cmdSearch($params) {
        if (!$this->hasPermission('read')) {
            throw new Exception('Permission denied', 403);
        }

        $query = trim((string)($params['q'] ?? ''));
        $target = $params['target'] ?? '';

        if ($query === '') {
            return ['files' => []];
        }

        $searchPath = $this->resolvePath($target);
        $results = $this->searchFiles($searchPath, $query);

        return ['files' => $results];
    }

    /**
     * Resolve path from hash/target safely within rootPath
     *
     * @param string $target Target hash
     * @return string Real filesystem path
     * @throws Exception
     */
    private function resolvePath($target) {
        if (empty($target)) {
            return $this->config['rootPath'];
        }

        $decoded = base64_decode((string)$target, true);
        if ($decoded === false) {
            throw new Exception('Invalid target hash', 400);
        }

        // Normalize directory separators and remove leading slashes
        $decoded = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $decoded);
        $decoded = ltrim($decoded, DIRECTORY_SEPARATOR);

        // Security: Prevent path traversal on relative input
        if (!$this->security->isPathSafe($decoded, $this->config['rootPath'])) {
            throw new Exception('Invalid path: Path traversal detected', 403);
        }

        $candidate = $decoded === ''
            ? $this->config['rootPath']
            : $this->config['rootPath'] . DIRECTORY_SEPARATOR . $decoded;

        $realPath = realpath($candidate);
        if ($realPath === false) {
            $realPath = $candidate;
        }

        // Double-check resolved path (catches symlinks escaping rootPath)
        if (!$this->security->isPathSafe($realPath, $this->config['rootPath'])) {
            throw new Exception('Access denied: Path outside root', 403);
        }

        return $realPath;
    }

    /**
     * Get file information
     *
     * @param string $path File path
     * @param bool $detailed Include detailed info
     * @return array|null File information
     */
    private function getFileInfo($path, $detailed = false) {
        if (empty($path) || !file_exists($path)) {
            error_log('getFileInfo called with invalid path: ' . var_export($path, true));
            return null;
        }

        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        $relativePath = '';
        if ($realPath && $realRootPath && strpos($realPath, $realRootPath) === 0) {
            $relativePath = substr($realPath, strlen($realRootPath));
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $hash = base64_encode($relativePath);

        $mime = $this->getMimeType($path);

        $info = [
            'name' => basename($path),
            'hash' => $hash,
            'mime' => $mime,
            'ts' => @filemtime($path) ?: 0,
            'size' => is_file($path) ? (@filesize($path) ?: 0) : 0,
            'read' => true,
            'write' => $this->hasPermission('write'),
            'locked' => false
        ];

        if (is_dir($path)) {
            $info['volumeid'] = 'l1_';
            $info['dirs'] = $this->hasDirs($path) ? 1 : 0;
        }

        if (is_file($path)) {
            $info['read'] = $this->checkExtensionPermission($path, 'read');
            $info['write'] = $this->checkExtensionPermission($path, 'write') && $this->hasPermission('write');
            $info['rm'] = $this->checkExtensionPermission($path, 'delete') && $this->hasPermission('delete');
            $info['openable'] = $this->checkExtensionPermission($path, 'open');
        }

        if ($detailed) {
            $info['path'] = $relativePath;
            $info['url'] = ($this->config['rootUrl'] ?? '') . ltrim($relativePath, '/');

            if (is_file($path)) {
                $info['dim'] = $this->getImageDimensions($path);
            }
        }

        return $info;
    }

    /**
     * Check if directory has subdirectories
     *
     * @param string $path Directory path
     * @return bool
     */
    private function hasDirs($path) {
        if (!is_dir($path) || !is_readable($path)) {
            return false;
        }

        $items = @scandir($path);
        if (!is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get MIME type of file
     *
     * @param string $path File path
     * @return string MIME type
     */
    private function getMimeType($path) {
        if (is_dir($path)) {
            return 'directory';
        }

        if (!is_file($path) || !is_readable($path)) {
            return 'application/octet-stream';
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Get image dimensions
     *
     * @param string $path Image path
     * @return string|null Dimensions string
     */
    private function getImageDimensions($path) {
        if (!is_file($path)) {
            return null;
        }

        $mime = $this->getMimeType($path);
        if (strpos($mime, 'image/') !== 0) {
            return null;
        }

        $size = @getimagesize($path);
        if ($size) {
            return $size[0] . 'x' . $size[1];
        }

        return null;
    }

    /**
     * Get quota information
     *
     * @return array Quota info
     */
    public function getQuota() {
        $userQuota = isset($this->user['quota']) ? (int)$this->user['quota'] : 0;

        if ($userQuota <= 0) {
            $totalSpace = @disk_total_space($this->config['rootPath']) ?: 0;
            $freeSpace = @disk_free_space($this->config['rootPath']) ?: 0;
            $usedSpace = max(0, $totalSpace - $freeSpace);
        } else {
            if ($this->cachedUsedSpace === null) {
                $this->cachedUsedSpace = $this->calculateUserSpace($this->config['rootPath']);
            }
            $usedSpace = $this->cachedUsedSpace;
            $totalSpace = $userQuota;
            $freeSpace = $userQuota - $usedSpace;
        }

        $uploadMaxSize = $this->parseSize(ini_get('upload_max_filesize'));
        $postMaxSize = $this->parseSize(ini_get('post_max_size'));
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));

        $maxUpload = min($uploadMaxSize, $postMaxSize, $memoryLimit);

        if ($userQuota > 0 && $freeSpace > 0) {
            $maxUpload = min($maxUpload, $freeSpace);
        }

        return [
            'total' => (int)$totalSpace,
            'used' => (int)$usedSpace,
            'free' => max(0, (int)$freeSpace),
            'maxUpload' => max(0, (int)$maxUpload)
        ];
    }

    /**
     * Calculate user space usage recursively
     * Excludes folders specified in quotaExcludedFolders from calculation
     *
     * @param string $path Directory path
     * @return int Bytes used
     */
    private function calculateUserSpace($path) {
        $size = 0;

        if (!is_dir($path) || !is_readable($path)) {
            return 0;
        }

        $realPath = realpath($path);
        $realRootPath = realpath($this->config['rootPath']);

        $relativePath = '';
        if ($realPath && $realRootPath) {
            if ($realPath === $realRootPath) {
                $relativePath = '';
            } elseif (strpos($realPath, $realRootPath) === 0) {
                $relativePath = substr($realPath, strlen($realRootPath));
                $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            }
        }

        if (isset($this->config['quotaExcludedFolders']) && !empty($this->config['quotaExcludedFolders'])) {
            foreach ($this->config['quotaExcludedFolders'] as $excludedFolder) {
                $excludedFolder = trim($excludedFolder, '/\\');
                if ($relativePath === $excludedFolder || strpos($relativePath, $excludedFolder . '/') === 0) {
                    return 0;
                }
            }
        }

        try {
            $items = @scandir($path);
            if ($items === false) {
                return 0;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $path . DIRECTORY_SEPARATOR . $item;
                // Skip symlinks to prevent double counting or infinite loops
                if (is_link($itemPath)) {
                    continue;
                }

                if (is_file($itemPath)) {
                    $fileSize = @filesize($itemPath);
                    if ($fileSize !== false) {
                        $size += $fileSize;
                    }
                } elseif (is_dir($itemPath)) {
                    $size += $this->calculateUserSpace($itemPath);
                }
            }
        } catch (Exception $e) {
            error_log('Error calculating user space: ' . $e->getMessage());
            return 0;
        }

        return $size;
    }

    /**
     * Parse size string (e.g., "128M") to bytes
     *
     * @param string $size Size string
     * @return int Bytes
     */
    private function parseSize($size) {
        if (empty($size) || $size === '-1') {
            return PHP_INT_MAX;
        }

        $size = trim($size);
        $unit = strtolower(substr($size, -1));
        $value = (int)substr($size, 0, -1);

        if (!is_numeric($unit)) {
            switch ($unit) {
                case 'g':
                    $value *= 1024 * 1024 * 1024;
                    break;
                case 'm':
                    $value *= 1024 * 1024;
                    break;
                case 'k':
                    $value *= 1024;
                    break;
            }
        } else {
            $value = (int)$size;
        }

        return $value;
    }

    /**
     * Check if quota allows upload
     *
     * @return bool
     */
    private function checkQuota() {
        if (!$this->hasPermission('quota')) {
            return true;
        }

        $quota = $this->getQuota();
        if (!$quota || $quota['total'] <= 0) {
            return true;
        }

        return $quota['free'] > 0;
    }

    /**
     * Calculate directory or file size
     *
     * @param string $path Directory or file path
     * @return int Size in bytes
     */
    private function getDirectorySize($path) {
        if (is_link($path)) {
            return 0;
        }
        if (!is_dir($path)) {
            return is_file($path) ? (@filesize($path) ?: 0) : 0;
        }

        $size = 0;
        $items = @scandir($path);
        if (!is_array($items)) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_link($fullPath)) {
                continue;
            }

            if (is_dir($fullPath)) {
                $size += $this->getDirectorySize($fullPath);
            } else {
                $size += (@filesize($fullPath) ?: 0);
            }
        }

        return $size;
    }

    /**
     * Empty all contents inside a directory without deleting the directory itself
     *
     * @param string $path Directory path
     */
    private function emptyDirectory($path) {
        if (!is_dir($path)) {
            return;
        }

        $items = @scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath) && !is_link($fullPath)) {
                $this->deleteDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $path Directory path
     */
    private function deleteDirectory($path) {
        if (!is_dir($path)) {
            return;
        }

        $this->emptyDirectory($path);
        @rmdir($path);
    }

    /**
     * Copy directory recursively
     *
     * @param string $src Source path
     * @param string $dst Destination path
     */
    private function copyDirectory($src, $dst) {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0750, true);
        }

        $items = @scandir($src);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;

            if (is_link($srcPath)) {
                continue;
            }

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
                @chmod($dstPath, 0640);
            }
        }
    }

    /**
     * Generate a unique path inside destination directory to avoid silent overwrites
     *
     * @param string $dir Directory path
     * @param string $filename Desired name
     * @return string Unique full path
     */
    private function getUniqueTrashPath($dir, $filename) {
        $info = pathinfo($filename);
        $basename = $info['filename'];
        $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
        $candidate = $dir . DIRECTORY_SEPARATOR . $basename . '_' . time() . $extension;

        $counter = 1;
        while (file_exists($candidate)) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $basename . '_' . time() . '_' . $counter . $extension;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Search files recursively (with result cap)
     *
     * @param string $path Search path
     * @param string $query Search query
     * @param int $maxResults Maximum number of results
     * @param array $results Accumulated results
     * @return array Found files
     */
    private function searchFiles($path, $query, $maxResults = 1000, &$results = []) {
        if (!is_dir($path) || !is_readable($path) || count($results) >= $maxResults) {
            return $results;
        }

        $items = @scandir($path);
        if (!is_array($items)) {
            return $results;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (count($results) >= $maxResults) {
                break;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_link($fullPath)) {
                continue;
            }

            if (stripos($item, $query) !== false) {
                $fileInfo = $this->getFileInfo($fullPath);
                if ($fileInfo) {
                    $results[] = $fileInfo;
                }
            }

            if (is_dir($fullPath)) {
                $this->searchFiles($fullPath, $query, $maxResults, $results);
            }
        }

        return $results;
    }

    /**
     * Check user permission
     *
     * @param string $permission Permission name
     * @return bool
     */
    private function hasPermission($permission) {
        return in_array($permission, $this->user['permissions'] ?? [], true);
    }

    /**
     * Get extension from filename
     *
     * @param string $filename Filename
     * @return string Extension without dot (lowercase)
     */
    private function getExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if file extension has specific permission
     *
     * @param string $path File path
     * @param string $action Action to check (read, write, open, delete)
     * @return bool
     */
    private function checkExtensionPermission($path, $action) {
        if (is_dir($path)) {
            return true;
        }

        $filename = basename($path);
        $ext = $this->getExtension($filename);

        if (isset($this->config['extensionRestrictions'][$ext])) {
            $restrictions = $this->config['extensionRestrictions'][$ext];
            return $restrictions[$action] ?? true;
        }

        if (isset($this->config['defaultExtensionPermissions'][$action])) {
            return $this->config['defaultExtensionPermissions'][$action];
        }

        return true;
    }
}