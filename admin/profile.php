<?php
// admin/profile.php - User Profile Management
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/functions.php";

// Get MySQLi connection
global $conn;

// Require login
require_admin_login();

// Get current user
$current_user = get_current_admin($conn);
$user_id = $current_user['id'];

// Get counts for sidebar badges
$total_videos_draft = 0;
$count_videos = mysqli_query($conn, "SELECT COUNT(*) as count FROM videos WHERE is_published = 0");
if ($count_videos) {
    $total_videos_draft = mysqli_fetch_assoc($count_videos)['count'];
}

$total_blog_draft = 0;
$count_blog = mysqli_query($conn, "SELECT COUNT(*) as count FROM blog_posts WHERE is_published = 0");
if ($count_blog) {
    $total_blog_draft = mysqli_fetch_assoc($count_blog)['count'];
}

$total_news_draft = 0;
$count_news = mysqli_query($conn, "SELECT COUNT(*) as count FROM news WHERE is_published = 0");
if ($count_news) {
    $total_news_draft = mysqli_fetch_assoc($count_news)['count'];
}

$total_research_draft = 0;
$count_research = mysqli_query($conn, "SELECT COUNT(*) as count FROM research WHERE is_published = 0");
if ($count_research) {
    $total_research_draft = mysqli_fetch_assoc($count_research)['count'];
}

$total_creator_draft = 0;
$count_creator = mysqli_query($conn, "SELECT COUNT(*) as count FROM creator WHERE is_published = 0");
if ($count_creator) {
    $total_creator_draft = mysqli_fetch_assoc($count_creator)['count'];
}

$total_team_inactive = 0;
$count_team = mysqli_query($conn, "SELECT COUNT(*) as count FROM team_members WHERE is_active = 0");
if ($count_team) {
    $total_team_inactive = mysqli_fetch_assoc($count_team)['count'];
}

$unread_contacts = 0;
$count_contacts = mysqli_query($conn, "SELECT COUNT(*) as count FROM contact_submissions WHERE is_read = 0");
if ($count_contacts) {
    $unread_contacts = mysqli_fetch_assoc($count_contacts)['count'];
}

$total_subscribers = 0;
$count_subs = mysqli_query($conn, "SELECT COUNT(*) as count FROM subscribers");
if ($count_subs) {
    $total_subscribers = mysqli_fetch_assoc($count_subs)['count'];
}

// Define upload_avatar function
if (!function_exists('upload_avatar')) {
    function upload_avatar($file) {
        $target_dir = __DIR__ . "/../uploads/avatars/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Check if file was uploaded
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return ['success' => false, 'error' => 'Only JPG, PNG, GIF, and WebP images are allowed.'];
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Image size must be less than 2MB.'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . time() . '_' . uniqid() . '.' . $extension;
        $target_file = $target_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return ['success' => true, 'filename' => $filename];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file.'];
        }
    }
}

// Get or create user profile
$profile = null;
$check_profile = mysqli_query($conn, "SELECT * FROM admin_profiles WHERE user_id = $user_id");
if (mysqli_num_rows($check_profile) > 0) {
    $profile = mysqli_fetch_assoc($check_profile);
} else {
    // Create profile if it doesn't exist
    mysqli_query($conn, "INSERT INTO admin_profiles (user_id) VALUES ($user_id)");
    $profile = [
        'user_id' => $user_id,
        'avatar' => null,
        'phone' => null,
        'bio' => null,
        'department' => null,
        'position' => null,
        'social_facebook' => null,
        'social_twitter' => null,
        'social_linkedin' => null,
        'social_instagram' => null,
        'notification_email' => 1,
        'notification_system' => 1,
        'theme' => 'light',
        'language' => 'en'
    ];
}

