/**
 * Comissão Relatório por Centro de Trabalho - JavaScript
 */

let dataTableFuncionarios = null;
let dadosFuncionariosCarregados = [];
let dadosCentro = null;
let dadosResumo = null;

/**
 * Mostra overlay de loading na tela
 */
function mostrarLoading(mensagem = 'Gerando relatório...') {
    esconderLoading();
    
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
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
    if (overlay) overlay.remove();
}

document.addEventListener('DOMContentLoaded', function() {
    definirDatasPadrao();
    carregarCentrosTrabalho();
});

/**
 * Define as datas padrão (mês atual)
 */
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

/**
 * Carrega centros de trabalho para o select (apenas vinculados)
 */
function carregarCentrosTrabalho() {
    fetch('/comissao-api-centros-vinculados')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroCentro');
                select.innerHTML = '<option value="">Selecione um centro de trabalho</option>';
                data.data.forEach(centro => {
                    select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                });
            }
        })
        .catch(error => console.error('Erro ao carregar centros:', error));
}

/**
 * Carrega o relatório
 */
function carregarRelatorio() {
    const centroTrabId = document.getElementById('filtroCentro').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!centroTrabId) {
        alert('❌ Selecione um centro de trabalho');
        return;
    }
    
    if (!dataInicio || !dataFim) {
        alert('❌ Informe o período');
        return;
    }
    
    mostrarLoading('Gerando relatório do centro de trabalho...');
    
    const params = new URLSearchParams({
        centroTrabId: centroTrabId,
        dataInicio: dataInicio,
        dataFim: dataFim
    });
    
    fetch(`/comissao-api-relatorio-centro-trabalho?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            if (data.success) {
                dadosCentro = data.centro;
                dadosResumo = data.resumo;
                dadosFuncionariosCarregados = data.funcionarios || [];
                
                renderizarCentro(data.centro);
                renderizarResumo(data.resumo);
                renderizarTabelaFuncionarios(data.funcionarios);
                
                document.getElementById('cardCentro').style.display = 'block';
                document.getElementById('metricsContainer').style.display = 'grid';
                document.getElementById('sectionFuncionarios').style.display = 'block';
            } else {
                alert('❌ ' + (data.error || 'Erro ao carregar relatório'));
            }
        })
        .catch(error => {
            esconderLoading();
            console.error('Erro ao carregar relatório:', error);
            alert('❌ Erro ao carregar relatório');
        });
}

/**
 * Renderiza dados do centro
 */
function renderizarCentro(centro) {
    if (!centro) return;
    document.getElementById('nomeCentro').textContent = (centro.CODIGO || '') + ' - ' + (centro.DESCRICAO || '');
    document.getElementById('codigoCentro').textContent = centro.CODIGO || '-';
}

/**
 * Renderiza resumo
 */
function renderizarResumo(resumo) {
    if (!resumo) return;
    document.getElementById('totalFuncionarios').textContent = formatarNumero(resumo.TOTAL_FUNCIONARIOS || 0);
    document.getElementById('totalPontos').textContent = formatarNumero(resumo.TOTAL_PONTOS || 0, 2);
    document.getElementById('totalComissao').textContent = formatarMoeda(resumo.TOTAL_COMISSAO || 0);
    document.getElementById('totalComFalta').textContent = formatarNumero(resumo.TOTAL_COM_FALTA || 0);
}

/**
 * Renderiza tabela de funcionários
 */
function renderizarTabelaFuncionarios(dados) {
    const tbody = document.getElementById('tabelaFuncionariosBody');
    
    if (dataTableFuncionarios) {
        dataTableFuncionarios.destroy();
        dataTableFuncionarios = null;
    }
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhum funcionário encontrado</td></tr>';
        atualizarTotais(0, 0);
        return;
    }
    
    let totalPontos = 0;
    let totalComissao = 0;
    let html = '';
    
    dados.forEach((item, idx) => {
        const temFalta = item.TEM_FALTA || false;
        const diasComFalta = item.DIAS_COM_FALTA || 0;
        const faltaBadge = temFalta 
            ? `<span class="badge bg-warning text-dark ms-1" title="${diasComFalta} dia(s) com falta">⚠️ ${diasComFalta}</span>` 
            : '';
        const rowClass = temFalta ? 'table-warning' : '';
        const tipoVinculo = item.TIPO_VINCULO === 'A' ? '<span class="badge bg-secondary ms-1">APOIO</span>' : '';
        
        totalPontos += parseFloat(item.TOTAL_PONTOS || 0);
        totalComissao += parseFloat(item.VALOR_COMISSAO || 0);
        
        html += `
            <tr class="${rowClass}">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input checkbox-func" value="${idx}">
                </td>
                <td>${item.COD_FUNC || '-'}</td>
                <td><strong>${item.NOME_FUNC || '-'}</strong>${tipoVinculo}</td>
                <td class="text-end">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td>${item.FAIXA_DESCRICAO || '-'}</td>
                <td class="text-center">${formatarNumero(item.DIAS_TRABALHADOS || 0)}</td>
                <td class="text-center">${faltaBadge || '0'}</td>
                <td class="text-end"><strong>${formatarMoeda(item.VALOR_COMISSAO)}</strong></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary" onclick="gerarComprovanteIndividual(${idx})" title="Gerar Comprovante">
                        <i class="bi bi-file-earmark-text"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    atualizarTotais(totalPontos, totalComissao);
    initDataTable();
}

