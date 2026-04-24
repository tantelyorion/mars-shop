<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$total_users = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM products");
$total_products = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
$total_revenue = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pending_orders = $stmt->fetch()['total'];

// Get recent orders
$stmt = $conn->query("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll();

// Get low stock products
$stmt = $conn->query("SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5");
$low_stock = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mars Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        .admin-nav {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.5rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--mars-red);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <a href="../index.php">
                    <i class="fas fa-planet-ringed"></i>
                    <span>Mars Shop Admin</span>
                </a>
            </div>
            <nav>
                <ul>
                    <li><a href="../index.php"><i class="fas fa-home"></i> Site Home</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="admin-container">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        
        <div class="admin-nav">
            <a href="index.php" class="active"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="products.php"><i class="fas fa-box"></i> Products</a>
            <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div>Total Users</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-box"></i>
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div>Total Products</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div>Total Orders</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div>Total Revenue</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-number"><?php echo $pending_orders; ?></div>
                <div>Pending Orders</div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px;">
                <h2><i class="fas fa-clock"></i> Recent Orders</h2>
                <?php if(count($recent_orders) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
                                    <td><?php echo $order['username']; ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo ucfirst($order['status']); ?></td>
                                    <td><?php echo date('m/d', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No orders yet</p>
                <?php endif; ?>
            </div>
            
            <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px;">
                <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h2>
                <?php if(count($low_stock) > 0): ?>
                    <table>
                        <thead>
                            <tr><th>Product</th><th>Stock</th><th>Price</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($low_stock as $product): ?>
                                <tr>
                                    <td><?php echo $product['name']; ?></td>
                                    <td style="color: #f44336;"><?php echo $product['stock']; ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><a href="products.php?edit=<?php echo $product['id']; ?>" class="btn-small">Update</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>All products have sufficient stock</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <footer style="text-align: center; padding: 2rem; margin-top: 2rem;">
        <p>&copy; 2024 Mars Shop Admin Panel</p>
    </footer>
</body>
</html>