<?php
/**
 * XenForo Autoloader
 * Simple autoloader for XenForo deployment
 */

// Only register if not already registered
if (!function_exists('xf_autoloader')) {
    function xf_autoloader($class) {
        // Convert namespace to file path
        $class = str_replace('\\', '/', $class);
        
        // Handle XF namespace
        if (strpos($class, 'XF/') === 0) {
            $class = substr($class, 3);
            $file = __DIR__ . '/../XF/' . $class . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        
        return false;
    }
    
    // Register the autoloader
    spl_autoload_register('xf_autoloader');
}
