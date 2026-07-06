/* Transferência de Estoque */
(function () {
    'use strict';

    let _itens = [];      // linha completa
    let _selecionados = new Set();

    /* ── Helpers ─────────────────────────────────────── */
    function toast(msg, tipo = 'danger') {
        const el = document.getElementById('toastTransf');
        el.querySelector('.toast-body').textContent = msg;
        el.className = `toast align-items-center text-white border-0 bg-${tipo}`;
        bootstrap.Toast.getOrCreateInstance(el).show();
    }
    async function fetchJson(url, body) {
        const opts = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
            : { method: 'GET' };
        const r = await fetch(url, opts);
        return r.json();
    }
    function fmtN(v) { const n = parseFloat(v); return isNaN(n) ? '0' : n.toLocaleString('pt-BR'); }
    function almoxOrig() { return document.getElementById('selAlmoxOrig').value.trim(); }
    function almoxDest() { return document.getElementById('selAlmoxDest').value.trim(); }

    /* ── Buscar Saldo ────────────────────────────────── */
    window.buscarSaldo = async function () {
        const orig    = almoxOrig();
        const dest    = almoxDest();
        const codItem = document.getElementById('inputCodItem').value.trim();

        if (!orig) { toast('Selecione o almoxarifado origem.', 'warning'); return; }
        if (!dest) { toast('Selecione o almoxarifado destino.', 'warning'); return; }
        if (orig === dest) { toast('Origem e destino devem ser diferentes.', 'warning'); return; }

        document.getElementById('tabelaBody').innerHTML =
            '<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Buscando saldo...</td></tr>';

        try {
            const data = await fetchJson('processo-api-transf-saldo', {
                almox_orig: orig,
                ...(codItem ? { cod_item: codItem } : {}),
            });
            if (data.error) { toast(data.error); renderTabela([]); return; }

            const rows = (data.data || []).map(r => ({
                ...r,
                almox_orig: orig,
                almox_dest: dest,
                qtde:       0,
                status:     'pendente',
                origem:     'busca',
            }));
            _itens = rows;
            _selecionados.clear();
            renderTabela();
            if (!rows.length) toast('Nenhum item com saldo no almoxarifado origem.', 'warning');
        } catch (e) {
            toast('Erro ao buscar saldo: ' + e.message);
            renderTabela([]);
        }
    };

    /* ── Render ──────────────────────────────────────── */
    function renderTabela() {
        const tbody = document.getElementById('tabelaBody');
        document.getElementById('totalItens').textContent = _itens.length;
        atualizarBotao();

        if (!_itens.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Nenhum item carregado.</td></tr>';
            return;
        }

        tbody.innerHTML = _itens.map((r, idx) => {
            const sel   = _selecionados.has(idx);
            const qtde  = parseFloat(r.qtde) || 0;
            const saldo = parseFloat(r.ESTOQUE) || 0;
            const valido = qtde > 0 && qtde <= saldo;
            const badge  = r.status === 'ok'
                ? '<span class="badge bg-success">Transferido</span>'
                : r.status === 'erro'
                    ? `<span class="badge bg-danger">${r.erroMsg || 'Erro'}</span>`
                    : qtde <= 0
                        ? '<span class="badge bg-secondary">Informe qtde</span>'
                        : qtde > saldo
                            ? '<span class="badge bg-danger">Qtde excede saldo</span>'
                            : '<span class="badge bg-primary">Pronto</span>';
            return `
<tr class="${r.status === 'ok' ? 'table-success' : r.status === 'erro' ? 'table-danger' : ''}">
    <td class="text-center">
        <input type="checkbox" class="ck-item" data-idx="${idx}"
               ${sel ? 'checked' : ''} ${r.status === 'ok' ? 'disabled' : ''}
               onchange="toggleItem(${idx})">
    </td>
    <td class="fw-bold">${r.COD_ITEM ?? '-'}</td>
    <td class="text-muted small">${r.ID_MASCARA ?? '-'}</td>
    <td class="text-truncate" style="max-width:220px" title="${r.DESCRICAO ?? ''}">${r.DESCRICAO ?? '-'}</td>
    <td class="small text-muted">${r.MASCARA ?? '-'}</td>
    <td class="text-center">${r.UM ?? '-'}</td>
    <td class="text-center"><span class="badge bg-secondary">${r.almox_orig}</span></td>
    <td class="text-center"><span class="badge bg-primary">${r.almox_dest}</span></td>
    <td class="text-center fw-bold ${saldo === 0 ? 'text-danger' : 'text-success'}">${fmtN(saldo)}</td>
    <td class="text-center p-1">
        ${r.status === 'ok' ? fmtN(qtde) : `
        <input type="number" min="1" max="${saldo}" step="1"
               class="form-control form-control-sm text-center p-0"
               style="width:88px;margin:auto"
               value="${qtde > 0 ? qtde : ''}"
               placeholder="0"
               oninput="setQtde(${idx}, this.value)">`}
    </td>
    <td class="text-center">${badge}</td>
</tr>`;
        }).join('');
    }

    window.setQtde = function (idx, val) {
        _itens[idx].qtde = parseFloat(val) || 0;
        atualizarBotao();
        // Atualiza só o badge da linha sem re-render completo
        const row = document.querySelector(`.ck-item[data-idx="${idx}"]`)?.closest('tr');
        if (!row) return;
        const saldo = parseFloat(_itens[idx].ESTOQUE) || 0;
        const qtde  = _itens[idx].qtde;
        const badgeTd = row.cells[10];
        if (qtde <= 0)
            badgeTd.innerHTML = '<span class="badge bg-secondary">Informe qtde</span>';
        else if (qtde > saldo)
            badgeTd.innerHTML = '<span class="badge bg-danger">Qtde excede saldo</span>';
        else
            badgeTd.innerHTML = '<span class="badge bg-primary">Pronto</span>';
    };

    window.toggleItem = function (idx) {
        if (_selecionados.has(idx)) _selecionados.delete(idx);
        else _selecionados.add(idx);
        atualizarBotao();
    };

    window.toggleTodos = function () {
        const checked = document.getElementById('checkTodos').checked;
        _selecionados.clear();
        if (checked) {
            _itens.forEach((r, i) => { if (r.status !== 'ok') _selecionados.add(i); });
        }
        document.querySelectorAll('.ck-item').forEach(cb => {
            if (!cb.disabled) cb.checked = checked;
        });
        atualizarBotao();
    };

    function atualizarBotao() {
        const count = _selecionados.size;
        const cntEl = document.getElementById('cntSel');
        if (cntEl) cntEl.textContent = count;
        const btnT = document.getElementById('btnTransferir');
        if (btnT && !btnT.querySelector('.spinner-border')) btnT.disabled = count === 0;
    }

    window.limparTabela = function () {
        _itens = [];
        _selecionados.clear();
        renderTabela();
    };

    /* ── Executar ────────────────────────────────────── */
    window.executarTransferencia = async function () {
        if (!_selecionados.size) { toast('Selecione ao menos um item.', 'warning'); return; }

        const itensSel = [..._selecionados].map(idx => _itens[idx]);
        const invalidos = itensSel.filter(r => !(r.qtde > 0 && r.qtde <= parseFloat(r.ESTOQUE)));
        if (invalidos.length) {
            toast(`${invalidos.length} item(ns) sem qtde válida ou que excede o saldo.`, 'warning');
            return;
        }

        const payload = itensSel.map(r => ({
            cod_item:   r.COD_ITEM,
            id_mascara: r.ID_MASCARA,
            almox_orig: r.almox_orig,
            almox_dest: r.almox_dest,
            qtde:       r.qtde,
            saldo:      r.ESTOQUE,
        }));

        const btn = document.getElementById('btnTransferir');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Transferindo...';

        try {
            const data = await fetchJson('processo-api-transf-executar', { itens: payload });
            if (data.error) { toast(data.error); return; }

            [..._selecionados].forEach(idx => { _itens[idx].status = 'ok'; });
            _selecionados.clear();
            renderTabela();
            toast(`${data.ok} item(ns) transferido(s) com sucesso!`, 'success');
        } catch (e) {
            toast('Erro ao executar transferência: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-left-right"></i> Transferir Selecionados (<span id="cntSel">0</span>)';
            atualizarBotao();
        }
    };

    /* ── Importar CSV ────────────────────────────────── */
    window.importarCsv = async function (input) {
        const file = input.files[0];
        if (!file) return;
        input.value = '';

        const rawTexto = await file.text();
        // Remove BOM (UTF-8 e UTF-16)
        const texto  = rawTexto.replace(/^﻿/, '').replace(/^\xFF\xFE/, '').replace(/^\xFE\xFF/, '');
        const linhas = texto.split(/\r?\n/).filter(l => l.trim());
        if (linhas.length < 2) { toast('CSV vazio ou sem dados.', 'warning'); return; }

        // Auto-detecta separador (;  ou  ,  ou  tab)
        const primeiraLinha = linhas[0];
        const sep = primeiraLinha.includes('\t') ? '\t'
                  : (primeiraLinha.split(';').length >= primeiraLinha.split(',').length ? ';' : ',');

        const cabecalho = primeiraLinha.split(sep).map(c => c.trim().replace(/[^A-Z0-9_]/gi, '').toUpperCase());
        console.log('[CSV] sep:', JSON.stringify(sep), 'colunas:', cabecalho);

        const idxCod   = cabecalho.indexOf('COD_ITEM');
        const idxMasc  = cabecalho.indexOf('ID_MASCARA');
        const idxOrig  = cabecalho.indexOf('ALMOX_ORIG');
        const idxDest  = cabecalho.indexOf('ALMOX_DEST');
        const idxQtde  = cabecalho.indexOf('QTDE');

        if ([idxCod, idxMasc, idxOrig, idxDest, idxQtde].includes(-1)) {
            toast(`CSV deve ter colunas: COD_ITEM, ID_MASCARA, ALMOX_ORIG, ALMOX_DEST, QTDE. Detectadas: ${cabecalho.join(', ')}`, 'warning');
            return;
        }

        const registros = linhas.slice(1).map(l => {
            const cols = l.split(sep);
            return {
                COD_ITEM:   cols[idxCod]?.trim()  || '',
                ID_MASCARA: cols[idxMasc]?.trim()  || '',
                almox_orig: cols[idxOrig]?.trim()  || '',
                almox_dest: cols[idxDest]?.trim()  || '',
                qtde:       parseFloat(cols[idxQtde]?.trim()) || 0,
            };
        }).filter(r => r.COD_ITEM && r.ID_MASCARA);

        if (!registros.length) { toast('Nenhum registro válido no CSV.', 'warning'); return; }

        // Valida saldo para cada registro importado
        document.getElementById('tabelaBody').innerHTML =
            `<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> Validando ${registros.length} item(ns)...</td></tr>`;

        const almoxsOrig = [...new Set(registros.map(r => r.almox_orig))];
        const saldoMap   = {};

        for (const almox of almoxsOrig) {
            const itensAlmox = registros.filter(r => r.almox_orig === almox);
            const codItems   = [...new Set(itensAlmox.map(r => r.COD_ITEM))];
            for (const codItem of codItems) {
                try {
                    const resp = await fetchJson('processo-api-transf-saldo', { almox_orig: almox, cod_item: codItem });
                    if (resp.data) {
                        resp.data.forEach(s => {
                            const key = `${s.COD_ITEM}_${s.ID_MASCARA}_${almox}`;
                            saldoMap[key] = s;
                        });
                    }
                } catch (_) {}
            }
        }

        _itens = registros.map(r => {
            const key  = `${r.COD_ITEM}_${r.ID_MASCARA}_${r.almox_orig}`;
            const info = saldoMap[key];
            return {
                COD_ITEM:   r.COD_ITEM,
                ID_MASCARA: r.ID_MASCARA,
                DESCRICAO:  info?.DESCRICAO  || '-',
                MASCARA:    info?.MASCARA     || '-',
                UM:         info?.UM          || '-',
                ESTOQUE:    info?.ESTOQUE     || 0,
                almox_orig: r.almox_orig,
                almox_dest: r.almox_dest,
                qtde:       r.qtde,
                status:     'pendente',
                origem:     'csv',
            };
        });

        _selecionados = new Set(
            _itens.map((r, i) => (r.qtde > 0 && r.qtde <= parseFloat(r.ESTOQUE)) ? i : null)
                  .filter(i => i !== null)
        );

        renderTabela();
        toast(`${_itens.length} item(ns) importado(s). ${_selecionados.size} pronto(s) para transferência.`, 'success');
    };

    /* ── Download Template CSV ───────────────────────── */
    window.downloadTemplate = function () {
        const conteudo = 'COD_ITEM;ID_MASCARA;ALMOX_ORIG;ALMOX_DEST;QTDE\n600007;966146;103;90;2\n';
        const blob = new Blob(['﻿' + conteudo], { type: 'text/csv;charset=utf-8;' });
        const a = Object.assign(document.createElement('a'), {
            href:     URL.createObjectURL(blob),
            download: 'template_transferencia_estoque.csv',
        });
        a.click();
        URL.revokeObjectURL(a.href);
    };
})();
