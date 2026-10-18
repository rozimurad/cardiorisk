<?php
/**
 * Add Patient Module
 * Form to register new patient
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

checkAuth('../modules/login.php');

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tcIdentity = sanitize($_POST['tc_identity'] ?? '');
    $fullName = sanitize($_POST['full_name'] ?? '');
    $birthDate = sanitize($_POST['birth_date'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    // Validation
    if (empty($tcIdentity) || empty($fullName) || empty($birthDate) || empty($gender)) {
        $error = 'Lütfen tüm gerekli alanları doldurunuz.';
    } elseif (!validateTC($tcIdentity)) {
        $error = 'Geçersiz TC Kimlik Numarası formatı.';
    } else {
        try {
            $db = getDB();
            
            // Check if TC Identity already exists
            $checkSql = "SELECT id FROM patients WHERE tc_identity = :tc_identity";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->bindParam(':tc_identity', $tcIdentity);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $error = 'Bu TC Kimlik Numarasına sahip bir hasta zaten mevcut.';
            } else {
                $db->beginTransaction();

                // 1. Create User Account
                // Use email or fallback to TCKN@cardiorisk.local
                $userEmail = !empty($email) ? $email : $tcIdentity . '@cardiorisk.local';
                $defaultPassword = password_hash($tcIdentity, PASSWORD_DEFAULT); // Default password is TCKN
                
                // Check if user email already exists (edge case)
                $checkUser = $db->prepare("SELECT id FROM users WHERE email = ?");
                $checkUser->execute([$userEmail]);
                
                if ($checkUser->fetch()) {
                    // Update: if user exists but patient doesn't, we might just link them?
                    // For simplicity, we assume new user for new patient or return error
                    // $error = 'Bu e-posta adresi kullanımda.'; 
                    // However, to be robust, let's just create a unique email if clash?
                    // Let's assume the user input email is correct.
                    // If generated email clashes, append timestamp?
                    if (empty($email)) {
                         $userEmail = $tcIdentity . '_' . time() . '@cardiorisk.local';
                    }
                }

                $userSql = "INSERT INTO users (full_name, email, password, role) VALUES (:name, :email, :pass, 'patient')";
                $userStmt = $db->prepare($userSql);
                $userStmt->execute([
                    ':name' => $fullName,
                    ':email' => $userEmail,
                    ':pass' => $defaultPassword
                ]);
                $newUserId = $db->lastInsertId();

                // 2. Insert Patient Record
                $sql = "INSERT INTO patients (doctor_id, user_id, tc_identity, full_name, birth_date, gender, phone, email) 
                        VALUES (:doctor_id, :user_id, :tc_identity, :full_name, :birth_date, :gender, :phone, :email)";
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':doctor_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $newUserId, PDO::PARAM_INT);
                $stmt->bindParam(':tc_identity', $tcIdentity);
                $stmt->bindParam(':full_name', $fullName);
                $stmt->bindParam(':birth_date', $birthDate);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                
                if ($stmt->execute()) {
                    $db->commit();
                    $success = true;
                } else {
                    $db->rollBack();
                    $error = 'Hasta kaydı oluşturulamadı.';
                }
            }
        } catch (PDOException $e) {
            error_log("Add Patient Error: " . $e->getMessage());
            $error = 'Hasta eklenirken bir hata oluştu.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header py-4">
    <h1 class="h3 mb-0 fw-bold">
        <i class="bi bi-person-plus text-primary me-2"></i>
        Yeni Hasta Ekle
    </h1>
    <p class="text-muted mb-0">Sisteme yeni bir hasta kayıt edin</p>
</div>

<!-- Add Patient Form -->
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                
                <form method="POST" action="" id="addPatientForm">
                    
                    <!-- TC Identity -->
                    <div class="mb-3">
                        <label for="tc_identity" class="form-label fw-semibold">
                            TC Kimlik Numarası <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="tc_identity" 
                               name="tc_identity" 
                               placeholder="11 haneli TC numarası"
                               maxlength="11"
                               pattern="[0-9]{11}"
                               required>
                        <small class="text-muted">11 haneli Türk kimlik numarası</small>
                    </div>
                    
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">
                            Ad Soyad <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="full_name" 
                               name="full_name" 
                               placeholder="Hastanın adını ve soyadını giriniz"
                               required>
                    </div>
                    
                    <!-- Birth Date -->
                    <div class="mb-3">
                        <label for="birth_date" class="form-label fw-semibold">
                            Doğum Tarihi <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               class="form-control" 
                               id="birth_date" 
                               name="birth_date" 
                               max="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <!-- Gender -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Cinsiyet <span class="text-danger">*</span>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="gender" id="gender_male" value="male" required>
                            <label class="btn btn-outline-primary" for="gender_male">
                                <i class="bi bi-gender-male me-2"></i>Erkek
                            </label>
                            
                            <input type="radio" class="btn-check" name="gender" id="gender_female" value="female" required>
                            <label class="btn btn-outline-primary" for="gender_female">
                                <i class="bi bi-gender-female me-2"></i>Kadın
                            </label>
                        </div>
                    </div>
                    
                    <!-- Phone -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                Telefon Numarası
                            </label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="+90 5XX XXX XXXX">
                            <small class="text-muted">SMS bilgilendirmesi için</small>
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-semibold">
                                E-posta Adresi
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="ornek@email.com">
                            <small class="text-muted">Rapor gönderimi için</small>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-2"></i>
                            Hasta Ekle
                        </button>
                        <a href="patients.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>
                            İptal
                        </a>
                    </div>
                    
                </form>
                
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Hasta Eklendi!',
    text: 'Hasta başarıyla kaydedildi.',
    confirmButtonColor: '#0891B2'
}).then((result) => {
    window.location.href = 'patients.php';
});
</script>
<?php endif; ?>

<?php if (!empty($error)): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Hata',
    text: '<?php echo addslashes($error); ?>',
    confirmButtonColor: '#0891B2'
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
