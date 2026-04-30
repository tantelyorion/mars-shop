<?php
// admin/users.php - Gestion des utilisateurs
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();

// Mise à jour du rôle
if (isset($_POST['update_role']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    $role = clean($_POST['role']);
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$role, $user_id]);
    setFlashMessage('success', 'Rôle mis à jour');
    header('Location: users.php');
    exit();
}

// Suppression d'un utilisateur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Ne pas supprimer l'admin courant
    if ($id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Utilisateur supprimé');
    } else {
        setFlashMessage('error', 'Vous ne pouvez pas supprimer votre propre compte');
    }
    header('Location: users.php');
    exit();
}

// Activation/Désactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: users.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? clean($_GET['search']) : '';

$sql = "SELECT * FROM users WHERE role != 'admin'";
$count_sql = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
$params = [];

if ($search) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $count_sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stmt = $conn->prepare($count_sql);
$count_params = array_slice($params, 0, count($params) - 2);
$stmt->execute($count_params);
$total_users = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_users / $per_page);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs - Administration</title>
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
        
        .search-form {
            display: flex;
            gap: 8px;
        }
        
        .search-input {
            padding: 8px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
            width: 250px;
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
        
        .role-select {
            padding: 4px 8px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 6px;
            color: white;
        }
        
        .btn-update {
            background: none;
            border: none;
            color: #c14432;
            cursor: pointer;
        }
        
        .btn-icon {
            background: none;
            border: none;
            color: #a0a0b0;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            margin: 0 4px;
        }
        
        .btn-icon:hover {
            background: #2a2a35;
        }
        
        .btn-delete:hover {
            color: #ef4444;
        }
        
        .badge-active {
            background: #10b981;
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
        
        .badge-admin {
            background: #c14432;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
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
            .admin-main {
                margin-left: 0;
            }
            table {
                font-size: 0.75rem;
            }
            th, td {
                padding: 6px;
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
                <h1>Gestion des utilisateurs</h1>
                <p>Gérez les comptes clients de votre boutique</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Nom, email..." value="<?php echo clean($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
                </form>
            </div>
            <div class="card-body">
                <?php if(count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Inscription</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo clean($user['username']); ?><br>
                                    <small><?php echo clean($user['full_name']); ?></small>
                                </td>
                                <td><?php echo clean($user['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <select name="role" class="role-select">
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Client</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn-update"><i class="fas fa-save"></i></button>
                                </td>
                                <td>
                                    <?php if($user['is_active']): ?>
                                    <span class="badge-active">Actif</span>
                                    <?php else: ?>
                                    <span class="badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?toggle=1&id=<?php echo $user['id']; ?>" class="btn-icon" title="<?php echo $user['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                        <i class="fas <?php echo $user['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                    </a>
                                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Supprimer cet utilisateur ?')" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </form>
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
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center text-muted" style="padding: 40px;">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>Aucun utilisateur trouvé</p>
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