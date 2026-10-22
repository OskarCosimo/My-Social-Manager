<?php
/**
 * My File Manager - Chunked Upload Handler
 * 
 * Handles large file uploads in chunks for better reliability
 * This file can be safely excluded from http access
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
        $this->config = $config;
        $this->tempDir = sys_get_temp_dir() . '/myfilemanager_chunks';
        
        // Create temp directory for chunks
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0750, true);
        }
    }
    
    /**
     * Handle file upload (chunked or regular)
     * 
     * @param array $file $_FILES array
     * @param string $targetPath Target directory
     * @param array $params Request parameters
     * @return array Upload result
     * @throws Exception
     */
    public function handleUpload($file, $targetPath, $params) {
    // Handle multiple files or single file
    if (isset($file['tmp_name']) && is_array($file['tmp_name'])) {
        // Multiple files - take first one
        $tmpName = $file['tmp_name'][0];
        $fileName = $file['name'][0];
        $fileSize = $file['size'][0];
        $fileError = $file['error'][0];
    } else {
        // Single file
        $tmpName = $file['tmp_name'] ?? null;
        $fileName = $file['name'] ?? null;
        $fileSize = $file['size'] ?? 0;
        $fileError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    }
    
    if (!$tmpName || !is_uploaded_file($tmpName)) {
        throw new Exception('No file uploaded', 400);
    }
    
    // Check file size
    if ($fileSize > $this->config['maxFileSize']) {
        throw new Exception('File too large', 413);
    }
    
    // Sanitize filename
    $security = new Security();
    $filename = $security->sanitizeFilename($fileName);
    
    // Check for blocked extensions
    if ($security->hasBlockedExtension($filename, $this->config['banExtensions'])) {
        throw new Exception('File type not allowed', 415);
    }
    
    // Get chunk parameters
    $chunkIndex = isset($_POST['part']) ? (int)$_POST['part'] : 0;
$totalChunks = isset($_POST['parts']) ? (int)$_POST['parts'] : 1;
$uploadId = $_POST['id'] ?? md5($_FILES['upload']['name'] . time());
    
    // Create file array for consistency
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
     * @param string $filename Filename
     * @return array Result
     * @throws Exception
     */
    private function handleSingleUpload($file, $targetPath, $filename) {
    $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;

    // Check if file exists
    if (file_exists($finalPath)) {
        $filename = $this->getUniqueFilename($targetPath, $filename);
        $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;
    }

    // Move uploaded file first
    if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
        throw new Exception('Failed to save file', 500);
    }

    // Use finfo instead of mime_content_type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $finalPath);
        finfo_close($finfo);
    } else {
        $mime = mime_content_type($finalPath);
    }

    // Validate MIME type
    $security = new Security();
    if (!$security->isAllowedMimeType($mime, $this->config['allowedMimeTypes'])) {
        unlink($finalPath);
        throw new Exception('MIME type not allowed: ' . $mime, 415);
    }

    // Set permissions
    chmod($finalPath, 0640);

    return ['path' => $finalPath, 'completed' => true];
}
    
    /**
     * Handle chunked upload
     * 
     * @param array $file File data
     * @param string $targetPath Target directory
     * @param string $filename Filename
     * @param int $chunkIndex Current chunk index
     * @param int $totalChunks Total chunks
     * @param string $uploadId Upload ID
     * @return array Result
     * @throws Exception
     */
    private function handleChunkedUpload($file, $targetPath, $filename, $chunkIndex, $totalChunks, $uploadId) {
    $chunkDir = $this->tempDir . '/' . $uploadId;
    
    // Create chunk directory
    if (!is_dir($chunkDir)) {
        mkdir($chunkDir, 0750, true);
    }
    
    // Save current chunk  
    $chunkPath = $chunkDir . '/chunk_' . $chunkIndex;
    if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk', 500);
    }
    
    // count chunks received
    $receivedChunks = count(glob($chunkDir . '/chunk_*'));
    
    // Merge if all chunks received
    if ($receivedChunks >= $totalChunks) {
        return $this->mergeChunks($chunkDir, $targetPath, $filename, $totalChunks);
    }
    
    // More chunks expected
    return array('path' => null, 'completed' => false, 'received' => $receivedChunks, 'total' => $totalChunks);
}
    
    /**
     * Merge all chunks into final file
     * 
     * @param string $chunkDir Chunk directory
     * @param string $targetPath Target directory
     * @param string $filename Filename
     * @param int $totalChunks Total chunks
     * @return array Result
     * @throws Exception
     */
    private function mergeChunks($chunkDir, $targetPath, $filename, $totalChunks) {
    $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;

    // Check if file exists
    if (file_exists($finalPath)) {
        $filename = $this->getUniqueFilename($targetPath, $filename);
        $finalPath = $targetPath . DIRECTORY_SEPARATOR . $filename;
    }

    // Open final file for writing
    $finalFile = fopen($finalPath, 'wb');
    if (!$finalFile) {
        throw new Exception('Failed to create final file', 500);
    }

    // Write chunks in order
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $chunkDir . '/chunk_' . $i;
        if (!file_exists($chunkPath)) {
            fclose($finalFile);
            unlink($finalPath);
            throw new Exception('Missing chunk ' . $i, 500);
        }
        $chunkData = file_get_contents($chunkPath);
        fwrite($finalFile, $chunkData);
    }
    fclose($finalFile);

    // Use finfo instead of mime_content_type (more reliable)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $finalPath);
        finfo_close($finfo);
    } else {
        // Fallback to mime_content_type
        $mime = mime_content_type($finalPath);
    }

    // Validate MIME type
    $security = new Security();
    if (!$security->isAllowedMimeType($mime, $this->config['allowedMimeTypes'])) {
        unlink($finalPath);
        throw new Exception('MIME type not allowed: ' . $mime, 415);
    }

    // Set permissions
    chmod($finalPath, 0640);

    // Clean up chunks
    $this->cleanupChunks($chunkDir);

    return ['path' => $finalPath, 'completed' => true];
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
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
        
        $counter = 1;
        while (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            $filename = $basename . '_' . $counter . $extension;
            $counter++;
        }
        
        return $filename;
    }
    
    /**
     * Clean up chunk files
     * 
     * @param string $chunkDir Chunk directory
     */
    private function cleanupChunks($chunkDir) {
        if (!is_dir($chunkDir)) return;
        
        $files = glob($chunkDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir($chunkDir);
    }
    
    /**
     * Clean up old abandoned chunks (cron job)
     * 
     * @param int $maxAge Maximum age in seconds
     */
    public function cleanupOldChunks($maxAge = 86400) {
        $dirs = glob($this->tempDir . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $age = time() - filemtime($dir);
            
            if ($age > $maxAge) {
                $this->cleanupChunks($dir);
            }
        }
    }
}