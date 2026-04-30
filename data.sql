-- ============================================
-- MARS SHOP - BASE DE DONNÉES COMPLÈTE
-- VERSION PROPR ET OPTIMISÉE
-- ============================================

-- Supprimer et recréer la base de données
DROP DATABASE IF EXISTS mars_shop;
CREATE DATABASE mars_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mars_shop;

-- ============================================
-- TABLE users
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    address TEXT,
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    amea_id VARCHAR(255) UNIQUE DEFAULT NULL,
    amea_avatar VARCHAR(500) DEFAULT NULL,
    auth_provider VARCHAR(50) DEFAULT 'local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_amea_id (amea_id)
);

-- ============================================
-- TABLE products
-- ============================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) DEFAULT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255),
    images TEXT,
    category VARCHAR(100),
    tags VARCHAR(255),
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_price (price),
    INDEX idx_featured (is_featured),
    FULLTEXT INDEX idx_search (name, description, category)
);

-- ============================================
-- TABLE cart
-- ============================================
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id),
    INDEX idx_user (user_id)
);

-- ============================================
-- TABLE orders
-- ============================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    coupon_code VARCHAR(50),
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100),
    shipping_zip VARCHAR(20),
    shipping_country VARCHAR(100) DEFAULT 'France',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number),
    INDEX idx_created (created_at)
);

-- ============================================
-- TABLE order_items
-- ============================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- ============================================
-- TABLE payments
-- ============================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50),
    card_last4 VARCHAR(4),
    transaction_id VARCHAR(100) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    response_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_transaction (transaction_id)
);

-- ============================================
-- TABLE wishlist
-- ============================================
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    INDEX idx_user (user_id)
);

-- ============================================
-- TABLE reviews
-- ============================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    comment TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_rating (rating),
    INDEX idx_created (created_at)
);

-- ============================================
-- TABLE coupons
-- ============================================
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    usage_limit INT DEFAULT 1,
    used_count INT DEFAULT 0,
    per_user_limit INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_valid (valid_from, valid_to),
    INDEX idx_active (is_active)
);

-- ============================================
-- TABLE categories
-- ============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug)
);

-- ============================================
-- INSERTION DES DONNÉES DE BASE
-- ============================================

-- Catégories
INSERT INTO categories (name, slug, sort_order) VALUES
('Vêtements', 'clothing', 1),
('Accessoires', 'accessories', 2),
('Alimentation', 'food', 3),
('Décoration', 'decor', 4),
('Jeux', 'toys', 5);

-- Produits
INSERT INTO products (name, slug, description, short_description, price, compare_price, stock, image, category, is_featured) VALUES
('T-Shirt Mars Rock', 'tshirt-mars-rock', 'T-shirt premium avec design paysage martien', 'T-shirt confortable en coton biologique', 29.99, 39.99, 50, 'tshirt.jpg', 'Vêtements', TRUE),
('Hoodie Planète Rouge', 'hoodie-planete-rouge', 'Hoodie chaud avec logo Mars', 'Hoodie en coton polaire', 59.99, 79.99, 30, 'hoodie.jpg', 'Vêtements', TRUE),
('Casquette Explorer', 'casquette-explorer', 'Casquette ajustable avec patch mission Mars', 'Casquette en coton respirant', 19.99, 29.99, 100, 'cap.jpg', 'Accessoires', FALSE),
('Barres Alimentaires Spatiales', 'barres-alimentaires-spatiales', 'Pack de 5 barres nutritives astronaute', 'Nourriture pour aventuriers', 24.99, 34.99, 200, 'food.jpg', 'Alimentation', FALSE),
('Poster Carte Mars', 'poster-carte-mars', 'Poster haute qualité de Mars', 'Poster 50x70cm', 14.99, 24.99, 75, 'poster.jpg', 'Décoration', FALSE),
('Maquette Rover', 'maquette-rover', 'Kit de construction du rover martien', 'Modèle à monter', 49.99, 69.99, 25, 'rover.jpg', 'Jeux', TRUE),
('T-Shirt Mission Mars', 'tshirt-mission-mars', 'T-shirt officiel mission Mars', 'Design mission officielle', 34.99, 44.99, 45, 'mission_tshirt.jpg', 'Vêtements', FALSE),
('Sweat Planète Rouge', 'sweat-planete-rouge', 'Sweat-shirt doux avec paysage martien', 'Sweat confortable', 69.99, 89.99, 25, 'sweatshirt.jpg', 'Vêtements', FALSE),
('Réplique Casque Spatial', 'replique-casque-spatial', 'Réplique à l\'échelle 1:1 du casque', 'Collectionneur exclusif', 199.99, 299.99, 10, 'helmet.jpg', 'Accessoires', TRUE),
('Kit Fusée Martienne', 'kit-fusee-martienne', 'Kit de fusée 500 pièces', 'Construisez votre fusée', 89.99, 119.99, 15, 'rocket.jpg', 'Jeux', FALSE),
('Tasse Carte Mars', 'tasse-carte-mars', 'Tasse avec carte détaillée de Mars', 'Céramique haute qualité', 19.99, 29.99, 75, 'mug.jpg', 'Accessoires', FALSE),
('Peluche Rover', 'peluche-rover', 'Peluche douce du rover martien', 'Jouet en peluche', 24.99, 34.99, 60, 'plush.jpg', 'Jeux', FALSE);

