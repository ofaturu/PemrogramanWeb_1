<?php
require_once 'config.php';

// Secure page: user must be logged in and must be admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
    header('Location: analytics.php');
    exit;
}

// Fetch all rental bookings for the calendar
$query = "
    SELECT p.id_sewa, p.tanggal_sewa, p.tanggal_kembali, p.status, p.total_biaya,
           u.nama AS nama_user, u.email AS email_user, k.nama_kendaraan
    FROM penyewaan p
    JOIN users u ON p.id_user = u.id
    JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
";
$res = mysqli_query($mysqli, $query);
$events = [];

while ($row = mysqli_fetch_assoc($res)) {
    // Map status to calendar event colors
    $color = '#5856d6'; // default
    if ($row['status'] === 'booking') {
        $color = '#f9b115'; // yellow/warning
    } elseif ($row['status'] === 'sedang_disewa') {
        $color = '#39f'; // blue/info
    } elseif ($row['status'] === 'selesai') {
        $color = '#1b9e3e'; // green/success
    } elseif ($row['status'] === 'dibatalkan') {
        $color = '#e55353'; // red/danger
    }

    $events[] = [
        'id'    => $row['id_sewa'],
        'title' => htmlspecialchars($row['nama_kendaraan']) . ' - ' . htmlspecialchars($row['nama_user']),
        'start' => $row['tanggal_sewa'],
        'end'   => $row['tanggal_kembali'],
        'color' => $color,
        'extendedProps' => [
            'penyewa'   => htmlspecialchars($row['nama_user']),
            'email'     => htmlspecialchars($row['email_user']),
            'total'     => 'Rp ' . number_format($row['total_biaya'], 0, ',', '.'),
            'status'    => ucfirst(str_replace('_', ' ', $row['status'])),
            'kendaraan' => htmlspecialchars($row['nama_kendaraan'])
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Kalender Pemesanan — FTrans';
include 'partials/head.php';
?>
<!-- FullCalendar 6 CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<style>
  /* Style matching calendar to dark mode */
  [data-coreui-theme="dark"] .fc {
    --fc-page-bg-color: #212631;
    --fc-neutral-bg-color: #2e3542;
    --fc-border-color: #3c4354;
    --fc-text-color: rgba(255, 255, 255, 0.87);
    --fc-today-bg-color: rgba(255, 255, 255, 0.05);
  }
  .fc-event {
    cursor: pointer;
    font-size: 0.85rem;
    padding: 2px 4px;
    border-radius: 4px;
    border: none !important;
  }
  .fc-header-title {
    font-size: 1.25rem !important;
  }
</style>

<body>
  <?php 
  $activePage = 'calendar';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Kalender Pemesanan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-calendar-alt me-2 text-primary"></i>Kalender Jadwal Sewa Kendaraan</h5>
            
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-warning text-dark"><i class="fa fa-clock"></i> Booking</span>
              <span class="badge bg-info text-white"><i class="fa fa-key"></i> Sedang Disewa</span>
              <span class="badge bg-success text-white"><i class="fa fa-check"></i> Selesai</span>
              <span class="badge bg-danger text-white"><i class="fa fa-times"></i> Dibatalkan</span>
            </div>
          </div>
          <div class="card-body p-4">
            <div id="calendar"></div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>

  <!-- Event Details Modal -->
  <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="eventModalLabel"><i class="fa fa-info-circle me-2"></i>Detail Transaksi Rental</h5>
          <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <table class="table table-striped align-middle mb-0">
            <tbody>
              <tr><td class="fw-semibold text-muted" style="width: 40%;">Kendaraan:</td><td id="modal-kendaraan" class="fw-bold"></td></tr>
              <tr><td class="fw-semibold text-muted">Nama Penyewa:</td><td id="modal-penyewaan"></td></tr>
              <tr><td class="fw-semibold text-muted">Email:</td><td id="modal-email"></td></tr>
              <tr><td class="fw-semibold text-muted">Mulai Sewa:</td><td id="modal-mulai"></td></tr>
              <tr><td class="fw-semibold text-muted">Batas Kembali:</td><td id="modal-kembali"></td></tr>
              <tr><td class="fw-semibold text-muted">Total Tarif:</td><td id="modal-tarif" class="fw-bold text-success"></td></tr>
              <tr><td class="fw-semibold text-muted">Status:</td><td><span id="modal-status" class="badge"></span></td></tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Tutup</button>
          <a id="modal-link-detail" href="#" class="btn btn-primary"><i class="fa fa-edit me-1"></i> Edit Transaksi</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const calendarEl = document.getElementById('calendar');
      
      const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        locale: 'id',
        events: <?= json_encode($events) ?>,
        eventClick: function(info) {
          const props = info.event.extendedProps;
          
          document.getElementById('modal-kendaraan').innerText = props.kendaraan;
          document.getElementById('modal-penyewaan').innerText = props.penyewa;
          document.getElementById('modal-email').innerText = props.email;
          
          const optDate = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
          document.getElementById('modal-mulai').innerText = info.event.start.toLocaleDateString('id-ID', optDate);
          document.getElementById('modal-kembali').innerText = info.event.end.toLocaleDateString('id-ID', optDate);
          
          document.getElementById('modal-tarif').innerText = props.total;
          
          const statusEl = document.getElementById('modal-status');
          statusEl.innerText = props.status;
          
          // Clear styles and apply new status badge
          statusEl.className = 'badge';
          if (props.status.toLowerCase() === 'booking') {
            statusEl.classList.add('bg-warning', 'text-dark');
          } else if (props.status.toLowerCase() === 'sedang disewa') {
            statusEl.classList.add('bg-info', 'text-white');
          } else if (props.status.toLowerCase() === 'selesai') {
            statusEl.classList.add('bg-success', 'text-white');
          } else {
            statusEl.classList.add('bg-danger', 'text-white');
          }
          
          // Set edit link target modal coreui trigger
          document.getElementById('modal-link-detail').href = 'sewa.php?search=' + encodeURIComponent(props.penyewa);
          
          const eventModal = new coreui.Modal(document.getElementById('eventModal'));
          eventModal.show();
        }
      });
      
      calendar.render();
    });
  </script>
</body>
</html>
