<?php
// admin/ajax-upload-image.php - Upload d'image via AJAX
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error_code = $_FILES['image']['error'] ?? 4;
    $errors = [
        1 => 'Fichier trop volumineux',
        2 => 'Fichier trop volumineux',
        3 => 'Upload partiel',
        4 => 'Aucun fichier sélectionné',
        6 => 'Dossier temporaire manquant',
        7 => 'Écriture impossible',
        8 => 'Extension non autorisée'
    ];
    echo json_encode(['success' => false, 'error' => $errors[$error_code] ?? 'Erreur inconnue']);
    exit();
}

// Configuration
$upload_dir = '../uploads/products/';
$max_file_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Vérifier la taille
if ($_FILES['image']['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)']);
    exit();
}

// Vérifier le type MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé (JPEG, PNG, GIF, WEBP)']);
    exit();
}

// Créer le dossier s'il n'existe pas
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Générer un nom unique
$extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Déplacer le fichier
if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    // Optimiser l'image
    optimizeImage($filepath, $mime_type);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => 'uploads/products/' . $filename,
        'url' => '../uploads/products/' . $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
}
exit();

function optimizeImage($filepath, $mime_type) {
    // Redimensionner si trop grande
    list($width, $height) = getimagesize($filepath);
    $max_width = 1200;
    $max_height = 1200;
    
    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        $src = null;
        $dst = imagecreatetruecolor($new_width, $new_height);
        
        switch ($mime_type) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($filepath);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagejpeg($dst, $filepath, 85);
                break;
            case 'image/png':
                $src = imagecreatefrompng($filepath);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagepng($dst, $filepath, 8);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($filepath);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagewebp($dst, $filepath, 85);
                break;
        }
        
        imagedestroy($src);
        imagedestroy($dst);
    }
}
?>