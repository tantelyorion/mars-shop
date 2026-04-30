<?php
// admin/payments.php - Gestion des paiements (suite)
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Validation d'une transaction Mobile Money
if (isset($_GET['verify']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE mobile_money_transactions 
            SET status = 'verified', verified_by = ?, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        // Récupérer l'order_id
        $stmt = $conn->prepare("SELECT order_id FROM mobile_money_transactions WHERE id = ?");
        $stmt->execute([$id]);
        $tx = $stmt->fetch();
        
        if ($tx) {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?");
            $stmt->execute([$tx['order_id']]);
            
            $stmt = $conn->prepare("UPDATE payments SET status = 'success' WHERE order_id = ?");
            $stmt->execute([$tx['order_id']]);
        }
        setFlashMessage('success', 'Transaction Mobile Money validée');
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE mobile_money_transactions SET status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        setFlashMessage('warning', 'Transaction Mobile Money rejetée');
    }
    header('Location: payments.php');
    exit();
}

// Récupérer les transactions Mobile Money en attente
$stmt = $conn->prepare("
    SELECT mmt.*, o.order_number, u.username, u.email 
    FROM mobile_money_transactions mmt
    JOIN orders o ON mmt.order_id = o.id
    JOIN users u ON mmt.user_id = u.id
    WHERE mmt.status = 'pending'
    ORDER BY mmt.created_at DESC
");
$stmt->execute();
$pending_transactions = $stmt->fetchAll();

// Récupérer tous les paiements
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

$operators = [
    'airtel' => 'Airtel Money',
    'mvola' => 'Mvola',
    'orange' => 'Orange Money'
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
        
        .btn-approve {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.75rem;
            margin-right: 5px;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.75rem;
        }
        
        .pending-row {
            background: rgba(245, 158, 11, 0.1);
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
                <p>Suivez et validez les paiements</p>
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
                    <h3>Transactions en attente</h3>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <!-- Transactions Mobile Money en attente -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-mobile-alt"></i> Transactions Mobile Money en attente</h3>
            </div>
            <div class="card-body">
                <?php if(count($pending_transactions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Opérateur</th>
                            <th>Montant</th>
                            <th>ID Transaction</th>
                            <th>Actions</th>
                        </table>
                    </thead>
                    <tbody>
                        <?php foreach($pending_transactions as $tx): ?>
                        <tr class="pending-row">
                            <td><?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td>
                            <td><?php echo $tx['order_number']; ?></td>
                            <td><?php echo clean($tx['username']); ?><br><small><?php echo $tx['email']; ?></small></td>
                            <td><?php echo $operators[$tx['operator']] ?? $tx['operator']; ?></td>
                            <td><?php echo formatPrice($tx['amount']); ?></td>
                            <td><code><?php echo $tx['transaction_id']; ?></code></td>
                            <td>
                                <a href="?verify=1&action=approve&id=<?php echo $tx['id']; ?>" class="btn-approve" onclick="return confirm('Valider cette transaction ?')">
                                    <i class="fas fa-check"></i> Valider
                                </a>
                                <a href="?verify=1&action=reject&id=<?php echo $tx['id']; ?>" class="btn-reject" onclick="return confirm('Rejeter cette transaction ?')">
                                    <i class="fas fa-times"></i> Rejeter
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 16px; color: #10b981;"></i>
                    <p>Aucune transaction en attente</p>
                </div>
                <?php endif; ?>
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
                            <td><?php echo str_replace('_', ' ', $payment['payment_method']); ?></td>
                            <td><?php echo formatPrice($payment['amount']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php echo $payment['status'] == 'success' ? 'Succès' : ($payment['status'] == 'pending' ? 'En attente' : 'Échoué'); ?>
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