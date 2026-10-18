<?php
/**
 * Patient Detail Module  
 * Comprehensive analytics dashboard - THE CENTERPIECE OF THE APPLICATION
 * Features: Radar chart, risk gauge, body heatmap, what-if simulator
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/Stats.php';
require_once __DIR__ . '/../core/RiskCalculator.php';

checkAuth('../modules/login.php');

$db = getDB();
$stats = new Stats();

// Get patient ID and visit ID from URL
$patientId = $_GET['id'] ?? 0;
$visitId = $_GET['visit'] ?? 0;

// Fetch patient information
$patientSql = "SELECT p.*, u.full_name as doctor_name 
               FROM patients p 
               LEFT JOIN users u ON p.doctor_id = u.id 
               WHERE p.id = :patient_id";
$patientStmt = $db->prepare($patientSql);
$patientStmt->bindParam(':patient_id', $patientId, PDO::PARAM_INT);
$patientStmt->execute();
$patient = $patientStmt->fetch();

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Get all visits for this patient
$allVisits = $stats->getPatientVisits($patientId);

// If no specific visit selected, use the latest one
if (empty($visitId) && count($allVisits) > 0) {
    $visitId = $allVisits[0]['id'];
}

// Fetch visit data
$visitData = $stats->getPatientMetrics($visitId);

if (!$visitData) {
    header('Location: patients.php');
    exit();
}

// Get healthy averages for comparison
$healthyAvg = $stats->getHealthyAverages();

// Calculate age
$age = calculateAge($patient['birth_date']);

// YENİ! Yaş ve cinsiyet bazlı peer comparison
$gender = $patient['gender'] === 'male' ? 1 : 0;
$peerComparison = $stats->compareWithPeers($visitData, $age, $gender);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header py-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
            <h1 class="h3 mb-1 fw-bold">
                <i class="bi bi-person-circle text-primary me-2"></i>
                <?php echo htmlspecialchars($patient['full_name']); ?>
            </h1>
            <p class="text-muted mb-0">
                <span class="me-3">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?php echo $age; ?> yaş
                </span>
                <span class="me-3">
                    <i class="bi bi-gender-<?php echo $patient['gender'] === 'male' ? 'male' : 'female'; ?> me-1"></i>
                    <?php echo $patient['gender'] === 'male' ? 'Erkek' : 'Kadın'; ?>
                </span>
                <span>
                    <i class="bi bi-telephone me-1"></i>
                    <?php echo htmlspecialchars($patient['phone']); ?>
                </span>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <div class="btn-group">
                <a href="new_visit.php?patient_id=<?php echo $patientId; ?>" class="btn btn-primary">
                    <i class="bi bi-clipboard2-plus me-2"></i>Yeni Ziyaret
               </a>
                <a href="report.php?visit=<?php echo $visitId; ?>" class="btn btn-outline-primary" target="_blank">
                    <i class="bi bi-file-pdf me-2"></i>PDF Oluştur
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Visit Selector -->
<?php if (count($allVisits) > 1): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info d-flex align-items-center">
            <i class="bi bi-info-circle me-3 fs-4"></i>
            <div class="flex-grow-1">
                <strong>Ziyaret Seçiniz:</strong>
                <select class="form-select form-select-sm d-inline-block w-auto ms-2" 
                        onchange="window.location.href='patient_detail.php?id=<?php echo $patientId; ?>&visit=' + this.value">
                    <?php foreach ($allVisits as $visit): ?>
                    <option value="<?php echo $visit['id']; ?>" <?php echo ($visit['id'] == $visitId) ? 'selected' : ''; ?>>
                        <?php echo $visit['formatted_date']; ?> - Risk: <?php echo $visit['calculated_risk_score']; ?>%
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- İlerleme Takibi (Progress Timeline) - YENI! -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                    İlerleme Takibi ve Zaman Tüneli
                </h5>
            </div>
            <div class="card-body">
                <canvas id="progressChart" height="80"></canvas>
                
                <?php
                // İlerleme analizi
                if (count($allVisits) >= 2) {
                    $latest = $allVisits[0];
                    $previous = $allVisits[1];
                    $riskChange = $latest['calculated_risk_score'] - $previous['calculated_risk_score'];
                    $changePercent = $previous['calculated_risk_score'] > 0 
                        ? round(($riskChange / $previous['calculated_risk_score']) * 100, 1) 
                        : 0;
                ?>
                <div class="alert alert-<?php echo $riskChange < 0 ? 'success' : ($riskChange > 0 ? 'danger' : 'info'); ?> mt-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <i class="bi bi-<?php echo $riskChange < 0 ? 'arrow-down-circle' : ($riskChange > 0 ? 'arrow-up-circle' : 'dash-circle'); ?> fs-1"></i>
                        </div>
                        <div class="col-md-8">
                            <h6 class="mb-2">
                                <?php if ($riskChange < 0): ?>
                                    ✅ Harika İlerleme!
                                <?php elseif ($riskChange > 0): ?>
                                    ⚠️ Dikkat! Risk Artışı
                                <?php else: ?>
                                    ℹ️ Stabil Durum
                                <?php endif; ?>
                            </h6>
                            <p class="mb-0">
                                Risk skorunuz <strong><?php echo $previous['calculated_risk_score']; ?>%</strong>'den 
                                <strong><?php echo $latest['calculated_risk_score']; ?>%</strong>'ye 
                                <?php echo $riskChange < 0 ? 'düştü' : ($riskChange > 0 ? 'yükseldi' : 'değişmedi'); ?>
                                <?php if ($riskChange != 0): ?>
                                    (<?php echo abs($changePercent); ?>% <?php echo $riskChange < 0 ? 'azalma' : 'artış'; ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Dashboard -->
<div class="row g-4">
    
    <!-- Left Column - Charts & Visualizations -->
    <div class="col-lg-8">
        
        <!-- Risk Score Gauge -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-speedometer2 text-primary me-2"></i>
                    Risk Değerlendirmesi
                </h5>
            </div>
            <div class="card-body text-center">
                <div style="max-width: 400px; margin: 0 auto;">
                    <canvas id="riskGauge"></canvas>
                </div>
                <h2 class="mt-3 mb-1">
                    <?php echo getRiskBadge($visitData['calculated_risk_score']); ?>
                </h2>
                <p class="text-muted">Mevcut kardiyovasküler risk değerlendirmesi</p>
            </div>
        </div>
        
        <!-- Comparative Radar Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-radar text-primary me-2"></i>
                    Karşılaştırmalı Analiz
                </h5>
            </div>
            <div class="card-body">
                <canvas id="radarChart" height="300"></canvas>
                <div class="text-center mt-3">
                    <span class="badge bg-danger me-2">
                        <i class="bi bi-circle-fill me-1"></i>Hasta Değerleri
                    </span>
                    <span class="badge bg-success">
                        <i class="bi bi-circle-fill me-1"></i>Sağlıklı Ortalama
                    </span>
                </div>
            </div>
        </div>
        
        <!-- YENİ! Peer Comparison - Yaş Grubu Kıyaslaması -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-people text-primary me-2"></i>
                    Sizin Gibi Hastalar (Yaş Grubu Kıyaslaması)
                </h5>
            </div>
            <div class="card-body">
                <?php if ($peerComparison['peer_info']['is_peer_specific']): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Kıyaslama Grubu:</strong> 
                    <?php echo $peerComparison['peer_info']['age_range']; ?> yaş arası 
                    <?php echo $peerComparison['peer_info']['gender']; ?> 
                    (<?php echo $peerComparison['peer_info']['peer_count']; ?> kişi)
                </div>
                
                <!-- Kolesterol Karşılaştırması -->
                <div class="mb-4">
                    <h6 class="fw-semibold mb-3">
                        <i class="bi bi-droplet text-warning me-2"></i>
                        Kolesterol Analizi
                    </h6>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Sizin Değeriniz</small>
                                <h4 class="mb-0 text-danger"><?php echo $peerComparison['cholesterol']['value']; ?></h4>
                                <small>mg/dL</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Genel Ortalama</small>
                                <h4 class="mb-0"><?php echo round($peerComparison['cholesterol']['general_avg']); ?></h4>
                                <small>mg/dL</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-success bg-opacity-10 rounded border border-success">
                                <small class="text-success d-block fw-bold">Yaş Grubunuz</small>
                                <h4 class="mb-0 text-success"><?php echo round($peerComparison['cholesterol']['peer_avg']); ?></h4>
                                <small>mg/dL</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-<?php echo abs($peerComparison['cholesterol']['vs_peer']) > 10 ? 'warning' : 'success'; ?> alert-sm">
                        <i class="bi bi-lightbulb me-2"></i>
                        <?php echo $peerComparison['cholesterol']['message']; ?>
                    </div>
                </div>
                
                <!-- Kan Basıncı Karşılaştırması -->
                <div class="mb-0">
                    <h6 class="fw-semibold mb-3">
                        <i class="bi bi-heart-pulse text-danger me-2"></i>
                        Kan Basıncı Analizi
                    </h6>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Sizin Değeriniz</small>
                                <h4 class="mb-0 text-danger"><?php echo $peerComparison['blood_pressure']['value']; ?></h4>
                                <small>mmHg</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-light rounded">
                                <small class="text-muted d-block">Genel Ortalama</small>
                                <h4 class="mb-0"><?php echo round($peerComparison['blood_pressure']['general_avg']); ?></h4>
                                <small>mmHg</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3 bg-success bg-opacity-10 rounded border border-success">
                                <small class="text-success d-block fw-bold">Yaş Grubunuz</small>
                                <h4 class="mb-0 text-success"><?php echo round($peerComparison['blood_pressure']['peer_avg']); ?></h4>
                                <small>mmHg</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-<?php echo abs($peerComparison['blood_pressure']['vs_peer']) > 10 ? 'warning' : 'success'; ?> alert-sm">
                        <i class="bi bi-lightbulb me-2"></i>
                        <?php echo $peerComparison['blood_pressure']['message']; ?>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Yaş/cinsiyet grubunuzda yeterli karşılaştırma verisi bulunamadı. Genel ortalama kullanılıyor.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Body Heatmap Visualization -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>
                    Semptom Lokalizasyonu
                </h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center">
                        <!-- Simple SVG Body Diagram -->
                        <svg viewBox="0 0 200 400" style="max-width: 200px; height: auto;">
                            <!-- Head -->
                            <circle cx="100" cy="30" r="25" fill="#E2E8F0" stroke="#94A3B8" stroke-width="2"/>
                            
                            <!-- Neck -->
                            <rect x="90" y="52" width="20" height="15" fill="#E2E8F0" stroke="#94A3B8" stroke-width="2"/>
                            
                            <!-- Torso -->
                            <ellipse cx="100" cy="120" rx="45" ry="60" 
                                     fill="<?php echo ($visitData['cp'] > 1) ? '#FCA5A5' : '#E2E8F0'; ?>" 
                                     stroke="#94A3B8" stroke-width="2"/>
                            
                            <!-- Chest/Heart Area (highlight if chest pain) -->
                            <?php if ($visitData['cp'] >= 2): ?>
                            <circle cx="100" cy="110" r="20" fill="#EF4444" opacity="0.7">
                                <animate attributeName="opacity" values="0.5;0.9;0.5" dur="2s" repeatCount="indefinite"/>
                            </circle>
                            <?php endif; ?>
                            
                            <!-- Arms -->
                            <rect x="50" y="70" width="15" height="80" rx="7" fill="#E2E8F0" stroke="#94A3B8" stroke-width="2"/>
                            <rect x="135" y="70" width="15" height="80" rx="7" fill="#E2E8F0" stroke="#94A3B8" stroke-width="2"/>
                            
                            <!-- Legs (highlight if exercise angina) -->
                            <rect x="75" y="175" width="20" height="120" rx="10" 
                                  fill="<?php echo ($visitData['exang'] == 1) ? '#FCA5A5' : '#E2E8F0'; ?>" 
                                  stroke="#94A3B8" stroke-width="2"/>
                            <rect x="105" y="175" width="20" height="120" rx="10" 
                                  fill="<?php echo ($visitData['exang'] == 1) ? '#FCA5A5' : '#E2E8F0'; ?>" 
                                  stroke="#94A3B8" stroke-width="2"/>
                            
                            <!-- Heart Icon -->
                            <text x="93" y="120" font-size="24" fill="#DC2626">❤</text>
                        </svg>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-3">Semptom Göstergeleri:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-circle-fill text-<?php echo ($visitData['cp'] >= 2) ? 'danger' : 'success'; ?> me-2"></i>
                                <strong>Göğüs Ağrısı:</strong> 
                                <?php echo getChestPainType($visitData['cp']); ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-circle-fill text-<?php echo ($visitData['exang'] == 1) ? 'danger' : 'success'; ?> me-2"></i>
                                <strong>Egzersiz Anjinası:</strong> 
                                <?php echo ($visitData['exang'] == 1) ? 'Var' : 'Yok'; ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-circle-fill text-<?php echo ($visitData['oldpeak'] > 1.0) ? 'danger' : 'success'; ?> me-2"></i>
                                <strong>ST Çökmesi:</strong> 
                                <?php echo $visitData['oldpeak']; ?>mm
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Right Column - What-If Simulator & Recommendations -->
    <div class="col-lg-4">
        
        <!-- What-If Risk Simulator -->
        <div class="card border-0 shadow-sm mb-4 bg-gradient-secondary">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-sliders me-2"></i>
                    Senaryo Simülatörü
                </h5>
            </div>
            <div class="card-body">
                <p class="small mb-3">Olası risk değişikliklerini görmek için parametreleri ayarlayın:</p>
                
                <!-- Cholesterol Slider -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Hedef Kolesterol</label>
                    <input type="range" 
                           class="form-range" 
                           id="sim_chol" 
                           min="120" 
                           max="400" 
                           value="<?php echo $visitData['chol']; ?>" 
                           step="5">
                    <div class="d-flex justify-content-between">
                        <small>Mevcut: <strong><?php echo $visitData['chol']; ?></strong></small>
                        <small>Hedef: <strong><span id="sim_chol_value"><?php echo $visitData['chol']; ?></span></strong></small>
                    </div>
                </div>
                
                <!-- Blood Pressure Slider -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Hedef Kan Basıncı</label>
                    <input type="range" 
                           class="form-range" 
                           id="sim_bp" 
                           min="90" 
                           max="200" 
                           value="<?php echo $visitData['bp']; ?>" 
                           step="5">
                    <div class="d-flex justify-content-between">
                        <small>Mevcut: <strong><?php echo $visitData['bp']; ?></strong></small>
                        <small>Hedef: <strong><span id="sim_bp_value"><?php echo $visitData['bp']; ?></span></strong></small>
                    </div>
                </div>
                
                <!-- Heart Rate Slider -->
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Hedef Maks Kalp Hızı</label>
                    <input type="range" 
                           class="form-range" 
                           id="sim_hr" 
                           min="60" 
                           max="220" 
                           value="<?php echo $visitData['hr']; ?>" 
                           step="5">
                    <div class="d-flex justify-content-between">
                        <small>Mevcut: <strong><?php echo $visitData['hr']; ?></strong></small>
                        <small>Hedef: <strong><span id="sim_hr_value"><?php echo $visitData['hr']; ?></span></strong></small>
                    </div>
                </div>
                
                <!-- Calculate Button -->
                <button onclick="simulateRisk()" class="btn btn-light w-100 mb-3">
                    <i class="bi bi-calculator me-2"></i>
                    Riski Yeniden Hesapla
                </button>
                
                <!-- Simulation Result -->
                <div id="simulationResult" class="alert alert-info d-none">
                    <strong>Olaganlaştırılmış Sonuç:</strong>
                    <div id="simResultText" class="mt-2"></div>
                </div>
                
                <!-- Reset Button -->
                <!-- Reset Button -->
                <button onclick="resetSimulation()" class="btn btn-outline-light btn-sm w-100 text-dark">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>
                    Orijinale Sıfırla
                </button>
            </div>
        </div>
        
        <!-- AI Recommendations -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-robot text-primary me-2"></i>
                    Yapay Zeka Önerileri
                </h5>
            </div>
            <div class="card-body">
                <?php
                $visitFullSql = "SELECT * FROM visits WHERE id = :visit_id";
                $visitFullStmt = $db->prepare($visitFullSql);
                $visitFullStmt->bindParam(':visit_id', $visitId, PDO::PARAM_INT);
                $visitFullStmt->execute();
                $fullVisit = $visitFullStmt->fetch();
                
                $recommendations = $fullVisit['ai_recommendation'];
                
                // Parse and format recommendations
                $recLines = explode("\n", $recommendations);
                $currentPriority = '';
                
                foreach ($recLines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, '**KRİTİK') !== false) {
                        echo '<h6 class="text-danger mt-3 mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Kritik Öncelik</h6>';
                    } elseif (strpos($line, '**YÜKSEK') !== false) {
                        echo '<h6 class="text-warning mt-3 mb-2"><i class="bi bi-exclamation-circle-fill me-2"></i>Yüksek Öncelik</h6>';
                    } elseif (strpos($line, '**ORTA') !== false) {
                        echo '<h6 class="text-info mt-3 mb-2"><i class="bi bi-info-circle-fill me-2"></i>Orta Öncelik</h6>';
                    } elseif (strpos($line, '**RUTİN') !== false) {
                        echo '<h6 class="text-secondary mt-3 mb-2"><i class="bi bi-check-circle-fill me-2"></i>Rutin Bakım</h6>';
                    } elseif (strpos($line, '**') === false) {
                        // Regular recommendation line
                        $line = str_replace(['⚠️', '🔴', '✓'], '', $line);
                        echo '<p class="small mb-2">' . nl2br(htmlspecialchars($line)) . '</p>';
                    }
                }
                ?>
            </div>
        </div>
        
    </div>
    
</div>

<!-- Hidden data for JavaScript -->
<div id="patientData" style="display: none;"
     data-patient-bp="<?php echo $visitData['bp']; ?>"
     data-patient-chol="<?php echo $visitData['chol']; ?>"
     data-patient-hr="<?php echo $visitData['hr']; ?>"
     data-patient-oldpeak="<?php echo $visitData['oldpeak']; ?>"
     data-patient-fbs="<?php echo $visitData['fbs']; ?>"
     data-patient-cp="<?php echo $visitData['cp']; ?>"
     data-patient-exang="<?php echo $visitData['exang']; ?>"
     data-patient-restecg="<?php echo $visitData['restecg']; ?>"
     data-patient-slope="<?php echo $visitData['slope']; ?>"
     data-patient-ca="<?php echo $visitData['ca']; ?>"
     data-patient-thal="<?php echo $visitData['thal']; ?>"
     data-patient-risk="<?php echo $visitData['calculated_risk_score']; ?>"
     data-healthy-bp="<?php echo $healthyAvg['bp']; ?>"
     data-healthy-chol="<?php echo $healthyAvg['chol']; ?>"
     data-healthy-hr="<?php echo $healthyAvg['hr']; ?>"
     data-healthy-oldpeak="<?php echo $healthyAvg['oldpeak']; ?>"
     data-healthy-fbs="<?php echo $healthyAvg['fbs']; ?>">
</div>

<?php if (count($allVisits) > 1): ?>
<!-- Progress Chart Data (Line Chart için) -->
<script>
window.visitHistoryData = {
    dates: [<?php 
        $reversedVisits = array_reverse($allVisits);
        foreach ($reversedVisits as $index => $visit) {
            echo "'" . $visit['formatted_date'] . "'";
            if ($index < count($reversedVisits) - 1) echo ", ";
        }
    ?>],
    riskScores: [<?php 
        foreach ($reversedVisits as $index => $visit) {
            echo $visit['calculated_risk_score'];
            if ($index < count($reversedVisits) - 1) echo ", ";
        }
    ?>],
    cholesterol: [<?php 
        foreach ($reversedVisits as $index => $visit) {
            $vStmt = $db->prepare("SELECT chol FROM visits WHERE id = :id");
            $vStmt->execute([':id' => $visit['id']]);
            $vData = $vStmt->fetch();
            echo $vData['chol'] ?? 0;
            if ($index < count($reversedVisits) - 1) echo ", ";
        }
    ?>],
    bloodPressure: [<?php 
        foreach ($reversedVisits as $index => $visit) {
            $vStmt = $db->prepare("SELECT trestbps FROM visits WHERE id = :id");
            $vStmt->execute([':id' => $visit['id']]);
            $vData = $vStmt->fetch();
            echo $vData['trestbps'] ?? 0;
            if ($index < count($reversedVisits) - 1) echo ", ";
        }
    ?>]
};
</script>
<?php endif; ?>

<script src="../assets/js/charts.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
