(function () {
    'use strict';

    const emprId = () => parseInt(document.getElementById('hEmprId').value, 10);

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

    // ── HISTÓRICO ─────────────────────────────────────────────────
    async function carregarHistorico() {
        const tbody   = document.getElementById('tbodyFila');
        const badge   = document.getElementById('badgeFila');
        const msgFila = document.getElementById('msgFila');
        msgFila.innerHTML = '';

        try {
            const data = await get('pd-api-listar-cadastros');
            if (data.error) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Histórico indisponível.</td></tr>';
                return;
            }

            const rows = data.rows || [];
            badge.textContent = rows.length;

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum registro ainda.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(r => {
                const validado = String(r.SIT) === '0';
                const statusBadge = validado
                    ? '<span class="badge bg-success">Validado pelo Job</span>'
                    : '<span class="badge bg-warning text-dark">Aguardando Job</span>';
                const btnExcl = `<button class="btn btn-xs btn-sm btn-outline-danger" onclick="removerItem(${r.ID})" title="Remover"><i class="bi bi-trash"></i></button>`;
                return `<tr>
                    <td>${r.COD_ITEM ?? ''}</td>
                    <td class="text-wrap">${r.DESC_TECNICA ?? ''}</td>
                    <td>${r.MASCARA ?? ''}</td>
                    <td>${r.DT_CADASTRO ?? ''}</td>
                    <td>${statusBadge}</td>
                    <td class="text-center">${btnExcl}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Histórico indisponível.</td></tr>';
        }
    }

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

    // ── BUSCAR ────────────────────────────────────────────────────
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

    // ── INATIVAR (ao cadastrar, inativa imediatamente) ────────────
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

            const tipo = data.erros && data.erros.length > 0 ? 'warning' : 'success';
            const icone = tipo === 'success' ? 'check-circle' : 'exclamation-triangle';
            msgEl.innerHTML = `<span class="text-${tipo}"><i class="bi bi-${icone}"></i> ${data.message}</span>`;

            // Desmarca todos
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

    carregarHistorico();
})();
