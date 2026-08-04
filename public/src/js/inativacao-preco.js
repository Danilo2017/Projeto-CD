(function () {
    'use strict';

    const selFilial  = document.getElementById('selFilial');
    const emprIdSessao = () => parseInt(document.getElementById('hEmprIdSessao').value, 10);
    const emprId     = () => parseInt(selFilial.value, 10) || emprIdSessao();

    async function post(url, body) {
        const res = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        });
        return res.json();
    }

    async function get(url) {
        const res = await fetch(url);
        return res.json();
    }

    // ── CARREGAR FILIAIS ──────────────────────────────────
    async function carregarFiliais() {
        const sessaoId = emprIdSessao();
        try {
            const data = await get('pd-api-listar-filiais');
            const filiais = data.rows || [];

            if (!filiais.length) {
                selFilial.innerHTML = `<option value="${sessaoId}">Filial ${sessaoId}</option>`;
                return;
            }

            selFilial.innerHTML = filiais.map(f => {
                const id  = f.EMPR_ID;
                const sel = id == sessaoId ? ' selected' : '';
                return `<option value="${id}"${sel}>Filial ${id}</option>`;
            }).join('');
        } catch (e) {
            selFilial.innerHTML = `<option value="${sessaoId}">Filial ${sessaoId}</option>`;
        }
    }

    selFilial.addEventListener('change', () => carregarHistorico());

    // ── HISTÓRICO ─────────────────────────────────────────
    let _todosHistorico = []; // cache para filtro client-side

    async function carregarHistorico() {
        const tbody   = document.getElementById('tbodyFila');
        const badge   = document.getElementById('badgeFila');
        const msgFila = document.getElementById('msgFila');
        msgFila.innerHTML = '';

        try {
            const data = await get(`pd-api-listar-cadastros?empr_id=${emprId()}`);
            if (data.error) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Histórico indisponível.</td></tr>';
                return;
            }

            _todosHistorico = data.rows || [];
            badge.textContent = _todosHistorico.length;
            renderHistorico(_todosHistorico);

        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Histórico indisponível.</td></tr>';
        }
    }

    function renderHistorico(rows) {
        const tbody = document.getElementById('tbodyFila');
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum registro.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const qtdAtivos = parseInt(r.QTD_ATIVOS ?? 0, 10);
            const tmascId   = r.TMASC_ITEM_ID ?? '';
            const emprIdR   = r.EMPR_ID ?? '';
            const descEsc   = (r.DESC_TECNICA ?? '').replace(/"/g, '&quot;');
            const mascEsc   = (r.MASCARA ?? '').replace(/"/g, '&quot;');

            const statusBadge = qtdAtivos === 0
                ? `<span class="badge bg-success pd-status-badge" style="cursor:pointer" title="Clique para ver pedidos"
                       data-tmasc="${tmascId}" data-empr="${emprIdR}" data-desc="${descEsc}" data-masc="${mascEsc}">
                       <i class="bi bi-check-circle"></i> Inativo</span>`
                : `<span class="badge bg-danger pd-status-badge"  style="cursor:pointer" title="Clique para ver pedidos"
                       data-tmasc="${tmascId}" data-empr="${emprIdR}" data-desc="${descEsc}" data-masc="${mascEsc}">
                       <i class="bi bi-exclamation-triangle"></i> Ativo (${qtdAtivos})</span>`;

            const btnExcl = `<button class="btn btn-xs btn-sm btn-outline-danger" onclick="removerItem(${r.ID})" title="Remover"><i class="bi bi-trash"></i></button>`;
            return `<tr>
                <td>${emprIdR}</td>
                <td>${r.COD_ITEM ?? ''}</td>
                <td>${tmascId}</td>
                <td class="text-wrap">${r.DESC_TECNICA ?? ''}</td>
                <td>${r.MASCARA ?? ''}</td>
                <td>${r.DT_CADASTRO ?? ''}</td>
                <td>${statusBadge}</td>
                <td class="text-center">${btnExcl}</td>
            </tr>`;
        }).join('');
    }

    // ── MODAL PEDIDOS PENDENTES ───────────────────────────
    let _modalPedidos  = null;
    let _modalRows     = [];
    let _modalFilename = 'pedidos_pendentes.csv';

    const btnExcel = document.getElementById('btnExcelPedidos');

    btnExcel.addEventListener('click', function () {
        if (!_modalRows.length) return;
        const cols    = ['EMPR_ID','DT_GERACAO','NUM_PEDIDO','SIT_PDV','SIT_FAT','SIT_FAT_COM','SIT_FAT_FIN','SIT_PDV_COM'];
        const headers = ['Filial','Dt. Geração','Nº Pedido','Sit. PDV','Sit. Fat.','Sit. Fat. Com.','Sit. Fat. Fin.','Sit. PDV Com.'];
        const csv = [
            headers.join(';'),
            ..._modalRows.map(r => cols.map(c => {
                const v = (r[c] ?? '').toString().replace(/"/g, '""');
                return `"${v}"`;
            }).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = _modalFilename;
        a.click();
        URL.revokeObjectURL(url);
    });

    window.abrirPedidosPendentes = async function (tmascItemId, emprId, descTecnica, mascara) {
        const titulo   = document.getElementById('modalPedidosTitulo');
        const loading  = document.getElementById('modalPedidosLoading');
        const content  = document.getElementById('modalPedidosContent');
        const tbody    = document.getElementById('tbodyPedidos');
        const countEl  = document.getElementById('modalPedidosCount');

        titulo.textContent    = `${descTecnica} — ${mascara}`;
        loading.style.display = '';
        content.style.display = 'none';
        btnExcel.style.display = 'none';
        tbody.innerHTML       = '';
        countEl.textContent   = '0';
        _modalRows            = [];
        _modalFilename        = `pedidos_${tmascItemId}.csv`;

        if (!_modalPedidos) {
            _modalPedidos = new bootstrap.Modal(document.getElementById('modalPedidos'));
        }
        _modalPedidos.show();

        try {
            const data = await post('pd-api-pedidos-pendentes', { tmasc_item_id: tmascItemId });

            _modalRows = data.rows || [];
            countEl.textContent = _modalRows.length;

            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">${data.error}</td></tr>`;
            } else if (_modalRows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Nenhum pedido com saldo encontrado.</td></tr>';
            } else {
                tbody.innerHTML = _modalRows.map(r => `<tr>
                    <td>${r.EMPR_ID     ?? ''}</td>
                    <td>${r.DT_GERACAO  ?? ''}</td>
                    <td>${r.NUM_PEDIDO  ?? ''}</td>
                    <td>${r.SIT_PDV     ?? ''}</td>
                    <td>${r.SIT_FAT     ?? ''}</td>
                    <td>${r.SIT_FAT_COM ?? ''}</td>
                    <td>${r.SIT_FAT_FIN ?? ''}</td>
                    <td>${r.SIT_PDV_COM ?? ''}</td>
                </tr>`).join('');
                btnExcel.style.display = '';
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">Erro: ${e.message}</td></tr>`;
        } finally {
            loading.style.display = 'none';
            content.style.display = '';
        }
    };

    // Clique no badge de status → abre modal de pedidos
    document.getElementById('tbodyFila').addEventListener('click', function (e) {
        const badge = e.target.closest('.pd-status-badge');
        if (!badge) return;
        abrirPedidosPendentes(
            parseInt(badge.dataset.tmasc, 10),
            parseInt(badge.dataset.empr,  10),
            badge.dataset.desc,
            badge.dataset.masc
        );
    });

    // ── FILTRO DO HISTÓRICO ───────────────────────────────
    const inpFiltro = document.getElementById('inpFiltroHistorico');
    inpFiltro.addEventListener('input', function () {
        const termo = this.value.toLowerCase().trim();
        if (!termo) {
            renderHistorico(_todosHistorico);
            return;
        }
        const filtrado = _todosHistorico.filter(r =>
            String(r.COD_ITEM ?? '').includes(termo) ||
            (r.DESC_TECNICA ?? '').toLowerCase().includes(termo) ||
            (r.MASCARA      ?? '').toLowerCase().includes(termo) ||
            String(r.TMASC_ITEM_ID ?? '').includes(termo)
        );
        renderHistorico(filtrado);
    });

    document.getElementById('btnLimparFiltro').addEventListener('click', function () {
        inpFiltro.value = '';
        renderHistorico(_todosHistorico);
    });

    // ── REMOVER ITEM ─────────────────────────────────────
    window.removerItem = async function (id) {
        if (!confirm('Remover este registro do histórico?')) return;
        try {
            const data = await post('pd-api-excluir-item', { empr_id: emprId(), id });
            if (data.error) { alert(data.error); return; }
            carregarHistorico();
        } catch (e) {
            alert('Erro: ' + e.message);
        }
    };

    // ── BUSCAR ────────────────────────────────────────────
    document.getElementById('btnBuscar').addEventListener('click', async function () {
        const codItem = parseInt(document.getElementById('inpCodItem').value.trim(), 10);
        const msgEl   = document.getElementById('msgBusca');
        const area    = document.getElementById('areaBusca');
        const tbody   = document.getElementById('tbodyResultados');
        const badge   = document.getElementById('badgeBusca');

        if (!codItem || codItem <= 0) { alert('Informe o código do item.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        area.style.display = 'none';

        try {
            const data = await post('pd-api-buscar-itens', { empr_id: emprId(), cod_item: codItem });

            if (data.error) { msgEl.innerHTML = `<span class="text-danger">${data.error}</span>`; return; }

            const rows = data.rows || [];
            badge.textContent = rows.length;

            if (rows.length === 0) {
                msgEl.innerHTML = '<span class="text-warning">Nenhum item encontrado para este código.</span>';
                return;
            }

            tbody.innerHTML = rows.map(r => `<tr>
                <td><input type="checkbox" class="chk-item"
                    data-cod="${r.COD_ITEM}"
                    data-id="${r.TMASC_ITEM_ID}"
                    data-desc="${(r.DESC_TECNICA || '').replace(/"/g, '&quot;')}"
                    data-masc="${(r.MASCARA || '').replace(/"/g, '&quot;')}"></td>
                <td>${r.EMPR_ID ?? ''}</td>
                <td>${r.COD_ITEM ?? ''}</td>
                <td>${r.TMASC_ITEM_ID ?? ''}</td>
                <td class="text-wrap">${r.DESC_TECNICA ?? ''}</td>
                <td>${r.MASCARA ?? ''}</td>
            </tr>`).join('');

            area.style.display = '';
            atualizarBtnCadastrar();

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${e.message}</span>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Buscar';
        }
    });

    // Selecionar todos
    document.getElementById('chkTodos').addEventListener('change', function () {
        document.querySelectorAll('.chk-item').forEach(c => c.checked = this.checked);
        atualizarBtnCadastrar();
    });
    document.getElementById('btnSelecionarTodos').addEventListener('click', function () {
        const chks = document.querySelectorAll('.chk-item');
        const todos = [...chks].every(c => c.checked);
        chks.forEach(c => c.checked = !todos);
        atualizarBtnCadastrar();
    });
    document.getElementById('tbodyResultados').addEventListener('change', function (e) {
        if (e.target.classList.contains('chk-item')) atualizarBtnCadastrar();
    });

    function atualizarBtnCadastrar() {
        const sel = document.querySelectorAll('.chk-item:checked').length;
        document.getElementById('btnCadastrar').disabled = sel === 0;
    }

    // ── INATIVAR ─────────────────────────────────────────
    document.getElementById('btnCadastrar').addEventListener('click', async function () {
        const selecionados = [...document.querySelectorAll('.chk-item:checked')].map(c => ({
            cod_item:      parseInt(c.dataset.cod, 10),
            tmasc_item_id: parseInt(c.dataset.id,  10),
            desc_tecnica:  c.dataset.desc,
            mascara:       c.dataset.masc,
        }));

        if (selecionados.length === 0) { alert('Selecione ao menos um item.'); return; }

        if (!confirm(`Inativar ${selecionados.length} item(ns) nas tabelas de preço agora?`)) return;

        const msgEl = document.getElementById('msgBusca');
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Inativando...';
        msgEl.innerHTML = '';

        try {
            const data = await post('pd-api-cadastrar-itens', { empr_id: emprId(), itens: selecionados });

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                return;
            }

            const tipo  = data.erros && data.erros.length > 0 ? 'warning' : 'success';
            const icone = tipo === 'success' ? 'check-circle' : 'exclamation-triangle';
            msgEl.innerHTML = `<span class="text-${tipo}"><i class="bi bi-${icone}"></i> ${data.message}</span>`;

            document.querySelectorAll('.chk-item').forEach(c => c.checked = false);
            document.getElementById('chkTodos').checked = false;
            atualizarBtnCadastrar();

            carregarHistorico();

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${e.message}</span>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-slash-circle"></i> Inativar Selecionados';
        }
    });

    document.getElementById('btnRecarregar').addEventListener('click', carregarHistorico);

    // ── INIT ─────────────────────────────────────────────
    carregarFiliais().then(() => carregarHistorico());
})();
