<?php
require_once __DIR__ . '/../config/db.php';

// Check if FPDF exists
if (!file_exists(__DIR__ . '/fpdf/fpdf.php')) {
    die('<div style="font-family:sans-serif; text-align:center; padding:50px;">
            <h1>FPDF Kütüphanesi Eksik</h1>
            <p>Lütfen <b>fpdf.php</b> dosyasını indirip aşağıdaki klasöre ekleyiniz:</p>
            <code>' . __DIR__ . '\fpdf\fpdf.php</code>
            <p><a href="http://www.fpdf.org/en/download.php" target="_blank">FPDF İndir</a></p>
         </div>');
}

require_once __DIR__ . '/fpdf/fpdf.php';

class ReportGenerator extends FPDF {
    private $doctor;
    private $patient;
    private $visit;
    private $clinicName;
    private $clinicAddress;
    private $clinicLogo;
    private $footerText;

    public function __construct($doctor, $patient, $visit) {
        parent::__construct();
        $this->doctor = $doctor;
        $this->patient = $patient;
        $this->visit = $visit;
        
        // Klinik Bilgileri
        $this->clinicName = !empty($doctor['clinic_name']) ? $doctor['clinic_name'] : 'Kardiyoloji Kliniği';
        $this->clinicAddress = !empty($doctor['clinic_address']) ? $doctor['clinic_address'] : '';
        $this->clinicLogo = !empty($doctor['clinic_logo']) ? __DIR__ . '/../assets/uploads/' . $doctor['clinic_logo'] : null;
        $this->footerText = !empty($doctor['report_footer']) ? $doctor['report_footer'] : 'Bu rapor CardioRisk Karar Destek Sistemi tarafından oluşturulmuştur.';
        
        // Türkçe karakter desteği için (Basit çözüm: Windows-1254 veya ISO-8859-9)
        // FPDF default is ISO-8859-1. Turkish chars need conversion usually or a compatible font.
        // We will stick to core fonts and convert text.
    }

    // Türkçe Karakter Dönüştürücü
    private function tr($str) {
        return iconv('utf-8', 'iso-8859-9//TRANSLIT//IGNORE', $str);
    }
    
    // Header
    function Header() {
        // Logo
        if ($this->clinicLogo && file_exists($this->clinicLogo)) {
            $this->Image($this->clinicLogo, 10, 6, 30);
        }
        
        // Klinik Adı (Ortala/Sağa yasla)
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80); // Move to right
        $this->Cell(30, 10, $this->tr($this->clinicName), 0, 1, 'C');
        
        // Adres
        if ($this->clinicAddress) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(80);
            $this->MultiCell(110, 5, $this->tr($this->clinicAddress), 0, 'C');
        }
        
        // Çizgi
        $this->Ln(20);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, 35, 200, 35);
        $this->Ln(10);
    }

    // Footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, $this->tr($this->footerText . ' - Sayfa ' . $this->PageNo()), 0, 0, 'C');
    }

    // Rapor Oluşturma
    public function generate() {
        $this->AddPage();
        
        // Başlık
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 10, $this->tr('KARDİYAK RİSK DEĞERLENDİRME RAPORU'), 0, 1, 'C');
        $this->Ln(10);
        
        // 1. Hasta Bilgileri
        $this->SetFillColor(240, 242, 245);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->tr('  HASTA BİLGİLERİ'), 0, 1, 'L', true);
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, $this->tr('Ad Soyad:'), 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(60, 7, $this->tr($this->patient['full_name']), 0, 0);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, $this->tr('TC Kimlik:'), 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(50, 7, $this->patient['tc_identity'], 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, $this->tr('Doğum Tarihi / Yaş:'), 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $age = date_diff(date_create($this->patient['birth_date']), date_create('today'))->y;
        $this->Cell(60, 7, $this->patient['birth_date'] . ' (' . $age . ')', 0, 0);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(40, 7, $this->tr('Cinsiyet:'), 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $gender = $this->patient['gender'] == 'male' ? 'Erkek' : 'Kadın';
        $this->Cell(50, 7, $this->tr($gender), 0, 1);
        
        $this->Ln(10);
        
        // 2. Muayene Bulguları (Tablo)
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->tr('  KLİNİK BULGULAR'), 0, 1, 'L', true);
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        
        // Satır 1
        $this->Cell(50, 7, $this->tr('Muayene Tarihi:'), 0, 0);
        $this->Cell(50, 7, date('d.m.Y', strtotime($this->visit['visit_date'])), 0, 0, 'L');
        $this->Cell(50, 7, $this->tr('Göğüs Ağrısı:'), 0, 0);
        $cpTypes = [1=>'Tipik Anjina', 2=>'Atipik Anjina', 3=>'Anjinal Olmayan', 4=>'Asemptomatik'];
        $this->Cell(40, 7, $this->tr($cpTypes[$this->visit['cp']] ?? '-'), 0, 1);
        
        // Satır 2
        $this->Cell(50, 7, $this->tr('Kan Basıncı:'), 0, 0);
        $this->Cell(50, 7, $this->visit['trestbps'] . ' mm Hg', 0, 0);
        $this->Cell(50, 7, $this->tr('Kolesterol:'), 0, 0);
        $this->Cell(40, 7, $this->visit['chol'] . ' mg/dL', 0, 1);
        
        // Satır 3
        $this->Cell(50, 7, $this->tr('Açlık Kan Şekeri:'), 0, 0);
        $fbs = $this->visit['fbs'] ? '> 120 mg/dL' : '< 120 mg/dL';
        $this->Cell(50, 7, $fbs, 0, 0);
        $this->Cell(50, 7, $this->tr('Maks. Kalp Hızı:'), 0, 0);
        $this->Cell(40, 7, $this->visit['thalach'] . ' bpm', 0, 1);
        
        $this->Ln(10);
        
        // 3. Risk Değerlendirmesi
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->tr('  RİSK ANALİZİ SONUCU'), 0, 1, 'L', true);
        $this->Ln(5);
        
        $riskScore = $this->visit['calculated_risk_score'];
        $riskColor = $riskScore >= 50 ? [220, 53, 69] : [40, 167, 69]; // Red or Green
        $riskText = $riskScore >= 50 ? 'YÜKSEK RİSK' : 'DÜŞÜK RİSK';
        
        // Kutu çiz
        $this->SetFillColor($riskColor[0], $riskColor[1], $riskColor[2]);
        $this->Rect(10, $this->GetY(), 190, 25, 'F');
        
        // Metin
        $this->SetXY(10, $this->GetY() + 5);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, $this->tr("RISK SKORU: %$riskScore"), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, $this->tr("SEVİYE: $riskText"), 0, 1, 'C');
        $this->SetTextColor(0, 0, 0); // Siyah
        
        $this->Ln(15);
        
        // 4. Yapay Zeka Önerileri
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->tr('  YAPAY ZEKA ÖNERİLERİ'), 0, 1, 'L', true);
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $recommendations = explode("\n", $this->visit['ai_recommendation']);
        foreach ($recommendations as $rec) {
            if (trim($rec)) {
                $this->Cell(5, 7, '-', 0, 0);
                $this->MultiCell(0, 7, $this->tr(trim($rec)), 0, 'L');
            }
        }
        
        $this->Ln(20);
        
        // 5. İmza Alanı
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(120); // Move right
        $this->Cell(60, 5, $this->tr($this->doctor['title'] . ' ' . $this->doctor['full_name']), 0, 1, 'C');
        $this->Cell(120);
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, $this->tr($this->doctor['specialty']), 0, 1, 'C');
        $this->Cell(120);
        $this->Cell(60, 5, $this->tr('Dip. No: ' . $this->doctor['diploma_no']), 0, 1, 'C');
        
        // Dijital İmza Görseli
        $sigPath = !empty($this->doctor['digital_signature']) ? __DIR__ . '/../assets/uploads/' . $this->doctor['digital_signature'] : null;
        if ($sigPath && file_exists($sigPath)) {
            $this->Image($sigPath, 140, $this->GetY() + 5, 40);
        } else {
            $this->Ln(15);
            $this->Cell(120);
            $this->Cell(60, 5, '(Imza)', 0, 1, 'C');
        }
    }
}
?>
