<footer class="footer px-4 py-3 border-top mt-auto bg-body-tertiary" style="position: sticky; bottom: 0; z-index: 1020;">
  <div class="container-fluid d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
    <div>
      &copy; <?= date('Y') ?> <a href="#" class="text-decoration-none text-primary fw-semibold">FTrans Management</a>. All Rights Reserved.
    </div>
    <div class="text-body-secondary small">
      Powered by <a href="https://www.instagram.com/ofaturu/" class="text-decoration-none text-primary fw-semibold" target="_blank">Ofaturu</a>
    </div>
  </div>
</footer>
</div> <!-- Closing div of wrapper -->

<!-- jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- CoreUI and necessary plugins-->
<script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
<script src="vendors/simplebar/js/simplebar.min.js"></script>
<!-- PWA & Mobile Script -->
<script src="js/pwa.js"></script>
<script>
  // Add scroll class to header
  const headerElement = document.querySelector("header.header");
  if (headerElement) {
    document.addEventListener("scroll", () => {
      headerElement.classList.toggle("shadow-sm", document.documentElement.scrollTop > 0);
    });
  }
</script>
</body>
</html>
