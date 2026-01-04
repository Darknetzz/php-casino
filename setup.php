<?php
/**
 * Setup script to create data directory and set permissions
 * Run this once via command line: php setup.php
 * Or access via browser if you have proper permissions
 */

require_once __DIR__ . '/includes/database.php';

$dataDir = __DIR__ . '/data';
$dbPath = $dataDir . '/casino.db';

echo "Casino Setup Script\n";
echo "===================\n\n";

// Try to create data directory
if (!is_dir($dataDir)) {
    echo "Creating data directory...\n";
    if (@mkdir($dataDir, 0755, true)) {
        echo "✓ Data directory created successfully\n";
    } else {
        echo "✗ Failed to create data directory\n";
        echo "Please run manually: mkdir -p $dataDir && chmod 755 $dataDir\n";
        echo "Or as root: chown www-data:www-data $dataDir\n";
        exit(1);
    }
} else {
    echo "✓ Data directory already exists\n";
}

// Check if directory is writable
if (is_writable($dataDir)) {
    echo "✓ Data directory is writable\n";
} else {
    echo "✗ Data directory is not writable\n";
    echo "Please run: chmod 755 $dataDir\n";
    echo "Or: chown www-data:www-data $dataDir\n";
    exit(1);
}

// Check if database file exists and fix permissions
if (file_exists($dbPath)) {
    echo "\nDatabase file exists, checking permissions...\n";
    if (!is_writable($dbPath)) {
        echo "⚠ Database file is not writable, attempting to fix...\n";
        if (@chmod($dbPath, 0664)) {
            echo "✓ Database file permissions updated\n";
        } else {
            echo "✗ Could not update database file permissions automatically\n";
            echo "Please run: chmod 664 $dbPath\n";
            echo "Or: chown www-data:www-data $dbPath\n";
        }
    } else {
        echo "✓ Database file is writable\n";
    }
}

// Try to create/test database file
echo "\nTesting database access...\n";
try {
    $testDb = new PDO('sqlite:' . $dbPath);
    $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $testDb->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER)");
    $testDb->exec("DROP TABLE IF EXISTS test");
    $testDb = null; // Close connection
    echo "✓ Database can be accessed successfully\n";
} catch (Exception $e) {
    echo "✗ Failed to access database: " . $e->getMessage() . "\n";
    echo "\nIf the database file exists but is readonly, try:\n";
    echo "sudo chmod 664 $dbPath\n";
    echo "sudo chown www-data:www-data $dbPath\n";
    exit(1);
}

echo "\n✓ Setup completed successfully!\n";
echo "You can now access the casino application.\n";
?>
