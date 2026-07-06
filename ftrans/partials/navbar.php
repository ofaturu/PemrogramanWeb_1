<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);
?>
<nav class="navbar navbar-expand-lg bg-secondary navbar-dark">
    <div class="container-fluid">
        <a href="dashboard.php" class="navbar-brand d-flex align-items-center">
            <i class="bi bi-car-front-fill me-2"></i>
            <span>Rental Mobil</span>
        </a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link">Hello, <?php echo $nama_user; ?></span>
                <a href="logout.php" class="nav-item nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>
