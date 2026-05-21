/* ================================================================
   Programação de Pedidos — Faturamento
   ================================================================ */

let dadosGlobais   = [];
let ocupacaoCache  = null; // { tanques, diasUteis }
let painelCache    = null; // dados do /faturamento-api-painel

const MESES_PT = [
    'Janeiro','Fevereiro','Março','Abril','Maio','Junho',
    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'
];

document.addEventListener('DOMContentLoaded', () => {
    carregarDados();

    const btnOcupacao = document.getElementById('tabOcupacaoBtn');
    if (btnOcupacao) {
        btnOcupacao.addEventListener('shown.bs.tab', carregarOcupacao);
    }
});

/* ----------------------------------------------------------------
   Carregamento
---------------------------------------------------------------- */
function carregarDados() {
    setLoading(true);
    Promise.all([
        fetch('/faturamento-api-programacao').then(r => r.json()),
        fetch('/faturamento-api-painel').then(r => r.json()).catch(() => null),
    ])
        .then(([resProgr, resPainel]) => {
            if (!resProgr.success) throw new Error(resProgr.error || 'Erro ao carregar dados');
            dadosGlobais = resProgr.data || [];
            painelCache  = (resPainel && resPainel.sucesso) ? resPainel : null;
            renderizarTudo();
            document.getElementById('btnExportarCSV').disabled   = false;
            document.getElementById('btnExportarGeral').disabled = false;
        })
        .catch(err => {
            setLoading(false, true, err.message);
        });
}

function setLoading(show, erro = false, msg = '') {
    const html = erro
        ? `<div class="loading-overlay text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${msg}</div>`
        : `<div class="loading-overlay"><span class="spinner-border spinner-border-sm me-2"></span>Carregando dados...</div>`;
    if (show || erro) {
        document.getElementById('conteudoEmpresa').innerHTML  = html;
        document.getElementById('conteudoTipo').innerHTML     = `<div class="loading-overlay text-muted">Aguardando carregamento...</div>`;
        document.getElementById('conteudoOcupacao').innerHTML = `<div class="loading-overlay text-muted">Clique na aba para carregar...</div>`;
        ocupacaoCache = null;
        painelCache   = null;
    }
}

/* ----------------------------------------------------------------
   Orquestra renderização
---------------------------------------------------------------- */
function renderizarTudo() {
    const { agendas, empresas, tipos, agg, grandTotal } = processarDados(dadosGlobais);

    renderResumoCards(agendas, agg, grandTotal);
    renderTabelaEmpresa(agendas, empresas, agg, grandTotal);
    renderTabelaTipo(agendas, empresas, tipos, agg, grandTotal);

    if (ocupacaoCache) {
        renderTabelaOcupacao(ocupacaoCache.tanques, ocupacaoCache.diasUteis);
    }

    // Exibe cards
    document.getElementById('resumoCards').style.display = '';
    document.getElementById('resumoCards').style.removeProperty('display');
    document.getElementById('resumoCards').classList.remove('d-none');
}

/* ----------------------------------------------------------------
   Processa dados em estrutura pivot
---------------------------------------------------------------- */
function processarDados(dados) {
    const agendasSet  = new Set();
    const empresasSet = new Set();
    const tiposSet    = new Set();

    // agg['TODOS'][emprId][agenda] = valor
    // agg[tipo][emprId][agenda]    = valor
    const agg = { TODOS: {} };
    let grandTotal = 0;

    dados.forEach(row => {
        const agenda = row.AGENDA || 'SEM AGENDA';
        const empr   = String(row.EMPR_ID || '?');
        const tipo   = row.PROGRAMACAO ? String(row.PROGRAMACAO).trim() : 'Sem Tipo';
        const valor  = parseFloat(row.PDV_VALOR_PENDENTE || 0);

        agendasSet.add(agenda);
        empresasSet.add(empr);
        tiposSet.add(tipo);

        // TODOS
        if (!agg.TODOS[empr])          agg.TODOS[empr]         = {};
        agg.TODOS[empr][agenda]        = (agg.TODOS[empr][agenda] || 0) + valor;

        // Por tipo
        if (!agg[tipo])                agg[tipo]               = {};
        if (!agg[tipo][empr])          agg[tipo][empr]         = {};
        agg[tipo][empr][agenda]        = (agg[tipo][empr][agenda] || 0) + valor;

        grandTotal += valor;
    });

    // Ordena agendas: SEM AGENDA primeiro, depois cronológico
    const agendas = [...agendasSet].sort((a, b) => {
        if (a === 'SEM AGENDA' && b !== 'SEM AGENDA') return -1;
        if (b === 'SEM AGENDA' && a !== 'SEM AGENDA') return  1;
        return parsePtDate(a) - parsePtDate(b);
    });

    const empresas = [...empresasSet].sort((a, b) => Number(a) - Number(b));
    const tipos    = [...tiposSet].sort();

    return { agendas, empresas, tipos, agg, grandTotal };
}

