'use strict';

const selEmpresa      = document.getElementById('selEmpresa');
const inpCarga        = document.getElementById('inpCarga');
const btnBuscar       = document.getElementById('btnBuscar');
const secaoItens      = document.getElementById('secaoItens');
const totalItens      = document.getElementById('totalItens');
const tabelaItens     = document.getElementById('tabelaItens');
const radioTodaCarga  = document.getElementById('radioTodaCarga');
const radioPorPedido  = document.getElementById('radioPorPedido');
const divPedido       = document.getElementById('divPedido');
const selPedido       = document.getElementById('selPedido');
const inpCodAlmox     = document.getElementById('inpCodAlmox');
const inpDescAlmox    = document.getElementById('inpDescAlmox');
const inpAlmoxDestId  = document.getElementById('inpAlmoxDestId');
const btnTrocar       = document.getElementById('btnTrocar');
const secaoResultado  = document.getElementById('secaoResultado');
const resumoResultado = document.getElementById('resumoResultado');
const corpoResultado  = document.getElementById('corpoResultado');

let almoxarifados = [];

async function fetchJson(url, opts = {}) {
    const res  = await fetch(url, opts);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch (_) {
        throw new Error(text || `HTTP ${res.status}`);
    }
    if (!res.ok) throw new Error(data.error ?? `HTTP ${res.status}`);
    return data;
}

function setLoading(btn, loading, labelDefault) {
    btn.disabled = loading;
    btn.innerHTML = loading
        ? '<span class="spinner-border spinner-border-sm me-1"></span> Aguarde...'
        : labelDefault;
}

function limparAlmox() {
    inpCodAlmox.value    = '';
    inpDescAlmox.value   = '';
    inpAlmoxDestId.value = '';
    inpCodAlmox.classList.remove('is-valid', 'is-invalid');
}

function fmt(v) { return v === null || v === undefined ? '' : v; }

/* ── Carregar almoxarifados ao selecionar empresa ── */
selEmpresa.addEventListener('change', async () => {
    limparAlmox();
    almoxarifados = [];
    tabelaItens.innerHTML = '';
    secaoItens.classList.add('d-none');
    secaoResultado.classList.add('d-none');

    const emprId = selEmpresa.value;
    if (!emprId) return;

    try {
        const data = await fetchJson(`processo-api-almoxarifados?empr_id=${emprId}`);
        almoxarifados = data ?? [];
    } catch (e) {
        console.error('Erro ao carregar almoxarifados:', e.message);
    }
});

/* ── Validar cód. almoxarifado ao digitar ── */
inpCodAlmox.addEventListener('input', () => {
    const cod  = inpCodAlmox.value.trim().toUpperCase();
    const achou = almoxarifados.find(a => String(a.COD_ALMOX).toUpperCase() === cod);
    if (achou) {
        inpDescAlmox.value   = achou.DESCRICAO;
        inpAlmoxDestId.value = achou.ID;
        inpCodAlmox.classList.replace('is-invalid', 'is-valid');
        inpDescAlmox.classList.replace('is-invalid', 'is-valid');
    } else {
        inpDescAlmox.value   = '';
        inpAlmoxDestId.value = '';
        inpCodAlmox.classList.remove('is-valid');
        inpDescAlmox.classList.remove('is-valid');
        if (cod) inpCodAlmox.classList.add('is-invalid');
    }
});

/* ── Escopo: mostrar/ocultar selector de pedido ── */
[radioTodaCarga, radioPorPedido].forEach(r => r.addEventListener('change', () => {
    divPedido.classList.toggle('d-none', radioTodaCarga.checked);
}));

/* ── Buscar itens da carga ── */
btnBuscar.addEventListener('click', async () => {
    const emprId = selEmpresa.value;
    const carga  = inpCarga.value.trim();

    if (!emprId)  { alert('Selecione uma empresa.');   return; }
    if (!carga)   { alert('Informe o número da carga.'); return; }

    setLoading(btnBuscar, true, '<i class="bi bi-search me-1"></i> Buscar');
    tabelaItens.innerHTML = '';
    secaoItens.classList.add('d-none');
    secaoResultado.classList.add('d-none');

    try {
        const data = await fetchJson('processo-api-troca-almox-carga-buscar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ empr_id: parseInt(emprId), carga: parseInt(carga) }),
        });

        totalItens.textContent = data.total ?? 0;

        if (!data.itens || data.itens.length === 0) {
            tabelaItens.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>';
            secaoItens.classList.remove('d-none');
            return;
        }

        tabelaItens.innerHTML = data.itens.map(r => `
<tr>
    <td><span class="badge bg-secondary">${fmt(r.NUM_PEDIDO)}</span></td>
    <td>${fmt(r.COD_ITEM)}</td>
    <td>${fmt(r.DESC_TECNICA)}</td>
    <td class="text-muted small">${fmt(r.MASCARA)}</td>
    <td><strong>${fmt(r.COD_ALMOX)}</strong></td>
    <td>${fmt(r.DESCRICAO)}</td>
</tr>`).join('');

        // Preencher selector de pedido
        selPedido.innerHTML = '<option value="">Selecione o pedido...</option>'
            + (data.pedidos ?? []).map(p => `<option value="${p}">${p}</option>`).join('');

        secaoItens.classList.remove('d-none');

    } catch (e) {
        alert('Erro ao buscar itens: ' + e.message);
    } finally {
        setLoading(btnBuscar, false, '<i class="bi bi-search me-1"></i> Buscar');
    }
});

/* ── Executar troca ── */
btnTrocar.addEventListener('click', async () => {
    const emprId     = selEmpresa.value;
    const carga      = inpCarga.value.trim();
    const almoxDestId = inpAlmoxDestId.value;

    if (!emprId)      { alert('Selecione uma empresa.');           return; }
    if (!carga)       { alert('Informe o número da carga.');        return; }
    if (!almoxDestId) { alert('Informe um almoxarifado destino válido.'); return; }

    let numPedido = null;
    if (radioPorPedido.checked) {
        numPedido = selPedido.value;
        if (!numPedido) { alert('Selecione o pedido para troca.'); return; }
    }

    const escopo = radioPorPedido.checked
        ? `pedido ${numPedido} da carga ${carga}`
        : `carga ${carga} completa`;

    if (!confirm(`Confirma a troca de almoxarifado para ${escopo}?`)) return;

    setLoading(btnTrocar, true, '<i class="bi bi-arrow-left-right me-1"></i> Trocar');
    secaoResultado.classList.add('d-none');

    try {
        const body = {
            empr_id:      parseInt(emprId),
            carga:        parseInt(carga),
            almox_dest_id: parseInt(almoxDestId),
        };
        if (numPedido) body.num_pedido = parseInt(numPedido);

        const data = await fetchJson('processo-api-troca-almox-carga', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        });

        resumoResultado.textContent = data.escopo ?? '';
        corpoResultado.innerHTML = `
<div class="alert alert-success mb-0">
    <i class="bi bi-check-circle me-2"></i>
    Troca realizada com sucesso para <strong>${data.escopo}</strong>.
    ${data.afetados !== undefined ? `<br><small>${data.afetados} linha(s) atualizadas.</small>` : ''}
</div>`;

        secaoResultado.classList.remove('d-none');

        // Recarregar itens para refletir novo almox
        btnBuscar.click();

    } catch (e) {
        corpoResultado.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>${e.message}</div>`;
        secaoResultado.classList.remove('d-none');
    } finally {
        setLoading(btnTrocar, false, '<i class="bi bi-arrow-left-right me-1"></i> Trocar');
    }
});
