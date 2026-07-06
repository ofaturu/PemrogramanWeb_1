<div class="sidebar sidebar-fixed border-end" id="sidebar">
  <div class="sidebar-header border-bottom">
    <div class="sidebar-brand me-auto">
      <a href="index.php" class="text-decoration-none text-body d-flex align-items-center gap-2">
        <i class="fa fa-car fa-lg text-primary"></i>
        <span class="fs-4 fw-bold">FTrans</span>
      </a>
    </div>
    <button class="btn-close d-lg-none" type="button" aria-label="Close" onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()"></button>
  </div>
  <ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
        <i class="nav-icon fa fa-table me-2"></i>
        Data Kendaraan
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'sewa' ? 'active' : '' ?>" href="sewa.php">
        <i class="nav-icon fa fa-receipt me-2"></i>
        Data Penyewaan
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>" href="users.php">
        <i class="nav-icon fa fa-users me-2"></i>
        Data User
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