/* ----------------------------------------------------------------
   Cards de resumo
---------------------------------------------------------------- */
function renderResumoCards(agendas, agg, grandTotal) {
    document.getElementById('cardTotal').textContent = fmt(grandTotal);

    let semAgenda = 0, mesAtual = 0, proxMes = 0;
    const hoje = new Date();
    const mm   = hoje.getMonth(); // 0-based

    agendas.forEach(ag => {
        const total = Object.values(agg.TODOS).reduce((s, emprAgg) => s + (emprAgg[ag] || 0), 0);
        if (ag === 'SEM AGENDA') {
            semAgenda = total;
        } else {
            const d = parsePtDate(ag);
            const agDate = new Date(d);
            if (agDate.getMonth() === mm) {
                mesAtual = total;
                document.getElementById('labelMesAtual').textContent = agendaLabel(ag);
            } else if (agDate.getMonth() === (mm + 1) % 12) {
                proxMes = total;
                document.getElementById('labelProxMes').textContent = agendaLabel(ag);
            }
        }
    });

    document.getElementById('cardSemAgenda').textContent = fmt(semAgenda);
    document.getElementById('cardMesAtual').textContent  = fmt(mesAtual);
    document.getElementById('cardProxMes').textContent   = fmt(proxMes);
}

/* ----------------------------------------------------------------
   Tabela 1 — Por Empresa
---------------------------------------------------------------- */
function renderTabelaEmpresa(agendas, empresas, agg) {
    const hoje           = new Date();
    const mmAtual        = hoje.getMonth();
    const mmProx         = (mmAtual + 1) % 12;
    const yyyyAtual      = hoje.getFullYear();
    const yyyyProx       = mmAtual === 11 ? yyyyAtual + 1 : yyyyAtual;
    const agendaMesAtual = '01/' + String(mmAtual + 1).padStart(2, '0') + '/' + yyyyAtual;
    const agendaProxMes  = '01/' + String(mmProx + 1).padStart(2, '0') + '/' + yyyyProx;
    const mesLabel       = MESES_PT[mmAtual];
    const proxLabel      = MESES_PT[mmProx];

    // Monta lookup meta/faturado do painel (valores Oracle: "1,234,567.89")
    const parseOra = v => parseFloat(String(v || 0).replace(/,/g, '')) || 0;
    const painelMap = {};
    if (painelCache && painelCache.dados) {
        painelCache.dados.forEach(row => {
            if (String(row.EMPR_ID).toUpperCase() === 'TOTAL') return;
            painelMap[String(row.EMPR_ID)] = {
                meta:     parseOra(row.META_FATURAMENTO),
                faturado: parseOra(row.FATURAMENTO),
            };
        });
    }

    let html = `<div class="pivot-wrap"><table class="pivot-table"><thead><tr>
        <th>Filial</th>
        <th>Meta Desafio Mês</th>
        <th>Faturado</th>
        <th>Valor da Carteira</th>
        <th>Lib. P/ ${mesLabel}</th>
        <th>Prog. P/ ${proxLabel}</th>
        <th>Sem Programação</th>
        <th>Falta/Sobra P/ ${mesLabel}</th>
    </tr></thead><tbody>`;

    let totMeta = 0, totFat = 0, totCart = 0, totLib = 0, totProx = 0, totSem = 0;

    empresas.forEach(empr => {
        const emprAgg  = agg.TODOS[empr] || {};
        const painel   = painelMap[empr]  || { meta: 0, faturado: 0 };
        const carteira   = agendas.reduce((s, ag) => s + (emprAgg[ag] || 0), 0);
        const libMes     = emprAgg[agendaMesAtual] || 0;
        const progProx   = emprAgg[agendaProxMes]  || 0;
        const semProg    = emprAgg['SEM AGENDA']    || 0;
        const faltaSobra = painel.faturado + libMes - painel.meta;

        totMeta += painel.meta;
        totFat  += painel.faturado;
        totCart += carteira;
        totLib  += libMes;
        totProx += progProx;
        totSem  += semProg;

        const corFalta = faltaSobra < 0 ? 'color:#e03131;' : '';
        html += `<tr>
            <td><strong>FL ${String(empr).padStart(2, '0')}</strong></td>
            <td class="val">R$&nbsp;${fmt2(painel.meta)}</td>
            <td class="val">R$&nbsp;${fmt2(painel.faturado)}</td>
            <td class="val">R$&nbsp;${fmt2(carteira)}</td>
            <td class="val">R$&nbsp;${fmt2(libMes)}</td>
            <td class="val">R$&nbsp;${fmt2(progProx)}</td>
            <td class="val">R$&nbsp;${fmt2(semProg)}</td>
            <td class="val" style="${corFalta}">${faltaSobra < 0 ? '-' : ''}R$&nbsp;${fmt2(Math.abs(faltaSobra))}</td>
        </tr>`;
    });

    const totFalta    = totFat + totLib - totMeta;
    const corTotFalta = totFalta < 0 ? 'color:#e03131;' : '';
    html += `<tr class="row-total">
        <td>TOTAL</td>
        <td class="val">R$&nbsp;${fmt2(totMeta)}</td>
        <td class="val">R$&nbsp;${fmt2(totFat)}</td>
        <td class="val">R$&nbsp;${fmt2(totCart)}</td>
        <td class="val">R$&nbsp;${fmt2(totLib)}</td>
        <td class="val">R$&nbsp;${fmt2(totProx)}</td>
        <td class="val">R$&nbsp;${fmt2(totSem)}</td>
        <td class="val" style="${corTotFalta}">${totFalta < 0 ? '-' : ''}R$&nbsp;${fmt2(Math.abs(totFalta))}</td>
    </tr></tbody></table></div>`;

    document.getElementById('conteudoEmpresa').innerHTML = html;
}

