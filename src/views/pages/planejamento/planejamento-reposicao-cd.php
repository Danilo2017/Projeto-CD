<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acessoPlanejamento = $is_admin || in_array('planejamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoPlanejamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Planejamento — Reposição CD',
    'showNavbar' => true,
    'pageActive' => 'planejamento-reposicao-cd',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:14px 20px;margin-bottom:12px; }

/* Cards de resumo */
.prc-cards      { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.prc-kpi        { flex:1 1 150px; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08);
                  padding:10px 14px; cursor:pointer; border:2px solid transparent; transition:border-color .15s; }
.prc-kpi:hover  { border-color:#bbdefb; }
.prc-kpi.ativo  { border-color:#1565c0; }
.prc-kpi .v     { font-size:20px; font-weight:700; color:#1e3a8a; line-height:1.1; }
.prc-kpi .l     { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.prc-kpi.crit .v{ color:#dc3545; }
.prc-kpi.inat .v{ color:#9ca3af; }
.prc-kpi.atr .v { color:#e67700; }

/* Tabela */
.prc-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.prc-card-head { background:#1565c0; color:#fff; padding:7px 14px; font-size:11px;
                 font-weight:700; text-transform:uppercase; letter-spacing:.4px;
                 display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.prc-card-head .ctrl { display:flex; gap:6px; align-items:center; }
.prc-card-head input, .prc-card-head select { font-size:12px !important; height:26px; padding:2px 8px !important; }
.prc-wrap      { overflow:auto; max-height:calc(100vh - 330px); }

/* Seletores com .prc-tbl + !important para vencer o design-system.css */
.prc-tbl                 { border-collapse:separate; border-spacing:0; width:100%; min-width:1560px; table-layout:fixed; }
.prc-tbl thead th        { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 5px !important;
                           font-size:10px !important; font-weight:700 !important; letter-spacing:.1px;
                           white-space:normal !important; line-height:1.2; vertical-align:bottom;
                           border-bottom:2px solid #bbdefb !important; text-align:left;
                           position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
.prc-tbl thead th.r      { text-align:right; }
.prc-tbl thead th.c      { text-align:center; cursor:default; }
.prc-tbl th .sort        { color:#90caf9; font-size:9px; margin-left:2px; }
.prc-tbl th.sorted .sort { color:#1565c0; }
.prc-tbl tbody td        { padding:3px 5px !important; font-size:11px !important; line-height:1.35;
                           border-bottom:1px solid #f0f0f0 !important; white-space:nowrap;
                           overflow:hidden; text-overflow:ellipsis; }
.prc-tbl td.r            { text-align:right; font-variant-numeric:tabular-nums; }
.prc-tbl td.c            { text-align:center; }
.prc-tbl tbody tr:hover td      { background:#f5f8fe !important; }
.prc-tbl tbody tr.inativo td    { color:#dc3545 !important; }
.prc-tbl td .neg         { color:#dc3545; font-weight:700; }
.prc-tbl td.edit         { cursor:pointer; }
.prc-tbl td.edit:hover   { text-decoration:underline dotted; color:#1565c0 !important; }
.prc-tbl td.link         { cursor:pointer; color:#1565c0 !important; text-decoration:underline dotted; }
.tag-st        { display:inline-block; padding:0 6px; border-radius:4px; font-size:10px; font-weight:700; }
.tag-st.a      { background:#e8f5e9; color:#2e7d32; }
.tag-st.n      { background:#fdecea; color:#c62828; }
.tag-obs       { color:#e67700; margin-left:3px; }
.btn-acao      { font-size:10px; padding:0 5px; line-height:17px; font-weight:700; }
.prc-msg       { text-align:center; padding:24px !important; color:#999 !important; font-size:13px !important; white-space:normal !important; }

/* Modal de detalhe */
.det-tbl                { width:100%; border-collapse:collapse; font-size:12px; }
.det-tbl thead th       { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 8px !important;
                          font-size:11px !important; border-bottom:2px solid #bbdefb !important; }
.det-tbl tbody td       { padding:4px 8px !important; font-size:12px !important; border-bottom:1px solid #f0f0f0 !important; }
.det-tbl tfoot td       { padding:5px 8px !important; font-weight:700 !important; background:#f3f6fb !important; }
.det-tbl .r             { text-align:right; }
.det-tbl tr.click       { cursor:pointer; }
.det-tbl tr.click:hover td { background:#f5f8fe !important; }
.det-sub                { font-size:12px; color:#6b7280; margin-bottom:8px; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-boxes me-1 text-primary"></i> Planejamento de Reposição CD</h5>
            <span id="spanAtualizado" class="small text-muted"></span>
            <button id="btnGerar" class="btn btn-sm btn-outline-primary" title="Recalcula agora (leva cerca de 30 segundos). O sistema também recalcula sozinho a cada 30 minutos.">
                <i class="bi bi-arrow-repeat"></i> Recalcular agora
            </button>
            <button id="btnExcel" class="btn btn-sm btn-success" style="display:none;">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <span id="msgRelatorio" class="small"></span>
            <span id="spanTotalReg" class="text-muted small ms-auto"></span>
        </div>
    </div>

    <div class="prc-cards" id="prcCards" style="display:none">
        <div class="prc-kpi ativo" data-f="todos"><div class="v" id="kTotal">0</div><div class="l">Itens</div></div>
        <div class="prc-kpi crit"  data-f="critico"><div class="v" id="kCrit">0</div><div class="l">Autonomia ≤ 15 dias</div></div>
        <div class="prc-kpi"       data-f="semaut"><div class="v" id="kSemAut">0</div><div class="l">Sem média / autonomia</div></div>
        <div class="prc-kpi atr"   data-f="atrasado"><div class="v" id="kAtr">0</div><div class="l">Entrega atrasada</div></div>
        <div class="prc-kpi inat"  data-f="inativo"><div class="v" id="kInat">0</div><div class="l">Inativos</div></div>
    </div>

    <div class="prc-card">
        <div class="prc-card-head">
            <span>Itens do CD — ordenado por autonomia (clique em Status, Planejador ou Estoque Seg. para alterar)</span>
            <div class="ctrl">
                <select id="selPlanejador" class="form-select form-select-sm" style="width:200px">
                    <option value="">Todos os planejadores</option>
                </select>
                <input type="text" id="inpFiltro" class="form-control form-control-sm" style="width:300px" placeholder="Localizar / filtrar (código, descrição, máscara...)">
            </div>
        </div>
        <div class="prc-wrap">
            <table class="prc-tbl">
                <colgroup>
                    <col style="width:58px">  <!-- Código -->
                    <col>                     <!-- Descrição -->
                    <col style="width:58px">  <!-- ID -->
                    <col style="width:13%">   <!-- Máscara -->
                    <col style="width:62px">  <!-- Status -->
                    <col style="width:120px"> <!-- Planejador -->
                    <col style="width:62px">  <!-- Estoque Seg. -->
                    <col style="width:64px">  <!-- Demanda Filiais -->
                    <col style="width:62px">  <!-- Média Trim. -->
                    <col style="width:62px">  <!-- Cons. Mês -->
                    <col style="width:64px">  <!-- Autonomia -->
                    <col style="width:62px">  <!-- Saldo CD -->
                    <col style="width:62px">  <!-- Saldo Filiais -->
                    <col style="width:58px">  <!-- Pedido -->
                    <col style="width:74px">  <!-- Inclusão -->
                    <col style="width:74px">  <!-- Entrega -->
                    <col style="width:62px">  <!-- Saldo Pedido -->
                    <col style="width:62px">  <!-- Carteira OCL -->
                    <col style="width:52px">  <!-- Lote Min. -->
                    <col style="width:52px">  <!-- Lote Mult. -->
                    <col style="width:46px">  <!-- Lead Time -->
                    <col style="width:74px">  <!-- Data Limite -->
                    <col style="width:78px">  <!-- Ações -->
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="COD_ITEM" data-num="1">Código<span class="sort">⇅</span></th>
                        <th data-col="DESC_ITEM">Descrição do Item<span class="sort">⇅</span></th>
                        <th data-col="MASC_ID" data-num="1">ID<span class="sort">⇅</span></th>
                        <th data-col="MASCARA">Máscara<span class="sort">⇅</span></th>
                        <th data-col="TIPO_PRODUTO">Status<span class="sort">⇅</span></th>
                        <th data-col="PLANEJADOR">Planejador<span class="sort">⇅</span></th>
                        <th data-col="ESTQ_SEG" data-num="1" class="r">Estoque Seg.<span class="sort">⇅</span></th>
                        <th data-col="DEMANDA" data-num="1" class="r">Demanda Filiais<span class="sort">⇅</span></th>
                        <th data-col="MEDIA_TRIMESTRAL" data-num="1" class="r">Média Trim. FL<span class="sort">⇅</span></th>
                        <th data-col="MEDIA_MENSAL" data-num="1" class="r">Cons. Mês FL<span class="sort">⇅</span></th>
                        <th data-col="AUTONOMIA" data-num="1" class="r">Autonomia (dias)<span class="sort">⇅</span></th>
                        <th data-col="SALDO_CD" data-num="1" class="r">Saldo CD<span class="sort">⇅</span></th>
                        <th data-col="SALDO_FILIAL" data-num="1" class="r">Saldo Filiais<span class="sort">⇅</span></th>
                        <th data-col="PEDIDO" data-num="1">Pedido<span class="sort">⇅</span></th>
                        <th data-col="INCLUSAO_ORD">Inclusão<span class="sort">⇅</span></th>
                        <th data-col="ENTREGA_ORD">Entrega<span class="sort">⇅</span></th>
                        <th data-col="SALDO_PEDIDO" data-num="1" class="r">Saldo Pedido<span class="sort">⇅</span></th>
                        <th data-col="CARTEIRA_OCL" data-num="1" class="r">Carteira OCL<span class="sort">⇅</span></th>
                        <th data-col="LOTE_MIN" data-num="1" class="r">Lote Min.<span class="sort">⇅</span></th>
                        <th data-col="LOTE_MULT" data-num="1" class="r">Lote Mult.<span class="sort">⇅</span></th>
                        <th data-col="LEAD_TIME" data-num="1" class="r">Lead Time<span class="sort">⇅</span></th>
                        <th data-col="DATA_LIMITE_ORD">Data Limite<span class="sort">⇅</span></th>
                        <th class="c">Ações</th>
                    </tr>
                </thead>
                <tbody id="prcBody">
                    <tr><td colspan="23" class="prc-msg"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal genérico (detalhes e alterações) -->
<div class="modal fade" id="mdl" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable" id="mdlDialog">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#1565c0;color:#fff">
                <h6 class="modal-title fw-semibold" id="mdlTitulo"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="mdlCorpo"></div>
            <div class="modal-footer py-2" id="mdlRodape"></div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const API = {
        listar:       'planejamento-api-rep-cd-listar',
        recalcular:   'planejamento-api-rep-cd-recalcular',
        detalhe:      'planejamento-api-rep-cd-detalhe',
        planejadores: 'planejamento-api-rep-cd-planejadores',
        alterar:      'planejamento-api-rep-cd-alterar',
    };

    const btnGerar  = document.getElementById('btnGerar');
    const btnExcel  = document.getElementById('btnExcel');
    const msgEl     = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg');
    const body      = document.getElementById('prcBody');
    const inpFiltro = document.getElementById('inpFiltro');
    const selPlan   = document.getElementById('selPlanejador');

    let _rows   = [];
    let _sort   = { col: null, num: false, dir: 1 };
    let _kpi    = 'todos';
    let _planejadores = null;

    // ── Utilitários ────────────────────────────────────────────────
    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }
    function num(v) {
        if (v === null || v === undefined || v === '') return 0;
        return parseFloat(String(v).replace(',', '.')) || 0;
    }
    function vazio(v) { return v === null || v === undefined || v === ''; }
    function fq(v) { return vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 }); }

    async function post(url, dados) {
        const res  = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados || {}),
        });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    const isInativo  = r => r.TIPO_PRODUTO === 'N';
    const isCritico  = r => !vazio(r.AUTONOMIA) && num(r.AUTONOMIA) <= 15;
    const isSemAut   = r => vazio(r.AUTONOMIA);
    const isAtrasado = r => String(r.ENTREGA_ATRASADA) === '1';
    const KPI = { todos: () => true, critico: isCritico, semaut: isSemAut, atrasado: isAtrasado, inativo: isInativo };

    // ── Filtro / ordenação ─────────────────────────────────────────
    function linhasVisiveis() {
        const termo = inpFiltro.value.trim().toLowerCase();
        const plan  = selPlan.value;
        let lista = _rows.filter(KPI[_kpi]);

        if (plan) lista = lista.filter(r => (r.PLANEJADOR || '') === plan);
        if (termo) {
            lista = lista.filter(r =>
                [r.COD_ITEM, r.DESC_ITEM, r.MASC_ID, r.MASCARA, r.PLANEJADOR, r.PEDIDO, r.OBS]
                    .some(v => String(v ?? '').toLowerCase().includes(termo))
            );
        }
        if (_sort.col) {
            const c = _sort.col, d = _sort.dir;
            lista = [...lista].sort((a, b) => {
                // vazios sempre no fim
                const ea = vazio(a[c]), eb = vazio(b[c]);
                if (ea !== eb) return ea ? 1 : -1;
                const va = _sort.num ? num(a[c]) : String(a[c] ?? '');
                const vb = _sort.num ? num(b[c]) : String(b[c] ?? '');
                return (va < vb ? -1 : va > vb ? 1 : 0) * d;
            });
        }
        return lista;
    }

    function atualizarKpis() {
        document.getElementById('kTotal').textContent = _rows.length.toLocaleString('pt-BR');
        document.getElementById('kCrit').textContent  = _rows.filter(isCritico).length.toLocaleString('pt-BR');
        document.getElementById('kSemAut').textContent= _rows.filter(isSemAut).length.toLocaleString('pt-BR');
        document.getElementById('kAtr').textContent   = _rows.filter(isAtrasado).length.toLocaleString('pt-BR');
        document.getElementById('kInat').textContent  = _rows.filter(isInativo).length.toLocaleString('pt-BR');
    }

    function preencherPlanejadores() {
        const atual = selPlan.value;
        const nomes = [...new Set(_rows.map(r => r.PLANEJADOR).filter(Boolean))].sort();
        selPlan.innerHTML = '<option value="">Todos os planejadores</option>' +
            nomes.map(n => `<option value="${esc(n)}"${n === atual ? ' selected' : ''}>${esc(n)}</option>`).join('');
    }

    // ── Render ─────────────────────────────────────────────────────
    function linhaHtml(r) {
        const aut = vazio(r.AUTONOMIA) ? '' :
            (isCritico(r) ? `<span class="neg">${fq(r.AUTONOMIA)}</span>` : fq(r.AUTONOMIA));
        const status = isInativo(r)
            ? '<span class="tag-st n">INATIVO</span>'
            : '<span class="tag-st a">ATIVO</span>';
        const obs = r.OBS ? `<i class="bi bi-chat-left-text-fill tag-obs" title="${esc(r.OBS)}"></i>` : '';
        const entrega = isAtrasado(r) ? `<span class="neg">${esc(r.ENTREGA)}</span>` : esc(r.ENTREGA);

        return `<tr data-id="${esc(r.ID)}" class="${isInativo(r) ? 'inativo' : ''}">
            <td>${esc(r.COD_ITEM)}</td>
            <td title="${esc(r.DESC_ITEM)}">${esc(r.DESC_ITEM)}</td>
            <td>${esc(r.MASC_ID)}</td>
            <td title="${esc(r.MASCARA)}">${esc(r.MASCARA)}</td>
            <td class="edit" data-act="status" title="${esc(r.OBS || 'Clique para alterar o status')}">${status}${obs}</td>
            <td class="edit" data-act="planejador" title="${esc(r.PLANEJADOR || '')} — clique para alterar">${esc(r.PLANEJADOR)}</td>
            <td class="r edit" data-act="estoque" title="Clique para alterar">${fq(r.ESTQ_SEG)}</td>
            <td class="r link" data-act="demandas" title="Demanda por filial">${fq(r.DEMANDA)}</td>
            <td class="r">${fq(r.MEDIA_TRIMESTRAL)}</td>
            <td class="r">${fq(r.MEDIA_MENSAL)}</td>
            <td class="r">${aut}</td>
            <td class="r">${fq(r.SALDO_CD)}</td>
            <td class="r link" data-act="saldo" title="Saldo por filial">${fq(r.SALDO_FILIAL)}</td>
            <td class="${r.PEDIDO ? 'link' : ''}" data-act="${r.PEDIDO ? 'pedidos' : ''}">${esc(r.PEDIDO)}</td>
            <td>${esc(r.INCLUSAO)}</td>
            <td>${entrega}</td>
            <td class="r">${fq(r.SALDO_PEDIDO)}</td>
            <td class="r">${fq(r.CARTEIRA_OCL)}</td>
            <td class="r">${fq(r.LOTE_MIN)}</td>
            <td class="r">${fq(r.LOTE_MULT)}</td>
            <td class="r">${fq(r.LEAD_TIME)}</td>
            <td>${esc(r.DATA_LIMITE)}</td>
            <td class="c">
                <button class="btn btn-outline-primary btn-acao" data-act="demandas" title="Demandas por filial">D</button>
                <button class="btn btn-outline-primary btn-acao" data-act="saldo" title="Saldo das filiais">S</button>
                <button class="btn btn-outline-primary btn-acao" data-act="pedidos" title="Pedidos de compra">P</button>
            </td>
        </tr>`;
    }

    function render() {
        const lista = linhasVisiveis();
        if (!lista.length) {
            body.innerHTML = `<tr><td colspan="23" class="prc-msg">${_rows.length ? 'Nenhum item corresponde ao filtro.' : 'Nenhum item retornado.'}</td></tr>`;
        } else {
            body.innerHTML = lista.map(linhaHtml).join('');
        }
        spanTotal.textContent = lista.length === _rows.length
            ? `${_rows.length.toLocaleString('pt-BR')} registro(s)`
            : `${lista.length.toLocaleString('pt-BR')} de ${_rows.length.toLocaleString('pt-BR')} registro(s)`;
    }

    function atualizarLinha(r) {
        const tr = body.querySelector(`tr[data-id="${CSS.escape(String(r.ID))}"]`);
        if (tr) tr.outerHTML = linhaHtml(r);
        atualizarKpis();
    }

    // ── Carregar snapshot / Recalcular ─────────────────────────────
    function aplicarDados(data) {
        _rows = Array.isArray(data.rows) ? data.rows : [];
        const c = data.controle || {};
        const spanAt = document.getElementById('spanAtualizado');
        if (c.STATUS === 'ERRO') {
            spanAt.innerHTML = `<span class="text-danger" title="${esc(c.ERRO)}"><i class="bi bi-exclamation-triangle"></i> Falha no último cálculo (${esc(c.DT_INICIO)})</span>`;
        } else if (c.DT_FIM) {
            spanAt.innerHTML = `<i class="bi bi-clock-history"></i> Atualizado em <b>${esc(c.DT_FIM)}</b> · ${esc(c.SEGUNDOS)}s · ${esc(c.USUARIO === 'JOB' ? 'automático' : c.USUARIO)}`;
        } else {
            spanAt.textContent = 'Ainda não calculado';
        }
        preencherPlanejadores();
        atualizarKpis();
        document.getElementById('prcCards').style.display = '';
        btnExcel.style.display = _rows.length ? '' : 'none';
        render();
    }

    async function carregar() {
        try {
            aplicarDados(await post(API.listar, {}));
        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
            body.innerHTML = '<tr><td colspan="23" class="prc-msg">—</td></tr>';
        }
    }

    btnGerar.addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Recalculando...';
        msgEl.innerHTML = '<span class="text-muted">Recalculando, leva cerca de 30 segundos...</span>';
        try {
            aplicarDados(await post(API.recalcular, {}));
            msgEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Recalculado.</span>';
        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-arrow-repeat"></i> Recalcular agora';
        }
    });

    carregar();

    // ── Filtros, ordenação, KPIs ───────────────────────────────────
    let _tFiltro;
    inpFiltro.addEventListener('input', () => { clearTimeout(_tFiltro); _tFiltro = setTimeout(render, 200); });
    selPlan.addEventListener('change', render);

    document.querySelectorAll('.prc-kpi').forEach(k => k.addEventListener('click', function () {
        _kpi = this.dataset.f;
        document.querySelectorAll('.prc-kpi').forEach(x => x.classList.toggle('ativo', x === this));
        render();
    }));

    document.querySelectorAll('.prc-tbl th[data-col]').forEach(th => {
        th.addEventListener('click', function () {
            const col = this.dataset.col;
            _sort = { col, num: this.dataset.num === '1', dir: _sort.col === col ? -_sort.dir : 1 };
            document.querySelectorAll('.prc-tbl th[data-col]').forEach(h => {
                h.classList.toggle('sorted', h === this);
                h.querySelector('.sort').textContent = h === this ? (_sort.dir === 1 ? '▲' : '▼') : '⇅';
            });
            render();
        });
    });

    // ── Modal ──────────────────────────────────────────────────────
    const mdlEl = document.getElementById('mdl');
    let _mdl = null;
    function modal(titulo, corpo, rodape = '', tamanho = 'modal-lg') {
        document.getElementById('mdlDialog').className = `modal-dialog modal-dialog-scrollable ${tamanho}`;
        document.getElementById('mdlTitulo').innerHTML = titulo;
        document.getElementById('mdlCorpo').innerHTML  = corpo;
        const rod = document.getElementById('mdlRodape');
        rod.innerHTML = rodape;
        rod.style.display = rodape ? '' : 'none';
        if (!_mdl) _mdl = new bootstrap.Modal(mdlEl);
        _mdl.show();
    }
    const carregando = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</div>';
    const erroHtml   = e => `<div class="text-danger py-3"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</div>`;
    const subtitulo  = r => `<div class="det-sub"><b>${esc(r.COD_ITEM)}</b> — ${esc(r.DESC_ITEM)}${r.MASCARA ? ` · <b>ID ${esc(r.MASC_ID)}</b> ${esc(r.MASCARA)}` : ''}</div>`;

    function tabela(cols, rows, opts = {}) {
        if (!rows.length) return '<div class="text-muted py-3">Nenhum registro encontrado.</div>';
        const tot = {};
        const thead = cols.map(c => `<th class="${c.r ? 'r' : ''}">${c.t}</th>`).join('');
        const tbody = rows.map((r, i) => {
            cols.forEach(c => { if (c.s) tot[c.k] = (tot[c.k] || 0) + num(r[c.k]); });
            return `<tr${opts.click ? ` class="click" data-i="${i}"` : ''}>` +
                cols.map(c => `<td class="${c.r ? 'r' : ''}">${c.f ? c.f(r) : (c.r ? fq(r[c.k]) : esc(r[c.k]))}</td>`).join('') + '</tr>';
        }).join('');
        const temTot = cols.some(c => c.s);
        const tfoot = temTot ? '<tfoot><tr>' + cols.map((c, i) =>
            `<td class="${c.r ? 'r' : ''}">${c.s ? fq(tot[c.k]) : (i === 0 ? 'Total' : '')}</td>`).join('') + '</tr></tfoot>' : '';
        return `<table class="det-tbl"><thead><tr>${thead}</tr></thead><tbody>${tbody}</tbody>${tfoot}</table>`;
    }

    async function abrirDemandas(r) {
        modal('Demanda por Filial', subtitulo(r) + carregando, '', '');
        try {
            const d = await post(API.detalhe, { tipo: 'demandas', item_id: r.ITEM_ID, mascara: r.MASCARA });
            document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + tabela([
                { t: 'Filial', k: 'FILIAL' },
                { t: 'Demanda', k: 'DEMANDA', r: 1, s: 1 },
            ], d.rows || []);
        } catch (e) { document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + erroHtml(e); }
    }

    async function abrirSaldo(r) {
        modal('Saldo das Filiais', subtitulo(r) + carregando);
        try {
            const d = await post(API.detalhe, {
                tipo: 'saldo', item_id: r.ITEM_ID,
                item_empr_id: r.ITEM_EMPR_ID, masc_id: r.MASC_ID, mascara: r.MASCARA,
            });
            document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + tabela([
                { t: 'Filial', k: 'FILIAL' },
                { t: 'Média Trim.', k: 'MEDIA_TRIM', r: 1, s: 1 },
                { t: 'Média Mensal', k: 'MEDIA_MENSAL', r: 1, s: 1 },
                { t: 'Saldo', k: 'SALDO', r: 1, s: 1 },
                { t: 'Qtde Pedido (15d)', k: 'QTDE_PEDIDO', r: 1, s: 1 },
                { t: 'Pedido Atend. (15d)', k: 'PEDIDO_ATEND', r: 1, s: 1 },
            ], d.rows || []);
        } catch (e) { document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + erroHtml(e); }
    }

    async function abrirPedidos(r) {
        modal('Pedidos de Compra na Filial 1', subtitulo(r) + carregando, '', 'modal-xl');
        try {
            const d = await post(API.detalhe, { tipo: 'pedidos', item_id: r.ITEM_ID, masc_id: r.MASC_ID });
            const rows = d.rows || [];
            const corpo = document.getElementById('mdlCorpo');
            corpo.innerHTML = subtitulo(r) + tabela([
                { t: 'Pedido', k: 'PEDIDO', f: p => `<a href="#" onclick="return false">${esc(p.PEDIDO)}</a>` },
                { t: 'Código', k: 'COD_ITEM' },
                { t: 'ID', k: 'MASC_ID' },
                { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1, s: 1 },
                { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1, s: 1 },
                { t: 'UM', k: 'UM' },
                { t: 'Data Entrega', k: 'DT_ENTREGA', f: p => String(p.ATRASADO) === '1' ? `<span class="text-danger fw-bold">${esc(p.DT_ENTREGA)}</span>` : esc(p.DT_ENTREGA) },
                { t: 'Fornecedor', k: 'FORNECEDOR' },
                { t: 'Comprador', k: 'COMPRADOR' },
            ], rows, { click: true }) + (rows.length ? '<div class="small text-muted mt-2">Clique no pedido para ver todos os itens pendentes dele.</div>' : '');
            corpo.querySelectorAll('tr.click').forEach(tr => tr.addEventListener('click', () => abrirPedidoItens(rows[tr.dataset.i], r)));
        } catch (e) { document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + erroHtml(e); }
    }

    async function abrirPedidoItens(p, r) {
        const voltar = `<button class="btn btn-sm btn-outline-secondary" id="btnVoltarPed"><i class="bi bi-arrow-left"></i> Voltar</button>`;
        modal(`Itens Pendentes do Pedido ${esc(p.PEDIDO)}`, carregando, voltar, 'modal-xl');
        document.getElementById('btnVoltarPed').onclick = () => abrirPedidos(r);
        try {
            const d = await post(API.detalhe, { tipo: 'pedido_itens', pedc_id: p.PEDC_ID });
            document.getElementById('mdlCorpo').innerHTML =
                `<div class="det-sub">Fornecedor: <b>${esc(p.FORNECEDOR)}</b> · Comprador: ${esc(p.COMPRADOR)}</div>` +
                tabela([
                    { t: 'Cód. Item', k: 'COD_ITEM' },
                    { t: 'Desc. Item', k: 'DESC_ITEM' },
                    { t: 'ID', k: 'MASC_ID' },
                    { t: 'Máscara', k: 'MASCARA' },
                    { t: 'Inclusão', k: 'DT_INCLUSAO' },
                    { t: 'Entrega', k: 'DT_ENTREGA' },
                    { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1 },
                    { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1 },
                    { t: 'UM', k: 'UM' },
                ], d.rows || []);
        } catch (e) { document.getElementById('mdlCorpo').innerHTML = erroHtml(e); }
    }

    // ── Alterações ─────────────────────────────────────────────────
    function rodapeSalvar() {
        return `<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" id="btnSalvar"><i class="bi bi-check-lg"></i> Salvar</button>`;
    }
    async function salvar(dados, aplicar) {
        const btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
        try {
            const d = await post(API.alterar, dados);
            aplicar(d);
            _mdl.hide();
            msgEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> ${esc(d.message || 'Registro salvo.')}</span>`;
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvar';
            alert('Erro: ' + e.message);
        }
    }

    function abrirEstoque(r) {
        modal('Alterar Estoque de Segurança', subtitulo(r) + `
            <label class="form-label fw-semibold small mb-1">Estoque Seg.</label>
            <input type="number" id="inpSeg" class="form-control form-control-sm" min="0" max="9999999" step="1" value="${esc(num(r.ESTQ_SEG))}" style="max-width:180px">
            <div class="small text-muted mt-2">Grava também no plano 21 (TGAZIN_PLANOS_PLAN).</div>`,
            rodapeSalvar(), '');
        const inp = document.getElementById('inpSeg');
        setTimeout(() => inp.select(), 300);
        document.getElementById('btnSalvar').onclick = () => {
            const v = inp.value === '' ? 0 : parseInt(inp.value, 10);
            if (isNaN(v) || v < 0 || v > 9999999) { alert('Informe um valor entre 0 e 9.999.999.'); return; }
            salvar({ tipo: 'estoque', id: r.ID, estq_seg: v },
                d => { r.ESTQ_SEG = d.estq_seg; atualizarLinha(r); });
        };
    }

    function abrirStatus(r) {
        modal('Alterar Status', subtitulo(r) + `
            <label class="form-label fw-semibold small mb-1">Status</label>
            <select id="selStatus" class="form-select form-select-sm mb-3" style="max-width:180px">
                <option value="A"${isInativo(r) ? '' : ' selected'}>ATIVO</option>
                <option value="N"${isInativo(r) ? ' selected' : ''}>INATIVO</option>
            </select>
            <label class="form-label fw-semibold small mb-1">Observação</label>
            <input type="text" id="inpObs" class="form-control form-control-sm text-uppercase" maxlength="100" value="${esc(r.OBS || '')}">
            <div class="small text-muted mt-2">Altera o tipo de produto na engenharia de todas as empresas do grupo 1003.</div>`,
            rodapeSalvar(), '');
        document.getElementById('btnSalvar').onclick = () => {
            salvar({
                tipo: 'status', id: r.ID,
                status: document.getElementById('selStatus').value,
                obs: document.getElementById('inpObs').value,
            }, d => { r.TIPO_PRODUTO = d.tipo_produto; r.OBS = d.obs || null; atualizarLinha(r); });
        };
    }

    async function abrirPlanejador(r) {
        modal('Alterar Planejador', subtitulo(r) + carregando, rodapeSalvar(), '');
        const btn = document.getElementById('btnSalvar');
        btn.disabled = true;
        try {
            if (!_planejadores) _planejadores = (await post(API.planejadores, {})).rows || [];
            document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + `
                <div class="small mb-2">Atual: <b>${esc(r.PLANEJADOR || '—')}</b></div>
                <label class="form-label fw-semibold small mb-1">Novo planejador</label>
                <select id="selNovoPlan" class="form-select form-select-sm">
                    <option value="">Selecione...</option>
                    ${_planejadores.filter(p => p.NOME !== r.PLANEJADOR)
                        .map(p => `<option value="${esc(p.ID)}">${esc(p.NOME)}</option>`).join('')}
                </select>`;
            btn.disabled = false;
            btn.onclick = () => {
                const pid = document.getElementById('selNovoPlan').value;
                if (!pid) { alert('Selecione o planejador.'); return; }
                salvar({ tipo: 'planejador', id: r.ID, planejador_id: pid },
                    d => { r.PLANEJADOR = d.planejador; atualizarLinha(r); preencherPlanejadores(); });
            };
        } catch (e) { document.getElementById('mdlCorpo').innerHTML = subtitulo(r) + erroHtml(e); }
    }

    const ACOES = {
        demandas: abrirDemandas, saldo: abrirSaldo, pedidos: abrirPedidos,
        estoque: abrirEstoque, status: abrirStatus, planejador: abrirPlanejador,
    };

    body.addEventListener('click', function (ev) {
        const alvo = ev.target.closest('[data-act]');
        if (!alvo || !alvo.dataset.act) return;
        const tr = alvo.closest('tr[data-id]');
        const r  = tr && _rows.find(x => String(x.ID) === tr.dataset.id);
        if (r && ACOES[alvo.dataset.act]) ACOES[alvo.dataset.act](r);
    });

    // ── Excel ──────────────────────────────────────────────────────
    btnExcel.addEventListener('click', function () {
        const lista = linhasVisiveis();
        if (!lista.length) return;
        const cols = [
            ['Código','COD_ITEM'], ['Descrição do Item','DESC_ITEM'], ['ID','MASC_ID'], ['Máscara','MASCARA'],
            ['Status', r => isInativo(r) ? 'INATIVO' : 'ATIVO'], ['Observação','OBS'], ['Planejador','PLANEJADOR'],
            ['Estoque Seg.','ESTQ_SEG'], ['Demanda Filiais','DEMANDA'], ['Média Trim. FL','MEDIA_TRIMESTRAL'],
            ['Cons. Mês FL','MEDIA_MENSAL'], ['Autonomia','AUTONOMIA'], ['Saldo CD','SALDO_CD'],
            ['Saldo Filiais','SALDO_FILIAL'], ['Pedido','PEDIDO'], ['Inclusão','INCLUSAO'], ['Entrega','ENTREGA'],
            ['Saldo Pedido','SALDO_PEDIDO'], ['Carteira OCL','CARTEIRA_OCL'], ['Lote Min.','LOTE_MIN'],
            ['Lote Mult.','LOTE_MULT'], ['Lead Time','LEAD_TIME'], ['Data Limite','DATA_LIMITE'],
        ];
        const csv = [
            cols.map(c => c[0]).join(';'),
            ...lista.map(r => cols.map(([, k]) => {
                const v = typeof k === 'function' ? k(r) : r[k];
                return `"${(v ?? '').toString().replace(/"/g, '""')}"`;
            }).join(';'))
        ].join('\r\n');

        const hoje = new Date().toISOString().slice(0, 10);
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = `planejamento-reposicao-cd_${hoje}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });
})();
</script>

<?= $render('footer') ?>
