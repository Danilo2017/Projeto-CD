/* Projeção de Carga — agendador */
(function () {
    'use strict';

    const SITUACOES = ['PENDENTE', 'EM CARREGAMENTO', 'CARREGADO', 'CANCELADO'];

    let carregandoLista = false;

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
    function badgeWms(status) {
        if (!status) return '<span class="text-muted small">-</span>';
        const cores = {
            'Importada WMS': 'bg-secondary',
            'Em Separação':  'bg-warning text-dark',
            'Encerrada':     'bg-success',
            'Excluída':      'bg-danger',
        };
        const cls = cores[status] || 'bg-secondary';
        return `<span class="badge ${cls}">${status}</span>`;
    }
    function badgePosPLC(pos) {
        if (!pos || pos === 'PE') return '';
        const map = { 'FT': ['bg-primary', 'Faturado'], 'FP': ['bg-info text-dark', 'Fat. Parcial'] };
        const [cls, label] = map[pos] || ['bg-secondary', pos];
        return ` <span class="badge ${cls}" title="Situação no FOCCO: ${pos}">${label}</span>`;
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

    function dataFiltroAtual() {
        return document.getElementById('inputDataFiltro')?.value || new Date().toISOString().slice(0, 10);
    }

    /* ── Carregar lista ───────────────────────────────── */
    async function carregarLista() {
        if (carregandoLista) return;
        carregandoLista = true;
        document.getElementById('btnAtualizar').disabled = true;
        document.getElementById('tabelaBody').innerHTML =
            '<tr><td colspan="13" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>';

        try {
            const data = await fetchJson('carga-api-listar', { data_filtro: dataFiltroAtual() });
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
            tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-4">Nenhuma carga encontrada.</td></tr>';
            document.getElementById('totalCargas').textContent = '0';
            document.getElementById('totalPendente').textContent = '-';
            document.getElementById('totalFaturado').textContent = '-';
            return;
        }
        document.getElementById('totalCargas').textContent = rows.length;

        const sumPend = rows.reduce((s, r) => s + (parseFloat(r.VALOR_PENDENTE) || 0), 0);
        const sumFat  = rows.reduce((s, r) => s + (parseFloat(r.VALOR_FATURADO)  || 0), 0);
        const fmt2    = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('totalPendente').textContent = fmt2(sumPend);
        document.getElementById('totalFaturado').textContent = fmt2(sumFat);

        tbody.innerHTML = rows.map(r => {
            const isFaturada = r.POS_PLC === 'FT' || r.POS_PLC === 'FP';
            const rowClass   = isFaturada ? 'table-success' : '';
            return `
            <tr data-carga="${r.NUM_CARGA}" data-pos="${r.POS_PLC}" class="${rowClass}">
                <td class="text-center fw-bold">${fmt(r.NUM_CARGA)}</td>
                <td>${fmt(r.DT_GERACAO)}</td>
                <td class="text-truncate" style="max-width:160px" title="${r.DESCRICAO ?? ''}">${fmt(r.DESCRICAO)}</td>
                <td class="text-truncate small" style="max-width:200px" title="${r.ROTA ?? ''}">${fmt(r.ROTA)}</td>
                <td class="text-end">${fmt(r.CUBAGEM)}</td>
                <td class="text-end">${fmtValor(r.VALOR_PENDENTE)}</td>
                <td class="text-end">${fmtValor(r.VALOR_FATURADO)}</td>
                <td class="text-center">${fmt(r.DT_CARREGAMENTO)}</td>
                <td class="text-center">${isFaturada ? badgePosPLC(r.POS_PLC) : badgeSituacao(r.SITUACAO)}</td>
                <td class="text-center">${badgeWms(r.STATUS_WMS)}</td>
                <td>${fmt(r.MOTORISTA)}</td>
                <td class="text-center">${r.PLACAS ? `<span class="cd-placa-carro">${r.PLACAS}</span>` : (r.FROTA ? fmt(r.FROTA) : '-')}</td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-sm btn-warning py-0 px-2 me-1" onclick="abrirModal(${r.NUM_CARGA})" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="abrirLog(${r.NUM_CARGA})" title="Histórico">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    /* ── Modal Editar ────────────────────────────────── */
    window.abrirModal = async function (numCarga) {
        const tr     = document.querySelector(`tr[data-carga="${numCarga}"]`);
        const posPLC = tr ? tr.dataset.pos : '';
        const isFat  = posPLC === 'FT' || posPLC === 'FP';

        document.getElementById('modalCargaTitulo').textContent = `Carga #${numCarga}`;
        document.getElementById('fldNumCarga').value = numCarga;

        // Limpar campos enquanto carrega
        ['fldDtCarregamento','fldSituacaoCarga','fldFrota','fldPlacas',
         'fldTipoVeiculo','fldMotorista','fldContato','fldNumDocs','fldObservacoes']
            .forEach(id => { document.getElementById(id).value = ''; });
        document.getElementById('fldSituacao').value = 'PENDENTE';

        // Esconder campo Situação para cargas já faturadas no FOCCO
        const grpSituacao = document.getElementById('fldSituacao').closest('.col-md-4');
        grpSituacao.style.display = isFat ? 'none' : '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCarga')).show();

        try {
            const res = await fetchJson('carga-api-listar', {});
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
    };

    /* ── Salvar ──────────────────────────────────────── */
    window.salvarCarga = async function () {
        const btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

        const payload = {
            num_carga:       document.getElementById('fldNumCarga').value,
            dt_carregamento: document.getElementById('fldDtCarregamento').value || null,
            situacao:        document.getElementById('fldSituacao').value,
            situacao_carga:  document.getElementById('fldSituacaoCarga').value,
            frota:           document.getElementById('fldFrota').value,
            placas:          document.getElementById('fldPlacas').value,
            tipo_veiculo:    document.getElementById('fldTipoVeiculo').value,
            motorista:       document.getElementById('fldMotorista').value,
            contato:         document.getElementById('fldContato').value,
            num_docs:        document.getElementById('fldNumDocs').value,
            observacoes:     document.getElementById('fldObservacoes').value,
        };

        try {
            const data = await fetchJson('carga-api-salvar', payload);
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
    window.abrirLog = async function (numCarga) {
        document.getElementById('logCargaTitulo').textContent = `Histórico — Carga #${numCarga}`;
        const tbody = document.getElementById('logBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLog')).show();

        try {
            const data = await fetchJson(`carga-api-log?num_carga=${numCarga}`);
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
        // Data padrão: hoje
        document.getElementById('inputDataFiltro').value = new Date().toISOString().slice(0, 10);

        document.getElementById('btnAtualizar').addEventListener('click', carregarLista);

        // Preencher situacao select
        const selSit = document.getElementById('fldSituacao');
        SITUACOES.forEach(s => {
            const o = document.createElement('option');
            o.value = s; o.textContent = s;
            selSit.appendChild(o);
        });

        carregarLista();
    });
})();
