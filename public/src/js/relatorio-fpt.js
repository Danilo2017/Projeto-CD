(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL     = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_CODE     = 'R.14.GEP-20';  // FPT
    const REVISAO      = 'REVISÃO-01';
    const DATA_REVISAO = '17/08/2026';    // Data do documento para auditoria

    // CON_BAS_AU → CONJUGADO; qualquer outro → ROBOTEC
    function getCategoria(ord) {
        return (ord === 'CON_BAS_AU') ? 'CONJUGADO' : 'ROBOTEC';
    }

    function fmt(v) { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }
    function fmtDecimal(v) { const n = parseFloat(v); return isNaN(n) ? '' : n.toFixed(2).replace('.', ','); }

    /* ── Renderiza relatório FPT ─────────────────── */
    function renderFpt(rows, numLote, dataLote, pillowLinear) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        let linearRobotec   = 0;
        let linearConjugado = 0;
        let totalQtde       = 0;

        let corpoTabela = '';
        for (const r of rows) {
            const qtde   = parseFloat(r.QTDE   || 0);
            const linear = parseFloat(r.LINEAR || 0);
            totalQtde += qtde;

            if (getCategoria(fmt(r.ORD)) === 'CONJUGADO') {
                linearConjugado += linear;
            } else {
                linearRobotec += linear;
            }

            corpoTabela += `
            <tr>
                <td>${fmt(r.ORD)}</td>
                <td>${fmt(r.ITEM)}</td>
                <td class="td-wrap">${fmt(r.DESCICAO)}</td>
                <td class="text-center">${fmtNum(r.QTDE)}</td>
                <td class="text-center">${fmtNum(r.ALT_FAIXA_ACABADA)}</td>
                <td class="td-wrap">${fmt(r.COD_FITILHO)}</td>
                <td class="text-center">${fmtDecimal(r.LINEAR)}</td>
                <td class="text-center">${fmtNum(r.ALT_FAIXA)}</td>
                <td class="text-center">${fmtDecimal(r.FATIA)}</td>
                <td class="text-center">${fmt(r.COD_ITEM_TECIDO)}</td>
            </tr>`;
        }

        const linPillow   = parseFloat(pillowLinear || 0);
        const linRobotec  = linearRobotec / 2;   // ROBOTEC = soma ÷ 2
        const linFpt      = linearRobotec + linearConjugado;
        const linTotal    = linRobotec + linearConjugado + linPillow;

        // Linha de subtotal dos dados FPT (QTDE + LINEAR bruto)
        corpoTabela += `
        <tr class="subtotal-row">
            <td colspan="3"></td>
            <td style="text-align:center">${totalQtde}</td>
            <td colspan="2"></td>
            <td style="text-align:center">${fmtDecimal(linFpt)}</td>
            <td colspan="3"></td>
        </tr>`;

        // Resumo por categoria
        corpoTabela += `
        <tr class="subtotal-row">
            <td colspan="6" style="text-align:right;font-weight:bold">ROBOTEC:</td>
            <td style="text-align:center">${fmtDecimal(linRobotec)}</td>
            <td colspan="3"></td>
        </tr>
        <tr class="subtotal-row">
            <td colspan="6" style="text-align:right;font-weight:bold">CONJUGADO:</td>
            <td style="text-align:center">${fmtDecimal(linearConjugado)}</td>
            <td colspan="3"></td>
        </tr>
        <tr class="subtotal-row">
            <td colspan="6" style="text-align:right;font-weight:bold">PILLOW:</td>
            <td style="text-align:center">${fmtDecimal(linPillow)}</td>
            <td colspan="3"></td>
        </tr>`;

        // Total geral
        corpoTabela += `
        <tr class="total-row">
            <td colspan="6" style="text-align:right">TOTAL:</td>
            <td style="text-align:center">${fmtDecimal(linTotal)}</td>
            <td colspan="3"></td>
        </tr>`;

        return `
<div class="pcp-section">
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
    <div style="display:flex;align-items:center;border:1px solid #000;border-top:none;font-size:8pt">
        <div style="width:110px;flex-shrink:0;border-right:1px solid #000;padding:2px 8px"></div>
        <div style="flex:1;text-align:center;padding:2px 8px;border-right:1px solid #000;font-weight:bold">${DOC_CODE}</div>
        <div style="width:170px;flex-shrink:0;text-align:right;padding:2px 8px">${REVISAO}&nbsp;&nbsp;DATA: ${DATA_REVISAO}</div>
    </div>

    <div class="pcp-section-title">
        FPT - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}
    </div>

    <div style="overflow-x:auto">
    <table class="pcp-table">
        <thead>
            <tr>
                <th>ORD</th>
                <th>CODIGO</th>
                <th>DESCRIÇÃO</th>
                <th>QTDE</th>
                <th>ALT FAIXA<br>ACABADA</th>
                <th>COD FITILHO</th>
                <th>LINEAR</th>
                <th>ALT FAIXA</th>
                <th>FATIA</th>
                <th>COD ITEM TECIDO</th>
            </tr>
        </thead>
        <tbody>${corpoTabela}</tbody>
    </table>
    </div>

    <div class="pcp-historico">
        <table>
            <thead>
                <tr><th colspan="2">Histórico de Revisões</th></tr>
                <tr><th style="width:120px">data</th><th>Alterações</th></tr>
            </thead>
            <tbody>
                ${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Abre janela de impressão ────────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Sequência de Produção — FPT</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:9pt;background:#fff}
.pcp-section{padding:10px 14px}
.pcp-report-header{display:flex;align-items:center;border:1px solid #000}
.pcp-report-header .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0}
.pcp-report-header .col-logo img{width:90px}
.pcp-report-header .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000}
.pcp-report-header .col-right{width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0}
.pcp-report-header .col-right div{margin-bottom:2px}
.pcp-revisao{border:1px solid #000;border-top:none;padding:2px 8px;font-size:8pt;text-align:right}
.pcp-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:12pt;padding:5px;margin-top:6px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table{width:100%;border-collapse:collapse;font-size:8pt}
.pcp-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:3px 4px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table td{border:1px solid #ccc;padding:2px 4px;white-space:nowrap}
.pcp-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.subtotal-row td{background:#dce6f1;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico{margin-top:10px;border:1px solid #999;font-size:8pt}
.pcp-historico table{width:100%;border-collapse:collapse}
.pcp-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico td{border:1px solid #ccc;padding:2px 6px}
@page{size:A4 landscape;margin:8mm}
@media screen{
  body{background:#6c757d;padding:24px}
  .pcp-section{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);border-radius:3px;margin-bottom:32px;padding:18px 20px}
}
@media print{
  body{background:#fff;padding:0}
  .pcp-section{page-break-after:always;box-shadow:none;border-radius:0;margin:0;padding:10px 14px}
  .pcp-section:last-child{page-break-after:avoid}
  .pcp-table td.td-wrap{white-space:normal;word-break:break-word}
}
</style>
</head><body>${conteudo}</body></html>`);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
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
                const res  = await fetch('pcp-api-relatorio-fpt', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                printArea.innerHTML = renderFpt(data.fpt_rows || [], numLote, data.data_lote || '', data.pillow_linear || 0);
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
