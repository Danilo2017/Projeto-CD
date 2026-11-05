'use strict';
/*
 * Dashboard de Ordens de Manutenção — mesmas regras e janelas de detalhe do painel OTM
 * "CLI/dashboard_ordens_manut" (blocos GRUPO, DISTRIBUIDO, GERADA_ATEND, GERADA_ATEND_PREV,
 * ORDENS_FUNC, ATEND_FUNC, ESTOQUE e seus drills).
 */

var _base   = '/';
var _emprId = 0;
var _qs     = '';
var _chDist = null;
var _chGer  = null;
var _chMin  = null;
var _chCust = null;
var _resumo = {};

var TIPO_NP = 'Requisição Não Planejada';
var CORES = {
    'Corretiva':  '#e65100',
    'Programada': '#1565c0',
    'Preventiva': '#c62828',
    'Melhoria':   '#2e7d32'
};
CORES[TIPO_NP] = '#ffd600';
var BADGE = { 'Corretiva': 'C', 'Programada': 'G', 'Preventiva': 'P', 'Melhoria': 'M' };

Chart.register(ChartDataLabels);

document.addEventListener('DOMContentLoaded', function () {
    var d   = document.getElementById('mdb-app-data');
    _base   = d ? d.dataset.base : '/';
    _emprId = d ? parseInt(d.dataset.empr || '0', 10) : 0;

    document.querySelectorAll('.mdb-kpi.clk').forEach(function (el) {
        el.addEventListener('click', function () {
            var f = el.dataset.filtro || '';
            abrirDrill({ tipo: el.dataset.drill, filtro: f,
                         titulo: 'Ordens ' + (f ? f + 's' : (el.dataset.drill === 'geradas' ? 'Geradas' : 'Atendidas')) });
        });
    });
    document.getElementById('mdbVoltar').addEventListener('click', voltarDrill);
    document.getElementById('mdbCsv').addEventListener('click', csvDrill);
    carregarEmpresas();
});

/* ---- empresas ---- */
function carregarEmpresas() {
    fetch(_base + 'manutencao-api-empresas')
        .then(function (r) { return r.json(); })
        .then(function (json) {
            var sel = document.getElementById('fEmpr');
            (json.data || []).forEach(function (e) {
                var opt = document.createElement('option');
                opt.value = e.ID;
                opt.textContent = (e.CODIGO ? e.CODIGO + ' - ' : '') +
                    (e.NOME_FANTASIA || e.RAZAO_SOCIAL || String(e.ID));
                if (String(e.ID) === String(_emprId)) opt.selected = true;
                sel.appendChild(opt);
            });
            filtrar();
        })
        .catch(function () { filtrar(); });
}

/* ---- filtrar ---- */
function filtrar() {
    _emprId = parseInt(document.getElementById('fEmpr').value || '0', 10) || _emprId;
    var di  = document.getElementById('fDi').value;
    var df  = document.getElementById('fDf').value;
    if (di && df && di > df) { status('Período inválido'); return; }
    _qs = 'empr_id=' + _emprId + '&data_ini=' + di + '&data_fim=' + df;
    status('Carregando...');
    Promise.allSettled([
        fetchJson('manutencao-api-dash-resumo?' + _qs,      onResumo),
        fetchJson('manutencao-api-dash-distrib?' + _qs,     onDistrib),
        fetchJson('manutencao-api-dash-grupos?' + _qs,      onGrupos),
        fetchJson('manutencao-api-dash-func-ordens?' + _qs, onFuncOrdens),
        fetchJson('manutencao-api-dash-func-horas?' + _qs,  onFuncHoras),
        fetchJson('manutencao-api-dash-estoque?' + _qs,     onEstoque),
        fetchJson('manutencao-api-dash-custos?' + _qs,      onCustos)
    ]).then(function (res) {
        var erro = res.filter(function (r) { return r.status === 'rejected'; }).length;
        status(erro ? erro + ' bloco(s) com erro' : '');
    });
}

function fetchJson(url, callback) {
    return fetch(_base + url)
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json.success) throw new Error(json.error || 'Erro');
            callback(json);
        });
}

/* ---- blocos ---- */

