(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_CODE = 'R.14.GEP-08';
    const REVISAO  = 'REVISÃO-02';

    function fmt(v)  { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtQ(v) { const n = parseFloat(v); return isNaN(n) ? '' : n; }

    function cabecalho(hoje) {
        return `
<div class="tap-header-wrap">
    <div class="tap-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div class="tap-header-row2">
        <div class="col-logo2"></div>
        <div class="col-code">${DOC_CODE}</div>
        <div class="col-rev">${REVISAO}&nbsp;&nbsp;DATA: ${hoje}</div>
    </div>
</div>`;
    }

    function historico() {
        return `
<div class="tap-historico">
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

    const THEAD = `
<thead>
    <tr>
        <th>ORD</th>
        <th>ORDEM</th>
        <th>ITEM</th>
        <th>DESCRIÇÃO</th>
        <th>ID</th>
        <th>MASCARA</th>
        <th>QTDE</th>
        <th>LARG</th>
    </tr>
</thead>`;

    function renderTravePeze(rows, numLote, dataLote, hoje) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        let corpo      = '';
        let grandTotal = 0;

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];
            const q = parseFloat(r.QTDE) || 0;
            grandTotal += q;

            corpo += `
<tr>
    <td class="text-center">${fmt(r.ORD)}</td>
    <td class="text-center">${fmt(r.NUM_ORDEM)}</td>
    <td class="text-center">${fmt(r.ITEM)}</td>
    <td class="td-wrap">${fmt(r.DESCICAO)}</td>
    <td class="text-center">${fmt(r.ID_MASCARA)}</td>
    <td class="td-wrap">${fmt(r.MASCARA)}</td>
    <td class="text-center">${fmtQ(r.QTDE)}</td>
    <td class="text-center">${fmt(r.LARGURA_COLCHAO)}</td>
</tr>`;
        }

        corpo += `
<tr class="total-row">
    <td colspan="6" style="text-align:right;padding-right:8px">QTDE TOTAL:</td>
    <td style="text-align:center">${grandTotal}</td>
    <td></td>
</tr>`;

        return `
<div class="tap-section">
    ${cabecalho(hoje)}
    <div class="tap-section-title">
        TRAVE PEZÊ - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="tap-table">
        ${THEAD}
        <tbody>${corpo}</tbody>
    </table>
    </div>
    ${historico()}
</div>`;
    }

    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Trave Pezê — Sequência de Produção</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.tap-section{padding:8px 12px}
.tap-header-wrap{border:1px solid #000}
.tap-header-row1{display:flex;align-items:stretch}
.tap-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.tap-header-row1 .col-logo img{width:85px}
.tap-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.tap-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.tap-header-row2{display:flex;align-items:stretch;border-top:1px solid #000}
.tap-header-row2 .col-logo2{width:110px;border-right:1px solid #000;flex-shrink:0}
.tap-header-row2 .col-code{flex:1;text-align:center;font-size:7.5pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.tap-header-row2 .col-rev{width:170px;font-size:7.5pt;padding:2px 6px;flex-shrink:0}
.tap-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:11pt;padding:4px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-table{width:100%;border-collapse:collapse;font-size:7pt}
.tap-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.tap-table td.td-wrap{white-space:normal;word-break:break-word}
.tap-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.tap-historico table{width:100%;border-collapse:collapse}
.tap-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-historico td{border:1px solid #ccc;padding:2px 4px}
.text-center{text-align:center}
@page{size:A4 landscape;margin:6mm}
@media print{
  body{background:#fff;padding:0}
  .tap-section{page-break-after:always;padding:8px 12px}
  .tap-section:last-child{page-break-after:avoid}
}
</style>
</head><body>${conteudo}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
    }

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
                const res  = await fetch('pcp-api-relatorio-trave-peze', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const hoje = new Date().toLocaleDateString('pt-BR');
                const html = renderTravePeze(data.travepeze_rows || [], numLote, data.data_lote || '', hoje);

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
