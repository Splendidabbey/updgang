<?php
/**
 * XenForo Autoloader
 * Minimal autoloader for XenForo deployment
 */

// Simple autoloader that XenForo can work with
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', '/', $class);
    
    // Handle XF namespace
    if (strpos($class, 'XF/') === 0) {
        $class = substr($class, 3);
        $file = __DIR__ . '/../XF/' . $class . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