function onResumo(json) {
    var d = _resumo = json.data || {};
    set('kGeradas',     fmt(d.GERADAS));
    set('kAtendidas',   fmt(d.ATENDIDAS));
    set('kAbertas',     fmt(d.ABERTAS));
    set('kCorretivas',  fmt(d.CORRETIVAS));
    set('kPreventivas', fmt(d.PREVENTIVAS));
    set('kProgramadas', fmt(d.PROGRAMADAS));
    renderGeradasChart(d);
    montarCountsPanel(d);
}

function onDistrib(json) {
    var rows = json.data || [];
    renderTipoTabela(rows);
    renderDistribChart(rows);
    renderMinutosChart(rows);
}

function onGrupos(json) {
    var rows = json.data || [];
    var tb = document.querySelector('#tbGrupos tbody');
    if (!rows.length) { tb.innerHTML = vazio(3); return; }
    tb.innerHTML = rows.map(function (r, i) {
        return '<tr class="clk" data-i="' + i + '" title="Detalhar grupo"><td class="c">' + esc(r.EMPR_ID || '') + '</td>' +
            '<td><b>' + esc(r.GRUPO || '') + '</b></td>' +
            '<td class="r">' + moeda(r.VALOR) + '</td></tr>';
    }).join('');
    ligarLinhas(tb, rows, function (r) { return { tipo: 'grupo', g: r.GRUPO, titulo: 'Detalhar Grupo ' + r.GRUPO }; });
}

function onFuncOrdens(json) {
    var rows = json.data || [];
    var tb = document.querySelector('#tbFuncOrdens tbody');
    if (!rows.length) { tb.innerHTML = vazio(3); return; }
    tb.innerHTML = rows.map(function (r, i) {
        return '<tr class="clk" data-i="' + i + '" title="Ordens do funcionário"><td>' + esc(r.FUNCIONARIO || '') + '</td>' +
            '<td class="r" style="color:#2e7d32;font-weight:700">' + fmt(r.FECHADAS) + '</td>' +
            '<td class="r" style="color:#e65100">' + fmt(r.ABERTAS) + '</td></tr>';
    }).join('');
    ligarLinhas(tb, rows, function (r) { return { tipo: 'func_ordens', f: r.FUNC_ID, titulo: 'Ordens do Funcionário ' + r.FUNCIONARIO }; });
}

function onFuncHoras(json) {
    var rows = json.data || [];
    var tb = document.querySelector('#tbFuncHoras tbody');
    if (!rows.length) { tb.innerHTML = vazio(6); return; }
    tb.innerHTML = rows.map(function (r, i) {
        var todos = String(r.FUNC_ID) === '0';
        return '<tr class="' + (todos ? 'tot' : 'clk') + '" data-i="' + i + '"' + (todos ? '' : ' title="Ordens do funcionário"') + '>' +
            '<td>' + esc(r.FUNCIONARIO || '') + '</td>' +
            '<td class="r">' + num(r.MIN_DISP, 0) + '</td>' +
            '<td class="r">' + num(r.MIN_EXEC, 0) + '</td>' +
            '<td class="r">' + num(r.PERCENTUAL, 1) + '</td>' +
            '<td class="r">' + num(r.MTBF, 1) + '</td>' +
            '<td class="r">' + num(r.MTTR, 1) + '</td></tr>';
    }).join('');
    ligarLinhas(tb, rows, function (r) {
        return String(r.FUNC_ID) === '0' ? null : { tipo: 'func_horas', f: r.FUNC_ID, titulo: 'Ordens em Execução - ' + r.FUNCIONARIO };
    });
}

function onEstoque(json) {
    var rows = json.data || [];
    var tb = document.querySelector('#tbEstoque tbody');
    set('estqDt', rows.length && rows[0].DT_GERACAO ? '(posição de ' + rows[0].DT_GERACAO + ')' : '');
    if (!rows.length) { tb.innerHTML = vazio(6); return; }
    tb.innerHTML = rows.map(function (r, i) {
        return '<tr class="clk" data-i="' + i + '" title="Itens da empresa"><td>' + esc(r.EMPRESA) + '</td>' +
            '<td class="r">' + num(r.QTDE_SALDO, 2) + '</td>' +
            '<td class="r">' + moeda(r.SALDO_TOTAL) + '</td>' +
            '<td class="r">' + moeda(r.CME) + '</td>' +
            '<td class="r">' + num(r.QTDE_SEM_GIRO, 2) + '</td>' +
            '<td class="r">' + moeda(r.SALDO_SEM_GIRO) + '</td></tr>';
    }).join('');
    ligarLinhas(tb, rows, function (r) { return { tipo: 'estoque', emp: r.EMPRESA, titulo: 'Estoque Manutenção - Empresa ' + r.EMPRESA }; });
}

