<?php
/**
 * Index / Entry Point
 * Routes users based on authentication status
 */

session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Role'e göre yönlendir
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: modules/patient_dashboard.php');
    } else {
        header('Location: modules/dashboard.php');
    }
    exit();
} else {
    // Redirect to login
    header('Location: modules/login.php');
    exit();
}
?>
