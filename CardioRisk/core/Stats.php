<?php
/**
 * Statistics Class
 * Handles statistical analysis and data comparison with reference dataset
 * Used for generating comparative visualizations (radar charts, etc.)
 */

require_once __DIR__ . '/../config/db.php';

class Stats {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get average values from healthy reference data (target = 0)
     * Used as baseline for radar chart comparison
     * 
     * @return array - Average values for key metrics
     */
    public function getHealthyAverages() {
        try {
            $sql = "SELECT 
                        ROUND(AVG(trestbps), 1) as avg_bp,
                        ROUND(AVG(chol), 1) as avg_chol,
                        ROUND(AVG(thalach), 1) as avg_hr,
                        ROUND(AVG(oldpeak), 2) as avg_oldpeak,
                        ROUND(AVG(fbs), 2) as avg_fbs
                    FROM reference_data 
                    WHERE target = 0";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return [
                'bp' => $result['avg_bp'] ?? 120,
                'chol' => $result['avg_chol'] ?? 180,
                'hr' => $result['avg_hr'] ?? 165,
                'oldpeak' => $result['avg_oldpeak'] ?? 0.2,
                'fbs' => $result['avg_fbs'] ?? 0
            ];
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            // Return default healthy values if query fails
            return [
                'bp' => 120,
                'chol' => 180,
                'hr' => 165,
                'oldpeak' => 0.2,
                'fbs' => 0
            ];
        }
    }
    
    /**
     * Get patient metrics from a specific visit
     * 
     * @param int $visitId - Visit ID
     * @return array|null - Patient metrics or null if not found
     */
    public function getPatientMetrics($visitId) {
        try {
            $sql = "SELECT 
                        trestbps as bp,
                        chol,
                        thalach as hr,
                        oldpeak,
                        fbs,
                        cp,
                        exang,
                        restecg,
                        slope,
                        ca,
                        thal,
                        calculated_risk_score
                    FROM visits 
                    WHERE id = :visit_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':visit_id', $visitId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Compare patient data to reference averages
     * Returns deviation percentages for each metric
     * 
     * @param array $patientData - Patient's medical data
     * @return array - Comparison results with deviations
     */
    public function compareToReference($patientData) {
        $healthyAvg = $this->getHealthyAverages();
        
        $comparison = [];
        
        // Blood Pressure deviation
        $comparison['bp'] = [
            'patient' => $patientData['bp'],
            'healthy' => $healthyAvg['bp'],
            'deviation' => round((($patientData['bp'] - $healthyAvg['bp']) / $healthyAvg['bp']) * 100, 1),
            'status' => $patientData['bp'] > $healthyAvg['bp'] ? 'above' : 'below'
        ];
        
        // Cholesterol deviation
        $comparison['chol'] = [
            'patient' => $patientData['chol'],
            'healthy' => $healthyAvg['chol'],
            'deviation' => round((($patientData['chol'] - $healthyAvg['chol']) / $healthyAvg['chol']) * 100, 1),
            'status' => $patientData['chol'] > $healthyAvg['chol'] ? 'above' : 'below'
        ];
        
        // Heart Rate deviation (higher is better)
        $comparison['hr'] = [
            'patient' => $patientData['hr'],
            'healthy' => $healthyAvg['hr'],
            'deviation' => round((($patientData['hr'] - $healthyAvg['hr']) / $healthyAvg['hr']) * 100, 1),
            'status' => $patientData['hr'] < $healthyAvg['hr'] ? 'below' : 'above'
        ];
        
        // ST Depression deviation (lower is better)
        $comparison['oldpeak'] = [
            'patient' => $patientData['oldpeak'],
            'healthy' => $healthyAvg['oldpeak'],
            'deviation' => $healthyAvg['oldpeak'] > 0 
                ? round((($patientData['oldpeak'] - $healthyAvg['oldpeak']) / $healthyAvg['oldpeak']) * 100, 1)
                : 0,
            'status' => $patientData['oldpeak'] > $healthyAvg['oldpeak'] ? 'above' : 'below'
        ];
        
        return $comparison;
    }
    
    /**
     * Get dashboard statistics for doctor
     * 
     * @param int $doctorId - Doctor's user ID
     * @return array - Dashboard statistics
     */
    public function getDashboardStats($doctorId) {
        try {
            // Total patients
            $sql = "SELECT COUNT(*) as total FROM patients WHERE doctor_id = :doctor_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $totalPatients = $stmt->fetch()['total'];
            
            // Total visits
            $sql = "SELECT COUNT(*) as total FROM visits v
                    INNER JOIN patients p ON v.patient_id = p.id
                    WHERE p.doctor_id = :doctor_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $totalVisits = $stmt->fetch()['total'];
            
            // High risk patients (risk score > 66)
            $sql = "SELECT COUNT(DISTINCT v.patient_id) as total 
                    FROM visits v
                    INNER JOIN patients p ON v.patient_id = p.id
                    WHERE p.doctor_id = :doctor_id 
                    AND v.calculated_risk_score > 66
                    AND v.id IN (
                        SELECT MAX(id) FROM visits GROUP BY patient_id
                    )";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $highRiskPatients = $stmt->fetch()['total'];
            
            return [
                'total_patients' => $totalPatients,
                'total_visits' => $totalVisits,
                'high_risk_patients' => $highRiskPatients
            ];
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            return [
                'total_patients' => 0,
                'total_visits' => 0,
                'high_risk_patients' => 0
            ];
        }
    }
    
    /**
     * Get recent visits for dashboard
     * 
     * @param int $doctorId - Doctor's user ID
     * @param int $limit - Number of records to fetch
     * @return array - Recent visits
     */
    public function getRecentVisits($doctorId, $limit = 5) {
        try {
            $sql = "SELECT 
                        v.id,
                        v.visit_date,
                        v.calculated_risk_score,
                        p.full_name as patient_name,
                        p.id as patient_id
                    FROM visits v
                    INNER JOIN patients p ON v.patient_id = p.id
                    WHERE p.doctor_id = :doctor_id
                    ORDER BY v.visit_date DESC, v.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':doctor_id', $doctorId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all visits for a specific patient
     * 
     * @param int $patientId - Patient ID
     * @return array - All visits for the patient
     */
    public function getPatientVisits($patientId) {
        try {
            $sql = "SELECT 
                        id,
                        visit_date,
                        calculated_risk_score,
                        DATE_FORMAT(visit_date, '%d %b %Y') as formatted_date
                    FROM visits
                    WHERE patient_id = :patient_id
                    ORDER BY visit_date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':patient_id', $patientId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * YENİ! Yaş ve Cinsiyet Bazlı Kıyaslama (Peer Comparison)
     * Hastanın yaş grubu ve cinsiyetine göre sağlıklı ortalamaları döner
     * 
     * @param int $age - Hastanın yaşı
     * @param int $gender - Cinsiyet (0: Kadın, 1: Erkek)
     * @return array - Yaş/cinsiyet grubuna özel ortalamalar
     */
    public function getPeerAverages($age, $gender) {
        try {
            // Yaş aralığını belirle (±5 yıl)
            $minAge = $age - 5;
            $maxAge = $age + 5;
            
            $sql = "SELECT 
                        ROUND(AVG(trestbps), 1) as avg_bp,
                        ROUND(AVG(chol), 1) as avg_chol,
                        ROUND(AVG(thalach), 1) as avg_hr,
                        ROUND(AVG(oldpeak), 2) as avg_oldpeak,
                        ROUND(AVG(fbs), 2) as avg_fbs,
                        COUNT(*) as peer_count
                    FROM reference_data 
                    WHERE target = 0 
                    AND age BETWEEN :min_age AND :max_age
                    AND sex = :gender";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':min_age', $minAge, PDO::PARAM_INT);
            $stmt->bindParam(':max_age', $maxAge, PDO::PARAM_INT);
            $stmt->bindParam(':gender', $gender, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            // Eğer yaş/cinsiyet grubunda yeterli veri yoksa genel ortalamaya dön
            if (!$result || $result['peer_count'] < 3) {
                return array_merge(
                    $this->getHealthyAverages(),
                    ['peer_count' => 0, 'is_peer_specific' => false]
                );
            }
            
            return [
                'bp' => $result['avg_bp'] ?? 120,
                'chol' => $result['avg_chol'] ?? 180,
                'hr' => $result['avg_hr'] ?? 165,
                'oldpeak' => $result['avg_oldpeak'] ?? 0.2,
                'fbs' => $result['avg_fbs'] ?? 0,
                'peer_count' => $result['peer_count'],
                'is_peer_specific' => true,
                'age_range' => "$minAge-$maxAge",
                'gender' => $gender == 1 ? 'Erkek' : 'Kadın'
            ];
            
        } catch (PDOException $e) {
            error_log("Stats Error: " . $e->getMessage());
            // Hata durumunda genel ortalamaya dön
            return array_merge(
                $this->getHealthyAverages(),
                ['peer_count' => 0, 'is_peer_specific' => false]
            );
        }
    }
    
    /**
     * YENİ! Peer Comparison ile Detaylı Karşılaştırma
     * Hem genel hem de yaş/cinsiyet bazlı karşılaştırma yapar
     * 
     * @param array $patientData - Hasta verileri
     * @param int $age - Hastanın yaşı
     * @param int $gender - Cinsiyet
     * @return array - Detaylı karşılaştırma sonuçları
     */
    public function compareWithPeers($patientData, $age, $gender) {
        $generalAvg = $this->getHealthyAverages();
        $peerAvg = $this->getPeerAverages($age, $gender);
        
        $comparison = [];
        
        // Kolesterol karşılaştırması
        $comparison['cholesterol'] = [
            'value' => $patientData['chol'],
            'general_avg' => $generalAvg['chol'],
            'peer_avg' => $peerAvg['chol'],
            'vs_general' => round((($patientData['chol'] - $generalAvg['chol']) / $generalAvg['chol']) * 100, 1),
            'vs_peer' => round((($patientData['chol'] - $peerAvg['chol']) / $peerAvg['chol']) * 100, 1),
            'message' => $this->generatePeerMessage($patientData['chol'], $generalAvg['chol'], $peerAvg['chol'], 'Kolesterol')
        ];
        
        // Kan basıncı karşılaştırması
        $comparison['blood_pressure'] = [
            'value' => $patientData['bp'],
            'general_avg' => $generalAvg['bp'],
            'peer_avg' => $peerAvg['bp'],
            'vs_general' => round((($patientData['bp'] - $generalAvg['bp']) / $generalAvg['bp']) * 100, 1),
            'vs_peer' => round((($patientData['bp'] - $peerAvg['bp']) / $peerAvg['bp']) * 100, 1),
            'message' => $this->generatePeerMessage($patientData['bp'], $generalAvg['bp'], $peerAvg['bp'], 'Kan basıncınız')
        ];
        
        $comparison['peer_info'] = $peerAvg;
        
        return $comparison;
    }
    
    /**
     * Peer comparison mesajı oluştur
     * 
     * @param float $value - Hasta değeri
     * @param float $generalAvg - Genel ortalama
     * @param float $peerAvg - Yaş grubu ortalaması
     * @param string $metric - Metrik adı
     * @return string - Karşılaştırma mesajı
     */
    private function generatePeerMessage($value, $generalAvg, $peerAvg, $metric) {
        $vsGeneral = $value > $generalAvg ? 'yüksek' : 'normal';
        $vsPeer = $value > $peerAvg ? 'yüksek' : 'normal';
        
        if ($vsGeneral == 'yüksek' && $vsPeer == 'normal') {
            return "$metric genel ortalamaya göre yüksek ama kendi yaş grubunuza göre normal seviyededir.";
        } elseif ($vsGeneral == 'normal' && $vsPeer == 'yüksek') {
            return "$metric genel olarak normal ama kendi yaş grubunuza göre yüksektir.";
        } elseif ($vsGeneral == 'yüksek' && $vsPeer == 'yüksek') {
            return "$metric hem genel hem de yaş grubunuza göre yüksektir. Dikkat gerektirmektedir.";
        } else {
            return "$metric hem genel hem de yaş grubunuza göre optimal seviyededir.";
        }
    }
}
?>
