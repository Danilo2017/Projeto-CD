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
    'pageTitle'  => 'Processo — Mov. de Estoque Rateio',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-mov-estoque',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.filial-header { background:#1f3864;color:#fff;font-weight:700;padding:5px 12px;border-radius:6px 6px 0 0;font-size:.85rem;letter-spacing:.04em; }
.filial-block { margin-bottom:18px; }
.filial-block table { width:100%;border-collapse:collapse;font-size:.82rem; }
.filial-block th { background:#e8edf5;color:#1f3864;padding:4px 10px;border:1px solid #cdd4df;text-align:center;white-space:nowrap; }
.filial-block td { padding:3px 10px;border:1px solid #dee2e6; }
.filial-block td.td-num { text-align:right;font-variant-numeric:tabular-nums; }
.row-subtotal td { background:#d0ddf0;font-weight:700; }
.row-total td { background:#1f3864;color:#fff;font-weight:700; }
.badge-tab { cursor:pointer;padding:6px 18px;border-radius:20px;border:2px solid #1f3864;color:#1f3864;background:#fff;font-size:.8rem;font-weight:600;transition:.15s; }
.badge-tab.active { background:#1f3864;color:#fff; }
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
        <h5 class="mb-3 fw-semibold"><i class="bi bi-bar-chart-steps me-1 text-primary"></i> Mov. de Estoque Rateio — RTE / RTS</h5>
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
            </div>
        </div>
    </div>

    <!-- Abas de visualização -->
    <div id="areaAbas" style="display:none;" class="mb-2 d-flex gap-2 align-items-center">
        <button class="badge-tab active" id="tabFamilia">Por Família / Filial</button>
        <button class="badge-tab" id="tabAgrupado">Agrupado por Filial</button>
        <span class="ms-3 text-muted small" id="spanTotalReg"></span>
    </div>

    <div id="printArea"></div>

</div>

<!-- Modal Detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="modalDetalheTitulo"></h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div style="overflow-x:auto;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-dark" id="modalDetalheHead"></thead>
                        <tbody id="modalDetalheTbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="text-muted small me-auto"><strong id="modalDetalheCount">0</strong> registro(s)</span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const LOGO = `<div style="text-align:left;margin-bottom:8px;"><img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png" style="height:45px;" alt="Gazin"></div>`;

    const btnGerar   = document.getElementById('btnGerar');
    const btnImpr    = document.getElementById('btnImprimir');
    const btnExcel   = document.getElementById('btnExcel');
    const msgEl      = document.getElementById('msgRelatorio');
    const printArea  = document.getElementById('printArea');
    const areaAbas   = document.getElementById('areaAbas');
    const tabFamilia = document.getElementById('tabFamilia');
    const tabAgrup   = document.getElementById('tabAgrupado');
    const spanTotal  = document.getElementById('spanTotalReg');

    let _rows    = [];
    let _arvore  = {};
    let _modal   = null;
    let _modoAtual = 'familia';

    // Pré-preenche com o mês atual
    (function () {
        const hoje = new Date();
        const ano  = hoje.getFullYear();
        const mes  = String(hoje.getMonth() + 1).padStart(2, '0');
        document.getElementById('inpDtIni').value = `${ano}-${mes}-01`;
        const ultimoDia = new Date(ano, hoje.getMonth() + 1, 0).getDate();
        document.getElementById('inpDtFim').value = `${ano}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
    })();

    function isoParaBr(iso) {
        if (!iso) return '';
        const [a, m, d] = iso.split('-');
        return `${d}/${m}/${a}`;
    }

    function fmt(v) {
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Monta árvore: filial → familia → itens
    function construirArvore(rows) {
        const arvore = {};
        rows.forEach(r => {
            const fil = r.COD_EMP;
            const fam = r.FAMILIA_ITEM || '(sem família)';
            const deb  = parseFloat(r.VALOR_DEBITO  ?? 0);
            const cred = parseFloat(r.VALOR_CREDITO ?? 0);
            const mov  = parseFloat(r.VALOR_MOVTO   ?? 0);
            if (!arvore[fil]) arvore[fil] = { familias: {}, deb: 0, cred: 0, mov: 0 };
            if (!arvore[fil].familias[fam]) arvore[fil].familias[fam] = { itens: [], deb: 0, cred: 0, mov: 0 };
            arvore[fil].familias[fam].itens.push({ cod: r.COD_ITEM ?? '', desc: r.DESC_TECNICA ?? '', masc: r.MASCARA ?? '', deb, cred, mov });
            arvore[fil].familias[fam].deb  += deb;
            arvore[fil].familias[fam].cred += cred;
            arvore[fil].familias[fam].mov  += mov;
            arvore[fil].deb  += deb;
            arvore[fil].cred += cred;
            arvore[fil].mov  += mov;
        });
        return arvore;
    }

    // ── Por Família / Filial ─────────────────────────────────────────────
    // Família = clicável → abre modal com itens
    function renderFamilia(arvore, dtIni, dtFim) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8.5pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Mov. de Estoque Rateio — RTE / RTS<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>`;

        let totDeb = 0, totCred = 0, totMov = 0;

        Object.keys(arvore).sort((a, b) => a - b).forEach(filial => {
            const fd = arvore[filial];
            let subDeb = 0, subCred = 0, subMov = 0;

            html += `<div class="filial-block">
                <div class="filial-header">Filial ${filial}</div>
                <table><thead><tr>
                    <th style="text-align:left;width:44%;">Família</th>
                    <th style="width:18%;">Débito (E)</th>
                    <th style="width:18%;">Crédito (S)</th>
                    <th style="width:20%;">Movimento</th>
                </tr></thead><tbody>`;

            Object.keys(fd.familias).sort().forEach(fam => {
                const famD = fd.familias[fam];
                const famEsc = fam.replace(/"/g, '&quot;');
                subDeb += famD.deb; subCred += famD.cred; subMov += famD.mov;

                html += `<tr data-click="familia" data-filial="${filial}" data-familia="${famEsc}"
                             style="cursor:pointer;"
                             title="Duplo clique para ver os itens">
                    <td>${fam}
                        <span style="color:#999;font-size:.72rem;margin-left:6px;">${famD.itens.length} item(ns)</span>
                    </td>
                    <td class="td-num">${fmt(famD.deb)}</td>
                    <td class="td-num">${fmt(famD.cred)}</td>
                    <td class="td-num">${fmt(famD.mov)}</td>
                </tr>`;
            });

            totDeb += subDeb; totCred += subCred; totMov += subMov;

            html += `<tr class="row-subtotal">
                <td><strong>Subtotal Filial ${filial}</strong></td>
                <td class="td-num">${fmt(subDeb)}</td>
                <td class="td-num">${fmt(subCred)}</td>
                <td class="td-num">${fmt(subMov)}</td>
            </tr></tbody></table></div>`;
        });

        html += `<div class="filial-block"><table><tbody>
            <tr class="row-total">
                <td style="width:44%;padding:4px 10px;border:1px solid #1f3864;"><strong>TOTAL GERAL</strong></td>
                <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totDeb)}</td>
                <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totCred)}</td>
                <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totMov)}</td>
            </tr>
        </tbody></table></div></div>`;
        return html;
    }

    // ── Agrupado por Filial ──────────────────────────────────────────────
    // Filial = clicável → abre modal com famílias
    function renderAgrupado(arvore, dtIni, dtFim) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8.5pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Mov. de Estoque Rateio — Agrupado por Filial<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>
            <div class="filial-block"><table>
            <thead><tr>
                <th style="text-align:left;width:44%;">Filial</th>
                <th style="width:18%;">Débito (E)</th>
                <th style="width:18%;">Crédito (S)</th>
                <th style="width:20%;">Movimento</th>
            </tr></thead><tbody>`;

        let totDeb = 0, totCred = 0, totMov = 0;

        Object.keys(arvore).sort((a, b) => a - b).forEach(filial => {
            const fd = arvore[filial];
            totDeb += fd.deb; totCred += fd.cred; totMov += fd.mov;

            html += `<tr data-click="filial" data-filial="${filial}"
                         style="cursor:pointer;background:#1f3864;color:#fff;font-weight:700;"
                         title="Duplo clique para ver as famílias">
                <td>Filial ${filial}
                    <span style="opacity:.65;font-size:.72rem;margin-left:6px;">${Object.keys(fd.familias).length} família(s)</span>
                </td>
                <td class="td-num">${fmt(fd.deb)}</td>
                <td class="td-num">${fmt(fd.cred)}</td>
                <td class="td-num">${fmt(fd.mov)}</td>
            </tr>`;
        });

        html += `<tr class="row-total">
            <td style="padding:4px 10px;border:1px solid #1f3864;"><strong>TOTAL GERAL</strong></td>
            <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totDeb)}</td>
            <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totCred)}</td>
            <td class="td-num" style="padding:4px 10px;border:1px solid #1f3864;">${fmt(totMov)}</td>
        </tr></tbody></table></div></div>`;
        return html;
    }

    // ── Modal helpers ────────────────────────────────────────────────────
    function abrirModal(titulo, headHtml, bodyHtml, count) {
        document.getElementById('modalDetalheTitulo').textContent = titulo;
        document.getElementById('modalDetalheHead').innerHTML     = headHtml;
        document.getElementById('modalDetalheTbody').innerHTML    = bodyHtml;
        document.getElementById('modalDetalheCount').textContent  = count;
        if (!_modal) _modal = new bootstrap.Modal(document.getElementById('modalDetalhe'));
        _modal.show();
    }

    function abrirDetalheItens(filial, familia) {
        const famD = _arvore[filial]?.familias[familia];
        if (!famD) return;
        const head = `<tr><th>Cód. Item</th><th>Descrição</th><th>Máscara</th>
                          <th>Débito (E)</th><th>Crédito (S)</th><th>Movimento</th></tr>`;
        const body = famD.itens.map(it => `<tr>
            <td>${it.cod}</td>
            <td>${it.desc}</td>
            <td>${it.masc}</td>
            <td class="text-end">${fmt(it.deb)}</td>
            <td class="text-end">${fmt(it.cred)}</td>
            <td class="text-end">${fmt(it.mov)}</td>
        </tr>`).join('');
        abrirModal(`Filial ${filial} — ${familia}`, head, body, famD.itens.length);
    }

    function abrirDetalheFilial(filial) {
        const fd = _arvore[filial];
        if (!fd) return;
        const head = `<tr><th>Família</th><th>Itens</th>
                          <th>Débito (E)</th><th>Crédito (S)</th><th>Movimento</th></tr>`;
        const body = Object.keys(fd.familias).sort().map(fam => {
            const f = fd.familias[fam];
            return `<tr>
                <td>${fam}</td>
                <td class="text-center">${f.itens.length}</td>
                <td class="text-end">${fmt(f.deb)}</td>
                <td class="text-end">${fmt(f.cred)}</td>
                <td class="text-end">${fmt(f.mov)}</td>
            </tr>`;
        }).join('');
        abrirModal(`Filial ${filial} — Famílias do Rateio`, head, body, Object.keys(fd.familias).length);
    }

    // Duplo clique → abre modal
    printArea.addEventListener('dblclick', function (e) {
        const tr = e.target.closest('tr[data-click]');
        if (!tr) return;
        if (tr.dataset.click === 'familia') abrirDetalheItens(tr.dataset.filial, tr.dataset.familia);
        if (tr.dataset.click === 'filial')  abrirDetalheFilial(tr.dataset.filial);
    });

    function renderizar() {
        if (!_rows.length) return;
        const dtIni = isoParaBr(document.getElementById('inpDtIni').value);
        const dtFim = isoParaBr(document.getElementById('inpDtFim').value);
        const html  = _modoAtual === 'familia'
            ? renderFamilia(_arvore, dtIni, dtFim)
            : renderAgrupado(_arvore, dtIni, dtFim);
        printArea.innerHTML = html;
        printArea.classList.add('visible');
    }

    tabFamilia.addEventListener('click', () => {
        _modoAtual = 'familia';
        tabFamilia.classList.add('active');
        tabAgrup.classList.remove('active');
        renderizar();
    });
    tabAgrup.addEventListener('click', () => {
        _modoAtual = 'agrupado';
        tabAgrup.classList.add('active');
        tabFamilia.classList.remove('active');
        renderizar();
    });

    btnGerar.addEventListener('click', async function () {
        const dtIniIso = document.getElementById('inpDtIni').value;
        const dtFimIso = document.getElementById('inpDtFim').value;
        if (!dtIniIso || !dtFimIso) { alert('Informe as datas de início e fim.'); return; }

        const dtIni = isoParaBr(dtIniIso);
        const dtFim = isoParaBr(dtFimIso);

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        printArea.innerHTML = '';
        printArea.classList.remove('visible');
        areaAbas.style.display  = 'none';
        btnImpr.style.display   = 'none';
        btnExcel.style.display  = 'none';

        try {
            const res  = await fetch('processo-api-relatorio-mov-estoque', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ dt_ini: dtIni, dt_fim: dtFim }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${data.error}</span>`;
                return;
            }

            _rows   = data.rows || [];
            _arvore = construirArvore(_rows);
            if (!_rows.length) {
                msgEl.innerHTML = '<span class="text-warning">Nenhum registro encontrado para o período.</span>';
                return;
            }

            spanTotal.textContent   = `${_rows.length} família(s) encontrada(s)`;
            areaAbas.style.display  = '';
            btnImpr.style.display   = '';
            btnExcel.style.display  = '';
            renderizar();

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${e.message}</span>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });

    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni   = isoParaBr(document.getElementById('inpDtIni').value).replace(/\//g, '-');
        const dtFim   = isoParaBr(document.getElementById('inpDtFim').value).replace(/\//g, '-');
        let linhas, headers;

        if (_modoAtual === 'agrupado') {
            headers = ['Filial', 'Família', 'Qtd. Itens', 'Débito (E)', 'Crédito (S)', 'Movimento'];
            linhas  = [];
            Object.keys(_arvore).sort((a, b) => a - b).forEach(filial => {
                const fd = _arvore[filial];
                Object.keys(fd.familias).sort().forEach(fam => {
                    const f = fd.familias[fam];
                    linhas.push([filial, fam, f.itens.length, f.deb.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), f.cred.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), f.mov.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})]);
                });
                linhas.push([`Subtotal Filial ${filial}`, '', '', fd.deb.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), fd.cred.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), fd.mov.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})]);
            });
        } else {
            headers = ['Filial', 'Família', 'Cód. Item', 'Descrição', 'Máscara', 'Débito (E)', 'Crédito (S)', 'Movimento'];
            linhas  = [];
            Object.keys(_arvore).sort((a, b) => a - b).forEach(filial => {
                const fd = _arvore[filial];
                Object.keys(fd.familias).sort().forEach(fam => {
                    fd.familias[fam].itens.forEach(it => {
                        linhas.push([filial, fam, it.cod, it.desc, it.masc, it.deb.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), it.cred.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}), it.mov.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})]);
                    });
                });
            });
        }

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `mov-estoque-rateio_${_modoAtual}_${dtIni}_${dtFim}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    });

    btnImpr.addEventListener('click', () => window.print());
})();
</script>

<?= $render('footer') ?>
