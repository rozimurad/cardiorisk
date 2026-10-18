<?php
/**
 * Patient Settings Module
 * Security, Notifications, and Privacy (GDPR Export)
 */

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

checkAuth('../../modules/login.php');
if (!hasRole('patient')) {
    header('Location: ../../modules/dashboard.php');
    exit();
}

$db = getDB();
$userId = getCurrentUserId();
$successMsg = '';
$errorMsg = '';

// Get Patient ID for Data Export
$dummyTC = '99' . str_pad($userId, 9, '0', STR_PAD_LEFT);
$stmt = $db->prepare("SELECT id, full_name, tc_identity FROM patients WHERE tc_identity = :tc_identity");
$stmt->execute([':tc_identity' => $dummyTC]);
$patient = $stmt->fetch();

// HANDLE DATA EXPORT (GDPR)
if (isset($_POST['action']) && $_POST['action'] === 'export_data') {
    if ($patient) {
        // Fetch all visits
        $visitStmt = $db->prepare("SELECT * FROM visits WHERE patient_id = :pid ORDER BY visit_date DESC");
        $visitStmt->execute([':pid' => $patient['id']]);
        $visits = $visitStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Construct Export Data
        $exportData = [
            'patient_info' => $patient,
            'export_date' => date('Y-m-d H:i:s'),
            'total_records' => count($visits),
            'medical_history' => $visits
        ];
        
        // Send JSON File
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="saglik_verilerim_' . date('Ymd') . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// HANDLE PASSWORD CHANGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $errorMsg = "Lütfen tüm alanları doldurunuz.";
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = "Yeni şifreler uyuşmuyor.";
    } else {
        // Verify current password
        $userStmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch();
        
        if (password_verify($currentPass, $user['password'])) {
            // Update password
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = :pass WHERE id = :id");
            if ($updateStmt->execute([':pass' => $newHash, ':id' => $userId])) {
                $successMsg = "Şifreniz başarıyla güncellendi.";
            } else {
                $errorMsg = "Bir hata oluştu.";
            }
        } else {
            $errorMsg = "Mevcut şifreniz hatalı.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - CardioRisk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-color: #0891B2; --secondary-color: #0D9488; }
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #0e7490 0%, #0891B2 100%); min-height: 100vh; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); padding: 12px 20px; margin: 4px 16px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-tabs .nav-link { color: #64748b; border: none; border-bottom: 2px solid transparent; padding: 1rem 1.5rem; font-weight: 500; }
        .nav-tabs .nav-link:hover { color: #0891B2; }
        .nav-tabs .nav-link.active { color: #0891B2; border-bottom-color: #0891B2; background: none; }
        .form-check-input:checked { background-color: #0891B2; border-color: #0891B2; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block">
            <div class="p-4 text-center border-bottom border-white border-opacity-10 mb-3">
                <i class="bi bi-heart-pulse-fill fs-1"></i>
                <h5 class="mt-2 fw-bold">CardioRisk</h5>
                <small class="text-white-50">Hasta Paneli</small>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="../patient_dashboard.php">
                        <i class="bi bi-speedometer2 me-2"></i>Genel Bakış
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person-badge me-2"></i>Sağlık Kimliğim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="settings.php">
                        <i class="bi bi-gear me-2"></i>Ayarlar
                    </a>
                </li>
                <li class="nav-item mt-auto">
                    <a class="nav-link text-white-50" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
            
            <h2 class="h3 fw-bold text-dark mb-4">Hesap Ayarları</h2>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs px-4" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bi bi-shield-lock me-2"></i>Güvenlik
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="bi bi-bell me-2"></i>Bildirimler
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                                <i class="bi bi-file-earmark-lock me-2"></i>Gizlilik & Veri
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-4" id="settingsTabContent">
                        
                        <!-- Security Tab -->
                        <div class="tab-pane fade show active" id="security" role="tabpanel">
                            <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <h5 class="mb-4">Şifre Değiştir</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Mevcut Şifre</label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Yeni Şifre</label>
                                            <input type="password" name="new_password" class="form-control" required>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Yeni Şifre (Tekrar)</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            Şifreyi Güncelle
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications Tab -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                             <div class="row justify-content-center">
                                <div class="col-lg-8">
                                    <h5 class="mb-4">Bildirim Tercihleri</h5>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 border rounded bg-light">
                                        <div>
                                            <h6 class="mb-1">E-posta Bildirimleri</h6>
                                            <small class="text-muted">Risk analiz raporları ve uyarılar e-posta olarak gelsin.</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 border rounded bg-light">
                                        <div>
                                            <h6 class="mb-1">SMS Bildirimleri</h6>
                                            <small class="text-muted">Kritik durumlarda telefonunuza SMS gönderilsin.</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                        </div>
                                    </div>
                                    
                                    <button class="btn btn-primary disabled">
                                        Tercihleri Kaydet (Demo)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy Tab -->
                        <div class="tab-pane fade" id="privacy" role="tabpanel">
                            <div class="text-center py-4">
                                <div class="mb-4">
                                    <i class="bi bi-filetype-json text-primary" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="fw-bold">Verilerinizi İndirin</h4>
                                <p class="text-muted w-75 mx-auto mb-4">
                                    KVKK ve GDPR kapsamındaki haklarınız gereği, sistemimizde kayıtlı olan tüm tıbbi geçmişinizi ve kişisel verilerinizi makine tarafından okunabilir (JSON) formatta indirebilirsiniz.
                                </p>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="export_data">
                                    <button type="submit" class="btn btn-lg btn-outline-primary px-5">
                                        <i class="bi bi-download me-2"></i>
                                        Tüm Verilerimi İndir (.json)
                                    </button>
                                </form>
                                
                                <div class="mt-4 pt-4 border-top">
                                    <small class="text-muted">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Bu dosya kişisel sağlık verilerinizi içerir. Lütfen güvenli bir yerde saklayınız.
                                    </small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if($successMsg): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Başarılı',
    text: '<?php echo $successMsg; ?>',
    confirmButtonColor: '#0891B2'
});
</script>
<?php endif; ?>

<?php if($errorMsg): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Hata',
    text: '<?php echo $errorMsg; ?>',
    confirmButtonColor: '#0891B2'
});
</script>
<?php endif; ?>

</body>
</html>
