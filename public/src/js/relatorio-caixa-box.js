(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const REVISAO  = 'REVISÃO-02';

    function toNum(v) {
        return parseFloat(String(v).replace(/\.(?=\d{3})/g, '').replace(',', '.')) || 0;
    }

    function fmt(v) { return (v === null || v === undefined || v === '') ? '-' : v; }

    function cabecalho(hoje) {
        return `
<div class="cb-header-wrap">
    <div class="cb-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right">
            <div><strong>SETOR</strong></div>
            <div>GESTÃO DE PRODUÇÃO</div>
        </div>
    </div>
    <div class="cb-header-row2">
        <div class="col-logo2"></div>
        <div class="col-code">${REVISAO}</div>
        <div class="col-rev">DATA: ${hoje}</div>
    </div>
</div>`;
    }

    function historico() {
        return `
<div class="cb-historico">
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

    /* ─── Seção 1: Principal (BASE / CONJUGADO) ─── */
    function renderPrincipal(rows, numLote, dataLote, hoje) {
        let corpo      = '';
        let totalQtde  = 0;
        let totalCab   = 0;
        let totalMeio  = 0;

        for (const r of rows) {
            const q   = toNum(r.QTDE);
            const cab = toNum(r.CABECEIRA);
            const mei = toNum(r.MEIO);
            totalQtde += q;
            totalCab  += cab;
            totalMeio += mei;

            corpo += `
<tr>
    <td class="text-center">${fmt(r.TIPO)}</td>
    <td class="text-center">${fmt(r.COD_ITEM)}</td>
    <td class="td-wrap">${fmt(r.DESC_TECNICA)}</td>
    <td class="text-center">${q}</td>
    <td class="text-center">${cab}</td>
    <td class="text-center">${mei}</td>
</tr>`;
        }

        corpo += `
<tr class="total-row">
    <td colspan="3" style="text-align:right;padding-right:8px"></td>
    <td class="text-center">${totalQtde}</td>
    <td class="text-center">${totalCab}</td>
    <td class="text-center">${totalMeio}</td>
</tr>`;

        return `
<div class="cb-section">
    ${cabecalho(hoje)}
    <div class="cb-section-title">CAIXA BOX - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}</div>
    <div style="overflow-x:auto">
    <table class="cb-table">
        <thead>
            <tr>
                <th>TIPO</th>
                <th>ITEM</th>
                <th>DESCRIÇÃO DA CAIXA</th>
                <th>QTDE</th>
                <th>CABECEIRA</th>
                <th>MEIO</th>
            </tr>
        </thead>
        <tbody>${corpo}</tbody>
    </table>
    </div>
    ${historico()}
</div>`;
    }

    /* ─── Seção 2: Auxiliar + Cabeceira ─── */
    function renderAux(rows, numLote, dataLote, hoje) {
        let corpo     = '';
        let totalQtde = 0;
        let totalCab  = 0;
        let totalMeio = 0;

        for (const r of rows) {
            const q   = toNum(r.QTDE);
            const cab = toNum(r.CABECEIRA);
            const mei = toNum(r.MEIO);
            totalQtde += q;
            totalCab  += cab;
            totalMeio += mei;

            const descAux = (r.DESC_P_AUXILIAR && r.DESC_P_AUXILIAR !== '') ? r.DESC_P_AUXILIAR : '-';

            corpo += `
<tr>
    <td class="text-center">${fmt(r.TIPO)}</td>
    <td class="text-center">${fmt(r.COD_ITEM_C_AUX)}</td>
    <td class="td-wrap">${fmt(r.DESC_C_AUXILIAR)}</td>
    <td class="text-center">${q}</td>
    <td class="td-wrap">${descAux}</td>
    <td class="text-center">${cab}</td>
    <td class="text-center">${mei}</td>
</tr>`;
        }

        corpo += `
<tr class="total-row">
    <td colspan="3" style="text-align:right;padding-right:8px"></td>
    <td class="text-center">${totalQtde}</td>
    <td></td>
    <td class="text-center">${totalCab}</td>
    <td class="text-center">${totalMeio}</td>
</tr>`;

        return `
<div class="cb-section">
    ${cabecalho(hoje)}
    <div class="cb-section-title">CAIXA BOX AUXILIAR E CABECEIRA - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}</div>
    <div style="overflow-x:auto">
    <table class="cb-table">
        <thead>
            <tr>
                <th>TIPO</th>
                <th>ITEM</th>
                <th>DESCRIÇÃO DA CAIXA</th>
                <th>QTDE</th>
                <th>DESCRIÇÃO DA CAIXA AUXILIAR</th>
                <th>CABECEIRA</th>
                <th>MEIO</th>
            </tr>
        </thead>
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
<title>Caixa Box — Sequência de Produção</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.cb-section{padding:8px 12px}
.cb-header-wrap{border:1px solid #000}
.cb-header-row1{display:flex;align-items:stretch}
.cb-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.cb-header-row1 .col-logo img{width:85px}
.cb-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.cb-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.cb-header-row2{display:flex;align-items:stretch;border-top:1px solid #000}
.cb-header-row2 .col-logo2{width:110px;border-right:1px solid #000;flex-shrink:0}
.cb-header-row2 .col-code{flex:1;text-align:center;font-size:7.5pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.cb-header-row2 .col-rev{width:170px;font-size:7.5pt;padding:2px 6px;flex-shrink:0}
.cb-section-title{background:#1f3864;color:#fff;text-align:center;font-weight:bold;font-size:13pt;padding:5px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.cb-table{width:100%;border-collapse:collapse;font-size:7pt}
.cb-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.cb-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.cb-table td.td-wrap{white-space:normal;word-break:break-word}
.cb-table tr.total-row td{background:#d9d9d9;font-weight:bold;border-top:2px solid #999;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.cb-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.cb-historico table{width:100%;border-collapse:collapse}
.cb-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.cb-historico td{border:1px solid #ccc;padding:2px 4px}
.text-center{text-align:center}
@page{size:A4 landscape;margin:6mm}
@media print{
  body{background:#fff;padding:0}
  .cb-section{page-break-after:always;padding:8px 12px}
  .cb-section:last-child{page-break-after:avoid}
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
                const res  = await fetch('pcp-api-relatorio-caixa-box', {
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
                const numL = parseInt(numLote);
                const dt   = data.data_lote || '';

                let html = '';
                if (data.principal_rows && data.principal_rows.length > 0) {
                    html += renderPrincipal(data.principal_rows, numL, dt, hoje);
                }
                if (data.aux_rows && data.aux_rows.length > 0) {
                    html += renderAux(data.aux_rows, numL, dt, hoje);
                }
                if (!html) {
                    html = '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
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
