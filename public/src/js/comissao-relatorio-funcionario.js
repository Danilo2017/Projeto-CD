/**
 * Comissão Relatório por Funcionário - JavaScript
 */

let chartEvolucao = null;
let dataTableDiario = null;
let dataTableApontamentos = null;
let dataTableComissoes = null;

// Dados globais para o comprovante
let dadosRelatorio = {
    funcionario: null,
    resumo: null,
    diario: []
};

/**
 * Mostra overlay de loading na tela
 */
function mostrarLoading(mensagem = 'Gerando relatório...') {
    // Remove loading existente se houver
    esconderLoading();
    
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay-fullscreen';
    // Adicionando estilos inline para garantir funcionamento
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:99999;';
    overlay.innerHTML = `
        <div style="background:white;padding:30px 50px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.3);text-align:center;">
            <div style="width:60px;height:60px;border:5px solid #e9ecef;border-top:5px solid #0d6efd;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 15px;"></div>
            <p style="font-size:16px;font-weight:600;color:#495057;margin:0;">${mensagem}</p>
            <p style="font-size:13px;color:#6c757d;margin-top:5px;">Por favor, aguarde...</p>
        </div>
        <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
    `;
    document.body.appendChild(overlay);
}

/**
 * Esconde overlay de loading
 */
function esconderLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Definir datas padrão (mês atual)
    definirDatasPadrao();
    
    // Carregar funcionários
    carregarFuncionarios();
});

/**
 * Define as datas padrão
 */
function definirDatasPadrao() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = formatarDataInput(primeiroDia);
    document.getElementById('filtroDataFim').value = formatarDataInput(hoje);
}

/**
 * Formata data para input
 */
function formatarDataInput(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

/**
 * Carrega funcionários para o select (apenas vinculados)
 */
function carregarFuncionarios() {
    fetch('/comissao-api-funcionarios-vinculados')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroFuncionario');
                select.innerHTML = '<option value="">Selecione um funcionário</option>';
                data.data.forEach(func => {
                    select.innerHTML += `<option value="${func.ID}">${func.COD_FUNC} - ${func.NOME}</option>`;
                });
            }
        })
        .catch(error => console.error('Erro ao carregar funcionários:', error));
}

/**
 * Carrega o relatório
 */
