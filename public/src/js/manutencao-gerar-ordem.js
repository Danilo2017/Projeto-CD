'use strict';

let _base         = '/';
let _emprId       = 0;
let _maqId        = 0;
let _solcId       = 0;
let _maquinas     = [];
let _solicitantes = [];
let _chkItens     = [];
let _dropMaq      = null;
let _dropSol      = null;

document.addEventListener('DOMContentLoaded', function () {
    const d = document.getElementById('go-app-data');
    _base   = d ? d.dataset.base : '/';
    _emprId = d ? parseInt(d.dataset.empr || '0', 10) : 0;

    document.getElementById('fDtSol').value = new Date().toLocaleDateString('pt-BR');

    _dropMaq = criarDrop('acMaqDrop');
    _dropSol = criarDrop('acSolDrop');

    carregarMaquinas();
    carregarSolicitantes();

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#fRecursoText')     && !_dropMaq.contains(e.target)) esconderDrop(_dropMaq);
        if (!e.target.closest('#fSolicitanteText') && !_dropSol.contains(e.target)) esconderDrop(_dropSol);
    });
    window.addEventListener('scroll', function () {
        reposicionar(_dropMaq, 'fRecursoText');
        reposicionar(_dropSol, 'fSolicitanteText');
    }, true);
    window.addEventListener('resize', function () {
        reposicionar(_dropMaq, 'fRecursoText');
        reposicionar(_dropSol, 'fSolicitanteText');
    });
});

/* ---- setup do dropdown ---- */
function criarDrop(elId) {
    const el = document.getElementById(elId);
    el.className = ''; // remove .ac-drop (tem right:0 que conflita)
    Object.assign(el.style, {
        display:      'none',
        position:     'fixed',
        zIndex:       '99999',
        maxHeight:    '280px',
        overflowY:    'auto',
        background:   '#fff',
        border:       '1px solid #ccc',
        borderRadius: '8px',
        boxShadow:    '0 6px 18px rgba(0,0,0,.2)',
        fontSize:     '13px',
        top: '0', left: '0', width: '200px'
    });
    document.body.appendChild(el);
    return el;
}

function reposicionar(drop, inputId) {
    const input = document.getElementById(inputId);
    if (!input || !drop || drop.style.display === 'none') return;
    const r = input.getBoundingClientRect();
    drop.style.top   = (r.bottom + 2) + 'px';
    drop.style.left  = r.left + 'px';
    drop.style.width = r.width + 'px';
}

function mostrarDrop(drop, inputId, lista, fnSelecionar, keyId, keyLabel) {
    const exibir = lista.slice(0, 60);
    drop.innerHTML = exibir.length
        ? exibir.map(item =>
            `<div onclick="${fnSelecionar.name}(${item[keyId]},'${esc(String(item[keyLabel]))}')"
                  style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f0f0f0"
                  onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background=''">
                ${esc(String(item[keyLabel]))}
            </div>`).join('')
        : '<div style="padding:10px 14px;color:#aaa">Nenhum resultado</div>';

    // Mostrar ANTES de reposicionar (display:none impede getBoundingClientRect correto)
    drop.style.display = 'block';
    reposicionar(drop, inputId);
}

function esconderDrop(drop) {
    if (drop) drop.style.display = 'none';
}

/* ---- Máquina ---- */
function carregarMaquinas() {
    fetch(_base + 'manutencao-api-maquinas?empr_id=' + _emprId)
        .then(r => r.json())
        .then(json => { _maquinas = json.data || []; });
}

function abrirMaquinas() {
    mostrarDrop(_dropMaq, 'fRecursoText', _maquinas, selecionarMaquina, 'ID', 'NOME');
}

function filtrarMaquinas() {
    const t = document.getElementById('fRecursoText').value.toLowerCase().trim();
    const l = t ? _maquinas.filter(m => m.NOME.toLowerCase().includes(t) || String(m.ID).includes(t)) : _maquinas;
    mostrarDrop(_dropMaq, 'fRecursoText', l, selecionarMaquina, 'ID', 'NOME');
}

function selecionarMaquina(id, nome) {
    _maqId = id; _chkItens = [];
    document.getElementById('fRecursoText').value = nome;
    esconderDrop(_dropMaq);
    const s = document.getElementById('acMaqSel');
    s.textContent = 'Selecionado: ' + nome;
    s.style.display = 'block';
}

