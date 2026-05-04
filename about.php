<?php
// about.php - Page À propos de Mars Shop
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Valeurs par défaut pour la page
$page_title = "À propos de Mars Shop";
$page_description = "Découvrez l'histoire de Mars Shop, votre boutique d'équipement spatial créée par Tantely Orion. Open Source MIT - Libre et gratuit.";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos - Mars Shop</title>
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="author" content="Tantely Orion">
    <style>
        /* ============================================
           ABOUT.PHP - STYLES COMPLETS
           ============================================ */
        
        :root {
            --primary: #c14432;
            --primary-dark: #8b3a2b;
            --primary-light: #e8755a;
            --dark: #0f0f14;
            --darker: #0a0a0e;
            --gray: #1a1a24;
            --gray-light: #2a2a35;
            --text: #ffffff;
            --text-muted: #a0a0b0;
            --border: #2a2a35;
            --success: #10b981;
            --info: #3b82f6;
            --warning: #f59e0b;
        }
        
        .about-page {
            padding: 40px 0;
        }
        
        /* Hero Section */
        .about-hero {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 30px;
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
        }
        
        .about-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08), transparent);
            animation: rotate 30s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .about-hero-content {
            position: relative;
            z-index: 1;
        }
        
        .about-hero h1 {
            font-size: 3rem;
            margin-bottom: 16px;
            animation: fadeInUp 0.6s ease;
        }
        
        .about-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease 0.1s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Section commune */
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .section-title p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        /* Cartes */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        
        .info-card {
            background: var(--gray);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .card-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .info-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .info-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }
        
        /* Section Open Source */
        .opensource-section {
            background: linear-gradient(135deg, var(--gray), var(--dark));
            border-radius: 24px;
            padding: 50px;
            margin-bottom: 60px;
            text-align: center;
            border: 1px solid var(--border);
        }
        
        .opensource-icon {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 20px;
        }
        
        .opensource-section h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .opensource-section .license-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: 8px 20px;
            border-radius: 40px;
            font-family: monospace;
            font-size: 1rem;
            font-weight: 600;
            margin: 20px 0;
        }
        
        .opensource-section .btn-github {
            background: #24292e;
            color: white;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            margin-top: 15px;
        }
        
        .btn-github:hover {
            background: #2c3136;
            transform: translateY(-2px);
        }
        
        /* Section Équipe */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .team-card {
            background: var(--gray);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .team-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .team-avatar i {
            font-size: 3.5rem;
            color: rgba(255,255,255,0.8);
        }
        
        .team-card h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .team-role {
            color: var(--primary-light);
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .team-bio {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .social-links a {
            width: 36px;
            height: 36px;
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
        
        /* Section Valeurs */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }
        
        .value-card {
            background: var(--gray);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .value-card:hover {
            border-color: var(--primary);
        }
        
        .value-icon {
            font-size: 2.5rem;
            color: var(--primary-light);
            margin-bottom: 15px;
        }
        
        .value-card h4 {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .value-card p {
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Section Technologies */
        .tech-section {
            background: var(--gray);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 60px;
        }
        
        .tech-section h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 25px;
        }
        
        .tech-item {
            text-align: center;
            padding: 20px;
            background: var(--gray-light);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .tech-item:hover {
            transform: translateY(-3px);
            background: rgba(193,68,50,0.1);
        }
        
        .tech-item i {
            font-size: 2.5rem;
            color: var(--primary-light);
            margin-bottom: 10px;
            display: block;
        }
        
        .tech-item span {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* CTA Section */
        .cta-section {
            text-align: center;
            padding: 50px;
            background: linear-gradient(135deg, rgba(193,68,50,0.1), rgba(232,117,90,0.05));
            border-radius: 24px;
            margin-bottom: 20px;
        }
        
        .cta-section h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .cta-section p {
            color: var(--text-muted);
            margin-bottom: 25px;
        }
        
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            border-radius: 40px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(193,68,50,0.3);
        }
        
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: transparent;
            border: 2px solid var(--border);
            border-radius: 40px;
            color: var(--text);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary-light);
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .about-hero h1 {
                font-size: 2rem;
            }
            
            .about-hero p {
                font-size: 1rem;
            }
            
            .opensource-section {
                padding: 30px 20px;
            }
            
            .opensource-section h2 {
                font-size: 1.5rem;
            }
            
            .section-title h2 {
                font-size: 1.5rem;
            }
            
            .cta-section {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .values-grid {
                grid-template-columns: 1fr;
            }
            
            .tech-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .cta-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<main>
    <div class="about-page">
        <div class="container">
            <!-- Hero Section -->
            <div class="about-hero">
                <div class="about-hero-content">
                    <h1><i class="fas fa-rocket"></i> Mars Shop</h1>
                    <p>Votre boutique d'équipement spatial pour les aventuriers modernes</p>
                </div>
            </div>
            
            <!-- Mission Section -->
            <div class="cards-grid">
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3>Notre Mission</h3>
                    <p>Rendre l'exploration spatiale accessible à tous en proposant des équipements de qualité, avec un service exceptionnel et une expérience d'achat unique.</p>
                </div>
                
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Notre Vision</h3>
                    <p>Devenir la référence mondiale pour l'équipement des passionnés d'espace et des futurs explorateurs martiens.</p>
                </div>
                
                <div class="info-card">
                    <div class="card-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Nos Valeurs</h3>
                    <p>Qualité, innovation, passion et accessibilité sont au cœur de chaque décision que nous prenons.</p>
                </div>
            </div>
            
            <!-- Open Source Section -->
            <div class="opensource-section">
                <div class="opensource-icon">
                    <i class="fab fa-github-alt"></i>
                </div>
                <h2>Open Source MIT</h2>
                <p>Mars Shop est un projet open source, libre et gratuit.<br>Vous pouvez l'utiliser, le modifier et le distribuer librement.</p>
                <div class="license-badge">
                    <i class="fas fa-balance-scale"></i> MIT License
                </div>
                <a href="https://github.com/tantelyorion/mars-shop" class="btn-github" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-github"></i> Voir sur GitHub
                </a>
            </div>
            
            <!-- Creator Section -->
            <div class="section-title">
                <h2><i class="fas fa-user-astronaut"></i> Le Créateur</h2>
                <p>Rencontrez l'esprit derrière Mars Shop</p>
            </div>
            
            <div class="team-grid">
                <div class="team-card" style="max-width: 400px; margin: 0 auto;">
                    <div class="team-avatar">
                        <i class="fas fa-user-astronaut"></i>
                    </div>
                    <h3>Tantely Orion</h3>
                    <div class="team-role">Fondateur & Développeur Principal</div>
                    <div class="team-bio">
                        Passionné d'espace et de développement web, Tantely a créé Mars Shop pour partager sa passion 
                        et offrir une plateforme e-commerce moderne, accessible et open source à tous.
                    </div>
                    <div class="social-links">
                        <a href="#" target="_blank"><i class="fab fa-github"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Technologies Section -->
            <div class="tech-section">
                <h2><i class="fas fa-code"></i> Technologies utilisées</h2>
                <div class="tech-grid">
                    <div class="tech-item">
                        <i class="fab fa-php"></i>
                        <span>PHP 8.x</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-database"></i>
                        <span>MySQL / MariaDB</span>
                    </div>
                    <div class="tech-item">
                        <i class="fab fa-js"></i>
                        <span>JavaScript (ES6+)</span>
                    </div>
                    <div class="tech-item">
                        <i class="fab fa-css3-alt"></i>
                        <span>CSS3 / Flexbox / Grid</span>
                    </div>
                    <div class="tech-item">
                        <i class="fab fa-html5"></i>
                        <span>HTML5</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-lock"></i>
                        <span>PDO / Sessions sécurisées</span>
                    </div>
                </div>
            </div>
            
            <!-- Call to Action -->
            <div class="cta-section">
                <h3>Prêt à explorer Mars ?</h3>
                <p>Découvrez notre collection d'équipements pour l'exploration spatiale</p>
                <div class="cta-buttons">
                    <a href="shop.php" class="btn-primary">
                        <i class="fas fa-store"></i> Découvrir la boutique
                    </a>
                    <a href="https://github.com/tantelyorion/mars-shop" class="btn-secondary" target="_blank">
                        <i class="fab fa-github"></i> Contribuer sur GitHub
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>