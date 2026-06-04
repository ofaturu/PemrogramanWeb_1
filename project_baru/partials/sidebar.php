<div class="sidebar sidebar-dark sidebar-fixed border-end" id="sidebar">
  <div class="sidebar-header border-bottom">
    <div class="sidebar-brand me-auto">
      <a href="dashboard.php" class="text-decoration-none text-white d-flex align-items-center gap-2">
        <i class="fa fa-car fa-lg text-primary"></i>
        <span class="fs-4 fw-bold">FTrans</span>
      </a>
    </div>
    <button class="btn-close d-lg-none" type="button" data-coreui-theme="dark" aria-label="Close" onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()"></button>
  </div>
  <ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
        <i class="nav-icon fa fa-table me-2"></i>
        Data Kendaraan
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'add' ? 'active' : '' ?>" href="add.php">
        <i class="nav-icon fa fa-plus me-2"></i>
        Tambah Kendaraan
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>" href="profile.php">
        <i class="nav-icon fa fa-user-edit me-2"></i>
        My Profile
      </a>
    </li>
    <li class="nav-divider"></li>
    <li class="nav-item">
      <a class="nav-link text-danger" href="logout.php">
        <i class="nav-icon fa fa-sign-out-alt me-2 text-danger"></i>
        Logout
      </a>
    </li>
  </ul>
  <div class="sidebar-footer border-top d-none d-md-flex">
    <button class="sidebar-toggler" type="button" data-coreui-toggle="unfoldable"></button>
  </div>
</div>
