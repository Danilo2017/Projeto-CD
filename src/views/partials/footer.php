   <?php if(isset($customScriptsBeforeFooter)): ?>
      <?php foreach((array)$customScriptsBeforeFooter as $script): ?>
         <script>
            <?= $script ?>
         </script>
      <?php endforeach; ?>
   <?php endif; ?>

   <!-- JavaScript Files -->
    <script src="<?= $base; ?>src/js/jquery-3.7.1.js"></script>
    <script src="<?= $base; ?>src/js/bootstrap.bundle.min.js"></script>
   
   <!-- DataTables JS -->
    <script src="<?= $base; ?>src/js/dataTables.js"></script>
    <script src="<?= $base; ?>src/js/dataTables.bootstrap5.js"></script>
    <script src="<?= $base; ?>src/js/dataTables.buttons.js"></script>
    <script src="<?= $base; ?>src/js/buttons.bootstrap5.js"></script>
    <script src="<?= $base; ?>src/js/buttons.html5.min.js"></script>
    <script src="<?= $base; ?>src/js/buttons.print.min.js"></script>
    <script src="<?= $base; ?>src/js/buttons.colVis.min.js"></script>
    <script src="<?= $base; ?>src/js/jszip.min.js"></script>
    <script src="<?= $base; ?>src/js/pdfmake.min.js"></script>
    <script src="<?= $base; ?>src/js/vfs_fonts.js"></script>
   
   <!-- Template Custom JS -->
    <script src="<?= $base; ?>src/js/template.js?v=<?= time() ?>"></script>
   
   <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>

   <?php if(isset($customJS)): ?>
      <?php foreach((array)$customJS as $js): ?>
         <script src="<?= $base . $js ?>?v=<?= time() ?>"></script>
      <?php endforeach; ?>
   <?php endif; ?>

   <script>
   // Template JavaScript
   document.addEventListener('DOMContentLoaded', function() {
       // Active menu highlighting
       const currentPath = window.location.pathname;
       const navLinks = document.querySelectorAll('.nav-link, .cd-btn-menu');
       
       navLinks.forEach(link => {
           const href = link.getAttribute('href');
           if (href && currentPath.includes(href.replace(BASE, ''))) {
               link.classList.add('active');
               link.classList.remove('collapsed');
           }
       });

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

   <?php if(isset($customScriptsInline)): ?>
      <?php foreach((array)$customScriptsInline as $scriptInline): ?>
         <script>
            <?= $scriptInline ?>
         </script>
      <?php endforeach; ?>
   <?php endif; ?>

</body>
</html>