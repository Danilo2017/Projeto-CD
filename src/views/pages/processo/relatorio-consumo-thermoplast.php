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
    'pageTitle'  => 'Processo — Consumo de Thermoplast',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-consumo-thermoplast',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.filial-header { background:#343a40;color:#fff;font-weight:700;padding:5px 12px;border-radius:6px 6px 0 0;font-size:.85rem;letter-spacing:.04em; }
.filial-block { margin-bottom:18px; }
.filial-block table { width:100%;border-collapse:collapse;font-size:.82rem; }
.filial-block th { background:#e9ecef;color:#212529;padding:4px 8px;border:1px solid #ced4da;text-align:center;white-space:nowrap; }
.filial-block td { padding:3px 8px;border:1px solid #dee2e6; }
.filial-block td.td-num { text-align:right;font-variant-numeric:tabular-nums; }
.filial-block td.td-desc { text-align:left;white-space:normal; }
.row-subtotal td { background:#e9ecef;font-weight:700; }
.row-total td { background:#343a40;color:#fff;font-weight:700; }
#printArea { display:none; }
#printArea.visible { display:block; }
@media print {
    body * { visibility:hidden; }
    #printArea, #printArea * { visibility:visible; }
    #printArea { position:absolute;left:0;top:0;width:100%; }
}
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-droplet-half me-1 text-primary"></i> Consumo de Thermoplast</h5>
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

    let _rows   = [];
    let _grupos = {};

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

    function fmt2(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmt4(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    }

    function construirGrupos(rows) {
        const g = {};
        rows.forEach(r => {
            const fil = r.EMPR_ID;
            if (!g[fil]) g[fil] = [];
            g[fil].push(r);
        });
        return g;
    }

    function renderTabela(grupos, dtIni, dtFim) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8.5pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Consumo de Thermoplast<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>`;

        let totEsp = 0, totTher = 0;

        Object.keys(grupos).sort((a, b) => a - b).forEach(filial => {
            const linhas = grupos[filial];
            let subEsp = 0, subTher = 0;

            html += `<div class="filial-block">
                <div class="filial-header">Filial ${filial}</div>
                <table><thead><tr>
                    <th style="text-align:left;width:8%;">Cód.</th>
                    <th style="text-align:left;width:32%;">Descrição</th>
                    <th style="width:12%;">Qtde Espuma</th>
                    <th style="width:12%;">Qtde Thermoplast</th>
                    <th style="width:10%;">Média</th>
                    <th style="width:13%;">Projetado 1</th>
                    <th style="width:13%;">Projetado 2</th>
                </tr></thead><tbody>`;

            linhas.forEach(r => {
                const esp  = parseFloat(r.QTDE_ESPUMA         ?? 0);
                const ther = parseFloat(r.QTDE_THERMOPLAST    ?? 0);
                const med  = parseFloat(r.MEDIA_THERMOPLAST   ?? 0);
                const pr1  = parseFloat(r.PROJETADO_THERMOPLAS  ?? 0);
                const pr2  = parseFloat(r.PROJETADO_THERMOPLAS2 ?? 0);
                subEsp  += esp;
                subTher += ther;

                html += `<tr>
                    <td>${r.COD_ITEM ?? ''}</td>
                    <td class="td-desc">${r.DESC_TECNICA ?? ''}</td>
                    <td class="td-num">${fmt2(esp)}</td>
                    <td class="td-num">${fmt2(ther)}</td>
                    <td class="td-num">${fmt4(med)}</td>
                    <td class="td-num">${fmt2(pr1)}</td>
                    <td class="td-num">${fmt2(pr2)}</td>
                </tr>`;
            });

            const subMed = subEsp > 0 ? subTher / subEsp : 0;
            totEsp  += subEsp;
            totTher += subTher;

            html += `<tr class="row-subtotal">
                <td colspan="2"><strong>Subtotal Filial ${filial}</strong></td>
                <td class="td-num"><strong>${fmt2(subEsp)}</strong></td>
                <td class="td-num"><strong>${fmt2(subTher)}</strong></td>
                <td class="td-num"><strong>${fmt4(subMed)}</strong></td>
                <td colspan="2"></td>
            </tr></tbody></table></div>`;
        });

        const totMed = totEsp > 0 ? totTher / totEsp : 0;

        html += `<div class="filial-block"><table><tbody>
            <tr class="row-total">
                <td colspan="2" style="padding:4px 8px;"><strong>TOTAL GERAL</strong></td>
                <td class="td-num" style="padding:4px 8px;"><strong>${fmt2(totEsp)}</strong></td>
                <td class="td-num" style="padding:4px 8px;"><strong>${fmt2(totTher)}</strong></td>
                <td class="td-num" style="padding:4px 8px;"><strong>${fmt4(totMed)}</strong></td>
                <td colspan="2" style="padding:4px 8px;"></td>
            </tr>
        </tbody></table></div></div>`;
        return html;
    }

    // ── Excel ────────────────────────────────────────────────────────────
    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni = isoParaBr(document.getElementById('inpDtIni').value).replace(/\//g, '-');
        const dtFim = isoParaBr(document.getElementById('inpDtFim').value).replace(/\//g, '-');

        const headers = ['Filial', 'Cód. Item', 'Descrição', 'Qtde Espuma', 'Qtde Thermoplast', 'Média', 'Projetado 1', 'Projetado 2'];
        const linhas  = _rows.map(r => [
            r.EMPR_ID      ?? '',
            r.COD_ITEM     ?? '',
            r.DESC_TECNICA ?? '',
            parseFloat(r.QTDE_ESPUMA         ?? 0).toFixed(2),
            parseFloat(r.QTDE_THERMOPLAST    ?? 0).toFixed(2),
            parseFloat(r.MEDIA_THERMOPLAST   ?? 0).toFixed(4),
            parseFloat(r.PROJETADO_THERMOPLAS  ?? 0).toFixed(2),
            parseFloat(r.PROJETADO_THERMOPLAS2 ?? 0).toFixed(2),
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `consumo-thermoplast_${dtIni}_${dtFim}.csv`;
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
            const res  = await fetch('processo-api-relatorio-consumo-thermoplast', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dt_ini: isoParaBr(dtIniIso), dt_fim: isoParaBr(dtFimIso) }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                return;
            }

            _rows   = Array.isArray(data.rows) ? data.rows : [];
            _grupos = construirGrupos(_rows);

            if (!_rows.length) {
                msgEl.innerHTML = '<span class="text-warning">Nenhum registro encontrado para o período.</span>';
                return;
            }

            spanTotal.textContent  = `${_rows.length} registro(s) encontrado(s)`;
            btnImpr.style.display  = '';
            btnExcel.style.display = '';

            const dtIni = isoParaBr(dtIniIso);
            const dtFim = isoParaBr(dtFimIso);
            printArea.innerHTML = renderTabela(_grupos, dtIni, dtFim);
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
