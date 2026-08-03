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
    'pageTitle'  => 'Processo — Variação NFE',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-variacao-nfe',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.nfe-wrap { overflow-x:auto; }
.nfe-table { width:100%;border-collapse:collapse;font-size:.78rem;white-space:nowrap; }
.nfe-table th { background:#e9ecef;color:#212529;padding:4px 8px;border:1px solid #ced4da;text-align:center;position:sticky;top:0;z-index:1; }
.nfe-table th.col-left { text-align:left; }
.nfe-table td { padding:3px 8px;border:1px solid #dee2e6;text-align:right; }
.nfe-table td.col-left { text-align:left;white-space:normal; }
.row-total td { background:#1f3864;color:#fff;font-weight:700; }
.var-pos { color:#198754;font-weight:600; }
.var-neg { color:#dc3545;font-weight:600; }
#printArea { display:none; }
#printArea.visible { display:block; }
@media print {
    @page { size: landscape; margin: 10mm; }
    body * { visibility:hidden; }
    #printArea, #printArea * { visibility:visible; }
    #printArea { position:absolute;left:0;top:0;width:100%; }
    .nfe-wrap { overflow:visible; }
}
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-graph-up me-1 text-primary"></i> Variação NFE</h5>
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
                <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Empresa</label>
                <select id="inpEmpresa" class="form-control form-control-sm" style="max-width:160px;font-size:.8rem;">
                    <option value="">Selecione...</option>
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?= intval($emp['CODIGO']) ?>">
                            FL <?= htmlspecialchars($emp['CODIGO']) ?> — <?= htmlspecialchars($emp['RAZAO_SOCIAL']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

    (function () {
        const hoje = new Date();
        const ano  = hoje.getMonth() === 0 ? hoje.getFullYear() - 1 : hoje.getFullYear();
        const mes  = hoje.getMonth() === 0 ? 12 : hoje.getMonth();
        const mesPad = String(mes).padStart(2, '0');
        const ultimo = new Date(ano, mes, 0).getDate();
        document.getElementById('inpDtIni').value = `${ano}-${mesPad}-01`;
        document.getElementById('inpDtFim').value = `${ano}-${mesPad}-${String(ultimo).padStart(2, '0')}`;
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

    function fmtVar(v) {
        const n = parseFloat(v ?? 0);
        const f = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (n > 0) return `<span class="var-pos">+${f}%</span>`;
        if (n < 0) return `<span class="var-neg">${f}%</span>`;
        return `${f}%`;
    }

    function fmtData(v) {
        if (!v) return '-';
        return String(v).substring(0, 10);
    }

    function renderTabela(rows, dtIni, dtFim, codEmp) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Variação NFE — Empresa ${codEmp}<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>
            <div class="nfe-wrap">
            <table class="nfe-table">
            <thead><tr>
                <th>Emp.</th>
                <th class="col-left" style="min-width:80px;">Cód. Item</th>
                <th class="col-left" style="min-width:70px;">Máscara</th>
                <th class="col-left" style="min-width:200px;">Descrição</th>
                <th style="min-width:90px;">NF Anterior</th>
                <th style="min-width:90px;">Data Ant.</th>
                <th style="min-width:80px;">Qtd. Ant.</th>
                <th style="min-width:90px;">Valor Ant.</th>
                <th style="min-width:90px;">NF Atual</th>
                <th style="min-width:90px;">Data Atual</th>
                <th style="min-width:80px;">Qtd. Atual</th>
                <th style="min-width:90px;">Valor Atual</th>
                <th style="min-width:85px;">Variação %</th>
            </tr></thead>
            <tbody>`;

        rows.forEach(r => {
            html += `<tr>
                <td>${r.COD_EMP ?? ''}</td>
                <td class="col-left">${r.COD_ITEM ?? ''}</td>
                <td class="col-left">${r.MASCARA ?? ''}</td>
                <td class="col-left">${r.DESC_TECNICA ?? ''}</td>
                <td>${r.NOTA_ANTERIOR ?? '-'}</td>
                <td>${fmtData(r.DATA_ANTERIOR)}</td>
                <td>${fmt4(r.QTDE_ANTERIOR)}</td>
                <td>${fmt2(r.VALOR_ANTERIOR)}</td>
                <td>${r.NOTA_ATUAL ?? '-'}</td>
                <td>${fmtData(r.DATA_ATUAL)}</td>
                <td>${fmt4(r.QTDE_ATUAL)}</td>
                <td>${fmt2(r.VALOR_ATUAL)}</td>
                <td>${fmtVar(r.PERC_VARIACAO)}</td>
            </tr>`;
        });

        html += `</tbody></table></div></div>`;
        return html;
    }

    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni = isoParaBr(document.getElementById('inpDtIni').value).replace(/\//g, '-');
        const dtFim = isoParaBr(document.getElementById('inpDtFim').value).replace(/\//g, '-');

        const headers = ['Emp.','Cód. Item','Máscara','Descrição','NF Anterior','Data Ant.','Qtd. Ant.','Valor Ant.','NF Atual','Data Atual','Qtd. Atual','Valor Atual','Variação %'];
        const linhas  = _rows.map(r => [
            r.COD_EMP ?? '',
            r.COD_ITEM ?? '',
            r.MASCARA ?? '',
            r.DESC_TECNICA ?? '',
            r.NOTA_ANTERIOR ?? '',
            fmtData(r.DATA_ANTERIOR),
            fmt4(r.QTDE_ANTERIOR),
            fmt2(r.VALOR_ANTERIOR),
            r.NOTA_ATUAL ?? '',
            fmtData(r.DATA_ATUAL),
            fmt4(r.QTDE_ATUAL),
            fmt2(r.VALOR_ATUAL),
            parseFloat(r.PERC_VARIACAO ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%',
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `variacao-nfe_${dtIni}_${dtFim}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    btnGerar.addEventListener('click', async function () {
        const dtIniIso = document.getElementById('inpDtIni').value;
        const dtFimIso = document.getElementById('inpDtFim').value;
        const codEmp   = parseInt(document.getElementById('inpEmpresa').value) || 0;

        if (!dtIniIso || !dtFimIso) { alert('Informe as datas de início e fim.'); return; }
        if (!codEmp) { alert('Selecione a empresa.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        printArea.innerHTML = '';
        printArea.classList.remove('visible');
        btnImpr.style.display  = 'none';
        btnExcel.style.display = 'none';

        try {
            const res  = await fetch('processo-api-relatorio-variacao-nfe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dt_ini: isoParaBr(dtIniIso), dt_fim: isoParaBr(dtFimIso), cod_emp: codEmp }),
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

            printArea.innerHTML = renderTabela(_rows, isoParaBr(dtIniIso), isoParaBr(dtFimIso), codEmp);
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
