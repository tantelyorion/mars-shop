<?php
// product.php - Page produit complète avec galerie d'images et avis
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: shop.php');
    exit();
}

$conn = getConnection();

// Récupérer le produit avec l'image principale
$stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: shop.php');
    exit();
}

// Incrémenter les vues
$stmt = $conn->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt->execute([$product_id]);

// Récupérer les images du produit
$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, is_primary DESC");
$stmt->execute([$product_id]);
$product_images = $stmt->fetchAll();

// Déterminer l'image principale
$main_image = null;
if (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])) {
    $main_image = 'uploads/products/' . $product['image'];
} elseif (!empty($product['primary_image']) && file_exists('uploads/products/' . $product['primary_image'])) {
    $main_image = 'uploads/products/' . $product['primary_image'];
} elseif (!empty($product_images)) {
    foreach ($product_images as $img) {
        if ($img['is_primary'] && file_exists('uploads/products/' . $img['image_path'])) {
            $main_image = 'uploads/products/' . $img['image_path'];
            break;
        }
    }
    if (!$main_image && !empty($product_images) && file_exists('uploads/products/' . $product_images[0]['image_path'])) {
        $main_image = 'uploads/products/' . $product_images[0]['image_path'];
    }
}

// Vérifier si dans wishlist
$in_wishlist = isInWishlist($product_id);

// Récupérer les avis
$stmt = $conn->prepare("
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Calculer la note moyenne
$avg_rating = 0;
if (count($reviews) > 0) {
    $total = array_sum(array_column($reviews, 'rating'));
    $avg_rating = $total / count($reviews);
}

// Distribution des notes
$rating_distribution = [];
for ($i = 5; $i >= 1; $i--) {
    $rating_distribution[$i] = 0;
}
foreach ($reviews as $review) {
    $rating_distribution[$review['rating']]++;
}

// Produits similaires
$stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    WHERE p.category = ? AND p.id != ? AND p.is_active = 1 
    LIMIT 4
");
$stmt->execute([$product['category'], $product_id]);
$related = $stmt->fetchAll();

// Ajout au panier (formulaire POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = max(1, min((int)$_POST['quantity'], $product['stock']));
    addToCart($product_id, $quantity);
    setFlashMessage('success', 'Produit ajouté au panier');
    header("Location: cart.php");
    exit();
}