// Handle form submission
$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_account') {
        // Update account information
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($full_name) || empty($email)) {
            $error = "Full name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email exists for other users
            $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != $user_id");
            if (mysqli_num_rows($check) > 0) {
                $error = "Email already exists.";
            } else {
                // Update basic info
                $update = "UPDATE users SET full_name = '$full_name', email = '$email' WHERE id = $user_id";
                
                // Handle password change
                if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                    // Verify current password
                    $user_check = mysqli_query($conn, "SELECT password_hash FROM users WHERE id = $user_id");
                    $user_data = mysqli_fetch_assoc($user_check);
                    
                    if (!password_verify($current_password, $user_data['password_hash'])) {
                        $error = "Current password is incorrect.";
                    } elseif (empty($new_password)) {
                        $error = "New password cannot be empty.";
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error = "Password must be at least 6 characters long.";
                    } else {
                        $password_hash = hashPassword($new_password);
                        $update = "UPDATE users SET full_name = '$full_name', email = '$email', password_hash = '$password_hash' WHERE id = $user_id";
                    }
                }
                
                if (empty($error)) {
                    if (mysqli_query($conn, $update)) {
                        $message = "Account information updated successfully.";
                        // Update session
                        $_SESSION['admin_name'] = $full_name;
                    } else {
                        $error = "Database error: " . mysqli_error($conn);
                    }
                }
            }
        }
        
    } elseif ($action === 'update_profile') {
        // Update profile information
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $bio = mysqli_real_escape_string($conn, $_POST['bio'] ?? '');
        $department = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
        $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
        $social_facebook = mysqli_real_escape_string($conn, $_POST['social_facebook'] ?? '');
        $social_twitter = mysqli_real_escape_string($conn, $_POST['social_twitter'] ?? '');
        $social_linkedin = mysqli_real_escape_string($conn, $_POST['social_linkedin'] ?? '');
        $social_instagram = mysqli_real_escape_string($conn, $_POST['social_instagram'] ?? '');
        
        $update = "UPDATE admin_profiles SET 
                   phone = " . ($phone ? "'$phone'" : "NULL") . ",
                   bio = " . ($bio ? "'$bio'" : "NULL") . ",
                   department = " . ($department ? "'$department'" : "NULL") . ",
                   position = " . ($position ? "'$position'" : "NULL") . ",
                   social_facebook = " . ($social_facebook ? "'$social_facebook'" : "NULL") . ",
                   social_twitter = " . ($social_twitter ? "'$social_twitter'" : "NULL") . ",
                   social_linkedin = " . ($social_linkedin ? "'$social_linkedin'" : "NULL") . ",
                   social_instagram = " . ($social_instagram ? "'$social_instagram'" : "NULL") . "
                   WHERE user_id = $user_id";
        
        if (mysqli_query($conn, $update)) {
            $message = "Profile information updated successfully.";
            // Refresh profile data
            $result = mysqli_query($conn, "SELECT * FROM admin_profiles WHERE user_id = $user_id");
            $profile = mysqli_fetch_assoc($result);
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
        
    } elseif ($action === 'update_preferences') {
        // Update preferences
        $notification_email = isset($_POST['notification_email']) ? 1 : 0;
        $notification_system = isset($_POST['notification_system']) ? 1 : 0;
        $theme = mysqli_real_escape_string($conn, $_POST['theme'] ?? 'light');
        $language = mysqli_real_escape_string($conn, $_POST['language'] ?? 'en');
        
        $update = "UPDATE admin_profiles SET 
                   notification_email = $notification_email,
                   notification_system = $notification_system,
                   theme = '$theme',
                   language = '$language'
                   WHERE user_id = $user_id";
        
        if (mysqli_query($conn, $update)) {
            $message = "Preferences updated successfully.";
            // Refresh profile data
            $result = mysqli_query($conn, "SELECT * FROM admin_profiles WHERE user_id = $user_id");
            $profile = mysqli_fetch_assoc($result);
        } else {
            $error = "Database error: " . mysqli_error($conn);
        }
        
    } elseif ($action === 'upload_avatar') {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_avatar($_FILES['avatar']);
            
            if ($upload_result['success']) {
                $avatar = $upload_result['filename'];
                
                // Delete old avatar if exists
                if (!empty($profile['avatar'])) {
                    $old_avatar = __DIR__ . "/../uploads/avatars/" . $profile['avatar'];
                    if (file_exists($old_avatar)) {
                        unlink($old_avatar);
                    }
                }
                
                // Update database
                $update = mysqli_query($conn, "UPDATE admin_profiles SET avatar = '$avatar' WHERE user_id = $user_id");
                
                if ($update) {
                    $message = "Avatar uploaded successfully.";
                    // Refresh profile data
                    $result = mysqli_query($conn, "SELECT * FROM admin_profiles WHERE user_id = $user_id");
                    $profile = mysqli_fetch_assoc($result);
                } else {
                    $error = "Database error: " . mysqli_error($conn);
                }
            } else {
                $error = $upload_result['error'];
            }
        } else {
            $error = "Please select an image to upload.";
        }
        
    } elseif ($action === 'remove_avatar') {
        // Remove avatar
        if (!empty($profile['avatar'])) {
            $old_avatar = __DIR__ . "/../uploads/avatars/" . $profile['avatar'];
            if (file_exists($old_avatar)) {
                unlink($old_avatar);
            }
            
            $update = mysqli_query($conn, "UPDATE admin_profiles SET avatar = NULL WHERE user_id = $user_id");
            
            if ($update) {
                $message = "Avatar removed successfully.";
                // Refresh profile data
                $result = mysqli_query($conn, "SELECT * FROM admin_profiles WHERE user_id = $user_id");
                $profile = mysqli_fetch_assoc($result);
            } else {
                $error = "Database error: " . mysqli_error($conn);
            }
        }
    }
}