/* ---- painel Resumo (OS Geradas/Atendidas + OS Preventivas) ---- */
function montarCountsPanel(d) {
    var linha = function (rot, val, drill) {
        return '<tr class="clk" data-drill="' + drill + '" title="Ver ordens"><td>' + rot + '</td><td class="r">' + fmt(val) + '</td></tr>';
    };
    var el = document.getElementById('mdbCounts');
    el.innerHTML =
        '<table class="mini-tbl"><thead><tr><th>OS</th><th>Quantidade</th></tr></thead><tbody>' +
        linha('Geradas', d.GERADAS, 'geradas') +
        linha('Atendidas', d.ATENDIDAS, 'atendidas') +
        '</tbody></table>' +
        '<table class="mini-tbl prev"><thead><tr><th>OS Preventivas</th><th>Quantidade</th></tr></thead><tbody>' +
        linha('Programadas', d.PREV_PROGRAMADAS, 'prev_programadas') +
        linha('Liberadas', d.PREV_LIBERADAS, 'prev_liberadas') +
        linha('Realizadas', d.PREV_REALIZADAS, 'prev_realizadas') +
        '</tbody></table>';
    var tit = { geradas: 'Ordens Geradas', atendidas: 'Ordens Atendidas', prev_programadas: 'Preventivas Programadas',
                prev_liberadas: 'Preventivas Liberadas', prev_realizadas: 'Preventivas Realizadas' };
    el.querySelectorAll('tr.clk').forEach(function (tr) {
        tr.addEventListener('click', function () { abrirDrill({ tipo: tr.dataset.drill, titulo: tit[tr.dataset.drill] }); });
    });
}

/* ---- tabela Tipo / Qtd / Valor ---- */
function renderTipoTabela(rows) {
    var tb = document.querySelector('#tbTipo tbody');
    if (!rows.length) { tb.innerHTML = vazio(3); return; }
    var totQ = rows.reduce(function (s, r) { return s + parseFloat(r.QTD || 0); }, 0);
    var totV = rows.reduce(function (s, r) { return s + parseFloat(r.VALOR || 0); }, 0);
    tb.innerHTML = rows.map(function (r, i) {
        var k = BADGE[r.TIPO] || 'N';
        return '<tr class="clk row-' + k + '" data-i="' + i + '" title="Máquinas deste tipo">' +
            '<td><span class="bt bt-' + k + '">' + esc(r.TIPO) + '</span></td>' +
            '<td class="r">' + fmt(r.QTD) + '</td>' +
            '<td class="r">' + moeda(r.VALOR) + '</td></tr>';
    }).join('') +
        '<tr class="row-T"><td><b>Total</b></td><td class="r"><b>' + fmt(totQ) + '</b></td>' +
        '<td class="r"><b>' + moeda(totV) + '</b></td></tr>';
    ligarLinhas(tb, rows, drillTipo);
}

function drillTipo(r) { return { tipo: 'tipo', tip: r.TIPO, titulo: 'Máquinas por Tipo de Manutenção ' + r.TIPO }; }

/* ---- gráficos (clique na barra abre o detalhe) ---- */
function opcoes(fmtLabel, onClick, extraY) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 24 } },
        onClick: onClick,
        onHover: function (ev, el) { ev.native.target.style.cursor = (onClick && el.length) ? 'pointer' : 'default'; },
        plugins: {
            legend: { display: false },
            datalabels: { anchor: 'end', align: 'top', color: '#333', font: { size: 10, weight: 'bold' },
                          formatter: function (v) { return v > 0 || (extraY && extraY.zero) ? fmtLabel(v || 0) : ''; } }
        },
        scales: {
            y: Object.assign({ beginAtZero: true, ticks: { font: { size: 10 } } }, extraY || {}),
            x: { ticks: { font: { size: 10 } } }
        }
    };
}

