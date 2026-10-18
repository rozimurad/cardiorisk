<?php
/**
 * Migration Script: Link Patients to User Accounts
 * Adds user_id to patients table and safeguards existing data
 */

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();
    echo "Starting migration...\n";

    // 1. Add user_id column if not exists
    try {
        $db->exec("ALTER TABLE patients ADD COLUMN user_id INT NULL AFTER doctor_id");
        echo "[OK] 'user_id' column added to 'patients' table.\n";
    } catch (PDOException $e) {
        // Column might already exist, ignore
        echo "[INFO] 'user_id' column might already exist.\n";
    }

    // 2. Fetch patients without user_id
    $stmt = $db->query("SELECT * FROM patients WHERE user_id IS NULL");
    $patients = $stmt->fetchAll();

    echo "Found " . count($patients) . " patients to migrate.\n";

    foreach ($patients as $p) {
        $tc = $p['tc_identity'];
        // Fallback email if empty
        $email = !empty($p['email']) ? $p['email'] : $tc . '@cardiorisk.local';
        $passHash = password_hash($tc, PASSWORD_DEFAULT); // Password is TCKN by default
        $name = $p['full_name'];

        // Check if user already exists (by email) -> unlikely for dummy data but good practice
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        $existingUser = $check->fetch();

        if ($existingUser) {
            $userId = $existingUser['id'];
            echo " - User already exists for $name (ID: $userId). Linking...\n";
        } else {
            // Create New User
            $insert = $db->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'patient')");
            $insert->execute([$name, $email, $passHash]);
            $userId = $db->lastInsertId();
            echo " - Created User for $name (ID: $userId)\n";
        }

        // Link Patient to User
        $update = $db->prepare("UPDATE patients SET user_id = ? WHERE id = ?");
        $update->execute([$userId, $p['id']]);
    }

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
