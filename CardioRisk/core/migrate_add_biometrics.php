<?php
require_once __DIR__ . '/../config/db.php';

echo "Sistem Kontrolü: Biyometrik Veri Sütunları Ekleniyor...\n";

$db = getDB();

try {
    // 1. Age (Yaş)
    try {
        $db->exec("ALTER TABLE visits ADD COLUMN age INT NULL AFTER patient_id");
        echo "[OK] 'age' sütunu eklendi.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "[INFO] 'age' sütunu zaten mevcut.\n";
        } else { throw $e; }
    }

    // 2. Height (Boy - cm)
    try {
        $db->exec("ALTER TABLE visits ADD COLUMN height INT NULL AFTER age");
        echo "[OK] 'height' sütunu eklendi.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "[INFO] 'height' sütunu zaten mevcut.\n";
        } else { throw $e; }
    }

    // 3. Weight (Kilo - kg)
    try {
        $db->exec("ALTER TABLE visits ADD COLUMN weight DECIMAL(5,2) NULL AFTER height");
        echo "[OK] 'weight' sütunu eklendi.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "[INFO] 'weight' sütunu zaten mevcut.\n";
        } else { throw $e; }
    }

    // 4. BMI (Vücut Kitle İndeksi)
    try {
        $db->exec("ALTER TABLE visits ADD COLUMN bmi DECIMAL(4,1) NULL AFTER weight");
        echo "[OK] 'bmi' sütunu eklendi.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
             echo "[INFO] 'bmi' sütunu zaten mevcut.\n";
        } else { throw $e; }
    }

    echo "\nİşlem başarıyla tamamlandı!";

} catch (Exception $e) {
    echo "\n[HATA] Bir sorun oluştu: " . $e->getMessage() . "\n";
}
?>
