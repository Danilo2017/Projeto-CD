'use strict';

const selEmpresa      = document.getElementById('selEmpresa');
const inpCodFor       = document.getElementById('inpCodFor');
const inpNumNf        = document.getElementById('inpNumNf');
const btnBuscar       = document.getElementById('btnBuscar');
const secaoNf         = document.getElementById('secaoNf');
const infoNumNf       = document.getElementById('infoNumNf');
const infoDtEnt       = document.getElementById('infoDtEnt');
const infoCodFor      = document.getElementById('infoCodFor');
const infoFornecedor  = document.getElementById('infoFornecedor');
const infoTipoCapa    = document.getElementById('infoTipoCapa');
const totalItens      = document.getElementById('totalItens');
const tabelaItens     = document.getElementById('tabelaItens');
const chkTodos        = document.getElementById('chkTodos');
const chkTrocarCapa   = document.getElementById('chkTrocarCapa');
const chkTrocarItens  = document.getElementById('chkTrocarItens');
const inpCodTipo      = document.getElementById('inpCodTipo');
const inpDescTipo     = document.getElementById('inpDescTipo');
const inpTipoDestId   = document.getElementById('inpTipoDestId');
const btnTrocar       = document.getElementById('btnTrocar');
const secaoResultados  = document.getElementById('secaoResultados');
const resumoResultado  = document.getElementById('resumoResultado');
const tabelaResultados = document.getElementById('tabelaResultados');

let tiposNf = [];
let nfeAtual = null;

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

function limparTipo() {
    inpCodTipo.value    = '';
    inpDescTipo.value   = '';
    inpTipoDestId.value = '';
    inpCodTipo.classList.remove('is-valid', 'is-invalid');
    inpDescTipo.classList.remove('is-valid', 'is-invalid');
}

/* ── Carregar tipos ao selecionar empresa ── */
selEmpresa.addEventListener('change', async () => {
    tiposNf  = [];
    nfeAtual = null;
    limparTipo();
    secaoNf.classList.add('d-none');
    secaoResultados.classList.add('d-none');
    tabelaItens.innerHTML = '';

    const emprId = selEmpresa.value;
    if (!emprId) return;

    try {
        const data = await fetchJson(`processo-api-tipos-nf-ent?empr_id=${emprId}`);
        tiposNf = data ?? [];
    } catch (e) {
        console.error('Erro ao carregar tipos de NF:', e.message);
    }
});

/* ── Validar código do tipo digitado ── */
function validarCodTipo() {
    const cod = inpCodTipo.value.trim().toUpperCase();
    if (!cod) {
        inpDescTipo.value   = '';
        inpTipoDestId.value = '';
        inpCodTipo.classList.remove('is-valid', 'is-invalid');
        inpDescTipo.classList.remove('is-valid', 'is-invalid');
        return;
    }

    const found = tiposNf.find(t => String(t.COD_TP_NF).toUpperCase() === cod);
    if (found) {
        inpDescTipo.value   = found.DESCRICAO;
        inpTipoDestId.value = found.ID;
        inpCodTipo.classList.add('is-valid');
        inpCodTipo.classList.remove('is-invalid');
        inpDescTipo.classList.add('is-valid');
        inpDescTipo.classList.remove('is-invalid');
    } else {
        inpDescTipo.value   = 'Código não encontrado';
        inpTipoDestId.value = '';
        inpCodTipo.classList.add('is-invalid');
        inpCodTipo.classList.remove('is-valid');
        inpDescTipo.classList.add('is-invalid');
        inpDescTipo.classList.remove('is-valid');
    }
}

inpCodTipo.addEventListener('blur',    validarCodTipo);
inpCodTipo.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); validarCodTipo(); } });

