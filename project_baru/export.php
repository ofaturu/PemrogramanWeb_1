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

if (!in_array($target, ['kendaraan', 'sewa', 'users']) || !in_array($format, ['excel', 'word', 'pdf'])) {
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
        $title_text = "Laporan Data Kendaraan — FTrans";
        $headers = ['No', 'Kode Unik', 'Merk Kendaraan', 'Model / Nama Kendaraan', 'Jenis Kendaraan', 'Harga per Hari'];
        
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $stmt = mysqli_prepare($mysqli, "
                SELECT k.*, m.nama_merk 
                FROM kendaraan k 
                LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
                WHERE k.kode_unik_kendaraan LIKE ? 
                   OR k.nama_kendaraan LIKE ? 
                   OR k.jenis_kendaraan LIKE ? 
                ORDER BY k.kode_unik_kendaraan ASC
            ");
            mysqli_stmt_bind_param($stmt, 'sss', $search_param, $search_param, $search_param);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        } else {
            $query = "
                SELECT k.*, m.nama_merk 
                FROM kendaraan k 
                LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
                ORDER BY k.kode_unik_kendaraan ASC
            ";
            $result = mysqli_query($mysqli, $query);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
    } elseif ($target === 'sewa') {
        $title_text = "Laporan Transaksi Penyewaan Kendaraan — FTrans";
        $headers = ['No', 'Nama Penyewa', 'Nama Kendaraan', 'Kode Kendaraan', 'Tanggal Sewa', 'Tanggal Kembali', 'Total Biaya', 'Status'];
        
        $query = "
            SELECT p.*, u.nama AS nama_user, k.nama_kendaraan 
            FROM penyewaan p 
            LEFT JOIN users u ON p.id_user = u.id 
            LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
            ORDER BY p.id_sewa DESC
        ";
        $result = mysqli_query($mysqli, $query);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } elseif ($target === 'users') {
        $search = trim($_GET['search'] ?? '');
        $title_text = "Laporan Data User Aplikasi — FTrans";
        $headers = ['No', 'ID User', 'Nama User', 'Email', 'Jumlah Transaksi Sewa'];
        
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $query = "
                SELECT u.id, u.nama, u.email, COUNT(p.id_sewa) AS jumlah_sewa
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
                SELECT u.id, u.nama, u.email, COUNT(p.id_sewa) AS jumlah_sewa
                FROM users u
                LEFT JOIN penyewaan p ON u.id = p.id_user
                GROUP BY u.id
                ORDER BY u.nama ASC
            ";
            $result = mysqli_query($mysqli, $query);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
        }
    }
}

// ---------------------------------------------------------
// EXCEL FORMAT (PhpSpreadsheet)
// ---------------------------------------------------------
if ($format === 'excel') {
    if (ob_get_level()) {
        ob_end_clean();
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
    
    $sheet->setCellValue('A2', 'FTrans Car Rental Management System');
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
            $sheet->setCellValue('E' . $currentRow, $row['jumlah_sewa']);
            $sheet->getStyle('E' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
        $colWidths = [800, 1200, 2800, 3200, 1500];
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
            $html .= '<td class="text-center">Rp ' . number_format($detail_data['harga_per_hari'], 0, ',', '.') . '</td>';
            $html .= '<td class="text-center">' . $diff_days . ' Hari</td>';
            $html .= '<td class="text-right" style="font-weight: bold;">Rp ' . number_format($detail_data['total_biaya'], 0, ',', '.') . '</td>';
            $html .= '</tr>';
            $html .= '<tr class="grand-total-row">';
            $html .= '<td colspan="3" class="text-right" style="padding: 10px;">Total Pembayaran:</td>';
            $html .= '<td class="text-right grand-total-val" style="padding: 10px;">Rp ' . number_format($detail_data['total_biaya'], 0, ',', '.') . '</td>';
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            
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
                $html .= '<td class="text-center">' . $row['jumlah_sewa'] . ' Sewa</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
    }
    
    $mpdf->WriteHTML($html);
    
    $filename = 'ftrans_doc_' . $target . ($is_detail ? '_detail_' . $detail_id : '_laporan') . '_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($filename, 'D');
    exit;
}