/* ---- Solicitante ---- */
function carregarSolicitantes() {
    fetch(_base + 'manutencao-api-solicitantes?empr_id=' + _emprId)
        .then(r => r.json())
        .then(json => { _solicitantes = json.data || []; });
}

function abrirSolicitantes() {
    mostrarDrop(_dropSol, 'fSolicitanteText', _solicitantes, selecionarSolicitante, 'ID', 'LABEL');
}

function filtrarSolicitantes() {
    const t = document.getElementById('fSolicitanteText').value.toLowerCase().trim();
    const l = t ? _solicitantes.filter(f => f.LABEL.toLowerCase().includes(t) || String(f.ID).includes(t)) : _solicitantes;
    mostrarDrop(_dropSol, 'fSolicitanteText', l, selecionarSolicitante, 'ID', 'LABEL');
}

function selecionarSolicitante(id, nome) {
    _solcId = id;
    document.getElementById('fSolicitanteText').value = nome;
    esconderDrop(_dropSol);
    const s = document.getElementById('acSolSel');
    s.textContent = 'Selecionado: ' + nome;
    s.style.display = 'block';
}

/* ---- Checklist & Salvar ---- */
async function tentarSalvar() {
    if (!_maqId) { alert('Selecione a máquina.'); return; }
    if (!document.getElementById('fDtSol').value.trim()) { alert('Informe a data de solicitação.'); return; }

    const r    = await fetch(_base + 'manutencao-api-chklist-maquina?maquina_id=' + _maqId);
    const json = await r.json();
    _chkItens  = json.data || [];

    _chkItens.length ? abrirChecklist() : salvar({});
}

function abrirChecklist() {
    document.getElementById('chkLista').innerHTML = _chkItens.map(item =>
        `<div class="chk-item">
            <input type="checkbox" id="chk_${item.ID}" value="${item.ID}">
            <label for="chk_${item.ID}">${esc(item.DESCRICAO)}</label>
        </div>`
    ).join('');
    document.getElementById('chkOverlay').classList.add('show');
}

function fecharChecklist() {
    document.getElementById('chkOverlay').classList.remove('show');
}

function confirmarEsalvar() {
    // Todos os itens do checklist devem estar marcados
    const naoMarcados = _chkItens.filter(item => !document.getElementById('chk_' + item.ID)?.checked);
    if (naoMarcados.length > 0) {
        alert('Todos os ' + _chkItens.length + ' itens do checklist devem ser verificados antes de gerar a ordem.\n\nItens pendentes:\n' +
              naoMarcados.map(i => '• ' + i.DESCRICAO).join('\n'));
        return;
    }

    const checklist = {};
    _chkItens.forEach(item => { checklist[item.ID] = 1; });
    fecharChecklist();
    salvar(checklist);
}

function salvar(checklist) {
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;

    fetch(_base + 'manutencao-api-gerar-ordem', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            maquina_id:    _maqId,
            tp_os:         document.getElementById('fTipo').value,
            situacao:      document.getElementById('fSituacao').value,
            tp_problema:   document.getElementById('fTpProblema').value  || null,
            tp_maq_parada: document.getElementById('fTpParada').value    || null,
            dt_solicitacao: document.getElementById('fDtSol').value,
            dt_prevista:   document.getElementById('fDtPrev').value      || null,
            func_id:       _solcId || null,
            ind_urgente:   document.getElementById('fUrgente').checked   ? 1 : 0,
            des_problema:  document.getElementById('fProblema').value,
            checklist
        })
    })
    .then(r => r.json())
    .then(json => {
        if (!json.success) { alert('Erro: ' + (json.error || 'desconhecido')); return; }
        alert('Ordem gerada com sucesso!');
        limparForm();
    })
    .catch(err => alert('Erro: ' + err.message))
    .finally(() => { btn.disabled = false; });
}

function limparForm() {
    _maqId = 0; _solcId = 0; _chkItens = [];
    ['fRecursoText','fSolicitanteText','fDtPrev','fProblema'].forEach(id =>
        document.getElementById(id).value = '');
    document.getElementById('acMaqSel').style.display = 'none';
    document.getElementById('acSolSel').style.display = 'none';
    document.getElementById('fTipo').value       = 'C';
    document.getElementById('fSituacao').value   = 'L';
    document.getElementById('fTpProblema').value = '';
    document.getElementById('fTpParada').value   = '';
    document.getElementById('fDtSol').value      = new Date().toLocaleDateString('pt-BR');
    document.getElementById('fUrgente').checked  = false;
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
