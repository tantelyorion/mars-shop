<?php
// admin/product-add.php - Ajouter un produit
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $slug = !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($name);
    $description = clean($_POST['description']);
    $short_description = clean($_POST['short_description']);
    $price = (float)$_POST['price'];
    $compare_price = !empty($_POST['compare_price']) ? (float)$_POST['compare_price'] : null;
    $stock = (int)$_POST['stock'];
    $category = clean($_POST['category']);
    $tags = clean($_POST['tags']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name) || empty($price) || empty($category)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } else {
        // Vérifier si le slug existe déjà
        $stmt = $conn->prepare("SELECT id FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug = $slug . '-' . uniqid();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO products (name, slug, description, short_description, price, compare_price, stock, category, tags, is_featured, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$name, $slug, $description, $short_description, $price, $compare_price, $stock, $category, $tags, $is_featured, $is_active])) {
            setFlashMessage('success', 'Produit ajouté avec succès');
            header('Location: products.php');
            exit();
        } else {
            $error = 'Erreur lors de l\'ajout du produit';
        }
    }
}

// Récupérer les catégories existantes
$stmt = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$existing_categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
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
        }
        
        .card-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #c14432;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #ef4444;
        }
        
        .btn-submit {
            background: #c14432;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .btn-back {
            background: #2a2a35;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }
        
        .text-muted {
            color: #a0a0b0;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-main {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
            <a href="products.php" class="nav-item active"><i class="fas fa-box"></i> Produits</a>
            <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="payments.php" class="nav-item"><i class="fas fa-credit-card"></i> Paiements</a>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </aside>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Ajouter un produit</h1>
                <p>Créez un nouveau produit dans votre catalogue</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Informations produit</h3>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom du produit *</label>
                            <input type="text" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Slug (URL)</label>
                            <input type="text" name="slug" placeholder="laissez vide pour auto-génération">
                            <div class="text-muted">Identifiant unique pour l'URL du produit</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prix (€) *</label>
                            <input type="number" step="0.01" name="price" required value="<?php echo $_POST['price'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Prix barré (optionnel)</label>
                            <input type="number" step="0.01" name="compare_price" value="<?php echo $_POST['compare_price'] ?? ''; ?>">
                            <div class="text-muted">Affiche le prix original barré</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock *</label>
                            <input type="number" name="stock" required value="<?php echo $_POST['stock'] ?? 0; ?>">
                        </div>
                        <div class="form-group">
                            <label>Catégorie *</label>
                            <input type="text" name="category" list="categories" required value="<?php echo $_POST['category'] ?? ''; ?>">
                            <datalist id="categories">
                                <?php foreach($existing_categories as $cat): ?>
                                <option value="<?php echo clean($cat['category']); ?>">
                                <?php endforeach; ?>
                                <option value="Vêtements"><option value="Accessoires"><option value="Alimentation"><option value="Décoration"><option value="Jeux">
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tags (séparés par des virgules)</label>
                        <input type="text" name="tags" placeholder="ex: mars, espace, rover" value="<?php echo $_POST['tags'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description courte</label>
                        <textarea name="short_description" rows="2"><?php echo $_POST['short_description'] ?? ''; ?></textarea>
                        <div class="text-muted">Apparaît dans les listes de produits</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description complète</label>
                        <textarea name="description" rows="6"><?php echo $_POST['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_featured" id="is_featured" <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                            <label for="is_featured">Mettre en vedette</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" checked <?php echo isset($_POST['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active">Produit actif</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="products.php" class="btn-back">Annuler</a>
                        <button type="submit" class="btn-submit">Ajouter le produit</button>
                    </div>
                </form>
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