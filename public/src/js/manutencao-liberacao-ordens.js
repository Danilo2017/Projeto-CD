'use strict';

let _base      = '/';
let _emprId    = 0;
let _rows      = [];
let _sel       = new Set();
let _lastCount = -1;
let _pollTimer = null;
let _audioCtx  = null;

const PRIO_LABEL    = { 0: 'Crítica', 1: 'Alta', 2: 'Média', 3: 'Baixa', 9: 'S/Prio' };
const POLL_INTERVAL = 30000;

document.addEventListener('DOMContentLoaded', function () {
    const d = document.getElementById('lib-app-data');
    _base   = d ? d.dataset.base   : '/';
    _emprId = d ? parseInt(d.dataset.empr || '0', 10) : 0;

    // Cria AudioContext na carga — fica suspenso até o 1º gesto do usuário
    try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) {}

    // Event delegation para seleção de cards (móvel) e linhas de tabela (desktop)
    document.getElementById('libContent').addEventListener('click', function (e) {
        const card = e.target.closest('.lib-card');
        const tr   = e.target.closest('tr[data-id]');
        const cb   = e.target.closest('input[type=checkbox][data-id]');

        if (cb) {
            // Checkbox da tabela desktop
            const id = parseInt(cb.dataset.id, 10);
            if (cb.checked) _sel.add(id); else _sel.delete(id);
            sincronizarBotoes();
            renderTabela();
        } else if (card) {
            // Card mobile — toggle seleção
            const id = parseInt(card.dataset.id, 10);
            if (_sel.has(id)) _sel.delete(id); else _sel.add(id);
            sincronizarBotoes();
            renderTabela();
        } else if (tr) {
            // Linha da tabela desktop
            const id = parseInt(tr.dataset.id, 10);
            if (_sel.has(id)) _sel.delete(id); else _sel.add(id);
            sincronizarBotoes();
            renderTabela();
        }
    });

    window.addEventListener('resize', renderTabela);
    carregar();
    agendarPoll();
});

/* ========== CARREGAMENTO ========== */

function carregar() {
    _sel.clear();
    sincronizarBotoes();
    fetch(_base + 'manutencao-api-lib-listar?empr_id=' + _emprId)
        .then(r => r.json())
        .then(json => {
            if (!json.success) { renderErro(json.error || 'Erro ao carregar'); return; }
            _rows = json.data || [];
            verificarAlertas();
            renderTabela();
        })
        .catch(err => renderErro(err.message));
}

function agendarPoll() {
    clearTimeout(_pollTimer);
    _pollTimer = setTimeout(function () {
        fetch(_base + 'manutencao-api-lib-listar?empr_id=' + _emprId)
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    _rows = json.data || [];
                    verificarAlertas();
                    renderTabela();
                }
            })
            .catch(() => {})
            .finally(agendarPoll);
    }, POLL_INTERVAL);
}

/* ========== ALERTAS ========== */

function verificarAlertas() {
    const count  = _rows.length;
    const alerta = document.getElementById('libAlert');

    if (count > 0) {
        alerta.classList.add('show');
        tocarBeep();
        vibrar();
        if (_lastCount >= 0 && count > _lastCount) {
            mostrarToast('⚠️ ' + (count - _lastCount) + ' nova(s) ordem(ns)!');
        }
    } else {
        alerta.classList.remove('show');
    }
    _lastCount = count;
}

/* Botão Som: resume AudioContext dentro de um gesto garantido */
function ativarSom() {
    if (!_audioCtx) {
        try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) {}
    }
    if (_audioCtx) {
        _audioCtx.resume().then(function () { tocarBeep(); }).catch(function () {});
    }
}

