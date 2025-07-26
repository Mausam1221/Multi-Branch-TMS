<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireRole('main_admin');

// Session timeout enforcement
$timeout_minutes = (int)getSystemSetting($db, 'session_timeout', 30);
$timeout_seconds = $timeout_minutes * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_seconds)) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_branch':
            $stmt = $db->prepare("INSERT INTO branches (name, location, contact_email, contact_phone) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$_POST['name'], $_POST['location'], $_POST['contact_email'], $_POST['contact_phone']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_branch':
            $stmt = $db->prepare("UPDATE branches SET name = ?, location = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['name'], $_POST['location'], $_POST['contact_email'], $_POST['contact_phone'], $_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_branch':
            $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
            $result = $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'add_user':
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $branch_id = ($_POST['role'] === 'branch_admin') ? $_POST['branch_id'] : null;
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, branch_id, full_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $password_hash,
                $_POST['role'],
                $branch_id,
                $_POST['full_name'],
                $_POST['phone']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_user':
            $branch_id = ($_POST['role'] === 'branch_admin') ? $_POST['branch_id'] : null;
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, branch_id = ?, full_name = ?, phone = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['username'],
                    $_POST['email'],
                    $password_hash,
                    $_POST['role'],
                    $branch_id,
                    $_POST['full_name'],
                    $_POST['phone'],
                    $_POST['id']
                ]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, branch_id = ?, full_name = ?, phone = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['username'],
                    $_POST['email'],
                    $_POST['role'],
                    $branch_id,
                    $_POST['full_name'],
                    $_POST['phone'],
                    $_POST['id']
                ]);
            }
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_user':
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'add_package':
            $stmt = $db->prepare("INSERT INTO packages (name, description, destination, duration_days, price, branch_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$_POST['name'], $_POST['description'], $_POST['destination'], $_POST['duration_days'], $_POST['price'], $_POST['branch_id'], $_POST['image_url']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_package':
            $stmt = $db->prepare("UPDATE packages SET name = ?, description = ?, destination = ?, duration_days = ?, price = ?, branch_id = ?, image_url = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['name'], $_POST['description'], $_POST['destination'], $_POST['duration_days'], $_POST['price'], $_POST['branch_id'], $_POST['image_url'], $_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_package':
            $stmt = $db->prepare("UPDATE packages SET status = 'inactive' WHERE id = ?");
            $result = $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;

        case 'restore_branch':
            $stmt = $db->prepare("UPDATE branches SET status = 'active' WHERE id = ?");
            $result = $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => $result]);
            exit;

        // Settings Management
        case 'save_general_settings':
            try {
                // Create settings table if it doesn't exist
                $create_table = "CREATE TABLE IF NOT EXISTS system_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $db->exec($create_table);
                
                $settings = [
                    'company_name' => $_POST['company_name'],
                    'company_email' => $_POST['company_email'],
                    'company_phone' => $_POST['company_phone'],
                    'company_address' => $_POST['company_address']
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                echo json_encode(['success' => true, 'message' => 'General settings saved successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'save_system_settings':
            try {
                $create_table = "CREATE TABLE IF NOT EXISTS system_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $db->exec($create_table);
                
                $settings = [
                    'default_currency' => $_POST['default_currency'],
                    'date_format' => $_POST['date_format'],
                    'timezone' => $_POST['timezone'],
                    'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
                    'sms_notifications' => isset($_POST['sms_notifications']) ? '1' : '0'
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                echo json_encode(['success' => true, 'message' => 'System settings saved successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'save_security_settings':
            try {
                $create_table = "CREATE TABLE IF NOT EXISTS system_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $db->exec($create_table);
                
                $settings = [
                    'session_timeout' => $_POST['session_timeout'],
                    'max_login_attempts' => $_POST['max_login_attempts'],
                    'inactivity_days' => $_POST['inactivity_days'],
                    'two_factor_auth' => isset($_POST['two_factor_auth']) ? '1' : '0',
                    'password_complexity' => isset($_POST['password_complexity']) ? '1' : '0'
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                
                // Return the updated settings for immediate use
                echo json_encode([
                    'success' => true, 
                    'message' => 'Security settings saved successfully!',
                    'settings' => $settings
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'save_email_template':
            try {
                $create_table = "CREATE TABLE IF NOT EXISTS email_templates (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    template_name VARCHAR(100) UNIQUE NOT NULL,
                    subject VARCHAR(255),
                    body TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $db->exec($create_table);
                
                $stmt = $db->prepare("INSERT INTO email_templates (template_name, subject, body) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE subject = ?, body = ?");
                $result = $stmt->execute([
                    $_POST['template_name'],
                    $_POST['subject'],
                    $_POST['body'],
                    $_POST['subject'],
                    $_POST['body']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Email template saved successfully!']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'unlock_account':
            try {
                $username = $_POST['username'];
                $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
                $result = $stmt->execute([$username]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => "Account unlocked successfully for user: {$username}"]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to unlock account']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_locked_accounts':
            try {
                $stmt = $db->prepare("
                    SELECT username, COUNT(*) as failed_attempts, MAX(attempt_time) as last_attempt
                    FROM login_attempts 
                    WHERE success = FALSE 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    GROUP BY username 
                    HAVING failed_attempts >= ?
                ");
                $maxAttempts = (int)getSystemSetting($db, 'max_login_attempts', 5);
                $stmt->execute([$maxAttempts]);
                $lockedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate failed attempts today (all failed login attempts recorded today)
                $stmt = $db->prepare("
                    SELECT COUNT(*) as failed_attempts_today
                    FROM login_attempts 
                    WHERE success = FALSE 
                    AND DATE(attempt_time) = CURDATE()
                ");
                $stmt->execute();
                $failedAttemptsToday = $stmt->fetch(PDO::FETCH_ASSOC)['failed_attempts_today'];
                
                echo json_encode([
                    'success' => true, 
                    'accounts' => $lockedAccounts,
                    'current_max_attempts' => $maxAttempts,
                    'failed_attempts_today' => $failedAttemptsToday
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_user_status':
            try {
                $user_id = $_POST['user_id'];
                $new_status = $_POST['status'];
                
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $result = $stmt->execute([$new_status, $user_id]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User status updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update user status']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_inactive_users':
            try {
                $stmt = $db->prepare("
                    SELECT u.*, b.name as branch_name,
                           DATEDIFF(NOW(), u.last_login) as days_inactive
                    FROM users u 
                    LEFT JOIN branches b ON u.branch_id = b.id 
                    WHERE u.role = 'customer' 
                    AND (u.status = 'inactive' OR u.status = 'blocked')
                    ORDER BY u.last_login ASC
                ");
                $stmt->execute();
                $inactiveUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'users' => $inactiveUsers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'fix_new_accounts':
            try {
                // Fix any active accounts that have NULL last_login (new accounts)
                $stmt = $db->prepare("
                    UPDATE users 
                    SET last_login = NOW(), status = 'active' 
                    WHERE status = 'inactive' 
                    AND last_login IS NULL
                    AND role = 'customer'
                ");
                $result = $stmt->execute();
                
                if ($result) {
                    $affected = $stmt->rowCount();
                    echo json_encode([
                        'success' => true, 
                        'message' => "Fixed {$affected} new accounts that were incorrectly marked as inactive!"
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to fix new accounts']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_failed_attempts_today':
            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as failed_attempts_today
                    FROM login_attempts 
                    WHERE success = FALSE 
                    AND DATE(attempt_time) = CURDATE()
                ");
                $stmt->execute();
                $failedAttemptsToday = $stmt->fetch(PDO::FETCH_ASSOC)['failed_attempts_today'];
                
                echo json_encode([
                    'success' => true, 
                    'failed_attempts_today' => $failedAttemptsToday
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'force_update_user_statuses':
            try {
                // Force update user statuses using the same logic as Auth class
                $maxAttempts = (int)getSystemSetting($db, 'max_login_attempts', 5);
                $inactivityDays = (int)getSystemSetting($db, 'inactivity_days', 30);
                
                // Update customer statuses based on inactivity
                $stmt = $db->prepare("
                    UPDATE users 
                    SET status = 'inactive' 
                    WHERE role = 'customer' 
                    AND status = 'active' 
                    AND last_login IS NOT NULL 
                    AND last_login < DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND id NOT IN (
                        SELECT DISTINCT user_id FROM login_attempts 
                        WHERE success = FALSE 
                        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                        GROUP BY user_id 
                        HAVING COUNT(*) >= ?
                    )
                ");
                $stmt->execute([$inactivityDays, $maxAttempts]);
                $inactiveUpdated = $stmt->rowCount();
                
                // Keep blocked users inactive (users with too many failed attempts)
                $stmt = $db->prepare("
                    UPDATE users 
                    SET status = 'blocked' 
                    WHERE id IN (
                        SELECT DISTINCT user_id FROM login_attempts 
                        WHERE success = FALSE 
                        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                        GROUP BY user_id 
                        HAVING COUNT(*) >= ?
                    )
                ");
                $stmt->execute([$maxAttempts]);
                $blockedUpdated = $stmt->rowCount();
                
                echo json_encode([
                    'success' => true, 
                    'message' => "User statuses updated: {$inactiveUpdated} inactive, {$blockedUpdated} blocked"
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'create_test_blocked_users':
            try {
                // Create some test failed login attempts to simulate blocked users
                $maxAttempts = (int)getSystemSetting($db, 'max_login_attempts', 5);
                
                // Get some customer users to make them blocked
                $stmt = $db->prepare("SELECT id, username FROM users WHERE role = 'customer' LIMIT 2");
                $stmt->execute();
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $blockedCount = 0;
                foreach ($customers as $customer) {
                    // Create multiple failed login attempts for each customer
                    for ($i = 0; $i < $maxAttempts + 1; $i++) {
                        $stmt = $db->prepare("
                            INSERT INTO login_attempts (username, user_id, ip_address, success, attempt_time) 
                            VALUES (?, ?, '127.0.0.1', FALSE, NOW())
                        ");
                        $stmt->execute([$customer['username'], $customer['id']]);
                    }
                    $blockedCount++;
                }
                
                // Now update their status to blocked
                if ($blockedCount > 0) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET status = 'blocked' 
                        WHERE id IN (
                            SELECT DISTINCT user_id FROM login_attempts 
                            WHERE success = FALSE 
                            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                            GROUP BY user_id 
                            HAVING COUNT(*) >= ?
                        )
                    ");
                    $stmt->execute([$maxAttempts]);
                    $blockedUpdated = $stmt->rowCount();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Created {$blockedCount} test blocked users with {$blockedUpdated} status updates"
                    ]);
                } else {
                    echo json_encode([
                        'success' => true, 
                        'message' => "No customers found to create test blocked users"
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_email_template':
            try {
                $stmt = $db->prepare("SELECT * FROM email_templates WHERE template_name = ?");
                $stmt->execute([$_POST['template_name']]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($template) {
                    echo json_encode(['success' => true, 'template' => $template]);
                } else {
                    // Return default template
                    $default_templates = [
                        'booking_confirmation' => [
                            'subject' => 'Booking Confirmation - TravelNepal',
                            'body' => "Dear {customer_name},\n\nThank you for booking with TravelNepal! Your booking has been confirmed.\n\nBooking Details:\n- Package: {package_name}\n- Destination: {destination}\n- Travel Date: {travel_date}\n- Number of People: {people_count}\n- Total Amount: {total_amount}\n\nWe look forward to serving you!\n\nBest regards,\nTravelNepal Team"
                        ],
                        'booking_cancellation' => [
                            'subject' => 'Booking Cancellation - TravelNepal',
                            'body' => "Dear {customer_name},\n\nYour booking has been cancelled as requested.\n\nCancelled Booking Details:\n- Package: {package_name}\n- Destination: {destination}\n- Travel Date: {travel_date}\n\nIf you have any questions, please contact us.\n\nBest regards,\nTravelNepal Team"
                        ],
                        'welcome_email' => [
                            'subject' => 'Welcome to TravelNepal',
                            'body' => "Dear {customer_name},\n\nWelcome to TravelNepal! We're excited to have you as part of our community.\n\nWe offer amazing travel packages to beautiful destinations. Start exploring our packages today!\n\nBest regards,\nTravelNepal Team"
                        ],
                        'password_reset' => [
                            'subject' => 'Password Reset Request - TravelNepal',
                            'body' => "Dear {customer_name},\n\nYou have requested a password reset for your TravelNepal account.\n\nClick the link below to reset your password:\n{reset_link}\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\nTravelNepal Team"
                        ]
                    ];
                    
                    $template_name = $_POST['template_name'];
                    if (isset($default_templates[$template_name])) {
                        echo json_encode(['success' => true, 'template' => $default_templates[$template_name]]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Template not found']);
                    }
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        case 'update_main_admin_profile':
            try {
                $response = ['success' => false];
                $userId = $_SESSION['user_id'];
                $fullName = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $profile_pic_path = null;
                
                // Handle profile picture upload
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    if (in_array($_FILES['profile_pic']['type'], $allowed_types) && $_FILES['profile_pic']['size'] <= $max_size) {
                        $upload_dir = '../uploads/profile_pics/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $userId . '_' . time() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filepath)) {
                            $profile_pic_path = 'uploads/profile_pics/' . $filename;
                            // Delete old profile picture if exists
                            if (!empty($_SESSION['profile_pic']) && $_SESSION['profile_pic'] != 'https://via.placeholder.com/150') {
                                $old_file = '../' . $_SESSION['profile_pic'];
                                if (file_exists($old_file)) {
                                    @unlink($old_file);
                                }
                            }
                        }
                    }
                }

                // Password update logic
                $updatePassword = false;
                if (!empty($newPassword)) {
                    // Verify current password
                    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && password_verify($currentPassword, $user['password'])) {
                        $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updatePassword = true;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                        exit;
                    }
                }

                // Build update query
                if ($updatePassword && $profile_pic_path) {
                    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, password = ?, profile_pic = ? WHERE id = ?');
                    $result = $stmt->execute([$fullName, $email, $hashed_password, $profile_pic_path, $userId]);
                } elseif ($updatePassword) {
                    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?');
                    $result = $stmt->execute([$fullName, $email, $hashed_password, $userId]);
                } elseif ($profile_pic_path) {
                    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ?, profile_pic = ? WHERE id = ?');
                    $result = $stmt->execute([$fullName, $email, $profile_pic_path, $userId]);
                } else {
                    $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
                    $result = $stmt->execute([$fullName, $email, $userId]);
                }

                if ($result) {
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                    if ($profile_pic_path) {
                        $_SESSION['profile_pic'] = $profile_pic_path;
                        $response['profile_pic'] = $profile_pic_path;
                    }
                    $response['success'] = true;
                    $response['message'] = 'Profile updated successfully!';
                    if ($updatePassword) {
                        $response['logout'] = true;
                    }
                } else {
                    $response['message'] = 'Failed to update profile.';
                }
                echo json_encode($response);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'branch_admin') as branch_admins,
    (SELECT COUNT(*) FROM users WHERE role = 'customer') as customers,
    (SELECT COUNT(*) FROM branches WHERE status = 'active') as active_branches,
    (SELECT COUNT(*) FROM packages WHERE status = 'active') as active_packages,
    (SELECT COUNT(*) FROM bookings) as total_bookings,
    (SELECT SUM(total_amount) FROM bookings WHERE status = 'confirmed') as total_revenue";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent bookings
$bookings_query = "SELECT b.*, u.full_name as customer_name, p.name as package_name, br.name as branch_name 
                   FROM bookings b 
                   JOIN users u ON b.customer_id = u.id 
                   JOIN packages p ON b.package_id = p.id 
                   JOIN branches br ON b.branch_id = br.id 
                   ORDER BY b.created_at DESC LIMIT 5";
$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->execute();
$recent_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all branches
$branches_query = "SELECT * FROM branches WHERE status = 'active' ORDER BY name";
$branches_stmt = $db->prepare($branches_query);
$branches_stmt->execute();
$branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);

// AJAX endpoint for branches table refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'branches') {
    $showAll = isset($_GET['all']) && $_GET['all'] == '1';
    $branches_query = $showAll ? "SELECT * FROM branches ORDER BY name" : "SELECT * FROM branches WHERE status = 'active' ORDER BY name";
    $branches_stmt = $db->prepare($branches_query);
    $branches_stmt->execute();
    $branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($branches as $branch): ?>
        <tr data-branch-id="<?php echo $branch['id']; ?>">
            <td><?php echo $branch['id']; ?></td>
            <td><?php echo $branch['name']; ?></td>
            <td><?php echo $branch['location']; ?></td>
            <td><?php echo $branch['contact_email']; ?></td>
            <td><?php echo $branch['contact_phone']; ?></td>
            <td><span class="badge bg-<?php echo $branch['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($branch['status']); ?></span></td>
            <td class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?php echo $branch['id']; ?>)">
                    <i class="fas fa-trash"></i>
                </button>
                <?php if ($branch['status'] !== 'active'): ?>
                <button class="btn btn-sm btn-outline-success" onclick="restoreBranch(<?php echo $branch['id']; ?>)">
                    <i class="fas fa-undo"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
    exit;
}

// AJAX endpoint for latest user (for dynamic add)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'latest_user') {
    $latest_user_query = "SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.created_at DESC, u.id DESC LIMIT 1";
    $latest_user_stmt = $db->prepare($latest_user_query);
    $latest_user_stmt->execute();
    $user = $latest_user_stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($user);
    exit;
}

// Get all users
$users_query = "SELECT u.*, b.name as branch_name FROM users u 
                LEFT JOIN branches b ON u.branch_id = b.id 
                ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all packages
$packages_query = "SELECT p.*, b.name as branch_name FROM packages p 
                   JOIN branches b ON p.branch_id = b.id 
                   WHERE p.status = 'active' ORDER BY p.created_at DESC";
$packages_stmt = $db->prepare($packages_query);
$packages_stmt->execute();
$packages = $packages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Load system settings
function getSystemSetting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Load settings with defaults
$settings = [
    'company_name' => getSystemSetting($db, 'company_name', 'TravelNepal'),
    'company_email' => getSystemSetting($db, 'company_email', 'admin@travelnepal.com'),
    'company_phone' => getSystemSetting($db, 'company_phone', '+977-9999999999'),
    'company_address' => getSystemSetting($db, 'company_address', 'Thamel, Kathmandu, Nepal'),
    'default_currency' => getSystemSetting($db, 'default_currency', 'NPR'),
    'date_format' => getSystemSetting($db, 'date_format', 'DD/MM/YYYY'),
    'timezone' => getSystemSetting($db, 'timezone', 'Asia/Kathmandu'),
    'email_notifications' => getSystemSetting($db, 'email_notifications', '1'),
    'sms_notifications' => getSystemSetting($db, 'sms_notifications', '0'),
    'session_timeout' => getSystemSetting($db, 'session_timeout', '30'),
    'max_login_attempts' => getSystemSetting($db, 'max_login_attempts', '5'),
    'inactivity_days' => getSystemSetting($db, 'inactivity_days', '30'),
    'two_factor_auth' => getSystemSetting($db, 'two_factor_auth', '0'),
    'password_complexity' => getSystemSetting($db, 'password_complexity', '1')
];

// Currency helper functions
function getCurrencySymbol($currency) {
    switch ($currency) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'NPR':
        default: return 'Rs.';
    }
}

function getCurrencyLabel($currency) {
    switch ($currency) {
        case 'USD': return 'USD';
        case 'EUR': return 'EUR';
        case 'NPR':
        default: return 'NPR';
    }
}

$currencySymbol = getCurrencySymbol($settings['default_currency']);
$currencyLabel = getCurrencyLabel($settings['default_currency']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Admin Dashboard - TravelNepal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <style>
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .sidebar-menu li.active a {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-left-color: #ffd700;
        }
        .table-actions {
            white-space: nowrap;
        }
        .modal-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        /* Make user summary numbers bold */
        #users-section .card-body h4 {
            font-weight: bold;
        }
        
        /* Image Preview Styles */
        .image-preview-wrapper {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .image-preview-wrapper:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        #image_preview {
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        #image_preview:hover {
            transform: scale(1.02);
        }
        
        #image_preview_container {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Loading Button Styles */
        .btn-loading {
            position: relative;
            cursor: not-allowed !important;
            opacity: 0.8;
        }
        
        .btn-loading:hover {
            transform: none !important;
        }
        
        .btn-loading .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Form Loading Overlay */
        .form-loading {
            position: relative;
            pointer-events: none;
        }
        
        .form-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
            border-radius: 8px;
        }
        
        /* Refresh button styles */
        .position-relative .btn-outline-secondary {
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        
        .position-relative:hover .btn-outline-secondary {
            opacity: 1;
        }
        
        .position-relative .btn-outline-secondary:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Toast Container for Alerts -->
        <div id="toast-container" style="position: fixed; top: 1rem; right: 1rem; z-index: 1080;"></div>
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-plane me-2"></i>TravelNepal</h3>
                <p class="text-muted">Main Admin</p>
            </div>
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="#" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('branches')"><i class="fas fa-code-branch"></i> Branches</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('users')"><i class="fas fa-users"></i> Users</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('packages')"><i class="fas fa-suitcase"></i> Packages</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('bookings')"><i class="fas fa-calendar-check"></i> Bookings</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('reports')"><i class="fas fa-chart-bar"></i> Reports</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('settings')"><i class="fas fa-cog"></i> Settings</a>
                </li>
                <li>
                    <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 id="page-title">Main Admin Dashboard</h1>
                    <div class="user-info">
                        <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                        <img src="<?php echo !empty($_SESSION['profile_pic']) ? '../' . $_SESSION['profile_pic'] : 'https://via.placeholder.com/40'; ?>" alt="Profile" class="rounded-circle ms-2">
                    </div>
                </div>
            </header>

            <div class="container-fluid px-4">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section active">
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?php echo $stats['active_branches']; ?></h4>
                                            <p>Active Branches</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-code-branch"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?php echo $stats['branch_admins']; ?></h4>
                                            <p>Branch Admins</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?php echo $stats['customers']; ?></h4>
                                            <p>Customers</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?php echo $currencySymbol . ' ' . number_format($stats['total_revenue'] ?? 0); ?></h4>
                                            <p>Total Revenue</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-rupee-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Bookings -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-calendar-check me-2"></i>Recent Bookings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Booking ID</th>
                                                    <th>Customer</th>
                                                    <th>Package</th>
                                                    <th>Branch</th>
                                                    <th>Travel Date</th>
                                                    <th>People</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>#<?php echo $booking['id']; ?></td>
                                                    <td><?php echo $booking['customer_name']; ?></td>
                                                    <td><?php echo $booking['package_name']; ?></td>
                                                    <td><?php echo $booking['branch_name']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                                    <td><?php echo $booking['number_of_people']; ?></td>
                                                    <td><?php echo $currencySymbol . ' ' . number_format($booking['total_amount']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $booking['status'] == 'confirmed' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($booking['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick='viewBooking(<?php echo json_encode($booking); ?>)'>
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branches Section -->
                <div id="branches-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-code-branch me-2"></i>Branches Management</h3>
                        <div>
                            <button class="btn btn-secondary btn-sm me-2" id="toggle-branches-btn" onclick="toggleBranches()">Show All</button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="openBranchModal()">
                                <i class="fas fa-plus me-1"></i>Add Branch
                            </button>
                        </div>
                    </div>
                    <!-- Success/Error Alert Placeholder -->
                    <div id="branches-alert"></div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Location</th>
                                            <th>Contact Email</th>
                                            <th>Contact Phone</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="branches-table">
                                        <?php foreach ($branches as $branch): ?>
                                        <tr data-branch-id="<?php echo $branch['id']; ?>">
                                            <td><?php echo $branch['id']; ?></td>
                                            <td><?php echo $branch['name']; ?></td>
                                            <td><?php echo $branch['location']; ?></td>
                                            <td><?php echo $branch['contact_email']; ?></td>
                                            <td><?php echo $branch['contact_phone']; ?></td>
                                            <td><span class="badge bg-<?php echo $branch['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($branch['status']); ?></span></td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?php echo $branch['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php if ($branch['status'] !== 'active'): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="restoreBranch(<?php echo $branch['id']; ?>)">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div id="users-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-users me-2"></i>Users Management</h3>
                        <div>
                            <!-- Removed Update Statuses button -->
                            <button class="btn btn-outline-secondary me-2" onclick="refreshUsersTable()" title="Refresh Users">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
                                <i class="fas fa-plus me-1"></i>Add User
                            </button>
                        </div>
                    </div>
                    
                    <!-- User Status Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center bg-success text-white">
                                <div class="card-body">
                                    <h4 id="activeUsersCount">-</h4>
                                    <p class="mb-0">Active Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-warning text-white">
                                <div class="card-body">
                                    <h4 id="inactiveUsersCount">-</h4>
                                    <p class="mb-0">Inactive Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-danger text-white">
                                <div class="card-body">
                                    <h4 id="blockedUsersCount">-</h4>
                                    <p class="mb-0">Blocked Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center bg-info text-white">
                                <div class="card-body">
                                    <h4 id="totalUsersCount">-</h4>
                                    <p class="mb-0">Total Users</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Bar -->
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <select class="form-select" id="filterUserRole">
                                <option value="">All Roles</option>
                                <option value="main_admin">Main Admin</option>
                                <option value="branch_admin">Branch Admin</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterUserStatus">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterUserBranch">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['name']; ?>"><?php echo $branch['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="filterUserSearch" placeholder="Search by username, full name, or email...">
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Branch</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="users-table">
                                        <?php foreach ($users as $user): ?>
                                        <tr data-user-id="<?php echo $user['id']; ?>">
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['full_name']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] == 'main_admin' ? 'danger' : ($user['role'] == 'branch_admin' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['branch_name'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = 'bg-success';
                                                $statusText = 'Active';
                                                if ($user['status'] === 'inactive') {
                                                    $statusClass = 'bg-warning';
                                                    $statusText = 'Inactive';
                                                } elseif ($user['status'] === 'blocked') {
                                                    $statusClass = 'bg-danger';
                                                    $statusText = 'Blocked';
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                <?php if ($user['role'] === 'customer' && $user['last_login']): ?>
                                                    <br><small class="text-muted">Last: <?php echo date('M d, Y', strtotime($user['last_login'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="table-actions">
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['role'] === 'customer'): ?>
                                                        <?php if ($user['status'] === 'active'): ?>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="updateUserStatus(<?php echo $user['id']; ?>, 'inactive')" title="Deactivate User">
                                                                <i class="fas fa-user-slash"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="updateUserStatus(<?php echo $user['id']; ?>, 'blocked')" title="Block User">
                                                                <i class="fas fa-user-lock"></i>
                                                            </button>
                                                        <?php elseif ($user['status'] === 'inactive'): ?>
                                                            <button class="btn btn-sm btn-outline-success" onclick="updateUserStatus(<?php echo $user['id']; ?>, 'active')" title="Activate User">
                                                                <i class="fas fa-user-check"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="updateUserStatus(<?php echo $user['id']; ?>, 'blocked')" title="Block User">
                                                                <i class="fas fa-user-lock"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($user['status'] === 'blocked'): ?>
                                                            <button class="btn btn-sm btn-outline-info" onclick="unlockUserAccount('<?php echo $user['username']; ?>')" title="Unlock Account">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete User">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Packages Section -->
                <div id="packages-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-suitcase me-2"></i>Packages Management</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openPackageModal()">
                            <i class="fas fa-plus me-1"></i>Add Package
                        </button>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($packages as $package): ?>
                        <div class="col-md-6 col-lg-4 mb-4" data-package-id="<?php echo $package['id']; ?>">
                            <div class="card h-100">
                                <img src="<?php echo $package['image_url']; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo $package['name']; ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo $package['name']; ?></h5>
                                    <p class="card-text text-muted small"><?php echo substr($package['description'], 0, 100); ?>...</p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-info"><?php echo $package['destination']; ?></span>
                                            <span class="fw-bold"><?php echo $currencySymbol . ' ' . number_format($package['price']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted"><?php echo $package['duration_days']; ?> Days</small>
                                            <small class="text-muted"><?php echo $package['branch_name']; ?></small>
                                        </div>
                                        <div class="btn-group w-100">
                                            <button class="btn btn-outline-primary btn-sm" onclick="editPackage(<?php echo htmlspecialchars(json_encode($package)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deletePackage(<?php echo $package['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bookings Section -->
                <div id="bookings-section" class="content-section">
                    <h3><i class="fas fa-calendar-check me-2"></i>All Bookings</h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Customer</th>
                                            <th>Package</th>
                                            <th>Branch</th>
                                            <th>Travel Date</th>
                                            <th>People</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>#<?php echo $booking['id']; ?></td>
                                            <td><?php echo $booking['customer_name']; ?></td>
                                            <td><?php echo $booking['package_name']; ?></td>
                                            <td><?php echo $booking['branch_name']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                            <td><?php echo $booking['number_of_people']; ?></td>
                                            <td><?php echo $currencySymbol . ' ' . number_format($booking['total_amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $booking['status'] == 'confirmed' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick='viewBooking(<?php echo json_encode($booking); ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Section -->
                <div id="reports-section" class="content-section">
                    <h3><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h3>
                    
                    <!-- Report Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-calendar-alt fa-2x text-primary mb-2"></i>
                                    <h5>Monthly Report</h5>
                                    <button class="btn btn-outline-primary btn-sm" onclick="generateReport('monthly')">Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                                    <h5>Revenue Report</h5>
                                    <button class="btn btn-outline-success btn-sm" onclick="generateReport('revenue')">Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-users fa-2x text-info mb-2"></i>
                                    <h5>Customer Report</h5>
                                    <button class="btn btn-outline-info btn-sm" onclick="generateReport('customer')">Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-suitcase fa-2x text-warning mb-2"></i>
                                    <h5>Package Report</h5>
                                    <button class="btn btn-outline-warning btn-sm" onclick="generateReport('package')">Generate</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-line me-2"></i>Revenue Trends</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-pie me-2"></i>Booking Status</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="statusChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Reports Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-table me-2"></i>Detailed Analytics</h5>
                            <div>
                                <select class="form-select form-select-sm d-inline-block w-auto me-2" id="reportFilter">
                                    <option value="all">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                                <button class="btn btn-success btn-sm" onclick="exportReport()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Branch</th>
                                            <th>Total Bookings</th>
                                            <th>Confirmed</th>
                                            <th>Pending</th>
                                            <th>Cancelled</th>
                                            <th>Revenue</th>
                                            <th>Growth</th>
                                        </tr>
                                    </thead>
                                    <tbody id="analyticsTable">
                                        <?php foreach ($branches as $branch): ?>
                                        <tr>
                                            <td><?php echo $branch['name']; ?></td>
                                            <td><span class="badge bg-primary">25</span></td>
                                            <td><span class="badge bg-success">20</span></td>
                                            <td><span class="badge bg-warning">3</span></td>
                                            <td><span class="badge bg-danger">2</span></td>
                                            <td><?php echo $currencySymbol . ' ' . number_format(rand(50000, 200000)); ?></td>
                                            <td><span class="text-success"><i class="fas fa-arrow-up"></i> 12%</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Section -->
                <div id="settings-section" class="content-section">
                    <h3><i class="fas fa-cog me-2"></i>System Settings</h3>
                    
                    <div class="row">
                        <!-- General Settings -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-sliders-h me-2"></i>General Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="generalSettingsForm">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                                            <label for="company_name">Company Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email']); ?>" required>
                                            <label for="company_email">Company Email</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="tel" class="form-control" id="company_phone" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone']); ?>" required>
                                            <label for="company_phone">Company Phone</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="company_address" name="company_address" style="height: 100px" required><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                                            <label for="company_address">Company Address</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save General Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- System Configuration -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-server me-2"></i>System Configuration</h5>
                                </div>
                                <div class="card-body">
                                    <form id="systemSettingsForm">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="default_currency" name="default_currency">
                                                <option value="NPR" <?php echo $settings['default_currency'] === 'NPR' ? 'selected' : ''; ?>>Nepali Rupee (Rs.)</option>
                                                <option value="USD" <?php echo $settings['default_currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                <option value="EUR" <?php echo $settings['default_currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                            </select>
                                            <label for="default_currency">Default Currency</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="date_format" name="date_format">
                                                <option value="DD/MM/YYYY" <?php echo $settings['date_format'] === 'DD/MM/YYYY' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                <option value="MM/DD/YYYY" <?php echo $settings['date_format'] === 'MM/DD/YYYY' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="YYYY-MM-DD" <?php echo $settings['date_format'] === 'YYYY-MM-DD' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                            </select>
                                            <label for="date_format">Date Format</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="Asia/Kathmandu" <?php echo $settings['timezone'] === 'Asia/Kathmandu' ? 'selected' : ''; ?>>Asia/Kathmandu (NPT)</option>
                                                <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                            </select>
                                            <label for="timezone">Timezone</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                Enable Email Notifications
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $settings['sms_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_notifications">
                                                Enable SMS Notifications
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save System Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Management -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-user me-2"></i>Profile Management</h5>
                                </div>
                                <div class="card-body">
                                    <form id="mainAdminProfileForm" enctype="multipart/form-data">
                                        <!-- Profile Picture Section -->
                                        <div class="text-center mb-4">
                                            <div class="position-relative d-inline-block">
                                                <img id="mainAdminProfilePreview" src="<?php echo !empty($_SESSION['profile_pic']) ? '../' . $_SESSION['profile_pic'] : 'https://via.placeholder.com/150'; ?>" 
                                                     alt="Profile Picture" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;">
                                                <label for="main_admin_profile_pic" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;">
                                                    <i class="fas fa-camera"></i>
                                                </label>
                                            </div>
                                            <input type="file" id="main_admin_profile_pic" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" onchange="previewMainAdminProfilePic(this)">
                                            <div class="mt-2">
                                                <small class="text-muted">Click the camera icon to change profile picture</small>
                                            </div>
                                        </div>
                                        
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="main_admin_name" value="<?php echo $_SESSION['full_name']; ?>">
                                            <label for="main_admin_name">Full Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="main_admin_email" value="<?php echo $_SESSION['email']; ?>">
                                            <label for="main_admin_email">Email</label>
                                        </div>
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="main_admin_current_password" placeholder="Current Password">
                                            <label for="main_admin_current_password">Current Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="main_admin_current_password" tabindex="-1" style="z-index:2;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="main_admin_new_password" placeholder="New Password">
                                            <label for="main_admin_new_password">New Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="main_admin_new_password" tabindex="-1" style="z-index:2;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="main_admin_confirm_new_password" placeholder="Confirm New Password">
                                            <label for="main_admin_confirm_new_password">Confirm New Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="main_admin_confirm_new_password" tabindex="-1" style="z-index:2;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form id="securitySettingsForm">
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>" min="5" max="120" required>
                                            <label for="session_timeout">Session Timeout (minutes)</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" value="<?php echo htmlspecialchars($settings['max_login_attempts']); ?>" min="3" max="10" required>
                                            <label for="max_login_attempts">Max Login Attempts</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="inactivity_days" name="inactivity_days" value="<?php echo htmlspecialchars($settings['inactivity_days']); ?>" min="7" max="365" required>
                                            <label for="inactivity_days">Inactivity Period (Days)</label>
                                        </div>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Current Settings:</strong><br>
                                            • <?php echo $settings['max_login_attempts']; ?> attempts before account lockout (15-minute lockout period)<br>
                                            • <?php echo $settings['inactivity_days']; ?> days of inactivity before account becomes inactive
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" <?php echo $settings['two_factor_auth'] === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="two_factor_auth">
                                                Enable Two-Factor Authentication
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="password_complexity" name="password_complexity" <?php echo $settings['password_complexity'] === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_complexity">
                                                Enforce Strong Passwords
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Save Security Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Backup & Maintenance -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-database me-2"></i>Backup & Maintenance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6>Database Backup</h6>
                                        <p class="text-muted small">Last backup: <?php echo date('M d, Y H:i'); ?></p>
                                        <button class="btn btn-outline-primary btn-sm me-2" onclick="createBackup()">
                                            <i class="fas fa-download me-1"></i>Create Backup
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="scheduleBackup()">
                                            <i class="fas fa-clock me-1"></i>Schedule Auto Backup
                                        </button>
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <h6>System Maintenance</h6>
                                        <button class="btn btn-outline-warning btn-sm me-2" onclick="clearCache()">
                                            <i class="fas fa-broom me-1"></i>Clear Cache
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="optimizeDatabase()">
                                            <i class="fas fa-tools me-1"></i>Optimize Database
                                        </button>
                                    </div>
                                    <hr>
                                    <div>
                                        <h6>System Information</h6>
                                        <small class="text-muted">
                                            <div>PHP Version: <?php echo phpversion(); ?></div>
                                            <div>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                                            <div>Database: MySQL</div>
                                            <div>Last Updated: <?php echo date('M d, Y'); ?></div>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Security Management -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-user-lock me-2"></i>Account Security Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Locked Accounts</h6>
                                    <p class="text-muted small">Accounts temporarily locked due to failed login attempts</p>
                                    <button class="btn btn-outline-info btn-sm" onclick="loadLockedAccounts()">
                                        <i class="fas fa-refresh me-1"></i>Refresh List
                                    </button>
                                    <div id="lockedAccountsList" class="mt-3">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin"></i> Loading...
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Security Statistics</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center p-3 bg-light rounded">
                                                <h4 class="text-primary mb-0" id="totalLockedAccounts">-</h4>
                                                <small class="text-muted">Locked Accounts</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3 bg-light rounded position-relative">
                                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 mt-1 me-1" 
                                                        onclick="refreshFailedAttemptsCount()" 
                                                        title="Refresh count">
                                                    <i class="fas fa-sync-alt fa-xs"></i>
                                                </button>
                                                <h4 class="text-warning mb-0" id="failedAttemptsToday">-</h4>
                                                <small class="text-muted" title="Total number of failed login attempts recorded today across all users">Failed Attempts Today</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Status Management -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-users-cog me-2"></i>User Status Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6>Inactive & Blocked Users</h6>
                                    <p class="text-muted small">Customers who haven't logged in for 30+ days or are blocked due to security</p>
                                    <button class="btn btn-outline-info btn-sm me-2" onclick="loadInactiveUsers()">
                                        <i class="fas fa-refresh me-1"></i>Refresh List
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="fixNewAccounts()">
                                        <i class="fas fa-tools me-1"></i>Fix New Accounts
                                    </button>
                                    <div id="inactiveUsersList" class="mt-3">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin"></i> Loading...
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6>Status Legend</h6>
                                    <div class="mb-2">
                                        <span class="badge bg-success me-2">Active</span>
                                        <small class="text-muted">Recently logged in</small>
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-warning me-2">Inactive</span>
                                        <small class="text-muted">No login for <?php echo $settings['inactivity_days']; ?>+ days</small>
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge bg-danger me-2">Blocked</span>
                                        <small class="text-muted">Too many failed attempts</small>
                                    </div>
                                    <hr>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Auto-Management:</strong> Customer accounts automatically become inactive after <?php echo $settings['inactivity_days']; ?> days of inactivity. Blocked users remain blocked until manually unlocked.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Templates -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-envelope me-2"></i>Email Templates</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="list-group">
                                        <a href="#" class="list-group-item list-group-item-action active" onclick="loadEmailTemplate('booking_confirmation')">
                                            Booking Confirmation
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" onclick="loadEmailTemplate('booking_cancellation')">
                                            Booking Cancellation
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" onclick="loadEmailTemplate('welcome_email')">
                                            Welcome Email
                                        </a>
                                        <a href="#" class="list-group-item list-group-item-action" onclick="loadEmailTemplate('password_reset')">
                                            Password Reset
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <form id="emailTemplateForm">
                                        <input type="hidden" id="template_name" name="template_name" value="booking_confirmation">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="email_subject" name="subject" value="Booking Confirmation - TravelNepal" required>
                                            <label for="email_subject">Email Subject</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="email_body" name="body" style="height: 200px" required>Dear {customer_name},

Thank you for booking with TravelNepal! Your booking has been confirmed.

Booking Details:
- Package: {package_name}
- Destination: {destination}
- Travel Date: {travel_date}
- Number of People: {people_count}
- Total Amount: {total_amount}

We look forward to serving you!

Best regards,
TravelNepal Team</textarea>
                                            <label for="email_body">Email Body</label>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-outline-secondary" onclick="previewEmail()">
                                                <i class="fas fa-eye me-1"></i>Preview
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>Save Template
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Modal -->
    <div class="modal fade" id="branchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="branchModalTitle">Add Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="branchForm">
                    <div class="modal-body">
                        <input type="hidden" id="branch_id" name="id">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="branch_name" name="name" required>
                            <label for="branch_name">Branch Name</label>
                        </div>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="branch_location" name="location" required>
                            <label for="branch_location">Location</label>
                        </div>
                        <div class="form-floating">
                            <input type="email" class="form-control" id="branch_email" name="contact_email" required>
                            <label for="branch_email">Contact Email</label>
                        </div>
                        <div class="form-floating">
                            <input type="tel" class="form-control" id="branch_phone" name="contact_phone" required>
                            <label for="branch_phone">Contact Phone</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Branch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <input type="hidden" id="user_id" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="user_username" name="username" required>
                                    <label for="user_username">Username</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="user_email" name="email" required>
                                    <label for="user_email">Email</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="user_role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="main_admin">Main Admin</option>
                                        <option value="branch_admin">Branch Admin</option>
                                        <option value="customer">Customer</option>
                                    </select>
                                    <label for="user_role">Role</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="user_full_name" name="full_name" required>
                                    <label for="user_full_name">Full Name</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="user_password" name="password">
                                    <label for="user_password">Password</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="user_phone" name="phone">
                                    <label for="user_phone">Phone</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating">
                            <select class="form-select" id="user_branch_id" name="branch_id">
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>"><?php echo $branch['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="user_branch_id">Branch</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Package Modal -->
    <div class="modal fade" id="packageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalTitle">Add Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="packageForm">
                    <div class="modal-body">
                        <input type="hidden" id="package_id" name="id">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="package_name" name="name" required>
                            <label for="package_name">Package Name</label>
                        </div>
                        <div class="form-floating">
                            <textarea class="form-control" id="package_description" name="description" style="height: 100px" required></textarea>
                            <label for="package_description">Description</label>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="package_destination" name="destination" required>
                                    <label for="package_destination">Destination</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="package_duration" name="duration_days" required>
                                    <label for="package_duration">Duration (Days)</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="package_price" name="price" step="0.01" required>
                                    <label for="package_price">Price (<?php echo $currencySymbol; ?>)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="package_branch_id" name="branch_id" required>
                                        <option value="">Select Branch</option>
                                        <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>"><?php echo $branch['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="package_branch_id">Branch</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating">
                            <input type="url" class="form-control" id="package_image_url" name="image_url" required>
                            <label for="package_image_url">Image URL</label>
                        </div>
                        <div id="image_preview_container" class="mt-3" style="display: none;">
                            <label class="form-label">Image Preview:</label>
                            <div class="image-preview-wrapper">
                                <img id="image_preview" src="" alt="Package Image Preview" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group">
                        <li class="list-group-item"><strong>Booking ID:</strong> <span id="detail_booking_id"></span></li>
                        <li class="list-group-item"><strong>Customer:</strong> <span id="detail_customer_name"></span></li>
                        <li class="list-group-item"><strong>Package:</strong> <span id="detail_package_name"></span></li>
                        <li class="list-group-item"><strong>Branch:</strong> <span id="detail_branch_name"></span></li>
                        <li class="list-group-item"><strong>Travel Date:</strong> <span id="detail_travel_date"></span></li>
                        <li class="list-group-item"><strong>People:</strong> <span id="detail_people"></span></li>
                        <li class="list-group-item"><strong>Amount:</strong> <span id="detail_amount"></span></li>
                        <li class="list-group-item"><strong>Status:</strong> <span id="detail_status"></span></li>
                        <li class="list-group-item"><strong>Created At:</strong> <span id="detail_created_at"></span></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Navigation
        window.showSection = function(section) {
            // Store active section persistently
            localStorage.setItem('activeSection', section);
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
            // Show selected section
            document.getElementById(section + '-section').classList.add('active');
            if (typeof event !== 'undefined' && event && event.target && typeof event.target.closest === 'function') {
                event.target.closest('li').classList.add('active');
            } else {
                // If triggered programmatically, highlight the correct sidebar item
                document.querySelectorAll('.sidebar-menu li').forEach(li => {
                    if (li.querySelector('a') && li.querySelector('a').getAttribute('onclick') && li.querySelector('a').getAttribute('onclick').includes(section)) {
                        li.classList.add('active');
                    }
                });
            }
            // Update page title
            const titles = {
                'dashboard': 'Main Admin Dashboard',
                'branches': 'Branches Management',
                'users': 'Users Management',
                'packages': 'Packages Management',
                'bookings': 'Bookings Management',
                'reports': 'Reports & Analytics',
                'settings': 'System Settings'
            };
            document.getElementById('page-title').textContent = titles[section] || 'Main Admin Dashboard';
        }
        // Restore last active section if available, otherwise show dashboard
        document.addEventListener('DOMContentLoaded', function() {
            var activeSection = localStorage.getItem('activeSection');
            if (activeSection) {
                showSection(activeSection);
            } else {
                showSection('dashboard');
            }
        });

        // Branch Management
        window.openBranchModal = function(branch = null) {
            const modal = document.getElementById('branchModal');
            const form = document.getElementById('branchForm');
            const title = document.getElementById('branchModalTitle');
            if (branch) {
                title.textContent = 'Edit Branch';
                document.getElementById('branch_id').value = branch.id;
                document.getElementById('branch_name').value = branch.name;
                document.getElementById('branch_location').value = branch.location;
                document.getElementById('branch_email').value = branch.contact_email;
                document.getElementById('branch_phone').value = branch.contact_phone;
            } else {
                title.textContent = 'Add Branch';
                form.reset();
                document.getElementById('branch_id').value = '';
            }
        }

        window.editBranch = function(branch) {
            openBranchModal(branch);
            new bootstrap.Modal(document.getElementById('branchModal')).show();
        }

        window.showBranchesAlert = function(message, type) {
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 show" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 250px; margin-bottom: 0.5rem;">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            const container = document.getElementById('toast-container');
            container.insertAdjacentHTML('beforeend', toastHtml);
            setTimeout(() => {
                const toastElem = document.getElementById(toastId);
                if (toastElem) toastElem.remove();
            }, 2000);
        }

        window.toggleBranches = function() {
            const btn = document.getElementById('toggle-branches-btn');
            const showAll = btn.textContent === 'Show All';
            btn.textContent = showAll ? 'Show Active Only' : 'Show All';
            refreshBranchesTable(showAll);
        }

        function refreshBranchesTable(showAll = false) {
            fetch(`?ajax=branches${showAll ? '&all=1' : ''}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('branches-table').innerHTML = html;
                });
        }

        window.deleteBranch = function(id) {
            if (confirm('Are you sure you want to delete this branch?')) {
                const formData = new FormData();
                formData.append('action', 'delete_branch');
                formData.append('id', id);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showBranchesAlert('Branch deleted successfully!', 'success');
                        refreshBranchesTable(document.getElementById('toggle-branches-btn').textContent === 'Show Active Only');
                    } else {
                        showBranchesAlert('Error deleting branch', 'danger');
                    }
                });
            }
        }

        window.restoreBranch = function(id) {
            if (confirm('Restore this branch?')) {
                const formData = new FormData();
                formData.append('action', 'restore_branch');
                formData.append('id', id);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showBranchesAlert('Branch restored successfully!', 'success');
                        refreshBranchesTable(document.getElementById('toggle-branches-btn').textContent === 'Show Active Only');
                    } else {
                        showBranchesAlert('Error restoring branch', 'danger');
                    }
                });
            }
        }

        // Branch form submission
        document.getElementById('branchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const isEdit = document.getElementById('branch_id').value;
            formData.append('action', isEdit ? 'update_branch' : 'add_branch');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const branchModal = bootstrap.Modal.getInstance(document.getElementById('branchModal'));
                    if (branchModal) branchModal.hide();
                    document.getElementById('branchForm').reset();
                    showBranchesAlert('Branch saved successfully!', 'success');
                    // Stay on Branches section after reload
                    localStorage.setItem('activeSection', 'branches');
                    location.reload();
                } else {
                    showBranchesAlert('Error saving branch', 'danger');
                }
            })
            .catch(() => {
                showBranchesAlert('Error saving branch', 'danger');
            });
        });

        // User Management
        window.openUserModal = function(user = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const title = document.getElementById('userModalTitle');
            const branchField = document.getElementById('user_branch_id').closest('.form-floating');
            const passwordField = document.getElementById('user_password');
            const phoneField = document.getElementById('user_phone');
            
            if (user) {
                title.textContent = 'Edit User';
                document.getElementById('user_id').value = user.id;
                document.getElementById('user_username').value = user.username;
                document.getElementById('user_email').value = user.email;
                document.getElementById('user_role').value = user.role;
                document.getElementById('user_full_name').value = user.full_name;
                document.getElementById('user_branch_id').value = user.branch_id || '';
                document.getElementById('user_phone').value = user.phone || '';
                
                // Clear password field and make it optional in edit mode
                passwordField.value = '';
                passwordField.required = false;
                passwordField.placeholder = 'Leave blank to keep current password';
                
                // Show both password and phone fields
                passwordField.closest('.col-md-6').style.display = '';
                phoneField.closest('.col-md-6').style.display = '';
            } else {
                title.textContent = 'Add User';
                form.reset();
                document.getElementById('user_id').value = '';
                
                // Make password required in add mode
                passwordField.required = true;
                passwordField.placeholder = '';
                
                // Show both password and phone fields
                passwordField.closest('.col-md-6').style.display = '';
                phoneField.closest('.col-md-6').style.display = '';
            }
            handleUserRoleChange();
        }

        window.editUser = function(user) {
            openUserModal(user);
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function handleUserRoleChange() {
            const role = document.getElementById('user_role').value;
            const branchField = document.getElementById('user_branch_id').closest('.form-floating');
            const branchInput = document.getElementById('user_branch_id');
            if (role === 'branch_admin') {
                branchField.style.display = '';
                branchInput.required = true;
            } else {
                branchField.style.display = 'none';
                branchInput.value = '';
                branchInput.required = false;
            }
        }
        document.getElementById('user_role').addEventListener('change', handleUserRoleChange);

        window.showUsersAlert = function(message, type) {
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 show" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 250px; margin-bottom: 0.5rem;">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            const container = document.getElementById('toast-container');
            container.insertAdjacentHTML('beforeend', toastHtml);
            setTimeout(() => {
                const toastElem = document.getElementById(toastId);
                if (toastElem) toastElem.remove();
            }, 2000);
        }

        function appendUserRow(user) {
            const tbody = document.getElementById('users-table');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td>${user.full_name}</td>
                <td>${user.email}</td>
                <td><span class="badge bg-${user.role == 'main_admin' ? 'danger' : (user.role == 'branch_admin' ? 'warning' : 'info')}">${user.role.replace('_', ' ').charAt(0).toUpperCase() + user.role.replace('_', ' ').slice(1)}</span></td>
                <td>${user.branch_name || 'N/A'}</td>
                <td><span class="badge bg-success">Active</span></td>
                <td class="table-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick='editUser(${JSON.stringify(user)})'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.prepend(tr);
        }

        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const isEdit = document.getElementById('user_id').value;
            if (document.getElementById('user_role').value !== 'branch_admin') {
                formData.set('branch_id', '');
            }
            formData.append('action', isEdit ? 'update_user' : 'add_user');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (!isEdit) {
                        fetch('?ajax=latest_user')
                        .then(res => res.json())
                        .then(user => {
                            appendUserRow(user);
                        });
                    } else {
                        // For edit, update the row in place
                        fetch(`?ajax=latest_user`)
                        .then(res => res.json())
                        .then(user => {
                            const row = document.querySelector(`#users-table tr[data-user-id='${user.id}']`);
                            if (row) {
                                row.children[1].textContent = user.username;
                                row.children[2].textContent = user.full_name;
                                row.children[3].textContent = user.email;
                                row.children[4].innerHTML = `<span class="badge bg-${user.role == 'main_admin' ? 'danger' : (user.role == 'branch_admin' ? 'warning' : 'info')}">${user.role.replace('_', ' ').charAt(0).toUpperCase() + user.role.replace('_', ' ').slice(1)}</span>`;
                                row.children[5].textContent = user.branch_name || 'N/A';
                            }
                        });
                    }
                    const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                    if (userModal) userModal.hide();
                    document.getElementById('userForm').reset();
                    showUsersAlert('User saved successfully!', 'success');
                    // Stay on Users section after reload
                    localStorage.setItem('activeSection', 'users');
                    location.reload();
                } else {
                    showUsersAlert('Error saving user', 'danger');
                }
            })
            .catch(() => {
                showUsersAlert('Error saving user', 'danger');
            });
        });

        // Filtering for Users Table
        function filterUsersTable() {
            const role = document.getElementById('filterUserRole').value.toLowerCase();
            const status = document.getElementById('filterUserStatus').value.toLowerCase();
            const branch = document.getElementById('filterUserBranch').value.toLowerCase();
            const search = document.getElementById('filterUserSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#users-table tr');
            rows.forEach(row => {
                const tds = row.querySelectorAll('td');
                const userRole = tds[4].textContent.trim().toLowerCase();
                const userStatusBadge = tds[6].querySelector('.badge');
                const userStatus = userStatusBadge ? userStatusBadge.textContent.trim().toLowerCase() : '';
                const userBranch = tds[5].textContent.trim().toLowerCase();
                const userName = tds[1].textContent.trim().toLowerCase();
                const fullName = tds[2].textContent.trim().toLowerCase();
                const email = tds[3].textContent.trim().toLowerCase();
                let show = true;
                if (role && userRole !== role.replace('_', ' ')) show = false;
                if (status && userStatus !== status) show = false;
                if (branch && userBranch !== branch) show = false;
                if (search && !(userName.includes(search) || fullName.includes(search) || email.includes(search))) show = false;
                row.style.display = show ? '' : 'none';
            });
        }
        document.getElementById('filterUserRole').addEventListener('change', filterUsersTable);
        document.getElementById('filterUserStatus').addEventListener('change', filterUsersTable);
        document.getElementById('filterUserBranch').addEventListener('change', filterUsersTable);
        document.getElementById('filterUserSearch').addEventListener('input', filterUsersTable);

        // Toggle show/hide password in user modal
        window.toggleUserPassword = function() {
            const pwd = document.getElementById('user_password');
            const eye = document.getElementById('user_password_eye');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // ... (rest of your code, e.g., reports, settings, etc.)

        // Restore last active section if available
        const activeSection = localStorage.getItem('activeSection');
        if (activeSection) {
            showSection(activeSection);
            localStorage.removeItem('activeSection');
        }
        
        // Function to update user counts
        window.updateUserCounts = function() {
            const rows = document.querySelectorAll('#users-table tr');
            let activeCount = 0;
            let inactiveCount = 0;
            let blockedCount = 0;
            rows.forEach((row) => {
                const statusCell = row.querySelector('td:nth-child(7)'); // Status column
                if (statusCell) {
                    const statusBadge = statusCell.querySelector('.badge');
                    if (statusBadge) {
                        const statusText = statusBadge.textContent.trim().toLowerCase();
                        if (statusText === 'active') {
                            activeCount++;
                        } else if (statusText === 'inactive') {
                            inactiveCount++;
                        } else if (statusText === 'blocked') {
                            blockedCount++;
                        }
                    }
                }
            });
            const totalCount = activeCount + inactiveCount + blockedCount;
            // Update the count displays
            const activeElement = document.getElementById('activeUsersCount');
            const inactiveElement = document.getElementById('inactiveUsersCount');
            const blockedElement = document.getElementById('blockedUsersCount');
            const totalElement = document.getElementById('totalUsersCount');
            if (activeElement) activeElement.textContent = activeCount;
            if (inactiveElement) inactiveElement.textContent = inactiveCount;
            if (blockedElement) blockedElement.textContent = blockedCount;
            if (totalElement) totalElement.textContent = totalCount;
        }
        
        // Update counts when users section is shown
        const usersLink = document.querySelector('a[onclick*="users"]');
        if (usersLink) {
            usersLink.addEventListener('click', function() {
                setTimeout(() => {
                    if (document.getElementById('users-section').classList.contains('active')) {
                        updateUserCounts();
                    }
                }, 100);
            });
        }
        
        // Force update user statuses when page loads (for debugging)
        setTimeout(() => {
            if (document.getElementById('users-section').classList.contains('active')) {
                updateUserCounts();
            }
        }, 500);
        
        // Also update counts when the page first loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                updateUserCounts();
            }, 1000);
        });
        // Revenue Chart (placeholder)
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            revenueCtx.style.background = '#f8f9fa';
            revenueCtx.style.display = 'flex';
            revenueCtx.style.alignItems = 'center';
            revenueCtx.style.justifyContent = 'center';
            revenueCtx.innerHTML = '<p class="text-muted">Revenue Chart (Chart.js integration needed)</p>';
        }
        // Status Chart (placeholder)
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            statusCtx.style.background = '#f8f9fa';
            statusCtx.style.display = 'flex';
            statusCtx.style.alignItems = 'center';
            statusCtx.style.justifyContent = 'center';
            statusCtx.innerHTML = '<p class="text-muted">Status Chart (Chart.js integration needed)</p>';
        }

        // Helper for textEquals selector
        (function(){
            if (!Element.prototype.matches) return;
            if (!Element.prototype.closest) return;
            // Add a custom selector for textEquals
            document.querySelectorAll = (function(qsa) {
                return function(selectors) {
                    if (selectors.includes(':textEquals')) {
                        const [sel, text] = selectors.match(/(.*):textEquals\('(.+)'\)/).slice(1,3);
                        return Array.from(qsa.call(document, sel)).filter(el => el.textContent.trim() === text);
                    }
                    return qsa.call(document, selectors);
                };
            })(document.querySelectorAll);
        })();
    });

    // Make deleteUser globally available
    window.deleteUser = function(id) {
        if (confirm('Are you sure you want to delete this user?')) {
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('id', id);
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the user row from the table using data-user-id
                    const row = document.querySelector(`#users-table tr[data-user-id='${id}']`);
                    if (row) row.remove();
                    if (typeof showUsersAlert === 'function') showUsersAlert('User deleted successfully!', 'success');
                } else {
                    if (typeof showUsersAlert === 'function') showUsersAlert('Error deleting user', 'danger');
                }
            });
        }
    }

    // Package Management Functions
    window.openPackageModal = function(package = null) {
        const modal = document.getElementById('packageModal');
        const form = document.getElementById('packageForm');
        const title = document.getElementById('packageModalTitle');
        if (package) {
            title.textContent = 'Edit Package';
            document.getElementById('package_id').value = package.id;
            document.getElementById('package_name').value = package.name;
            document.getElementById('package_description').value = package.description;
            document.getElementById('package_destination').value = package.destination;
            document.getElementById('package_duration').value = package.duration_days;
            document.getElementById('package_price').value = package.price;
            document.getElementById('package_branch_id').value = package.branch_id;
            document.getElementById('package_image_url').value = package.image_url;
            
            // Show image preview for edit mode
            if (package.image_url) {
                showImagePreview(package.image_url);
            }
        } else {
            title.textContent = 'Add Package';
            form.reset();
            document.getElementById('package_id').value = '';
            hideImagePreview();
        }
    }

    // Image preview functionality
    function showImagePreview(imageUrl) {
        const previewContainer = document.getElementById('image_preview_container');
        const previewImage = document.getElementById('image_preview');
        
        if (imageUrl && imageUrl.trim() !== '') {
            previewImage.src = imageUrl;
            previewImage.onload = function() {
                previewContainer.style.display = 'block';
            };
            previewImage.onerror = function() {
                previewContainer.style.display = 'none';
                showNotification('Invalid image URL or image not accessible', 'error');
            };
        } else {
            hideImagePreview();
        }
    }

    function hideImagePreview() {
        const previewContainer = document.getElementById('image_preview_container');
        previewContainer.style.display = 'none';
    }

    // Add event listener for image URL input
    document.addEventListener('DOMContentLoaded', function() {
        const imageUrlInput = document.getElementById('package_image_url');
        if (imageUrlInput) {
            imageUrlInput.addEventListener('input', function() {
                const imageUrl = this.value.trim();
                if (imageUrl) {
                    showImagePreview(imageUrl);
                } else {
                    hideImagePreview();
                }
            });
            
            // Also trigger on blur for better UX
            imageUrlInput.addEventListener('blur', function() {
                const imageUrl = this.value.trim();
                if (imageUrl) {
                    showImagePreview(imageUrl);
                }
            });
        }
    });

    window.editPackage = function(package) {
        openPackageModal(package);
        new bootstrap.Modal(document.getElementById('packageModal')).show();
    }

    window.deletePackage = function(id) {
        if (confirm('Are you sure you want to delete this package?')) {
            const formData = new FormData();
            formData.append('action', 'delete_package');
            formData.append('id', id);
            
            // Find the package card before making the request
            const packageCard = document.querySelector(`[data-package-id='${id}']`);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the package card from the UI with animation
                    if (packageCard) {
                        packageCard.style.transition = 'all 0.3s ease';
                        packageCard.style.transform = 'scale(0.8)';
                        packageCard.style.opacity = '0';
                        
                        setTimeout(() => {
                            packageCard.remove();
                            showNotification('Package deleted successfully!', 'success');
                            
                            // Check if no packages left
                            const remainingPackages = document.querySelectorAll('[data-package-id]');
                            if (remainingPackages.length === 0) {
                                const packagesSection = document.getElementById('packages-section');
                                packagesSection.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h3><i class="fas fa-suitcase me-2"></i>Packages Management</h3>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openPackageModal()">
                                            <i class="fas fa-plus me-1"></i>Add Package
                                        </button>
                                    </div>
                                    <div class="text-center py-5">
                                        <i class="fas fa-suitcase fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No packages available</h5>
                                        <p class="text-muted">Start by adding your first package!</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openPackageModal()">
                                            <i class="fas fa-plus me-1"></i>Add First Package
                                        </button>
                                    </div>
                                `;
                            }
                        }, 300);
                    } else {
                        showNotification('Package deleted successfully!', 'success');
                    }
                } else {
                    showNotification('Error deleting package: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deleting package', 'error');
            });
        }
    }

    // Package form submission
    document.getElementById('packageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const isEdit = document.getElementById('package_id').value;
        formData.append('action', isEdit ? 'update_package' : 'add_package');
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const packageModal = bootstrap.Modal.getInstance(document.getElementById('packageModal'));
                if (packageModal) packageModal.hide();
                document.getElementById('packageForm').reset();
                hideImagePreview(); // Hide image preview when form is reset
                showNotification('Package saved successfully!', 'success');
                // Always stay on Packages section after reload
                localStorage.setItem('activeSection', 'packages');
                location.reload();
            } else {
                showNotification('Error saving package: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving package', 'error');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

    // Function to add new package to UI dynamically
    function addPackageToUI(formData) {
        const packagesSection = document.getElementById('packages-section');
        let packagesRow = packagesSection.querySelector('.row');
        
        // If no row exists (empty state), create one
        if (!packagesRow) {
            packagesRow = document.createElement('div');
            packagesRow.className = 'row';
            packagesSection.appendChild(packagesRow);
        }
        
        // Remove empty state if it exists
        const emptyState = packagesSection.querySelector('.text-center');
        if (emptyState) {
            emptyState.remove();
        }
        
        // Get branch name for display
        const branchSelect = document.getElementById('package_branch_id');
        const branchName = branchSelect ? branchSelect.options[branchSelect.selectedIndex]?.text || 'Branch Name' : 'Branch Name';
        
        // Create new package card
        const newPackageCard = document.createElement('div');
        newPackageCard.className = 'col-md-6 col-lg-4 mb-4';
        const tempId = 'new-' + Date.now();
        newPackageCard.setAttribute('data-package-id', tempId);
        
        const packageName = formData.get('name');
        const packageDescription = formData.get('description');
        const packageDestination = formData.get('destination');
        const packageDuration = formData.get('duration_days');
        const packagePrice = formData.get('price');
        const packageImageUrl = formData.get('image_url');
        const packageBranchId = formData.get('branch_id');
        
        newPackageCard.innerHTML = `
            <div class="card h-100">
                <img src="${packageImageUrl}" class="card-img-top" style="height: 200px; object-fit: cover;" alt="${packageName}" onerror="this.src='https://via.placeholder.com/300x200?text=Image+Not+Found'">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">${packageName}</h5>
                    <p class="card-text text-muted small">${packageDescription.substring(0, 100)}${packageDescription.length > 100 ? '...' : ''}</p>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-info">${packageDestination}</span>
                            <span class="fw-bold"><?php echo $currencySymbol; ?> ${parseInt(packagePrice).toLocaleString()}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">${packageDuration} Days</small>
                            <small class="text-muted">${branchName}</small>
                        </div>
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-primary btn-sm" onclick="editPackage(${JSON.stringify({
                                id: tempId,
                                name: packageName,
                                description: packageDescription,
                                destination: packageDestination,
                                duration_days: packageDuration,
                                price: packagePrice,
                                image_url: packageImageUrl,
                                branch_id: packageBranchId
                            }).replace(/"/g, '&quot;')})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deletePackage('${tempId}')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add with animation at the beginning (top)
        newPackageCard.style.opacity = '0';
        newPackageCard.style.transform = 'scale(0.8)';
        packagesRow.insertBefore(newPackageCard, packagesRow.firstChild);
        
        setTimeout(() => {
            newPackageCard.style.transition = 'all 0.3s ease';
            newPackageCard.style.opacity = '1';
            newPackageCard.style.transform = 'scale(1)';
        }, 10);
        
        // Scroll to the new package
        setTimeout(() => {
            newPackageCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 400);
    }

    // Alternative function to refresh packages section (fallback)
    function refreshPackagesSection() {
        const packagesSection = document.getElementById('packages-section');
        const currentContent = packagesSection.innerHTML;
        
        // Store scroll position
        const scrollPos = window.scrollY;
        
        // Reload the section content
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newPackagesSection = doc.getElementById('packages-section');
                
                if (newPackagesSection) {
                    packagesSection.innerHTML = newPackagesSection.innerHTML;
                    // Restore scroll position
                    window.scrollTo(0, scrollPos);
                    showNotification('Packages updated successfully!', 'success');
                }
            })
            .catch(error => {
                console.error('Error refreshing packages:', error);
                showNotification('Error refreshing packages', 'error');
            });
    }

    // Booking Management Functions
    window.viewBooking = function(booking) {
        document.getElementById('detail_booking_id').textContent = '#' + booking.id;
        document.getElementById('detail_customer_name').textContent = booking.customer_name;
        document.getElementById('detail_package_name').textContent = booking.package_name;
        document.getElementById('detail_branch_name').textContent = booking.branch_name;
        document.getElementById('detail_travel_date').textContent = new Date(booking.travel_date).toLocaleDateString();
        document.getElementById('detail_people').textContent = booking.number_of_people;
        document.getElementById('detail_amount').textContent = '<?php echo $currencySymbol; ?> ' + new Intl.NumberFormat().format(booking.total_amount);
        document.getElementById('detail_status').textContent = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
        document.getElementById('detail_created_at').textContent = new Date(booking.created_at).toLocaleString();
        
        new bootstrap.Modal(document.getElementById('bookingDetailsModal')).show();
    }

    // Notification function
    window.showNotification = function(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0 show" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 250px; margin-bottom: 0.5rem;">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        const container = document.getElementById('toast-container');
        container.insertAdjacentHTML('beforeend', toastHtml);
        setTimeout(() => {
            const toastElem = document.getElementById(toastId);
            if (toastElem) toastElem.remove();
        }, 3000);
    }

    // Reports and Settings Functions (placeholders)
    window.generateReport = function(type) {
        showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} report generation started...`, 'info');
    }

    window.exportReport = function() {
        showNotification('Report export started...', 'info');
    }

    window.createBackup = function() {
        showNotification('Database backup created successfully!', 'success');
    }

    window.scheduleBackup = function() {
        showNotification('Auto backup scheduled!', 'success');
    }

    window.clearCache = function() {
        showNotification('Cache cleared successfully!', 'success');
    }

    window.optimizeDatabase = function() {
        showNotification('Database optimized successfully!', 'success');
    }

    // Account Security Management Functions
    window.loadLockedAccounts = function() {
        const formData = new FormData();
        formData.append('action', 'get_locked_accounts');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLockedAccounts(data.accounts);
                updateSecurityStats(data.accounts, data.failed_attempts_today);
                
                // Update the current max attempts display if it changed
                if (data.current_max_attempts) {
                    const infoAlert = document.querySelector('#securitySettingsForm .alert-info');
                    if (infoAlert) {
                        infoAlert.innerHTML = `
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Current Setting:</strong> ${data.current_max_attempts} attempts before account lockout (15-minute lockout period)
                        `;
                    }
                }
            } else {
                showNotification('Error loading locked accounts: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading locked accounts', 'error');
        });
    }

    window.displayLockedAccounts = function(accounts) {
        const container = document.getElementById('lockedAccountsList');
        
        if (accounts.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <p>No accounts are currently locked</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="list-group">';
        accounts.forEach(account => {
            const lastAttempt = new Date(account.last_attempt).toLocaleString();
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${account.username}</strong>
                        <br>
                        <small class="text-muted">
                            ${account.failed_attempts} failed attempts | Last: ${lastAttempt}
                        </small>
                    </div>
                    <button class="btn btn-outline-success btn-sm" onclick="unlockAccount('${account.username}')">
                        <i class="fas fa-unlock me-1"></i>Unlock
                    </button>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
    }

    window.updateSecurityStats = function(accounts, failedAttemptsToday = null) {
        document.getElementById('totalLockedAccounts').textContent = accounts.length;
        
        // Use the actual failed attempts count from the server
        if (failedAttemptsToday !== null) {
            document.getElementById('failedAttemptsToday').textContent = failedAttemptsToday;
        }
    }

    window.unlockAccount = function(username) {
        if (confirm(`Are you sure you want to unlock the account for user "${username}"?`)) {
            const formData = new FormData();
            formData.append('action', 'unlock_account');
            formData.append('username', username);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadLockedAccounts(); // Refresh the list
                } else {
                    showNotification('Error unlocking account: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error unlocking account', 'error');
            });
        }
    }

    // Load locked accounts when settings section is shown
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener to load locked accounts when settings section is shown
        const settingsLink = document.querySelector('a[onclick*="settings"]');
        if (settingsLink) {
            settingsLink.addEventListener('click', function() {
                setTimeout(() => {
                    if (document.getElementById('settings-section').classList.contains('active')) {
                        loadLockedAccounts();
                        loadInactiveUsers();
                        refreshSettingsDisplay();
                    }
                }, 100);
            });
        }
    });

    // Function to refresh settings display with current values
    window.refreshSettingsDisplay = function() {
        // Refresh the security settings info alert
        const formData = new FormData();
        formData.append('action', 'get_locked_accounts');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.current_max_attempts) {
                const infoAlert = document.querySelector('#securitySettingsForm .alert-info');
                if (infoAlert) {
                    infoAlert.innerHTML = `
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Current Setting:</strong> ${data.current_max_attempts} attempts before account lockout (15-minute lockout period)
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing settings display:', error);
        });
    }

    // User Status Management Functions
    window.loadInactiveUsers = function() {
        const formData = new FormData();
        formData.append('action', 'get_inactive_users');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInactiveUsers(data.users);
            } else {
                showNotification('Error loading inactive users: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading inactive users', 'error');
        });
    }

    window.displayInactiveUsers = function(users) {
        const container = document.getElementById('inactiveUsersList');
        
        if (users.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <p>No inactive or blocked users found</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="list-group">';
        users.forEach(user => {
            const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';
            const daysInactive = user.days_inactive || 'Unknown';
            const statusClass = user.status === 'blocked' ? 'danger' : 'warning';
            const statusText = user.status === 'blocked' ? 'Blocked' : 'Inactive';
            
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${user.full_name}</strong> (${user.username})
                        <br>
                        <small class="text-muted">
                            Last Login: ${lastLogin} | Days Inactive: ${daysInactive} | Role: ${user.role}
                        </small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-${statusClass} me-2">${statusText}</span>
                        <div class="btn-group">
                            <button class="btn btn-outline-success btn-sm" onclick="updateUserStatus(${user.id}, 'active')">
                                <i class="fas fa-check me-1"></i>Activate
                            </button>
                            ${user.status === 'blocked' ? `
                                <button class="btn btn-outline-warning btn-sm" onclick="unlockUserAccount('${user.username}')">
                                    <i class="fas fa-unlock me-1"></i>Unlock
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
    }

    window.updateUserStatus = function(userId, newStatus) {
        if (confirm(`Are you sure you want to change this user's status to "${newStatus}"?`)) {
            const formData = new FormData();
            formData.append('action', 'update_user_status');
            formData.append('user_id', userId);
            formData.append('status', newStatus);
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update the user row in the users table
                    const userRow = document.querySelector(`#users-table tr[data-user-id='${userId}']`);
                    if (userRow) {
                        // Update status badge
                        const statusCell = userRow.querySelector('td:nth-child(7)');
                        let statusClass = 'bg-success', statusText = 'Active';
                        if (newStatus === 'inactive') { statusClass = 'bg-warning'; statusText = 'Inactive'; }
                        else if (newStatus === 'blocked') { statusClass = 'bg-danger'; statusText = 'Blocked'; }
                        statusCell.innerHTML = `<span class="badge ${statusClass}">${statusText}</span>`;
                        // Update action buttons
                        const actionCell = userRow.querySelector('td:nth-child(8)');
                        let actionsHtml = `<div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(${userId})" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>`;
                        if (newStatus === 'active') {
                            actionsHtml += `
                                <button class="btn btn-sm btn-outline-warning" onclick="updateUserStatus(${userId}, 'inactive')" title="Deactivate User">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="updateUserStatus(${userId}, 'blocked')" title="Block User">
                                    <i class="fas fa-user-lock"></i>
                                </button>
                            `;
                        } else if (newStatus === 'inactive') {
                            actionsHtml += `
                                <button class="btn btn-sm btn-outline-success" onclick="updateUserStatus(${userId}, 'active')" title="Activate User">
                                    <i class="fas fa-user-check"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="updateUserStatus(${userId}, 'blocked')" title="Block User">
                                    <i class="fas fa-user-lock"></i>
                                </button>
                            `;
                        } else if (newStatus === 'blocked') {
                            actionsHtml += `
                                <button class="btn btn-sm btn-outline-info" onclick="unlockUserAccount('${userRow.children[1].textContent}')" title="Unlock Account">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            `;
                        }
                        actionsHtml += `
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${userId})" title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>`;
                        actionCell.innerHTML = actionsHtml;
                    }
                    updateUserCounts();
                } else {
                    showNotification('Error updating user status: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating user status', 'error');
            });
        }
    }

    window.unlockUserAccount = function(username) {
        if (confirm(`Are you sure you want to unlock the account for user "${username}"?`)) {
            const formData = new FormData();
            formData.append('action', 'unlock_account');
            formData.append('username', username);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadInactiveUsers(); // Refresh the list
                } else {
                    showNotification('Error unlocking account: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error unlocking account', 'error');
            });
        }
    }

    window.fixNewAccounts = function() {
        if (confirm('This will fix any new accounts that were incorrectly marked as inactive. Continue?')) {
            const formData = new FormData();
            formData.append('action', 'fix_new_accounts');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadInactiveUsers(); // Refresh the list
                } else {
                    showNotification('Error fixing accounts: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error fixing accounts', 'error');
            });
        }
    }

    window.refreshFailedAttemptsCount = function() {
        const formData = new FormData();
        formData.append('action', 'get_failed_attempts_today');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('failedAttemptsToday').textContent = data.failed_attempts_today;
            } else {
                console.error('Error refreshing failed attempts count:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    window.refreshUsersTable = function() {
        // Reload the page to get fresh data
        location.reload();
    }

    window.forceUpdateUserStatuses = function() {
        if (confirm('This will force update all user statuses based on inactivity and failed login attempts. Continue?')) {
            const formData = new FormData();
            formData.append('action', 'force_update_user_statuses');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to show updated statuses
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error updating user statuses: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating user statuses', 'error');
            });
        }
    }

    window.createTestBlockedUsers = function() {
        if (confirm('This will create test failed login attempts for 2 customer users to simulate blocked accounts. Continue?')) {
            const formData = new FormData();
            formData.append('action', 'create_test_blocked_users');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to show updated statuses
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error creating test blocked users: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error creating test blocked users', 'error');
            });
        }
    }

    window.loadEmailTemplate = function(template) {
        // Update active state in template list
        document.querySelectorAll('.list-group-item').forEach(item => item.classList.remove('active'));
        event.target.classList.add('active');
        
        // Load template from server
        const formData = new FormData();
        formData.append('action', 'get_email_template');
        formData.append('template_name', template);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('template_name').value = template;
                document.getElementById('email_subject').value = data.template.subject;
                document.getElementById('email_body').value = data.template.body;
                showNotification(`${template.replace('_', ' ')} template loaded!`, 'success');
            } else {
                showNotification('Error loading template: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error loading template', 'error');
        });
    }

    window.previewEmail = function() {
        showNotification('Email preview generated!', 'info');
    }

    // Settings form submissions
    document.getElementById('generalSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_general_settings');
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        const form = this;
        
        // Add loading state to button
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        
        // Add loading state to form
        form.classList.add('form-loading');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'General settings saved successfully!', 'success');
            } else {
                showNotification('Error saving settings: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving settings', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-loading');
            form.classList.remove('form-loading');
        });
    });

    document.getElementById('systemSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_system_settings');
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        const form = this;
        
        // Add loading state to button
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        // Add loading state to form
        form.classList.add('form-loading');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'System settings saved successfully!', 'success');
            } else {
                showNotification('Error saving settings: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving settings', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-loading');
            form.classList.remove('form-loading');
        });
    });

    document.getElementById('securitySettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_security_settings');
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        const form = this;
        
        // Add loading state to button
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        // Add loading state to form
        form.classList.add('form-loading');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Security settings saved successfully!', 'success');
                
                // Update the current setting display immediately using returned data
                if (data.settings) {
                    const maxAttempts = data.settings.max_login_attempts;
                    const sessionTimeout = data.settings.session_timeout;
                    const inactivityDays = data.settings.inactivity_days;
                    
                    // Update the info alert with new values and show it was just updated
                    const infoAlert = document.querySelector('#securitySettingsForm .alert-info');
                    if (infoAlert) {
                        infoAlert.innerHTML = `
                            <i class="fas fa-check-circle me-2 text-success"></i>
                            <strong>Current Settings:</strong><br>
                            • ${maxAttempts} attempts before account lockout (15-minute lockout period)<br>
                            • ${inactivityDays} days of inactivity before account becomes inactive
                            <span class="badge bg-success ms-2">Updated</span>
                        `;
                        
                        // Remove the "Updated" badge after 3 seconds
                        setTimeout(() => {
                            const badge = infoAlert.querySelector('.badge');
                            if (badge) {
                                badge.remove();
                            }
                            const icon = infoAlert.querySelector('.fas');
                            if (icon) {
                                icon.className = 'fas fa-info-circle me-2';
                            }
                        }, 3000);
                    }
                    
                    // Store settings in localStorage for immediate use
                    localStorage.setItem('security_settings', JSON.stringify(data.settings));
                    
                    // Update session timeout enforcement immediately
                    if (sessionTimeout) {
                        localStorage.setItem('session_timeout', sessionTimeout);
                    }
                }
                
                // Refresh locked accounts list to reflect new settings
                if (document.getElementById('settings-section').classList.contains('active')) {
                    setTimeout(() => {
                        loadLockedAccounts();
                    }, 500);
                }
                
            } else {
                showNotification('Error saving settings: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving settings', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-loading');
            form.classList.remove('form-loading');
        });
    });

    document.getElementById('emailTemplateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'save_email_template');
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Email template saved successfully!', 'success');
            } else {
                showNotification('Error saving template: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error saving template', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-loading');
        });
    });

    // Main Admin Profile Picture Preview
    function previewMainAdminProfilePic(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('mainAdminProfilePreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Main Admin Profile Form Submission
    const mainAdminProfileForm = document.getElementById('mainAdminProfileForm');
    if (mainAdminProfileForm) {
        mainAdminProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'update_main_admin_profile');
            formData.append('full_name', document.getElementById('main_admin_name').value);
            formData.append('email', document.getElementById('main_admin_email').value);
            formData.append('current_password', document.getElementById('main_admin_current_password').value);
            formData.append('new_password', document.getElementById('main_admin_new_password').value);
            formData.append('confirm_new_password', document.getElementById('main_admin_confirm_new_password').value);
            const profilePicInput = document.getElementById('main_admin_profile_pic');
            if (profilePicInput.files && profilePicInput.files[0]) {
                formData.append('profile_pic', profilePicInput.files[0]);
            }
            const submitBtn = mainAdminProfileForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
            submitBtn.disabled = true;
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Profile updated successfully!', 'success');
                    // Update header name and image
                    document.querySelector('.user-info span').textContent = 'Welcome, ' + document.getElementById('main_admin_name').value;
                    if (data.profile_pic) {
                        document.querySelector('.user-info img').src = '../' + data.profile_pic;
                        document.getElementById('mainAdminProfilePreview').src = '../' + data.profile_pic;
                    }
                    // Clear password fields
                    document.getElementById('main_admin_current_password').value = '';
                    document.getElementById('main_admin_new_password').value = '';
                    document.getElementById('main_admin_confirm_new_password').value = '';
                    if (data.logout) {
                        setTimeout(() => { window.location.href = '../index.php'; }, 1200);
                    } else {
                        setTimeout(() => location.reload(), 1200);
                    }
                } else {
                    showNotification(data.message || 'Error updating profile', 'error');
                }
            })
            .catch(() => {
                showNotification('Error updating profile', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    </script>
</body>
</html>
