<?php
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Taxa de Pedidos Pendentes',
    'showNavbar' => true,
    'pageActive' => 'faturamento-eficiencia-uep',
    'bodyStyle'  => 'margin: 0; padding: 0;',
]) ?>

<div style="padding: 24px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <h2 style="margin:0; font-size:1.15rem; font-weight:600;">Taxa de Pedidos Pendentes</h2>
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

    <!-- Projeção por Tanque -->
    <div id="proj-wrap" style="display:none; margin-top:32px;">
        <h5 style="font-weight:700; margin-bottom:12px; border-bottom:2px solid #dee2e6; padding-bottom:6px;">
            Projeção de Faturamento por Tanque
        </h5>
        <div id="proj-loading" style="text-align:center; padding:20px; display:none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <span style="margin-left:8px;">Carregando tanques...</span>
        </div>
        <div id="proj-error" style="display:none;" class="alert alert-warning"></div>
        <div id="proj-table-wrap" style="display:none; overflow-x:auto;">
            <table class="table table-sm table-hover" id="proj-table">
                <thead class="table-dark">
                    <tr>
                        <th>Empresa</th>
                        <th>Tanque</th>
                        <th style="text-align:right;">Capacidade (UEP/dia)</th>
                        <th style="text-align:right;">Taxa (R$/UEP)</th>
                        <th style="text-align:right;">Projeção (R$/dia)</th>
                    </tr>
                </thead>
                <tbody id="proj-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<?= $render('footer', ['customJS' => ['src/js/faturamento-eficiencia-uep.js']]) ?>
