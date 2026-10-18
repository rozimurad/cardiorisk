<?php
/**
 * Recommendation Engine Class
 * Rule-based expert system for generating clinical recommendations
 * Analyzes patient data and provides actionable medical advice
 */

class Recommendation {
    
    /**
     * Generate comprehensive medical recommendations based on visit data
     * 
     * @param array $visitData - Complete visit data with all parameters
     * @param int $riskScore - Calculated risk score
     * @return string - Formatted recommendations text
     */
    public static function generate($visitData, $riskScore) {
        $recommendations = [];
        $priority = [];  // Track priority level for each recommendation
        
        // ===================================
        // GENEL RİSK DEĞERLENDİRMESİ
        // ===================================
        if ($riskScore >= 67) {
            $recommendations[] = "⚠️ YÜKSEK RİSK TESPİT EDİLDİ: Derhal kardiyolog muayenesi önerilmektedir.";
            $priority[] = 'critical';
            
            $recommendations[] = "1 hafta içinde ileri kardiyak görüntüleme (ekokardiyografi veya kardiyak MRI) planlanmalıdır.";
            $priority[] = 'high';
        } elseif ($riskScore >= 34) {
            $recommendations[] = "⚠️ ORTA RİSK: Düzenli kardiyak takip önerilir.";
            $priority[] = 'medium';
            
            $recommendations[] = "3-6 ay içinde kontrol randevusu planlayınız.";
            $priority[] = 'medium';
        } else {
            $recommendations[] = "✓ DÜŞÜK RİSK: Sağlıklı yaşam tarzına ve rutin kontrollere devam ediniz.";
            $priority[] = 'low';
        }
        
        // ===================================
        // GÖĞÜS AĞRISI ANALİZİ
        // ===================================
        if ($visitData['cp'] == 4) {
            $recommendations[] = "Diğer risk faktörleriyle birlikte asemptomatik görünüm, efor testi dahil kapsamlı kardiyak tarama gerektirir.";
            $priority[] = 'high';
        } elseif ($visitData['cp'] >= 2) {
            $recommendations[] = "Atipik göğüs ağrısı semptomları ileri tetkik gerektirir. Efor testi veya koroner BT anjiyografi düşünülmelidir.";
            $priority[] = 'medium';
        }
        
        // ===================================
        // KAN BASINCI YÖNETİMİ
        // ===================================
        $bp = $visitData['trestbps'];
        if ($bp >= 180) {
            $recommendations[] = "🔴 KRİTİK: Kan basıncı ciddi derecede yüksek ({$bp} mmHg). İlaç tedavisinin acilen düzenlenmesi gerekir.";
            $priority[] = 'critical';
            $recommendations[] = "Evde kan basıncı takibi önerilir. Kombine antihipertansif tedavi düşünülmelidir.";
            $priority[] = 'high';
        } elseif ($bp >= 140) {
            $recommendations[] = "Kan basıncı yüksek ({$bp} mmHg). Mevcut ilaçlar ve yaşam tarzı değişiklikleri gözden geçirilmelidir.";
            $priority[] = 'medium';
            $recommendations[] = "Tuzsuz diyet (DASH) uygulanmalı ve sodyum alımı <2g/gün olmalıdır.";
            $priority[] = 'medium';
        } elseif ($bp >= 130) {
            $recommendations[] = "Kan basıncı hafif yüksek ({$bp} mmHg). Yaşam tarzı değişiklikleri önerilir.";
            $priority[] = 'low';
        }
        
        // ===================================
        // KOLESTEROL YÖNETİMİ
        // ===================================
        $chol = $visitData['chol'];
        if ($chol >= 280) {
            $recommendations[] = "🔴 Kolesterol kritik seviyede yüksek ({$chol} mg/dL). Yüksek doz statin tedavisi güçlü şekilde önerilir.";
            $priority[] = 'critical';
            $recommendations[] = "Hedef LDL <70 mg/dL olmalıdır. Statin yetersiz kalırsa ezetimibe eklenmesi düşünülmelidir.";
            $priority[] = 'high';
        } elseif ($chol >= 240) {
            $recommendations[] = "Kolesterol yüksek ({$chol} mg/dL). Statin tedavisi ve diyet değişiklikleri birlikte değerlendirilmelidir.";
            $priority[] = 'high';
            $recommendations[] = "Düşük kolesterollü diyet, lif alımının artırılması ve düzenli aerobik egzersiz (haftada 150 dk) önerilir.";
            $priority[] = 'medium';
        } elseif ($chol >= 200) {
            $recommendations[] = "Kolesterol sınırda yüksek ({$chol} mg/dL). Yaşam tarzı değişiklikleri önerilir.";
            $priority[] = 'medium';
            $recommendations[] = "Doymuş yağ alımını azaltın, omega-3 tüketimini artırın ve ideal kilonuzu koruyun.";
            $priority[] = 'low';
        }
        
        // ===================================
        // KAN ŞEKERİ YÖNETİMİ
        // ===================================
        if ($visitData['fbs'] == 1) {
            $recommendations[] = "Açlık kan şekeri yüksek tespit edildi. HbA1c testi ile diyabet taraması yapılmalıdır.";
            $priority[] = 'high';
            $recommendations[] = "Diyabet dostu diyet ve düzenli şeker takibi uygulanmalıdır.";
            $priority[] = 'medium';
        }
        
        // ===================================
        // EKG ANORMALLİKLERİ
        // ===================================
        if ($visitData['restecg'] == 2) {
            $recommendations[] = "EKG'de sol ventriküler hipertrofi saptandı. Kardiyak fonksiyonu değerlendirmek için ekokardiyografi önerilir.";
            $priority[] = 'high';
            $recommendations[] = "İlerlemenin önlenmesi için agresif kan basıncı kontrolü şarttır.";
            $priority[] = 'high';
        } elseif ($visitData['restecg'] == 1) {
            $recommendations[] = "ST-T dalga anormallikleri gözlendi. İleri kardiyak değerlendirme gerekebilir.";
            $priority[] = 'medium';
        }
        
        // ===================================
        // KALP HIZI ANALİZİ
        // ===================================
        $hr = $visitData['thalach'];
        if ($hr < 100) {
            $recommendations[] = "Maksimum kalp hızı çok düşük ({$hr} bpm). Egzersiz toleransı zayıf - kardiyak rehabilitasyon programı düşünülmelidir.";
            $priority[] = 'high';
            $recommendations[] = "Tıbbi gözetim altında fiziksel aktivitenin kademeli olarak artırılması önerilir.";
            $priority[] = 'medium';
        } elseif ($hr < 120) {
            $recommendations[] = "Egzersiz kapasitesi ortalamanın altında. Yapılandırılmış egzersiz programı kardiyovasküler kondisyonu artırabilir.";
            $priority[] = 'medium';
        }
        
        // ===================================
        // EGZERSİZLE TETİKLENEN SEMPTOMLAR
        // ===================================
        if ($visitData['exang'] == 1) {
            $recommendations[] = "⚠️ Egzersizle tetiklenen anjina mevcut. Görüntülemeli efor testi şiddetle önerilir.";
            $priority[] = 'high';
            $recommendations[] = "Kardiyak değerlendirme tamamlanana kadar ağır fiziksel aktivitelerden kaçınılmalıdır. Anti-anjinal ilaçlar düşünülebilir.";
            $priority[] = 'high';
        }
        
        // ===================================
        // ST ÇÖKMESİ ANALİZİ
        // ===================================
        $oldpeak = $visitData['oldpeak'];
        if ($oldpeak >= 2.0) {
            $recommendations[] = "Belirgin ST çökmesi ({$oldpeak}mm) iskemiye işaret etmektedir. Koroner anjiyografi gerekebilir.";
            $priority[] = 'critical';
            $recommendations[] = "İkili antiplatelet tedavi (aspirin + P2Y12 inhibitörü) düşünülmelidir.";
            $priority[] = 'high';
        } elseif ($oldpeak >= 1.0) {
            $recommendations[] = "Orta derecede ST çökmesi tespit edildi. Yakın takip ve gerekirse stres görüntüleme önerilir.";
            $priority[] = 'medium';
        }
        
        // ===================================
        // KORONER DAMAR TIKANIKLIĞI
        // ===================================
        if ($visitData['ca'] >= 2) {
            $recommendations[] = "🔴 KRİTİK: Çoklu damar koroner hastalığı ({$visitData['ca']} damar). Revaskülarizasyon (PCI veya CABG) konsültasyonu gereklidir.";
            $priority[] = 'critical';
            $recommendations[] = "Optimal medikal tedavi: Statin, antiplatelet, ACE inhibitörü ve beta-bloker.";
            $priority[] = 'critical';
        } elseif ($visitData['ca'] == 1) {
            $recommendations[] = "Tek damar koroner hastalığı tespit edildi. Medikal tedavi veya girişimsel müdahale seçenekleri tartışılmalıdır.";
            $priority[] = 'high';
        }
        
        // ===================================
        // TALASEMİ / PERFÜZYON DEFEKTLERİ
        // ===================================
        if ($visitData['thal'] == 7) {
            $recommendations[] = "Geri dönüşümlü perfüzyon defekti aktif iskemiye işaret eder. Acil kardiyoloji sevki.";
            $priority[] = 'critical';
        } elseif ($visitData['thal'] == 6) {
            $recommendations[] = "Sabit perfüzyon defekti geçirilmiş miyokard enfarktüsünü düşündürür. İkincil koruma önlemleri şarttır.";
            $priority[] = 'high';
        }
        
        // ===================================
        // GENEL YAŞAM TARZI ÖNERİLERİ
        // ===================================
        $recommendations[] = "Genel Yaşam Tarzı Önerileri: Akdeniz diyeti, düzenli egzersiz, stres yönetimi ve varsa sigaranın bırakılması.";
        $priority[] = 'low';
        
        // ===================================
        // ÇIKTI FORMATI
        // ===================================
        return self::formatRecommendations($recommendations, $priority);
    }
    
