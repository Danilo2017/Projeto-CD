'use strict';

let _base     = '/';
let _emprId   = 0;
let _maqId    = 0;
let _maquinas = [];
let _dropEl   = null;

document.addEventListener('DOMContentLoaded', function () {
    const d = document.getElementById('cc-app-data');
    _base   = d ? d.dataset.base   : '/';
    _emprId = d ? parseInt(d.dataset.empr || '0', 10) : 0;

    // Move o dropdown para body — contorna qualquer overflow:hidden de ancestrais
    _dropEl = document.getElementById('acDropdown');
    _dropEl.className = ''; // remove classe que tem right:0 conflitante
    Object.assign(_dropEl.style, {
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
    document.body.appendChild(_dropEl);

    carregarMaquinas();

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#buscarMaquina') && e.target !== _dropEl && !_dropEl.contains(e.target)) {
            fecharDropdown();
        }
    });
    window.addEventListener('scroll',  posicionar, true);
    window.addEventListener('resize',  posicionar);
});

function carregarMaquinas() {
    fetch(_base + 'manutencao-api-maquinas?empr_id=' + _emprId)
        .then(r => r.json())
        .then(json => { _maquinas = json.data || []; });
}

function abrirDropdown() {
    renderDropdown(_maquinas);
}

function filtrarMaquinas() {
    const termo = document.getElementById('buscarMaquina').value.toLowerCase().trim();
    const lista = termo
        ? _maquinas.filter(m => m.NOME.toLowerCase().includes(termo) || String(m.ID).includes(termo))
        : _maquinas;
    renderDropdown(lista);
}

function renderDropdown(lista) {
    const exibir = lista.slice(0, 60);

    if (!exibir.length) {
        _dropEl.innerHTML = '<div style="padding:10px 12px;color:#aaa">Nenhuma máquina encontrada</div>';
    } else {
        _dropEl.innerHTML = exibir.map(m =>
            `<div onclick="selecionarMaquina(${m.ID},'${esc(m.NOME)}')"
                  style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f0f0f0"
                  onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background=''">
                ${esc(m.NOME)}
            </div>`
        ).join('');
    }

    // Mostrar ANTES de posicionar (display:none impede cálculo correto de posição)
    _dropEl.style.display = 'block';
    posicionar();
}

function posicionar() {
    const input = document.getElementById('buscarMaquina');
    if (!input) return;
    const r = input.getBoundingClientRect();
    _dropEl.style.top   = (r.bottom + 2) + 'px';
    _dropEl.style.left  = r.left + 'px';
    _dropEl.style.width = r.width + 'px';
}

function fecharDropdown() {
    _dropEl.style.display = 'none';
}

function selecionarMaquina(id, nome) {
    _maqId = id;
    document.getElementById('buscarMaquina').value  = nome;
    const label = document.getElementById('maqSelecionada');
    label.textContent   = 'Máquina selecionada: ' + nome;
    label.style.display = 'block';
    fecharDropdown();
    carregarItens();
}

function carregarItens() {
    const card = document.getElementById('cardItens');
    if (!_maqId) { card.style.display = 'none'; return; }
    card.style.display = 'block';

    fetch(_base + 'manutencao-api-chklist-todos?maquina_id=' + _maqId)
        .then(r => r.json())
        .then(json => renderItens(json.data || []));
}

function renderItens(itens) {
    const tb = document.getElementById('tbItens');
    if (!itens.length) {
        tb.innerHTML = '<tr><td colspan="3" class="cc-empty">Nenhum item cadastrado para esta máquina.</td></tr>';
        return;
    }
    tb.innerHTML = itens.map((it, i) =>
        `<tr>
            <td>${i + 1}</td>
            <td>${esc(it.DESCRICAO)}</td>
            <td style="text-align:center">
                <button class="btn btn-sm btn-danger py-0 px-1" onclick="excluirItem(${it.ID})" title="Excluir">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`
    ).join('');
}

function adicionarItem() {
    const input = document.getElementById('novoItem');
    const desc  = input.value.trim();
    if (!desc)   { alert('Informe a descrição do item.'); return; }
    if (!_maqId) { alert('Selecione a máquina.'); return; }

    fetch(_base + 'manutencao-api-chklist-salvar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ maquina_id: _maqId, descricao: desc })
    })
    .then(r => r.json())
    .then(json => {
        if (!json.success) { alert('Erro: ' + (json.error || 'desconhecido')); return; }
        input.value = '';
        input.focus();
        carregarItens();
    })
    .catch(err => alert('Erro: ' + err.message));
}

function excluirItem(id) {
    if (!confirm('Excluir este item do checklist?')) return;
    fetch(_base + 'manutencao-api-chklist-excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(json => {
        if (!json.success) { alert('Erro: ' + (json.error || 'desconhecido')); return; }
        carregarItens();
    })
    .catch(err => alert('Erro: ' + err.message));
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
