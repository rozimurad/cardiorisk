<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Check auth - Only allow doctors or administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'patient') {
    header("Location: ../modules/login.php");
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';
$activeTab = 'clinic'; // Default tab

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Clinic Settings Update
    if (isset($_POST['update_clinic'])) {
        $activeTab = 'clinic';
        $clinicName = cleanInput($_POST['clinic_name']);
        $clinicAddress = cleanInput($_POST['clinic_address']);
        $reportFooter = cleanInput($_POST['report_footer']);
        
        try {
            $stmt = $db->prepare("UPDATE users SET clinic_name = ?, clinic_address = ?, report_footer = ? WHERE id = ?");
            if ($stmt->execute([$clinicName, $clinicAddress, $reportFooter, $userId])) {
                $success = 'Klinik ayarları güncellendi.';
            }
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }

        // Clip Logo Upload
        if (isset($_FILES['clinic_logo']) && $_FILES['clinic_logo']['error'] === 0) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileExt = strtolower(pathinfo($_FILES['clinic_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
                $newFileName = 'logo_' . $userId . '_' . time() . '.' . $fileExt;
                if (move_uploaded_file($_FILES['clinic_logo']['tmp_name'], $uploadDir . $newFileName)) {
                    $db->prepare("UPDATE users SET clinic_logo = ? WHERE id = ?")->execute([$newFileName, $userId]);
                    $success .= ' Logo yüklendi.';
                }
            } else {
                $error = 'Logo için sadece JPG ve PNG formatları kabul edilir.';
            }
        }
    }

    // 2. Algorithm Settings Update
    if (isset($_POST['update_algorithm'])) {
        $activeTab = 'algorithm';
        $riskThreshold = intval($_POST['risk_threshold']);
        if ($riskThreshold < 10 || $riskThreshold > 90) $riskThreshold = 50; // Safety clamp
        
        try {
            $stmt = $db->prepare("UPDATE users SET risk_threshold = ? WHERE id = ?");
            if ($stmt->execute([$riskThreshold, $userId])) {
                $success = 'Algoritma hassasiyeti güncellendi.';
            }
        } catch (PDOException $e) {
            $error = 'Hata: ' . $e->getMessage();
        }
    }

    // 3. Password Change
    if (isset($_POST['change_password'])) {
        $activeTab = 'security';
        $currentPass = $_POST['current_password'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        
        // Fetch current password hash
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userPass = $stmt->fetchColumn();
        
        if (password_verify($currentPass, $userPass)) {
            if ($newPass === $confirmPass) {
                if (strlen($newPass) >= 6) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
                    $success = 'Şifre başarıyla değiştirildi.';
                } else {
                    $error = 'Yeni şifre en az 6 karakter olmalıdır.';
                }
            } else {
                $error = 'Yeni şifreler eşleşmiyor.';
            }
        } else {
            $error = 'Mevcut şifre yanlış.';
        }
    }
}

// Fetch User Data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch Logs (Mock or Real)
$logs = [];
try {
    $stmtLogs = $db->prepare("SELECT * FROM access_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 5");
    $stmtLogs->execute([$userId]);
    $logs = $stmtLogs->fetchAll();
} catch (PDOException $e) {
    // Table might not exist if migration failed
    $logs = [
        ['login_time' => date('Y-m-d H:i:s'), 'ip_address' => '127.0.0.1', 'status' => 'success']
    ];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 text-gray-800">Sistem Ayarları</h1>
            <p class="text-muted">Klinik bilgileri, algoritma yapılandırması ve güvenlik.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <ul class="nav nav-tabs card-header-tabs" id="settingsTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?php echo $activeTab === 'clinic' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#clinic" type="button">
                        <i class="bi bi-hospital me-2"></i>Klinik Ayarları
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $activeTab === 'algorithm' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#algorithm" type="button">
                        <i class="bi bi-sliders me-2"></i>Algoritma
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#security" type="button">
                        <i class="bi bi-shield-lock me-2"></i>Güvenlik
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="settingsTabContent">
                
                <!-- 1. Klinik Ayarları -->
                <div class="tab-pane fade <?php echo $activeTab === 'clinic' ? 'show active' : ''; ?>" id="clinic">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_clinic" value="1">
                        
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Klinik / Hastane Adı</label>
                                    <input type="text" name="clinic_name" class="form-control" value="<?php echo htmlspecialchars($user['clinic_name'] ?? ''); ?>" placeholder="Örn: Özel Kalp Sağlığı Merkezi">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Adres Bilgisi</label>
                                    <textarea name="clinic_address" class="form-control" rows="3"><?php echo htmlspecialchars($user['clinic_address'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rapor Alt Metni (Footer)</label>
                                    <input type="text" name="report_footer" class="form-control" value="<?php echo htmlspecialchars($user['report_footer'] ?? ''); ?>" placeholder="Örn: Bu rapor CardioRisk algoritması ile üretilmiştir.">
                                    <div class="form-text">PDF raporlarının en altında görünecek yasal uyarı veya not.</div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <label class="form-label d-block text-start">Klinik Logosu</label>
                                <div class="p-4 border rounded bg-light mb-3 d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <?php if (!empty($user['clinic_logo'])): ?>
                                        <img src="../assets/uploads/<?php echo htmlspecialchars($user['clinic_logo']); ?>" style="max-width: 100%; max-height: 100%;">
                                    <?php else: ?>
                                        <div class="text-muted">
                                            <i class="bi bi-image fs-1 d-block mb-2"></i>
                                            Logo Yok
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="clinic_logo" class="form-control form-control-sm">
                                <div class="form-text mt-2">Raporda kullanılacak logo (PNG/JPG).</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Klinik Bilgilerini Kaydet</button>
                    </form>
                </div>

                <!-- 2. Algoritma Konfigürasyonu -->
                <div class="tab-pane fade <?php echo $activeTab === 'algorithm' ? 'show active' : ''; ?>" id="algorithm">
                    <form method="POST">
                        <input type="hidden" name="update_algorithm" value="1">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Risk Eşiği Nedir?</strong> Bu değer, bir hastanın "Yüksek Riskli" olarak işaretlenmesi ve kırmızı uyarı verilmesi için gereken minimum yüzdeyi belirler. Standart değer 50'dir.
                        </div>

                        <div class="my-5 px-3">
                            <label for="riskRange" class="form-label d-flex justify-content-between">
                                <span class="fw-bold">Hassasiyet Ayarı</span>
                                <span class="badge bg-primary fs-6" id="rangeVal"><?php echo $user['risk_threshold'] ?? 50; ?>%</span>
                            </label>
                            <input type="range" class="form-range" min="30" max="80" step="5" id="riskRange" name="risk_threshold" value="<?php echo $user['risk_threshold'] ?? 50; ?>" oninput="document.getElementById('rangeVal').innerText = this.value + '%'">
                            <div class="d-flex justify-content-between text-muted small mt-2">
                                <span>30% (Çok Hassas)</span>
                                <span>50% (Standart)</span>
                                <span>80% (Yalnızca Kritik)</span>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-cpu me-2"></i>Algoritma Ayarını Güncelle</button>
                    </form>
                </div>

                <!-- 3. Güvenlik -->
                <div class="tab-pane fade <?php echo $activeTab === 'security' ? 'show active' : ''; ?>" id="security">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h5 class="mb-4">Şifre Değiştir</h5>
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Mevcut Şifre</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Yeni Şifre</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Yeni Şifre (Tekrar)</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-danger text-white">Şifreyi Değiştir</button>
                            </form>
                        </div>
                        <div class="col-md-6 ps-4">
                            <h5 class="mb-4">Son Erişim Logları</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tarih</th>
                                            <th>IP Adresi</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($logs)): ?>
                                            <?php foreach ($logs as $log): ?>
                                                <tr>
                                                    <td><?php echo $log['login_time']; ?></td>
                                                    <td><?php echo $log['ip_address']; ?></td>
                                                    <td>
                                                        <?php if ($log['status'] == 'success'): ?>
                                                            <span class="badge bg-success">Başarılı</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Hata</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center text-muted">Kayıt bulunamadı.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
