<?php
// order-success.php - Confirmation de commande améliorée
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$order_number = $_GET['order'] ?? '';

if (!$order_number) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

$stmt = $conn->prepare("
    SELECT o.*, p.status as payment_status, p.transaction_id 
    FROM orders o 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.order_number = ? AND o.user_id = ?
");
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: profile.php');
    exit();
}

// Récupérer les articles pour l'affichage
$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();

// Calculer la date de livraison estimée (5 jours ouvrés)
$delivery_date = new DateTime($order['created_at']);
$delivery_date->modify('+5 weekdays');
?>

<div class="success-page">
    <div class="container">
        <div class="success-card">
            <!-- Animation de succès -->
            <div class="success-animation">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-ring"></div>
            </div>
            
            <h1>Commande confirmée ! 🎉</h1>
            <p class="success-message">Merci pour votre commande, <strong><?php echo clean($_SESSION['username']); ?></strong>.</p>
            <p class="success-submessage">Un email de confirmation vous a été envoyé.</p>
            
            <!-- Informations commande -->
            <div class="order-summary-card">
                <div class="order-summary-header">
                    <i class="fas fa-receipt"></i>
                    <span>Récapitulatif de votre commande</span>
                </div>
                
                <div class="order-info-grid">
                    <div class="order-info-item">
                        <span class="info-label">N° commande</span>
                        <span class="info-value order-number"><?php echo $order['order_number']; ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="info-label">Date</span>
                        <span class="info-value"><?php echo formatDate($order['created_at'], 'd/m/Y à H:i'); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="info-label">Montant total</span>
                        <span class="info-value total-amount"><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="info-label">Statut paiement</span>
                        <span class="info-value payment-status <?php echo $order['payment_status']; ?>">
                            <i class="fas <?php echo $order['payment_status'] == 'paid' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                            <?php echo $order['payment_status'] == 'paid' ? 'Payé' : 'En attente'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Livraison estimée -->
            <div class="delivery-card">
                <div class="delivery-icon">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <div class="delivery-info">
                    <h4>Livraison estimée</h4>
                    <p class="delivery-date"><?php echo $delivery_date->format('d/m/Y'); ?></p>
                    <span class="delivery-status">En cours de préparation</span>
                </div>
                <div class="delivery-progress">
                    <div class="progress-steps">
                        <div class="step completed">
                            <div class="step-dot"></div>
                            <span>Commandée</span>
                        </div>
                        <div class="step active">
                            <div class="step-dot"></div>
                            <span>Préparation</span>
                        </div>
                        <div class="step">
                            <div class="step-dot"></div>
                            <span>Expédition</span>
                        </div>
                        <div class="step">
                            <div class="step-dot"></div>
                            <span>Livrée</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Articles commandés (résumé) -->
            <div class="items-preview">
                <h4><i class="fas fa-box"></i> Articles commandés</h4>
                <div class="preview-items">
                    <?php 
                    $displayItems = array_slice($items, 0, 3);
                    $remainingItems = count($items) - 3;
                    foreach($displayItems as $item): 
                    ?>
                    <div class="preview-item">
                        <div class="preview-item-image">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="preview-item-info">
                            <span class="preview-item-name"><?php echo clean($item['name']); ?></span>
                            <span class="preview-item-qty">x<?php echo $item['quantity']; ?></span>
                        </div>
                        <div class="preview-item-price"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if($remainingItems > 0): ?>
                    <div class="preview-more">
                        <a href="order-details.php?id=<?php echo $order['id']; ?>">+ <?php echo $remainingItems; ?> autre(s) article(s)</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="success-actions">
                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn-secondary">
                    <i class="fas fa-eye"></i> Voir le détail
                </a>
                <a href="profile.php?tab=orders" class="btn-secondary">
                    <i class="fas fa-list"></i> Mes commandes
                </a>
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-store"></i> Continuer mes achats
                </a>
            </div>
            
            <!-- Partager / Aide -->
            <div class="success-footer">
                <div class="share-links">
                    <span>Partager :</span>
                    <a href="#" onclick="shareOrder('<?php echo $order['order_number']; ?>')"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" onclick="shareOrder('<?php echo $order['order_number']; ?>')"><i class="fab fa-facebook-messenger"></i></a>
                    <a href="#" onclick="shareOrder('<?php echo $order['order_number']; ?>')"><i class="fab fa-twitter"></i></a>
                </div>
                <div class="help-link">
                    <a href="contact.php"><i class="fas fa-headset"></i> Besoin d'aide ? Contactez-nous</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.success-page {
    min-height: 80vh;
    display: flex;
    align-items: center;
    padding: 40px 0;
}

.success-card {
    max-width: 700px;
    margin: 0 auto;
    background: var(--gray);
    border-radius: 24px;
    padding: 40px;
    text-align: center;
}

/* Animation succès */
.success-animation {
    position: relative;
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
}

