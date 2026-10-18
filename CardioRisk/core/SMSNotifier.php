<?php
/**
 * SMS Notification Service (Simulation)
 * Simulates sending SMS alerts for critical risk situations
 * 
 * Not: Bu bir demo simülasyonudur. Gerçek SMS gönderimi için
 * Twilio, Vonage veya yerel bir SMS sağlayıcısının API'si entegre edilmelidir.
 */

class SMSNotifier {
    
    private $logFile;
    
    public function __construct() {
        // Log dosyasını proje kök dizinindeki 'logs' klasörüne kaydet
        $this->logFile = __DIR__ . '/../logs/sms_log.txt';
        
        // Logs klasörü yoksa oluştur
        if (!file_exists(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0777, true);
        }
    }
    
    /**
     * Kritik risk uyarısı SMS'i gönder (Simüle)
     * 
     * @param string $phoneNumber - Hasta telefon numarası
     * @param string $patientName - Hasta adı
     * @param int $riskScore - Risk skoru
     * @param string $doctorName - Doktor adı
     * @return bool - Başarılı ise true
     */
    public function sendCriticalRiskSMS($phoneNumber, $patientName, $riskScore, $doctorName) {
        // Telefon numarası temizleme (sadece rakamlar)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (empty($cleanPhone)) {
            error_log("SMS Hatası: Geçersiz telefon numarası");
            return false;
        }
        
        // SMS Mesajı Oluştur
        $message = "DIKKAT: Sn. $patientName, kardiyak risk skorunuz %$riskScore olarak hesaplanmistir (Kritik Seviye). Lutfen en kisa surede Dr. $doctorName ile iletisime geciniz. - CardioRisk";
        
        // Simülasyon: Log dosyasına yaz
        $logEntry = sprintf(
            "[%s] SMS gönderildi -> Tel: %s | Mesaj: %s\n",
            date('Y-m-d H:i:s'),
            $phoneNumber,
            $message
        );
        
        try {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND);
            error_log("SMS Simulation: Sent to $phoneNumber (See logs/sms_log.txt)");
            return true;
        } catch (Exception $e) {
            error_log("SMS Hatası: " . $e->getMessage());
            return false;
        }
    }
}
?>
