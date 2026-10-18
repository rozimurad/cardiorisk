<?php
/**
 * Header Component
 * Navigation bar with user information and authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once __DIR__ . '/../config/functions.php';

// Get current user info
$userName = getCurrentUserName();
$userRole = $_SESSION['role'] ?? 'guest';
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch Profile Image
$headerProfileImg = null;
if ($userRole === 'patient') {
    // Patient: Fetch from patients table
    if (isset($patient) && !empty($patient['profile_image'])) {
        $headerProfileImg = $patient['profile_image'];
    } else {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        $dummyTC = '99' . str_pad($_SESSION['user_id'] ?? 0, 9, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT profile_image FROM patients WHERE tc_identity = ?");
        $stmt->execute([$dummyTC]);
        $res = $stmt->fetch();
        if ($res && !empty($res['profile_image'])) {
            $headerProfileImg = $res['profile_image'];
        }
    }
} else {
    // Doctor/Admin: Fetch from users table
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $res = $stmt->fetch();
    if ($res && !empty($res['profile_image'])) {
        $headerProfileImg = $res['profile_image'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CardioRisk - Tıbbi Karar Destek Sistemi</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <i class="bi bi-heart-pulse-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                <span class="fw-bold text-primary">Cardio</span>
                <span class="fw-light">Risk</span>
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto align-items-center">
                    
                    <!-- User Info -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php if ($headerProfileImg): ?>
                                    <img src="../assets/uploads/<?php echo htmlspecialchars($headerProfileImg); ?>" 
                                         alt="Profile" 
                                         class="rounded-circle" 
                                         style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #0891B2;">
                                <?php else: ?>
                                    <i class="bi bi-person-circle text-primary" style="font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-info d-none d-lg-block">
                                <div class="fw-semibold"><?php echo htmlspecialchars($userName); ?></div>
                                <small class="text-muted text-capitalize"><?php echo htmlspecialchars($userRole); ?></small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <?php if (hasRole('patient')): ?>
                                <a class="dropdown-item" href="patient/profile.php">
                                    <i class="bi bi-person-badge me-2"></i> Sağlık Kimliği (Hasta)
                                </a>
                                <?php else: ?>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person-lines-fill me-2"></i> Doktor Profili
                                </a>
                                <?php endif; ?>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo ($userRole === 'patient') ? 'patient/settings.php' : 'settings.php'; ?>">
                                    <i class="bi bi-gear me-2"></i> Ayarlar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="../logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Container -->
    <div class="container-fluid">
        <div class="row">
