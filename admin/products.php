<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin();

$conn = getConnection();

// Handle product deletion
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: products.php?msg=deleted');
    exit();
}

// Handle product add/edit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category = $_POST['category'];
    
    if(isset($_POST['product_id']) && $_POST['product_id'] > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, category=? WHERE id=?");
        $stmt->execute([$name, $description, $price, $stock, $category, $_POST['product_id']]);
        $msg = "Product updated successfully!";
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $stock, $category]);
        $msg = "Product added successfully!";
    }
}

// Get product for editing
$edit_product = null;
if(isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_product = $stmt->fetch();
}

// Get all products
$products = $conn->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Mars Shop Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .admin-nav { background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
        .admin-nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; margin: 0 0.5rem; border-radius: 5px; }
        .admin-nav a:hover, .admin-nav a.active { background: var(--mars-red); }
        .product-form { background: rgba(255,255,255,0.1); padding: 2rem; border-radius: 15px; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .btn-small { padding: 5px 10px; font-size: 0.8rem; margin: 0 2px; display: inline-block; }
        .btn-danger { background: #f44336; }
        .btn-warning { background: #ff9800; }
        .alert-success { background: #4caf50; padding: 10px; border-radius: 5px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <a href="../index.php"><i class="fas fa-planet-ringed"></i><span>Mars Shop Admin</span></a>
            </div>
            <nav><ul><li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></nav>
        </div>
    </header>
    
    <div class="admin-container">
        <h1><i class="fas fa-box"></i> Manage Products</h1>
        
        <div class="admin-nav">
            <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="products.php" class="active"><i class="fas fa-box"></i> Products</a>
            <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
        </div>
        
        <?php if(isset($msg)): ?>
            <div class="alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div class="alert-success">Product deleted successfully!</div>
        <?php endif; ?>
        
        <div class="product-form">
            <h2><?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?></h2>
            <form method="POST">
                <?php if($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" required><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Price ($)</label>
                    <input type="number" step="0.01" name="price" value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="stock" value="<?php echo $edit_product ? $edit_product['stock'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Clothing" <?php echo ($edit_product && $edit_product['category'] == 'Clothing') ? 'selected' : ''; ?>>Clothing</option>
                        <option value="Accessories" <?php echo ($edit_product && $edit_product['category'] == 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                        <option value="Food" <?php echo ($edit_product && $edit_product['category'] == 'Food') ? 'selected' : ''; ?>>Food</option>
                        <option value="Decor" <?php echo ($edit_product && $edit_product['category'] == 'Decor') ? 'selected' : ''; ?>>Decor</option>
                        <option value="Toys" <?php echo ($edit_product && $edit_product['category'] == 'Toys') ? 'selected' : ''; ?>>Toys</option>
                    </select>
                </div>
                
                <button type="submit" class="btn"><?php echo $edit_product ? 'Update Product' : 'Add Product'; ?></button>
                <?php if($edit_product): ?>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 15px;">
            <h2>All Products</h2>
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Category</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><?php echo $product['category']; ?></td>
                            <td>
                                <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn-small btn-warning">Edit</a>
                                <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>