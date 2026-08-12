'use strict';

const EMPRESAS_AT = {
    '1':'1-DOURADINA PR','2':'2-VILHENA RO','3':'3-CANDELÁRIA RS',
    '4':'4-F.SANTANA BA','5':'5-JACIARA MT','9':'9-S.GUIOMARD AC',
    '10':'10-MOLAS DOURAD.','11':'11-MOLAS CAND.','13':'13-ELOI MENDES MG',
    '14':'14-ARAGUATINS TO','15':'15-PATOS MINAS MG','16':'16-PATOS DE MINAS MG',
};

const COLCHAO_TANQUES = new Set([1, 2]);
const SOMIE_TANQUES   = new Set([3, 12, 23]);

const fmt    = v => 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtN   = v => parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtXls = v => typeof v === 'number'
    ? v.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})
    : String(v ?? '');
const num = v => parseFloat(v || 0);

let dadosGlobais = [];

// estado de abertura
const grpAberto  = {};  // grpId  -> bool
const tanqAberto = {};  // tanqId -> bool
const tanqCarregado = {}; // tanqId -> bool

function grupoTanque(cod) {
    const c = Number(cod);
    if (COLCHAO_TANQUES.has(c)) return 'COLCHÃO';
    if (SOMIE_TANQUES.has(c))   return 'SOMIE';
    return 'OUTROS';
}

// Retorna todas as <tr> de nível tanque de um grupo
function tanqRowsDoGrupo(tbody, grpId) {
    return [...tbody.querySelectorAll(`tr.tanq-row[data-grp="${grpId}"]`)];
}

// Retorna todas as <tr> filhas de um tanque (loading + clas)
function filhosTanq(tbody, tanqId) {
    return [...tbody.querySelectorAll(`tr[data-pai-tanq="${tanqId}"]`)];
}