/* ----------------------------------------------------------------
   Tabela 2 — Por Tipo (Agendado / Data Firme / etc.)
---------------------------------------------------------------- */
function renderTabelaTipo(agendas, empresas, tipos, agg, grandTotal) {
    const totalPorAgenda = {};
    agendas.forEach(ag => {
        totalPorAgenda[ag] = Object.values(agg.TODOS).reduce((s, e) => s + (e[ag] || 0), 0);
    });

    let html = `<div class="pivot-wrap"><table class="pivot-table">`;

    // Cabeçalho
    html += `<thead><tr>
        <th style="min-width:80px;">Tipo / Empresa</th>`;
    agendas.forEach(ag => {
        html += `<th class="col-agenda">${agendaLabel(ag)}</th>`;
    });
    html += `<th>Total VALOR CARTEIRA</th><th>% CARTEIRA</th></tr></thead><tbody>`;

    tipos.forEach(tipo => {
        const tipoAgg = agg[tipo] || {};

        // Total do grupo para este tipo
        const grupoTotalPorAgenda = {};
        let grupoTotal = 0;
        agendas.forEach(ag => {
            grupoTotalPorAgenda[ag] = Object.values(tipoAgg).reduce((s, e) => s + (e[ag] || 0), 0);
            grupoTotal += grupoTotalPorAgenda[ag];
        });
        const grupoPct = grandTotal > 0 ? (grupoTotal / grandTotal * 100) : 0;

        // Linha cabeçalho do grupo
        html += `<tr class="row-group"><td colspan="${agendas.length + 3}">
            <i class="bi bi-tag-fill me-1"></i>${tipo}
        </td></tr>`;

        // Linhas de empresa dentro do grupo
        empresas.forEach(empr => {
            const emprAgg = tipoAgg[empr];
            if (!emprAgg) return; // empresa não tem registro neste tipo

            const rowTotal = agendas.reduce((s, ag) => s + (emprAgg[ag] || 0), 0);
            if (rowTotal === 0) return;
            const pct = grandTotal > 0 ? (rowTotal / grandTotal * 100) : 0;

            html += `<tr><td style="padding-left:28px;">${empr}</td>`;
            agendas.forEach(ag => {
                html += `<td class="val">${fmt(emprAgg[ag] || 0)}</td>`;
            });
            html += `<td class="val"><strong>${fmt(rowTotal)}</strong></td>`;
            html += `<td class="pct">${pct.toFixed(0)}%</td></tr>`;
        });

        // Subtotal do grupo
        html += `<tr class="row-group-total"><td style="padding-left:14px;">Subtotal ${tipo}</td>`;
        agendas.forEach(ag => {
            html += `<td class="val">${fmt(grupoTotalPorAgenda[ag] || 0)}</td>`;
        });
        html += `<td class="val"><strong>${fmt(grupoTotal)}</strong></td>`;
        html += `<td class="pct">${grupoPct.toFixed(0)}%</td></tr>`;
    });

    // Linha total geral
    html += `<tr class="row-total"><td>Total Geral</td>`;
    agendas.forEach(ag => {
        html += `<td class="val">${fmt(totalPorAgenda[ag] || 0)}</td>`;
    });
    html += `<td class="val">${fmt(grandTotal)}</td><td class="pct">100%</td></tr>`;

    html += `</tbody></table></div>`;

    document.getElementById('conteudoTipo').innerHTML = html;
}

/* ----------------------------------------------------------------
   Aba 3 — Taxa de Ocupação
---------------------------------------------------------------- */
function carregarOcupacao() {
    if (ocupacaoCache) {
        renderTabelaOcupacao(ocupacaoCache.tanques, ocupacaoCache.diasUteis);
        return;
    }
    document.getElementById('conteudoOcupacao').innerHTML =
        '<div class="loading-overlay"><span class="spinner-border spinner-border-sm me-2"></span>Carregando tanques...</div>';

    fetch('/faturamento-api-ocupacao')
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Erro ao carregar ocupação');
            ocupacaoCache = { tanques: res.tanques || [], diasUteis: res.diasUteis || [] };
            renderTabelaOcupacao(ocupacaoCache.tanques, ocupacaoCache.diasUteis);
        })
        .catch(err => {
            document.getElementById('conteudoOcupacao').innerHTML =
                `<div class="loading-overlay text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${err.message}</div>`;
        });
}

/* Configuração fixa: empresas e tanques da taxa de ocupação */
const EMPR_OCUP       = ['1','2','3','4','5','9','13','14','15'];
const TANQUES_DETALHE = [1, 2, 3, 12, 14, 23];
const TANQUES_COLCHAO = new Set([1, 2]);
const TANQUES_BOX     = new Set([3, 12, 14, 23]);

