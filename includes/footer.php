<?php
// includes/footer.php - Version épurée
?>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>Mars Shop</h4>
                    <p>Équipements pour l'exploration spatiale</p>
                </div>
                <div>
                    <h4>Liens</h4>
                    <a href="shop.php">Boutique</a>
                    <a href="contact.php">Contact</a>
                    <a href="cgv.php">CGV</a>
                </div>
                <div>
                    <h4>Contact</h4>
                    <p>contact@marsshop.com</p>
                    <p>01 23 45 67 89</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Mars Shop. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="assets/js/main.js"></script>
    <script>
        // Flash auto-close
        const flash = document.querySelector('.flash-message');
        if(flash) {
            setTimeout(() => flash.remove(), 3000);
            flash.querySelector('.flash-close')?.addEventListener('click', () => flash.remove());
        }
        
        // Mobile menu
        const toggle = document.getElementById('mobileToggle');
        const menu = document.getElementById('mobileMenu');
        if(toggle && menu) {
            toggle.addEventListener('click', () => menu.classList.add('active'));
            menu.querySelector('.mobile-close')?.addEventListener('click', () => menu.classList.remove('active'));
        }
        
        // Back to top
        const backBtn = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            backBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        });
        backBtn?.addEventListener('click', () => window.scrollTo({top: 0, behavior: 'smooth'}));
    </script>
</body>
</html>