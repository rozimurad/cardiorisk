<?php
/**
 * Dashboard Module
 * Overview statistics and recent activity
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/Stats.php';

// Check authentication
checkAuth('../modules/login.php');

// Get dashboard statistics
$stats = new Stats();
$dashboardData = $stats->getDashboardStats(getCurrentUserId());
$recentVisits = $stats->getRecentVisits(getCurrentUserId(), 10);

// Include header
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header py-4">
    <h1 class="h3 mb-0 fw-bold">
        <i class="bi bi-speedometer2 text-primary me-2"></i>
        Ana Sayfa
    </h1>
    <p class="text-muted mb-0">Tekrar hoşgeldiniz, <?php echo getCurrentUserName(); ?></p>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    
    <!-- Total Patients -->
    <div class="col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Toplam Hasta</p>
                        <h2 class="mb-0 fw-bold"><?php echo $dashboardData['total_patients']; ?></h2>
                    </div>
                    <div class="stat-icon bg-primary-soft">
                        <i class="bi bi-people text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Visits -->
    <div class="col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Toplam Ziyaret</p>
                        <h2 class="mb-0 fw-bold"><?php echo $dashboardData['total_visits']; ?></h2>
                    </div>
                    <div class="stat-icon bg-info-soft">
                        <i class="bi bi-clipboard2-pulse text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- High Risk Patients -->
    <div class="col-md-4">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Yüksek Riskli Hasta</p>
                        <h2 class="mb-0 fw-bold text-danger"><?php echo $dashboardData['high_risk_patients']; ?></h2>
                    </div>
                    <div class="stat-icon bg-danger-soft">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                    </div>
                </div>
                <?php if ($dashboardData['high_risk_patients'] > 0): ?>
                <small class="text-danger">
                    <i class="bi bi-info-circle me-1"></i>Dikkat gerektirir
                </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>

<!-- Recent Visits -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history text-primary me-2"></i>
                    Son Ziyaretler
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($recentVisits) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hasta Adı</th>
                                <th>Ziyaret Tarihi</th>
                                <th>Risk Skoru</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentVisits as $visit): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-person-circle text-muted me-2"></i>
                                    <?php echo htmlspecialchars($visit['patient_name']); ?>
                                </td>
                                <td>
                                    <i class="bi bi-calendar-event text-muted me-2"></i>
                                    <?php echo formatDate($visit['visit_date']); ?>
                                </td>
                                <td>
                                    <?php echo getRiskBadge($visit['calculated_risk_score']); ?>
                                </td>
                                <td class="text-end">
                                    <a href="patient_detail.php?id=<?php echo $visit['patient_id']; ?>&visit=<?php echo $visit['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Detayları Gör
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Son ziyaret bulunmamaktadır</p>
                    <a href="new_visit.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Yeni Ziyaret Oluştur
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-gradient-primary">
            <div class="card-body p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-lightning-charge me-2"></i>
                    Hızlı İşlemler
                </h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="add_patient.php" class="btn btn-light">
                        <i class="bi bi-person-plus me-2"></i>Yeni Hasta Ekle
                    </a>
                    <a href="new_visit.php" class="btn btn-light">
                        <i class="bi bi-clipboard2-pulse me-2"></i>Yeni Ziyaret
                    </a>
                    <a href="patients.php" class="btn btn-light">
                        <i class="bi bi-list-ul me-2"></i>Tüm Hastalar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