function renderTabelaOcupacao(tanques, diasUteisArr) {
    if (!tanques.length) {
        document.getElementById('conteudoOcupacao').innerHTML =
            '<div class="loading-overlay text-muted">Sem dados de tanques.</div>';
        return;
    }

    const hoje           = new Date();
    const mesAtualAgenda = '01/' + String(hoje.getMonth() + 1).padStart(2, '0') + '/' + hoje.getFullYear();
    const mesLabel       = MESES_PT[hoje.getMonth()];

    const diasUteisMap = {};
    diasUteisArr.forEach(r => { diasUteisMap[String(r.EMPR_ID)] = parseFloat(r.DIAS_UTEIS || 0); });

    const tanquesMap  = {};
    const tanqueNomes = {};
    tanques.forEach(t => {
        const empr = String(t.EMPR_ID);
        const cod  = Number(t.COD_TANQUE);
        if (!tanquesMap[empr]) tanquesMap[empr] = {};
        tanquesMap[empr][cod] = parseFloat(t.CAP_UEP_DIA || 0);
        if (!tanqueNomes[cod]) tanqueNomes[cod] = t.DESCRICAO || String(cod);
    });

    const carteiraMap = {};
    dadosGlobais.forEach(row => {
        if ((row.AGENDA || '') !== mesAtualAgenda) return;
        const empr = String(row.EMPR_ID);
        const cod  = Number(row.COD_TANQUE);
        if (!cod) return;
        if (!carteiraMap[empr]) carteiraMap[empr] = {};
        carteiraMap[empr][cod] = (carteiraMap[empr][cod] || 0) + parseFloat(row.UEP || 0);
    });

    const nT = TANQUES_DETALHE.length;

    const totCap  = {};
    const totCart = {};
    TANQUES_DETALHE.forEach(c => { totCap[c] = 0; totCart[c] = 0; });

    let rColchaoCap = 0, rColchaoCart = 0, rColchaoCapDia = 0;
    let rBoxCap     = 0, rBoxCart     = 0, rBoxCapDia     = 0;

    let html = '<div class="pivot-wrap"><table class="pivot-table"><thead>';

    /* Linha 1 — MÊS | TAXA DE OCUPAÇÃO | RESUMO | OCUPAÇÃO */
    html += `<tr>`;
    html += `<th colspan="2" style="background:#212529;color:#fff;text-align:center;">MÊS</th>`;
    html += `<th colspan="${nT * 3}" style="background:#1971c2;color:#fff;font-weight:700;text-align:center;letter-spacing:.5px;">TAXA DE OCUPAÇÃO — ${mesLabel}</th>`;
    html += `<th colspan="2" style="background:#1864ab;color:#fff;text-align:center;">RESUMO</th>`;
    html += `<th colspan="2" style="background:#2f9e44;color:#fff;text-align:center;">OCUPAÇÃO</th>`;
    html += `</tr>`;

    /* Linha 2 — TANQUE | nomes dos tanques | sub-colunas resumo (rowspan=2) */
    html += `<tr>`;
    html += `<th colspan="2" style="background:#343a40;color:#fff;text-align:center;">TANQUE</th>`;
    TANQUES_DETALHE.forEach(cod => {
        html += `<th colspan="3" class="col-agenda" style="text-align:center;">${cod} — ${tanqueNomes[cod] || cod}</th>`;
    });
    html += `<th rowspan="2" style="background:#339af0;color:#fff;text-align:center;">Colchão<br><small>%</small></th>`;
    html += `<th rowspan="2" style="background:#339af0;color:#fff;text-align:center;">Box<br><small>%</small></th>`;
    html += `<th rowspan="2" style="background:#40c057;color:#fff;text-align:center;">Colchão<br><small>Dias</small></th>`;
    html += `<th rowspan="2" style="background:#40c057;color:#fff;text-align:center;">Box<br><small>Dias</small></th>`;
    html += `</tr>`;

    /* Linha 3 — Dias Úteis | Filial | Cap|Cart|% por tanque */
    html += `<tr>`;
    html += `<th style="min-width:65px;text-align:center;">Dias<br>Úteis</th>`;
    html += `<th style="min-width:55px;text-align:center;">Filial</th>`;
    TANQUES_DETALHE.forEach(() => {
        html += `<th>Capacidade</th><th>Carteira</th><th>%</th>`;
    });
    html += `</tr></thead><tbody>`;

    EMPR_OCUP.forEach(empr => {
        const du   = diasUteisMap[empr] || 0;
        const tMap = tanquesMap[empr]   || {};
        const cMap = carteiraMap[empr]  || {};

        html += `<tr>`;
        html += `<td class="val">${du}</td>`;
        html += `<td class="val"><strong>${empr}</strong></td>`;

        TANQUES_DETALHE.forEach(cod => {
            const temReg = cod in tMap;
            const cap    = (tMap[cod] || 0) * du;
            const cart   = cMap[cod] || 0;
            const pct    = cap > 0 ? (cart / cap * 100) : 0;

            totCap[cod]  += cap;
            totCart[cod] += cart;

            if (!temReg) {
                html += `<td class="val text-muted">-</td><td class="val text-muted">-</td><td class="val text-muted">-</td>`;
            } else {
                const e = pct >= 100 ? 'background:#e03131;color:#fff;font-weight:700;' : '';
                html += `<td class="val">${fmtUep(cap)}</td>`;
                html += `<td class="val">${fmtUep(cart)}</td>`;
                html += `<td class="val" style="${e}">${pct.toFixed(0)}%</td>`;
            }
        });

        /* Colunas RESUMO / OCUPAÇÃO na mesma linha */
        let colchaoCap = 0, colchaoCapDia = 0;
        TANQUES_COLCHAO.forEach(cod => {
            const cd = tMap[cod] || 0;
            colchaoCap    += cd * du;
            colchaoCapDia += cd;
        });
        const colchaoCart = [...TANQUES_COLCHAO].reduce((s, c) => s + (cMap[c] || 0), 0);
        const colchaoPct  = colchaoCap    > 0 ? (colchaoCart / colchaoCap    * 100) : 0;
        const colchaoDias = colchaoCapDia > 0 ? (colchaoCart / colchaoCapDia) : 0;

        let boxCap = 0, boxCapDia = 0;
        TANQUES_BOX.forEach(cod => {
            const cd = tMap[cod] || 0;
            boxCap    += cd * du;
            boxCapDia += cd;
        });
        const boxCart = [...TANQUES_BOX].reduce((s, c) => s + (cMap[c] || 0), 0);
        const boxPct  = boxCap    > 0 ? (boxCart / boxCap    * 100) : 0;
        const boxDias = boxCapDia > 0 ? (boxCart / boxCapDia) : 0;

        rColchaoCap += colchaoCap;  rColchaoCart += colchaoCart;  rColchaoCapDia += colchaoCapDia;
        rBoxCap     += boxCap;      rBoxCart     += boxCart;      rBoxCapDia     += boxCapDia;

        const eC = colchaoPct >= 100 ? 'background:#e03131;color:#fff;font-weight:700;' : '';
        const eB = boxPct     >= 100 ? 'background:#e03131;color:#fff;font-weight:700;' : '';

        html += `<td class="val" style="${eC}">${colchaoPct.toFixed(0)}%</td>`;
        html += `<td class="val" style="${eB}">${boxPct.toFixed(0)}%</td>`;
        html += `<td class="val">${fmtUep(colchaoDias)}</td>`;
        html += `<td class="val">${fmtUep(boxDias)}</td>`;
        html += `</tr>`;
    });

    /* Linha TOTAL */
    const rTCP = rColchaoCap    > 0 ? (rColchaoCart / rColchaoCap    * 100) : 0;
    const rTBP = rBoxCap        > 0 ? (rBoxCart     / rBoxCap        * 100) : 0;
    const rTCD = rColchaoCapDia > 0 ? (rColchaoCart / rColchaoCapDia) : 0;
    const rTBD = rBoxCapDia     > 0 ? (rBoxCart     / rBoxCapDia)     : 0;

    html += `<tr class="row-total"><td colspan="2">TOTAL</td>`;
    TANQUES_DETALHE.forEach(cod => {
        const pct = totCap[cod] > 0 ? (totCart[cod] / totCap[cod] * 100) : 0;
        const e   = pct >= 100 ? 'background:#e03131;color:#fff;font-weight:700;' : '';
        html += `<td class="val">${fmtUep(totCap[cod])}</td>`;
        html += `<td class="val">${fmtUep(totCart[cod])}</td>`;
        html += `<td class="val" style="${e}">${pct.toFixed(0)}%</td>`;
    });
    html += `<td class="val">${rTCP.toFixed(0)}%</td>`;
    html += `<td class="val">${rTBP.toFixed(0)}%</td>`;
    html += `<td class="val">${fmtUep(rTCD)}</td>`;
    html += `<td class="val">${fmtUep(rTBD)}</td>`;
    html += `</tr></tbody></table></div>`;

    document.getElementById('conteudoOcupacao').innerHTML = html;
}

