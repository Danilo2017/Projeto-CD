/**
 * Transferência de Pedidos entre Filiais
 */

(function () {
    'use strict';

    // ── Elementos ──────────────────────────────────────────────
    const selOrigem        = document.getElementById('selOrigem');
    const txtNumeros       = document.getElementById('txtNumeros');
    const btnBuscar        = document.getElementById('btnBuscar');

    let pedidoMap = {}; // id → num_pedido, montado na busca e usado no resultado
    const secaoPedidos     = document.getElementById('secaoPedidos');
    const tabelaPedidos    = document.getElementById('tabelaPedidos');
    const totalPedidos     = document.getElementById('totalPedidos');
    const chkTodos         = document.getElementById('chkTodos');
    const selDestino       = document.getElementById('selDestino');
    const inpTipoNf        = document.getElementById('inpTipoNf');
    const inpPreven        = document.getElementById('inpPreven');
    const btnTransferir    = document.getElementById('btnTransferir');
    const secaoResultados  = document.getElementById('secaoResultados');
    const tabelaResultados = document.getElementById('tabelaResultados');
    const resumoResultado  = document.getElementById('resumoResultado');

    // ── Utilitários ────────────────────────────────────────────
    function setLoading(btn, carregando) {
        btn.disabled = carregando;
        if (carregando) {
            btn.dataset.original = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aguarde...';
        } else {
            btn.innerHTML = btn.dataset.original || btn.innerHTML;
        }
    }

    function toast(msg, tipo) {
        tipo = tipo || 'danger';
        const div = document.createElement('div');
        div.className = 'alert alert-' + tipo + ' alert-dismissible position-fixed bottom-0 end-0 m-3 shadow';
        div.style.zIndex = 9999;
        div.style.maxWidth = '480px';
        div.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(div);
        setTimeout(function () { div.remove(); }, 8000);
    }

    function fetchJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.text().then(function (txt) {
                try {
                    return JSON.parse(txt);
                } catch (_) {
                    throw new Error(txt.substring(0, 500));
                }
            });
        });
    }

    function formatarValor(v) {
        if (v === null || v === undefined || v === '') return '-';
        const n = parseFloat(String(v).replace(/,/g, ''));
        if (isNaN(n)) return '-';
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function pedidosSelecionados() {
        return Array.from(tabelaPedidos.querySelectorAll('input[type=checkbox]:checked'))
            .map(function (c) { return c.value; });
    }

    // ── Buscar Pedidos ─────────────────────────────────────────
    btnBuscar.addEventListener('click', function () {
        const emprOrigId = selOrigem.value;
        const numeros    = txtNumeros.value.trim();
        if (!emprOrigId) { toast('Selecione a Filial Origem.'); return; }

        setLoading(btnBuscar, true);
        secaoPedidos.classList.add('d-none');
        secaoResultados.classList.add('d-none');
        tabelaPedidos.innerHTML = '';
        pedidoMap = {};

        fetchJson('pedidos-api-transferencia-buscar', { empr_orig_id: emprOrigId, numeros: numeros })
            .then(function (data) {
                if (data.error) { toast(data.error); return; }

                const pedidos = data.pedidos || [];
                totalPedidos.textContent = pedidos.length;

                if (pedidos.length === 0) {
                    tabelaPedidos.innerHTML =
                        '<tr><td colspan="9" class="text-center text-muted py-3">Nenhum pedido encontrado para esta filial.</td></tr>';
                } else {
                    pedidos.forEach(function (p) {
                        pedidoMap[p.ID] = p.NUM_PEDIDO;
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><input type="checkbox" class="form-check-input chk-pedido" value="' + p.ID + '" checked></td>' +
                            '<td><strong>' + p.NUM_PEDIDO + '</strong></td>' +
                            '<td>' + (p.COD_CLIENTE || '-') + '</td>' +
                            '<td class="text-truncate" style="max-width:180px;" title="' + htmlEscape(p.NOME_CLIENTE || '') + '">' + htmlEscape(p.NOME_CLIENTE || '-') + '</td>' +
                            '<td>' + (p.COD_TP_NF || '-') + '</td>' +
                            '<td class="text-truncate" style="max-width:140px;" title="' + htmlEscape(p.DESCRICAO_NF || '') + '">' + htmlEscape(p.DESCRICAO_NF || '-') + '</td>' +
                            '<td>' + (p.COD_DIVISAO || '-') + '</td>' +
                            '<td class="text-truncate" style="max-width:140px;" title="' + htmlEscape(p.DESCRICAO_DIVISAO || '') + '">' + htmlEscape(p.DESCRICAO_DIVISAO || '-') + '</td>' +
                            '<td class="text-end">' + formatarValor(p.VLR_LIQ) + '</td>';
                        tabelaPedidos.appendChild(tr);
                    });

                    tabelaPedidos.querySelectorAll('.chk-pedido').forEach(function (c) {
                        c.addEventListener('change', sincronizarChkTodos);
                    });

                    // Pré-preenche COD_TP_NF com o valor único se todos os pedidos tiverem o mesmo
                    const codigos = [...new Set(pedidos.map(function (p) { return p.COD_TP_NF; }).filter(Boolean))];
                    if (codigos.length === 1) inpTipoNf.value = codigos[0];
                }

                chkTodos.checked = true;
                secaoPedidos.classList.remove('d-none');
            })
            .catch(function (err) {
                toast('<strong>Erro ao buscar pedidos:</strong><br><small>' + htmlEscape(err.message) + '</small>');
            })
            .finally(function () { setLoading(btnBuscar, false); });
    });

    // ── Marcar/desmarcar todos ──────────────────────────────────
    chkTodos.addEventListener('change', function () {
        tabelaPedidos.querySelectorAll('.chk-pedido').forEach(function (c) {
            c.checked = chkTodos.checked;
        });
    });

    function sincronizarChkTodos() {
        const total    = tabelaPedidos.querySelectorAll('.chk-pedido').length;
        const marcados = tabelaPedidos.querySelectorAll('.chk-pedido:checked').length;
        chkTodos.checked       = marcados === total;
        chkTodos.indeterminate = marcados > 0 && marcados < total;
    }

    // ── Executar Transferência ─────────────────────────────────
    btnTransferir.addEventListener('click', function () {
        const pdvIds    = pedidosSelecionados();
        const emprDest  = selDestino.value;
        const codTpNf   = inpTipoNf.value.trim();
        const codPreven = inpPreven.value.trim();

        if (pdvIds.length === 0)       { toast('Selecione ao menos um pedido para transferir.'); return; }
        if (!emprDest)                 { toast('Selecione a Filial Destino.'); return; }
        if (!codTpNf || !codPreven)    { toast('Preencha o Tipo de NF e a Tabela de Preço.'); return; }

        if (!confirm('Confirma a transferência de ' + pdvIds.length + ' pedido(s) para a filial selecionada?')) return;

        setLoading(btnTransferir, true);
        secaoResultados.classList.add('d-none');
        tabelaResultados.innerHTML = '';

        fetchJson('pedidos-api-transferencia', {
            pdv_ids:      pdvIds,
            empr_dest_id: emprDest,
            cod_tp_nf:    codTpNf,
            cod_preven:   codPreven
        })
            .then(function (data) {
                if (data.error) { toast(data.error); return; }

                const resultados = data.resultados || [];
                resumoResultado.textContent = data.sucessos + ' ok / ' + data.erros + ' erro(s)';

                resultados.forEach(function (res) {
                    const tr = document.createElement('tr');
                    const badgeClass  = res.sucesso ? 'success' : 'danger';
                    const badgeText   = res.sucesso ? 'Sucesso' : 'Erro';
                    const numOriginal = pedidoMap[res.pdv_id_orig] || res.pdv_id_orig;
                    tr.innerHTML =
                        '<td><strong>' + numOriginal + '</strong></td>' +
                        '<td><span class="badge bg-' + badgeClass + '">' + badgeText + '</span></td>' +
                        '<td><strong>' + (res.num_pedido_dest || '-') + '</strong></td>' +
                        '<td class="text-muted small">' + htmlEscape(res.erro || '') + '</td>';
                    tabelaResultados.appendChild(tr);
                });

                secaoResultados.classList.remove('d-none');

                if (data.erros === 0) {
                    toast('Transferência concluída com sucesso!', 'success');
                } else if (data.sucessos === 0) {
                    toast('Todos os pedidos falharam na transferência.');
                } else {
                    toast(data.erros + ' pedido(s) com erro. Verifique o resultado.', 'warning');
                }
            })
            .catch(function (err) {
                toast('<strong>Erro ao executar transferência:</strong><br><small>' + htmlEscape(err.message) + '</small>');
            })
            .finally(function () { setLoading(btnTransferir, false); });
    });

    // ── Escape HTML básico ─────────────────────────────────────
    function htmlEscape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();
