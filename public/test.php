<?php

// Load Composer autoloader - path adjusted assuming test.php is in public folder
require __DIR__.'/../vendor/autoload.php';
// Load Laravel application instance - path adjusted assuming test.php is in public folder
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap(); // Bootstrap the application to use helpers like storage_path()

// Define the relative path to the file within storage/app/public
$relativePath = 'uploads/homepage/logo/2BKmc8jud60wjqDkN7FGBvhMPivfMOOKlAuUeXa.png'; // !!! تأكد من مطابقة اسم الملف هنا !!!

// Get the correct physical path to the file using storage_path()
$physicalPath = storage_path('app/public/' . $relativePath);

echo "Checking file: " . htmlspecialchars($physicalPath) . "<br>"; // Use htmlspecialchars for safety
// Check if the file exists physically
echo "File exists: " . (file_exists($physicalPath) ? 'Yes' : 'No') . "<br>";

echo "<hr>"; // Horizontal rule for separation

// Get the correct physical path to the directory using storage_path()
$physicalDir = storage_path('app/public/uploads/homepage/logo');

echo "Listing directory: " . htmlspecialchars($physicalDir) . "<br>"; // Use htmlspecialchars for safety
// List files in the physical directory
if (is_dir($physicalDir)) {
    $files = scandir($physicalDir);
    // Filter out . and .. entries
    $files = array_filter($files, function($item) {
        return !in_array($item, ['.', '..']);
    });
    echo "Files: <pre>" . htmlspecialchars(print_r($files, true)) . "</pre>"; // Use htmlspecialchars for safety
} else {
    echo "Directory does not exist: " . htmlspecialchars($physicalDir);
}