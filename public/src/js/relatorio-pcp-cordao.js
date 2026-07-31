(function () {
    'use strict';

    const HISTORICO = [
        { data: '27/07/2026', alteracao: 'Elaboração Inicial' },
        { data: '31/07/2026', alteracao: 'Adicionada seção de detalhe por ordem' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v)  { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtN(v) { const n = parseFloat(v); return isNaN(n) ? '' : n.toLocaleString('pt-BR'); }

    function cabecalho(hoje) {
        return `
<div class="pm-header-wrap">
    <div class="pm-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO CORDÃO MOLAS — PCP MOLAS</div>
        <div class="col-right"><div><strong>SETOR</strong></div><div>GESTÃO DE PRODUÇÃO</div></div>
    </div>
    <div class="pm-revisao">REVISÃO-02 &nbsp;&nbsp; DATA: ${hoje}</div>
</div>`;
    }

    function historico() {
        return `
<div class="pm-historico">
    <table>
        <thead><tr><th colspan="2">Histórico de Revisões</th></tr>
        <tr><th style="width:120px">Data</th><th>Alterações</th></tr></thead>
        <tbody>${HISTORICO.map(h => `<tr><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}</tbody>
    </table>
</div>`;
    }

    function renderRelatorio(rows, numLote, hoje) {
        if (!rows || rows.length === 0) {
            return '<p class="text-muted text-center py-4">Nenhum dado encontrado para este lote.</p>';
        }

        const titulo = `CORDÃO DE MOLAS — LOTE ${numLote}`;

        // ── SEÇÃO 1: DETALHE POR ORDEM ──────────────────────────────
        let corpoDetalhe = '';
        for (const r of rows) {
            corpoDetalhe += `
<tr>
    <td style="text-align:center">${fmt(r.NUM_ORDEM)}</td>
    <td style="text-align:center">${fmt(r.DT_INICIAL)}</td>
    <td>${fmt(r.COD_ITEM)}</td>
    <td class="td-wrap">${fmt(r.DESC_TECNICA)}</td>
    <td class="td-wrap">${fmt(r.MASCARA)}</td>
    <td style="text-align:center">${fmtN(r.QTDE_OF)}</td>
    <td>${fmt(r.COD_ITEM_CORDAO)}</td>
    <td class="td-wrap">${fmt(r.DESC_CORDAO)}</td>
    <td class="td-wrap">${fmt(r.MASCARA_CORDAO)}</td>
    <td style="text-align:center;font-weight:bold">${fmtN(r.TOTAL_MOLINHA)}</td>
</tr>`;
        }

        // ── SEÇÃO 2: AGRUPADO POR CORDÃO ────────────────────────────
        const mapaGrupos = new Map();
        for (const r of rows) {
            const chave = `${r.COD_ITEM_CORDAO}||${r.MASCARA_CORDAO}`;
            if (!mapaGrupos.has(chave)) {
                mapaGrupos.set(chave, {
                    COD_ITEM_CORDAO: r.COD_ITEM_CORDAO,
                    DESC_CORDAO:     r.DESC_CORDAO,
                    MASCARA_CORDAO:  r.MASCARA_CORDAO,
                    TOTAL:           0,
                });
            }
            mapaGrupos.get(chave).TOTAL += parseFloat(r.TOTAL_MOLINHA) || 0;
        }

        let corpoAgrup = '';
        let grandTotal = 0;
        for (const g of mapaGrupos.values()) {
            grandTotal += g.TOTAL;
            corpoAgrup += `
<tr>
    <td>${fmt(g.COD_ITEM_CORDAO)}</td>
    <td class="td-wrap">${fmt(g.DESC_CORDAO)}</td>
    <td style="text-align:center">${fmt(g.MASCARA_CORDAO)}</td>
    <td style="text-align:center;font-weight:bold">${fmtN(g.TOTAL)}</td>
</tr>`;
        }
        corpoAgrup += `
<tr class="total-row">
    <td colspan="3" style="text-align:right;padding-right:8px">TOTAL GERAL DE CORDÕES:</td>
    <td style="text-align:center">${fmtN(grandTotal)}</td>
</tr>`;

        return `
<div class="pm-section">
    ${cabecalho(hoje)}

    <div class="pm-section-title">${titulo} — DETALHE POR ORDEM</div>
    <div style="overflow-x:auto">
    <table class="pm-table">
        <thead>
            <tr>
                <th>Nº ORDEM</th>
                <th>DT. INI</th>
                <th>CÓD. ITEM</th>
                <th>DESCRIÇÃO</th>
                <th>MÁSCARA</th>
                <th>QTDE OF</th>
                <th>CÓD. CORDÃO</th>
                <th>DESC. CORDÃO</th>
                <th>MÁSCARA CORDÃO</th>
                <th>TOTAL CORDÕES</th>
            </tr>
        </thead>
        <tbody>${corpoDetalhe}</tbody>
    </table>
    </div>

    <div class="pm-section-title" style="margin-top:14px">${titulo} — AGRUPADO POR CORDÃO</div>
    <div style="overflow-x:auto">
    <table class="pm-table">
        <thead>
            <tr>
                <th>CÓD. CORDÃO</th>
                <th>DESC. CORDÃO</th>
                <th>MÁSCARA CORDÃO</th>
                <th>TOTAL CORDÕES</th>
            </tr>
        </thead>
        <tbody>${corpoAgrup}</tbody>
    </table>
    </div>

    ${historico()}
</div>`;
    }

    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>PCP Molas — Cordão de Molas</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.pm-section{padding:8px 12px}
.pm-header-wrap{border:1px solid #000}
.pm-header-row1{display:flex;align-items:stretch}
.pm-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.pm-header-row1 .col-logo img{width:85px}
.pm-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:10pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.pm-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.pm-revisao{border:1px solid #000;border-top:none;padding:2px 8px;font-size:7.5pt;text-align:right}
.pm-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:11pt;padding:4px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pm-table{width:100%;border-collapse:collapse;font-size:6.5pt;table-layout:fixed}
.pm-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 2px;text-align:center;font-weight:bold;white-space:normal;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pm-table td{border:1px solid #ccc;padding:1px 2px;white-space:nowrap}
.pm-table td.td-wrap{white-space:normal;word-break:break-word}
.pm-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pm-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.pm-historico table{width:100%;border-collapse:collapse}
.pm-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pm-historico td{border:1px solid #ccc;padding:2px 4px}
@page{size:A4 landscape;margin:6mm}
@media print{
  .pm-section{page-break-after:always;padding:8px 12px}
  .pm-section:last-child{page-break-after:avoid}
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
                const res  = await fetch('pcp-api-relatorio-pcp-cordao', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const hoje = dataHoje();
                const html = renderRelatorio(data.rows || [], numLote, hoje);

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
