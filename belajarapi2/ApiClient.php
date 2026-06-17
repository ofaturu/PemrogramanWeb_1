<?php

class ApiClient {
    private $apiUrl = "http://172.16.9.36/belajarrelasi/api.php";

    /**
     * Membantu mengeksekusi cURL agar kode tidak berulang (DRY principle)
     */
    private function sendRequest($method, $data = null, $queryParams = "") {
        $url = $this->apiUrl . $queryParams;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ["status" => "error", "message" => $error];
        }

        return json_decode($response, true);
    }

    /**
     * READ: Mengambil semua data mahasiswa
     */
    public function read() {
        return $this->sendRequest('GET');
    }

    /**
     * CREATE: Menambahkan data mahasiswa baru
     */
    public function create($nim, $nama, $id_prodi) {
        $data = [
            "nim" => (int)$nim,
            "nama" => $nama,
            "id_prodi" => (int)$id_prodi
        ];
        return $this->sendRequest('POST', $data);
    }

    /**
     * UPDATE: Mengubah data mahasiswa berdasarkan NIM
     */
    public function update($nim, $nama, $id_prodi) {
        $data = [
            "nim" => (int)$nim,
            "nama" => $nama,
            "id_prodi" => (int)$id_prodi
        ];
        // Asumsi API membutuhkan NIM di URL parameter atau di body untuk identifikasi update
        return $this->sendRequest('PUT', $data); 
    }

    /**
     * DELETE: Menghapus data mahasiswa berdasarkan NIM
     */
    public function delete($nim) {
        $data = ["nim" => (int)$nim];
        // Tergantung spesifikasi API, method DELETE bisa menggunakan body atau query string
        return $this->sendRequest('DELETE', $data);
    }
}
?>