/* Igual ao OTM: sempre as 5 barras, nesta ordem, com 0 quando o tipo não tem ordens. */
var ORDEM_TIPOS = ['Corretiva', 'Programada', 'Preventiva', 'Melhoria', TIPO_NP];
var ROTULO_TIPO = { 'Requisição Não Planejada': 'Não Planj.' };

function graficoTipo(id, atual, dados, campo, fmtLabel, extraY) {
    var ctx = document.getElementById(id).getContext('2d');
    if (atual) atual.destroy();
    if (!dados.length) return null;
    var rows = ORDEM_TIPOS.map(function (t) {
        return dados.filter(function (r) { return r.TIPO === t; })[0] || { TIPO: t };
    });
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: rows.map(function (r) { return ROTULO_TIPO[r.TIPO] || r.TIPO; }),
            datasets: [{ data: rows.map(function (r) { return parseFloat(r[campo] || 0); }),
                         backgroundColor: rows.map(function (r) { return CORES[r.TIPO] || '#607d8b'; }),
                         borderRadius: 4 }]
        },
        options: opcoes(fmtLabel, function (ev, el) { if (el.length) abrirDrill(drillTipo(rows[el[0].index])); }, extraY)
    });
}

function renderDistribChart(rows) {
    _chDist = graficoTipo('chartDistrib', _chDist, rows, 'PERC_QTD',
        function (v) { return num(v, 2) + '%'; },
        { zero: true, ticks: { font: { size: 10 }, callback: function (v) { return v + '%'; } } });
}

function renderMinutosChart(rows) {
    _chMin = graficoTipo('chartMinutos', _chMin, rows, 'MINUTOS', function (v) { return num(v, 0); });
}

function renderGeradasChart(d) {
    var ctx = document.getElementById('chartGeradas').getContext('2d');
    if (_chGer) _chGer.destroy();
    var drills = ['geradas', 'atendidas'];
    _chGer = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Geradas', 'Atendidas'],
            datasets: [{ data: [parseInt(d.GERADAS || 0, 10), parseInt(d.ATENDIDAS || 0, 10)],
                         backgroundColor: ['#1565c0', '#c62828'], borderRadius: 4 }]
        },
        options: opcoes(function (v) { return v; }, function (ev, el) {
            if (el.length) abrirDrill({ tipo: drills[el[0].index], titulo: 'Ordens ' + (el[0].index ? 'Atendidas' : 'Geradas') });
        }, { ticks: { font: { size: 10 }, precision: 0 } })
    });
}

/* Custos: mesmas 4 barras do OTM (orçamento + contabilidade contas 1541/1550) */
function onCustos(json) {
    var d = json.data || {};
    var ctx = document.getElementById('chartCustos').getContext('2d');
    if (_chCust) _chCust.destroy();
    _chCust = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Orçado', 'Mov. Estoque', 'Serv. Terceiros - Compra Direta', 'Realizado'],
            datasets: [{ data: [d.ORCADO, d.MOV_ESTOQUE, d.SERV_TERCEIROS, d.REALIZADO].map(function (v) { return parseFloat(v || 0); }),
                         backgroundColor: ['#90a4ae', '#1565c0', '#e65100', '#2e7d32'], borderRadius: 4 }]
        },
        options: opcoes(moeda, null, { ticks: { font: { size: 10 }, callback: function (v) { return moeda(v); } } })
    });
}

/* =====================================================================
 * Drill-down: janela com pilha de níveis (Voltar) — mesmos detalhes do OTM
 * ===================================================================== */
