<?php
// admin/includes/sidebar.php - Sidebar réutilisable pour toutes les pages admin
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-planet-ringed"></i>
            Mars<span>Admin</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Tableau de bord
        </a>
        <a href="products.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'product-add.php', 'product-edit.php']) ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Produits
        </a>
        <a href="orders.php" class="nav-item <?php echo in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'order-detail.php']) ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Commandes
        </a>
        <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Utilisateurs
        </a>
        
        <div class="nav-group">
            <div class="nav-group-title">Gestion financière</div>
            <a href="payments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Paiements
            </a>
            <a href="payment-methods.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'payment-methods.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i> Méthodes de paiement
            </a>
            <a href="mobile-money-accounts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mobile-money-accounts.php' ? 'active' : ''; ?>">
                <i class="fas fa-mobile-alt"></i> Comptes Mobile Money
            </a>
        </div>
        
        <div class="nav-group">
            <div class="nav-group-title">Marketing</div>
            <a href="coupons.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : ''; ?>">
                <i class="fas fa-ticket-alt"></i> Coupons
            </a>
            <a href="categories.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Catégories            </a>
        </div>
        
        <div class="nav-group">
            <div class="nav-group-title">Configuration</div>
            <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Paramètres
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>
</aside>

<style>
.admin-sidebar {
    width: 280px;
    background: #0a0a0e;
    border-right: 1px solid #2a2a35;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    transition: transform 0.3s ease;
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

.nav-group {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #2a2a35;
}

.nav-group-title {
    padding: 8px 16px;
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #666;
    letter-spacing: 1px;
}

@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.active {
        transform: translateX(0);
    }
}
</style>