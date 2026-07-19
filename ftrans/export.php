<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

// Cek autentikasi session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$target = $_GET['target'] ?? '';
$format = $_GET['format'] ?? '';

if (!in_array($target, ['kendaraan', 'sewa', 'users', 'analytics']) || !in_array($format, ['excel', 'word', 'pdf'])) {
    die("Parameter ekspor tidak valid.");
}

$data = [];
$detail_data = null;
$user_rentals = [];
$title_text = "";
$headers = [];
$is_detail = false;
$detail_id = '';

// Check individual detail parameters
if ($target === 'kendaraan') {
    $kode = trim($_GET['kode'] ?? '');
    if ($kode !== '') {
        $is_detail = true;
        $detail_id = $kode;
        
        $stmt = mysqli_prepare($mysqli, "
            SELECT k.*, m.nama_merk 
            FROM kendaraan k 
            LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
            WHERE k.kode_unik_kendaraan = ?
        ");
        mysqli_stmt_bind_param($stmt, 's', $kode);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $detail_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$detail_data) {
            die("Data kendaraan tidak ditemukan.");
        }
    }
} elseif ($target === 'sewa') {
    $id_sewa = intval($_GET['id'] ?? 0);
    if ($id_sewa > 0) {
        $is_detail = true;
        $detail_id = $id_sewa;
        
        $stmt = mysqli_prepare($mysqli, "
            SELECT p.*, u.nama AS nama_user, u.email AS email_user, k.nama_kendaraan, k.harga_per_hari, m.nama_merk 
            FROM penyewaan p 
            LEFT JOIN users u ON p.id_user = u.id 
            LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
            LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
            WHERE p.id_sewa = ?
        ");
        mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $detail_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$detail_data) {
            die("Data transaksi penyewaan tidak ditemukan.");
        }
           
           // Enforce role permission check for individual rental details
           if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin' && $detail_data['id_user'] != $_SESSION['user_id']) {
               die("Akses ditolak: Anda tidak memiliki wewenang untuk mengekspor detail transaksi penyewaan ini.");
           }
    }
} elseif ($target === 'users') {
    $id_user = intval($_GET['id'] ?? 0);
    if ($id_user > 0) {
        $is_detail = true;
        $detail_id = $id_user;
        
        $stmt = mysqli_prepare($mysqli, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id_user);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $detail_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$detail_data) {
            die("Data user tidak ditemukan.");
        }

           // Enforce role permission check for individual user details
           if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin' && $detail_data['id'] != $_SESSION['user_id']) {
               die("Akses ditolak: Anda tidak memiliki wewenang untuk mengekspor profil pengguna ini.");
           }
        
        // Ambil riwayat sewa user
        $stmt_rent = mysqli_prepare($mysqli, "
            SELECT p.*, k.nama_kendaraan, k.harga_per_hari, m.nama_merk
            FROM penyewaan p
            LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
            LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk
            WHERE p.id_user = ?
            ORDER BY p.tanggal_sewa DESC
        ");
        mysqli_stmt_bind_param($stmt_rent, 'i', $id_user);
        mysqli_stmt_execute($stmt_rent);
        $res_rent = mysqli_stmt_get_result($stmt_rent);
        $user_rentals = mysqli_fetch_all($res_rent, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_rent);
    }
}

// Blokir format non-PDF untuk data individual
if ($is_detail && $format !== 'pdf') {
    die("Format ekspor data individual hanya mendukung PDF.");
}