var COLS_ORDENS = [['ORDEM', 'Ordem', 't'], ['DT_SOLICITACAO', 'Data', 'd'], ['DT_FECHAMENTO', 'Data Fechamento', 'd'], ['TIPO', 'Tipo', 't']];
var DRILLS = {
    grupo:   { cols: [['MAQUINA', 'Máquina', 't'], ['VALOR', 'Valor em $', 'm']], tot: ['VALOR'] },
    tipo:    { cols: [['MAQUINA', 'Máquina', 't'], ['MINUTOS', 'Quantidade Min.', 'n'], ['VALOR', 'Valor em $', 'm']], tot: ['MINUTOS', 'VALOR'],
               next: function (r) { return { tipo: 'maquina', maq: r.MAQUINA, tp: r.TP_OS, titulo: r.MAQUINA }; } },
    maquina: { cols: [['NUM_ORDEM', 'Ordem', 't'], ['MINUTOS', 'Quantidade Min.', 'n'], ['VALOR', 'Valor em $', 'm']], tot: ['MINUTOS', 'VALOR'],
               next: function (r) { return { tipo: 'ordem', o: r.ID, titulo: 'Ordem ' + r.NUM_ORDEM }; } },
    ordem:   { cols: [['MOV_ESTOQUE', 'Mov. Estoque', 'm'], ['SERV_TERCEIROS', 'Serv. Terc. - Comp. Direta', 'm']] },
    geradas:   { cols: COLS_ORDENS },
    atendidas: { cols: COLS_ORDENS },
    prev_liberadas:   { cols: COLS_ORDENS.slice(0, 3) },
    prev_realizadas:  { cols: COLS_ORDENS.slice(0, 3) },
    prev_programadas: { cols: [['ORDEM', 'Ordem', 't'], ['DT_PREVISTA', 'Data', 'd'], ['MAQUINA', 'Máquina', 't']] },
    func_horas:  { cols: [['ORDEM', 'Ordem', 't'], ['MINUTOS', 'Quantidade Minutos', 'n'], ['DT_SOLICITACAO', 'Data Abertura', 'd'], ['DT_FECHAMENTO', 'Data Fechamento', 'd']], tot: ['MINUTOS'] },
    func_ordens: { cols: [['ORDEM', 'Ordem', 't'], ['DATA', 'Data', 'd'], ['QTDE', 'Quantidade Minutos', 'n'], ['TIPO', 'Tipo', 't'], ['SIT', 'Situação', 't']], tot: ['QTDE'] },
    estoque: { cols: [['ITEM', 'Item', 't'], ['MASC_ID', 'Masc Id', 't'], ['MASCARA', 'Máscara', 't'], ['QTDE_SALDO', 'Qtde Saldo', 'q'],
                      ['SALDO_TOTAL', 'Saldo Total', 'm'], ['CME', 'CME', 'm'], ['QTDE_SEM_GIRO', 'Qtde Saldo S. Giro', 'q'], ['SALDO_SEM_GIRO', 'Saldo Total S. Giro', 'm']],
               tot: ['QTDE_SALDO', 'SALDO_TOTAL', 'CME', 'QTDE_SEM_GIRO', 'SALDO_SEM_GIRO'] }
};
var _pilha = [];
var _modal = null;

function ligarLinhas(tb, rows, fnDrill) {
    tb.querySelectorAll('tr.clk').forEach(function (tr) {
        tr.addEventListener('click', function () {
            var p = fnDrill(rows[parseInt(tr.dataset.i, 10)]);
            if (p) abrirDrill(p);
        });
    });
}

function abrirDrill(p) {
    if (!_modal) _modal = new bootstrap.Modal(document.getElementById('mdbModal'));
    var aberto = document.getElementById('mdbModal').classList.contains('show');
    if (!aberto) _pilha = [];
    _pilha.push({ p: p, rows: null });
    if (!aberto) _modal.show();
    carregarNivel();
}

function voltarDrill() {
    if (_pilha.length > 1) { _pilha.pop(); renderNivel(); }
}

function irPara(i) {
    _pilha = _pilha.slice(0, i + 1);
    renderNivel();
}

function carregarNivel() {
    var nv = _pilha[_pilha.length - 1];
    var qs = _qs;
    Object.keys(nv.p).forEach(function (k) {
        if (k !== 'titulo' && k !== 'filtro') qs += '&' + k + '=' + encodeURIComponent(nv.p[k] == null ? '' : nv.p[k]);
    });
    document.getElementById('mdbCorpo').innerHTML = '<div class="mdb-load">Carregando...</div>';
    cabecalho();
    fetch(_base + 'manutencao-api-dash-drill?' + qs)
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json.success) throw new Error(json.error || 'Erro');
            var rows = json.data || [];
            var f = nv.p.filtro;
            if (f) rows = rows.filter(function (r) { return f === 'Aberta' ? !r.DT_FECHAMENTO : r.TIPO === f; });
            nv.rows = rows;
            if (_pilha[_pilha.length - 1] === nv) renderNivel();
        })
        .catch(function (e) {
            document.getElementById('mdbCorpo').innerHTML = '<div class="mdb-empty" style="color:#c62828">' + esc(e.message) + '</div>';
        });
}

