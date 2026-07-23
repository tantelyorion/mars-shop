<?php
// login.php - Connexion avec synchronisation du panier
require_once 'config/database.php';
require_once 'includes/header.php';

if(isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        syncCartAfterLogin($user['id']);
        
        if(isset($_SESSION['guest_wishlist'])) {
            foreach($_SESSION['guest_wishlist'] as $product_id) {
                $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user['id'], $product_id]);
            }
            unset($_SESSION['guest_wishlist']);
        }
        
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        header("Location: $redirect");
        exit();
    } else {
        $error = 'Email ou mot de passe incorrect';
    }
}
?>

<div class="container" style="max-width: 400px;">
    <div style="background: var(--gray); border-radius: 16px; padding: 32px; margin: 40px auto;">
        <h2 style="text-align: center; margin-bottom: 24px;">Connexion</h2>
        
        <?php if($error): ?>
        <div style="background: rgba(239,68,68,0.15); border: 1px solid var(--error); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">Se connecter</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            Pas encore de compte ? <a href="register.php" style="color: var(--primary-light);">Inscription</a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>