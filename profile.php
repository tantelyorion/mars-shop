<?php
// profile.php - Page profil utilisateur
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les infos
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = clean($_POST['full_name']);
    $phone = clean($_POST['phone']);
    $address = clean($_POST['address']);
    
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    if ($stmt->execute([$full_name, $phone, $address, $user_id])) {
        setFlashMessage('success', 'Profil mis à jour');
        header('Location: profile.php');
        exit();
    }
}

// Changement mot de passe
$pass_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (password_verify($current, $user['password'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $user_id])) {
                setFlashMessage('success', 'Mot de passe modifié');
                header('Location: profile.php');
                exit();
            }
        } else {
            $pass_error = 'Les mots de passe ne correspondent pas (min 6 caractères)';
        }
    } else {
        $pass_error = 'Mot de passe actuel incorrect';
    }
}

// Récupérer les commandes
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
?>

<div class="profile-page">
    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fas fa-user-astronaut"></i>
            </div>
            <div class="profile-info">
                <h2><?php echo clean($user['username']); ?></h2>
                <p><?php echo clean($user['email']); ?></p>
            </div>
        </div>
        
        <div class="profile-tabs">
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="info">Informations</button>
                <button class="tab-btn" data-tab="password">Sécurité</button>
                <button class="tab-btn" data-tab="orders">Commandes</button>
            </div>
            
            <div class="tab-content active" id="tab-info">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom complet</label>
                            <input type="text" name="full_name" value="<?php echo clean($user['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="phone" value="<?php echo clean($user['phone']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <textarea name="address" rows="3"><?php echo clean($user['address']); ?></textarea>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary">Enregistrer</button>
                </form>
            </div>
            
            <div class="tab-content" id="tab-password">
                <?php if($pass_error): ?>
                <div class="alert-error"><?php echo $pass_error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-primary">Changer</button>
                </form>
            </div>
            
            <div class="tab-content" id="tab-orders">
                <?php if(count($orders) > 0): ?>
                    <?php foreach($orders as $order): ?>
                    <div class="order-item">
                        <div class="order-header">
                            <span class="order-number"><?php echo $order['order_number']; ?></span>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <?php echo $order['status'] == 'processing' ? 'En traitement' : ($order['status'] == 'delivered' ? 'Livrée' : 'En attente'); ?>
                            </span>
                        </div>
                        <div class="order-body">
                            <span><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                        <div class="order-footer">
                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn-secondary btn-sm">Détails</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty">Aucune commande</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.profile-page {
    padding: 20px 0;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-avatar i {
    font-size: 2.5rem;
}

.profile-info h2 {
    margin-bottom: 4px;
}

.profile-info p {
    color: var(--text-muted);
}

.profile-tabs {
    background: var(--gray);
    border-radius: 16px;
    overflow: hidden;
}

.tabs-nav {
    display: flex;
    border-bottom: 1px solid var(--border);
    background: var(--gray-light);
}

.tab-btn {
    padding: 12px 24px;
    background: none;
    border: none;
    color: var(--text);
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: var(--primary-light);
}

.tab-btn.active {
    color: var(--primary-light);
    border-bottom: 2px solid var(--primary);
}

.tab-content {
    display: none;
    padding: 24px;
}

.tab-content.active {
    display: block;
}

.order-item {
    background: var(--gray-light);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.order-number {
    font-weight: 600;
}

.order-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.status-processing { background: var(--warning); color: #000; }
.status-delivered { background: var(--success); }
.status-pending { background: var(--info); }

.order-body {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.order-footer {
    text-align: right;
}

.alert-error {
    background: rgba(239,68,68,0.15);
    border: 1px solid var(--error);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: var(--error);
}

.empty {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .tabs-nav {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        text-align: center;
    }
}
</style>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(`tab-${tabId}`).classList.add('active');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>