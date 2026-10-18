<?php
/**
 * Patient Dashboard (Hasta Paneli)
 * Hastalar sadece kendi verilerini görebilir (Read-Only)
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/Stats.php';

// Sadece hasta rolü girebilir
checkAuth('../modules/login.php');
if (!hasRole('patient')) {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();
$stats = new Stats();
$userId = getCurrentUserId();

// 1. Önce User ID ile ara (En güvenli yöntem)
$patientSql = "SELECT * FROM patients WHERE user_id = :user_id";
$stmt = $db->prepare($patientSql);
$stmt->execute([':user_id' => $userId]);
$patient = $stmt->fetch();

// 2. Eğer bulunamazsa TC ile dene (Legacy support)
if (!$patient) {
    // Format: 99 + UserID (9 haneye tamamlanmış) -> Toplam 11 hane
    $dummyTC = '99' . str_pad($userId, 9, '0', STR_PAD_LEFT);
    
    $stmt = $db->prepare("SELECT * FROM patients WHERE tc_identity = :tc_identity");
    $stmt->execute([':tc_identity' => $dummyTC]);
    $patient = $stmt->fetch();
    
    // 3. Hala yoksa ve gerçekten yeni bir kullanıcı ise oluştur (Sadece TC bulunamadıysa)
    if (!$patient) {
        // User bilgilerini al
        $userSql = "SELECT * FROM users WHERE id = :user_id";
        $userStmt = $db->prepare($userSql);
        $userStmt->execute([':user_id' => $userId]);
        $user = $userStmt->fetch();
        
        if ($user) {
            // User ID'ye bağlı, benzersiz TC ile kayıt oluştur
            $insertSql = "INSERT INTO patients (user_id, doctor_id, tc_identity, full_name, birth_date, gender, phone, email) 
                          VALUES (:user_id, 1, :tc_identity, :full_name, '1980-01-01', 'male', '0000000000', :email)";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                ':user_id' => $userId, // Link to user immediately
                ':tc_identity' => $dummyTC,
                ':full_name' => $user['full_name'],
                ':email' => $user['email']
            ]);
            
            // Tekrar al
            $stmt = $db->prepare("SELECT * FROM patients WHERE id = :id");
            $stmt->execute([':id' => $db->lastInsertId()]);
            $patient = $stmt->fetch();
        }
    } else {
        // TC ile bulunduysa ama user_id yoksa, güncelle
        if (empty($patient['user_id'])) {
            $updateSql = "UPDATE patients SET user_id = :user_id WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([':user_id' => $userId, ':id' => $patient['id']]);
        }
    }
}

$patientId = $patient['id'] ?? 0;

// Hasta ziyaretlerini al
$visits = $stats->getPatientVisits($patientId);

// Yaş hesapla
$age = calculateAge($patient['birth_date'] ?? '1980-01-01');

// En son risk skoru
$latestRisk = !empty($visits) ? $visits[0]['calculated_risk_score'] : 0;

include __DIR__ . '/../includes/header.php';
?>

<!-- Hasta için Özel Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h4>👤 Hasta Paneli</h4>
    </div>
    <nav class="sidebar-nav">
        <a href="patient_dashboard.php" class="nav-link active">
            <i class="bi bi-house-door"></i> Ana Sayfa
        </a>
        <a href="#history" class="nav-link">
            <i class="bi bi-clock-history"></i> Geçmiş Ziyaretler
        </a>
        <a href="#" class="nav-link" onclick="alert('Bu özellik sadece doktorunuz tarafından kullanılabilir')">
            <i class="bi bi-file-earmark-medical text-muted"></i> Raporlar
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i> Çıkış Yap
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Page Header -->
    <div class="page-header py-4">
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-person-circle text-primary me-2"></i>
            Hoş Geldiniz, <?php echo htmlspecialchars($patient['full_name'] ?? 'Hasta'); ?>
        </h1>
        <p class="text-muted mb-0">Sağlık verilerinizi buradan takip edebilirsiniz</p>
    </div>

    <!-- Bilgi Notu -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Not:</strong> Bu panel sadece görüntüleme amaçlıdır. Yeni kayıt eklemek veya değişiklik yapmak için doktorunuz ile iletişime geçiniz.
    </div>

    <!-- Ana İstatistikler -->
    <div class="row g-4 mb-4">
        <!-- Toplam Ziyaret -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-calendar-check text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1 small">Toplam Ziyaret</p>
                            <h3 class="mb-0"><?php echo count($visits); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Risk Skoru -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-<?php echo $latestRisk > 66 ? 'danger' : ($latestRisk > 33 ? 'warning' : 'success'); ?> bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-heart-pulse text-<?php echo $latestRisk > 66 ? 'danger' : ($latestRisk > 33 ? 'warning' : 'success'); ?> fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1 small">Son Risk Skoru</p>
                            <h3 class="mb-0"><?php echo $latestRisk; ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Yaş -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-person text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1 small">Yaşınız</p>
                            <h3 class="mb-0"><?php echo $age; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($visits) > 0): ?>
    <!-- Son Ziyaret Detayı -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-file-medical text-primary me-2"></i>
                        Son Muayene Sonucunuz
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $latestVisit = $visits[0];
                    $visitId = $latestVisit['id'];
                    
                    // Ziyaret detaylarını al
                    $visitDetailSql = "SELECT * FROM visits WHERE id = :visit_id";
                    $visitStmt = $db->prepare($visitDetailSql);
                    $visitStmt->execute([':visit_id' => $visitId]);
                    $visitDetail = $visitStmt->fetch();
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-2"><strong>Tarih:</strong> <?php echo $latestVisit['formatted_date']; ?></p>
                            <p class="mb-2">
                                <strong>Risk Seviyesi:</strong> 
                                <?php echo getRiskBadge($latestVisit['calculated_risk_score']); ?>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-2"><strong>Kan Basıncı:</strong> <?php echo $visitDetail['trestbps']; ?> mmHg</p>
                            <p class="mb-2"><strong>Kolesterol:</strong> <?php echo $visitDetail['chol']; ?> mg/dL</p>
                        </div>
                    </div>

                    <?php if (!empty($visitDetail['ai_recommendation'])): ?>
                    <div class="alert alert-light mt-3">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-lightbulb text-warning me-2"></i>
                            Doktorunuzun Önerileri:
                        </h6>
                        <div class="small">
                            <?php
                            $recommendations = explode("\n", $visitDetail['ai_recommendation']);
                            foreach ($recommendations as $rec) {
                                $rec = trim($rec);
                                if (!empty($rec) && strpos($rec, '**') === false) {
                                    echo "<p class='mb-1'>• " . htmlspecialchars($rec) . "</p>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="patient_detail_view.php?visit=<?php echo $visitId; ?>" class="btn btn-primary">
                            <i class="bi bi-eye me-2"></i>
                            Detaylı Sonuçları Görüntüle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ziyaret Geçmişi -->
    <div class="row" id="history">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Geçmiş Muayeneleriniz
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Risk Skoru</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td><?php echo $visit['formatted_date']; ?></td>
                                    <td><strong><?php echo $visit['calculated_risk_score']; ?>%</strong></td>
                                    <td><?php echo getRiskBadge($visit['calculated_risk_score']); ?></td>
                                    <td>
                                        <a href="patient_detail_view.php?visit=<?php echo $visit['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Görüntüle
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Ziyaret Yok Mesajı -->
    <div class="alert alert-warning" id="history">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Henüz kayıtlı muayene bulunmamaktadır. Lütfen doktorunuz ile görüşün.
    </div>
    <?php endif; ?>

</div>

<style>
/* Hasta Paneli için Özel Stiller */
html {
    scroll-behavior: smooth;
    scroll-padding-top: 20px;
}
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    background: linear-gradient(180deg, #0891B2 0%, #0E7490 100%);
    color: white;
    padding: 20px 0;
    overflow-y: auto;
}

.sidebar-header {
    padding: 0 20px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 20px;
}

.sidebar-header h4 {
    margin: 0;
    font-size: 18px;
}

.sidebar-nav .nav-link {
    display: block;
    padding: 12px 20px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s;
}

.sidebar-nav .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.sidebar-nav .nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    border-left: 3px solid white;
}

.sidebar-nav .nav-link i {
    margin-right: 10px;
    width: 20px;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.main-content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
    background: #F9FAFB;
}

@media (max-width: 768px) {
    .sidebar {
        width: 200px;
    }
    .main-content {
        margin-left: 200px;
        padding: 20px;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
