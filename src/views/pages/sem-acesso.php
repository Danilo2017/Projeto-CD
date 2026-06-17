<?= $render('header', [
    'pageTitle' => 'Acesso Negado',
    'showNavbar' => true,
    'pageActive' => '',
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 60vh;">
    <div style="text-align: center; background: white; padding: 60px 80px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
        <h1 style="color: #dc3545; margin-bottom: 0;">Acesso Negado</h1>
    </div>
</div>

<?= $render('footer') ?>