/**
 * Atualiza totais do footer
 */
function atualizarTotais(pontos, comissao) {
    document.getElementById('footTotalPontos').innerHTML = `<strong>${formatarNumero(pontos, 2)}</strong>`;
    document.getElementById('footTotalComissao').innerHTML = `<strong>${formatarMoeda(comissao)}</strong>`;
}

/**
 * Inicializa DataTable
 */
function initDataTable() {
    if (dataTableFuncionarios) dataTableFuncionarios.destroy();
    
    dataTableFuncionarios = $('#tabelaFuncionarios').DataTable({
        language: {
            processing: "Processando...",
            search: "Pesquisar:",
            lengthMenu: "Exibir _MENU_ resultados por página",
            info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 até 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros no total)",
            loadingRecords: "Carregando...",
            zeroRecords: "Nenhum registro encontrado",
            emptyTable: "Nenhum dado disponível na tabela",
            paginate: { first: "Primeiro", previous: "Anterior", next: "Próximo", last: "Último" }
        },
        lengthChange: false,
        pageLength: 25,
        order: [[2, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 8] }
        ]
    });
}

/**
 * Toggle seleção de todos os checkboxes
 */
function toggleSelecaoTodos() {
    const selecionarTodos = document.getElementById('selecionarTodos').checked;
    document.querySelectorAll('.checkbox-func').forEach(cb => {
        cb.checked = selecionarTodos;
    });
}

/**
 * Obtém índices dos funcionários selecionados
 */
function obterSelecionados() {
    const indices = [];
    document.querySelectorAll('.checkbox-func:checked').forEach(cb => {
        indices.push(parseInt(cb.value));
    });
    return indices;
}

// ==================== COMPROVANTES ====================

/**
 * Gera comprovante individual de um funcionário
 */
function gerarComprovanteIndividual(idx) {
    const func = dadosFuncionariosCarregados[idx];
    if (!func) {
        alert('❌ Dados do funcionário não encontrados');
        return;
    }
    
    imprimirComprovantes([func]);
}

/**
 * Imprime comprovantes dos funcionários selecionados
 */
function imprimirComprovantesSelecionados() {
    const indices = obterSelecionados();
    if (indices.length === 0) {
        alert('❌ Selecione ao menos um funcionário');
        return;
    }
    
    const funcionarios = indices.map(idx => dadosFuncionariosCarregados[idx]).filter(f => f);
    imprimirComprovantes(funcionarios);
}

/**
 * Imprime comprovantes de todos os funcionários
 */
function imprimirTodosComprovantes() {
    if (dadosFuncionariosCarregados.length === 0) {
        alert('❌ Gere o relatório primeiro');
        return;
    }
    
    if (!confirm(`Deseja imprimir comprovantes de todos os ${dadosFuncionariosCarregados.length} funcionário(s)?`)) return;
    
    imprimirComprovantes(dadosFuncionariosCarregados);
}

/**
 * Gera a janela de impressão com comprovantes (cada um em uma folha separada)
 */
