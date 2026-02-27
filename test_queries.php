<?php
require_once 'includes/db.php';
require_once 'includes/config.php';

echo "<h1>Testing Database Queries</h1>";

$tables = ['videos', 'blog_posts', 'news', 'research', 'founders', 'creator', 'media_features', 'sponsors'];

foreach ($tables as $table) {
    echo "<h2>Testing $table</h2>";
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "✓ Table exists with {$row['count']} rows<br>";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "<br>";
    }
}

// Test specific queries
echo "<h2>Testing Founder Query</h2>";
$result = mysqli_query($conn, "SELECT * FROM founders WHERE is_active = 1 LIMIT 1");
if ($result) {
    echo "✓ Founder query works<br>";
    if (mysqli_num_rows($result) > 0) {
        $founder = mysqli_fetch_assoc($result);
        echo "Founder found: " . $founder['name'] . "<br>";
    } else {
        echo "No founder found<br>";
    }
} else {
    echo "✗ Error: " . mysqli_error($conn) . "<br>";
}

echo "<h2>Testing Creator Query</h2>";
$query = "SELECT COUNT(*) as count FROM creator WHERE (author LIKE '%Ibrahim%' OR author = 'MalamIromba') AND is_published = 1";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "✓ Creator query works, found {$row['count']} items<br>";
} else {
    echo "✗ Error: " . mysqli_error($conn) . "<br>";
}
?>