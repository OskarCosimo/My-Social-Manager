<?php
/**
 * // src/security.php
 * My File Manager - Security Utilities
 *
 * Handles security validation, path traversal prevention, token generation
 * This file can be safely excluded from HTTP access
 *
 * @package MyFileManager
 * @author MyeTV Team
 * @license MIT
 */

class Security {

    /**
     * Check if path is safe (no traversal attacks and confined within basePath)
     * Supports both relative paths and resolved absolute paths.
     *
     * @param string $path Path to check
     * @param string $basePath Base root path
     * @return bool
     */
    public function isPathSafe($path, $basePath) {
        if (!is_string($path) || !is_string($basePath) || $basePath === '') {
            return false;
        }

        // Reject null bytes in both raw and URL-decoded input
        $decodedPath = urldecode($path);
        if (strpos($path, "\0") !== false || strpos($decodedPath, "\0") !== false) {
            error_log('isPathSafe FAILED: contains null byte');
            return false;
        }

        // Reject parent directory traversal segments ("..") in both raw and decoded input
        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $path) ||
            preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $decodedPath) ||
            strpos($path, '..') !== false ||
            strpos($decodedPath, '..') !== false) {
            error_log('isPathSafe FAILED: contains ..');
            return false;
        }

        $realBase = realpath($basePath);
        if ($realBase === false) {
            error_log('isPathSafe FAILED: invalid basePath');
            return false;
        }
        $realBase = rtrim($realBase, DIRECTORY_SEPARATOR);

        // Normalize directory separators
        $normalizedInput = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Determine if input is already an absolute path or relative to basePath
        $isAbsolute = (strpos($normalizedInput, DIRECTORY_SEPARATOR) === 0) ||
            (preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $normalizedInput) === 1);

        if ($isAbsolute) {
            $candidate = $normalizedInput;
        } else {
            $trimmedRelative = ltrim($normalizedInput, DIRECTORY_SEPARATOR);
            $candidate = $trimmedRelative === ''
                ? $realBase
                : $realBase . DIRECTORY_SEPARATOR . $trimmedRelative;
        }

        // If target exists (file, directory, or symlink), verify realpath stays inside realBase
        $realCandidate = realpath($candidate);
        if ($realCandidate !== false) {
            if ($realCandidate === $realBase || strpos($realCandidate, $realBase . DIRECTORY_SEPARATOR) === 0) {
                return true;
            }
            error_log('isPathSafe FAILED: resolved path outside basePath');
            return false;
        }

        // Target does not exist yet (e.g., new file/folder): walk up to nearest existing ancestor
        $probe = $candidate;
        while ($probe !== '' && !file_exists($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                return false;
            }
            $probe = $parent;
        }

        $realAncestor = realpath($probe);
        if ($realAncestor === false) {
            return false;
        }

        if ($realAncestor === $realBase || strpos($realAncestor, $realBase . DIRECTORY_SEPARATOR) === 0) {
            return true;
        }

        error_log('isPathSafe FAILED: ancestor path outside basePath');
        return false;
    }

    /**
     * Sanitize filename
     *
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public function sanitizeFilename($filename) {
        if (!is_string($filename)) {
            return '';
        }

        // Remove null bytes and control characters
        $filename = str_replace("\0", '', $filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);

        // Remove path separators and traversal sequences
        $filename = str_replace(['/', '\\', '..'], '', $filename);

        // Trim whitespace and trailing dots (prevents trailing-dot extension bypass)
        $filename = trim($filename);
        $filename = rtrim($filename, '. ');

        // Limit length safely
        if (strlen($filename) > 255) {
            $filename = function_exists('mb_strcut')
                ? mb_strcut($filename, 0, 255, 'UTF-8')
                : substr($filename, 0, 255);
        }

        return $filename;
    }

    /**
     * Sanitize chunk upload identifier to prevent directory traversal
     *
     * @param string $uploadId Raw upload identifier
     * @return string Safe alphanumeric upload identifier
     */
    public function sanitizeUploadId($uploadId) {
        if (!is_string($uploadId)) {
            return '';
        }
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
        if (strlen($clean) > 128) {
            $clean = substr($clean, 0, 128);
        }
        return $clean;
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
        if (!is_string($token) || $token === '' || !is_string($secret) || $secret === '') {
            return false;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        list($tokenUserId, $timestamp, $signature) = $parts;

        // Check user ID matches in constant time
        if (!hash_equals((string)$userId, (string)$tokenUserId)) {
            return false;
        }

        // Validate timestamp format
        if (!ctype_digit((string)$timestamp)) {
            return false;
        }

        $ts = (int)$timestamp;
        $now = time();

        // Reject future timestamps (allow 60s clock skew) and tokens older than 12 hours
        if ($ts > ($now + 60) || ($now - $ts) > 43200) {
            return false;
        }

        // Verify HMAC signature
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
        if (!is_string($mime) || $mime === '' || !is_array($allowed) || empty($allowed)) {
            return false;
        }

        // Strip optional MIME parameters (e.g., "; charset=utf-8")
        $cleanMime = trim(explode(';', $mime)[0]);

        foreach ($allowed as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }
            // Escape regex metacharacters, then restore wildcard "*" as ".*"
            $regex = str_replace('\*', '.*', preg_quote(trim($pattern), '#'));

            if (preg_match('#^' . $regex . '$#i', $cleanMime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check file extension against blocked extensions list
     *
     * @param string $filename Filename
     * @param array $blockedExtensions Blocked extensions
     * @return bool
     */
    public function hasBlockedExtension($filename, $blockedExtensions = []) {
        if (!is_string($filename) || $filename === '' || empty($blockedExtensions) || !is_array($blockedExtensions)) {
            return false;
        }

        $normalizedBlocked = array_map('strtolower', $blockedExtensions);

        // Trim whitespace and trailing dots to prevent "shell.php." bypass
        $cleanName = rtrim(trim($filename), '. ');
        if ($cleanName === '') {
            return false;
        }

        $extension = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));
        if ($extension !== '' && in_array($extension, $normalizedBlocked, true)) {
            return true;
        }

        // Check compound extensions (e.g., "aspx.cs")
        $parts = explode('.', strtolower($cleanName));
        if (count($parts) >= 3) {
            $compoundExt = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
            if (in_array($compoundExt, $normalizedBlocked, true)) {
                return true;
            }
        }

        return false;
    }
}