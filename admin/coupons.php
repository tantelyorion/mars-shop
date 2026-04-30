<?php
// admin/coupons.php - Gestion des coupons de réduction
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Suppression d'un coupon
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', 'Coupon supprimé');
    header('Location: coupons.php');
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(clean($_POST['code']));
    $description = clean($_POST['description']);
    $discount_type = clean($_POST['discount_type']);
    $discount_value = (float)$_POST['discount_value'];
    $min_order_amount = (float)$_POST['min_order_amount'];
    $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
    $valid_from = clean($_POST['valid_from']);
    $valid_to = clean($_POST['valid_to']);
    $usage_limit = (int)$_POST['usage_limit'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (isset($_POST['coupon_id']) && !empty($_POST['coupon_id'])) {
        // Modification
        $id = (int)$_POST['coupon_id'];
        $stmt = $conn->prepare("
            UPDATE coupons SET 
                code = ?, description = ?, discount_type = ?, discount_value = ?,
                min_order_amount = ?, max_discount = ?, valid_from = ?, valid_to = ?,
                usage_limit = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_amount, $max_discount, $valid_from, $valid_to, $usage_limit, $is_active, $id]);
        setFlashMessage('success', 'Coupon modifié');
    } else {
        // Ajout
        $stmt = $conn->prepare("
            INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, max_discount, valid_from, valid_to, usage_limit, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $description, $discount_type, $discount_value, $min_order_amount, $max_discount, $valid_from, $valid_to, $usage_limit, $is_active]);
        setFlashMessage('success', 'Coupon ajouté');
    }
    header('Location: coupons.php');
    exit();
}

// Récupération des coupons
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$sql = "SELECT * FROM coupons WHERE 1=1";
if ($search) {
    $sql .= " AND (code LIKE ? OR description LIKE ?)";
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($search) {
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param]);
} else {
    $stmt->execute();
}
$coupons = $stmt->fetchAll();

// Récupérer un coupon pour édition
$edit_coupon = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_coupon = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - Administration</title>
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            color: #a0a0b0;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 8px;
            color: white;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-submit {
            background: #c14432;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
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
        
        .badge-active {
            background: #10b981;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .badge-inactive {
            background: #ef4444;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .btn-icon {
            background: none;
            border: none;
            color: #a0a0b0;
            cursor: pointer;
            margin: 0 4px;
        }
        
        .btn-icon:hover {
            color: #c14432;
        }
        
        .search-form {
            display: flex;
            gap: 8px;
        }
        
        .search-input {
            padding: 8px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 8px;
            color: white;
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
        <div class="admin-header">
            <div class="page-title">
                <h1>Gestion des coupons</h1>
                <p>Créez et gérez vos codes promotionnels</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <!-- Formulaire d'ajout/modification -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo $edit_coupon ? 'Modifier le coupon' : 'Ajouter un coupon'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if($edit_coupon): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo $edit_coupon['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Code promo *</label>
                            <input type="text" name="code" required value="<?php echo $edit_coupon['code'] ?? ''; ?>" placeholder="EX: BIENVENUE10">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" value="<?php echo $edit_coupon['description'] ?? ''; ?>" placeholder="Description du coupon">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Type de réduction *</label>
                            <select name="discount_type" required>
                                <option value="percentage" <?php echo ($edit_coupon['discount_type'] ?? '') == 'percentage' ? 'selected' : ''; ?>>Pourcentage (%)</option>
                                <option value="fixed" <?php echo ($edit_coupon['discount_type'] ?? '') == 'fixed' ? 'selected' : ''; ?>>Montant fixe (€)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Valeur de réduction *</label>
                            <input type="number" step="0.01" name="discount_value" required value="<?php echo $edit_coupon['discount_value'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Montant minimum d'achat</label>
                            <input type="number" step="0.01" name="min_order_amount" value="<?php echo $edit_coupon['min_order_amount'] ?? 0; ?>">
                        </div>
                        <div class="form-group">
                            <label>Réduction maximale (pourcentage uniquement)</label>
                            <input type="number" step="0.01" name="max_discount" value="<?php echo $edit_coupon['max_discount'] ?? ''; ?>" placeholder="Laissez vide pour illimité">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" name="valid_from" required value="<?php echo $edit_coupon['valid_from'] ?? date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date de fin *</label>
                            <input type="date" name="valid_to" required value="<?php echo $edit_coupon['valid_to'] ?? date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre d'utilisations maximum</label>
                            <input type="number" name="usage_limit" value="<?php echo $edit_coupon['usage_limit'] ?? 100; ?>">
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($edit_coupon['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active">Coupon actif</label>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> <?php echo $edit_coupon ? 'Modifier' : 'Ajouter'; ?> le coupon
                        </button>
                        <?php if($edit_coupon): ?>
                        <a href="coupons.php" class="btn-submit" style="background: #2a2a35; text-decoration: none; margin-left: 10px;">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Liste des coupons -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-ticket-alt"></i> Liste des coupons</h3>
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Rechercher..." value="<?php echo clean($search); ?>">
                    <button type="submit" style="background:none;border:none;color:#c14432;"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body">
                <?php if(count($coupons) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Réduction</th>
                            <th>Min. d'achat</th>
                            <th>Valable du</th>
                            <th>Utilisé</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($coupons as $coupon): ?>
                        <tr>
                            <td><strong><?php echo $coupon['code']; ?></strong></td>
                            <td><?php echo clean($coupon['description']); ?></td>
                            <td>
                                <?php if($coupon['discount_type'] == 'percentage'): ?>
                                <?php echo $coupon['discount_value']; ?>%
                                <?php else: ?>
                                <?php echo formatPrice($coupon['discount_value']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $coupon['min_order_amount'] > 0 ? formatPrice($coupon['min_order_amount']) : 'Aucun'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($coupon['valid_from'])); ?> <br>
                            <small>au <?php echo date('d/m/Y', strtotime($coupon['valid_to'])); ?></small>
                            </td>
                            <td><?php echo $coupon['used_count']; ?> / <?php echo $coupon['usage_limit']; ?></td>
                            <td>
                                <?php if($coupon['is_active'] && strtotime($coupon['valid_to']) >= time()): ?>
                                <span class="badge-active">Actif</span>
                                <?php else: ?>
                                <span class="badge-inactive">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $coupon['id']; ?>" class="btn-icon" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $coupon['id']; ?>" class="btn-icon" onclick="return confirm('Supprimer ce coupon ?')" title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-ticket-alt" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucun coupon trouvé</p>
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