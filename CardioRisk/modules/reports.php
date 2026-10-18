<?php
/**
 * Clinical Reports & Analytics Module
 * Provides insights into patient population and risk distribution.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Auth Check
checkAuth('../modules/login.php');
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    die('Erişim yetkiniz yok.');
}

$db = getDB();
$doctorId = $_SESSION['user_id'];

// --- CSV EXPORT LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $filename = 'klinik_verileri_' . date('Y-md') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Hasta ID', 'Cinsiyet', 'Doğum Tarihi', 'Ziyaret Tarihi', 
        'Göğüs Ağrısı Tip', 'Kan Basıncı', 'Kolesterol', 'Açlık Şekeri', 
        'EKG', 'Max Nabız', 'Egzersiz Anjina', 'ST Çökmesi', 'Slope', 
        'Damar Sayısı', 'Talassemi', 'Risk Skoru'
    ]);
    
    // Data Query
    $sql = "SELECT p.id, p.gender, p.birth_date, v.visit_date, 
            v.cp, v.trestbps, v.chol, v.fbs, v.restecg, v.thalach, 
            v.exang, v.oldpeak, v.slope, v.ca, v.thal, v.calculated_risk_score
            FROM visits v 
            JOIN patients p ON v.patient_id = p.id 
            WHERE p.doctor_id = :message OR :is_admin = 1
            ORDER BY v.visit_date DESC";
            
    $stmt = $db->prepare($sql);
    $isAdmin = ($_SESSION['role'] === 'admin') ? 1 : 0;
    $stmt->bindParam(':message', $doctorId); // using $doctorId (variable name fix in bind)
    $stmt->bindParam(':is_admin', $isAdmin);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// --- DATA FETCHING FOR CHARTS ---

// 1. Risk Distribution
$sqlRisk = "SELECT 
    CASE 
        WHEN calculated_risk_score < 30 THEN 'Düşük Risk (<30)'
        WHEN calculated_risk_score BETWEEN 30 AND 70 THEN 'Orta Risk (30-70)'
        ELSE 'Yüksek Risk (>70)'
    END as risk_group,
    COUNT(*) as count
    FROM (
        SELECT calculated_risk_score 
        FROM visits v 
        JOIN patients p ON v.patient_id = p.id
        WHERE p.doctor_id = :did1 OR :is_admin1 = 1
        -- Her hastanın sadece SON ziyaretini alırsak daha doğru olur ama basitlik için tüm ziyaretler
        -- veya GROUP BY patient_id ile sonuncuyu alabiliriz. Şimdilik genel dağılım.
    ) as risk_data
    GROUP BY risk_group";

$stmtRisk = $db->prepare($sqlRisk);
$isAdmin = ($_SESSION['role'] === 'admin') ? 1 : 0;
$stmtRisk->bindParam(':did1', $doctorId);
$stmtRisk->bindParam(':is_admin1', $isAdmin);
$stmtRisk->execute();
$riskStats = $stmtRisk->fetchAll(PDO::FETCH_KEY_PAIR);

// Prepare for JS
$labelsRisk = json_encode(array_keys($riskStats));
$dataRisk = json_encode(array_values($riskStats));


// 2. Age Demographics
$sqlAge = "SELECT 
    CASE 
        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 40 THEN '20-39'
        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 40 AND 59 THEN '40-59'
        ELSE '60+'
    END as age_group,
    COUNT(*) as count
    FROM patients
    WHERE doctor_id = :did2 OR :is_admin2 = 1
    GROUP BY age_group
    ORDER BY age_group";

$stmtAge = $db->prepare($sqlAge);
$stmtAge->bindParam(':did2', $doctorId);
$stmtAge->bindParam(':is_admin2', $isAdmin);
$stmtAge->execute();
$ageStats = $stmtAge->fetchAll(PDO::FETCH_KEY_PAIR);

$labelsAge = json_encode(array_keys($ageStats));
$dataAge = json_encode(array_values($ageStats));


// 3. High Risk List
$sqlAlert = "SELECT p.id, p.full_name, p.birth_date, p.phone, v.calculated_risk_score, v.visit_date
             FROM visits v
             JOIN patients p ON v.patient_id = p.id
             WHERE (p.doctor_id = :did3 OR :is_admin3 = 1)
             AND v.calculated_risk_score > 70
             ORDER BY v.visit_date DESC
             LIMIT 20";

$stmtAlert = $db->prepare($sqlAlert);
$stmtAlert->bindParam(':did3', $doctorId);
$stmtAlert->bindParam(':is_admin3', $isAdmin);
$stmtAlert->execute();
$highRiskPatients = $stmtAlert->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Raporlar ve Klinik Analiz</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?action=export" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>
            Verileri İndir (CSV)
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
            <i class="bi bi-pie-chart-fill me-2"></i>Klinik İstatistikleri
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Yüksek Risk Alarmları
            <?php if(count($highRiskPatients) > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?php echo count($highRiskPatients); ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab">
            <i class="bi bi-database-down me-2"></i>Veri Yönetimi
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="reportTabsContent">
    
    <!-- Tab 1: Statistics -->
    <div class="tab-pane fade show active" id="stats" role="tabpanel">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Risk Grubu Dağılımı</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="riskPieChart"></canvas>
                        <div class="mt-3 text-center text-muted small">
                            Hasta popülasyonunuzun risk profili
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Hasta Demografisi (Yaş)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ageBarChart"></canvas>
                        <div class="mt-3 text-center text-muted small">
                            Yaş gruplarına göre hasta dağılımı
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Alerts -->
    <div class="tab-pane fade" id="alerts" role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>Acil Aksiyon Gerektiren Hastalar (>70 Risk)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Hasta Adı</th>
                                <th>Yaş</th>
                                <th>Telefon</th>
                                <th class="text-center">Risk Skoru</th>
                                <th>Son Muayene</th>
                                <th class="text-end">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($highRiskPatients) > 0): ?>
                                <?php foreach ($highRiskPatients as $p): 
                                    $age = date_diff(date_create($p['birth_date']), date_create('today'))->y;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                    <td><?php echo $age; ?></td>
                                    <td><?php echo htmlspecialchars($p['phone']); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-danger" style="font-size: 1em;">
                                            %<?php echo $p['calculated_risk_score']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($p['visit_date'])); ?></td>
                                    <td class="text-end">
                                        <a href="tel:<?php echo $p['phone']; ?>" class="btn btn-sm btn-outline-success me-1" title="Ara">
                                            <i class="bi bi-telephone-fill"></i>
                                        </a>
                                        <a href="patient_detail.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">
                                            Detaylar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                                        Harika! Şu anda yüksek risk grubunda hastanız bulunmuyor.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Export -->
    <div class="tab-pane fade" id="export" role="tabpanel">
        <div class="card text-center shadow-sm border-0 p-5">
            <div class="card-body">
                <i class="bi bi-file-earmark-spreadsheet text-success mb-3" style="font-size: 4rem;"></i>
                <h3 class="card-title">Klinik Verileri Şuraya Aktar</h3>
                <p class="card-text text-muted mw-50 mx-auto" style="max-width: 600px;">
                    Tüm hasta ziyaretlerini ve hesaplanmış risk faktörlerini içeren ham veriyi CSV formatında indirebilirsiniz. 
                    Bu veriler tıbbi araştırmalar, akademik çalışmalar veya harici istatistiksel analizler (SPSS, Excel) için kullanılabilir.
                </p>
                <div class="mt-4">
                    <a href="?action=export" class="btn btn-success btn-lg">
                        <i class="bi bi-download me-2"></i>
                        Verileri İndir (.csv)
                    </a>
                </div>
                <div class="alert alert-warning mt-4 d-inline-block text-start">
                    <small><i class="bi bi-shield-lock me-1"></i> <strong>KVKK Uyarısı:</strong> İndirilen dosyalar hassas hasta verileri içerir. Lütfen dosya güvenliğini sağladığınızdan emin olun.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Tab Activation (Bootstrap 5)
    var triggerTabList = [].slice.call(document.querySelectorAll('#reportTabs a'))
    triggerTabList.forEach(function (triggerEl) {
      var tabTrigger = new bootstrap.Tab(triggerEl)
      triggerEl.addEventListener('click', function (event) {
        event.preventDefault()
        tabTrigger.show()
      })
    })

    // Charts Initialization
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Pie Chart
        const ctxPie = document.getElementById('riskPieChart');
        if (ctxPie) {
            new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $labelsRisk; ?>,
                    datasets: [{
                        data: <?php echo $dataRisk; ?>,
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // 2. Bar Chart
        const ctxBar = document.getElementById('ageBarChart');
        if (ctxBar) {
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: <?php echo $labelsAge; ?>,
                    datasets: [{
                        label: 'Hasta Sayısı',
                        data: <?php echo $dataAge; ?>,
                        backgroundColor: '#0d6efd',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
