<?php
/**
 * // src/chunkuploader.php
 * My File Manager - Chunked Upload Handler
 *
 * Handles large file uploads in chunks for better reliability
 * This file can be safely excluded from HTTP access
 *
 * @package MyFileManager
 * @author Oscar Cosimo & MYETV Team
 * @license MIT
 */

class ChunkUploader {
    private $config;
    private $tempDir;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config) {
        $this->config = is_array($config) ? $config : [];
        $this->tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'myfilemanager_chunks';

        // Create temp directory for chunks
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0750, true);
        }
    }

    /**
     * Handle file upload (chunked or regular)
     *
     * @param array $file $_FILES entry
     * @param string $targetPath Target directory
     * @param array $params Request parameters
     * @return array Upload result
     * @throws Exception
     */
    public function handleUpload($file, $targetPath, $params) {
        $params = is_array($params) ? $params : [];

        // Handle multiple files or single file
        if (isset($file['tmp_name']) && is_array($file['tmp_name'])) {
            $tmpName = $file['tmp_name'][0] ?? null;
            $fileName = $file['name'][0] ?? null;
            $fileSize = (int)($file['size'][0] ?? 0);
            $fileError = (int)($file['error'][0] ?? UPLOAD_ERR_NO_FILE);
        } else {
            $tmpName = $file['tmp_name'] ?? null;
            $fileName = $file['name'] ?? null;
            $fileSize = (int)($file['size'] ?? 0);
            $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        }

        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($fileError), 400);
        }

        if (!$tmpName || !is_uploaded_file($tmpName)) {
            throw new Exception('No file uploaded', 400);
        }

        if (!is_dir($targetPath) || !is_writable($targetPath)) {
            throw new Exception('Target directory is not writable', 500);
        }

        $maxFileSize = isset($this->config['maxFileSize']) ? (int)$this->config['maxFileSize'] : PHP_INT_MAX;

        // Check single chunk/file size
        if ($fileSize <= 0) {
            throw new Exception('Empty files not allowed', 400);
        }
        if ($fileSize > $maxFileSize) {
            throw new Exception('File too large', 413);
        }

        // Sanitize filename
        $security = new Security();
        $filename = $security->sanitizeFilename((string)$fileName);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new Exception('Invalid filename', 400);
        }

        // Check for blocked extensions
        $banExtensions = $this->config['banExtensions'] ?? [];
        if ($security->hasBlockedExtension($filename, $banExtensions)) {
            throw new Exception('File type not allowed', 415);
        }

        // Read chunk parameters from $params first, falling back to $_POST
        $chunkIndex = isset($params['part']) ? (int)$params['part'] : (isset($_POST['part']) ? (int)$_POST['part'] : 0);
        $totalChunks = isset($params['parts']) ? (int)$params['parts'] : (isset($_POST['parts']) ? (int)$_POST['parts'] : 1);

        if ($totalChunks < 1) {
            throw new Exception('Invalid total chunks count', 400);
        }
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw new Exception('Invalid chunk index', 400);
        }

        // Sanitize uploadId strictly to prevent directory traversal in tempDir
        $rawUploadId = $params['id'] ?? ($_POST['id'] ?? '');
        $uploadId = $security->sanitizeUploadId((string)$rawUploadId);

        if ($uploadId === '') {
            if ($totalChunks > 1) {
                throw new Exception('Invalid upload identifier', 400);
            }
            $uploadId = bin2hex(random_bytes(16));
        }

        $normalizedFile = [
            'tmp_name' => $tmpName,
            'name' => $fileName,
            'size' => $fileSize,
            'error' => $fileError
        ];

        // Single file upload (no chunks)
        if ($totalChunks === 1) {
            return $this->handleSingleUpload($normalizedFile, $targetPath, $filename);
        }

        // Chunked upload
        return $this->handleChunkedUpload($normalizedFile, $targetPath, $filename, $chunkIndex, $totalChunks, $uploadId);
    }

    /**
     * Handle single file upload
     *
     * @param array $file File data
     * @param string $targetPath Target directory
     * @param string $filename Sanitized filename
     * @return array Result
     * @throws Exception
     */
    private function handleSingleUpload($file, $targetPath, $filename) {
        $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($finalPath)) {
            $filename = $this->getUniqueFilename($targetPath, $filename);
            $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;
        }

        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            throw new Exception('Failed to save file', 500);
        }

        $actualSize = @filesize($finalPath);
        $maxFileSize = isset($this->config['maxFileSize']) ? (int)$this->config['maxFileSize'] : PHP_INT_MAX;
        if ($actualSize === false || $actualSize > $maxFileSize) {
            @unlink($finalPath);
            throw new Exception('File too large', 413);
        }

        $this->validateSavedFile($finalPath);
        @chmod($finalPath, 0640);

        return ['path' => $finalPath, 'completed' => true];
    }

    /**
     * Handle chunked upload
     *
     * @param array $file File data
     * @param string $targetPath Target directory
     * @param string $filename Sanitized filename
     * @param int $chunkIndex Current chunk index
     * @param int $totalChunks Total chunks
     * @param string $uploadId Sanitized upload ID
     * @return array Result
     * @throws Exception
     */
    private function handleChunkedUpload($file, $targetPath, $filename, $chunkIndex, $totalChunks, $uploadId) {
        $chunkDir = $this->tempDir . DIRECTORY_SEPARATOR . $uploadId;

        if (!is_dir($chunkDir) && !mkdir($chunkDir, 0750, true) && !is_dir($chunkDir)) {
            throw new Exception('Failed to create chunk directory', 500);
        }

        // Verify chunkDir resolves inside tempDir
        $realTemp = realpath($this->tempDir);
        $realChunkDir = realpath($chunkDir);
        if ($realTemp === false || $realChunkDir === false || strpos($realChunkDir, $realTemp . DIRECTORY_SEPARATOR) !== 0) {
            throw new Exception('Invalid chunk directory', 403);
        }

        $chunkPath = $realChunkDir . DIRECTORY_SEPARATOR . 'chunk_' . $chunkIndex;
        if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
            throw new Exception('Failed to save chunk', 500);
        }

        // Enforce maxFileSize (and optional quotaFree) across all accumulated chunks
        $maxFileSize = isset($this->config['maxFileSize']) ? (int)$this->config['maxFileSize'] : PHP_INT_MAX;
        $accumulatedSize = 0;
        $chunkFiles = glob($realChunkDir . DIRECTORY_SEPARATOR . 'chunk_*') ?: [];
        foreach ($chunkFiles as $cf) {
            if (is_file($cf)) {
                $accumulatedSize += (int)@filesize($cf);
            }
        }

        if ($accumulatedSize > $maxFileSize) {
            $this->cleanupChunks($realChunkDir);
            throw new Exception('File too large', 413);
        }

        if (isset($this->config['quotaFree']) && $this->config['quotaFree'] >= 0 && $accumulatedSize > (int)$this->config['quotaFree']) {
            $this->cleanupChunks($realChunkDir);
            throw new Exception('Quota exceeded', 507);
        }

        $receivedChunks = count($chunkFiles);

        if ($receivedChunks >= $totalChunks) {
            // Verify all expected chunk indices exist before merging
            $allPresent = true;
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!is_file($realChunkDir . DIRECTORY_SEPARATOR . 'chunk_' . $i)) {
                    $allPresent = false;
                    break;
                }
            }

            if ($allPresent) {
                return $this->mergeChunks($realChunkDir, $targetPath, $filename, $totalChunks);
            }
        }

        return [
            'path' => null,
            'completed' => false,
            'received' => $receivedChunks,
            'total' => $totalChunks
        ];
    }

    /**
     * Merge all chunks into final file using streaming (low memory usage)
     *
     * @param string $chunkDir Chunk directory
     * @param string $targetPath Target directory
     * @param string $filename Sanitized filename
     * @param int $totalChunks Total chunks
     * @return array Result
     * @throws Exception
     */
    private function mergeChunks($chunkDir, $targetPath, $filename, $totalChunks) {
        $lockPath = $chunkDir . DIRECTORY_SEPARATOR . 'merge.lock';
        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp) {
            flock($lockFp, LOCK_EX);
        }

        $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($finalPath)) {
            $filename = $this->getUniqueFilename($targetPath, $filename);
            $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;
        }

        $finalFile = @fopen($finalPath, 'wb');
        if (!$finalFile) {
            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
            $this->cleanupChunks($chunkDir);
            throw new Exception('Failed to create final file', 500);
        }

        $maxFileSize = isset($this->config['maxFileSize']) ? (int)$this->config['maxFileSize'] : PHP_INT_MAX;
        $totalWritten = 0;

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . 'chunk_' . $i;
                if (!is_file($chunkPath)) {
                    throw new Exception('Missing chunk ' . $i, 500);
                }

                $in = @fopen($chunkPath, 'rb');
                if (!$in) {
                    throw new Exception('Failed to read chunk ' . $i, 500);
                }

                $copied = stream_copy_to_stream($in, $finalFile);
                fclose($in);

                if ($copied === false) {
                    throw new Exception('Failed to merge chunk ' . $i, 500);
                }

                $totalWritten += $copied;
                if ($totalWritten > $maxFileSize) {
                    throw new Exception('File too large', 413);
                }
            }

            fclose($finalFile);
            $finalFile = null;

            // Validate MIME type on merged file
            $this->validateSavedFile($finalPath);
            @chmod($finalPath, 0640);

            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                $lockFp = null;
            }
            $this->cleanupChunks($chunkDir);

            return ['path' => $finalPath, 'completed' => true];
        } catch (Exception $e) {
            if ($finalFile) {
                fclose($finalFile);
            }
            if (file_exists($finalPath)) {
                @unlink($finalPath);
            }
            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
            $this->cleanupChunks($chunkDir);
            throw $e;
        }
    }

    /**
     * Validate MIME type of saved file
     *
     * @param string $filePath File path
     * @throws Exception
     */
    private function validateSavedFile($filePath) {
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
            }
        }
        if (!$mime && function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
        }
        $mime = $mime ?: 'application/octet-stream';

        $allowedMimeTypes = $this->config['allowedMimeTypes'] ?? [];
        if (!empty($allowedMimeTypes)) {
            $security = new Security();
            if (!$security->isAllowedMimeType($mime, $allowedMimeTypes)) {
                @unlink($filePath);
                throw new Exception('MIME type not allowed: ' . $mime, 415);
            }
        }
    }

    /**
     * Get human-readable PHP upload error message
     *
     * @param int $errorCode PHP UPLOAD_ERR_* code
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds maximum upload size';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary upload folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by PHP extension';
            default:
                return 'Upload error';
        }
    }

    /**
     * Get unique filename if file exists
     *
     * @param string $path Directory path
     * @param string $filename Original filename
     * @return string Unique filename
     */
    private function getUniqueFilename($path, $filename) {
        $info = pathinfo($filename);
        $basename = $info['filename'];
        $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        $counter = 1;
        while (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            $filename = $basename . '_' . $counter . $extension;
            $counter++;
        }

        return $filename;
    }

    /**
     * Clean up chunk files and lock file
     *
     * @param string $chunkDir Chunk directory
     */
    private function cleanupChunks($chunkDir) {
        if (!is_dir($chunkDir)) {
            return;
        }

        $items = @scandir($chunkDir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $itemPath = $chunkDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($itemPath)) {
                    @unlink($itemPath);
                }
            }
        }

        @rmdir($chunkDir);
    }

    /**
     * Clean up old abandoned chunks (cron job)
     *
     * @param int $maxAge Maximum age in seconds
     */
    public function cleanupOldChunks($maxAge = 86400) {
        $dirs = glob($this->tempDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            $age = time() - (int)@filemtime($dir);
            if ($age > $maxAge) {
                $this->cleanupChunks($dir);
            }
        }
    }
}