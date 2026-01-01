<?php
// /linen-closet/includes/footer.php
?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p>Your favorite clothing store with the latest fashion trends.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>" class="text-light">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>products" class="text-light">All Products</a></li>
                        <li><a href="<?php echo SITE_URL; ?>about" class="text-light">About Us</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <p><i class="fas fa-envelope"></i> support@linencloset.com</p>
                    <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
      <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js"></script>
    
    <?php if(isset($customScript)): ?>
        <script src="<?php echo SITE_URL; ?>assets/js/<?php echo $customScript; ?>"></script>
    <?php endif; ?>
    
</body>
</html>