// Mengambil data list (jika bukan cetakan detail)
if (!$is_detail) {
    if ($target === 'kendaraan') {
        $search = trim($_GET['search'] ?? '');
        $f_jenis = trim($_GET['jenis'] ?? '');
        $f_status = trim($_GET['status'] ?? '');
        $f_merk = trim($_GET['merk'] ?? '');
        $title_text = "Laporan Data Kendaraan — FTrans";
        $headers = ['No', 'Kode Unik', 'Merk Kendaraan', 'Model / Nama Kendaraan', 'Jenis Kendaraan', 'Harga per Hari'];
        
        $where_clauses = [];
        $params = [];
        $types = "";

        if (!empty($search)) {
            $where_clauses[] = "(k.kode_unik_kendaraan LIKE ? OR k.nama_kendaraan LIKE ? OR k.jenis_kendaraan LIKE ?)";
            $search_param = "%" . $search . "%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "sss";
        }

        if (!empty($f_jenis)) {
            $where_clauses[] = "k.jenis_kendaraan = ?";
            $params[] = $f_jenis;
            $types .= "s";
        }

        if (!empty($f_status)) {
            $where_clauses[] = "k.status_kendaraan = ?";
            $params[] = $f_status;
            $types .= "s";
        }

        if (!empty($f_merk)) {
            $where_clauses[] = "k.id_merk = ?";
            $params[] = intval($f_merk);
            $types .= "i";
        }

        $where_sql = "";
        if (count($where_clauses) > 0) {
            $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        }

        $query = "
            SELECT k.*, m.nama_merk 
            FROM kendaraan k 
            LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
            $where_sql
            ORDER BY k.kode_unik_kendaraan ASC
        ";

        if (count($params) > 0) {
            $stmt = mysqli_prepare($mysqli, $query);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        } else {
            $result = mysqli_query($mysqli, $query);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
    } elseif ($target === 'sewa') {
        $title_text = "Laporan Transaksi Penyewaan Kendaraan — FTrans";
        $headers = ['No', 'Nama Penyewa', 'Nama Kendaraan', 'Kode Kendaraan', 'Tanggal Sewa', 'Tanggal Kembali', 'Total Biaya', 'Status'];
        
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $query = "
                SELECT p.*, u.nama AS nama_user, k.nama_kendaraan 
                FROM penyewaan p 
                LEFT JOIN users u ON p.id_user = u.id 
                LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
                ORDER BY p.id_sewa DESC
            ";
            $result = mysqli_query($mysqli, $query);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            $query = "
                SELECT p.*, u.nama AS nama_user, k.nama_kendaraan 
                FROM penyewaan p 
                LEFT JOIN users u ON p.id_user = u.id 
                LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
                WHERE p.id_user = ?
                ORDER BY p.id_sewa DESC
            ";
            $stmt = mysqli_prepare($mysqli, $query);
            mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        }
    } elseif ($target === 'users') {
        $search = trim($_GET['search'] ?? '');
        $title_text = "Laporan Data User Aplikasi — FTrans";
        $headers = ['No', 'ID User', 'Nama User', 'Email', 'Role', 'Jumlah Transaksi Sewa'];
        
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            if (!empty($search)) {
                $search_param = "%" . $search . "%";
                $query = "
                    SELECT u.id, u.nama, u.email, u.role, COUNT(p.id_sewa) AS jumlah_sewa
                    FROM users u
                    LEFT JOIN penyewaan p ON u.id = p.id_user
                    WHERE u.nama LIKE ? OR u.email LIKE ?
                    GROUP BY u.id
                    ORDER BY u.nama ASC
                ";
                $stmt = mysqli_prepare($mysqli, $query);
                mysqli_stmt_bind_param($stmt, 'ss', $search_param, $search_param);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
                mysqli_stmt_close($stmt);
            } else {
                $query = "
                    SELECT u.id, u.nama, u.email, u.role, COUNT(p.id_sewa) AS jumlah_sewa
                    FROM users u
                    LEFT JOIN penyewaan p ON u.id = p.id_user
                    GROUP BY u.id
                    ORDER BY u.nama ASC
                ";
                $result = mysqli_query($mysqli, $query);
                $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        } else {
            // Non-admin can only see themselves
            $query = "
                SELECT u.id, u.nama, u.email, u.role, COUNT(p.id_sewa) AS jumlah_sewa
                FROM users u
                LEFT JOIN penyewaan p ON u.id = p.id_user
                WHERE u.id = ?
                GROUP BY u.id
            ";
            $stmt = mysqli_prepare($mysqli, $query);
            mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        }
    } elseif ($target === 'analytics') {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            die("Akses ditolak: Hanya admin yang dapat mengekspor laporan analitik.");
        }
        $title_text = "Laporan Analitik Keuangan & Operasional — FTrans";
        
        // 1. Financial stats
        $res = mysqli_query($mysqli, "SELECT SUM(total_biaya) AS total FROM penyewaan WHERE status IN ('sedang_disewa', 'selesai')");
        $row = mysqli_fetch_assoc($res);
        $total_income = $row['total'] ?? 0;

        $res_m = mysqli_query($mysqli, "SELECT SUM(biaya) AS total FROM maintenance");
        $row_m = mysqli_fetch_assoc($res_m);
        $total_maintenance_cost = $row_m['total'] ?? 0;
        $net_profit = $total_income - $total_maintenance_cost;

        // 2. Active bookings
        $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM penyewaan WHERE status = 'sedang_disewa'");
        $row = mysqli_fetch_assoc($res);
        $active_bookings = $row['total'] ?? 0;

        // 3. Vehicles summary
        $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM kendaraan");
        $row = mysqli_fetch_assoc($res);
        $total_vehicles = $row['total'] ?? 0;

        // 4. Users summary
        $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM users WHERE role = 'user'");
        $row = mysqli_fetch_assoc($res);
        $total_users = $row['total'] ?? 0;

        // 5. Income vs Maintenance (Last 6 Months)
        $months_list = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-$i months"));
            $months_list[$key] = [
                'label' => date('M Y', strtotime("-$i months")),
                'income' => 0,
                'maintenance' => 0
            ];
        }
        $res_inc = mysqli_query($mysqli, "
            SELECT DATE_FORMAT(tanggal_sewa, '%Y-%m') AS raw_month, SUM(total_biaya) AS total
            FROM penyewaan 
            WHERE status IN ('sedang_disewa', 'selesai')
            GROUP BY raw_month
        ");
        while ($row = mysqli_fetch_assoc($res_inc)) {
            if (isset($months_list[$row['raw_month']])) {
                $months_list[$row['raw_month']]['income'] = intval($row['total']);
            }
        }
        $res_maint = mysqli_query($mysqli, "
            SELECT DATE_FORMAT(tanggal_selesai, '%Y-%m') AS raw_month, SUM(biaya) AS total
            FROM maintenance 
            WHERE tanggal_selesai IS NOT NULL
            GROUP BY raw_month
        ");
        while ($row = mysqli_fetch_assoc($res_maint)) {
            if (isset($months_list[$row['raw_month']])) {
                $months_list[$row['raw_month']]['maintenance'] = intval($row['total']);
            }
        }

        // 6. Top vehicle brands
        $top_brands = [];
        $res = mysqli_query($mysqli, "
            SELECT m.nama_merk, COUNT(p.id_sewa) AS count 
            FROM penyewaan p 
            JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
            JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
            GROUP BY m.id_merk 
            ORDER BY count DESC 
            LIMIT 5
        ");
        while ($row = mysqli_fetch_assoc($res)) {
            $top_brands[] = $row;
        }
        
        // 7. Full data lists for tables in report:
        $maint_list_res = mysqli_query($mysqli, "
            SELECT m.*, k.nama_kendaraan 
            FROM maintenance m 
            JOIN kendaraan k ON m.kode_unik_kendaraan = k.kode_unik_kendaraan 
            ORDER BY m.tanggal_mulai DESC
        ");
        $maintenance_data = mysqli_fetch_all($maint_list_res, MYSQLI_ASSOC);

        $rentals_list_res = mysqli_query($mysqli, "
            SELECT p.*, u.nama AS nama_user, k.nama_kendaraan 
            FROM penyewaan p 
            LEFT JOIN users u ON p.id_user = u.id 
            LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
            ORDER BY p.tanggal_sewa DESC
        ");
        $rentals_data = mysqli_fetch_all($rentals_list_res, MYSQLI_ASSOC);

        $users_list_res = mysqli_query($mysqli, "
            SELECT id, nama, email, no_hp, role 
            FROM users 
            ORDER BY nama ASC
        ");
        $users_data = mysqli_fetch_all($users_list_res, MYSQLI_ASSOC);
    }
}

// ---------------------------------------------------------
// EXCEL FORMAT (PhpSpreadsheet)
// ---------------------------------------------------------
if ($format === 'excel') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if ($target === 'analytics') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
        
        // ----------------------------------------------------
        // SHEET 1: RINGKASAN & KEUANGAN
        // ----------------------------------------------------
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan Keuangan');
        
        $sheet->setCellValue('A1', 'LAPORAN RINGKASAN KEUANGAN & OPERASIONAL');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
        
        $sheet->setCellValue('A2', 'FTrans Car Rental — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com');
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
        
        $sheet->setCellValue('A3', 'Metrik Kunci');
        $sheet->getStyle('A3')->getFont()->setSize(12)->setBold(true);
        
        $sheet->setCellValue('A4', 'Total Pendapatan:');
        $sheet->setCellValue('B4', $total_income);
        $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle('B4')->getFont()->setBold(true);
        
        $sheet->setCellValue('A5', 'Total Biaya Pemeliharaan:');
        $sheet->setCellValue('B5', $total_maintenance_cost);
        $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle('B5')->getFont()->setBold(true);
        
        $sheet->setCellValue('A6', 'Keuntungan Bersih:');
        $sheet->setCellValue('B6', $net_profit);
        $sheet->getStyle('B6')->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle('B6')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($net_profit >= 0 ? '16A34A' : 'DC2626'));
        
        $sheet->setCellValue('A8', 'Statistik Operasional');
        $sheet->getStyle('A8')->getFont()->setSize(12)->setBold(true);
        
        $sheet->setCellValue('A9', 'Penyewaan Aktif:');
        $sheet->setCellValue('B9', $active_bookings);
        
        $sheet->setCellValue('A10', 'Total Armada Kendaraan:');
        $sheet->setCellValue('B10', $total_vehicles);
        
        $sheet->setCellValue('A11', 'Total Pelanggan Terdaftar:');
        $sheet->setCellValue('B11', $total_users);
        
        // Income vs Maintenance table
        $sheet->setCellValue('A13', 'Tren Keuangan 6 Bulan Terakhir');
        $sheet->getStyle('A13')->getFont()->setSize(12)->setBold(true);
        
        $sheet->setCellValue('A14', 'Bulan');
        $sheet->setCellValue('B14', 'Pendapatan');
        $sheet->setCellValue('C14', 'Biaya Servis');
        $sheet->setCellValue('D14', 'Keuntungan Bersih');
        $sheet->getStyle('A14:D14')->getFont()->setBold(true);
        $sheet->getStyle('A14:D14')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        
        $rowIdx = 15;
        foreach ($months_list as $m) {
            $sheet->setCellValue('A' . $rowIdx, $m['label']);
            $sheet->setCellValue('B' . $rowIdx, $m['income']);
            $sheet->setCellValue('C' . $rowIdx, $m['maintenance']);
            $sheet->setCellValue('D' . $rowIdx, $m['income'] - $m['maintenance']);
            
            $sheet->getStyle('B' . $rowIdx)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle('C' . $rowIdx)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle('D' . $rowIdx)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $rowIdx++;
        }
        
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // ----------------------------------------------------
        // SHEET 2: LAPORAN SERVIS & PERAWATAN
        // ----------------------------------------------------
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Laporan Perawatan');
        
        $sheet2->setCellValue('A1', 'DAFTAR RIWAYAT PERAWATAN KENDARAAN');
        $sheet2->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        
        $sheet2->setCellValue('A2', 'FTrans Car Rental — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com');
        $sheet2->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
        
        $sheet2->setCellValue('A3', 'No');
        $sheet2->setCellValue('B3', 'ID');
        $sheet2->setCellValue('C3', 'Kode Kendaraan');
        $sheet2->setCellValue('D3', 'Nama Kendaraan');
        $sheet2->setCellValue('E3', 'Deskripsi Perawatan');
        $sheet2->setCellValue('F3', 'Tanggal Mulai');
        $sheet2->setCellValue('G3', 'Tanggal Selesai');
        $sheet2->setCellValue('H3', 'Biaya');
        $sheet2->getStyle('A3:H3')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet2->getStyle('A3:H3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        
        $rowIdx = 4;
        foreach ($maintenance_data as $idx => $m_row) {
            $sheet2->setCellValue('A' . $rowIdx, $idx + 1);
            $sheet2->setCellValue('B' . $rowIdx, '#MNT-' . str_pad($m_row['id'], 5, '0', STR_PAD_LEFT));
            $sheet2->setCellValue('C' . $rowIdx, $m_row['kode_unik_kendaraan']);
            $sheet2->setCellValue('D' . $rowIdx, $m_row['nama_kendaraan']);
            $sheet2->setCellValue('E' . $rowIdx, $m_row['deskripsi']);
            $sheet2->setCellValue('F' . $rowIdx, date('d M Y', strtotime($m_row['tanggal_mulai'])));
            $sheet2->setCellValue('G' . $rowIdx, $m_row['tanggal_selesai'] ? date('d M Y', strtotime($m_row['tanggal_selesai'])) : '-');
            $sheet2->setCellValue('H' . $rowIdx, $m_row['biaya']);
            
            $sheet2->getStyle('H' . $rowIdx)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $rowIdx++;
        }
        foreach (range('A', 'H') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
        
        // ----------------------------------------------------
        // SHEET 3: TRANSAKSI PENYEWAAN
        // ----------------------------------------------------
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Data Penyewaan');
        
        $sheet3->setCellValue('A1', 'DAFTAR TRANSAKSI PENYEWAAN KENDARAAN');
        $sheet3->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        
        $sheet3->setCellValue('A2', 'FTrans Car Rental — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com');
        $sheet3->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
        
        $sheet3->setCellValue('A3', 'No');
        $sheet3->setCellValue('B3', 'No Invoice');
        $sheet3->setCellValue('C3', 'Nama Penyewa');
        $sheet3->setCellValue('D3', 'Nama Kendaraan');
        $sheet3->setCellValue('E3', 'Kode Kendaraan');
        $sheet3->setCellValue('F3', 'Tanggal Sewa');
        $sheet3->setCellValue('G3', 'Tanggal Kembali');
        $sheet3->setCellValue('H3', 'Total Biaya');
        $sheet3->setCellValue('I3', 'Status');
        $sheet3->getStyle('A3:I3')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet3->getStyle('A3:I3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        
        $rowIdx = 4;
        foreach ($rentals_data as $idx => $r_row) {
            $sheet3->setCellValue('A' . $rowIdx, $idx + 1);
            $sheet3->setCellValue('B' . $rowIdx, '#INV-' . str_pad($r_row['id_sewa'], 5, '0', STR_PAD_LEFT));
            $sheet3->setCellValue('C' . $rowIdx, $r_row['nama_user'] ?? 'N/A');
            $sheet3->setCellValue('D' . $rowIdx, $r_row['nama_kendaraan'] ?? 'N/A');
            $sheet3->setCellValue('E' . $rowIdx, $r_row['kode_unik_kendaraan']);
            $sheet3->setCellValue('F' . $rowIdx, date('d M Y H:i', strtotime($r_row['tanggal_sewa'])));
            $sheet3->setCellValue('G' . $rowIdx, date('d M Y H:i', strtotime($r_row['tanggal_kembali'])));
            $sheet3->setCellValue('H' . $rowIdx, $r_row['total_biaya']);
            
            $status = $r_row['status'];
            $statusLabel = 'Booking';
            if ($status === 'sedang_disewa') $statusLabel = 'Sedang Disewa';
            if ($status === 'selesai') $statusLabel = 'Selesai';
            if ($status === 'dibatalkan') $statusLabel = 'Dibatalkan';
            $sheet3->setCellValue('I' . $rowIdx, $statusLabel);
            
            $sheet3->getStyle('H' . $rowIdx)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $rowIdx++;
        }
        foreach (range('A', 'I') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }

        // ----------------------------------------------------
        // SHEET 4: DATA PENGGUNA
        // ----------------------------------------------------
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Data Pengguna');
        
        $sheet4->setCellValue('A1', 'DAFTAR PENGGUNA SISTEM (USERS)');
        $sheet4->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        
        $sheet4->setCellValue('A2', 'FTrans Car Rental — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com');
        $sheet4->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
        
        $sheet4->setCellValue('A3', 'No');
        $sheet4->setCellValue('B3', 'ID User');
        $sheet4->setCellValue('C3', 'Nama Lengkap');
        $sheet4->setCellValue('D3', 'Alamat Email');
        $sheet4->setCellValue('E3', 'Nomor HP');
        $sheet4->setCellValue('F3', 'Role');
        $sheet4->getStyle('A3:F3')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet4->getStyle('A3:F3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        
        $rowIdx = 4;
        foreach ($users_data as $idx => $u_row) {
            $sheet4->setCellValue('A' . $rowIdx, $idx + 1);
            $sheet4->setCellValue('B' . $rowIdx, $u_row['id']);
            $sheet4->setCellValue('C' . $rowIdx, $u_row['nama']);
            $sheet4->setCellValue('D' . $rowIdx, $u_row['email']);
            $sheet4->setCellValue('E' . $rowIdx, $u_row['no_hp'] ?? '-');
            $sheet4->setCellValue('F' . $rowIdx, ucfirst($u_row['role']));
            $rowIdx++;
        }
        foreach (range('A', 'F') as $col) {
            $sheet4->getColumnDimension($col)->setAutoSize(true);
        }
        
        $spreadsheet->setActiveSheetIndex(0);
        
        $filename = "Laporan_Analitik_" . date('Ymd_His') . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr(ucfirst($target), 0, 30));
    
    $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
    $maxColIndex = count($headers);
    $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxColIndex);
    
    // Header Laporan
    $sheet->setCellValue('A1', strtoupper($title_text));
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setSize(15)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
    $sheet->getStyle("A1:{$lastColLetter}1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'FTrans Car Rental Management System — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com');
    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->getStyle("A2:{$lastColLetter}2")->getFont()->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
    $sheet->getStyle("A2:{$lastColLetter}2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Tanggal Cetak: ' . date('d F Y, H:i:s'));
    $sheet->mergeCells("A3:{$lastColLetter}3");
    $sheet->getStyle("A3:{$lastColLetter}3")->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet->getStyle("A3:{$lastColLetter}3")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Style Table Header
    $headerRange = "A5:{$lastColLetter}5";
    $sheet->getRowDimension(5)->setRowHeight(28);
    
    foreach ($headers as $colIdx => $headerTitle) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
        $sheet->setCellValue($colLetter . '5', $headerTitle);
    }
    
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 10
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1E293B']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ]
    ]);
    
    // Isi data
    $startRow = 6;
    $currentRow = $startRow;
    
    foreach ($data as $index => $row) {
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $sheet->setCellValue('A' . $currentRow, $index + 1);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        if ($target === 'kendaraan') {
            $sheet->setCellValue('B' . $currentRow, $row['kode_unik_kendaraan']);
            $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $currentRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            $sheet->setCellValue('C' . $currentRow, ucwords($row['nama_merk'] ?? ''));
            $sheet->setCellValue('D' . $currentRow, $row['nama_kendaraan']);
            
            $jenis = strtolower(trim($row['jenis_kendaraan']));
            $jenisLabel = ($jenis === 'roda 2') ? 'Roda 2' : 'Roda 4';
            $sheet->setCellValue('E' . $currentRow, $jenisLabel);
            $sheet->getStyle('E' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('F' . $currentRow, $row['harga_per_hari']);
            $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('F' . $currentRow)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            
        } elseif ($target === 'sewa') {
            $sheet->setCellValue('B' . $currentRow, $row['nama_user'] ?? 'N/A');
            $sheet->setCellValue('C' . $currentRow, $row['nama_kendaraan'] ?? 'N/A');
            
            $sheet->setCellValue('D' . $currentRow, $row['kode_unik_kendaraan']);
            $sheet->getStyle('D' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $currentRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            $tglSewa = date('d M Y H:i', strtotime($row['tanggal_sewa']));
            $tglKembali = date('d M Y H:i', strtotime($row['tanggal_kembali']));
            
            $sheet->setCellValue('E' . $currentRow, $tglSewa);
            $sheet->getStyle('E' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('F' . $currentRow, $tglKembali);
            $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('G' . $currentRow, $row['total_biaya']);
            $sheet->getStyle('G' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('G' . $currentRow)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            
            $status = $row['status'] ?? 'booking';
            $statusLabel = 'Booking';
            if ($status === 'sedang_disewa') $statusLabel = 'Sedang Disewa';
            if ($status === 'selesai') $statusLabel = 'Selesai';
            if ($status === 'dibatalkan') $statusLabel = 'Dibatalkan';
            
            $sheet->setCellValue('H' . $currentRow, $statusLabel);
            $sheet->getStyle('H' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
        } elseif ($target === 'users') {
            $sheet->setCellValue('B' . $currentRow, $row['id']);
            $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('C' . $currentRow, $row['nama']);
            $sheet->setCellValue('D' . $currentRow, $row['email']);
            $sheet->setCellValue('E' . $currentRow, ucfirst($row['role'] ?? 'user'));
            $sheet->getStyle('E' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue('F' . $currentRow, $row['jumlah_sewa']);
            $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        $currentRow++;
    }
    
    // Border tabel
    $lastRow = $currentRow - 1;
    $dataRange = "A5:{$lastColLetter}{$lastRow}";
    $sheet->getStyle($dataRange)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ]
    ]);
    
    // Auto-fit kolom
    for ($col = 1; $col <= $maxColIndex; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }
    
    $filename = 'ftrans_laporan_' . $target . '_' . date('Ymd_His') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ---------------------------------------------------------
// WORD FORMAT (PhpWord)
// ---------------------------------------------------------
if ($format === 'word') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if ($target === 'analytics') {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection(['paperSize' => 'A4']);
        
        $section->addText('LAPORAN RINGKASAN KEUANGAN & OPERASIONAL FTRANS', ['bold' => true, 'size' => 16]);
        $section->addText('FTrans Car Rental — Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com', ['italic' => true, 'size' => 9, 'color' => '475569']);
        $section->addText('Tanggal Cetak: ' . date('d F Y, H:i:s'), ['italic' => true]);
        $section->addTextBreak(1);
        
        $section->addText('1. Metrik Kunci Keuangan & Operasional:', ['bold' => true, 'size' => 12]);
        $section->addText('Total Pendapatan: Rp ' . number_format($total_income, 0, ',', '.'));
        $section->addText('Total Biaya Servis: Rp ' . number_format($total_maintenance_cost, 0, ',', '.'));
        $section->addText('Keuntungan Bersih: Rp ' . number_format($net_profit, 0, ',', '.'), ['bold' => true]);
        $section->addText('Penyewaan Aktif: ' . $active_bookings . ' Transaksi');
        $section->addText('Total Armada: ' . $total_vehicles . ' Unit');
        $section->addText('Total Pelanggan Terdaftar: ' . $total_users . ' User');
        $section->addTextBreak(1);
        
        $section->addText('2. Rincian Tren Keuangan (6 Bulan Terakhir):', ['bold' => true, 'size' => 12]);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $table->addRow();
        $table->addCell(2000)->addText('Bulan', ['bold' => true]);
        $table->addCell(2500)->addText('Pendapatan', ['bold' => true]);
        $table->addCell(2500)->addText('Biaya Servis', ['bold' => true]);
        $table->addCell(2500)->addText('Keuntungan', ['bold' => true]);
        
        foreach ($months_list as $m) {
            $table->addRow();
            $table->addCell(2000)->addText($m['label']);
            $table->addCell(2500)->addText('Rp ' . number_format($m['income'], 0, ',', '.'));
            $table->addCell(2500)->addText('Rp ' . number_format($m['maintenance'], 0, ',', '.'));
            $table->addCell(2500)->addText('Rp ' . number_format($m['income'] - $m['maintenance'], 0, ',', '.'));
        }
        $section->addTextBreak(1);

        $section->addText('3. Distribusi Terpopuler (Top 5 Merk):', ['bold' => true, 'size' => 12]);
        $table_tb = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $table_tb->addRow();
        $table_tb->addCell(4000)->addText('Nama Merk', ['bold' => true]);
        $table_tb->addCell(4000)->addText('Jumlah Transaksi', ['bold' => true]);
        foreach ($top_brands as $tb) {
            $table_tb->addRow();
            $table_tb->addCell(4000)->addText(ucwords($tb['nama_merk']));
            $table_tb->addCell(4000)->addText($tb['count'] . ' Kali Sewa');
        }
        
        $filename = "Laporan_Analitik_" . date('Ymd_His') . ".docx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    $properties = $phpWord->getDocInfo();
    $properties->setCreator('FTrans Car Rental');
    $properties->setCompany('FTrans');
    $properties->setTitle($title_text);
    
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginLeft' => 1000,
        'marginRight' => 1000,
        'marginTop' => 1000,
        'marginBottom' => 1000,
    ]);
    
    $phpWord->addTitleStyle(1, [
        'name' => 'Segoe UI',
        'size' => 16,
        'bold' => true,
        'color' => '1E293B',
    ], [
        'alignment' => 'center',
        'spaceAfter' => 120
    ]);
    $section->addTitle(strtoupper($title_text), 1);
    
    $section->addText('FTrans Car Rental Management System', [
        'name' => 'Segoe UI',
        'size' => 10,
        'italic' => true,
        'color' => '475569',
    ], [
        'alignment' => 'center',
        'spaceAfter' => 20
    ]);
    $section->addText('Jl. Premium Luxury No. 7, Surakarta | Telp: +62 821 8888 9999 | Email: support@ftrans.com', [
        'name' => 'Segoe UI',
        'size' => 9,
        'italic' => true,
        'color' => '64748B',
    ], [
        'alignment' => 'center',
        'spaceAfter' => 60
    ]);
    
    $section->addText('Tanggal Cetak: ' . date('d F Y, H:i:s'), [
        'name' => 'Segoe UI',
        'size' => 8,
        'color' => '64748B'
    ], [
        'alignment' => 'center',
        'spaceAfter' => 300
    ]);
    
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => 'CBD5E1',
        'cellMargin' => 80,
        'alignment' => 'center'
    ];
    $phpWord->addTableStyle('Report Table', $tableStyle);
    $table = $section->addTable('Report Table');
    
    $colWidths = [];
    if ($target === 'kendaraan') {
        $colWidths = [800, 1500, 1800, 2400, 1500, 1500];
    } elseif ($target === 'sewa') {
        $colWidths = [600, 1600, 1800, 1000, 1400, 1400, 1200, 800];
    } elseif ($target === 'users') {
        $colWidths = [600, 1000, 2200, 2600, 1400, 1700];
    }
    
    $table->addRow(350);
    foreach ($headers as $idx => $headerTitle) {
        $width = $colWidths[$idx] ?? 1000;
        $cell = $table->addCell($width, ['bgColor' => '1E293B']);
        $cell->addText($headerTitle, [
            'name' => 'Segoe UI',
            'size' => 9,
            'bold' => true,
            'color' => 'FFFFFF'
        ], [
            'alignment' => 'center',
            'spaceAfter' => 0
        ]);
    }
    
    foreach ($data as $index => $row) {
        $bg = ($index % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
        $cellStyle = ['bgColor' => $bg];
        
        $table->addRow(300);
        $cell = $table->addCell($colWidths[0], $cellStyle);
        $cell->addText($index + 1, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
        
        if ($target === 'kendaraan') {
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText($row['kode_unik_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText(ucwords($row['nama_merk'] ?? ''), ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['nama_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $jenisLabel = (strtolower(trim($row['jenis_kendaraan'])) === 'roda 2') ? 'Roda 2' : 'Roda 4';
            $cell = $table->addCell($colWidths[4], $cellStyle);
            $cell->addText($jenisLabel, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $hargaText = 'Rp ' . number_format($row['harga_per_hari'], 0, ',', '.');
            $cell = $table->addCell($colWidths[5], $cellStyle);
            $cell->addText($hargaText, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'right', 'spaceAfter' => 0]);
            
        } elseif ($target === 'sewa') {
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText($row['nama_user'] ?? 'N/A', ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText($row['nama_kendaraan'] ?? 'N/A', ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['kode_unik_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $tglSewa = date('d M Y H:i', strtotime($row['tanggal_sewa']));
            $cell = $table->addCell($colWidths[4], $cellStyle);
            $cell->addText($tglSewa, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $tglKembali = date('d M Y H:i', strtotime($row['tanggal_kembali']));
            $cell = $table->addCell($colWidths[5], $cellStyle);
            $cell->addText($tglKembali, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $totalBiayaText = $row['total_biaya'] !== null ? 'Rp ' . number_format($row['total_biaya'], 0, ',', '.') : '-';
            $cell = $table->addCell($colWidths[6], $cellStyle);
            $cell->addText($totalBiayaText, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'right', 'spaceAfter' => 0]);
            
            $status = $row['status'] ?? 'booking';
            $statusLabel = 'Booking';
            if ($status === 'sedang_disewa') $statusLabel = 'Sewa';
            if ($status === 'selesai') $statusLabel = 'Selesai';
            if ($status === 'dibatalkan') $statusLabel = 'Batal';
            
            $cell = $table->addCell($colWidths[7], $cellStyle);
            $cell->addText($statusLabel, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
        } elseif ($target === 'users') {
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText('#' . $row['id'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText($row['nama'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['email'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[4], $cellStyle);
            $cell->addText(ucfirst($row['role'] ?? 'user'), ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            $cell = $table->addCell($colWidths[5], $cellStyle);
            $cell->addText($row['jumlah_sewa'] . ' Sewa', ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
        }
    }
    
    $filename = 'ftrans_laporan_' . $target . '_' . date('Ymd_His') . '.docx';
    
    header("Content-Description: File Transfer");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $xmlWriter->save("php://output");
    exit;
}

// ---------------------------------------------------------
// PDF FORMAT (mPDF)
// ---------------------------------------------------------
if ($format === 'pdf') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Inisialisasi mPDF dengan format A4 Potret
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    $mpdf->SetTitle($is_detail ? 'Dokumen Detail' : $title_text);
    $mpdf->SetAuthor('FTrans Car Rental');
    
    // Set Footer Laporan untuk format list
    if (!$is_detail) {
        $mpdf->SetFooter('FTrans Management || Halaman {PAGENO} dari {nbpg}');
    }
    
    // Struktur Stylesheet HTML
    $html = '
    <style>
        body { font-family: "Segoe UI", Helvetica, Arial, sans-serif; color: #1E293B; font-size: 10pt; line-height: 1.4; }
        .header-table { width: 100%; border-bottom: 2px solid #0F172A; margin-bottom: 20px; padding-bottom: 8px; }
        .logo-text { font-size: 22pt; font-weight: bold; color: #3B82F6; }
        .comp-title { font-size: 11pt; font-weight: bold; color: #0F172A; text-align: right; }
        .comp-sub { font-size: 8pt; color: #64748B; text-align: right; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 3px; color: #0F172A; }
        .subtitle { text-align: center; font-size: 9pt; color: #64748B; margin-bottom: 25px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background-color: #1E293B; color: #FFFFFF; font-weight: bold; font-size: 9pt; padding: 8px; border: 1px solid #CBD5E1; }
        .data-table td { padding: 8px; border: 1px solid #CBD5E1; font-size: 9pt; vertical-align: middle; }
        .data-table tr:nth-child(even) { background-color: #F8FAFC; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        /* Styles untuk Detail Kendaraan (Spec Sheet) */
        .spec-container { width: 100%; border: none; margin-top: 15px; }
        .spec-img-box { text-align: center; vertical-align: top; padding-right: 20px; }
        .spec-img { width: 250px; height: 180px; border-radius: 8px; border: 1px solid #CBD5E1; }
        .spec-no-img { width: 250px; height: 180px; line-height: 180px; background-color: #F1F5F9; border-radius: 8px; border: 1px dashed #CBD5E1; color: #64748B; font-weight: bold; font-size: 12pt; text-align: center; }
        .spec-table { width: 100%; border-collapse: collapse; }
        .spec-table td { padding: 10px; border: 1px solid #E2E8F0; }
        .spec-label { font-weight: bold; background-color: #F8FAFC; width: 35%; color: #475569; }
        
        /* Styles untuk Invoice Penyewaan */
        .invoice-info-table { width: 100%; border: none; margin-bottom: 25px; }
        .invoice-to { font-size: 10pt; vertical-align: top; }
        .invoice-details { font-size: 10pt; vertical-align: top; text-align: right; }
        .grand-total-row { background-color: #F1F5F9; font-weight: bold; font-size: 11pt; }
        .grand-total-val { font-size: 12pt; color: #16A34A; }
        
        /* Styles untuk Detail User */
        .profile-card { background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .profile-title { font-size: 11pt; font-weight: bold; color: #0F172A; margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #E2E8F0; padding-bottom: 5px; }
        .profile-grid { width: 100%; }
        .profile-grid td { padding: 4px 0; border: none; }
        
        .footer-note { margin-top: 50px; text-align: center; font-size: 8.5pt; color: #64748B; }
    </style>
    ';
    
    // Kop Laporan
    $html .= '
    <table class="header-table">
        <tr>
            <td class="logo-text">FTrans</td>
            <td class="comp-title">FTRANS CAR RENTAL MANAGEMENT
                <div class="comp-sub">Kenyamanan Perjalanan Anda, Prioritas Kami</div>
                <div style="font-size: 7.5pt; color: #64748B; text-align: right; font-weight: normal; margin-top: 3px;">
                    Jl. Premium Luxury No. 7, Surakarta &bull; Telp: +62 821 8888 9999 &bull; Email: support@ftrans.com
                </div>
            </td>
        </tr>
    </table>
    ';
    
    if ($is_detail) {
        if ($target === 'kendaraan') {
            $html .= '<div class="title">SPESIFIKASI DETAIL KENDARAAN</div>';
            $html .= '<div class="subtitle">Kode Unik: #' . htmlspecialchars($detail_data['kode_unik_kendaraan']) . '</div>';
            
            $html .= '<table class="spec-container">';
            $html .= '<tr>';
            
            // Kolom Gambar
            $html .= '<td class="spec-img-box" style="width: 40%;">';
            if (!empty($detail_data['gambar']) && file_exists('uploads/' . $detail_data['gambar'])) {
                $html .= '<img class="spec-img" src="uploads/' . htmlspecialchars($detail_data['gambar']) . '">';
            } else {
                $html .= '<div class="spec-no-img">No Image</div>';
            }
            $html .= '</td>';
            
            // Kolom Spesifikasi
            $html .= '<td style="width: 60%; vertical-align: top;">';
            $html .= '<table class="spec-table">';
            $html .= '<tr><td class="spec-label">Kode Kendaraan</td><td style="font-weight: bold;">' . htmlspecialchars($detail_data['kode_unik_kendaraan']) . '</td></tr>';
            $html .= '<tr><td class="spec-label">Merk Kendaraan</td><td>' . htmlspecialchars(ucwords($detail_data['nama_merk'] ?? '')) . '</td></tr>';
            $html .= '<tr><td class="spec-label">Model / Nama</td><td style="font-weight: bold;">' . htmlspecialchars($detail_data['nama_kendaraan']) . '</td></tr>';
            $html .= '<tr><td class="spec-label">Jenis Kendaraan</td><td>' . (strtolower($detail_data['jenis_kendaraan']) === 'roda 2' ? 'Roda 2 (Motor)' : 'Roda 4 (Mobil)') . '</td></tr>';
            $html .= '<tr><td class="spec-label">Harga Sewa / Hari</td><td style="font-weight: bold; color: #16A34A; font-size: 11pt;">Rp ' . number_format($detail_data['harga_per_hari'], 0, ',', '.') . '</td></tr>';
            $html .= '</table>';
            $html .= '</td>';
            
            $html .= '</tr>';
            $html .= '</table>';
            
            $html .= '<div class="footer-note" style="margin-top: 80px;">';
            $html .= '<p>Lembar Spesifikasi Resmi Kendaraan — FTrans Car Rental</p>';
            $html .= '</div>';
            
        } elseif ($target === 'sewa') {
            $t1 = strtotime($detail_data['tanggal_sewa']);
            $t2 = strtotime($detail_data['tanggal_kembali']);
            $diff_days = ceil(($t2 - $t1) / 86400);
            if ($diff_days <= 0) $diff_days = 1;
            
            $html .= '<div class="title">FAKTUR TRANSAKSI PENYEWAAN</div>';
            $html .= '<div class="subtitle">Invoice No: #INV-' . str_pad($detail_data['id_sewa'], 5, '0', STR_PAD_LEFT) . '</div>';
            
            $html .= '<table class="invoice-info-table">';
            $html .= '<tr>';
            
            // Info Penyewa
            $html .= '<td class="invoice-to" style="width: 50%;">';
            $html .= '<div style="font-weight: bold; color: #475569; margin-bottom: 5px;">PENGGUNA JASA:</div>';
            $html .= '<div style="font-size: 11pt; font-weight: bold; color: #0F172A;">' . htmlspecialchars($detail_data['nama_user'] ?? 'N/A') . '</div>';
            $html .= '<div>Email: ' . htmlspecialchars($detail_data['email_user'] ?? 'N/A') . '</div>';
            $html .= '</td>';
            
            // Info Sewa
            $html .= '<td class="invoice-details" style="width: 50%;">';
            $html .= '<div style="font-weight: bold; color: #475569; margin-bottom: 5px;">DETAIL PENYEWAAN:</div>';
            $html .= '<div><strong>Tanggal Sewa:</strong> ' . date('d M Y H:i', strtotime($detail_data['tanggal_sewa'])) . '</div>';
            $html .= '<div><strong>Tanggal Kembali:</strong> ' . date('d M Y H:i', strtotime($detail_data['tanggal_kembali'])) . '</div>';
            
            $status = $detail_data['status'] ?? 'booking';
            $statusLabel = 'Booking';
            if ($status === 'sedang_disewa') $statusLabel = 'Sedang Disewa';
            if ($status === 'selesai') $statusLabel = 'Selesai';
            if ($status === 'dibatalkan') $statusLabel = 'Dibatalkan';
            
            $html .= '<div><strong>Status:</strong> ' . $statusLabel . '</div>';
            if (!empty($detail_data['metode_pembayaran'])) {
                $html .= '<div><strong>Metode Pembayaran:</strong> ' . htmlspecialchars($detail_data['metode_pembayaran']) . '</div>';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
            $html .= '</table>';
            
            // Tabel Rincian Pembayaran
            $html .= '<table class="data-table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="width: 45%; text-align: left;">Deskripsi Sewa Kendaraan</th>';
            $html .= '<th style="width: 15%;">Tarif / Hari</th>';
            $html .= '<th style="width: 15%;">Durasi</th>';
            $html .= '<th style="width: 25%; text-align: right;">Total Biaya</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<div style="font-weight: bold;">' . htmlspecialchars($detail_data['nama_kendaraan'] ?? 'Kendaraan N/A') . '</div>';
            $html .= '<div style="font-size: 8pt; color: #64748B;">Kode Unik: ' . htmlspecialchars($detail_data['kode_unik_kendaraan']) . ' (' . ucwords($detail_data['nama_merk'] ?? '') . ')</div>';
            $html .= '</td>';
            $harga_per_hari = intval($detail_data['harga_per_hari'] ?? 0);
            $original_total = $diff_days * $harga_per_hari;
            $sewa_cost = intval($detail_data['total_biaya'] ?? 0);
            $diskon = $original_total - $sewa_cost;
            if ($diskon < 0) $diskon = 0;
            $diskon_pct = ($original_total > 0) ? round(($diskon / $original_total) * 100) : 0;
            
            $html .= '<td class="text-center">Rp ' . number_format($harga_per_hari, 0, ',', '.') . '</td>';
            $html .= '<td class="text-center">' . $diff_days . ' Hari</td>';
            $html .= '<td class="text-right" style="font-weight: bold;">Rp ' . number_format($original_total, 0, ',', '.') . '</td>';
            $html .= '</tr>';
            
            $denda = intval($detail_data['denda'] ?? 0);
            $total_bayar = $sewa_cost + $denda;

            if ($diskon > 0) {
                $html .= '<tr>';
                $html .= '<td colspan="3" class="text-right" style="padding: 10px;">Subtotal Sewa:</td>';
                $html .= '<td class="text-right" style="padding: 10px; font-weight: bold;">Rp ' . number_format($original_total, 0, ',', '.') . '</td>';
                $html .= '</tr>';
                $html .= '<tr>';
                $html .= '<td colspan="3" class="text-right" style="padding: 10px; color: #16A34A;">Diskon Member (' . $diskon_pct . '%):</td>';
                $html .= '<td class="text-right" style="padding: 10px; font-weight: bold; color: #16A34A;">- Rp ' . number_format($diskon, 0, ',', '.') . '</td>';
                $html .= '</tr>';
            }

            if ($denda > 0) {
                if ($diskon > 0) {
                    $html .= '<tr>';
                    $html .= '<td colspan="3" class="text-right" style="padding: 10px;">Total Sewa:</td>';
                    $html .= '<td class="text-right" style="padding: 10px; font-weight: bold;">Rp ' . number_format($sewa_cost, 0, ',', '.') . '</td>';
                    $html .= '</tr>';
                }
                $html .= '<tr>';
                $html .= '<td colspan="3" class="text-right" style="padding: 10px; color: #DC2626;">Denda Keterlambatan:</td>';
                $html .= '<td class="text-right" style="padding: 10px; font-weight: bold; color: #DC2626;">Rp ' . number_format($denda, 0, ',', '.') . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="grand-total-row">';
            $html .= '<td colspan="3" class="text-right" style="padding: 10px;">Total Pembayaran:</td>';
            $html .= '<td class="text-right grand-total-val" style="padding: 10px;">Rp ' . number_format($total_bayar, 0, ',', '.') . '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';

            // Validasi QR Code & Barcode Receipt secara dinamis (mendukung Localhost & Hosting InfinityFree/Vercel/dll)
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $script_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
            if ($script_dir === '/' || $script_dir === '.') $script_dir = '';
            $receipt_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $script_dir . "/bayar.php?id=" . $detail_data['id_sewa'];
            
            $inv_code_str = 'INV-' . str_pad($detail_data['id_sewa'], 5, '0', STR_PAD_LEFT);
            $qr_code_src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($receipt_url);
            $barcode_src = "https://bwipjs-api.metafloor.com/bwipjs?bcid=code128&text=" . urlencode($inv_code_str) . "&scale=2&rotate=N&includetext";

            $html .= '
            <table style="width: 100%; border: none; margin-top: 25px;">
                <tr>
                    <td style="width: 60%; vertical-align: top; border: none; padding: 0;">
                        <div style="font-weight: bold; color: #475569; margin-bottom: 5px; font-size: 8.5pt;">CATATAN & KETENTUAN:</div>
                        <div style="font-size: 8pt; color: #64748B; line-height: 1.4;">
                            * Struk ini adalah bukti pembayaran digital resmi dari <strong>FTrans Car Rental</strong>.<br>
                            * Silakan tunjukkan QR Code / Barcode ini kepada petugas kami saat melakukan pengambilan atau pengembalian armada.<br>
                            * Hubungi support@ftrans.com jika Anda memerlukan bantuan lebih lanjut.
                        </div>
                        <div style="margin-top: 12px;">
                            <div style="font-size: 7.5pt; color: #64748B; font-weight: bold; margin-bottom: 3px;">BARCODE FAKTUR:</div>
                            <img src="' . $barcode_src . '" style="height: 38px; max-width: 210px;" alt="Barcode ' . $inv_code_str . '">
                        </div>
                    </td>
                    <td style="width: 40%; text-align: right; vertical-align: top; border: none; padding: 0;">
                        <div style="font-weight: bold; color: #475569; font-size: 8pt; margin-bottom: 5px;">VERIFIKASI STRUK DIGITAL:</div>
                        <div style="display: inline-block; text-align: center; border: 1px solid #CBD5E1; padding: 6px; border-radius: 6px; background-color: #FFFFFF;">
                            <img src="' . $qr_code_src . '" style="width: 100px; height: 100px; display: block; margin: 0 auto;" alt="QR Code Verification">
                            <div style="font-size: 6.5pt; color: #64748B; margin-top: 4px; font-weight: bold;">SCAN UNTUK CEK STATUS</div>
                        </div>
                    </td>
                </tr>
            </table>
            ';
            
            $html .= '<div class="footer-note">';
            $html .= '<p>Terima kasih telah mempercayakan perjalanan Anda bersama FTrans.</p>';
            $html .= '<p style="font-style: italic; font-weight: bold; margin-top: 5px;">Kuitansi Pembayaran Elektronik Resmi Sah.</p>';
            $html .= '</div>';
            
        } elseif ($target === 'users') {
            $html .= '<div class="title">RIWAYAT TRANSAKSI PENYEWAAN USER</div>';
            $html .= '<div class="subtitle">Data Profil & Rekap Transaksi Historis</div>';
            
            $html .= '<div class="profile-card">';
            $html .= '<div class="profile-title">INFORMASI USER:</div>';
            $html .= '<table class="profile-grid">';
            $html .= '<tr><td style="width: 25%; font-weight: bold;">ID User:</td><td>#' . $detail_data['id'] . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Nama Lengkap:</td><td style="font-weight: bold; color: #0F172A;">' . htmlspecialchars($detail_data['nama']) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Alamat Email:</td><td>' . htmlspecialchars($detail_data['email']) . '</td></tr>';
            $html .= '<tr><td style="font-weight: bold;">Jumlah Rental:</td><td><strong style="color: #3B82F6;">' . count($user_rentals) . ' Kali Transaksi</strong></td></tr>';
            $html .= '</table>';
            $html .= '</div>';
            
            $html .= '<h4 style="margin: 20px 0 8px 0; color: #0F172A;">Histori Sewa Kendaraan:</h4>';
            $html .= '<table class="data-table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="width: 8%;">No</th>';
            $html .= '<th style="width: 37%; text-align: left;">Nama Kendaraan</th>';
            $html .= '<th style="width: 35%;">Periode Penyewaan</th>';
            $html .= '<th style="width: 20%; text-align: right;">Total Biaya</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            if (count($user_rentals) > 0) {
                foreach ($user_rentals as $idx => $r) {
                    $period = date('d M Y H:i', strtotime($r['tanggal_sewa'])) . '<br><span style="font-size: 8pt; color: #64748B;">s.d. ' . date('d M Y H:i', strtotime($r['tanggal_kembali'])) . '</span>';
                    $html .= '<tr>';
                    $html .= '<td class="text-center">' . ($idx + 1) . '</td>';
                    $html .= '<td>';
                    $html .= '<strong>' . htmlspecialchars($r['nama_kendaraan'] ?? 'N/A') . '</strong>';
                    $html .= '<div style="font-size: 8pt; color: #64748B;">Kode: ' . htmlspecialchars($r['kode_unik_kendaraan']) . ' (' . ucwords($r['nama_merk'] ?? '') . ')</div>';
                    $html .= '</td>';
                    $html .= '<td class="text-center" style="font-size: 8.5pt;">' . $period . '</td>';
                    $html .= '<td class="text-right" style="font-weight: bold;">Rp ' . number_format($r['total_biaya'], 0, ',', '.') . '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="4" class="text-center" style="padding: 30px; color: #64748B;">Belum memiliki riwayat sewa kendaraan.</td></tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
        }
    } else {
        if ($target === 'analytics') {
            // QuickChart configuration
            $chart_labels = [];
            $chart_income = [];
            $chart_maint = [];
            foreach ($months_list as $m) {
                $chart_labels[] = $m['label'];
                $chart_income[] = $m['income'];
                $chart_maint[] = $m['maintenance'];
            }
            
            $chart_config = [
                'type' => 'bar',
                'data' => [
                    'labels' => $chart_labels,
                    'datasets' => [
                        [
                            'label' => 'Pendapatan',
                            'data' => $chart_income,
                            'backgroundColor' => 'rgba(59, 130, 246, 0.7)',
                            'borderColor' => 'rgb(59, 130, 246)',
                            'borderWidth' => 1
                        ],
                        [
                            'label' => 'Biaya Servis',
                            'data' => $chart_maint,
                            'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                            'borderColor' => 'rgb(239, 68, 68)',
                            'borderWidth' => 1
                        ]
                    ]
                ],
                'options' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Perbandingan Keuangan (6 Bulan Terakhir)'
                    ]
                ]
            ];
            
            $chart_url = "https://quickchart.io/chart?c=" . urlencode(json_encode($chart_config)) . "&w=600&h=300";

            $html .= '
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="margin-bottom: 5px; color: #0F172A;">LAPORAN ANALITIK KEUANGAN & OPERASIONAL</h2>
                <div style="font-size: 9pt; color: #64748B;">Tanggal Cetak: ' . date('d F Y, H:i:s') . '</div>
            </div>
            
            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B;">1. Ringkasan Finansial & Operasional</h3>
            <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
                <tr style="background-color: #F8FAFC;">
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; width: 35%;">Total Pendapatan</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; color: #16A34A; font-size: 11pt;">Rp ' . number_format($total_income, 0, ',', '.') . '</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; width: 35%;">Penyewaan Aktif</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">' . $active_bookings . ' Transaksi</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">Total Pengeluaran Servis</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; color: #DC2626; font-size: 11pt;">Rp ' . number_format($total_maintenance_cost, 0, ',', '.') . '</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">Total Armada</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">' . $total_vehicles . ' Unit</td>
                </tr>
                <tr style="background-color: #F1F5F9;">
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; color: #0F172A;">Keuntungan Bersih (Net Profit)</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold; color: ' . ($net_profit >= 0 ? '#16A34A' : '#DC2626') . '; font-size: 12pt;">Rp ' . number_format($net_profit, 0, ',', '.') . '</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">Total Pelanggan Terdaftar</td>
                    <td style="padding: 10px; border: 1px solid #E2E8F0; font-weight: bold;">' . $total_users . ' User</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin-top: 15px; margin-bottom: 25px;">
                <img src="' . $chart_url . '" style="width: 550px; height: 275px; border: 1px solid #E2E8F0; border-radius: 6px;">
            </div>
            
            <pagebreak />
            
            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B; margin-top: 20px;">2. Tren Bulanan (Bulan Berjalan)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">No</th>
                        <th>Bulan</th>
                        <th style="text-align: right;">Pendapatan</th>
                        <th style="text-align: right;">Biaya Servis</th>
                        <th style="text-align: right;">Keuntungan Bersih</th>
                    </tr>
                </thead>
                <tbody>';
                $no = 1;
                foreach ($months_list as $m) {
                    $profit = $m['income'] - $m['maintenance'];
                    $profit_color = ($profit >= 0) ? '#16A34A' : '#DC2626';
                    $html .= '
                    <tr>
                        <td class="text-center">' . $no++ . '</td>
                        <td>' . $m['label'] . '</td>
                        <td class="text-right">Rp ' . number_format($m['income'], 0, ',', '.') . '</td>
                        <td class="text-right">Rp ' . number_format($m['maintenance'], 0, ',', '.') . '</td>
                        <td class="text-right" style="font-weight: bold; color: ' . $profit_color . ';">Rp ' . number_format($profit, 0, ',', '.') . '</td>
                    </tr>';
                }
            $html .= '
                </tbody>
            </table>
            
            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B; margin-top: 30px;">3. Distribusi Terpopuler (Top 5 Merk)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">No</th>
                        <th>Nama Merk Kendaraan</th>
                        <th style="width: 30%; text-align: center;">Jumlah Transaksi</th>
                    </tr>
                </thead>
                <tbody>';
                if (!empty($top_brands)) {
                    $no = 1;
                    foreach ($top_brands as $tb) {
                        $html .= '
                        <tr>
                            <td class="text-center">' . $no++ . '</td>
                            <td style="font-weight: bold;">' . htmlspecialchars(ucwords($tb['nama_merk'])) . '</td>
                            <td class="text-center">' . $tb['count'] . ' Kali Sewa</td>
                        </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="3" class="text-center text-muted">Belum ada data distribusi penyewaan.</td></tr>';
                }
            $html .= '
                </tbody>
            </table>

            <pagebreak />
            
            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B;">4. Detail Pengeluaran Servis / Perawatan</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 15%;">ID Servis</th>
                        <th style="width: 25%;">Nama Kendaraan</th>
                        <th>Deskripsi Servis</th>
                        <th style="width: 15%; text-align: right;">Biaya</th>
                    </tr>
                </thead>
                <tbody>';
                if (!empty($maintenance_data)) {
                    $no = 1;
                    foreach ($maintenance_data as $md) {
                        $html .= '
                        <tr>
                            <td class="text-center">' . $no++ . '</td>
                            <td class="text-center font-monospace">#MNT-' . str_pad($md['id'], 5, '0', STR_PAD_LEFT) . '</td>
                            <td>' . htmlspecialchars($md['nama_kendaraan']) . '</td>
                            <td>' . htmlspecialchars($md['deskripsi']) . '</td>
                            <td class="text-right">Rp ' . number_format($md['biaya'], 0, ',', '.') . '</td>
                        </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat pengeluaran servis.</td></tr>';
                }
            $html .= '
                </tbody>
            </table>

            <pagebreak />
            
            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B;">5. Detail Riwayat Penyewaan Kendaraan</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 15%;">No Invoice</th>
                        <th style="width: 25%;">Penyewa</th>
                        <th>Kendaraan</th>
                        <th style="width: 15%; text-align: right;">Total Biaya</th>
                    </tr>
                </thead>
                <tbody>';
                if (!empty($rentals_data)) {
                    $no = 1;
                    foreach ($rentals_data as $rd) {
                        $html .= '
                        <tr>
                            <td class="text-center">' . $no++ . '</td>
                            <td class="text-center font-monospace">#INV-' . str_pad($rd['id_sewa'], 5, '0', STR_PAD_LEFT) . '</td>
                            <td>' . htmlspecialchars($rd['nama_user']) . '</td>
                            <td>' . htmlspecialchars($rd['nama_kendaraan']) . '</td>
                            <td class="text-right">Rp ' . number_format($rd['total_biaya'], 0, ',', '.') . '</td>
                        </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="5" class="text-center text-muted">Belum ada riwayat penyewaan.</td></tr>';
                }
            $html .= '
                </tbody>
            </table>

            <h3 style="border-bottom: 1px solid #CBD5E1; padding-bottom: 5px; color: #1E293B; margin-top: 30px;">6. Informasi Anggota Terdaftar (Users)</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 12%;">ID User</th>
                        <th>Nama Pengguna</th>
                        <th>Email</th>
                        <th style="width: 20%;">Nomor HP</th>
                    </tr>
                </thead>
                <tbody>';
                if (!empty($users_data)) {
                    $no = 1;
                    foreach ($users_data as $ud) {
                        $html .= '
                        <tr>
                            <td class="text-center">' . $no++ . '</td>
                            <td class="text-center">' . $ud['id'] . '</td>
                            <td style="font-weight: bold;">' . htmlspecialchars($ud['nama']) . '</td>
                            <td>' . htmlspecialchars($ud['email']) . '</td>
                            <td class="text-center">' . htmlspecialchars($ud['no_hp'] ?? '-') . '</td>
                        </tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="5" class="text-center text-muted">Belum ada pelanggan terdaftar.</td></tr>';
                }
            $html .= '
                </tbody>
            </table>
            
            <div class="footer-note" style="margin-top: 80px;">
                <p>Laporan Resmi FTrans Car Rental — Dicetak secara otomatis</p>
            </div>
            ';
        } else {
            // Cetakan List Laporan (All data)
            $html .= '<div class="title">' . strtoupper($title_text) . '</div>';
            $html .= '<div class="subtitle">Laporan Rekapitulasi Data Aplikasi FTrans</div>';
        
        $html .= '<table class="data-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        foreach ($headers as $headerTitle) {
            $html .= '<th>' . $headerTitle . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($data as $index => $row) {
            $html .= '<tr>';
            $html .= '<td class="text-center">' . ($index + 1) . '</td>';
            
            if ($target === 'kendaraan') {
                $html .= '<td class="text-center">' . htmlspecialchars($row['kode_unik_kendaraan']) . '</td>';
                $html .= '<td>' . htmlspecialchars(ucwords($row['nama_merk'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nama_kendaraan']) . '</td>';
                $jenisLabel = (strtolower(trim($row['jenis_kendaraan'])) === 'roda 2') ? 'Roda 2' : 'Roda 4';
                $html .= '<td class="text-center">' . $jenisLabel . '</td>';
                $html .= '<td class="text-right">Rp ' . number_format($row['harga_per_hari'], 0, ',', '.') . '</td>';
                
            } elseif ($target === 'sewa') {
                $html .= '<td>' . htmlspecialchars($row['nama_user'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nama_kendaraan'] ?? 'N/A') . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($row['kode_unik_kendaraan']) . '</td>';
                $html .= '<td class="text-center" style="font-size: 8.5pt;">' . date('d M Y H:i', strtotime($row['tanggal_sewa'])) . '</td>';
                $html .= '<td class="text-center" style="font-size: 8.5pt;">' . date('d M Y H:i', strtotime($row['tanggal_kembali'])) . '</td>';
                $html .= '<td class="text-right" style="font-weight: bold;">Rp ' . number_format($row['total_biaya'], 0, ',', '.') . '</td>';
                
                $status = $row['status'] ?? 'booking';
                $statusLabel = 'Booking';
                if ($status === 'sedang_disewa') $statusLabel = 'Sewa';
                if ($status === 'selesai') $statusLabel = 'Selesai';
                if ($status === 'dibatalkan') $statusLabel = 'Batal';
                
                $html .= '<td class="text-center">' . $statusLabel . '</td>';
                
            } elseif ($target === 'users') {
                $html .= '<td class="text-center">#' . $row['id'] . '</td>';
                $html .= '<td>' . htmlspecialchars($row['nama']) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['email']) . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars(ucfirst($row['role'] ?? 'user')) . '</td>';
                $html .= '<td class="text-center">' . $row['jumlah_sewa'] . ' Sewa</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        }
    }
    
    $mpdf->WriteHTML($html);
    
    $filename = 'ftrans_doc_' . $target . ($is_detail ? '_detail_' . $detail_id : '_laporan') . '_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($filename, 'I');
    exit;
}
