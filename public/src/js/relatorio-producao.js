(function () {
    'use strict';

    const SECOES_CONFIG = {
        conjugado:  'CAIXOTE CONJUGADO',
        mesa:       'CAIXOTE MESA',
        sem_pillow: 'CAIXOTE SEM PILLOW',
        com_pillow: 'CAIXOTE COM PILLOW',
    };

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v) { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }

    /* ── Renderiza uma seção ────────────────────── */
    function renderSecao(chave, titulo, rows, numLote) {
        if (!rows || rows.length === 0) return '';

        const hoje = dataHoje();

        // Agrupar por LARGURA_COLCHAO para subtotais
        const grupos = {};
        for (const r of rows) {
            const lar = fmt(r.LARGURA_COLCHAO) || '—';
            if (!grupos[lar]) grupos[lar] = [];
            grupos[lar].push(r);
        }

        let corpoTabela = '';
        let totalGeral  = 0;

        for (const [lar, itens] of Object.entries(grupos)) {
            let subtotal = 0;
            for (const r of itens) {
                const qtde = parseFloat(r.QTDE || 0);
                subtotal += qtde;
                corpoTabela += `
                <tr>
                    <td>${fmt(r.ORD)}</td>
                    <td>${fmt(r.NUM_ORDEM)}</td>
                    <td>${fmt(r.ITEM)}</td>
                    <td>${fmt(r.ID)}</td>
                    <td style="max-width:200px;white-space:normal">${fmt(r.DESCICAO)}</td>
                    <td style="max-width:220px;white-space:normal">${fmt(r.MASCARA)}</td>
                    <td class="text-center">${fmtNum(r.LARGURA_COLCHAO)}</td>
                    <td class="text-center">${fmtNum(r.QTDE)}</td>
                    <td class="text-center">${fmtNum(r.ALT_EPS)}</td>
                    <td class="text-center">${fmtNum(r.ALT_MOLA)}</td>
                    <td>${fmt(r.BORDA)}</td>
                    <td>${fmt(r.TNT_OU_FELTRO)}</td>
                    <td class="text-center">${fmtNum(r.PILLOW)}</td>
                    <td class="text-center">${fmtNum(r.ALT)}</td>
                    <td>${fmt(r.TECIDO)}</td>
                </tr>`;
            }
            totalGeral += subtotal;
            // Linha de subtotal por grupo de largura
            corpoTabela += `
            <tr class="subtotal-row">
                <td colspan="14"></td>
                <td style="text-align:right;padding-right:6px">${subtotal}</td>
            </tr>`;
        }

        // Linha de total geral
        corpoTabela += `
        <tr class="total-row">
            <td colspan="12"></td>
            <td colspan="2" style="text-align:right;padding-right:6px">QTDE TOTAL:</td>
            <td style="text-align:center">${totalGeral}</td>
        </tr>`;

        return `
<div class="pcp-section">
    <!-- Cabeçalho do relatório -->
    <div class="pcp-report-header">
        <div class="col-logo">
            <img src="${LOGO_URL}" alt="Gazin">
        </div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div class="pcp-revisao">REVISÃO-01 &nbsp;&nbsp; DATA: ${hoje}</div>

    <!-- Título da seção -->
    <div class="pcp-section-title">
        ${titulo} — LOTE ${numLote}
    </div>

    <!-- Tabela de dados -->
    <table class="pcp-table">
        <thead>
            <tr>
                <th>ORD</th>
                <th>ORDEM</th>
                <th>COD</th>
                <th>ID</th>
                <th>DESCRIÇÃO</th>
                <th>MÁSCARA</th>
                <th>LAR</th>
                <th>QTDE</th>
                <th>ALT EPS</th>
                <th>ALT MOLA</th>
                <th>BORDA</th>
                <th>TNT OU FELTRO</th>
                <th>PILLOW</th>
                <th>AL</th>
                <th>TECIDO</th>
            </tr>
        </thead>
        <tbody>${corpoTabela}</tbody>
    </table>

    <!-- Histórico de revisões -->
    <div class="pcp-historico">
        <table>
            <thead>
                <tr>
                    <th colspan="2">Histórico de Revisões</th>
                </tr>
                <tr>
                    <th style="width:120px">data</th>
                    <th>Alterações</th>
                </tr>
            </thead>
            <tbody>
                ${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Renderiza todas as 4 seções ────────────── */
    function renderRelatorio(secoes, numLote) {
        let html = '';
        for (const [chave, titulo] of Object.entries(SECOES_CONFIG)) {
            const rows = secoes[chave] || [];
            if (rows.length > 0) {
                html += renderSecao(chave, titulo, rows, numLote);
            }
        }
        return html || '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
    }

    /* ── Init ───────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        const btnGerar    = document.getElementById('btnGerar');
        const btnImprimir = document.getElementById('btnImprimir');
        const printArea   = document.getElementById('printArea');
        const statusMsg   = document.getElementById('statusMsg');

        btnGerar.addEventListener('click', async function () {
            const numLote = document.getElementById('inputLote').value.trim();

            if (!numLote || parseInt(numLote) <= 0) { alert('Informe o número do lote.'); return; }

            btnGerar.disabled = true;
            btnGerar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
            btnImprimir.style.display = 'none';
            statusMsg.innerHTML = '';
            printArea.className = '';
            printArea.innerHTML = '';

            try {
                const res  = await fetch('pcp-api-relatorio-producao', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                printArea.innerHTML = renderRelatorio(data.secoes, numLote);
                printArea.className = 'visible';
                btnImprimir.style.display = '';

            } catch (e) {
                statusMsg.innerHTML = `<span class="text-danger">Erro ao buscar dados: ${e.message}</span>`;
            } finally {
                btnGerar.disabled = false;
                btnGerar.innerHTML = '<i class="bi bi-search"></i> Gerar';
            }
        });

        btnImprimir.addEventListener('click', function () {
            window.print();
        });
    });
})();