.success-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2;
    animation: pulse 0.5s ease-out;
}

.success-icon i {
    font-size: 3.5rem;
    color: white;
}

.success-ring {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.3);
    animation: ripple 1.5s ease-out infinite;
}

@keyframes pulse {
    0% { transform: scale(0); opacity: 0; }
    80% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes ripple {
    0% { transform: scale(1); opacity: 0.5; }
    100% { transform: scale(1.5); opacity: 0; }
}

.success-card h1 {
    margin-bottom: 8px;
    font-size: 1.8rem;
}

.success-message {
    font-size: 1rem;
    margin-bottom: 4px;
}

.success-submessage {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 24px;
}

/* Résumé commande */
.order-summary-card {
    background: var(--gray-light);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    text-align: left;
}

.order-summary-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
}

.order-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.order-info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
}

.info-value {
    font-size: 0.9rem;
    font-weight: 500;
}

.order-number {
    font-family: monospace;
    font-size: 1rem;
    color: var(--primary-light);
}

.total-amount {
    font-size: 1.1rem;
    color: var(--primary-light);
}

.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.payment-status.paid {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.payment-status.pending {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Livraison */
.delivery-card {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(16, 185, 129, 0.05));
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.delivery-icon {
    width: 60px;
    height: 60px;
    background: rgba(59, 130, 246, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.delivery-icon i {
    font-size: 1.8rem;
    color: #3b82f6;
}

.delivery-info {
    flex: 1;
    text-align: left;
}

.delivery-info h4 {
    font-size: 0.85rem;
    margin-bottom: 4px;
    color: var(--text-muted);
}

.delivery-date {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 4px;
}

.delivery-status {
    font-size: 0.7rem;
    color: #10b981;
}

.delivery-progress {
    flex: 2;
    min-width: 200px;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 12px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--border);
    z-index: 1;
}

.step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}

.step-dot {
    width: 12px;
    height: 12px;
    background: var(--border);
    border-radius: 50%;
    margin: 6px auto;
}

.step.completed .step-dot {
    background: #10b981;
}

.step.active .step-dot {
    background: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

.step span {
    font-size: 0.65rem;
    color: var(--text-muted);
}

.step.completed span,
.step.active span {
    color: var(--text);
}

/* Articles prévisualisation */
.items-preview {
    text-align: left;
    margin-bottom: 24px;
}

.items-preview h4 {
    margin-bottom: 16px;
    font-size: 1rem;
}

.preview-items {
    background: var(--gray-light);
    border-radius: 12px;
    overflow: hidden;
}

.preview-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border);
}

.preview-item:last-child {
    border-bottom: none;
}

.preview-item-image {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-item-image i {
    font-size: 1rem;
    color: rgba(255,255,255,0.5);
}

.preview-item-info {
    flex: 1;
}

.preview-item-name {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
}

.preview-item-qty {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.preview-item-price {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary-light);
}

.preview-more {
    padding: 12px;
    text-align: center;
    border-top: 1px solid var(--border);
}

.preview-more a {
    color: var(--primary-light);
    text-decoration: none;
    font-size: 0.85rem;
}

/* Actions */
.success-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

/* Footer succès */
.success-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    font-size: 0.8rem;
}

.share-links {
    display: flex;
    align-items: center;
    gap: 12px;
}

.share-links span {
    color: var(--text-muted);
}

.share-links a {
    color: var(--text-muted);
    font-size: 1.1rem;
    transition: color 0.2s;
}

.share-links a:hover {
    color: var(--primary-light);
}

.help-link a {
    color: var(--text-muted);
    text-decoration: none;
}

.help-link a:hover {
    color: var(--primary-light);
}

/* Responsive */
@media (max-width: 768px) {
    .success-card {
        padding: 24px;
        margin: 0 16px;
    }
    
    .order-info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .delivery-card {
        flex-direction: column;
        text-align: center;
    }
    
    .delivery-info {
        text-align: center;
    }
    
    .success-actions {
        flex-direction: column;
    }
    
    .success-footer {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .progress-steps span {
        display: none;
    }
    
    .progress-steps::before {
        top: 6px;
    }
    
    .step-dot {
        width: 8px;
        height: 8px;
    }
}
</style>

<script>
// Fonction pour partager la commande
function shareOrder(orderNumber) {
    const text = `Ma commande ${orderNumber} sur Mars Shop vient d'être confirmée ! 🚀`;
    if (navigator.share) {
        navigator.share({
            title: 'Commande confirmée',
            text: text,
            url: window.location.href
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(text);
        showNotification('Lien copié dans le presse-papier', 'success');
    }
}

// Notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    notification.style.cssText = `
        position: fixed; bottom: 20px; right: 20px; background: #1a1a24;
        border-left: 4px solid #10b981; padding: 12px 20px;
        border-radius: 8px; z-index: 10000; animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>