function tocarBeep() {
    if (!_audioCtx) return;
    // Se suspenso, tenta resumir (só funciona em contexto de gesto — falha silenciosa fora)
    if (_audioCtx.state === 'suspended') {
        _audioCtx.resume().catch(function () {});
        return;
    }
    try {
        var t = _audioCtx.currentTime;
        [880, 1100].forEach(function (freq, i) {
            var osc  = _audioCtx.createOscillator();
            var gain = _audioCtx.createGain();
            osc.connect(gain);
            gain.connect(_audioCtx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, t + i * 0.25);
            gain.gain.exponentialRampToValueAtTime(0.001, t + i * 0.25 + 0.22);
            osc.start(t + i * 0.25);
            osc.stop(t  + i * 0.25 + 0.25);
        });
    } catch (e) {}
}

function vibrar() {
    try { if (navigator.vibrate) navigator.vibrate([300, 100, 300, 100, 500]); } catch (e) {}
}

function mostrarToast(msg) {
    var t = document.createElement('div');
    Object.assign(t.style, {
        position: 'fixed', bottom: '20px', left: '50%',
        transform: 'translateX(-50%)',
        background: '#c62828', color: '#fff',
        padding: '12px 22px', borderRadius: '10px',
        fontSize: '14px', fontWeight: '700',
        zIndex: '99999', boxShadow: '0 4px 16px rgba(0,0,0,.35)',
        whiteSpace: 'nowrap'
    });
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 5000);
}

/* ========== RENDER ========== */

function isMobile() { return window.innerWidth <= 640; }

function renderTabela() {
    var cnt = document.getElementById('cntLib');
    if (!_rows.length) {
        document.getElementById('libContent').innerHTML =
            '<div class="lib-empty">Nenhuma ordem pendente de liberação.</div>';
        cnt.textContent = '0 registros';
        return;
    }
    cnt.textContent = _rows.length + ' registro(s)';
    isMobile() ? renderCards() : renderTable();
}

/* ---- Tabela desktop ---- */
function renderTable() {
    document.getElementById('libContent').innerHTML =
        '<table class="lib-table"><thead><tr>' +
        '<th style="width:30px"></th>' +
        '<th>Ordem</th><th>Data</th><th>Recurso</th>' +
        '<th>Tipo Prob.</th><th>Tipo</th>' +
        '<th class="c">Crítico</th><th class="c">Maq. Par.</th>' +
        '<th>Prob.</th><th class="c">Prio.</th>' +
        '<th class="c">Atendida</th><th>Funcionário</th>' +
        '</tr></thead><tbody>' +
        _rows.map(rowHtml).join('') +
        '</tbody></table>';
}

function rowHtml(r) {
    var sel   = _sel.has(r.ID) ? 'row-sel' : '';
    var prio  = r.PRIORIDADE != null ? r.PRIORIDADE : 9;
    var atend = r.TEM_ATEND === 'S'
        ? '<span class="atend-badge">&#10003;</span>'
        : '<span style="color:#aaa">&#8212;</span>';
    return '<tr class="' + sel + '" data-id="' + r.ID + '">' +
        '<td class="c"><input type="checkbox" data-id="' + r.ID + '" ' + (_sel.has(r.ID) ? 'checked' : '') +
            ' style="cursor:pointer;width:16px;height:16px"></td>' +
        '<td>' + (r.NUM_ORDEM || '') + '</td>' +
        '<td>' + (r.DT_SOLICITACAO || '') + '</td>' +
        '<td title="' + esc(r.RECURSO || '') + '">' + esc(r.RECURSO || '') + '</td>' +
        '<td>' + (r.TP_PROBLEMA || '') + '</td>' +
        '<td>' + (r.TP_OS || '') + '</td>' +
        '<td class="c">' + (r.IND_CRITICO || '') + '</td>' +
        '<td class="c">' + (r.MAQ_PARADA || '') + '</td>' +
        '<td title="' + esc(r.DES_PROBLEMA || '') + '">' + esc(r.DES_PROBLEMA || '').substring(0,40) + '</td>' +
        '<td class="c"><span class="prio prio-' + prio + '">' + prio + '</span></td>' +
        '<td class="c">' + atend + '</td>' +
        '<td>' + (r.FUNC_OK || '') + '</td>' +
        '</tr>';
}

