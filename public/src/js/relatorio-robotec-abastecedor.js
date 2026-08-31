(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL     = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_CODE     = 'R.14.GEP-07';   // Número do documento para auditoria
    const REVISAO      = 'REVISÃO-01';
    const DATA_REVISAO = '17/08/2026';    // Data do documento para auditoria

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v)    { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }

    /* ── Renderiza Robotec Abastecedor ─────────── */
    function renderRobotecAbastecedor(rows, numLote, dataLote) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        let totalGeral = 0;
        let subtotal   = 0;
        let ordAtual   = null;
        let corpo      = '';
        const buf      = [];

        function flushGrupo() {
            if (buf.length === 0) return;
            for (const linha of buf) corpo += linha;
            corpo += `
            <tr class="subtotal-row">
                <td colspan="8" style="text-align:right;font-weight:bold">SUBTOTAL ${ordAtual}:</td>
                <td style="text-align:center;font-weight:bold">${subtotal}</td>
                <td colspan="6"></td>
            </tr>
            <tr class="separator-row"><td colspan="15"></td></tr>`;
            buf.length = 0;
            subtotal   = 0;
        }

        for (const r of rows) {
            const ord  = fmt(r.ORD);
            const qtde = parseFloat(r.QTDE || 0);

            if (ord !== ordAtual) {
                flushGrupo();
                ordAtual = ord;
            }

            subtotal   += qtde;
            totalGeral += qtde;

            buf.push(`
            <tr>
                <td>${fmt(r.ORD)}</td>
                <td class="text-center">${fmt(r.NUM_ORDEM)}</td>
                <td class="text-center">${fmt(r.ITEM)}</td>
                <td class="text-center">${fmt(r.ID)}</td>
                <td class="td-wrap">${fmt(r.DESCICAO)}</td>
                <td class="td-wrap">${fmt(r.MASCARA)}</td>
                <td class="text-center">${fmtNum(r.LARGURA_COLCHAO)}</td>
                <td class="text-center">${fmtNum(r.MOLA)}</td>
                <td class="text-center">${fmtNum(r.QTDE)}</td>
                <td class="text-center">${fmtNum(r.ARCO)}</td>
                <td class="text-center">${fmtNum(r.EPS)}</td>
                <td class="td-wrap">${fmt(r.MANTA)}</td>
                <td class="td-wrap">${fmt(r.FAIXA)}</td>
                <td class="td-wrap">${fmt(r.TAMPO)}</td>
                <td class="text-center">${fmt(r.CABECOTE)}</td>
            </tr>`);
        }
        flushGrupo();

        corpo += `
        <tr class="total-row">
            <td colspan="8" style="text-align:right">QTDE TOTAL:</td>
            <td style="text-align:center">${totalGeral}</td>
            <td colspan="6"></td>
        </tr>`;

        return `
<div class="pcp-section">
    <div class="pcp-report-header">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right"><div><strong>SETOR</strong></div><div>GESTÃO DE PRODUÇÃO</div></div>
    </div>
    <div style="display:flex;align-items:center;border:1px solid #000;border-top:none;font-size:8pt">
        <div style="width:110px;flex-shrink:0;border-right:1px solid #000;padding:2px 8px"></div>
        <div style="flex:1;text-align:center;padding:2px 8px;border-right:1px solid #000;font-weight:bold">${DOC_CODE}</div>
        <div style="width:170px;flex-shrink:0;text-align:right;padding:2px 8px">${REVISAO}&nbsp;&nbsp;DATA: ${DATA_REVISAO}</div>
    </div>
    <div class="pcp-section-title">
        ROBOTEC ABASTECEDOR - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>
    <div style="overflow-x:auto">
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
                <th>MOLA</th>
                <th>QTD</th>
                <th>ARCO</th>
                <th>EPS</th>
                <th>MANTA</th>
                <th>FAIXA</th>
                <th>TAMPO</th>
                <th>CABEÇOTE</th>
            </tr>
        </thead>
        <tbody>${corpo}</tbody>
    </table>
    </div>
    <div class="pcp-historico">
        <table>
            <thead>
                <tr><th colspan="2">Histórico de Revisões</th></tr>
                <tr><th style="width:120px">data</th><th>Alterações</th></tr>
            </thead>
            <tbody>${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}</tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Janela de impressão ────────────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Sequência de Produção — Robotec Abastecedor</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:8pt;background:#fff}
.pcp-section{padding:10px 14px}
.pcp-report-header{display:flex;align-items:center;border:1px solid #000}
.pcp-report-header .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0}
.pcp-report-header .col-logo img{width:90px}
.pcp-report-header .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000}
.pcp-report-header .col-right{width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0}
.pcp-report-header .col-right div{margin-bottom:2px}
.pcp-revisao{display:flex;align-items:center;border:1px solid #000;border-top:none;font-size:8pt}
.pcp-revisao .col-logo2{width:110px;flex-shrink:0;border-right:1px solid #000;padding:2px 8px}
.pcp-revisao .col-code{flex:1;text-align:center;padding:2px 8px;border-right:1px solid #000;font-weight:bold}
.pcp-revisao .col-rev{width:170px;flex-shrink:0;text-align:right;padding:2px 8px}
.pcp-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:12pt;padding:5px;margin-top:6px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table{width:100%;border-collapse:collapse;font-size:7pt}
.pcp-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.pcp-table td.td-wrap{white-space:pre-wrap;word-break:break-word}
.pcp-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.subtotal-row td{background:#dce6f1;font-weight:bold;border:none;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.separator-row td{border:none;padding:2px;background:#fff}
.pcp-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico{margin-top:10px;border:1px solid #999;font-size:8pt}
.pcp-historico table{width:100%;border-collapse:collapse}
.pcp-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico td{border:1px solid #ccc;padding:2px 6px}
.text-center{text-align:center}
@page{size:A4 landscape;margin:6mm}
@media print{
  .pcp-section{page-break-after:always}
  .pcp-section:last-child{page-break-after:avoid}
}
</style>
</head><body>${conteudo}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
    }

    /* ── Init ──────────────────────────────────── */
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
                const res  = await fetch('pcp-api-relatorio-robotec-abastecedor', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const html = renderRobotecAbastecedor(data.rows || [], numLote, data.data_lote || '');

                if (!html.trim()) {
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
