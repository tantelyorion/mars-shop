<?php
// includes/header.php - Version épurée et professionnelle
if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php';
    $conn = getConnection();
}
require_once __DIR__ . '/functions.php';

$cart_count = getCartCount();
$wishlist_count = getWishlistCount();
$flash = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mars Shop - Votre boutique spatiale</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header-wrapper">
            <div class="logo">
                <a href="index.php">
                    <i class="fas fa-rocket"></i>
                    <span>Mars<span>Shop</span></span>
                </a>
            </div>
            
            <nav class="nav">
                <a href="index.php">Accueil</a>
                <a href="shop.php">Boutique</a>
                <a href="wishlist.php" class="nav-wishlist">
                    <i class="far fa-heart"></i>
                    <?php if($wishlist_count > 0): ?>
                        <span class="badge"><?php echo $wishlist_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="cart.php" class="nav-cart">
                    <i class="fas fa-shopping-bag"></i>
                    <?php if($cart_count > 0): ?>
                        <span class="badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            
            <div class="user-menu">
                <?php if(isLoggedIn()): ?>
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <i class="fas fa-user-astronaut"></i>
                            <span><?php echo clean($_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> Mon compte</a>
                            <a href="orders.php"><i class="fas fa-box"></i> Commandes</a>
                            <?php if(isAdmin()): ?>
                                <a href="admin/"><i class="fas fa-dashboard"></i> Admin</a>
                            <?php endif; ?>
                            <hr>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Connexion</a>
                    <a href="register.php" class="btn-register">Inscription</a>
                <?php endif; ?>
            </div>
            
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <div class="logo">MarsShop</div>
        <button class="mobile-close">&times;</button>
    </div>
    <div class="mobile-menu-body">
        <a href="index.php">Accueil</a>
        <a href="shop.php">Boutique</a>
        <a href="wishlist.php">Wishlist</a>
        <a href="cart.php">Panier</a>
        <?php if(isLoggedIn()): ?>
            <a href="profile.php">Mon compte</a>
            <a href="orders.php">Commandes</a>
            <a href="logout.php">Déconnexion</a>
        <?php else: ?>
            <a href="login.php">Connexion</a>
            <a href="register.php">Inscription</a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Message -->
<?php if($flash): ?>
<div class="flash-message flash-<?php echo $flash['type']; ?>">
    <span><?php echo $flash['message']; ?></span>
    <button class="flash-close">&times;</button>
</div>
<?php endif; ?>

<main class="main">