-- Admin user (password: Admin123!)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@marsshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'admin');

-- Utilisateur test (password: User123!)
INSERT INTO users (username, email, password, full_name, address, phone) VALUES
('john_doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '123 Rue de l\'Espace, Paris', '0612345678');

-- Coupons
INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, valid_from, valid_to, usage_limit) VALUES
('BIENVENUE10', '10% de réduction sur votre première commande', 'percentage', 10.00, 50.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 100),
('MARS20', '20€ de réduction sur votre commande', 'fixed', 20.00, 100.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 50),
('MARS25', '25% sur la collection Mars', 'percentage', 25.00, 80.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 30);

-- ============================================
-- COMMANDES DE TEST (CORRIGÉES)
-- ============================================

-- Génération des numéros de commande avec DATE_FORMAT
SET @order_num1 = CONCAT('MARS-', DATE_FORMAT(NOW(), '%Y%m%d'), '-001');
SET @order_num2 = CONCAT('MARS-', DATE_FORMAT(NOW(), '%Y%m%d'), '-002');
SET @txn1 = CONCAT('TXN-MARS-', REPLACE(UUID(), '-', ''));
SET @txn2 = CONCAT('TXN-MARS-', REPLACE(UUID(), '-', ''));

-- Insertion des commandes
INSERT INTO orders (user_id, order_number, subtotal, discount, shipping_cost, tax, total_amount, status, payment_status, shipping_address) VALUES
(2, @order_num1, 119.97, 0, 0, 23.99, 143.96, 'delivered', 'paid', '123 Rue de l\'Espace, Paris'),
(2, @order_num2, 149.98, 0, 0, 30.00, 179.98, 'processing', 'paid', '123 Rue de l\'Espace, Paris');

-- Insertion des articles
INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES
(1, 1, 'T-Shirt Mars Rock', 2, 29.99, 59.98),
(1, 3, 'Casquette Explorer', 2, 29.99, 59.99),
(2, 6, 'Maquette Rover', 1, 49.99, 49.99),
(2, 10, 'Kit Fusée Martienne', 2, 49.99, 99.99);

-- Insertion des paiements
INSERT INTO payments (order_id, payment_method, card_last4, transaction_id, amount, status) VALUES
(1, 'cash', NULL, @txn1, 143.96, 'success'),
(2, 'papay', NULL, @txn2, 179.98, 'success');

-- ============================================
-- FIN DU SCRIPT
-- ============================================


-- ============================================
-- MARS SHOP - AJOUT DES TABLES POUR PAIEMENTS
-- ============================================

-- Table des méthodes de paiement (configurable par admin)
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    logo VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);

-- Table des comptes Mobile Money (configurable par admin)
CREATE TABLE mobile_money_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator ENUM('airtel', 'mvola', 'orange') NOT NULL UNIQUE,
    operator_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    account_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des transactions Mobile Money (attente validation)
CREATE TABLE mobile_money_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    operator ENUM('airtel', 'mvola', 'orange') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    UNIQUE KEY unique_transaction (transaction_id, operator)
);

-- Ajouter les méthodes de paiement par défaut
INSERT INTO payment_methods (name, display_name, description, logo, sort_order, settings) VALUES
('credit_card', 'Carte bancaire', 'Paiement sécurisé par carte bancaire (Visa, Mastercard)', 'cc-visa', 1, '{"api_key": "", "enabled": true}'),
('paypal', 'PayPal', 'Paiement via votre compte PayPal', 'paypal', 2, '{"client_id": "", "enabled": true}'),
('mobile_money', 'Mobile Money', 'Paiement par Mobile Money (Airtel Money, Mvola, Orange Money)', 'mobile-alt', 3, '{"enabled": true}'),
('cash', 'Paiement à la livraison', 'Payez en espèces à la réception de votre commande', 'money-bill-wave', 4, '{"enabled": true}');

-- Ajouter les comptes Mobile Money par défaut
INSERT INTO mobile_money_accounts (operator, operator_name, phone_number, account_name, is_active) VALUES
('airtel', 'Airtel Money', '+225 07 00 00 00 01', 'Mars Shop Airtel', TRUE),
('mvola', 'Mvola', '+261 34 00 000 01', 'Mars Shop Mvola', TRUE),
('orange', 'Orange Money', '+225 07 00 00 00 02', 'Mars Shop Orange', TRUE);