function renderizar(rows) {
    const tbody = document.getElementById('at-tbody');

    const empresas = new Map();
    for (const t of rows) {
        const empr  = String(t.EMPR_ID ?? '');
        const grupo = grupoTanque(t.COD_TANQUE);
        if (!empresas.has(empr)) empresas.set(empr, new Map());
        const g = empresas.get(empr);
        if (!g.has(grupo)) g.set(grupo, []);
        g.get(grupo).push(t);
    }

    const ORDEM_GRUPO = ['COLCHÃO', 'SOMIE', 'OUTROS'];
    let html = '';
    let totGeral = 0;

    for (const [emprId, grupos] of empresas) {
        const emprNome = EMPRESAS_AT[emprId] || ('Empresa ' + emprId);
        let subEmpr = 0;

        html += `<tr style="background:#1a1a2e;color:#fff;font-weight:700;">
            <td colspan="4" style="padding:7px 10px;">${emprNome}</td>
        </tr>`;

        for (const grupoNome of ORDEM_GRUPO) {
            if (!grupos.has(grupoNome)) continue;
            const tanques  = grupos.get(grupoNome);
            const grpCap   = tanques.reduce((s, t) => s + num(t.CAP_UEP_DIA) * 0.80, 0);
            const grpValor = tanques.reduce((s, t) => s + num(t.VALOR_PENDENTE), 0);
            const grpUep   = tanques.reduce((s, t) => s + num(t.UEP), 0);
            const grpTaxa  = grpUep ? grpValor / grpUep : 0;
            const grpProj  = tanques.reduce((s, t) => s + num(t.CAP_UEP_DIA) * 0.80 * num(t.TAXA), 0);
            subEmpr += grpProj;

            const grpId = `grp-${emprId}-${grupoNome.replace(/[^a-z0-9]/gi,'')}`;

            // Linha do grupo
            html += `<tr class="grp-row" data-grp-id="${grpId}" style="background:#e8eeff;font-weight:700;cursor:pointer;">
                <td style="padding:6px 10px;color:#1a56db;user-select:none;">${grupoNome}</td>
                <td style="text-align:right;">${fmtN(grpCap)}</td>
                <td style="text-align:right;">${fmtN(grpTaxa)}</td>
                <td style="text-align:right;">${fmt(grpProj)}</td>
            </tr>`;

            // Linhas dos tanques (ocultas por padrão)
            for (const t of tanques) {
                const cap    = num(t.CAP_UEP_DIA) * 0.80;
                const taxa   = num(t.TAXA);
                const proj   = cap * taxa;
                const tanqId = `tanq-${emprId}-${t.COD_TANQUE}`;

                html += `<tr class="tanq-row"
                            data-grp="${grpId}"
                            data-tanq-id="${tanqId}"
                            data-empr-id="${emprId}"
                            data-empr-nome="${emprNome}"
                            data-cod-tanque="${t.COD_TANQUE}"
                            data-desc-tanque="${t.DESC_TANQUE ?? ''}"
                            style="display:none;cursor:pointer;background:#f4f6ff;">
                    <td style="padding:6px 10px 6px 28px;color:#1a56db;font-weight:600;user-select:none;">
                        ${t.COD_TANQUE} — ${t.DESC_TANQUE ?? ''}
                    </td>
                    <td style="text-align:right;">${fmtN(cap)} <small style="color:#888;">(80%)</small></td>
                    <td style="text-align:right;">${fmtN(taxa)}</td>
                    <td style="text-align:right;">${fmt(proj)}</td>
                </tr>`;

                // Linha de loading (oculta, sem classe de grupo)
                html += `<tr data-pai-tanq="${tanqId}" data-loading="1" style="display:none;">
                    <td colspan="4" style="padding:6px 16px 6px 50px;color:#888;font-size:.85rem;">
                        <div class="spinner-border spinner-border-sm text-primary me-2" style="width:.8rem;height:.8rem;" role="status"></div>
                        Carregando classificações...
                    </td>
                </tr>`;
            }
        }

        totGeral += subEmpr;
        html += `<tr style="background:#f0f0f0;font-weight:600;border-top:2px solid #dee2e6;">
            <td style="padding:6px 10px;">Total ${emprNome}</td>
            <td colspan="2"></td>
            <td style="text-align:right;">${fmt(subEmpr)}</td>
        </tr><tr><td colspan="4" style="padding:3px;"></td></tr>`;
    }

    html += `<tr style="background:#0d1b2a;color:#fff;font-weight:700;border-top:3px solid #333;">
        <td style="padding:8px 10px;">TOTAL GERAL</td>
        <td colspan="2"></td>
        <td style="text-align:right;padding:8px;">${fmt(totGeral)}</td>
    </tr>`;

    tbody.innerHTML = html;
    document.getElementById('at-wrap').style.display = 'block';

    // ── Clique no GRUPO → expande/colapsa tanques ─────────────────────────
    tbody.querySelectorAll('.grp-row').forEach(tr => {
        const grpId = tr.dataset.grpId;
        grpAberto[grpId] = false;

        tr.addEventListener('click', () => {
            grpAberto[grpId] = !grpAberto[grpId];
            const aberto = grpAberto[grpId];

            tr.style.background = aberto ? '#d0dbff' : '#e8eeff';
            tr.querySelector('td').style.color = aberto ? '#0b3d91' : '#1a56db';

            // Mostra/oculta tanques
            tanqRowsDoGrupo(tbody, grpId).forEach(tanqTr => {
                tanqTr.style.display = aberto ? '' : 'none';
                // Se colapsando o grupo, colapsa também os filhos do tanque
                if (!aberto) {
                    const tid = tanqTr.dataset.tanqId;
                    filhosTanq(tbody, tid).forEach(f => { f.style.display = 'none'; });
                    tanqAberto[tid] = false;
                    tanqTr.style.background = '#f4f6ff';
                    tanqTr.querySelector('td').style.color = '#1a56db';
                }
            });
        });
    });

    // ── Clique no TANQUE → expande/colapsa classificações ────────────────
    tbody.querySelectorAll('.tanq-row').forEach(tr => {
        const tanqId    = tr.dataset.tanqId;
        const emprId    = tr.dataset.emprId;
        const emprNome  = tr.dataset.emprNome;
        const codTanque = tr.dataset.codTanque;
        const descTanq  = tr.dataset.descTanque;
        tanqAberto[tanqId] = false;

        tr.addEventListener('click', async () => {
            const jaCarregado = tanqCarregado[tanqId];
            const clasRows    = filhosTanq(tbody, tanqId).filter(r => !r.dataset.loading);
            const loadRow     = filhosTanq(tbody, tanqId).find(r => r.dataset.loading);

            if (jaCarregado) {
                // Toggle
                tanqAberto[tanqId] = !tanqAberto[tanqId];
                const aberto = tanqAberto[tanqId];
                clasRows.forEach(r => { r.style.display = aberto ? '' : 'none'; });
                tr.style.background = aberto ? '#dde5ff' : '#f4f6ff';
                tr.querySelector('td').style.color = aberto ? '#0b3d91' : '#1a56db';
                return;
            }

            // Primeira vez — buscar via API
            tanqCarregado[tanqId] = true;
            tanqAberto[tanqId]    = true;
            if (loadRow) loadRow.style.display = '';
            tr.style.background = '#dde5ff';
            tr.querySelector('td').style.color = '#0b3d91';

            try {
                const res  = await fetch(`/faturamento-api-analise-tanque-clas?empr_id=${emprId}&cod_tanque=${codTanque}`);
                const data = await res.json();
                if (loadRow) loadRow.style.display = 'none';

                if (!data.success || !data.data?.length) {
                    insertAfter(loadRow || tr, `<tr data-pai-tanq="${tanqId}">
                        <td colspan="4" style="padding:5px 10px 5px 50px;color:#888;font-size:.85rem;">Nenhuma classificação encontrada.</td>
                    </tr>`);
                    return;
                }

                let html = '';
                let totV = 0, totU = 0;
                for (const r of data.data) {
                    const vp = num(r.VALOR_PENDENTE), up = num(r.UEP);
                    totV += vp; totU += up;
                    const params = new URLSearchParams({empr_id: emprId, classificacao: r.CLASSIFICACAO ?? ''});
                    html += `<tr class="clas-row" data-pai-tanq="${tanqId}" data-href="/faturamento-eficiencia-uep-detalhe?${params}" style="cursor:pointer;">
                        <td style="padding:5px 10px 5px 50px;color:#1a56db;font-weight:500;user-select:none;">${r.CLASSIFICACAO ?? ''}</td>
                        <td style="text-align:right;">${fmt(vp)}</td>
                        <td style="text-align:right;">${fmtN(up)}</td>
                        <td style="text-align:right;">${fmtN(r.TAXA)}</td>
                    </tr>`;
                }
                const taxaTot = totU ? totV / totU : 0;
                html += `<tr data-pai-tanq="${tanqId}" style="background:#f7f8fa;font-weight:600;border-top:1px solid #dee2e6;">
                    <td style="padding:5px 10px 5px 50px;color:#555;">Total ${codTanque} — ${descTanq}</td>
                    <td style="text-align:right;">${fmt(totV)}</td>
                    <td style="text-align:right;">${fmtN(totU)}</td>
                    <td style="text-align:right;">${fmtN(taxaTot)}</td>
                </tr>`;

                insertAfter(loadRow || tr, html);

                // Clique na CLASSIFICAÇÃO → abre itens em nova aba/página
                tbody.querySelectorAll(`tr.clas-row[data-pai-tanq="${tanqId}"]`).forEach(cr => {
                    cr.addEventListener('click', () => {
                        cr.style.background = '#c7d3ff';
                        cr.querySelector('td').style.color = '#0b3d91';
                        setTimeout(() => { window.location.href = cr.dataset.href; }, 150);
                    });
                    cr.addEventListener('mouseenter', () => { cr.style.background = '#eef2ff'; });
                    cr.addEventListener('mouseleave', () => { cr.style.background = ''; });
                });

            } catch (e) {
                if (loadRow) loadRow.style.display = 'none';
                tanqCarregado[tanqId] = false;
                tanqAberto[tanqId]    = false;
                tr.style.background = '#f4f6ff';
                tr.querySelector('td').style.color = '#1a56db';
            }
        });

        tr.addEventListener('mouseenter', () => {
            if (!tanqAberto[tanqId]) tr.style.background = '#dde5ff';
        });
        tr.addEventListener('mouseleave', () => {
            if (!tanqAberto[tanqId]) tr.style.background = '#f4f6ff';
        });
    });
}

