<?php
// register.php - Inscription
require_once 'config/database.php';
require_once 'includes/header.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Récupérer les données AMEA si disponibles (après OAuth)
$amea_data = null;
if (isset($_GET['amea_id']) && isset($_GET['amea_email'])) {
    $amea_data = [
        'amea_id' => $_GET['amea_id'],
        'email' => $_GET['amea_email'],
        'username' => $_GET['amea_username'] ?? '',
        'avatar' => $_GET['amea_avatar'] ?? ''
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $full_name = clean($_POST['full_name']);
    $phone = clean($_POST['phone']);
    $amea_id = !empty($_POST['amea_id']) ? $_POST['amea_id'] : null;
    $amea_avatar = !empty($_POST['amea_avatar']) ? $_POST['amea_avatar'] : null;
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } elseif (strlen($password) < 6) {
        $error = 'Mot de passe (min 6 caractères)';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas';
    } else {
        $conn = getConnection();
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->fetch()) {
            $error = 'Email ou nom d\'utilisateur déjà utilisé';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, phone, amea_id, amea_avatar, auth_provider) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $auth_provider = $amea_id ? 'amea' : 'local';
            
            if ($stmt->execute([$username, $email, $hashed, $full_name, $phone, $amea_id, $amea_avatar, $auth_provider])) {
                setFlashMessage('success', 'Inscription réussie ! Vous pouvez vous connecter.');
                header('Location: login.php');
                exit();
            } else {
                $error = 'Erreur lors de l\'inscription';
            }
        }
    }
}
?>

<div class="auth-page">
    <div class="container" style="max-width: 500px;">
        <div class="auth-card">
            <h2>Inscription</h2>
            
            <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($amea_data): ?>
            <div class="alert-info" style="background: rgba(245,158,11,0.15); border: 1px solid #f59e0b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> Vous vous inscrivez avec AMEA. Votre compte sera lié automatiquement.
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <?php if($amea_data): ?>
                <input type="hidden" name="amea_id" value="<?php echo clean($amea_data['amea_id']); ?>">
                <input type="hidden" name="amea_avatar" value="<?php echo clean($amea_data['avatar']); ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom d'utilisateur *</label>
                        <input type="text" name="username" value="<?php echo clean($amea_data['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="full_name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo clean($amea_data['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="phone">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary btn-block">S'inscrire</button>
            </form>
            
            <p class="auth-link">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
        </div>
    </div>
</div>

<style>
.auth-page {
    min-height: 60vh;
    display: flex;
    align-items: center;
    padding: 40px 0;
}

.auth-card {
    background: var(--gray);
    border-radius: 16px;
    padding: 32px;
}

.auth-card h2 {
    text-align: center;
    margin-bottom: 24px;
}

.alert-error {
    background: rgba(239,68,68,0.15);
    border: 1px solid var(--error);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: var(--error);
}

.btn-block {
    width: 100%;
}

.auth-link {
    text-align: center;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.auth-link a {
    color: var(--primary-light);
    text-decoration: none;
}

@media (max-width: 768px) {
    .auth-card {
        padding: 24px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>