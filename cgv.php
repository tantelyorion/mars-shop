<?php
// cgv.php - Conditions Générales de Vente
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions Générales de Vente - Mars Shop</title>
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
        }
        
        .cgv-page {
            padding: 40px 0;
        }
        
        .cgv-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .cgv-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--primary-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .cgv-header p {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        .cgv-content {
            background: var(--gray);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid var(--border);
        }
        
        .cgv-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }
        
        .cgv-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .cgv-section h2 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cgv-section h3 {
            font-size: 1rem;
            margin: 15px 0 10px;
        }
        
        .cgv-section p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .cgv-section ul, .cgv-section ol {
            color: var(--text-muted);
            line-height: 1.6;
            margin: 10px 0;
            padding-left: 25px;
        }
        
        .cgv-section li {
            margin-bottom: 5px;
        }
        
        .last-update {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: var(--gray-light);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
            margin-bottom: 30px;
            transition: all 0.2s;
        }
        
        .print-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .cgv-header h1 {
                font-size: 2rem;
            }
            
            .cgv-content {
                padding: 25px;
            }
            
            .cgv-section h2 {
                font-size: 1.1rem;
            }
        }
        
        @media print {
            .header, .footer, .print-btn, .cart-notification, .back-to-top {
                display: none !important;
            }
            
            .cgv-page {
                padding: 0;
            }
            
            .cgv-content {
                background: white;
                color: black;
                padding: 0;
            }
            
            .cgv-section h2 {
                color: #333;
            }
            
            .cgv-section p, .cgv-section li {
                color: #555;
            }
        }
    </style>
</head>
<body>

<?php require_once 'includes/header.php'; ?>

<main>
    <div class="cgv-page">
        <div class="container">
            <div class="cgv-header">
                <h1><i class="fas fa-file-contract"></i> Conditions Générales de Vente</h1>
                <p>Veuillez lire attentivement nos conditions avant de passer commande</p>
            </div>
            
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimer cette page
            </button>
            
            <div class="cgv-content">
                <!-- Article 1 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-store"></i> Article 1 - Champ d'application</h2>
                    <p>Les présentes conditions générales de vente (CGV) s'appliquent à toutes les ventes de produits effectuées sur le site internet Mars Shop (ci-après dénommé "le Site") par la société Mars Shop (ci-après dénommée "le Vendeur").</p>
                    <p>Toute commande passée sur le Site implique l'acceptation sans réserve des présentes CGV par le client.</p>
                </div>
                
                <!-- Article 2 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-shopping-cart"></i> Article 2 - Commandes</h2>
                    <p>Les commandes sont passées directement sur le Site internet. Le client sélectionne les produits qu'il souhaite commander, les ajoute à son panier, puis valide sa commande après avoir renseigné ses coordonnées et choisi son mode de livraison et de paiement.</p>
                    <p>Une confirmation de commande est envoyée par email au client. Le Vendeur se réserve le droit d'annuler toute commande en cas de litige sur le paiement ou de stock indisponible.</p>
                </div>
                
                <!-- Article 3 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-tag"></i> Article 3 - Prix</h2>
                    <p>Les prix des produits sont indiqués en euros toutes taxes comprises (TTC). La TVA applicable est celle en vigueur en France.</p>
                    <p>Le Vendeur se réserve le droit de modifier ses prix à tout moment, les produits étant facturés sur la base des tarifs en vigueur au moment de la validation de la commande.</p>
                </div>
                
                <!-- Article 4 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-credit-card"></i> Article 4 - Paiement</h2>
                    <p>Le paiement des commandes s'effectue par l'un des moyens suivants :</p>
                    <ul>
                        <li><strong>Carte bancaire</strong> (Visa, Mastercard) via notre plateforme sécurisée</li>
                        <li><strong>PayPal</strong> via votre compte PayPal</li>
                        <li><strong>Mobile Money</strong> (Airtel Money, Mvola, Orange Money)</li>
                        <li><strong>Paiement à la livraison</strong> (espèces uniquement)</li>
                    </ul>
                    <p>Les transactions sont sécurisées et garanties. Les coordonnées bancaires du client ne transitent pas sur nos serveurs.</p>
                </div>
                
                <!-- Article 5 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-truck"></i> Article 5 - Livraison</h2>
                    <p>Les produits sont livrés à l'adresse indiquée par le client lors de sa commande. Les délais de livraison sont donnés à titre indicatif et peuvent varier selon la destination.</p>
                    <p>Les délais de livraison standard sont de :</p>
                    <ul>
                        <li>France métropolitaine : 2 à 5 jours ouvrés</li>
                        <li>Union Européenne : 3 à 7 jours ouvrés</li>
                        <li>International : 5 à 14 jours ouvrés</li>
                    </ul>
                    <p>La livraison est offerte en France métropolitaine dès 50€ d'achat.</p>
                </div>
                
                <!-- Article 6 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-undo-alt"></i> Article 6 - Droit de rétractation</h2>
                    <p>Conformément à la loi, le client dispose d'un délai de 14 jours à compter de la réception de sa commande pour exercer son droit de rétractation sans avoir à justifier de motifs ni à payer de pénalités.</p>
                    <p>Les frais de retour sont à la charge du client. Les produits doivent être retournés dans leur emballage d'origine, complets et en parfait état.</p>
                    <p>Sont exclus du droit de rétractation :</p>
                    <ul>
                        <li>Les produits personnalisés</li>
                        <li>Les produits descellés après livraison</li>
                        <li>Les produits périssables</li>
                    </ul>
                </div>
                
                <!-- Article 7 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-shield-alt"></i> Article 7 - Garanties</h2>
                    <p>Tous nos produits bénéficient de la garantie légale de conformité et de la garantie des vices cachés, conformément aux articles L.217-4 et suivants du Code de la consommation.</p>
                    <p>En cas de défaut de conformité ou de vice caché, le client peut demander la réparation ou le remplacement du produit, ou à défaut, le remboursement.</p>
                </div>
                
                <!-- Article 8 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-user-shield"></i> Article 8 - Données personnelles</h2>
                    <p>Les informations collectées lors de la commande sont nécessaires à son traitement. Le client dispose d'un droit d'accès, de rectification et de suppression de ses données personnelles.</p>
                    <p>Conformément au RGPD, nous ne partageons pas vos données avec des tiers sans votre consentement explicite.</p>
                </div>
                
                <!-- Article 9 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-gavel"></i> Article 9 - Droit applicable</h2>
                    <p>Les présentes CGV sont soumises au droit français. Tout litige sera porté devant les tribunaux compétents dont dépend le siège social du Vendeur.</p>
                </div>
                
                <!-- Article 10 -->
                <div class="cgv-section">
                    <h2><i class="fas fa-code-branch"></i> Article 10 - Open Source</h2>
                    <p>Mars Shop est un projet open source distribué sous licence MIT. Cela signifie que le code source de la plateforme est librement accessible, modifiable et réutilisable.</p>
                    <p>Pour plus d'informations, consultez notre dépôt GitHub : <a href="https://github.com/tantelyorion/mars-shop" style="color: var(--primary-light);">github.com/tantelyorion/mars-shop</a></p>
                </div>
                
                <div class="last-update">
                    <i class="fas fa-calendar-alt"></i> Dernière mise à jour : 1er mai 2024
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>