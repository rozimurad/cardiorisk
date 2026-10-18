<?php
/**
 * Sidebar Component
 * Navigation menu for the dashboard
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Define menu items
$menuItems = [
    [
        'icon' => 'bi-speedometer2',
        'title' => 'Ana Sayfa',
        'link' => 'dashboard.php',
        'page' => 'dashboard.php'
    ],
    [
        'icon' => 'bi-people',
        'title' => 'Hastalar',
        'link' => 'patients.php',
        'page' => 'patients.php'
    ],
    [
        'icon' => 'bi-person-plus',
        'title' => 'Hasta Ekle',
        'link' => 'add_patient.php',
        'page' => 'add_patient.php'
    ],
    [
        'icon' => 'bi-clipboard2-pulse',
        'title' => 'Yeni Ziyaret',
        'link' => 'new_visit.php',
        'page' => 'new_visit.php'
    ],
    [
        'icon' => 'bi-file-earmark-medical',
        'title' => 'Raporlar',
        'link' => 'reports.php',
        'page' => 'reports.php'
    ]
];
?>

<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse show">
    <div class="position-sticky pt-4">
        <ul class="nav flex-column">
            <?php foreach ($menuItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === $item['page']) ? 'active' : ''; ?>" 
                       href="<?php echo $item['link']; ?>">
                        <i class="bi <?php echo $item['icon']; ?> me-2"></i>
                        <?php echo $item['title']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <!-- Divider -->
        <hr class="my-3">
        
        <!-- Additional Links -->
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link text-muted" href="help.php">
                    <i class="bi bi-question-circle me-2"></i>
                    Yardım ve Destek
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-muted" href="documentation.php">
                    <i class="bi bi-book me-2"></i>
                    Dokümantasyon
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- Main Content Column -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
