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
    'pageTitle'  => 'Planejamento — Compra Direta Crossdocking',
    'showNavbar' => true,
    'pageActive' => 'planejamento-compra-direta-cross',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.rel-card { background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.08);padding:14px 20px;margin-bottom:12px; }
.rel-card .form-label { font-size:.75rem; font-weight:600; margin-bottom:2px; }

.pm-cards      { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.pm-kpi        { flex:1 1 120px; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; cursor:pointer; border:2px solid transparent; }
.pm-kpi.ativo  { border-color:#1565c0; }
.pm-kpi .v     { font-size:20px; font-weight:700; color:#1e3a8a; line-height:1.1; }
.pm-kpi .l     { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.pm-kpi.rup .v { color:#c62828; } .pm-kpi.ris .v { color:#e67700; } .pm-kpi.exc .v { color:#6a1b9a; }
.pm-kpi.ok .v  { color:#2e7d32; } .pm-kpi.urg .v { color:#c62828; } .pm-kpi.sel .v { color:#1565c0; }

.pm-card       { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.pm-card-head  { background:#1565c0; color:#fff; padding:7px 14px; font-size:11px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.4px; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.pm-card-head .ctrl { display:flex; gap:6px; align-items:center; }
.pm-card-head input, .pm-card-head select { font-size:12px !important; height:26px; padding:2px 8px !important; }
.pm-wrap       { overflow:auto; max-height:calc(100vh - 330px); }
.pm-tbl                 { border-collapse:separate; border-spacing:0; width:max-content; min-width:100%; }
.pm-tbl thead th        { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 6px !important; font-size:10px !important;
                          font-weight:700 !important; white-space:normal !important; line-height:1.2; vertical-align:bottom;
                          border-bottom:2px solid #bbdefb !important; text-align:left; position:sticky; top:0; z-index:2; cursor:pointer;
                          user-select:none; min-width:58px; max-width:110px; }
.pm-tbl thead th.r      { text-align:right; } .pm-tbl thead th.c { text-align:center; cursor:default; min-width:30px; }
.pm-tbl th .sort        { color:#90caf9; font-size:9px; margin-left:2px; } .pm-tbl th.sorted .sort { color:#1565c0; }
.pm-tbl tbody td        { padding:3px 6px !important; font-size:11px !important; line-height:1.35; border-bottom:1px solid #f0f0f0 !important;
                          white-space:nowrap; max-width:260px; overflow:hidden; text-overflow:ellipsis; }
.pm-tbl td.r            { text-align:right; font-variant-numeric:tabular-nums; } .pm-tbl td.c { text-align:center; }
.pm-tbl tbody tr:hover td       { background:#f5f8fe !important; }
.pm-tbl tbody tr.marcado td     { background:#e8f5e9 !important; }
.pm-tbl td .neg         { color:#c62828; font-weight:700; }
.tag-st        { display:inline-block; padding:0 6px; border-radius:4px; font-size:10px; font-weight:700; cursor:pointer; }
.tag-st.a      { background:#e8f5e9; color:#2e7d32; } .tag-st.n { background:#fdecea; color:#c62828; }
.tag-st.obs    { font-style:italic; text-decoration:underline dotted; }   /* OTM: status em itálico quando tem observação */
.tag-an        { display:inline-block; padding:0 6px; border-radius:4px; font-size:10px; font-weight:700; }
.tag-an.RUPTURA { background:#fdecea; color:#c62828; } .tag-an.RISCO { background:#fff4e5; color:#e67700; }
.tag-an.EXCESSO { background:#f3e5f5; color:#6a1b9a; } .tag-an.OK { background:#e8f5e9; color:#2e7d32; }
.sug-urg       { color:#c62828; font-weight:700; } .sug-ant { color:#e67700; font-weight:700; } .sug-pos { color:#1565c0; }
.btn-acao      { font-size:10px; padding:0 5px; line-height:17px; font-weight:700; }
.inp-dem       { width:90px; text-align:right; font-size:11px !important; height:22px; padding:1px 5px !important; }
.pm-msg        { text-align:center; padding:24px !important; color:#999 !important; font-size:13px !important; white-space:normal !important; }

.det-tbl                { width:100%; border-collapse:collapse; font-size:12px; }
.det-tbl thead th       { background:#e3f2fd !important; color:#1565c0 !important; padding:5px 8px !important; font-size:11px !important;
                          border-bottom:2px solid #bbdefb !important; position:sticky; top:0; }
.det-tbl tbody td       { padding:4px 8px !important; font-size:12px !important; border-bottom:1px solid #f0f0f0 !important; }
.det-tbl tfoot td       { padding:5px 8px !important; font-weight:700 !important; background:#f3f6fb !important; }
.det-tbl .r             { text-align:right; }
.det-tbl tr.click       { cursor:pointer; } .det-tbl tr.click:hover td { background:#f5f8fe !important; }
.det-sub                { font-size:12px; color:#6b7280; margin-bottom:8px; }
</style>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:12px;margin:0;">

    <div class="rel-card">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <h5 class="mb-1 fw-semibold"><i class="bi bi-truck me-1 text-primary"></i> Compra Direta Crossdocking</h5>
                <span id="spanGerado" class="small text-muted"></span>
                <div id="spanMrp" class="small"></div>
            </div>
            <div class="col-md-3 ms-md-3">
                <label class="form-label">Empresa</label>
                <select id="selEmpresa" class="form-select form-select-sm"><option value="">Carregando...</option></select>
            </div>
            <div class="col-auto">
                <label class="form-label">Classificação <span class="text-muted fw-normal">(use % ; várias separadas por vírgula)</span></label>
                <input type="text" id="inpClassif" class="form-control form-control-sm" style="width:230px" value="10.001%">
            </div>
            <div class="col-auto">
                <label class="form-label">Item <span class="text-muted fw-normal">(códigos separados por vírgula)</span></label>
                <input type="text" id="inpItem" class="form-control form-control-sm" style="width:200px">
            </div>
            <div class="col-auto">
                <button id="btnGerar" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Gerar</button>
                <button id="btnLinha" class="btn btn-sm btn-outline-danger ms-1" style="display:none">
                    <i class="bi bi-diagram-3"></i> Gerar Linha (<span id="qtdMarc">0</span>)
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
        <div class="pm-kpi rup" data-f="RUPTURA"><div class="v" id="kRUPTURA">0</div><div class="l">Ruptura</div></div>
        <div class="pm-kpi ris" data-f="RISCO"><div class="v" id="kRISCO">0</div><div class="l">Risco</div></div>
        <div class="pm-kpi exc" data-f="EXCESSO"><div class="v" id="kEXCESSO">0</div><div class="l">Excesso</div></div>
        <div class="pm-kpi ok" data-f="OK"><div class="v" id="kOK">0</div><div class="l">OK</div></div>
        <div class="pm-kpi urg" data-f="urgente"><div class="v" id="kUrg">0</div><div class="l">Pedido urgente</div></div>
        <div class="pm-kpi sel" data-f="marcado"><div class="v" id="kMarc">0</div><div class="l">Marcados</div></div>
    </div>

    <div class="pm-card">
        <div class="pm-card-head">
            <span>Itens — marque e informe a Qtde Demanda para o "Gerar Linha"</span>
            <div class="ctrl">
                <select id="selSug" class="form-select form-select-sm" style="width:180px"><option value="">Toda sugestão progr.</option></select>
                <input type="text" id="inpFiltro" class="form-control form-control-sm" style="width:260px" placeholder="Localizar / filtrar (código, descrição, máscara...)">
            </div>
        </div>
        <div class="pm-wrap">
            <table class="pm-tbl">
                <thead><tr id="trHead"></tr></thead>
                <tbody id="pmBody"><tr><td colspan="40" class="pm-msg">Selecione a empresa, informe a classificação ou o item e clique em Gerar.</td></tr></tbody>
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
        filtros: 'planejamento-api-cdc-filtros', gerar: 'planejamento-api-cdc-gerar', listar: 'planejamento-api-cdc-listar',
        detalhe: 'planejamento-api-cdc-detalhe', status: 'planejamento-api-cdc-status',
        opcoes: 'planejamento-api-cdc-opcoes-linha', gerarLinha: 'planejamento-api-cdc-gerar-linha',
        mrpStatus: 'planejamento-api-cdc-mrp-status', liberar: 'planejamento-api-cdc-liberar', concluir: 'planejamento-api-cdc-concluir',
    };

    const esc   = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    const vazio = v => v === null || v === undefined || v === '';
    const num   = v => vazio(v) ? 0 : (parseFloat(String(v).replace(',', '.')) || 0);
    const f0    = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    const f2    = v => vazio(v) ? '' : num(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    const dOrd  = v => vazio(v) || !/^\d{2}\/\d{2}\/\d{4}$/.test(v) ? '' : v.slice(6) + v.slice(3, 5) + v.slice(0, 2);

    async function post(url, dados) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(dados || {}) });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    // Colunas na ordem da tela do OTM. t = tipo: n (número), d (data dd/mm/aaaa), s (texto)
    const COLS = [
        { k: 'EMPRESA', h: 'Empresa', t: 'n' },
        { k: 'COD_ITEM', h: 'Cód. Item', t: 'n' },
        { k: 'DESC_ITEM', h: 'Desc. Item', t: 's' },
        { k: 'MASC_ID', h: 'Config. ID', t: 'n' },
        { k: 'MASCARA', h: 'Máscara', t: 's' },
        { k: 'TIPO_PRODUTO', h: 'Status', t: 's', f: r => {
            const ina = r.TIPO_PRODUTO === 'N';
            return `<span class="tag-st ${ina ? 'n' : 'a'} ${r.OBS ? 'obs' : ''}" data-act="status" title="${esc(r.OBS ? 'Obs.: ' + r.OBS + ' — clique para alterar' : 'Clique para alterar o status')}">${ina ? 'INATIVO' : 'ATIVO'}</span>`; } },
        { k: 'QTDE_DEMANDA', h: 'Qtde Demanda', t: 'n', r: 1, f: r => `<input type="number" min="0" step="any" class="form-control inp-dem" data-k="${esc(r.CHAVE)}" value="${esc(estado(r).qtde || '')}">` },
        { k: 'CLASSIFICACAO', h: 'Classificação', t: 's' },
        { k: 'ESTOQUE_PRINC', h: 'Estoque Principal', t: 'n', r: 1 },
        { k: 'ESTOQUE_PROD', h: 'Estoque Produção', t: 'n', r: 1 },
        { k: 'PONTO_REPOSICAO', h: 'Ponto de Reposição', t: 'n', r: 1 },
        { k: 'AUTONOMIA', h: 'Autonomia', t: 'n', r: 1, z: 1 },
        { k: 'AUTONOMIA_EST', h: 'Autonomia Est.', t: 'd' },
        { k: 'EST_FUTURO', h: 'Estoque Futuro', t: 'n', r: 1 },
        { k: 'SUGESTAO_PROGR', h: 'Sugestão Progr.', t: 's', f: r => {
            const s = r.SUGESTAO_PROGR || '';
            const c = s === 'PEDIDO URGENTE' ? 'sug-urg' : s === 'ANTECIPAR' ? 'sug-ant' : s === 'POSTERGAR' ? 'sug-pos' : '';
            return `<span class="${c}">${esc(s)}</span>`; } },
        { k: 'NOVA_DATA', h: 'Nova Data', t: 'd' },
        { k: 'DATA_LIMITE', h: 'Data Limite', t: 'd' },
        { k: 'SUGESTAO_COMPRA', h: 'Sugestão de Compra', t: 'n', r: 1 },
        { k: 'MEDIA_TRI', h: 'Média Tri.', t: 'n', r: 1 },
        { k: 'MEDIA_MENSAL', h: 'Média Mensal', t: 'n', r: 1 },
        { k: 'CONSUMO_DIA_CALC', h: 'Consumo dia', t: 'n', r: 1 },
        { k: 'PROJECAO_MES', h: 'Projeção Consumo Mês', t: 'n', r: 1 },
        { k: 'DESVIO_6M', h: 'Desvio P. 6 Meses', t: 'n', r: 1 },
        { k: 'PEDIDO_COMPRA', h: 'Pedido Compra', t: 'n' },
        { k: 'DT_INCLUSAO', h: 'Inclusão', t: 'd' },
        { k: 'DT_PREV_ENTREGA', h: 'Previsão', t: 'd' },
        { k: 'QTDE', h: 'Qtde', t: 'n', r: 1 },
        { k: 'QTDE_PDC', h: 'Saldo', t: 'n', r: 1 },
        { k: 'QTDE_PEDIDOS', h: 'Qtde Pedidos', t: 'n', r: 1 },
        { k: 'LEAD_TIME', h: 'Lead-Time', t: 'n', r: 1, z: 1 },
        { k: 'AUTONOMIA_MAX', h: 'Autonomia Máximo', t: 'n', r: 1, z: 1 },
        { k: 'ESTOQUE_MIN_10', h: 'Estoque Mín. 10 dias', t: 'n', r: 1 },
        { k: 'EST_MAX', h: 'Estoque Máximo', t: 'n', r: 1 },
        { k: 'ANALISE', h: 'Análise', t: 's', f: r => `<span class="tag-an ${esc(r.ANALISE)}">${esc(r.ANALISE)}</span>` },
        { k: 'DATA_LIMITE_UTIL', h: 'Data Limite (dia útil)', t: 'd' },
    ];

    // ── Estado ─────────────────────────────────────────────────────
    const body = document.getElementById('pmBody'), msgEl = document.getElementById('msgRelatorio');
    const spanTotal = document.getElementById('spanTotalReg'), inpFiltro = document.getElementById('inpFiltro');
    const selSug = document.getElementById('selSug'), selEmp = document.getElementById('selEmpresa');
    let _rows = [], _par = null, _sort = { k: null, t: 's', dir: 1 }, _kpi = 'todos';
    const _marc = new Map();   // chave -> { m: marcado, qtde }
    const estado = r => _marc.get(r.CHAVE) || { m: false, qtde: '' };
    const isMarc = r => estado(r).m;
    const isUrg  = r => r.SUGESTAO_PROGR === 'PEDIDO URGENTE';
    const KPI = { todos: () => true, urgente: isUrg, marcado: isMarc };

    document.getElementById('trHead').innerHTML =
        '<th class="c"><input type="checkbox" id="chkTodos" title="Marcar/desmarcar os itens visíveis"></th>' +
        COLS.map(c => `<th data-k="${c.k}" data-t="${c.t}" class="${c.r ? 'r' : ''}">${c.h}<span class="sort">⇅</span></th>`).join('') +
        '<th class="c">Ações</th>';

    function valorOrdem(r, c) {
        if (c.k === 'QTDE_DEMANDA') return num(estado(r).qtde);
        const v = r[c.k];
        if (c.t === 'n') return vazio(v) ? null : num(v);
        if (c.t === 'd') return dOrd(v) || null;
        return vazio(v) ? null : String(v);
    }

    function linhasVisiveis() {
        const termo = inpFiltro.value.trim().toLowerCase(), sug = selSug.value;
        let lista = _rows.filter(r => KPI[_kpi] ? KPI[_kpi](r) : r.ANALISE === _kpi);
        if (sug) lista = lista.filter(r => (r.SUGESTAO_PROGR || '(vazio)') === sug);
        if (termo) lista = lista.filter(r => [r.COD_ITEM, r.DESC_ITEM, r.MASC_ID, r.MASCARA, r.CLASSIFICACAO, r.PEDIDO_COMPRA, r.OBS]
            .some(v => String(v ?? '').toLowerCase().includes(termo)));
        if (_sort.k) {
            const c = COLS.find(x => x.k === _sort.k), d = _sort.dir;
            lista = [...lista].sort((a, b) => {
                const va = valorOrdem(a, c), vb = valorOrdem(b, c);
                if ((va === null) !== (vb === null)) return va === null ? 1 : -1;
                return (va < vb ? -1 : va > vb ? 1 : 0) * d;
            });
        }
        return lista;
    }

    function atualizarKpis() {
        const n = f => _rows.filter(f).length.toLocaleString('pt-BR');
        document.getElementById('kTotal').textContent = _rows.length.toLocaleString('pt-BR');
        ['RUPTURA', 'RISCO', 'EXCESSO', 'OK'].forEach(a => document.getElementById('k' + a).textContent = n(r => r.ANALISE === a));
        document.getElementById('kUrg').textContent = n(isUrg);
        const qm = _rows.filter(isMarc).length;
        document.getElementById('kMarc').textContent = qm.toLocaleString('pt-BR');
        document.getElementById('qtdMarc').textContent = qm;
    }

    function celula(r, c) {
        if (c.f) return `<td class="${c.r ? 'r' : ''}">${c.f(r)}</td>`;
        const v = r[c.k];
        if (c.t === 'n' && c.r) {
            const txt = c.z ? f0(v) : f2(v);
            return `<td class="r">${num(v) < 0 ? `<span class="neg">${txt}</span>` : txt}</td>`;
        }
        return `<td title="${esc(v)}">${esc(v)}</td>`;
    }

    function linhaHtml(r) {
        return `<tr data-k="${esc(r.CHAVE)}" class="${isMarc(r) ? 'marcado' : ''}">
            <td class="c"><input type="checkbox" class="chk" ${isMarc(r) ? 'checked' : ''}></td>
            ${COLS.map(c => celula(r, c)).join('')}
            <td class="c">
                <button class="btn btn-outline-primary btn-acao" data-act="perfil" title="Perfil do item">Perfil</button>
                <button class="btn btn-outline-primary btn-acao" data-act="pedidos" title="${Number(r.EMPR_ID) === 1 ? 'Pedidos de venda' : 'Pedidos de compra'}">Pedidos</button>
            </td>
        </tr>`;
    }

    function render() {
        if (!_par) { body.innerHTML = '<tr><td colspan="40" class="pm-msg">Selecione a empresa, informe a classificação ou o item e clique em Gerar.</td></tr>'; return; }
        const lista = linhasVisiveis();
        const LIM = 2000;
        body.innerHTML = lista.length ? lista.slice(0, LIM).map(linhaHtml).join('') +
            (lista.length > LIM ? `<tr><td colspan="40" class="pm-msg">Mostrando ${LIM} de ${lista.length} — use o filtro para reduzir.</td></tr>` : '')
            : `<tr><td colspan="40" class="pm-msg">${_rows.length ? 'Nenhum item corresponde ao filtro.' : 'Nenhum item retornado.'}</td></tr>`;
        document.getElementById('chkTodos').checked = lista.length > 0 && lista.every(isMarc);
        spanTotal.textContent = lista.length === _rows.length ? `${_rows.length.toLocaleString('pt-BR')} registro(s)`
            : `${lista.length.toLocaleString('pt-BR')} de ${_rows.length.toLocaleString('pt-BR')} registro(s)`;
    }

    function aplicar(d) {
        _par = d.parametros || null;
        _rows = Array.isArray(d.rows) ? d.rows : [];
        _marc.clear();
        const sg = document.getElementById('spanGerado');
        sg.innerHTML = _par ? `<i class="bi bi-clock-history"></i> Gerado em <b>${esc(_par.dt_geracao)}</b> · ${esc(_par.segundos)}s` : '';
        const sugs = [...new Set(_rows.map(r => r.SUGESTAO_PROGR || '(vazio)'))].sort();
        selSug.innerHTML = '<option value="">Toda sugestão progr.</option>' + sugs.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        document.getElementById('pmCards').style.display = _par ? '' : 'none';
        document.getElementById('btnLinha').style.display = _par ? '' : 'none';
        document.getElementById('btnExcel').style.display = _rows.length ? '' : 'none';
        atualizarKpis(); render();
    }

    // ── Carregar / Gerar ───────────────────────────────────────────
    post(API.filtros, {}).then(d => {
        selEmp.innerHTML = '<option value="">Selecione...</option>' +
            (d.empresas || []).map(e => `<option value="${esc(e.ID)}">${esc(e.COD_EMP)} — ${esc(e.NOME)}</option>`).join('');
        const par = d.parametros;
        if (par) {
            selEmp.value = String(par.empresa);
            document.getElementById('inpClassif').value = par.classificacao || '';
            document.getElementById('inpItem').value = par.item || '';
            body.innerHTML = '<tr><td colspan="40" class="pm-msg"><span class="spinner-border spinner-border-sm me-2"></span>Carregando a última geração...</td></tr>';
            return post(API.listar, {}).then(aplicar);
        }
    }).catch(e => { msgEl.innerHTML = `<span class="text-danger">${esc(e.message)}</span>`; });

    document.getElementById('btnGerar').addEventListener('click', async function () {
        if (!selEmp.value) { alert('Selecione a empresa.'); return; }
        const classificacao = document.getElementById('inpClassif').value.trim(), item = document.getElementById('inpItem').value.trim();
        if (!classificacao && !item) { alert('Informe a classificação ou o item.'); return; }
        this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
        msgEl.innerHTML = '<span class="text-muted">Calculando... (classificação ampla pode levar alguns minutos)</span>';
        const t0 = Date.now();
        try {
            aplicar(await post(API.gerar, { empresa: selEmp.value, classificacao, item }));
            msgEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Gerado em ${((Date.now() - t0) / 1000).toFixed(0)}s.</span>`;
        } catch (e) {
            msgEl.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</span>`;
        } finally {
            this.disabled = false; this.innerHTML = '<i class="bi bi-search"></i> Gerar';
        }
    });

    // ── Filtros / ordenação / KPIs ─────────────────────────────────
    let _tF; inpFiltro.addEventListener('input', () => { clearTimeout(_tF); _tF = setTimeout(render, 200); });
    selSug.addEventListener('change', render);
    document.querySelectorAll('.pm-kpi').forEach(k => k.addEventListener('click', function () {
        _kpi = this.dataset.f; document.querySelectorAll('.pm-kpi').forEach(x => x.classList.toggle('ativo', x === this)); render();
    }));
    document.getElementById('trHead').addEventListener('click', e => {
        const th = e.target.closest('th[data-k]'); if (!th) return;
        const k = th.dataset.k;
        _sort = { k, t: th.dataset.t, dir: _sort.k === k ? -_sort.dir : 1 };
        document.querySelectorAll('#trHead th[data-k]').forEach(h => {
            h.classList.toggle('sorted', h === th);
            h.querySelector('.sort').textContent = h === th ? (_sort.dir === 1 ? '▲' : '▼') : '⇅';
        });
        render();
    });

    // ── Marcar / Qtde Demanda (ficam na tela, como a tabela de sessão do OTM) ──
    function marcar(r, flag) {
        const s = { ...estado(r), m: flag };
        _marc.set(r.CHAVE, s);
    }
    document.getElementById('trHead').addEventListener('change', e => {
        if (e.target.id !== 'chkTodos') return;
        const lista = linhasVisiveis();
        if (e.target.checked && lista.length > 300 && !confirm(`Marcar ${lista.length} itens?`)) { e.target.checked = false; return; }
        lista.forEach(r => marcar(r, e.target.checked));
        atualizarKpis(); render();
    });
    body.addEventListener('change', e => {
        const tr = e.target.closest('tr[data-k]'); if (!tr) return;
        const r = _rows.find(x => x.CHAVE === tr.dataset.k); if (!r) return;
        if (e.target.classList.contains('chk')) { marcar(r, e.target.checked); tr.classList.toggle('marcado', e.target.checked); atualizarKpis(); }
        if (e.target.classList.contains('inp-dem')) {
            const v = e.target.value === '' ? '' : Math.max(0, num(e.target.value));
            _marc.set(r.CHAVE, { ...estado(r), qtde: v });
        }
    });

    // ── Modal ──────────────────────────────────────────────────────
    const mdlEl = document.getElementById('mdl'); let _mdl = null;
    function modal(titulo, corpoHtml, rodape = '', tam = 'modal-lg') {
        document.getElementById('mdlDialog').className = `modal-dialog modal-dialog-scrollable ${tam}`;
        document.getElementById('mdlTitulo').innerHTML = titulo;
        document.getElementById('mdlCorpo').innerHTML = corpoHtml;
        const rod = document.getElementById('mdlRodape'); rod.innerHTML = rodape; rod.style.display = rodape ? '' : 'none';
        if (!_mdl) _mdl = new bootstrap.Modal(mdlEl);
        _mdl.show();
    }
    const corpo = () => document.getElementById('mdlCorpo');
    const carregando = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Carregando...</div>';
    const erroHtml = e => `<div class="text-danger py-3"><i class="bi bi-exclamation-circle"></i> ${esc(e.message)}</div>`;
    const sub = r => `<div class="det-sub">Empresa <b>${esc(r.EMPRESA)}</b> · <b>${esc(r.COD_ITEM)}</b> — ${esc(r.DESC_ITEM)}${r.MASCARA ? ` · <b>ID ${esc(r.MASC_ID)}</b> ${esc(r.MASCARA)}` : ''}</div>`;
    function tabela(cols, rows, opts = {}) {
        if (!rows.length) return '<div class="text-muted py-3">Nenhum registro encontrado.</div>';
        const tot = {};
        const tb = rows.map((r, i) => { cols.forEach(c => { if (c.s) tot[c.k] = (tot[c.k] || 0) + num(r[c.k]); });
            return `<tr${opts.click ? ` class="click" data-i="${i}"` : ''}>` + cols.map(c => `<td class="${c.r ? 'r' : ''}">${c.f ? c.f(r) : (c.r ? f2(r[c.k]) : esc(r[c.k]))}</td>`).join('') + '</tr>'; }).join('');
        const tf = cols.some(c => c.s) ? '<tfoot><tr>' + cols.map((c, i) => `<td class="${c.r ? 'r' : ''}">${c.s ? f2(tot[c.k]) : (i === 0 ? 'Total' : '')}</td>`).join('') + '</tr></tfoot>' : '';
        return `<table class="det-tbl"><thead><tr>${cols.map(c => `<th class="${c.r ? 'r' : ''}">${c.t}</th>`).join('')}</tr></thead><tbody>${tb}</tbody>${tf}</table>`;
    }

    async function abrirPerfil(r) {
        modal('Perfil Item', sub(r) + carregando, '', 'modal-lg');
        try {
            const rows = (await post(API.detalhe, { tipo: 'perfil', chave: r.CHAVE })).rows || [];
            corpo().innerHTML = sub(r) + tabela([
                { t: 'Data', k: 'DATA' }, { t: 'OFA/OFM', k: 'OFA_OFM', r: 1 }, { t: 'OFP', k: 'OFP', r: 1 },
                { t: 'Demandas', k: 'DEMANDA', r: 1 }, { t: 'OCL', k: 'OCL', r: 1 },
                { t: 'Projetado', k: 'PROJETADO', r: 1, f: p => num(p.PROJETADO) < 0 ? `<span class="text-danger fw-bold">${f2(p.PROJETADO)}</span>` : f2(p.PROJETADO) },
            ], rows) + (rows.length ? `<div class="small text-muted mt-2">Plano 1 da empresa. Projetado parte do saldo atual (${f2(rows[0].ESTOQUE)}): saldo − demandas − OFA/OFM + OFP + OCL, data a data.</div>` : '');
        } catch (e) { corpo().innerHTML = sub(r) + erroHtml(e); }
    }

    async function abrirPedidos(r) {
        const pdv = Number(r.EMPR_ID) === 1;
        modal(pdv ? 'Pedidos na Filial (venda)' : 'Pedidos na Filial (compra)', sub(r) + carregando, '', 'modal-xl');
        try {
            const rows = (await post(API.detalhe, { tipo: 'pedidos', chave: r.CHAVE })).rows || [];
            corpo().innerHTML = sub(r) + tabela([
                { t: 'Pedido', k: 'PEDIDO', f: p => pdv ? esc(p.PEDIDO) : `<a href="#" onclick="return false">${esc(p.PEDIDO)}</a>` },
                { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1, s: 1 }, { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1, s: 1 },
                { t: 'UM', k: 'UM' },
                { t: 'Data Entrega', k: 'DT_ENTREGA', f: p => String(p.ATRASADO) === '1' ? `<span class="text-danger fw-bold">${esc(p.DT_ENTREGA)}</span>` : esc(p.DT_ENTREGA) },
                { t: pdv ? 'Cliente' : 'Fornecedor', k: 'PARCEIRO' },
                ...(pdv ? [] : [{ t: 'Comprador', k: 'COMPRADOR' }]),
            ], rows, { click: !pdv }) + (!pdv && rows.length ? '<div class="small text-muted mt-2">Clique no pedido para ver todos os itens pendentes dele.</div>' : '');
            if (!pdv) corpo().querySelectorAll('tr.click').forEach(tr => tr.addEventListener('click', () => abrirPedidoItens(rows[tr.dataset.i], r)));
        } catch (e) { corpo().innerHTML = sub(r) + erroHtml(e); }
    }
    async function abrirPedidoItens(p, r) {
        modal(`Itens Pendentes do Pedido ${esc(p.PEDIDO)}`, carregando, '<button class="btn btn-sm btn-outline-secondary" id="btnVoltar"><i class="bi bi-arrow-left"></i> Voltar</button>', 'modal-xl');
        document.getElementById('btnVoltar').onclick = () => abrirPedidos(r);
        try {
            const rows = (await post(API.detalhe, { tipo: 'pedido_itens', ped_id: p.PED_ID })).rows || [];
            corpo().innerHTML = tabela([
                { t: 'Cód. Item', k: 'COD_ITEM' }, { t: 'Desc. Item', k: 'DESC_ITEM' }, { t: 'ID', k: 'MASC_ID' }, { t: 'Máscara', k: 'MASCARA' },
                { t: 'Inclusão', k: 'DT_INCLUSAO' }, { t: 'Entrega', k: 'DT_ENTREGA' },
                { t: 'Qtde Original', k: 'QTDE_ORIGINAL', r: 1 }, { t: 'Qtde Pendente', k: 'QTDE_PENDENTE', r: 1 }, { t: 'UM', k: 'UM' },
            ], rows);
        } catch (e) { corpo().innerHTML = erroHtml(e); }
    }

    function abrirStatus(r) {
        const ina = r.TIPO_PRODUTO === 'N';
        modal('Alterar Status', sub(r) + `
            <div class="row g-2">
                <div class="col-4"><label class="form-label small fw-semibold">Status</label>
                    <select id="stSel" class="form-select form-select-sm"><option value="A" ${ina ? '' : 'selected'}>ATIVO</option><option value="N" ${ina ? 'selected' : ''}>INATIVO</option></select></div>
                <div class="col-8"><label class="form-label small fw-semibold">Observação</label>
                    <input id="stObs" class="form-control form-control-sm" maxlength="100" style="text-transform:uppercase" value="${esc(r.OBS || '')}"></div>
            </div>
            <div class="small text-muted mt-2">Como no Focco: altera o status do item${r.MASCARA ? ' (nesta máscara)' : ''} em <b>todas as empresas</b> e grava a observação no histórico.</div>`,
            '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-sm btn-primary" id="stSalvar"><i class="bi bi-check2"></i> Salvar</button>', '');
        document.getElementById('stSalvar').onclick = async function () {
            if (!confirm('Salvar registro?')) return;
            this.disabled = true;
            try {
                const d = await post(API.status, { chave: r.CHAVE, status: document.getElementById('stSel').value, obs: document.getElementById('stObs').value });
                _rows.forEach(x => { if (x.ITEM_ID === r.ITEM_ID && x.MASCARA === r.MASCARA) { x.TIPO_PRODUTO = d.tipo_produto; x.OBS = d.obs; } });
                _mdl.hide(); render();
            } catch (e) { alert('Erro: ' + e.message); this.disabled = false; }
        };
    }

    body.addEventListener('click', ev => {
        const tr = ev.target.closest('tr[data-k]'); if (!tr) return;
        const r = _rows.find(x => x.CHAVE === tr.dataset.k); if (!r) return;
        const act = ev.target.closest('[data-act]')?.dataset.act;
        if (act === 'perfil') abrirPerfil(r);
        if (act === 'pedidos') abrirPedidos(r);
        if (act === 'status') abrirStatus(r);
    });

    // ── Gerar Linha (demanda independente + MRP) ───────────────────
    document.getElementById('btnLinha').addEventListener('click', async () => {
        const marcados = _rows.filter(isMarc);
        if (!marcados.length) { alert('Marque ao menos um item.'); return; }
        const semQtde = marcados.filter(r => !(num(estado(r).qtde) > 0));
        modal('Gerar Linha', carregando, '', 'modal-lg');
        try {
            const op = await post(API.opcoes, {});
            corpo().innerHTML = (semQtde.length ? `<div class="alert alert-warning py-2 small">Favor verificar itens selecionados sem quantidade para a demanda! (${semQtde.length})</div>` : '') + `
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-semibold">Plano</label>
                        <select id="glPlano" class="form-select form-select-sm"><option value="">Selecione...</option>${(op.planos || []).map(p => `<option value="${esc(p.ID)}">${esc(p.COD_PLANO)} - ${esc(p.DESCRICAO)}</option>`).join('')}</select></div>
                    <div class="col-6"><label class="form-label small fw-semibold">Centro de Custo</label>
                        <select id="glCc" class="form-select form-select-sm"><option value="">Selecione...</option>${(op.centros_custo || []).map(c => `<option value="${esc(c.ID)}">${esc(c.COD)} - ${esc(c.DESCRICAO)}</option>`).join('')}</select></div>
                </div>` + tabela([
                    { t: 'Cód. Item', k: 'COD_ITEM' }, { t: 'Desc. Item', k: 'DESC_ITEM' }, { t: 'Config. ID', k: 'MASC_ID' }, { t: 'Máscara', k: 'MASCARA' },
                    { t: 'Qtde Demanda', k: 'Q', r: 1, f: r => num(estado(r).qtde) > 0 ? f2(estado(r).qtde) : '<span class="text-danger fw-bold">0</span>' },
                ], marcados) + ((op.em_calculo || []).length
                    ? `<div class="alert alert-warning py-2 small mt-2 mb-0"><i class="bi bi-hourglass-split"></i> Já existe MRP rodando nesta empresa (plano ${(op.em_calculo || []).map(c => esc(c.COD_PLANO)).join(', ')}). O cálculo desta demanda vai esperar ele terminar (a rotina do Focco confere a cada 60 segundos).</div>` : '');
            document.getElementById('mdlRodape').style.display = '';
            document.getElementById('mdlRodape').innerHTML = '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>' +
                `<button class="btn btn-sm btn-danger" id="glGerar" ${semQtde.length ? 'disabled' : ''}><i class="bi bi-diagram-3"></i> Gerar Linha</button>`;
            const btn = document.getElementById('glGerar');
            btn.onclick = async () => {
                const plano = document.getElementById('glPlano').value, cc = document.getElementById('glCc').value;
                if (!plano || !cc) { alert('Selecione o plano e o centro de custo.'); return; }
                if (!confirm(`Deseja gerar linha independente para os ${marcados.length} itens selecionados?\n\nIsso CRIA demandas independentes no Focco e executa o MRP do plano.`)) return;
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
                try {
                    await post(API.gerarLinha, { plano_id: plano, cc_id: cc, itens: marcados.map(r => ({ chave: r.CHAVE, qtde: estado(r).qtde })) });
                    _marc.clear(); atualizarKpis(); render();
                    abrirAcompanhamento();
                } catch (e) {
                    // não reabilita o botão: a demanda pode ter sido gravada (evita demanda em duplicidade)
                    corpo().innerHTML = `<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> ${esc(e.message)}</div>
                        <div class="small text-muted">Antes de gerar de novo, confira em Demandas Independentes (empresa ${esc(String(_par?.empresa ?? ''))}) se a demanda foi criada.</div>`;
                    document.getElementById('mdlRodape').innerHTML = '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>';
                }
            };
        } catch (e) { corpo().innerHTML = erroHtml(e); }
    });

    // ── Acompanhamento do MRP da demanda + liberação da ordem (FGAZIN_SUP004) ──
    const spanMrp = document.getElementById('spanMrp');
    let _acompAberto = false, _timer = null, _ultimo = null;
    const hojeIso = () => { const h = new Date(); return `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`; };
    const isoDeBr = s => /^\d{2}\/\d{2}\/\d{4}$/.test(s || '') ? `${s.slice(6)}-${s.slice(3, 5)}-${s.slice(0, 2)}` : '';
    mdlEl.addEventListener('hidden.bs.modal', () => {
        _acompAberto = false; clearTimeout(_timer);
        if (_ultimo && _ultimo.estado && _ultimo.estado.status === 'rodando') _timer = setTimeout(tick, 15000);   // segue atualizando o aviso do topo
    });

    function atualizarBanner(d) {
        const e = d && d.estado;
        if (!e) { spanMrp.innerHTML = ''; return; }
        const txt = e.status === 'rodando' ? `<span class="spinner-border spinner-border-sm text-primary"></span> MRP da demanda <b>${esc(e.num_demanda)}</b> rodando`
            : e.status === 'erro' ? `<i class="bi bi-exclamation-triangle-fill text-danger"></i> MRP da demanda <b>${esc(e.num_demanda)}</b> com erro`
            : `<i class="bi bi-check-circle-fill text-success"></i> MRP da demanda <b>${esc(e.num_demanda)}</b> concluído${(d.liberadas || []).length ? ' · ordem liberada' : ' · ordem a liberar'}`;
        spanMrp.innerHTML = `${txt} <a href="#" id="lnkMrp" class="ms-1">ver</a>`;
        document.getElementById('lnkMrp').onclick = ev => { ev.preventDefault(); abrirAcompanhamento(); };
        document.getElementById('btnLinha').disabled = e.status === 'rodando';
    }

    async function abrirAcompanhamento() {
        _acompAberto = true;
        modal('Gerar Linha — MRP e liberação da ordem', carregando, '', 'modal-xl');
        await tick();
    }

    async function tick() {
        clearTimeout(_timer);
        let d;
        try { d = await post(API.mrpStatus, {}); } catch (e) { if (_acompAberto) corpo().innerHTML = erroHtml(e); return; }
        _ultimo = d; atualizarBanner(d);
        if (!_acompAberto) { if (d.estado && d.estado.status === 'rodando') _timer = setTimeout(tick, 15000); return; }
        renderAcomp(d);
        if (d.estado && d.estado.status === 'rodando') _timer = setTimeout(tick, 5000);
    }

    function tabelaEtapas(etapas) {
        if (!etapas.length) return '<div class="small text-muted">Aguardando a rotina do MRP começar...</div>';
        return tabela([
            { t: 'Etapa do MRP', k: 'DESCRICAO', f: x => String(x.NIVEL) === '9' ? `<span class="text-danger">${esc(x.DESCRICAO)}</span>` : (x.FIM ? esc(x.DESCRICAO) : `<b>${esc(x.DESCRICAO)}</b> <span class="spinner-border spinner-border-sm text-primary"></span>`) },
            { t: 'Início', k: 'INICIO' }, { t: 'Fim', k: 'FIM' }, { t: 'Tempo (s)', k: 'SEGUNDOS', r: 1 },
        ], etapas);
    }

    function renderAcomp(d) {
        const rod = document.getElementById('mdlRodape'); rod.style.display = '';
        const e = d.estado;
        if (!e) { corpo().innerHTML = '<div class="text-muted py-3">Nenhuma demanda em acompanhamento.</div>'; rod.innerHTML = '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>'; return; }
        const cab = `<div class="det-sub">Demanda nº <b>${esc(e.num_demanda)}</b> · Plano <b>${esc(e.plano_cod)} - ${esc(e.plano_desc)}</b> · início ${esc(String(e.inicio).slice(11))}${e.fim ? ` · fim ${esc(String(e.fim).slice(11))}` : ''}</div>`;

        if (e.status === 'rodando') {
            const fila = (d.fila || []).length ? `<div class="alert alert-warning py-2 small"><i class="bi bi-hourglass-split"></i> Aguardando o MRP do plano ${(d.fila || []).map(f => `${esc(f.COD_PLANO)} (${esc(f.ETAPA || '')})`).join(', ')} terminar — a rotina do Focco espera em ciclos de 60 segundos.</div>` : '';
            corpo().innerHTML = cab + `<div class="alert alert-info py-2 small"><span class="spinner-border spinner-border-sm me-1"></span> Demanda criada. Rodando o MRP do plano — costuma levar de 8 a 10 minutos. Pode fechar esta janela: o cálculo continua e o aviso no topo da tela mostra quando terminar.</div>` + fila + tabelaEtapas(d.etapas || []);
            rod.innerHTML = '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar (continua rodando)</button>';
            return;
        }
        if (e.status === 'erro') {
            corpo().innerHTML = cab + `<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle"></i> ${esc(e.erro || 'Erro no MRP.')} A demanda ${esc(e.num_demanda)} foi criada; rode o MRP do plano pelo Focco.</div>` + tabelaEtapas(d.etapas || []);
            rod.innerHTML = '<button class="btn btn-sm btn-outline-secondary me-auto" id="btnConcluir">Concluir</button><button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>';
            document.getElementById('btnConcluir').onclick = concluir;
            return;
        }

        // concluído: ordens geradas para a demanda
        const hoje = hojeIso();
        let algumaLiberavel = false;
        const blocos = (d.itens || []).map(it => {
            const dem = it.demanda;
            const sub = `<div class="fw-semibold mt-2">${esc(it.cod_item)} — ${esc(it.desc)}${it.mascara ? ` · ${esc(it.mascara)}` : ''}</div>`;
            if (!dem) return sub + '<div class="small text-muted mb-2">A linha da demanda não apareceu no perfil deste plano (o item pode não ser planejado nele).</div>';
            const numIt = it.num_demanda || e.num_demanda;
            const info = `<div class="small text-muted mb-1">Demanda ${esc(numIt)}:<b>${f2(dem.QTDE)}</b> em ${esc(dem.DATA)} · estoque projetado depois da demanda: <b>${f2(dem.PROJ)}</b></div>`;
            if (!it.ordens.length) return sub + info + '<div class="alert alert-secondary py-1 small mb-2">O MRP não gerou ordem para esta demanda: o estoque projetado cobre a necessidade (acima do estoque de segurança).</div>';
            // O MRP repõe o saldo do dia (todas as demandas + estoque de segurança), não cada demanda:
            // pré-marca só as ordens que somam a quantidade desta demanda; as demais ficam desmarcadas.
            const outras = (it.demandas_dia || []).filter(x => String(x.NUM_DEMANDA) !== String(numIt));
            const marcar = new Set(); let acum = 0;
            it.ordens.filter(o => o.LIBERAVEL).forEach(o => { if (acum < num(dem.QTDE) - 0.0001) { marcar.add(String(o.PERFIL_ID)); acum += num(o.QTDE_LOTE); } });
            const totOrd = it.ordens.reduce((s, o) => s + num(o.QTDE), 0);
            const totDia = (it.demandas_dia || []).reduce((s, x) => s + num(x.QTDE), 0);
            const nOutras = it.ordens.filter(o => o.LIBERAVEL && !marcar.has(String(o.PERFIL_ID))).length;
            const avisoDia = nOutras ? `<div class="small text-muted mb-1"><i class="bi bi-info-circle"></i>
                O MRP repõe o saldo do dia, não cada demanda${outras.length ? ` (no dia também há ${outras.map(x => `a demanda ${esc(x.NUM_DEMANDA)} de ${f2(x.QTDE)}`).join(' e ')})` : ''}${it.proj_antes !== null && it.proj_antes !== undefined ? ` e completa o estoque de segurança (projetado antes do dia: ${f2(it.proj_antes)})` : ''}.
                Abaixo está só a ordem da demanda ${esc(numIt)} (${f2(dem.QTDE)}).
                <a href="#" class="lnk-outras">ver as outras ${nOutras} ordem(ns) do dia (${f2(totOrd - acum)})</a></div>` : '';
            return sub + info + avisoDia + `<table class="det-tbl mb-2"><thead><tr><th></th><th>Ordem</th><th>Tipo</th><th>Dt. Compra</th><th>Dt. Entrega</th><th class="r">Qtde</th><th class="r">Estoque projetado</th><th>Situação</th></tr></thead><tbody>` +
                it.ordens.map(o => {
                    if (o.LIBERAVEL) algumaLiberavel = true;
                    const venc = isoDeBr(o.DT_COMPRA) && isoDeBr(o.DT_COMPRA) < hoje;
                    const dtPadrao = (o.DT_ENTREGA_ISO && o.DT_ENTREGA_ISO >= hoje) ? o.DT_ENTREGA_ISO : hoje;
                    const sit = String(o.STATUS_LIB) === '1' ? '<span class="text-success">Já liberada (com o comprador)</span>'
                        : o.TIPO !== 'OCP' ? '<span class="text-warning">Inter-fábrica: liberar pela FGAZIN_SUP004</span>'
                        : String(o.STATUS_LIB) === '0' ? '<span class="text-danger">Rejeitada pelo comprador — liberar de novo</span>'
                        : marcar.has(String(o.PERFIL_ID)) ? '<b>A liberar (esta demanda)</b>'
                        : '<span class="text-muted">Outras demandas do dia / estoque de segurança</span>';
                    const outraDoDia = o.LIBERAVEL && !marcar.has(String(o.PERFIL_ID));
                    return `<tr class="${outraDoDia ? 'ord-outra d-none' : ''}">
                        <td>${o.LIBERAVEL ? `<input type="checkbox" class="chk-lib" data-id="${esc(o.PERFIL_ID)}" ${marcar.has(String(o.PERFIL_ID)) ? 'checked' : ''}>` : ''}</td>
                        <td><b>${esc(o.ORDEM)}</b></td><td>${esc(o.TIPO)}</td>
                        <td>${venc ? `<span class="text-danger" title="Data de compra já passou (lead-time)">${esc(o.DT_COMPRA)}</span>` : esc(o.DT_COMPRA)}</td>
                        <td>${o.LIBERAVEL ? `<input type="date" class="form-control form-control-sm inp-dt" data-id="${esc(o.PERFIL_ID)}" min="${hoje}" value="${dtPadrao}" style="width:150px">` : esc(o.DT_ENTREGA)}</td>
                        <td class="r">${o.LIBERAVEL ? `<input type="number" min="0" step="any" class="form-control form-control-sm inp-q text-end" data-id="${esc(o.PERFIL_ID)}" value="${esc(num(o.QTDE_LOTE))}" style="width:110px;display:inline-block">` : f2(o.QTDE)}</td>
                        <td class="r">${f2(o.PROJ)}</td><td>${sit}</td></tr>`;
                }).join('') + '</tbody></table>';
        }).join('');

        const libs = (d.liberadas || []).length ? '<div class="fw-semibold mt-3">Ordens liberadas</div>' + tabela([
            { t: 'Ordem', k: 'ORDEM' }, { t: 'Tipo', k: 'TIPO' }, { t: 'Qtde planejada', k: 'QTDE_PLANEJADA', r: 1 },
            { t: 'Entrega planejada', k: 'DT_ENTR_PLANEJADA' }, { t: 'Situação', k: 'DESC_STATUS', f: x => String(x.STATUS) === '1' ? `<span class="text-success fw-semibold">${esc(x.DESC_STATUS)}</span>` : esc(x.DESC_STATUS) },
        ], d.liberadas) : '';

        corpo().innerHTML = cab + `<div class="alert alert-success py-2 small mb-2"><i class="bi bi-check-circle"></i> MRP do plano concluído. Ordens que o MRP gerou para repor a demanda:</div>` + blocos + libs +
            (algumaLiberavel ? '<div class="alert alert-primary py-2 small mt-2 mb-0"><b>Deseja liberar a(s) ordem(ns) marcada(s) no FGAZIN_SUP004?</b> Ela vai para o comprador (FGAZIN_SUP005) gerar o pedido de compra. A quantidade é ajustada ao lote do plano, como na FGAZIN_SUP004.</div>' : '');
        rod.innerHTML = '<button class="btn btn-sm btn-outline-secondary me-auto" id="btnConcluir">Concluir</button>' +
            (algumaLiberavel ? '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Agora não</button><button class="btn btn-sm btn-primary" id="btnLiberar"><i class="bi bi-send-check"></i> Sim, liberar</button>'
                             : '<button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>');
        document.getElementById('btnConcluir').onclick = concluir;
        corpo().querySelectorAll('.lnk-outras').forEach(a => a.addEventListener('click', ev => {
            ev.preventDefault();
            const linhas = [...corpo().querySelectorAll('tr.ord-outra')];
            const mostrar = linhas.some(tr => tr.classList.contains('d-none'));
            linhas.forEach(tr => tr.classList.toggle('d-none', !mostrar));
            a.textContent = mostrar ? 'esconder as outras ordens do dia' : a.dataset.txt || a.textContent;
        }));
        corpo().querySelectorAll('.lnk-outras').forEach(a => a.dataset.txt = a.textContent);
        const bl = document.getElementById('btnLiberar');
        if (bl) bl.onclick = liberar;
    }

    async function liberar() {
        const sel = [...corpo().querySelectorAll('.chk-lib:checked')].map(c => {
            const id = c.dataset.id;
            return { perfil_id: id, qtde: corpo().querySelector(`.inp-q[data-id="${id}"]`).value, dt_entrega: corpo().querySelector(`.inp-dt[data-id="${id}"]`).value };
        });
        if (!sel.length) { alert('Marque ao menos uma ordem.'); return; }
        if (sel.some(s => !(num(s.qtde) > 0) || !s.dt_entrega)) { alert('Informe quantidade e data de entrega das ordens marcadas.'); return; }
        if (!confirm(`Liberar ${sel.length} ordem(ns) para o comprador?\n\nÉ o mesmo que marcar e clicar em "Liberar" na FGAZIN_SUP004.`)) return;
        const bl = document.getElementById('btnLiberar'); bl.disabled = true; bl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Liberando...';
        try { await post(API.liberar, { ordens: sel }); await tick(); }
        catch (e) { alert('Erro: ' + e.message); bl.disabled = false; bl.innerHTML = '<i class="bi bi-send-check"></i> Sim, liberar'; }
    }

    async function concluir() {
        if (!confirm('Fechar o acompanhamento desta demanda? (Não altera nada no Focco.)')) return;
        try { await post(API.concluir, {}); _mdl.hide(); atualizarBanner(null); document.getElementById('btnLinha').disabled = false; }
        catch (e) { alert('Erro: ' + e.message); }
    }

    // ao abrir a tela: mostra se há demanda em acompanhamento
    post(API.mrpStatus, {}).then(d => { _ultimo = d; atualizarBanner(d); if (d.estado && d.estado.status === 'rodando') _timer = setTimeout(tick, 15000); }).catch(() => {});

    // ── Excel ──────────────────────────────────────────────────────
    document.getElementById('btnExcel').addEventListener('click', () => {
        const lista = linhasVisiveis(); if (!lista.length) return;
        const ex = COLS.filter(c => c.k !== 'QTDE_DEMANDA');
        const val = (r, c) => c.k === 'TIPO_PRODUTO' ? (r.TIPO_PRODUTO === 'N' ? 'INATIVO' : 'ATIVO')
            : (c.t === 'n' && !vazio(r[c.k]) ? String(num(r[c.k])).replace('.', ',') : r[c.k]);
        const cab = ['Marcado', ...ex.map(c => c.h), 'Observação', 'Qtde Demanda'];
        const csv = [cab.join(';'), ...lista.map(r => [isMarc(r) ? 'SIM' : '', ...ex.map(c => val(r, c)), r.OBS, estado(r).qtde]
            .map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';'))].join('\r\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        a.download = `compra-direta-crossdocking_${new Date().toISOString().slice(0, 10)}.csv`; a.click(); URL.revokeObjectURL(a.href);
    });
})();
</script>

<?= $render('footer') ?>
