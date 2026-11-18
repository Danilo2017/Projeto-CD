<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle' => 'Relatórios - Comissão Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-relatorio',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <div class="ds-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        <a href="<?= $base ?>expedicao-relatorio-comissoes" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-success-light); color: var(--ds-success);"><i class="bi bi-currency-dollar"></i></div>
                <div>
                    <div class="ds-card-label">COMISSÕES</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Valores por funcionário</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>expedicao-relatorio-diario" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon"><i class="bi bi-calendar-day"></i></div>
                <div>
                    <div class="ds-card-label">DETALHE POR DIA</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Cargas, m³ e valor dia a dia por equipe</div>
                </div>
            </div>
        </a>
    </div>
</div>

<?= $render('footer') ?>
