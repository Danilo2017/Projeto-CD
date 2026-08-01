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
    'pageTitle'  => 'Processo — Mov. de Estoque Refugo e Perda',
    'showNavbar' => true,
    'pageActive' => 'processo-relatorio-mov-estoque-refugo',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }
.filial-header { background:#343a40;color:#fff;font-weight:700;padding:5px 12px;border-radius:6px 6px 0 0;font-size:.85rem;letter-spacing:.04em; }
.filial-block { margin-bottom:18px; }
.filial-block table { width:100%;border-collapse:collapse;font-size:.82rem; }
.filial-block th { background:#e9ecef;color:#212529;padding:4px 10px;border:1px solid #ced4da;text-align:center;white-space:nowrap; }
.filial-block td { padding:3px 10px;border:1px solid #dee2e6; }
.filial-block td.td-num { text-align:right;font-variant-numeric:tabular-nums; }
.row-subtotal td { background:#e9ecef;font-weight:700; }
.row-total td { background:#343a40;color:#fff;font-weight:700; }
.badge-tab { cursor:pointer;padding:6px 18px;border-radius:20px;border:2px solid #343a40;color:#343a40;background:#fff;font-size:.8rem;font-weight:600;transition:.15s; }
.badge-tab.active { background:#343a40;color:#fff; }
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
        <h5 class="mb-3 fw-semibold"><i class="bi bi-exclamation-triangle me-1 text-danger"></i> Mov. de Estoque Refugo e Perda</h5>
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

    let _rows      = [];
    let _arvore    = {};
    let _modal     = null;
    let _modoAtual = 'familia';

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
        return parseFloat(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function construirArvore(rows) {
        const arvore = {};
        rows.forEach(r => {
            const fil = r.COD_EMP;
            const fam = r.FAMILIA_ITEM || '(sem família)';
            const ent  = parseFloat(r.ENTRADA    ?? 0);
            const sai  = parseFloat(r.SAIDA      ?? 0);
            const tot  = parseFloat(r.VALOR_TOTAL ?? 0);
            if (!arvore[fil]) arvore[fil] = { familias: {}, ent: 0, sai: 0, tot: 0 };
            if (!arvore[fil].familias[fam]) arvore[fil].familias[fam] = { itens: [], ent: 0, sai: 0, tot: 0 };
            arvore[fil].familias[fam].itens.push({ cod: r.COD_ITEM_FILHO ?? '', desc: r.DESC_ITEM_FILHO ?? '', ent, sai, tot });
            arvore[fil].familias[fam].ent += ent;
            arvore[fil].familias[fam].sai += sai;
            arvore[fil].familias[fam].tot += tot;
            arvore[fil].ent += ent;
            arvore[fil].sai += sai;
            arvore[fil].tot += tot;
        });
        return arvore;
    }

    // ── Por Família / Filial ─────────────────────────────────────────────
    function renderFamilia(arvore, dtIni, dtFim) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8.5pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Mov. de Estoque Refugo e Perda<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>`;

        let totEnt = 0, totSai = 0, totTot = 0;

        Object.keys(arvore).sort((a, b) => a - b).forEach(filial => {
            const fd = arvore[filial];
            let subEnt = 0, subSai = 0, subTot = 0;

            html += `<div class="filial-block">
                <div class="filial-header">Filial ${filial}</div>
                <table><thead><tr>
                    <th style="text-align:left;width:44%;">Família</th>
                    <th style="width:18%;">Entrada</th>
                    <th style="width:18%;">Saída</th>
                    <th style="width:20%;">Total</th>
                </tr></thead><tbody>`;

            Object.keys(fd.familias).sort().forEach(fam => {
                const famD  = fd.familias[fam];
                const famEsc = fam.replace(/"/g, '&quot;');
                subEnt += famD.ent; subSai += famD.sai; subTot += famD.tot;

                html += `<tr data-click="familia" data-filial="${filial}" data-familia="${famEsc}"
                             style="cursor:pointer;" title="Duplo clique para ver os itens">
                    <td>${fam}
                        <span style="color:#999;font-size:.72rem;margin-left:6px;">${famD.itens.length} item(ns)</span>
                    </td>
                    <td class="td-num">${fmt(famD.ent)}</td>
                    <td class="td-num">${fmt(famD.sai)}</td>
                    <td class="td-num">${fmt(famD.tot)}</td>
                </tr>`;
            });

            totEnt += subEnt; totSai += subSai; totTot += subTot;

            html += `<tr class="row-subtotal">
                <td><strong>Subtotal Filial ${filial}</strong></td>
                <td class="td-num">${fmt(subEnt)}</td>
                <td class="td-num">${fmt(subSai)}</td>
                <td class="td-num">${fmt(subTot)}</td>
            </tr></tbody></table></div>`;
        });

        html += `<div class="filial-block"><table><tbody>
            <tr class="row-total">
                <td style="width:44%;padding:4px 10px;"><strong>TOTAL GERAL</strong></td>
                <td class="td-num" style="padding:4px 10px;">${fmt(totEnt)}</td>
                <td class="td-num" style="padding:4px 10px;">${fmt(totSai)}</td>
                <td class="td-num" style="padding:4px 10px;">${fmt(totTot)}</td>
            </tr>
        </tbody></table></div></div>`;
        return html;
    }

    // ── Agrupado por Filial ──────────────────────────────────────────────
    function renderAgrupado(arvore, dtIni, dtFim) {
        let html = `<div style="font-family:Arial,sans-serif;font-size:8.5pt;">
            ${LOGO}
            <div style="text-align:center;font-weight:bold;font-size:11pt;margin-bottom:8px;">
                Mov. de Estoque Refugo e Perda — Agrupado por Filial<br>
                <span style="font-size:9pt;font-weight:normal;">Período: ${dtIni} a ${dtFim}</span>
            </div>
            <div class="filial-block"><table>
            <thead><tr>
                <th style="text-align:left;width:44%;">Filial</th>
                <th style="width:18%;">Entrada</th>
                <th style="width:18%;">Saída</th>
                <th style="width:20%;">Total</th>
            </tr></thead><tbody>`;

        let totEnt = 0, totSai = 0, totTot = 0;

        Object.keys(arvore).sort((a, b) => a - b).forEach(filial => {
            const fd = arvore[filial];
            totEnt += fd.ent; totSai += fd.sai; totTot += fd.tot;

            html += `<tr data-click="filial" data-filial="${filial}"
                         style="cursor:pointer;font-weight:700;"
                         title="Duplo clique para ver as famílias">
                <td>Filial ${filial}
                    <span style="opacity:.65;font-size:.72rem;margin-left:6px;">${Object.keys(fd.familias).length} família(s)</span>
                </td>
                <td class="td-num">${fmt(fd.ent)}</td>
                <td class="td-num">${fmt(fd.sai)}</td>
                <td class="td-num">${fmt(fd.tot)}</td>
            </tr>`;
        });

        html += `<tr class="row-total">
            <td style="padding:4px 10px;"><strong>TOTAL GERAL</strong></td>
            <td class="td-num" style="padding:4px 10px;">${fmt(totEnt)}</td>
            <td class="td-num" style="padding:4px 10px;">${fmt(totSai)}</td>
            <td class="td-num" style="padding:4px 10px;">${fmt(totTot)}</td>
        </tr></tbody></table></div></div>`;
        return html;
    }

    // ── Modal ────────────────────────────────────────────────────────────
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
        const head = `<tr><th>Cód. Item</th><th>Descrição</th><th>Entrada</th><th>Saída</th><th>Total</th></tr>`;
        const body = famD.itens.map(it => `<tr>
            <td>${it.cod}</td>
            <td>${it.desc}</td>
            <td class="text-end">${fmt(it.ent)}</td>
            <td class="text-end">${fmt(it.sai)}</td>
            <td class="text-end">${fmt(it.tot)}</td>
        </tr>`).join('');
        abrirModal(`Filial ${filial} — ${familia}`, head, body, famD.itens.length);
    }

    function abrirDetalheFilial(filial) {
        const fd = _arvore[filial];
        if (!fd) return;
        const head = `<tr><th>Família</th><th>Itens</th><th>Entrada</th><th>Saída</th><th>Total</th></tr>`;
        const body = Object.keys(fd.familias).sort().map(fam => {
            const f = fd.familias[fam];
            return `<tr>
                <td>${fam}</td>
                <td class="text-center">${f.itens.length}</td>
                <td class="text-end">${fmt(f.ent)}</td>
                <td class="text-end">${fmt(f.sai)}</td>
                <td class="text-end">${fmt(f.tot)}</td>
            </tr>`;
        }).join('');
        abrirModal(`Filial ${filial} — Famílias`, head, body, Object.keys(fd.familias).length);
    }

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

    // ── Excel ────────────────────────────────────────────────────────────
    btnExcel.addEventListener('click', function () {
        if (!_rows.length) return;
        const dtIni = isoParaBr(document.getElementById('inpDtIni').value).replace(/\//g, '-');
        const dtFim = isoParaBr(document.getElementById('inpDtFim').value).replace(/\//g, '-');
        let headers, linhas;

        if (_modoAtual === 'agrupado') {
            headers = ['Filial', 'Família', 'Qtd. Itens', 'Entrada', 'Saída', 'Total'];
            linhas  = [];
            Object.keys(_arvore).sort((a, b) => a - b).forEach(filial => {
                const fd = _arvore[filial];
                Object.keys(fd.familias).sort().forEach(fam => {
                    const f = fd.familias[fam];
                    linhas.push([filial, fam, f.itens.length, f.ent.toFixed(2), f.sai.toFixed(2), f.tot.toFixed(2)]);
                });
                linhas.push([`Subtotal Filial ${filial}`, '', '', fd.ent.toFixed(2), fd.sai.toFixed(2), fd.tot.toFixed(2)]);
            });
        } else {
            headers = ['Filial', 'Família', 'Cód. Item', 'Descrição', 'Entrada', 'Saída', 'Total'];
            linhas  = [];
            Object.keys(_arvore).sort((a, b) => a - b).forEach(filial => {
                const fd = _arvore[filial];
                Object.keys(fd.familias).sort().forEach(fam => {
                    fd.familias[fam].itens.forEach(it => {
                        linhas.push([filial, fam, it.cod, it.desc, it.ent.toFixed(2), it.sai.toFixed(2), it.tot.toFixed(2)]);
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
        a.href = url; a.download = `mov-estoque-refugo_${_modoAtual}_${dtIni}_${dtFim}.csv`;
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
        areaAbas.style.display = 'none';
        btnImpr.style.display  = 'none';
        btnExcel.style.display = 'none';

        try {
            const res  = await fetch('processo-api-relatorio-mov-estoque-refugo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dt_ini: isoParaBr(dtIniIso), dt_fim: isoParaBr(dtFimIso) }),
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

            spanTotal.textContent  = `${_rows.length} família(s) encontrada(s)`;
            areaAbas.style.display = '';
            btnImpr.style.display  = '';
            btnExcel.style.display = '';
            renderizar();

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