function carregarRelatorio() {
    const funcionarioId = document.getElementById('filtroFuncionario').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!funcionarioId) {
        exibirMensagemErro('Selecione um funcionário');
        return;
    }
    
    if (!dataInicio || !dataFim) {
        exibirMensagemErro('Informe o período');
        return;
    }
    
    // Mostrar loading
    mostrarLoading('Gerando relatório do funcionário...');
    
    const params = new URLSearchParams({
        funcionarioId: funcionarioId,
        dataInicio: dataInicio,
        dataFim: dataFim
    });
    
    fetch(`/comissao-api-funcionario?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            if (data.success) {
                // Armazenar dados para o comprovante
                dadosRelatorio.funcionario = data.funcionario;
                dadosRelatorio.resumo = data.resumo;
                dadosRelatorio.diario = data.diario;
                
                mostrarSections();
                renderizarFuncionario(data.funcionario);
                renderizarResumo(data.resumo);
                renderizarTabelaDiario(data.diario);
                renderizarTabelaApontamentos(data.apontamentos);
                renderizarTabelaComissoes(data.comissoes);
                
                // Mostrar botão de comprovante
                document.getElementById('btnComprovante').style.display = 'inline-block';
            } else {
                exibirMensagemErro(data.message || 'Erro ao carregar relatório');
            }
        })
        .catch(error => {
            esconderLoading();
            console.error('Erro ao carregar relatório:', error);
            exibirMensagemErro('Erro ao carregar relatório');
        });
}

/**
 * Mostra as seções ocultas
 */
function mostrarSections() {
    document.getElementById('cardFuncionario').style.display = 'block';
    document.getElementById('metricsContainer').style.display = 'grid';
    document.getElementById('sectionDiario').style.display = 'block';
    document.getElementById('sectionApontamentos').style.display = 'block';
    document.getElementById('sectionComissoes').style.display = 'block';
}

/**
 * Renderiza dados do funcionário
 */
function renderizarFuncionario(func) {
    if (!func) return;
    
    document.getElementById('nomeFuncionario').textContent = func.NOME;
    document.getElementById('codigoFuncionario').textContent = func.CODIGO;
    document.getElementById('centroFuncionario').textContent = func.CENTRO_TRABALHO || '-';
    document.getElementById('admissaoFuncionario').textContent = formatarData(func.DT_ADMISSAO);
    document.getElementById('situacaoFuncionario').textContent = func.SITUACAO === 'A' ? 'Ativo' : 'Inativo';
}

/**
 * Renderiza resumo
 */
function renderizarResumo(resumo) {
    if (!resumo) return;
    
    document.getElementById('totalApontamentos').textContent = formatarNumero(resumo.TOTAL_APONTAMENTOS || 0);
    document.getElementById('totalPontos').textContent = formatarNumero(resumo.TOTAL_PONTOS || 0, 2);
    document.getElementById('totalComissao').textContent = formatarMoeda(resumo.TOTAL_COMISSAO || 0);
    document.getElementById('mediaDiaria').textContent = formatarNumero(resumo.MEDIA_DIARIA || 0, 2);
}

/**
 * Renderiza gráfico de evolução
 */
function renderizarGraficoEvolucao(dados) {
    const ctx = document.getElementById('graficoEvolucao').getContext('2d');
    
    if (chartEvolucao) {
        chartEvolucao.destroy();
    }
    
    if (!dados || dados.length === 0) return;
    
    chartEvolucao = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dados.map(d => formatarData(d.DATA)),
            datasets: [{
                label: 'Pontos',
                data: dados.map(d => d.TOTAL_PONTOS),
                borderColor: 'rgba(13, 110, 253, 1)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Renderiza tabela diária
 */
function renderizarTabelaDiario(dados) {
    const tbody = document.getElementById('tabelaDiarioBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum dado encontrado</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const comissaoDia = item.COMISSAO_DIA || 0;
        const temFalta = item.TEM_FALTA || false;
        const tipoFalta = item.TIPO_FALTA || '';
        const motivoFalta = item.MOTIVO_FALTA || '';
        
        // Classe de linha para destacar falta
        const rowClass = temFalta ? 'table-warning' : '';
        
        // Badge de falta
        let faltaBadge = '';
        if (temFalta) {
            const tipoDesc = tipoFalta === 'I' ? 'Integral' : 'Parcial';
            const tooltip = motivoFalta ? ` - ${motivoFalta}` : '';
            faltaBadge = `<span class="badge bg-danger ms-2" title="Falta ${tipoDesc}${tooltip}">FALTA ${tipoDesc.toUpperCase()}</span>`;
        }
        
        html += `
            <tr class="${rowClass}">
                <td>${formatarData(item.DATA)}${faltaBadge}</td>
                <td class="text-center">${formatarNumero(item.QTD_APONTAMENTOS)}</td>
                <td>${item.CENTRO_TRABALHO || '-'}</td>
                <td>${item.RECURSO || '-'}</td>
                <td class="text-end"><strong>${formatarNumero(item.TOTAL_PONTOS, 2)}</strong></td>
                <td class="text-end"><strong>R$ ${formatarNumero(comissaoDia, 2)}</strong></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTableDiario();
}

/**
 * Renderiza tabela de apontamentos
 */
