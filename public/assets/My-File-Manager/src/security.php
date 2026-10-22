<?php
/**
 * My File Manager - Security Utilities
 * 
 * Handles security validation, path traversal prevention, token generation
 * This file can be safely excluded from http access
 * 
 * @package MyFileManager
 * @author MyeTV Team
 * @license MIT
 */

class Security {
    
    /**
     * Check if path is safe (no traversal attacks)
     * 
     * @param string $path Path to check
     * @param string $basePath Base path
     * @return bool
     */
    public function isPathSafe($path, $basePath) {
    // Remove URL encoding
    $path = urldecode($path);
    
    // Check for path traversal patterns
    if (strpos($path, '..') !== false) {
        error_log("isPathSafe FAILED: contains ..");
        return false;
    }
    
    // Check for null bytes
    if (strpos($path, "\0") !== false) {
        error_log("isPathSafe FAILED: contains null byte");
        return false;
    }
    
    return true;
}

    
    /**
     * Sanitize filename
     * 
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public function sanitizeFilename($filename) {
        // Remove path separators
        $filename = str_replace(['/', '\\', '..'], '', $filename);
        
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);
        
        // Remove control characters
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Generate security token
     * 
     * @param string $secret Secret key
     * @param string $userId User ID
     * @return string Token
     */
    public static function generateToken($secret, $userId) {
        $timestamp = time();
        $data = $userId . '|' . $timestamp;
        $signature = hash_hmac('sha256', $data, $secret);
        
        return base64_encode($data . '|' . $signature);
    }
    
    /**
     * Validate security token
     * 
     * @param string $token Token to validate
     * @param string $secret Secret key
     * @param string $userId User ID
     * @return bool
     */
    public static function validateToken($token, $secret, $userId) {
        $decoded = base64_decode($token);
        
        if (!$decoded) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($tokenUserId, $timestamp, $signature) = $parts;
        
        // Check user ID matches
        if ($tokenUserId !== $userId) {
            return false;
        }
        
        // Check token age (valid for 12 hours)
        if (time() - $timestamp > 43200) { // 12 hours = 12 * 3600 = 43200 seconds (can be adjusted as needed)
            return false;
        }
        
        // Verify signature
        $data = $tokenUserId . '|' . $timestamp;
        $expectedSignature = hash_hmac('sha256', $data, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Validate MIME type
     * 
     * @param string $mime MIME type to check
     * @param array $allowed Allowed MIME patterns
     * @return bool
     */
    public function isAllowedMimeType($mime, $allowed) {
        foreach ($allowed as $pattern) {
            // Convert wildcard pattern to regex
            $regex = str_replace('*', '.*', $pattern);
            
            if (preg_match('#^' . $regex . '$#i', $mime)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check file extension
     * 
     * @param string $filename Filename
     * @param array $blockedExtensions Blocked extensions
     * @return bool
     */
    public function hasBlockedExtension($filename, $blockedExtensions = []) {
    $blocked = $blockedExtensions ?: [];  // banned extensions ar ein connector.php config
    
    if (empty($blocked)) {
        return false;
    }
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $blocked);
}

}