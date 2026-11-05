<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
/** @var array    $empresas */
$acessoPd = $is_admin || in_array('pd', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoPd) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'P&D — Análise Itens Criação Vendas',
    'showNavbar' => true,
    'pageActive' => 'pd-analise-itens-criacao',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:18px 22px;margin-bottom:14px; }

.aic-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.aic-card-head { background:#1565c0; color:#fff; padding:8px 14px; font-size:11px;
                 font-weight:700; text-transform:uppercase; letter-spacing:.4px;
                 display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.aic-card-head input { max-width:340px; font-size:12px !important; height:26px; padding:2px 8px !important; }
.aic-wrap      { overflow:auto; max-height:calc(100vh - 250px); }

/* Seletores com .aic-tbl + !important para vencer o design-system.css (table td { padding:10px 14px !important }) */
.aic-tbl                { border-collapse:separate; border-spacing:0; width:100%; min-width:1150px; table-layout:fixed; }
.aic-tbl thead th       { background:#e3f2fd !important; color:#1565c0 !important; padding:6px 6px !important;
                          font-size:10.5px !important; font-weight:700 !important; letter-spacing:.2px;
                          white-space:normal !important; line-height:1.25; vertical-align:bottom;
                          border-bottom:2px solid #bbdefb !important; text-align:left;
                          position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
.aic-tbl thead th.r     { text-align:right; }
.aic-tbl thead th.c     { text-align:center; cursor:default; }
.aic-tbl th .sort       { color:#90caf9; font-size:9px; margin-left:2px; }
.aic-tbl th.sorted .sort{ color:#1565c0; }
.aic-tbl tbody td       { padding:3px 6px !important; font-size:11.5px !important; line-height:1.35;
                          border-bottom:1px solid #f0f0f0 !important; white-space:nowrap;
                          overflow:hidden; text-overflow:ellipsis; }
.aic-tbl td.r           { text-align:right; font-variant-numeric:tabular-nums; }
.aic-tbl td.c           { text-align:center; }
.aic-tbl tbody tr:hover td  { background:#f5f8fe !important; }
.aic-tbl tbody tr.sem-mov td { color:#9ca3af !important; }
.aic-tbl tfoot td       { padding:6px 6px !important; font-size:11.5px !important; font-weight:700 !important;
                          background:#1e3a8a !important; color:#fff !important;
                          position:sticky; bottom:0; z-index:2; white-space:nowrap; }
.aic-tbl tfoot td.r     { text-align:right; }
.aic-msg       { text-align:center; padding:24px !important; color:#999 !important; font-size:13px !important; }
.tag-novo      { display:inline-block; width:7px; height:7px; border-radius:50%; background:#2e7d32; margin-left:5px; vertical-align:middle; }
.btn-inativar  { font-size:11px; padding:0 6px; line-height:18px; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-lightbulb me-1 text-primary"></i> Análise Itens Criação Vendas</h5>
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
                <select id="inpEmpresa" class="form-select" style="max-width:260px;font-size:.8rem;height:31px;padding-top:.25rem;padding-bottom:.25rem;">
                    <option value="">Selecione...</option>
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?= intval($emp['CODIGO']) ?>" <?= intval($emp['CODIGO']) === 1 ? 'selected' : '' ?>>
                            FL <?= htmlspecialchars($emp['CODIGO']) ?> — <?= htmlspecialchars($emp['RAZAO_SOCIAL']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="chkSemMov">
                    <label class="form-check-label" for="chkSemMov" style="font-size:.8rem;">Somente sem faturamento/pendente</label>
                </div>
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> Gerar
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

    <div class="aic-card">
        <div class="aic-card-head">
            <span>Máscaras ativas — classificação 30.% (colchões)</span>
            <input type="text" id="inpFiltro" class="form-control form-control-sm" placeholder="Localizar / filtrar (código, descrição, máscara...)">
        </div>
        <div class="aic-wrap">
            <table class="aic-tbl">
                <colgroup>
                    <col style="width:62px">   <!-- Empresa -->
                    <col style="width:14%">    <!-- Classificação -->
                    <col style="width:88px">   <!-- Data ID -->
                    <col style="width:82px">   <!-- Data Pai -->
                    <col style="width:64px">   <!-- Código -->
                    <col style="width:66px">   <!-- ID -->
                    <col>                      <!-- Descrição (resto) -->
                    <col style="width:20%">    <!-- Máscara -->
                    <col style="width:76px">   <!-- Faturado -->
                    <col style="width:76px">   <!-- Pendente -->
                    <col style="width:66px">   <!-- Assistência -->
                    <col style="width:42px">   <!-- Ação -->
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="COD_EMP">Empresa<span class="sort">⇅</span></th>
                        <th data-col="CLASSIFICACAO">Classificação<span class="sort">⇅</span></th>
                        <th data-col="DATA_ID_ORD">Data ID<span class="sort">⇅</span></th>
                        <th data-col="DATA_PAI_ORD">Data Cód. Pai<span class="sort">⇅</span></th>
                        <th data-col="COD_ITEM" data-num="1">Código<span class="sort">⇅</span></th>
                        <th data-col="ID" data-num="1">ID<span class="sort">⇅</span></th>
                        <th data-col="DESC_TECNICA">Descrição do Item<span class="sort">⇅</span></th>
                        <th data-col="MASCARA">Máscara<span class="sort">⇅</span></th>
                        <th data-col="QTDE_FATURADO" data-num="1" class="r">Qtde Faturado<span class="sort">⇅</span></th>
                        <th data-col="QTDE_PDV_PENDENTE" data-num="1" class="r">Qtde PDV Pendente<span class="sort">⇅</span></th>
                        <th data-col="ASSISTENCIA" data-num="1" class="r">Assistência<span class="sort">⇅</span></th>
                        <th class="c">Ação</th>
                    </tr>
                </thead>
                <tbody id="aicBody">
                    <tr><td colspan="12" class="aic-msg">Informe os filtros e clique em Gerar.</td></tr>
                </tbody>
                <tfoot id="aicFoot"></tfoot>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    const btnGerar  = document.getElementById('btnGerar');
    const btnExcel  = document.getElementById('btnExcel');
    const msgEl     = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg');
    const body      = document.getElementById('aicBody');
    const foot      = document.getElementById('aicFoot');
    const inpFiltro = document.getElementById('inpFiltro');
    const chkSemMov = document.getElementById('chkSemMov');

    let _rows = [];
    let _sort = { col: null, num: false, dir: 1 };

    (function () {
        const hoje = new Date();
        const pad  = n => String(n).padStart(2, '0');
        document.getElementById('inpDtIni').value = `${hoje.getFullYear()}-01-01`;
        document.getElementById('inpDtFim').value = `${hoje.getFullYear()}-${pad(hoje.getMonth() + 1)}-${pad(hoje.getDate())}`;
    })();

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    function num(v) {
        if (v === null || v === undefined || v === '') return 0;
        return parseFloat(String(v).replace(',', '.')) || 0;
    }

    function fmtQtd(v) {
        if (v === null || v === undefined || v === '') return '';
        return num(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function semMovimento(r) {
        return !num(r.QTDE_FATURADO) && !num(r.QTDE_PDV_PENDENTE);
    }

    function linhasVisiveis() {
        const termo = inpFiltro.value.trim().toLowerCase();
        let lista = _rows;

        if (chkSemMov.checked) lista = lista.filter(semMovimento);

        if (termo) {
            lista = lista.filter(r =>
                [r.CLASSIFICACAO, r.COD_ITEM, r.ID, r.DESC_TECNICA, r.MASCARA, r.DATA_ID, r.DATA_PAI]
                    .some(v => String(v ?? '').toLowerCase().includes(termo))
            );
        }

        if (_sort.col) {
            const c = _sort.col, d = _sort.dir;
            lista = [...lista].sort((a, b) => {
                const va = _sort.num ? num(a[c]) : String(a[c] ?? '');
                const vb = _sort.num ? num(b[c]) : String(b[c] ?? '');
                return (va < vb ? -1 : va > vb ? 1 : 0) * d;
            });
        }
        return lista;
    }

    function render() {
        const lista = linhasVisiveis();
        const anoAtual = String(new Date().getFullYear());

        if (!lista.length) {
            body.innerHTML = `<tr><td colspan="12" class="aic-msg">${_rows.length ? 'Nenhum item corresponde ao filtro.' : 'Nenhum item encontrado.'}</td></tr>`;
            foot.innerHTML = '';
            spanTotal.textContent = _rows.length ? `0 de ${_rows.length} registro(s)` : '';
            return;
        }

        let fat = 0, pend = 0, ass = 0;
        body.innerHTML = lista.map(r => {
            fat  += num(r.QTDE_FATURADO);
            pend += num(r.QTDE_PDV_PENDENTE);
            ass  += num(r.ASSISTENCIA);
            const novo = String(r.DATA_ID_ORD ?? '').startsWith(anoAtual) ? `<span class="tag-novo" title="Criado em ${anoAtual}"></span>` : '';
            return `<tr class="${semMovimento(r) ? 'sem-mov' : ''}">
                <td>${esc(r.COD_EMP)}</td>
                <td title="${esc(r.CLASSIFICACAO)}">${esc(r.CLASSIFICACAO)}</td>
                <td>${esc(r.DATA_ID)}${novo}</td>
                <td>${esc(r.DATA_PAI)}</td>
                <td>${esc(r.COD_ITEM)}</td>
                <td>${esc(r.ID)}</td>
                <td title="${esc(r.DESC_TECNICA)}">${esc(r.DESC_TECNICA)}</td>
                <td title="${esc(r.MASCARA)}">${esc(r.MASCARA)}</td>
                <td class="r">${fmtQtd(r.QTDE_FATURADO)}</td>
                <td class="r">${fmtQtd(r.QTDE_PDV_PENDENTE)}</td>
                <td class="r">${fmtQtd(r.ASSISTENCIA)}</td>
                <td class="c"><button class="btn btn-sm btn-outline-danger btn-inativar" data-id="${esc(r.ID)}" title="Inativar máscara em todas as pastas"><i class="bi bi-slash-circle"></i></button></td>
            </tr>`;
        }).join('');

        foot.innerHTML = `<tr>
            <td colspan="8">Total — ${lista.length} registro(s)</td>
            <td class="r">${fmtQtd(fat)}</td>
            <td class="r">${fmtQtd(pend)}</td>
            <td class="r">${fmtQtd(ass)}</td>
            <td></td>
        </tr>`;

        spanTotal.textContent = lista.length === _rows.length
            ? `${_rows.length} registro(s) listado(s)`
            : `${lista.length} de ${_rows.length} registro(s)`;
    }

    // Ordenação por coluna
    document.querySelectorAll('.aic-tbl th[data-col]').forEach(th => {
        th.addEventListener('click', function () {
            const col = this.dataset.col;
            _sort = { col, num: this.dataset.num === '1', dir: _sort.col === col ? -_sort.dir : 1 };
            document.querySelectorAll('.aic-tbl th[data-col]').forEach(h => {
                h.classList.toggle('sorted', h === this);
                h.querySelector('.sort').textContent = h === this ? (_sort.dir === 1 ? '▲' : '▼') : '⇅';
            });
            render();
        });
    });

    let _tFiltro;
    inpFiltro.addEventListener('input', () => { clearTimeout(_tFiltro); _tFiltro = setTimeout(render, 200); });
    chkSemMov.addEventListener('change', render);

    // Inativar máscara (procedure MAN_ITE_INATIVA_TODAS_PASTAS)
    body.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('.btn-inativar');
        if (!btn) return;
        const id  = btn.dataset.id;
        const row = _rows.find(r => String(r.ID) === String(id));
        const txt = row ? `${row.COD_ITEM} — ${row.MASCARA}` : id;
        if (!confirm(`Inativar a máscara ID ${id} (${txt}) em todas as pastas?`)) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        try {
            const res  = await fetch('pd-api-analise-itens-criacao-inativar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            _rows = _rows.filter(r => String(r.ID) !== String(id));
            render();
            msgEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Máscara ${esc(id)} inativada.</span>`;
        } catch (e) {
            alert('Erro ao inativar: ' + e.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-slash-circle"></i>';
        }
    });

    btnExcel.addEventListener('click', function () {
        const lista = linhasVisiveis();
        if (!lista.length) return;
        const dtIni = document.getElementById('inpDtIni').value;
        const dtFim = document.getElementById('inpDtFim').value;

        const headers = ['Empresa','Classificação','Data ID','Data Cód. Pai','Código','ID','Descrição do Item','Máscara','Qtde Faturado','Qtde PDV Pendente','Assistência'];
        const linhas  = lista.map(r => [
            r.COD_EMP, r.CLASSIFICACAO, r.DATA_ID, r.DATA_PAI, r.COD_ITEM, r.ID,
            r.DESC_TECNICA, r.MASCARA, num(r.QTDE_FATURADO), num(r.QTDE_PDV_PENDENTE), num(r.ASSISTENCIA),
        ]);

        const csv = [
            headers.join(';'),
            ...linhas.map(cols => cols.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(';'))
        ].join('\r\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `analise-itens-criacao_${dtIni}_${dtFim}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    btnGerar.addEventListener('click', async function () {
        const dtIni  = document.getElementById('inpDtIni').value;
        const dtFim  = document.getElementById('inpDtFim').value;
        const codEmp = parseInt(document.getElementById('inpEmpresa').value) || 0;

        if (!dtIni || !dtFim) { alert('Informe as datas de início e fim.'); return; }
        if (!codEmp) { alert('Selecione a empresa.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = '';
        spanTotal.textContent = '';
        btnExcel.style.display = 'none';
        body.innerHTML = '<tr><td colspan="12" class="aic-msg">Carregando...</td></tr>';
        foot.innerHTML = '';

        try {
            const res  = await fetch('pd-api-analise-itens-criacao', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dt_ini: dtIni, dt_fim: dtFim, cod_emp: codEmp }),
            });
            const data = await res.json();

            if (data.error) {
                msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(data.error)}</span>`;
                body.innerHTML = '<tr><td colspan="12" class="aic-msg">—</td></tr>';
                return;
            }

            _rows = Array.isArray(data.rows) ? data.rows : [];
            if (_rows.length) btnExcel.style.display = '';
            render();

        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger">Erro: ${esc(e.message)}</span>`;
            body.innerHTML = '<tr><td colspan="12" class="aic-msg">—</td></tr>';
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });
})();
</script>

<?= $render('footer') ?>
