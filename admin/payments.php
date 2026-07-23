<?php
// admin/payments.php - Gestion des paiements (CB et PayPal uniquement)
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Récupérer tous les paiements (historique)
$stmt = $conn->prepare("
    SELECT p.*, o.order_number, u.username 
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 50
");
$stmt->execute();
$payments = $stmt->fetchAll();

// Calcul des totaux
$stmt = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'success'");
$total_success = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
$pending_count = $stmt->fetch()['count'] ?? 0;

// Noms des méthodes de paiement
$method_names = [
    'credit_card' => 'Carte bancaire',
    'paypal' => 'PayPal'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements - Administration</title>
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
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a35;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        
        .page-title p {
            color: #a0a0b0;
            font-size: 0.85rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a24;
            border: 1px solid #2a2a35;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-info h3 {
            font-size: 0.8rem;
            color: #a0a0b0;
            margin-bottom: 8px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
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
        
        .card-header h3 {
            font-size: 1rem;
        }
        
        .card-body {
            padding: 20px;
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
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-success {
            background: #10b981;
            color: white;
        }
        
        .status-pending {
            background: #f59e0b;
            color: #000;
        }
        
        .status-failed {
            background: #ef4444;
            color: white;
        }
        
        .text-muted {
            color: #a0a0b0;
        }
        
        .text-center {
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
            }
            table {
                font-size: 0.75rem;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Gestion des paiements</h1>
                <p>Suivez l'historique des paiements par carte bancaire et PayPal</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total encaissé</h3>
                    <div class="stat-number"><?php echo formatPrice($total_success); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Paiements en attente</h3>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <!-- Historique des paiements -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Historique des paiements</h3>
            </div>
            <div class="card-body">
                <?php if(count($payments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Mode</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Transaction</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                            <td><?php echo $payment['order_number']; ?></td>
                            <td><?php echo clean($payment['username']); ?></td>
                            <td><?php echo $method_names[$payment['payment_method']] ?? $payment['payment_method']; ?></td>
                            <td><?php echo formatPrice($payment['amount']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php 
                                    echo $payment['status'] == 'success' ? 'Succès' : 
                                        ($payment['status'] == 'pending' ? 'En attente' : 'Échoué'); 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $payment['transaction_id']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-credit-card" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucun paiement enregistré</p>
                </div>
                <?php endif; ?>
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