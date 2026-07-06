<?php
$nama_user_header = htmlspecialchars($_SESSION['user_nama'] ?? 'User');
?>
<header class="header header-sticky p-0 mb-4">
  <div class="container-fluid border-bottom px-4">
    <!-- Sidebar Toggler -->
    <button class="header-toggler" type="button" onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()" style="margin-inline-start: -14px">
      <i class="fa fa-bars fa-lg text-body"></i>
    </button>
    
    <!-- Title / Breadcrumb (hidden on small devices) -->
    <ul class="header-nav d-none d-md-flex ms-2">
      <li class="nav-item">
        <span class="nav-link fs-5 fw-semibold text-body"><?= isset($pageTitle) ? $pageTitle : 'FTrans Management' ?></span>
      </li>
    </ul>

    <!-- Right Side Navigation -->
    <ul class="header-nav ms-auto gap-2">
      <!-- Theme Switcher -->
      <li class="nav-item dropdown">
        <button class="btn btn-link nav-link py-2 px-2 d-flex align-items-center" type="button" aria-expanded="false" data-coreui-toggle="dropdown">
          <svg class="icon icon-lg theme-icon-active" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="20" height="20">
            <path fill="currentColor" d="M256 16C123.452 16 16 123.452 16 256s107.452 240 240 240 240-107.452 240-240S388.548 16 256 16m-22 446.849a208.35 208.35 0 0 1-169.667-125.9c-.364-.859-.706-1.724-1.057-2.587L234 429.939Zm0-69.582L50.889 290.76A210 210 0 0 1 48 256q0-9.912.922-19.67L234 339.939Zm0-90L54.819 202.96a206 206 0 0 1 9.514-27.913Q67.1 168.5 70.3 162.191L234 253.934Zm0-86.015L86.914 134.819a209.4 209.4 0 0 1 22.008-25.9q3.72-3.72 7.6-7.228L234 166.027Zm0-87.708-89.648-49.093A206.95 206.95 0 0 1 234 49.151ZM464 256a207.775 207.775 0 0 1-198 207.761V48.239A207.79 207.79 0 0 1 464 256" />
          </svg>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="--cui-dropdown-min-width: 8rem">
          <li>
            <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="light">
              <svg class="icon icon-lg me-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16">
                <path fill="currentColor" d="M256 104c-83.813 0-152 68.187-152 152s68.187 152 152 152 152-68.187 152-152-68.187-152-152-152m0 272a120 120 0 1 1 120-120 120.136 120.136 0 0 1-120 120M240 16h32v48h-32zm0 432h32v48h-32zm208-208h48v32h-48zm-432 0h48v32H16zm372.687 171.314 22.627-22.627 32 32-22.627 22.627zm-320-320 22.628-22.628 32 32-22.628 22.628zm-.002 329.375 32-32 22.628 22.626-32 32zm320.002-320.003 32-32 22.628 22.628-32 32" />
              </svg>
              Light
            </button>
          </li>
          <li>
            <button class="dropdown-item d-flex align-items-center" type="button" data-coreui-theme-value="dark">
              <svg class="icon icon-lg me-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16">
                <path fill="currentColor" d="M268.279 496c-67.574 0-130.978-26.191-178.534-73.745S16 311.293 16 243.718A252.25 252.25 0 0 1 154.183 18.676a24.44 24.44 0 0 1 34.46 28.958 220.12 220.12 0 0 0 54.8 220.923A218.75 218.75 0 0 0 399.085 333.2a220.2 220.2 0 0 0 65.277-9.846 24.439 24.439 0 0 1 28.959 34.461A252.26 252.26 0 0 1 268.279 496M153.31 55.781A219.3 219.3 0 0 0 48 243.718C48 365.181 146.816 464 268.279 464a219.3 219.3 0 0 0 187.938-105.31 253 253 0 0 1-57.13 6.513 250.54 250.54 0 0 1-178.268-74.016 252.15 252.15 0 0 1-67.509-235.4Z" />
              </svg>
              Dark
            </button>
          </li>
          <li>
            <button class="dropdown-item d-flex align-items-center active" type="button" data-coreui-theme-value="auto">
              <svg class="icon icon-lg me-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16">
                <path fill="currentColor" d="M256 16C123.452 16 16 123.452 16 256s107.452 240 240 240 240-107.452 240-240S388.548 16 256 16m-22 446.849a208.35 208.35 0 0 1-169.667-125.9c-.364-.859-.706-1.724-1.057-2.587L234 429.939Zm0-69.582L50.889 290.76A210 210 0 0 1 48 256q0-9.912.922-19.67L234 339.939Zm0-90L54.819 202.96a206 206 0 0 1 9.514-27.913Q67.1 168.5 70.3 162.191L234 253.934Zm0-86.015L86.914 134.819a209.4 209.4 0 0 1 22.008-25.9q3.72-3.72 7.6-7.228L234 166.027Zm0-87.708-89.648-49.093A206.95 206.95 0 0 1 234 49.151ZM464 256a207.775 207.775 0 0 1-198 207.761V48.239A207.79 207.79 0 0 1 464 256" />
              </svg>
              Auto
            </button>
          </li>
        </ul>
      </li>

      <!-- Divider -->
      <li class="nav-item py-1">
        <div class="vr h-100 mx-2 text-body text-opacity-75"></div>
      </li>

      <!-- Notification Dropdown -->
      <li class="nav-item dropdown" id="notificationDropdownContainer">
        <button class="btn btn-link nav-link py-2 px-2 d-flex align-items-center position-relative text-body" type="button" aria-expanded="false" data-coreui-toggle="dropdown" id="notificationBellBtn">
          <i class="fa fa-bell" style="font-size: 1.15rem;"></i>
          <span class="position-absolute top-2 start-75 translate-middle badge rounded-pill bg-danger d-none" id="notificationBadge" style="font-size: 0.65rem;">
            0
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end pt-0 shadow-sm border border-secondary border-opacity-25" style="width: 320px; max-height: 400px; overflow-y: auto;" id="notificationList">
          <div class="dropdown-header bg-body-tertiary text-body-secondary fw-semibold rounded-top d-flex justify-content-between align-items-center mb-2">
            <span>Pemberitahuan</span>
            <button class="btn btn-link p-0 text-decoration-none" style="font-size: 0.75rem;" id="markAllReadBtn">Tandai semua dibaca</button>
          </div>
          <div id="notificationItemsContainer">
            <!-- Notifications loaded here dynamically -->
            <li class="text-center text-muted py-3 small">Tidak ada pemberitahuan baru.</li>
          </div>
        </ul>
      </li>

      <!-- User Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link py-0 pe-0 d-flex align-items-center gap-2" data-coreui-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
          <div class="avatar avatar-md border border-secondary bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:50%;">
            <?= strtoupper(substr($nama_user_header, 0, 1)) ?>
          </div>
          <span class="d-none d-md-inline-block text-body fw-semibold"><?= $nama_user_header ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-end pt-0 shadow-sm border border-secondary border-opacity-25">
          <div class="dropdown-header bg-body-tertiary text-body-secondary fw-semibold rounded-top mb-2">Settings</div>
          <a class="dropdown-item d-flex align-items-center gap-2" href="profile.php">
            <i class="fa fa-user-edit text-muted"></i> My Profile
          </a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="logout.php">
            <i class="fa fa-sign-out-alt text-danger"></i> Log Out
          </a>
        </div>
      </li>
    </ul>
  </div>
