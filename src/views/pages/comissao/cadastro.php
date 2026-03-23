<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Cadastro Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-cadastro',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <div class="ds-cards-grid" style="grid-template-columns: repeat(3, 1fr);">
        <a href="<?= $base ?>comissao-pontuacao" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div>
                    <div class="ds-card-label">PONTUAÇÃO UP</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Cadastro de pontos por produto</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-faixas" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-success-light); color: var(--ds-success);">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <div class="ds-card-label">FAIXAS DE COMISSÃO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Definir faixas e valores</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-vinculo" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-info-light); color: var(--ds-info);">
                    <i class="bi bi-link-45deg"></i>
                </div>
                <div>
                    <div class="ds-card-label">VÍNCULO CENTRO/RECURSO/FUNCIONÁRIO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Cadastrar vínculo</div>
                </div>
            </div>
        </a>
    </div>

    <div class="ds-cards-grid" style="grid-template-columns: repeat(4, 1fr);">
        <a href="<?= $base ?>comissao-faltas" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-warning-light); color: var(--ds-warning);">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <div>
                    <div class="ds-card-label">CONTROLE DE FALTAS</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Registrar faltas (bloqueia comissão)</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-retrabalho" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: var(--ds-danger-light); color: var(--ds-danger);">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="ds-card-label">RETRABALHO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Controle de retrabalho com impacto</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-vinculo-apontamento" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: #f0ebfe; color: #6f42c1;">
                    <i class="bi bi-diagram-3"></i>
                </div>
                <div>
                    <div class="ds-card-label">VÍNCULO DE APONTAMENTOS</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Vincular apontamentos sem recurso</div>
                </div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-regras" class="ds-card" style="text-decoration: none; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="comissao-metric-icon" style="background: #e6f9f1; color: #20c997;">
                    <i class="bi bi-person-gear"></i>
                </div>
                <div>
                    <div class="ds-card-label">REGRAS POR FUNCIONÁRIO</div>
                    <div style="font-size: 0.85rem; color: var(--ds-text-secondary);">Regras específicas de comissão</div>
                </div>
            </div>
        </a>
    </div>
</div>

<?= $render('footer') ?>
