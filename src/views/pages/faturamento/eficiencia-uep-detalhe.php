<?php
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
$emprId        = (int) ($_GET['empr_id'] ?? 0);
$classificacao = trim($_GET['classificacao'] ?? '');
$origem        = trim($_GET['origem'] ?? '');
$voltarRota    = $origem === 'analise-tanque' ? 'faturamento-analise-tanque' : 'faturamento-eficiencia-uep';
?>
<?= $render('header', [
    'pageTitle'  => 'Itens — ' . htmlspecialchars($classificacao),
    'showNavbar' => true,
    'pageActive' => 'faturamento-eficiencia-uep',
    'bodyStyle'  => 'margin: 0; padding: 0;',
]) ?>

<div style="padding: 24px;">
    <div style="position:relative; margin-bottom:20px; min-height:48px;">
        <a href="<?= $base . $voltarRota ?>" class="btn btn-outline-secondary btn-sm" style="position:absolute;top:0;left:0;">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <div style="text-align:center; padding: 0 120px;">
            <div style="font-size:.8rem;color:#888;">Taxa de Pedidos Pendentes</div>
            <h2 style="margin:0;font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($classificacao) ?></h2>
        </div>
        <button id="btnExcel" class="btn btn-success btn-sm" style="position:absolute;top:0;right:0;">
            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
        </button>
    </div>

    <div id="det-loading" style="text-align:center; padding:40px;">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span style="margin-left:8px;">Carregando itens...</span>
    </div>

    <div id="det-error" style="display:none;" class="alert alert-danger"></div>

    <div id="det-wrap" style="display:none; overflow-x:auto;">
        <table class="table table-sm table-hover" id="det-table">
            <thead class="table-dark">
                <tr>
                    <th>Cód. Item</th>
                    <th>Descrição</th>
                    <th style="text-align:right;">Valor Pendente</th>
                    <th style="text-align:right;">UEP</th>
                    <th style="text-align:right;">Taxa (R$/UEP)</th>
                </tr>
            </thead>
            <tbody id="det-tbody"></tbody>
        </table>
    </div>
</div>

<script>
window._detParams = {
    emprId: <?= $emprId ?>,
    classificacao: <?= json_encode($classificacao) ?>,
};
</script>
<?= $render('footer', ['customJS' => ['src/js/faturamento-eficiencia-uep-detalhe.js']]) ?>
