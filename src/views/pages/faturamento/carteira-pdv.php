<?php
/** @var bool $is_admin @var array $rotas_permitidas @var string $base @var callable $render */
$acesso = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Carteira PDV',
    'showNavbar' => true,
    'pageActive' => 'faturamento-carteira-pdv',
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.cpv-wrap      { padding:12px; background:#f0f2f5; min-height:calc(100vh - 56px); }
.cpv-filter    { display:flex; align-items:center; gap:8px; flex-wrap:wrap;
                 background:#fff; border-radius:8px; padding:9px 14px;
                 box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:10px; }
.cpv-filter label { font-size:12px; font-weight:600; color:#555; margin:0; }
.cpv-filter input  { font-size:12px; border:1px solid #ccc; border-radius:6px; padding:5px 8px; }
.cpv-card      { background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); overflow:hidden; }
.cpv-card-head { background:#1565c0; color:#fff; padding:8px 14px; font-size:11px;
                 font-weight:700; text-transform:uppercase; letter-spacing:.4px;
                 display:flex; align-items:center; justify-content:space-between; }
.cpv-tbl       { border-collapse:collapse; font-size:12px; table-layout:fixed;
                 width:100%; min-width:860px; }
.cpv-tbl th    { background:#e3f2fd; color:#1565c0; padding:6px 10px; font-size:11px;
                 font-weight:700; white-space:nowrap; border-bottom:2px solid #bbdefb;
                 overflow:hidden; text-align:left; }
.cpv-tbl th.r  { text-align:right; }
.cpv-tbl td    { padding:5px 10px; border-bottom:1px solid #f0f0f0;
                 overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cpv-tbl td.r  { text-align:right; }

/* ── Nível 1: Empresa ── */
.row-l1 td              { font-weight:700; background:#e8edf5; color:#1e3a8a;
                          border-top:2px solid #c7d2e8; cursor:pointer; }
.row-l1:hover td        { background:#dce4f5 !important; }
.row-l1.loading td      { opacity:.6; }

/* ── Nível 2: Tanque ── */
.row-l2 td              { font-weight:600; background:#f0f4fb; color:#1565c0; cursor:pointer; }
.row-l2 td:first-child  { padding-left:28px; }
.row-l2:hover td        { background:#e3ecf8 !important; }

/* ── Nível 3: Sub Grupo ── */
.row-l3 td              { background:#f8faff; color:#374151; cursor:pointer; }
.row-l3 td:first-child  { padding-left:48px; }
.row-l3:hover td        { background:#eef2fb !important; }

/* ── Nível 4: Item ── */
.row-l4 td              { background:#fff; color:#666; font-size:11px; }
.row-l4 td:first-child  { padding-left:68px; }
.row-l4:hover td        { background:#f9fafb !important; }

/* Total geral */
.row-total td           { font-weight:700; background:#1e3a8a; color:#fff; }
.row-total td.r         { color:#fff; }

/* Ícone expand */
.ico-exp {
    display:inline-flex; align-items:center; justify-content:center;
    width:16px; height:16px; border-radius:3px; margin-right:5px;
    font-size:10px; font-style:normal; color:inherit;
    transition:transform .2s ease;
}
.expanded > td .ico-exp { transform:rotate(90deg); }

/* Botões topo */
.btn-exp-all { font-size:11px; padding:3px 10px; }

/* Loading / empty */
.cpv-msg     { text-align:center; padding:24px; color:#999; font-size:13px; }
</style>

<div class="cpv-wrap">

    <!-- Filtros -->
    <div class="cpv-filter">
        <label>De:</label>
        <input type="date" id="fDi" value="<?= date('Y-m-01', strtotime('-12 months')) ?>">
        <label>Até:</label>
        <input type="date" id="fDf" value="<?= date('Y-m-t') ?>">
        <button class="btn btn-sm btn-primary" onclick="carregar()">
            <i class="bi bi-search"></i> Filtrar
        </button>
        <span id="cpvStatus" style="font-size:11px;color:#888;margin-left:auto"></span>
    </div>

    <!-- Tabela -->
    <div class="cpv-card">
        <div class="cpv-card-head">
            <span>Carteira PDV — Empresa / Tanque / Sub Grupo / Item</span>
            <div style="display:flex;gap:6px">
                <button class="btn btn-sm btn-outline-light btn-exp-all" onclick="expandirTudo()">+ Expandir tudo</button>
                <button class="btn btn-sm btn-outline-light btn-exp-all" onclick="recolherTudo()">− Recolher tudo</button>
                <button class="btn btn-sm btn-outline-light btn-exp-all" onclick="exportarCSV()">⬇ CSV</button>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="cpv-tbl">
                <colgroup>
                    <col style="width:320px">
                    <col style="width:140px">
                    <col style="width:80px">
                    <col style="width:140px">
                    <col style="width:80px">
                    <col style="width:100px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Empresa / Tanque / Sub Grupo / Item</th>
                        <th class="r">Vlr. Pendente (R$)</th>
                        <th class="r">% Part.</th>
                        <th class="r">MC (R$)</th>
                        <th class="r">MC %</th>
                        <th class="r">Taxa</th>
                    </tr>
                </thead>
                <tbody id="cpvBody">
                    <tr><td colspan="6" class="cpv-msg">Clique em Filtrar para carregar.</td></tr>
                </tbody>
                <tfoot id="cpvFoot"></tfoot>
            </table>
        </div>
    </div>

</div>

<div id="cpv-data" data-base="<?= htmlspecialchars($base) ?>" style="display:none"></div>

<script>
'use strict';
var _base = document.getElementById('cpv-data').dataset.base;

var _c = { l2:{}, l3:{}, l4:{}, l5:{}, l6:{} };

/* ── utilitários ── */
function moeda(v) { return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function perc(v)  { return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+'%'; }
function num(v)   { return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:4,maximumFractionDigits:4}); }
function esc(s)   { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function qs()     { return 'data_ini='+document.getElementById('fDi').value+'&data_fim='+document.getElementById('fDf').value; }
function status(m){ document.getElementById('cpvStatus').textContent = m; }

/* key segura para usar em id/data-path */
function k(v)     { return encodeURIComponent(String(v||'')); }

/* células de medidas */
function cells(r) {
    return '<td class="r">'+moeda(r.VLR_PENDENTE)+'</td>'+
           '<td class="r">'+perc(r.PERC_PARTICIPACAO)+'</td>'+
           '<td class="r">'+moeda(r.MC_VALOR)+'</td>'+
           '<td class="r">'+perc(r.MC_PERC)+'</td>'+
           '<td class="r">'+num(r.TAXA)+'</td>';
}

/* remove do DOM todas as linhas descendentes de um path */
function removeDesc(path) {
    document.querySelectorAll('[data-path]').forEach(function(tr) {
        if (tr.dataset.path.indexOf(path+'|') === 0) tr.remove();
    });
}

/* insere linhas após uma linha de referência */
function insertAfter(refRow, rows) {
    var frag = document.createDocumentFragment();
    rows.forEach(function(tr){ frag.appendChild(tr); });
    refRow.parentNode.insertBefore(frag, refRow.nextSibling);
}

/* ── CARREGAR (nível 1) ── */
function carregar() {
    status('Carregando...');
    _c = { l2:{}, l3:{}, l4:{} };
    document.getElementById('cpvBody').innerHTML = '<tr><td colspan="6" class="cpv-msg">Carregando...</td></tr>';
    document.getElementById('cpvFoot').innerHTML = '';

    fetch(_base+'faturamento-carteira-api-l1?'+qs())
        .then(function(r){ return r.json(); })
        .then(function(json) {
            if (!json.success) { status('Erro: '+json.error); return; }
            var rows = json.data || [];
            if (!rows.length) {
                document.getElementById('cpvBody').innerHTML = '<tr><td colspan="6" class="cpv-msg">Sem dados para o período.</td></tr>';
                status(''); return;
            }
            var html = '';
            rows.forEach(function(r) {
                var empr = r.EMPR_ID;
                var p    = k(empr);
                html += '<tr class="row-l1" id="r1-'+p+'" data-path="'+p+'" data-empr="'+esc(empr)+'" onclick="toggleL1(this)">' +
                    '<td><i class="bi bi-chevron-right ico-exp"></i> Empresa '+esc(empr)+'</td>'+cells(r)+'</tr>';
            });
            document.getElementById('cpvBody').innerHTML = html;

            var t = json.total || {};
            document.getElementById('cpvFoot').innerHTML =
                '<tr class="row-total"><td>Total Geral</td>'+
                '<td class="r">'+moeda(t.VLR_PENDENTE)+'</td>'+
                '<td class="r">100,00%</td>'+
                '<td class="r">'+moeda(t.MC_VALOR)+'</td>'+
                '<td class="r">'+perc(t.MC_PERC)+'</td>'+
                '<td class="r">'+num(t.TAXA)+'</td></tr>';

            status('Atualizado: '+new Date().toLocaleTimeString('pt-BR'));
        })
        .catch(function(){ status('Erro de comunicação.'); });
}

/* ── TOGGLE L1 → mostra/oculta tanques ── */
function toggleL1(row) {
    var path  = row.dataset.path;
    var empr  = row.dataset.empr;
    var emprK = k(empr);

    if (row.classList.contains('expanded')) {
        removeDesc(path);
        row.classList.remove('expanded');
        return;
    }

    if (_c.l2[emprK]) {
        insertL2(row, _c.l2[emprK], path, empr);
        row.classList.add('expanded');
        return;
    }

    row.classList.add('loading');
    fetch(_base+'faturamento-carteira-api-l2?'+qs()+'&empr_id='+encodeURIComponent(empr))
        .then(function(r){ return r.json(); })
        .then(function(json) {
            row.classList.remove('loading');
            if (!json.success) return;
            _c.l2[emprK] = json.data || [];
            insertL2(row, _c.l2[emprK], path, empr);
            row.classList.add('expanded');
        })
        .catch(function(){ row.classList.remove('loading'); });
}

function insertL2(refRow, rows, parentPath, empr) {
    var trs = rows.map(function(r) {
        var tq   = r.COD_TANQUE;
        var p    = parentPath+'|'+k(tq);
        var tr   = document.createElement('tr');
        tr.className   = 'row-l2';
        tr.id          = 'r2-'+p;
        tr.dataset.path  = p;
        tr.dataset.empr  = empr;
        tr.dataset.tanque= tq;
        var label = esc(tq) + (r.DESC_TANQUE ? ' - ' + esc(r.DESC_TANQUE) : '');
        tr.innerHTML   = '<td><i class="bi bi-chevron-right ico-exp"></i> '+label+'</td>'+cells(r);
        tr.onclick     = function(){ toggleL2(tr); };
        return tr;
    });
    insertAfter(refRow, trs);
}

/* ── TOGGLE L2 → mostra/oculta sub grupos ── */
function toggleL2(row) {
    var path  = row.dataset.path;
    var empr  = row.dataset.empr;
    var tq    = row.dataset.tanque;
    var tqK   = k(empr)+'|'+k(tq);

    if (row.classList.contains('expanded')) {
        removeDesc(path);
        row.classList.remove('expanded');
        return;
    }

    if (_c.l3[tqK]) {
        insertL3(row, _c.l3[tqK], path, empr, tq);
        row.classList.add('expanded');
        return;
    }

    row.classList.add('loading');
    fetch(_base+'faturamento-carteira-api-l3?'+qs()+'&empr_id='+encodeURIComponent(empr)+'&tanque='+encodeURIComponent(tq))
        .then(function(r){ return r.json(); })
        .then(function(json) {
            row.classList.remove('loading');
            if (!json.success) return;
            _c.l3[tqK] = json.data || [];
            insertL3(row, _c.l3[tqK], path, empr, tq);
            row.classList.add('expanded');
        })
        .catch(function(){ row.classList.remove('loading'); });
}

function insertL3(refRow, rows, parentPath, empr, tq) {
    var trs = rows.map(function(r) {
        var sg  = r.SUB_GRUPO_ITEM, fam = r.FAMILIA_ITEM, sf = r.SUB_FAMILIA_ITEM;
        var p   = parentPath+'|'+k(sg)+'|'+k(fam)+'|'+k(sf);
        var lbl = esc(sg)+' - '+esc(fam)+' - '+esc(sf);
        var tr  = document.createElement('tr');
        tr.className        = 'row-l3';
        tr.id               = 'r3-'+p;
        tr.dataset.path     = p;
        tr.dataset.empr     = empr;
        tr.dataset.tanque   = tq;
        tr.dataset.subGrupo = sg;
        tr.dataset.familia  = fam;
        tr.dataset.subFamilia = sf;
        tr.innerHTML = '<td><i class="bi bi-chevron-right ico-exp"></i>'+lbl+'</td>'+cells(r);
        tr.onclick   = function(){ toggleL3(tr); };
        return tr;
    });
    insertAfter(refRow, trs);
}

/* ── TOGGLE L3 → mostra/oculta itens ── */
function toggleL3(row) {
    var path = row.dataset.path, empr = row.dataset.empr,
        tq   = row.dataset.tanque,  sg = row.dataset.subGrupo,
        fam  = row.dataset.familia, sf = row.dataset.subFamilia;
    var cK = k(empr)+'|'+k(tq)+'|'+k(sg)+'|'+k(fam)+'|'+k(sf);
    if (row.classList.contains('expanded')) { removeDesc(path); row.classList.remove('expanded'); return; }
    if (_c.l4[cK]) { insertL4(row, _c.l4[cK], path); row.classList.add('expanded'); return; }
    row.classList.add('loading');
    fetch(_base+'faturamento-carteira-api-l4?'+qs()+
          '&empr_id='+encodeURIComponent(empr)+
          '&tanque='+encodeURIComponent(tq)+
          '&sub_grupo='+encodeURIComponent(sg)+
          '&familia='+encodeURIComponent(fam)+
          '&sub_familia='+encodeURIComponent(sf))
        .then(function(r){ return r.json(); })
        .then(function(json) {
            row.classList.remove('loading');
            if (!json.success) return;
            _c.l4[cK] = json.data || [];
            insertL4(row, _c.l4[cK], path);
            row.classList.add('expanded');
        }).catch(function(){ row.classList.remove('loading'); });
}

function insertL4(refRow, rows, parentPath) {
    var trs = rows.map(function(r) {
        var p  = parentPath+'|'+k(r.COD_ITEM);
        var tr = document.createElement('tr');
        tr.className    = 'row-l4';
        tr.id           = 'r4-'+p;
        tr.dataset.path = p;
        tr.innerHTML    = '<td>'+esc(r.COD_ITEM)+' - '+esc(r.ITEM)+'</td>'+cells(r);
        return tr;
    });
    insertAfter(refRow, trs);
}

/* ── EXPANDIR / RECOLHER TUDO ── */
function expandirTudo() {
    document.querySelectorAll('.row-l1:not(.expanded)').forEach(function(r){ toggleL1(r); });
}
function recolherTudo() {
    document.querySelectorAll('.row-l3.expanded').forEach(function(r){ toggleL3(r); });
    document.querySelectorAll('.row-l2.expanded').forEach(function(r){ toggleL2(r); });
    document.querySelectorAll('.row-l1.expanded').forEach(function(r){ toggleL1(r); });
}

/* ── EXPORTAR CSV ── */
function exportarCSV() {
    var cols = ['Descrição','Vlr Pendente','% Part.','MC (R$)','MC %','Taxa'];
    var linhas = [cols];

    document.querySelectorAll('#cpvBody tr[data-path], #cpvFoot tr.row-total').forEach(function(tr) {
        var tds = tr.querySelectorAll('td');
        if (tds.length < 6) return;
        linhas.push([
            tds[0].textContent.trim().replace(/^[▶\s]+/,''),
            tds[1].textContent.trim(),
            tds[2].textContent.trim(),
            tds[3].textContent.trim(),
            tds[4].textContent.trim(),
            tds[5].textContent.trim()
        ]);
    });

    var csv = linhas.map(function(l) {
        return l.map(function(c){ return '"'+String(c).replace(/"/g,'""')+'"'; }).join(';');
    }).join('\n');

    var blob = new Blob(['﻿'+csv], {type:'text/csv;charset=utf-8'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href   = url;
    a.download = 'carteira-pdv-'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', carregar);
</script>

<?= $render('footer') ?>
