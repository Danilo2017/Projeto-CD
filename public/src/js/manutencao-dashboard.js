'use strict';

var _base   = '/';
var _emprId = 0;
var _chDist = null;
var _chGer  = null;
var _chMin  = null;
var _chCust = null;

var CORES = {
    'Corretiva':     '#e65100',
    'Programada':    '#1565c0',
    'Preventiva':    '#c62828',
    'Melhoria':      '#2e7d32',
    'Nao Planejada': '#ffd600'
};
var BADGE = {
    'Corretiva': 'C', 'Programada': 'G',
    'Preventiva': 'P', 'Melhoria': 'M', 'Nao Planejada': 'N'
};
var ROW = {
    'Corretiva': 'row-C', 'Programada': 'row-G',
    'Preventiva': 'row-P', 'Melhoria': 'row-M', 'Nao Planejada': 'row-N'
};

document.addEventListener('DOMContentLoaded', function () {
    var d   = document.getElementById('mdb-app-data');
    _base   = d ? d.dataset.base : '/';
    _emprId = d ? parseInt(d.dataset.empr || '0', 10) : 0;
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
    var qs  = 'empr_id=' + _emprId + '&data_ini=' + di + '&data_fim=' + df;
    status('Carregando...');
    Promise.allSettled([
        fetchJson('manutencao-api-dash-resumo?' + qs,     onResumo),
        fetchJson('manutencao-api-dash-distrib?' + qs,    onDistrib),
        fetchJson('manutencao-api-dash-geradas?' + qs,    onGeradas),
        fetchJson('manutencao-api-dash-grupos?' + qs,     onGrupos),
        fetchJson('manutencao-api-dash-preventivas?' + qs,onPreventivas),
        fetchJson('manutencao-api-dash-func-ordens?' + qs,onFuncOrdens),
        fetchJson('manutencao-api-dash-minutos?' + qs,    onMinutos),
        fetchJson('manutencao-api-dash-func-horas?' + qs, onFuncHoras)
    ]).then(function () { status(''); });
}

function fetchJson(url, callback) {
    return fetch(_base + url)
        .then(function (r) { return r.json(); })
        .then(function (json) { callback(json); })
        .catch(function () {});
}

/* ---- callbacks ---- */

function onResumo(json) {
    var d = json.success ? (json.data || {}) : {};
    set('kTotal',      d.TOTAL       || 0);
    set('kGeradas',    d.TOTAL       || 0);
    set('kAbertas',    d.ABERTAS     || 0);
    set('kCorretivas', d.CORRETIVAS  || 0);
    set('kPreventivas',d.PREVENTIVAS || 0);
    // kAtendidas vem de onGeradas (DT_FECHAMENTO); kProgramadas vem de onPreventivas
}

function onDistrib(json) {
    var rows = json.success ? (json.data || []) : [];
    renderTipoTabela(rows);
    renderDistribChart(rows);
}

function onGeradas(json) {
    var d = json.success ? (json.data || {}) : {};
    renderGeradasChart(d);
    renderCountsPanel(d);
    set('kAtendidas', fmt(d.ATENDIDAS));
}

function onGrupos(json) {
    var rows = json.success ? (json.data || []) : [];
    var tb = document.querySelector('#tbGrupos tbody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="3" class="mdb-empty">Sem dados</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(function (r) {
        return '<tr><td class="c">' + esc(r.EMPR_ID || '') + '</td>' +
            '<td><b>' + esc(r.GRUPO || '') + '</b></td>' +
            '<td class="r">' + moeda(r.VALOR || 0) + '</td></tr>';
    }).join('');
}

function onPreventivas(json) {
    _prevData = json.success ? (json.data || {}) : {};
    montarCountsPanel(document.getElementById('mdbCounts'));
    set('kProgramadas', fmt(_prevData.PROGRAMADAS));
}

