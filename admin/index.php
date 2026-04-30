<?php
// admin/index.php - Tableau de bord administrateur
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Statistiques générales
$stats = [];

// Nombre total de produits
$stmt = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['products'] = $stmt->fetch()['total'];

// Nombre de produits en stock faible (<10)
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 10 AND stock > 0");
$stats['low_stock'] = $stmt->fetch()['total'];

// Nombre de produits en rupture
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock = 0");
$stats['out_stock'] = $stmt->fetch()['total'];

// Nombre total d'utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['users'] = $stmt->fetch()['total'];

// Nombre de commandes
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['orders'] = $stmt->fetch()['total'];

// Commandes en attente
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$stats['pending_orders'] = $stmt->fetch()['total'];

// Chiffre d'affaires total
$stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled' AND payment_status = 'paid'");
$stats['revenue'] = $stmt->fetch()['total'] ?? 0;

// Commandes par mois (pour graphique)
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM orders 
    WHERE status != 'cancelled'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthly_orders = $stmt->fetchAll();
$monthly_orders = array_reverse($monthly_orders);

// Commandes récentes
$stmt = $conn->prepare("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Meilleurs produits
$stmt = $conn->query("
    SELECT p.id, p.name, p.price, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products = $stmt->fetchAll();

// Derniers utilisateurs inscrits
$stmt = $conn->query("
    SELECT id, username, email, full_name, created_at 
    FROM users 
    WHERE role = 'user'
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration Mars Shop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Layout Admin */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: 280px;
            background: #0a0a0e;
            border-right: 1px solid #2a2a35;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #2a2a35;
        }

        .sidebar-header .logo {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .sidebar-header .logo span {
            color: #c14432;
        }

        .sidebar-nav {
            padding: 20px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #a0a0b0;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(193, 68, 50, 0.15);
            color: #c14432;
        }

        .nav-item i {
            width: 22px;
            font-size: 1.1rem;
        }

        .nav-group {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #2a2a35;
        }

        .nav-group-title {
            padding: 8px 16px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 1px;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }

        /* Header Top */
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

        .admin-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #c14432, #e8755a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #c14432;
        }

        .stat-info h3 {
            font-size: 0.8rem;
            color: #a0a0b0;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(193, 68, 50, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #c14432;
        }

        /* Row Grid */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        /* Cards */
        .card {
            background: #1a1a24;
            border: 1px solid #2a2a35;
            border-radius: 16px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #2a2a35;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #2a2a35;
        }

        .data-table th {
            color: #a0a0b0;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-pending { background: #f59e0b; color: #000; }
        .status-processing { background: #3b82f6; color: #fff; }
        .status-shipped { background: #8b5cf6; color: #fff; }
        .status-delivered { background: #10b981; color: #fff; }
        .status-cancelled { background: #ef4444; color: #fff; }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #c14432;
            color: white;
        }

        .btn-secondary {
            background: #2a2a35;
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #2a2a35;
            color: white;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #a0a0b0;
        }

        /* Chart */
        .chart-container {
            max-height: 300px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Mobile menu toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-planet-ringed"></i>
                Mars<span>Admin</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="products.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            
            <div class="nav-group">
                <div class="nav-group-title">Gestion financière</div>
                <a href="payments.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Paiements</span>
                </a>
                <a href="payment-methods.php" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Méthodes de paiement</span>
                </a>
                <a href="mobile-money-accounts.php" class="nav-item">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Comptes Mobile Money</span>
                </a>
            </div>
            
            <div class="nav-group">
                <div class="nav-group-title">Marketing</div>
                <a href="coupons.php" class="nav-item">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Coupons</span>
                </a>
                <a href="categories.php" class="nav-item">
                    <i class="fas fa-tags"></i>
                    <span>Catégories</span>
                </a>
            </div>
            
            <div class="nav-group">
                <div class="nav-group-title">Configuration</div>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Tableau de bord</h1>
                <p>Bienvenue, <?php echo clean($_SESSION['username']); ?> !</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Chiffre d'affaires</h3>
                    <div class="stat-number"><?php echo formatPrice($stats['revenue']); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-euro-sign"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Commandes</h3>
                    <div class="stat-number"><?php echo $stats['orders']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Commandes en attente</h3>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Produits</h3>
                    <div class="stat-number"><?php echo $stats['products']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Stock faible</h3>
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['low_stock']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Clients</h3>
                    <div class="stat-number"><?php echo $stats['users']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <!-- Graphique et Top Produits -->
        <div class="row">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Évolution des ventes</h3>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-fire"></i> Meilleurs produits</h3>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr><th>Produit</th><th>Prix</th><th>Vendus</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_products as $product): ?>
                            <tr>
                                <td><?php echo clean($product['name']); ?></td>
                                <td><?php echo formatPrice($product['price']); ?></td>
                                <td><?php echo $product['total_sold']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($top_products)): ?>
                            <tr><td colspan="3" class="text-center text-muted">Aucune vente</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Commandes récentes -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Commandes récentes</h3>
                <a href="orders.php" class="btn-sm btn-outline">Voir toutes</a>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr><th>N° commande</th><th>Client</th><th>Date</th><th>Montant</th><th>Statut</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo $order['order_number']; ?></td>
                            <td><?php echo clean($order['username']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                            <td><?php echo formatPrice($order['total_amount']); ?></td>
                            <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-sm btn-secondary">Voir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Derniers utilisateurs -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Derniers inscrits</h3>
                <a href="users.php" class="btn-sm btn-outline">Voir tous</a>
            </div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr><th>Nom</th><th>Email</th><th>Date d'inscription</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td><?php echo clean($user['username']); ?> <?php echo clean($user['full_name']); ?></td>
                            <td><?php echo clean($user['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td><a href="users.php?edit=<?php echo $user['id']; ?>" class="btn-sm btn-secondary">Modifier</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
// Graphique des ventes
<?php if(!empty($monthly_orders)): ?>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($monthly_orders, 'month')) . "'"; ?>],
        datasets: [{
            label: 'Chiffre d\'affaires (€)',
            data: [<?php echo implode(',', array_column($monthly_orders, 'total')); ?>],
            borderColor: '#c14432',
            backgroundColor: 'rgba(193, 68, 50, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { labels: { color: '#fff' } }
        },
        scales: {
            y: { ticks: { color: '#a0a0b0' }, grid: { color: '#2a2a35' } },
            x: { ticks: { color: '#a0a0b0' }, grid: { color: '#2a2a35' } }
        }
    }
});
<?php endif; ?>

// Mobile menu toggle
const toggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('adminSidebar');
if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}
</script>
</body>
</html>