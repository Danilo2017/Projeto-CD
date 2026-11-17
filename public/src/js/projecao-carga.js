/* Projeção de Carga — agendador */
(function () {
    'use strict';

    const SITUACOES = ['PENDENTE', 'EM CARREGAMENTO', 'CARREGADO', 'CANCELADO'];

    let carregandoLista = false;
    let _cargasData     = [];
    let _filtroTimer    = null;

    /* ── Cache de elementos DOM ───────────────────────── */
    let elTbody = null, elTotalCargas = null, elTotalPend = null, elTotalFat = null;
    let elTotalComVeiculo = null, elTotalEncHoje = null;

    /* ── Helpers ─────────────────────────────────────── */
    const _fmtBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    function fmt(v) { return v ?? '-'; }
    // a descrição agora é editável pelo usuário: escapa antes de entrar em innerHTML (texto e atributo)
    function escHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    // descrição alterada só nesta tela (DESCRICAO_TELA) difere da original do Focco (DESCRICAO_ORIG, que nunca é alterada)
    function descPersonalizada(r) { return (r.DESCRICAO ?? '') !== (r.DESCRICAO_ORIG ?? ''); }
    function tituloLapis(r) {
        return descPersonalizada(r)
            ? `Descrição alterada só nesta tela. Original no Focco: ${r.DESCRICAO_ORIG || '(vazia)'}. Clique para alterar ou voltar à original.`
            : 'Alterar a descrição (vale só nesta tela; o Focco não é alterado)';
    }
    // sequência de carregamento: menor primeiro; sem sequência segue a ordem que veio do servidor (_ord)
    // ordenação escolhida clicando no cabeçalho; null = ordem de sequência (padrão)
    let _ordem = null; // { chave, dir: 1 | -1 }
    const _COLUNAS_ORD = { // índice da coluna → [campo, tipo]
        0: ['SEQ_CARREGAMENTO', 'seq'], 1: ['NUM_CARGA', 'num'], 2: ['DT_GERACAO', 'data'], 3: ['DESCRICAO', 'txt'],
        4: ['ROTA', 'txt'], 5: ['CUBAGEM', 'num'], 6: ['VALOR_PENDENTE', 'num'], 7: ['VALOR_FATURADO', 'num'],
        8: ['DT_CARREGAMENTO', 'data'], 9: ['SITUACAO', 'txt'], 10: ['STATUS_WMS', 'txt'], 11: ['MOTORISTA', 'txt'],
        12: ['PLACAS', 'txt'], 13: ['SITUACAO_CAMINHAO', 'txt'], 14: ['DOCA', 'txt'], 15: ['NUM_DOCS', 'txt'],
    };
    function valorOrdenacao(r, campo, tipo) {
        const v = r[campo];
        if (v === null || v === undefined || String(v).trim() === '') return null;
        if (tipo === 'num')  { const n = parseFloat(v); return Number.isFinite(n) ? n : null; }
        if (tipo === 'data') { const m = /^(\d{2})\/(\d{2})\/(\d{4})/.exec(String(v)); return m ? m[3] + m[2] + m[1] : null; }
        return String(v);
    }
    function ordenarPorColuna(idx) {
        const def = _COLUNAS_ORD[idx];
        if (!def) return;
        // a tela já abre em SEQ crescente: o 1º clique na SEQ inverte; os demais alternam
        if (idx === 0) _ordem = (!_ordem || (_ordem.chave === 0 && _ordem.dir === 1)) ? { chave: 0, dir: -1 } : { chave: 0, dir: 1 };
        else _ordem = (_ordem && _ordem.chave === idx) ? { chave: idx, dir: -_ordem.dir } : { chave: idx, dir: 1 };
        ordenarCargas();
        aplicarFiltro();
        atualizarSetas();
    }
    function atualizarSetas() {
        document.querySelectorAll('th[data-ord-idx]').forEach(th => {
            const idx = Number(th.dataset.ordIdx);
            const ativo = _ordem && _ordem.chave === idx;
            th.querySelector('.ord-seta').textContent = ativo ? (_ordem.dir === 1 ? ' ▲' : ' ▼') : '';
        });
    }
    function iniciarOrdenacaoCabecalho() {
        const ths = document.querySelectorAll('#tabelaBody')[0]?.closest('table')?.querySelectorAll('thead th');
        if (!ths) return;
        ths.forEach((th, idx) => {
            if (!_COLUNAS_ORD[idx]) return;
            th.dataset.ordIdx = idx;
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            th.insertAdjacentHTML('beforeend', '<span class="ord-seta"></span>');
            th.addEventListener('click', () => ordenarPorColuna(idx));
        });
    }
    function ordenarCargas() {
        if (_ordem && _ordem.chave !== 0) {
            const [campo, tipo] = _COLUNAS_ORD[_ordem.chave];
            const col = new Intl.Collator('pt-BR', { numeric: true, sensitivity: 'base' });
            _cargasData.sort((a, b) => {
                const va = valorOrdenacao(a, campo, tipo), vb = valorOrdenacao(b, campo, tipo);
                if (va === null && vb === null) return (a._ord ?? 0) - (b._ord ?? 0);
                if (va === null) return 1;          // vazio sempre no fim
                if (vb === null) return -1;
                const c = tipo === 'txt' ? col.compare(va, vb) : (va < vb ? -1 : va > vb ? 1 : 0);
                return c !== 0 ? c * _ordem.dir : (a._ord ?? 0) - (b._ord ?? 0);
            });
            return;
        }
        // SEQ: crescente (padrão) ou decrescente
        const inverte = _ordem && _ordem.chave === 0 && _ordem.dir === -1 ? -1 : 1;
        // "17-1" vira [17, 1]; "17" vira [17, 0]; vazio/inválido vai para o fim. Compara número a número (17-2 vem antes de 17-10).
        const seqDe = r => {
            const m = /^(\d+)(?:-(\d+))?$/.exec(String(r.SEQ_CARREGAMENTO ?? '').trim());
            return m ? [parseInt(m[1], 10), m[2] ? parseInt(m[2], 10) : 0] : [Infinity, 0];
        };
        _cargasData.sort((a, b) => {
            const sa = seqDe(a), sb = seqDe(b);
            // sem sequência fica sempre no fim, também na ordem decrescente
            if (sa[0] === Infinity || sb[0] === Infinity) {
                if (sa[0] !== sb[0]) return sa[0] === Infinity ? 1 : -1;
                return (a._ord ?? 0) - (b._ord ?? 0);
            }
            if (sa[0] !== sb[0]) return (sa[0] < sb[0] ? -1 : 1) * inverte;
            if (sa[1] !== sb[1]) return (sa[1] < sb[1] ? -1 : 1) * inverte;
            return (a._ord ?? 0) - (b._ord ?? 0);
        });
    }
    function fmtValor(v) {
        const n = parseFloat(v);
        return isNaN(n) ? '-' : _fmtBRL.format(n);
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
    function badgeSitCaminhao(sit) {
        if (!sit) return '';
        const cores = {
            'DISPONÍVEL':              'bg-light text-dark border',
            'AGUARDANDO CARREGAMENTO': 'bg-secondary',
            'CARREGANDO':              'bg-warning text-dark',
            'EM TRÂNSITO':             'bg-primary',
            'EM ENTREGA':              'bg-info text-dark',
            'DESCARREGANDO':           'bg-info text-dark',
            'RETORNANDO':              'bg-success',
            'EM MANUTENÇÃO':           'bg-danger',
            'AGUARDANDO DOCUMENTAÇÃO': 'text-white',
            'FINALIZADO':              'bg-success',
        };
        const cls   = cores[sit] || 'bg-secondary';
        const style = sit === 'AGUARDANDO DOCUMENTAÇÃO'
            ? 'background-color:#fd7e14;font-size:0.65rem;white-space:normal;max-width:110px'
            : 'font-size:0.65rem;white-space:normal;max-width:110px';
        return `<span class="badge ${cls}" style="${style}">${sit}</span>`;
    }
    function badgeCanhoto(val) {
        return val === 'SIM'
            ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Sim</span>'
            : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Não</span>';
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

    function mostraWa(sit) {
        const s = (sit || '').toUpperCase();
        return s.startsWith('AGUARDANDO DOC') || s === 'FINALIZADO';
    }

    function dataFiltroAtual() {
        const hoje = (() => {
            const d = new Date();
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        })();
        return {
            inicio: document.getElementById('inputDataInicio')?.value || hoje,
            fim:    document.getElementById('inputDataFim')?.value    || hoje,
        };
    }

    /* ── Carregar lista ───────────────────────────────── */
    async function carregarLista() {
        if (carregandoLista) return;
        carregandoLista = true;
        document.getElementById('btnAtualizar').disabled = true;
        document.getElementById('tabelaBody').innerHTML =
            '<tr><td colspan="17" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>';

        try {
            const df   = dataFiltroAtual();
            const data = await fetchJson('carga-api-listar', { data_inicio: df.inicio, data_fim: df.fim });
            if (data.error) { toast(data.error); return; }
            if (data._transicao_erro) console.warn('[transição] erro ao gravar status:', data._transicao_erro);
            // _ord guarda a ordem original do servidor para as cargas sem sequência
            const lista = (data.data || []).map((r, i) => { r._ord = i; return r; });
            _cargasData = lista;
            ordenarCargas();
            renderTabela(lista);
        } catch (e) {
            toast('Erro ao carregar dados.');
        } finally {
            carregandoLista = false;
            document.getElementById('btnAtualizar').disabled = false;
        }
    }

    function filtroAtual() {
        return (document.getElementById('inputFiltro')?.value || '').toLowerCase().trim();
    }

    function aplicarFiltro() {
        const q = filtroAtual();
        if (!q) { renderTabela(_cargasData); return; }
        const filtrados = _cargasData.filter(r =>
            String(r.SEQ_CARREGAMENTO ?? '').toLowerCase().includes(q) ||
            String(r.NUM_CARGA  ?? '').toLowerCase().includes(q) ||
            String(r.DESCRICAO  ?? '').toLowerCase().includes(q) ||
            String(r.ROTA       ?? '').toLowerCase().includes(q) ||
            String(r.DT_GERACAO ?? '').toLowerCase().includes(q)
        );
        renderTabela(filtrados, true);
    }

    function renderTabela(rows, filtrado = false) {
        if (!filtrado) _cargasData = rows;
        const tbody = elTbody || document.getElementById('tabelaBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="17" class="text-center text-muted py-4">Nenhuma carga encontrada.</td></tr>';
            (elTotalCargas     || document.getElementById('totalCargas')).textContent = '0';
            (elTotalPend       || document.getElementById('totalPendente')).textContent = '-';
            (elTotalFat        || document.getElementById('totalFaturado')).textContent = '-';
            (elTotalComVeiculo || document.getElementById('totalComVeiculo')).textContent = '0';
            (elTotalEncHoje    || document.getElementById('totalEncerradasHoje')).textContent = '0';
            return;
        }
        (elTotalCargas || document.getElementById('totalCargas')).textContent = rows.length;

        const _dtFim = document.getElementById('inputDataFim')?.value;
        let _hoje;
        if (_dtFim) {
            const [_y, _m, _d] = _dtFim.split('-');
            _hoje = `${_d}/${_m}/${_y}`;
        } else {
            const _hj = new Date();
            _hoje = String(_hj.getDate()).padStart(2,'0') + '/'
                + String(_hj.getMonth()+1).padStart(2,'0') + '/'
                + _hj.getFullYear();
        }

        const [sumPend, sumFat, cntVeiculo, cntEncHoje] = rows.reduce(
            ([p, f, v, e], r) => [
                p + (parseFloat(r.VALOR_PENDENTE) || 0),
                f + (parseFloat(r.VALOR_FATURADO)  || 0),
                v + (r.MOTORISTA && r.MOTORISTA.trim() && r.DT_GERACAO === _hoje ? 1 : 0),
                e + (r.STATUS_WMS === 'Encerrada'      && r.DT_GERACAO === _hoje ? 1 : 0),
            ],
            [0, 0, 0, 0]
        );
        (elTotalPend       || document.getElementById('totalPendente')).textContent = _fmtBRL.format(sumPend);
        (elTotalFat        || document.getElementById('totalFaturado')).textContent = _fmtBRL.format(sumFat);
        (elTotalComVeiculo || document.getElementById('totalComVeiculo')).textContent = cntVeiculo;
        (elTotalEncHoje    || document.getElementById('totalEncerradasHoje')).textContent = cntEncHoje;

        tbody.innerHTML = rows.map(r => {
            const isFaturada  = r.POS_PLC === 'FT' || r.POS_PLC === 'FP';
            const temObs      = !isFaturada && r.OBSERVACOES && r.OBSERVACOES.trim() !== '';
            const rowClass    = isFaturada ? 'table-success' : (temObs ? 'table-warning' : '');
            return `
            <tr data-carga="${r.NUM_CARGA}" data-plc="${r.PLC_ID}" data-pos="${r.POS_PLC}" class="${rowClass}">
                <td class="text-center" style="width:78px">
                    <input type="text" maxlength="9" autocomplete="off" placeholder="17-1"
                           class="form-control form-control-sm text-center px-1" style="width:66px!important;height:24px!important;min-height:0!important;padding:0 4px!important;font-size:0.8rem!important;margin:0 auto"
                           value="${escHtml(r.SEQ_CARREGAMENTO)}" data-orig="${escHtml(r.SEQ_CARREGAMENTO)}"
                           title="Sequência de carregamento, ex.: 17-1, 17-2, 17-3 (menor carrega primeiro). Vazio segue a ordem padrão."
                           onchange="salvarSequencia(this, ${r.NUM_CARGA})">
                </td>
                <td class="text-center fw-bold">${r.STATUS_WMS === 'Encerrada'
                    ? `<a href="javascript:void(0)" class="text-decoration-none" onclick="abrirItensCarga(${r.NUM_CARGA})" title="Ver itens da carga">${fmt(r.NUM_CARGA)}</a>`
                    : fmt(r.NUM_CARGA)}</td>
                <td>${fmt(r.DT_GERACAO)}</td>
                <td class="td-desc" style="max-width:210px">
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-truncate" style="min-width:0" title="${escHtml(r.DESCRICAO)}">${escHtml(fmt(r.DESCRICAO))}</span>
                        <i class="bi bi-pencil-fill ${descPersonalizada(r) ? 'text-primary' : 'text-muted'} flex-shrink-0"
                           style="font-size:0.6rem;opacity:${descPersonalizada(r) ? 1 : .6};cursor:pointer"
                           title="${escHtml(tituloLapis(r))}" onclick="editarDescricao(${r.PLC_ID})"></i>
                    </div>
                </td>
                <td class="text-truncate small" style="max-width:200px;cursor:pointer"
                    title="${r.ROTA ? r.ROTA + ' — clique para editar sequência' : 'Clique para editar sequência'}"
                    onclick="abrirRota(${r.NUM_CARGA})">${fmt(r.ROTA)} <i class="bi bi-pencil-fill text-muted" style="font-size:0.6rem;opacity:.6"></i></td>
                <td class="text-end">${fmt(r.CUBAGEM)}</td>
                <td class="text-end">${fmtValor(r.VALOR_PENDENTE)}</td>
                <td class="text-end">${fmtValor(r.VALOR_FATURADO)}</td>
                <td class="text-center">${fmt(r.DT_CARREGAMENTO)}</td>
                <td class="text-center">
                    ${isFaturada ? badgePosPLC(r.POS_PLC) : badgeSituacao(r.SITUACAO)}
                    ${r.SITUACAO_CARGA ? `<div class="text-muted text-truncate" style="font-size:0.7rem;max-width:110px;margin:0 auto" title="${r.SITUACAO_CARGA}">${r.SITUACAO_CARGA}</div>` : ''}
                </td>
                <td class="text-center">${r.STATUS_WMS === 'Encerrada'
                    ? `<a href="javascript:void(0)" class="text-decoration-none" onclick="abrirExpedicaoCarga(${r.NUM_CARGA})" title="Ver itens da expedição">${badgeWms(r.STATUS_WMS)}</a>`
                    : badgeWms(r.STATUS_WMS)}</td>
                <td style="max-width:160px">
                    <div class="text-truncate" title="${r.MOTORISTA ?? ''}">${fmt(r.MOTORISTA)}</div>
                    ${r.CONTATO ? `<div class="text-muted text-truncate" style="font-size:0.7rem" title="${r.CONTATO}">${r.CONTATO}</div>` : ''}
                </td>
                <td class="text-center" style="width:115px;min-width:115px">
                    ${r.PLACAS ? `<span class="cd-placa-carro">${r.PLACAS}</span>` : (r.FROTA ? fmt(r.FROTA) : '-')}
                    ${r.PLACAS && r.FROTA ? `<div class="text-muted" style="font-size:0.7rem">${r.FROTA}</div>` : ''}
                </td>
                <td class="text-center">${r.SITUACAO_CAMINHAO ? badgeSitCaminhao(r.SITUACAO_CAMINHAO) : '<span class="text-muted small">-</span>'}</td>
                <td class="text-center small">${fmt(r.DOCA)}</td>
                <td class="text-center" style="cursor:pointer" onclick="toggleCanhoto(${r.NUM_CARGA})" title="Clique para alternar">${badgeCanhoto(r.NUM_DOCS)}</td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-sm btn-warning py-0 px-2 me-1" onclick="abrirModal(${r.NUM_CARGA})" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" onclick="abrirLog(${r.NUM_CARGA})" title="Histórico">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-dark py-0 px-2 me-1" onclick="abrirAnexos(${r.NUM_CARGA})" title="Anexos">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    ${mostraWa(r.SITUACAO_CAMINHAO)
                        ? `<button class="btn btn-sm py-0 px-2" style="background-color:#25D366;color:#fff;border-color:#25D366"
                               onclick="abrirWhatsapp(${r.NUM_CARGA})" title="Enviar via WhatsApp">
                               <i class="bi bi-whatsapp"></i>
                           </button>`
                        : ''}
                </td>
            </tr>`;
        }).join('');
    }

    /* ── Toggle Canhoto NF ────────────────────────────── */
    window.setCanhoto = function (val) {
        document.getElementById('fldNumDocs').value = val;
        const btnSim = document.getElementById('btnCanhotoSim');
        const btnNao = document.getElementById('btnCanhotoNao');
        btnSim.classList.toggle('btn-success',         val === 'SIM');
        btnSim.classList.toggle('btn-outline-success', val !== 'SIM');
        btnNao.classList.toggle('btn-danger',          val === 'NAO');
        btnNao.classList.toggle('btn-outline-danger',  val !== 'NAO');
    };

    /* ── Sequência de carregamento (grava só esse campo) ─ */
    window.salvarSequencia = async function (input, numCarga) {
        // aceita 17 ou 17-1 (traço "do Word" e espaços viram hífen simples); guarda sem zeros à esquerda
        const bruto = input.value.replace(/[–—]/g, '-').replace(/\s+/g, '');
        const antes = input.dataset.orig ?? '';
        const m = /^(\d{1,4})(?:-(\d{1,4}))?$/.exec(bruto);
        if (bruto !== '' && (!m || Number(m[1]) < 1 || (m[2] !== undefined && Number(m[2]) < 1))) {
            toast('Informe a sequência como 17 ou 17-1 (números de 1 a 9999), ou deixe vazio.', 'warning');
            input.value = antes;
            return;
        }
        const seq = bruto === '' ? null : String(parseInt(m[1], 10)) + (m[2] !== undefined ? '-' + parseInt(m[2], 10) : '');
        input.value = seq ?? '';
        if ((seq ?? '') === antes) return;
        input.disabled = true;
        try {
            const data = await fetchJson('carga-api-sequencia-salvar', { num_carga: numCarga, seq });
            if (data.error) { toast(data.error); input.value = antes; input.disabled = false; return; }
            _cargasData.forEach(r => { if (r.NUM_CARGA == numCarga) r.SEQ_CARREGAMENTO = seq; });
            ordenarCargas();
            aplicarFiltro();
            toast(seq === null ? `Sequência da carga ${numCarga} removida.` : `Carga ${numCarga} na sequência ${seq}.`, 'success');
        } catch (e) {
            toast('Erro ao gravar a sequência.');
            input.value = antes;
            input.disabled = false;
        }
    };

    /* ── Trocar a descrição da carga (grava no Focco) ──── */
    window.editarDescricao = function (plcId) {
        const r  = _cargasData.find(x => x.PLC_ID == plcId);
        const td = document.querySelector(`tr[data-plc="${plcId}"] .td-desc`);
        if (!r || !td) return;

        td.style.maxWidth = 'none';
        td.innerHTML = '<div class="d-flex align-items-center gap-1" style="min-width:320px">'
            + '<input type="text" class="form-control form-control-sm" maxlength="70" placeholder="Vazio = descrição original do Focco">'
            + '<button type="button" class="btn btn-sm btn-success py-0 px-2" title="Salvar"><i class="bi bi-check-lg"></i></button>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Cancelar"><i class="bi bi-x-lg"></i></button>'
            + '</div>';
        const inp = td.querySelector('input');
        const [btnOk, btnNo] = td.querySelectorAll('button');
        inp.value = r.DESCRICAO ?? '';

        const cancelar = () => aplicarFiltro();
        const salvar = async () => {
            const nova = inp.value.trim();
            if (nova === (r.DESCRICAO ?? '')) { cancelar(); return; }
            if (!nova && !descPersonalizada(r)) { cancelar(); return; } // já está na original: nada a restaurar
            inp.disabled = btnOk.disabled = btnNo.disabled = true;
            try {
                const data = await fetchJson('carga-api-descricao-salvar', { plc_id: plcId, descricao: nova });
                if (data.error) {
                    toast(data.error);
                    inp.disabled = btnOk.disabled = btnNo.disabled = false;
                    inp.focus();
                    return;
                }
                r.DESCRICAO = data.descricao ?? nova;
                r.DESCRICAO_ORIG = data.descricao_orig ?? r.DESCRICAO_ORIG;
                aplicarFiltro();
                toast(descPersonalizada(r)
                    ? `Descrição da carga ${data.num_carga ?? ''} alterada (só nesta tela).`
                    : `Descrição original da carga ${data.num_carga ?? ''} restaurada.`, 'success');
            } catch (e) {
                toast('Erro ao gravar a descrição.');
                inp.disabled = btnOk.disabled = btnNo.disabled = false;
            }
        };
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter')  { e.preventDefault(); salvar(); }
            if (e.key === 'Escape') { cancelar(); }
        });
        btnOk.addEventListener('click', salvar);
        btnNo.addEventListener('click', cancelar);
        inp.focus();
        inp.select();
    };

    /* ── Toggle rápido do Canhoto na tabela ───────────── */
    window.toggleCanhoto = async function (numCarga) {
        const idx = _cargasData.findIndex(x => x.NUM_CARGA == numCarga);
        if (idx === -1) return;
        const r = _cargasData[idx];
        const novoValor = r.NUM_DOCS === 'SIM' ? 'NAO' : 'SIM';

        const payload = {
            num_carga:          numCarga,
            dt_carregamento:    r.DT_CARREGAMENTO ? r.DT_CARREGAMENTO.split('/').reverse().join('-') : null,
            situacao:           r.SITUACAO          || 'PENDENTE',
            situacao_carga:     r.SITUACAO_CARGA    || '',
            frota:              r.FROTA             || '',
            placas:             r.PLACAS            || '',
            tipo_veiculo:       r.TIPO_VEICULO      || '',
            situacao_caminhao:  r.SITUACAO_CAMINHAO || '',
            motorista:          r.MOTORISTA         || '',
            contato:            r.CONTATO           || '',
            doca:               r.DOCA              || '',
            num_docs:           novoValor,
            observacoes:        r.OBSERVACOES       || '',
        };

        try {
            const data = await fetchJson('carga-api-salvar', payload);
            if (data.error) { toast(data.error); return; }
            r.NUM_DOCS = novoValor;
            renderTabela(_cargasData);
            toast(`Canhoto marcado como ${novoValor === 'SIM' ? 'entregue' : 'não entregue'}.`, 'success');
        } catch (e) {
            toast('Erro ao atualizar canhoto.');
        }
    };

    /* ── Modal Editar ────────────────────────────────── */
    window.abrirModal = function (numCarga) {
        const r      = _cargasData.find(x => x.NUM_CARGA == numCarga) || {};
        const isFat  = r.POS_PLC === 'FT' || r.POS_PLC === 'FP';

        document.getElementById('modalCargaTitulo').textContent = `Carga #${numCarga}`;
        document.getElementById('fldNumCarga').value = numCarga;

        const dtVal = r.DT_CARREGAMENTO ? r.DT_CARREGAMENTO.split('/').reverse().join('-') : '';
        document.getElementById('fldDtCarregamento').value = dtVal;
        document.getElementById('fldSituacao').value       = r.SITUACAO       || 'PENDENTE';
        document.getElementById('fldSituacaoCarga').value  = r.SITUACAO_CARGA || '';
        document.getElementById('fldFrota').value          = r.FROTA          || '';
        document.getElementById('fldPlacas').value         = r.PLACAS         || '';
        document.getElementById('fldTipoVeiculo').value      = r.TIPO_VEICULO       || '';
        document.getElementById('fldSituacaoCaminhao').value = r.SITUACAO_CAMINHAO || '';
        document.getElementById('fldMotorista').value        = r.MOTORISTA         || '';
        document.getElementById('fldContato').value        = r.CONTATO        || '';
        document.getElementById('fldDoca').value           = r.DOCA           || '';
        setCanhoto(r.NUM_DOCS === 'SIM' ? 'SIM' : 'NAO');
        document.getElementById('fldObservacoes').value    = r.OBSERVACOES    || '';

        const grpSituacao = document.getElementById('fldSituacao').closest('.col-md-4');
        grpSituacao.style.display = isFat ? 'none' : '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCarga')).show();
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
            tipo_veiculo:       document.getElementById('fldTipoVeiculo').value,
            situacao_caminhao:  document.getElementById('fldSituacaoCaminhao').value,
            motorista:          document.getElementById('fldMotorista').value,
            contato:         document.getElementById('fldContato').value,
            doca:            document.getElementById('fldDoca').value,
            num_docs:        document.getElementById('fldNumDocs').value,
            observacoes:     document.getElementById('fldObservacoes').value,
        };

        try {
            const data = await fetchJson('carga-api-salvar', payload);
            if (data.error) { toast(data.error); return; }
            toast(`Salvo com sucesso. ${data.alteracoes} campo(s) alterado(s).`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalCarga')).hide();

            // Atualiza o item em memória e re-renderiza sem nova requisição
            const idx = _cargasData.findIndex(x => x.NUM_CARGA == payload.num_carga);
            if (idx !== -1) {
                const r = _cargasData[idx];
                r.DT_CARREGAMENTO = payload.dt_carregamento
                    ? payload.dt_carregamento.split('-').reverse().join('/') : null;
                r.SITUACAO        = payload.situacao        || r.SITUACAO;
                r.SITUACAO_CARGA  = payload.situacao_carga  || null;
                r.FROTA           = payload.frota           || null;
                r.PLACAS          = payload.placas          || null;
                r.TIPO_VEICULO      = payload.tipo_veiculo      || null;
                r.SITUACAO_CAMINHAO = payload.situacao_caminhao || null;
                r.MOTORISTA         = payload.motorista         || null;
                r.CONTATO         = payload.contato         || null;
                r.DOCA            = payload.doca            || null;
                r.NUM_DOCS        = payload.num_docs        || null;
                r.OBSERVACOES     = payload.observacoes     || null;
            }
            renderTabela(_cargasData);
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

    /* ── Download Excel ──────────────────────────────── */
    window.downloadExcel = function () {
        if (!_cargasData.length) { toast('Nenhum dado para exportar.', 'warning'); return; }

        const NUMERICOS = new Set(['CUBAGEM', 'VALOR_PENDENTE', 'VALOR_FATURADO']);

        const cols = [
            ['SEQ_CARREGAMENTO',   'SEQ CARREGAMENTO'],
            ['NUM_CARGA',          'Nº CARGA'],
            ['DT_GERACAO',         'DT CARGA'],
            ['DESCRICAO',          'DESCRIÇÃO'],
            ['ROTA',               'ROTA'],
            ['CUBAGEM',            'CUBAGEM'],
            ['VALOR_PENDENTE',     'VLR PENDENTE'],
            ['VALOR_FATURADO',     'VLR FATURADO'],
            ['DT_CARREGAMENTO',    'DT CARREGAMENTO'],
            ['SITUACAO',           'SITUAÇÃO'],
            ['SITUACAO_CAMINHAO',  'SIT. CAMINHÃO'],
            ['STATUS_WMS',         'EXPEDIÇÃO'],
            ['MOTORISTA',          'MOTORISTA'],
            ['PLACAS',             'PLACA'],
            ['FROTA',              'FROTA'],
            ['TIPO_VEICULO',       'TIPO VEÍCULO'],
            ['CONTATO',            'CONTATO'],
            ['DOCA',               'DOCA'],
            ['NUM_DOCS',           'CANHOTO NF'],
            ['OBSERVACOES',        'OBSERVAÇÕES'],
            ['POS_PLC',            'POS. PLC'],
        ];

        const fmtNum = v => {
            const n = parseFloat(v);
            if (isNaN(n)) return '';
            return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        };

        const esc = (v, key) => {
            if (v === null || v === undefined || v === '') return '';
            if (NUMERICOS.has(key)) return fmtNum(v);
            const s = String(v).replaceAll('"', '""');
            return /[;\"\n\r]/.test(s) ? `"${s}"` : s;
        };

        const linhas = [
            cols.map(([, l]) => esc(l, '')).join(';'),
            ..._cargasData.map(r => cols.map(([k]) => esc(r[k], k)).join(';')),
        ];

        const blob = new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), {
            href:     url,
            download: `cargas_${document.getElementById('inputDataInicio')?.value || 'export'}.csv`,
        });
        a.click();
        URL.revokeObjectURL(url);
    };

    /* ── Modal Anexos ────────────────────────────────── */
    let _numCargaAnexo = null;

    function fmtBytes(bytes) {
        if (!bytes) return '-';
        if (bytes < 1024)        return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    window.abrirAnexos = async function (numCarga) {
        _numCargaAnexo = numCarga;
        document.getElementById('modalAnexoTitulo').innerHTML =
            `<i class="bi bi-paperclip"></i> Anexos — Carga #${numCarga}`;
        document.getElementById('fldArquivo').value = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAnexo')).show();
        await _carregarAnexos();
    };

    async function _carregarAnexos() {
        const tbody = document.getElementById('anexoBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        try {
            const data = await fetchJson(`carga-api-anexo-listar?num_carga=${_numCargaAnexo}`);
            if (!data.data?.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhum anexo vinculado.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(a => `
                <tr>
                    <td><i class="bi bi-file-earmark me-1"></i>${a.NOME_ORIG}</td>
                    <td class="text-end small">${fmtBytes(a.TAMANHO)}</td>
                    <td class="small">${a.USUARIO ?? '-'}</td>
                    <td class="small">${a.DT_CADASTRO ?? '-'}</td>
                    <td class="text-center text-nowrap">
                        <a href="carga-api-anexo-download?id=${a.ID}" target="_blank"
                           class="btn btn-sm btn-outline-primary py-0 px-2 me-1" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                onclick="_excluirAnexo(${a.ID})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Erro ao carregar anexos.</td></tr>';
        }
    }

    window.uploadAnexo = async function () {
        const input = document.getElementById('fldArquivo');
        const btn   = document.getElementById('btnUpload');
        const prog  = document.getElementById('uploadProgress');
        const files = input.files;
        if (!files.length) { toast('Selecione ao menos um arquivo.', 'warning'); return; }

        btn.disabled = true;
        prog.classList.remove('d-none');
        try {
            for (const file of files) {
                const fd = new FormData();
                fd.append('num_carga', _numCargaAnexo);
                fd.append('arquivo', file);
                const res  = await fetch('carga-api-anexo-upload', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                if (data._transicao_erro) console.warn('[transição] marcarFinalizado:', data._transicao_erro);
            }
            input.value = '';
            await _carregarAnexos();
            // Reflete FINALIZADO na tabela sem recarregar a lista
            const idx = _cargasData.findIndex(x => x.NUM_CARGA == _numCargaAnexo);
            if (idx !== -1 && _cargasData[idx].SITUACAO_CAMINHAO !== 'DISPONÍVEL') {
                _cargasData[idx].SITUACAO_CAMINHAO = 'FINALIZADO';
                renderTabela(_cargasData);
            }
            toast(`${files.length} arquivo(s) enviado(s).`, 'success');
        } catch (e) {
            toast(e.message || 'Erro no upload.');
        } finally {
            btn.disabled = false;
            prog.classList.add('d-none');
        }
    };

    window._excluirAnexo = async function (id) {
        if (!confirm('Excluir este anexo? Esta ação não pode ser desfeita.')) return;
        try {
            const data = await fetchJson('carga-api-anexo-excluir', { id });
            if (data.error) throw new Error(data.error);
            await _carregarAnexos();
        } catch (e) {
            toast(e.message || 'Erro ao excluir.');
        }
    };

    /* ── WhatsApp ────────────────────────────────────── */
    window.abrirWhatsapp = function (numCarga) {
        const r = _cargasData.find(x => x.NUM_CARGA == numCarga) || {};

        const linhas = [
            '🚛 *Projeção de Carga - Gazin*',
            '',
            `📦 Carga: *#${r.NUM_CARGA ?? numCarga}*`,
            r.PLACAS    ? `🚗 Placa: *${r.PLACAS}*`        : null,
            r.FROTA     ? `🏭 Frota: ${r.FROTA}`           : null,
            r.MOTORISTA ? `👤 Condutor: ${r.MOTORISTA}`    : null,
            r.CONTATO   ? `📱 Contato: ${r.CONTATO}`       : null,
            r.ROTA      ? `📍 Rota: ${r.ROTA}`             : null,
            '',
            `📊 Situação: *${r.SITUACAO_CAMINHAO ?? ''}*`,
        ].filter(l => l !== null);

        document.getElementById('waMensagem').value  = linhas.join('\n');
        document.getElementById('waNumCarga').value  = numCarga;
        document.getElementById('waTelefone').value  = r.CONTATO ? r.CONTATO.replace(/\D/g, '') : '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalWhatsapp')).show();
    };

    window.enviarWhatsapp = function (modo) {
        const msg = encodeURIComponent(document.getElementById('waMensagem').value);
        let url;

        if (modo === 'numero') {
            let tel = document.getElementById('waTelefone').value.replace(/\D/g, '');
            if (!tel) { toast('Informe o número de telefone.', 'warning'); return; }
            if (!tel.startsWith('55')) tel = '55' + tel;
            url = `https://wa.me/${tel}?text=${msg}`;
        } else {
            url = `https://web.whatsapp.com/send?text=${msg}`;
        }

        window.open(url, '_blank', 'noopener');
    };

    /* ── Modal Sequência de Rota ─────────────────────── */
    let _rotaState = { plcId: null, numCarga: null, rows: [] };

    window.abrirRota = async function (numCarga) {
        _rotaState = { plcId: null, numCarga, rows: [] };
        document.getElementById('modalRotaTitulo').innerHTML =
            `<i class="bi bi-geo-alt-fill"></i> Sequência — Carga #${numCarga}`;
        const div = document.getElementById('rotaLista');
        div.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRota')).show();

        try {
            const data = await fetchJson(`carga-api-rota-listar?num_carga=${numCarga}`);
            if (data.error) { div.innerHTML = `<div class="text-danger p-2">${data.error}</div>`; return; }
            if (!data.data?.length) {
                div.innerHTML = '<div class="text-muted text-center p-3">Nenhum pedido encontrado nesta carga.</div>';
                return;
            }
            _rotaState.plcId = data.data[0].PLC_ID;
            _rotaState.rows  = data.data;
            _renderRotaLista();
        } catch (e) {
            div.innerHTML = '<div class="text-danger p-2">Erro ao carregar rota.</div>';
        }
    };

    function _renderRotaLista() {
        const rows = _rotaState.rows;
        document.getElementById('rotaLista').innerHTML = `
        <p class="text-muted small px-2 mb-1">Digite a sequência de entrega de cada pedido. Pedidos com o mesmo número serão entregues juntos.</p>
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-secondary">
                <tr>
                    <th class="text-center" style="width:64px">Seq</th>
                    <th>Pedido</th>
                    <th>Cidade / UF</th>
                    <th class="text-end">Cubagem</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((r, i) => `
                <tr>
                    <td class="text-center p-1">
                        <input type="number" min="1"
                               class="form-control form-control-sm text-center fw-bold p-0"
                               style="width:54px;margin:auto"
                               data-idx="${i}"
                               value="${r.SEQ ?? (i + 1)}">
                    </td>
                    <td>${r.NUM_PEDIDO ?? '-'}</td>
                    <td>${r.CIDADE} - ${r.UF}</td>
                    <td class="text-end small">${r.CUBAGEM ?? '-'}</td>
                </tr>`).join('')}
            </tbody>
        </table>`;
    }

    window.salvarSequenciaRota = async function () {
        const btn = document.getElementById('btnSalvarRota');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const inputs = document.querySelectorAll('#rotaLista input[data-idx]');
            const sequencias = _rotaState.rows.map((r, i) => ({
                pdv_id: r.PDV_ID,
                seq:    parseInt(inputs[i]?.value || (i + 1), 10),
            }));
            const data = await fetchJson('carga-api-rota-salvar', {
                plc_id:     _rotaState.plcId,
                sequencias,
            });
            if (data.error) throw new Error(data.error);
            toast('Sequência salva com sucesso.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalRota')).hide();
        } catch (e) {
            toast(e.message || 'Erro ao salvar sequência.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvar Sequência';
        }
    };

    /* ── Modal Itens da Carga (Expedição Encerrada) ─── */
    window.abrirItensCarga = async function (numCarga) {
        document.getElementById('modalItensTitulo').textContent = `Itens — Carga #${numCarga}`;
        const tbody = document.getElementById('itensBody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItens')).show();

        try {
            const data = await fetchJson(`carga-api-itens?num_carga=${numCarga}`);
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">${data.error}</td></tr>`;
                return;
            }
            if (!data.data?.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>';
                return;
            }
            const fmtN = v => { const n = parseFloat(v); return isNaN(n) ? '-' : n.toLocaleString('pt-BR'); };
            tbody.innerHTML = data.data.map(r => {
                const qtde  = parseFloat(r.QTDE_CARGA)   || 0;
                const est98 = parseFloat(r.ESTOQUE_998)  || 0;
                const pct   = qtde > 0 ? Math.min((est98 / qtde) * 100, 100) : 0;
                const pctCls = pct >= 100 ? 'text-success fw-bold'
                             : pct >= 50  ? 'text-warning fw-bold'
                             : 'text-danger fw-bold';
                return `
                <tr>
                    <td class="text-center">${fmt(r.COD_ITEM)}</td>
                    <td>${fmt(r.DESC_TECNICA)}</td>
                    <td class="small text-muted">${fmt(r.MASCARA)}</td>
                    <td class="text-center fw-bold">${fmtN(r.QTDE_CARGA)}</td>
                    <td class="text-center">${fmtN(r.ESTOQUE_998)}</td>
                    <td class="text-center">${fmtN(r.ESTOQUE_90)}</td>
                    <td class="text-center">${fmtN(r.ESTOQUE_997)}</td>
                    <td class="text-center ${pctCls}">${qtde > 0 ? pct.toFixed(1) + '%' : '-'}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">Erro ao carregar itens.</td></tr>`;
        }
    };

    /* ── Modal Expedição WMS (Encerrada) ─────────────── */
    window.abrirExpedicaoCarga = async function (numCarga) {
        document.getElementById('modalExpedicaoTitulo').textContent = `Expedição — Carga #${numCarga}`;
        const tbody = document.getElementById('expedicaoBody');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExpedicao')).show();

        try {
            const data = await fetchJson(`carga-api-itens-expedicao?num_carga=${numCarga}`);
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-3">${data.error}</td></tr>`;
                return;
            }
            if (!data.data?.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>';
                return;
            }
            const fmtN   = v => { const n = parseFloat(v); return isNaN(n) ? '-' : n.toLocaleString('pt-BR'); };
            const fmtPct = v => { const n = parseFloat(v); return isNaN(n) ? '-' : n.toFixed(1) + '%'; };
            tbody.innerHTML = data.data.map(r => `
                <tr>
                    <td class="text-center">${fmt(r.NUM_CARGA)}</td>
                    <td><span class="badge bg-success">${fmt(r.DESCRICAO_STATUS)}</span></td>
                    <td class="text-center">${fmt(r.NUM_PEDIDO)}</td>
                    <td class="text-center">${fmt(r.CODIGO)}</td>
                    <td>${fmt(r.DESCRICAO)}</td>
                    <td class="text-center">${fmtN(r.QTDE)}</td>
                    <td class="text-center">${fmtN(r.QTDE_EXECUTADA)}</td>
                    <td class="text-center">${fmtN(r.QTDE_DISTRIBUIDA)}</td>
                    <td class="text-center">${fmtPct(r.PERCENTUAL)}</td>
                </tr>`).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-3">Erro ao carregar itens de expedição.</td></tr>`;
        }
    };

    /* ── Init ─────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        // Cache dos elementos DOM usados em renderTabela
        elTbody           = document.getElementById('tabelaBody');
        iniciarOrdenacaoCabecalho();
        elTotalCargas     = document.getElementById('totalCargas');
        elTotalPend       = document.getElementById('totalPendente');
        elTotalFat        = document.getElementById('totalFaturado');
        elTotalComVeiculo = document.getElementById('totalComVeiculo');
        elTotalEncHoje    = document.getElementById('totalEncerradasHoje');

        // Data padrão: hoje no fuso local (toISOString usa UTC e pode dar dia errado)
        const _h = new Date();
        const _dataHoje = _h.getFullYear() + '-'
            + String(_h.getMonth() + 1).padStart(2, '0') + '-'
            + String(_h.getDate()).padStart(2, '0');
        document.getElementById('inputDataInicio').value = _dataHoje;
        document.getElementById('inputDataFim').value    = _dataHoje;

        document.getElementById('btnAtualizar').addEventListener('click', carregarLista);

        document.getElementById('inputFiltro').addEventListener('input', function () {
            clearTimeout(_filtroTimer);
            _filtroTimer = setTimeout(aplicarFiltro, 200);
        });
        document.getElementById('btnLimparFiltro').addEventListener('click', function () {
            clearTimeout(_filtroTimer);
            document.getElementById('inputFiltro').value = '';
            aplicarFiltro();
        });

        // Preencher situacao select
        const selSit = document.getElementById('fldSituacao');
        SITUACOES.forEach(s => {
            const o = document.createElement('option');
            o.value = s; o.textContent = s;
            selSit.appendChild(o);
        });

        // Aguarda clique do usuário — não carrega automaticamente
        document.getElementById('tabelaBody').innerHTML =
            '<tr><td colspan="17" class="text-center text-muted py-5">'
            + '<i class="bi bi-search me-2"></i>Selecione a data e clique em <strong>Buscar</strong>.'
            + '</td></tr>';
    });
})();
