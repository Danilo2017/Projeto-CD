<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Comissões - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-relatorio-comissoes',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>expedicao-relatorio" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
    <div id="exMsg" style="display:none"></div>

    <div class="dashboard-filters">
        <div class="filter-row d-flex align-items-end flex-wrap">
            <div class="filter-group">
                <label>Empresa</label>
                <input type="text" class="form-control" readonly style="background-color: #e9ecef;" value="<?= htmlspecialchars(($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '')) ?>">
            </div>
            <div class="filter-group"><label for="dtIni">Data Início *</label><input type="date" id="dtIni" class="form-control"></div>
            <div class="filter-group"><label for="dtFim">Data Fim *</label><input type="date" id="dtFim" class="form-control"></div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" id="btnGerar"><i class="bi bi-search"></i> Gerar Relatório</button>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-3 pt-3 border-top">
                <button type="button" class="btn btn-outline-success btn-sm" id="btnExcel"><i class="bi bi-file-excel"></i> Excel</button>
                <button type="button" class="btn btn-info btn-sm text-white" id="btnImpSel"><i class="bi bi-printer"></i> Imprimir Selecionados</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnImpTodos"><i class="bi bi-printer-fill"></i> Imprimir Todos</button>
        </div>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card"><div class="metric-icon bg-primary"><i class="bi bi-truck"></i></div>
            <div class="metric-content"><span class="metric-value" id="mCargas">-</span><span class="metric-label">Cargas fechadas</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-info"><i class="bi bi-box"></i></div>
            <div class="metric-content"><span class="metric-value" id="mM3">-</span><span class="metric-label">m³ (pontos)</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-warning"><i class="bi bi-people"></i></div>
            <div class="metric-content"><span class="metric-value" id="mFunc">-</span><span class="metric-label">Funcionários</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-success"><i class="bi bi-currency-dollar"></i></div>
            <div class="metric-content"><span class="metric-value" id="mValor">-</span><span class="metric-label">Total a pagar</span></div></div>
    </div>
    <div id="avisos" class="small text-warning mb-2"></div>

    <div class="dashboard-section">
        <table class="table table-striped table-hover table-sm" style="font-size: 0.8rem;">
            <thead><tr><th class="text-center" style="width:40px"><input type="checkbox" id="selTodos" class="form-check-input"></th><th>Código</th><th>Funcionário</th><th>Equipe(s)</th><th class="text-end">Dias</th><th class="text-end">m³ dos dias</th><th class="text-end">Comissão (R$)</th><th class="text-center" style="width:110px">Comprovante</th></tr></thead>
            <tbody id="corpo"><tr><td colspan="8" class="text-center text-muted">Informe o período e clique em Gerar Relatório.</td></tr></tbody>
            <tfoot id="rodape"></tfoot>
        </table>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    let dados = null;

    $i('btnGerar').onclick = async () => {
        $i('btnGerar').disabled = true; EX.aviso('Apurando...', true);
        try {
            const r = dados = await EX.post('/expedicao-api-apurar', { dt_ini: $i('dtIni').value, dt_fim: $i('dtFim').value });
            EX.aviso(r.resumo.cargas ? '' : 'Nenhuma carga fechada neste período.', !r.resumo.cargas ? false : true);
            $i('mCargas').textContent = r.resumo.cargas; $i('mM3').textContent = EX.fmt(r.resumo.m3); $i('mFunc').textContent = r.resumo.funcionarios; $i('mValor').textContent = 'R$ ' + EX.brl(r.resumo.valor);
            $i('avisos').innerHTML = r.avisos.map(a => '<i class="bi bi-exclamation-triangle"></i> ' + EX.esc(a)).join('<br>');
            $i('corpo').innerHTML = r.funcionarios.length ? r.funcionarios.map(f => '<tr><td class="text-center"><input type="checkbox" class="form-check-input sel-func" value="' + f.ID_FUNCIONARIO + '"></td><td>' + EX.esc(f.COD_FUNC) + '</td><td>' + EX.esc(f.FUNCIONARIO) + '</td><td>' + EX.esc(f.EQUIPES) + '</td><td class="text-end">' + f.DIAS + '</td><td class="text-end">' + EX.brl(f.M3) + '</td><td class="text-end"><strong>' + EX.brl(f.VALOR) + '</strong></td><td class="text-center"><button class="btn btn-sm btn-outline-primary py-0 btn-comp" data-f="' + f.ID_FUNCIONARIO + '" title="Imprimir comprovante"><i class="bi bi-printer"></i></button></td></tr>').join('')
                : '<tr><td colspan="8" class="text-center text-muted">Nenhuma comissão no período.</td></tr>';
            $i('rodape').innerHTML = r.funcionarios.length ? '<tr class="table-primary fw-bold"><td colspan="6">Total</td><td class="text-end">' + EX.brl(r.resumo.valor) + '</td><td></td></tr>' : '';
        } catch (e) { EX.aviso(e.message, false); }
        $i('btnGerar').disabled = false;
    };

    $i('btnExcel').onclick = () => {
        if (!dados || !dados.funcionarios.length) return EX.aviso('Gere o relatório antes de exportar.', false);
        const q = v => '"' + String(v ?? '').replace(/"/g, '""') + '"', n = v => String(v).replace('.', ',');
        const linhas = ['Código;Funcionário;Equipe(s);Dias;m³ dos dias;Comissão (R$)'].concat(dados.funcionarios.map(f => [q(f.COD_FUNC), q(f.FUNCIONARIO), q(f.EQUIPES), f.DIAS, n(f.M3), n(f.VALOR)].join(';')));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'comissao-expedicao-' + $i('dtIni').value + '-a-' + $i('dtFim').value + '.csv'; a.click(); URL.revokeObjectURL(a.href);
    };

    // ── Comprovante para assinatura (mesmo formato dos comprovantes da produtividade) ──
    const EMPRESA = <?= json_encode($empresa['razao_social'] ?? $empresa['nome_fantasia'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
    function pagina(f, ultima) {
        const linhas = dados.detalhe.filter(l => l.ID_FUNCIONARIO === f.ID_FUNCIONARIO).sort((a, b) => a.DATA.localeCompare(b.DATA) || a.EQUIPE.localeCompare(b.EQUIPE));
        const m3 = linhas.reduce((s, l) => s + Number(l.M3_DIA), 0), cargas = linhas.reduce((s, l) => s + Number(l.CARGAS), 0);
        const corpo = linhas.map(l => '<tr><td>' + EX.dbr(l.DATA) + '</td><td>' + EX.esc(l.EQUIPE) + ' (' + EX.tipo(l.TIPO) + ')</td><td style="text-align:center">' + l.CARGAS + '</td><td style="text-align:right">' + EX.brl(l.M3_DIA) + '</td><td style="text-align:right">' + EX.brl(l.VALOR) + '</td></tr>').join('');
        return '<div class="comprovante-pagina"' + (ultima ? '' : ' style="page-break-after: always;"') + '>' +
            '<div class="header"><div><h5>COMPROVANTE DE COMISSÃO DA EXPEDIÇÃO</h5><small>' + EX.esc(EMPRESA) + '</small></div>' +
            '<div class="header-right"><div><strong>Período:</strong> ' + EX.dbr($i('dtIni').value) + ' a ' + EX.dbr($i('dtFim').value) + '</div></div></div>' +
            '<div class="funcionario-info"><span><strong>Funcionário:</strong> ' + EX.esc(f.FUNCIONARIO) + '</span><span><strong>Cód:</strong> ' + EX.esc(f.COD_FUNC) + '</span><span><strong>Equipe:</strong> ' + EX.esc(f.EQUIPES) + '</span></div>' +
            '<table><thead><tr><th>Data</th><th>Equipe</th><th style="text-align:center">Cargas carregadas</th><th style="text-align:right">m³ carregados</th><th style="text-align:right">Comissão</th></tr></thead><tbody>' + corpo + '</tbody>' +
            '<tfoot><tr><td colspan="2"><strong>TOTAL</strong></td><td style="text-align:center">' + cargas + '</td><td style="text-align:right">' + EX.brl(m3) + '</td><td style="text-align:right">R$ ' + EX.brl(f.VALOR) + '</td></tr></tfoot></table>' +
            '<div class="assinaturas"><div class="assinatura-box"><div class="linha-assinatura"></div><small>Funcionário: ' + EX.esc(f.FUNCIONARIO) + '</small></div>' +
            '<div class="assinatura-box"><div class="linha-assinatura"></div><small>Responsável RH</small></div></div>' +
            '<div class="rodape">Gerado em: ' + new Date().toLocaleString('pt-BR') + '</div></div>';
    }
    function imprimir(lista) {
        if (!dados || !lista.length) return EX.aviso('Gere o relatório e escolha ao menos um funcionário.', false);
        const html = lista.map((f, i) => pagina(f, i === lista.length - 1)).join('');
        const w = window.open('', '_blank', 'width=800,height=600');
        if (!w) return EX.aviso('O navegador bloqueou a janela de impressão. Libere pop-ups para este site.', false);
        w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Comprovantes de Comissão - Expedição</title><style>' +
            '* { box-sizing: border-box; } body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }' +
            '.comprovante-pagina { padding: 20px; max-width: 800px; margin: 0 auto; }' +
            '.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }' +
            '.header h5 { margin: 0; font-size: 14px; } .header-right { text-align: right; font-size: 11px; }' +
            '.funcionario-info { padding: 8px 0; border-bottom: 1px solid #ccc; margin-bottom: 12px; } .funcionario-info span { margin-right: 20px; }' +
            'table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; } th, td { border: 1px solid #333; padding: 5px 10px; }' +
            'thead { background-color: #333; color: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }' +
            'tfoot { background-color: #e9ecef; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td { font-size: 13px; }' +
            'tr { page-break-inside: avoid; } thead { display: table-header-group; }' +
            '.assinaturas { display: flex; justify-content: space-between; margin-top: 50px; } .assinatura-box { flex: 1; text-align: center; margin: 0 20px; }' +
            '.linha-assinatura { border-top: 1px solid #333; margin-bottom: 5px; } .rodape { margin-top: 20px; text-align: center; font-size: 10px; color: #666; }' +
            '@media print { @page { size: A4; margin: 15mm; } body { padding: 0; } .comprovante-pagina { padding: 0; } }' +
            '@media screen { .comprovante-pagina { border: 1px solid #ccc; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); } }' +
            '</style></head><body>' + html + '</body></html>');
        w.document.close(); w.focus(); setTimeout(() => w.print(), 400);
    }
    $i('btnImpTodos').onclick = () => {
        if (!dados) return EX.aviso('Gere o relatório primeiro.', false);
        if (dados.funcionarios.length && confirm('Imprimir os comprovantes de todos os ' + dados.funcionarios.length + ' funcionário(s)?')) imprimir(dados.funcionarios);
    };
    $i('btnImpSel').onclick = () => {
        if (!dados) return EX.aviso('Gere o relatório primeiro.', false);
        const ids = [...document.querySelectorAll('.sel-func:checked')].map(c => Number(c.value));
        imprimir(dados.funcionarios.filter(f => ids.includes(f.ID_FUNCIONARIO)));
    };
    $i('selTodos').onchange = ev => document.querySelectorAll('.sel-func').forEach(c => c.checked = ev.target.checked);
    $i('corpo').addEventListener('click', ev => {
        const b = ev.target.closest('.btn-comp'); if (!b || !dados) return;
        imprimir(dados.funcionarios.filter(f => String(f.ID_FUNCIONARIO) === b.dataset.f));
    });

    $i('dtIni').value = EX.inicioMes(); $i('dtFim').value = EX.hoje();
});
</script>
<?= $render('footer') ?>
