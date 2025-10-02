#!/bin/bash
# Build script for XenForo deployment

echo "Building XenForo application..."

# Create vendor directory if it doesn't exist
mkdir -p src/vendor

# Create a simple autoloader for XenForo
cat > src/vendor/autoload.php << 'EOF'
<?php
/**
 * XenForo Autoloader
 * This file provides basic autoloading for XenForo without Composer dependencies
 */

// Basic autoloader for XenForo classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', '/', $class);
    
    // Remove XF\ prefix and convert to file path
    if (strpos($class, 'XF/') === 0) {
        $class = substr($class, 3);
        $file = __DIR__ . '/../XF/' . $class . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

// Load XenForo core files
$coreFiles = [
    'XF.php',
    'XF/App.php',
    'XF/Container.php',
    'XF/Entity.php',
    'XF/Repository.php',
    'XF/Service.php',
    'XF/Controller.php',
    'XF/ControllerPlugin.php',
    'XF/Response.php',
    'XF/Request.php',
    'XF/Session.php',
    'XF/Db.php',
    'XF/Db/AbstractAdapter.php',
    'XF/Db/Mysqli.php',
    'XF/Db/Mysql.php',
    'XF/Error.php',
    'XF/Exception.php',
    'XF/Language.php',
    'XF/Template.php',
    'XF/Templater.php',
    'XF/Util/Arr.php',
    'XF/Util/File.php',
    'XF/Util/Hash.php',
    'XF/Util/Ip.php',
    'XF/Util/Json.php',
    'XF/Util/Php.php',
    'XF/Util/Random.php',
    'XF/Util/Time.php',
    'XF/Util/Url.php',
    'XF/Util/Xml.php'
];

foreach ($coreFiles as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}
EOF

echo "Autoloader created successfully!"
echo "Build completed!"
