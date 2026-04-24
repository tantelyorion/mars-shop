<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Handle order status update
if(isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    $msg = "Order status updated!";
}

// Get all orders with user info
$orders = $conn->query("
    SELECT o.*, u.username, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Mars Shop Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .admin-nav { background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .admin-nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; margin: 0 0.5rem; border-radius: 5px; }
        .admin-nav a:hover, .admin-nav a.active { background: var(--mars-red); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .status-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; display: inline-block; }
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
        <h1><i class="fas fa-shopping-cart"></i> Manage Orders</h1>
        
        <div class="admin-nav">
            <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="products.php"><i class="fas fa-box"></i> Products</a>
            <a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
        </div>
        
        <?php if(isset($msg)): ?>
            <div class="alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px; overflow-x: auto;">
            <h2>All Orders</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order #</th><th>Customer</th><th>Email</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['order_number']; ?></td>
                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                            <td><?php echo $order['email']; ?></td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge" style="background: 
                                    <?php echo $order['status'] == 'completed' ? '#4caf50' : 
                                        ($order['status'] == 'processing' ? '#2196f3' : 
                                        ($order['status'] == 'cancelled' ? '#f44336' : '#ff9800')); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>