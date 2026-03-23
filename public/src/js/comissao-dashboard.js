/**
 * Dashboard Comissão - JavaScript
 */

let dadosDashboard = null; // Cache dos dados carregados

document.addEventListener('DOMContentLoaded', function() {
    definirDatasPadrao();
    carregarFiliais();
});

function definirDatasPadrao() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    document.getElementById('filtroDataInicio').value = formatarDataInput(primeiroDia);
    document.getElementById('filtroDataFim').value = formatarDataInput(hoje);
}

function formatarDataInput(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function carregarFiliais() {
    const select = document.getElementById('filtroFilial');
    select.innerHTML = '<option value="">Carregando filiais...</option>';
    
    fetch('/comissao-api-filiais', { credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error('Erro'); return r.json(); })
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Selecione a Filial</option>';
                data.data.forEach(f => {
                    const nome = f.NOME_FANTASIA || f.RAZAO_SOCIAL;
                    select.innerHTML += `<option value="${f.ID}">${f.CODIGO} - ${nome}</option>`;
                });
                const sessaoId = document.getElementById('sessaoEmpresaId')?.value;
                if (sessaoId) {
                    select.value = sessaoId;
                    carregarDashboard();
                }
            }
        })
        .catch(() => { select.innerHTML = '<option value="">Erro ao carregar</option>'; });
}

function atualizarFiltrosPorFilial() {
    // Nada extra necessário - dashboard usa apenas filial + datas
}

function carregarDashboard() {
    const emprId = document.getElementById('filtroFilial').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;

    if (!emprId) {
        alert('Selecione uma filial antes de filtrar.');
        return;
    }

    // Loading nos cards
    ['totalFuncionarios', 'totalComissao', 'totalComFalta', 'totalCentros'].forEach(id => {
        document.getElementById(id).textContent = '...';
    });
    document.getElementById('tabelaCentrosBody').innerHTML = '<tr><td colspan="6" class="comissao-loading">Carregando...</td></tr>';
    document.getElementById('tabelaFaltasBody').innerHTML = '<tr><td colspan="6" class="comissao-loading">Carregando...</td></tr>';
    document.getElementById('tabelaRankingBody').innerHTML = '<tr><td colspan="5" class="comissao-loading">Carregando...</td></tr>';
    document.getElementById('tabelaFuncionariosBody').innerHTML = '<tr><td colspan="8" class="comissao-loading">Carregando...</td></tr>';

    const params = new URLSearchParams({ emprId, dataInicio, dataFim });

    fetch(`/comissao-api-dashboard-completo?${params.toString()}`, { credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error('Erro na requisição'); return r.json(); })
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Erro ao carregar dados');
            dadosDashboard = data;
            renderizarCards(data.cards);
            renderizarTabelaCentros(data.comissao_por_centro);
            renderizarTabelaFaltas(data.funcionarios_com_falta);
            renderizarRanking(data.ranking);
            renderizarTabelaFuncionarios(data.funcionarios);
            popularFiltroCentroRanking(data.comissao_por_centro);
        })
        .catch(error => {
            console.error('Erro dashboard:', error);
            alert('Erro ao carregar dashboard: ' + error.message);
        });
}

// ========== CARDS ==========
function renderizarCards(cards) {
    document.getElementById('totalFuncionarios').textContent = formatarNumero(cards.total_funcionarios);
    document.getElementById('totalComissao').textContent = formatarMoeda(cards.total_comissao);
    document.getElementById('totalComFalta').textContent = formatarNumero(cards.funcionarios_com_falta);
    document.getElementById('totalCentros').textContent = formatarNumero(cards.total_centros);
}