function fmtUep(valor) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 1,
    }).format(valor || 0);
}

/* ----------------------------------------------------------------
   Exportar CSV — despacha para a aba ativa
---------------------------------------------------------------- */
function exportarCSV() {
    if (!dadosGlobais.length) return;
    const activeBtn = document.querySelector('#pivotTabs .nav-link.active');
    const target    = activeBtn ? activeBtn.getAttribute('data-bs-target') : '#tabEmpresa';
    if (target === '#tabOcupacao') { exportarCSVOcupacao(); return; }
    if (target === '#tabTipo')     { exportarCSVTipo();     return; }
    exportarCSVEmpresa();
}

function exportarCSVEmpresa() {
    const { agendas, empresas, agg } = processarDados(dadosGlobais);

    const hoje           = new Date();
    const mmAtual        = hoje.getMonth();
    const mmProx         = (mmAtual + 1) % 12;
    const yyyyAtual      = hoje.getFullYear();
    const yyyyProx       = mmAtual === 11 ? yyyyAtual + 1 : yyyyAtual;
    const agendaMesAtual = '01/' + String(mmAtual + 1).padStart(2, '0') + '/' + yyyyAtual;
    const agendaProxMes  = '01/' + String(mmProx + 1).padStart(2, '0') + '/' + yyyyProx;
    const mesLabel       = MESES_PT[mmAtual];
    const proxLabel      = MESES_PT[mmProx];

    const parseOra = v => parseFloat(String(v || 0).replace(/,/g, '')) || 0;
    const painelMap = {};
    if (painelCache && painelCache.dados) {
        painelCache.dados.forEach(row => {
            if (String(row.EMPR_ID).toUpperCase() === 'TOTAL') return;
            painelMap[String(row.EMPR_ID)] = {
                meta:     parseOra(row.META_FATURAMENTO),
                faturado: parseOra(row.FATURAMENTO),
            };
        });
    }

    const cab = ['Filial', 'Meta Desafio Mes', 'Faturado', 'Valor da Carteira',
        `Lib. P/ ${mesLabel}`, `Prog. P/ ${proxLabel}`, 'Sem Programacao', `Falta/Sobra P/ ${mesLabel}`];
    const linhas = [cab.join(';')];

    let totMeta = 0, totFat = 0, totCart = 0, totLib = 0, totProx = 0, totSem = 0;

    empresas.forEach(empr => {
        const emprAgg  = agg.TODOS[empr] || {};
        const painel   = painelMap[empr]  || { meta: 0, faturado: 0 };
        const carteira   = agendas.reduce((s, ag) => s + (emprAgg[ag] || 0), 0);
        const libMes     = emprAgg[agendaMesAtual] || 0;
        const progProx   = emprAgg[agendaProxMes]  || 0;
        const semProg    = emprAgg['SEM AGENDA']    || 0;
        const faltaSobra = painel.faturado + libMes - painel.meta;

        totMeta += painel.meta; totFat  += painel.faturado; totCart += carteira;
        totLib  += libMes;      totProx += progProx;         totSem  += semProg;

        linhas.push([
            `FL ${String(empr).padStart(2, '0')}`,
            fmtNum(painel.meta), fmtNum(painel.faturado), fmtNum(carteira),
            fmtNum(libMes), fmtNum(progProx), fmtNum(semProg), fmtNum(faltaSobra),
        ].join(';'));
    });

    const totFalta = totFat + totLib - totMeta;
    linhas.push(['TOTAL',
        fmtNum(totMeta), fmtNum(totFat), fmtNum(totCart),
        fmtNum(totLib), fmtNum(totProx), fmtNum(totSem), fmtNum(totFalta),
    ].join(';'));

    baixarCsv(linhas, 'programacao_empresa');
}

