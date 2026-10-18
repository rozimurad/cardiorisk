<?php
/**
 * SMTP Mail Configuration
 * Gerçek e-posta gönderimi için gerekli ayarlar
 */

return [
    'smtp_host' => 'smtp.gmail.com',         // Örn: smtp.gmail.com
    'smtp_port' => 587,                      // 587 (TLS) veya 465 (SSL)
    'smtp_user' => 'timuzi123@gmail.com',  // Gmail adresiniz
    'smtp_pass' => 'ngud chxx wzqj ilie',       // Gmail "App Password" (Normal şifre değil!)
    'smtp_secure' => 'tls',                  // 'tls' veya 'ssl'
    'from_email' => 'timuzi123@gmail.com', // Gönderen görünen adres
    'from_name' => 'CardioRisk Sistemi'
];
?>