</header>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const badge = document.getElementById("notificationBadge");
    const container = document.getElementById("notificationItemsContainer");
    const markAllBtn = document.getElementById("markAllReadBtn");

    // Request browser notification permission
    if (window.Notification && Notification.permission === "default") {
        Notification.requestPermission();
    }

    let loadedIds = new Set();

    function fetchNotifications() {
        fetch("get_notifications.php")
            .then(res => res.json())
            .then(data => {
                const notifs = data.notifications || [];
                if (notifs.length > 0) {
                    badge.innerText = notifs.length;
                    badge.classList.remove("d-none");

                    container.innerHTML = "";
                    notifs.forEach(n => {
                        const li = document.createElement("li");
                        li.className = "dropdown-item py-2 border-bottom";
                        li.style.whiteSpace = "normal";
                        li.style.cursor = "pointer";
                        li.innerHTML = `
                            <div class="fw-bold small text-body-emphasis">${n.title}</div>
                            <div class="small text-body-secondary mb-1">${n.message}</div>
                            <div class="text-muted" style="font-size: 0.7rem;">${new Date(n.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}</div>
                        `;
                        
                        li.addEventListener("click", () => {
                            const formData = new FormData();
                            formData.append("action", "read");
                            formData.append("id", n.id);
                            fetch("get_notifications.php", {
                                method: "POST",
                                body: formData
                            }).then(() => {
                                fetchNotifications();
                            });
                        });
                        
                        container.appendChild(li);

                        // Trigger native push
                        if (!loadedIds.has(n.id)) {
                            loadedIds.add(n.id);
                            if (window.Notification && Notification.permission === "granted") {
                                new Notification(n.title, {
                                    body: n.message
                                });
                            }
                        }
                    });
                } else {
                    badge.innerText = "0";
                    badge.classList.add("d-none");
                    container.innerHTML = `<li class="text-center text-muted py-3 small">Tidak ada pemberitahuan baru.</li>`;
                }
            })
            .catch(err => console.error("Error fetching notifications:", err));
    }

    markAllBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        const formData = new FormData();
        formData.append("action", "read_all");
        fetch("get_notifications.php", {
            method: "POST",
            body: formData
        }).then(() => {
            fetchNotifications();
        });
    });

    fetchNotifications();
    setInterval(fetchNotifications, 15000);
});
</script>