function renderizarTabelaApontamentos(dados) {
    const tbody = document.getElementById('tabelaApontamentosBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum apontamento encontrado</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const codigo = item.CODIGO_PRODUTO || '-';
        const descricao = item.DESC_PRODUTO || '-';
        const mascara = item.MASCARA || '-';
        const operacao = item.DESC_OPERACAO || '-';
        const recurso = item.DESC_MAQUINA || item.COD_MAQUINA || '-';
        const quantidade = item.QUANTIDADE || 0;
        const pontos = item.TOTAL_PONTOS || 0;
        
        html += `
            <tr>
                <td>${codigo}</td>
                <td>${descricao}</td>
                <td style="font-size: 0.75rem;">${mascara}</td>
                <td>${operacao}</td>
                <td>${recurso}</td>
                <td class="text-center">${formatarNumero(quantidade)}</td>
                <td class="text-end"><strong>${formatarNumero(pontos, 2)}</strong></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTableApontamentos();
}

/**
 * Renderiza tabela de comissões
 */
function renderizarTabelaComissoes(dados) {
    const tbody = document.getElementById('tabelaComissoesBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma comissão encontrada</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const statusClass = getStatusClass(item.STATUS);
        const statusTexto = getStatusTexto(item.STATUS);
        
        html += `
            <tr>
                <td>${item.PERIODO}</td>
                <td class="text-end">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td>${item.FAIXA_DESCRICAO || '-'}</td>
                <td class="text-end"><strong>${formatarMoeda(item.VALOR_COMISSAO)}</strong></td>
                <td><span class="status-badge ${statusClass}">${statusTexto}</span></td>
                <td>${formatarDataHora(item.DT_PROCESSAMENTO)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTableComissoes();
}

/**
 * Inicializa DataTables
 */
function initDataTableDiario() {
    if (dataTableDiario) dataTableDiario.destroy();
    dataTableDiario = $('#tabelaDiario').DataTable({
        language: { processing: "Processando...", search: "Pesquisar:", lengthMenu: "Exibir _MENU_ resultados por p\u00e1gina", info: "Mostrando _START_ at\u00e9 _END_ de _TOTAL_ registros", infoEmpty: "Mostrando 0 at\u00e9 0 de 0 registros", infoFiltered: "(filtrado de _MAX_ registros no total)", loadingRecords: "Carregando...", zeroRecords: "Nenhum registro encontrado", emptyTable: "Nenhum dado dispon\u00edvel na tabela", paginate: { first: "Primeiro", previous: "Anterior", next: "Pr\u00f3ximo", last: "\u00daltimo" } },
        lengthChange: false,
        pageLength: 10,
        order: [[0, 'desc']]
    });
}

function initDataTableApontamentos() {
    if (dataTableApontamentos) dataTableApontamentos.destroy();
    dataTableApontamentos = $('#tabelaApontamentos').DataTable({
        language: { processing: "Processando...", search: "Pesquisar:", lengthMenu: "Exibir _MENU_ resultados por p\u00e1gina", info: "Mostrando _START_ at\u00e9 _END_ de _TOTAL_ registros", infoEmpty: "Mostrando 0 at\u00e9 0 de 0 registros", infoFiltered: "(filtrado de _MAX_ registros no total)", loadingRecords: "Carregando...", zeroRecords: "Nenhum registro encontrado", emptyTable: "Nenhum dado dispon\u00edvel na tabela", paginate: { first: "Primeiro", previous: "Anterior", next: "Pr\u00f3ximo", last: "\u00daltimo" } },
        lengthChange: false,
        pageLength: 10,
        order: [[0, 'desc'], [1, 'desc']]
    });
}

function initDataTableComissoes() {
    if (dataTableComissoes) dataTableComissoes.destroy();
    dataTableComissoes = $('#tabelaComissoes').DataTable({
        language: { processing: "Processando...", search: "Pesquisar:", lengthMenu: "Exibir _MENU_ resultados por p\u00e1gina", info: "Mostrando _START_ at\u00e9 _END_ de _TOTAL_ registros", infoEmpty: "Mostrando 0 at\u00e9 0 de 0 registros", infoFiltered: "(filtrado de _MAX_ registros no total)", loadingRecords: "Carregando...", zeroRecords: "Nenhum registro encontrado", emptyTable: "Nenhum dado dispon\u00edvel na tabela", paginate: { first: "Primeiro", previous: "Anterior", next: "Pr\u00f3ximo", last: "\u00daltimo" } },
        lengthChange: false,
        pageLength: 10,
        order: [[0, 'desc']]
    });
}

/**
 * Helpers
 */
function getStatusClass(status) {
    switch (status) {
        case 'P': return 'status-pendente';
        case 'A': return 'status-aprovado';
        case 'C': return 'status-cancelado';
        default: return '';
    }
}

function getStatusTexto(status) {
    switch (status) {
        case 'P': return 'Pendente';
        case 'A': return 'Aprovado';
        case 'C': return 'Cancelado';
        default: return status;
    }
}

function formatarNumero(valor, casasDecimais = 0) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: casasDecimais,
        maximumFractionDigits: casasDecimais
    }).format(valor || 0);
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor || 0);
}

function formatarData(data) {
    if (!data) return '-';
    // Adiciona T12:00:00 para evitar problemas de timezone
    // Quando a data vem como YYYY-MM-DD, JS interpreta como UTC meia-noite
    // que no Brasil (UTC-3) volta um dia
    if (typeof data === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data)) {
        data = data + 'T12:00:00';
    }
    return new Date(data).toLocaleDateString('pt-BR');
}

function formatarDataHora(data) {
    if (!data) return '-';
    return new Date(data).toLocaleString('pt-BR');
}

function exportarPDF() {
    alert('Funcionalidade de exportação PDF será implementada');
}

function exportarExcel() {
    exportarTabelaExcel('tabelaDiario', 'Relatorio_Funcionario');
}

