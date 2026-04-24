<?php
// oauth_amea_callback.php - Callback après connexion AMEA
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code)) {
    header('Location: login.php?error=oauth_failed');
    exit();
}

// Configuration OAuth AMEA
$client_id = 'VOTRE_CLIENT_ID'; // À remplacer par votre client ID AMEA
$client_secret = 'VOTRE_CLIENT_SECRET'; // À remplacer par votre client secret
$redirect_uri = 'https://mars-shop.com/oauth_amea_callback.php';
$token_url = 'https://amea.chaudly.com/api/oauth/token.php';
$userinfo_url = 'https://amea.chaudly.com/api/oauth/userinfo.php';

// Échanger le code contre un token
$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: login.php?error=oauth_failed');
    exit();
}

$token_data = json_decode($response, true);
$access_token = $token_data['access_token'] ?? '';

if (empty($access_token)) {
    header('Location: login.php?error=oauth_failed');
    exit();
}

// Récupérer les informations utilisateur
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    header('Location: login.php?error=oauth_failed');
    exit();
}

$amea_user = json_decode($response, true);

if (empty($amea_user) || empty($amea_user['id'])) {
    header('Location: login.php?error=oauth_failed');
    exit();
}

$conn = getConnection();

// Vérifier si l'utilisateur existe déjà via AMEA ID
$stmt = $conn->prepare("SELECT * FROM users WHERE amea_id = ?");
$stmt->execute([$amea_user['id']]);
$user = $stmt->fetch();

if ($user) {
    // Utilisateur existant - connexion directe
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    syncCartAfterLogin($user['id']);
    
    $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
    unset($_SESSION['redirect_after_login']);
    header("Location: $redirect");
    exit();
}

// Vérifier si l'email existe déjà (compte local)
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$amea_user['email']]);
$existing_user = $stmt->fetch();

if ($existing_user) {
    // Lier le compte AMEA au compte existant
    $stmt = $conn->prepare("UPDATE users SET amea_id = ?, amea_avatar = ?, auth_provider = 'amea' WHERE id = ?");
    $stmt->execute([$amea_user['id'], $amea_user['avatar'], $existing_user['id']]);
    
    $_SESSION['user_id'] = $existing_user['id'];
    $_SESSION['username'] = $existing_user['username'];
    $_SESSION['email'] = $existing_user['email'];
    $_SESSION['role'] = $existing_user['role'];
    
    syncCartAfterLogin($existing_user['id']);
    
    $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
    unset($_SESSION['redirect_after_login']);
    header("Location: $redirect");
    exit();
}

// Créer un nouveau compte
$username = $amea_user['username'];
$base_username = $username;
$counter = 1;

// Vérifier si le username existe déjà
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
while ($stmt->execute([$username]) && $stmt->fetch()) {
    $username = $base_username . '_' . $counter++;
}

// Générer un mot de passe aléatoire (l'utilisateur utilisera AMEA pour se connecter)
$random_password = bin2hex(random_bytes(16));
$hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (username, email, password, full_name, amea_id, amea_avatar, auth_provider, is_active) 
    VALUES (?, ?, ?, ?, ?, ?, 'amea', 1)
");
$stmt->execute([
    $username,
    $amea_user['email'],
    $hashed_password,
    $amea_user['username'],
    $amea_user['id'],
    $amea_user['avatar']
]);

$user_id = $conn->lastInsertId();

$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $username;
$_SESSION['email'] = $amea_user['email'];
$_SESSION['role'] = 'user';

syncCartAfterLogin($user_id);

$redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
unset($_SESSION['redirect_after_login']);
header("Location: $redirect");
exit();
?>