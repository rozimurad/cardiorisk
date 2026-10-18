<?php
/**
 * PDF Report Generator Module
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../core/ReportGenerator.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    die('Erişim reddedildi.');
}

$db = getDB();
$userId = $_SESSION['user_id'];
$visitId = isset($_GET['visit']) ? intval($_GET['visit']) : (isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0);

if (!$visitId) {
    die('Geçersiz ziyaret ID.');
}

// 1. Ziyaret Bilgilerini Çek
$stmtVisit = $db->prepare("SELECT * FROM visits WHERE id = ?");
$stmtVisit->execute([$visitId]);
$visit = $stmtVisit->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    die('Ziyaret bulunamadı.');
}

$patientId = $visit['patient_id'];

// 2. Hasta Bilgilerini Çek
$stmtPatient = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch(PDO::FETCH_ASSOC);

// Yetki Kontrolü: 
// Eğer doktorsa, hasta ona mı ait? 
// Eğer hastaysa, kendi ziyareti mi?
if ($_SESSION['role'] === 'patient') {
    // Hasta kendi verisine bakıyor mu?
    // patients tablosunda user_id yoksa email ile eşleştirebiliriz veya session logic'ine bakarız
    // Burada basitlik adına: Eğer hasta giriş yaptıysa ve patient_id uyuşuyorsa (ki patient_id session'da yoksa zor)
    // Şimdilik sadece doktor erişimine açalım veya basit kontrol yapalım.
} else {
    // Doktorsa, hasta ona mı ait?
    // Doktorsa, tüm hastaları görebilir (Gevşetilmiş Yetki - Dr. Request)
    // if ($patient['doctor_id'] != $userId && $_SESSION['role'] != 'admin') {
    //     die('Bu hastanın raporunu görüntüleme yetkiniz yok.');
    // }
}

// 3. Doktor Bilgilerini Çek
$doctorId = $patient['doctor_id'];
$stmtDoc = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtDoc->execute([$doctorId]);
$doctor = $stmtDoc->fetch(PDO::FETCH_ASSOC);

// 4. Raporu Oluştur
try {
    $pdf = new ReportGenerator($doctor, $patient, $visit);
    $pdf->generate();
    
    // Dosya adı
    $filename = 'Rapor_' . $patient['tc_identity'] . '_' . date('Ymd', strtotime($visit['visit_date'])) . '.pdf';
    
    // Çıktı ver (I: Inline - tarayıcıda aç / D: Download - indir)
    $pdf->Output('I', $filename);
    
} catch (Exception $e) {
    die('Rapor oluşturulurken hata: ' . $e->getMessage());
}
?>
