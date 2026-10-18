<?php
/**
 * Patients List Module
 * Display all patients with their latest risk scores
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

checkAuth('../modules/login.php');

$db = getDB();
$doctorId = getCurrentUserId();

// Fetch all patients with their latest visit risk score
$sql = "SELECT 
            p.*,
            (SELECT calculated_risk_score 
             FROM visits 
             WHERE patient_id = p.id 
             ORDER BY visit_date DESC LIMIT 1) as latest_risk_score,
            (SELECT visit_date 
             FROM visits 
             WHERE patient_id = p.id 
             ORDER BY visit_date DESC LIMIT 1) as latest_visit_date
        FROM patients p
        ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
// $stmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
$stmt->execute();
$patients = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header py-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0 fw-bold">
                <i class="bi bi-people text-primary me-2"></i>
                Hastalar
            </h1>
            <p class="text-muted mb-0">Hasta kayıtlarınızı yönetin</p>
        </div>
        <div>
            <a href="add_patient.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-2"></i>
                Yeni Hasta Ekle
            </a>
        </div>
    </div>
</div>

<!-- Patients Table -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (count($patients) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="patientsTable">
                        <thead class="table-light">
                            <tr>
                                <th>TC Kimlik</th>
                                <th>Ad Soyad</th>
                                <th>Yaş</th>
                                <th>Cinsiyet</th>
                                <th>Telefon</th>
                                <th>E-posta</th>
                                <th>Son Ziyaret</th>
                                <th>Risk Skoru</th>
                                <th class="text-end">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($patient['tc_identity']); ?></code>
                                </td>
                                <td>
                                    <i class="bi bi-person-circle text-muted me-2"></i>
                                    <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo calculateAge($patient['birth_date']); ?> yaş
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $patient['gender'] === 'male' ? 'info' : 'pink'; ?>">
                                        <i class="bi bi-<?php echo $patient['gender'] === 'male' ? 'gender-male' : 'gender-female'; ?> me-1"></i>
                                        <?php echo $patient['gender'] === 'male' ? 'Erkek' : 'Kadın'; ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <?php echo htmlspecialchars($patient['phone']); ?>
                                </td>
                                <td>
                                    <?php if (!empty($patient['email'])): ?>
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <?php echo htmlspecialchars($patient['email']); ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['latest_visit_date']): ?>
                                        <i class="bi bi-calendar-check text-success me-1"></i>
                                        <?php echo formatDate($patient['latest_visit_date']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Ziyaret yok</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($patient['latest_risk_score'] !== null): ?>
                                        <?php echo getRiskBadge($patient['latest_risk_score']); ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($patient['latest_risk_score'] !== null): ?>
                                        <a href="patient_detail.php?id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-outline-primary"
                                           title="Analiz Görüntüle">
                                            <i class="bi bi-bar-chart"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="new_visit.php?patient_id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-outline-success"
                                           title="Yeni Ziyaret">
                                            <i class="bi bi-clipboard2-pulse"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Hasta bulunamadı</p>
                    <a href="add_patient.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>İlk Hastanızı Ekleyin
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Add search functionality if table has many rows
$(document).ready(function() {
    // Simple client-side search
    $('#searchPatient').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#patientsTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
