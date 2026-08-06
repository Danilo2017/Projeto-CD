(function () {
    'use strict';

    const HISTORICO = [
        { rev: '1', data: '06/08/2026', alteracao: 'Edição Inicial' },
    ];

    const LOGO_URL = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';

    function dataHoje() {
        const d = new Date();
        return String(d.getDate()).padStart(2,'0') + '/' +
               String(d.getMonth()+1).padStart(2,'0') + '/' +
               d.getFullYear();
    }

    function fmt(v) { return (v === null || v === undefined || v === '') ? '' : v; }

    /* ── Renderiza rastreabilidade Borda ────────── */
    function renderBorda(rows, numLote) {
        const hoje = dataHoje();

        let corpoTabela = '';
        if (rows && rows.length > 0) {
            for (const r of rows) {
                corpoTabela += `
            <tr>
                <td class="text-center">${fmt(r.DT_INICIAL)}</td>
                <td class="text-center">${fmt(r.NUM_ORDEM)}</td>
                <td class="text-center">${fmt(r.COD_ITEM_BORDA)}</td>
                <td class="td-wrap">${fmt(r.DESC_BORDA)}</td>
                <td class="text-center">${fmt(r.MASCARA_BORDA)}</td>
                <td></td>
                <td></td>
            </tr>`;
            }
        } else {
            corpoTabela = '<tr><td colspan="7" class="text-center text-muted">Nenhum dado encontrado para este lote.</td></tr>';
        }

        return `
<div class="rastr-section">
    <div class="rastr-header">
        <div class="col-logo">
            <img src="${LOGO_URL}" alt="Gazin">
        </div>
        <div class="col-title">RASTREABILIDADE FABRICAÇÃO BORDA</div>
        <div class="col-setor">
            <span class="setor-label">SETOR</span>
            <span class="setor-value">MOLAS</span>
        </div>
    </div>
    <div class="rastr-subheader">
        <div class="sub-row1">
            <span class="sub-code">R.16.MOL-03</span>
            <span class="sub-sep">|</span>
            <span class="sub-rev">Revisão:01</span>
            <span class="sub-sep">|</span>
            <span class="sub-data">Data: ${hoje}</span>
        </div>
        <div class="sub-row2">COLABORADOR: <span class="sub-colab-line"></span></div>
    </div>

    <div style="overflow-x:auto">
    <table class="rastr-table">
        <thead>
            <tr>
                <th>DATA</th>
                <th>NF DO PEDIDO /<br>ORDEM DE PRODUÇÃO</th>
                <th>CÓD. BORDA</th>
                <th>DESC. BORDA</th>
                <th>MÁSCARA<br>BORDA</th>
                <th>NF DO ARAME</th>
                <th>LOTE DO ARAME</th>
            </tr>
        </thead>
        <tbody>${corpoTabela}</tbody>
    </table>
    </div>

    <div class="rastr-historico">
        <table>
            <thead>
                <tr><th colspan="3">CONTROLE DE ALTERAÇÕES</th></tr>
                <tr><th style="width:80px">REVISÃO</th><th style="width:120px">DATA</th><th>ALTERAÇÃO</th></tr>
            </thead>
            <tbody>
                ${HISTORICO.map(h => `<tr><td style="text-align:center">${h.rev}</td><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
            </tbody>
        </table>
    </div>
</div>`;
    }

    /* ── Abre janela de impressão ───────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Rastreabilidade — Fabricação Borda</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:9pt;background:#fff}
.rastr-section{padding:10px 14px}
.rastr-header{display:flex;align-items:stretch;border:1px solid #000}
.rastr-header .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.rastr-header .col-logo img{width:90px}
.rastr-header .col-title{flex:1;text-align:center;font-weight:bold;font-size:10.5pt;padding:6px 8px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.rastr-header .col-setor{width:130px;font-size:8pt;padding:4px 8px;flex-shrink:0;display:flex;flex-direction:column;justify-content:center}
.rastr-header .col-setor .setor-label{font-size:7pt;color:#555}
.rastr-header .col-setor .setor-value{font-weight:bold;font-size:10pt}
.rastr-subheader{display:flex;flex-direction:column;border:1px solid #000;border-top:none;font-size:8pt}
.rastr-subheader .sub-row1{padding:3px 8px;border-bottom:1px solid #ccc}
.rastr-subheader .sub-sep{margin:0 6px;color:#999}
.rastr-subheader .sub-row2{padding:3px 8px;display:flex;align-items:center;gap:6px}
.rastr-subheader .sub-colab-line{flex:1;border-bottom:1px solid #000;min-width:200px;display:inline-block}
.rastr-table{width:100%;border-collapse:collapse;font-size:8pt;margin-top:6px}
.rastr-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:4px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rastr-table td{border:1px solid #ccc;padding:3px 6px;height:20px}
.rastr-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rastr-historico{margin-top:12px;border:1px solid #999;font-size:8pt}
.rastr-historico table{width:100%;border-collapse:collapse}
.rastr-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rastr-historico td{border:1px solid #ccc;padding:2px 6px}
.text-center{text-align:center}
@page{size:A4 portrait;margin:8mm}
@media screen{
  body{background:#6c757d;padding:24px}
  .rastr-section{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);border-radius:3px;padding:18px 20px}
}
@media print{
  body{background:#fff;padding:0}
  .rastr-section{box-shadow:none;border-radius:0;margin:0;padding:10px 14px}
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
                const res  = await fetch('qualidade-api-rastreabilidade-borda-molas', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                printArea.innerHTML = renderBorda(data.rows || [], numLote);
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
