<?php
/**
 * Global Helper Functions
 * Common utility functions used throughout the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 * @param mixed $data - Input data to sanitize
 * @return mixed - Sanitized data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    
    // Remove whitespace
    $data = trim($data);
    
    // Remove backslashes
    $data = stripslashes($data);
    
    // Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Clean input alias for sanitize
 */
function cleanInput($data) {
    return sanitize($data);
}

/**
 * Format date for consistent display
 * @param string $date - Date string
 * @param string $format - Desired format (default: d/m/Y)
 * @return string - Formatted date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Check if user is authenticated
 * Redirects to login if not authenticated
 * @param string $redirect_url - URL to redirect if not authenticated
 */
function checkAuth($redirect_url = 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Check if user has specific role
 * @param string $role - Required role (doctor/patient)
 * @return bool - True if user has role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require specific role or redirect
 * @param string $role - Required role
 * @param string $redirect_url - URL to redirect if role not matched
 */
function requireRole($role, $redirect_url = 'dashboard.php') {
    if (!hasRole($role)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Get current user ID from session
 * @return int|null - User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user full name from session
 * @return string - User's full name
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'Misafir';
}

/**
 * Safe redirect function
 * @param string $url - URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Calculate age from birth date
 * @param string $birth_date - Birth date (Y-m-d format)
 * @return int - Age in years
 */
function calculateAge($birth_date) {
    try {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        return $age;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get risk level badge HTML
 * @param int $risk_score - Risk score (0-100)
 * @return string - HTML badge element
 */
function getRiskBadge($risk_score) {
    if ($risk_score <= 33) {
        $level = 'Düşük';
        $class = 'success';
    } elseif ($risk_score <= 66) {
        $level = 'Orta';
        $class = 'warning';
    } else {
        $level = 'Yüksek';
        $class = 'danger';
    }
    
    return "<span class='badge bg-{$class}'>{$level} Risk ({$risk_score}%)</span>";
}

/**
 * Format chest pain type for display
 * @param int $cp - Chest pain type code
 * @return string - Human-readable chest pain type
 */
function getChestPainType($cp) {
    $types = [
        1 => 'Tipik Anjina',
        2 => 'Atipik Anjina',
        3 => 'Anjinal Olmayan Ağrı',
        4 => 'Asemptomatik'
    ];
    return $types[$cp] ?? 'Bilinmiyor';
}

/**
 * Format ECG result for display
 * @param int $restecg - ECG result code
 * @return string - Human-readable ECG result
 */
function getECGResult($restecg) {
    $results = [
        0 => 'Normal',
        1 => 'ST-T Dalga Anormalliği',
        2 => 'Sol Ventriküler Hipertrofi'
    ];
    return $results[$restecg] ?? 'Bilinmiyor';
}

/**
 * Format slope type for display
 * @param int $slope - Slope code
 * @return string - Human-readable slope type
 */
function getSlopeType($slope) {
    $types = [
        1 => 'Yükselen',
        2 => 'Düz',
        3 => 'Alçalan'
    ];
    return $types[$slope] ?? 'Bilinmiyor';
}

/**
 * Format thalassemia for display
 * @param int $thal - Thalassemia code
 * @return string - Human-readable thalassemia type
 */
function getThalassemiaType($thal) {
    $types = [
        3 => 'Normal',
        6 => 'Sabit Defekt',
        7 => 'Geri Dönüşümlü Defekt'
    ];
    return $types[$thal] ?? 'Bilinmiyor';
}

/**
 * Validate TC Identity Number (Turkish ID)
 * Basic validation for demonstration purposes
 * @param string $tc - TC Identity Number
 * @return bool - True if valid format
 */
function validateTC($tc) {
    // Must be 11 digits
    if (!preg_match('/^[0-9]{11}$/', $tc)) {
        return false;
    }
    
    // First digit cannot be 0
    if ($tc[0] == '0') {
        return false;
    }
    
    return true;
}

/**
 * Generate JSON response for AJAX requests
 * @param bool $success - Success status
 * @param mixed $data - Response data
 * @param string $message - Response message
 */
function jsonResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit();
}

/**
 * Debug helper (only for development)
 * @param mixed $data - Data to debug
 * @param bool $die - Whether to stop execution
 */
function dd($data, $die = true) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    if ($die) {
        die();
    }
}
?>
