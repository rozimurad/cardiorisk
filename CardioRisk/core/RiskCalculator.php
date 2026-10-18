<?php
/**
 * Risk Calculator Class
 * Implements weighted algorithm for cardiovascular risk assessment
 * Based on multiple cardiac parameters to calculate risk score (0-100)
 */

class RiskCalculator {
    
    /**
     * Calculate comprehensive risk score based on all cardiac parameters
     * 
     * @param array $params - Array containing all medical parameters:
     *   - cp: Chest pain type (1-4)
     *   - trestbps: Resting blood pressure
     *   - chol: Cholesterol level
     *   - fbs: Fasting blood sugar (0 or 1)
     *   - restecg: Resting ECG results (0-2)
     *   - thalach: Maximum heart rate achieved
     *   - exang: Exercise induced angina (0 or 1)
     *   - oldpeak: ST depression
     *   - slope: Slope of peak exercise ST segment (1-3)
     *   - ca: Number of major vessels (0-3)
     *   - thal: Thalassemia (3, 6, or 7)
     * 
     * @return array - ['score' => int, 'level' => string, 'color' => string]
     */
    /**
     * Calculate comprehensive risk score based on all cardiac parameters
     * 
     * @param array $params - Array containing all medical parameters
     * @param int $highRiskThreshold - Score required to be considered High Risk (Default: 50)
     * 
     * @return array - ['score' => int, 'level' => string, 'color' => string]
     */
    public static function calculate($params, $highRiskThreshold = 50) {
        $score = 0;
        
        // ===================================
        // CHEST PAIN ANALYSIS (0-20 points)
        // ===================================
        // Asymptomatic chest pain is highest risk
        if ($params['cp'] == 4) {
            $score += 20;
        } elseif ($params['cp'] == 3) {
            $score += 10;
        } elseif ($params['cp'] == 2) {
            $score += 5;
        }
        // Type 1 (typical angina) adds 0 - it's already diagnosed
        
        // ===================================
        // BLOOD PRESSURE ANALYSIS (0-15 points)
        // ===================================
        $bp = $params['trestbps'];
        if ($bp >= 180) {
            $score += 15;  // Stage 3 hypertension
        } elseif ($bp >= 160) {
            $score += 12;  // Stage 2 hypertension
        } elseif ($bp >= 140) {
            $score += 8;   // Stage 1 hypertension
        } elseif ($bp >= 130) {
            $score += 4;   // Elevated
        }
        // Normal (< 130) adds 0
        
        // ===================================
        // CHOLESTEROL ANALYSIS (0-15 points)
        // ===================================
        $chol = $params['chol'];
        if ($chol >= 280) {
            $score += 15;  // Very high
        } elseif ($chol >= 240) {
            $score += 12;  // High
        } elseif ($chol >= 200) {
            $score += 6;   // Borderline high
        }
        // Desirable (< 200) adds 0
        
        // ===================================
        // FASTING BLOOD SUGAR (0-8 points)
        // ===================================
        if ($params['fbs'] == 1) {
            $score += 8;   // Fasting blood sugar > 120 mg/dL indicates diabetes risk
        }
        
        // ===================================
        // RESTING ECG ANALYSIS (0-10 points)
        // ===================================
        if ($params['restecg'] == 2) {
            $score += 10;  // Left ventricular hypertrophy
        } elseif ($params['restecg'] == 1) {
            $score += 6;   // ST-T wave abnormality
        }
        // Normal adds 0
        
        // ===================================
        // MAX HEART RATE ANALYSIS (0-12 points)
        // ===================================
        $hr = $params['thalach'];
        if ($hr < 100) {
            $score += 12;  // Very low - poor cardiac reserve
        } elseif ($hr < 120) {
            $score += 8;   // Low
        } elseif ($hr < 140) {
            $score += 4;   // Below average
        }
        // Good heart rate (>= 140) adds 0
        
        // ===================================
        // EXERCISE-INDUCED ANGINA (0-10 points)
        // ===================================
        if ($params['exang'] == 1) {
            $score += 10;  // Exercise-induced chest pain is significant
        }
        
        // ===================================
        // ST DEPRESSION (OLDPEAK) (0-20 points)
        // ===================================
        $oldpeak = $params['oldpeak'];
        if ($oldpeak >= 3.0) {
            $score += 20;  // Severe ST depression
        } elseif ($oldpeak >= 2.0) {
            $score += 15;  // Moderate-severe
        } elseif ($oldpeak >= 1.0) {
            $score += 8;   // Moderate
        } elseif ($oldpeak > 0) {
            $score += 3;   // Mild
        }
        // No depression (0) adds 0
        
        // ===================================
        // ST SLOPE ANALYSIS (0-8 points)
        // ===================================
        if ($params['slope'] == 3) {
            $score += 8;   // Downsloping - worst prognosis
        } elseif ($params['slope'] == 2) {
            $score += 5;   // Flat
        }
        // Upsloping (1) adds 0 - best prognosis
        
        // ===================================
        // MAJOR VESSELS (CA) (0-15 points)
        // ===================================
        // More blocked vessels = higher risk
        $score += $params['ca'] * 5;  // 0, 5, 10, or 15 points
        
        // ===================================
        // THALASSEMIA (0-12 points)
        // ===================================
        if ($params['thal'] == 7) {
            $score += 12;  // Reversible defect - significant risk
        } elseif ($params['thal'] == 6) {
            $score += 8;   // Fixed defect
        }
        // Normal (3) adds 0
        
        // ===================================
        // NORMALIZE SCORE TO 0-100 RANGE
        // ===================================
        // Maximum possible points: ~145
        // Normalize to 100-point scale
        $score = min(100, round($score * 0.75));
        
        // ===================================
        // DETERMINE RISK LEVEL
        // ===================================
        $level = self::getRiskLevel($score, $highRiskThreshold);
        $color = self::getRiskColor($score, $highRiskThreshold);
        
        return [
            'score' => $score,
            'level' => $level,
            'color' => $color
        ];
    }
    
