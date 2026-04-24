<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Handle user deletion
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if($id != $_SESSION['user_id']) { // Prevent self-deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "User deleted successfully!";
    } else {
        $msg = "You cannot delete your own account!";
    }
}

// Handle role update
if(isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$role, $user_id]);
    $msg = "User role updated!";
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Mars Shop Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .admin-nav { background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .admin-nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; margin: 0 0.5rem; border-radius: 5px; }
        .admin-nav a:hover, .admin-nav a.active { background: var(--mars-red); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .role-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; display: inline-block; }
        .btn-small { padding: 5px 10px; font-size: 0.8rem; }
        .alert-success { background: #4caf50; padding: 10px; border-radius: 5px; margin-bottom: 1rem; }
        select { padding: 5px; border-radius: 5px; background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo"><a href="../index.php"><i class="fas fa-planet-ringed"></i><span>Mars Shop Admin</span></a></div>
            <nav><ul><li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>
        </div>
    </header>
    
    <div class="admin-container">
        <h1><i class="fas fa-users"></i> Manage Users</h1>
        
        <div class="admin-nav">
            <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="products.php"><i class="fas fa-box"></i> Products</a>
            <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="users.php" class="active"><i class="fas fa-users"></i> Users</a>
        </div>
        
        <?php if(isset($msg)): ?>
            <div class="alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px; overflow-x: auto;">
            <h2>All Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Joined</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td>
                                <span class="role-badge" style="background: <?php echo $user['role'] == 'admin' ? '#f44336' : '#4caf50'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if($user['role'] != 'admin'): ?>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" onchange="this.form.submit()">
                                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="update_role" value="1">
                                    </form>
                                    <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                <?php else: ?>
                                    <span>Admin Account</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>