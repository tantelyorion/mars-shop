<?php
// admin/order-detail.php - Détail d'une commande pour admin
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: orders.php');
    exit();
}

$conn = getConnection();

// Récupérer la commande
$stmt = $conn->prepare("
    SELECT o.*, u.username, u.email, u.phone 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Récupérer les articles
$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Récupérer le paiement
$stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ?");
$stmt->execute([$order_id]);
$payment = $stmt->fetch();

$statuses = [
    'pending' => 'En attente',
    'processing' => 'En traitement',
    'shipped' => 'Expédiée',
    'delivered' => 'Livrée',
    'cancelled' => 'Annulée'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail commande #<?php echo $order['order_number']; ?> - Administration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f14;
            color: #ffffff;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #c14432;
            text-decoration: none;
        }
        
        .card {
            background: #1a1a24;
            border: 1px solid #2a2a35;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #2a2a35;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .info-group {
            background: #2a2a35;
            border-radius: 12px;
            padding: 16px;
        }
        
        .info-group h4 {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #a0a0b0;
            margin-bottom: 8px;
        }
        
        .info-group p {
            font-size: 1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #2a2a35;
        }
        
        th {
            color: #a0a0b0;
            font-weight: 500;
        }
        
        .total-row {
            font-weight: bold;
            background: rgba(193, 68, 50, 0.1);
        }
        
        .status-select {
            padding: 8px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 8px;
            color: white;
        }
        
        .btn-update {
            background: #c14432;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
            }
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            table {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour aux commandes</a>
        
        <div class="card">
            <div class="card-header">
                <h2>Commande #<?php echo $order['order_number']; ?></h2>
                <form method="POST" action="update-order-status.php" style="display: flex; gap: 12px; align-items: center;">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <select name="status" class="status-select">
                        <?php foreach($statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $order['status'] == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-update">Mettre à jour</button>
                </form>
            </div>
            <div class="card-body">
                <div class="order-info-grid">
                    <div class="info-group">
                        <h4>Client</h4>
                        <p><strong><?php echo clean($order['username']); ?></strong></p>
                        <p><?php echo clean($order['email']); ?></p>
                        <p><?php echo clean($order['phone']); ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Date de commande</h4>
                        <p><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Adresse de livraison</h4>
                        <p><?php echo nl2br(clean($order['shipping_address'])); ?></p>
                    </div>
                    <div class="info-group">
                        <h4>Paiement</h4>
                        <p>Mode : <?php echo str_replace('_', ' ', $order['payment_method']); ?></p>
                        <p>Statut : <span class="status-badge <?php echo $order['payment_status']; ?>"><?php echo $order['payment_status'] == 'paid' ? 'Payé' : 'En attente'; ?></span></p>
                        <?php if($payment && $payment['transaction_id']): ?>
                        <p>Transaction : <?php echo $payment['transaction_id']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h3 style="margin-bottom: 16px;">Articles commandés</h3>
                <table>
                    <thead>
                        <tr><th>Produit</th><th>Prix unitaire</th><th>Quantité</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td><?php echo clean($item['name']); ?></td>
                            <td><?php echo formatPrice($item['price']); ?></td>
                            <td>x<?php echo $item['quantity']; ?></td>
                            <td><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;"><strong>Total</strong></td>
                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
const toggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('adminSidebar');
if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
}
</script>
</body>
</html>