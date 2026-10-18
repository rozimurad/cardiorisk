<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Check auth - Only allow doctors/admins (not patients)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'patient') {
    header("Location: ../modules/login.php");
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Update Personal Info
    if (isset($_POST['update_info'])) {
        $title = cleanInput($_POST['title']);
        $fullName = cleanInput($_POST['full_name']);
        $specialty = cleanInput($_POST['specialty']);
        $diplomaNo = cleanInput($_POST['diploma_no']);
        
        try {
            $stmt = $db->prepare("UPDATE users SET title = ?, full_name = ?, specialty = ?, diploma_no = ? WHERE id = ?");
            if ($stmt->execute([$title, $fullName, $specialty, $diplomaNo, $userId])) {
                $success = 'Profil bilgileri başarıyla güncellendi.';
                // Update session name if changed
                $_SESSION['user_name'] = $fullName;
            }
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }

    // 1.5 Handle Photo Deletion
    if (isset($_POST['delete_photo'])) {
        $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currImg = $stmt->fetchColumn();

        if ($currImg) {
            $filePath = __DIR__ . '/../assets/uploads/' . $currImg;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $db->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")->execute([$userId]);
            $success = 'Profil fotoğrafı başarıyla kaldırıldı.';
            
            // Refresh logic handled below
            header("Refresh:0");
            exit;
        }
    }

    // 2. Handle File Uploads (Signature & Profile Photo)
    if (isset($_FILES['digital_signature']) && $_FILES['digital_signature']['error'] === 0) {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExt = strtolower(pathinfo($_FILES['digital_signature']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'sign_' . $userId . '_' . time() . '.' . $fileExt;
            if (move_uploaded_file($_FILES['digital_signature']['tmp_name'], $uploadDir . $newFileName)) {
                $db->prepare("UPDATE users SET digital_signature = ? WHERE id = ?")->execute([$newFileName, $userId]);
                $success = 'Dijital imza güncellendi.';
            } else {
                $error = 'Dosya yükleme başarısız.';
            }
        } else {
            $error = 'Sadece JPG ve PNG dosyaları kabul edilir.';
        }
    }
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'doc_' . $userId . '_' . time() . '.' . $fileExt;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $newFileName)) {
                $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$newFileName, $userId]);
                $success = 'Profil fotoğrafı güncellendi.';
            } else {
                $error = 'Dosya yükleme başarısız.';
            }
        } else {
            $error = 'Sadece JPG ve PNG dosyaları kabul edilir.';
        }
    }
    
    // Refresh variable
    if (!empty($success) && empty($error)) {
        header("Refresh:0"); // Reload to show new images/data
        exit;
    }
}

// Fetch Current Data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 text-gray-800">Doktor Profili</h1>
            <p class="text-muted">Kişisel bilgilerinizi ve dijital imzanızı yönetin.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <!-- Sol Kolon: Görseller -->
            <div class="col-lg-4 mb-4">
                <!-- Profil Kartı -->
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-body text-center pt-5 pb-4">
                        <div class="position-relative d-inline-block mb-3">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../assets/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                    <i class="bi bi-person-fill text-secondary" style="font-size: 5rem;"></i>
                                </div>
                            <?php endif; ?>
                            <label for="profile_image" class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" title="Fotoğrafı Değiştir">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            
                            <?php if (!empty($user['profile_image'])): ?>
                            <button type="button" class="position-absolute top-0 end-0 btn btn-sm btn-danger rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0; line-height: 30px; transform: translate(25%, -25%);" title="Fotoğrafı Kaldır" onclick="if(confirm('Profil fotoğrafını kaldırmak istediğinize emin misiniz?')) { document.getElementById('delete_photo_form').submit(); }">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                            <?php endif; ?>
                            
                            <input type="file" id="profile_image" name="profile_image" class="d-none" onchange="this.form.submit()">
                        </div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($user['title'] . ' ' . $user['full_name']); ?></h4>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user['specialty']); ?></p>
                        <div class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Onaylı Hesap</div>
                    </div>
                </div>

                <!-- Dijital İmza Kartı -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-pen me-2"></i>Dijital İmza</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3 p-3 border rounded bg-light" style="min-height: 100px; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($user['digital_signature'])): ?>
                                <img src="../assets/uploads/<?php echo htmlspecialchars($user['digital_signature']); ?>" class="img-fluid" style="max-height: 80px;">
                            <?php else: ?>
                                <span class="text-muted small">Henüz imza yüklenmedi</span>
                            <?php endif; ?>
                        </div>
                        <label class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-upload me-2"></i>İmza Yükle
                            <input type="file" name="digital_signature" class="d-none" onchange="this.form.submit()">
                        </label>
                        <small class="d-block text-muted mt-2" style="font-size: 0.75rem;">Bu imza, oluşturulan PDF raporlarında otomatik olarak kullanılacaktır.</small>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Form Bilgileri -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Kişisel Bilgiler</h6>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="update_info" value="1">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Unvan</label>
                                <select name="title" class="form-select">
                                    <option value="" <?php echo empty($user['title']) ? 'selected' : ''; ?>>Seçiniz</option>
                                    <option value="Dr." <?php echo ($user['title'] == 'Dr.') ? 'selected' : ''; ?>>Dr.</option>
                                    <option value="Uzm. Dr." <?php echo ($user['title'] == 'Uzm. Dr.') ? 'selected' : ''; ?>>Uzm. Dr.</option>
                                    <option value="Op. Dr." <?php echo ($user['title'] == 'Op. Dr.') ? 'selected' : ''; ?>>Op. Dr.</option>
                                    <option value="Doç. Dr." <?php echo ($user['title'] == 'Doç. Dr.') ? 'selected' : ''; ?>>Doç. Dr.</option>
                                    <option value="Prof. Dr." <?php echo ($user['title'] == 'Prof. Dr.') ? 'selected' : ''; ?>>Prof. Dr.</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Ad Soyad</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Uzmanlık Alanı</label>
                                <input type="text" name="specialty" class="form-control" value="<?php echo htmlspecialchars($user['specialty'] ?? ''); ?>" placeholder="Örn: Kardiyoloji">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Diploma / Tescil No</label>
                                <input type="text" name="diploma_no" class="form-control" value="<?php echo htmlspecialchars($user['diploma_no'] ?? ''); ?>" placeholder="Örn: 123456">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">E-posta Adresi</label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled>
                                <div class="form-text">E-posta adresi değiştirilemez.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rol</label>
                                <input type="text" class="form-control bg-light" value="<?php echo ucfirst($user['role']); ?>" readonly disabled>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-2"></i>Değişiklikleri Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- External Hidden Form for Deletion -->
<form id="delete_photo_form" method="POST" style="display:none;">
    <input type="hidden" name="delete_photo" value="1">
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
