<!DOCTYPE html>
<html lang="pt-BR">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
   <meta content="width=device-width, initial-scale=1.0" name="viewport">

   <title><?= isset($pageTitle) ? $pageTitle : 'Sistema de Comissão' ?></title>
   <meta content="Sistema de Gestão CD" name="description">
   <meta content="gestão, sistema, dashboard" name="keywords">

   <!-- Google Fonts -->
   <link href="https://fonts.gstatic.com" rel="preconnect">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

   <!-- Bootstrap CSS -->
   <link href="<?= $base; ?>src/css/bootstrap.min.css" rel="stylesheet">
   
   <!-- DataTables CSS -->
   <link href="<?= $base; ?>src/css/dataTables.bootstrap5.css" rel="stylesheet">
   <link href="<?= $base; ?>src/css/buttons.bootstrap5.css" rel="stylesheet">
   
   <!-- Select2 CSS -->
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
   <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
   
   <!-- Template Custom CSS -->
   <link href="<?= $base; ?>src/css/template-style.css" rel="stylesheet">
   
   <!-- Design System (Padrão Visual Unificado) -->
   <link href="<?= $base; ?>src/css/design-system.css" rel="stylesheet">

   <?php if(isset($customCSS)): ?>
      <?php foreach((array)$customCSS as $css): ?>
         <link href="<?= $base . $css ?>?v=<?= time() ?>" rel="stylesheet">
      <?php endforeach; ?>
   <?php endif; ?>
   
   <script>
      const BASE = '<?= $base; ?>';
   </script>
</head>
<body id="body-head" style="<?= isset($bodyStyle) ? $bodyStyle : '' ?>">

<?php if(isset($showNavbar) && $showNavbar): ?>
   <?= $render('navbar', [
       'base' => $base, 
       'pageActive' => isset($pageActive) ? $pageActive : '',
       'pageTitle' => isset($pageTitle) ? $pageTitle : 'SISTEMA CD',
       'showRefreshBtn' => isset($showRefreshBtn) ? $showRefreshBtn : ($pageActive === 'dashboard'),
       'showLastUpdate' => isset($showLastUpdate) ? $showLastUpdate : ($pageActive === 'dashboard')
   ]) ?>
<?php endif; ?>