function onFuncOrdens(json) {
    var rows = json.success ? (json.data || []) : [];
    var tb = document.querySelector('#tbFuncOrdens tbody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="3" class="mdb-empty">Sem dados</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(function (r) {
        return '<tr><td>' + esc(r.FUNCIONARIO || '') + '</td>' +
            '<td class="r" style="color:#2e7d32;font-weight:700">' + fmt(r.FECHADAS) + '</td>' +
            '<td class="r" style="color:#e65100">' + fmt(r.ABERTAS) + '</td></tr>';
    }).join('');
}

function onMinutos(json) {
    var rows = json.success ? (json.data || []) : [];
    renderMinutosChart(rows);
    renderCustosChart(rows);
}

function onFuncHoras(json) {
    var rows = json.success ? (json.data || []) : [];
    var tb = document.querySelector('#tbFuncHoras tbody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="4" class="mdb-empty">Sem dados</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(function (r) {
        return '<tr><td>' + esc(r.FUNCIONARIO || '') + '</td>' +
            '<td class="r">' + num(r.MIN_EXEC || 0) + '</td>' +
            '<td class="r">' + fmt(r.QTD_CORRETIVAS) + '</td>' +
            '<td class="r">' + num(r.MTTR || 0) + '</td></tr>';
    }).join('');
}

/* ---- painel de counts (centro) ---- */
var _geradasData = null;
var _prevData    = null;

function renderCountsPanel(d) {
    _geradasData = d;
    montarCountsPanel(document.getElementById('mdbCounts'));
}

function montarCountsPanel(el) {
    var g = _geradasData || {};
    var p = _prevData    || {};

    var html =
        '<table class="mini-tbl">' +
        '<thead><tr><th>OS</th><th>Quantidade</th></tr></thead>' +
        '<tbody>' +
        '<tr><td>Geradas</td><td class="r">' + fmt(g.GERADAS) + '</td></tr>' +
        '<tr><td>Atendidas</td><td class="r">' + fmt(g.ATENDIDAS) + '</td></tr>' +
        '</tbody></table>';

    html +=
        '<table class="mini-tbl prev">' +
        '<thead><tr><th>OS Preventivas</th><th>Quantidade</th></tr></thead>' +
        '<tbody>' +
        '<tr><td>Programadas</td><td class="r">' + fmt(p.PROGRAMADAS) + '</td></tr>' +
        '<tr><td>Liberadas</td><td class="r">' + fmt(p.LIBERADAS) + '</td></tr>' +
        '<tr><td>Realizadas</td><td class="r">' + fmt(p.REALIZADAS) + '</td></tr>' +
        '</tbody></table>';

    el.innerHTML = html;
}

/* ---- tabela Tipo / Qtd / Valor ---- */
function renderTipoTabela(rows) {
    var tb = document.querySelector('#tbTipo tbody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="3" class="mdb-empty">Sem dados</td></tr>';
        return;
    }
    var totalQtd  = rows.reduce(function (s, r) { return s + parseInt(r.QTD || 0, 10); }, 0);
    var totalVal  = rows.reduce(function (s, r) { return s + parseFloat(r.VALOR || 0); }, 0);

    var totalRow = '<tr class="row-T">' +
        '<td><b>Total</b></td>' +
        '<td class="r"><b>' + totalQtd + '</b></td>' +
        '<td class="r"><b>' + moeda(totalVal) + '</b></td></tr>';

    var dataRows = rows.map(function (r) {
        var key = BADGE[r.TIPO] || 'N';
        var cls = ROW[r.TIPO] || 'row-N';
        return '<tr class="' + cls + '">' +
            '<td><span class="bt bt-' + key + '">' + esc(r.TIPO) + '</span></td>' +
            '<td class="r">' + fmt(r.QTD) + '</td>' +
            '<td class="r">' + moeda(r.VALOR || 0) + '</td></tr>';
    }).join('');

    tb.innerHTML = totalRow + dataRows;
}

