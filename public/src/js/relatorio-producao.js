(function () {
    'use strict';

    const HISTORICO = [
        { data: '24/10/2024', alteracao: 'Elaboração Inicial' },
        { data: '13/08/2025', alteracao: 'Alteração do Título do documento e do rodapé.' },
    ];

    const LOGO_URL      = 'https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png';
    const DOC_RBT_LINHA = 'R.14.GEP-02';  // Robotec Linha de Montagem
    const DOC_RBT_MESA  = 'R.14.GEP-03';  // Robotec Mesa
    const DOC_BOX       = 'R.14.GEP-04';  // Colchão Box
    const DOC_CAB       = 'R.14.GEP-05';  // Cabeceiras
    const DOC_TRAV      = 'R.14.GEP-06';  // Travesseiro e Outros
    const DOC_CONJ      = 'R.14.GEP-04';  // Conjugado
    const REVISAO       = 'REVISÃO-01';
    const DATA_REVISAO  = '17/08/2026';   // Data do documento para auditoria

    const LARGURA_LABEL = {
        790:  'LARGURA 790',
        1380: 'LARGURA 1380',
        960:  'LARGURA 960',
        880:  'LARGURA 880',
        980:  'LARGURA 980',
    };

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

    function fmt(v)    { return (v === null || v === undefined || v === '') ? '' : v; }
    function fmtNum(v) { const n = parseFloat(v); return isNaN(n) ? '' : (n === 0 ? '0' : n); }
    function fmtQ(v)   { const n = parseFloat(v); return isNaN(n) ? '' : n; }

    /* ── Cabeçalho 2 linhas (Robotec / Tap) ─────── */
    function cabecalho2(docCode, hoje) {
        return `
<div class="pcp-header-wrap">
    <div class="pcp-header-row1">
        <div class="col-logo"><img src="${LOGO_URL}" alt="Gazin"></div>
        <div class="col-title">RELATÓRIO DE PRODUÇÃO</div>
        <div class="col-right"><div><strong>SETOR</strong></div><div>GESTÃO DE PRODUÇÃO</div></div>
    </div>
    <div class="pcp-header-row2">
        <div class="col-logo2"></div>
        <div class="col-code">${docCode}</div>
        <div class="col-rev">${REVISAO}&nbsp;&nbsp;DATA: ${DATA_REVISAO}</div>
    </div>
</div>`;
    }

    /* ── Rodapé histórico ────────────────────────── */
    function historico() {
        return `
<div class="pcp-historico">
    <table>
        <thead><tr><th>Revisão</th><th>data</th><th>Alterações</th></tr></thead>
        <tbody>
            ${HISTORICO.map((h, i) => `<tr><td style="text-align:center">${i+1}</td><td>${h.data}</td><td>${h.alteracao}</td></tr>`).join('')}
        </tbody>
    </table>
</div>`;
    }

    /* ══════════════════════════════════════════════
       1. ROBOTEC
    ══════════════════════════════════════════════ */
    const THEAD_RBT = `<thead><tr>
        <th>ORD</th><th>ORDEM</th><th>ITEM</th><th>DESCRIÇÃO</th>
        <th>MASCARA</th><th>QTD</th><th>LAR</th><th>MOL</th>
        <th>DENSIDADE</th><th>EP</th><th>CABEÇOTE</th>
    </tr></thead>`;

    function renderRobotec(rows, titulo, numLote, dataLote, hoje, docCode) {
        if (!rows || rows.length === 0) return '';

        let corpo = '', grandTotal = 0, subQtde = 0, curLar = null, curOrd = null;

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i], lar = r.LARGURA_COLCHAO, ord = r.ORD, q = parseFloat(r.QTDE) || 0;
            if (curLar !== null && (lar !== curLar || ord !== curOrd)) {
                corpo += `<tr class="subtotal-row"><td colspan="10"></td><td>${subQtde}</td></tr>`;
                subQtde = 0;
                corpo += `<tr><td colspan="11" style="padding:0;border:none;height:4px;background:#fff"></td></tr>`;
            }
            curLar = lar; curOrd = ord; subQtde += q; grandTotal += q;
            const cls = ORD_CLASS[fmt(r.ORD)] || '';
            corpo += `
<tr class="${cls}">
    <td class="text-center">${fmt(r.ORD)}</td>
    <td class="text-center">${fmt(r.NUM_ORDEM)}</td>
    <td class="text-center">${fmt(r.ITEM)}</td>
    <td class="td-wrap">${fmt(r.DESCICAO)}</td>
    <td class="td-wrap">${fmt(r.MASCARA)}</td>
    <td class="text-center">${fmtQ(r.QTDE)}</td>
    <td class="text-center">${fmt(r.LARGURA_COLCHAO)}</td>
    <td class="text-center">${fmt(r.MOLA)}</td>
    <td class="text-center">${fmt(r.DENSIDADE)}</td>
    <td class="text-center">${fmt(r.EPS)}</td>
    <td class="text-center">${fmt(r.CABECOTE)}</td>
</tr>`;
        }
        corpo += `<tr class="subtotal-row"><td colspan="10"></td><td>${subQtde}</td></tr>`;
        corpo += `<tr class="total-row"><td colspan="10" style="text-align:right;padding-right:8px">QTDE TOTAL:</td><td style="text-align:center">${grandTotal}</td></tr>`;

        return `
<div class="pcp-section">
    ${cabecalho2(docCode, hoje)}
    <div class="pcp-section-title">${titulo} - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}</div>
    <div style="overflow-x:auto"><table class="rbt-table">${THEAD_RBT}<tbody>${corpo}</tbody></table></div>
    ${historico()}
</div>`;
    }

    /* ══════════════════════════════════════════════
       2. COLCHÃO BOX / CABECEIRAS / CONJUGADO
    ══════════════════════════════════════════════ */
    const THEAD_TAP = `<thead><tr>
        <th>ORD</th><th>ORDEM</th><th>ITEM</th><th>DESCRIÇÃO</th>
        <th>ID</th><th>MASCARA</th><th>QTDE</th><th>LARG</th>
    </tr></thead>`;

    function buildCorpoTap(rows) {
        let corpo = '', grandTotal = 0, subQtde = 0, curLar = null;

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i], lar = parseInt(r.LARGURA_COLCHAO) || 0, q = parseFloat(r.QTDE) || 0;
            if (curLar !== null && lar !== curLar) {
                corpo += `
<tr class="subtotal-row">
    <td colspan="6" style="text-align:right;padding-right:8px">Subtotal ${LARGURA_LABEL[curLar] || 'LARGURA ' + curLar}:</td>
    <td style="text-align:center">${subQtde}</td><td></td>
</tr>
<tr><td colspan="8" style="padding:0;border:none;height:3px;background:#fff"></td></tr>`;
                subQtde = 0;
            }
            curLar = lar; subQtde += q; grandTotal += q;
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

        if (curLar !== null) {
            corpo += `
<tr class="subtotal-row">
    <td colspan="6" style="text-align:right;padding-right:8px">Subtotal ${LARGURA_LABEL[curLar] || 'LARGURA ' + curLar}:</td>
    <td style="text-align:center">${subQtde}</td><td></td>
</tr>`;
        }
        corpo += `<tr class="total-row"><td colspan="6" style="text-align:right;padding-right:8px">QTDE TOTAL:</td><td style="text-align:center">${grandTotal}</td><td></td></tr>`;
        return corpo;
    }

    function renderSecaoTap(rows, titulo, numLote, dataLote, hoje, docCode) {
        if (!rows || rows.length === 0) return '';
        return `
<div class="pcp-section">
    ${cabecalho2(docCode, hoje)}
    <div class="pcp-section-title">${titulo} - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}</div>
    <div style="overflow-x:auto"><table class="tap-table">${THEAD_TAP}<tbody>${buildCorpoTap(rows)}</tbody></table></div>
    ${historico()}
</div>`;
    }

    /* ══════════════════════════════════════════════
       3. TRAVESSEIRO E OUTROS (ex Trave Pezê — sem subtotal por largura)
    ══════════════════════════════════════════════ */
    function renderTravesseiro(rows, numLote, dataLote, hoje) {
        if (!rows || rows.length === 0) return '';

        let corpo = '', grandTotal = 0;
        for (const r of rows) {
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
        corpo += `<tr class="total-row"><td colspan="6" style="text-align:right;padding-right:8px">QTDE TOTAL:</td><td style="text-align:center">${grandTotal}</td><td></td></tr>`;

        return `
<div class="pcp-section">
    ${cabecalho2(DOC_TRAV, hoje)}
    <div class="pcp-section-title">TRAVESSEIRO E OUTROS - LOTE ${numLote}${dataLote ? ' (' + dataLote + ')' : ''}</div>
    <div style="overflow-x:auto"><table class="tap-table">${THEAD_TAP}<tbody>${corpo}</tbody></table></div>
    ${historico()}
</div>`;
    }

    /* ── Janela de impressão ─────────────────────── */
    function abrirJanelaImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Relatório de Produção</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:7.5pt;background:#fff}
.pcp-section{padding:8px 12px}
/* Cabeçalho caixote (1 linha) */
.pcp-report-header{display:flex;align-items:center;border:1px solid #000}
.pcp-report-header .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0}
.pcp-report-header .col-logo img{width:90px}
.pcp-report-header .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:6px;border-right:1px solid #000}
.pcp-report-header .col-right{width:170px;font-size:8pt;padding:4px 8px;flex-shrink:0}
.pcp-report-header .col-right div{margin-bottom:2px}
.pcp-revisao{border:1px solid #000;border-top:none;padding:2px 8px;font-size:8pt;text-align:right}
/* Cabeçalho 2 linhas */
.pcp-header-wrap{border:1px solid #000}
.pcp-header-row1{display:flex;align-items:stretch}
.pcp-header-row1 .col-logo{width:110px;padding:4px 8px;border-right:1px solid #000;flex-shrink:0;display:flex;align-items:center}
.pcp-header-row1 .col-logo img{width:85px}
.pcp-header-row1 .col-title{flex:1;text-align:center;font-weight:bold;font-size:11pt;padding:5px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.pcp-header-row1 .col-right{width:170px;font-size:7.5pt;padding:3px 6px;flex-shrink:0}
.pcp-header-row2{display:flex;align-items:stretch;border-top:1px solid #000}
.pcp-header-row2 .col-logo2{width:110px;border-right:1px solid #000;flex-shrink:0}
.pcp-header-row2 .col-code{flex:1;text-align:center;font-size:7.5pt;padding:2px 6px;border-right:1px solid #000;display:flex;align-items:center;justify-content:center}
.pcp-header-row2 .col-rev{width:170px;font-size:7.5pt;padding:2px 6px;flex-shrink:0}
/* Título seção */
.pcp-section-title{background:#002060;color:#fff;text-align:center;font-weight:bold;font-size:11pt;padding:4px;margin-top:5px;margin-bottom:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
/* Tabela caixote */
.pcp-table{width:100%;border-collapse:collapse;font-size:8pt}
.pcp-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:3px 4px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table td{border:1px solid #ccc;padding:2px 4px;white-space:nowrap}
.pcp-table td.td-wrap{white-space:normal;word-break:break-word}
.pcp-table tr:nth-child(even) td{background:#f2f2f2;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.subtotal-row td{background:#dce6f1;font-weight:bold;text-align:right;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;text-align:right;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-table tr.separator-row td{background:#fff;border:none;padding:4px}
/* Tabela robotec */
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
/* Tabela tap */
.tap-table{width:100%;border-collapse:collapse;font-size:7pt}
.tap-table th{background:#1f3864;color:#fff;border:1px solid #999;padding:2px 3px;text-align:center;font-weight:bold;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-table td{border:1px solid #ccc;padding:1px 3px;white-space:nowrap}
.tap-table td.td-wrap{white-space:normal;word-break:break-word}
.tap-table tr.subtotal-row td{background:#d9d9d9;font-weight:bold;border-top:2px solid #999;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tap-table tr.total-row td{background:#1f3864;color:#fff;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
/* Histórico */
.pcp-historico{margin-top:8px;border:1px solid #999;font-size:7pt}
.pcp-historico table{width:100%;border-collapse:collapse}
.pcp-historico th{background:#d9d9d9;border:1px solid #999;padding:2px 4px;text-align:center;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pcp-historico td{border:1px solid #ccc;padding:2px 4px}
.text-center{text-align:center}
@page{size:A4 landscape;margin:6mm}
@media print{
  body{background:#fff;padding:0}
  .pcp-section{page-break-after:always;padding:8px 12px}
  .pcp-section:last-child{page-break-after:avoid}
  .pcp-table td.td-wrap,.rbt-table td.td-wrap,.tap-table td.td-wrap{white-space:normal;word-break:break-word}
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

                const hoje = dataHoje();
                const numL = parseInt(numLote);
                const dt   = data.data_lote || '';

                let html = '';

                // 1. Robotec Linha de Montagem + Mesa
                html += renderRobotec(data.linha_rows || [], 'ROBOTEC LINHA DE MONTAGEM', numL, dt, hoje, DOC_RBT_LINHA);
                html += renderRobotec(data.mesa_rows  || [], 'ROBOTEC MESA',              numL, dt, hoje, DOC_RBT_MESA);

                // 2. Colchão Box + Cabeceiras
                html += renderSecaoTap(data.colchaobox_rows || [], 'COLCHÃO BOX', numL, dt, hoje, DOC_BOX);
                html += renderSecaoTap(data.cabeceira_rows  || [], 'CABECEIRAS',  numL, dt, hoje, DOC_CAB);

                // 3. Conjugado
                html += renderSecaoTap(data.conjugado_rows || [], 'CONJUGADO', numL, dt, hoje, DOC_CONJ);

                // 4. Travesseiro e Outros
                html += renderTravesseiro(data.travepeze_rows || [], numL, dt, hoje);

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
