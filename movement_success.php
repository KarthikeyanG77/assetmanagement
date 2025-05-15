<?php
require_once 'config.php';
require_once 'session_helper.php';
include('header.php');
redirect_if_not_logged_in();

if (!isset($_SESSION['movement_success'])) {
    header("Location: update_status.php");
    exit;
}

$successData = $_SESSION['movement_success'];
unset($_SESSION['movement_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movement Success</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; margin-top: 30px; }
        .success-card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .asset-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
        .whatsapp-btn { background-color: #25D366; border-color: #25D366; }
        .whatsapp-btn:hover { background-color: #128C7E; border-color: #128C7E; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card success-card">
            <div class="card-header bg-success text-white">
                <h3 class="mb-0">Movement Successfully Logged</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <?= htmlspecialchars($successData['message']) ?>
                </div>
                
                <div class="mb-4 asset-info">
                    <h5>Movement Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Asset ID:</strong> <?= htmlspecialchars($successData['asset_id']) ?></p>
                            <p><strong>Serial No:</strong> <?= htmlspecialchars($successData['serial_no']) ?></p>
                            <p><strong>From Location:</strong> <?= htmlspecialchars($successData['from_location']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>To Location:</strong> <?= htmlspecialchars($successData['to_location']) ?></p>
                            <p><strong>Receiver:</strong> <?= htmlspecialchars($successData['receiver_name']) ?></p>
                            <p><strong>Move Date:</strong> <?= htmlspecialchars($successData['move_date']) ?></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Status:</strong> <?= htmlspecialchars($successData['status']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button onclick="sendWhatsAppMessage()" class="btn whatsapp-btn btn-lg">
                        <i class="fab fa-whatsapp"></i> Send WhatsApp Acknowledgment
                    </button>
                    <a href="update_status.php" class="btn btn-primary btn-lg ms-3">
                        <i class="fas fa-undo"></i> Back to Movement Log
                    </a>
                </div>
                
                <script>
                    function sendWhatsAppMessage() {
                        // Open WhatsApp Web in a new tab
                        const whatsappTab = window.open('<?= htmlspecialchars($successData['whatsapp_url']) ?>', '_blank');
                        
                        // After a delay, try to simulate the send button click
                        setTimeout(() => {
                            try {
                                // This is a hack to try to send the message automatically
                                // Note: Due to browser security restrictions, this might not work in all cases
                                if (whatsappTab) {
                                    whatsappTab.focus();
                                    // You might need to manually click send in the WhatsApp Web interface
                                }
                            } catch (e) {
                                console.log("Couldn't automatically send message due to security restrictions");
                            }
                        }, 3000); // Wait 3 seconds for WhatsApp Web to load
                    }
                    
                    // Automatically try to send the message when page loads
                    window.onload = function() {
                        sendWhatsAppMessage();
                    };
                </script>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include 'footer.php'; ?>