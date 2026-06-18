<!-- Modal Edit Mahasiswa -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.1);">
            <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #ffc107, #ff9800); border-bottom: none;">
                <h5 class="modal-title fw-semibold text-dark" id="modalEditLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Data Mahasiswa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="edit.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit-nim" class="form-label fw-medium text-secondary">NIM (Tidak dapat diubah)</label>
                        <input type="number" class="form-control form-control-lg bg-light" id="edit-nim" name="nim" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-nama" class="form-label fw-medium text-secondary">Nama Lengkap</label>
                        <input type="text" class="form-control form-control-lg" id="edit-nama" name="nama" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-id_prodi" class="form-label fw-medium text-secondary">ID Program Studi</label>
                        <input type="number" class="form-control form-control-lg" id="edit-id_prodi" name="id_prodi" required>
                    </div>
                </div>
                <div class="modal-footer p-3 bg-light" style="border-top: none;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning px-4 text-dark fw-medium" style="background: linear-gradient(135deg, #ffc107, #e0a800); border: none;">
                        Update Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