/* ── Buscar NF ── */
btnBuscar.addEventListener('click', async () => {
    const emprId = selEmpresa.value;
    const numNf  = inpNumNf.value.trim();

    const codFor = inpCodFor.value.trim();

    if (!emprId) { alert('Selecione uma empresa.'); return; }
    if (!codFor) { alert('Informe o código do fornecedor.'); return; }
    if (!numNf)  { alert('Informe o número da NF.'); return; }

    setLoading(btnBuscar, true);
    secaoNf.classList.add('d-none');
    secaoResultados.classList.add('d-none');
    tabelaItens.innerHTML = '';
    nfeAtual = null;

    try {
        const data = await fetchJson('processo-api-troca-tipo-nf-buscar', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ empr_id: emprId, num_nf: numNf, cod_for: codFor }),
        });

        nfeAtual = data.nf;

        /* Preenche info da capa */
        infoNumNf.textContent      = nfeAtual.NUM_NF ?? '';
        infoDtEnt.textContent      = nfeAtual.DT_ENT ? String(nfeAtual.DT_ENT).substring(0, 10) : '';
        infoCodFor.textContent     = nfeAtual.COD_FOR ?? '';
        infoFornecedor.textContent = nfeAtual.FORNECEDOR ?? nfeAtual.COD_FOR ?? '';
        infoTipoCapa.textContent   = `${nfeAtual.COD_TP_NF_CAPA} — ${nfeAtual.DESC_TP_NF_CAPA}`;

        /* Preenche tabela de itens */
        const itens = data.itens ?? [];
        totalItens.textContent = itens.length;

        if (!itens.length) {
            tabelaItens.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>';
        } else {
            itens.forEach(it => {
                const tr = document.createElement('tr');
                tr.dataset.id = it.ID;
                tr.innerHTML = `
                    <td><input type="checkbox" class="form-check-input chk-item" value="${it.ID}" checked></td>
                    <td>${it.NUM_ITE}</td>
                    <td>${it.DESCRICAO ?? ''}</td>
                    <td>${it.COD_TP_NF ?? ''}</td>
                    <td>${it.DESC_TP_NF ?? ''}</td>`;
                tabelaItens.appendChild(tr);
            });
        }

        secaoNf.classList.remove('d-none');
    } catch (e) {
        alert('Erro ao buscar NF: ' + e.message);
    } finally {
        setLoading(btnBuscar, false);
    }
});

/* ── Selecionar / desselecionar todos os itens ── */
chkTodos.addEventListener('change', () => {
    document.querySelectorAll('.chk-item').forEach(c => { c.checked = chkTodos.checked; });
});

/* ── Trocar tipo ── */
btnTrocar.addEventListener('click', async () => {
    if (!nfeAtual) return;

    const tipoId    = inpTipoDestId.value;
    const trocarCapa  = chkTrocarCapa.checked;
    const trocarItens = chkTrocarItens.checked;

    if (!tipoId) { alert('Informe um código de tipo de NF válido.'); return; }
    if (!trocarCapa && !trocarItens) { alert('Selecione ao menos uma opção: Capa ou Itens.'); return; }

    const itemIds = trocarItens
        ? [...document.querySelectorAll('.chk-item:checked')].map(c => c.value)
        : [];

    if (trocarItens && !itemIds.length) { alert('Selecione ao menos um item para trocar.'); return; }

    const novoTipo  = `${inpCodTipo.value.trim().toUpperCase()} — ${inpDescTipo.value}`;
    const alvos     = [trocarCapa ? 'Capa' : null, trocarItens ? `${itemIds.length} item(ns)` : null]
                        .filter(Boolean).join(' + ');

    if (!confirm(`Trocar tipo de NF para:\n${novoTipo}\n\nAlvo: ${alvos}\n\nDeseja continuar?`)) return;

    setLoading(btnTrocar, true);
    secaoResultados.classList.add('d-none');
    tabelaResultados.innerHTML = '';

    try {
        const data = await fetchJson('processo-api-troca-tipo-nf', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                nfe_id:       nfeAtual.ID,
                empr_id:      selEmpresa.value,
                tipo_dest_id: tipoId,
                trocar_capa:  trocarCapa,
                item_ids:     itemIds,
            }),
        });

        resumoResultado.textContent = `${data.sucessos} sucesso(s) / ${data.erros} erro(s)`;
        tabelaResultados.innerHTML  = '';

        /* Linha da capa */
        if (data.capa_resultado !== null && data.capa_resultado !== undefined) {
            const r  = data.capa_resultado;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="badge bg-secondary">Capa</span></td>
                <td>${r.sucesso ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Erro</span>'}</td>
                <td>${r.sucesso ? novoTipo : '—'}</td>
                <td class="text-danger small">${r.erro ?? ''}</td>`;
            tabelaResultados.appendChild(tr);

            if (r.sucesso) {
                infoTipoCapa.textContent = novoTipo;
            }
        }

        /* Linhas dos itens */
        const itensMap = {};
        document.querySelectorAll('#tabelaItens tr').forEach(tr => {
            const chk = tr.querySelector('.chk-item');
            if (chk) itensMap[chk.value] = tr;
        });

        (data.itens_resultado ?? []).forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>Item ID ${r.id}</td>
                <td>${r.sucesso ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Erro</span>'}</td>
                <td>${r.sucesso ? novoTipo : '—'}</td>
                <td class="text-danger small">${r.erro ?? ''}</td>`;
            tabelaResultados.appendChild(tr);

            if (r.sucesso && itensMap[String(r.id)]) {
                const cells = itensMap[String(r.id)].querySelectorAll('td');
                if (cells[3]) cells[3].textContent = inpCodTipo.value.trim().toUpperCase();
                if (cells[4]) cells[4].textContent = inpDescTipo.value;
            }
        });

        secaoResultados.classList.remove('d-none');
    } catch (e) {
        alert('Erro ao executar troca: ' + e.message);
    } finally {
        setLoading(btnTrocar, false);
    }
});
