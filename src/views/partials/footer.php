
   <!-- Back to top button -->
   <a href="#" class="back-to-top d-flex align-items-center justify-content-center" style="display: none;">
      <i class="bi bi-arrow-up-short"></i>
   </a>

   <!-- JavaScript Files -->
    <script src="<?= $base; ?>public/src/js/jquery-3.7.1.js"></script>
    <script src="<?= $base; ?>public/src/js/bootstrap.bundle.min.js"></script>
   
   <!-- DataTables JS -->
    <script src="<?= $base; ?>public/src/js/dataTables.js"></script>
    <script src="<?= $base; ?>public/src/js/dataTables.bootstrap5.js"></script>
    <script src="<?= $base; ?>public/src/js/dataTables.buttons.js"></script>
    <script src="<?= $base; ?>public/src/js/buttons.bootstrap5.js"></script>
    <script src="<?= $base; ?>public/src/js/buttons.html5.min.js"></script>
    <script src="<?= $base; ?>public/src/js/buttons.print.min.js"></script>
    <script src="<?= $base; ?>public/src/js/buttons.colVis.min.js"></script>
    <script src="<?= $base; ?>public/src/js/jszip.min.js"></script>
    <script src="<?= $base; ?>public/src/js/pdfmake.min.js"></script>
    <script src="<?= $base; ?>public/src/js/vfs_fonts.js"></script>
   
   <!-- Template Custom JS -->
    <script src="<?= $base; ?>public/src/js/template.js"></script>

   <script>
   // Template JavaScript
   document.addEventListener('DOMContentLoaded', function() {
       // Smooth scrolling for back to top
       const backToTop = document.querySelector('.back-to-top');
       
       window.addEventListener('scroll', function() {
           if (window.scrollY > 100) {
               backToTop.style.display = 'flex';
           } else {
               backToTop.style.display = 'none';
           }
       });

       backToTop.addEventListener('click', function(e) {
           e.preventDefault();
           window.scrollTo({
               top: 0,
               behavior: 'smooth'
           });
       });

       // Active menu highlighting
       const currentPath = window.location.pathname;
       const navLinks = document.querySelectorAll('.nav-link');
       
       navLinks.forEach(link => {
           const href = link.getAttribute('href');
           if (href && currentPath.includes(href.replace(BASE, ''))) {
               link.classList.add('active');
               link.classList.remove('collapsed');
           }
       });

       // (Removido) Inicialização global do DataTables – agora feita somente no template.js em tabelas com atributo data-datatable

       // Animation on scroll
       const observerOptions = {
           threshold: 0.1,
           rootMargin: '0px 0px -50px 0px'
       };

       const observer = new IntersectionObserver(function(entries) {
           entries.forEach(entry => {
               if (entry.isIntersecting) {
                   entry.target.classList.add('animate-fade-up');
               }
           });
       }, observerOptions);

       // Observe cards and content elements
       document.querySelectorAll('.card, .dashboard-card').forEach(card => {
           observer.observe(card);
       });
   });
   </script>
</body>
</html>