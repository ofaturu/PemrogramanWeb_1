<?php
// ==========================================
// 1. PENGATURAN HEADER & DATABASE
// ==========================================
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Sesuaikan dengan kredensial database kamu
$host = "localhost";
$user = "root";
$pass = "";
$db   = "belajarrelasi";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $conn->connect_error]));
}

// Menangkap metode HTTP (GET, POST, PUT, DELETE)
$method = $_SERVER['REQUEST_METHOD'];

// Menangkap input JSON dari raw body (untuk POST dan PUT)
$input = json_decode(file_get_contents('php://input'), true);

// ==========================================
// 2. ROUTING LOGIC CRUD
// ==========================================
switch ($method) {
    
    // --- READ (Tampilkan Data) ---
    case 'GET':
        if (isset($_GET['nim'])) {
            // Ambil satu data berdasarkan NIM
            $nim = intval($_GET['nim']);
            $stmt = $conn->prepare("SELECT * FROM mahasiswa WHERE nim = ?");
            $stmt->bind_param("i", $nim);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            echo json_encode($data ? $data : ["message" => "Data tidak ditemukan"]);
        } else {
            // Ambil semua data
            $result = $conn->query("SELECT * FROM mahasiswa ORDER BY nim DESC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode($data);
        }
        break;

    // --- CREATE (Tambah Data Baru) ---
    case 'POST':
        if (!empty($input['nim']) && !empty($input['nama']) && !empty($input['id_prodi'])) {
            $nim = intval($input['nim']);
            $nama = $input['nama'];
            $id_prodi = intval($input['id_prodi']);

            $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, nama, id_prodi) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $nim, $nama, $id_prodi);

            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Data berhasil ditambahkan"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal menambahkan data: " . $stmt->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
        }
        break;

    // --- UPDATE (Ubah Data) ---
    case 'PUT':
        if (!empty($input['nim']) && !empty($input['nama']) && !empty($input['id_prodi'])) {
            $nim = intval($input['nim']);
            $nama = $input['nama'];
            $id_prodi = intval($input['id_prodi']);

            // NIM digunakan sebagai acuan (WHERE), nama dan id_prodi yang diubah
            $stmt = $conn->prepare("UPDATE mahasiswa SET nama = ?, id_prodi = ? WHERE nim = ?");
            $stmt->bind_param("sii", $nama, $id_prodi, $nim);

            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Data berhasil diupdate"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal mengupdate data"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
        }
        break;

    // --- DELETE (Hapus Data) ---
    case 'DELETE':
        // Bisa dari parameter URL (?nim=...) atau dari body JSON
        $nim = isset($_GET['nim']) ? intval($_GET['nim']) : (isset($input['nim']) ? intval($input['nim']) : null);

        if ($nim) {
            $stmt = $conn->prepare("DELETE FROM mahasiswa WHERE nim = ?");
            $stmt->bind_param("i", $nim);

            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Data berhasil dihapus"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal menghapus data"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "NIM tidak ditemukan untuk dihapus"]);
        }
        break;

    default:
        // Jika method tidak dikenali
        header("HTTP/1.0 405 Method Not Allowed");
        echo json_encode(["status" => "error", "message" => "Method HTTP tidak diizinkan"]);
        break;
}

$conn->close();
?>