<?php
// includes/functions.php - Version corrigée sans doublons

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirige si non connecté
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirige si non admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Formate le prix
 */
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}

/**
 * Formate la date
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Ajoute un produit au panier
 */
function addToCart($product_id, $quantity = 1) {
    if (isLoggedIn()) {
        global $conn;
        $user_id = $_SESSION['user_id'];
        
        // Vérifier si le produit existe déjà dans le panier
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
        // Panier en session
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        
        $found = false;
        foreach ($_SESSION['guest_cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
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
function getCartCount() {
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
function removeFromCart($cart_id, $is_guest = false) {
    if (isLoggedIn() && !$is_guest) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
    } else {
        if (isset($_SESSION['guest_cart'])) {
            foreach ($_SESSION['guest_cart'] as $key => $item) {
                if ($item['product_id'] == $cart_id) {
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
function updateCartQuantity($cart_id, $quantity, $is_guest = false) {
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
                if ($item['product_id'] == $cart_id) {
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
function syncCartAfterLogin($user_id) {
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
function getCartItems() {
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
                    if ($item['product_id'] == $product['id']) {
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
function clearCart() {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        unset($_SESSION['guest_cart']);
    }
}

/**
 * Ajoute à la wishlist
 */
function addToWishlist($product_id) {
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
function removeFromWishlist($product_id) {
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
function getWishlistCount() {
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
function isInWishlist($product_id) {
    if (isLoggedIn()) {
        global $conn;
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        return (bool)$stmt->fetch();
    }
    return isset($_SESSION['guest_wishlist']) && in_array($product_id, $_SESSION['guest_wishlist']);
}

/**
 * Nettoie une chaîne
 */
function clean($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Tronque un texte
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Affiche un message flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Génère un slug
 */
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Génère un numéro de commande
 */
function generateOrderNumber() {
    return 'MARS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Envoie un email (simulation)
 */
function sendEmail($to, $subject, $body) {
    error_log("Email envoyé à $to: $subject");
    return true;
}

/**
 * Génère l'URL de connexion AMEA
 */
function getAmeaLoginUrl() {
    return 'https://amea.chaudly.com/oauth_authorize.php?' . http_build_query([
        'client_id' => AMEA_CLIENT_ID,
        'redirect_uri' => AMEA_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'profile email',
        'state' => bin2hex(random_bytes(16))
    ]);
}


?>