function cabecalho() {
    var nv = _pilha[_pilha.length - 1];
    set('mdbTitulo', nv.p.titulo || 'Detalhe');
    document.getElementById('mdbVoltar').style.display = _pilha.length > 1 ? '' : 'none';
    var tr = document.getElementById('mdbTrilha');
    tr.style.display = _pilha.length > 1 ? '' : 'none';
    tr.innerHTML = _pilha.map(function (n, i) {
        return i < _pilha.length - 1 ? '<a data-i="' + i + '">' + esc(n.p.titulo) + '</a>' : '<b>' + esc(n.p.titulo) + '</b>';
    }).join(' &rsaquo; ');
    tr.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { irPara(parseInt(a.dataset.i, 10)); }); });
}

function renderNivel() {
    var nv  = _pilha[_pilha.length - 1];
    var def = DRILLS[nv.p.tipo];
    cabecalho();
    if (!nv.rows) { carregarNivel(); return; }
    var rows = nv.rows;
    set('mdbRodape', rows.length + ' registro(s)' + (def.next ? ' — clique na linha para detalhar' : ''));
    if (!rows.length) { document.getElementById('mdbCorpo').innerHTML = '<div class="mdb-empty">Sem dados</div>'; return; }

    var h = '<table class="mdb-tbl"><thead><tr>' + def.cols.map(function (c) {
        return '<th' + (isNum(c[2]) ? ' class="r" style="text-align:right"' : '') + '>' + c[1] + '</th>';
    }).join('') + '</tr></thead><tbody>';
    h += rows.map(function (r, i) {
        return '<tr' + (def.next ? ' class="clk" data-i="' + i + '"' : '') + '>' + def.cols.map(function (c) {
            return '<td' + (isNum(c[2]) ? ' class="r"' : '') + '>' + celula(r[c[0]], c[2]) + '</td>';
        }).join('') + '</tr>';
    }).join('');
    if (def.tot && rows.length > 1) {
        h += '<tr class="tot">' + def.cols.map(function (c, i) {
            if (def.tot.indexOf(c[0]) < 0) return '<td>' + (i === 0 ? 'Total' : '') + '</td>';
            var s = rows.reduce(function (a, r) { return a + parseFloat(r[c[0]] || 0); }, 0);
            return '<td class="r">' + celula(s, c[2]) + '</td>';
        }).join('') + '</tr>';
    }
    var corpo = document.getElementById('mdbCorpo');
    corpo.innerHTML = h + '</tbody></table>';
    if (def.next) {
        corpo.querySelectorAll('tr.clk').forEach(function (tr) {
            tr.addEventListener('click', function () {
                _pilha.push({ p: def.next(rows[parseInt(tr.dataset.i, 10)]), rows: null });
                carregarNivel();
            });
        });
    }
}

function csvDrill() {
    var nv = _pilha[_pilha.length - 1];
    if (!nv || !nv.rows || !nv.rows.length) return;
    var def = DRILLS[nv.p.tipo];
    var q = function (v) { v = v == null ? '' : String(v); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    var linhas = [def.cols.map(function (c) { return q(c[1]); }).join(';')].concat(nv.rows.map(function (r) {
        return def.cols.map(function (c) {
            var v = r[c[0]];
            return isNum(c[2]) && v != null && v !== '' ? String(v).replace('.', ',') : q(v);
        }).join(';');
    }));
    var blob = new Blob(['﻿' + linhas.join('\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (nv.p.titulo || 'detalhe').replace(/[^\w\-]+/g, '_') + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

/* ---- utils ---- */
function isNum(t) { return t === 'n' || t === 'm' || t === 'q'; }
function celula(v, t) {
    if (t === 'm') return moeda(v);
    if (t === 'n') return num(v, 0);
    if (t === 'q') return num(v, 3);
    return esc(v == null ? '' : v);
}
function vazio(n) { return '<tr><td colspan="' + n + '" class="mdb-empty">Sem dados</td></tr>'; }
function set(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
function status(msg)  { var el = document.getElementById('mdbStatus'); if (el) el.textContent = msg; }
function fmt(v)  { return v != null && v !== '' ? Math.round(parseFloat(v)).toLocaleString('pt-BR') : '0'; }
function num(v, d) { return parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: d == null ? 1 : d }); }
function moeda(v){ return parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function esc(s)  { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
