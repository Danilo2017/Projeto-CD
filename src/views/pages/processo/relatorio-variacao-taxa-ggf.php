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
    'pageTitle'  => 'Processo — Variação Taxa GGF',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-variacao-taxa-ggf',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.ggf-wrap { overflow-x:auto; }
.ggf-table { width:100%;border-collapse:collapse;font-size:.78rem;white-space:nowrap; }
.ggf-table th { background:#e9ecef;color:#212529;padding:4px 8px;border:1px solid #ced4da;text-align:center;position:sticky;top:0;z-index:1; }
.ggf-table th.col-left { text-align:left; }
.ggf-table td { padding:3px 8px;border:1px solid #dee2e6;text-align:right; }
.ggf-table td.col-left { text-align:left;white-space:normal; }
.ggf-table td.td-zero { color:#bbb; }
.row-tipo td { background:#343a40;color:#fff;font-weight:700;text-align:left; }
.row-subtotal td { background:#e9ecef;font-weight:700; }
.row-total td { background:#343a40;color:#fff;font-weight:700; }
#printArea { display:none; }
#printArea.visible { display:block; }
@media print {
    @page { size: landscape; margin: 10mm; }
    body * { visibility:hidden; }
    #printArea, #printArea * { visibility:visible; }
    #printArea { position:absolute;left:0;top:0;width:100%; }
    .ggf-wrap { overflow:visible; }
}
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-percent me-1 text-primary"></i> Variação Taxa GGF</h5>
        <div class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-1" style="font-size:.85rem;">Mês / Ano</label>
                <div class="input-group" style="width:260px;">
                    <select id="selMes" class="form-select" style="font-size:.95rem;">
                        <option value="01">Janeiro</option>
                        <option value="02">Fevereiro</option>
                        <option value="03">Março</option>
                        <option value="04">Abril</option>
                        <option value="05">Maio</option>
                        <option value="06">Junho</option>
                        <option value="07">Julho</option>
                        <option value="08">Agosto</option>
                        <option value="09">Setembro</option>
                        <option value="10">Outubro</option>
                        <option value="11">Novembro</option>
                        <option value="12">Dezembro</option>
                    </select>
                    <select id="selAno" class="form-select" style="font-size:.95rem;max-width:95px;">
                        <option>2024</option>
                        <option>2025</option>
                        <option>2026</option>
                        <option>2027</option>
                    </select>
                </div>
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-primary" style="font-size:.95rem;">
                    <i class="bi bi-search"></i> Gerar
                </button>
                <button id="btnImprimir" class="btn btn-outline-secondary ms-2" style="font-size:.95rem;display:none;">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <button id="btnExcel" class="btn btn-success ms-2" style="font-size:.95rem;display:none;">
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
        { key: 'EMP_1',  label: 'Emp. 1'  },
        { key: 'EMP_2',  label: 'Emp. 2'  },
        { key: 'EMP_3',  label: 'Emp. 3'  },
        { key: 'EMP_4',  label: 'Emp. 4'  },
        { key: 'EMP_5',  label: 'Emp. 5'  },
        { key: 'EMP_7',  label: 'Emp. 7'  },
        { key: 'EMP_9',  label: 'Emp. 9'  },
        { key: 'EMP_10', label: 'Emp. 10' },
        { key: 'EMP_13', label: 'Emp. 13' },
        { key: 'EMP_14', label: 'Emp. 14' },
        { key: 'EMP_15', label: 'Emp. 15' },
        { key: 'EMP_16', label: 'Emp. 16' },
    ];

    // pré-seleciona mês anterior (mais provável ter dados)
    (function () {
        const d = new Date();
        d.setMonth(d.getMonth() - 1);
        document.getElementById('selMes').value = String(d.getMonth() + 1).padStart(2, '0');
        document.getElementById('selAno').value  = String(d.getFullYear());
    })();

    function fmt(v) {
        const n = parseFloat(v ?? 0);
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtCell(v) {
        const n = parseFloat(v ?? 0);
        if (n === 0) return `<span class="td-zero">-</span>`;
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getMesAno() {
        const mes = document.getElementById('selMes').value;
        const ano = document.getElementById('selAno').value;
        return `${ano}-${mes}`;
    }

    function mesAnoLabel(val) {
        if (!val) return '';
        const [a, m] = val.split('-');
        const nomes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return `${nomes[parseInt(m, 10) - 1]}/${a}`;
    }

    function construirGrupos(rows) {
        const grupos = {};
        rows.forEach(r => {
            const tipo = r.TIPO_CC || '(sem tipo)';
            if (!grupos[tipo]) grupos[tipo] = [];
            grupos[tipo].push(r);
        });
        return grupos;
    }

    function somaEmps(rows) {
        const totais = {};
        EMPS.forEach(e => { totais[e.key] = 0; });
        rows.forEach(r => EMPS.forEach(e => { totais[e.key] += parseFloat(r[e.key] ?? 0); }));
        return totais;
    }

    function renderTabela(grupos, mesAno) {
        const mesLabel = mesAnoLabel(mesAno);

        let theadCols = `<th class="col-left" style="min-width:60px;">Tipo CC</th>
            <th class="col-left" style="min-width:60px;">Cód.</th>
            <th class="col-left" style="min-width:200px;">Centro de Custo</th>`;
        EMPS.forEach(e => { theadCols += `<th style="min-width:70px;">${e.label}</th>`; });

        let html = `<div style="font-family:Arial,sans-serif;font-size:8pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Variação Taxa GGF — ${mesLabel}
            </div>
            <div class="ggf-wrap">
            <table class="ggf-table">
            <thead><tr>${theadCols}</tr></thead>
            <tbody>`;

        const totaisGeral = {};
        EMPS.forEach(e => { totaisGeral[e.key] = 0; });

        Object.keys(grupos).sort().forEach(tipo => {
            const linhas   = grupos[tipo];
            const subtotais = somaEmps(linhas);

            // linha separadora do tipo
            const colspan = 3 + EMPS.length;
            html += `<tr class="row-tipo"><td colspan="${colspan}">${tipo}</td></tr>`;

            linhas.forEach(r => {
                let tds = `<td class="col-left">${r.TIPO_CC ?? ''}</td>
                    <td class="col-left">${r.COD ?? ''}</td>
                    <td class="col-left">${r.CENTRO_CUSTO ?? ''}</td>`;
                EMPS.forEach(e => { tds += `<td>${fmtCell(r[e.key])}</td>`; });
                html += `<tr>${tds}</tr>`;
            });

            // subtotal do tipo
            let subTds = `<td class="col-left" colspan="3"><strong>Subtotal ${tipo}</strong></td>`;
            EMPS.forEach(e => {
                subTds += `<td><strong>${fmt(subtotais[e.key])}</strong></td>`;
                totaisGeral[e.key] += subtotais[e.key];
            });
            html += `<tr class="row-subtotal">${subTds}</tr>`;
        });

        // total geral
        let totTds = `<td class="col-left" colspan="3"><strong>TOTAL GERAL</strong></td>`;
        EMPS.forEach(e => { totTds += `<td><strong>${fmt(totaisGeral[e.key])}</strong></td>`; });
        html += `<tr class="row-total">${totTds}</tr>`;

        html += `</tbody></table></div></div>`;
        return html;
    }

    // ── Excel ────────────────────────────────────────────────────────────
    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const mesAno = getMesAno();

        const headers = ['Tipo CC', 'Cód.', 'Centro de Custo', ...EMPS.map(e => e.label)];
        const linhas  = _rows.map(r => [
            r.TIPO_CC ?? '',
            r.COD ?? '',
            r.CENTRO_CUSTO ?? '',
            ...EMPS.map(e => parseFloat(r[e.key] ?? 0).toFixed(2)),
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `variacao-taxa-ggf_${mesAno}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    // ── Gerar ────────────────────────────────────────────────────────────
    btnGerar.addEventListener('click', async function () {
        const mesAno = getMesAno();
        if (!mesAno) { alert('Informe o mês e o ano.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        printArea.innerHTML = '';
        printArea.classList.remove('visible');
        btnImpr.style.display  = 'none';
        btnExcel.style.display = 'none';

        try {
            const res  = await fetch('processo-api-relatorio-variacao-taxa-ggf', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mes_ano: mesAno }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                return;
            }

            _rows = data.rows || [];

            if (!_rows.length) {
                msgEl.innerHTML = '<span class="text-warning">Nenhum registro encontrado para o período.</span>';
                return;
            }

            spanTotal.textContent  = `${_rows.length} centro(s) de custo encontrado(s)`;
            btnImpr.style.display  = '';
            btnExcel.style.display = '';

            const grupos = construirGrupos(_rows);
            printArea.innerHTML = renderTabela(grupos, mesAno);
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
