<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = getConnection();

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.username, u.email, u.full_name, u.phone, u.address,
           p.status as payment_status, p.transaction_id, p.payment_method
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if(!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Mars Shop Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: var(--mars-light); }
        .info-card { background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .status-badge { padding: 5px 10px; border-radius: 5px; display: inline-block; }
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
        <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        
        <h1><i class="fas fa-receipt"></i> Order Details</h1>
        
        <div class="grid-2">
            <div class="info-card">
                <h3>Order Information</h3>
                <p><strong>Order Number:</strong> <?php echo $order['order_number']; ?></p>
                <p><strong>Order Date:</strong> <?php echo date('F d, Y H:i', strtotime($order['created_at'])); ?></p>
                <p><strong>Order Status:</strong> 
                    <span class="status-badge" style="background: 
                        <?php echo $order['status'] == 'completed' ? '#4caf50' : 
                            ($order['status'] == 'processing' ? '#2196f3' : 
                            ($order['status'] == 'cancelled' ? '#f44336' : '#ff9800')); ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </p>
                <p><strong>Payment Status:</strong> 
                    <span class="status-badge" style="background: <?php echo $order['payment_status'] == 'success' ? '#4caf50' : '#ff9800'; ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </p>
                <?php if($order['transaction_id']): ?>
                    <p><strong>Transaction ID:</strong> <?php echo $order['transaction_id']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['full_name']); ?></p>
                <p><strong>Username:</strong> <?php echo $order['username']; ?></p>
                <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
                <p><strong>Phone:</strong> <?php echo $order['phone'] ?: 'N/A'; ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($order['address'] ?: $order['shipping_address'])); ?></p>
            </div>
        </div>
        
        <div class="info-card">
            <h3>Order Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>