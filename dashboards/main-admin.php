<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireRole('main_admin');

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
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
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
                WHERE u.status = 'active' ORDER BY u.created_at DESC";
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
                        <img src="https://via.placeholder.com/40" alt="Profile" class="rounded-circle ms-2">
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
                                            <h4>NPR <?php echo number_format($stats['total_revenue'] ?? 0); ?></h4>
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
                                                    <td>NPR <?php echo number_format($booking['total_amount']); ?></td>
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
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
                            <i class="fas fa-plus me-1"></i>Add User
                        </button>
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
                                        <tr>
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
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
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
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <img src="<?php echo $package['image_url']; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="<?php echo $package['name']; ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo $package['name']; ?></h5>
                                    <p class="card-text text-muted small"><?php echo substr($package['description'], 0, 100); ?>...</p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-info"><?php echo $package['destination']; ?></span>
                                            <span class="fw-bold">NPR <?php echo number_format($package['price']); ?></span>
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
                                            <td>NPR <?php echo number_format($booking['total_amount']); ?></td>
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
                                            <td>NPR <?php echo number_format(rand(50000, 200000)); ?></td>
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
                                            <input type="text" class="form-control" id="company_name" value="TravelNepal">
                                            <label for="company_name">Company Name</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="company_email" value="admin@travelnepal.com">
                                            <label for="company_email">Company Email</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="tel" class="form-control" id="company_phone" value="+977-9999999999">
                                            <label for="company_phone">Company Phone</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="company_address" style="height: 100px">Thamel, Kathmandu, Nepal</textarea>
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
                                            <select class="form-select" id="default_currency">
                                                <option value="NPR" selected>Nepali Rupee (Rs.)</option>
                                                <option value="USD">US Dollar ($)</option>
                                                <option value="EUR">Euro (€)</option>
                                            </select>
                                            <label for="default_currency">Default Currency</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="date_format">
                                                <option value="DD/MM/YYYY" selected>DD/MM/YYYY</option>
                                                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                                <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                            </select>
                                            <label for="date_format">Date Format</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="timezone">
                                                <option value="Asia/Kathmandu" selected>Asia/Kathmandu (NPT)</option>
                                                <option value="UTC">UTC</option>
                                                <option value="America/New_York">America/New_York (EST)</option>
                                            </select>
                                            <label for="timezone">Timezone</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" checked>
                                            <label class="form-check-label" for="email_notifications">
                                                Enable Email Notifications
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications">
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
                                            <input type="number" class="form-control" id="session_timeout" value="30" min="5" max="120">
                                            <label for="session_timeout">Session Timeout (minutes)</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <input type="number" class="form-control" id="max_login_attempts" value="5" min="3" max="10">
                                            <label for="max_login_attempts">Max Login Attempts</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="two_factor_auth">
                                            <label class="form-check-label" for="two_factor_auth">
                                                Enable Two-Factor Authentication
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="password_complexity" checked>
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
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="email_subject" value="Booking Confirmation - TravelCo">
                                            <label for="email_subject">Email Subject</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="email_body" style="height: 200px">Dear {customer_name},

Thank you for booking with TravelCo! Your booking has been confirmed.

Booking Details:
- Package: {package_name}
- Destination: {destination}
- Travel Date: {travel_date}
- Number of People: {people_count}
- Total Amount: {total_amount}

We look forward to serving you!

