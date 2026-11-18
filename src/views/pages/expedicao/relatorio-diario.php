<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Detalhe por Dia - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-relatorio-diario',
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
            <div class="filter-group" style="min-width: 220px;">
                <label for="fEquipe">Equipe</label>
                <select id="fEquipe" class="form-select"><option value="">Todas</option></select>
            </div>
            <div class="filter-group" style="min-width: 220px;">
                <label for="fBusca">Funcionário</label>
                <input type="text" id="fBusca" class="form-control" placeholder="Código ou nome">
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-primary btn-sm" id="btnGerar"><i class="bi bi-search"></i> Gerar Relatório</button>
            </div>
            <div class="filter-group ms-auto">
                <button type="button" class="btn btn-outline-success btn-sm" id="btnExcel"><i class="bi bi-file-excel"></i> Excel</button>
            </div>
        </div>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card"><div class="metric-icon bg-primary"><i class="bi bi-calendar-day"></i></div>
            <div class="metric-content"><span class="metric-value" id="mDias">-</span><span class="metric-label">Dias com carga fechada</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-info"><i class="bi bi-truck"></i></div>
            <div class="metric-content"><span class="metric-value" id="mCargas">-</span><span class="metric-label">Cargas fechadas</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-warning"><i class="bi bi-box"></i></div>
            <div class="metric-content"><span class="metric-value" id="mM3">-</span><span class="metric-label">m³ (pontos)</span></div></div>
        <div class="metric-card"><div class="metric-icon bg-success"><i class="bi bi-currency-dollar"></i></div>
            <div class="metric-content"><span class="metric-value" id="mValor">-</span><span class="metric-label">Total do filtro</span></div></div>
    </div>
    <div id="avisos" class="small text-warning mb-2"></div>

    <div class="dashboard-section">
        <table class="table table-striped table-hover table-sm" style="font-size: 0.78rem;">
            <thead><tr><th>Dia</th><th>Equipe</th><th>Tipo</th><th class="text-end">Cargas</th><th class="text-end">m³ do dia</th><th class="text-end">Valor da equipe</th><th>Código</th><th>Funcionário</th><th>Cálculo</th><th class="text-end">Peso</th><th class="text-end">Comissão (R$)</th></tr></thead>
            <tbody id="corpo"><tr><td colspan="11" class="text-center text-muted">Informe o período e clique em Gerar Relatório.</td></tr></tbody>
            <tfoot id="rodape"></tfoot>
        </table>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    let dados = null;

    function filtrado() {
        const eq = $i('fEquipe').value, b = $i('fBusca').value.trim().toLowerCase();
        return dados.detalhe.filter(l => (!eq || String(l.ID_EQUIPE) === eq) && (!b || (l.COD_FUNC + ' ' + l.FUNCIONARIO).toLowerCase().includes(b)));
    }
    function desenhar() {
        if (!dados) return;
        const rows = filtrado();
        $i('corpo').innerHTML = rows.length ? rows.map(l => '<tr><td>' + EX.dbr(l.DATA) + '</td><td>' + EX.esc(l.EQUIPE) + '</td><td>' + EX.tagTipo(l.TIPO) + '</td><td class="text-end">' + l.CARGAS + '</td><td class="text-end">' + EX.brl(l.M3_DIA) + '</td><td class="text-end">' + EX.brl(l.VALOR_EQUIPE) + '</td><td>' + EX.esc(l.COD_FUNC) + '</td><td>' + EX.esc(l.FUNCIONARIO) + '</td><td>' +
            (l.ORIGEM === 'REGRA' ? '<span class="badge bg-info">Regra individual</span>' : '<span class="badge bg-success">Rateio</span>') + '</td><td class="text-end">' + l.PESO + '</td><td class="text-end"><strong>' + EX.brl(l.VALOR) + '</strong></td></tr>').join('')
            : '<tr><td colspan="11" class="text-center text-muted">Nenhum resultado.</td></tr>';
        const total = rows.reduce((s, l) => s + Number(l.VALOR), 0);
        $i('mValor').textContent = 'R$ ' + EX.brl(total);
        $i('rodape').innerHTML = rows.length ? '<tr class="table-primary fw-bold"><td colspan="10">Total</td><td class="text-end">' + EX.brl(total) + '</td></tr>' : '';
    }
    $i('fEquipe').onchange = desenhar; $i('fBusca').oninput = desenhar;

    $i('btnGerar').onclick = async () => {
        $i('btnGerar').disabled = true; EX.aviso('Apurando...', true);
        try {
            const r = dados = await EX.post('/expedicao-api-apurar', { dt_ini: $i('dtIni').value, dt_fim: $i('dtFim').value });
            EX.aviso(r.resumo.cargas ? '' : 'Nenhuma carga fechada neste período.', !r.resumo.cargas ? false : true);
            $i('mDias').textContent = r.resumo.dias; $i('mCargas').textContent = r.resumo.cargas; $i('mM3').textContent = EX.fmt(r.resumo.m3);
            $i('avisos').innerHTML = r.avisos.map(a => '<i class="bi bi-exclamation-triangle"></i> ' + EX.esc(a)).join('<br>');
            const atual = $i('fEquipe').value, eqs = {};
            r.detalhe.forEach(l => eqs[l.ID_EQUIPE] = l.EQUIPE);
            $i('fEquipe').innerHTML = '<option value="">Todas</option>' + Object.entries(eqs).map(([id, n]) => '<option value="' + id + '">' + EX.esc(n) + '</option>').join('');
            if (eqs[atual]) $i('fEquipe').value = atual;
            desenhar();
        } catch (e) { EX.aviso(e.message, false); }
        $i('btnGerar').disabled = false;
    };

    $i('btnExcel').onclick = () => {
        if (!dados) return EX.aviso('Gere o relatório antes de exportar.', false);
        const rows = filtrado();
        if (!rows.length) return EX.aviso('Nada para exportar.', false);
        const q = v => '"' + String(v ?? '').replace(/"/g, '""') + '"', n = v => String(v).replace('.', ',');
        const linhas = ['Dia;Equipe;Tipo;Cargas;m³ do dia;Valor da equipe;Código;Funcionário;Cálculo;Peso;Comissão (R$)'].concat(rows.map(l =>
            [EX.dbr(l.DATA), q(l.EQUIPE), EX.tipo(l.TIPO), l.CARGAS, n(l.M3_DIA), n(l.VALOR_EQUIPE), q(l.COD_FUNC), q(l.FUNCIONARIO), l.ORIGEM === 'REGRA' ? 'Regra individual' : 'Rateio', n(l.PESO), n(l.VALOR)].join(';')));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = 'comissao-expedicao-diario-' + $i('dtIni').value + '-a-' + $i('dtFim').value + '.csv'; a.click(); URL.revokeObjectURL(a.href);
    };

    $i('dtIni').value = EX.inicioMes(); $i('dtFim').value = EX.hoje();
});
</script>
<?= $render('footer') ?>
