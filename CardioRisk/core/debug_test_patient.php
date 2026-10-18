<?php
require_once __DIR__ . '/../config/db.php';

echo "Hasta Kontrolü: 'test test'...\n";

$db = getDB();

$stmt = $db->prepare("SELECT * FROM patients WHERE full_name LIKE ? ORDER BY id DESC");
$stmt->execute(['%test%test%']);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($patients) > 0) {
    echo "Bulunan kayıt sayısı: " . count($patients) . "\n\n";
    foreach ($patients as $p) {
        echo "ID: " . $p['id'] . "\n";
        echo "Ad: " . $p['full_name'] . "\n";
        echo "Email: " . $p['email'] . "\n";
        echo "TC: " . $p['tc_identity'] . "\n";
        echo "User ID: " . $p['user_id'] . "\n";
        echo "Oluşturulma: " . $p['created_at'] . "\n";
        echo "--------------------------\n";
    }
} else {
    echo "Kayıt bulunamadı.\n";
}
?>