/* ---- charts (verticais) ---- */
var chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } }
};

function renderDistribChart(rows) {
    var ctx = document.getElementById('chartDistrib').getContext('2d');
    if (_chDist) _chDist.destroy();
    if (!rows.length) return;

    var labels = rows.map(function (r) { return r.TIPO; });
    var data   = rows.map(function (r) {
        return parseFloat(r.PERC_QTD || r.PERCENTUAL || 0);
    });
    var cores  = labels.map(function (l) { return CORES[l] || '#607d8b'; });

    _chDist = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '%',
                data: data,
                backgroundColor: cores,
                borderRadius: 4
            }]
        },
        options: Object.assign({}, chartDefaults, {
            plugins: {
                legend: { display: true, position: 'bottom',
                    labels: { font: { size: 10 }, boxWidth: 12 } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 }, callback: function (v) { return v + '%'; } }
                },
                x: { ticks: { font: { size: 10 } } }
            }
        })
    });
}

function renderGeradasChart(d) {
    var ctx = document.getElementById('chartGeradas').getContext('2d');
    if (_chGer) _chGer.destroy();
    var ger  = parseInt(d.GERADAS   || 0, 10);
    var aten = parseInt(d.ATENDIDAS || 0, 10);
    _chGer = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Geradas', 'Atendidas'],
            datasets: [
                { data: [ger,  0],   backgroundColor: '#1565c0', label: 'Geradas',   borderRadius: 4 },
                { data: [0,    aten],backgroundColor: '#c62828', label: 'Atendidas', borderRadius: 4 }
            ]
        },
        options: Object.assign({}, chartDefaults, {
            plugins: {
                legend: { display: true, position: 'bottom',
                    labels: { font: { size: 10 }, boxWidth: 12 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 10 }, stepSize: 1 } },
                x: { ticks: { font: { size: 11 } } }
            }
        })
    });
}

function renderMinutosChart(rows) {
    var ctx = document.getElementById('chartMinutos').getContext('2d');
    if (_chMin) _chMin.destroy();
    if (!rows.length) return;

    var labels = rows.map(function (r) { return r.TIPO; });
    var data   = rows.map(function (r) { return parseFloat(r.MINUTOS || 0); });
    var cores  = labels.map(function (l) { return CORES[l] || '#607d8b'; });

    _chMin = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Minutos',
                data: data,
                backgroundColor: cores,
                borderRadius: 4
            }]
        },
        options: Object.assign({}, chartDefaults, {
            plugins: {
                legend: { display: true, position: 'bottom',
                    labels: { font: { size: 10 }, boxWidth: 12 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 10 } } },
                x: { ticks: { font: { size: 10 } } }
            }
        })
    });
}

function renderCustosChart(rows) {
    var ctx = document.getElementById('chartCustos').getContext('2d');
    if (_chCust) _chCust.destroy();
    var totalServ = rows.reduce(function (s, r) { return s + parseFloat(r.VALOR_SERVICO || r.VALOR || 0); }, 0);
    _chCust = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mov. Estoque', 'Serv. Terceiros', 'Realizado'],
            datasets: [{
                data: [0, totalServ, totalServ],
                backgroundColor: ['#1565c0', '#e65100', '#2e7d32'],
                borderRadius: 4,
                label: 'R$'
            }]
        },
        options: Object.assign({}, chartDefaults, {
            plugins: {
                legend: { display: true, position: 'bottom',
                    labels: { font: { size: 10 }, boxWidth: 12 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 10 } } },
                x: { ticks: { font: { size: 10 } } }
            }
        })
    });
}

/* ---- utils ---- */
function set(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
function status(msg)  { var el = document.getElementById('mdbStatus'); if (el) el.textContent = msg; }
function fmt(v)  { return v != null ? String(parseInt(v, 10)) : '0'; }
function num(v)  { return parseFloat(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 }); }
function moeda(v){ return parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function esc(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
