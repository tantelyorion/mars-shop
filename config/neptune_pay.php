<?php
/**
 * Neptune Pay - Configuration
 * Intégration avec Mars Shop
 */

// ============================================
// ENVIRONNEMENT
// ============================================
define('NEPTUNE_ENVIRONMENT', 'production'); // 'sandbox' ou 'production'

// ============================================
// URLS DE L'API
// ============================================
if (NEPTUNE_ENVIRONMENT === 'sandbox') {
    define('NEPTUNE_API_URL', 'https://sandbox.neptunepay.com/api/');
    define('NEPTUNE_PAYMENT_URL', 'https://sandbox.neptunepay.com/user/payment.php');
} else {
    define('NEPTUNE_API_URL', 'https://neptunepay.com/api/');
    define('NEPTUNE_PAYMENT_URL', 'https://neptunepay.com/user/payment.php');
}

// ============================================
// CLÉS API DU MARCHAND
// ============================================
// À remplacer par vos vraies clés après enregistrement
define('NEPTUNE_API_KEY', 'votre_api_key_ici');
define('NEPTUNE_API_SECRET', 'votre_api_secret_ici');

// ============================================
// WEBHOOK
// ============================================
define('NEPTUNE_WEBHOOK_SECRET', 'mars_shop_webhook_secret_2026');
define('NEPTUNE_WEBHOOK_URL', 'https://mars-shop.com/payment/neptune-webhook.php');

// ============================================
// PARAMÈTRES DE PAIEMENT
// ============================================
define('NEPTUNE_PAYMENT_EXPIRES_IN', 600); // 10 minutes
define('NEPTUNE_DEFAULT_CURRENCY', 'EUR'); // Devise par défaut

// ============================================
// MAPPING DES DEVISES
// ============================================
// Neptune Pay utilise des codes ISO, Mars Shop utilise EUR
// Si vous vendez en MGA, KES, XOF, adaptez
$NEPTUNE_CURRENCY_MAP = [
    'EUR' => 'EUR',
    'MGA' => 'MGA',
    'KES' => 'KES',
    'XOF' => 'XOF',
    'GHS' => 'GHS'
];

// ============================================
// CALLBACK URL
// ============================================
define('NEPTUNE_CALLBACK_URL', 'https://mars-shop.com/payment/neptune-callback.php');