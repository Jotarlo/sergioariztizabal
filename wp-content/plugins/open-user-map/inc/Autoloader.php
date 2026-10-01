<?php
/**
 * Custom PSR-4 Autoloader for Open User Map
 * 
 * Lightweight autoloader that replaces Composer for OUM classes.
 * Only handles OpenUserMapPlugin namespace and ensures complete isolation
 * from other plugins' autoloaders.
 * 
 * @package OpenUserMapPlugin
 */

namespace OpenUserMapPlugin;

class Autoloader {
    
    /**
     * @var string Plugin root directory
     */
    private static $plugin_dir = '';
    
    /**
     * @var string Namespace prefix (without trailing backslash)
     */
    private static $namespace_prefix = 'OpenUserMapPlugin';
    
    /**
     * @var bool Flag to prevent multiple registrations
     */
    private static $registered = false;
    
    /**
     * @var array Cache for found classes (prevents redundant file checks)
     */
    private static $class_cache = array();
    
    /**
     * Initialize the autoloader
     * 
     * @param string $plugin_dir Absolute path to plugin root directory
     * @return void
     */
    public static function init($plugin_dir) {
        // Prevent multiple registrations
        if (self::$registered) {
            return;
        }
        
        // Validate plugin directory exists
        if (empty($plugin_dir) || !is_dir($plugin_dir)) {
            return;
        }
        
        self::$plugin_dir = rtrim($plugin_dir, '/\\');
        
        // Register autoloader with high priority (prepend = true)
        spl_autoload_register(array(__CLASS__, 'loadClass'), true, true);
        
        self::$registered = true;
    }
    
    /**
     * Autoload function - PSR-4 compatible
     * 
     * This method ONLY loads classes from the OpenUserMapPlugin namespace
     * and validates that all file paths are within the OUM plugin directory.
     * 
     * @param string $class Fully qualified class name
     * @return bool True if class was loaded, false otherwise
     */
    public static function loadClass($class) {
        // CRITICAL: ONLY handle OpenUserMapPlugin namespace
        // Reject any other namespace immediately to prevent interference
        $namespace_with_separator = self::$namespace_prefix . '\\';
        if (strpos($class, $namespace_with_separator) !== 0) {
            return false;
        }
        
        // Check cache first for performance
        if (isset(self::$class_cache[$class])) {
            $file = self::$class_cache[$class];
            if (file_exists($file) && is_readable($file)) {
                // Use require_once to prevent duplicate includes
                require_once $file;
                return true;
            }
            // Cache miss - remove invalid entry
            unset(self::$class_cache[$class]);
        }
        
        // Remove namespace prefix dynamically (more robust than hardcoded length)
        $namespace_length = strlen($namespace_with_separator);
        $relative_class = substr($class, $namespace_length);
        
        // Validate relative class is not empty
        if (empty($relative_class)) {
            return false;
        }
        
        // Convert namespace separators to directory separators
        $file_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);
        
        // Build full file path: plugin_dir/inc/Namespace/Class.php
        $file = self::$plugin_dir . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . $file_path . '.php';
        
        // Normalize path and resolve symlinks
        // Use realpath() but have fallback for edge cases (Windows, permissions, etc.)
        $real_file = realpath($file);
        
        // Fallback: if realpath fails, use original path but validate it exists
        if ($real_file === false) {
            // realpath() can fail on Windows or with certain permissions
            // Fall back to original path but validate it's within plugin directory
            $normalized_file = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file);
            $normalized_plugin_dir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, self::$plugin_dir);
            
            // Ensure file path starts with plugin directory
            if (strpos($normalized_file, $normalized_plugin_dir) !== 0) {
                return false;
            }
            
            // Use normalized path if file exists
            if (file_exists($normalized_file) && is_readable($normalized_file)) {
                $real_file = $normalized_file;
            } else {
                return false;
            }
        }
        
        // Get real path of inc directory for validation
        $inc_dir = self::$plugin_dir . DIRECTORY_SEPARATOR . 'inc';
        $real_inc_dir = realpath($inc_dir);
        
        // Fallback for inc directory path resolution
        if ($real_inc_dir === false) {
            $normalized_inc_dir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $inc_dir);
            if (is_dir($normalized_inc_dir)) {
                $real_inc_dir = $normalized_inc_dir;
            } else {
                return false;
            }
        }
        
        // CRITICAL SECURITY CHECK: Ensure file is within inc directory
        // This prevents loading files from other plugins or outside the plugin
        // Use case-insensitive comparison on Windows for better compatibility
        $file_check = $real_file;
        $dir_check = $real_inc_dir;
        
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: case-insensitive comparison
            $file_check = strtolower($file_check);
            $dir_check = strtolower($dir_check);
        }
        
        if (strpos($file_check, $dir_check) !== 0) {
            // File is outside OUM inc directory - reject it
            return false;
        }
        
        // Verify file exists and is readable
        if (!file_exists($real_file) || !is_readable($real_file)) {
            return false;
        }
        
        // Cache the valid file path for future requests
        self::$class_cache[$class] = $real_file;
        
        // Load the class file
        // Note: We've already validated the file exists and is readable above
        // If the file has syntax errors, PHP will throw a fatal error (expected behavior)
        require_once $real_file;
        
        return true;
    }
    
    /**
     * Get plugin directory
     * 
     * @return string Absolute path to plugin root directory
     */
    public static function getPluginDir() {
        return self::$plugin_dir;
    }
    
    /**
     * Clear class cache (useful for testing/debugging)
     * 
     * @return void
     */
    public static function clearCache() {
        self::$class_cache = array();
    }
    
    /**
     * Get cache statistics (for debugging)
     * 
     * @return array Cache information
     */
    public static function getCacheStats() {
        return array(
            'cached_classes' => count(self::$class_cache),
            'plugin_dir' => self::$plugin_dir,
        );
    }
}

