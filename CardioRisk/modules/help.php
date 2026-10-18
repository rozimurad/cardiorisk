<?php
/**
 * Help & Support Module
 * FAQ and Support Ticket System
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}


// Handle AJAX Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    header('Content-Type: application/json');
    
    // Validate inputs
    $subject = trim($_POST['subject'] ?? '');
    $priority = trim($_POST['priority'] ?? 'medium');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları doldurunuz.']);
        exit();
    }
    
    // Get User Details
    $db = getDB();
    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Send Email
    require_once __DIR__ . '/../core/EmailNotifier.php';
    $notifier = new EmailNotifier(); // Assuming config is loaded in constructor
    
    // Note: User might not have email in DB if early version, fallback to generic
    $userEmail = $user['email'] ?? 'unknown@user.com';
    $userName = $user['full_name'] ?? 'Bilinmeyen Kullanıcı';
    
    $sent = $notifier->sendSupportTicket($userEmail, $userName, $subject, $priority, $message);
    
    if ($sent) {
        $ticketId = 'TR-' . rand(10000, 99999);
        echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email gönderilemedi. SMTP ayarlarını kontrol edin.']);
    }
    exit();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Yardım ve Destek Merkezi</h1>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="helpTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab">
            <i class="bi bi-question-circle-fill me-2"></i>Sıkça Sorulan Sorular
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="support-tab" data-bs-toggle="tab" data-bs-target="#support" type="button" role="tab">
            <i class="bi bi-headset me-2"></i>Destek Talebi
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="helpTabsContent">
    
    <!-- Tab 1: FAQ -->
    <div class="tab-pane fade show active" id="faq" role="tabpanel">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="accordion shadow-sm" id="accordionFAQ">
                    
                    <!-- Q1 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                <i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>
                                Sistem risk skorunu nasıl hesaplıyor?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                <strong>Yapay zeka tabanlı algoritmamız</strong>, Cleveland Kalp Hastalığı veri setinden eğitilmiş bir model kullanır. 
                                Girilen 13 farklı klinik parametreyi (yaş, cinsiyet, göğüs ağrısı tipi vb.) analiz eder ve bu parametreleri 
                                geçmiş 3000+ vaka verisiyle kıyaslayarak ağırlıklı bir risk puanı (%0-%100) üretir.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Q2 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                <i class="bi bi-pen-fill me-2 text-primary"></i>
                                PDF Raporuna imzamı nasıl eklerim?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                İmzalı rapor oluşturabilmek için öncelikle imzanızı sisteme yüklemeniz gerekir:
                                <ol class="mt-2">
                                    <li>Sağ üst köşedeki profil fotoğrafınıza tıklayın.</li>
                                    <li>Açılan menüden <strong>Ayarlar</strong> seçeneğine gidin.</li>
                                    <li>"Doktor Profil Ayarları" sekmesinde <strong>Dijital İmza</strong> alanını bulun.</li>
                                    <li>İmzanızın bulunduğu PNG veya JPG dosyasını yükleyin ve kaydedin.</li>
                                </ol>
                                Bundan sonra oluşturacağınız tüm PDF raporlarında imzanız otomatik olarak görünecektir.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Q3 -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                <i class="bi bi-shield-check me-2 text-primary"></i>
                                Hasta verileri güvende mi?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFAQ">
                            <div class="accordion-body">
                                Evet, veri güvenliği bizim için en öncelikli konudur.
                                <ul>
                                    <li>Tüm veriler KVKK ve GDPR uyumlu olarak 256-bit SSL şifreleme ile sunucularda saklanmaktadır.</li>
                                    <li>Şifreleriniz veritabanında "Hash"lenmiş (karıştırılmış) olarak tutulur, yöneticiler dahil kimse göremez.</li>
                                    <li>Her erişim (Login) ve işlem loglanarak (Access Logs) kayıt altına alınır.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Support Form -->
    <div class="tab-pane fade" id="support" role="tabpanel">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>Yeni Destek Talebi</h5>
                    </div>
                    <div class="card-body p-4">
                        <form id="supportForm" onsubmit="submitSupportTicket(event)">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Konu</label>
                                <input type="text" class="form-control" id="subject" name="subject" placeholder="Örn: PDF oluştururken hata alıyorum" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Öncelik Seviyesi</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low">Düşük</option>
                                    <option value="medium" selected>Orta</option>
                                    <option value="high">Yüksek (Kritik)</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="message" class="form-label">Mesajınız</label>
                                <textarea class="form-control" id="message" name="message" rows="5" placeholder="Sorununuzu detaylı bir şekilde açıklayınız..." required></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send me-2"></i>Talebimi Gönder
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Tab Activation (Bootstrap 5)
    var triggerTabList = [].slice.call(document.querySelectorAll('#helpTabs a'))
    triggerTabList.forEach(function (triggerEl) {
      var tabTrigger = new bootstrap.Tab(triggerEl)
      triggerEl.addEventListener('click', function (event) {
        event.preventDefault()
        tabTrigger.show()
      })
    })

    // Support Form Handling with AJAX
    function submitSupportTicket(e) {
        e.preventDefault();
        
        const form = document.getElementById('supportForm');
        const submitBtn = document.getElementById('submitBtn');
        const originalBtnText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gönderiliyor...';
        
        const formData = new FormData(form);
        formData.append('action', 'submit_ticket');
        
        fetch('help.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Talebiniz İletildi!',
                    html: 'Destek ekibimiz en kısa sürede e-posta ile dönüş yapacaktır.<br><br><strong>Takip Numaranız:</strong> <span class="text-primary fw-bold">' + data.ticket_id + '</span>',
                    confirmButtonText: 'Tamam',
                    confirmButtonColor: '#0d6efd'
                }).then(() => {
                    form.reset();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: data.message || 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
                    confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Bağlantı Hatası',
                text: 'Sunucuya ulaşılamadı.',
                confirmButtonColor: '#dc3545'
            });
        })
        .finally(() => {
            // Restore button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