// Get user data
$user_result = mysqli_query($conn, "SELECT username, full_name, email, role, created_at FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TechInHausa Admin</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #333;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #031837 0%, #02122b 100%);
            color: white;
            padding: 2rem 1.5rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(211, 201, 254, 0.2);
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            color: #D3C9FE;
        }
        
        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .admin-info {
            background: rgba(211, 201, 254, 0.1);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .admin-name {
            font-weight: 600;
        }
        
        .admin-role {
            font-size: 0.85rem;
            background: #D3C9FE;
            color: #031837;
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            margin-top: 0.5rem;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(211, 201, 254, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            color: #D3C9FE;
        }
        
        .nav-badge {
            background: #D3C9FE;
            color: #031837;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            margin-left: auto;
            font-weight: 600;
        }
        
        .nav-section {
            margin: 1.5rem 0 0.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            opacity: 0.5;
            color: #D3C9FE;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title h1 {
            font-size: 1.8rem;
            color: #031837;
        }
        
        .page-title p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #031837;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a2a4a;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .message {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }
        
        .message.success {
            border-left-color: #28a745;
            background: #d4edda;
            color: #155724;
        }
        
        .message.error {
            border-left-color: #dc3545;
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Profile Container */
        .profile-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #031837 0%, #0a2a4a 100%);
            padding: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
        }
        
        .avatar-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #D3C9FE;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(211, 201, 254, 0.2);
            border: 4px solid #D3C9FE;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            color: #D3C9FE;
            text-transform: uppercase;
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: #D3C9FE;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #031837;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .avatar-upload-btn:hover {
            background: #b8a9fe;
            transform: scale(1.1);
        }
        
        .avatar-upload-input {
            display: none;
        }
        
        .profile-info h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .profile-info .role {
            display: inline-block;
            background: #D3C9FE;
            color: #031837;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .profile-info .meta {
            display: flex;
            gap: 2rem;
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }
        
        .profile-info .meta i {
            color: #D3C9FE;
            margin-right: 0.5rem;
        }
        
        /* Profile Tabs */
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 2rem;
        }
        
        .tab-link {
            padding: 1rem 1.5rem;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-link i {
            color: #D3C9FE;
        }
        
        .tab-link:hover {
            color: #031837;
        }
        
        .tab-link.active {
            color: #031837;
            border-bottom-color: #D3C9FE;
        }
        
        .tab-link.active i {
            color: #031837;
        }
        
        /* Profile Content */
        .profile-content {
            padding: 2rem;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group label i {
            color: #D3C9FE;
            margin-right: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #D3C9FE;
            box-shadow: 0 0 0 3px rgba(211, 201, 254, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #031837;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .social-links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .input-group-prepend {
            background: #f0f0f0;
            padding: 0.75rem 1rem;
            color: #666;
            border-right: 1px solid #e0e0e0;
        }
        
        .input-group .form-control {
            border: none;
            border-radius: 0;
        }
        
        .input-group .form-control:focus {
            box-shadow: none;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-box-title {
            font-weight: 600;
            color: #031837;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 1rem 0.5rem;
            }
            
            .sidebar-header h2, .sidebar-header p, .admin-info .admin-name, .admin-role, .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 0.75rem;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .profile-info .meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .social-links-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>TechInHausa</h2>
                <p>Admin Panel</p>
            </div>
            
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                <div class="admin-role"><?php echo ucfirst($user['role'] ?? 'Admin'); ?></div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-section">Main</li>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-section">Content</li>
                <li class="nav-item">
                    <a href="videos.php" class="nav-link">
                        <i class="fas fa-play-circle"></i>
                        <span>Videos</span>
                        <?php if ($total_videos_draft > 0): ?>
                        <span class="nav-badge"><?php echo $total_videos_draft; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="blog.php" class="nav-link">
                        <i class="fas fa-blog"></i>
                        <span>Blog Posts</span>
                        <?php if ($total_blog_draft > 0): ?>
                        <span class="nav-badge"><?php echo $total_blog_draft; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="news.php" class="nav-link">
                        <i class="fas fa-newspaper"></i>
                        <span>News</span>
                        <?php if ($total_news_draft > 0): ?>
                        <span class="nav-badge"><?php echo $total_news_draft; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="research.php" class="nav-link">
                        <i class="fas fa-flask"></i>
                        <span>Research</span>
                        <?php if ($total_research_draft > 0): ?>
                        <span class="nav-badge"><?php echo $total_research_draft; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="creator.php" class="nav-link">
                        <i class="fas fa-user-graduate"></i>
                        <span>MalamIromba</span>
                        <?php if ($total_creator_draft > 0): ?>
                        <span class="nav-badge"><?php echo $total_creator_draft; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-section">Organization</li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link">
                        <i class="fas fa-folder"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tags.php" class="nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Tags</span>
                    </a>
                </li>
                
                <li class="nav-section">Team & People</li>
                <li class="nav-item">
                    <a href="team_members.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Team Members</span>
                        <?php if ($total_team_inactive > 0): ?>
                        <span class="nav-badge"><?php echo $total_team_inactive; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-section">Features</li>
                <li class="nav-item">
                    <a href="media_features.php" class="nav-link">
                        <i class="fas fa-trophy"></i>
                        <span>Media Features</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sponsors.php" class="nav-link">
                        <i class="fas fa-handshake"></i>
                        <span>Sponsors</span>
                    </a>
                </li>
                
                <li class="nav-section">Communications</li>
                <li class="nav-item">
                    <a href="contact_submissions.php" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        <span>Contact Messages</span>
                        <?php if ($unread_contacts > 0): ?>
                        <span class="nav-badge"><?php echo $unread_contacts; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="subscribers.php" class="nav-link">
                        <i class="fas fa-envelope-open-text"></i>
                        <span>Newsletter</span>
                        <span class="nav-badge"><?php echo $total_subscribers; ?></span>
                    </a>
                </li>
                
                <li class="nav-section">Users</li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        <span>Admin Users</span>
                    </a>
                </li>
                
                <li class="nav-section">Account</li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link active">
                        <i class="fas fa-user-circle"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link" style="color: #D3C9FE;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                    <p>Manage your account settings and preferences</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (!empty($profile['avatar'])): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo htmlspecialchars($profile['avatar']); ?>" 
                                 alt="Avatar" class="avatar-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Avatar Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display: inline;">
                            <input type="hidden" name="action" value="upload_avatar">
                            <label for="avatarUpload" class="avatar-upload-btn">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="avatarUpload" name="avatar" class="avatar-upload-input" accept="image/*" onchange="document.getElementById('avatarForm').submit();">
                        </form>
                        
                        <?php if (!empty($profile['avatar'])): ?>
                            <form method="POST" style="position: absolute; bottom: 0; left: 0;" onsubmit="return confirm('Remove avatar?');">
                                <input type="hidden" name="action" value="remove_avatar">
                                <button type="submit" class="avatar-upload-btn" style="background: #dc3545; color: white;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h2>
                        <div class="role"><?php echo ucfirst($user['role']); ?></div>
                        <div class="meta">
                            <span><i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?></span>
                            <span><i class="fas fa-calendar-alt"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Tabs -->
                <div class="profile-tabs">
                    <a href="?tab=account" class="tab-link <?php echo $active_tab === 'account' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Account
                    </a>
                    <a href="?tab=profile" class="tab-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-id-card"></i> Profile
                    </a>
                    <a href="?tab=preferences" class="tab-link <?php echo $active_tab === 'preferences' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Preferences
                    </a>
                </div>
                
                <!-- Profile Content -->
                <div class="profile-content">
                    <!-- Account Tab -->
                    <div class="tab-pane <?php echo $active_tab === 'account' ? 'active' : ''; ?>" id="account">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_account">
                            
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label><i class="fas fa-user"></i> Full Name</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label><i class="fas fa-envelope"></i> Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label><i class="fas fa-lock"></i> Username</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                    <small style="color: #666;">Username cannot be changed</small>
                                </div>
                            </div>
                            
                            <div class="info-box">
                                <div class="info-box-title"><i class="fas fa-key"></i> Change Password</div>
                                <p style="color: #666; margin-bottom: 1rem;">Leave blank to keep current password</p>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <input type="password" name="current_password" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div class="tab-pane <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="profile">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Phone Number</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Department</label>
                                    <input type="text" name="department" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['department'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-briefcase"></i> Position</label>
                                    <input type="text" name="position" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['position'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label><i class="fas fa-align-left"></i> Bio</label>
                                    <textarea name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <h3 style="margin: 2rem 0 1rem; color: #031837;">Social Links</h3>
                            
                            <div class="social-links-grid">
                                <div class="form-group">
                                    <label><i class="fab fa-facebook" style="color: #1877f2;"></i> Facebook</label>
                                    <div class="input-group">
                                        <span class="input-group-prepend">fb.com/</span>
                                        <input type="text" name="social_facebook" class="form-control" 
                                               value="<?php echo htmlspecialchars($profile['social_facebook'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-twitter" style="color: #1da1f2;"></i> Twitter</label>
                                    <div class="input-group">
                                        <span class="input-group-prepend">@</span>
                                        <input type="text" name="social_twitter" class="form-control" 
                                               value="<?php echo htmlspecialchars($profile['social_twitter'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-linkedin" style="color: #0077b5;"></i> LinkedIn</label>
                                    <div class="input-group">
                                        <span class="input-group-prepend">linkedin.com/in/</span>
                                        <input type="text" name="social_linkedin" class="form-control" 
                                               value="<?php echo htmlspecialchars($profile['social_linkedin'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-instagram" style="color: #e4405f;"></i> Instagram</label>
                                    <div class="input-group">
                                        <span class="input-group-prepend">@</span>
                                        <input type="text" name="social_instagram" class="form-control" 
                                               value="<?php echo htmlspecialchars($profile['social_instagram'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Preferences Tab -->
                    <div class="tab-pane <?php echo $active_tab === 'preferences' ? 'active' : ''; ?>" id="preferences">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-palette"></i> Theme</label>
                                    <select name="theme" class="form-control">
                                        <option value="light" <?php echo ($profile['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($profile['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="system" <?php echo ($profile['theme'] ?? '') === 'system' ? 'selected' : ''; ?>>System Default</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-language"></i> Language</label>
                                    <select name="language" class="form-control">
                                        <option value="en" <?php echo ($profile['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="ha" <?php echo ($profile['language'] ?? '') === 'ha' ? 'selected' : ''; ?>>Hausa</option>
                                        <option value="fr" <?php echo ($profile['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h3 style="margin: 2rem 0 1rem; color: #031837;">Notifications</h3>
                            
                            <div class="form-check">
                                <input type="checkbox" name="notification_email" id="notification_email" 
                                       <?php echo ($profile['notification_email'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="notification_email">Email notifications</label>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" name="notification_system" id="notification_system" 
                                       <?php echo ($profile['notification_system'] ?? 1) ? 'checked' : ''; ?>>
                                <label for="notification_system">System notifications</label>
                            </div>
                            
                            <div class="info-box" style="margin-top: 1rem;">
                                <p style="color: #666;">
                                    <i class="fas fa-info-circle"></i> 
                                    Notification preferences will be used for system alerts and updates.
                                </p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>