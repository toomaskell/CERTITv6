<?php
// Test if form submission is working
session_start();

echo "<h2>Application Form Debug Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: #d4edda; padding: 20px; margin: 20px 0;'>";
    echo "<h3>✓ Form Submitted Successfully!</h3>";
    echo "<pre>";
    echo "POST data received:\n";
    print_r($_POST);
    echo "</pre>";
    echo "</div>";
} else {
    echo "<p>Form not submitted yet. Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";
}

// Test database connection
require_once 'config/constants.php';
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection OK</p>";
    
    // Check if applications table exists
    $stmt = $db->query("SHOW TABLES LIKE 'applications'");
    if ($stmt->fetch()) {
        echo "<p style='color: green;'>✓ Applications table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Applications table NOT found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}
?>

<form method="POST" action="">
    <h3>Simple Test Form</h3>
    <input type="hidden" name="test" value="123">
    
    <label>
        <input type="radio" name="criteria_1" value="yes" required> Yes
        <input type="radio" name="criteria_1" value="no"> No
    </label>
    
    <br><br>
    
    <button type="submit">Test Submit</button>
</form>

<hr>

<h3>Test Links:</h3>
<ul>
    <li><a href="/applications">Back to Applications</a></li>
    <li><a href="/standards">Back to Standards</a></li>
</ul>