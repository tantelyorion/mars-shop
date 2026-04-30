<?php
// admin/orders.php - Gestion des commandes
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Mise à jour du statut d'une commande
if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $status = clean($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    setFlashMessage('success', 'Statut de la commande mis à jour');
    header('Location: orders.php');
    exit();
}

// Filtres
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Requête
$sql = "SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $count_sql .= " AND status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.username LIKE ?)";
    $count_sql .= " AND (order_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    if (!$status_filter) $params[] = $search_param;
}

$sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Compter le total
$stmt = $conn->prepare($count_sql);
$count_params = array_slice($params, 0, count($params) - 2);
$stmt->execute($count_params);
$total_orders = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_orders / $per_page);

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
    <title>Commandes - Administration</title>
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
        
        .admin-sidebar {
            width: 280px;
            background: #0a0a0e;
            border-right: 1px solid #2a2a35;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
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
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .filters {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .filter-select, .search-input {
            padding: 8px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
        }
        
        .search-form {
            display: flex;
            gap: 8px;
        }
        
        .search-form button {
            padding: 8px 16px;
            background: #c14432;
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
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
        
        .status-pending { background: #f59e0b; color: #000; }
        .status-processing { background: #3b82f6; color: #fff; }
        .status-shipped { background: #8b5cf6; color: #fff; }
        .status-delivered { background: #10b981; color: #fff; }
        .status-cancelled { background: #ef4444; color: #fff; }
        
        .status-select {
            padding: 4px 8px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 6px;
            color: white;
            font-size: 0.75rem;
        }
        
        .btn-update {
            background: none;
            border: none;
            color: #c14432;
            cursor: pointer;
            font-size: 0.75rem;
        }
        
        .btn-view {
            color: #c14432;
            text-decoration: none;
            font-size: 0.8rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            background: #2a2a35;
            border-radius: 8px;
            color: white;
            text-decoration: none;
        }
        
        .pagination .active {
            background: #c14432;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-main {
                margin-left: 0;
            }
            table {
                font-size: 0.8rem;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-planet-ringed"></i>
                Mars<span>Admin</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
            <a href="products.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            <a href="orders.php" class="nav-item active"><i class="fas fa-shopping-cart"></i> Commandes</a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="payments.php" class="nav-item"><i class="fas fa-credit-card"></i> Paiements</a>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </aside>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Gestion des commandes</h1>
                <p>Suivez et gérez les commandes de vos clients</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="filters">
                    <select id="statusFilter" class="filter-select">
                        <option value="">Tous les statuts</option>
                        <?php foreach($statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <form method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="N° commande ou client" value="<?php echo clean($search); ?>">
                        <?php if($status_filter): ?>
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <?php endif; ?>
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if(count($orders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $order): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <tr>
                                <td><?php echo $order['order_number']; ?></td>
                                <td><?php echo clean($order['username']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                <td><?php echo formatPrice($order['total_amount']); ?></td>
                                <td>
                                    <select name="status" class="status-select">
                                        <?php foreach($statuses as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $order['status'] == $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="submit" name="update_status" class="btn-update"><i class="fas fa-save"></i> Mettre à jour</button>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> Détail</a>
                                </td>
                            </tr>
                        </form>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                        <?php elseif($i == 1 || $i == $total_pages || ($i >= $page-2 && $i <= $page+2)): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucune commande trouvée</p>
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

// Redirection par filtre
document.getElementById('statusFilter')?.addEventListener('change', function() {
    const url = new URL(window.location.href);
    if (this.value) {
        url.searchParams.set('status', this.value);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
});
</script>
</body>
</html>