function imprimirComprovantes(funcionarios) {
    if (!funcionarios || funcionarios.length === 0) return;
    
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    const centroNome = dadosCentro ? (dadosCentro.CODIGO + ' - ' + dadosCentro.DESCRICAO) : '-';
    const agora = new Date();
    
    let paginasHtml = '';
    
    funcionarios.forEach((func, index) => {
        const isLast = index === funcionarios.length - 1;
        
        paginasHtml += `
            <div class="comprovante-pagina" ${!isLast ? 'style="page-break-after: always;"' : ''}>
                <div class="header">
                    <div>
                        <h5>COMPROVANTE DE COMISSÃO POR PRODUTIVIDADE</h5>
                        <small>Gazin Indústria de Colchões Ltda</small>
                    </div>
                    <div class="header-right">
                        <div><strong>Período:</strong> ${formatarData(dataInicio)} a ${formatarData(dataFim)}</div>
                        <div><strong>Centro:</strong> ${centroNome}</div>
                    </div>
                </div>
                
                <div class="funcionario-info">
                    <span><strong>Funcionário:</strong> ${func.NOME_FUNC || '-'}</span>
                    <span><strong>Cód:</strong> ${func.COD_FUNC || '-'}</span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th style="text-align: center;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total de Pontos</td>
                            <td style="text-align: center;">${formatarNumero(func.TOTAL_PONTOS, 2)}</td>
                        </tr>
                        <tr>
                            <td>Faixa Aplicada</td>
                            <td style="text-align: center;">${func.FAIXA_DESCRICAO || '-'}</td>
                        </tr>
                        <tr>
                            <td>Dias Trabalhados</td>
                            <td style="text-align: center;">${formatarNumero(func.DIAS_TRABALHADOS || 0)}</td>
                        </tr>
                        <tr>
                            <td>Dias com Falta</td>
                            <td style="text-align: center;">${formatarNumero(func.DIAS_COM_FALTA || 0)}</td>
                        </tr>
                        ${func.USA_REGRA_ESPECIFICA ? '<tr><td>Regra Especial</td><td style="text-align: center;">Sim</td></tr>' : ''}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>VALOR DA COMISSÃO</strong></td>
                            <td style="text-align: center;"><strong>${formatarMoeda(func.VALOR_COMISSAO)}</strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="assinaturas">
                    <div class="assinatura-box">
                        <div class="linha-assinatura"></div>
                        <small>Funcionário: ${func.NOME_FUNC || '-'}</small>
                    </div>
                    <div class="assinatura-box">
                        <div class="linha-assinatura"></div>
                        <small>Responsável RH</small>
                    </div>
                </div>
                
                <div class="rodape">
                    Gerado em: ${agora.toLocaleString('pt-BR')}
                </div>
            </div>
        `;
    });
    
    const janelaImpressao = window.open('', '_blank', 'width=800,height=600');
    janelaImpressao.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Comprovantes de Comissão - ${centroNome}</title>
            <style>
                * { box-sizing: border-box; }
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px;
                    margin: 0;
                    padding: 20px;
                }
                .comprovante-pagina {
                    padding: 20px;
                    max-width: 800px;
                    margin: 0 auto;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 2px solid #333;
                    padding-bottom: 8px;
                    margin-bottom: 12px;
                }
                .header h5 {
                    margin: 0;
                    font-size: 14px;
                }
                .header-right {
                    text-align: right;
                    font-size: 11px;
                }
                .funcionario-info {
                    padding: 8px 0;
                    border-bottom: 1px solid #ccc;
                    margin-bottom: 12px;
                }
                .funcionario-info span {
                    margin-right: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                    margin-bottom: 10px;
                }
                th, td {
                    border: 1px solid #333;
                    padding: 6px 10px;
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
                tfoot td {
                    font-size: 14px;
                }
                .assinaturas {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 50px;
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
                    body { padding: 0; }
                    .comprovante-pagina { padding: 0; }
                }
                @media screen {
                    .comprovante-pagina {
                        border: 1px solid #ccc;
                        margin-bottom: 20px;
                        border-radius: 4px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                }
            </style>
        </head>
        <body>
            ${paginasHtml}
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() { window.close(); };
                };
            <\/script>
        </body>
        </html>
    `);
    janelaImpressao.document.close();
}

// ==================== HELPERS ====================

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
    if (typeof data === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data)) {
        data = data + 'T12:00:00';
    }
    return new Date(data).toLocaleDateString('pt-BR');
}

function exportarExcel() {
    const tabela = document.getElementById('tabelaFuncionarios');
    if (!tabela) { alert('Tabela não encontrada'); return; }

    let csvContent = '\uFEFF';

    // Headers (pular checkbox e ações)
    const headers = [];
    tabela.querySelectorAll('thead th').forEach((th, idx) => {
        if (idx === 0 || idx === 8) return; // pular checkbox e comprovante
        headers.push('"' + th.innerText.replace(/"/g, '""') + '"');
    });
    csvContent += headers.join(';') + '\n';

    // Rows
    try {
        const dtInstance = $(tabela).DataTable();
        if (dtInstance && dtInstance.rows().count() > 0) {
            dtInstance.rows({ search: 'applied' }).every(function() {
                const row = this.node();
                const cols = [];
                row.querySelectorAll('td').forEach((td, idx) => {
                    if (idx === 0 || idx === 8) return; // pular checkbox e comprovante
                    cols.push('"' + td.innerText.replace(/"/g, '""') + '"');
                });
                csvContent += cols.join(';') + '\n';
            });
        }
    } catch(e) {
        tabela.querySelectorAll('tbody tr').forEach(tr => {
            const cols = [];
            tr.querySelectorAll('td').forEach((td, idx) => {
                if (idx === 0 || idx === 8) return;
                cols.push('"' + td.innerText.replace(/"/g, '""') + '"');
            });
            csvContent += cols.join(';') + '\n';
        });
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    const dataInicio = document.getElementById('filtroDataInicio').value || '';
    const dataFim = document.getElementById('filtroDataFim').value || '';
    link.download = `Relatorio_Centro_Trabalho_${dataInicio}_a_${dataFim}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}