/* ---- Cards mobile ---- */
function renderCards() {
    document.getElementById('libContent').innerHTML =
        '<div class="lib-cards">' + _rows.map(cardHtml).join('') + '</div>';
}

function cardHtml(r) {
    var sel   = _sel.has(r.ID);
    var prio  = r.PRIORIDADE != null ? r.PRIORIDADE : 9;
    var atend = r.TEM_ATEND === 'S'
        ? '<span class="atend-badge">&#10003; Atendida</span>'
        : '<span style="color:#aaa">Não atendida</span>';
    var prob  = r.DES_PROBLEMA
        ? '<div class="lib-card-row"><span class="lib-card-label">Problema</span>' +
          '<span class="lib-card-value">' + esc(r.DES_PROBLEMA).substring(0,80) + '</span></div>'
        : '';

    return '<div class="lib-card' + (sel ? ' sel' : '') + '" data-id="' + r.ID + '">' +
        '<div class="lib-card-head">' +
            '<input type="checkbox" ' + (sel ? 'checked' : '') +
                ' style="width:22px;height:22px;flex-shrink:0;pointer-events:none">' +
            '<span class="lib-card-ordem">Ordem #' + (r.NUM_ORDEM || '') + '</span>' +
            '<span class="prio prio-' + prio + '">' + prio + '</span>' +
        '</div>' +
        '<div class="lib-card-body">' +
            '<div class="lib-card-row"><span class="lib-card-label">Recurso</span>' +
                '<span class="lib-card-value">' + esc(r.RECURSO || '') + '</span></div>' +
            '<div class="lib-card-row"><span class="lib-card-label">Data</span>' +
                '<span class="lib-card-value">' + (r.DT_SOLICITACAO || '') + '</span></div>' +
            '<div class="lib-card-row"><span class="lib-card-label">Tipo</span>' +
                '<span class="lib-card-value">' + (r.TP_OS || '') + (r.TP_PROBLEMA ? ' / ' + r.TP_PROBLEMA : '') + '</span></div>' +
            '<div class="lib-card-row"><span class="lib-card-label">Máq. Parada</span>' +
                '<span class="lib-card-value">' + (r.MAQ_PARADA || '—') + '</span></div>' +
            prob +
            '<div class="lib-card-row"><span class="lib-card-label">Status</span>' +
                '<span class="lib-card-value">' + atend + '</span></div>' +
        '</div>' +
        '<div class="lib-card-foot">Funcionário: ' + esc(r.FUNC_OK || '—') + '</div>' +
    '</div>';
}

/* ========== BOTÕES DE AÇÃO ========== */

function sincronizarBotoes() {
    var n = _sel.size;
    document.getElementById('btnAtender').disabled = n === 0;
    document.getElementById('btnFechar').disabled  = n === 0;
}

function acaoAtender() {
    if (_sel.size === 0) return;
    var ids = Array.from(_sel);
    if (!confirm('Registrar atendimento para ' + ids.length + ' ordem(ns)?')) return;
    fetch(_base + 'manutencao-api-lib-atender', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(function(r) { return r.json(); })
    .then(function(json) {
        if (!json.success) { alert('Erro: ' + (json.error || 'desconhecido')); return; }
        carregar();
    })
    .catch(function(err) { alert('Erro: ' + err.message); });
}

function acaoFechar() {
    if (_sel.size === 0) return;
    var ids = Array.from(_sel);
    if (!confirm('Fechar ' + ids.length + ' ordem(ns)? Esta ação não pode ser desfeita.')) return;
    fetch(_base + 'manutencao-api-lib-fechar', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(function(r) { return r.json(); })
    .then(function(json) {
        if (!json.success) { alert('Erro: ' + (json.error || 'desconhecido')); return; }
        carregar();
    })
    .catch(function(err) { alert('Erro: ' + err.message); });
}

function renderErro(msg) {
    document.getElementById('libContent').innerHTML =
        '<div class="lib-empty" style="color:#c00">Erro: ' + esc(msg) + '</div>';
    document.getElementById('cntLib').textContent = '—';
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