Best regards,
TravelCo Team</textarea>
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
                                    <input type="password" class="form-control" id="user_password" name="password">
                                    <label for="user_password">Password</label>
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>
                            </div>
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
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="user_full_name" name="full_name" required>
                                    <label for="user_full_name">Full Name</label>
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
                                    <label for="package_price">Price (Rs.)</label>
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
                        <li class="list-group-item"><strong>Amount:</strong> NPR <span id="detail_amount"></span></li>
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
        // Navigation
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
            
            // Show selected section
            document.getElementById(section + '-section').classList.add('active');
            if (event && event.target) {
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
            // Store active section
            localStorage.setItem('activeSection', section);
        }

        // Branch Management
        function openBranchModal(branch = null) {
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
            }
        }

        function editBranch(branch) {
            openBranchModal(branch);
            new bootstrap.Modal(document.getElementById('branchModal')).show();
        }

        function showBranchesAlert(message, type) {
            // Create a Bootstrap toast
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 fade" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 250px; margin-bottom: 0.5rem; opacity: 0; transition: opacity 0.5s;">
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
            const toastElem = document.getElementById(toastId);
            // Fade in
            setTimeout(() => {
                toastElem.classList.add('show');
                toastElem.style.opacity = 1;
            }, 10);
            // Fade out after 2 seconds
            setTimeout(() => {
                toastElem.classList.remove('show');
                toastElem.classList.add('hide');
                toastElem.style.opacity = 0;
                setTimeout(() => {
                    toastElem.remove();
                }, 500); // Wait for fade-out transition
            }, 2000);
        }

        function toggleBranches() {
            const btn = document.getElementById('toggle-branches-btn');
            const showAll = btn.textContent === 'Show All';
            btn.textContent = showAll ? 'Show Active Only' : 'Show All';
            refreshBranchesTable(showAll);
        }

        function refreshBranchesTable(showAll = false) {
            // Fetch the updated branches table via AJAX
            fetch(`?ajax=branches${showAll ? '&all=1' : ''}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('branches-table').innerHTML = html;
                });
        }

        function deleteBranch(id) {
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

        function restoreBranch(id) {
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

        // User Management
        function openUserModal(user = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const title = document.getElementById('userModalTitle');
            const branchField = document.getElementById('user_branch_id').closest('.form-floating');
            
            if (user) {
                title.textContent = 'Edit User';
                document.getElementById('user_id').value = user.id;
                document.getElementById('user_username').value = user.username;
                document.getElementById('user_email').value = user.email;
                document.getElementById('user_role').value = user.role;
                document.getElementById('user_full_name').value = user.full_name;
                document.getElementById('user_phone').value = user.phone || '';
                document.getElementById('user_branch_id').value = user.branch_id || '';
                document.getElementById('user_password').required = false;
            } else {
                title.textContent = 'Add User';
                form.reset();
                document.getElementById('user_id').value = '';
                document.getElementById('user_password').required = true;
            }
            // Show/hide branch field based on role
            handleUserRoleChange();
        }

        // Show/hide branch field based on role
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

        function editUser(user) {
            openUserModal(user);
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function deleteUser(id) {
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
                        location.reload();
                    } else {
                        alert('Error deleting user');
                    }
                });
            }
        }

        // Toast for Users
        function showUsersAlert(message, type) {
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 fade" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 250px; margin-bottom: 0.5rem; opacity: 0; transition: opacity 0.5s;">
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
            const toastElem = document.getElementById(toastId);
            setTimeout(() => {
                toastElem.classList.add('show');
                toastElem.style.opacity = 1;
            }, 10);
            setTimeout(() => {
                toastElem.classList.remove('show');
                toastElem.classList.add('hide');
                toastElem.style.opacity = 0;
                setTimeout(() => {
                    toastElem.remove();
                }, 500);
            }, 2000);
        }

        // Add user row to table dynamically
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
            // Only append branch_id if role is branch_admin
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
                    // If adding, append the new user row
                    if (!isEdit) {
                        // Fetch the latest user (assume backend returns the new user or fetch via AJAX)
                        fetch('?ajax=latest_user')
                        .then(res => res.json())
                        .then(user => {
                            appendUserRow(user);
                        });
                    } else {
                        // For edit, reload for now (can be improved to update row in place)
                        location.reload();
                    }
                    // Close modal
                    const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                    if (userModal) userModal.hide();
                    document.getElementById('userForm').reset();
                    showUsersAlert('User saved successfully!', 'success');
                } else {
                    showUsersAlert('Error saving user', 'danger');
                }
            })
            .catch(() => {
                showUsersAlert('Error saving user', 'danger');
            });
        });

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
                document.getElementById('package_branch_id').value = package.branch_id;
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
                        location.reload();
                    } else {
                        alert('Error deleting package');
                    }
                });
            }
        }

        // Reports Functions
        function generateReport(type) {
            alert(`Generating ${type} report... This would typically download a PDF or Excel file.`);
        }

        function exportReport() {
            alert('Exporting report... This would download the current view as Excel/PDF.');
        }

        // Settings Functions
        document.getElementById('generalSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('General settings saved successfully!');
        });

        document.getElementById('systemSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('System settings saved successfully!');
        });

        document.getElementById('securitySettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Security settings saved successfully!');
        });

        document.getElementById('emailTemplateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Email template saved successfully!');
        });

        function createBackup() {
            if(confirm('Create database backup? This may take a few minutes.')) {
                alert('Backup created successfully! File: backup_' + new Date().toISOString().split('T')[0] + '.sql');
            }
        }

        function scheduleBackup() {
            alert('Auto backup scheduled for daily at 2:00 AM');
        }

        function clearCache() {
            if(confirm('Clear system cache?')) {
                alert('Cache cleared successfully!');
            }
        }

        function optimizeDatabase() {
            if(confirm('Optimize database? This may take a few minutes.')) {
                alert('Database optimized successfully!');
            }
        }

        function loadEmailTemplate(template) {
            // Remove active class from all items
            document.querySelectorAll('.list-group-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked item
            event.target.classList.add('active');
            
            // Load template content based on type
            const templates = {
                'booking_confirmation': {
                    subject: 'Booking Confirmation - TravelCo',
                    body: `Dear {customer_name},

Thank you for booking with TravelCo! Your booking has been confirmed.

Booking Details:
- Package: {package_name}
- Destination: {destination}
- Travel Date: {travel_date}
- Number of People: {people_count}
- Total Amount: {total_amount}

We look forward to serving you!

Best regards,
TravelCo Team`
                },
                'booking_cancellation': {
                    subject: 'Booking Cancellation - TravelCo',
                    body: `Dear {customer_name},

Your booking has been cancelled as requested.

Cancelled Booking Details:
- Package: {package_name}
- Booking ID: {booking_id}
- Refund Amount: {refund_amount}

The refund will be processed within 5-7 business days.

Best regards,
TravelCo Team`
                },
                'welcome_email': {
                    subject: 'Welcome to TravelCo!',
                    body: `Dear {customer_name},

Welcome to TravelCo! We're excited to have you as part of our travel community.

Explore our amazing packages and start planning your next adventure.

Best regards,
TravelCo Team`
                },
                'password_reset': {
                    subject: 'Password Reset - TravelCo',
                    body: `Dear {customer_name},

You have requested to reset your password.

Click the link below to reset your password:
{reset_link}

This link will expire in 24 hours.

Best regards,
TravelCo Team`
                }
            };
            
            if (templates[template]) {
                document.getElementById('email_subject').value = templates[template].subject;
                document.getElementById('email_body').value = templates[template].body;
            }
        }

        function previewEmail() {
            const subject = document.getElementById('email_subject').value;
            const body = document.getElementById('email_body').value;
            
            const previewWindow = window.open('', '_blank', 'width=600,height=400');
            previewWindow.document.write(`
                <html>
                    <head><title>Email Preview</title></head>
                    <body style="font-family: Arial, sans-serif; padding: 20px;">
                        <h3>Subject: ${subject}</h3>
                        <hr>
                        <div style="white-space: pre-line;">${body}</div>
                    </body>
                </html>
            `);
        }

        // Initialize Charts (using Chart.js - you would need to include the library)
        document.addEventListener('DOMContentLoaded', function() {
            // Restore last active section if available
            const activeSection = localStorage.getItem('activeSection');
            if (activeSection) {
                showSection(activeSection);
                localStorage.removeItem('activeSection');
            }
            // Revenue Chart (placeholder)
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                // This would initialize a real chart with Chart.js
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
        });

        // Booking Details Modal Function
        function viewBooking(booking) {
            document.getElementById('detail_booking_id').textContent = '#' + booking.id;
            document.getElementById('detail_customer_name').textContent = booking.customer_name;
            document.getElementById('detail_package_name').textContent = booking.package_name;
            document.getElementById('detail_branch_name').textContent = booking.branch_name;
            document.getElementById('detail_travel_date').textContent = booking.travel_date ? (new Date(booking.travel_date)).toLocaleDateString() : '';
            document.getElementById('detail_people').textContent = booking.number_of_people;
            document.getElementById('detail_amount').textContent = Number(booking.total_amount).toLocaleString();
            document.getElementById('detail_status').innerHTML = `<span class="badge bg-${booking.status === 'confirmed' ? 'success' : (booking.status === 'pending' ? 'warning' : 'danger')}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span>`;
            document.getElementById('detail_created_at').textContent = booking.created_at ? (new Date(booking.created_at)).toLocaleString() : '';
            new bootstrap.Modal(document.getElementById('bookingDetailsModal')).show();
        }
    </script>
</body>
</html>
