<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labwise Asset Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1200px; margin-top: 30px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .table-responsive { margin-top: 20px; }
        .report-title { margin-bottom: 30px; text-align: center; }
        .print-btn { margin-top: 20px; }
        .small-col { width: 80px; }
        .center {
            display: block;
            margin-left: auto;
            margin-right: auto;
            width: 50%;
        }
        /* Print-specific styles */
        @media print {
            body * {
                visibility: hidden;
                font-size: 10px;
                line-height: 1.2;
                margin: 0;
                padding: 0;
            }
            #report-section, #report-section * {
                visibility: visible;
            }
            #report-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .print-btn, .card-header, .alert, form {
                display: none !important;
            }
            table {
                width: 100% !important;
                font-size: 8px;
            }
            .report-title h4 {
                font-size: 14px;
                margin-bottom: 5px;
            }
            .report-title p {
                font-size: 10px;
                margin-bottom: 10px;
            }
            th, td {
                padding: 2px !important;
            }
            .badge {
                font-size: 8px;
                padding: 2px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Centered Image -->
        <img src="path_to_your_image.jpg" alt="Descriptive Alt Text" class="center">
        <!-- Your existing card and form code here -->
    </div>
    <!-- Footer -->
    <footer>
        <p class="text-center">ICT Lead Sign</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            const table = document.querySelector('table');
            const wb = XLSX.utils.table_to_book(table);
            const labName = "<?= $lab_name ?>";
            const fileName = labName ? `Asset_Report_${labName.replace(/\s+/g, '_')}.xlsx` : 'Asset_Report.xlsx';
            XLSX.writeFile(wb, fileName);
        }
    </script>
</body>
</html>
