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
    'pageTitle'  => 'Planejamento — Follow-up Pedido Compra',
    'showNavbar' => true,
    'pageActive' => 'planejamento-follow-up-pedido-compra',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:14px 20px;margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }

/* Multiseleção (empresas, classificações, tipos de nota) */
.ms            { position:relative; }
.ms-btn        { width:100%; text-align:left; font-size:.8rem; height:31px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ms-pop        { display:none; position:absolute; z-index:1050; top:100%; left:0; min-width:100%; width:max-content; max-width:460px;
                 background:#fff; border:1px solid #d0d7e2; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,.12); padding:6px; }
.ms.open .ms-pop { display:block; }
.ms-pop input[type=text] { font-size:.8rem; margin-bottom:4px; }
.ms-list       { max-height:260px; overflow:auto; font-size:.78rem; }
.ms-list label { display:flex; gap:6px; align-items:flex-start; padding:2px 4px; border-radius:4px; cursor:pointer; }
.ms-list label:hover { background:#f1f5fb; }
.ms-list .grp  { font-weight:700; color:#1565c0; padding:4px 4px 2px; font-size:.72rem; text-transform:uppercase; }
.ms-acts       { display:flex; justify-content:space-between; padding-top:4px; border-top:1px solid #eef1f6; margin-top:4px; }
.ms-acts a     { font-size:.75rem; cursor:pointer; }

/* Fornecedor (autocomplete) */
.ac            { position:relative; }
.ac-pop        { display:none; position:absolute; z-index:1050; top:100%; left:0; width:100%; min-width:320px; background:#fff;
                 border:1px solid #d0d7e2; border-radius:6px; box-shadow:0 6px 18px rgba(0,0,0,.12); max-height:260px; overflow:auto; font-size:.78rem; }
.ac-pop div    { padding:4px 8px; cursor:pointer; }
.ac-pop div:hover { background:#f1f5fb; }
.chip          { display:inline-flex; align-items:center; gap:4px; background:#e3f2fd; color:#1565c0; border-radius:12px;
                 padding:1px 8px; font-size:.72rem; margin:2px 4px 0 0; }
.chip i        { cursor:pointer; }

/* Cards */
.fu-cards      { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.fu-kpi        { flex:1 1 150px; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; cursor:pointer; border:2px solid transparent; }
.fu-kpi.ativo  { border-color:#1565c0; }
.fu-kpi .v     { font-size:19px; font-weight:700; color:#1e3a8a; line-height:1.1; }
.fu-kpi .l     { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.fu-kpi.atr .v { color:#dc3545; }

/* Tabela */
.fu-card       { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.fu-card-head  { background:#1565c0; color:#fff; padding:7px 14px; font-size:11px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.4px; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.fu-card-head .ctrl { display:flex; gap:6px; align-items:center; }
.fu-card-head input { font-size:12px !important; height:26px; padding:2px 8px !important; width:280px; }
.fu-wrap       { overflow:auto; max-height:calc(100vh - 360px); }
.fu-tbl                 { border-collapse:separate; border-spacing:0; width:max-content; min-width:100%; }
.fu-tbl thead th        { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 6px !important;
                          font-size:10px !important; font-weight:700 !important; white-space:normal !important;
                          line-height:1.2; vertical-align:bottom; border-bottom:2px solid #bbdefb !important; text-align:left;
                          position:sticky; top:0; z-index:2; cursor:pointer; user-select:none; }
.fu-tbl thead th.r      { text-align:right; }
.fu-tbl th .sort        { color:#90caf9; font-size:9px; margin-left:2px; }
.fu-tbl th.sorted .sort { color:#1565c0; }
.fu-tbl tbody td        { padding:3px 6px !important; font-size:11px !important; line-height:1.35; white-space:nowrap;
                          border-bottom:1px solid #f0f0f0 !important; overflow:hidden; text-overflow:ellipsis; }
.fu-tbl td.r            { text-align:right; font-variant-numeric:tabular-nums; }
.fu-tbl tbody tr:hover td { background:#f5f8fe !important; }
.fu-tbl td .neg         { color:#dc3545; font-weight:700; }
.fu-tbl tfoot td        { padding:5px 6px !important; font-size:11px !important; font-weight:700 !important; white-space:nowrap;
                          background:#1e3a8a !important; color:#fff !important; position:sticky; bottom:0; z-index:2; }
.fu-tbl tfoot td.r      { text-align:right; }
.tag-sit       { display:inline-block; padding:0 6px; border-radius:4px; font-size:10px; font-weight:700; }
.tag-sit.p     { background:#fff4e5; color:#b45309; }
.tag-sit.a     { background:#e8f5e9; color:#2e7d32; }
.fu-msg        { text-align:center; padding:24px !important; color:#999 !important; font-size:13px !important; white-space:normal !important; }
.col-menu      { max-height:360px; overflow:auto; font-size:.78rem; min-width:220px; }
.col-menu label{ display:flex; gap:6px; padding:2px 10px; cursor:pointer; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <h5 class="mb-3 fw-semibold"><i class="bi bi-truck me-1 text-primary"></i> Follow-up Pedido Compra</h5>
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Empresa</label>
                <div class="ms" id="msEmpresa"></div>
            </div>
            <div class="col-md-1">
                <label class="form-label">Situação</label>
                <select id="selSituacao" class="form-select form-select-sm">
                    <option value="P" selected>Pendente</option>
                    <option value="A">Atendido</option>
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Classificação</label>
                <div class="ms" id="msClas"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Fornecedor</label>
                <div class="ac">
                    <input type="text" id="inpFornecedor" class="form-control form-control-sm" placeholder="Código ou nome (Todos)" autocomplete="off">
                    <div class="ac-pop" id="acFornecedor"></div>
                </div>
                <div id="chipsFornecedor"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo Nota</label>
                <div class="ms" id="msTipoNota"></div>
            </div>
            <div class="col-md-1">
                <label class="form-label">Cód. Item</label>
                <input type="text" id="inpItem" class="form-control form-control-sm" placeholder="Todos">
            </div>
            <div class="col-md-1">
                <label class="form-label">Máscara</label>
                <div class="ms" id="msMascara"></div>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button id="btnGerar" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search"></i> Gerar</button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 mt-2">
            <button id="btnExcel" class="btn btn-sm btn-success" style="display:none;"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            <span id="msgRelatorio" class="small"></span>
            <span id="spanTotalReg" class="text-muted small ms-auto"></span>
        </div>
    </div>

    <div class="fu-cards" id="fuCards" style="display:none">
        <div class="fu-kpi ativo" data-f="todos"><div class="v" id="kLinhas">0</div><div class="l">Linhas</div></div>
        <div class="fu-kpi" data-f="todos"><div class="v" id="kPedidos">0</div><div class="l">Pedidos</div></div>
        <div class="fu-kpi" data-f="todos"><div class="v" id="kFornec">0</div><div class="l">Fornecedores</div></div>
        <div class="fu-kpi" data-f="todos"><div class="v" id="kVlrSaldo">0</div><div class="l">Valor do saldo (R$)</div></div>
        <div class="fu-kpi atr" data-f="atrasado"><div class="v" id="kAtr">0</div><div class="l">Entrega vencida c/ saldo</div></div>
    </div>

    <div class="fu-card">
        <div class="fu-card-head">
            <span>Itens dos pedidos de compra</span>
            <div class="ctrl">
                <input type="text" id="inpFiltro" class="form-control form-control-sm" placeholder="Localizar / filtrar (pedido, fornecedor, item...)">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" style="font-size:11px;padding:2px 8px">
                        <i class="bi bi-layout-three-columns"></i> Colunas
                    </button>
                    <div class="dropdown-menu dropdown-menu-end col-menu" id="colMenu"></div>
                </div>
            </div>
        </div>
        <div class="fu-wrap">
            <table class="fu-tbl">
                <thead id="fuHead"></thead>
                <tbody id="fuBody"><tr><td class="fu-msg">Informe os filtros e clique em Gerar.</td></tr></tbody>
                <tfoot id="fuFoot"></tfoot>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const API = {
        filtros:      'planejamento-api-follow-up-filtros',
        fornecedores: 'planejamento-api-follow-up-fornecedores',
        mascaras:     'planejamento-api-follow-up-mascaras',
        listar:       'planejamento-api-follow-up-listar',
    };

    // ── Utilitários ────────────────────────────────────────────────
    const esc  = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    const vazio = v => v === null || v === undefined || v === '';
    const num  = v => vazio(v) ? 0 : (parseFloat(String(v).replace(',', '.')) || 0);
    const fInt = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    const fQtd = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
    const fVlr = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fPrc = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    const fDec = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 4 });
    function fCnpj(v) {
        if (vazio(v)) return '';
        const d = String(v).replace(/\D/g, '').padStart(14, '0');
        return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
    }

    async function post(url, dados) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados || {}) });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    // ── Colunas (mesma ordem do Focco) ─────────────────────────────
    const COLS = [
        { k: 'EMPRESA',         t: 'Empresa',          w: 50 },
        { k: 'PEDIDO',          t: 'Pedido',           w: 60, n: 1 },
        { k: 'CNPJ',            t: 'CNPJ',             w: 120, f: r => fCnpj(r.CNPJ) },
        { k: 'FORNECEDOR',      t: 'Fornecedor',       w: 220 },
        { k: 'LINHA',           t: 'Linha',            w: 40, n: 1 },
        { k: 'ITEM',            t: 'Cód. Item',        w: 60 },
        { k: 'DESC_ITEM',       t: 'Desc. Item',       w: 260 },
        { k: 'DESC_FORN_ITENS', t: 'Desc. Forn. Itens',w: 220 },
        { k: 'MASC_ID',         t: 'ID',               w: 60, n: 1 },
        { k: 'MASCARA',         t: 'Máscara',          w: 170 },
        { k: 'UM',              t: 'UM',               w: 36 },
        { k: 'TIPO_PDC',        t: 'Tipo PDC',         w: 44 },
        { k: 'UF',              t: 'UF',               w: 30 },
        { k: 'DT_EMIS',         t: 'Dt. Emis.',        w: 72, o: 'DT_EMIS_ORD' },
        { k: 'DT_FAT',          t: 'Dt. Fat.',         w: 72, o: 'DT_FAT_ORD' },
        { k: 'DT_ENTREGA',      t: 'Dt. Entr.',        w: 72, o: 'DT_ENTREGA_ORD',
          f: r => String(r.ATRASADO) === '1' ? `<span class="neg">${esc(r.DT_ENTREGA)}</span>` : esc(r.DT_ENTREGA) },
        { k: 'UM_PED',          t: 'UM Ped.',          w: 44 },
        { k: 'QTDE',            t: 'Qtde',             w: 72, n: 1, r: 1, s: 1, fmt: fQtd },
        { k: 'QTDE_ATENDIDA',   t: 'Qtde Atend.',      w: 72, n: 1, r: 1, s: 1, fmt: fQtd },
        { k: 'QTDE_SALDO',      t: 'Saldo',            w: 72, n: 1, r: 1, s: 1, fmt: fQtd },
        { k: 'MOEDA',           t: 'Moeda',            w: 44 },
        { k: 'PRECO_UNIT',      t: 'Preço Unit.',      w: 76, n: 1, r: 1, fmt: fPrc },
        { k: 'VLR_TOTAL',       t: 'Vlr. Total',       w: 92, n: 1, r: 1, s: 1, fmt: fVlr },
        { k: 'VLR_SALDO',       t: 'Vlr. Saldo',       w: 92, n: 1, r: 1, s: 1, fmt: fVlr },
        { k: 'PARCELA',         t: 'Parcela',          w: 46, n: 1, r: 1, fmt: fInt },
        { k: 'FRETE_TRANSP',    t: 'Frete Transp.',    w: 86 },
        { k: 'FRETE_REDESP',    t: 'Frete Redesp.',    w: 86 },
        { k: 'TRANSPORTADORA',  t: 'Transportadora',   w: 170 },
        { k: 'DESC_SITUACAO',   t: 'Situação',         w: 70,
          f: r => `<span class="tag-sit ${r.SITUACAO === 'A' ? 'a' : 'p'}">${esc(r.DESC_SITUACAO)}</span>` },
        { k: 'COD_TP_NOTA',     t: 'Cód. Tp. Nota',    w: 56, n: 1 },
        { k: 'TIPO_NOTA',       t: 'Tipo Nota',        w: 200 },
        { k: 'COMPRADOR',       t: 'Comprador',        w: 150 },
        { k: 'CUBAGEM',         t: 'Cubagem',          w: 60, n: 1, r: 1, fmt: fDec },
        { k: 'PESO_BRT',        t: 'Peso BRT.',        w: 60, n: 1, r: 1, fmt: fDec },
        { k: 'OBS_ITEM',        t: 'OBS',              w: 200 },
    ];

    const LS_COLS = 'fu_cols_ocultas';
    let _ocultas = new Set();
    try { _ocultas = new Set(JSON.parse(localStorage.getItem(LS_COLS) || '[]')); } catch (_) {}
    const visiveis = () => COLS.filter(c => !_ocultas.has(c.k));

    // ── Componente multiseleção ────────────────────────────────────
    function multiSelect(el, { vazio: rotuloVazio, itens = [], marcados = [] }) {
        const st = { itens, sel: new Set(marcados.map(String)) };
        el.innerHTML = `<button type="button" class="form-select form-select-sm ms-btn"></button>
            <div class="ms-pop">
                <input type="text" class="form-control form-control-sm" placeholder="Localizar...">
                <div class="ms-list"></div>
                <div class="ms-acts"><a data-a="todos">Marcar todos</a><a data-a="limpar">Limpar</a></div>
            </div>`;
        const btn = el.querySelector('.ms-btn'), lista = el.querySelector('.ms-list'), busca = el.querySelector('input');

        function rotulo() {
            if (!st.sel.size) { btn.textContent = rotuloVazio; return; }
            const nomes = st.itens.filter(i => st.sel.has(String(i.v))).map(i => i.curto || i.t);
            btn.textContent = nomes.length <= 2 ? nomes.join(', ') : `${nomes.length} selecionados`;
            btn.title = nomes.join('\n');
        }
        function desenhar() {
            const termo = busca.value.trim().toLowerCase();
            let grupo = null, html = '';
            st.itens.filter(i => !termo || i.t.toLowerCase().includes(termo)).forEach(i => {
                if (i.g && i.g !== grupo) { grupo = i.g; html += `<div class="grp">${esc(i.g)}</div>`; }
                html += `<label><input type="checkbox" value="${esc(i.v)}" ${st.sel.has(String(i.v)) ? 'checked' : ''}> <span>${esc(i.t)}</span></label>`;
            });
            lista.innerHTML = html || '<div class="text-muted p-2">Nenhum item.</div>';
        }
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ms.open').forEach(o => { if (o !== el) o.classList.remove('open'); });
            el.classList.toggle('open');
            if (el.classList.contains('open')) { desenhar(); busca.focus(); }
        });
        busca.addEventListener('input', desenhar);
        lista.addEventListener('change', e => {
            if (e.target.checked) st.sel.add(e.target.value); else st.sel.delete(e.target.value);
            rotulo(); el.dispatchEvent(new Event('mudou'));
        });
        el.querySelector('.ms-acts').addEventListener('click', e => {
            const a = e.target.dataset.a;
            if (a === 'todos') st.itens.forEach(i => st.sel.add(String(i.v)));
            if (a === 'limpar') st.sel.clear();
            desenhar(); rotulo(); el.dispatchEvent(new Event('mudou'));
        });
        rotulo();
        return {
            valores: () => [...st.sel],
            definir(novos, marc = []) { st.itens = novos; st.sel = new Set(marc.map(String)); rotulo(); },
        };
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.ms')) document.querySelectorAll('.ms.open').forEach(o => o.classList.remove('open'));
        if (!e.target.closest('.ac')) document.getElementById('acFornecedor').style.display = 'none';
    });

    const msEmpresa  = multiSelect(document.getElementById('msEmpresa'),  { vazio: 'Selecione...' });
    const msClas     = multiSelect(document.getElementById('msClas'),     { vazio: 'Todas' });
    const msTipoNota = multiSelect(document.getElementById('msTipoNota'), { vazio: 'Todas' });
    const msMascara  = multiSelect(document.getElementById('msMascara'),  { vazio: 'Todas' });

    post(API.filtros, {}).then(d => {
        msEmpresa.definir((d.empresas || []).map(e => ({ v: e.ID, t: `FL ${e.COD_EMP} — ${e.NOME}`, curto: `FL ${e.COD_EMP}` })),
                          (d.empresas || []).filter(e => String(e.COD_EMP) === '1').map(e => e.ID));
        msClas.definir((d.classificacoes || []).map(c => ({ v: c.ID, t: `${c.CODIGO} — ${c.DESCRICAO}`, curto: c.DESCRICAO, g: c.FAMILIA })));
        msTipoNota.definir((d.tiposNota || []).map(n => ({ v: n.ID, t: `${n.COD_TP_NF} — ${n.DESCRICAO}`, curto: String(n.COD_TP_NF) })));
    }).catch(e => { document.getElementById('msgRelatorio').innerHTML = `<span class="text-danger">${esc(e.message)}</span>`; });

    // ── Fornecedor (autocomplete, múltiplos) ───────────────────────
    const inpForn = document.getElementById('inpFornecedor'), acForn = document.getElementById('acFornecedor');
    const chips = document.getElementById('chipsFornecedor');
    const _forn = new Map();
    function desenharChips() {
        chips.innerHTML = [..._forn].map(([id, nome]) => `<span class="chip">${esc(nome)} <i class="bi bi-x" data-id="${esc(id)}"></i></span>`).join('');
    }
    chips.addEventListener('click', e => { if (e.target.dataset.id) { _forn.delete(e.target.dataset.id); desenharChips(); } });
    let _tForn;
    inpForn.addEventListener('input', () => {
        clearTimeout(_tForn);
        const termo = inpForn.value.trim();
        if (termo.length < 2) { acForn.style.display = 'none'; return; }
        _tForn = setTimeout(async () => {
            try {
                const d = await post(API.fornecedores, { termo });
                acForn.innerHTML = (d.rows || []).map(f => `<div data-id="${esc(f.ID)}" data-nome="${esc(f.COD_FOR + ' - ' + f.DESCRICAO)}">${esc(f.COD_FOR)} — ${esc(f.DESCRICAO)}</div>`).join('')
                                   || '<div class="text-muted">Nenhum fornecedor encontrado.</div>';
                acForn.style.display = 'block';
            } catch (_) { acForn.style.display = 'none'; }
        }, 250);
    });
    acForn.addEventListener('click', e => {
        const it = e.target.closest('[data-id]');
        if (!it) return;
        _forn.set(it.dataset.id, it.dataset.nome);
        desenharChips(); inpForn.value = ''; acForn.style.display = 'none';
    });

    // ── Máscaras do item ───────────────────────────────────────────
    const inpItem = document.getElementById('inpItem');
    async function carregarMascaras() {
        const cod = inpItem.value.trim();
        if (!cod) { msMascara.definir([]); return; }
        try {
            const d = await post(API.mascaras, { cod_item: cod, empresas: msEmpresa.valores() });
            msMascara.definir((d.rows || []).map(m => ({ v: m.ID, t: `${m.ID} — ${m.MASCARA}`, curto: String(m.ID) })));
        } catch (_) { msMascara.definir([]); }
    }
    inpItem.addEventListener('change', carregarMascaras);
    document.getElementById('msEmpresa').addEventListener('mudou', () => { if (inpItem.value.trim()) carregarMascaras(); });

    // ── Tabela ─────────────────────────────────────────────────────
    const head = document.getElementById('fuHead'), body = document.getElementById('fuBody'), foot = document.getElementById('fuFoot');
    const inpFiltro = document.getElementById('inpFiltro'), spanTotal = document.getElementById('spanTotalReg');
    const msgEl = document.getElementById('msgRelatorio'), btnExcel = document.getElementById('btnExcel');
    let _rows = [], _sort = { col: null, num: false, dir: 1 }, _kpi = 'todos', _gerado = false;

    function desenharMenuColunas() {
        document.getElementById('colMenu').innerHTML =
            `<div class="px-2 pb-1 d-flex justify-content-between"><a href="#" data-c="todas" style="font-size:.75rem">Mostrar todas</a></div>` +
            COLS.map(c => `<label><input type="checkbox" value="${c.k}" ${_ocultas.has(c.k) ? '' : 'checked'}> ${esc(c.t)}</label>`).join('');
    }
    document.getElementById('colMenu').addEventListener('change', e => {
        if (e.target.checked) _ocultas.delete(e.target.value); else _ocultas.add(e.target.value);
        try { localStorage.setItem(LS_COLS, JSON.stringify([..._ocultas])); } catch (_) {}
        render();
    });
    document.getElementById('colMenu').addEventListener('click', e => {
        if (e.target.dataset.c === 'todas') {
            e.preventDefault(); _ocultas.clear();
            try { localStorage.setItem(LS_COLS, '[]'); } catch (_) {}
            desenharMenuColunas(); render();
        }
    });

    function linhasVisiveis() {
        const termo = inpFiltro.value.trim().toLowerCase();
        let lista = _kpi === 'atrasado' ? _rows.filter(r => String(r.ATRASADO) === '1') : _rows;
        if (termo) {
            lista = lista.filter(r => ['PEDIDO','FORNECEDOR','ITEM','DESC_ITEM','DESC_FORN_ITENS','MASCARA','COMPRADOR','TIPO_NOTA','OBS_ITEM','CNPJ']
                .some(k => String(r[k] ?? '').toLowerCase().includes(termo)));
        }
        if (_sort.col) {
            const c = _sort.col, d = _sort.dir, n = _sort.num;
            lista = [...lista].sort((a, b) => {
                const ea = vazio(a[c]), eb = vazio(b[c]);
                if (ea !== eb) return ea ? 1 : -1;
                const va = n ? num(a[c]) : String(a[c] ?? ''), vb = n ? num(b[c]) : String(b[c] ?? '');
                return (va < vb ? -1 : va > vb ? 1 : 0) * d;
            });
        }
        return lista;
    }

    function render() {
        const cols = visiveis();
        head.innerHTML = '<tr>' + cols.map(c => {
            const sorted = _sort.col === (c.o || c.k);
            return `<th class="${c.r ? 'r' : ''} ${sorted ? 'sorted' : ''}" style="min-width:${c.w}px;max-width:${Math.max(c.w, 90) * 1.6}px"
                        data-col="${c.o || c.k}" data-num="${c.n ? 1 : 0}">${esc(c.t)}<span class="sort">${sorted ? (_sort.dir === 1 ? '▲' : '▼') : '⇅'}</span></th>`;
        }).join('') + '</tr>';

        if (!_rows.length) {
            body.innerHTML = `<tr><td colspan="${cols.length}" class="fu-msg">${_gerado ? 'Nenhum registro encontrado.' : 'Informe os filtros e clique em Gerar.'}</td></tr>`;
            foot.innerHTML = '';
            return;
        }

        const lista = linhasVisiveis();
        const tot = {};
        body.innerHTML = lista.length ? lista.map(r => '<tr>' + cols.map(c => {
            if (c.s) tot[c.k] = (tot[c.k] || 0) + num(r[c.k]);
            const v = c.f ? c.f(r) : esc(c.fmt ? c.fmt(r[c.k]) : r[c.k]);
            const titulo = (!c.r && !c.f && !vazio(r[c.k])) ? ` title="${esc(r[c.k])}"` : '';
            return `<td class="${c.r ? 'r' : ''}" style="max-width:${Math.max(c.w, 90) * 1.6}px"${titulo}>${v}</td>`;
        }).join('') + '</tr>').join('') : `<tr><td colspan="${cols.length}" class="fu-msg">Nenhum item corresponde ao filtro.</td></tr>`;

        foot.innerHTML = '<tr>' + cols.map((c, i) =>
            `<td class="${c.r ? 'r' : ''}">${c.s ? esc((c.fmt || fQtd)(tot[c.k] || 0)) : (i === 0 ? 'Total' : '')}</td>`).join('') + '</tr>';

        spanTotal.textContent = lista.length === _rows.length
            ? `${_rows.length.toLocaleString('pt-BR')} registro(s)`
            : `${lista.length.toLocaleString('pt-BR')} de ${_rows.length.toLocaleString('pt-BR')} registro(s)`;
    }

    head.addEventListener('click', e => {
        const th = e.target.closest('th[data-col]');
        if (!th) return;
        const col = th.dataset.col;
        _sort = { col, num: th.dataset.num === '1', dir: _sort.col === col ? -_sort.dir : 1 };
        render();
    });
    let _tFiltro;
    inpFiltro.addEventListener('input', () => { clearTimeout(_tFiltro); _tFiltro = setTimeout(render, 200); });
    document.querySelectorAll('.fu-kpi').forEach(k => k.addEventListener('click', function () {
        _kpi = this.dataset.f;
        document.querySelectorAll('.fu-kpi').forEach(x => x.classList.toggle('ativo', x.dataset.f === _kpi && (x === this || _kpi === 'atrasado')));
        render();
    }));

    function atualizarKpis() {
        document.getElementById('kLinhas').textContent   = _rows.length.toLocaleString('pt-BR');
        document.getElementById('kPedidos').textContent  = new Set(_rows.map(r => r.EMPRESA + '|' + r.PEDIDO)).size.toLocaleString('pt-BR');
        document.getElementById('kFornec').textContent   = new Set(_rows.map(r => r.FORNECEDOR)).size.toLocaleString('pt-BR');
        document.getElementById('kVlrSaldo').textContent = fVlr(_rows.reduce((s, r) => s + num(r.VLR_SALDO), 0));
        document.getElementById('kAtr').textContent      = _rows.filter(r => String(r.ATRASADO) === '1').length.toLocaleString('pt-BR');
    }

    // ── Gerar ──────────────────────────────────────────────────────
    const btnGerar = document.getElementById('btnGerar');
    btnGerar.addEventListener('click', async function () {
        const empresas = msEmpresa.valores();
        if (!empresas.length) { alert('Selecione ao menos uma empresa.'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        msgEl.innerHTML = ''; spanTotal.textContent = ''; btnExcel.style.display = 'none';
        body.innerHTML = '<tr><td class="fu-msg"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</td></tr>';
        foot.innerHTML = '';
        const t0 = Date.now();
        try {
            const d = await post(API.listar, {
                empresas,
                situacao:       document.getElementById('selSituacao').value,
                classificacoes: msClas.valores(),
                fornecedores:   [..._forn.keys()],
                tipos_nota:     msTipoNota.valores(),
                cod_item:       inpItem.value.trim(),
                mascaras:       msMascara.valores(),
            });
            _rows = Array.isArray(d.rows) ? d.rows : [];
            _gerado = true;
            _kpi = 'todos';
            document.querySelectorAll('.fu-kpi').forEach((x, i) => x.classList.toggle('ativo', i === 0));
            atualizarKpis();
            document.getElementById('fuCards').style.display = '';
            if (_rows.length) btnExcel.style.display = '';
            render();
            msgEl.innerHTML = `<span class="text-muted">Gerado em ${((Date.now() - t0) / 1000).toFixed(1)}s</span>`;
        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
            body.innerHTML = '<tr><td class="fu-msg">—</td></tr>';
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });

    // ── Excel (todas as colunas, linhas filtradas) ─────────────────
    btnExcel.addEventListener('click', function () {
        const lista = linhasVisiveis();
        if (!lista.length) return;
        const valor = (c, r) => {
            if (c.k === 'CNPJ') return fCnpj(r.CNPJ);
            if (c.n && c.r) return vazio(r[c.k]) ? '' : String(num(r[c.k])).replace('.', ',');
            return r[c.k] ?? '';
        };
        const csv = [COLS.map(c => c.t).join(';'),
                     ...lista.map(r => COLS.map(c => `"${String(valor(c, r)).replace(/"/g, '""').replace(/\r?\n/g, ' ')}"`).join(';'))].join('\r\n');
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob), a = document.createElement('a');
        a.href = url; a.download = `follow-up-pedido-compra_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click(); URL.revokeObjectURL(url);
    });

    desenharMenuColunas();
    render();
})();
</script>

<?= $render('footer') ?>
