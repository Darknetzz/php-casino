<?php
/**
 * Utility script to make a user an admin
 * Usage: php make_admin.php <username>
 */

require_once __DIR__ . '/includes/database.php';

if ($argc < 2) {
    echo "Usage: php make_admin.php <username>\n";
    exit(1);
}

$username = $argv[1];
$db = new Database();

$user = $db->getUserByUsername($username);

if (!$user) {
    echo "Error: User '$username' not found.\n";
    exit(1);
}

$db->setAdmin($user['id'], 1);
echo "User '$username' is now an admin.\n";
?>
