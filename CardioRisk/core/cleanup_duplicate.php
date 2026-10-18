<?php
require_once __DIR__ . '/../config/db.php';

echo "Duplicate Temizleme İşlemi (Test Test)...\n";

$db = getDB();

try {
    // ID 17'yi sil (Duplicate olan)
    $deleteId = 17;
    
    // Önce var mı kontrol et
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$deleteId]);
    $p = $stmt->fetch();
    
    if ($p) {
        $db->prepare("DELETE FROM patients WHERE id = ?")->execute([$deleteId]);
        echo "Duplicate hasta (ID: $deleteId) silindi.\n";
    } else {
        echo "Duplicate kayıt (ID: $deleteId) bulunamadı veya zaten silinmiş.\n";
    }
    
    // ID 16'nın email'ini güncelleyelim mi? 
    // Kullanıcı "updated email" demişti. ID 17 silinince güncelleme kaybolacak.
    // ID 16'yı 'timuzi123@gmail.com' olarak güncelleyelim.
    $originalId = 16;
    $newEmail = 'timuzi123@gmail.com'; // Duplicate kayıttan aldığımız bilgi
    
    $updateStmt = $db->prepare("UPDATE patients SET email = ? WHERE id = ?");
    $updateStmt->execute([$newEmail, $originalId]);
    echo "Orijinal hasta (ID: $originalId) emaili güncellendi: $newEmail\n";
    
    // Verify updates
    $finalStmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $finalStmt->execute([$originalId]);
    $finalP = $finalStmt->fetch();
    echo "Güncel Durum -> ID: {$finalP['id']}, UserID: {$finalP['user_id']}, Email: {$finalP['email']}\n";

} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
?>