    /**
     * Get risk level category based on score
     * @param int $score - Risk score (0-100)
     * @param int $threshold - High risk threshold (default 50)
     * @return string - Risk level (Low/Moderate/High)
     */
    public static function getRiskLevel($score, $threshold = 50) {
        $mid = $threshold / 2; // Moderate boundary seems reasonable as half of high
        
        if ($score < $mid) {
            return 'Low';
        } elseif ($score < $threshold) {
            return 'Moderate';
        } else {
            return 'High';
        }
    }
    
    /**
     * Get color code for risk level
     * @param int $score - Risk score (0-100)
     * @param int $threshold - High risk threshold (default 50)
     * @return string - Color name for UI
     */
    public static function getRiskColor($score, $threshold = 50) {
        $mid = $threshold / 2;
        
        if ($score < $mid) {
            return 'success';  // Green
        } elseif ($score < $threshold) {
            return 'warning';  // Orange
        } else {
            return 'danger';   // Red
        }
    }
    
    /**
     * Recalculate risk with modified parameters (for What-If simulator)
     * 
     * @param array $originalParams - Original medical parameters
     * @param array $modifications - Parameters to modify (e.g., ['chol' => 180])
     * @param int $threshold - Risk threshold
     * @return array - New risk calculation result
     */
    public static function simulateRisk($originalParams, $modifications, $threshold = 50) {
        // Merge modifications into original parameters
        $newParams = array_merge($originalParams, $modifications);
        
        // Calculate new risk
        $newRisk = self::calculate($newParams, $threshold);
        $oldRisk = self::calculate($originalParams, $threshold);
        
        // Calculate improvement/deterioration
        $difference = $newRisk['score'] - $oldRisk['score'];
        $percentChange = $oldRisk['score'] > 0 
            ? round(($difference / $oldRisk['score']) * 100, 1)
            : 0;
        
        return [
            'old_score' => $oldRisk['score'],
            'new_score' => $newRisk['score'],
            'difference' => $difference,
            'percent_change' => $percentChange,
            'new_level' => $newRisk['level'],
            'new_color' => $newRisk['color']
        ];
    }
}
?>
