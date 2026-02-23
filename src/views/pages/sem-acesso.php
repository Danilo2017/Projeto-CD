<?= $render('header', [
    'pageTitle' => 'Acesso Negado',
    'showNavbar' => true,
    'pageActive' => '',
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 60vh;">
    <div style="text-align: center; background: white; padding: 60px 80px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
        <div style="font-size: 80px; margin-bottom: 20px;">🚫</div>
        <h1 style="color: #dc3545; margin-bottom: 15px;">Acesso Negado</h1>
        <p style="color: #666; font-size: 18px;">
            Você não possui permissão para acessar esta página.<br>
            Entre em contato com o administrador do sistema.
        </p>
    </div>
</div>

<?= $render('footer') ?>
