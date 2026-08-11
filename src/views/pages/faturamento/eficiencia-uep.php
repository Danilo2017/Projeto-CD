<?php
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Eficiência de Pendência por UEP',
    'showNavbar' => true,
    'pageActive' => 'faturamento-eficiencia-uep',
    'bodyStyle'  => 'margin: 0; padding: 0;',
]) ?>

<div style="padding: 24px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <h2 style="margin:0; font-size:1.15rem; font-weight:600;">Eficiência de Pendência por UEP</h2>
        <div style="display:flex;gap:8px;">
            <button id="btnAtualizar" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button id="btnExcel" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
            </button>
        </div>
    </div>

    <div id="uep-loading" style="text-align:center; padding:40px; display:none;">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span style="margin-left:8px;">Carregando...</span>
    </div>

    <div id="uep-error" style="display:none;" class="alert alert-danger"></div>

    <div id="uep-wrap" style="display:none; overflow-x:auto;">
        <table class="table table-sm table-hover" id="uep-table">
            <thead class="table-dark">
                <tr>
                    <th>Classificação</th>
                    <th style="text-align:right;">Valor Pendente</th>
                    <th style="text-align:right;">UEP</th>
                    <th style="text-align:right;">Taxa (R$/UEP)</th>
                </tr>
            </thead>
            <tbody id="uep-tbody"></tbody>
        </table>
    </div>
</div>

<?= $render('footer', ['customJS' => ['src/js/faturamento-eficiencia-uep.js']]) ?>
