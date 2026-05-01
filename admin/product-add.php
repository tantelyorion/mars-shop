<?php
// admin/product-add.php - Ajouter un produit avec gestion d'images
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$conn = getConnection();
$error = '';
$success = '';

// Récupérer les catégories existantes
$stmt = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$existing_categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0f0f14;
            color: #ffffff;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            width: 280px;
            background: #0a0a0e;
            border-right: 1px solid #2a2a35;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #2a2a35;
        }
        
        .sidebar-header .logo {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .sidebar-header .logo span {
            color: #c14432;
        }
        
        .sidebar-nav {
            padding: 20px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #a0a0b0;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(193, 68, 50, 0.15);
            color: #c14432;
        }
        
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #2a2a35;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        
        .page-title p {
            color: #a0a0b0;
        }
        
        .card {
            background: #1a1a24;
            border: 1px solid #2a2a35;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #2a2a35;
            background: rgba(0,0,0,0.2);
        }
        
        .card-header h3 {
            font-size: 1rem;
        }
        
        .card-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: #2a2a35;
            border: 1px solid #3a3a45;
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #c14432;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        /* Image upload */
        .image-upload-area {
            border: 2px dashed #3a3a45;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #2a2a35;
        }
        
        .image-upload-area:hover {
            border-color: #c14432;
            background: rgba(193,68,50,0.05);
        }
        
        .image-upload-area i {
            font-size: 3rem;
            color: #a0a0b0;
            margin-bottom: 10px;
        }
        
        .image-upload-area p {
            color: #a0a0b0;
            font-size: 0.85rem;
        }
        
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 20px;
        }
        
        .image-preview-item {
            position: relative;
            width: 120px;
            height: 120px;
            background: #2a2a35;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #3a3a45;
        }
        
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-item .primary-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #10b981;
            color: white;
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .image-preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239,68,68,0.9);
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.7rem;
        }
        
        .image-preview-item .set-primary {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            cursor: pointer;
        }
        
        .upload-progress {
            display: none;
            margin-top: 10px;
            height: 4px;
            background: #3a3a45;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .upload-progress .progress-bar {
            width: 0%;
            height: 100%;
            background: #c14432;
            transition: width 0.3s;
        }
        
        .image-list-hidden {
            display: none;
        }
        
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid #ef4444;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: #ef4444;
        }
        
        .btn-submit {
            background: #c14432;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .btn-back {
            background: #2a2a35;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }
        
        .text-muted {
            color: #a0a0b0;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            .admin-sidebar.active {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-planet-ringed"></i>
                Mars<span>Admin</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
            <a href="products.php" class="nav-item active"><i class="fas fa-box"></i> Produits</a>
            <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="payments.php" class="nav-item"><i class="fas fa-credit-card"></i> Paiements</a>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </aside>
    
    <main class="admin-main">
        <div class="admin-header">
            <div class="page-title">
                <h1>Ajouter un produit</h1>
                <p>Créez un nouveau produit dans votre catalogue</p>
            </div>
            <div class="admin-user">
                <button class="mobile-toggle" id="mobileToggle" style="background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <form method="POST" id="productForm" enctype="multipart/form-data">
            <!-- Informations produit -->
            <div class="card">
                <div class="card-header">
                    <h3>Informations produit</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom du produit *</label>
                            <input type="text" name="name" id="productName" required>
                        </div>
                        <div class="form-group">
                            <label>Slug (URL)</label>
                            <input type="text" name="slug" id="productSlug" placeholder="laissez vide pour auto-génération">
                            <div class="text-muted">Identifiant unique pour l'URL du produit</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prix (€) *</label>
                            <input type="number" step="0.01" name="price" required>
                        </div>
                        <div class="form-group">
                            <label>Prix barré (optionnel)</label>
                            <input type="number" step="0.01" name="compare_price">
                            <div class="text-muted">Affiche le prix original barré</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock *</label>
                            <input type="number" name="stock" value="0" required>
                        </div>
                        <div class="form-group">
                            <label>Catégorie *</label>
                            <input type="text" name="category" list="categories" required>
                            <datalist id="categories">
                                <?php foreach($existing_categories as $cat): ?>
                                <option value="<?php echo clean($cat['category']); ?>">
                                <?php endforeach; ?>
                                <option value="Vêtements"><option value="Accessoires"><option value="Alimentation"><option value="Décoration"><option value="Jeux">
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tags (séparés par des virgules)</label>
                        <input type="text" name="tags" placeholder="ex: mars, espace, rover">
                    </div>
                    
                    <div class="form-group">
                        <label>Description courte</label>
                        <textarea name="short_description" rows="2"></textarea>
                        <div class="text-muted">Apparaît dans les listes de produits</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description complète</label>
                        <textarea name="description" rows="6"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_featured" id="is_featured">
                            <label for="is_featured">Mettre en vedette</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" checked>
                            <label for="is_active">Produit actif</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gestion des images -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-images"></i> Images du produit (max 10)</h3>
                </div>
                <div class="card-body">
                    <div id="imageUploadArea" class="image-upload-area">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Cliquez ou glissez-déposez des images ici</p>
                        <p style="font-size: 0.7rem;">JPG, PNG, GIF, WEBP (max 5MB)</p>
                    </div>
                    <input type="file" id="imageInput" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar"></div>
                    </div>
                    <div id="imagePreviewContainer" class="image-preview-container"></div>
                    <div id="imageDataContainer" class="image-list-hidden"></div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="products.php" class="btn-back">Annuler</a>
                <button type="submit" class="btn-submit">Ajouter le produit</button>
            </div>
        </form>
    </main>
</div>

<script>
// Génération automatique du slug
const productName = document.getElementById('productName');
const productSlug = document.getElementById('productSlug');

productName.addEventListener('input', function() {
    if (!productSlug.value || productSlug.value === '' || productSlug.value === generateSlug(productSlug.getAttribute('data-old') || '')) {
        const slug = generateSlug(this.value);
        productSlug.value = slug;
        productSlug.setAttribute('data-old', slug);
    }
});

function generateSlug(str) {
    str = str.toLowerCase();
    str = str.replace(/[éèêë]/g, 'e');
    str = str.replace(/[àâä]/g, 'a');
    str = str.replace(/[ôö]/g, 'o');
    str = str.replace(/[ç]/g, 'c');
    str = str.replace(/[^a-z0-9\s-]/g, '');
    str = str.replace(/\s+/g, '-');
    str = str.replace(/-+/g, '-');
    return str.trim();
}

// Gestion des images
const imageUploadArea = document.getElementById('imageUploadArea');
const imageInput = document.getElementById('imageInput');
const imagePreviewContainer = document.getElementById('imagePreviewContainer');
const uploadProgress = document.getElementById('uploadProgress');
const progressBar = uploadProgress.querySelector('.progress-bar');

let uploadedImages = [];
let uploading = false;

imageUploadArea.addEventListener('click', () => imageInput.click());

imageUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    imageUploadArea.style.borderColor = '#c14432';
});

