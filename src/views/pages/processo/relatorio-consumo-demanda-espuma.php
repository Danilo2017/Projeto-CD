<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acessoProcesso = $is_admin || in_array('processo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoProcesso) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Processo — Consumo Demanda Espuma',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-consumo-demanda-espuma',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.dem-wrap { overflow-x:auto; }
.dem-table { width:100%;border-collapse:collapse;font-size:.78rem;white-space:nowrap; }
.dem-table th { background:#e9ecef;color:#212529;padding:4px 8px;border:1px solid #ced4da;text-align:center;position:sticky;top:0;z-index:1; }
.dem-table th.col-left { text-align:left; }
.dem-table td { padding:3px 8px;border:1px solid #dee2e6;text-align:right; }
.dem-table td.col-left { text-align:left;white-space:normal; }
.dem-table td.td-zero { color:#bbb; }
.row-total td { background:#343a40;color:#fff;font-weight:700; }
#printArea { display:none; }
#printArea.visible { display:block; }
@media print {
    @page { size: landscape; margin: 10mm; }
    body * { visibility:hidden; }
    #printArea, #printArea * { visibility:visible; }
    #printArea { position:absolute;left:0;top:0;width:100%; }
    .dem-wrap { overflow:visible; }
}
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-box-seam me-1 text-primary"></i> Consumo Demanda Espuma</h5>
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Data Início</label>
                <input type="date" id="inpDtIni" class="form-control form-control-sm" style="max-width:160px;">
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Data Fim</label>
                <input type="date" id="inpDtFim" class="form-control form-control-sm" style="max-width:160px;">
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Gerar
                </button>
                <button id="btnImprimir" class="btn btn-sm btn-outline-secondary ms-2" style="display:none;">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <button id="btnExcel" class="btn btn-sm btn-success ms-2" style="display:none;">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
            </div>
            <div class="col">
                <span id="msgRelatorio"></span>
                <span id="spanTotalReg" class="text-muted small ms-3"></span>
            </div>
        </div>
    </div>

    <div id="printArea"></div>

</div>

<script>
(function () {
    'use strict';

    const LOGO = `<div style="text-align:left;margin-bottom:8px;"><img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png" style="height:45px;" alt="Gazin"></div>`;

    const btnGerar  = document.getElementById('btnGerar');
    const btnImpr   = document.getElementById('btnImprimir');
    const btnExcel  = document.getElementById('btnExcel');
    const msgEl     = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg');
    const printArea = document.getElementById('printArea');

    let _rows = [];

    const EMPS = [
        { key: 'EMPRESA_1',  label: 'Emp. 1'  },
        { key: 'EMPRESA_2',  label: 'Emp. 2'  },
        { key: 'EMPRESA_3',  label: 'Emp. 3'  },
        { key: 'EMPRESA_4',  label: 'Emp. 4'  },
        { key: 'EMPRESA_5',  label: 'Emp. 5'  },
        { key: 'EMPRESA_13', label: 'Emp. 13' },
        { key: 'EMPRESA_14', label: 'Emp. 14' },
        { key: 'EMPRESA_15', label: 'Emp. 15' },
    ];

    (function () {
        const hoje = new Date();
        const ano  = hoje.getFullYear();
        const mes  = String(hoje.getMonth() + 1).padStart(2, '0');
        document.getElementById('inpDtIni').value = `${ano}-${mes}-01`;
        const ultimo = new Date(ano, hoje.getMonth() + 1, 0).getDate();
        document.getElementById('inpDtFim').value = `${ano}-${mes}-${String(ultimo).padStart(2, '0')}`;
    })();

    function isoParaBr(iso) {
        if (!iso) return '';
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    }

    function fmt(v) {
        const n = parseFloat(v ?? 0);
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    }

    function fmtKg(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtCell(v) {
        const n = parseFloat(v ?? 0);
        if (n === 0) return `<span class="td-zero">-</span>`;
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    }

    function renderTabela(rows, dtIni, dtFim) {
        let theadCols = `<th class="col-left" style="min-width:70px;">Cód.</th>
            <th class="col-left" style="min-width:200px;">Descrição</th>`;
        EMPS.forEach(e => { theadCols += `<th style="min-width:80px;">${e.label}</th>`; });
        theadCols += `<th style="min-width:80px;">KG Ref.</th>`;

        let html = `<div style="font-family:Arial,sans-serif;font-size:8pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Consumo Demanda Espuma<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>
            <div class="dem-wrap">
            <table class="dem-table">
            <thead><tr>${theadCols}</tr></thead>
            <tbody>`;

        // totalizadores
        const totais = {};
        EMPS.forEach(e => { totais[e.key] = 0; });
        let totKg = 0;

        rows.forEach(r => {
            let tds = `<td class="col-left">${r.COD_ITEM ?? ''}</td>
                <td class="col-left">${r.DESC_TECNICA ?? ''}</td>`;
            EMPS.forEach(e => {
                const n = parseFloat(r[e.key] ?? 0);
                totais[e.key] += n;
                tds += `<td>${fmtCell(n)}</td>`;
            });
            const kg = parseFloat(r.KG_REFERENCIA ?? 0);
            totKg += kg;
            tds += `<td>${fmtKg(kg)}</td>`;
            html += `<tr>${tds}</tr>`;
        });

        // total geral
        let totTds = `<td class="col-left" colspan="2"><strong>TOTAL GERAL</strong></td>`;
        EMPS.forEach(e => { totTds += `<td><strong>${fmt(totais[e.key])}</strong></td>`; });
        totTds += `<td><strong>${fmtKg(totKg)}</strong></td>`;
        html += `<tr class="row-total">${totTds}</tr>`;

        html += `</tbody></table></div></div>`;
        return html;
    }

    // ── Excel ────────────────────────────────────────────────────────────
    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni = isoParaBr(document.getElementById('inpDtIni').value).replace(/\//g, '-');
        const dtFim = isoParaBr(document.getElementById('inpDtFim').value).replace(/\//g, '-');

        const headers = ['Cód. Item', 'Descrição', ...EMPS.map(e => e.label), 'KG Ref.'];
        const linhas  = _rows.map(r => [
            r.COD_ITEM     ?? '',
            r.DESC_TECNICA ?? '',
            ...EMPS.map(e => parseFloat(r[e.key] ?? 0).toFixed(4)),
            parseFloat(r.KG_REFERENCIA ?? 0).toFixed(2),
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `consumo-demanda-espuma_${dtIni}_${dtFim}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    // ── Gerar ────────────────────────────────────────────────────────────
    btnGerar.addEventListener('click', async function () {
        const dtIniIso = document.getElementById('inpDtIni').value;
        const dtFimIso = document.getElementById('inpDtFim').value;
        if (!dtIniIso || !dtFimIso) { alert('Informe as datas de início e fim.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        printArea.innerHTML = '';
        printArea.classList.remove('visible');
        btnImpr.style.display  = 'none';
        btnExcel.style.display = 'none';

        try {
            const res  = await fetch('processo-api-relatorio-consumo-demanda-espuma', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dt_ini: isoParaBr(dtIniIso), dt_fim: isoParaBr(dtFimIso) }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                return;
            }

            _rows = Array.isArray(data.rows) ? data.rows : [];

            if (!_rows.length) {
                msgEl.innerHTML = '<span class="text-warning">Nenhum registro encontrado para o período.</span>';
                return;
            }

            spanTotal.textContent  = `${_rows.length} registro(s) encontrado(s)`;
            btnImpr.style.display  = '';
            btnExcel.style.display = '';

            printArea.innerHTML = renderTabela(_rows, isoParaBr(dtIniIso), isoParaBr(dtFimIso));
            printArea.classList.add('visible');

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${e.message}</span>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });

    btnImpr.addEventListener('click', () => window.print());
})();
</script>

<?= $render('footer') ?>
