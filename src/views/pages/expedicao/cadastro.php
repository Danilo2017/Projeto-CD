<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle' => 'Cadastro Comissão Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-cadastro',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <div class="ds-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        <a href="<?= $base ?>expedicao-equipes" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="ds-card-label">EQUIPES E FUNCIONÁRIOS</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Expedição e Separação</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>expedicao-regras-equipe" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-success-light); color: var(--ds-success);"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="ds-card-label">VALOR DO PONTO POR EQUIPE</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">R$ por m³ e por carga fechada</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>expedicao-regras-funcionario" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: #e6f9f1; color: #20c997;"><i class="bi bi-person-gear"></i></div>
                <div>
                    <div class="ds-card-label">REGRAS POR FUNCIONÁRIO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Valor fixo e valor por m³</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>expedicao-cargas" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-info-light); color: var(--ds-info);"><i class="bi bi-truck"></i></div>
                <div>
                    <div class="ds-card-label">CARGAS FECHADAS</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Consulta com a data de fechamento do Focco</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>expedicao-faltas" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-warning-light); color: var(--ds-warning);"><i class="bi bi-calendar-x"></i></div>
                <div>
                    <div class="ds-card-label">CONTROLE DE FALTAS</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Falta integral ou parcial, por dia ou período</div>
                </div>
            </div>
        </a>
    </div>
</div>

<?= $render('footer') ?>
