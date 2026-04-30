<?php
// admin/categories.php - Gestion des catégories
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Suppression d'une catégorie
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', 'Catégorie supprimée');
    header('Location: categories.php');
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $slug = !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($name);
    $description = clean($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        // Modification
        $id = (int)$_POST['category_id'];
        $stmt = $conn->prepare("
            UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ?, sort_order = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $slug, $description, $parent_id, $sort_order, $is_active, $id]);
        setFlashMessage('success', 'Catégorie modifiée');
    } else {
        // Ajout
        $stmt = $conn->prepare("
            INSERT INTO categories (name, slug, description, parent_id, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $slug, $description, $parent_id, $sort_order, $is_active]);
        setFlashMessage('success', 'Catégorie ajoutée');
    }
    header('Location: categories.php');
    exit();
}

// Récupération des catégories
$stmt = $conn->query("SELECT * FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

// Récupérer une catégorie pour édition
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_category = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories - Administration</title>
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
                <h1>Gestion des catégories</h1>
                <p>Organisez vos produits par catégories</p>
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
                <h3><?php echo $edit_category ? 'Modifier la catégorie' : 'Ajouter une catégorie'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom de la catégorie *</label>
                            <input type="text" name="name" required value="<?php echo $edit_category['name'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Slug (URL)</label>
                            <input type="text" name="slug" value="<?php echo $edit_category['slug'] ?? ''; ?>" placeholder="laissez vide pour auto-génération">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Catégorie parente</label>
                            <select name="parent_id">
                                <option value="">Aucune (catégorie principale)</option>
                                <?php foreach($categories as $cat): ?>
                                    <?php if(!$edit_category || $cat['id'] != $edit_category['id']): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_category['parent_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('--', $cat['parent_id'] ? 1 : 0) . clean($cat['name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ordre d'affichage</label>
                            <input type="number" name="sort_order" value="<?php echo $edit_category['sort_order'] ?? 0; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?php echo $edit_category['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($edit_category['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active">Catégorie active</label>
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> <?php echo $edit_category ? 'Modifier' : 'Ajouter'; ?> la catégorie
                        </button>
                        <?php if($edit_category): ?>
                        <a href="categories.php" class="btn-submit" style="background: #2a2a35; text-decoration: none; margin-left: 10px;">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Liste des catégories -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Liste des catégories</h3>
            </div>
            <div class="card-body">
                <?php if(count($categories) > 0): ?>
                </tr>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Slug</th>
                            <th>Parent</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><?php echo clean($cat['name']); ?></td>
                            <td><?php echo $cat['slug']; ?></td>
                            <td>
                                <?php
                                if($cat['parent_id']) {
                                    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                                    $stmt->execute([$cat['parent_id']]);
                                    $parent = $stmt->fetch();
                                    echo $parent ? clean($parent['name']) : '-';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo $cat['sort_order']; ?></td>
                            <td>
                                <?php if($cat['is_active']): ?>
                                <span class="badge-active">Actif</span>
                                <?php else: ?>
                                <span class="badge-inactive">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $cat['id']; ?>" class="btn-icon" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $cat['id']; ?>" class="btn-icon" onclick="return confirm('Supprimer cette catégorie ?')" title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucune catégorie trouvée</p>
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