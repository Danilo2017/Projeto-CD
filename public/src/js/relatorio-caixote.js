(function () {
    'use strict';

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

    function fmt(v)    { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }

    /* ── Renderiza uma seção ────────────────────────────── */
    // getKeys(row) → { outerKey, grupoKey }
    //   Quando outerKey muda → separador cinza grosso entre grupos principais
    //   Quando só grupoKey muda → separador fino (pula linha entre subgrupos)
    function renderSecao(rows, titulo, numLote, dataLote, getKeys) {
        if (rows.length === 0) return '';

        let totalGeral = 0;
        let subtotal   = 0;
        let outerAtual = null;
        let grupoAtual = null;
        let corpo      = '';
        const buf      = [];

        function flushGrupo(outerMudou) {
            if (buf.length === 0) return;
            for (const linha of buf) corpo += linha;
            if (buf.length > 1) {
                corpo += `
            <tr class="subtotal-row">
                <td colspan="7" style="text-align:right;font-weight:bold"></td>
                <td style="text-align:center;font-weight:bold">${subtotal}</td>
                <td colspan="7"></td>
            </tr>`;
            }
            if (outerMudou) {
                corpo += `<tr class="separator-row" style="height:8px"><td colspan="15" style="background:#b8b8b8"></td></tr>`;
            } else {
                corpo += `<tr class="separator-row"><td colspan="15"></td></tr>`;
            }
            buf.length = 0;
            subtotal   = 0;
        }

        for (const r of rows) {
            const { outerKey, grupoKey } = getKeys(r);
            const qtde     = parseFloat(r.QTDE || 0);

            if (grupoKey !== grupoAtual) {
                if (grupoAtual !== null) flushGrupo(outerKey !== outerAtual);
                outerAtual = outerKey;
                grupoAtual = grupoKey;
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
                <td class="text-center">${fmtNum(r.QTDE)}</td>
                <td class="text-center">${fmtNum(r.ALT_EPS)}</td>
                <td class="text-center">${fmtNum(r.ALT_MOLA)}</td>
                <td class="text-center">${fmt(r.BORDA)}</td>
                <td class="text-center">${fmt(r.TNT_OU_FELTRO)}</td>
                <td class="text-center">${fmt(r.PILLOW)}</td>
                <td class="text-center">${fmtNum(r.ALT)}</td>
                <td class="text-center">${fmt(r.TECIDO)}</td>
            </tr>`);
        }
        flushGrupo(false);

        corpo += `
        <tr class="total-row">
            <td colspan="7" style="text-align:right">QTDE TOTAL:</td>
            <td style="text-align:center">${totalGeral}</td>
            <td colspan="7"></td>
        </tr>`;

        const cabecalho = `${titulo} - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}`;

        return `
<div class="pcp-section">
    <div class="pcp-report-header">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right"><div><strong>SETOR</strong></div><div>GESTÃO DE PRODUÇÃO</div></div>
    </div>
    <div class="pcp-revisao">REVISÃO-01 &nbsp;&nbsp; DATA: ${dataHoje()}</div>
    <div class="pcp-section-title">${cabecalho}</div>
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
                <th>QTD</th>
                <th>ALT EF</th>
                <th>ALT MOL</th>
                <th>BORD</th>
                <th>TNT OU FELTRO</th>
                <th>PILLOW</th>
                <th>AL</th>
                <th>TECIDO</th>
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
<title>Sequência de Produção — Caixote</title>
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
.pcp-revisao{border:1px solid #000;border-top:none;padding:2px 8px;font-size:8pt;text-align:right}
.pcp-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:12pt;padding:5px;margin-top:6px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table{width:100%;border-collapse:collapse;font-size:7pt;table-layout:fixed}
.pcp-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:1px 2px;text-align:center;font-weight:bold;white-space:normal;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table td{border:1px solid #ccc;padding:1px 2px;white-space:normal;word-break:break-word}
.pcp-table th:nth-child(1),.pcp-table td:nth-child(1){width:6.5%}
.pcp-table th:nth-child(2),.pcp-table td:nth-child(2){width:4.5%}
.pcp-table th:nth-child(3),.pcp-table td:nth-child(3){width:3.5%}
.pcp-table th:nth-child(4),.pcp-table td:nth-child(4){width:3.5%}
.pcp-table th:nth-child(5),.pcp-table td:nth-child(5){width:13%;white-space:normal;word-break:break-word}
.pcp-table th:nth-child(6),.pcp-table td:nth-child(6){width:17.5%;white-space:normal;word-break:break-word}
.pcp-table th:nth-child(7),.pcp-table td:nth-child(7){width:3%}
.pcp-table th:nth-child(8),.pcp-table td:nth-child(8){width:3%}
.pcp-table th:nth-child(9),.pcp-table td:nth-child(9){width:3.5%}
.pcp-table th:nth-child(10),.pcp-table td:nth-child(10){width:3.5%}
.pcp-table th:nth-child(11),.pcp-table td:nth-child(11){width:5%}
.pcp-table th:nth-child(12),.pcp-table td:nth-child(12){width:6.5%}
.pcp-table th:nth-child(13),.pcp-table td:nth-child(13){width:4%}
.pcp-table th:nth-child(14),.pcp-table td:nth-child(14){width:2.5%}
.pcp-table th:nth-child(15),.pcp-table td:nth-child(15){width:10%;white-space:normal;word-break:break-word}
.pcp-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.subtotal-row td{background:#dce6f1;font-weight:bold;border:none;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.separator-row td{border:none;padding:2px;background:#fff}
.pcp-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico{margin-top:10px;border:1px solid #999;font-size:8pt}
.pcp-historico table{width:100%;border-collapse:collapse}
.pcp-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 6px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico td{border:1px solid #ccc;padding:2px 6px}
.text-center{text-align:center}
.td-wrap{white-space:normal;word-break:break-word}
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
                const res  = await fetch('pcp-api-relatorio-caixote', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const dt     = data.data_lote || '';
                const secoes = data.secoes    || {};

                const rowsSem     = secoes.sem_pillow || [];
                const rowsCom     = secoes.com_pillow || [];
                const rowsMesa    = secoes.mesa       || [];
                const rowsConj    = secoes.conjugado  || [];
                const ordPeso = { 'MOLA': 1, 'MOLA_PL': 2 };
                const rowsRobotec = [...rowsSem, ...rowsCom];
                // Ordem: ORD primeiro (MOLA → MOLA_PL → MESA), depois LARGURA, depois ALT_EPS (0 por último)
                rowsRobotec.sort((a, b) => {
                    const pA = ordPeso[a.ORD] || 99;
                    const pB = ordPeso[b.ORD] || 99;
                    if (pA !== pB) return pA - pB;
                    const larA = parseInt(a.LARGURA_COLCHAO) || 0;
                    const larB = parseInt(b.LARGURA_COLCHAO) || 0;
                    if (larA !== larB) return larA - larB;
                    const epsA = parseInt(a.ALT_EPS) || 0;
                    const epsB = parseInt(b.ALT_EPS) || 0;
                    if (epsA === 0 && epsB !== 0) return 1;
                    if (epsA !== 0 && epsB === 0) return -1;
                    return epsA - epsB;
                });

                // ROBOTEC: outer = ORD+LARGURA, inner = ORD+LARGURA+ALTEPS
                const kRobotec = r => {
                    const ord = r.ORD || '';
                    const lar = parseInt(r.LARGURA_COLCHAO) || 0;
                    const eps = parseInt(r.ALT_EPS) || 0;
                    return { outerKey: `${ord}_${lar}`, grupoKey: `${ord}_${lar}_${eps}` };
                };
                // Demais seções: outer = LARGURA, inner = LARGURA+ALTEPS
                const kLarEps = r => {
                    const lar = parseInt(r.LARGURA_COLCHAO) || 0;
                    const eps = parseInt(r.ALT_EPS) || 0;
                    return { outerKey: `${lar}`, grupoKey: `${lar}_${eps}` };
                };

                let html = renderSecao(rowsRobotec, 'CAIXOTE ROBOTEC',    numLote, dt, kRobotec);
                html    += renderSecao(rowsSem,     'CAIXOTE SEM PILLOW', numLote, dt, kLarEps);
                html    += renderSecao(rowsCom,     'CAIXOTE COM PILLOW', numLote, dt, kLarEps);
                html    += renderSecao(rowsMesa,    'CAIXOTE MESA',       numLote, dt, kLarEps);
                html    += renderSecao(rowsConj,    'CAIXOTE CONJUGADO',  numLote, dt, kLarEps);

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
