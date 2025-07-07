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
                   WHERE p.status = 'active' AND br.status = 'active' 
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

// Get reviews left by this customer
$reviews_stmt = $db->prepare("SELECT booking_id FROM reviews WHERE customer_id = ?");
$reviews_stmt->execute([$customer_id]);
$customer_reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
$reviewed_bookings = array_column($customer_reviews, 'booking_id');

// Get detailed customer information
$customer_query = "SELECT u.*, u.created_at as joined_date 
                   FROM users u 
                   WHERE u.id = ? AND u.role = 'customer'";
$customer_stmt = $db->prepare($customer_query);
$customer_stmt->execute([$customer_id]);
$customer_details = $customer_stmt->fetch(PDO::FETCH_ASSOC);

// Get customer preferences and settings
$preferences_query = "SELECT * FROM customer_preferences WHERE user_id = ?";
$preferences_stmt = $db->prepare($preferences_query);
$preferences_stmt->execute([$customer_id]);
$customer_preferences = $preferences_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$activity_query = "SELECT 'booking' as type, b.created_at, p.name as title, b.status 
                   FROM bookings b 
                   JOIN packages p ON b.package_id = p.id 
                   WHERE b.customer_id = ? 
                   UNION ALL 
                   SELECT 'review' as type, r.created_at, p.name as title, 'reviewed' as status 
                   FROM reviews r 
                   JOIN bookings b ON r.booking_id = b.id 
                   JOIN packages p ON b.package_id = p.id 
                   WHERE r.customer_id = ? 
                   ORDER BY created_at DESC LIMIT 10";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->execute([$customer_id, $customer_id]);
$recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
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

        .category-beach {
            background: #fef5e7;
            color: #d69e2e;
        }

        .category-mountain {
            background: #f0fff4;
            color: #38a169;
        }

        .category-cultural {
            background: #fff5f5;
            color: #e53e3e;
        }

        .category-adventure {
            background: #edf2f7;
            color: #4a5568;
        }

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

        .status-confirmed {
            background: #e6fffa;
            color: #00b894;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #e53e3e;
        }

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
            display: inline-button;
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
            display: inline-button;
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

        .booking-filter-btn.active {
            background: #667eea;
            color: #fff;
            border-color: #667eea;
        }

        /* Enhanced Profile Styles */
        .profile-details {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .profile-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .profile-detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
        }

        .profile-sections {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .profile-section-btn {
            flex: 1;
            padding: 12px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .profile-section-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .profile-subsection {
            display: none;
        }

        .profile-subsection.active {
            display: block;
        }

        .settings-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .settings-title {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        .settings-description {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background: #cbd5e0;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .toggle-switch.active {
            background: #667eea;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }

        .toggle-switch.active::after {
            transform: translateX(26px);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
        }

        .activity-booking {
            background: #667eea;
        }

        .activity-review {
            background: #10b981;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 12px;
            color: #64748b;
        }

        .activity-status {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 600;
        }

        .status-confirmed {
            background: #e6fffa;
            color: #00b894;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
        }

        .status-completed {
            background: #f0fff4;
            color: #38a169;
        }

        .status-reviewed {
            background: #f0f9ff;
            color: #0ea5e9;
        }

        .edit-profile-form {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-cancel {
            background: transparent;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 24px;
            color: #64748b;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 12px;
        }

        .btn-cancel:hover {
            border-color: #cbd5e0;
            background: #f8fafc;
        }

        .profile-actions-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .achievement-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f0f9ff;
            color: #0ea5e9;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 4px;
        }

        .achievement-badge i {
            font-size: 10px;
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background: #1a202c;
            color: #e2e8f0;
        }

        body.dark-mode .app-header {
            background: #2d3748;
            border-bottom-color: #4a5568;
        }

        body.dark-mode .search-bar {
            background: #4a5568;
            color: #e2e8f0;
        }

        body.dark-mode .search-bar:focus {
            background: #2d3748;
        }

        body.dark-mode .search-bar::placeholder {
            color: #a0aec0;
        }

        body.dark-mode .stat-card,
        body.dark-mode .trip-card,
        body.dark-mode .category-item,
        body.dark-mode .booking-item,
        body.dark-mode .profile-card,
        body.dark-mode .profile-details,
        body.dark-mode .settings-item,
        body.dark-mode .edit-profile-form {
            background: #2d3748;
            color: #e2e8f0;
        }

        body.dark-mode .stat-number,
        body.dark-mode .trip-name,
        body.dark-mode .booking-name,
        body.dark-mode .profile-name,
        body.dark-mode .detail-value,
        body.dark-mode .settings-title,
        body.dark-mode .activity-title {
            color: #f7fafc;
        }

        body.dark-mode .stat-label,
        body.dark-mode .trip-location,
        body.dark-mode .trip-meta,
        body.dark-mode .booking-meta,
        body.dark-mode .profile-role,
        body.dark-mode .detail-label,
        body.dark-mode .settings-description,
        body.dark-mode .activity-time {
            color: #a0aec0;
        }

        body.dark-mode .bottom-nav {
            background: #2d3748;
            border-top-color: #4a5568;
        }

        body.dark-mode .nav-item {
            color: #a0aec0;
        }

        body.dark-mode .nav-item.active {
            color: #4299e1;
            background: #2c5282;
        }

        body.dark-mode .form-input {
            background: #4a5568;
            border-color: #718096;
            color: #e2e8f0;
        }

        body.dark-mode .form-input:focus {
            border-color: #4299e1;
        }

        body.dark-mode .form-input::placeholder {
            color: #a0aec0;
        }

        body.dark-mode .profile-detail-item,
        body.dark-mode .activity-item {
            border-bottom-color: #4a5568;
        }

        body.dark-mode .empty-state {
            background: #2d3748;
            color: #e2e8f0;
        }

        body.dark-mode .empty-icon {
            color: #4a5568;
        }

        body.dark-mode .profile-details h4 {
            color: #f7fafc !important;
        }

        body.dark-mode .modal-title#editProfileModalLabel {
            color: #23272f !important;
        }

        .avatar-upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 18px;
            border: 2px solid #fff;
            z-index: 2;
        }

        #profile-avatar-upload-area:hover .avatar-upload-overlay,
        #profile-avatar-upload-area:focus-within .avatar-upload-overlay {
            opacity: 1;
        }

        #profile-avatar-upload-area {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div id="location-search-bar">
        <div class="header-top">
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
                    <div class="stat-number"><?php echo $customer_stats['completed_trips'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $customer_stats['confirmed_bookings'] ?? 0; ?></div>
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

            <!-- Booking Filters -->
            <div class="booking-filters" style="margin-bottom: 16px; display: flex; gap: 10px;">
                <button class="btn-outline booking-filter-btn active" data-status="all">All</button>
                <button class="btn-outline booking-filter-btn" data-status="pending">Pending</button>
                <button class="btn-outline booking-filter-btn" data-status="completed">Completed</button>
                <button class="btn-outline booking-filter-btn" data-status="cancelled">Cancelled</button>
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
                    <div class="booking-item" data-status="<?php echo strtolower($booking['status']); ?>">
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
                            <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                <div>
                                    <button type="button" class="btn-outline btn-cancel-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                        Cancel
                                    </button>
                                </div>
                            <?php endif; ?>
                            <?php if ($booking['status'] === 'completed' && !in_array($booking['id'], $reviewed_bookings)): ?>
                                <div>
                                    <button type="button" class="btn-outline btn-leave-review" data-booking-id="<?php echo $booking['id']; ?>">
                                        Leave Review
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="section">
            <!-- Profile Header -->
            <div class="profile-card">
                <div class="profile-avatar position-relative" id="profile-avatar-upload-area">
                    <?php if (!empty($customer_details['profile_pic'])): ?>
                        <img id="profile-avatar-img" src="/Multi-Branch%20TMS/uploads/profile_pics/<?php echo ltrim(htmlspecialchars($customer_details['profile_pic']), '/'); ?>?v=<?php echo time(); ?>" alt="Profile Picture" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <span id="profile-avatar-initial-text"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></span>
                    <?php endif; ?>
                    <label for="profile-avatar-file" class="avatar-upload-overlay" style="cursor:pointer;">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile-avatar-file" name="profile_pic" accept="image/jpeg,image/png,image/gif" style="display:none;">
                </div>
                <div class="profile-name" id="profile-header-full-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="profile-role">
                    <?php
                    $total_trips = $customer_stats['completed_trips'] ?? 0;
                    if ($total_trips >= 10) {
                        echo "Travel Expert";
                    } elseif ($total_trips >= 5) {
                        echo "Adventure Seeker";
                    } elseif ($total_trips >= 2) {
                        echo "Travel Explorer";
                    } else {
                        echo "New Traveler";
                    }
                    ?>
                </div>

                <!-- Achievement Badges -->
                <div style="margin: 16px 0;">
                    <?php if ($customer_stats['total_bookings'] >= 1): ?>
                        <span class="achievement-badge">
                            <i class="fas fa-star"></i> First Trip
                        </span>
                    <?php endif; ?>
                    <?php if ($customer_stats['completed_trips'] >= 5): ?>
                        <span class="achievement-badge">
                            <i class="fas fa-trophy"></i> Explorer
                        </span>
                    <?php endif; ?>
                    <?php if ($customer_stats['total_spent'] >= 50000): ?>
                        <span class="achievement-badge">
                            <i class="fas fa-crown"></i> VIP Traveler
                        </span>
                    <?php endif; ?>
                </div>

                <div class="profile-actions-row">
                    <button type="button" class="btn-primary" id="editProfileBtn">Edit Profile</button>
                    <a href="../config/logout.php" class="btn-outline">Logout</a>
                </div>
            </div>

            <!-- Profile Navigation -->
            <div class="profile-sections">
                <button type="button" class="profile-section-btn active" onclick="showProfileSubsection('details')">
                    <i class="fas fa-user"></i> Details
                </button>
                <button type="button" class="profile-section-btn" onclick="showProfileSubsection('activity')">
                    <i class="fas fa-history"></i> Activity
                </button>
                <button type="button" class="profile-section-btn" onclick="showProfileSubsection('settings')">
                    <i class="fas fa-cog"></i> Settings
                </button>
            </div>

            <!-- Profile Details Subsection -->
            <div id="profile-details" class="profile-subsection active">
                <div class="profile-details">
                    <div class="profile-detail-item">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value" id="profile-full-name"><?php echo $customer_details['full_name'] ?? $_SESSION['full_name']; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value" id="profile-email"><?php echo $customer_details['email'] ?? $_SESSION['email']; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value" id="profile-phone"><?php echo $customer_details['phone'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Address</span>
                        <span class="detail-value" id="profile-address"><?php echo $customer_details['address'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Date of Birth</span>
                        <span class="detail-value" id="profile-date-of-birth"><?php echo $customer_details['date_of_birth'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Emergency Contact</span>
                        <span class="detail-value" id="profile-emergency-contact"><?php echo $customer_details['emergency_contact'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Travel Preferences</span>
                        <span class="detail-value" id="profile-travel-preferences"><?php echo $customer_details['travel_preferences'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Date Joined</span>
                        <span class="detail-value"><?php echo isset($customer_details['joined_date']) ? date('M d, Y', strtotime($customer_details['joined_date'])) : 'Not available'; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Total Trips</span>
                        <span class="detail-value"><?php echo $customer_stats['total_bookings'] ?? 0; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Completed Trips</span>
                        <span class="detail-value"><?php echo $customer_stats['completed_trips'] ?? 0; ?></span>
                    </div>
                    <div class="profile-detail-item">
                        <span class="detail-label">Total Spent</span>
                        <span class="detail-value">Rs.<?php echo number_format($customer_stats['total_spent'] ?? 0); ?></span>
                    </div>
                </div>
            </div>

            <!-- Activity Feed Subsection -->
            <div id="profile-activity" class="profile-subsection">
                <div class="profile-details">
                    <h4 style="margin-bottom: 16px; color: #1e293b;">Recent Activity</h4>
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clock empty-icon"></i>
                            <div class="empty-title">No Recent Activity</div>
                            <div class="empty-subtitle">Start booking trips to see your activity here!</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon activity-<?php echo $activity['type']; ?>">
                                    <i class="fas fa-<?php echo $activity['type'] === 'booking' ? 'calendar-check' : 'star'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo $activity['title']; ?></div>
                                    <div class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></div>
                                </div>
                                <div class="activity-status status-<?php echo $activity['status']; ?>">
                                    <?php echo ucfirst($activity['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Subsection -->
            <div id="profile-settings" class="profile-subsection">
                <div class="settings-item">
                    <div class="settings-header">
                        <div class="settings-title">Dark Mode</div>
                        <div class="toggle-switch" onclick="toggleDarkMode.call(this)"></div>
                    </div>
                    <div class="settings-description">Switch to dark theme for better viewing</div>
                </div>

                <div class="settings-item">
                    <div class="settings-header">
                        <div class="settings-title">Language</div>
                        <select class="form-input" style="width: auto; padding: 8px 12px; font-size: 13px;" onchange="changeLanguage(this.value)">
                            <option value="en" <?php echo ($customer_preferences['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ne" <?php echo ($customer_preferences['language'] ?? 'en') === 'ne' ? 'selected' : ''; ?>>नेपाली</option>
                        </select>
                    </div>
                    <div class="settings-description">Choose your preferred language</div>
                </div>

                <div class="settings-item">
                    <div class="settings-header">
                        <div class="settings-title">Change Password</div>
                        <button type="button" class="btn btn-primary btn-sm" id="openChangePasswordModal">Change</button>
                    </div>
                    <div class="settings-description">Update your account password</div>
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
            <a href="#" class="nav-item active" onclick="showSection('home-section', this)">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('packages-section', this)">
                <i class="fas fa-compass"></i>
                <span>Explore</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('bookings-section', this)">
                <i class="fas fa-calendar-check"></i>
                <span>Bookings</span>
            </a>
            <a href="#" class="nav-item" onclick="showSection('profile-section', this)">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="reviewForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Leave a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reviewBookingId" name="booking_id">
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating</label>
                        <select id="rating" name="rating" class="form-select" required>
                            <option value="">Select rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Terrible</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review" class="form-label">Review</label>
                        <textarea id="review" name="review" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editProfileForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-full-name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="edit-full-name" name="full_name" required value="<?php echo htmlspecialchars($customer_details['full_name'] ?? $_SESSION['full_name']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="edit-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit-email" name="email" required value="<?php echo htmlspecialchars($customer_details['email'] ?? $_SESSION['email']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="edit-phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="edit-phone" name="phone" value="<?php echo htmlspecialchars($customer_details['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                    </div>

                    <div class="mb-3">
                        <label for="edit-address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit-address" name="address"><?php echo htmlspecialchars($customer_details['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit-date-of-birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="edit-date-of-birth" name="date_of_birth" value="<?php echo htmlspecialchars($customer_details['date_of_birth'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="edit-emergency-contact" class="form-label">Emergency Contact</label>
                        <input type="tel" class="form-control" id="edit-emergency-contact" name="emergency_contact" value="<?php echo htmlspecialchars($customer_details['emergency_contact'] ?? ''); ?>" placeholder="Emergency contact number">
                    </div>

                    <div class="mb-3">
                        <label for="edit-travel-preferences" class="form-label">Travel Preferences</label>
                        <textarea class="form-control" id="edit-travel-preferences" name="travel_preferences"><?php echo htmlspecialchars($customer_details['travel_preferences'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="changePasswordForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current-password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current-password" name="current_password" autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current-password" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new-password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new-password" name="new_password" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new-password" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm-password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm-password" name="confirm_password" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm-password" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId, navItem = null) {
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

            if (navItem) {
                navItem.classList.add('active');
            }

            // Scroll to top
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            // Update the URL hash
            window.location.hash = sectionId;
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
        document.addEventListener('touchstart', function() {}, {
            passive: true
        });

        // Prevent zoom on double tap for better mobile experience
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-cancel-booking').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookingId = this.getAttribute('data-booking-id');
                    if (confirm('Are you sure you want to cancel this booking?')) {
                        fetch('../api/cancel-booking.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    booking_id: bookingId
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Booking cancelled successfully.');
                                    window.location.hash = 'bookings-section';
                                    location.reload();
                                } else {
                                    alert('Failed to cancel booking: ' + (data.error || 'Unknown error'));
                                }
                            });
                    }
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show modal on button click
            document.querySelectorAll('.btn-leave-review').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('reviewBookingId').value = this.getAttribute('data-booking-id');
                    new bootstrap.Modal(document.getElementById('reviewModal')).show();
                });
            });

            // Handle review form submission
            document.getElementById('reviewForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = {
                    booking_id: document.getElementById('reviewBookingId').value,
                    rating: document.getElementById('rating').value,
                    review: document.getElementById('review').value
                };
                fetch('../api/submit-review.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Thank you for your review!');
                            location.reload();
                        } else {
                            alert('Failed to submit review: ' + (data.error || 'Unknown error'));
                        }
                    });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Booking filter logic
            document.querySelectorAll('.booking-filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    // Remove 'active' from all buttons
                    document.querySelectorAll('.booking-filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    var status = this.getAttribute('data-status');
                    document.querySelectorAll('.booking-item').forEach(function(item) {
                        if (status === 'all' || item.getAttribute('data-status') === status) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show section from hash on page load
            if (window.location.hash) {
                var sectionId = window.location.hash.substring(1);
                if (document.getElementById(sectionId)) {
                    showSection(sectionId);
                }
            }
        });
    </script>
    <script>
        // Profile Management Functions
        function cleanValue(val) {
            return (val === 'Not provided' || val === 'Not available' || val === 'dd/mm/yyyy') ? '' : val;
        }

        // Replace the showProfileSubsection definition with this:
        window.showProfileSubsection = function(subsection) {
            if (subsection === 'edit') {
                populateEditProfileForm();
            }
            // Hide all profile subsections
            document.querySelectorAll('.profile-subsection').forEach(sub => {
                sub.classList.remove('active');
            });
            // Show selected subsection
            document.getElementById('profile-' + subsection).classList.add('active');
            // Update navigation buttons
            document.querySelectorAll('.profile-section-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Find and activate the corresponding button
            const buttonMap = {
                'details': 0,
                'activity': 1,
                'settings': 2,
                'edit': 0 // Edit uses the details button
            };
            if (subsection !== 'edit') {
                document.querySelectorAll('.profile-section-btn')[buttonMap[subsection]].classList.add('active');
            }
        };
    </script>
    <script>
        window.toggleDarkMode = function() {
            const toggle = this;
            toggle.classList.toggle('active');
            if (toggle.classList.contains('active')) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            }
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sync dark mode toggle and body class
            const darkModeOn = localStorage.getItem('darkMode') === 'true';
            if (darkModeOn) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
            // Set the toggle visual state
            document.querySelectorAll('.settings-item').forEach(function(item) {
                if (item.textContent.includes('Dark Mode')) {
                    var toggle = item.querySelector('.toggle-switch');
                    if (toggle) {
                        if (darkModeOn) {
                            toggle.classList.add('active');
                        } else {
                            toggle.classList.remove('active');
                        }
                    }
                }
            });
        });
    </script>
    <script>
        function updateLocationSearchBarVisibility() {
            const bar = document.getElementById('location-search-bar');
            if (!bar) return;
            // Get the currently active section
            const activeSection = document.querySelector('.section.active');
            if (!activeSection) return;
            const id = activeSection.id;
            // Show only on home-section and packages-section (explore)
            if (id === 'home-section' || id === 'packages-section') {
                bar.style.display = '';
            } else {
                bar.style.display = 'none';
            }
        }
        // Call this function after any section switch
        // (nav-item click triggers section switch)
        document.querySelectorAll('.nav-item').forEach(function(item) {
            item.addEventListener('click', function() {
                setTimeout(updateLocationSearchBarVisibility, 10);
            });
        });
        // Also call on page load
        document.addEventListener('DOMContentLoaded', updateLocationSearchBarVisibility);
    </script>
    <script>
        function updateBookingTitleColor() {
            var bookingTitle = document.querySelector('#bookings-section .section-title');
            if (bookingTitle) {
                if (document.body.classList.contains('dark-mode')) {
                    bookingTitle.style.color = '#fff';
                    bookingTitle.style.opacity = '1';
                    bookingTitle.style.fontWeight = '700';
                } else {
                    bookingTitle.style.color = '';
                    bookingTitle.style.opacity = '';
                    bookingTitle.style.fontWeight = '';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateBookingTitleColor();

            // Listen for dark mode toggle
            document.querySelectorAll('.toggle-switch').forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    setTimeout(updateBookingTitleColor, 10);
                });
            });

            // Edit Profile Modal open
            const editProfileBtn = document.getElementById('editProfileBtn');
            if (editProfileBtn) {
                editProfileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = document.getElementById('editProfileModal');
                    if (modal) {
                        new bootstrap.Modal(modal).show();
                    }
                });
            }

            // Edit Profile AJAX submit (with file upload)
            const editProfileForm = document.getElementById('editProfileForm');
            if (editProfileForm) {
                editProfileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    // Collect values without validation
                    const fullName = document.getElementById('edit-full-name').value;
                    const email = document.getElementById('edit-email').value;
                    const phone = document.getElementById('edit-phone').value;
                    const address = document.getElementById('edit-address').value;
                    const dateOfBirth = document.getElementById('edit-date-of-birth').value;
                    const emergencyContact = document.getElementById('edit-emergency-contact').value;
                    const travelPreferences = document.getElementById('edit-travel-preferences').value;
                    const formData = new FormData();
                    formData.append('full_name', fullName);
                    formData.append('email', email);
                    formData.append('phone', phone);
                    formData.append('address', address);
                    formData.append('date_of_birth', dateOfBirth);
                    formData.append('emergency_contact', emergencyContact);
                    formData.append('travel_preferences', travelPreferences);
                    fetch('../api/update-profile.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update profile fields on the page
                                document.getElementById('profile-full-name').textContent = fullName;
                                document.getElementById('profile-header-full-name').textContent = fullName;
                                document.getElementById('profile-email').textContent = email;
                                document.getElementById('profile-phone').textContent = phone || 'Not provided';
                                document.getElementById('profile-address').textContent = address || 'Not provided';
                                document.getElementById('profile-date-of-birth').textContent = dateOfBirth || 'Not provided';
                                document.getElementById('profile-emergency-contact').textContent = emergencyContact || 'Not provided';
                                document.getElementById('profile-travel-preferences').textContent = travelPreferences || 'Not provided';
                                showNotification('Profile updated successfully!', 'success');
                                bootstrap.Modal.getInstance(document.getElementById('editProfileModal')).hide();
                            } else {
                                showNotification(data.error || 'Failed to update profile', 'error');
                            }
                        })
                        .catch(() => showNotification('Failed to update profile', 'error'));
                });
            }
        });
    </script>
    <script>
        window.showNotification = function(message, type = 'success') {
            // Remove any existing notification
            const existing = document.getElementById('custom-slide-notification');
            if (existing) existing.remove();

            // Create notification element
            const notification = document.createElement('div');
            notification.id = 'custom-slide-notification';
            notification.className = `slide-notification ${type}`;
            notification.innerHTML = `
        <span>${message}</span>
        <button type="button" class="close-btn" onclick="this.parentElement.remove()">×</button>
      `;
            document.body.appendChild(notification);

            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
        };

        // Profile avatar upload functionality
        document.getElementById('profile-avatar-file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('profile_pic', file);

                fetch('../api/upload-profile-pic.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the avatar image
                        const avatarImg = document.getElementById('profile-avatar-img');
                        const avatarInitial = document.getElementById('profile-avatar-initial-text');
                        
                        if (avatarImg) {
                            avatarImg.src = '../' + data.url + '?v=' + new Date().getTime();
                        } else {
                            // Create new image element if it doesn't exist
                            const newImg = document.createElement('img');
                            newImg.id = 'profile-avatar-img';
                            newImg.src = '../' + data.url + '?v=' + new Date().getTime();
                            newImg.alt = 'Profile Picture';
                            newImg.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                            
                            const avatarArea = document.getElementById('profile-avatar-upload-area');
                            avatarArea.innerHTML = '';
                            avatarArea.appendChild(newImg);
                            avatarArea.appendChild(document.querySelector('.avatar-upload-overlay'));
                        }
                        
                        if (avatarInitial) {
                            avatarInitial.style.display = 'none';
                        }
                        
                        showNotification('Profile picture updated successfully!', 'success');
                    } else {
                        showNotification(data.error || 'Failed to upload profile picture', 'error');
                    }
                })
                .catch(() => showNotification('Failed to upload profile picture', 'error'));
            }
        });

        // Change Password Modal
        document.getElementById('openChangePasswordModal').addEventListener('click', function() {
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        });

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Change Password Form
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('New passwords do not match', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                showNotification('New password must be at least 6 characters long', 'error');
                return;
            }
            
            fetch('../api/update-profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.logout) {
                        showNotification('Password changed successfully! Please log in again.', 'success');
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 1500);
                    } else {
                        showNotification('Password changed successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                        document.getElementById('changePasswordForm').reset();
                    }
                } else {
                    showNotification(data.error || 'Failed to change password', 'error');
                }
            })
            .catch(() => showNotification('Failed to change password', 'error'));
        });

        // Language change functionality
        window.changeLanguage = function(language) {
            fetch('../api/update-preference.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    language: language
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Language preference updated!', 'success');
                } else {
                    showNotification('Failed to update language preference', 'error');
                }
            })
            .catch(() => showNotification('Failed to update language preference', 'error'));
        };
    </script>

    <style>
        /* Notification Styles */
        .slide-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        }

        .slide-notification.success {
            border-left: 4px solid #10b981;
        }

        .slide-notification.error {
            border-left: 4px solid #ef4444;
        }

        .slide-notification span {
            flex: 1;
            font-size: 14px;
            color: #374151;
        }

        .slide-notification .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            color: #9ca3af;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .slide-notification .close-btn:hover {
            color: #6b7280;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Dark mode notification styles */
        body.dark-mode .slide-notification {
            background: #2d3748;
            color: #e2e8f0;
        }

        body.dark-mode .slide-notification span {
            color: #e2e8f0;
        }

        body.dark-mode .slide-notification .close-btn {
            color: #a0aec0;
        }

        body.dark-mode .slide-notification .close-btn:hover {
            color: #cbd5e0;
        }
    </style>
</body>
</html>
