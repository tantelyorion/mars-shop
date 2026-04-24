<?php
// login.php - Connexion avec synchronisation du panier + AMEA Social Login
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

// URL de redirection vers AMEA OAuth
$amea_auth_url = 'https://amea.chaudly.com/oauth_authorize.php?' . http_build_query([
    'client_id' => 'VOTRE_CLIENT_ID_AMEA',
    'redirect_uri' => 'https://mars-shop.com/oauth_amea_callback.php',
    'response_type' => 'code',
    'scope' => 'profile email',
    'state' => bin2hex(random_bytes(16))
]);
?>

<div class="container" style="max-width: 400px;">
    <div style="background: var(--gray); border-radius: 16px; padding: 32px; margin: 40px auto;">
        <h2 style="text-align: center; margin-bottom: 24px;">Connexion</h2>
        
        <?php if($error): ?>
        <div style="background: rgba(239,68,68,0.15); border: 1px solid var(--error); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Bouton Login with AMEA -->
        <a href="<?php echo $amea_auth_url; ?>" class="btn-amea-login">
            <img src="https://amea.chaudly.com/res/tr.png" alt="AMEA" style="width: 20px; height: 20px;">
            Se connecter avec AMEA
        </a>
        
        <div style="text-align: center; margin: 20px 0; position: relative;">
            <hr style="border: none; border-top: 1px solid var(--border);">
            <span style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--gray); padding: 0 10px; color: var(--text-secondary);">ou</span>
        </div>
        
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

<style>
.btn-amea-login {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 12px;
    background: #000000;
    border: 1px solid #ffd60a;
    border-radius: 40px;
    color: #ffd60a;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    margin-bottom: 10px;
}

.btn-amea-login:hover {
    background: #ffd60a;
    color: #000000;
    transform: scale(0.98);
}
</style>

<?php require_once 'includes/footer.php'; ?>