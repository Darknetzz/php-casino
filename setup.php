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

// Try to create database file
echo "\nTesting database creation...\n";
try {
    $testDb = new PDO('sqlite:' . $dbPath);
    $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $testDb->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER)");
    $testDb = null; // Close connection
    unlink($dbPath); // Delete test file
    echo "✓ Database can be created successfully\n";
} catch (Exception $e) {
    echo "✗ Failed to create database: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Setup completed successfully!\n";
echo "You can now access the casino application.\n";
?>