function exportarTabelaExcel(tabelaId, nomeArquivo) {
    const tabela = document.getElementById(tabelaId);
    if (!tabela) { alert('Tabela não encontrada'); return; }

    let csvContent = '\uFEFF';

    // Headers
    const headers = [];
    tabela.querySelectorAll('thead th').forEach(th => {
        let texto = th.innerText.replace(/"/g, '""');
        headers.push('"' + texto + '"');
    });
    csvContent += headers.join(';') + '\n';

    // Rows - usar DataTable API para pegar TODAS as linhas
    try {
        const dtInstance = $(tabela).DataTable();
        if (dtInstance && dtInstance.rows().count() > 0) {
            dtInstance.rows({ search: 'applied' }).every(function() {
                const row = this.node();
                const cols = [];
                row.querySelectorAll('td').forEach(td => {
                    let texto = td.innerText.replace(/"/g, '""');
                    cols.push('"' + texto + '"');
                });
                csvContent += cols.join(';') + '\n';
            });
        }
    } catch(e) {
        // Fallback DOM
        tabela.querySelectorAll('tbody tr').forEach(tr => {
            const cols = [];
            tr.querySelectorAll('td').forEach(td => {
                let texto = td.innerText.replace(/"/g, '""');
                cols.push('"' + texto + '"');
            });
            csvContent += cols.join(';') + '\n';
        });
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nomeArquivo + '_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function exibirMensagemErro(mensagem) {
    alert('❌ ' + mensagem);
}

/**
 * Gera o comprovante de comissão
 */
function gerarComprovante() {
    if (!dadosRelatorio.funcionario || !dadosRelatorio.resumo) {
        exibirMensagemErro('Gere o relatório primeiro');
        return;
    }
    
    const func = dadosRelatorio.funcionario;
    const resumo = dadosRelatorio.resumo;
    const diario = dadosRelatorio.diario || [];
    
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    // Preencher dados do funcionário (compacto)
    document.getElementById('comprovanteNome').textContent = func.NOME || '-';
    document.getElementById('comprovanteCodigo').textContent = func.CODIGO || '-';
    document.getElementById('comprovanteCentro').textContent = func.CENTRO_TRABALHO || '-';
    document.getElementById('comprovanteNomeAssinatura').textContent = func.NOME || '-';
    
    // Preencher período
    document.getElementById('comprovantePeriodo').textContent = 
        `${formatarData(dataInicio)} a ${formatarData(dataFim)}`;
    
    // Preencher detalhamento diário (TODOS os dias)
    const tbody = document.getElementById('comprovanteDetalhamento');
    let html = '';
    
    diario.forEach(item => {
        const comissaoDia = item.COMISSAO_DIA || 0;
        const temFalta = item.TEM_FALTA || false;
        const tipoFalta = item.TIPO_FALTA || '';
        
        let faltaIndicador = '';
        if (temFalta) {
            faltaIndicador = tipoFalta === 'I' ? ' *' : ' ~';
        }
        
        html += `
            <tr>
                <td>${formatarData(item.DATA)}${faltaIndicador}</td>
                <td class="text-center">${formatarNumero(item.QTD_APONTAMENTOS)}</td>
                <td class="text-center">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td class="text-end">R$ ${formatarNumero(comissaoDia, 2)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Preencher totais no rodapé da tabela
    document.getElementById('comprovanteTotalApontamentos').textContent = formatarNumero(resumo.TOTAL_APONTAMENTOS || 0);
    document.getElementById('comprovanteTotalPontos').textContent = formatarNumero(resumo.TOTAL_PONTOS || 0, 2);
    document.getElementById('comprovanteTotalComissao').textContent = formatarMoeda(resumo.TOTAL_COMISSAO || 0);
    
    // Mostrar info de valor fixo para tipo M (Misto)
    const infoFixo = document.getElementById('comprovanteValorFixoInfo');
    if (resumo.TIPO_REGRA === 'M' && resumo.VALOR_FIXO > 0) {
        document.getElementById('comprovanteValorFixo').textContent = formatarMoeda(resumo.VALOR_FIXO);
        document.getElementById('comprovanteValorPorPonto').textContent = 'R$ ' + formatarNumero(resumo.VALOR_POR_PONTO || 0, 4) + '/pt';
        infoFixo.style.display = 'block';
    } else {
        infoFixo.style.display = 'none';
    }
    
    // Data de geração
    const agora = new Date();
    document.getElementById('comprovanteDataGeracao').textContent = agora.toLocaleString('pt-BR');
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalComprovante'));
    modal.show();
}

/**
 * Imprime o comprovante
 */
function imprimirComprovante() {
    // Construir HTML limpo diretamente dos dados (sem DataTable)
    const func = dadosRelatorio.funcionario;
    const resumo = dadosRelatorio.resumo;
    const diario = dadosRelatorio.diario || [];
    
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    // Construir linhas da tabela
    let linhasHtml = '';
    diario.forEach(item => {
        const comissaoDia = item.COMISSAO_DIA || 0;
        const temFalta = item.TEM_FALTA || false;
        const tipoFalta = item.TIPO_FALTA || '';
        
        let faltaIndicador = '';
        if (temFalta) {
            faltaIndicador = tipoFalta === 'I' ? ' *' : ' ~';
        }
        
        linhasHtml += `
            <tr>
                <td>${formatarData(item.DATA)}${faltaIndicador}</td>
                <td style="text-align: center;">${formatarNumero(item.QTD_APONTAMENTOS)}</td>
                <td style="text-align: center;">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td style="text-align: right;">R$ ${formatarNumero(comissaoDia, 2)}</td>
            </tr>
        `;
    });
    
    const agora = new Date();
    
    const janelaImpressao = window.open('', '_blank', 'width=800,height=600');
    janelaImpressao.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Comprovante de Comissão</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px;
                    margin: 20px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 8px;
                    margin-bottom: 10px;
                }
                .header h5 {
                    margin: 0;
                    font-size: 14px;
                }
                .funcionario {
                    padding: 8px 0;
                    border-bottom: 1px solid #ccc;
                    margin-bottom: 10px;
                }
                .funcionario span {
                    margin-right: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                }
                th, td {
                    border: 1px solid #333;
                    padding: 4px 8px;
                }
                thead {
                    background-color: #333;
                    color: white;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                tfoot {
                    background-color: #e9ecef;
                    font-weight: bold;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .assinaturas {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 40px;
                }
                .assinatura-box {
                    flex: 1;
                    text-align: center;
                    margin: 0 20px;
                }
                .linha-assinatura {
                    border-top: 1px solid #333;
                    margin-bottom: 5px;
                }
                .rodape {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                }
                @media print {
                    @page { size: A4; margin: 15mm; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <h5>COMPROVANTE DE COMISSÃO POR PRODUTIVIDADE</h5>
                    <small>Gazin Indústria de Colchões Ltda</small>
                </div>
                <div>
                    <strong>Período:</strong> ${formatarData(dataInicio)} a ${formatarData(dataFim)}
                </div>
            </div>
            
            <div class="funcionario">
                <span><strong>Funcionário:</strong> ${func.NOME || '-'}</span>
                <span><strong>Cód:</strong> ${func.CODIGO || '-'}</span>
                <span><strong>Centro:</strong> ${func.CENTRO_TRABALHO || '-'}</span>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th style="text-align: center;">Apontamentos</th>
                        <th style="text-align: center;">Pontos</th>
                        <th style="text-align: right;">Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    ${linhasHtml}
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td style="text-align: center;">${formatarNumero(resumo.TOTAL_APONTAMENTOS || 0)}</td>
                        <td style="text-align: center;">${formatarNumero(resumo.TOTAL_PONTOS || 0, 2)}</td>
                        <td style="text-align: right;">${formatarMoeda(resumo.TOTAL_COMISSAO || 0)}</td>
                    </tr>
                </tfoot>
            </table>
            
            ${resumo.TIPO_REGRA === 'M' && resumo.VALOR_FIXO > 0 ? `
            <div style="padding: 8px 12px; margin: 8px 0; background: #f0f7ff; border-left: 3px solid #0d6efd; font-size: 11px;">
                <strong>Regra Misto:</strong> Valor Fixo ${formatarMoeda(resumo.VALOR_FIXO)} + Valor por Ponto R$ ${formatarNumero(resumo.VALOR_POR_PONTO || 0, 4)}/pt
            </div>
            ` : ''}
            
            <div class="assinaturas">
                <div class="assinatura-box">
                    <div class="linha-assinatura"></div>
                    <small>Funcionário: ${func.NOME || '-'}</small>
                </div>
                <div class="assinatura-box">
                    <div class="linha-assinatura"></div>
                    <small>Responsável RH</small>
                </div>
            </div>
            
            <div class="rodape">
                Gerado em: ${agora.toLocaleString('pt-BR')}
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() { window.close(); };
                };
            </script>
        </body>
        </html>
    `);
    janelaImpressao.document.close();
}
