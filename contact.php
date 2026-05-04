<?php
// contact.php - Page de contact
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } else {
        // Envoi du message (simulation)
        $to = 'contact@marsshop.com';
        $email_subject = "Contact Mars Shop - $subject";
        $email_body = "
            Nom: $name\n
            Email: $email\n
            Sujet: $subject\n
            Message:\n$message
        ";
        $headers = "Reply-To: $email\r\n";
        
        if (sendEmail($to, $email_subject, $email_body)) {
            $success = true;
        } else {
            $error = 'Erreur lors de l\'envoi du message. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Mars Shop</title>
    <style>
        :root {
            --primary: #c14432;
            --primary-dark: #8b3a2b;
            --primary-light: #e8755a;
            --dark: #0f0f14;
            --gray: #1a1a24;
            --gray-light: #2a2a35;
            --text: #ffffff;
            --text-muted: #a0a0b0;
            --border: #2a2a35;
            --success: #10b981;
            --error: #ef4444;
        }
        
        .contact-page {
            padding: 40px 0;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .contact-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .contact-header p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
        }
        
        /* Informations de contact */
        .contact-info {
            background: var(--gray);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--border);
        }
        
        .contact-info h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .info-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .info-icon i {
            font-size: 1.2rem;
        }
        
        .info-content h3 {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .info-content p, .info-content a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .info-content a:hover {
            color: var(--primary-light);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: var(--gray-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        
        .social-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Formulaire */
        .contact-form-container {
            background: var(--gray);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--border);
        }
        
        .contact-form-container h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: var(--gray-light);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(193,68,50,0.3);
        }
        
        .alert-success {
            background: rgba(16,185,129,0.15);
            border: 1px solid var(--success);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid var(--error);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: var(--error);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .map-section {
            margin-top: 50px;
            background: var(--gray);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: 1px solid var(--border);
        }
        
        .map-placeholder {
            background: var(--gray-light);
            border-radius: 16px;
            padding: 60px 20px;
            margin-top: 20px;
        }
        
        .map-placeholder i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 15px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .contact-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<main>
    <div class="contact-page">
        <div class="container">
            <div class="contact-header">
                <h1><i class="fas fa-envelope"></i> Contactez-nous</h1>
                <p>Une question ? Un problème ? Notre équipe est là pour vous aider</p>
            </div>
            
            <div class="contact-grid">
                <!-- Informations de contact -->
                <div class="contact-info">
                    <h2><i class="fas fa-info-circle"></i> Nos coordonnées</h2>
                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h3>Adresse</h3>
                                <p>123 Avenue de l'Espace<br>75001 Paris, France</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="info-content">
                                <h3>Téléphone</h3>
                                <p><a href="tel:+33123456789">+33 (0)1 23 45 67 89</a></p>
                                <p style="font-size: 0.7rem;">Lun-Ven, 9h-18h</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <h3>Email</h3>
                                <p><a href="mailto:contact@marsshop.com">contact@marsshop.com</a></p>
                                <p><a href="mailto:support@marsshop.com">support@marsshop.com</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-twitter"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                
                <!-- Formulaire de contact -->
                <div class="contact-form-container">
                    <h2><i class="fas fa-paper-plane"></i> Envoyez-nous un message</h2>
                    
                    <?php if($success): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom complet *</label>
                                <input type="text" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required value="<?php echo $_POST['email'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Sujet *</label>
                            <select name="subject" required>
                                <option value="">-- Sélectionnez un sujet --</option>
                                <option value="Question produit" <?php echo ($_POST['subject'] ?? '') == 'Question produit' ? 'selected' : ''; ?>>Question produit</option>
                                <option value="Commande" <?php echo ($_POST['subject'] ?? '') == 'Commande' ? 'selected' : ''; ?>>Commande</option>
                                <option value="Livraison" <?php echo ($_POST['subject'] ?? '') == 'Livraison' ? 'selected' : ''; ?>>Livraison</option>
                                <option value="Retour produit" <?php echo ($_POST['subject'] ?? '') == 'Retour produit' ? 'selected' : ''; ?>>Retour produit</option>
                                <option value="Partenariat" <?php echo ($_POST['subject'] ?? '') == 'Partenariat' ? 'selected' : ''; ?>>Partenariat</option>
                                <option value="Autre" <?php echo ($_POST['subject'] ?? '') == 'Autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" rows="5" required placeholder="Décrivez votre demande..."><?php echo $_POST['message'] ?? ''; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Carte -->
            <div class="map-section">
                <h2><i class="fas fa-map"></i> Notre localisation</h2>
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <p>123 Avenue de l'Espace, 75001 Paris, France</p>
                    <p style="font-size: 0.8rem; margin-top: 10px;">
                        <i class="fas fa-subway"></i> Métro : Ligne 1, station "Espace"
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>