    /**
     * Önerileri öncelik sırasına göre formatla
     * 
     * @param array $recommendations - Öneri metinleri dizisi
     * @param array $priority - Öncelik seviyeleri
     * @return string - Formatlanmış öneri metni
     */
    private static function formatRecommendations($recommendations, $priority) {
        $formatted = [];
        
        // Önceliğe göre grupla
        $critical = [];
        $high = [];
        $medium = [];
        $low = [];
        
        foreach ($recommendations as $index => $rec) {
            switch ($priority[$index]) {
                case 'critical':
                    $critical[] = $rec;
                    break;
                case 'high':
                    $high[] = $rec;
                    break;
                case 'medium':
                    $medium[] = $rec;
                    break;
                case 'low':
                    $low[] = $rec;
                    break;
            }
        }
        
        $output = "";
        
        if (!empty($critical)) {
            $output .= "**KRİTİK ÖNCELİK:**\n" . implode("\n\n", $critical) . "\n\n";
        }
        
        if (!empty($high)) {
            $output .= "**YÜKSEK ÖNCELİK:**\n" . implode("\n\n", $high) . "\n\n";
        }
        
        if (!empty($medium)) {
            $output .= "**ORTA ÖNCELİK:**\n" . implode("\n\n", $medium) . "\n\n";
        }
        
        if (!empty($low)) {
            $output .= "**RUTİN KONTROL:**\n" . implode("\n\n", $low) . "\n\n";
        }
        
        return trim($output);
    }
}
?>
