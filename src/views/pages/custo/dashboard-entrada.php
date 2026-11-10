<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
/** @var array    $empresas */
/** @var ?array   $empresa */
$acessoCusto = $is_admin || in_array('custo', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoCusto) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
$mesPadrao  = date('Y-m', strtotime('first day of last month'));
$empPadrao  = (int) ($empresa['id'] ?? 1);
?>
<?= $render('header', [
    'pageTitle'  => 'Custo — Custo das Notas de Entrada',
    'showNavbar' => true,
    'pageActive' => 'custo-dashboard-entrada',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.ce-root {
    --surface-0: #f0f2f5; --surface-1: #ffffff; --head: #1565c0;
    --text-primary: #1f2937; --text-secondary: #52514e; --text-muted: #8a8f98;
    --sobe: #c62828; --desce: #2e7d32; --alerta: #e65100;
    padding: 12px; background: var(--surface-0); min-height: calc(100vh - 56px); box-sizing: border-box; color: var(--text-primary);
}
.ce-filtros { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; background:var(--surface-1); border-radius:10px;
              box-shadow:0 1px 6px rgba(0,0,0,.08); padding:10px 16px; margin-bottom:12px; }
.ce-filtros label { display:block; font-size:.72rem; font-weight:600; color:var(--text-secondary); margin-bottom:2px; }
.ce-filtros .form-select, .ce-filtros .form-control { font-size:.8rem; }
.ce-filtros .form-check-label { font-size:.78rem; color:var(--text-secondary); font-weight:500; }
.ce-status  { font-size:12px; color:var(--text-muted); margin-left:auto; align-self:center; }
.ce-nota    { font-size:11px; color:var(--text-muted); font-style:italic; flex-basis:100%; }

.ce-kpis    { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:12px; }
.ce-kpi     { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:10px 14px; border-left:4px solid var(--head); }
.ce-kpi .l  { font-size:10.5px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; font-weight:700; }
.ce-kpi .v  { font-size:20px; font-weight:700; line-height:1.2; margin-top:2px; font-variant-numeric:tabular-nums; }
.ce-kpi .s  { font-size:11px; color:var(--text-secondary); margin-top:2px; }

.ce-grid    { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:12px; }
@media (max-width: 1200px) { .ce-grid { grid-template-columns:1fr; } }
.ce-card    { background:var(--surface-1); border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; margin-bottom:12px; }
.ce-grid .ce-card { margin-bottom:0; }
.ce-head    { background:var(--head); color:#fff; padding:7px 14px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
              display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
.ce-head small { font-weight:400; text-transform:none; letter-spacing:0; opacity:.9; }
.ce-head a  { color:#fff; cursor:pointer; text-decoration:none; }
.ce-head label { color:#fff !important; opacity:1 !important; font-size:11px !important; }

.ce-tbl     { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
.ce-tbl th  { background:#e3f2fd !important; color:var(--head) !important; font-size:10.5px !important; font-weight:700 !important; padding:5px 8px !important;
              text-align:right; white-space:normal; line-height:1.2; vertical-align:bottom; border-bottom:2px solid #bbdefb !important;
              position:sticky; top:0; z-index:1; cursor:pointer; user-select:none; }
.ce-tbl th.l, .ce-tbl td.l { text-align:left; }
.ce-tbl th.ord-asc::after  { content:' ▲'; font-size:8px; }
.ce-tbl th.ord-desc::after { content:' ▼'; font-size:8px; }
.ce-tbl td  { padding:3px 8px !important; font-size:11.5px !important; text-align:right; white-space:nowrap; border-bottom:1px solid #f0f0f0 !important;
              font-variant-numeric:tabular-nums; }
.ce-tbl td.desc { max-width:320px; overflow:hidden; text-overflow:ellipsis; }
.ce-tbl tbody tr.clic { cursor:pointer; }
.ce-tbl tbody tr:hover td { background:#f5f9ff !important; }
.ce-tbl tr.tot td { font-weight:700; background:#f5f8fe !important; border-top:2px solid #bbdefb !important; position:sticky; bottom:0; }
.ce-tbl .sobe  { color:var(--sobe); font-weight:600; }
.ce-tbl .desce { color:var(--desce); font-weight:600; }
.ce-tbl .falta { color:#fff; background:var(--alerta); border-radius:4px; padding:1px 6px; font-size:10.5px; font-weight:700; }
.ce-tbl .mut   { color:var(--text-muted); }
.ce-tbl tr.puxa-cima td  { background:#fdecea !important; }
.ce-tbl tr.puxa-baixo td { background:#e8f5e9 !important; }
.ce-tbl td.motivo { white-space:normal; min-width:380px; max-width:560px; text-align:left; font-size:11px !important; }
.ce-resumo  { padding:8px 14px; font-size:12px; color:var(--text-secondary); border-bottom:1px solid #eef0f3; }
.ce-resumo ol { margin:4px 0 0 18px; padding:0; }
.ce-resumo li { margin:2px 0; }
.ce-scroll  { overflow:auto; max-height:480px; }
.ce-vazio   { text-align:center; color:var(--text-muted); padding:16px; font-size:12px; }
</style>

<div class="ce-root">
    <div class="ce-filtros">
        <div>
            <label for="fEmp">Empresa</label>
            <select id="fEmp" class="form-select form-select-sm" style="max-width:260px">
                <?php foreach ($empresas as $e): ?>
                    <option value="<?= (int) $e['id'] ?>" <?= (int) $e['id'] === $empPadrao ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['codigo'] . ' - ' . $e['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="fIni">Ano/Mês inicial</label>
            <input type="month" id="fIni" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
        </div>
        <div>
            <label for="fFim">Ano/Mês final</label>
            <input type="month" id="fFim" class="form-control form-control-sm" value="<?= $mesPadrao ?>">
        </div>
        <div>
            <label for="fVar">Variação a partir de (%)</label>
            <input type="number" id="fVar" class="form-control form-control-sm" value="10" min="0" step="1" style="max-width:110px">
        </div>
        <div>
            <label for="fBusca">Item ou fornecedor</label>
            <input type="text" id="fBusca" class="form-control form-control-sm" placeholder="Filtrar..." style="max-width:200px">
        </div>
        <div class="form-check" style="margin-bottom:4px">
            <input class="form-check-input" type="checkbox" id="fAtuCm" checked>
            <label class="form-check-label" for="fAtuCm">Só notas que atualizam o custo médio</label>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnCsv"><i class="bi bi-download"></i> CSV</button>
        </div>
        <span class="ce-status" id="status"></span>
        <div class="ce-nota">
            Notas de compra (tipo de nota de compra), itens com quantidade. Valores por unidade de estoque (quantidade convertida da nota).
            Custo c/ frete = custo unitário da nota (sem impostos recuperáveis)
            + frete das notas da transportadora ligadas ao item + outros custos ligados (ex.: despesas de importação), por unidade.
            Variação = custo c/ frete contra o custo médio do mês anterior à entrada (mesma máscara).
            Falta nota de frete = frete FOB (por nossa conta) sem nota de transportadora ligada ao item e sem frete na própria nota.
        </div>
    </div>

    <div class="ce-kpis" id="kpis"></div>

    <div class="ce-card">
        <div class="ce-head"><span>Itens com variação de custo</span><small id="rotItens"></small></div>
        <div class="ce-scroll"><table class="ce-tbl" id="tblItens"></table></div>
    </div>

    <div class="ce-grid">
        <div class="ce-card">
            <div class="ce-head"><span>Notas FOB sem nota de frete</span><small id="rotSemFrete"></small></div>
            <div class="ce-scroll"><table class="ce-tbl" id="tblSemFrete"></table></div>
        </div>
        <div class="ce-card">
            <div class="ce-head"><span>Por fornecedor</span><small>clique para ver as notas</small></div>
            <div class="ce-scroll"><table class="ce-tbl" id="tblForn"></table></div>
        </div>
    </div>

    <div class="ce-card" id="cardDet" style="display:none">
        <div class="ce-head"><span id="detTitulo">Detalhe</span><small><a id="detCsv">CSV</a> &nbsp;·&nbsp; <a id="detFechar">fechar ✕</a></small></div>
        <div class="ce-scroll" style="max-height:560px"><table class="ce-tbl" id="tblDet"></table></div>
    </div>

    <div class="ce-card" id="cardRaz" style="display:none">
        <div class="ce-head"><span id="razTitulo">Por que o custo médio mudou</span>
            <small><label style="cursor:pointer;color:#fff;margin:0;text-transform:none;font-weight:400;letter-spacing:0"><input type="checkbox" id="razTodas" style="vertical-align:middle"> mostrar todas as movimentações</label>
            &nbsp;·&nbsp; <a id="razFechar">fechar ✕</a></small></div>
        <div class="ce-resumo" id="razResumo"></div>
        <div class="ce-scroll" style="max-height:600px"><table class="ce-tbl" id="tblRaz"></table></div>
    </div>
</div>

<script>
(function () {
    const BASE = <?= json_encode($base) ?>;
    const API  = BASE + 'custo-api-entrada-listar';
    const API_RAZAO = BASE + 'custo-api-entrada-razao';
    const FRETE = { C: 'CIF', F: 'FOB' };

    let dados = null, linhas = [], detLinhas = [];
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fmt = (v, d) => v == null ? '-' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
    const rs  = v => v == null ? '-' : 'R$ ' + fmt(v, 2);
    const n0  = v => fmt(v, 0);
    const un  = v => v == null ? '-' : fmt(v, Math.abs(v) < 10 ? 4 : 2);
    const pct = v => v == null ? '-' : (v > 0 ? '+' : '') + fmt(v, 1) + '%';
    const clsVar = v => v == null ? 'mut' : v > 0 ? 'sobe' : v < 0 ? 'desce' : '';
    const dBr = d => d.split('-').reverse().join('/');
    const limite = () => Math.max(0, parseFloat(document.getElementById('fVar').value) || 0);
    const freteRot = t => FRETE[t] ? t + ' (' + FRETE[t] + ')' : (t || '-');

    async function post(url, body) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
        let data;
        try { data = await res.json(); } catch (_) { throw new Error('Resposta inválida do servidor (HTTP ' + res.status + ').'); }
        if (data.error) throw new Error(data.error);
        return data;
    }

    async function filtrar() {
        const empresa = parseInt(document.getElementById('fEmp').value, 10);
        const ini = document.getElementById('fIni').value, fim = document.getElementById('fFim').value;
        if (!empresa || !ini || !fim) return;
        const st = document.getElementById('status');
        st.textContent = 'Carregando...';
        document.getElementById('btnFiltrar').disabled = true;
        const t0 = Date.now();
        try {
            // Um mês por requisição (o servidor guarda cada mês em cache) para nenhuma passar do tempo limite (504).
            const meses = [];
            for (let [a, m] = ini.split('-').map(Number); a * 12 + m <= Number(fim.slice(0, 4)) * 12 + Number(fim.slice(5, 7)) && meses.length <= 6; m++) {
                if (m > 12) { m = 1; a++; }
                meses.push(a + '-' + String(m).padStart(2, '0'));
            }
            if (meses.length > 1 && meses.length <= 6) {
                for (let i = 0; i < meses.length; i++) {
                    st.textContent = 'Carregando ' + meses[i].slice(5) + '/' + meses[i].slice(0, 4) + ' (' + (i + 1) + ' de ' + meses.length + ')...';
                    await post(API, { empresa, ini: meses[i], fim: meses[i] });
                }
                st.textContent = 'Montando o período...';
            }
            dados = await post(API, { empresa, ini, fim });
            document.getElementById('cardDet').style.display = 'none';
            desenhar();
            st.textContent = 'Atualizado em ' + ((Date.now() - t0) / 1000).toFixed(1) + 's';
        } catch (e) {
            st.textContent = 'Erro: ' + e.message;
        } finally {
            document.getElementById('btnFiltrar').disabled = false;
        }
    }

    // linhas após os filtros da tela (tipo de nota e busca); o limite de variação só marca/seleciona
    function filtradas() {
        const soCm = document.getElementById('fAtuCm').checked;
        const b = document.getElementById('fBusca').value.trim().toLowerCase();
        return dados.linhas.filter(l => (!soCm || l.atu_cm) &&
            (!b || (l.item + ' ' + l.desc + ' ' + l.fornecedor + ' ' + l.nf + ' ' + l.grupo).toLowerCase().includes(b)));
    }
    const acima = l => l.var_pct != null && Math.abs(l.var_pct) >= limite();

    function desenhar() {
        linhas = filtradas();
        const lim = limite();
        const notas = new Set(linhas.map(l => l.nfe_id));
        const valor = linhas.reduce((a, l) => a + l.vlr_total, 0);
        const frete = linhas.reduce((a, l) => a + l.frete, 0);
        const comVar = linhas.filter(acima);
        const sobe = comVar.filter(l => l.var_pct > 0).length;
        const impacto = comVar.reduce((a, l) => a + l.impacto, 0);
        const semFrete = linhas.filter(l => l.falta_frete);
        const notasSem = new Set(semFrete.map(l => l.nfe_id));
        const valorSem = semFrete.reduce((a, l) => a + l.vlr_total, 0);
        const semCm = linhas.filter(l => l.cm_ant == null).length;

        const k = [
            ['Valor comprado', rs(valor), n0(notas.size) + ' notas · ' + n0(linhas.length) + ' itens', '#1565c0'],
            ['Frete ligado (notas da transportadora)', rs(frete), valor ? fmt(frete / valor * 100, 2) + '% do valor comprado' : '', '#1565c0'],
            ['Itens com variação ≥ ' + fmt(lim, 0) + '%', n0(comVar.length), n0(sobe) + ' subiram · ' + n0(comVar.length - sobe) + ' caíram', '#c62828'],
            ['Impacto desses itens no custo', rs(impacto), 'custo c/ frete − custo médio do mês anterior, × qtde', impacto > 0 ? '#c62828' : '#2e7d32'],
            ['Notas FOB sem nota de frete', n0(notasSem.size), rs(valorSem) + ' em ' + n0(semFrete.length) + ' itens', '#e65100'],
            ['Itens sem custo médio anterior', n0(semCm), 'item sem estoque/custo no mês anterior: não entra na variação', '#8a8f98'],
        ];
        document.getElementById('kpis').innerHTML = k.map(([l, v, s, c]) =>
            '<div class="ce-kpi" style="border-left-color:' + c + '"><div class="l">' + esc(l) + '</div><div class="v">' + v + '</div><div class="s">' + esc(s) + '</div></div>').join('');

        tabelaItens(lim);
        tabelaSemFrete(semFrete);
        tabelaForn();
    }

    // ── tabela genérica com ordenação por coluna ──
    // cols: [rótulo, valor(linha), formato(valor, linha) => html, classe 'l' para texto, chave de ordenação opcional]
    function tabela(id, cols, rows, ordemInicial, aoClicar, total, clsLinha) {
        const t = document.getElementById(id);
        let ord = ordemInicial;
        const montar = () => {
            const [ci, dir] = ord;
            const chave = cols[ci][4] || cols[ci][1];
            const r = rows.slice().sort((a, b) => {
                const va = chave(a), vb = chave(b);
                if (va == null && vb == null) return 0; if (va == null) return 1; if (vb == null) return -1;
                return (typeof va === 'string' ? va.localeCompare(vb, 'pt-BR') : va - vb) * dir;
            });
            let h = '<thead><tr>' + cols.map((c, i) => '<th data-i="' + i + '" class="' + (c[3] || '') + (i === ci ? (dir > 0 ? ' ord-asc' : ' ord-desc') : '') + '">' + c[0] + '</th>').join('') + '</tr></thead><tbody>';
            if (!r.length) h += '<tr><td class="ce-vazio" colspan="' + cols.length + '">Nada encontrado com esses filtros</td></tr>';
            r.forEach((x, j) => {
                h += '<tr class="' + (aoClicar ? 'clic ' : '') + (clsLinha ? clsLinha(x) : '') + '" data-j="' + j + '">' +
                     cols.map(c => '<td class="' + (c[3] || '') + '">' + (c[2] ? c[2](c[1](x), x) : esc(c[1](x))) + '</td>').join('') + '</tr>';
            });
            if (total && r.length) h += '<tr class="tot">' + total(r) + '</tr>';
            t.innerHTML = h + '</tbody>';
            t.querySelectorAll('th[data-i]').forEach(th => th.addEventListener('click', () => {
                const i = parseInt(th.dataset.i, 10);
                ord = [i, ord[0] === i ? -ord[1] : (String(cols[i][3] || '').split(' ').includes('l') ? 1 : -1)];
                montar();
            }));
            if (aoClicar) t.querySelectorAll('tbody tr[data-j]').forEach(tr => tr.addEventListener('click', () => aoClicar(r[parseInt(tr.dataset.j, 10)])));
        };
        montar();
    }

    function tabelaItens(lim) {
        const porItem = {};
        linhas.forEach(l => {
            const k = l.item + '|' + l.mascara;
            const g = porItem[k] ??= { item: l.item, desc: l.desc, mascara: l.mascara, grupo: l.grupo, notas: new Set(), qtde: 0, valor: 0,
                                       cf: 0, qCm: 0, cfCm: 0, cmQ: 0, impacto: 0, semFrete: 0, linhas: [] };
            g.notas.add(l.nfe_id); g.qtde += l.qtde; g.valor += l.vlr_total; g.cf += l.custo_frete * l.qtde;
            if (l.cm_ant != null) { g.qCm += l.qtde; g.cfCm += l.custo_frete * l.qtde; g.cmQ += l.cm_ant * l.qtde; g.impacto += l.impacto; }
            if (l.falta_frete) g.semFrete++;
            g.linhas.push(l);
        });
        const itens = Object.values(porItem).map(g => ({ ...g, nNotas: g.notas.size, custoMed: g.qtde ? g.cf / g.qtde : null,
            cmAnt: g.qCm ? g.cmQ / g.qCm : null, varPct: g.cmQ ? (g.cfCm / g.cmQ - 1) * 100 : null }))
            .filter(g => g.varPct != null && Math.abs(g.varPct) >= lim);
        document.getElementById('rotItens').textContent = n0(itens.length) + ' itens com variação ≥ ' + fmt(lim, 0) + '% · clique para ver as notas';
        tabela('tblItens', [
            ['Grupo', g => g.grupo, null, 'l'],
            ['Item', g => g.item, null, 'l'],
            ['Descrição', g => g.desc, (v) => '<span title="' + esc(v) + '">' + esc(v) + '</span>', 'l desc'],
            ['Máscara', g => g.mascara, null, 'l'],
            ['Notas', g => g.nNotas, n0],
            ['Qtde (estoque)', g => g.qtde, v => fmt(v, 2)],
            ['Valor comprado', g => g.valor, rs],
            ['Custo c/ frete (médio)', g => g.custoMed, un],
            ['Custo médio mês anterior', g => g.cmAnt, un],
            ['Variação', g => g.varPct, v => '<span class="' + clsVar(v) + '">' + pct(v) + '</span>', '', g => Math.abs(g.varPct)],
            ['Impacto no custo', g => g.impacto, v => '<span class="' + clsVar(v) + '">' + rs(v) + '</span>', '', g => Math.abs(g.impacto)],
            ['Falta nota de frete', g => g.semFrete, v => v ? '<span class="falta">' + v + '</span>' : ''],
        ], itens, [10, -1], g => { detalhe('Item ' + g.item + (g.mascara ? ' / ' + g.mascara : '') + ' — ' + g.desc, g.linhas); razao(g.linhas[0]); },
        r => '<td class="l" colspan="6">Total</td><td>' + rs(r.reduce((a, g) => a + g.valor, 0)) + '</td><td></td><td></td><td></td><td>' +
             rs(r.reduce((a, g) => a + g.impacto, 0)) + '</td><td>' + n0(r.reduce((a, g) => a + g.semFrete, 0)) + '</td>');
    }

    function tabelaSemFrete(sem) {
        const porNota = {};
        sem.forEach(l => {
            const n = porNota[l.nfe_id] ??= { nfe_id: l.nfe_id, nf: l.nf, serie: l.serie, dt: l.dt, fornecedor: l.fornecedor, tipo: l.tipo, itens: 0, valor: 0 };
            n.itens++; n.valor += l.vlr_total;
        });
        const notas = Object.values(porNota);
        document.getElementById('rotSemFrete').textContent = n0(notas.length) + ' notas · clique para ver os itens';
        tabela('tblSemFrete', [
            ['Data', n => n.dt, dBr, 'l'],
            ['NF', n => n.nf, (v, n) => esc(v) + (n.serie ? '<span class="mut"> / ' + esc(n.serie) + '</span>' : ''), 'l'],
            ['Fornecedor', n => n.fornecedor, null, 'l desc'],
            ['Tipo de nota', n => n.tipo, (v) => '<span title="' + esc(v) + '">' + esc(String(v).slice(0, 40)) + '</span>', 'l'],
            ['Itens', n => n.itens, n0],
            ['Valor', n => n.valor, rs],
        ], notas, [5, -1], n => detalhe('NF ' + n.nf + ' — ' + n.fornecedor, linhas.filter(l => l.nfe_id === n.nfe_id)),
        r => '<td class="l" colspan="4">Total</td><td>' + n0(r.reduce((a, n) => a + n.itens, 0)) + '</td><td>' + rs(r.reduce((a, n) => a + n.valor, 0)) + '</td>');
    }

    function tabelaForn() {
        const porF = {};
        linhas.forEach(l => {
            const f = porF[l.forn_id] ??= { forn_id: l.forn_id, nome: l.fornecedor, notas: new Set(), valor: 0, frete: 0, comVar: 0, impacto: 0, sem: new Set() };
            f.notas.add(l.nfe_id); f.valor += l.vlr_total; f.frete += l.frete;
            if (acima(l)) { f.comVar++; f.impacto += l.impacto; }
            if (l.falta_frete) f.sem.add(l.nfe_id);
        });
        const forn = Object.values(porF).map(f => ({ ...f, nNotas: f.notas.size, nSem: f.sem.size, fretePct: f.valor ? f.frete / f.valor * 100 : null }));
        tabela('tblForn', [
            ['Fornecedor', f => f.nome, null, 'l desc'],
            ['Notas', f => f.nNotas, n0],
            ['Valor comprado', f => f.valor, rs],
            ['Frete ligado', f => f.frete, rs],
            ['Frete %', f => f.fretePct, v => v == null ? '-' : fmt(v, 2) + '%'],
            ['Itens c/ variação', f => f.comVar, n0],
            ['Impacto', f => f.impacto, v => '<span class="' + clsVar(v) + '">' + rs(v) + '</span>', '', f => Math.abs(f.impacto)],
            ['Notas sem frete', f => f.nSem, v => v ? '<span class="falta">' + v + '</span>' : ''],
        ], forn, [2, -1], f => detalhe('Fornecedor — ' + f.nome, linhas.filter(l => l.forn_id === f.forn_id)));
    }

    function detalhe(titulo, ls) {
        detLinhas = ls;
        document.getElementById('detTitulo').textContent = titulo + ' (' + ls.length + ' itens de nota)';
        tabela('tblDet', [
            ['Data', l => l.dt, dBr, 'l'],
            ['NF', l => l.nf, null, 'l'],
            ['Fornecedor', l => l.fornecedor, null, 'l desc'],
            ['Item', l => l.item + (l.mascara ? ' / ' + l.mascara : ''), (v, l) => '<span title="' + esc(l.desc) + '">' + esc(v) + '</span>', 'l'],
            ['Descrição', l => l.desc, null, 'l desc'],
            ['Frete', l => l.tp_frete, freteRot, 'l'],
            ['Qtde na nota', l => l.qtde_nf, v => fmt(v, 2)],
            ['Preço unit. nota', l => l.preco, un],
            ['Qtde estoque', l => l.qtde, v => fmt(v, 2)],
            ['Custo unit. estoque', l => l.custo, un],
            ['Frete unit.', l => l.qtde ? l.frete / l.qtde : null, v => v ? un(v) : '-'],
            ['Outros unit.', l => l.qtde ? l.outros / l.qtde : null, v => v ? un(v) : '-'],
            ['Custo c/ frete', l => l.custo_frete, v => '<b>' + un(v) + '</b>'],
            ['Custo médio mês anterior', l => l.cm_ant, un],
            ['Custo médio mês da entrada', l => l.cm_mes, un],
            ['Variação', l => l.var_pct, v => '<span class="' + clsVar(v) + '">' + pct(v) + '</span>', '', l => l.var_pct == null ? null : Math.abs(l.var_pct)],
            ['Impacto', l => l.impacto, v => '<span class="' + clsVar(v) + '">' + rs(v) + '</span>', '', l => l.impacto == null ? null : Math.abs(l.impacto)],
            ['Nota de frete', l => l.falta_frete ? '' : l.notas_frete.join(', '), (v, l) =>
                l.falta_frete ? '<span class="falta">FALTA NOTA DE FRETE</span>'
                : l.notas_frete.length ? '<span title="' + esc(l.transp) + '">' + esc(v) + '</span>'
                : l.frete_nf ? '<span class="mut">frete na própria nota</span>' : '<span class="mut">-</span>', 'l'],
        ], ls, [0, 1], l => razao(l));
        document.getElementById('detTitulo').textContent += ' · clique numa linha para ver por que o custo médio mudou';
        const c = document.getElementById('cardDet');
        c.style.display = '';
        c.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── razão de custo do item: o que mexeu no custo médio e por quê ──
    let raz = null;
    async function razao(l) {
        const card = document.getElementById('cardRaz');
        card.style.display = '';
        document.getElementById('razTitulo').textContent = 'Por que o custo médio mudou — item ' + l.item + (l.mascara ? ' / ' + l.mascara : '') + ' — ' + l.desc;
        document.getElementById('razResumo').textContent = 'Carregando movimentações...';
        document.getElementById('tblRaz').innerHTML = '';
        try {
            raz = await post(API_RAZAO, { empresa: dados.empresa, itestq_id: l.itestq_id, masc_id: l.masc_id, ini: dados.meses[0], fim: dados.meses[dados.meses.length - 1] });
            raz.movs.forEach((m, i) => m.i = i);
            desenharRazao();
        } catch (e) {
            document.getElementById('razResumo').textContent = 'Erro: ' + e.message;
        }
    }

    function desenharRazao() {
        const lim = limite();
        const todas = document.getElementById('razTodas').checked;
        const mexeu = m => m.efeito != null && Math.abs(m.efeito) > 0.00005;
        const ms = raz.movs.filter(m => todas || mexeu(m));
        const top = raz.movs.filter(mexeu).slice().sort((a, b) => Math.abs(b.efeito_pct || 0) - Math.abs(a.efeito_pct || 0)).slice(0, 3);
        const ini = raz.movs.find(m => m.cm_antes != null), fim = raz.movs[raz.movs.length - 1];
        let h = 'De ' + dBr(raz.de) + ' a ' + dBr(raz.ate) + ' (inclui o mês anterior ao período): ' + n0(raz.movs.length) + ' movimentações, ' +
                n0(raz.movs.filter(mexeu).length) + ' mudaram o custo médio';
        if (ini && fim) h += ' · custo médio de <b>' + un(ini.cm_antes) + '</b> para <b>' + un(fim.cm) + '</b>';
        if (top.length) h += '<ol>' + top.map(m => '<li><b class="' + clsVar(m.efeito) + '">' + pct(m.efeito_pct) + '</b> em ' + dBr(m.dt) + ' — ' + esc(m.motivo || m.tipo) + '</li>').join('') + '</ol>';
        if (!raz.movs.length) h = 'Nenhuma movimentação de custo deste item no período.';
        document.getElementById('razResumo').innerHTML = h;
        const t = tabela('tblRaz', [
            ['Data', m => m.dt, dBr, 'l', m => m.i],
            ['Movimento', m => m.es + ' ' + m.sigla, (v, m) => '<span title="' + esc(m.tipo) + '">' + esc(v) + '</span>', 'l', m => m.i],
            ['Custo médio antes', m => m.cm_antes, un, '', m => m.i],
            ['Custo médio depois', m => m.cm, v => '<b>' + un(v) + '</b>', '', m => m.i],
            ['Efeito no médio', m => m.efeito_pct, (v, m) => mexeu(m) ? '<span class="' + clsVar(m.efeito) + '">' + pct(v) + '</span>' : '', '', m => m.i],
            ['Motivo', m => m.motivo, null, 'l motivo', m => m.i],
            ['Documento', m => m.doc, (v, m) => '<span title="' + esc(m.tipo_nf || m.usuario) + '">' + esc(v) + '</span>', 'l desc', m => m.i],
            ['Qtde', m => m.qtde, v => fmt(v, 3), '', m => m.i],
            ['Valor', m => m.valor, rs, '', m => m.i],
            ['Unit.', m => m.unit, un, '', m => m.i],
            ['Saldo antes', m => m.saldo_antes, v => v == null ? '-' : fmt(v, 3), '', m => m.i],
        ], ms, [0, 1], null, null,
        m => (m.efeito_pct != null && Math.abs(m.efeito_pct) >= lim && mexeu(m)) ? (m.efeito > 0 ? 'puxa-cima' : 'puxa-baixo') : '');
    }

    function baixarCsv(ls, nome) {
        const q = v => { v = String(v ?? ''); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const n = (v, d) => v == null ? '' : Number(v).toFixed(d).replace('.', ',');
        const cab = ['Data', 'NF', 'Série', 'Fornecedor', 'Tipo de nota', 'Atualiza custo médio', 'Tipo frete', 'Grupo', 'Item', 'Máscara', 'Descrição',
                     'Qtde na nota', 'Preço unit. nota', 'Qtde estoque', 'Custo unit. estoque', 'Valor total', 'Frete ligado', 'Outros custos ligados', 'Frete na nota', 'Custo c/ frete',
                     'Custo médio mês anterior', 'Custo médio mês entrada', 'Variação %', 'Impacto', 'Notas de frete', 'Transportadora', 'Falta nota de frete'];
        const lin = [cab.join(';')].concat(ls.map(l => [dBr(l.dt), l.nf, l.serie, q(l.fornecedor), q(l.tipo), l.atu_cm ? 'Sim' : 'Não', l.tp_frete, q(l.grupo),
            l.item, q(l.mascara), q(l.desc), n(l.qtde_nf, 3), n(l.preco, 6), n(l.qtde, 3), n(l.custo, 6), n(l.vlr_total, 2), n(l.frete, 2), n(l.outros, 2), n(l.frete_nf, 2),
            n(l.custo_frete, 6), n(l.cm_ant, 6), n(l.cm_mes, 6), n(l.var_pct, 2), n(l.impacto, 2), q(l.notas_frete.join(', ')), q(l.transp),
            l.falta_frete ? 'Sim' : 'Não'].join(';')));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + lin.join('\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = nome;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    let tBusca = null;
    document.getElementById('btnFiltrar').addEventListener('click', filtrar);
    document.getElementById('btnCsv').addEventListener('click', () => { if (dados) baixarCsv(linhas, 'custo_notas_entrada_' + dados.meses[0] + '_' + dados.meses[dados.meses.length - 1] + '.csv'); });
    document.getElementById('detCsv').addEventListener('click', () => baixarCsv(detLinhas, 'custo_notas_entrada_detalhe.csv'));
    document.getElementById('detFechar').addEventListener('click', () => document.getElementById('cardDet').style.display = 'none');
    ['fVar', 'fAtuCm'].forEach(id => document.getElementById(id).addEventListener('change', () => { if (dados) desenhar(); if (raz) desenharRazao(); }));
    document.getElementById('razTodas').addEventListener('change', () => { if (raz) desenharRazao(); });
    document.getElementById('razFechar').addEventListener('click', () => document.getElementById('cardRaz').style.display = 'none');
    document.getElementById('fBusca').addEventListener('input', () => { clearTimeout(tBusca); tBusca = setTimeout(() => { if (dados) desenhar(); }, 250); });
    filtrar();
})();
</script>
<?= $render('footer') ?>
