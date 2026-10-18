<?php
/**
 * Documentation Module
 * Contains Medical Dictionary and Data Set Explanations
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dokümantasyon</h1>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0"><i class="bi bi-book-half me-2"></i>Tıbbi Sözlük (Veri Seti Kılavuzu)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-4">Aşağıdaki tablo, risk hesaplama algoritmasında kullanılan klinik veri noktalarının açıklamalarını içerir.</p>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 15%">Kısaltma</th>
                        <th style="width: 25%">Tam Adı</th>
                        <th>Açıklama ve Değerler</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-primary">CP</span></td>
                        <td>Chest Pain Type<br>(Göğüs Ağrısı Tipi)</td>
                        <td>
                            <strong>1:</strong> Tipik Anjina (Eforla gelen, dinlenince geçen)<br>
                            <strong>2:</strong> Atipik Anjina (Anjina benzeri ama tam uymayan)<br>
                            <strong>3:</strong> Anjinal Olmayan Ağrı<br>
                            <strong>4:</strong> Asemptomatik (Ağrı yok)
                        </td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-info text-dark">Trestbps</span></td>
                        <td>Resting Blood Pressure<br>(Dinlenme Kan Basıncı)</td>
                        <td>Hastaneye kabul anındaki dinlenme kan basıncı (mm Hg cinsinden).</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-warning text-dark">Chol</span></td>
                        <td>Serum Cholesterol<br>(Serum Kolesterolü)</td>
                        <td>mg/dl cinsinden serum kolesterol değeri.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-danger">Fbs</span></td>
                        <td>Fasting Blood Sugar<br>(Açlık Kan Şekeri)</td>
                        <td>
                            <strong>1:</strong> > 120 mg/dl (Diyabet riski)<br>
                            <strong>0:</strong> < 120 mg/dl (Normal)
                        </td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-secondary">Restecg</span></td>
                        <td>Resting ECG<br>(Dinlenme EKG)</td>
                        <td>
                            <strong>0:</strong> Normal<br>
                            <strong>1:</strong> ST-T Dalga Anomalisi (T dalgası inversiyonu veya >0.05mV ST yükselmesi/çökmesi)<br>
                            <strong>2:</strong> Sol Ventriküler Hipertrofi
                        </td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">Thalach</span></td>
                        <td>Max Heart Rate<br>(Maks. Kalp Hızı)</td>
                        <td>Ulaşılan maksimum kalp atış hızı.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-dark">Exang</span></td>
                        <td>Exercise Induced Angina<br>(Egzersiz Anjinası)</td>
                        <td>
                            <strong>1:</strong> Evet (Egzersizle ağrı oluyor)<br>
                            <strong>0:</strong> Hayır
                        </td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-primary">Slope</span></td>
                        <td>ST Segment Slope<br>(ST Eğimi)</td>
                        <td>
                            <strong>1:</strong> Yükselen (Upsloping)<br>
                            <strong>2:</strong> Düz (Flat)<br>
                            <strong>3:</strong> Alçalan (Downsloping) - En riskli
                        </td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-info text-dark">Thal</span></td>
                        <td>Thalassemia<br>(Talasemi Durumu)</td>
                        <td>
                            <strong>3:</strong> Normal<br>
                            <strong>6:</strong> Sabit Defekt (Kalıcı hasar)<br>
                            <strong>7:</strong> Tersinir Defekt (Geçici iskemi)
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
