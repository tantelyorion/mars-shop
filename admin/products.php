<?php
// admin/products.php - Gestion des produits
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Suppression d'un produit
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', 'Produit supprimé avec succès');
    header('Location: products.php');
    exit();
}

// Activation/Désactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: products.php');
    exit();
}

// Mise en vedette
if (isset($_GET['featured']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $value = (int)$_GET['featured'];
    $stmt = $conn->prepare("UPDATE products SET is_featured = ? WHERE id = ?");
    $stmt->execute([$value, $id]);
    header('Location: products.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Recherche
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Requête
$sql = "SELECT * FROM products WHERE 1=1";
$count_sql = "SELECT COUNT(*) as total FROM products WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Compter le total
$stmt = $conn->prepare($count_sql);
$count_params = array_slice($params, 0, count($params) - 2);
$stmt->execute($count_params);
$total_products = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_products / $per_page);

// Récupérer les catégories pour le filtre
$stmt = $conn->query("SELECT DISTINCT category FROM products ORDER BY category");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - Administration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        /* Styles spécifiques pour la gestion des produits */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .badge-featured {
            background: #f59e0b;
            color: #000;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .badge-inactive {
            background: #ef4444;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .badge-active {
            background: #10b981;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .stock-low {
            color: #f59e0b;
        }
        
        .stock-out {
            color: #ef4444;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-form input {
            flex: 1;
            max-width: 300px;
            padding: 10px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
        }
        
        .search-form button {
            padding: 10px 20px;
            background: #c14432;
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
        }
        
        .add-btn {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #2a2a35;
        }
        
        th {
            color: #a0a0b0;
            font-weight: 500;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #8b3a2b, #c14432);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image i {
            font-size: 1.2rem;
        }
        
        .btn-icon {
            background: none;
            border: none;
            color: #a0a0b0;
            cursor: pointer;
            font-size: 1rem;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: #2a2a35;
            color: white;
        }
        
        .btn-delete:hover {
            color: #ef4444;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Gestion des produits</h1>
                <p>Gérez votre catalogue de produits</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="toolbar">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Rechercher un produit..." value="<?php echo clean($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
            </form>
            <a href="product-add.php" class="add-btn">
                <i class="fas fa-plus"></i> Ajouter un produit
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <?php if(count($products) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $product): ?>
                        <tr>
                            <td>
                                <div class="product-image">
                                    <i class="fas fa-box-open"></i>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo clean($product['name']); ?></strong><br>
                                <small><?php echo clean($product['slug']); ?></small>
                                <?php if($product['is_featured']): ?>
                                <span class="badge-featured">En vedette</span>
                                <?php endif; ?>
                             </td>
                            <td><?php echo clean($product['category']); ?></td>
                            <td><?php echo formatPrice($product['price']); ?></td>
                            <td>
                                <?php if($product['stock'] <= 0): ?>
                                <span class="stock-out"><?php echo $product['stock']; ?></span>
                                <?php elseif($product['stock'] < 10): ?>
                                <span class="stock-low"><?php echo $product['stock']; ?></span>
                                <?php else: ?>
                                <?php echo $product['stock']; ?>
                                <?php endif; ?>
                             </td>
                            <td>
                                <?php if($product['is_active']): ?>
                                <span class="badge-active">Actif</span>
                                <?php else: ?>
                                <span class="badge-inactive">Inactif</span>
                                <?php endif; ?>
                             </td>
                            <td>
                                <div class="actions">
                                    <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn-icon" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?toggle=1&id=<?php echo $product['id']; ?>" class="btn-icon" title="<?php echo $product['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                        <i class="fas <?php echo $product['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                    </a>
                                    <a href="?featured=<?php echo $product['is_featured'] ? 0 : 1; ?>&id=<?php echo $product['id']; ?>" class="btn-icon" title="<?php echo $product['is_featured'] ? 'Retirer des vedettes' : 'Mettre en vedette'; ?>">
                                        <i class="fas <?php echo $product['is_featured'] ? 'fa-star' : 'fa-star-o'; ?>"></i>
                                    </a>
                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Supprimer ce produit ?')" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                             </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                        <?php elseif($i == 1 || $i == $total_pages || ($i >= $page-2 && $i <= $page+2)): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        <?php elseif($i == $page-3 || $i == $page+3): ?>
                        <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucun produit trouvé</p>
                    <a href="product-add.php" class="add-btn" style="margin-top: 16px;">Ajouter un produit</a>
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