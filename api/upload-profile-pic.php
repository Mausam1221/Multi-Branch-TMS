<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$user_id = str_replace(' ', '_', $_SESSION['user_id']);

// Fetch current profile picture filename from DB
$stmt = $db->prepare('SELECT profile_pic FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$currentPic = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['profile_pic'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['profile_pic'];
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}
if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}
$filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
$filename = str_replace(' ', '_', $filename);
$target = '../uploads/profile_pics/' . $filename;

// Delete old profile picture if it exists and is not empty
if (!empty($currentPic)) {
    $oldFile = '../uploads/profile_pics/' . $currentPic;
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}
// Update DB
$stmt = $db->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
$stmt->execute([$filename, $user_id]);

// Update session data
$_SESSION['profile_pic'] = 'uploads/profile_pics/' . $filename;

echo json_encode(['success' => true, 'filename' => $filename, 'url' => 'uploads/profile_pics/' . $filename]); 