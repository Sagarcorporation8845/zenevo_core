<?php
/*
--------------------------------------------------------------------------------
-- File: /config/security.php
-- Description: Security enhancements including CSRF protection, input validation, and rate limiting
--------------------------------------------------------------------------------
*/

// CSRF Token Management
class CSRFProtection {
    
    public static function generateToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public static function validateToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is not expired (30 minutes)
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['csrf_token_time'] > 1800) { // 30 minutes
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getTokenInput() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

// Input Validation and Sanitization
class InputValidator {
    
    public static function sanitizeString($input, $maxLength = 255) {
        if ($input === null) return '';
        
        $sanitized = trim($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength > 0) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }
        
        return $sanitized;
    }
    
    public static function sanitizeEmail($email) {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        return $email;
    }
    
    public static function sanitizeInteger($input, $min = null, $max = null) {
        $int = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return false;
        }
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
    
    public static function sanitizeFloat($input, $min = null, $max = null) {
        $float = filter_var($input, FILTER_VALIDATE_FLOAT);
        
        if ($float === false) {
            return false;
        }
        
        if ($min !== null && $float < $min) {
            return false;
        }
        
        if ($max !== null && $float > $max) {
            return false;
        }
        
        return $float;
    }
    
    public static function sanitizeUrl($url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return $url;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function sanitizeFilename($filename) {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove special characters except dots, dashes, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevent double dots
        $filename = preg_replace('/\.+/', '.', $filename);
        
        return $filename;
    }
}

// Rate Limiting
class RateLimiter {
    
    private static function getRedisConnection() {
        // For now, use session-based rate limiting
        // In production, consider using Redis or database
        return null;
    }
    
    public static function checkLimit($identifier, $maxAttempts = 5, $windowMinutes = 15) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $currentTime = time();
        $windowStart = $currentTime - ($windowMinutes * 60);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Remove old attempts outside the window
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$key]) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        $_SESSION[$key][] = $currentTime;
        
        return true;
    }
    
    public static function getRemainingAttempts($identifier, $maxAttempts = 5, $windowMinutes = 15) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $currentTime = time();
        $windowStart = $currentTime - ($windowMinutes * 60);
        
        if (!isset($_SESSION[$key])) {
            return $maxAttempts;
        }
        
        // Count recent attempts
        $recentAttempts = array_filter($_SESSION[$key], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        return max(0, $maxAttempts - count($recentAttempts));
    }
}

// SQL Injection Prevention Helpers
class DatabaseSecurity {
    
    public static function preparePlaceholders($count) {
        return str_repeat('?,', $count - 1) . '?';
    }
    
    public static function validateSQLOperator($operator) {
        $allowedOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        return in_array(strtoupper($operator), $allowedOperators);
    }
    
    public static function validateOrderDirection($direction) {
        return in_array(strtoupper($direction), ['ASC', 'DESC']);
    }
    
    public static function validateColumnName($column, $allowedColumns) {
        return in_array($column, $allowedColumns);
    }
}

// File Upload Security
class FileUploadSecurity {
    
    private static $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $allowedDocumentTypes = ['application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    public static function validateImageUpload($file, $maxSize = 5242880) { // 5MB default
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::$allowedImageTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Check if it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'Not a valid image'];
        }
        
        return ['valid' => true, 'mime_type' => $mimeType, 'dimensions' => $imageInfo];
    }
    
    public static function generateSecureFilename($originalName = null) {
        $extension = '';
        if ($originalName) {
            $extension = '.' . pathinfo($originalName, PATHINFO_EXTENSION);
        }
        
        return uniqid() . '_' . bin2hex(random_bytes(8)) . $extension;
    }
}

// XSS Prevention
class XSSProtection {
    
    public static function cleanOutput($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function cleanHtml($input, $allowedTags = []) {
        if (empty($allowedTags)) {
            return strip_tags($input);
        }
        
        return strip_tags($input, '<' . implode('><', $allowedTags) . '>');
    }
    
    public static function validateJsonInput($input) {
        if (!is_string($input)) {
            return false;
        }
        
        json_decode($input);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

// Session Security
class SessionSecurity {
    
    public static function regenerateSession() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    public static function validateSession($userAgent = null, $ipAddress = null) {
        if (session_status() == PHP_SESSION_NONE) {
            return false;
        }
        
        // Check session age (24 hours max)
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > 86400) {
            session_destroy();
            return false;
        }
        
        // Check user agent consistency
        if ($userAgent && isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $userAgent) {
            session_destroy();
            return false;
        }
        
        // Check IP consistency (optional, can be problematic with proxies)
        if ($ipAddress && isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $ipAddress) {
            session_destroy();
            return false;
        }
        
        return true;
    }
    
    public static function initSecureSession($userAgent = null, $ipAddress = null) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        
        if ($userAgent) {
            $_SESSION['user_agent'] = $userAgent;
        }
        
        if ($ipAddress) {
            $_SESSION['ip_address'] = $ipAddress;
        }
    }
}

?>