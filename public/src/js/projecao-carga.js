/* Projeção de Carga — agendador */
(function () {
    'use strict';

    const SITUACOES = ['PENDENTE', 'EM CARREGAMENTO', 'CARREGADO', 'CANCELADO'];

    let emprIdSelecionado = null;
    let carregandoLista   = false;

    /* ── Helpers ─────────────────────────────────────── */
    function fmt(v) { return v ?? '-'; }
    function fmtValor(v) {
        const n = parseFloat(v);
        if (isNaN(n)) return '-';
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    function badgeSituacao(sit) {
        const cores = {
            'PENDENTE':         'bg-danger',
            'EM CARREGAMENTO':  'bg-warning text-dark',
            'CARREGADO':        'bg-success',
            'CANCELADO':        'bg-secondary',
        };
        const cls = cores[sit] || 'bg-secondary';
        return `<span class="badge ${cls}">${sit}</span>`;
    }
    async function fetchJson(url, body) {
        const opts = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
            : { method: 'GET' };
        const res = await fetch(url, opts);
        return res.json();
    }
    function toast(msg, tipo = 'danger') {
        const el = document.getElementById('toastProjecao');
        el.querySelector('.toast-body').textContent = msg;
        el.className = `toast align-items-center text-white border-0 bg-${tipo}`;
        bootstrap.Toast.getOrCreateInstance(el).show();
    }

    /* ── Carregar lista ───────────────────────────────── */
    async function carregarLista() {
        if (!emprIdSelecionado || carregandoLista) return;
        carregandoLista = true;
        document.getElementById('btnAtualizar').disabled = true;
        document.getElementById('tabelaBody').innerHTML =
            '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>';

        try {
            const data = await fetchJson('cd-api-projecao-listar', { empr_id: emprIdSelecionado });
            if (data.error) { toast(data.error); return; }
            renderTabela(data.data || []);
        } catch (e) {
            toast('Erro ao carregar dados.');
        } finally {
            carregandoLista = false;
            document.getElementById('btnAtualizar').disabled = false;
        }
    }

    function renderTabela(rows) {
        const tbody = document.getElementById('tabelaBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Nenhuma carga encontrada.</td></tr>';
            document.getElementById('totalCargas').textContent = '0';
            return;
        }
        document.getElementById('totalCargas').textContent = rows.length;
        tbody.innerHTML = rows.map(r => `
            <tr data-empr="${r.EMPR_ID}" data-carga="${r.NUM_CARGA}">
                <td class="text-center fw-bold">${fmt(r.NUM_CARGA)}</td>
                <td>${fmt(r.DT_GERACAO)}</td>
                <td class="text-truncate" style="max-width:180px" title="${r.DESCRICAO ?? ''}">${fmt(r.DESCRICAO)}</td>
                <td class="text-end">${fmt(r.CUBAGEM)}</td>
                <td class="text-end">${fmtValor(r.VALOR)}</td>
                <td class="text-center">${fmt(r.DT_CARREGAMENTO)}</td>
                <td class="text-center">${badgeSituacao(r.SITUACAO)}</td>
                <td>${fmt(r.MOTORISTA)}</td>
                <td>${fmt(r.FROTA)} ${r.PLACAS ? '/ ' + r.PLACAS : ''}</td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-sm btn-warning py-0 px-2 me-1" onclick="abrirModal(${r.EMPR_ID},${r.NUM_CARGA})" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="abrirLog(${r.EMPR_ID},${r.NUM_CARGA})" title="Histórico">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </td>
            </tr>`).join('');
    }

    /* ── Modal Editar ────────────────────────────────── */
    window.abrirModal = async function (emprId, numCarga) {
        const row = document.querySelector(`tr[data-empr="${emprId}"][data-carga="${numCarga}"]`);
        if (!row) return;

        document.getElementById('modalCargaTitulo').textContent = `Carga #${numCarga}`;
        document.getElementById('fldEmprId').value   = emprId;
        document.getElementById('fldNumCarga').value = numCarga;

        // Preencher com dados atuais da linha
        const get = (idx) => row.cells[idx]?.textContent.trim() || '';
        const dtCarreg = get(5) !== '-' ? get(5).split('/').reverse().join('-') : '';
        document.getElementById('fldDtCarregamento').value = dtCarreg;
        document.getElementById('fldSituacao').value       = get(6).replace(/\s+/g,'') === '' ? 'PENDENTE' : row.cells[6].querySelector('.badge')?.textContent.trim() || 'PENDENTE';
        document.getElementById('fldMotorista').value      = get(7) !== '-' ? get(7) : '';
        // Frota e Placas podem estar juntos ("XX / YY")
        const frotaPlaca = get(8).split('/');
        document.getElementById('fldFrota').value  = frotaPlaca[0]?.trim() !== '-' ? frotaPlaca[0]?.trim() : '';
        document.getElementById('fldPlacas').value = frotaPlaca[1]?.trim() || '';

        // Buscar dados completos via log/API (observações, num_docs, etc.)
        // Usamos os dados já carregados na tabela como referência principal
        // Para campos não visíveis na tabela, carregamos via GET quando o modal abre
        try {
            const logData = await fetchJson(`cd-api-projecao-log?empr_id=${emprId}&num_carga=${numCarga}`);
            // Usar o log para detectar valores atuais não visíveis na tabela
            // (os campos completos virão da próxima requisição de lista)
        } catch (e) { /* silencioso */ }

        // Recarregar dados completos para o modal
        try {
            const res = await fetchJson('cd-api-projecao-listar', { empr_id: emprId });
            const item = (res.data || []).find(r => r.NUM_CARGA == numCarga);
            if (item) {
                const dtVal = item.DT_CARREGAMENTO
                    ? item.DT_CARREGAMENTO.split('/').reverse().join('-') : '';
                document.getElementById('fldDtCarregamento').value  = dtVal;
                document.getElementById('fldSituacao').value        = item.SITUACAO || 'PENDENTE';
                document.getElementById('fldSituacaoCarga').value   = item.SITUACAO_CARGA || '';
                document.getElementById('fldFrota').value           = item.FROTA || '';
                document.getElementById('fldPlacas').value          = item.PLACAS || '';
                document.getElementById('fldTipoVeiculo').value     = item.TIPO_VEICULO || '';
                document.getElementById('fldMotorista').value       = item.MOTORISTA || '';
                document.getElementById('fldContato').value         = item.CONTATO || '';
                document.getElementById('fldNumDocs').value         = item.NUM_DOCS || '';
                document.getElementById('fldObservacoes').value     = item.OBSERVACOES || '';
            }
        } catch (e) { /* silencioso */ }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCarga')).show();
    };

    /* ── Salvar ──────────────────────────────────────── */
    window.salvarCarga = async function () {
        const btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

        const payload = {
            empr_id:        document.getElementById('fldEmprId').value,
            num_carga:      document.getElementById('fldNumCarga').value,
            dt_carregamento:document.getElementById('fldDtCarregamento').value || null,
            situacao:       document.getElementById('fldSituacao').value,
            situacao_carga: document.getElementById('fldSituacaoCarga').value,
            frota:          document.getElementById('fldFrota').value,
            placas:         document.getElementById('fldPlacas').value,
            tipo_veiculo:   document.getElementById('fldTipoVeiculo').value,
            motorista:      document.getElementById('fldMotorista').value,
            contato:        document.getElementById('fldContato').value,
            num_docs:       document.getElementById('fldNumDocs').value,
            observacoes:    document.getElementById('fldObservacoes').value,
        };

        try {
            const data = await fetchJson('cd-api-projecao-salvar', payload);
            if (data.error) { toast(data.error); return; }
            toast(`Salvo com sucesso. ${data.alteracoes} campo(s) alterado(s).`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalCarga')).hide();
            carregarLista();
        } catch (e) {
            toast('Erro ao salvar.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvar';
        }
    };

    /* ── Modal Log ───────────────────────────────────── */
    window.abrirLog = async function (emprId, numCarga) {
        document.getElementById('logCargaTitulo').textContent = `Histórico — Carga #${numCarga}`;
        const tbody = document.getElementById('logBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLog')).show();

        try {
            const data = await fetchJson(`cd-api-projecao-log?empr_id=${emprId}&num_carga=${numCarga}`);
            if (!data.data?.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sem alterações registradas.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(r => `
                <tr>
                    <td>${r.DT_ALTERACAO}</td>
                    <td>${r.USUARIO}</td>
                    <td class="text-muted small">${r.CAMPO}</td>
                    <td class="text-muted small">${r.VALOR_ANTES}</td>
                    <td class="small">${r.VALOR_DEPOIS}</td>
                </tr>`).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Erro ao carregar histórico.</td></tr>';
        }
    };

    /* ── Init ─────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        const selEmpresa = document.getElementById('selEmpresa');
        selEmpresa.addEventListener('change', function () {
            emprIdSelecionado = this.value ? parseInt(this.value) : null;
            carregarLista();
        });

        document.getElementById('btnAtualizar').addEventListener('click', carregarLista);

        // Preencher situacao select
        const selSit = document.getElementById('fldSituacao');
        SITUACOES.forEach(s => {
            const o = document.createElement('option');
            o.value = s; o.textContent = s;
            selSit.appendChild(o);
        });
    });
})();
