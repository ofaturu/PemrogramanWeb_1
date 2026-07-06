<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

// Proteksi session login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$action = $_GET['action'] ?? '';

// ---------------------------------------------------------
// AKSI 1: Download Template Excel
// ---------------------------------------------------------
if ($action === 'template') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template Kendaraan');
    
    // Set Font default ke Segoe UI
    $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
    
    // Judul & Petunjuk Pengisian
    $sheet->setCellValue('A1', 'TEMPLATE IMPOR DATA KENDARAAN');
    $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
    
    $sheet->setCellValue('A2', 'Petunjuk Pengisian:');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
    
    $sheet->setCellValue('A3', '1. Kolom Kode Unik wajib diisi dengan angka unik. Jika Kode Unik sudah ada di database, data lama akan diperbarui.');
    $sheet->setCellValue('A4', '2. ID Merk harus diisi dengan ID Merk yang valid. Lihat daftar ID Merk yang valid di kolom sebelah kanan.');
    $sheet->setCellValue('A5', '3. Jenis Kendaraan wajib diisi dengan tulisan "Roda 2" atau "Roda 4".');
    $sheet->setCellValue('A6', '4. Harga per Hari harus diisi dengan angka bulat positif (tanpa Rp, titik, atau koma).');
    
    $sheet->getStyle('A3:A6')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
    
    // Table Header di baris ke-8
    $headers = ['Kode Unik', 'ID Merk', 'Model Kendaraan', 'Jenis Kendaraan', 'Harga per Hari'];
    foreach ($headers as $colIdx => $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
        $sheet->setCellValue($colLetter . '8', $header);
    }
    
    $sheet->getStyle('A8:E8')->applyFromArray([
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
    $sheet->getRowDimension(8)->setRowHeight(25);
    
    // Baris Contoh Data di baris ke-9
    $sheet->setCellValue('A9', '9091');
    $sheet->setCellValue('B9', '2'); // ID Toyota
    $sheet->setCellValue('C9', 'Avanza Veloz');
    $sheet->setCellValue('D9', 'Roda 4');
    $sheet->setCellValue('E9', '350000');
    
    $sheet->getStyle('A9:E9')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet->getRowDimension(9)->setRowHeight(20);
    
    // Border untuk tabel input
    $sheet->getStyle('A8:E9')->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ]
    ]);
    
    // Kolom Referensi ID Merk (valid) di bagian kanan (Kolom G)
    $sheet->setCellValue('G2', 'Daftar Referensi ID Merk (Valid)');
    $sheet->getStyle('G2')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
    
    $sheet->setCellValue('G3', 'ID');
    $sheet->setCellValue('H3', 'Nama Merk');
    $sheet->getStyle('G3:H3')->getFont()->setBold(true);
    
    $res_merk = mysqli_query($mysqli, "SELECT id_merk, nama_merk FROM merk_kendaraan ORDER BY nama_merk ASC");
    $currentRow = 4;
    while ($m = mysqli_fetch_assoc($res_merk)) {
        $sheet->setCellValue('G' . $currentRow, $m['id_merk']);
        $sheet->setCellValue('H' . $currentRow, ucwords($m['nama_merk']));
        $currentRow++;
    }
    
    // Border tabel referensi merk
    $sheet->getStyle('G3:H' . ($currentRow - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0']
            ]
        ]
    ]);
    
    // Auto-fit Column Width
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);
    $sheet->getColumnDimension('D')->setAutoSize(true);
    $sheet->getColumnDimension('E')->setAutoSize(true);
    $sheet->getColumnDimension('G')->setAutoSize(true);
    $sheet->getColumnDimension('H')->setAutoSize(true);
    
    $filename = 'ftrans_template_import_kendaraan.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ---------------------------------------------------------
