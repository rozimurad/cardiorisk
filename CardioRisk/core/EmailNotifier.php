<?php
/**
 * Email Notification Service
 * Sends email alerts for critical risk situations
 * 
 * Not: Bu demo versiyonunda PHP'nin mail() fonksiyonu kullanılıyor.
 * Production için PHPMailer veya SMTP kullanılmalıdır.
 */


// PHPMailer sınıflarını dahil et
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class EmailNotifier {
    
    private $config;
    
    public function __construct() {
        // Config dosyasını yükle
        $this->config = require __DIR__ . '/../config/mail.php';
    }
    
    /**
     * Kritik risk uyarısı email'i gönder
     */
    public function sendCriticalRiskAlert($doctorEmail, $doctorName, $patientName, $riskScore, $visitData = []) {
        $subject = "⚠️ KRİTİK UYARI: Yüksek Kardiyak Risk Tespit Edildi - $patientName";
        $message = $this->buildCriticalAlertHtml($doctorName, $patientName, $riskScore, $visitData);
        
        return $this->sendEmail($doctorEmail, $subject, $message);
    }

    /**
     * Hastaya özel kritik risk bildirimi gönder
     */
    public function sendPatientCriticalRiskAlert($patientEmail, $patientName, $riskScore) {
        $subject = "⚠️ Önemli: Sağlık Durumunuz Hakkında Bilgilendirme";
        $message = $this->buildPatientCriticalAlertHtml($patientName, $riskScore);
        
        return $this->sendEmail($patientEmail, $subject, $message);
    }
    
    /**
     * Hasta için özel kritik uyarı HTML'i
     */
    private function buildPatientCriticalAlertHtml($patientName, $riskScore) {
        $color = $riskScore >= 80 ? '#DC2626' : '#F59E0B';
        $riskLevel = $riskScore >= 80 ? 'YÜKSEK' : 'ORTA';
    
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
                .header { background: $color; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .alert-badge { background: white; color: $color; padding: 10px 20px; border-radius: 20px; font-weight: bold; display: inline-block; margin: 10px 0; }
                .content { padding: 20px; background: #FEF3C7; border: 2px solid $color; }
                .risk-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid $color; }
                .action-required { background: $color; color: white; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                .button { background: $color; color: white; padding: 12px 30px; text-decoration: none; display: inline-block; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ ÖNEMLİ SAĞLIK UYARISI</h1>
                    <div class='alert-badge'>$riskLevel RİSK TESPİT EDİLDİ</div>
                </div>
                
                <div class='content'>
                    <p>Sayın <strong>$patientName</strong>,</p>
                    
                    <p>Bugün yapılan kardiyovasküler risk değerlendirmenizde, sonuçlarınız <strong>dikkat edilmesi gereken bir seviyede</strong> çıkmıştır.</p>
                    
                    <div class='risk-box'>
                        <h2 style='color: $color; margin: 0 0 10px 0;'>Risk Skorunuz: %$riskScore</h2>
                        <p style='margin: 0;'>Bu sonuç, doktorunuz tarafından daha detaylı bir değerlendirme yapılmasını gerektirmektedir.</p>
                    </div>
                    
                    <div class='action-required'>
                        <h3 style='margin: 0 0 10px 0;'>✋ NE YAPMALISINIZ?</h3>
                        <p style='margin: 0;'>
                            Endişelenmeyin, bu sadece bir risk hesabıdır ve kesin teşhis değildir.<br><br>
                            Ancak önlem olarak <strong>en kısa sürede doktorunuzla iletişime geçmenizi</strong> öneririz.
                        </p>
                    </div>
                    

                </div>
                
                <div class='footer'>
                    <p><strong>CardioRisk Sağlık Asistanı</strong></p>
                    <p>Bu mesaj otomatik olarak oluşturulmuştur.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * İlerleme bildirimi email'i gönder
     */
    public function sendProgressNotification($doctorEmail, $doctorName, $patientName, $oldRisk, $newRisk) {
        $improvement = $oldRisk - $newRisk;
        $percentage = round(($improvement / $oldRisk) * 100, 1);
        
        $subject = "✅ Harika Haber: $patientName - Risk Skoru Düştü!";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10B981; color: white; padding: 20px; text-align: center; }
                .content { background: #f9fafb; padding: 20px; }
                .stats { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10B981; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Pozitif Gelişme!</h1>
                </div>
                <div class='content'>
                    <p>Sayın <strong>$doctorName</strong>,</p>
                    <p>Hastanız <strong>$patientName</strong>'in risk skorunda önemli bir düşüş gözlemlendi:</p>
                    
                    <div class='stats'>
                        <p><strong>Önceki Risk:</strong> $oldRisk%</p>
                        <p><strong>Yeni Risk:</strong> $newRisk%</p>
                        <p><strong>İyileşme:</strong> ▼ $improvement puan (%$percentage düşüş)</p>
                    </div>
                    
                    <p>Tedavi planınız olumlu sonuç veriyor. Hasta ile paylaşabilirsiniz.</p>
                </div>
                <div class='footer'>
                    <p>CardioRisk Karar Destek Sistemi</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($doctorEmail, $subject, $message);
    }

    /**
     * Destek Talebi Email'i Gönder
     */
    public function sendSupportTicket($userEmail, $userName, $subject, $priority, $messageBody) {
        // Admin email address (In a real app, this might be in config/db.php or settings)
        // For now, sending to the configured SMTP user or a hardcoded admin email
        $adminEmail = $this->config['smtp_user']; 
        
        $priorityLabels = [
            'low' => 'Düşük',
            'medium' => 'Orta',
            'high' => 'YÜKSEK (KRİTİK)'
        ];
        $priorityText = $priorityLabels[$priority] ?? 'Normal';
        $priorityColor = $priority === 'high' ? '#DC2626' : ($priority === 'medium' ? '#F59E0B' : '#10B981');

        $emailSubject = "🎫 Destek Talebi: $subject ($priorityText)";
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; }
                .header { background: #3B82F6; color: white; padding: 15px; text-align: center; }
                .field { margin-bottom: 10px; }
                .label { font-weight: bold; color: #666; }
                .message-box { background: #f9f9f9; padding: 15px; border-left: 4px solid #3B82F6; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Yeni Destek Talebi</h2>
                </div>
                <div style='padding: 20px;'>
                    <div class='field'>
                        <span class='label'>Gönderen:</span> $userName ($userEmail)
                    </div>
                    <div class='field'>
                        <span class='label'>Öncelik:</span> 
                        <span style='color: $priorityColor; font-weight: bold;'>$priorityText</span>
                    </div>
                    <div class='field'>
                        <span class='label'>Konu:</span> $subject
                    </div>
                    
                    <div class='message-box'>
                        <p style='margin-top:0'><strong>Mesaj:</strong></p>
                        " . nl2br(htmlspecialchars($messageBody)) . "
                    </div>
                    
                    <p style='font-size: 12px; color: #999; margin-top: 20px;'>
                        Bu mesaj CardioRisk yardım modülü üzerinden gönderilmiştir.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send to Admin
        return $this->sendEmail($adminEmail, $emailSubject, $htmlContent);
    }
    
    /**
     * PHPMailer ile Email Gönderimi (Ortak Metod)
     */
    private function sendEmail($toEmail, $subject, $body) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['smtp_user'];
            $mail->Password   = $this->config['smtp_pass'];
            $mail->SMTPSecure = $this->config['smtp_secure']; // PHPMailer::ENCRYPTION_STARTTLS
            $mail->Port       = $this->config['smtp_port'];
            
            // Charset
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // Recipients
            $mail->setFrom($this->config['smtp_user'], $this->config['from_name']); // SMTP user ile aynı olması önerilir
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            error_log("Email Sent Successfully to: $toEmail");
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Kritik uyarı HTML'i oluştur
     */
    private function buildCriticalAlertHtml($doctorName, $patientName, $riskScore, $visitData) {
        $riskLevel = $riskScore >= 80 ? 'KRİTİK' : 'YÜKSEK';
        $color = $riskScore >= 80 ? '#DC2626' : '#F59E0B';
        
        // Kritik parametreleri belirle
        $criticalParams = [];
        if (!empty($visitData)) {
            $chol = $visitData['chol'] ?? 0;
            $trestbps = $visitData['trestbps'] ?? ($visitData['bp'] ?? 0); // Handle both keys
            $ca = $visitData['ca'] ?? 0;
            $oldpeak = $visitData['oldpeak'] ?? 0;

            if ($chol > 240) $criticalParams[] = "Kolesterol: {$chol} mg/dL (>240)";
            if ($trestbps > 140) $criticalParams[] = "Kan Basıncı: {$trestbps} mmHg (>140)";
            if ($ca > 0) $criticalParams[] = "Tıkalı Koroner Damar: {$ca} adet";
            if ($oldpeak > 2.0) $criticalParams[] = "ST Çökmesi: {$oldpeak} mm";
        }
        
        $paramsHtml = '';
        if (!empty($criticalParams)) {
            $paramsHtml = '<div class="critical-params"><h3>⚠️ Kritik Parametreler:</h3><ul>';
            foreach ($criticalParams as $param) {
                $paramsHtml .= "<li>$param</li>";
            }
            $paramsHtml .= '</ul></div>';
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
                .header { background: $color; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .alert-badge { background: white; color: $color; padding: 10px 20px; border-radius: 20px; font-weight: bold; display: inline-block; margin: 10px 0; }
                .content { padding: 20px; background: #FEF3C7; border: 2px solid $color; }
                .risk-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid $color; }
                .critical-params { background: #FEE2E2; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .critical-params ul { margin: 10px 0; padding-left: 20px; }
                .critical-params li { margin: 5px 0; }
                .action-required { background: $color; color: white; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                .button { background: $color; color: white; padding: 12px 30px; text-decoration: none; display: inline-block; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 ACİL UYARI</h1>
                    <div class='alert-badge'>$riskLevel RİSK SEVİYESİ</div>
                </div>
                
                <div class='content'>
                    <p>Sayın <strong>Dr. $doctorName</strong>,</p>
                    
                    <p>Sistem, hastanız <strong>$patientName</strong> için kritik seviyede kardiyovasküler risk tespit etti.</p>
                    
                    <div class='risk-box'>
                        <h2 style='color: $color; margin: 0 0 10px 0;'>Risk Skoru: $riskScore%</h2>
                        <p style='margin: 0;'>Bu seviye <strong>acil tıbbi değerlendirme</strong> gerektirebilir.</p>
                    </div>
                    
                    $paramsHtml
                    
                    <div class='action-required'>
                        <h3 style='margin: 0 0 10px 0;'>✋ ÖNERİLEN ADIMLAR</h3>
                        <p style='margin: 0;'>
                            1. Hasta ile ivedi temas kurun<br>
                            2. Ek kardiyolojik testler değerlendirin<br>
                            3. İlaç tedavisini gözden geçirin<br>
                            4. Gerekirse kardiyoloji konsültasyonu planlayın
                        </p>
                    </div>
                    

                </div>
                
                <div class='footer'>
                    <p><strong>CardioRisk Karar Destek Sistemi</strong></p>
                    <p>Bu otomatik bir uyarıdır. Hastanın durumunu klinik olarak değerlendirin.</p>
                    <p style='font-size: 10px; color: #999;'>Gönderim Zamanı: " . date('d.m.Y H:i') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Email headers oluştur
     */
    private function buildHeaders() {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "X-Priority: 1"; // High priority
        
        return implode("\r\n", $headers);
    }
}
?>