// ========== COMISSÃO POR CENTRO ==========
function renderizarTabelaCentros(dados) {
    const tbody = document.getElementById('tabelaCentrosBody');
    const tfoot = document.getElementById('tabelaCentrosFoot');

    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum dado encontrado</td></tr>';
        tfoot.style.display = 'none';
        return;
    }

    const totalComissao = dados.reduce((s, c) => s + (c.TOTAL_COMISSAO || 0), 0);
    let totalFunc = 0, totalPontos = 0, totalFaltas = 0;

    let html = '';
    dados.forEach(centro => {
        totalFunc += centro.TOTAL_FUNCIONARIOS;
        totalPontos += centro.TOTAL_PONTOS;
        totalFaltas += centro.FUNCIONARIOS_COM_FALTA;
        const pct = totalComissao > 0 ? ((centro.TOTAL_COMISSAO / totalComissao) * 100).toFixed(1) : 0;

        html += `
            <tr>
                <td><strong>${centro.CENTRO_TRABALHO}</strong></td>
                <td class="text-center">${centro.TOTAL_FUNCIONARIOS}</td>
                <td class="text-end">${formatarNumero(centro.TOTAL_PONTOS, 2)}</td>
                <td class="text-end"><strong>${formatarMoeda(centro.TOTAL_COMISSAO)}</strong></td>
                <td class="text-center">${centro.FUNCIONARIOS_COM_FALTA > 0 ? '<span class="badge-falta">' + centro.FUNCIONARIOS_COM_FALTA + '</span>' : '0'}</td>
                <td>
                    <div class="progress-container">
                        <div class="progress-bar-custom">
                            <div class="progress-fill ${getProgressClass(pct)}" style="width: ${pct}%"></div>
                        </div>
                        <span class="progress-label">${pct}%</span>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;

    // Footer totais
    document.getElementById('centroTotalFunc').textContent = totalFunc;
    document.getElementById('centroTotalPontos').textContent = formatarNumero(totalPontos, 2);
    document.getElementById('centroTotalComissao').textContent = formatarMoeda(totalComissao);
    document.getElementById('centroTotalFaltas').textContent = totalFaltas;
    tfoot.style.display = '';
}

// ========== FUNCIONÁRIOS COM FALTA ==========
function renderizarTabelaFaltas(dados) {
    const tbody = document.getElementById('tabelaFaltasBody');

    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="color:#999;">Nenhum funcionário com falta no período</td></tr>';
        return;
    }

    let html = '';
    dados.forEach(func => {
        html += `
            <tr>
                <td>${func.COD_FUNC}</td>
                <td><strong>${func.NOME_FUNC}</strong></td>
                <td>${func.CENTRO_TRABALHO}</td>
                <td class="text-center"><span class="badge-falta">${func.DIAS_COM_FALTA}</span></td>
                <td class="text-end">${formatarNumero(func.TOTAL_PONTOS, 2)}</td>
                <td class="text-end">${formatarMoeda(func.VALOR_COMISSAO)}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// ========== RANKING POR CENTRO ==========
function popularFiltroCentroRanking(centros) {
    const select = document.getElementById('filtroRankingCentro');
    select.innerHTML = '<option value="">Todos os Centros</option>';
    if (centros) {
        centros.forEach(c => {
            select.innerHTML += `<option value="${c.CENTRO_TRABALHO}">${c.CENTRO_TRABALHO}</option>`;
        });
    }
}

function filtrarRankingPorCentro() {
    if (!dadosDashboard) return;
    const centroSelecionado = document.getElementById('filtroRankingCentro').value;
    let dados = dadosDashboard.ranking;
    if (centroSelecionado) {
        dados = dados.filter(f => f.CENTRO_TRABALHO === centroSelecionado);
    }
    renderizarRanking(dados);
}

function renderizarRanking(dados) {
    const tbody = document.getElementById('tabelaRankingBody');

    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="color:#999;">Nenhum funcionário com comissão</td></tr>';
        return;
    }

    let html = '';
    dados.forEach((func, idx) => {
        const pos = idx + 1;
        let medalha = '';
        if (pos === 1) medalha = '<span class="ranking-badge gold">1</span>';
        else if (pos === 2) medalha = '<span class="ranking-badge silver">2</span>';
        else if (pos === 3) medalha = '<span class="ranking-badge bronze">3</span>';
        else medalha = `<span class="ranking-badge normal">${pos}</span>`;

        html += `
            <tr>
                <td class="text-center">${medalha}</td>
                <td>
                    <strong>${func.NOME_FUNC}</strong>
                    <br><small style="color:#888;">Cód: ${func.COD_FUNC}</small>
                </td>
                <td>${func.CENTRO_TRABALHO}</td>
                <td class="text-end">${formatarNumero(func.TOTAL_PONTOS, 2)}</td>
                <td class="text-end"><strong style="color:#16a34a;">${formatarMoeda(func.VALOR_COMISSAO)}</strong></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// ========== TABELA TODOS FUNCIONÁRIOS ==========
function renderizarTabelaFuncionarios(dados) {
    const tbody = document.getElementById('tabelaFuncionariosBody');

    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhum dado encontrado</td></tr>';
        return;
    }

    let html = '';
    dados.forEach(func => {
        const temFalta = func.DIAS_COM_FALTA > 0;
        const statusBadge = temFalta
            ? '<span class="badge-falta-status">Com Falta</span>'
            : '<span class="badge-ok-status">OK</span>';

        html += `
            <tr>
                <td>${func.COD_FUNC}</td>
                <td><strong>${func.NOME_FUNC}</strong></td>
                <td>${func.CENTRO_TRABALHO}</td>
                <td class="text-center">${func.DIAS_TRABALHADOS}</td>
                <td class="text-center">${temFalta ? '<span class="badge-falta">' + func.DIAS_COM_FALTA + '</span>' : '0'}</td>
                <td class="text-end">${formatarNumero(func.TOTAL_PONTOS, 2)}</td>
                <td class="text-end"><strong>${formatarMoeda(func.VALOR_COMISSAO)}</strong></td>
                <td class="text-center">${statusBadge}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

// ========== EXPORTAR EXCEL ==========
function exportarExcel() {
    if (!dadosDashboard || !dadosDashboard.funcionarios) {
        alert('Carregue os dados antes de exportar.');
        return;
    }

    let csv = '\uFEFF'; // BOM UTF-8
    csv += 'DASHBOARD COMISSÃO\n\n';
    csv += 'Código;Funcionário;Centro de Trabalho;Dias Trab.;Dias Falta;Total Pontos;Valor Comissão\n';
    
    dadosDashboard.funcionarios.forEach(f => {
        csv += `"${f.COD_FUNC}";"${f.NOME_FUNC}";"${f.CENTRO_TRABALHO}";${f.DIAS_TRABALHADOS};${f.DIAS_COM_FALTA};${f.TOTAL_PONTOS};${f.VALOR_COMISSAO}\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Dashboard_Comissao_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

// ========== UTILITÁRIOS ==========
function formatarNumero(valor, casas = 0) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }).format(valor || 0);
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor || 0);
}

function getProgressClass(pct) {
    if (pct >= 40) return 'progress-high';
    if (pct >= 20) return 'progress-mid';
    return 'progress-low';
}
