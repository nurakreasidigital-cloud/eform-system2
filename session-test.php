<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User login: " . $_SESSION['fullname'];
} else {
    echo "❌ Belum login. <a href='index.php'>Login dulu</a>";
}
?>
