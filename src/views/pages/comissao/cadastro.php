<?php
// Verificar permissão de acesso
$acessoComissao = $_SESSION['user']['acesso_comissao'] ?? 'N';
if ($acessoComissao !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Cadastros - Sistema de Comissão',
    'showNavbar' => true,
    'pageActive' => 'comissao-cadastro',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="padding-top: 10px;">
    <!-- Cadastros Básicos -->
    <h5 class="mb-3" style="color: #004080;"><i class="bi bi-gear-fill"></i> Cadastros Básicos</h5>
    <div class="comissao-metrics-grid" style="margin-top: 0;">
        <a href="<?= $base ?>comissao-pontuacao" class="comissao-metric-card" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">PONTUAÇÃO UP</div>
                <div class="comissao-metric-value">Cadastro de pontos por produto</div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-faixas" class="comissao-metric-card success" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">FAIXAS DE COMISSÃO</div>
                <div class="comissao-metric-value">Definir faixas e valores</div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-vinculo" class="comissao-metric-card info" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-link-45deg"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">VÍNCULO CENTRO/RECURSO/FUNCIONÁRIO</div>
                <div class="comissao-metric-value">Cadastrar vínculo</div>
            </div>
        </a>
    </div>

    <!-- Controles de Comissão -->
    <h5 class="mb-3 mt-4" style="color: #004080;"><i class="bi bi-sliders"></i> Controles de Comissão</h5>
    <div class="comissao-metrics-grid">
        <a href="<?= $base ?>comissao-faltas" class="comissao-metric-card warning" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">CONTROLE DE FALTAS</div>
                <div class="comissao-metric-value">Registrar faltas (bloqueia comissão)</div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-retrabalho" class="comissao-metric-card danger" style="text-decoration: none; cursor: pointer;">
            <div class="comissao-metric-icon">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">RETRABALHO</div>
                <div class="comissao-metric-value">Controle de retrabalho com impacto</div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-vinculo-apontamento" class="comissao-metric-card" style="text-decoration: none; cursor: pointer; background: linear-gradient(135deg, #6f42c1 0%, #8b5cf6 100%);">
            <div class="comissao-metric-icon">
                <i class="bi bi-diagram-3"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">VÍNCULO DE APONTAMENTOS</div>
                <div class="comissao-metric-value">Vincular apontamentos sem recurso</div>
            </div>
        </a>
        <a href="<?= $base ?>comissao-regras" class="comissao-metric-card" style="text-decoration: none; cursor: pointer; background: linear-gradient(135deg, #20c997 0%, #38d9a9 100%);">
            <div class="comissao-metric-icon">
                <i class="bi bi-person-gear"></i>
            </div>
            <div class="comissao-metric-content">
                <div class="comissao-metric-label">REGRAS POR FUNCIONÁRIO</div>
                <div class="comissao-metric-value">Regras específicas de comissão</div>
            </div>
        </a>
    </div>
</div>

<?= $render('footer') ?>
