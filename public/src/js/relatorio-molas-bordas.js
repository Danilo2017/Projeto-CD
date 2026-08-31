(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/24', alteracao: 'Elaboração Inicial' },
        { data: '13/08/25', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_CODE     = 'R.14.GEP-13';
    const REVISAO      = 'REVISÃO-01';
    const DATA_REVISAO = '17/08/2026';    // Data do documento para auditoria

    function toNum(v) {
        return parseFloat(String(v).replace(/\.(?=\d{3})/g, '').replace(',', '.')) || 0;
    }

    function fmt(v) { return (v === null || v === undefined || v === '') ? '' : v; }

    function cabecalho() {
        return `
<div class="mb-header-wrap">
    <div class="mb-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;border-top:1px solid #000;font-size:7.5pt">
        <div style="width:110px;flex-shrink:0;border-right:1px solid #000;padding:2px 6px"></div>
        <div style="flex:1;text-align:center;padding:2px 6px;border-right:1px solid #000;font-weight:bold">${DOC_CODE}</div>
        <div style="width:170px;flex-shrink:0;text-align:right;padding:2px 6px">${REVISAO}&nbsp;&nbsp;DATA: ${DATA_REVISAO}</div>
    </div>
</div>`;
    }

    function historico() {
        return `
<div class="mb-historico">
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
        <th>LARGURA COLCHÃO</th>
        <th>CÓD ITEM MOLA</th>
        <th>MOLA</th>
        <th>QUANTIDADE MOLA</th>
    </tr>
</thead>`;

    function renderMolasBordas(rows, numLote, dataLote) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        let corpo      = '';
        let grandTotal = 0;

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];
            const q = toNum(r.QTDE_MOLA);
            grandTotal += q;

            corpo += `
<tr>
    <td class="text-center">${fmt(r.LARGURA_COLCHAO)}</td>
    <td class="td-wrap">${fmt(r.COD_ITEM_MOLA)}</td>
    <td class="text-center">${fmt(r.MOLA)}</td>
    <td class="text-center">${q}</td>
</tr>`;
        }

        corpo += `
<tr class="total-row">
    <td colspan="3" style="text-align:right;padding-right:8px"></td>
    <td style="text-align:center">${grandTotal}</td>
</tr>`;

        return `
<div class="mb-section">
    ${cabecalho()}
    <div class="mb-section-title">
        MOLAS E BORDAS - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
    <table class="mb-table">
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
<title>Molas e Bordas — Sequência de Produção</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.mb-section{padding:8px 12px}
.mb-header-wrap{border:1px solid #000}
.mb-header-row1{display:flex;align-items:stretch}
.mb-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.mb-header-row1 .col-logo img{width:85px}
.mb-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.mb-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.mb-header-row2{display:flex;align-items:stretch;border-top:1px solid #000}
.mb-header-row2 .col-logo2{width:110px;border-right:1px solid #000;flex-shrink:0}
.mb-header-row2 .col-code{flex:1;text-align:center;font-size:7.5pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.mb-header-row2 .col-rev{width:170px;font-size:7.5pt;padding:2px 6px;flex-shrink:0}
.mb-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:11pt;padding:4px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.mb-table{width:100%;border-collapse:collapse;font-size:7pt}
.mb-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.mb-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.mb-table td.td-wrap{white-space:normal;word-break:break-word}
.mb-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.mb-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.mb-historico table{width:100%;border-collapse:collapse}
.mb-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.mb-historico td{border:1px solid #ccc;padding:2px 4px}
.text-center{text-align:center}
@page{size:A4 landscape;margin:6mm}
@media print{
  body{background:#fff;padding:0}
  .mb-section{page-break-after:always;padding:8px 12px}
  .mb-section:last-child{page-break-after:avoid}
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
                const res  = await fetch('pcp-api-relatorio-molas-bordas', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const html = renderMolasBordas(data.molas_rows || [], numLote, data.data_lote || '');

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
