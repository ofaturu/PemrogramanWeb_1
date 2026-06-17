<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Tangani preflight request dari browser/client
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "belajarrelasi";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->connect_error]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Kunci utamanya di sini: Selalu decode raw JSON untuk semua method (POST, PUT, DELETE)
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $result = $conn->query("SELECT * FROM mahasiswa ORDER BY nim DESC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;

    case 'POST':
        if (!empty($input['nim']) && !empty($input['nama']) && !empty($input['id_prodi'])) {
            $nim = intval($input['nim']);
            $nama = $input['nama'];
            $id_prodi = intval($input['id_prodi']);

            $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, nama, id_prodi) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $nim, $nama, $id_prodi);

            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Data $nama berhasil ditambahkan!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal: NIM mungkin sudah ada."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Data POST tidak lengkap."]);
        }
        break;

    case 'PUT':
        if (!empty($input['nim']) && !empty($input['nama']) && !empty($input['id_prodi'])) {
            $nim = intval($input['nim']);
            $nama = $input['nama'];
            $id_prodi = intval($input['id_prodi']);

            $stmt = $conn->prepare("UPDATE mahasiswa SET nama = ?, id_prodi = ? WHERE nim = ?");
            $stmt->bind_param("sii", $nama, $id_prodi, $nim);

            if ($stmt->execute()) {
                // Cek apakah ada baris yang benar-benar berubah
                if ($stmt->affected_rows > 0) {
                    echo json_encode(["status" => "success", "message" => "Data NIM $nim berhasil diupdate!"]);
                } else {
                    echo json_encode(["status" => "success", "message" => "Tidak ada perubahan data pada NIM $nim."]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Query update gagal."]);
            }
        } else {
            // Memberikan detail error jika data kosong (berguna untuk debugging)
            echo json_encode(["status" => "error", "message" => "Data PUT tidak lengkap. Terbaca: " . json_encode($input)]);
        }
        break;

    case 'DELETE':
        // Menangkap NIM dari Body JSON yang dikirim cURL client
        if (!empty($input['nim'])) {
            $nim = intval($input['nim']);
            $stmt = $conn->prepare("DELETE FROM mahasiswa WHERE nim = ?");
            $stmt->bind_param("i", $nim);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(["status" => "success", "message" => "Data NIM $nim berhasil dihapus!"]);
                } else {
                    echo json_encode(["status" => "error", "message" => "NIM $nim tidak ditemukan di database."]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Gagal eksekusi query hapus."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "NIM tidak dikirimkan untuk dihapus."]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Method tidak didukung."]);
        break;
}

$conn->close();
?>