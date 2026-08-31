(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL  = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_CODE  = 'R.14.GEP-02';
    const REVISAO   = 'REVISÃO-01  ';

    /* ── ORD → CSS class e rótulo ───────────────────── */
    const ORD_CLASS = {
        'LISO':    'ord-LISO',
        'BORDADO': 'ord-BORDADO',
        'MISTO':   'ord-MISTO',
        'MOLA':    'ord-MOLA',
        'MESA':    'ord-MESA',
        'COSTURA': 'ord-COSTURA',
        'MESA_PL': 'ord-MESA_PL',
    };

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v)  { return (v === null || v === undefined || v === '') ? '' : v; }
    function num(v)  { const n = parseInt(v); return isNaN(n) ? '' : n; }
    function fmtQ(v) { const n = parseFloat(v); return isNaN(n) ? '' : n; }

    /* ── Cabeçalho do relatório ─────────────────────── */
    function cabecalho(hoje) {
        return `
<div class="rbt-header-wrap">
    <div class="rbt-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div class="rbt-header-row2">
        <div class="col-logo2"></div>
        <div class="col-code">${DOC_CODE}</div>
        <div class="col-rev">${REVISAO}&nbsp;&nbsp;DATA: ${hoje}</div>
    </div>
</div>`;
    }

    /* ── Rodapé histórico ───────────────────────────── */
    function historico() {
        return `
<div class="rbt-historico">
    <table>
        <thead>
            <tr><th>Revisão</th><th>data</th><th>Alterações</th></tr>
        </thead>
        <tbody>
            ${HISTORICO.map((h, i) => `<tr><td style="text-align:center">${i+1}</td><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
        </tbody>
    </table>
</div>`;
    }

    /* ── Linha de dados ─────────────────────────────── */
    function linha(r) {
        const cls = ORD_CLASS[fmt(r.ORD)] || '';
        return `
<tr class="${cls}">
    <td class="text-center">${fmt(r.ORD)}</td>
    <td class="text-center">${fmt(r.NUM_ORDEM)}</td>
    <td class="text-center">${fmt(r.ITEM)}</td>
    <td class="td-wrap">${fmt(r.DESCICAO)}</td>
    <td class="td-wrap">${fmt(r.MASCARA)}</td>
    <td class="text-center">${fmtQ(r.QTDE)}</td>
    <td class="text-center">${num(r.LARGURA_COLCHAO)}</td>
    <td class="text-center">${fmt(r.MOLA)}</td>
    <td class="text-center">${fmt(r.DENSIDADE)}</td>
    <td class="text-center">${fmt(r.EPS)}</td>
    <td class="text-center">${fmt(r.CABECOTE)}</td>
</tr>`;
    }

    /* ── Linha de subtotal (quebra de largura) ──────── */
    function subtotal(qtde, colspan) {
        return `
<tr class="subtotal-row">
    <td colspan="${colspan || 10}"></td>
    <td>${qtde}</td>
</tr>`;
    }

    /* ── Linha total final ──────────────────────────── */
    function totalFinal(qtde) {
        return `
<tr class="total-row">
    <td colspan="10" style="text-align:right;padding-right:8px">QTDE TOTAL:</td>
    <td style="text-align:center">${qtde}</td>
</tr>`;
    }

    /* ── Cabeçalho da tabela ────────────────────────── */
    const THEAD = `
<thead>
    <tr>
        <th>ORD</th>
        <th>ORDEM</th>
        <th>ITEM</th>
        <th>DESCRIÇÃO</th>
        <th>MASCARA</th>
        <th>QTD</th>
        <th>LAR</th>
        <th>MOL</th>
        <th>DENSIDADE</th>
        <th>EP</th>
        <th>CABEÇOTE</th>
    </tr>
</thead>`;

    /* ── Renderiza LINHA DE MONTAGEM ────────────────── */
    function renderLinha(rows, numLote, dataLote, hoje) {
        if (!rows || rows.length === 0) return '';

        let corpo    = '';
        let grandTotal = 0;
        let subQtde  = 0;
        let curLar   = null;
        let curOrd   = null;

        for (let i = 0; i < rows.length; i++) {
            const r   = rows[i];
            const lar = r.LARGURA_COLCHAO;
            const ord = r.ORD;
            const q   = parseFloat(r.QTDE) || 0;

            // Quebra de grupo: largura ou ORD mudou
            if (curLar !== null && (lar !== curLar || ord !== curOrd)) {
                corpo += subtotal(subQtde);
                subQtde = 0;
                // Espaço visual entre ORDs diferentes
                if (ord !== curOrd) {
                    corpo += `<tr><td colspan="11" style="padding:0;border:none;height:4px;background:#fff"></td></tr>`;
                }
            }

            curLar = lar;
            curOrd = ord;
            subQtde  += q;
            grandTotal += q;
            corpo += linha(r);
        }
        // Último subtotal
        corpo += subtotal(subQtde);
        corpo += totalFinal(grandTotal);

        return `
<div class="rbt-section">
    ${cabecalho(hoje)}
    <div class="rbt-section-title">
        ROBOTEC LINHA DE MONTAGEM - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="rbt-table">
        ${THEAD}
        <tbody>${corpo}</tbody>
    </table>
    </div>
    ${historico()}
</div>`;
    }

    /* ── Renderiza MESA ─────────────────────────────── */
    function renderMesa(rows, numLote, dataLote, hoje) {
        if (!rows || rows.length === 0) return '';

        let corpo    = '';
        let grandTotal = 0;
        let subQtde  = 0;
        let curLar   = null;

        for (let i = 0; i < rows.length; i++) {
            const r   = rows[i];
            const lar = r.LARGURA_COLCHAO;
            const q   = parseFloat(r.QTDE) || 0;

            if (curLar !== null && lar !== curLar) {
                corpo += subtotal(subQtde);
                subQtde = 0;
            }

            curLar = lar;
            subQtde  += q;
            grandTotal += q;
            corpo += linha(r);
        }
        corpo += subtotal(subQtde);
        corpo += totalFinal(grandTotal);

        return `
<div class="rbt-section">
    ${cabecalho(hoje)}
    <div class="rbt-section-title">
        ROBOTEC MESA - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="rbt-table">
        ${THEAD}
        <tbody>${corpo}</tbody>
    </table>
    </div>
    ${historico()}
</div>`;
    }

    /* ── Janela de impressão ─────────────────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Robotec — Sequência de Produção</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.rbt-section{padding:8px 12px}
.rbt-header-wrap{border:1px solid #000}
.rbt-header-row1{display:flex;align-items:stretch}
.rbt-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.rbt-header-row1 .col-logo img{width:85px}
.rbt-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.rbt-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.rbt-header-row2{display:flex;align-items:stretch;border-top:1px solid #000}
.rbt-header-row2 .col-logo2{width:110px;border-right:1px solid #000;flex-shrink:0}
.rbt-header-row2 .col-code{flex:1;text-align:center;font-size:7.5pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.rbt-header-row2 .col-rev{width:170px;font-size:7.5pt;padding:2px 6px;flex-shrink:0}
.rbt-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:11pt;padding:4px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rbt-table{width:100%;border-collapse:collapse;font-size:7pt}
.rbt-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rbt-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.rbt-table td.td-wrap{white-space:normal;word-break:break-word}
.rbt-table tr.subtotal-row td{background:#d9d9d9;font-weight:bold;text-align:right;border-top:2px solid #999;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rbt-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-LISO    {background:#f0f8ff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-BORDADO {background:#fff9c4;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-MISTO   {background:#e3f2fd;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-MOLA    {background:#e8f5e9;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-MESA    {background:#ede7f6;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-COSTURA {background:#fce4ec;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.ord-MESA_PL {background:#e8eef8;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rbt-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.rbt-historico table{width:100%;border-collapse:collapse}
.rbt-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rbt-historico td{border:1px solid #ccc;padding:2px 4px}
.text-center{text-align:center}
.text-right{text-align:right}
@page{size:A4 landscape;margin:6mm}
@media print{
  .rbt-section{page-break-after:always;padding:8px 12px}
  .rbt-section:last-child{page-break-after:avoid}
}
</style>
</head><body>${conteudo}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
    }

    /* ── Init ──────────────────────────────────────── */
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
                const res  = await fetch('pcp-api-relatorio-robotec', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const hoje      = new Date().toLocaleDateString('pt-BR');
                const linhaRows = data.linha_rows || [];
                const mesaRows  = data.mesa_rows  || [];
                const dataLote  = data.data_lote  || '';

                let html = '';
                if (linhaRows.length > 0) {
                    html += renderLinha(linhaRows, numLote, dataLote, hoje);
                }
                if (mesaRows.length > 0) {
                    html += renderMesa(mesaRows, numLote, dataLote, hoje);
                }

                if (!html) {
                    statusMsg.innerHTML = '<span class="text-warning">Nenhum dado encontrado para este lote.</span>';
                    return;
                }

                printArea.innerHTML = html;
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
            abrirJanelaImpressao(printArea.innerHTML);
        });
    });
})();
