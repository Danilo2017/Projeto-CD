<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatórios - Sistema de Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <div class="ds-cards-grid" style="grid-template-columns: repeat(4, 1fr);">
        <a href="<?= $base ?>comissao-relatorio-diario" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div>
                    <div class="ds-card-label">PRODUTIVIDADE DIÁRIA</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Acompanhamento por dia</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-relatorio-comissoes" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-success-light); color: var(--ds-success);">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div>
                    <div class="ds-card-label">COMISSÕES</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Valores calculados</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-relatorio-funcionario" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-info-light); color: var(--ds-info);">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div>
                    <div class="ds-card-label">POR FUNCIONÁRIO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Desempenho individual</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-relatorio-centro-trabalho" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-warning-light, #fff3cd); color: var(--ds-warning, #856404);">
                    <i class="bi bi-building"></i>
                </div>
                <div>
                    <div class="ds-card-label">POR CENTRO DE TRABALHO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Comissões e comprovantes</div>
                </div>
            </div>
        </a>
    </div>
</div>

<?= $render('footer') ?>
