<?php
/**
 * New Visit Module
 * Advanced medical data entry form with sliders and tooltips
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/RiskCalculator.php';
require_once __DIR__ . '/../core/Recommendation.php';
require_once __DIR__ . '/../core/EmailNotifier.php';
require_once __DIR__ . '/../core/SMSNotifier.php';

checkAuth('../modules/login.php');

$db = getDB();
$doctorId = getCurrentUserId();

// Fetch Doctor's Risk Threshold
$stmtReq = $db->prepare("SELECT risk_threshold FROM users WHERE id = ?");
$stmtReq->execute([$doctorId]);
$riskThreshold = $stmtReq->fetchColumn();
if (!$riskThreshold) $riskThreshold = 50; // Default fallback

// Get all patients for this doctor
// Get all patients (Removed doctor_id filter for visibility)
$patientsSql = "SELECT id, full_name, tc_identity FROM patients ORDER BY full_name";
$patientsStmt = $db->prepare($patientsSql);
// $patientsStmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
$patientsStmt->execute();
$patients = $patientsStmt->fetchAll();

// Pre-select patient if coming from patient page
$selectedPatientId = $_GET['patient_id'] ?? '';

$success = false;
$error = '';
$visitId = 0; // YENİ!
$patientId = 0; // YENİ!

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Collect all form data
        $patientId = $_POST['patient_id'] ?? '';
        $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
        $cp = intval($_POST['cp'] ?? 0);
        $trestbps = intval($_POST['trestbps'] ?? 0);
        $chol = intval($_POST['chol'] ?? 0);
        $fbs = intval($_POST['fbs'] ?? 0);
        $restecg = intval($_POST['restecg'] ?? 0);
        $thalach = intval($_POST['thalach'] ?? 0);
        $exang = intval($_POST['exang'] ?? 0);
        $oldpeak = floatval($_POST['oldpeak'] ?? 0);
        $slope = intval($_POST['slope'] ?? 0);
        $ca = intval($_POST['ca'] ?? 0);
        $thal = intval($_POST['thal'] ?? 0);
        
        // Validate required fields
        if (empty($patientId)) {
            throw new Exception('Lütfen bir hasta seçiniz.');
        }
        
        // Prepare data for risk calculation
        $params = [
            'cp' => $cp,
            'trestbps' => $trestbps,
            'chol' => $chol,
            'fbs' => $fbs,
            'restecg' => $restecg,
            'thalach' => $thalach,
            'exang' => $exang,
            'oldpeak' => $oldpeak,
            'slope' => $slope,
            'ca' => $ca,
            'thal' => $thal
        ];
        
        // Calculate risk score
        $riskResult = RiskCalculator::calculate($params, $riskThreshold);
        $riskScore = $riskResult['score'];
        
        // Generate recommendations
        $recommendations = Recommendation::generate($params, $riskScore);
        
        // Insert visit into database
        $sql = "INSERT INTO visits (
                    patient_id, visit_date, cp, trestbps, chol, fbs, restecg, 
                    thalach, exang, oldpeak, slope, ca, thal, 
                    calculated_risk_score, ai_recommendation
                ) VALUES (
                    :patient_id, :visit_date, :cp, :trestbps, :chol, :fbs, :restecg,
                    :thalach, :exang, :oldpeak, :slope, :ca, :thal,
                    :risk_score, :ai_recommendation
                )";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindParam(':visit_date', $visitDate);
        $stmt->bindParam(':cp', $cp, PDO::PARAM_INT);
        $stmt->bindParam(':trestbps', $trestbps, PDO::PARAM_INT);
        $stmt->bindParam(':chol', $chol, PDO::PARAM_INT);
        $stmt->bindParam(':fbs', $fbs, PDO::PARAM_INT);
        $stmt->bindParam(':restecg', $restecg, PDO::PARAM_INT);
        $stmt->bindParam(':thalach', $thalach, PDO::PARAM_INT);
        $stmt->bindParam(':exang', $exang, PDO::PARAM_INT);
        $stmt->bindParam(':oldpeak', $oldpeak);
        $stmt->bindParam(':slope', $slope, PDO::PARAM_INT);
        $stmt->bindParam(':ca', $ca, PDO::PARAM_INT);
        $stmt->bindParam(':thal', $thal, PDO::PARAM_INT);
        $stmt->bindParam(':risk_score', $riskScore, PDO::PARAM_INT);
        $stmt->bindParam(':ai_recommendation', $recommendations);
        
        if ($stmt->execute()) {
            $visitId = $db->lastInsertId();
            $success = true;
            
            // YENİ! Kritik risk durumunda email gönder
            if ($riskScore >= $riskThreshold) {
                // Hasta ve doktor bilgilerini al
                $patientSql = "SELECT p.*, u.email as doctor_email, u.full_name as doctor_name 
                              FROM patients p 
                              INNER JOIN users u ON p.doctor_id = u.id 
                              WHERE p.id = :patient_id";
                $patientStmt = $db->prepare($patientSql);
                $patientStmt->execute([':patient_id' => $patientId]);
                $patientInfo = $patientStmt->fetch();
                
                if ($patientInfo) {
                    // 1. Email Gönderimi (Mevcut)
                    try {
                        $emailNotifier = new EmailNotifier();
                        $visitDataForEmail = array_merge($params, ['patient_id' => $patientId]);
                        
                        // 1. Doktora Bildirim
                        $emailNotifier->sendCriticalRiskAlert(
                            $patientInfo['doctor_email'],
                            $patientInfo['doctor_name'],
                            $patientInfo['full_name'],
                            $riskScore,
                            $visitDataForEmail
                        );

                        // 2. Hastaya Bildirim (Eğer emaili varsa)
                        if (!empty($patientInfo['email'])) {
                            // Hastaya özel formatta gönderim
                            $emailNotifier->sendPatientCriticalRiskAlert(
                                $patientInfo['email'],
                                $patientInfo['full_name'], // Hasta Adı
                                $riskScore
                            );
                        }

                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $e->getMessage());
                    }

                    // 2. SMS Gönderimi (YENİ!)
                    // Hasta telefon numarasını kontrol et
                    if (!empty($patientInfo['phone'])) {
                        try {
                            $smsNotifier = new SMSNotifier();
                            $smsResult = $smsNotifier->sendCriticalRiskSMS(
                                $patientInfo['phone'],
                                $patientInfo['full_name'],
                                $riskScore,
                                $patientInfo['doctor_name']
                            );
                            
                            if ($smsResult) {
                                // Başarılı SMS gönderimini session'da işaretle (UI için)
                                $_SESSION['sms_sent'] = true;
                            }
                        } catch (Exception $e) {
                            error_log("SMS sending failed: " . $e->getMessage());
                        }
                    }
                    
                    error_log("Critical risk alerts processed for patient ID: $patientId");
                }
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("New Visit Error: " . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header py-4">
    <h1 class="h3 mb-0 fw-bold">
        <i class="bi bi-clipboard2-pulse text-primary me-2"></i>
        Yeni Ziyaret
    </h1>
    <p class="text-muted mb-0">Risk değerlendirmesi için hasta muayene verilerin girişi</p>
</div>

<!-- Visit Form -->
<form method="POST" action="" id="newVisitForm">
    <div class="row">
        
        <!-- Left Column -->
        <div class="col-lg-6">
            
            <!-- Patient Selection Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-check me-2"></i>
                        Hasta Seçimi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="patient_id" class="form-label fw-semibold">
                            Hasta Seçiniz <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">-- Bir hasta seçiniz --</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    <?php echo ($patient['id'] == $selectedPatientId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name']); ?> 
                                (<?php echo htmlspecialchars($patient['tc_identity']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-0">
                        <label for="visit_date" class="form-label fw-semibold">Ziyaret Tarihi</label>
                        <input type="date" 
                               class="form-control" 
                               id="visit_date" 
                               name="visit_date" 
                               value="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Vital Signs Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-heart-pulse me-2"></i>
                        Vital Bulgular
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Blood Pressure -->
                    <div class="mb-4">
                        <label for="trestbps" class="form-label fw-semibold">
                            Dinlenme Kan Basıncı (mm Hg)
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Dinlenme halindeki kan basıncı. Normal: 90-120 mmHg"></i>
                        </label>
                        <input type="range" 
                               class="form-range" 
                               id="trestbps" 
                               name="trestbps" 
                               min="90" 
                               max="200" 
                               value="120" 
                               step="1">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">90</small>
                            <strong class="text-primary">
                                <span id="trestbps_value">120</span> mm Hg
                            </strong>
                            <small class="text-muted">200</small>
                        </div>
                    </div>
                    
                    <!-- Max Heart Rate -->
                    <div class="mb-0">
                        <label for="thalach" class="form-label fw-semibold">
                            Ulaşılan Maksimum Kalp Hızı
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Egzersiz testi sırasında en yüksek kalp hızı. Yüksek olması genelde daha iyidir."></i>
                        </label>
                        <input type="range" 
                               class="form-range" 
                               id="thalach" 
                               name="thalach" 
                               min="60" 
                               max="220" 
                               value="150" 
                               step="1">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">60</small>
                            <strong class="text-primary">
                                <span id="thalach_value">150</span> bpm
                            </strong>
                            <small class="text-muted">220</small>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Laboratory Results Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-droplet-half me-2"></i>
                        Laboratuvar Sonuçları
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Cholesterol -->
                    <div class="mb-4">
                        <label for="chol" class="form-label fw-semibold">
                            Serum Kolesterol (mg/dL)
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Toplam kolesterol seviyesi. İdeal: <200 mg/dL"></i>
                        </label>
                        <input type="range" 
                               class="form-range" 
                               id="chol" 
                               name="chol" 
                               min="120" 
                               max="400" 
                               value="200" 
                               step="1">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">120</small>
                            <strong class="text-primary">
                                <span id="chol_value">200</span> mg/dL
                            </strong>
                            <small class="text-muted">400</small>
                        </div>
                    </div>
                    
                    <!-- Fasting Blood Sugar -->
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Aç Kan Şekeri > 120 mg/dL?
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Yüksekse diyabeti gösterir"></i>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="fbs" id="fbs_no" value="0" checked>
                            <label class="btn btn-outline-success" for="fbs_no">Hayır (< 120)</label>
                            
                            <input type="radio" class="btn-check" name="fbs" id="fbs_yes" value="1">
                            <label class="btn btn-outline-danger" for="fbs_yes">Evet (> 120)</label>
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-6">
            
            <!-- Cardiac Tests Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-activity me-2"></i>
                        Kardiyak Testler
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Chest Pain Type -->
                    <div class="mb-3">
                        <label for="cp" class="form-label fw-semibold">
                            Göğüs Ağrısı Tipi
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Göğüs ağrısı semptomlarının sınıflandırılması"></i>
                        </label>
                        <select class="form-select" id="cp" name="cp" required>
                            <option value="1">Tipik Anjina</option>
                            <option value="2">Atipik Anjina</option>
                            <option value="3">Anjinal Olmayan Ağrı</option>
                            <option value="4">Asemptomatik</option>
                        </select>
                    </div>
                    
                    <!-- Resting ECG -->
                    <div class="mb-3">
                        <label for="restecg" class="form-label fw-semibold">
                            Dinlenme EKG Sonuçları
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Dinlenme halinde elektrokardiyogram bulguları"></i>
                        </label>
                        <select class="form-select" id="restecg" name="restecg" required>
                            <option value="0">Normal</option>
                            <option value="1">ST-T Dalga Anormalliği</option>
                            <option value="2">Sol Ventriküler Hipertrofi</option>
                        </select>
                    </div>
                    
                    <!-- Exercise Induced Angina -->
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Egzersizle Tetiklenen Anjina?
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Fiziksel eforla tetiklenen göğüs ağrısı"></i>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="exang" id="exang_no" value="0" checked>
                            <label class="btn btn-outline-success" for="exang_no">Hayır</label>
                            
                            <input type="radio" class="btn-check" name="exang" id="exang_yes" value="1">
                            <label class="btn btn-outline-danger" for="exang_yes">Evet</label>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Advanced Metrics Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Gelişmiş Metrikler
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- ST Depression (Oldpeak) -->
                    <div class="mb-4">
                        <label for="oldpeak" class="form-label fw-semibold">
                            ST Çökmesi (Oldpeak)
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Egzersizin neden olduğu ST çökmesi. Düşük olması daha iyidir."></i>
                        </label>
                        <input type="range" 
                               class="form-range" 
                               id="oldpeak" 
                               name="oldpeak" 
                               min="0" 
                               max="6.2" 
                               value="0" 
                               step="0.1">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">0.0</small>
                            <strong class="text-primary">
                                <span id="oldpeak_value">0.0</span>
                            </strong>
                            <small class="text-muted">6.2</small>
                        </div>
                    </div>
                    
                    <!-- Peak Exercise ST Segment Slope -->
                    <div class="mb-3">
                        <label for="slope" class="form-label fw-semibold">
                            Egzersiz Zirvesi ST Segment Eğimi
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Stres testi sırasında eğim modeli"></i>
                        </label>
                        <select class="form-select" id="slope" name="slope" required>
                            <option value="1">Yükselen (En İyi)</option>
                            <option value="2">Düz</option>
                            <option value="3">Alçalan (En Kötü)</option>
                        </select>
                    </div>
                    
                    <!-- Number of Major Vessels -->
                    <div class="mb-3">
                        <label for="ca" class="form-label fw-semibold">
                            Renklenen Ana Damar Sayısı (0-3)
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Floroskopi ile tespit edilen tıkanmış damarlar"></i>
                        </label>
                        <select class="form-select" id="ca" name="ca" required>
                            <option value="0">0 damar</option>
                            <option value="1">1 damar</option>
                            <option value="2">2 damar</option>
                            <option value="3">3 damar</option>
                        </select>
                    </div>
                    
                    <!-- Thalassemia -->
                    <div class="mb-0">
                        <label for="thal" class="form-label fw-semibold">
                            Talasemi
                            <i class="bi bi-question-circle text-primary" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Hemoglobin üretimini etkileyen kan bozukluğu"></i>
                        </label>
                        <select class="form-select" id="thal" name="thal" required>
                            <option value="3">Normal</option>
                            <option value="6">Sabit Defekt</option>
                            <option value="7">Geri Dönüşümlü Defekt</option>
                        </select>
                    </div>
                    
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="card border-0 shadow-sm bg-gradient-primary">
                <div class="card-body text-center p-4">
                    <button type="submit" class="btn btn-light btn-lg px-5">
                        <i class="bi bi-calculator me-2"></i>
                        Risk Hesapla ve Ziyareti Kaydet
                    </button>
                </div>
            </div>
            
        </div>
        
    </div>
</form>

<!-- Success/Error Handling -->
<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Ziyaret Kaydedildi!',
    html: 'Risk değerlendirmesi başarıyla tamamlandı.<br>' + 
          '<?php 
             if (isset($_SESSION['sms_sent'])) {
                 echo "<br><strong>📱 SMS BİLGİLENDİRMESİ:</strong><br>Hastaya kritik risk uyarısı SMS olarak gönderildi.";
                 unset($_SESSION['sms_sent']);
             }
           ?>',
    confirmButtonColor: '#0891B2'
}).then((result) => {
    window.location.href = 'patient_detail.php?id=<?php echo $patientId; ?>&visit=<?php echo $visitId; ?>';
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
