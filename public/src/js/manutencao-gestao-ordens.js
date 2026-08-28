/* Gestão de Ordens de Manutenção */
'use strict';

let _emprId   = 0;
let _dataIni  = '';
let _dataFim  = '';
let _modoDetalhe = ''; // 'aberta' | 'atendimento'
let _maqId    = 0;
let _prio     = 0;
let _ordensSelecionadas = [];

/* ─── Inicialização ─────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    _emprId = EMPR_SESS;
    carregarTodos();
});

function params() {
    _emprId  = EMPR_SESS;
    _dataIni = document.getElementById('dataIni').value;
    _dataFim = document.getElementById('dataFim').value;
    return new URLSearchParams({ empr_id: _emprId, data_ini: _dataIni, data_fim: _dataFim });
}

function carregarTodos() {
    const p = params();
    carregarAberta(p);
    carregarAtendimento(p);
    carregarLiberada(p);
    carregarProgramada(p);
}

/* ─── Prioridade ─────────────────────────────── */
function prioBadge(n) {
    const cls = 'prio prio-' + n;
    return '<span class="' + cls + '">' + n + '</span>';
}

/* ─── ABERTA ─────────────────────────────────── */
async function carregarAberta(p) {
    const tb = document.getElementById('tbAberta');
    tb.innerHTML = '<tr><td colspan="4" class="man-empty">Carregando...</td></tr>';
    try {
        const r = await fetch(BASE + 'manutencao-api-aberta?' + p);
        const d = await r.json();
        const rows = d.data || [];
        document.getElementById('cntAberta').textContent = rows.length + ' registro(s)';
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="4" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function (row) {
            return '<tr onclick="abrirDetalheAberta(' + row.MAQUINA_ID + ',' + row.PRIORIDADE + ',\'' + esc(row.MAQUINA) + '\')">'
                + '<td>' + esc(row.MAQUINA) + '</td>'
                + '<td class="c">' + row.TOTAL + '</td>'
                + '<td class="c">' + prioBadge(row.PRIORIDADE) + '</td>'
                + '<td class="c" style="font-size:11px">' + (row.DT_MAX || '') + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        tb.innerHTML = '<tr><td colspan="4" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
        console.error(err);
    }
}

/* ─── EM ATENDIMENTO ─────────────────────────── */
async function carregarAtendimento(p) {
    const tb = document.getElementById('tbAtend');
    tb.innerHTML = '<tr><td colspan="3" class="man-empty">Carregando...</td></tr>';
    try {
        const r = await fetch(BASE + 'manutencao-api-atendimento?' + new URLSearchParams({ empr_id: EMPR_SESS }));
        const d = await r.json();
        const rows = d.data || [];
        document.getElementById('cntAtend').textContent = rows.length + ' registro(s)';
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="3" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function (row) {
            return '<tr onclick="abrirDetalheAtendimento(' + row.MAQUINA_ID + ',' + row.PRIORIDADE + ',\'' + esc(row.MAQUINA) + '\')">'
                + '<td>' + esc(row.MAQUINA) + '</td>'
                + '<td class="c">' + row.TOTAL + '</td>'
                + '<td class="c">' + prioBadge(row.PRIORIDADE) + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        tb.innerHTML = '<tr><td colspan="3" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
        console.error(err);
    }
}

/* ─── LIBERADA ───────────────────────────────── */
async function carregarLiberada(p) {
    const tb = document.getElementById('tbLib');
    tb.innerHTML = '<tr><td colspan="2" class="man-empty">Carregando...</td></tr>';
    try {
        const r = await fetch(BASE + 'manutencao-api-liberada?' + p);
        const d = await r.json();
        const rows = d.data || [];
        document.getElementById('cntLib').textContent = rows.length + ' registro(s)';
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="2" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td>' + esc(row.MAQUINA) + '</td>'
                + '<td class="c">' + (row.DT_PREVISTA || '') + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        tb.innerHTML = '<tr><td colspan="2" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
        console.error(err);
    }
}

/* ─── PROGRAMADA ─────────────────────────────── */
async function carregarProgramada(p) {
    const tb = document.getElementById('tbProg');
    tb.innerHTML = '<tr><td colspan="3" class="man-empty">Carregando...</td></tr>';
    try {
        const r = await fetch(BASE + 'manutencao-api-programada?' + p);
        const d = await r.json();
        const rows = d.data || [];
        document.getElementById('cntProg').textContent = rows.length + ' registro(s)';
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="3" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td class="c">' + (row.NUM_ORDEM || '') + '</td>'
                + '<td>' + esc(row.MAQUINA) + '</td>'
                + '<td class="c">' + (row.DT_PREVISTA || '') + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        tb.innerHTML = '<tr><td colspan="3" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
        console.error(err);
    }
}

/* ─── DETALHE — Abertas ─────────────────────── */
async function abrirDetalheAberta(maqId, prio, maqName) {
    _modoDetalhe = 'aberta';
    _maqId = maqId;
    _prio  = prio;
    _ordensSelecionadas = [];
    document.getElementById('modalDetalheTitulo').textContent = 'Aberta — ' + maqName;
    document.getElementById('modalAcoes').style.display = 'flex';

    const head = document.getElementById('tblDetalheHead');
    const body = document.getElementById('tblDetalheBody');
    head.innerHTML = '<tr>'
        + '<th style="width:30px"></th>'
        + '<th>Ordem</th><th>Data Solic.</th><th>Recurso</th>'
        + '<th>Tipo OS</th><th>Tp. Prob.</th><th>Crítico</th><th>Maq. Par.</th>'
        + '<th class="c">OK</th><th>Funcionário</th><th>Problema</th>'
        + '</tr>';
    body.innerHTML = '<tr><td colspan="11" class="man-empty">Carregando...</td></tr>';

    const modal = new bootstrap.Modal(document.getElementById('modalDetalhe'));
    modal.show();

    try {
        const p = new URLSearchParams({ maquina_id: maqId, prioridade: prio, empr_id: _emprId, data_ini: _dataIni, data_fim: _dataFim });
        const r = await fetch(BASE + 'manutencao-api-detalhar-aberta?' + p);
        const d = await r.json();
        const rows = d.data || [];
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="11" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (row) {
            const prob = row.DES_PROBLEMA ? '<span class="prob-icon" title="' + esc(row.DES_PROBLEMA) + '">⚠</span>' : '';
            const okBadge = row.TEM_OK === 'S' ? '<span class="ok-badge">✔</span>' : '';
            return '<tr data-id="' + row.ID + '" onclick="toggleRow(this)">'
                + '<td class="c"><input type="checkbox" class="det-chk" value="' + row.ID + '" onclick="event.stopPropagation();syncCheck(this)"></td>'
                + '<td>' + (row.NUM_ORDEM || '') + '</td>'
                + '<td style="font-size:11px">' + (row.DT_SOLICITACAO || '') + '</td>'
                + '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis">' + esc(row.RECURSO || '') + '</td>'
                + '<td>' + (row.TP_OS || '') + '</td>'
                + '<td>' + (row.TP_PROBLEMA || '') + '</td>'
                + '<td class="c">' + (row.IND_CRITICO || '') + '</td>'
                + '<td class="c">' + (row.MAQ_PARADA || '') + '</td>'
                + '<td class="c">' + okBadge + '</td>'
                + '<td style="font-size:11px">' + esc(row.FUNC_OK || '') + '</td>'
                + '<td class="c">' + prob + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        body.innerHTML = '<tr><td colspan="11" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
    }
}

/* ─── DETALHE — Atendimento ─────────────────── */
async function abrirDetalheAtendimento(maqId, prio, maqName) {
    _modoDetalhe = 'atendimento';
    _maqId = maqId;
    _prio  = prio;
    _ordensSelecionadas = [];
    document.getElementById('modalDetalheTitulo').textContent = 'Em Atendimento — ' + maqName;
    document.getElementById('modalAcoes').style.display = 'flex';

    const head = document.getElementById('tblDetalheHead');
    const body = document.getElementById('tblDetalheBody');
    head.innerHTML = '<tr>'
        + '<th style="width:30px"></th>'
        + '<th>Ordem</th><th>Data Solic.</th><th>Recurso</th>'
        + '<th>Tipo OS</th><th>Tp. Prob.</th><th>Crítico</th><th>Maq. Par.</th>'
        + '<th class="c">OK</th><th>Funcionário</th><th>Problema</th>'
        + '</tr>';
    body.innerHTML = '<tr><td colspan="11" class="man-empty">Carregando...</td></tr>';

    const modal = new bootstrap.Modal(document.getElementById('modalDetalhe'));
    modal.show();

    try {
        const p = new URLSearchParams({ maquina_id: maqId, prioridade: prio });
        const r = await fetch(BASE + 'manutencao-api-detalhar-atendimento?' + p);
        const d = await r.json();
        const rows = d.data || [];
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="11" class="man-empty">Nenhum registro</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (row) {
            const prob = row.DES_PROBLEMA ? '<span class="prob-icon" title="' + esc(row.DES_PROBLEMA) + '">⚠</span>' : '';
            const okBadge = row.TEM_OK === 'S' ? '<span class="ok-badge">✔</span>' : '';
            return '<tr data-id="' + row.ID + '" onclick="toggleRow(this)">'
                + '<td class="c"><input type="checkbox" class="det-chk" value="' + row.ID + '" onclick="event.stopPropagation();syncCheck(this)"></td>'
                + '<td>' + (row.NUM_ORDEM || '') + '</td>'
                + '<td style="font-size:11px">' + (row.DT_SOLICITACAO || '') + '</td>'
                + '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis">' + esc(row.RECURSO || '') + '</td>'
                + '<td>' + (row.TP_OS || '') + '</td>'
                + '<td>' + (row.TP_PROBLEMA || '') + '</td>'
                + '<td class="c">' + (row.IND_CRITICO || '') + '</td>'
                + '<td class="c">' + (row.MAQ_PARADA || '') + '</td>'
                + '<td class="c">' + okBadge + '</td>'
                + '<td style="font-size:11px">' + esc(row.FUNC_OK || '') + '</td>'
                + '<td class="c">' + prob + '</td>'
                + '</tr>';
        }).join('');
    } catch (err) {
        body.innerHTML = '<tr><td colspan="11" class="man-empty" style="color:red">Erro ao carregar</td></tr>';
    }
}

/* ─── Seleção de linhas no modal ─────────────── */
function toggleRow(tr) {
    const chk = tr.querySelector('.det-chk');
    chk.checked = !chk.checked;
    tr.classList.toggle('row-selected', chk.checked);
    syncSelecionadas();
}

function syncCheck(chk) {
    const tr = chk.closest('tr');
    tr.classList.toggle('row-selected', chk.checked);
    syncSelecionadas();
}

function syncSelecionadas() {
    _ordensSelecionadas = [];
    document.querySelectorAll('#tblDetalheBody .det-chk:checked').forEach(function (c) {
        _ordensSelecionadas.push(parseInt(c.value));
    });
}

function getSelectedIds() {
    syncSelecionadas();
    return _ordensSelecionadas;
}

/* ─── Ações ──────────────────────────────────── */
async function acaoAtender() {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Selecione ao menos uma ordem.'); return; }
    if (!confirm('Deseja iniciar o atendimento das ordens selecionadas?')) return;
    await postAcao('manutencao-api-atender', { ids });
}

function abrirModalOk() {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Selecione ao menos uma ordem.'); return; }
    carregarFuncionarios();
    const m = new bootstrap.Modal(document.getElementById('modalOk'));
    m.show();
}

async function carregarFuncionarios() {
    try {
        const r = await fetch(BASE + 'manutencao-api-funcionarios?empr_id=' + EMPR_SESS);
        const d = await r.json();
        const sel = document.getElementById('selFuncionario');
        sel.innerHTML = '<option value="">Selecione...</option>';
        (d.data || []).forEach(function (f) {
            const opt = document.createElement('option');
            opt.value = f.ID;
            opt.textContent = f.NOME;
            sel.appendChild(opt);
        });
    } catch (err) { console.error(err); }
}

async function acaoOk() {
    const funcId = document.getElementById('selFuncionario').value;
    if (!funcId) { alert('Selecione o funcionário.'); return; }
    const ids = getSelectedIds();
    bootstrap.Modal.getInstance(document.getElementById('modalOk')).hide();
    await postAcao('manutencao-api-ok', { ids, func_id: parseInt(funcId) });
}

async function acaoDesOk() {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Selecione ao menos uma ordem.'); return; }
    if (!confirm('Deseja desmarcar o OK das ordens selecionadas?')) return;
    await postAcao('manutencao-api-des-ok', { ids });
}

function abrirModalFechar() {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Selecione ao menos uma ordem.'); return; }
    document.getElementById('inpObs').value = '';
    const m = new bootstrap.Modal(document.getElementById('modalFechar'));
    m.show();
}

async function acaoFechar() {
    const ids = getSelectedIds();
    const obs = document.getElementById('inpObs').value.trim();
    if (!confirm('Deseja fechar as ordens selecionadas?')) return;
    bootstrap.Modal.getInstance(document.getElementById('modalFechar')).hide();
    await postAcao('manutencao-api-fechar', { ids, obs });
}

async function acaoExcluir() {
    const ids = getSelectedIds();
    if (!ids.length) { alert('Selecione ao menos uma ordem.'); return; }
    if (!confirm('Deseja EXCLUIR as ordens selecionadas? Esta ação não pode ser desfeita.')) return;
    await postAcao('manutencao-api-excluir', { ids });
}

async function postAcao(rota, body) {
    try {
        const r = await fetch(BASE + rota, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const d = await r.json();
        if (!d.success) { alert('Erro: ' + d.error); return; }
        alert(d.message || 'Operação realizada com sucesso.');
        bootstrap.Modal.getInstance(document.getElementById('modalDetalhe')).hide();
        carregarTodos();
    } catch (err) {
        alert('Erro de comunicação: ' + err.message);
    }
}

/* ─── Utilitário ─────────────────────────────── */
function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
