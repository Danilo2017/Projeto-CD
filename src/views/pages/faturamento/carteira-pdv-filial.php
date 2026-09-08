<?php
/** @var bool $is_admin @var array $rotas_permitidas @var string $base @var callable $render */
$acesso = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Carteira PDV — Filial',
    'showNavbar' => true,
    'pageActive' => 'faturamento-carteira-pdv-filial',
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
/* ── página ───────────────────────────────────────────────────────── */
.cpvf-page  { padding:12px; background:#f0f2f5; min-height:calc(100vh - 56px); }

/* ── barra de filtros ─────────────────────────────────────────────── */
.cpvf-filter {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:#fff; border-radius:8px;
    padding:10px 14px; box-shadow:0 1px 4px rgba(0,0,0,.08);
    margin-bottom:10px;
}
.cpvf-filter label { font-size:12px; font-weight:600; color:#555; margin:0; }
.cpvf-filter input  { font-size:12px; border:1px solid #ccc; border-radius:6px; padding:5px 8px; }
.cpvf-filter .btn   { font-size:12px; }

/* ── card da tabela ───────────────────────────────────────────────── */
.cpvf-card  { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.cpvf-wrap  { overflow-x:auto; }

/* ── tabela ───────────────────────────────────────────────────────── */
.cpvf-tbl {
    table-layout:fixed; border-collapse:collapse;
    font-size:12px; white-space:nowrap;
}
.cpvf-tbl th,
.cpvf-tbl td { padding:5px 9px; border-bottom:1px solid #e8ecf3; border-right:1px solid #e8ecf3; vertical-align:middle; }

/* ── coluna sticky ────────────────────────────────────────────────── */
.cpvf-tbl th.col-desc,
.cpvf-tbl td.col-desc {
    position:sticky; left:0; z-index:2;
    background:inherit; border-right:2px solid #c7d2e8;
}
.cpvf-tbl thead th.col-desc { z-index:3; }

/* ── cabeçalhos das filiais ───────────────────────────────────────── */
.row-emp-header th {
    background:#1e3a8a; color:#fff;
    text-align:center; font-weight:600; font-size:12px;
    border-color:#1b357d; padding:7px 9px;
}
.row-emp-header th.col-desc { background:#1e3a8a; }

.row-col-header th {
    background:#2d4a9a; color:#c8d4f0;
    text-align:right; font-size:10px; font-weight:500;
    border-color:#2840a8; padding:4px 8px;
}
.row-col-header th.col-desc { text-align:left; background:#2d4a9a; }

/* ── linhas de dados ──────────────────────────────────────────────── */
.row-l1 { background:#eef1f8; cursor:pointer; }
.row-l1 td { color:#1e3a8a; font-weight:600; border-top:2px solid #c7d2e8; }
.row-l1:hover { background:#dde3f3; }

.row-l2 { background:#f7f9fd; cursor:pointer; }
.row-l2 td { color:#1565c0; }
.row-l2 td.col-desc { padding-left:26px; }
.row-l2:hover { background:#eaedfa; }

.row-l3 { background:#fff; }
.row-l3 td { color:#555; font-size:11px; }
.row-l3 td.col-desc { padding-left:48px; }

/* ── rodapé total ─────────────────────────────────────────────────── */
tfoot .row-total td {
    background:#1e3a8a; color:#fff;
    font-weight:700; font-size:12px;
    border-color:#1b357d; text-align:right; padding:6px 9px;
}
tfoot .row-total td.col-desc { text-align:left; }

/* ── células numéricas ────────────────────────────────────────────── */
.nm      { text-align:right; }
.nm-dash { text-align:right; color:#ccc; }

/* ── ícone expand ─────────────────────────────────────────────────── */
.ico-exp { display:inline-block; transition:transform .18s; font-size:9px; margin-right:4px; color:#7b8fb8; }
.row-l1.expanded .ico-exp,
.row-l2.expanded .ico-exp { transform:rotate(90deg); }
</style>

<div class="cpvf-page">

    <div class="d-flex align-items-center mb-2 gap-2">
        <span class="fw-semibold" style="font-size:15px">Carteira PDV</span>
        <span class="text-muted small">— Visão por Filial</span>
        <button class="btn btn-sm btn-success ms-auto" id="btn-csv" hidden onclick="exportarCSV()">
            <i class="bi bi-download"></i> Exportar CSV
        </button>
    </div>

    <!-- filtros -->
    <div class="cpvf-filter">
        <label>Data Inicial</label>
        <input type="date" id="cpvf-di">
        <label>Data Final</label>
        <input type="date" id="cpvf-df">
        <button class="btn btn-sm btn-primary" onclick="cpvfLoad()">
            <i class="bi bi-search"></i> Buscar
        </button>
        <button class="btn btn-sm btn-outline-secondary" id="btn-toggle-all" hidden onclick="toggleAll()" data-state="collapsed">
            <i class="bi bi-arrows-expand"></i> Expandir Tudo
        </button>
    </div>

    <!-- loading -->
    <div id="cpvf-loading" hidden class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted small">Carregando dados...</p>
    </div>

    <!-- card da tabela -->
    <div class="cpvf-card" id="cpvf-card" hidden>
        <div class="cpvf-wrap">
            <table class="cpvf-tbl" id="cpvf-tbl">
                <colgroup id="cpvf-cg"></colgroup>
                <thead id="cpvf-head"></thead>
                <tbody id="cpvf-body"></tbody>
                <tfoot id="cpvf-foot"></tfoot>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    var BASE  = '<?= $base ?>';
    var _di   = '', _df = '';
    var _emps = [];
    var _cache = { l2: {}, l3: {} };

    /* ── formatação ──────────────────────────────────────────────── */
    var numFmt = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function fmt(v)    { return (v == null || v === '') ? '-' : numFmt.format(Number(v)); }
    function fmtPct(v) { return (v == null || v === '') ? '-' : numFmt.format(Number(v)) + '%'; }
    function fmtTxa(v) { return (v == null || v === '') ? '-' : Number(v).toFixed(4); }
    function esc(s)    {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── pivot ───────────────────────────────────────────────────── */
    function pivot(data, keyFn) {
        var map = new Map();
        for (var i = 0; i < data.length; i++) {
            var r = data[i];
            var k = keyFn(r);
            if (!map.has(k)) map.set(k, { ref: r, byEmpr: {} });
            map.get(k).byEmpr[r.EMPR_ID] = r;
        }
        return map;
    }

    /* ── células de métricas ─────────────────────────────────────── */
    function mCells(byEmpr, emprId) {
        var r = byEmpr[emprId];
        if (!r) return '<td class="nm-dash">-</td><td class="nm-dash">-</td><td class="nm-dash">-</td><td class="nm-dash">-</td><td class="nm-dash">-</td>';
        return '<td class="nm">' + fmt(r.VLR_PENDENTE)         + '</td>' +
               '<td class="nm">' + fmtPct(r.PERC_PARTICIPACAO) + '</td>' +
               '<td class="nm">' + fmt(r.MC_VALOR)             + '</td>' +
               '<td class="nm">' + fmtPct(r.MC_PERC)           + '</td>' +
               '<td class="nm">' + fmtTxa(r.TAXA)              + '</td>';
    }
    function allMCells(byEmpr) {
        var h = '';
        for (var i = 0; i < _emps.length; i++) h += mCells(byEmpr, _emps[i]);
        return h;
    }

    /* ── remover descendentes ────────────────────────────────────── */
    function removeDesc(path) {
        var pfx = path + '_';
        document.querySelectorAll('#cpvf-body tr[data-path]').forEach(function (tr) {
            if (tr.dataset.path.startsWith(pfx)) tr.remove();
        });
    }

    /* ── cabeçalho dinâmico ──────────────────────────────────────── */
    function buildHeader() {
        var cols  = [130, 70, 130, 70, 90];
        var total = 280;
        var cg    = '<col style="width:280px">';
        for (var i = 0; i < _emps.length; i++) {
            for (var j = 0; j < cols.length; j++) { cg += '<col style="width:' + cols[j] + 'px">'; total += cols[j]; }
        }
        document.getElementById('cpvf-cg').innerHTML  = cg;
        document.getElementById('cpvf-tbl').style.width = total + 'px';

        var r1 = '<tr class="row-emp-header"><th class="col-desc" rowspan="2">Tanque / Sub Grupo / Item</th>';
        for (var i = 0; i < _emps.length; i++) r1 += '<th colspan="5">Filial ' + esc(_emps[i]) + '</th>';
        r1 += '</tr>';

        var r2 = '<tr class="row-col-header">';
        for (var i = 0; i < _emps.length; i++) {
            r2 += '<th>Vlr. Pendente (R$)</th><th>% Part.</th><th>MC (R$)</th><th>MC %</th><th>Taxa</th>';
        }
        r2 += '</tr>';
        document.getElementById('cpvf-head').innerHTML = r1 + r2;
    }

    /* ── rodapé ──────────────────────────────────────────────────── */
    function buildFoot(totais) {
        var by = {};
        for (var i = 0; i < totais.length; i++) by[totais[i].EMPR_ID] = totais[i];
        document.getElementById('cpvf-foot').innerHTML =
            '<tr class="row-total"><td class="col-desc">TOTAL GERAL</td>' + allMCells(by) + '</tr>';
    }

    /* ── inserir L2 (sub grupos) ─────────────────────────────────── */
    function insertL2(afterTr, tq, data, expandChildren) {
        var sgMap = pivot(data, function (r) {
            return r.SUB_GRUPO_ITEM + '\x00' + r.FAMILIA_ITEM + '\x00' + r.SUB_FAMILIA_ITEM;
        });
        var html = '', idx = 0;
        sgMap.forEach(function (entry) {
            var r    = entry.ref;
            var path = 't_' + tq + '_s_' + idx;
            var lbl  = esc(r.SUB_GRUPO_ITEM) + ' - ' + esc(r.FAMILIA_ITEM) + ' - ' + esc(r.SUB_FAMILIA_ITEM);
            html += '<tr class="row-l2" data-path="' + path + '"'  +
                    ' data-tq="'   + esc(tq)                 + '"' +
                    ' data-sg="'   + esc(r.SUB_GRUPO_ITEM)   + '"' +
                    ' data-fam="'  + esc(r.FAMILIA_ITEM)     + '"' +
                    ' data-sfam="' + esc(r.SUB_FAMILIA_ITEM) + '"' +
                    ' data-idx="'  + idx + '">'               +
                    '<td class="col-desc"><i class="bi bi-chevron-right ico-exp"></i>' + lbl + '</td>' +
                    allMCells(entry.byEmpr) + '</tr>';
            idx++;
        });
        afterTr.insertAdjacentHTML('afterend', html);
        if (expandChildren) {
            document.querySelectorAll('#cpvf-body tr.row-l2[data-tq="' + tq + '"]:not(.expanded)')
                .forEach(function (l2tr) { toggleL2(l2tr); });
        }
    }

    /* ── inserir L3 (itens) ──────────────────────────────────────── */
    function insertL3(afterTr, tq, sgIdx, data) {
        var itemMap = pivot(data, function (r) { return r.COD_ITEM + '\x00' + r.ITEM; });
        var html = '', idx = 0;
        itemMap.forEach(function (entry) {
            var r    = entry.ref;
            var path = 't_' + tq + '_s_' + sgIdx + '_i_' + idx;
            html += '<tr class="row-l3" data-path="' + path + '">' +
                    '<td class="col-desc">' + esc(r.COD_ITEM) + ' - ' + esc(r.ITEM) + '</td>' +
                    allMCells(entry.byEmpr) + '</tr>';
            idx++;
        });
        afterTr.insertAdjacentHTML('afterend', html);
    }

    /* ── toggle tanque ───────────────────────────────────────────── */
    function toggleL1(tr, expandChildren) {
        var tq = tr.dataset.tq, path = 't_' + tq;
        if (tr.classList.contains('expanded')) { removeDesc(path); tr.classList.remove('expanded'); return; }
        tr.classList.add('expanded');
        if (_cache.l2[tq]) { insertL2(tr, tq, _cache.l2[tq], expandChildren); return; }
        setLoad(true);
        fetch(BASE + 'faturamento-carteira-filial-l2?data_ini=' + _di + '&data_fim=' + _df + '&tanque=' + encodeURIComponent(tq))
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) throw new Error(j.error);
                _cache.l2[tq] = j.data;
                insertL2(tr, tq, j.data, expandChildren);
            })
            .catch(function (e) { alert('Erro: ' + e.message); })
            .finally(function () { setLoad(false); });
    }

    /* ── toggle sub grupo ────────────────────────────────────────── */
    function toggleL2(tr) {
        var tq = tr.dataset.tq, sg = tr.dataset.sg, fam = tr.dataset.fam,
            sfam = tr.dataset.sfam, idx = tr.dataset.idx;
        var path = 't_' + tq + '_s_' + idx, cKey = tq + '|' + idx;
        if (tr.classList.contains('expanded')) { removeDesc(path); tr.classList.remove('expanded'); return; }
        tr.classList.add('expanded');
        if (_cache.l3[cKey]) { insertL3(tr, tq, idx, _cache.l3[cKey]); return; }
        setLoad(true);
        fetch(BASE + 'faturamento-carteira-filial-l3?data_ini=' + _di + '&data_fim=' + _df +
              '&tanque=' + encodeURIComponent(tq) + '&sub_grupo=' + encodeURIComponent(sg) +
              '&familia=' + encodeURIComponent(fam) + '&sub_familia=' + encodeURIComponent(sfam))
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) throw new Error(j.error);
                _cache.l3[cKey] = j.data;
                insertL3(tr, tq, idx, j.data);
            })
            .catch(function (e) { alert('Erro: ' + e.message); })
            .finally(function () { setLoad(false); });
    }

    /* ── delegação de eventos ────────────────────────────────────── */
    document.getElementById('cpvf-body').addEventListener('click', function (e) {
        var tr = e.target.closest('tr.row-l1, tr.row-l2');
        if (!tr) return;
        if (tr.classList.contains('row-l1')) toggleL1(tr); else toggleL2(tr);
    });

    /* ── loading ─────────────────────────────────────────────────── */
    function setLoad(on) { document.getElementById('cpvf-loading').hidden = !on; }

    /* ── carga principal ─────────────────────────────────────────── */
    window.cpvfLoad = function () {
        _di = document.getElementById('cpvf-di').value;
        _df = document.getElementById('cpvf-df').value;
        if (!_di || !_df) { alert('Informe as datas.'); return; }
        _cache = { l2: {}, l3: {} };
        document.getElementById('cpvf-card').hidden     = true;
        document.getElementById('btn-csv').hidden       = true;
        document.getElementById('btn-toggle-all').hidden = true;
        var _btnTA = document.getElementById('btn-toggle-all');
        _btnTA.dataset.state = 'collapsed';
        _btnTA.innerHTML = '<i class="bi bi-arrows-expand"></i> Expandir Tudo';
        setLoad(true);
        fetch(BASE + 'faturamento-carteira-filial-l1?data_ini=' + _di + '&data_fim=' + _df)
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) throw new Error(json.error);
                _emps = json.totais.map(function (t) { return t.EMPR_ID; });
                if (!_emps.length) { alert('Nenhum dado encontrado.'); return; }
                buildHeader();
                buildFoot(json.totais);
                var tqMap = pivot(json.tanques, function (r) { return r.COD_TANQUE || 'null'; });
                var html  = '';
                tqMap.forEach(function (entry, tq) {
                    var r    = entry.ref;
                    var desc = r.DESC_TANQUE || '—';
                    var lbl  = tq === 'null' ? 'Sem Tanque' : esc(tq) + ' - ' + esc(desc);
                    html += '<tr class="row-l1" data-path="t_' + esc(tq) + '" data-tq="' + esc(tq) + '">' +
                            '<td class="col-desc"><i class="bi bi-chevron-right ico-exp"></i>' + lbl + '</td>' +
                            allMCells(entry.byEmpr) + '</tr>';
                });
                document.getElementById('cpvf-body').innerHTML = html;
                document.getElementById('cpvf-card').hidden      = false;
                document.getElementById('btn-csv').hidden        = false;
                document.getElementById('btn-toggle-all').hidden = false;
            })
            .catch(function (e) { alert('Erro: ' + e.message); })
            .finally(function () { setLoad(false); });
    };

    /* ── expandir / recolher tudo ───────────────────────────────── */
    function collapseAll() {
        document.querySelectorAll('#cpvf-body tr:not(.row-l1)').forEach(function (tr) { tr.remove(); });
        document.querySelectorAll('#cpvf-body tr.row-l1').forEach(function (tr) { tr.classList.remove('expanded'); });
    }

    function expandAll() {
        document.querySelectorAll('#cpvf-body tr.row-l1:not(.expanded)').forEach(function (tr) { toggleL1(tr, true); });
    }

    window.toggleAll = function () {
        var btn = document.getElementById('btn-toggle-all');
        if (btn.dataset.state === 'expanded') {
            collapseAll();
            btn.dataset.state = 'collapsed';
            btn.innerHTML = '<i class="bi bi-arrows-expand"></i> Expandir Tudo';
        } else {
            expandAll();
            btn.dataset.state = 'expanded';
            btn.innerHTML = '<i class="bi bi-arrows-collapse"></i> Recolher Tudo';
        }
    };

    /* ── export CSV ──────────────────────────────────────────────── */
    window.exportarCSV = function () {
        var rows = [], hdr = ['Descrição'];
        for (var i = 0; i < _emps.length; i++) {
            var f = 'Filial ' + _emps[i];
            hdr.push(f+' Vlr.Pend.', f+' %Part.', f+' MC(R$)', f+' MC%', f+' Taxa');
        }
        rows.push(hdr);
        document.querySelectorAll('#cpvf-body tr, #cpvf-foot tr').forEach(function (tr) {
            var row = [];
            tr.querySelectorAll('td').forEach(function (td) {
                row.push('"' + td.textContent.trim().replace(/"/g,'""') + '"');
            });
            rows.push(row);
        });
        var csv  = rows.map(function (r) { return r.join(';'); }).join('\n');
        var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href   = url; a.download = 'carteira-pdv-filial.csv';
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
    };

    /* ── datas padrão + auto-carga ───────────────────────────────── */
    (function () {
        var now  = new Date();
        var y    = now.getFullYear();
        var m    = String(now.getMonth() + 1).padStart(2, '0');
        var last = new Date(y, now.getMonth() + 1, 0).getDate();
        document.getElementById('cpvf-di').value = y + '-' + m + '-01';
        document.getElementById('cpvf-df').value = y + '-' + m + '-' + String(last).padStart(2, '0');
        cpvfLoad();
    })();
})();
</script>

<?= $render('footer') ?>