imageUploadArea.addEventListener('dragleave', (e) => {
    e.preventDefault();
    imageUploadArea.style.borderColor = '#3a3a45';
});

imageUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    imageUploadArea.style.borderColor = '#3a3a45';
    const files = Array.from(e.dataTransfer.files);
    uploadImages(files);
});

imageInput.addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    uploadImages(files);
    imageInput.value = '';
});

async function uploadImages(files) {
    if (uploading) return;
    if (uploadedImages.length + files.length > 10) {
        alert('Maximum 10 images par produit');
        return;
    }
    
    uploading = true;
    uploadProgress.style.display = 'block';
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const formData = new FormData();
        formData.append('image', file);
        
        progressBar.style.width = `${((i) / files.length) * 100}%`;
        
        try {
            const response = await fetch('ajax-upload-image.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                uploadedImages.push({
                    filename: result.filename,
                    path: result.path,
                    is_primary: uploadedImages.length === 0
                });
                displayImages();
            } else {
                alert(`Erreur: ${result.error}`);
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Erreur lors de l\'upload');
        }
    }
    
    progressBar.style.width = '100%';
    setTimeout(() => {
        uploadProgress.style.display = 'none';
        progressBar.style.width = '0%';
    }, 500);
    uploading = false;
}

function displayImages() {
    imagePreviewContainer.innerHTML = '';
    
    uploadedImages.forEach((img, index) => {
        const div = document.createElement('div');
        div.className = 'image-preview-item';
        div.innerHTML = `
            <img src="../${img.path}" alt="Image produit">
            ${img.is_primary ? '<div class="primary-badge">Principale</div>' : ''}
            <button type="button" class="remove-image" data-index="${index}">&times;</button>
            ${!img.is_primary ? `<button type="button" class="set-primary" data-index="${index}">Définir principale</button>` : ''}
            <input type="hidden" name="images[]" value="${img.filename}">
            ${img.is_primary ? `<input type="hidden" name="primary_image" value="${img.filename}">` : ''}
        `;
        
        div.querySelector('.remove-image').addEventListener('click', (e) => {
            e.stopPropagation();
            uploadedImages.splice(index, 1);
            if (uploadedImages.length > 0 && !uploadedImages.some(i => i.is_primary)) {
                uploadedImages[0].is_primary = true;
            }
            displayImages();
        });
        
        if (!img.is_primary) {
            div.querySelector('.set-primary').addEventListener('click', (e) => {
                e.stopPropagation();
                uploadedImages.forEach((i, idx) => {
                    i.is_primary = (idx === index);
                });
                displayImages();
            });
        }
        
        imagePreviewContainer.appendChild(div);
    });
    
    // Mettre à jour les champs cachés
    const imageDataContainer = document.getElementById('imageDataContainer');
    imageDataContainer.innerHTML = '';
    uploadedImages.forEach(img => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'images[]';
        input.value = img.filename;
        imageDataContainer.appendChild(input);
        
        if (img.is_primary) {
            const primaryInput = document.createElement('input');
            primaryInput.type = 'hidden';
            primaryInput.name = 'primary_image';
            primaryInput.value = img.filename;
            imageDataContainer.appendChild(primaryInput);
        }
    });
}

// Soumission du formulaire
document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Ajouter les images
    uploadedImages.forEach(img => {
        formData.append('images[]', img.filename);
        if (img.is_primary) {
            formData.append('primary_image', img.filename);
        }
    });
    
    const submitBtn = this.querySelector('.btn-submit');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('product-save.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'products.php?success=1';
        } else {
            alert(result.error || 'Erreur lors de la création');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Erreur de connexion');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Mobile toggle
const toggle = document.getElementById('mobileToggle');
const sidebar = document.getElementById('adminSidebar');
if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
}
</script>
</body>
</html>