// AKSI 2: Proses Unggah & Impor File Excel/CSV
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['import_error'] = 'Gagal mengunggah file. Pastikan Anda memilih file Excel/CSV yang valid.';
        header('Location: dashboard.php');
        exit;
    }
    
    $file_tmp = $_FILES['excel_file']['tmp_name'];
    
    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_tmp);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file_tmp);
        $sheet = $spreadsheet->getActiveSheet();
        
        $highestRow = $sheet->getHighestRow();
        
        // Ambil daftar merk untuk lookup cepat (optimasi DB query)
        $merk_list = [];
        $res_m = mysqli_query($mysqli, "SELECT id_merk, nama_merk FROM merk_kendaraan");
        while ($m = mysqli_fetch_assoc($res_m)) {
            $merk_list[$m['id_merk']] = $m['nama_merk'];
        }
        
        $inserted = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        
        // Data dimulai dari baris ke-9 (baris ke-8 adalah header, baris 9 adalah baris data pertama)
        for ($rowNum = 9; $rowNum <= $highestRow; $rowNum++) {
            $kode = trim($sheet->getCell('A' . $rowNum)->getValue() ?? '');
            $id_merk = trim($sheet->getCell('B' . $rowNum)->getValue() ?? '');
            $model = trim($sheet->getCell('C' . $rowNum)->getValue() ?? '');
            $jenis = trim($sheet->getCell('D' . $rowNum)->getValue() ?? '');
            $harga = trim($sheet->getCell('E' . $rowNum)->getValue() ?? '');
            
            // Jika baris benar-benar kosong, lewati secara senyap
            if ($kode === '' && $id_merk === '' && $model === '' && $jenis === '' && $harga === '') {
                continue;
            }
            
            // VALIDASI DATA
            if ($kode === '' || $id_merk === '' || $model === '' || $jenis === '' || $harga === '') {
                $failed++;
                $errors[] = "Baris {$rowNum}: Semua field wajib diisi.";
                continue;
            }
            
            if (!is_numeric($kode)) {
                $failed++;
                $errors[] = "Baris {$rowNum}: Kode Unik harus berupa angka.";
                continue;
            }
            
            if (!array_key_exists($id_merk, $merk_list)) {
                $failed++;
                $errors[] = "Baris {$rowNum}: ID Merk '{$id_merk}' tidak valid / tidak terdaftar di sistem.";
                continue;
            }
            
            $jenis_clean = strtolower(trim($jenis));
            if ($jenis_clean !== 'roda 2' && $jenis_clean !== 'roda 4') {
                $failed++;
                $errors[] = "Baris {$rowNum}: Jenis Kendaraan harus tertulis 'Roda 2' atau 'Roda 4'.";
                continue;
            }
            $jenis_db = ($jenis_clean === 'roda 2') ? 'roda 2' : 'roda 4';
            
            if (!is_numeric($harga) || $harga < 0) {
                $failed++;
                $errors[] = "Baris {$rowNum}: Harga per hari harus berupa angka positif.";
                continue;
            }
            
            // Susun nama_kendaraan: [Nama Merk] + [Model]
            $brand = $merk_list[$id_merk];
            $nama = trim(ucwords($brand) . ' ' . $model);
            
            // Cek apakah Kode Unik Kendaraan sudah ada
            $cek = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = ?");
            mysqli_stmt_bind_param($cek, 'i', $kode);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            
            if (mysqli_stmt_num_rows($cek) > 0) {
                // UPDATE data yang sudah ada
                mysqli_stmt_close($cek);
                $upd = mysqli_prepare($mysqli, "
                    UPDATE kendaraan 
                    SET id_merk = ?, nama_kendaraan = ?, jenis_kendaraan = ?, harga_per_hari = ? 
                    WHERE kode_unik_kendaraan = ?
                ");
                mysqli_stmt_bind_param($upd, 'issdi', $id_merk, $nama, $jenis_db, $harga, $kode);
                
                if (mysqli_stmt_execute($upd)) {
                    $updated++;
                } else {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Gagal memperbarui data di database.";
                }
                mysqli_stmt_close($upd);
            } else {
                // INSERT data baru
                mysqli_stmt_close($cek);
                $ins = mysqli_prepare($mysqli, "
                    INSERT INTO kendaraan (kode_unik_kendaraan, id_merk, nama_kendaraan, jenis_kendaraan, harga_per_hari, gambar) 
                    VALUES (?, ?, ?, ?, ?, NULL)
                ");
                mysqli_stmt_bind_param($ins, 'sissd', $kode, $id_merk, $nama, $jenis_db, $harga);
                
                if (mysqli_stmt_execute($ins)) {
                    $inserted++;
                } else {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Gagal menyimpan data baru ke database.";
                }
                mysqli_stmt_close($ins);
            }
        }
        
        // Simpan hasil ke session untuk ditampilkan di dashboard.php
        $_SESSION['import_result'] = [
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ];
        
        header('Location: dashboard.php?msg=imported');
        exit;
        
    } catch (\Exception $e) {
        $_SESSION['import_error'] = 'Terjadi kesalahan saat memproses file: ' . $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
}
