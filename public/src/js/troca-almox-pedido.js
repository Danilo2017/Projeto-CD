'use strict';

const hEmprId        = document.getElementById('hEmprId');
const txtNumeros     = document.getElementById('txtNumeros');
const btnBuscar      = document.getElementById('btnBuscar');
const secaoItens     = document.getElementById('secaoItens');
const tabelaItens    = document.getElementById('tabelaItens');
const totalItens     = document.getElementById('totalItens');
const inpCodAlmox    = document.getElementById('inpCodAlmox');
const inpDescAlmox   = document.getElementById('inpDescAlmox');
const inpAlmoxDestId = document.getElementById('inpAlmoxDestId');
const btnTrocar      = document.getElementById('btnTrocar');
const secaoResultado = document.getElementById('secaoResultado');
const corpoResultado = document.getElementById('corpoResultado');

let almoxarifados = [];
let numPedidosAtual = [];

/* ── Utilitários ─────────────────────────────────────── */
async function fetchJson(url, opts) {
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

function fmt(v) { return v === null || v === undefined ? '' : v; }

function limparAlmox() {
    inpCodAlmox.value    = '';
    inpDescAlmox.value   = '';
    inpAlmoxDestId.value = '';
    inpCodAlmox.classList.remove('is-valid', 'is-invalid');
    inpDescAlmox.classList.remove('is-valid', 'is-invalid');
}

function parseNumeros(txt) {
    return [...new Set(
        txt.split(/[\s,;]+/)
           .map(s => parseInt(s, 10))
           .filter(n => n > 0)
    )];
}

/* ── Carregar almoxarifados da empresa da sessão ─────── */
async function carregarAlmoxarifados() {
    const emprId = hEmprId.value;
    if (!emprId) return;
    try {
        const data = await fetchJson(`processo-api-almoxarifados?empr_id=${emprId}`);
        almoxarifados = data ?? [];
    } catch (e) {
        console.error('Erro ao carregar almoxarifados:', e.message);
    }
}

carregarAlmoxarifados();

/* ── Validar cód. almoxarifado ao digitar ───────────── */
inpCodAlmox.addEventListener('input', () => {
    const cod   = inpCodAlmox.value.trim().toUpperCase();
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

/* ── Buscar itens ────────────────────────────────────── */
btnBuscar.addEventListener('click', async () => {
    const numPedidos = parseNumeros(txtNumeros.value);
    if (!numPedidos.length) { alert('Informe ao menos um número de pedido.'); return; }

    setLoading(btnBuscar, true, '<i class="bi bi-search me-1"></i> Buscar Itens');
    tabelaItens.innerHTML = '';
    secaoItens.classList.add('d-none');
    secaoResultado.classList.add('d-none');
    limparAlmox();

    try {
        const data = await fetchJson('processo-api-troca-almox-pedido-buscar-itens', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                empr_id:     parseInt(hEmprId.value, 10),
                num_pedidos: numPedidos,
            }),
        });

        numPedidosAtual        = numPedidos;
        totalItens.textContent = data.total ?? 0;

        if (!data.itens || data.itens.length === 0) {
            tabelaItens.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum item encontrado para os pedidos informados.</td></tr>';
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
    <td>${fmt(r.DESCRICAO_ALMOX)}</td>
</tr>`).join('');

        secaoItens.classList.remove('d-none');

    } catch (e) {
        alert('Erro ao buscar itens: ' + e.message);
    } finally {
        setLoading(btnBuscar, false, '<i class="bi bi-search me-1"></i> Buscar Itens');
    }
});

/* ── Executar troca ──────────────────────────────────── */
btnTrocar.addEventListener('click', async () => {
    const almoxDestId = inpAlmoxDestId.value;

    if (!numPedidosAtual.length) { alert('Busque os pedidos primeiro.'); return; }
    if (!almoxDestId)            { alert('Informe um almoxarifado destino válido.'); return; }

    const descAlmox = inpDescAlmox.value;
    const lista     = numPedidosAtual.join(', ');
    if (!confirm(`Confirma a troca do almoxarifado dos pedidos [${lista}] para "${descAlmox}"?`)) return;

    setLoading(btnTrocar, true, '<i class="bi bi-arrow-left-right me-1"></i> Trocar Almoxarifado');
    secaoResultado.classList.add('d-none');

    try {
        const data = await fetchJson('processo-api-troca-almox-pedido', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                empr_id:       parseInt(hEmprId.value, 10),
                num_pedidos:   numPedidosAtual,
                almox_dest_id: parseInt(almoxDestId, 10),
            }),
        });

        corpoResultado.innerHTML = `
<div class="alert alert-success mb-0">
    <i class="bi bi-check-circle me-2"></i>${data.mensagem}
</div>`;
        secaoResultado.classList.remove('d-none');

        btnBuscar.click();

    } catch (e) {
        corpoResultado.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>${e.message}</div>`;
        secaoResultado.classList.remove('d-none');
    } finally {
        setLoading(btnTrocar, false, '<i class="bi bi-arrow-left-right me-1"></i> Trocar Almoxarifado');
    }
});
