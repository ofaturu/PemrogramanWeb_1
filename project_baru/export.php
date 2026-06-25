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

if (!in_array($target, ['kendaraan', 'sewa', 'users']) || !in_array($format, ['excel', 'word'])) {
    die("Parameter ekspor tidak valid.");
}

$data = [];
$title_text = "";
$headers = [];

// Mengambil data berdasarkan target
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

// ---------------------------------------------------------
// EXCEL FORMAT (PhpSpreadsheet)
// ---------------------------------------------------------
if ($format === 'excel') {
    // Bersihkan buffer output untuk menghindari file korup
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr(ucfirst($target), 0, 30));
    
    // Default Font Style
    $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
    
    // Tentukan kolom terakhir
    $maxColIndex = count($headers);
    $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxColIndex);
    
    // Bagian Header Dokumen
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
    
    // Table Header Style
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
            'startColor' => ['rgb' => '1E293B'] // Slate-800
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ]
    ]);
    
    // Isi Data Laporan
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
    
    // Border tabel data
    $lastRow = $currentRow - 1;
    $dataRange = "A5:{$lastColLetter}{$lastRow}";
    
    $sheet->getStyle($dataRange)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'] // Light slate gray
            ]
        ]
    ]);
    
    // Auto-fit Column Width
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
    
    $properties = $phpWord->getDocProperties();
    $properties->setCreator('FTrans Car Rental');
    $properties->setCompany('FTrans');
    $properties->setTitle($title_text);
    
    // Page margins setup
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginLeft' => 1000,
        'marginRight' => 1000,
        'marginTop' => 1000,
        'marginBottom' => 1000,
    ]);
    
    // Styling title
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
    
    // Subtitle
    $section->addText('FTrans Car Rental Management System', [
        'name' => 'Segoe UI',
        'size' => 10,
        'italic' => true,
        'color' => '475569',
    ], [
        'alignment' => 'center',
        'spaceAfter' => 60
    ]);
    
    // Date
    $section->addText('Tanggal Cetak: ' . date('d F Y, H:i:s'), [
        'name' => 'Segoe UI',
        'size' => 8,
        'color' => '64748B'
    ], [
        'alignment' => 'center',
        'spaceAfter' => 300
    ]);
    
    // Table Style definition
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => 'CBD5E1',
        'cellMargin' => 80,
        'alignment' => 'center'
    ];
    $phpWord->addTableStyle('Report Table', $tableStyle);
    $table = $section->addTable('Report Table');
    
    // Column widths in twips
    $colWidths = [];
    if ($target === 'kendaraan') {
        $colWidths = [800, 1500, 1800, 2400, 1500, 1500];
    } elseif ($target === 'sewa') {
        $colWidths = [600, 1600, 1800, 1000, 1400, 1400, 1200, 800];
    } elseif ($target === 'users') {
        $colWidths = [800, 1200, 2800, 3200, 1500];
    }
    
    // Table Header Row
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
    
    // Table Data Rows
    foreach ($data as $index => $row) {
        $bg = ($index % 2 === 0) ? 'FFFFFF' : 'F8FAFC'; // Zebra striping
        $cellStyle = ['bgColor' => $bg];
        
        $table->addRow(300);
        
        // No
        $cell = $table->addCell($colWidths[0], $cellStyle);
        $cell->addText($index + 1, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
        
        if ($target === 'kendaraan') {
            // Kode Unik
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText($row['kode_unik_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Merk
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText(ucwords($row['nama_merk'] ?? ''), ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Model/Nama
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['nama_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Jenis
            $jenisLabel = (strtolower(trim($row['jenis_kendaraan'])) === 'roda 2') ? 'Roda 2' : 'Roda 4';
            $cell = $table->addCell($colWidths[4], $cellStyle);
            $cell->addText($jenisLabel, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Harga
            $hargaText = 'Rp ' . number_format($row['harga_per_hari'], 0, ',', '.');
            $cell = $table->addCell($colWidths[5], $cellStyle);
            $cell->addText($hargaText, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'right', 'spaceAfter' => 0]);
            
        } elseif ($target === 'sewa') {
            // Penyewa
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText($row['nama_user'] ?? 'N/A', ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Kendaraan
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText($row['nama_kendaraan'] ?? 'N/A', ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Kode
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['kode_unik_kendaraan'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Tgl Sewa
            $tglSewa = date('d M Y H:i', strtotime($row['tanggal_sewa']));
            $cell = $table->addCell($colWidths[4], $cellStyle);
            $cell->addText($tglSewa, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Tgl Kembali
            $tglKembali = date('d M Y H:i', strtotime($row['tanggal_kembali']));
            $cell = $table->addCell($colWidths[5], $cellStyle);
            $cell->addText($tglKembali, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Total Biaya
            $totalBiayaText = $row['total_biaya'] !== null ? 'Rp ' . number_format($row['total_biaya'], 0, ',', '.') : '-';
            $cell = $table->addCell($colWidths[6], $cellStyle);
            $cell->addText($totalBiayaText, ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'right', 'spaceAfter' => 0]);
            
            // Status
            $status = $row['status'] ?? 'booking';
            $statusLabel = 'Booking';
            if ($status === 'sedang_disewa') $statusLabel = 'Sewa';
            if ($status === 'selesai') $statusLabel = 'Selesai';
            if ($status === 'dibatalkan') $statusLabel = 'Batal';
            
            $cell = $table->addCell($colWidths[7], $cellStyle);
            $cell->addText($statusLabel, ['name' => 'Segoe UI', 'size' => 8], ['alignment' => 'center', 'spaceAfter' => 0]);
            
        } elseif ($target === 'users') {
            // ID
            $cell = $table->addCell($colWidths[1], $cellStyle);
            $cell->addText('#' . $row['id'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'center', 'spaceAfter' => 0]);
            
            // Nama
            $cell = $table->addCell($colWidths[2], $cellStyle);
            $cell->addText($row['nama'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Email
            $cell = $table->addCell($colWidths[3], $cellStyle);
            $cell->addText($row['email'], ['name' => 'Segoe UI', 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            
            // Transaksi
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
