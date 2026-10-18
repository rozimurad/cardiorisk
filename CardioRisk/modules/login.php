<?php
/**
 * Login Module
 * User authentication page
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/db.php';
require_once '../config/functions.php';

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = sanitize($_POST['email'] ?? ''); // Treating 'email' field as generic input
    $password = $_POST['password'] ?? '';
    
    if (empty($input) || empty($password)) {
        $error = 'Lütfen e-posta veya TC Kimlik numaranızı giriniz.';
    } else {
        try {
            $db = getDB();
            $user = false;

            // Check if input is TCKN (11 digits, numeric)
            if (is_numeric($input) && strlen($input) == 11) {
                // Find user via patients table
                $stmt = $db->prepare("SELECT user_id FROM patients WHERE tc_identity = :tc");
                $stmt->bindParam(':tc', $input);
                $stmt->execute();
                $patient = $stmt->fetch();
                
                if ($patient && !empty($patient['user_id'])) {
                    $sql = "SELECT id, full_name, email, password, role FROM users WHERE id = :id";
                    $stmtUser = $db->prepare($sql);
                    $stmtUser->execute([':id' => $patient['user_id']]);
                    $user = $stmtUser->fetch();
                }
            } else {
                // Standard Email Login
                $sql = "SELECT id, full_name, email, password, role FROM users WHERE email = :email";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':email', $input);
                $stmt->execute();
                $user = $stmt->fetch();
            }
            
            if ($user && password_verify($password, $user['password'])) {
                // Giriş başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log Access (Success)
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $logStmt = $db->prepare("INSERT INTO access_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
                $logStmt->execute([$user['id'], $ipAddress]);
                
                // Role'e göre yönlendir
                if ($user['role'] === 'patient') {
                    header('Location: patient_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                // Log Access (Failure)
                if ($user) {
                     $ipAddress = $_SERVER['REMOTE_ADDR'];
                     $logStmt = $db->prepare("INSERT INTO access_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'failure')");
                     $logStmt->execute([$user['id'], $ipAddress]);
                }
                
                $error = 'Giriş bilgileri hatalı.';
            }
            
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - CardioRisk</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="login-page">
    
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                
                <!-- Login Card -->
                <div class="card shadow-lg border-0 login-card">
                    <div class="card-body p-5">
                        
                        <!-- Logo & Title -->
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="bi bi-heart-pulse-fill text-primary" style="font-size: 3.5rem;"></i>
                            </div>
                            <h2 class="fw-bold mb-1">
                                <span class="text-primary">Cardio</span><span>Risk</span>
                            </h2>
                            <p class="text-muted">Tıbbi Karar Destek Sistemi</p>
                        </div>
                        
                        <!-- Login Form -->
                        <form method="POST" action="" id="loginForm">
                            
                            <!-- Email/TC Field -->
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta veya TC Kimlik No</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-person text-primary"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="E-posta veya 11 haneli TC No"
                                           value="<?php echo htmlspecialchars($input ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Password Field -->
                            <div class="mb-4">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bi bi-lock text-primary"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Şifrenizi giriniz"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Giriş Yap
                            </button>
                            
                            <!-- Demo Credentials -->
                            <div class="alert alert-info py-2 small">
                                <strong>Demo Giriş Bilgileri:</strong><br>
                                E-posta: <code>dr.mehmet@cardiorisk.com</code><br>
                                Şifre: <code>password123</code>
                            </div>
                            
                        </form>
                        
                    </div>
                </div>
                
                <!-- Footer Text -->
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="bi bi-shield-check text-success"></i>
                        Güvenli Giriş | HIPAA Uyumlu
                    </small>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($error)): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Giriş Başarısız',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#0891B2'
        });
    </script>
    <?php endif; ?>
    
</body>
</html>
