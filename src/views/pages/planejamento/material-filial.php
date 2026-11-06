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
    'pageTitle'  => 'Planejamento — Planej. Material por Filial',
    'showNavbar' => true,
    'pageActive' => 'planejamento-material-filial',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:14px 20px;margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }

/* Multiseleção de empresas */
.ms            { position:relative; }
.ms-btn        { width:100%; text-align:left; font-size:.8rem; height:31px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ms-pop        { display:none; position:absolute; z-index:1050; top:100%; left:0; min-width:100%; width:max-content; max-width:420px;
                 background:#fff; border:1px solid #d0d7e2; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,.12); padding:6px; }
.ms.open .ms-pop { display:block; }
.ms-list       { max-height:280px; overflow:auto; font-size:.78rem; }
.ms-list label { display:flex; gap:6px; padding:2px 4px; border-radius:4px; cursor:pointer; }
.ms-list label:hover { background:#f1f5fb; }
.ms-acts       { display:flex; justify-content:space-between; padding-top:4px; border-top:1px solid #eef1f6; margin-top:4px; }
.ms-acts a     { font-size:.75rem; cursor:pointer; }
/* Multiseleção dentro da barra azul da tabela (planejadores) */
.ms-head .ms-btn { height:26px; font-size:12px !important; padding:2px 24px 2px 8px !important; }
.ms-head .ms-pop { right:0; left:auto; color:#212529; text-transform:none; font-weight:400; letter-spacing:0; }
.ms-head .ms-list { max-height:360px; }

/* Cards */
.pm-cards      { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.pm-kpi        { flex:1 1 140px; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; cursor:pointer; border:2px solid transparent; }
.pm-kpi.ativo  { border-color:#1565c0; }
.pm-kpi .v     { font-size:20px; font-weight:700; color:#1e3a8a; line-height:1.1; }
.pm-kpi .l     { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.pm-kpi.crit .v{ color:#dc3545; } .pm-kpi.inat .v{ color:#9ca3af; } .pm-kpi.atr .v{ color:#e67700; } .pm-kpi.sel .v{ color:#2e7d32; }

/* Tabela */
.pm-card       { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.pm-card-head  { background:#1565c0; color:#fff; padding:7px 14px; font-size:11px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.4px; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.pm-card-head .ctrl { display:flex; gap:6px; align-items:center; }
.pm-card-head input, .pm-card-head select { font-size:12px !important; height:26px; padding:2px 8px !important; }
.pm-wrap       { overflow:auto; max-height:calc(100vh - 350px); }
.pm-tbl                 { border-collapse:separate; border-spacing:0; width:100%; min-width:1600px; table-layout:fixed; }
.pm-tbl thead th        { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 5px !important; font-size:10px !important;
                          font-weight:700 !important; white-space:normal !important; line-height:1.2; vertical-align:bottom;
                          border-bottom:2px solid #bbdefb !important; text-align:left; position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
.pm-tbl thead th.r      { text-align:right; } .pm-tbl thead th.c { text-align:center; cursor:default; }
.pm-tbl th .sort        { color:#90caf9; font-size:9px; margin-left:2px; } .pm-tbl th.sorted .sort { color:#1565c0; }
.pm-tbl tbody td        { padding:3px 5px !important; font-size:11px !important; line-height:1.35; border-bottom:1px solid #f0f0f0 !important;
                          white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pm-tbl td.r            { text-align:right; font-variant-numeric:tabular-nums; } .pm-tbl td.c { text-align:center; }
.pm-tbl tbody tr.usoconsumo td { background:#fff4d6 !important; }   /* material de uso e consumo (grupo de inventário 300) */
.pm-tbl tbody tr:hover td      { background:#f5f8fe !important; }
.pm-tbl tbody tr.selecionado td{ background:#e8f5e9 !important; }
.pm-tbl tbody tr.inativo td    { color:#dc3545 !important; }
.pm-tbl td .neg         { color:#dc3545; font-weight:700; }
.pm-tbl td .grp300      { font-style:italic; font-weight:700; }   /* OTM: {X-X-X/X-X-X/Tahoma-3-12} = negrito + itálico, sem cor */
.tag-st        { display:inline-block; padding:0 6px; border-radius:4px; font-size:10px; font-weight:700; }
.tag-st.a      { background:#e8f5e9; color:#2e7d32; } .tag-st.n { background:#fdecea; color:#c62828; }
.tag-obs       { color:#e67700; margin-left:3px; }
.btn-acao      { font-size:10px; padding:0 5px; line-height:17px; font-weight:700; }
.pm-msg        { text-align:center; padding:24px !important; color:#999 !important; font-size:13px !important; white-space:normal !important; }

/* Modais */
.det-tbl                { width:100%; border-collapse:collapse; font-size:12px; }
.det-tbl thead th       { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 8px !important; font-size:11px !important;
                          border-bottom:2px solid #bbdefb !important; position:sticky; top:0; }
.det-tbl tbody td       { padding:4px 8px !important; font-size:12px !important; border-bottom:1px solid #f0f0f0 !important; }
.det-tbl tfoot td       { padding:5px 8px !important; font-weight:700 !important; background:#f3f6fb !important; }
.det-tbl .r             { text-align:right; }
.det-tbl tr.click       { cursor:pointer; } .det-tbl tr.click:hover td { background:#f5f8fe !important; }
.det-sub                { font-size:12px; color:#6b7280; margin-bottom:8px; }
.inp-qtde               { width:110px; text-align:right; font-size:12px !important; height:26px; padding:2px 6px !important; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <h5 class="mb-1 fw-semibold"><i class="bi bi-boxes me-1 text-primary"></i> Consulta Planej. Material por Filial</h5>
                <span id="spanGerado" class="small text-muted"></span>
            </div>
            <div class="col-md-3 ms-md-3">
                <label class="form-label">Empresa</label>
                <div class="ms" id="msEmpresa"></div>
            </div>
            <div class="col-auto">
                <label class="form-label">Período inicial</label>
                <input type="date" id="inpIni" class="form-control form-control-sm" style="width:150px">
            </div>
            <div class="col-auto">
                <label class="form-label">Período final</label>
                <input type="date" id="inpFim" class="form-control form-control-sm" style="width:150px">
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Gerar</button>
                <button id="btnResumo" class="btn btn-sm btn-outline-success ms-1" style="display:none">
                    <i class="bi bi-list-check"></i> Resumo Itens (<span id="qtdSel">0</span>)
                </button>
                <button id="btnExcel" class="btn btn-sm btn-success ms-1" style="display:none"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            </div>
            <div class="col">
                <span id="msgRelatorio" class="small"></span>
                <span id="spanTotalReg" class="text-muted small float-end"></span>
            </div>
        </div>
    </div>

    <div class="pm-cards" id="pmCards" style="display:none">
        <div class="pm-kpi ativo" data-f="todos"><div class="v" id="kTotal">0</div><div class="l">Itens</div></div>
        <div class="pm-kpi crit" data-f="critico"><div class="v" id="kCrit">0</div><div class="l">Autonomia ≤ 15 dias</div></div>
        <div class="pm-kpi" data-f="semaut"><div class="v" id="kSemAut">0</div><div class="l">Sem média / autonomia</div></div>
        <div class="pm-kpi atr" data-f="atrasado"><div class="v" id="kAtr">0</div><div class="l">Entrega atrasada</div></div>
        <div class="pm-kpi inat" data-f="inativo"><div class="v" id="kInat">0</div><div class="l">Inativos</div></div>
        <div class="pm-kpi sel" data-f="selecionado"><div class="v" id="kSel">0</div><div class="l">Selecionados</div></div>
    </div>

    <div class="pm-card">
        <div class="pm-card-head">
            <span>Itens por filial — marque os itens para o Resumo / Interfábrica</span>
            <div class="ctrl">
                <select id="selEmpFiltro" class="form-select form-select-sm" style="width:130px"><option value="">Todas as filiais</option></select>
                <div class="ms ms-head" id="msPlan" style="width:220px"></div>
                <input type="text" id="inpFiltro" class="form-control form-control-sm" style="width:260px" placeholder="Localizar / filtrar (código, descrição, máscara...)">
            </div>
        </div>
        <div class="pm-wrap">
            <table class="pm-tbl">
                <colgroup>
                    <col style="width:30px"><col style="width:50px"><col style="width:60px"><col><col style="width:62px"><col style="width:12%">
                    <col style="width:64px"><col style="width:118px"><col style="width:60px"><col style="width:62px"><col style="width:60px">
                    <col style="width:60px"><col style="width:64px"><col style="width:62px"><col style="width:62px"><col style="width:62px">
                    <col style="width:60px"><col style="width:74px"><col style="width:74px"><col style="width:62px"><col style="width:62px">
                    <col style="width:52px"><col style="width:52px"><col style="width:78px">
                </colgroup>
                <thead>
                    <tr>
                        <th class="c"><input type="checkbox" id="chkTodos" title="Marcar/desmarcar os itens visíveis"></th>
                        <th data-col="EMPR_ID" data-num="1">Empresa<span class="sort">⇅</span></th>
                        <th data-col="COD_ITEM" data-num="1">Código<span class="sort">⇅</span></th>
                        <th data-col="DESC_ITEM">Descrição do Item<span class="sort">⇅</span></th>
                        <th data-col="MASC_ID" data-num="1">ID<span class="sort">⇅</span></th>
                        <th data-col="MASCARA">Máscara<span class="sort">⇅</span></th>
                        <th data-col="TIPO_PRODUTO">Status<span class="sort">⇅</span></th>
                        <th data-col="PLANEJADOR">Planejador<span class="sort">⇅</span></th>
                        <th data-col="ESTQ_SEG" data-num="1" class="r">Estoque Seg.<span class="sort">⇅</span></th>
                        <th data-col="DEMANDA" data-num="1" class="r">Demanda<span class="sort">⇅</span></th>
                        <th data-col="MEDIA_TRIMESTRAL" data-num="1" class="r">Média Trim.<span class="sort">⇅</span></th>
                        <th data-col="MEDIA_MENSAL" data-num="1" class="r">Cons. Mês<span class="sort">⇅</span></th>
                        <th data-col="AUTONOMIA" data-num="1" class="r">Autonomia (dias)<span class="sort">⇅</span></th>
                        <th data-col="SALDO_CD" data-num="1" class="r">Saldo CD<span class="sort">⇅</span></th>
                        <th data-col="ALMOX_PADRAO" data-num="1" class="r">Almox. Padrão<span class="sort">⇅</span></th>
                        <th data-col="OUTROS_ALMOX" data-num="1" class="r">Outros Almox.<span class="sort">⇅</span></th>
                        <th data-col="PEDIDO" data-num="1">Pedido<span class="sort">⇅</span></th>
                        <th data-col="INCLUSAO_ORD">Inclusão<span class="sort">⇅</span></th>
                        <th data-col="ENTREGA_ORD">Entrega<span class="sort">⇅</span></th>
                        <th data-col="SALDO_PEDIDO" data-num="1" class="r">Saldo Pedido<span class="sort">⇅</span></th>
                        <th data-col="CARTEIRA_OCI" data-num="1" class="r">Carteira OCI<span class="sort">⇅</span></th>
                        <th data-col="LOTE_MIN" data-num="1" class="r">Lote Min.<span class="sort">⇅</span></th>
                        <th data-col="LOTE_MULT" data-num="1" class="r">Lote Mult.<span class="sort">⇅</span></th>
                        <th class="c">Ações</th>
                    </tr>
                </thead>
                <tbody id="pmBody"><tr><td colspan="24" class="pm-msg">Selecione as empresas e o período e clique em Gerar.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

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
        filtros: 'planejamento-api-pmf-filtros', gerar: 'planejamento-api-pmf-gerar', listar: 'planejamento-api-pmf-listar',
        selecionar: 'planejamento-api-pmf-selecionar', detalhe: 'planejamento-api-pmf-detalhe', resumo: 'planejamento-api-pmf-resumo',
        qtde: 'planejamento-api-pmf-qtde', interfabrica: 'planejamento-api-pmf-interfabrica', mensagens: 'planejamento-api-pmf-mensagens',
    };

    const esc   = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    const vazio = v => v === null || v === undefined || v === '';
    const num   = v => vazio(v) ? 0 : (parseFloat(String(v).replace(',', '.')) || 0);
    const fq    = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    const fq2   = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

    async function post(url, dados) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados || {}) });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    // ── Multiseleção de empresas ───────────────────────────────────
    const msEl = document.getElementById('msEmpresa');
    const ms = { itens: [], sel: new Set() };
    msEl.innerHTML = `<button type="button" class="form-select form-select-sm ms-btn"></button>
        <div class="ms-pop"><div class="ms-list"></div><div class="ms-acts"><a data-a="todos">Marcar todas</a><a data-a="limpar">Limpar</a></div></div>`;
    const msBtn = msEl.querySelector('.ms-btn'), msList = msEl.querySelector('.ms-list');
    function msRotulo() {
        const nomes = ms.itens.filter(i => ms.sel.has(i.v)).map(i => i.curto);
        msBtn.textContent = !nomes.length ? 'Selecione...' : (nomes.length <= 4 ? nomes.join(', ') : `${nomes.length} filiais`);
    }
    function msDesenhar() {
        msList.innerHTML = ms.itens.map(i => `<label><input type="checkbox" value="${esc(i.v)}" ${ms.sel.has(i.v) ? 'checked' : ''}> ${esc(i.t)}</label>`).join('');
    }
    msBtn.addEventListener('click', () => { msEl.classList.toggle('open'); msDesenhar(); });
    msList.addEventListener('change', e => { if (e.target.checked) ms.sel.add(e.target.value); else ms.sel.delete(e.target.value); msRotulo(); });
    msEl.querySelector('.ms-acts').addEventListener('click', e => {
        if (e.target.dataset.a === 'todos') ms.itens.forEach(i => ms.sel.add(i.v));
        if (e.target.dataset.a === 'limpar') ms.sel.clear();
        msDesenhar(); msRotulo();
    });
    document.addEventListener('click', e => { if (!e.target.closest('#msEmpresa')) msEl.classList.remove('open'); });

    // ── Estado ─────────────────────────────────────────────────────
    const body = document.getElementById('pmBody'), msgEl = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg'), inpFiltro = document.getElementById('inpFiltro');
    const selEmp = document.getElementById('selEmpFiltro');
    let _rows = [], _sort = { col: null, num: false, dir: 1 }, _kpi = 'todos', _par = null;

    // ── Multiseleção de planejadores (filtro da tabela; vazio = todos) ──
    const SEM_PLAN = '(sem planejador)';
    const mpEl = document.getElementById('msPlan');
    const mp = { itens: [], sel: new Set() };
    mpEl.innerHTML = `<button type="button" class="form-select form-select-sm ms-btn"></button>
        <div class="ms-pop"><div class="ms-list"></div><div class="ms-acts"><a data-a="todos">Marcar todos</a><a data-a="limpar">Limpar (todos)</a></div></div>`;
    const mpBtn = mpEl.querySelector('.ms-btn'), mpList = mpEl.querySelector('.ms-list');
    function mpRotulo() {
        const n = mp.sel.size;
        mpBtn.textContent = !n || n === mp.itens.length ? 'Todos os planejadores' : (n <= 2 ? [...mp.sel].join(', ') : `${n} planejadores`);
        mpBtn.title = n ? [...mp.sel].join(', ') : '';
    }
    function mpDesenhar() {
        mpList.innerHTML = mp.itens.map(p => `<label><input type="checkbox" value="${esc(p)}" ${mp.sel.has(p) ? 'checked' : ''}> ${esc(p)}</label>`).join('');
    }
    mpBtn.addEventListener('click', () => { mpEl.classList.toggle('open'); mpDesenhar(); });
    mpList.addEventListener('change', e => { if (e.target.checked) mp.sel.add(e.target.value); else mp.sel.delete(e.target.value); mpRotulo(); render(); });
    mpEl.querySelector('.ms-acts').addEventListener('click', e => {
        if (e.target.dataset.a === 'todos') mp.itens.forEach(p => mp.sel.add(p));
        if (e.target.dataset.a === 'limpar') mp.sel.clear();
        mpDesenhar(); mpRotulo(); render();
    });
    document.addEventListener('click', e => { if (!e.target.closest('#msPlan')) mpEl.classList.remove('open'); });
    const planDe = r => r.PLANEJADOR || SEM_PLAN;

    const isInativo  = r => r.TIPO_PRODUTO === 'N';
    const isCritico  = r => !vazio(r.AUTONOMIA) && num(r.AUTONOMIA) <= 15;
    const isSemAut   = r => vazio(r.AUTONOMIA);
    const isAtrasado = r => String(r.ENTREGA_ATRASADA) === '1';
    const isSel      = r => String(r.SELECAO) === '1';
    const KPI = { todos: () => true, critico: isCritico, semaut: isSemAut, atrasado: isAtrasado, inativo: isInativo, selecionado: isSel };

    (function () {
        const h = new Date();
        document.getElementById('inpIni').value = `${h.getFullYear()}-01-01`;
        document.getElementById('inpFim').value = '2099-12-31';
    })();

    function linhasVisiveis() {
        const termo = inpFiltro.value.trim().toLowerCase(), emp = selEmp.value;
        let lista = _rows.filter(KPI[_kpi]);
        if (emp)  lista = lista.filter(r => String(r.EMPR_ID) === emp);
        if (mp.sel.size) lista = lista.filter(r => mp.sel.has(planDe(r)));
        if (termo) lista = lista.filter(r => [r.COD_ITEM, r.DESC_ITEM, r.MASC_ID, r.MASCARA, r.PLANEJADOR, r.PEDIDO, r.OBS]
            .some(v => String(v ?? '').toLowerCase().includes(termo)));
        if (_sort.col) {
            const c = _sort.col, d = _sort.dir;
            lista = [...lista].sort((a, b) => {
                const ea = vazio(a[c]), eb = vazio(b[c]);
                if (ea !== eb) return ea ? 1 : -1;
                const va = _sort.num ? num(a[c]) : String(a[c] ?? ''), vb = _sort.num ? num(b[c]) : String(b[c] ?? '');
                return (va < vb ? -1 : va > vb ? 1 : 0) * d;
            });
        }
        return lista;
    }

    function atualizarKpis() {
        const n = f => _rows.filter(f).length.toLocaleString('pt-BR');
        document.getElementById('kTotal').textContent = _rows.length.toLocaleString('pt-BR');
        document.getElementById('kCrit').textContent = n(isCritico);
        document.getElementById('kSemAut').textContent = n(isSemAut);
        document.getElementById('kAtr').textContent = n(isAtrasado);
        document.getElementById('kInat').textContent = n(isInativo);
        const qs = _rows.filter(isSel).length;
        document.getElementById('kSel').textContent = qs.toLocaleString('pt-BR');
        document.getElementById('qtdSel').textContent = qs;
    }

    function linhaHtml(r) {
        const aut = vazio(r.AUTONOMIA) ? '' : (isCritico(r) ? `<span class="neg">${fq(r.AUTONOMIA)}</span>` : fq(r.AUTONOMIA));
        const st  = isInativo(r) ? '<span class="tag-st n">INATIVO</span>' : '<span class="tag-st a">ATIVO</span>';
        const obs = r.OBS ? `<i class="bi bi-chat-left-text-fill tag-obs" title="${esc(r.OBS)}"></i>` : '';
        const usoConsumo = String(r.COD_GRP_INVENT) === '300';
        const cod = usoConsumo ? `<span class="grp300" title="Material de uso e consumo (grupo de inventário 300)">${esc(r.COD_ITEM)}</span>` : esc(r.COD_ITEM);
        return `<tr data-id="${esc(r.ID)}" class="${usoConsumo ? 'usoconsumo' : ''} ${isSel(r) ? 'selecionado' : ''} ${isInativo(r) ? 'inativo' : ''}"${usoConsumo ? ' title="Material de uso e consumo"' : ''}>
            <td class="c"><input type="checkbox" class="chk" ${isSel(r) ? 'checked' : ''}></td>
            <td>${esc(r.EMPR_ID)}</td><td>${cod}</td>
            <td title="${esc(r.DESC_ITEM)}">${esc(r.DESC_ITEM)}</td>
            <td>${esc(r.MASC_ID)}</td><td title="${esc(r.MASCARA)}">${esc(r.MASCARA)}</td>
            <td title="${esc(r.OBS || '')}">${st}${obs}</td>
            <td title="${esc(r.PLANEJADOR || '')}">${esc(r.PLANEJADOR)}</td>
            <td class="r">${fq(r.ESTQ_SEG)}</td><td class="r">${fq(r.DEMANDA)}</td>
            <td class="r">${fq(r.MEDIA_TRIMESTRAL)}</td><td class="r">${fq(r.MEDIA_MENSAL)}</td>
            <td class="r">${aut}</td><td class="r">${fq(r.SALDO_CD)}</td>
            <td class="r">${fq(r.ALMOX_PADRAO)}</td><td class="r">${fq(r.OUTROS_ALMOX)}</td>
            <td>${esc(r.PEDIDO)}</td><td>${esc(r.INCLUSAO)}</td>
            <td>${isAtrasado(r) ? `<span class="neg">${esc(r.ENTREGA)}</span>` : esc(r.ENTREGA)}</td>
            <td class="r">${fq(r.SALDO_PEDIDO)}</td><td class="r">${fq(r.CARTEIRA_OCI)}</td>
            <td class="r">${fq(r.LOTE_MIN)}</td><td class="r">${fq(r.LOTE_MULT)}</td>
            <td class="c">
                <button class="btn btn-outline-primary btn-acao" data-act="pedidos" title="Pedidos">P</button>
                <button class="btn btn-outline-primary btn-acao" data-act="almox" title="Saldo almoxarifados">A</button>
                <button class="btn btn-outline-primary btn-acao" data-act="necessidade" title="Necessidade">N</button>
            </td>
        </tr>`;
    }

    function render() {
        if (!_par) { body.innerHTML = '<tr><td colspan="24" class="pm-msg">Selecione as empresas e o período e clique em Gerar.</td></tr>'; return; }
        const lista = linhasVisiveis();
        body.innerHTML = lista.length ? lista.map(linhaHtml).join('')
            : `<tr><td colspan="24" class="pm-msg">${_rows.length ? 'Nenhum item corresponde ao filtro.' : 'Nenhum item retornado.'}</td></tr>`;
        document.getElementById('chkTodos').checked = lista.length > 0 && lista.every(isSel);
        spanTotal.textContent = lista.length === _rows.length ? `${_rows.length.toLocaleString('pt-BR')} registro(s)`
            : `${lista.length.toLocaleString('pt-BR')} de ${_rows.length.toLocaleString('pt-BR')} registro(s)`;
    }

    function aplicar(d) {
        _par = d.parametros || null;
        _rows = Array.isArray(d.rows) ? d.rows : [];
        const sg = document.getElementById('spanGerado');
        sg.innerHTML = _par ? `<i class="bi bi-clock-history"></i> Gerado em <b>${esc(_par.DT_GERACAO)}</b> · ${esc(_par.SEGUNDOS)}s · período ${esc(_par.DT_INI_BR)} a ${esc(_par.DT_FIM_BR)}` : '';
        const plans = [...new Set(_rows.map(r => r.PLANEJADOR).filter(Boolean))].sort();
        if (_rows.some(r => !r.PLANEJADOR)) plans.push(SEM_PLAN);
        mp.itens = plans;
        [...mp.sel].forEach(p => { if (!plans.includes(p)) mp.sel.delete(p); });   // mantém a escolha ao gerar de novo
        mpRotulo();
        const emps = [...new Set(_rows.map(r => String(r.EMPR_ID)))].sort((a, b) => a - b);
        selEmp.innerHTML = '<option value="">Todas as filiais</option>' + emps.map(e => `<option value="${e}">FL ${e}</option>`).join('');
        document.getElementById('pmCards').style.display = _par ? '' : 'none';
        document.getElementById('btnResumo').style.display = _par ? '' : 'none';
        document.getElementById('btnExcel').style.display = _rows.length ? '' : 'none';
        atualizarKpis(); render();
    }

    // ── Carregar / Gerar ───────────────────────────────────────────
    post(API.filtros, {}).then(d => {
        ms.itens = (d.empresas || []).map(e => ({ v: String(e.ID), t: `FL ${e.COD_EMP} — ${e.NOME}`, curto: `FL ${e.COD_EMP}` }));
        const par = d.parametros;
        if (par) {
            String(par.EMPRESAS).split(',').forEach(id => ms.sel.add(id));
            document.getElementById('inpIni').value = par.DT_INI;
            document.getElementById('inpFim').value = par.DT_FIM;
            msRotulo();
            body.innerHTML = '<tr><td colspan="24" class="pm-msg"><span class="spinner-border spinner-border-sm me-2"></span>Carregando a última geração...</td></tr>';
            return post(API.listar, {}).then(aplicar);
        }
        msRotulo();
    }).catch(e => { msgEl.innerHTML = `<span class="text-danger">${esc(e.message)}</span>`; });

    document.getElementById('btnGerar').addEventListener('click', async function () {
        if (!ms.sel.size) { alert('Selecione ao menos uma empresa.'); return; }
        this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
        msgEl.innerHTML = '<span class="text-muted">Calculando... (cerca de 10 a 60 segundos, conforme as filiais)</span>';
        const t0 = Date.now();
        try {
            aplicar(await post(API.gerar, { empresas: [...ms.sel], dt_ini: document.getElementById('inpIni').value, dt_fim: document.getElementById('inpFim').value }));
            msgEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Gerado em ${((Date.now() - t0) / 1000).toFixed(0)}s.</span>`;
        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
        } finally {
            this.disabled = false; this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });

    // ── Filtros / ordenação / KPIs ─────────────────────────────────
    let _tF; inpFiltro.addEventListener('input', () => { clearTimeout(_tF); _tF = setTimeout(render, 200); });
    selEmp.addEventListener('change', render);
    document.querySelectorAll('.pm-kpi').forEach(k => k.addEventListener('click', function () {
        _kpi = this.dataset.f; document.querySelectorAll('.pm-kpi').forEach(x => x.classList.toggle('ativo', x === this)); render();
    }));
    document.querySelectorAll('.pm-tbl th[data-col]').forEach(th => th.addEventListener('click', function () {
        const col = this.dataset.col;
        _sort = { col, num: this.dataset.num === '1', dir: _sort.col === col ? -_sort.dir : 1 };
        document.querySelectorAll('.pm-tbl th[data-col]').forEach(h => {
            h.classList.toggle('sorted', h === this);
            h.querySelector('.sort').textContent = h === this ? (_sort.dir === 1 ? '▲' : '▼') : '⇅';
        });
        render();
    }));

    // ── Seleção ────────────────────────────────────────────────────
    async function selecionar(ids, flag) {
        if (!ids.length) return;
        try {
            await post(API.selecionar, { ids, selecao: flag ? 1 : 0 });
            const set = new Set(ids.map(String));
            _rows.forEach(r => { if (set.has(String(r.ID))) r.SELECAO = flag ? '1' : '0'; });
            atualizarKpis(); render();
        } catch (e) { alert('Erro ao selecionar: ' + e.message); render(); }
    }
    document.getElementById('chkTodos').addEventListener('change', function () {
        const lista = linhasVisiveis();
        if (this.checked && lista.length > 300 && !confirm(`Marcar ${lista.length} itens?`)) { this.checked = false; return; }
        selecionar(lista.filter(r => isSel(r) !== this.checked).map(r => r.ID), this.checked);
    });

    // ── Modal ──────────────────────────────────────────────────────
    const mdlEl = document.getElementById('mdl'); let _mdl = null;
    function modal(titulo, corpo, rodape = '', tam = 'modal-lg') {
        document.getElementById('mdlDialog').className = `modal-dialog modal-dialog-scrollable ${tam}`;
        document.getElementById('mdlTitulo').innerHTML = titulo;
        document.getElementById('mdlCorpo').innerHTML = corpo;
        const rod = document.getElementById('mdlRodape'); rod.innerHTML = rodape; rod.style.display = rodape ? '' : 'none';
        if (!_mdl) _mdl = new bootstrap.Modal(mdlEl);
        _mdl.show();
    }
    const corpo = () => document.getElementById('mdlCorpo');
    const carregando = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</div>';
    const erroHtml = e => `<div class="text-danger py-3"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</div>`;
    const sub = r => `<div class="det-sub">FL <b>${esc(r.EMPR_ID)}</b> · <b>${esc(r.COD_ITEM)}</b> — ${esc(r.DESC_ITEM)}${r.MASCARA ? ` · <b>ID ${esc(r.MASC_ID)}</b> ${esc(r.MASCARA)}` : ''}</div>`;
    function tabela(cols, rows, opts = {}) {
        if (!rows.length) return '<div class="text-muted py-3">Nenhum registro encontrado.</div>';
        const tot = {};
        const tb = rows.map((r, i) => { cols.forEach(c => { if (c.s) tot[c.k] = (tot[c.k] || 0) + num(r[c.k]); });
            return `<tr${opts.click ? ` class="click" data-i="${i}"` : ''}>` + cols.map(c => `<td class="${c.r ? 'r' : ''}">${c.f ? c.f(r) : (c.r ? fq2(r[c.k]) : esc(r[c.k]))}</td>`).join('') + '</tr>'; }).join('');
        const tf = cols.some(c => c.s) ? '<tfoot><tr>' + cols.map((c, i) => `<td class="${c.r ? 'r' : ''}">${c.s ? fq2(tot[c.k]) : (i === 0 ? 'Total' : '')}</td>`).join('') + '</tr></tfoot>' : '';
        return `<table class="det-tbl"><thead><tr>${cols.map(c => `<th class="${c.r ? 'r' : ''}">${c.t}</th>`).join('')}</tr></thead><tbody>${tb}</tbody>${tf}</table>`;
    }

    async function abrirPedidos(r) {
        modal(Number(r.EMPR_ID) === 1 ? 'Pedidos de Venda na Filial 1' : `Pedidos de Compra na Filial ${esc(r.EMPR_ID)}`, sub(r) + carregando, '', 'modal-xl');
        try {
            const rows = (await post(API.detalhe, { tipo: 'pedidos', id: r.ID })).rows || [];
            const pdv = Number(r.EMPR_ID) === 1;
            corpo().innerHTML = sub(r) + tabela([
                { t: 'Pedido', k: 'PEDIDO', f: p => `<a href="#" onclick="return false">${esc(p.PEDIDO)}</a>` },
                { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1, s: 1 }, { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1, s: 1 },
                { t: 'UM', k: 'UM' },
                { t: 'Data Entrega', k: 'DT_ENTREGA', f: p => String(p.ATRASADO) === '1' ? `<span class="text-danger fw-bold">${esc(p.DT_ENTREGA)}</span>` : esc(p.DT_ENTREGA) },
                { t: pdv ? 'Cliente' : 'Fornecedor', k: 'PARCEIRO' },
                ...(pdv ? [] : [{ t: 'Comprador', k: 'COMPRADOR' }]),
            ], rows, { click: true }) + (rows.length ? '<div class="small text-muted mt-2">Clique no pedido para ver todos os itens pendentes dele.</div>' : '');
            corpo().querySelectorAll('tr.click').forEach(tr => tr.addEventListener('click', () => abrirPedidoItens(rows[tr.dataset.i], r)));
        } catch (e) { corpo().innerHTML = sub(r) + erroHtml(e); }
    }
    async function abrirPedidoItens(p, r) {
        modal(`Itens Pendentes do Pedido ${esc(p.PEDIDO)}`, carregando, '<button class="btn btn-sm btn-outline-secondary" id="btnVoltar"><i class="bi bi-arrow-left"></i> Voltar</button>', 'modal-xl');
        document.getElementById('btnVoltar').onclick = () => abrirPedidos(r);
        try {
            const rows = (await post(API.detalhe, { tipo: 'pedido_itens', tipo_pedido: p.TIPO, ped_id: p.PED_ID })).rows || [];
            corpo().innerHTML = tabela([
                { t: 'Cód. Item', k: 'COD_ITEM' }, { t: 'Desc. Item', k: 'DESC_ITEM' }, { t: 'ID', k: 'MASC_ID' }, { t: 'Máscara', k: 'MASCARA' },
                { t: 'Inclusão', k: 'DT_INCLUSAO' }, { t: 'Entrega', k: 'DT_ENTREGA' },
                { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1 }, { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1 }, { t: 'UM', k: 'UM' },
            ], rows);
        } catch (e) { corpo().innerHTML = erroHtml(e); }
    }
    async function abrirAlmox(r) {
        modal('Saldo Almoxarifados', sub(r) + carregando, '', '');
        try {
            const rows = (await post(API.detalhe, { tipo: 'almox', id: r.ID })).rows || [];
            corpo().innerHTML = sub(r) + tabela([{ t: 'Cód. Almox.', k: 'COD_ALMOX' }, { t: 'Almoxarifado', k: 'DESCRICAO' }, { t: 'Estoque', k: 'ESTOQUE', r: 1, s: 1 }], rows);
        } catch (e) { corpo().innerHTML = sub(r) + erroHtml(e); }
    }
    async function abrirNecessidade(r) {
        modal('Necessidade', sub(r) + carregando, '', '');
        try {
            const v = (await post(API.detalhe, { tipo: 'necessidade', id: r.ID })).necessidade;
            corpo().innerHTML = sub(r) + `<div class="fs-4 fw-bold ${num(v) < 0 ? 'text-danger' : ''}">${vazio(v) ? '—' : fq2(v)}</div>
                <div class="small text-muted">(Almox. padrão + Carteira OCI − Estoque seg. − Demanda), ajustado ao lote múltiplo.</div>`;
        } catch (e) { corpo().innerHTML = sub(r) + erroHtml(e); }
    }

    body.addEventListener('click', ev => {
        const tr = ev.target.closest('tr[data-id]'); if (!tr) return;
        const r = _rows.find(x => String(x.ID) === tr.dataset.id); if (!r) return;
        if (ev.target.classList.contains('chk')) { selecionar([r.ID], ev.target.checked); return; }
        const act = ev.target.closest('[data-act]')?.dataset.act;
        if (act === 'pedidos') abrirPedidos(r);
        if (act === 'almox') abrirAlmox(r);
        if (act === 'necessidade') abrirNecessidade(r);
    });

    // ── Resumo dos itens / Interfábrica ────────────────────────────
    async function abrirResumo() {
        const rod = `<button class="btn btn-sm btn-outline-secondary me-auto" id="btnMsgs"><i class="bi bi-sticky"></i> Mensagens</button>
                     <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                     <button class="btn btn-sm btn-danger" id="btnInterf"><i class="bi bi-building-gear"></i> Gerar Interfábrica</button>`;
        modal('Resumo dos Itens selecionados', carregando, rod, 'modal-xl');
        document.getElementById('btnMsgs').onclick = abrirMensagens;
        const btnI = document.getElementById('btnInterf');
        try {
            const rows = (await post(API.resumo, {})).rows || [];
            btnI.disabled = !rows.length;
            corpo().innerHTML = (rows.length ? '' : '<div class="alert alert-warning py-2 small">Nenhum item selecionado com empresa produtora cadastrada.</div>') + tabela([
                { t: 'Emp.', k: 'EMPR_ID' }, { t: 'Código', k: 'COD_ITEM' }, { t: 'Descrição do Item', k: 'DESC_ITEM' },
                { t: 'ID', k: 'MASC_ID' }, { t: 'Máscara', k: 'MASCARA' },
                { t: 'Estoque Calc.', k: 'ESTQ_CALC', r: 1 }, { t: 'Demanda', k: 'DEMANDA', r: 1 }, { t: 'Saldo CD', k: 'SALDO_CD', r: 1 },
                { t: 'Almox. Padrão', k: 'ALMOX_PADRAO', r: 1 }, { t: 'Outros Almox.', k: 'OUTROS_ALMOX', r: 1 }, { t: 'Lote Mult.', k: 'LOTE_MULT', r: 1 },
                { t: 'Necessidade', k: 'NECESSIDADE', r: 1, f: x => num(x.NECESSIDADE) < 0 ? `<span class="text-danger">${fq2(x.NECESSIDADE)}</span>` : fq2(x.NECESSIDADE) },
                { t: 'Qtde (lote)', k: 'QTDE_LOTE', r: 1 },
                { t: 'Qtde a gerar', k: 'QTDE_ALT', r: 1, f: x => `<input type="number" min="0" step="any" class="form-control inp-qtde" data-id="${esc(x.ID)}" value="${esc(num(x.QTDE_ALT))}">` },
                { t: 'Peso', k: 'PESO', r: 1, s: 1 },
            ], rows) + (rows.length ? '<div class="small text-muted mt-2">"Qtde a gerar" é a quantidade enviada para a interfábrica (mesmo campo editável do Focco). "Qtde (lote)" é a sugestão ajustada ao lote mínimo/múltiplo.</div>' : '');
            corpo().querySelectorAll('.inp-qtde').forEach(inp => inp.addEventListener('change', async () => {
                try { await post(API.qtde, { id: inp.dataset.id, qtde: inp.value }); inp.classList.add('is-valid'); setTimeout(() => inp.classList.remove('is-valid'), 1200); }
                catch (e) { inp.classList.add('is-invalid'); alert('Erro: ' + e.message); }
            }));
            btnI.onclick = async () => {
                if (!confirm(`Gerar interfábrica para ${rows.length} item(ns)?\n\nIsso CRIA pedidos de compra e de venda no Focco.`)) return;
                btnI.disabled = true; btnI.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
                try { const d = await post(API.interfabrica, {}); mostrarMensagens(d.mensagens || []); }
                catch (e) { alert('Erro: ' + e.message); btnI.disabled = false; btnI.innerHTML = '<i class="bi bi-building-gear"></i> Gerar Interfábrica'; }
            };
        } catch (e) { corpo().innerHTML = erroHtml(e); btnI.disabled = true; }
    }
    function mostrarMensagens(rows) {
        modal('Mensagens da Interfábrica', tabela([
            { t: 'Data', k: 'DT_MSG' }, { t: 'Tipo', k: 'TIPO' }, { t: 'Número', k: 'NUMERO' }, { t: 'Empresa', k: 'EMPR_ID' },
            { t: 'Mensagem', k: 'MSG', f: m => /erro/i.test(m.MSG || '') ? `<span class="text-danger">${esc(m.MSG)}</span>` : esc(m.MSG) },
        ], rows), '<button class="btn btn-sm btn-outline-secondary" id="btnVoltarRes"><i class="bi bi-arrow-left"></i> Resumo</button>', 'modal-xl');
        document.getElementById('btnVoltarRes').onclick = abrirResumo;
    }
    async function abrirMensagens() {
        try { mostrarMensagens((await post(API.mensagens, {})).rows || []); } catch (e) { alert('Erro: ' + e.message); }
    }
    document.getElementById('btnResumo').addEventListener('click', abrirResumo);

    // ── Excel ──────────────────────────────────────────────────────
    document.getElementById('btnExcel').addEventListener('click', () => {
        const lista = linhasVisiveis(); if (!lista.length) return;
        const cols = [['Empresa','EMPR_ID'],['Código','COD_ITEM'],['Descrição do Item','DESC_ITEM'],['ID','MASC_ID'],['Máscara','MASCARA'],
            ['Status', r => isInativo(r) ? 'INATIVO' : 'ATIVO'],['Observação','OBS'],['Planejador','PLANEJADOR'],['Estoque Seg.','ESTQ_SEG'],
            ['Demanda','DEMANDA'],['Média Trim.','MEDIA_TRIMESTRAL'],['Cons. Mês','MEDIA_MENSAL'],['Autonomia','AUTONOMIA'],['Saldo CD','SALDO_CD'],
            ['Almox. Padrão','ALMOX_PADRAO'],['Outros Almox.','OUTROS_ALMOX'],['Pedido','PEDIDO'],['Inclusão','INCLUSAO'],['Entrega','ENTREGA'],
            ['Saldo Pedido','SALDO_PEDIDO'],['Carteira OCI','CARTEIRA_OCI'],['Lote Min.','LOTE_MIN'],['Lote Mult.','LOTE_MULT'],
            ['Selecionado', r => isSel(r) ? 'SIM' : '']];
        const csv = [cols.map(c => c[0]).join(';'), ...lista.map(r => cols.map(([, k]) => {
            const v = typeof k === 'function' ? k(r) : r[k]; return `"${String(v ?? '').replace(/"/g, '""')}"`; }).join(';'))].join('\r\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        a.download = `planej-material-filial_${new Date().toISOString().slice(0, 10)}.csv`; a.click(); URL.revokeObjectURL(a.href);
    });
})();
</script>

<?= $render('footer') ?>
