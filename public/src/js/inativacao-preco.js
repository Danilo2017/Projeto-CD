(function () {
    'use strict';

    const emprId = () => parseInt(document.getElementById('hEmprId').value, 10);

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    async function get(url) {
        const res = await fetch(url);
        return res.json();
    }

    // ── FILA ──────────────────────────────────────────────────────
    async function carregarFila() {
        const tbody      = document.getElementById('tbodyFila');
        const badge      = document.getElementById('badgeFila');
        const btnProc    = document.getElementById('btnProcessar');
        const msgFila    = document.getElementById('msgFila');
        msgFila.innerHTML = '';

        try {
            const data = await get('pd-api-listar-cadastros');
            if (data.error) { tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">${data.error}</td></tr>`; return; }

            const rows = data.rows || [];
            badge.textContent = rows.length;

            const pendentes = rows.filter(r => String(r.SIT) === '1');
            btnProc.style.display = pendentes.length > 0 ? '' : 'none';

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum item na fila.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(r => {
                const pendente = String(r.SIT) === '1';
                const badge    = pendente
                    ? '<span class="badge bg-warning text-dark">Pendente</span>'
                    : '<span class="badge bg-success">Processado</span>';
                const btnExcl  = pendente
                    ? `<button class="btn btn-xs btn-danger btn-sm" onclick="removerItem(${r.ID})"><i class="bi bi-trash"></i></button>`
                    : '';
                return `<tr>
                    <td>${r.COD_ITEM ?? ''}</td>
                    <td class="text-wrap">${r.DESC_TECNICA ?? ''}</td>
                    <td>${r.MASCARA ?? ''}</td>
                    <td>${r.DT_CADASTRO ?? ''}</td>
                    <td>${badge}</td>
                    <td class="text-center">${btnExcl}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">Erro: ${e.message}</td></tr>`;
        }
    }

    window.removerItem = async function (id) {
        if (!confirm('Remover este item da fila?')) return;
        try {
            const data = await post('pd-api-excluir-item', { empr_id: emprId(), id });
            if (data.error) { alert(data.error); return; }
            carregarFila();
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
                    data-cod="${r.COD_ITEM}" data-id="${r.TMASC_ITEM_ID}"
                    data-desc="${(r.DESC_TECNICA||'').replace(/"/g,'&quot;')}"
                    data-masc="${(r.MASCARA||'').replace(/"/g,'&quot;')}"></td>
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

    // selecionar todos
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

    // ── CADASTRAR ─────────────────────────────────────────────────
    document.getElementById('btnCadastrar').addEventListener('click', async function () {
        const selecionados = [...document.querySelectorAll('.chk-item:checked')].map(c => ({
            cod_item:      parseInt(c.dataset.cod, 10),
            tmasc_item_id: parseInt(c.dataset.id,  10),
            desc_tecnica:  c.dataset.desc,
            mascara:       c.dataset.masc,
        }));

        if (selecionados.length === 0) { alert('Selecione ao menos um item.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cadastrando...';

        try {
            const data = await post('pd-api-cadastrar-itens', { empr_id: emprId(), itens: selecionados });

            if (data.error) { alert(data.error); return; }

            document.getElementById('msgBusca').innerHTML =
                `<span class="text-success"><i class="bi bi-check-circle"></i> ${data.message}</span>`;

            carregarFila();
        } catch (e) {
            alert('Erro: ' + e.message);
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-plus-circle"></i> Cadastrar Selecionados';
        }
    });

    // ── PROCESSAR INATIVAÇÃO ──────────────────────────────────────
    document.getElementById('btnProcessar').addEventListener('click', async function () {
        if (!confirm('Executar a inativação de preço para todos os itens pendentes?\n\nEssa ação irá setar SIT=0 e PRECO=0 nas tabelas de preço.')) return;

        const msgFila = document.getElementById('msgFila');
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';
        msgFila.innerHTML = '';

        try {
            const data = await post('pd-api-processar-inativacao', { empr_id: emprId() });

            if (data.error) {
                msgFila.innerHTML = `<div class="alert alert-danger py-2 mb-2">${data.error}</div>`;
                return;
            }

            msgFila.innerHTML = `<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle"></i> ${data.message}</div>`;
            carregarFila();

        } catch (e) {
            msgFila.innerHTML = `<div class="alert alert-danger py-2 mb-2">Erro: ${e.message}</div>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-play-fill"></i> Executar Inativação';
        }
    });

    // ── RECARREGAR ────────────────────────────────────────────────
    document.getElementById('btnRecarregar').addEventListener('click', carregarFila);

    // Carrega a fila ao abrir
    carregarFila();
})();
