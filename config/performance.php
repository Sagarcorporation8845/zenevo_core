<?php
/*
--------------------------------------------------------------------------------
-- File: /config/performance.php
-- Description: Performance and scalability improvements for the HR platform
--------------------------------------------------------------------------------
*/

// Simple File-based Caching System
class SimpleCache {
    private static $cacheDir = __DIR__ . '/../cache/';
    
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function get($key, $defaultValue = null) {
        self::init();
        
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($filename)) {
            return $defaultValue;
        }
        
        $data = file_get_contents($filename);
        $cache = unserialize($data);
        
        // Check if expired
        if ($cache['expires'] < time()) {
            unlink($filename);
            return $defaultValue;
        }
        
        return $cache['data'];
    }
    
    public static function set($key, $value, $ttl = 3600) {
        self::init();
        
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        $cache = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($filename, serialize($cache), LOCK_EX);
    }
    
    public static function delete($key) {
        self::init();
        
        $filename = self::$cacheDir . md5($key) . '.cache';
        
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
    
    public static function clear() {
        self::init();
        
        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// Database Query Optimization
class DatabaseOptimizer {
    
    public static function getOptimizedEmployeeList($conn, $limit = 100, $offset = 0) {
        $cacheKey = "employees_list_{$limit}_{$offset}";
        $cached = SimpleCache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) as name, 
                       e.designation, e.department, u.email, u.is_active
                FROM employees e 
                JOIN users u ON e.user_id = u.id 
                WHERE u.is_active = 1 
                ORDER BY e.first_name, e.last_name
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        $stmt->close();
        
        // Cache for 10 minutes
        SimpleCache::set($cacheKey, $employees, 600);
        
        return $employees;
    }
    
    public static function getOptimizedAttendanceData($conn, $employeeId, $startDate, $endDate) {
        $cacheKey = "attendance_{$employeeId}_{$startDate}_{$endDate}";
        $cached = SimpleCache::get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT date, clock_in_time, clock_out_time, in_photo_base64, out_photo_base64 
                FROM attendance 
                WHERE employee_id = ? AND date BETWEEN ? AND ? 
                ORDER BY date";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $employeeId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
        
        $stmt->close();
        
        // Cache for 5 minutes
        SimpleCache::set($cacheKey, $attendance, 300);
        
        return $attendance;
    }
    
    public static function invalidateEmployeeCache() {
        $files = glob(SimpleCache::$cacheDir . '*employees*');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public static function invalidateAttendanceCache($employeeId = null) {
        if ($employeeId) {
            $files = glob(SimpleCache::$cacheDir . "*attendance_{$employeeId}_*");
        } else {
            $files = glob(SimpleCache::$cacheDir . '*attendance_*');
        }
        
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

// Memory Usage Optimization
class MemoryOptimizer {
    
    private static $peakMemory = 0;
    
    public static function startMonitoring() {
        self::$peakMemory = memory_get_usage(true);
    }
    
    public static function getCurrentUsage() {
        return memory_get_usage(true);
    }
    
    public static function getPeakUsage() {
        return memory_get_peak_usage(true);
    }
    
    public static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public static function cleanupTemporaryData() {
        // Clear any temporary session data
        if (isset($_SESSION['temp_data'])) {
            unset($_SESSION['temp_data']);
        }
        
        // Force garbage collection
        gc_collect_cycles();
    }
    
    public static function optimizeImageMemory($base64Image) {
        // For large base64 images, we can implement progressive loading
        // or thumbnail generation to reduce memory usage
        
        $imageData = base64_decode($base64Image);
        if (strlen($imageData) > 1024 * 1024) { // 1MB
            // Generate thumbnail for large images
            $image = imagecreatefromstring($imageData);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                
                // Calculate new dimensions (max 300px)
                $maxSize = 300;
                if ($width > $height) {
                    $newWidth = $maxSize;
                    $newHeight = ($height / $width) * $maxSize;
                } else {
                    $newHeight = $maxSize;
                    $newWidth = ($width / $height) * $maxSize;
                }
                
                $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                ob_start();
                imagejpeg($thumbnail, null, 80);
                $thumbnailData = ob_get_contents();
                ob_end_clean();
                
                imagedestroy($image);
                imagedestroy($thumbnail);
                
                return base64_encode($thumbnailData);
            }
        }
        
        return $base64Image;
    }
}

// Database Connection Pooling (Simple Implementation)
class ConnectionPool {
    private static $connections = [];
    private static $maxConnections = 5;
    private static $activeConnections = 0;
    
    public static function getConnection($config) {
        $connectionId = md5(serialize($config));
        
        if (isset(self::$connections[$connectionId]) && !empty(self::$connections[$connectionId])) {
            return array_pop(self::$connections[$connectionId]);
        }
        
        if (self::$activeConnections >= self::$maxConnections) {
            // Wait or return null if max connections reached
            return null;
        }
        
        // Create new connection
        $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
        
        if ($conn->connect_error) {
            return null;
        }
        
        $conn->set_charset('utf8mb4');
        self::$activeConnections++;
        
        return $conn;
    }
    
    public static function releaseConnection($conn, $config) {
        $connectionId = md5(serialize($config));
        
        if (!isset(self::$connections[$connectionId])) {
            self::$connections[$connectionId] = [];
        }
        
        if (count(self::$connections[$connectionId]) < 3) { // Max 3 pooled connections
            self::$connections[$connectionId][] = $conn;
        } else {
            $conn->close();
            self::$activeConnections--;
        }
    }
}

// Asset Optimization
class AssetOptimizer {
    
    public static function generateCriticalCSS() {
        // Generate critical CSS for above-the-fold content
        $criticalCSS = '
        .enhanced-input{border-radius:.375rem;border-width:2px;border-color:#d1d5db;padding:.5rem .75rem;}
        .enhanced-input:focus{border-color:#6366f1;outline:none;box-shadow:0 0 0 3px rgba(99,102,241,.1);}
        .btn-primary{background-color:#1f2937!important;color:#fff!important;border:2px solid #1f2937!important;}
        .btn-primary:hover{background-color:#111827!important;transform:translateY(-1px);}
        .card-enhanced{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);}
        .selfie-container{width:48px!important;height:48px!important;border-radius:50%!important;overflow:hidden!important;}
        ';
        
        return $criticalCSS;
    }
    
    public static function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(': ', ':', $css);
        $css = str_replace(' {', '{', $css);
        $css = str_replace('{ ', '{', $css);
        $css = str_replace(', ', ',', $css);
        
        return trim($css);
    }
    
    public static function shouldCompress($content) {
        return strlen($content) > 1024; // Only compress if larger than 1KB
    }
}

// Performance Monitoring
class PerformanceMonitor {
    private static $startTime;
    private static $queries = [];
    
    public static function start() {
        self::$startTime = microtime(true);
        MemoryOptimizer::startMonitoring();
    }
    
    public static function logQuery($query, $executionTime) {
        self::$queries[] = [
            'query' => $query,
            'time' => $executionTime,
            'memory' => memory_get_usage(true)
        ];
    }
    
    public static function getStats() {
        $endTime = microtime(true);
        $totalTime = $endTime - self::$startTime;
        
        return [
            'total_time' => round($totalTime * 1000, 2) . 'ms',
            'memory_usage' => MemoryOptimizer::formatBytes(MemoryOptimizer::getCurrentUsage()),
            'peak_memory' => MemoryOptimizer::formatBytes(MemoryOptimizer::getPeakUsage()),
            'query_count' => count(self::$queries),
            'slow_queries' => array_filter(self::$queries, function($q) { return $q['time'] > 0.1; })
        ];
    }
}

// Auto-scaling recommendations
class ScalingRecommendations {
    
    public static function analyzeLoad($conn) {
        $recommendations = [];
        
        // Check database size
        $result = $conn->query("SELECT 
            table_name, 
            ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY size_mb DESC");
        
        $totalSize = 0;
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $totalSize += $row['size_mb'];
            $tables[] = $row;
        }
        
        if ($totalSize > 500) { // 500MB
            $recommendations[] = "Consider implementing database partitioning or archiving old data";
        }
        
        // Check for missing indexes
        $slowQueries = PerformanceMonitor::getStats()['slow_queries'];
        if (count($slowQueries) > 5) {
            $recommendations[] = "Consider adding database indexes for frequently queried columns";
        }
        
        // Check memory usage
        $memoryUsage = MemoryOptimizer::getCurrentUsage();
        if ($memoryUsage > 64 * 1024 * 1024) { // 64MB
            $recommendations[] = "Consider implementing more aggressive caching or memory optimization";
        }
        
        return [
            'database_size_mb' => $totalSize,
            'largest_tables' => array_slice($tables, 0, 5),
            'recommendations' => $recommendations
        ];
    }
}

?>