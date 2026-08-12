<?php
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) { header('Location: ' . $base . 'sem-acesso'); exit; }
$emprId    = (int) ($_GET['empr_id']    ?? 0);
$codTanque = (int) ($_GET['cod_tanque'] ?? 0);
$descTanq  = htmlspecialchars(trim($_GET['desc'] ?? ('Tanque ' . $codTanque)));
$emprNome  = htmlspecialchars(trim($_GET['empr'] ?? $emprId));
?>
<?= $render('header', [
    'pageTitle'  => 'Classificações — ' . $descTanq,
    'showNavbar' => true,
    'pageActive' => 'faturamento-analise-tanque',
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<div style="padding:24px;">
    <div style="position:relative;margin-bottom:20px;min-height:48px;">
        <a href="<?= $base ?>faturamento-analise-tanque" class="btn btn-outline-secondary btn-sm" style="position:absolute;top:0;left:0;">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <div style="text-align:center;padding:0 120px;">
            <div style="font-size:.8rem;color:#888;"><?= $emprNome ?></div>
            <h2 style="margin:0;font-size:1.1rem;font-weight:700;"><?= $descTanq ?></h2>
        </div>
        <button id="btnExcel" class="btn btn-success btn-sm" style="position:absolute;top:0;right:0;">
            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
        </button>
    </div>

    <div id="atc-loading" style="text-align:center;padding:40px;">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span style="margin-left:8px;">Carregando classificações...</span>
    </div>
    <div id="atc-error" style="display:none;" class="alert alert-danger"></div>
    <div id="atc-wrap" style="display:none;overflow-x:auto;">
        <table class="table table-sm table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Classificação</th>
                    <th style="text-align:right;">Valor Pendente</th>
                    <th style="text-align:right;">UEP</th>
                    <th style="text-align:right;">Taxa (R$/UEP)</th>
                </tr>
            </thead>
            <tbody id="atc-tbody"></tbody>
        </table>
    </div>
</div>

<script>
window._atcParams = {
    emprId:    <?= $emprId ?>,
    codTanque: <?= $codTanque ?>,
    descTanq:  <?= json_encode($descTanq) ?>,
    emprNome:  <?= json_encode($emprNome) ?>,
};
</script>
<?= $render('footer', ['customJS' => ['src/js/faturamento-analise-tanque-clas.js']]) ?>