function exportarCSVTipo() {
    const { agendas, empresas, tipos, agg, grandTotal } = processarDados(dadosGlobais);
    const linhas = [['Tipo', 'Empresa', ...agendas.map(agendaLabel), 'Total VALOR CARTEIRA', '% CARTEIRA'].join(';')];

    tipos.forEach(tipo => {
        const tipoAgg = agg[tipo] || {};

        empresas.forEach(empr => {
            const emprAgg  = tipoAgg[empr];
            if (!emprAgg) return;
            const rowTotal = agendas.reduce((s, ag) => s + (emprAgg[ag] || 0), 0);
            if (rowTotal === 0) return;
            const pct = grandTotal > 0 ? (rowTotal / grandTotal * 100).toFixed(0) + '%' : '0%';
            linhas.push([tipo, empr, ...agendas.map(ag => fmtNum(emprAgg[ag] || 0)), fmtNum(rowTotal), pct].join(';'));
        });

        const subTotalPorAgenda = agendas.map(ag =>
            Object.values(tipoAgg).reduce((s, e) => s + (e[ag] || 0), 0));
        const subTotal = subTotalPorAgenda.reduce((s, v) => s + v, 0);
        const subPct   = grandTotal > 0 ? (subTotal / grandTotal * 100).toFixed(0) + '%' : '0%';
        linhas.push([`Subtotal ${tipo}`, '', ...subTotalPorAgenda.map(fmtNum), fmtNum(subTotal), subPct].join(';'));
    });

    linhas.push(['Total Geral', '',
        ...agendas.map(ag => fmtNum(Object.values(agg.TODOS).reduce((s, e) => s + (e[ag] || 0), 0))),
        fmtNum(grandTotal), '100%'].join(';'));

    baixarCsv(linhas, 'programacao_tipo');
}

