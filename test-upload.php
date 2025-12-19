<?php
// test-upload.php - DELETE AFTER TESTING

echo "<h2>PHP Upload Settings:</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

echo "<h2>File Upload Test:</h2>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES['testfile']) && $_FILES['testfile']['error'] == 0) {
        $upload_dir = 'uploads/videos/test/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $target = $upload_dir . basename($_FILES['testfile']['name']);
        
        if (move_uploaded_file($_FILES['testfile']['tmp_name'], $target)) {
            echo "<p style='color:green;'>SUCCESS! File uploaded to: $target</p>";
        } else {
            echo "<p style='color:red;'>FAILED to move uploaded file</p>";
        }
    } else {
        echo "<p style='color:red;'>Upload Error: " . $_FILES['testfile']['error'] . "</p>";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="testfile" required><br><br>
    <button type="submit">Test Upload</button>
</form>