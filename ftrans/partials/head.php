<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= isset($title) ? $title : 'FTrans Management' ?></title>
    
    <link rel="apple-touch-icon" sizes="57x57" href="assets/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FTrans">
    <meta name="msapplication-TileColor" content="#1e293b">
    <meta name="msapplication-TileImage" content="assets/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#1e293b">
    
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Vendors styles-->
    <link rel="stylesheet" href="vendors/simplebar/css/simplebar.css">
    <link rel="stylesheet" href="css/vendors/simplebar.css">
    <!-- Main styles for this application-->
    <link href="css/style.css" rel="stylesheet">
    <!-- Examples styles-->
    <link href="css/examples.css" rel="stylesheet">
    
    <script src="js/config.js"></script>
    <script src="js/color-modes.js"></script>
    <!-- Select2 & Theme -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        /* Select2 Dark Mode Override to match CoreUI inputs */
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-selection {
            background-color: #222630 !important;
            color: rgba(255, 255, 255, 0.87) !important;
            border-color: #323a49 !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: rgba(255, 255, 255, 0.87) !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown {
            background-color: #222630 !important;
            color: rgba(255, 255, 255, 0.87) !important;
            border-color: #323a49 !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-results__option {
            background-color: transparent !important;
            color: rgba(255, 255, 255, 0.87) !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #323a49 !important;
            color: #fff !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: #fff !important;
        }
        [data-coreui-theme="dark"] .select2-container--bootstrap-5 .select2-search__field {
            background-color: #1d222b !important;
            color: #fff !important;
            border-color: #323a49 !important;
        }
        /* Force modal footers to match the active theme's background */
        .modal-footer, .modal-footer.bg-light {
            background-color: transparent !important;
        }
        
        /* Smartphone & Mobile Layout Optimizations */
        @media (max-width: 767.98px) {
            .body.flex-grow-1 {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            .container-lg, .container-fluid {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .table-responsive {
                -webkit-overflow-scrolling: touch;
                border-radius: 8px;
            }
            .card-body {
                padding: 1rem !important;
            }
            .btn {
                padding-top: 0.6rem;
                padding-bottom: 0.6rem;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            /* High visibility WhatsApp CTA for mobile */
            .btn-whatsapp-cta {
                font-size: 0.95rem;
                padding: 12px 18px;
                border-radius: 50px;
                box-shadow: 0 4px 15px rgba(37, 211, 102, 0.35);
            }
        }
    </style>
</head>