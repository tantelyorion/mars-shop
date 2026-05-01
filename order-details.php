<?php
// order-details.php - Détails d'une commande avec images des produits
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: profile.php?tab=orders');
    exit();
}

$conn = getConnection();

// Récupérer la commande
$stmt = $conn->prepare("
    SELECT o.*, p.status as payment_status, p.transaction_id, p.payment_method
    FROM orders o 
    LEFT JOIN payments p ON o.id = p.order_id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: profile.php?tab=orders');
    exit();
}

// Récupérer les articles avec leurs images
$stmt = $conn->prepare("
    SELECT oi.*, p.name, 
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Ajouter le chemin de l'image pour chaque article
foreach ($items as &$item) {
    if (!empty($item['primary_image']) && file_exists('uploads/products/' . $item['primary_image'])) {
        $item['product_image'] = 'uploads/products/' . $item['primary_image'];
    } elseif (!empty($item['image']) && file_exists('uploads/products/' . $item['image'])) {
        $item['product_image'] = 'uploads/products/' . $item['image'];
    } else {
        $item['product_image'] = null;
    }
}

// Statuts possibles
$statuses = [
    'pending' => ['label' => 'En attente', 'icon' => 'fa-clock', 'color' => '#f59e0b'],
    'processing' => ['label' => 'En traitement', 'icon' => 'fa-spinner', 'color' => '#3b82f6'],
    'shipped' => ['label' => 'Expédiée', 'icon' => 'fa-truck', 'color' => '#8b5cf6'],
    'delivered' => ['label' => 'Livrée', 'icon' => 'fa-check-circle', 'color' => '#10b981'],
    'cancelled' => ['label' => 'Annulée', 'icon' => 'fa-times-circle', 'color' => '#ef4444']
];
$currentStatus = $statuses[$order['status']] ?? $statuses['pending'];
?>

<div class="order-details-page">
    <div class="container">
        <div class="back-link">
            <a href="profile.php?tab=orders">
                <i class="fas fa-arrow-left"></i> Retour à mes commandes
            </a>
        </div>
        
        <div class="details-card">
            <!-- En-tête -->
            <div class="details-header">
                <div>
                    <h2>Commande #<?php echo $order['order_number']; ?></h2>
                    <p class="order-date">Passée le <?php echo formatDate($order['created_at'], 'd/m/Y à H:i'); ?></p>
                </div>
                <div class="order-status-badge" style="background: <?php echo $currentStatus['color']; ?>20; color: <?php echo $currentStatus['color']; ?>;">
                    <i class="fas <?php echo $currentStatus['icon']; ?>"></i>
                    <?php echo $currentStatus['label']; ?>
                </div>
            </div>
            
            <!-- Timeline de suivi -->
            <div class="order-timeline">
                <div class="timeline-steps">
                    <?php 
                    $stepStatuses = ['pending', 'processing', 'shipped', 'delivered'];
                    $currentIndex = array_search($order['status'], $stepStatuses);
                    foreach($stepStatuses as $index => $step):
                        $isCompleted = $index <= $currentIndex;
                        $isCurrent = $index == $currentIndex;
                    ?>
                    <div class="timeline-step <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                        <div class="timeline-icon">
                            <i class="fas <?php 
                                echo $step == 'pending' ? 'fa-receipt' : 
                                    ($step == 'processing' ? 'fa-box' : 
                                    ($step == 'shipped' ? 'fa-truck' : 'fa-check-circle')); 
                            ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <span class="timeline-title"><?php echo $statuses[$step]['label']; ?></span>
                            <?php if($isCompleted && $step == 'delivered'): ?>
                            <span class="timeline-date"><?php echo formatDate($order['updated_at'], 'd/m/Y'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Grille d'informations -->
            <div class="details-grid">
                <!-- Adresse de livraison -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Adresse de livraison</h3>
                    </div>
                    <div class="info-card-body">
                        <p><?php echo nl2br(clean($order['shipping_address'])); ?></p>
                    </div>
                </div>
                
                <!-- Paiement -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="fas fa-credit-card"></i>
                        <h3>Paiement</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="payment-info">
                            <span class="payment-method">
                                <i class="fas <?php echo $order['payment_method'] == 'credit_card' ? 'fa-credit-card' : ($order['payment_method'] == 'paypal' ? 'fa-paypal' : 'fa-money-bill'); ?>"></i>
                                <?php echo $order['payment_method'] == 'credit_card' ? 'Carte bancaire' : ($order['payment_method'] == 'paypal' ? 'PayPal' : 'Paiement à la livraison'); ?>
                            </span>
                            <span class="payment-status-badge <?php echo $order['payment_status']; ?>">
                                <i class="fas <?php echo $order['payment_status'] == 'paid' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                <?php echo $order['payment_status'] == 'paid' ? 'Payé' : 'En attente'; ?>
                            </span>
                        </div>
                        <?php if($order['transaction_id']): ?>
                        <p class="transaction-id">
                            <span>Transaction :</span> <?php echo $order['transaction_id']; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Résumé -->
                <div class="info-card summary-card">
                    <div class="info-card-header">
                        <i class="fas fa-calculator"></i>
                        <h3>Résumé</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="summary-row">
                            <span>Sous-total</span>
                            <span><?php echo formatPrice($order['subtotal']); ?></span>
                        </div>
                        <?php if($order['discount'] > 0): ?>
                        <div class="summary-row discount">
                            <span>Réduction</span>
                            <span>-<?php echo formatPrice($order['discount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row">
                            <span>Livraison</span>
                            <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'Offerte'; ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <strong><?php echo formatPrice($order['total_amount']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Articles commandés avec images -->
            <div class="items-section">
                <div class="items-header">
                    <h3><i class="fas fa-boxes"></i> Articles commandés</h3>
                    <span class="items-count"><?php echo count($items); ?> article(s)</span>
                </div>
                
                <div class="items-table-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                            <tr>
                                <td class="product-cell">
                                    <div class="product-info-cell">
                                        <div class="product-image-cell">
                                            <?php if($item['product_image'] && file_exists($item['product_image'])): ?>
                                                <img src="<?php echo $item['product_image']; ?>" alt="<?php echo clean($item['name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-box-open"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="product-name-cell"><?php echo clean($item['name']); ?></div>
                                            <a href="product.php?id=<?php echo $item['product_id']; ?>" class="product-link">Voir le produit</a>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatPrice($item['price']); ?></td>
                                <td class="quantity-cell">x<?php echo $item['quantity']; ?></td>
                                <td class="total-cell"><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Notes -->
            <?php if($order['notes']): ?>
            <div class="notes-section">
                <h3><i class="fas fa-pencil-alt"></i> Notes</h3>
                <p><?php echo nl2br(clean($order['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="details-actions">
                <?php if($order['status'] == 'processing'): ?>
                <button class="btn-secondary" onclick="trackOrder('<?php echo $order['order_number']; ?>')">
                    <i class="fas fa-truck"></i> Suivre mon colis
                </button>
                <?php endif; ?>
                <?php if($order['status'] == 'pending'): ?>
                <button class="btn-outline" onclick="if(confirm('Annuler cette commande ?')) window.location.href='cancel-order.php?id=<?php echo $order['id']; ?>'">
                    <i class="fas fa-times"></i> Annuler la commande
                </button>
                <?php endif; ?>
                <button class="btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <a href="shop.php" class="btn-primary">
                    <i class="fas fa-store"></i> Commander à nouveau
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.order-details-page {
    padding: 30px 0;
}

.back-link {
    margin-bottom: 20px;
}

.back-link a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}

.back-link a:hover {
    color: var(--primary-light);
}

.details-card {
    background: var(--gray);
    border-radius: 24px;
    padding: 32px;
}

/* En-tête */
.details-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 16px;
}

.details-header h2 {
    margin-bottom: 8px;
}

.order-date {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.order-status-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 40px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Timeline */
.order-timeline {
    background: var(--gray-light);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 30px;
}

.timeline-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.timeline-steps::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 40px;
    right: 40px;
    height: 2px;
    background: var(--border);
    z-index: 1;
}

.timeline-step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}

.timeline-icon {
    width: 50px;
    height: 50px;
    background: var(--gray);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    transition: all 0.3s;
}

.timeline-step.completed .timeline-icon {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.timeline-step.current .timeline-icon {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.timeline-title {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
}

.timeline-date {
    display: block;
    font-size: 0.65rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Grille d'informations */
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.info-card {
    background: var(--gray-light);
    border-radius: 16px;
    overflow: hidden;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px;
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid var(--border);
}

.info-card-header h3 {
    font-size: 1rem;
    margin-bottom: 0;
}

.info-card-body {
    padding: 16px;
}

.payment-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.payment-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.payment-status-badge.paid {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.payment-status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.transaction-id {
    font-size: 0.7rem;
    color: var(--text-muted);
    word-break: break-all;
}

.summary-card .summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.summary-card .summary-row.discount {
    color: #10b981;
}

.summary-card .summary-row.total {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
    font-size: 1.1rem;
}

/* Articles avec images */
.items-section {
    margin-bottom: 30px;
}

.items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.items-count {
    background: var(--gray-light);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.items-table-container {
    overflow-x: auto;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table th {
    text-align: left;
    padding: 16px 12px;
    background: var(--gray-light);
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.items-table td {
    padding: 16px 12px;
    border-bottom: 1px solid var(--border);
}

.product-cell {
    min-width: 280px;
}

.product-info-cell {
    display: flex;
    align-items: center;
    gap: 16px;
}

.product-image-cell {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.product-image-cell img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image-cell i {
    font-size: 1.5rem;
    color: rgba(255,255,255,0.5);
}

.product-name-cell {
    font-weight: 500;
    margin-bottom: 4px;
}

.product-link {
    font-size: 0.7rem;
    color: var(--primary-light);
    text-decoration: none;
}

.quantity-cell {
    text-align: center;
}

.total-cell {
    font-weight: 600;
    color: var(--primary-light);
}

/* Notes */
.notes-section {
    background: var(--gray-light);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
}

.notes-section h3 {
    margin-bottom: 12px;
    font-size: 1rem;
}

.notes-section p {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Actions */
.details-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

/* Responsive */
@media (max-width: 768px) {
    .details-card {
        padding: 20px;
    }
    
    .details-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timeline-steps {
        flex-direction: column;
        gap: 16px;
    }
    
    .timeline-steps::before {
        display: none;
    }
    
    .timeline-step {
        display: flex;
        align-items: center;
        gap: 16px;
        text-align: left;
    }
    
    .timeline-icon {
        margin: 0;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .details-actions {
        justify-content: stretch;
    }
    
    .details-actions .btn-primary,
    .details-actions .btn-secondary,
    .details-actions .btn-outline {
        flex: 1;
        text-align: center;
        justify-content: center;
    }
    
    .product-info-cell {
        flex-direction: column;
        text-align: center;
    }
    
    .product-image-cell {
        margin-bottom: 8px;
    }
}

@media (max-width: 480px) {
    .payment-info {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Impression */
@media print {
    .header, .footer, .back-link, .details-actions, .order-timeline {
        display: none;
    }
    
    .details-card {
        background: white;
        color: black;
        padding: 0;
    }
    
    .order-status-badge {
        border: 1px solid #ccc;
        background: #f5f5f5 !important;
        color: #333 !important;
    }
    
    .product-image-cell {
        background: #f0f0f0;
    }
}
</style>

<script>
function trackOrder(orderNumber) {
    // Simulation de suivi de colis
    window.open(`https://www.laposte.fr/outils/suivre-vos-envois?code=${orderNumber}`, '_blank');
}
</script>

<?php require_once 'includes/footer.php'; ?>