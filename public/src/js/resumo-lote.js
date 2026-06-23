(function () {
    'use strict';

    function fmt(v)    { return (v === null || v === undefined || v === '') ? '0' : v; }
    function fmtDec(v) { const n = parseFloat(v); return isNaN(n) ? '0,00' : n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}); }

    function card(titulo, total, linhas) {
        const rows = linhas.map(l => {
            const cls = (l.sub ? ' row-sub' : '') + (l.bold ? ' row-bold' : '');
            return `<div class="resumo-row${cls}">
                <span class="rr-label">${l.label}</span>
                <span class="rr-value">${l.value}</span>
            </div>`;
        }).join('');

        return `
<div class="resumo-card">
    <div class="resumo-card-header">
        <span>${titulo}</span>
        <span class="rc-total">${fmt(total)}</span>
    </div>
    <div class="resumo-card-body">${rows}</div>
</div>`;
    }

    function renderResumo(d, numLote) {
        const titulo = `LOTE ${numLote}${d.data_lote ? ' &nbsp;|&nbsp; ' + d.data_lote : ''}`;

        const c = d.colchoes;
        const t = d.tapecaria;
        const x = d.caixote;
        const s = d.costura;
        const m = d.mesa_corte;
        const b = d.bordadeira;

        const cards = [
            card('COLCHÕES', c.total, [
                { label: 'COL. MESA',   value: fmt(c.col_mesa)   },
                { label: 'COL. MOLA',   value: fmt(c.col_mola)   },
                { label: 'COL. ESPUMA', value: fmt(c.col_espuma) },
                { label: 'COLCHONETE',  value: fmt(c.colchonete) },
            ]),
            card('TAPEÇARIA', t.total, [
                { label: 'BASE',          value: fmt(t.base)        },
                { label: 'CONJUGADO',     value: fmt(t.conjugado),  bold: true },
                { label: 'CONJ. ESPUMA',  value: fmt(t.conj_espuma), sub: true },
                { label: 'CONJ. MOLA',    value: fmt(t.conj_mola),   sub: true },
                { label: 'AUX. AUXILIAR', value: fmt(t.aux_auxiliar) },
                { label: 'CABEÇEIRA',     value: fmt(t.cabeceira)   },
            ]),
            card('CAIXOTE', x.total, [
                { label: 'C. COLCHÃO',   value: fmt(x.c_colchao)   },
                { label: 'C. CONJUGADO', value: fmt(x.c_conjugado) },
                { label: 'C. PILLOW',    value: fmt(x.c_pillow),  sub: true },
                { label: 'C. MESA',      value: fmt(x.c_mesa),    sub: true },
                { label: 'MOLA BONNEL',  value: fmt(x.mola_bonnel) },
            ]),
            card('COSTURA (FAIXA)', s.total, [
                { label: 'PILLOW',  value: fmt(s.pillow)             },
                { label: 'FPT',     value: fmtDec(s.fpt_linear)      },
                { label: 'OPTRON',  value: fmtDec(s.optron_linear)   },
                { label: 'DIVERSOS',value: fmt(s.diversos)           },
            ]),
            card('MESA DE CORTE', m.total, [
                { label: 'CAIXA BOX', value: fmt(m.caixa_box) },
                { label: 'CAIXOTE',   value: fmt(m.caixote)   },
            ]),
            card('BORDADEIRA', fmtDec(b.total), [
                { label: 'BORDADEIRA 01', value: fmtDec(b.brd01) },
                { label: 'BORDADEIRA 02', value: fmtDec(b.brd02) },
            ]),
        ];

        return `
<div style="font-family:Arial,sans-serif;font-size:9pt;">
    <div class="resumo-header-card">
        <div class="rh-item">
            <span class="rh-label">LOTE</span>
            <span class="rh-value">${numLote}</span>
        </div>
        ${d.data_lote ? `<div class="rh-item">
            <span class="rh-label">DATA</span>
            <span class="rh-value">${d.data_lote}</span>
        </div>` : ''}
    </div>
    <div class="resumo-grid">${cards.join('')}</div>
</div>`;
    }

    function abrirImpressao(conteudo) {
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8">
<title>Resumo do Lote</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:9pt;background:#fff;padding:12px}
.resumo-header-card{background:#1f3864;color:#fff;border-radius:6px;padding:10px 16px;margin-bottom:12px;display:flex;gap:28px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rh-item{display:flex;flex-direction:column}
.rh-label{font-size:9px;opacity:.75;text-transform:uppercase;letter-spacing:.5px}
.rh-value{font-size:16px;font-weight:bold}
.resumo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.resumo-card{border:1px solid #ccc;border-radius:6px;overflow:hidden;break-inside:avoid}
.resumo-card-header{background:#002060;color:#fff;display:flex;justify-content:space-between;align-items:center;padding:5px 10px;font-weight:bold;font-size:10.5pt;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rc-total{background:rgba(255,255,255,.2);border-radius:3px;padding:1px 6px;font-size:11pt;min-width:46px;text-align:right}
.resumo-row{display:flex;justify-content:space-between;padding:3px 10px;border-bottom:1px solid #f0f0f0;font-size:8.5pt}
.resumo-row:last-child{border-bottom:none}
.resumo-row.row-bold{font-weight:bold;background:#f5f5f5;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.resumo-row.row-sub{padding-left:22px;color:#555;background:#fafafa;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rr-value{font-weight:600;color:#1f3864;text-align:right}
.resumo-row.row-sub .rr-value{color:#555;font-weight:normal}
@page{size:A4 portrait;margin:10mm}
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
                const res  = await fetch('pcp-api-resumo-lote', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ num_lote: parseInt(numLote) }),
                });
                const data = await res.json();

                if (data.error) {
                    statusMsg.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                    return;
                }

                const html = renderResumo(data, numLote);
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
            abrirImpressao(printArea.innerHTML);
        });
    });
})();
