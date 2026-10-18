<?php
/**
 * Patient Detail View (Read-Only)
 * Hasta kendi muayene sonuçlarını detaylı görür
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/Stats.php';

checkAuth('../modules/login.php');
if (!hasRole('patient')) {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();
$stats = new Stats();
$visitId = $_GET['visit'] ?? 0;
$userId = getCurrentUserId();

// Mevcut kullanıcının hasta kaydını bul (Güvenlik Kontrolü İçin)
$patientCheckSql = "SELECT id FROM patients WHERE user_id = :user_id";
$checkStmt = $db->prepare($patientCheckSql);
$checkStmt->execute([':user_id' => $userId]);
$currentPatient = $checkStmt->fetch();

// Fallback logic if needed (optional, but good for safety)
if (!$currentPatient) {
    $dummyTC = '99' . str_pad($userId, 9, '0', STR_PAD_LEFT);
    $patientCheckSql = "SELECT id FROM patients WHERE tc_identity = :tc";
    $checkStmt = $db->prepare($patientCheckSql);
    $checkStmt->execute([':tc' => $dummyTC]);
    $currentPatient = $checkStmt->fetch();
}

if (!$currentPatient) {
    header('Location: patient_dashboard.php');
    exit();
}

$currentPatientId = $currentPatient['id'];

// Ziyaret bilgilerini al
$visitData = $stats->getPatientMetrics($visitId);

if (!$visitData) {
    header('Location: patient_dashboard.php');
    exit();
}

// Güvenlik Kontrolü: Ziyaret bu hastaya mı ait?
// Visit tablosundan patient_id kontrolü yapmalıyız
$ownerCheckSql = "SELECT patient_id FROM visits WHERE id = :visit_id";
$ownerStmt = $db->prepare($ownerCheckSql);
$ownerStmt->execute([':visit_id' => $visitId]);
$visitOwner = $ownerStmt->fetch();

if (!$visitOwner || $visitOwner['patient_id'] != $currentPatientId) {
    // Yetkisiz erişim denemesi
    header('Location: patient_dashboard.php?error=unauthorized');
    exit();
}

// Hasta bilgilerini al (Full data)
$visitSql = "SELECT v.*, p.* FROM visits v 
             INNER JOIN patients p ON v.patient_id = p.id 
             WHERE v.id = :visit_id";
$stmt = $db->prepare($visitSql);
$stmt->execute([':visit_id' => $visitId]);
$fullData = $stmt->fetch();

// Healthy averages
$healthyAvg = $stats->getHealthyAverages();

include __DIR__ . '/../includes/header.php';
?>

<style>
.patient-view-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px;
    background: #f9fafb;
    min-height: 100vh;
}

.back-button {
    margin-bottom: 20px;
}
</style>

<div class="patient-view-container">
    <!-- Geri Butonu -->
    <div class="back-button">
        <a href="patient_dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>
            Panele Dön
        </a>
    </div>

    <!-- Başlık -->
    <div class="page-header py-4 mb-4">
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-file-medical text-primary me-2"></i>
            Muayene Sonuçlarınız
        </h1>
        <p class="text-muted mb-0">
            Tarih: <?php echo formatDate($fullData['visit_date']); ?>
        </p>
    </div>

    <!-- Risk Göstergesi -->\
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-speedometer2 text-primary me-2"></i>
                        Risk Değerlendirmeniz
                    </h5>
                </div>
                <div class="card-body text-center">
                    <h2 class="mb-3">
                        <?php echo getRiskBadge($visitData['calculated_risk_score']); ?>
                    </h2>
                    <p class="text-muted">
                        <?php
                        $risk = $visitData['calculated_risk_score'];
                        if ($risk <= 33) {
                            echo "Kardiyovasküler risk seviyeniz düşüktür. Sağlıklı yaşam alışkanlıklarınızı sürdürün.";
                        } elseif ($risk <= 66) {
                            echo "Orta seviye kardiyovasküler risk tespit edildi. Doktorunuzun önerilerini uygulayın.";
                        } else {
                            echo "Yüksek kardiyovasküler risk seviyesi. Doktorunuz ile düzenli takip önemlidir.";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Ölçüm Sonuçları -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-heart-pulse text-danger me-2"></i>
                        Vital Bulgular
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Kan Basıncı:</strong>
                        <span class="float-end"><?php echo $visitData['bp']; ?> mmHg</span>
                    </div>
                    <div class="mb-3">
                        <strong>Maksimum Kalp Hızı:</strong>
                        <span class="float-end"><?php echo $visitData['hr']; ?> bpm</span>
                    </div>
                    <div class="mb-0">
                        <strong>Göğüs Ağrısı Tipi:</strong>
                        <span class="float-end"><?php echo getChestPainType($visitData['cp']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-droplet text-primary me-2"></i>
                        Laboratuvar Sonuçları
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Kolesterol:</strong>
                        <span class="float-end"><?php echo $visitData['chol']; ?> mg/dL</span>
                    </div>
                    <div class="mb-3">
                        <strong>Açlık Kan Şekeri:</strong>
                        <span class="float-end"><?php echo $visitData['fbs'] == 1 ? '> 120 mg/dL' : '< 120 mg/dL'; ?></span>
                    </div>
                    <div class="mb-0">
                        <strong>ST Çökmesi:</strong>
                        <span class="float-end"><?php echo $visitData['oldpeak']; ?> mm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Karşılaştırmalı Grafik -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-bar-chart text-success me-2"></i>
                        Değerlerinizin Karşılaştırması
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="patientRadarChart" height="300"></canvas>
                    <div class="text-center mt-3">
                        <span class="badge bg-danger me-2">
                            <i class="bi bi-circle-fill me-1"></i>Sizin Değerleriniz
                        </span>
                        <span class="badge bg-success">
                            <i class="bi bi-circle-fill me-1"></i>Sağlıklı Ortalama
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doktor Önerileri -->
    <?php if (!empty($fullData['ai_recommendation'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-clipboard-check text-warning me-2"></i>
                        Doktorunuzun Önerileri
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $recommendations = explode("\n", $fullData['ai_recommendation']);
                    foreach ($recommendations as $rec) {
                        $rec = trim($rec);
                        if (!empty($rec) && strpos($rec, '**') === false) {
                            echo "<p class='mb-2'><i class='bi bi-check-circle text-success me-2'></i>" . htmlspecialchars($rec) . "</p>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Uyarı Notu -->
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Önemli Not:</strong> Bu sonuçlar bilgilendirme amaçlıdır. Herhangi bir rahatsızlık durumunda mutlaka doktorunuz ile iletişime geçiniz.
    </div>

</div>

<!-- Chart Data -->
<div id="patientChartData" style="display: none;"
     data-patient-bp="<?php echo $visitData['bp']; ?>"
     data-patient-chol="<?php echo $visitData['chol']; ?>"
     data-patient-hr="<?php echo $visitData['hr']; ?>"
     data-patient-oldpeak="<?php echo $visitData['oldpeak']; ?>"
     data-patient-fbs="<?php echo $visitData['fbs']; ?>"
     data-healthy-bp="<?php echo $healthyAvg['bp']; ?>"
     data-healthy-chol="<?php echo $healthyAvg['chol']; ?>"
     data-healthy-hr="<?php echo $healthyAvg['hr']; ?>"
     data-healthy-oldpeak="<?php echo $healthyAvg['oldpeak']; ?>"
     data-healthy-fbs="<?php echo $healthyAvg['fbs']; ?>">
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Radar Chart için basit script
document.addEventListener('DOMContentLoaded', function() {
    var dataEl = document.getElementById('patientChartData');
    if (!dataEl) return;

    var ctx = document.getElementById('patientRadarChart');
    if(!ctx) return;

    var patientData = {
        bp: parseFloat(dataEl.dataset.patientBp),
        chol: parseFloat(dataEl.dataset.patientChol),
        hr: parseFloat(dataEl.dataset.patientHr),
        oldpeak: parseFloat(dataEl.dataset.patientOldpeak),
        fbs: parseFloat(dataEl.dataset.patientFbs)
    };

    var healthyData = {
        bp: parseFloat(dataEl.dataset.healthyBp),
        chol: parseFloat(dataEl.dataset.healthyChol),
        hr: parseFloat(dataEl.dataset.healthyHr),
        oldpeak: parseFloat(dataEl.dataset.healthyOldpeak),
        fbs: parseFloat(dataEl.dataset.healthyFbs)
    };

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Kan Basıncı', 'Kolesterol', 'Kalp Hızı', 'ST Çökmesi', 'Kan Şekeri'],
            datasets: [{
                label: 'Sizin Değerleriniz',
                data: [
                    patientData.bp,
                    patientData.chol,
                    patientData.hr,
                    patientData.oldpeak * 50,
                    patientData.fbs * 100
                ],
                backgroundColor: 'rgba(239, 68, 68, 0.2)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 2
            }, {
                label: 'Sağlıklı Ortalama',
                data: [
                    healthyData.bp,
                    healthyData.chol,
                    healthyData.hr,
                    healthyData.oldpeak * 50,
                    healthyData.fbs * 100
                ],
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 50
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
