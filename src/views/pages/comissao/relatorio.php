<?php
// Verificar permissão de acesso
$acessoComissao = $_SESSION['user']['acesso_comissao'] ?? 'N';
if ($acessoComissao !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatórios - Sistema de Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <h2 style="text-align: center; color: #004080; margin-bottom: 30px;">
        <i class="bi bi-clipboard-data"></i> Relatórios do Sistema de Comissão
    </h2>
    
    <div class="comissao-metrics-grid">
        <a href="<?= $base ?>comissao-relatorio-diario" class="comissao-metric-card" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">PRODUTIVIDADE DIÁRIA</div>
                <div class="comissao-metric-value">Acompanhamento por dia</div>
            </div>
        </a>
        
        <a href="<?= $base ?>comissao-relatorio-comissoes" class="comissao-metric-card success" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">COMISSÕES</div>
                <div class="comissao-metric-value">Valores calculados</div>
            </div>
        </a>
        
        <a href="<?= $base ?>comissao-relatorio-funcionario" class="comissao-metric-card info" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">POR FUNCIONÁRIO</div>
                <div class="comissao-metric-value">Desempenho individual</div>
            </div>
        </a>
        

    </div>
</div>

<?= $render('footer') ?>
