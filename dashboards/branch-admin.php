<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireRole('branch_admin');

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

if (!$_SESSION['branch_id']) {
    die("Error: No branch assigned to this admin account.");
}

$branch_id = $_SESSION['branch_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_package':
            $stmt = $db->prepare("INSERT INTO packages (name, description, destination, duration_days, price, branch_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$_POST['name'], $_POST['description'], $_POST['destination'], $_POST['duration_days'], $_POST['price'], $branch_id, $_POST['image_url']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_package':
            $stmt = $db->prepare("UPDATE packages SET name = ?, description = ?, destination = ?, duration_days = ?, price = ?, image_url = ? WHERE id = ? AND branch_id = ?");
            $result = $stmt->execute([$_POST['name'], $_POST['description'], $_POST['destination'], $_POST['duration_days'], $_POST['price'], $_POST['image_url'], $_POST['id'], $branch_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_package':
            $stmt = $db->prepare("UPDATE packages SET status = 'inactive' WHERE id = ? AND branch_id = ?");
            $result = $stmt->execute([$_POST['id'], $branch_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_booking_status':
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ? AND branch_id = ?");
            $result = $stmt->execute([$_POST['status'], $_POST['booking_id'], $branch_id]);
            echo json_encode(['success' => $result]);
            exit;

        case 'update_branch_info':
            $stmt = $db->prepare("UPDATE branches SET location = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['location'], $_POST['email'], $_POST['phone'], $branch_id]);
            echo json_encode(['success' => $result]);
            exit;

        case 'update_admin_profile':
            try {
                // First verify current password if provided
                if (!empty($_POST['current_password'])) {
                    $stmt = $db->prepare("SELECT password, email, full_name FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $currentPasswordOk = false;
                    if ($user) {
                        if (password_verify($_POST['current_password'], $user['password'])) {
                            $currentPasswordOk = true;
                        } elseif ($_POST['current_password'] === $user['password']) { // legacy plain text
                            $currentPasswordOk = true;
                        }
                    }
                    if (!$currentPasswordOk) {
                        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                        exit;
                    }
                }

                // Handle profile picture upload
                $profile_pic_path = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    if (in_array($_FILES['profile_pic']['type'], $allowed_types) && $_FILES['profile_pic']['size'] <= $max_size) {
                        $upload_dir = '../uploads/profile_pics/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filepath)) {
                            $profile_pic_path = 'uploads/profile_pics/' . $filename;
                            // Delete old profile picture if exists
                            if (!empty($_SESSION['profile_pic']) && $_SESSION['profile_pic'] != 'https://via.placeholder.com/150') {
                                $old_file = '../' . $_SESSION['profile_pic'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                            }
                        }
                    }
                }

                // Update user information
                if (!empty($_POST['new_password'])) {
                    // 1. Check current password (support both hash and legacy plain text)
                    $stmt = $db->prepare("SELECT password, email, full_name FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $currentPasswordOk = false;
                    if ($user) {
                        if (password_verify($_POST['current_password'], $user['password'])) {
                            $currentPasswordOk = true;
                        } elseif ($_POST['current_password'] === $user['password']) { // legacy plain text
                            $currentPasswordOk = true;
                        }
                    }
                    if (!$currentPasswordOk) {
                        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                        exit;
                    }
                    // 2. Check password length
                    if (strlen($_POST['new_password']) < 6) {
                        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
                        exit;
                    }
                    // 3. Update password
                    $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    if ($profile_pic_path) {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, profile_pic = ? WHERE id = ?");
                        $result = $stmt->execute([$_POST['full_name'], $_POST['email'], $hashed_password, $profile_pic_path, $_SESSION['user_id']]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                        $result = $stmt->execute([$_POST['full_name'], $_POST['email'], $hashed_password, $_SESSION['user_id']]);
                    }
                    // Destroy session and force logout
                    session_unset();
                    session_destroy();
                    echo json_encode(['success' => true, 'logout' => true, 'message' => 'Password changed. Please log in again.']);
                    exit;
                } else {
                    // Update without password change
                    if ($profile_pic_path) {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, profile_pic = ? WHERE id = ?");
                        $result = $stmt->execute([$_POST['full_name'], $_POST['email'], $profile_pic_path, $_SESSION['user_id']]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                        $result = $stmt->execute([$_POST['full_name'], $_POST['email'], $_SESSION['user_id']]);
                    }
                }

                if ($result) {
                    // Update session data
                    $_SESSION['full_name'] = $_POST['full_name'];
                    $_SESSION['email'] = $_POST['email'];
                    if ($profile_pic_path) {
                        $_SESSION['profile_pic'] = $profile_pic_path;
                    }
                }

                echo json_encode(['success' => $result, 'profile_pic' => $profile_pic_path]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        case 'toggle_branch_status':
            $new_status = ($_POST['status'] === 'active') ? 'active' : 'inactive';
            $stmt = $db->prepare("UPDATE branches SET status = ? WHERE id = ?");
            $result = $stmt->execute([$new_status, $branch_id]);
            echo json_encode(['success' => $result, 'new_status' => $new_status]);
            exit;
    }
}

// Get branch statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM packages WHERE branch_id = ? AND status = 'active') as active_packages,
    (SELECT COUNT(*) FROM bookings WHERE branch_id = ?) as total_bookings,
    (SELECT COUNT(*) FROM bookings WHERE branch_id = ? AND status = 'pending') as pending_bookings,
    (SELECT SUM(total_amount) FROM bookings WHERE branch_id = ? AND status = 'confirmed') as branch_revenue";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$branch_id, $branch_id, $branch_id, $branch_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get branch info
$branch_query = "SELECT * FROM branches WHERE id = ?";
$branch_stmt = $db->prepare($branch_query);
$branch_stmt->execute([$branch_id]);
$branch_info = $branch_stmt->fetch(PDO::FETCH_ASSOC);

// Get branch bookings
$bookings_query = "SELECT b.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone, p.name as package_name 
                   FROM bookings b 
                   JOIN users u ON b.customer_id = u.id 
                   JOIN packages p ON b.package_id = p.id 
                   WHERE b.branch_id = ? 
                   ORDER BY b.created_at DESC";
$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->execute([$branch_id]);

// Load system settings for currency
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

// Currency helper functions
function getCurrencySymbol($currency) {
    switch ($currency) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'NPR':
        default: return 'Rs.';
    }
}

$defaultCurrency = getSystemSetting($db, 'default_currency', 'NPR');
$currencySymbol = getCurrencySymbol($defaultCurrency);
$branch_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch packages
$packages_query = "SELECT * FROM packages WHERE branch_id = ? AND status = 'active' ORDER BY created_at DESC";
$packages_stmt = $db->prepare($packages_query);
$packages_stmt->execute([$branch_id]);
$branch_packages = $packages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch customers
$customers_query = "SELECT DISTINCT u.*, COUNT(b.id) as total_bookings, SUM(b.total_amount) as total_spent
                    FROM users u 
                    JOIN bookings b ON u.id = b.customer_id 
                    WHERE b.branch_id = ? AND u.role = 'customer'
                    GROUP BY u.id 
                    ORDER BY total_bookings DESC";
$customers_stmt = $db->prepare($customers_query);
$customers_stmt->execute([$branch_id]);
$branch_customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Admin Dashboard - TravelNepal</title>
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
        .modal-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        /* Simple Switch Styles */
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.2em;
            background-color: #e9ecef;
            border: 1px solid #ccc;
            transition: background 0.2s, border-color 0.2s;
            box-shadow: none;
        }
        .form-switch .form-check-input:checked {
            background-color: #28d745;
            border-color: #28d745;
        }
        .form-switch .form-check-label {
            margin-left: 0.75em;
            font-weight: bold;
            color: #28d745;
            transition: color 0.2s;
        }
        .form-switch .form-check-input:not(:checked) + .form-check-label {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-plane me-2"></i>TravelNepal</h3>
                <p class="text-muted">Branch Admin</p>
                <small class="text-muted"><?php echo $branch_info['name']; ?></small>
            </div>
            <ul class="sidebar-menu">
                <li class="active">
                    <a href="#" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('packages')"><i class="fas fa-suitcase"></i> My Packages</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('bookings')"><i class="fas fa-calendar-check"></i> Bookings</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('customers')"><i class="fas fa-users"></i> Customers</a>
                </li>
                <li>
                    <a href="#" onclick="showSection('analytics')"><i class="fas fa-chart-line"></i> Analytics</a>
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
                    <h1 id="page-title">Branch Admin Dashboard</h1>
                    <div class="user-info">
                        <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                        <img src="<?php echo !empty($_SESSION['profile_pic']) ? '../' . $_SESSION['profile_pic'] : 'https://via.placeholder.com/40'; ?>" alt="Profile" class="rounded-circle ms-2">
                    </div>
                </div>
            </header>

            <div class="container-fluid px-4">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section active">
                    <!-- Branch Info Card -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card branch-info-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h4><i class="fas fa-map-marker-alt me-2"></i><?php echo $branch_info['name']; ?></h4>
                                            <p class="text-muted mb-1"><i class="fas fa-location-dot me-2"></i><?php echo $branch_info['location']; ?></p>
                                            <p class="text-muted mb-0"><i class="fas fa-envelope me-2"></i><?php echo $branch_info['contact_email']; ?></p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge fs-6 bg-<?php echo ($branch_info['status'] ?? 'active') === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($branch_info['status'] ?? 'active'); ?> Branch
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4><?php echo $stats['active_packages']; ?></h4>
                                            <p>Active Packages</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-suitcase"></i>
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
                                            <h4><?php echo $stats['total_bookings']; ?></h4>
                                            <p>Total Bookings</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-calendar-check"></i>
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
                                            <h4><?php echo $stats['pending_bookings']; ?></h4>
                                            <p>Pending Bookings</p>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-clock"></i>
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
                                            <h4><?php echo $currencySymbol . number_format($stats['branch_revenue'] ?? 0); ?></h4>
                                            <p>Branch Revenue</p>
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
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-calendar-check me-2"></i>Recent Bookings</h5>
                                    <button class="btn btn-primary btn-sm" onclick="showSection('packages')">
                                        <i class="fas fa-plus me-1"></i>Manage Packages
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Booking ID</th>
                                                    <th>Customer</th>
                                                    <th>Package</th>
                                                    <th>Travel Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($branch_bookings, 0, 5) as $booking): ?>
                                                <tr>
                                                    <td>#<?php echo $booking['id']; ?></td>
                                                    <td><?php echo $booking['customer_name']; ?></td>
                                                    <td><?php echo $booking['package_name']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                                    <td><?php echo $currencySymbol . number_format($booking['total_amount']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $booking['status'] == 'confirmed' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($booking['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info btn-view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($booking['status'] == 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'confirmed')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr class="booking-details-row" id="details-<?php echo $booking['id']; ?>" style="display: none; background: #f9f9f9;">
                                                    <td colspan="7">
                                                        <div class="booking-details-content">
                                                            <strong>Customer Email:</strong> <?php echo htmlspecialchars($booking['customer_email']); ?><br>
                                                            <strong>Phone:</strong> <?php echo htmlspecialchars($booking['customer_phone']); ?><br>
                                                            <strong>Number of People:</strong> <?php echo $booking['number_of_people']; ?><br>
                                                            <strong>Booking Reference:</strong> <?php echo htmlspecialchars($booking['payment_reference'] ?? 'N/A'); ?><br>
                                                            <strong>Status:</strong> <?php echo ucfirst($booking['status']); ?><br>
                                                            <strong>Payment Status:</strong> <?php echo ucfirst($booking['payment_status'] ?? 'N/A'); ?><br>
                                                            <strong>Booking ID:</strong> #<?php echo $booking['id']; ?>
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
                    </div>
                </div>

                <!-- My Packages Section -->
                <div id="packages-section" class="content-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3><i class="fas fa-suitcase me-2"></i>My Packages</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="openPackageModal()">
                            <i class="fas fa-plus me-1"></i>Add Package
                        </button>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($branch_packages as $package): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <img src="<?php echo $package['image_url']; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo $package['name']; ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo $package['name']; ?></h5>
                                    <p class="card-text text-muted small"><?php echo substr($package['description'], 0, 100); ?>...</p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-info"><?php echo $package['destination']; ?></span>
                                            <span class="fw-bold"><?php echo $currencySymbol . number_format($package['price']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted"><?php echo $package['duration_days']; ?> Days</small>
                                            <span class="badge bg-success">Active</span>
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
                                            <th>Travel Date</th>
                                            <th>People</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branch_bookings as $booking): ?>
                                        <tr>
                                            <td>#<?php echo $booking['id']; ?></td>
                                            <td><?php echo $booking['customer_name']; ?></td>
                                            <td><?php echo $booking['package_name']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($booking['travel_date'])); ?></td>
                                            <td><?php echo $booking['number_of_people']; ?></td>
                                            <td><?php echo $currencySymbol . number_format($booking['total_amount']); ?></td>
                                            <td>
                                                <select class="form-select form-select-sm" onchange="updateBookingStatus(<?php echo $booking['id']; ?>, this.value)">
                                                    <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $booking['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <option value="completed" <?php echo $booking['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info btn-view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="booking-details-row" id="details-<?php echo $booking['id']; ?>" style="display: none; background: #f9f9f9;">
                                            <td colspan="8">
                                                <div class="booking-details-content">
                                                    <strong>Customer Email:</strong> <?php echo htmlspecialchars($booking['customer_email']); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($booking['customer_phone']); ?><br>
                                                    <strong>Number of People:</strong> <?php echo $booking['number_of_people']; ?><br>
                                                    <strong>Booking Reference:</strong> <?php echo htmlspecialchars($booking['payment_reference'] ?? 'N/A'); ?><br>
                                                    <strong>Status:</strong> <?php echo ucfirst($booking['status']); ?><br>
                                                    <strong>Payment Status:</strong> <?php echo ucfirst($booking['payment_status'] ?? 'N/A'); ?><br>
                                                    <strong>Booking ID:</strong> #<?php echo $booking['id']; ?>
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

                <!-- Customers Section -->
                <div id="customers-section" class="content-section">
                    <h3><i class="fas fa-users me-2"></i>My Customers</h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Total Bookings</th>
                                            <th>Total Spent</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branch_customers as $customer): ?>
                                        <tr>
                                            <td><?php echo $customer['full_name']; ?></td>
                                            <td><?php echo $customer['email']; ?></td>
                                            <td><?php echo $customer['phone'] ?? 'N/A'; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $customer['total_bookings']; ?></span></td>
                                            <td><?php echo $currencySymbol . number_format($customer['total_spent']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info btn-view-customer"
                                                    data-customer-id="<?php echo $customer['id']; ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($customer['full_name']); ?>"
                                                    data-customer-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                    data-customer-phone="<?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>"
                                                    data-customer-total-bookings="<?php echo $customer['total_bookings']; ?>"
                                                    data-customer-total-spent="<?php echo number_format($customer['total_spent']); ?>">
                                                    <i class="fas fa-eye"></i> View
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

                <!-- Analytics Section -->
                <div id="analytics-section" class="content-section">
                    <h3><i class="fas fa-chart-line me-2"></i>Branch Analytics</h3>
                    
                    <!-- Analytics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-chart-bar fa-2x text-primary mb-2"></i>
                                    <h5>Monthly Report</h5>
                                    <button class="btn btn-outline-primary btn-sm">Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-chart-pie fa-2x text-success mb-2"></i>
                                    <h5>Package Performance</h5>
                                    <button class="btn btn-outline-success btn-sm">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-users fa-2x text-info mb-2"></i>
                                    <h5>Customer Analysis</h5>
                                    <button class="btn btn-outline-info btn-sm">Analyze</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-rupee-sign fa-2x text-warning mb-2"></i>
                                    <h5>Revenue Trends</h5>
                                    <button class="btn btn-outline-warning btn-sm">View</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-table me-2"></i>Package Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Package Name</th>
                                            <th>Total Bookings</th>
                                            <th>Revenue Generated</th>
                                            <th>Average Rating</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branch_packages as $package): ?>
                                        <tr>
                                            <td><?php echo $package['name']; ?></td>
                                            <td><span class="badge bg-primary"><?php echo rand(5, 25); ?></span></td>
                                            <td><?php echo $currencySymbol . number_format(rand(50000, 200000)); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-1"><?php echo number_format(rand(35, 50)/10, 1); ?></span>
                                                    <div class="text-warning">
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="far fa-star"></i>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-success">Active</span></td>
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
                    <h3><i class="fas fa-cog me-2"></i>Branch Settings</h3>
                    
                    <div class="row">
                        <!-- Branch Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5><i class="fas fa-building me-2"></i>Branch Information</h5>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="branchStatusSwitch"
                                            <?php echo ($branch_info['status'] ?? 'active') === 'active' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="branchStatusSwitch" id="branchStatusSwitchLabel">
                                            <?php echo ucfirst($branch_info['status'] ?? 'active'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="branchInfoForm">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="branch_name" value="<?php echo $branch_info['name']; ?>" readonly>
                                            <label for="branch_name">Branch Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="branch_location" value="<?php echo $branch_info['location']; ?>">
                                            <label for="branch_location">Location</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="branch_email" value="<?php echo $branch_info['contact_email']; ?>">
                                            <label for="branch_email">Contact Email</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="tel" class="form-control" id="branch_phone" value="<?php echo $branch_info['contact_phone']; ?>">
                                            <label for="branch_phone">Contact Phone</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Branch Info
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Profile -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5><i class="fas fa-user me-2"></i>Admin Profile</h5>
                                </div>
                                <div class="card-body">
                                    <form id="adminProfileForm" enctype="multipart/form-data">
                                        <!-- Profile Picture Section -->
                                        <div class="text-center mb-4">
                                            <div class="position-relative d-inline-block">
                                                <img id="profilePreview" src="<?php echo !empty($_SESSION['profile_pic']) ? '../' . $_SESSION['profile_pic'] : 'https://via.placeholder.com/150'; ?>" 
                                                     alt="Profile Picture" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;">
                                                <label for="profile_pic" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;">
                                                    <i class="fas fa-camera"></i>
                                                </label>
                                            </div>
                                            <input type="file" id="profile_pic" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" onchange="previewProfilePic(this)">
                                            <div class="mt-2">
                                                <small class="text-muted">Click the camera icon to change profile picture</small>
                                            </div>
                                        </div>
                                        
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="admin_name" value="<?php echo $_SESSION['full_name']; ?>">
                                            <label for="admin_name">Full Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="admin_email" value="<?php echo $_SESSION['email'] ?? 'admin@branch.com'; ?>">
                                            <label for="admin_email">Email</label>
                                        </div>
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="current_password" placeholder="Current Password">
                                            <label for="current_password">Current Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="current_password" tabindex="-1" style="z-index:2;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="new_password" placeholder="New Password">
                                            <label for="new_password">New Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="new_password" tabindex="-1" style="z-index:2;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <!-- Confirm New Password Field -->
                                        <div class="form-floating mb-3 position-relative">
                                            <input type="password" class="form-control" id="confirm_new_password" placeholder="Confirm New Password">
                                            <label for="confirm_new_password">Confirm New Password</label>
                                            <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 toggle-password" data-target="confirm_new_password" tabindex="-1" style="z-index:2;">
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

                    <!-- Notification Settings -->
                    <!-- <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="notificationForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_bookings" checked>
                                            <label class="form-check-label" for="email_bookings">
                                                Email notifications for new bookings
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_cancellations" checked>
                                            <label class="form-check-label" for="email_cancellations">
                                                Email notifications for cancellations
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_payments">
                                            <label class="form-check-label" for="email_payments">
                                                Email notifications for payments
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_bookings">
                                            <label class="form-check-label" for="sms_bookings">
                                                SMS notifications for new bookings
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_urgent">
                                            <label class="form-check-label" for="sms_urgent">
                                                SMS for urgent notifications only
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="dashboard_alerts" checked>
                                            <label class="form-check-label" for="dashboard_alerts">
                                                Dashboard alert notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Save Notification Settings
                                </button>
                            </form>
                        </div>
                    </div> -->
                </div>
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
                        <div class="form-floating">
                            <input type="number" class="form-control" id="package_price" name="price" step="0.01" required>
                                                                        <label for="package_price">Price (<?php echo $currencySymbol; ?>)</label>
                        </div>
                        <div class="form-floating">
                            <input type="url" class="form-control" id="package_image_url" name="image_url" required>
                            <label for="package_image_url">Image URL</label>
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
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1" aria-labelledby="bookingDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="bookingDetailsModalLabel">Booking Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="bookingDetailsContent">
            <!-- Details will be injected here -->
          </div>
        </div>
      </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="customerDetailsModalLabel">Customer Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="customerDetailsContent">
            <!-- Details will be injected here -->
          </div>
        </div>
      </div>
    </div>

    <!-- Toast with progress bar -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11000;">
      <div id="mainToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="mainToastBody"></div>
          <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="progress toast-progress" style="height: 3px;">
          <div id="mainToastProgress" class="progress-bar bg-success" style="width: 100%;"></div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
            
            // Show selected section
            document.getElementById(section + '-section').classList.add('active');
            event.target.closest('li').classList.add('active');
            
            // Update page title
            const titles = {
                'dashboard': 'Branch Admin Dashboard',
                'packages': 'My Packages',
                'bookings': 'Bookings Management',
                'customers': 'My Customers',
                'analytics': 'Branch Analytics',
                'settings': 'Branch Settings'
            };
            document.getElementById('page-title').textContent = titles[section] || 'Branch Admin Dashboard';
        }

        // Package Management
        function openPackageModal(package = null) {
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
                document.getElementById('package_image_url').value = package.image_url;
            } else {
                title.textContent = 'Add Package';
                form.reset();
            }
        }

        function editPackage(package) {
            openPackageModal(package);
            new bootstrap.Modal(document.getElementById('packageModal')).show();
        }

        function deletePackage(id) {
            if (confirm('Are you sure you want to delete this package?')) {
                const formData = new FormData();
                formData.append('action', 'delete_package');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Package deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification('Error deleting package', 'error');
                    }
                });
            }
        }

        function updateBookingStatus(bookingId, status) {
            const formData = new FormData();
            formData.append('action', 'update_booking_status');
            formData.append('booking_id', bookingId);
            formData.append('status', status);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Booking status updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification('Error updating booking status', 'error');
                }
            });
        }

        function viewBooking(bookingId) {
            alert('View booking details for booking #' + bookingId);
        }

        function viewCustomer(customerId) {
            alert('View customer details for customer #' + customerId);
        }

        // Form Submissions
        document.getElementById('packageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const isEdit = document.getElementById('package_id').value;
            formData.append('action', isEdit ? 'update_package' : 'add_package');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Package saved successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification('Error saving package', 'error');
                }
            });
        });

        document.getElementById('branchInfoForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Save the current section to localStorage
            localStorage.setItem('lastActiveSection', 'settings');

            const formData = new FormData();
            formData.append('action', 'update_branch_info');
            formData.append('location', document.getElementById('branch_location').value);
            formData.append('email', document.getElementById('branch_email').value);
            formData.append('phone', document.getElementById('branch_phone').value);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Branch information updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification('Error updating branch information', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating branch information', 'error');
            });
        });

        // Branch Status Toggle
        var branchStatusSwitch = document.getElementById('branchStatusSwitch');
        if (branchStatusSwitch) {
            branchStatusSwitch.addEventListener('change', function() {
                const newStatus = this.checked ? 'active' : 'inactive';
                const formData = new FormData();
                formData.append('action', 'toggle_branch_status');
                formData.append('status', newStatus);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update label
                        const label = document.getElementById('branchStatusSwitchLabel');
                        label.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
                        showNotification('Branch status updated to ' + data.new_status + '!', 'success');
                    } else {
                        showNotification('Error updating branch status', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating branch status', 'error');
                });
            });
        }

        // Restore last active section after reload
        window.addEventListener('DOMContentLoaded', function() {
            const lastSection = localStorage.getItem('lastActiveSection');
            if (lastSection) {
                showSection(lastSection);
                localStorage.removeItem('lastActiveSection');
            }
        });

        // Admin Profile
        document.getElementById('adminProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Save the current section to localStorage
            localStorage.setItem('lastActiveSection', 'settings');

            const fullName = document.getElementById('admin_name').value;
            const email = document.getElementById('admin_email').value;
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmNewPassword = document.getElementById('confirm_new_password').value;

            // If new password is set, require current password and confirmation
            if (newPassword) {
                if (!currentPassword) {
                    showNotification('Please enter your current password to change password.', 'error');
                    return;
                }
                if (newPassword.length < 6) {
                    showNotification('New password must be at least 6 characters.', 'error');
                    return;
                }
                if (newPassword !== confirmNewPassword) {
                    showNotification('New password and confirmation do not match.', 'error');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('action', 'update_admin_profile');
            formData.append('full_name', fullName);
            formData.append('email', email);

            if (newPassword) {
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
            } else {
                // Only append current_password if filled (for legacy support)
                if (currentPassword) {
                    formData.append('current_password', currentPassword);
                }
            }

            // Add profile picture if selected
            const profilePicInput = document.getElementById('profile_pic');
            if (profilePicInput.files && profilePicInput.files[0]) {
                formData.append('profile_pic', profilePicInput.files[0]);
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.logout) {
                        showNotification('Password changed. Please log in again.', 'success');
                        setTimeout(() => {
                            window.location.href = '../index.php'; // or your login page
                        }, 1200);
                    } else {
                        showNotification('Admin profile updated successfully!', 'success');
                        // Update the displayed name in the header
                        document.querySelector('.user-info span').textContent = 'Welcome, ' + fullName;
                        // Update profile picture in header if uploaded
                        if (data.profile_pic) {
                            document.querySelector('.user-info img').src = '../' + data.profile_pic;
                            document.getElementById('profilePreview').src = '../' + data.profile_pic;
                        }
                        // Clear password fields for security
                        document.getElementById('current_password').value = '';
                        document.getElementById('new_password').value = '';
                        document.getElementById('confirm_new_password').value = '';
                        setTimeout(() => location.reload(), 1200);
                    }
                } else {
                    showNotification(data.message || 'Error updating admin profile', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating admin profile', 'error');
            });
        });

        var notificationForm = document.getElementById('notificationForm');
        if (notificationForm) {
            notificationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showNotification('Notification settings saved successfully!', 'success');
            });
        }

        // Function to preview profile picture
        function previewProfilePic(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Store the last opened bookingId to toggle modal on repeated click
            let lastOpenedBookingId = null;
            let bookingModal = new bootstrap.Modal(document.getElementById('bookingDetailsModal'));

            document.querySelectorAll('.btn-view-booking').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookingId = this.getAttribute('data-booking-id');
                    // If the same booking is clicked again, close the modal
                    if (lastOpenedBookingId === bookingId && document.getElementById('bookingDetailsModal').classList.contains('show')) {
                        bookingModal.hide();
                        lastOpenedBookingId = null;
                        return;
                    }
                    lastOpenedBookingId = bookingId;

                    // Get the details from the hidden row (or directly from data attributes if you prefer)
                    var detailsRow = document.getElementById('details-' + bookingId);
                    var detailsHtml = detailsRow ? detailsRow.querySelector('.booking-details-content').innerHTML : 'No details found.';

                    document.getElementById('bookingDetailsContent').innerHTML = detailsHtml;
                    bookingModal.show();
                });
            });

            // Reset lastOpenedBookingId when modal is closed
            document.getElementById('bookingDetailsModal').addEventListener('hidden.bs.modal', function () {
                lastOpenedBookingId = null;
            });

            document.querySelectorAll('.btn-view-customer').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var html = `
                        <strong>Name:</strong> ${this.getAttribute('data-customer-name')}<br>
                        <strong>Email:</strong> ${this.getAttribute('data-customer-email')}<br>
                        <strong>Phone:</strong> ${this.getAttribute('data-customer-phone')}<br>
                        <strong>Total Bookings:</strong> ${this.getAttribute('data-customer-total-bookings')}<br>
                                                        <strong>Total Spent:</strong> <?php echo $currencySymbol; ?>${this.getAttribute('data-customer-total-spent')}
                    `;
                    document.getElementById('customerDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('customerDetailsModal')).show();
                });
            });
        });

        window.showNotification = function(message, type = 'success') {
            const toast = document.getElementById('mainToast');
            const toastBody = document.getElementById('mainToastBody');
            const progressBar = document.getElementById('mainToastProgress');
            toast.className = 'toast align-items-center border-0';
            progressBar.className = 'progress-bar';
            if (type === 'error') {
                toast.classList.add('bg-danger', 'text-white');
                progressBar.classList.add('bg-danger');
            } else if (type === 'success') {
                toast.classList.add('bg-success', 'text-white');
                progressBar.classList.add('bg-success');
            } else {
                toast.classList.add('bg-primary', 'text-white');
                progressBar.classList.add('bg-primary');
            }
            toastBody.textContent = message;

            // Reset progress bar
            progressBar.style.width = '100%';
            progressBar.style.transition = 'none';

            // Show the toast using Bootstrap's JS API
            const bsToast = bootstrap.Toast.getOrCreateInstance(toast);
            bsToast.show();

            // Animate the progress bar
            setTimeout(() => {
                progressBar.style.transition = 'width 2.8s linear';
                progressBar.style.width = '0%';
            }, 100); // slight delay to trigger transition

            // Optionally, hide the toast after 3s (Bootstrap default is 5s)
            setTimeout(() => {
                bsToast.hide();
            }, 3000);
        };

        // Toggle password visibility for all .toggle-password buttons (single handler for all)
        document.addEventListener('DOMContentLoaded', function() {
          document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
              const targetId = btn.getAttribute('data-target');
              const input = document.getElementById(targetId);
              if (input) {
                if (input.type === 'password') {
                  input.type = 'text';
                  btn.querySelector('i').classList.remove('fa-eye');
                  btn.querySelector('i').classList.add('fa-eye-slash');
                } else {
                  input.type = 'password';
                  btn.querySelector('i').classList.remove('fa-eye-slash');
                  btn.querySelector('i').classList.add('fa-eye');
                }
              }
            });
          });
        });
    </script>
</body>
</html>