function exportarCSVOcupacao() {
    if (!ocupacaoCache) { alert('Abra a aba Taxa de Ocupação antes de exportar.'); return; }

    const hoje           = new Date();
    const mesAtualAgenda = '01/' + String(hoje.getMonth() + 1).padStart(2, '0') + '/' + hoje.getFullYear();
    const mesLabel       = MESES_PT[hoje.getMonth()].toLowerCase();

    const diasUteisMap = {};
    ocupacaoCache.diasUteis.forEach(r => { diasUteisMap[String(r.EMPR_ID)] = parseFloat(r.DIAS_UTEIS || 0); });

    const tanquesMap  = {};
    const tanqueNomes = {};
    ocupacaoCache.tanques.forEach(t => {
        const empr = String(t.EMPR_ID);
        const cod  = Number(t.COD_TANQUE);
        if (!tanquesMap[empr]) tanquesMap[empr] = {};
        tanquesMap[empr][cod] = parseFloat(t.CAP_UEP_DIA || 0);
        if (!tanqueNomes[cod]) tanqueNomes[cod] = t.DESCRICAO || String(cod);
    });

    const carteiraMap = {};
    dadosGlobais.forEach(row => {
        if ((row.AGENDA || '') !== mesAtualAgenda) return;
        const empr = String(row.EMPR_ID);
        const cod  = Number(row.COD_TANQUE);
        if (!cod) return;
        if (!carteiraMap[empr]) carteiraMap[empr] = {};
        carteiraMap[empr][cod] = (carteiraMap[empr][cod] || 0) + parseFloat(row.UEP || 0);
    });

    const tanqueCols = [];
    TANQUES_DETALHE.forEach(cod => {
        const n = tanqueNomes[cod] || String(cod);
        tanqueCols.push(`T${cod} - ${n} - Capacidade`, `T${cod} - ${n} - Carteira`, `T${cod} - ${n} - %`);
    });
    const linhas = [['Filial', 'Dias Uteis', ...tanqueCols,
        'Colchao %', 'Box %', 'Colchao Dias', 'Box Dias'].join(';')];

    const totCap = {}, totCart = {};
    TANQUES_DETALHE.forEach(c => { totCap[c] = 0; totCart[c] = 0; });
    let rColchaoCap = 0, rColchaoCart = 0, rColchaoCapDia = 0;
    let rBoxCap     = 0, rBoxCart     = 0, rBoxCapDia     = 0;

    EMPR_OCUP.forEach(empr => {
        const du   = diasUteisMap[empr] || 0;
        const tMap = tanquesMap[empr]   || {};
        const cMap = carteiraMap[empr]  || {};
        const cols = [empr, String(du)];

        TANQUES_DETALHE.forEach(cod => {
            const temReg = cod in tMap;
            const cap    = (tMap[cod] || 0) * du;
            const cart   = cMap[cod] || 0;
            const pct    = cap > 0 ? (cart / cap * 100) : 0;
            totCap[cod]  += cap;
            totCart[cod] += cart;
            if (!temReg) { cols.push('-', '-', '-'); }
            else { cols.push(fmtNum(cap), fmtNum(cart), pct.toFixed(0) + '%'); }
        });

        let colchaoCap = 0, colchaoCapDia = 0;
        TANQUES_COLCHAO.forEach(cod => { colchaoCap += (tMap[cod] || 0) * du; colchaoCapDia += tMap[cod] || 0; });
        const colchaoCart = [...TANQUES_COLCHAO].reduce((s, c) => s + (cMap[c] || 0), 0);
        const colchaoPct  = colchaoCap    > 0 ? (colchaoCart / colchaoCap    * 100) : 0;
        const colchaoDias = colchaoCapDia > 0 ? (colchaoCart / colchaoCapDia) : 0;

        let boxCap = 0, boxCapDia = 0;
        TANQUES_BOX.forEach(cod => { boxCap += (tMap[cod] || 0) * du; boxCapDia += tMap[cod] || 0; });
        const boxCart = [...TANQUES_BOX].reduce((s, c) => s + (cMap[c] || 0), 0);
        const boxPct  = boxCap    > 0 ? (boxCart / boxCap    * 100) : 0;
        const boxDias = boxCapDia > 0 ? (boxCart / boxCapDia) : 0;

        rColchaoCap += colchaoCap; rColchaoCart += colchaoCart; rColchaoCapDia += colchaoCapDia;
        rBoxCap     += boxCap;     rBoxCart     += boxCart;     rBoxCapDia     += boxCapDia;

        cols.push(colchaoPct.toFixed(0) + '%', boxPct.toFixed(0) + '%',
            fmtNum(colchaoDias), fmtNum(boxDias));
        linhas.push(cols.join(';'));
    });

    const rTCP = rColchaoCap    > 0 ? (rColchaoCart / rColchaoCap    * 100) : 0;
    const rTBP = rBoxCap        > 0 ? (rBoxCart     / rBoxCap        * 100) : 0;
    const rTCD = rColchaoCapDia > 0 ? (rColchaoCart / rColchaoCapDia) : 0;
    const rTBD = rBoxCapDia     > 0 ? (rBoxCart     / rBoxCapDia)     : 0;
    const totCols = ['TOTAL', ''];
    TANQUES_DETALHE.forEach(cod => {
        const pct = totCap[cod] > 0 ? (totCart[cod] / totCap[cod] * 100) : 0;
        totCols.push(fmtNum(totCap[cod]), fmtNum(totCart[cod]), pct.toFixed(0) + '%');
    });
    totCols.push(rTCP.toFixed(0) + '%', rTBP.toFixed(0) + '%', fmtNum(rTCD), fmtNum(rTBD));
    linhas.push(totCols.join(';'));

    baixarCsv(linhas, `programacao_ocupacao_${mesLabel}`);
}