function insertAfter(refRow, html) {
    const tmp = document.createElement('table');
    tmp.innerHTML = `<tbody>${html}</tbody>`;
    const rows = [...tmp.querySelector('tbody').children];
    let cursor = refRow;
    for (const row of rows) {
        cursor.insertAdjacentElement('afterend', row);
        cursor = row;
    }
}

function exportarExcel() {
    if (!dadosGlobais.length) return;
    const linhas = [['Empresa', 'Grupo', 'Tanque', 'Capacidade (80%)', 'Taxa (R$/UEP)', 'Projeção (R$/dia)']];
    for (const t of dadosGlobais) {
        const emprNome = EMPRESAS_AT[String(t.EMPR_ID)] || ('Empresa ' + t.EMPR_ID);
        const cap = num(t.CAP_UEP_DIA) * 0.80, taxa = num(t.TAXA);
        linhas.push([emprNome, grupoTanque(t.COD_TANQUE), `${t.COD_TANQUE} — ${t.DESC_TANQUE ?? ''}`, cap, taxa, cap * taxa]);
    }
    const csv = linhas.map(l => l.map(c => {
        const s = fmtXls(c).replace(/"/g, '""');
        return s.includes(';') || s.includes('\n') || s.includes('"') ? `"${s}"` : s;
    }).join(';')).join('\r\n');
    const blob = new Blob(['﻿' + csv], {type: 'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'analise-tanque.csv'; a.click();
    URL.revokeObjectURL(url);
}

function carregar() {
    document.getElementById('at-loading').style.display = 'block';
    document.getElementById('at-wrap').style.display    = 'none';
    document.getElementById('at-error').style.display   = 'none';
    Object.keys(grpAberto).forEach(k => delete grpAberto[k]);
    Object.keys(tanqAberto).forEach(k => delete tanqAberto[k]);
    Object.keys(tanqCarregado).forEach(k => delete tanqCarregado[k]);
    dadosGlobais = [];

    fetch('/faturamento-api-eficiencia-uep-tanques')
        .then(r => r.json())
        .then(res => {
            document.getElementById('at-loading').style.display = 'none';
            if (!res.success) {
                document.getElementById('at-error').style.display = 'block';
                document.getElementById('at-error').textContent   = res.error || 'Erro ao carregar';
                return;
            }
            dadosGlobais = res.data || [];
            if (!dadosGlobais.length) {
                document.getElementById('at-wrap').style.display = 'block';
                document.getElementById('at-tbody').innerHTML =
                    '<tr><td colspan="4" style="text-align:center;">Nenhum dado encontrado.</td></tr>';
                return;
            }
            renderizar(dadosGlobais);
        })
        .catch(e => {
            document.getElementById('at-loading').style.display = 'none';
            document.getElementById('at-error').style.display   = 'block';
            document.getElementById('at-error').textContent     = 'Erro de comunicação: ' + e.message;
        });
}

document.getElementById('btnAtualizar').addEventListener('click', carregar);
document.getElementById('btnExcel').addEventListener('click', exportarExcel);
carregar();
