<?php
// includes/functions.php - Version clean et optimisée (sans AMEA)

// ============================================
// AUTHENTIFICATION
// ============================================

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirige si non connecté
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirige si non admin
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// ============================================
// FORMATAGE
// ============================================

/**
 * Formate le prix
 */
function formatPrice(float $price): string {
    return number_format($price, 2, ',', ' ') . ' €';
}

/**
 * Formate la date
 */
function formatDate(string $date, string $format = 'd/m/Y H:i'): string {
    return date($format, strtotime($date));
}

/**
 * Nettoie une chaîne (XSS protection)
 */
function clean(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Tronque un texte
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Génère un slug SEO-friendly
 */
function createSlug(string $string): string {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Génère un numéro de commande unique
 */
function generateOrderNumber(): string {
    return 'MARS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// ============================================
// GESTION DU PANIER
// ============================================

/**
 * Ajoute un produit au panier
 */
function addToCart(int $product_id, int $quantity = 1): void {
    if (isLoggedIn()) {
        global $conn;
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_qty, $existing['id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
        }
    } else {
        // Panier en session pour les non connectés
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        
        $found = false;
        foreach ($_SESSION['guest_cart'] as &$item) {
            if ($item['product_id'] === $product_id) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['guest_cart'][] = [
                'product_id' => $product_id,
                'quantity' => $quantity
            ];
        }
    }
}

/**
 * Récupère le nombre d'articles dans le panier
 */
function getCartCount(): int {
    $count = 0;
    
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $count = (int)($result['total'] ?? 0);
    } else {
        if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $item) {
                $count += $item['quantity'];
            }
        }
    }
    
    return $count;
}

/**
 * Supprime un produit du panier
 */
function removeFromCart(int $cart_id, bool $is_guest = false): void {
    if (isLoggedIn() && !$is_guest) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
    } else {
        if (isset($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $key => $item) {
                if ($item['product_id'] === $cart_id) {
                    unset($_SESSION['guest_cart'][$key]);
                    $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
                    break;
                }
            }
        }
    }
}

/**
 * Met à jour la quantité d'un produit dans le panier
 */
function updateCartQuantity(int $cart_id, int $quantity, bool $is_guest = false): void {
    if ($quantity <= 0) {
        removeFromCart($cart_id, $is_guest);
        return;
    }
    
    if (isLoggedIn() && !$is_guest) {
        global $conn;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
    } else {
        if (isset($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as &$item) {
                if ($item['product_id'] === $cart_id) {
                    $item['quantity'] = $quantity;
                    break;
                }
            }
        }
    }
}

/**
 * Synchronise le panier guest vers la BDD après connexion
 */
function syncCartAfterLogin(int $user_id): void {
    if (!isset($_SESSION['guest_cart']) || empty($_SESSION['guest_cart'])) {
        return;
    }
    
    global $conn;
    
    foreach ($_SESSION['guest_cart'] as $item) {
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $item['product_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_qty = $existing['quantity'] + $item['quantity'];
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_qty, $existing['id']]);
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $item['product_id'], $item['quantity']]);
        }
    }
    
    unset($_SESSION['guest_cart']);
}

/**
 * Récupère les articles du panier
 */
function getCartItems(): array {
    $items = [];
    
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("
            SELECT c.id as cart_id, c.quantity, p.* 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll();
    } else {
        if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
            global $conn;
            $ids = array_column($_SESSION['guest_cart'], 'product_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $products = $stmt->fetchAll();
            
            foreach ($products as $product) {
                foreach ($_SESSION['guest_cart'] as $item) {
                    if ($item['product_id'] === $product['id']) {
                        $product['cart_id'] = 'guest_' . $product['id'];
                        $product['quantity'] = $item['quantity'];
                        $items[] = $product;
                        break;
                    }
                }
            }
        }
    }
    
    return $items;
}

/**
 * Vider le panier
 */
function clearCart(): void {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        unset($_SESSION['guest_cart']);
    }
}

// ============================================
// GESTION DE LA WISHLIST
// ============================================

/**
 * Ajoute à la wishlist
 */
function addToWishlist(int $product_id): void {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        if (!isset($_SESSION['guest_wishlist'])) {
            $_SESSION['guest_wishlist'] = [];
        }
        if (!in_array($product_id, $_SESSION['guest_wishlist'])) {
            $_SESSION['guest_wishlist'][] = $product_id;
        }
    }
}

/**
 * Supprime de la wishlist
 */
function removeFromWishlist(int $product_id): void {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
    } else {
        if (isset($_SESSION['guest_wishlist'])) {
            $key = array_search($product_id, $_SESSION['guest_wishlist']);
            if ($key !== false) {
                unset($_SESSION['guest_wishlist'][$key]);
                $_SESSION['guest_wishlist'] = array_values($_SESSION['guest_wishlist']);
            }
        }
    }
}

/**
 * Récupère le nombre d'articles dans la wishlist
 */
function getWishlistCount(): int {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }
    return isset($_SESSION['guest_wishlist']) ? count($_SESSION['guest_wishlist']) : 0;
}

/**
 * Vérifie si produit dans wishlist
 */
function isInWishlist(int $product_id): bool {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        return (bool)$stmt->fetch();
    }
    return isset($_SESSION['guest_wishlist']) && in_array($product_id, $_SESSION['guest_wishlist']);
}

// ============================================
// MESSAGES FLASH
// ============================================

/**
 * Définit un message flash
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Récupère et efface le message flash
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================
// EMAIL
// ============================================

/**
 * Envoie un email (simulation)
 */
function sendEmail(string $to, string $subject, string $body): bool {
    error_log("Email envoyé à $to: $subject");
    return true;
}