'use strict';

const selEmpresa      = document.getElementById('selEmpresa');
const txtNumeros      = document.getElementById('txtNumeros');
const btnBuscar       = document.getElementById('btnBuscar');
const secaoOrdens     = document.getElementById('secaoOrdens');
const totalOrdens     = document.getElementById('totalOrdens');
const tabelaOrdens    = document.getElementById('tabelaOrdens');
const chkTodos        = document.getElementById('chkTodos');
const inpCodAlmox     = document.getElementById('inpCodAlmox');
const inpDescAlmox    = document.getElementById('inpDescAlmox');
const inpAlmoxDestId  = document.getElementById('inpAlmoxDestId');
const btnTrocar       = document.getElementById('btnTrocar');
const secaoResultados  = document.getElementById('secaoResultados');
const resumoResultado  = document.getElementById('resumoResultado');
const tabelaResultados = document.getElementById('tabelaResultados');

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

function setLoading(btn, loading) {
    btn.disabled = loading;
    btn.querySelector('i').className = loading
        ? 'spinner-border spinner-border-sm me-1'
        : (btn === btnBuscar ? 'bi bi-search me-1' : 'bi bi-arrow-left-right me-1');
}

function limparAlmox() {
    inpCodAlmox.value    = '';
    inpDescAlmox.value   = '';
    inpAlmoxDestId.value = '';
    inpCodAlmox.classList.remove('is-valid', 'is-invalid');
    inpDescAlmox.classList.remove('is-valid', 'is-invalid');
}

/* ── Carregar almoxarifados ao selecionar empresa ── */
selEmpresa.addEventListener('change', async () => {
    limparAlmox();
    almoxarifados = [];
    tabelaOrdens.innerHTML = '';
    secaoOrdens.classList.add('d-none');
    secaoResultados.classList.add('d-none');

    const emprId = selEmpresa.value;
    if (!emprId) return;

    try {
        const data = await fetchJson(`processo-api-almoxarifados?empr_id=${emprId}`);
        almoxarifados = data ?? [];
    } catch (e) {
        console.error('Erro ao carregar almoxarifados:', e.message);
    }
});

/* ── Validar código digitado ── */
function validarCodigo() {
    const cod = inpCodAlmox.value.trim().toUpperCase();
    if (!cod) {
        inpDescAlmox.value   = '';
        inpAlmoxDestId.value = '';
        inpCodAlmox.classList.remove('is-valid', 'is-invalid');
        inpDescAlmox.classList.remove('is-valid', 'is-invalid');
        return;
    }

    const found = almoxarifados.find(a => String(a.COD_ALMOX).toUpperCase() === cod);
    if (found) {
        inpDescAlmox.value   = found.DESCRICAO;
        inpAlmoxDestId.value = found.ID;
        inpCodAlmox.classList.add('is-valid');
        inpCodAlmox.classList.remove('is-invalid');
        inpDescAlmox.classList.add('is-valid');
        inpDescAlmox.classList.remove('is-invalid');
    } else {
        inpDescAlmox.value   = 'Código não encontrado';
        inpAlmoxDestId.value = '';
        inpCodAlmox.classList.add('is-invalid');
        inpCodAlmox.classList.remove('is-valid');
        inpDescAlmox.classList.add('is-invalid');
        inpDescAlmox.classList.remove('is-valid');
    }
}

inpCodAlmox.addEventListener('blur',  validarCodigo);
inpCodAlmox.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); validarCodigo(); } });

/* ── Buscar ordens ── */
btnBuscar.addEventListener('click', async () => {
    const emprId = selEmpresa.value;
    if (!emprId) { alert('Selecione uma empresa.'); return; }

    setLoading(btnBuscar, true);
    secaoOrdens.classList.add('d-none');
    secaoResultados.classList.add('d-none');
    tabelaOrdens.innerHTML = '';

    try {
        const data = await fetchJson('processo-api-troca-almox-ordens', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ empr_id: emprId, numeros: txtNumeros.value }),
        });

        const ordens = data.ordens ?? [];
        totalOrdens.textContent = ordens.length;

        if (!ordens.length) {
            tabelaOrdens.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhuma ordem encontrada.</td></tr>';
            secaoOrdens.classList.remove('d-none');
            return;
        }

        ordens.forEach(o => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="checkbox" class="form-check-input chk-ordem" value="${o.ID}" checked></td>
                <td>${o.NUM_ORDEM}</td>
                <td>${o.SITUACAO ?? ''}</td>
                <td>${o.TIPO_ORDEM ?? ''}</td>
                <td>${o.COD_ALMOX ?? ''}</td>
                <td>${o.DESCRICAO_ALMOX ?? ''}</td>`;
            tabelaOrdens.appendChild(tr);
        });

        secaoOrdens.classList.remove('d-none');
    } catch (e) {
        alert('Erro ao buscar ordens: ' + e.message);
    } finally {
        setLoading(btnBuscar, false);
    }
});

/* ── Selecionar / desselecionar todos ── */
chkTodos.addEventListener('change', () => {
    document.querySelectorAll('.chk-ordem').forEach(c => { c.checked = chkTodos.checked; });
});

/* ── Executar troca ── */
btnTrocar.addEventListener('click', async () => {
    const emprId   = selEmpresa.value;
    const almoxId  = inpAlmoxDestId.value;
    const destText = `${inpCodAlmox.value.trim().toUpperCase()} — ${inpDescAlmox.value}`;

    if (!almoxId) { alert('Informe um código de almoxarifado destino válido.'); return; }

    const selecionadas = [...document.querySelectorAll('.chk-ordem:checked')].map(c => c.value);
    if (!selecionadas.length) { alert('Selecione ao menos uma ordem.'); return; }

    if (!confirm(`Trocar almoxarifado de ${selecionadas.length} ordem(ns) para:\n${destText}\n\nDeseja continuar?`)) return;

    setLoading(btnTrocar, true);
    secaoResultados.classList.add('d-none');
    tabelaResultados.innerHTML = '';

    try {
        const data = await fetchJson('processo-api-troca-almox', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ empr_id: emprId, ordem_ids: selecionadas, almox_dest_id: almoxId }),
        });

        resumoResultado.textContent = `${data.sucessos} sucesso(s) / ${data.erros} erro(s)`;

        (data.resultados ?? []).forEach(r => {
            const tr = document.createElement('tr');
            const badge = r.sucesso
                ? '<span class="badge bg-success">OK</span>'
                : '<span class="badge bg-danger">Erro</span>';
            tr.innerHTML = `
                <td>${r.id}</td>
                <td>${badge}</td>
                <td>${r.sucesso ? destText : '—'}</td>
                <td class="text-danger small">${r.erro ?? ''}</td>`;
            tabelaResultados.appendChild(tr);
        });

        secaoResultados.classList.remove('d-none');

        if (data.sucessos > 0) {
            const idsOk = new Set(
                (data.resultados ?? []).filter(r => r.sucesso).map(r => String(r.id))
            );
            document.querySelectorAll('.chk-ordem').forEach(chk => {
                if (!idsOk.has(chk.value)) return;
                const tr = chk.closest('tr');
                if (!tr) return;
                const cells = tr.querySelectorAll('td');
                if (cells[4]) cells[4].textContent = inpCodAlmox.value.trim().toUpperCase();
                if (cells[5]) cells[5].textContent = inpDescAlmox.value;
            });
        }
    } catch (e) {
        alert('Erro ao executar troca: ' + e.message);
    } finally {
        setLoading(btnTrocar, false);
    }
});
