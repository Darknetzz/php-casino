<?php
// Determine the base path for scripts/CSS based on current directory
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/games/') !== false) {
    $basePath = '../';
} elseif (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    $basePath = '../';
} else {
    $basePath = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Casino'; ?> - Casino</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo $basePath; ?>js/common.js"></script>
    <script src="<?php echo $basePath; ?>js/navbar.js"></script>
</head>
<body>
