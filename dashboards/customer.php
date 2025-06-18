<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireRole('customer');

$customer_id = $_SESSION['user_id'];

// Get customer bookings with payment info
$bookings_query = "SELECT b.*, p.name as package_name, p.destination, p.image_url, p.duration_days, 
                   br.name as branch_name, py.payment_reference, py.status as payment_status
                   FROM bookings b 
                   JOIN packages p ON b.package_id = p.id 
                   JOIN branches br ON b.branch_id = br.id 
                   LEFT JOIN payments py ON b.id = py.booking_id
                   WHERE b.customer_id = ? 
                   ORDER BY b.created_at DESC";
$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->execute([$customer_id]);
$customer_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available packages
$packages_query = "SELECT p.*, br.name as branch_name FROM packages p 
                   JOIN branches br ON p.branch_id = br.id 
                   WHERE p.status = 'active' 
                   ORDER BY p.created_at DESC";
$packages_stmt = $db->prepare($packages_query);
$packages_stmt->execute();
$available_packages = $packages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
    SUM(CASE WHEN status = 'confirmed' OR status = 'completed' THEN total_amount ELSE 0 END) as total_spent
    FROM bookings WHERE customer_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$customer_id]);
$customer_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelNepal - Discover Amazing Places</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #2d3748;
            overflow-x: hidden;
        }

        /* Header */
        .app-header {
            background: white;
            padding: 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #e2e8f0;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .location-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .location-info i {
            color: #4299e1;
            font-size: 16px;
        }

        .location-text {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .search-container {
            position: relative;
        }

        .search-bar {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: none;
            border-radius: 20px;
            background: #f7fafc;
            font-size: 15px;
            color: #2d3748;
            transition: all 0.3s ease;
        }

        .search-bar:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .search-bar::placeholder {
            color: #a0aec0;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            padding: 0 16px 100px;
            max-width: 100%;
        }

        /* Welcome Section */
        .welcome-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 24px 20px;
            margin: 16px 0;
            text-align: center;
        }

        .welcome-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .welcome-subtitle {
            font-size: 15px;
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            font-weight: 500;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 24px 0 16px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }

        .see-more {
            color: #4299e1;
            font-size: 14px;
            text-decoration: none;
            font-weight: 500;
        }

        /* Trip Cards */
        .trips-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .trip-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }

        .trip-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            color: inherit;
        }

        .trip-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .trip-price {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            color: #2d3748;
        }

        .trip-info {
            padding: 16px;
        }

        .trip-name {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .trip-location {
            font-size: 13px;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 4px;
        }

        .trip-meta {
            font-size: 12px;
            color: #a0aec0;
        }

        /* Categories */
        .categories-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .category-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 12px;
            background: white;
            border-radius: 16px;
            text-decoration: none;
            color: #4a5568;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .category-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            color: #4299e1;
        }

        .category-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .category-beach { background: #fef5e7; color: #d69e2e; }
        .category-mountain { background: #f0fff4; color: #38a169; }
        .category-cultural { background: #fff5f5; color: #e53e3e; }
        .category-adventure { background: #edf2f7; color: #4a5568; }

        .category-label {
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }

        /* Bookings */
        .booking-item {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .booking-item:hover {
            transform: translateY(-1px);
        }

        .booking-content {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .booking-image {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }

        .booking-details {
            flex: 1;
        }

        .booking-name {
            font-size: 15px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .booking-meta {
            font-size: 12px;
            color: #718096;
            margin-bottom: 2px;
        }

        .booking-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }

        .status-confirmed { background: #e6fffa; color: #00b894; }
        .status-pending { background: #fef5e7; color: #d69e2e; }
        .status-completed { background: #f0fff4; color: #38a169; }
        .status-cancelled { background: #fed7d7; color: #e53e3e; }

        .booking-price {
            text-align: right;
            flex-shrink: 0;
        }

        .booking-amount {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 2px;
        }

        .booking-id {
            font-size: 10px;
            color: #a0aec0;
        }

        /* Profile Section */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin: 0 auto 16px;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .profile-role {
            font-size: 14px;
            color: #718096;
            margin-bottom: 24px;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 20px;
            padding: 12px 24px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 10px 24px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline:hover {
            border-color: #cbd5e0;
            background: #f7fafc;
            color: #4a5568;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 0 20px;
            border-top: 1px solid #e2e8f0;
            z-index: 1000;
        }

        .nav-container {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 500px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: #a0aec0;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 12px;
            min-width: 60px;
        }

        .nav-item.active {
            color: #4299e1;
            background: #ebf8ff;
        }

        .nav-item i {
            font-size: 18px;
        }

        .nav-item span {
            font-size: 11px;
            font-weight: 600;
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 100px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }

        /* Sections */
        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 20px;
            margin: 20px 0;
        }

        .empty-icon {
            font-size: 48px;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .empty-subtitle {
            font-size: 14px;
            color: #718096;
            margin-bottom: 24px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .app-header {
                padding: 12px 16px;
            }

            .header-top {
                margin-bottom: 12px;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .search-bar {
                padding: 12px 16px 12px 40px;
                font-size: 14px;
            }

            .welcome-message {
                padding: 20px 16px;
                margin: 12px 0;
            }

            .welcome-title {
                font-size: 20px;
            }

            .welcome-subtitle {
                font-size: 14px;
            }

            .stats-grid {
                gap: 10px;
                margin: 16px 0;
            }

            .stat-card {
                padding: 16px 12px;
            }

            .stat-number {
                font-size: 20px;
            }

            .trips-container {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .trip-image {
                height: 160px;
            }

            .categories-container {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .category-item {
                padding: 12px 8px;
            }

            .category-icon {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .category-label {
                font-size: 10px;
            }

            .booking-content {
                gap: 10px;
            }

            .booking-image {
                width: 50px;
                height: 50px;
            }

            .booking-name {
                font-size: 14px;
            }

            .booking-amount {
                font-size: 14px;
            }

            .profile-avatar {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .profile-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary,
            .btn-outline {
                width: 100%;
                max-width: 200px;
            }

            .fab {
                bottom: 90px;
                right: 16px;
                width: 50px;
                height: 50px;
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0 12px 100px;
            }

            .welcome-message {
                padding: 16px 12px;
            }

            .section-title {
                font-size: 16px;
            }

            .trip-card {
                border-radius: 16px;
            }

            .trip-image {
                height: 140px;
            }

            .trip-info {
                padding: 12px;
            }

            .categories-container {
                gap: 6px;
            }

            .category-item {
                padding: 10px 6px;
            }

            .booking-item {
                padding: 12px;
            }

            .profile-card {
                padding: 20px 16px;
            }
        }

        /* Hide scrollbars but keep functionality */
        .trips-container::-webkit-scrollbar {
            display: none;
        }

        .trips-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="app-header">
        <div class="header-top">
            <div class="location-info">
                <i class="fas fa-map-marker-alt"></i>
                <span class="location-text">Nepal</span>
            </div>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
        </div>
        
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-bar" placeholder="Search destinations..." id="globalSearch">
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Home Section -->
        <div id="home-section" class="section active">
            <!-- Welcome Message -->
            <div class="welcome-message">
                <div class="welcome-title">Welcome Back!</div>
                <div class="welcome-subtitle">Hi <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>, ready for your next adventure?</div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $customer_stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Trips</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $customer_stats['completed_trips']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $customer_stats['confirmed_bookings']; ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">Rs.<?php echo number_format($customer_stats['total_spent']); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>

            <!-- Popular Trips -->
            <div class="section-header">
                <div class="section-title">Popular Destinations</div>
                <a href="#" class="see-more" onclick="showSection('packages-section')">See all</a>
            </div>
            
            <div class="trips-container">
                <?php foreach (array_slice($available_packages, 0, 6) as $package): ?>
                <a href="customer-booking.php?package_id=<?php echo $package['id']; ?>" class="trip-card">
                    <div class="trip-image" style="background-image: url('<?php echo $package['image_url']; ?>')">
                        <div class="trip-price">Rs.<?php echo number_format($package['price']); ?></div>
                    </div>
                    <div class="trip-info">
                        <div class="trip-name"><?php echo $package['name']; ?></div>
                        <div class="trip-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo $package['destination']; ?>
                        </div>
                        <div class="trip-meta"><?php echo $package['duration_days']; ?> Days</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Categories -->
            <div class="section-header">
                <div class="section-title">Browse by Category</div>
            </div>
            
            <div class="categories-container">
                <a href="#" class="category-item" onclick="filterPackages('beach')">
                    <div class="category-icon category-beach">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                    <span class="category-label">Beach</span>
                </a>
                <a href="#" class="category-item" onclick="filterPackages('mountain')">
                    <div class="category-icon category-mountain">
                        <i class="fas fa-mountain"></i>
                    </div>
                    <span class="category-label">Mountain</span>
                </a>
                <a href="#" class="category-item" onclick="filterPackages('cultural')">
                    <div class="category-icon category-cultural">
                        <i class="fas fa-landmark"></i>
                    </div>
                    <span class="category-label">Cultural</span>
                </a>
                <a href="#" class="category-item" onclick="filterPackages('adventure')">
                    <div class="category-icon category-adventure">
                        <i class="fas fa-hiking"></i>
                    </div>
                    <span class="category-label">Adventure</span>
                </a>
            </div>
        </div>

        <!-- Packages Section -->
        <div id="packages-section" class="section">
            <div class="section-header">
                <div class="section-title">All Destinations</div>
            </div>
            
            <div class="trips-container" id="packagesContainer">
                <?php foreach ($available_packages as $package): ?>
                <div class="package-item">
                    <a href="customer-booking.php?package_id=<?php echo $package['id']; ?>" class="trip-card">
                        <div class="trip-image" style="background-image: url('<?php echo $package['image_url']; ?>')">
                            <div class="trip-price">Rs.<?php echo number_format($package['price']); ?></div>
                        </div>
                        <div class="trip-info">
                            <div class="trip-name"><?php echo $package['name']; ?></div>
                            <div class="trip-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo $package['destination']; ?>
                            </div>
                            <div class="trip-meta"><?php echo $package['duration_days']; ?> Days • by <?php echo $package['branch_name']; ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bookings Section -->
        <div id="bookings-section" class="section">
            <div class="section-header">
                <div class="section-title">My Bookings</div>
            </div>
            
            <?php if (empty($customer_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <div class="empty-title">No Bookings Yet</div>
                    <div class="empty-subtitle">Start exploring destinations and make your first booking!</div>
                    <button class="btn-primary" onclick="showSection('packages-section')">
                        Browse Destinations
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($customer_bookings as $booking): ?>
                <div class="booking-item">
                    <div class="booking-content">
                        <div class="booking-image" style="background-image: url('<?php echo $booking['image_url']; ?>')"></div>
                        <div class="booking-details">
                            <div class="booking-name"><?php echo $booking['package_name']; ?></div>
                            <div class="booking-meta">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $booking['destination']; ?>
                            </div>
                            <div class="booking-meta">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['travel_date'])); ?>
                            </div>
                            <div class="booking-meta">
                                <i class="fas fa-users"></i> <?php echo $booking['number_of_people']; ?> People
                            </div>
                            <div class="booking-status status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </div>
                        </div>
                        <div class="booking-price">
                            <div class="booking-amount">Rs.<?php echo number_format($booking['total_amount']); ?></div>
                            <div class="booking-id">#<?php echo $booking['id']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="section">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="profile-role">Travel Explorer</div>
                
                <div class="profile-actions">
                    <button class="btn-primary">Edit Profile</button>
                    <a href="../config/logout.php" class="btn-outline">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fab" onclick="toggleChatbot()">
        <i class="fas fa-robot"></i>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-container">
            <a href="#" class="nav-item active" onclick="showSection('home-section')">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('packages-section')">
                <i class="fas fa-compass"></i>
                <span>Explore</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('bookings-section')">
                <i class="fas fa-calendar-check"></i>
                <span>Bookings</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('profile-section')">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionId).classList.add('active');
            
            // Update navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            event.target.closest('.nav-item').classList.add('active');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function filterPackages(category) {
            showSection('packages-section');
            
            // Simple filter simulation
            const packages = document.querySelectorAll('.package-item');
            packages.forEach(package => {
                if (category === 'all') {
                    package.style.display = 'block';
                } else {
                    // Simple random filter for demo
                    package.style.display = Math.random() > 0.5 ? 'block' : 'none';
                }
            });
        }
        
        function toggleChatbot() {
            if (window.advancedChatbot) {
                window.advancedChatbot.toggleChatbot();
            }
        }
        
        // Global search functionality
        document.getElementById('globalSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            if (searchTerm.length > 0) {
                showSection('packages-section');
                
                const packages = document.querySelectorAll('.package-item');
                packages.forEach(package => {
                    const packageName = package.querySelector('.trip-name').textContent.toLowerCase();
                    const destination = package.querySelector('.trip-location').textContent.toLowerCase();
                    
                    if (packageName.includes(searchTerm) || destination.includes(searchTerm)) {
                        package.style.display = 'block';
                    } else {
                        package.style.display = 'none';
                    }
                });
            }
        });
        
        // Touch-friendly interactions
        document.addEventListener('touchstart', function() {}, {passive: true});
        
        // Prevent zoom on double tap for better mobile experience
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
    
    <?php include '../components/advanced-chatbot-widget.php'; ?>
    <script>
    // Enhanced chatbot integration for customer dashboard
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize advanced chatbot with customer context
        window.advancedChatbot = new AdvancedChatbot();
        
        // Pass customer data to chatbot
        advancedChatbot.userProfile = {
            customer_id: <?php echo $customer_id; ?>,
            username: '<?php echo $_SESSION['username']; ?>',
            full_name: '<?php echo $_SESSION['full_name']; ?>',
            total_bookings: <?php echo $customer_stats['total_bookings']; ?>,
            completed_trips: <?php echo $customer_stats['completed_trips']; ?>,
            total_spent: <?php echo $customer_stats['total_spent']; ?>,
            voice_enabled: true,
            preferred_language: 'mixed'
        };
        
        // Auto-show chatbot for first-time visitors
        if (!localStorage.getItem("chatbot_visited_customer")) {
            setTimeout(() => {
                advancedChatbot.toggleChatbot();
                localStorage.setItem("chatbot_visited_customer", "true");
                
                // Send welcome message with customer context
                setTimeout(() => {
                    advancedChatbot.sendContextualWelcome();
                }, 1000);
            }, 3000);
        }
    });
    </script>
</body>
</html>
