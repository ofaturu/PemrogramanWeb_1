<!-- Modal Tambah Mahasiswa -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.1);">
            <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #0d6efd, #0b5ed7); border-bottom: none;">
                <h5 class="modal-title fw-semibold" id="modalTambahLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>Tambah Mahasiswa Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="tambah.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="nim" class="form-label fw-medium text-secondary">NIM (Nomor Induk Mahasiswa)</label>
                        <input type="number" class="form-control form-control-lg" id="nim" name="nim" 
                               placeholder="Contoh: 21010101" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label fw-medium text-secondary">Nama Lengkap</label>
                        <input type="text" class="form-control form-control-lg" id="nama" name="nama" 
                               placeholder="Nama lengkap mahasiswa" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_prodi" class="form-label fw-medium text-secondary">ID Program Studi</label>
                        <input type="number" class="form-control form-control-lg" id="id_prodi" name="id_prodi" 
                               placeholder="Contoh: 12" required>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light" style="border-top: none;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4" style="background: linear-gradient(135deg, #0d6efd, #0b5ed7); border: none;">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
