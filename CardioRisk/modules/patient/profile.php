<?php
/**
 * Patient Health Identity Card (Profile)
 * Hastanın tıbbi kimlik kartı ve profil yönetimi
 */

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

// Auth Check & Patient Role Check
checkAuth('../../modules/login.php');
if (!hasRole('patient')) {
    header('Location: ../../modules/dashboard.php');
    exit();
}

$db = getDB();
$userId = getCurrentUserId();
$success = false;
$error = '';

// Get Patient Data Logic (Shared with Dashboard)
// Get Patient Data Logic (Prioritize User ID)
$stmt = $db->prepare("SELECT * FROM patients WHERE user_id = :user_id");
$stmt->execute([':user_id' => $userId]);
$patient = $stmt->fetch();

if (!$patient) {
    // Fallback to TC (Legacy)
    $dummyTC = '99' . str_pad($userId, 9, '0', STR_PAD_LEFT);
    $stmt = $db->prepare("SELECT * FROM patients WHERE tc_identity = :tc_identity");
    $stmt->execute([':tc_identity' => $dummyTC]);
    $patient = $stmt->fetch();
}

if (!$patient) {
    die("Patient record not found. Please contact administration.");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CASE 1: Profile Image Update
    if (isset($_POST['action']) && $_POST['action'] === 'update_photo') {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($_FILES['profile_image']['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $fileName = 'profile_' . $patient['id'] . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $fileName)) {
                    // Update only image in DB
                    $stmt = $db->prepare("UPDATE patients SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$fileName, $patient['id']]);
                    
                    // Refresh page to show new image
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Resim yüklenirken bir hata oluştu.";
                }
            } else {
                $error = "Sadece JPG, PNG ve WEBP formatları kabul edilir.";
            }
        }
    } 
    // CASE 1.5: Delete Profile Image
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_photo') {
        if (!empty($patient['profile_image'])) {
            $filePath = __DIR__ . '/../../assets/uploads/' . $patient['profile_image'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $stmt = $db->prepare("UPDATE patients SET profile_image = NULL WHERE id = ?");
            $stmt->execute([$patient['id']]);
            
            header("Location: profile.php");
            exit();
        }
    }
    // CASE 2: Text Data Update
    else {
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $bloodType = sanitize($_POST['blood_type'] ?? '');
        $height = (int)($_POST['height'] ?? 0);
        $weight = (int)($_POST['weight'] ?? 0);

        try {
            // 1. Update Patients Table
            $sql = "UPDATE patients SET 
                    email = :email,
                    phone = :phone,
                    blood_type = :blood_type,
                    height = :height,
                    weight = :weight
                    WHERE id = :id";
            
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute([
                ':email' => $email,
                ':phone' => $phone,
                ':blood_type' => $bloodType,
                ':height' => $height,
                ':weight' => $weight,
                ':id' => $patient['id']
            ]);
            
            // 2. Update Users Table (Sync Email)
            if (!empty($patient['user_id'])) {
                $userSql = "UPDATE users SET email = :email WHERE id = :user_id";
                $userStmt = $db->prepare($userSql);
                $userStmt->execute([
                    ':email' => $email,
                    ':user_id' => $patient['user_id']
                ]);
            }
            
            $success = true;
            // Re-fetch patient data to show updated values
            $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patient['id']]);
            $patient = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "Güncelleme hatası: " . $e->getMessage();
        }
    }
}

// Calculate BMI
$bmi = 0;
$bmiBadge = '';
$bmiText = 'Veri Yok';