// Ajout/retrait wishlist via GET
if (isset($_GET['wishlist'])) {
    addToWishlist($product_id);
    header("Location: product.php?id=$product_id");
    exit();
}
if (isset($_GET['remove_wishlist'])) {
    removeFromWishlist($product_id);
    header("Location: product.php?id=$product_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($product['name']); ?> - Mars Shop</title>
    <meta name="description" content="<?php echo clean(substr($product['description'], 0, 160)); ?>">
    <style>
        /* ============================================
           PRODUCT.PHP - STYLES COMPLETS
           ============================================ */
        
        :root {
            --primary: #c14432;
            --primary-dark: #8b3a2b;
            --primary-light: #e8755a;
            --dark: #0f0f14;
            --darker: #0a0a0e;
            --gray: #1a1a24;
            --gray-light: #2a2a35;
            --text: #ffffff;
            --text-muted: #a0a0b0;
            --border: #2a2a35;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        .product-page {
            padding: 30px 0;
        }
        
        /* Fil d'Ariane */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 30px;
            font-size: 0.85rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-light);
        }
        
        .breadcrumb i {
            font-size: 0.7rem;
        }
        
        .breadcrumb span {
            color: var(--primary-light);
        }
        
        /* Grille produit */
        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 50px;
        }
        
        /* Galerie */
        .product-gallery {
            position: sticky;
            top: 100px;
        }
        
        .product-main-image {
            background: var(--gray);
            border-radius: 20px;
            height: 450px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .product-main-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .product-main-image i {
            font-size: 8rem;
            color: rgba(255,255,255,0.2);
        }
        
        .product-thumbnails {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .product-thumb {
            width: 85px;
            height: 85px;
            background: var(--gray);
            border: 2px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .product-thumb:hover,
        .product-thumb.active {
            border-color: var(--primary);
        }
        
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Informations produit */
        .product-details h1 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .stars {
            display: flex;
            gap: 3px;
        }
        
        .stars i {
            font-size: 1rem;
        }
        
        .rating-count {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .product-price-large {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-light);
            margin: 15px 0;
        }
        
        .old-price {
            font-size: 1rem;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-left: 12px;
        }
        
        .discount-badge {
            background: var(--success);
            color: white;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 12px;
            vertical-align: middle;
        }
        
        .product-stock {
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        
        .in-stock {
            color: var(--success);
        }
        
        .out-of-stock {
            color: var(--error);
        }
        
        .stock-low {
            color: var(--warning);
            margin-left: 5px;
        }
        
        .product-description {
            margin: 20px 0;
        }
        
        .product-description h3 {
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .product-description p {
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        /* Formulaire ajout panier */
        .product-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            margin: 25px 0;
            flex-wrap: wrap;
        }
        
        .quantity-selector {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .quantity-selector label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .quantity-input {
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .quantity-input button {
            width: 44px;
            height: 48px;
            background: var(--gray-light);
            border: none;
            color: var(--text);
            cursor: pointer;
            font-size: 1.2rem;
            transition: background 0.2s;
        }
        
        .quantity-input button:hover {
            background: var(--primary);
        }
        
        .quantity-input input {
            width: 70px;
            height: 48px;
            text-align: center;
            background: transparent;
            border: none;
            color: var(--text);
            font-size: 1rem;
        }
        
        .quantity-input input:focus {
            outline: none;
        }
        
        .btn-large {
            padding: 12px 32px;
            font-size: 1rem;
        }
        
        .out-of-stock-message {
            background: rgba(239,68,68,0.1);
            border: 1px solid var(--error);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            color: var(--error);
            margin: 20px 0;
        }
        
        /* Wishlist */
        .product-actions-side {
            margin: 15px 0;
        }
        
        .wishlist-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
        }
        
        .wishlist-link.active {
            background: rgba(193,68,50,0.15);
            border-color: var(--primary);
            color: var(--primary-light);
        }
        
        /* Métadonnées */
        .product-meta {
            background: var(--gray);
            border-radius: 16px;
            padding: 18px;
            margin: 20px 0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .meta-item:last-child {
            border-bottom: none;
        }
        
        .meta-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-muted);
            min-width: 100px;
        }
        
        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .tag {
            background: var(--gray-light);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: var(--text);
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .tag:hover {
            background: var(--primary);
        }
        
        /* Partage */
        .share-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 0;
            border-top: 1px solid var(--border);
        }
        
        .share-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .share-link {
            width: 36px;
            height: 36px;
            background: var(--gray-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .share-link.facebook:hover { background: #1877f2; }
        .share-link.twitter:hover { background: #1da1f2; }
        .share-link.whatsapp:hover { background: #25d366; }
        
        /* Onglets */
        .product-tabs {
            background: var(--gray);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 50px;
            border: 1px solid var(--border);
        }
        
        .tabs-header {
            display: flex;
            gap: 5px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 24px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }
        
        .tab-btn:hover {
            color: var(--primary-light);
        }
        
        .tab-btn.active {
            color: var(--primary-light);
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Tableau spécifications */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .specs-table tr {
            border-bottom: 1px solid var(--border);
        }
        
        .specs-table th,
        .specs-table td {
            padding: 14px;
            text-align: left;
        }
        
        .specs-table th {
            width: 200px;
            font-weight: 600;
            color: var(--text-muted);
        }
        
        /* Avis clients */
        .reviews-summary {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        
        .avg-rating {
            text-align: center;
            padding: 20px;
            background: var(--gray-light);
            border-radius: 16px;
            min-width: 150px;
        }
        
        .avg-rating .rating-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--warning);
        }
        
        .avg-rating .stars-large {
            margin: 10px 0;
        }
        
        .rating-distribution {
            flex: 1;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .rating-star-label {
            width: 50px;
            font-size: 0.85rem;
        }
        
        .rating-bar {
            flex: 1;
            height: 8px;
            background: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: var(--warning);
            border-radius: 4px;
        }
        
        .rating-count-label {
            width: 40px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .review-item {
            background: var(--gray-light);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .review-author {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .review-author i {
            font-size: 1.2rem;
            color: var(--primary-light);
        }
        
        .review-stars {
            display: flex;
            gap: 3px;
        }
        
        .review-stars i {
            font-size: 0.8rem;
        }
        
        .review-date {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        
        .review-title {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .review-comment {
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .write-review {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .write-review h4 {
            margin-bottom: 20px;
        }
        
        .rating-select {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .rating-select label {
            cursor: pointer;
        }
        
        .rating-select input {
            display: none;
        }
        
        .rating-select i {
            font-size: 1.5rem;
            color: var(--gray-light);
            transition: color 0.2s;
        }
        
        .rating-select label:hover i,
        .rating-select label:hover ~ label i,
        .rating-select input:checked ~ i {
            color: var(--warning);
        }
        
        .login-to-review {
            text-align: center;
            padding: 40px;
            background: var(--gray-light);
            border-radius: 16px;
        }
        
        .login-to-review a {
            color: var(--primary-light);
            text-decoration: none;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        
        .no-reviews i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Produits similaires */
        .related-products {
            margin-top: 50px;
        }
        
        .related-products h2 {
            font-size: 1.3rem;
            margin-bottom: 25px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: var(--gray);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
        }
        
        .product-image {
            height: 200px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image i {
            font-size: 3rem;
            color: rgba(255,255,255,0.2);
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-info h3 {
            font-size: 0.95rem;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-info h3 a {
            color: var(--text);
            text-decoration: none;
        }
        
        .product-info h3 a:hover {
            color: var(--primary-light);
        }
        
        .product-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-light);
            margin-bottom: 12px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .product-grid {
                gap: 30px;
            }
            
            .product-main-image {
                height: 380px;
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .product-gallery {
                position: static;
            }
            
            .product-main-image {
                height: 350px;
            }
            
            .product-thumb {
                width: 70px;
                height: 70px;
            }
            
            .tabs-header {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .specs-table th {
                width: 120px;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .reviews-summary {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .product-main-image {
                height: 280px;
            }
            
            .product-thumb {
                width: 60px;
                height: 60px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<main>
    <div class="product-page">
        <div class="container">
            <!-- Fil d'Ariane -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                <i class="fas fa-chevron-right"></i>
                <a href="shop.php">Boutique</a>
                <i class="fas fa-chevron-right"></i>
                <a href="shop.php?category=<?php echo urlencode($product['category']); ?>"><?php echo clean($product['category']); ?></a>
                <i class="fas fa-chevron-right"></i>
                <span><?php echo clean($product['name']); ?></span>
            </div>
            
            <div class="product-grid">
                <!-- Galerie d'images -->
                <div class="product-gallery">
                    <div class="product-main-image">
                        <?php if($main_image): ?>
                            <img src="<?php echo $main_image; ?>" alt="<?php echo clean($product['name']); ?>" id="mainProductImage">
                        <?php else: ?>
                            <i class="fas fa-box-open"></i>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(count($product_images) > 1 || (!empty($product['image']) && count($product_images) > 0)): ?>
                    <div class="product-thumbnails">
                        <?php 
                        $thumbnails = [];
                        if (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])) {
                            $thumbnails[] = ['path' => 'uploads/products/' . $product['image']];
                        }
                        if (!empty($product['primary_image']) && file_exists('uploads/products/' . $product['primary_image']) && $product['primary_image'] !== $product['image']) {
                            $thumbnails[] = ['path' => 'uploads/products/' . $product['primary_image']];
                        }
                        foreach($product_images as $img) {
                            if (file_exists('uploads/products/' . $img['image_path'])) {
                                $thumbnails[] = ['path' => 'uploads/products/' . $img['image_path']];
                            }
                        }
                        $thumbnails = array_unique($thumbnails, SORT_REGULAR);
                        foreach($thumbnails as $thumb): 
                        ?>
                        <div class="product-thumb <?php echo ($thumb['path'] === $main_image) ? 'active' : ''; ?>" 
                             data-image="<?php echo $thumb['path']; ?>">
                            <img src="<?php echo $thumb['path']; ?>" alt="Miniature">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informations produit -->
                <div class="product-details">
                    <h1><?php echo clean($product['name']); ?></h1>
                    
                    <div class="product-rating">
                        <div class="stars">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= round($avg_rating) ? '#f59e0b' : '#2a2a35'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-count">(<?php echo count($reviews); ?> avis)</span>
                    </div>
                    
                    <div class="product-price-large">
                        <?php echo formatPrice($product['price']); ?>
                        <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                        <span class="old-price"><?php echo formatPrice($product['compare_price']); ?></span>
                        <span class="discount-badge">
                            -<?php echo round((1 - $product['price']/$product['compare_price']) * 100); ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-stock">
                        <?php if($product['stock'] > 0): ?>
                            <span class="in-stock">
                                <i class="fas fa-check-circle"></i> En stock 
                                <?php if($product['stock'] < 10): ?>
                                    <span class="stock-low">(Plus que <?php echo $product['stock']; ?> exemplaires)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="out-of-stock">
                                <i class="fas fa-times-circle"></i> Rupture de stock
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(clean($product['description'])); ?></p>
                    </div>
                    
                    <?php if($product['stock'] > 0): ?>
                    <form method="POST" class="product-form" id="addToCartForm">
                        <div class="quantity-selector">
                            <label>Quantité :</label>
                            <div class="quantity-input">
                                <button type="button" class="quantity-minus">-</button>
                                <input type="number" name="quantity" id="productQuantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button type="button" class="quantity-plus">+</button>
                            </div>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn-primary btn-large">
                            <i class="fas fa-shopping-bag"></i> Ajouter au panier
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="out-of-stock-message">
                        <i class="fas fa-clock"></i> Produit momentanément indisponible
                    </div>
                    <?php endif; ?>
                    
                    <div class="product-actions-side">
                        <?php if($in_wishlist): ?>
                            <a href="?remove_wishlist=1" class="btn-secondary wishlist-link active">
                                <i class="fas fa-heart"></i> Dans ma wishlist
                            </a>
                        <?php else: ?>
                            <a href="?wishlist=1" class="btn-secondary wishlist-link">
                                <i class="far fa-heart"></i> Ajouter à ma wishlist
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-meta">
                        <div class="meta-item">
                            <span class="meta-label">Catégorie :</span>
                            <a href="shop.php?category=<?php echo urlencode($product['category']); ?>"><?php echo clean($product['category']); ?></a>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Référence :</span>
                            <span>MARS-<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <?php if($product['views']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Consultations :</span>
                            <span><?php echo number_format($product['views']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($product['tags']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Tags :</span>
                            <div class="tags-list">
                                <?php 
                                $tags = explode(',', $product['tags']);
                                foreach($tags as $tag): 
                                    $tag_clean = trim($tag);
                                ?>
                                <a href="shop.php?search=<?php echo urlencode($tag_clean); ?>" class="tag"><?php echo clean($tag_clean); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Partage -->
                    <div class="share-section">
                        <span class="share-label">Partager :</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-link facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($product['name']); ?>&url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-link twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-link whatsapp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Onglets -->
            <div class="product-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="description">Description</button>
                    <button class="tab-btn" data-tab="specifications">Caractéristiques</button>
                    <button class="tab-btn" data-tab="reviews">Avis clients (<?php echo count($reviews); ?>)</button>
                    <button class="tab-btn" data-tab="shipping">Livraison</button>
                </div>
                
                <div class="tabs-content">
                    <div class="tab-pane active" id="tab-description">
                        <div class="tab-content">
                            <?php echo nl2br(clean($product['description'])); ?>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="tab-specifications">
                        <div class="tab-content">
                            <table class="specs-table">
                                <tr><th>Référence</th><td>MARS-<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></td></tr>
                                <tr><th>Catégorie</th><td><?php echo clean($product['category']); ?></td></tr>
                                <tr><th>Prix</th><td><?php echo formatPrice($product['price']); ?></td></tr>
                                <tr><th>Stock</th><td><?php echo $product['stock'] > 0 ? $product['stock'] . ' disponible(s)' : 'Rupture'; ?></td></tr>
                                <?php if($product['tags']): ?>
                                <tr><th>Tags</th><td><?php echo clean($product['tags']); ?></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="tab-reviews">
                        <div class="tab-content">
                            <?php if(count($reviews) > 0): ?>
                            <div class="reviews-summary">
                                <div class="avg-rating">
                                    <div class="rating-number"><?php echo number_format($avg_rating, 1); ?></div>
                                    <div class="stars-large">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= round($avg_rating) ? '#f59e0b' : '#2a2a35'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="rating-count">Basé sur <?php echo count($reviews); ?> avis</div>
                                </div>
                                <div class="rating-distribution">
                                    <?php for($i = 5; $i >= 1; $i--): ?>
                                    <div class="rating-bar-item">
                                        <span class="rating-star-label"><?php echo $i; ?> <i class="fas fa-star"></i></span>
                                        <div class="rating-bar">
                                            <div class="rating-bar-fill" style="width: <?php echo count($reviews) > 0 ? ($rating_distribution[$i] / count($reviews)) * 100 : 0; ?>%"></div>
                                        </div>
                                        <span class="rating-count-label">(<?php echo $rating_distribution[$i]; ?>)</span>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <?php foreach($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="review-author">
                                        <i class="fas fa-user-circle"></i>
                                        <strong><?php echo clean($review['username']); ?></strong>
                                    </div>
                                    <div class="review-stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#f59e0b' : '#2a2a35'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="review-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo formatDate($review['created_at'], 'd/m/Y'); ?>
                                    </div>
                                </div>
                                <?php if($review['title']): ?>
                                <div class="review-title"><?php echo clean($review['title']); ?></div>
                                <?php endif; ?>
                                <div class="review-comment"><?php echo nl2br(clean($review['comment'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="no-reviews">
                                <i class="fas fa-comments"></i>
                                <p>Aucun avis pour le moment.</p>
                                <p>Soyez le premier à donner votre avis !</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(isLoggedIn()): ?>
                            <div class="write-review">
                                <h4>Donner mon avis</h4>
                                <form method="POST" id="reviewForm">
                                    <div class="form-group">
                                        <label>Note *</label>
                                        <div class="rating-select">
                                            <?php for($i = 5; $i >= 1; $i--): ?>
                                            <label>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" required>
                                                <i class="far fa-star"></i>
                                            </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Titre (optionnel)</label>
                                        <input type="text" name="review_title" placeholder="Résumez votre avis">
                                    </div>
                                    <div class="form-group">
                                        <label>Votre avis *</label>
                                        <textarea name="review_comment" rows="4" required placeholder="Partagez votre expérience avec ce produit..."></textarea>
                                    </div>
                                    <button type="submit" name="submit_review" class="btn-primary">Publier mon avis</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="login-to-review">
                                <i class="fas fa-lock"></i>
                                <p><a href="login.php">Connectez-vous</a> pour laisser un avis</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="tab-shipping">
                        <div class="tab-content">
                            <h3><i class="fas fa-truck"></i> Livraison</h3>
                            <p>Livraison offerte en France métropolitaine dès 50€ d'achat.</p>
                            <p>Délai de livraison : 2 à 5 jours ouvrés.</p>
                            
                            <h3><i class="fas fa-undo-alt"></i> Retours</h3>
                            <p>Vous disposez de 30 jours pour retourner votre produit s'il ne vous satisfait pas.</p>
                            
                            <h3><i class="fas fa-shield-alt"></i> Garantie</h3>
                            <p>Tous nos produits bénéficient d'une garantie de 2 ans.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Produits similaires -->
            <?php if(count($related) > 0): ?>
            <div class="related-products">
                <h2><i class="fas fa-tags"></i> Vous aimerez aussi</h2>
                <div class="products-grid">
                    <?php foreach($related as $item): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if(!empty($item['primary_image']) && file_exists('uploads/products/' . $item['primary_image'])): ?>
                                <img src="uploads/products/<?php echo $item['primary_image']; ?>" alt="<?php echo clean($item['name']); ?>">
                            <?php elseif(!empty($item['image']) && file_exists('uploads/products/' . $item['image'])): ?>
                                <img src="uploads/products/<?php echo $item['image']; ?>" alt="<?php echo clean($item['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-box-open"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo clean($item['name']); ?></a></h3>
                            <div class="product-price"><?php echo formatPrice($item['price']); ?></div>
                            <div class="product-actions">
                                <a href="product.php?id=<?php echo $item['id']; ?>" class="btn-secondary btn-sm">Voir</a>
                                <?php if($item['stock'] > 0): ?>
                                <button class="btn-primary btn-sm add-to-cart-btn" data-product-id="<?php echo $item['id']; ?>">
                                    Ajouter
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================
// PRODUCT.JS - COMPLET
// ============================================

// Gestion des quantités
const quantityInput = document.getElementById('productQuantity');
const minusBtn = document.querySelector('.quantity-minus');
const plusBtn = document.querySelector('.quantity-plus');

if (minusBtn && plusBtn && quantityInput) {
    minusBtn.addEventListener('click', () => {
        let val = parseInt(quantityInput.value);
        const min = parseInt(quantityInput.min) || 1;
        if (val > min) {
            quantityInput.value = val - 1;
        }
    });
    
    plusBtn.addEventListener('click', () => {
        let val = parseInt(quantityInput.value);
        const max = parseInt(quantityInput.max) || Infinity;
        if (val < max) {
            quantityInput.value = val + 1;
        }
    });
}

// Galerie d'images
const thumbnails = document.querySelectorAll('.product-thumb');
const mainImage = document.getElementById('mainProductImage');

if (thumbnails.length > 0 && mainImage) {
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const imageUrl = this.dataset.image;
            if (imageUrl) {
                mainImage.src = imageUrl;
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
}

// Onglets
const tabBtns = document.querySelectorAll('.tab-btn');
const tabPanes = document.querySelectorAll('.tab-pane');

tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;
        
        tabBtns.forEach(b => b.classList.remove('active'));
        tabPanes.forEach(p => p.classList.remove('active'));
        
        btn.classList.add('active');
        const activePane = document.getElementById(`tab-${tabId}`);
        if (activePane) activePane.classList.add('active');
    });
});

// Formulaire avis
const reviewForm = document.getElementById('reviewForm');
if (reviewForm) {
    reviewForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('product_id', <?php echo $product_id; ?>);
        formData.append('submit_review', '1');
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('submit-review.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Merci pour votre avis !', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(result.error || 'Erreur', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Erreur de connexion', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Étoiles du formulaire d'avis
const ratingInputs = document.querySelectorAll('.rating-select input');
ratingInputs.forEach(input => {
    input.addEventListener('change', function() {
        const stars = this.closest('.rating-select').querySelectorAll('i');
        const value = parseInt(this.value);
        stars.forEach((star, index) => {
            star.style.color = index < (5 - value + 1) ? '#f59e0b' : '#2a2a35';
        });
    });
});

// Notification
function showNotification(message, type = 'success') {
    const existing = document.querySelector('.product-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'product-notification';
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        info: '#3b82f6'
    };
    
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1a1a24;
        border-left: 4px solid ${colors[type]};
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = 'background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;margin-left:8px';
    closeBtn.onclick = () => notification.remove();
    
    setTimeout(() => notification.remove(), 3000);
}

// Ajout au panier depuis produits similaires
document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        const productId = this.dataset.productId;
        const originalHTML = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        this.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            const response = await fetch('ajax-add-to-cart.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                showNotification('Produit ajouté au panier', 'success');
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }, 1500);
            } else {
                this.innerHTML = originalHTML;
                this.disabled = false;
                showNotification(result.message || 'Erreur', 'error');
            }
        } catch (error) {
            this.innerHTML = originalHTML;
            this.disabled = false;
            showNotification('Erreur de connexion', 'error');
        }
    });
});

// Styles d'animation
if (!document.querySelector('#product-styles')) {
    const style = document.createElement('style');
    style.id = 'product-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once 'includes/footer.php'; ?>