function baixarCsv(linhas, nome) {
    const blob = new Blob(['﻿' + linhas.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = `${nome}_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
}

/* ----------------------------------------------------------------
   Exportar Geral — todos os campos da query original
---------------------------------------------------------------- */
function exportarGeral() {
    if (!dadosGlobais.length) return;

    const colunas = [
        'DATA','TIPO_OPER','EMPR_ID','EMPRESA','DIVISAO_VENDA','CLIENTE','LINHA_PRODUTO',
        'COD_CLIENTE','TIPO_CLIENTE','CIDADE','UF','PAIS','COD_TAB_VEN','TAB_VENDA',
        'SEGMENTO_MERCADO','COD_REP','REPRESENTANTE','TIPO_REPRESENTANTE','PDV_NRO_PEDIDO',
        'PDV_POSICAO','SIT_PDV_FIN','SIT_PDV_COM','PDV_SITUACAO','COD_ITEM','ID_CONFIG',
        'ITEM','MASCARA','DATA_CARGA','EM_CARGA','PDV_VALOR_LIQUIDO','PDV_QTDE_VEND_MENOS_CANC',
        'PDV_DATA_ENTREGA','PDV_VALOR_PENDENTE','PDV_VLR_PEND_CIPI','PDV_QTDE_PENDENTE',
        'PDV_DIAS_ATRASO','DT_ENTREGA_CLI','PDV_VALOR_LIQ_IPI','PDV_VALOR_PEND_LIBERADO',
        'PDV_VALOR_PEND_BLOQUEADO','PDV_DIAS_ENTRADA','PDV_DATA_FATURAMENTO','STATUS_CARGA',
        'PDV_CARGA','ATENDIDO','OBS','OBS_NFS','OBS_ETIQUETA','DT_EMIS','UEP',
        'COD_TANQUE','DESC_TANQUE','PROGRAMACAO','DATA_FORMATADA','AGENDA'
    ];

    const linhas = [colunas.join(';')];

    dadosGlobais.forEach(row => {
        const linha = colunas.map(col => {
            const val = row[col];
            return celulaCsv(val);
        });
        linhas.push(linha.join(';'));
    });

    const blob = new Blob(['﻿' + linhas.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = `programacao_pedidos_geral_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

/* ----------------------------------------------------------------
   Utilitários
---------------------------------------------------------------- */
/* Mapa de meses Oracle (inglês + português) → número */
const MESES_ORACLE = {
    JAN:'01', FEB:'02', FEV:'02', MAR:'03', APR:'04', ABR:'04',
    MAY:'05', MAI:'05', JUN:'06', JUL:'07', AUG:'08', AGO:'08',
    SEP:'09', SET:'09', OCT:'10', OUT:'10', NOV:'11', DEC:'12', DEZ:'12'
};

/* Converte data Oracle (DD-MON-YY, DD/MON/YY, DD-MON-YYYY) → DD/MM/YYYY.
   Retorna null se não for esse formato. */
function oracleDateParaBR(str) {
    const m = str.match(/^(\d{1,2})[-\/]([A-Za-z]{3})[-\/](\d{2,4})$/);
    if (!m) return null;
    const dia = m[1].padStart(2, '0');
    const mes = MESES_ORACLE[m[2].toUpperCase()];
    if (!mes) return null;
    const ano = m[3].length === 2 ? '20' + m[3] : m[3];
    return `${dia}/${mes}/${ano}`;
}

/* Formata célula para CSV pt-BR:
   - Número → vírgula decimal, sem aspas (somável no Excel)
   - Data Oracle (DD-MON-YY / DD/MON/YY) → DD/MM/YYYY entre aspas
   - Texto → entre aspas com " escapadas */
function celulaCsv(val) {
    if (val === null || val === undefined || val === '') return '';

    if (typeof val === 'number') {
        if (isNaN(val)) return '';
        return val.toFixed(10).replace(/\.?0+$/, '').replace('.', ',');
    }

    const str = String(val).trim();

    // Número puro
    if (/^-?\d+(\.\d+)?$/.test(str)) {
        return parseFloat(str).toFixed(10).replace(/\.?0+$/, '').replace('.', ',');
    }

    // Data no formato Oracle (DD-MON-YY, DD/MON/YY)
    const dataBR = oracleDateParaBR(str);
    if (dataBR) return '"' + dataBR + '"';

    return '"' + str.replace(/"/g, '""') + '"';
}

function agendaLabel(agenda) {
    if (!agenda || agenda === 'SEM AGENDA') return 'Sem Programação';
    const parts = agenda.split('/');
    if (parts.length !== 3) return agenda;
    const mes = parseInt(parts[1], 10) - 1;
    return MESES_PT[mes] || agenda;
}

function parsePtDate(str) {
    if (!str || str === 'SEM AGENDA') return Infinity;
    const [d, m, y] = str.split('/');
    return new Date(y, m - 1, d).getTime();
}

function fmt(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency', currency: 'BRL',
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }).format(valor || 0);
}

function fmt2(valor) {
    return Math.abs(valor || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}

function fmtNum(valor) {
    return String(Math.round(valor || 0)).replace('.', ',');
}