if ($patient['height'] > 0 && $patient['weight'] > 0) {
    $heightM = $patient['height'] / 100;
    $bmi = $patient['weight'] / ($heightM * $heightM);
    
    if ($bmi > 30) {
        $bmiBadge = 'bg-danger';
        $bmiText = 'Obez';
    } elseif ($bmi > 25) {
        $bmiBadge = 'bg-warning text-dark';
        $bmiText = 'Fazla Kilolu';
    } elseif ($bmi > 18.5) {
        $bmiBadge = 'bg-success';
        $bmiText = 'Normal';
    } else {
        $bmiBadge = 'bg-info';
        $bmiText = 'Zayıf';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sağlık Kimlik Kartı - CardioRisk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-color: #0891B2; --secondary-color: #0D9488; }
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #0e7490 0%, #0891B2 100%); min-height: 100vh; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); padding: 12px 20px; margin: 4px 16px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .profile-img-container { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden; margin: 0 auto; position: relative; }
        .profile-img { width: 100%; height: 100%; object-fit: cover; }
        .profile-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.5); height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; }
        .profile-overlay:hover { background: rgba(0,0,0,0.7); }
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
                    <a class="nav-link active" href="profile.php">
                        <i class="bi bi-person-badge me-2"></i>Sağlık Kimliğim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear me-2"></i>Ayarlar
                    </a>
                </li>
                <?php 
                // Dynamically calculate root path for logout to avoid 404
                // Goes up 2 levels from /modules/patient/
                $rootPath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
                $logoutUrl = rtrim($rootPath, '/\\') . '/logout.php';
                ?>
                <li class="nav-item mt-auto">
                    <a class="nav-link text-white-50" href="<?php echo $logoutUrl; ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 fw-bold text-dark">Sağlık Kimlik Kartı</h2>
                <span class="badge bg-light text-dark border p-2">
                    <i class="bi bi-clock me-1"></i> <?php echo date('d.m.Y'); ?>
                </span>
            </div>

            <div class="row g-4">
                <!-- Left Column: Identity Card -->
                <div class="col-lg-4">
                    <div class="card h-100 bg-white">
                        <div class="card-body text-center p-4">
                            
                            <!-- Profile Image with Upload Trigger -->
                            <form id="avatarForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_photo">
                                <div class="profile-img-container mb-3" onclick="document.getElementById('profile_input').click()">
                                    <?php 
                                    $imgSrc = $patient['profile_image'] ? "../../assets/uploads/" . $patient['profile_image'] : "https://ui-avatars.com/api/?name=".urlencode($patient['full_name'])."&background=0D9488&color=fff&size=150"; 
                                    ?>
                                    <img src="<?php echo $imgSrc; ?>" class="profile-img" alt="Profil">
                                    <div class="profile-overlay">
                                        <i class="bi bi-camera text-white"></i>
                                    </div>
                                    <input type="file" name="profile_image" id="profile_input" class="d-none" onchange="document.getElementById('avatarForm').submit()">
                                </div>
                            </form>

                            <?php if ($patient['profile_image']): ?>
                            <form method="POST" class="d-inline-block">
                                <input type="hidden" name="action" value="delete_photo">
                                <button type="submit" class="btn btn-sm btn-outline-danger mb-3" onclick="return confirm('Profil fotoğrafını kaldırmak istediğinize emin misiniz?')">
                                    <i class="bi bi-trash me-1"></i>Fotoğrafı Kaldır
                                </button>
                            </form>
                            <?php endif; ?>

                            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($patient['full_name']); ?></h4>
                            <p class="text-muted mb-3">
                                <?php echo calculateAge($patient['birth_date']); ?> Yaş | 
                                <i class="bi bi-gender-<?php echo $patient['gender'] === 'male' ? 'male' : 'female'; ?>"></i>
                            </p>

                            <hr>

                            <div class="row text-start mt-4">
                                <div class="col-6 mb-3">
                                    <small class="text-muted d-block">Kan Grubu</small>
                                    <span class="fs-5 fw-semibold text-danger">
                                        <i class="bi bi-droplet-fill me-1"></i>
                                        <?php echo $patient['blood_type'] ? $patient['blood_type'] : '-'; ?>
                                    </span>
                                </div>
                                <div class="col-6 mb-3">
                                    <small class="text-muted d-block">BMI İndeksi</small>
                                    <span class="badge <?php echo $bmiBadge; ?> fs-6 mt-1">
                                        <?php echo number_format($bmi, 1); ?> - <?php echo $bmiText; ?>
                                    </span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Boy</small>
                                    <span class="fs-5 fw-semibold"><?php echo $patient['height'] ? $patient['height'] . ' cm' : '-'; ?></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Kilo</small>
                                    <span class="fs-5 fw-semibold"><?php echo $patient['weight'] ? $patient['weight'] . ' kg' : '-'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Edit Form -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0 fw-bold text-primary">
                                <i class="bi bi-pencil-square me-2"></i>Bilgileri Güncelle
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" enctype="multipart/form-data">
                                <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem;">Kişisel Bilgiler</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">E-posta Adresi</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($patient['email']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telefon Numarası</label>
                                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($patient['phone']); ?>">
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Kan Grubu</label>
                                        <select name="blood_type" class="form-select">
                                            <option value="">Seçiniz</option>
                                            <?php 
                                            $bloods = ['0 Rh-', '0 Rh+', 'A Rh-', 'A Rh+', 'B Rh-', 'B Rh+', 'AB Rh-', 'AB Rh+'];
                                            foreach ($bloods as $b) {
                                                $sel = ($patient['blood_type'] === $b) ? 'selected' : '';
                                                echo "<option value='$b' $sel>$b</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem;">Fiziksel Veriler</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Boy (cm)</label>
                                        <input type="number" name="height" class="form-control" placeholder="175" value="<?php echo $patient['height']; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kilo (kg)</label>
                                        <input type="number" name="weight" class="form-control" placeholder="70" value="<?php echo $patient['weight']; ?>">
                                    </div>
                                </div>


                                <div class="text-end">
                                    <button type="submit" id="saveBtn" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-2"></i>Değişiklikleri Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Güncellendi',
    text: 'Profil bilgileriniz başarıyla kaydedildi.',
    confirmButtonColor: '#0891B2'
});
</script>
<?php endif; ?>

</body>
</html>
