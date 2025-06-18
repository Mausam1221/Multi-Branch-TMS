<?php
session_start();
session_destroy();
echo "<script>localStorage.removeItem('lastActiveSection'); window.location.href = '../index.php';</script>";
exit();
?>
