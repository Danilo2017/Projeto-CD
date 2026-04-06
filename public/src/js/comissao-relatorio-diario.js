/**
 * Comissão Relatório Diário - JavaScript
 * Com validação de cadastros (Pontuação, Faixa, Vínculo)
 */

let dataTableProdutividade = null;
let dataTableApontamentos = null;

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

const dtLanguagePtBr = {
    processing: "Processando...",
    search: "Pesquisar:",
    lengthMenu: "Exibir _MENU_ resultados por p\u00e1gina",
    info: "Mostrando _START_ at\u00e9 _END_ de _TOTAL_ registros",
    infoEmpty: "Mostrando 0 at\u00e9 0 de 0 registros",
    infoFiltered: "(filtrado de _MAX_ registros no total)",
    loadingRecords: "Carregando...",
    zeroRecords: "Nenhum registro encontrado",
    emptyTable: "Nenhum dado dispon\u00edvel na tabela",
    paginate: {
        first: "Primeiro",
        previous: "Anterior",
        next: "Pr\u00f3ximo",
        last: "\u00daltimo"
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('filtroDataInicio').value = hoje;
    document.getElementById('filtroDataFim').value = hoje;
    carregarCentrosTrabalho();
    carregarRecursos();
});

/**
 * Carrega centros de trabalho para o select (apenas vinculados)
 */
function carregarCentrosTrabalho() {
    const emprId = document.getElementById('filtroEmpresa').value;
    const params = new URLSearchParams();
    if (emprId) params.append('emprId', emprId);
    
    fetch(`/comissao-api-centros-vinculados?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroCentro');
                select.innerHTML = '<option value="">Todos</option>';
                data.data.forEach(centro => {
                    select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                });
                inicializarSelect2Centro();
            } else {
                console.error('Erro ao carregar centros:', data.error);
            }
        })
        .catch(error => console.error('Erro ao carregar centros:', error));
}

/**
 * Inicializa Select2 no campo Centro de Trabalho
 */
function inicializarSelect2Centro() {
    if ($('#filtroCentro').data('select2')) {
        $('#filtroCentro').select2('destroy');
    }
    $('#filtroCentro').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true
    }).on('change', function() {
        carregarRecursos();
    });
}

/**
 * Carrega recursos baseado no centro selecionado (apenas vinculados)
 */
function carregarRecursos() {
    const centroId = document.getElementById('filtroCentro').value;
    const emprId = document.getElementById('filtroEmpresa').value;
    const params = new URLSearchParams();
    if (emprId) params.append('emprId', emprId);
    if (centroId) params.append('centroTrabId', centroId);
    
    fetch(`/comissao-api-recursos-vinculados?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroRecurso');
                select.innerHTML = '<option value="">Todos</option>';
                data.data.forEach(recurso => {
                    select.innerHTML += `<option value="${recurso.ID}">${recurso.COD_MAQUINA} - ${recurso.DESCRICAO}</option>`;
                });
                inicializarSelect2Recurso();
            }
        })
        .catch(error => console.error('Erro ao carregar recursos:', error));
}

/**
 * Inicializa Select2 no campo Recurso
 */
function inicializarSelect2Recurso() {
    if ($('#filtroRecurso').data('select2')) {
        $('#filtroRecurso').select2('destroy');
    }
    $('#filtroRecurso').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true
    });
}

/**
 * Carrega o relatório
 */
function carregarRelatorio() {
    // Forçar commit de qualquer input de data que esteja sendo editado
    document.getElementById('filtroDataInicio').blur();
    document.getElementById('filtroDataFim').blur();
    
    // Ler valores após blur para garantir que foram commitados
    setTimeout(function() {
        _executarCarregarRelatorio();
    }, 50);
}

function _executarCarregarRelatorio() {
    const hoje = new Date().toISOString().split('T')[0];
    const dataInicio = document.getElementById('filtroDataInicio').value || hoje;
    const dataFim = document.getElementById('filtroDataFim').value || hoje;
    
    // Garantir que os inputs tenham valores
    if (!document.getElementById('filtroDataInicio').value) {
        document.getElementById('filtroDataInicio').value = hoje;
    }
    if (!document.getElementById('filtroDataFim').value) {
        document.getElementById('filtroDataFim').value = hoje;
    }
    
    if (dataFim < dataInicio) {
        exibirMensagemErro('A data fim não pode ser anterior à data início');
        return;
    }
    
    // Mostrar loading
    mostrarLoading('Gerando relatório diário...');
    
    const params = new URLSearchParams();
    params.append('data', dataInicio);
    params.append('dataFim', dataFim);
    
    const emprId = document.getElementById('filtroEmpresa').value;
    const centroTrabId = document.getElementById('filtroCentro').value;
    const recursoId = document.getElementById('filtroRecurso').value;
    
    if (emprId) params.append('emprId', emprId);
    if (centroTrabId) params.append('centroTrabId', centroTrabId);
    if (recursoId) params.append('recursoId', recursoId);
    
    fetch(`/comissao-api-produtividade-diaria?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            if (data.success) {
                renderizarResumo(data.resumo);
                renderizarTabelaProdutividade(data.produtividade);
                renderizarTabelaApontamentos(data.apontamentos);

            } else {
                exibirMensagemErro(data.error || 'Erro ao carregar relatório');
            }
        })
        .catch(error => {
            esconderLoading();
            console.error('Erro ao carregar relatório:', error);
            exibirMensagemErro('Erro ao carregar relatório');
        });
}

/**
 * Renderiza o resumo (cards e badges de validação)
 */
function renderizarResumo(resumo) {
    if (!resumo) return;
    
    document.getElementById('totalRegistros').textContent = formatarNumero(resumo.TOTAL_REGISTROS || 0);
    document.getElementById('totalQtdProduzida').textContent = formatarNumero(resumo.TOTAL_QTD_PRODUZIDA || 0);
    document.getElementById('totalPontos').textContent = formatarNumero(resumo.TOTAL_PONTOS || 0, 2);
    document.getElementById('totalFuncionarios').textContent = formatarNumero(resumo.TOTAL_FUNCIONARIOS || 0);
    
    // Badges de validação
    const semPont = resumo.TOTAL_SEM_PONTUACAO || 0;
    const semFaixa = resumo.TOTAL_SEM_FAIXA || 0;
    const semVinc = resumo.TOTAL_SEM_VINCULO || 0;
    
    document.getElementById('badgeSemPontuacao').textContent = semPont;
    document.getElementById('badgeSemPontuacao').className = `badge fs-6 ${semPont > 0 ? 'bg-danger' : 'bg-success'}`;
    
    document.getElementById('badgeSemFaixa').textContent = semFaixa;
    document.getElementById('badgeSemFaixa').className = `badge fs-6 ${semFaixa > 0 ? 'bg-warning text-dark' : 'bg-success'}`;
    
    document.getElementById('badgeSemVinculo').textContent = semVinc;
    document.getElementById('badgeSemVinculo').className = `badge fs-6 ${semVinc > 0 ? 'bg-secondary' : 'bg-success'}`;
}

/**
 * Helper: badge de validação (check verde ou X vermelho)
 */
function badgeValidacao(valido) {
    if (valido) {
        return '<span class="badge bg-success"><i class="bi bi-check-lg"></i></span>';
    }
    return '<span class="badge bg-danger"><i class="bi bi-x-lg"></i></span>';
}

/**
 * Renderiza a tabela de produtividade por funcionário
 */
function renderizarTabelaProdutividade(dados) {
    // Destruir DataTable ANTES de alterar o DOM
    if (dataTableProdutividade) {
        dataTableProdutividade.destroy();
        dataTableProdutividade = null;
    }

    const tbody = document.getElementById('tabelaProdutividadeBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhum dado encontrado para esta data</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        // Verificar se tem falta
        const temFalta = item.TEM_FALTA || false;
        const tipoFalta = item.TIPO_FALTA || '';
        const rowClass = temFalta ? 'table-danger' : '';
        
        let faltaBadge = '';
        if (temFalta) {
            const tipoDesc = tipoFalta === 'I' ? 'INTEGRAL' : 'PARCIAL';
            faltaBadge = `<span class="badge bg-danger ms-1">FALTA ${tipoDesc}</span>`;
        }
        
        html += `
            <tr class="${rowClass}">
                <td>
                    <small><strong>${item.NOME}</strong></small>${faltaBadge}
                    <br><small class="text-muted" style="font-size:0.7rem">${item.CODIGO}</small>
                </td>
                <td><small>${item.CENTRO_TRABALHO || '-'}</small></td>
                <td><small>${item.RECURSO || '-'}</small></td>
                <td class="text-center"><small>${formatarNumero(item.QTD_ITENS)}</small></td>
                <td class="text-center"><small>${formatarNumero(item.QTD_PRODUZIDA)}</small></td>
                <td class="text-end"><small><strong>${formatarNumero(item.TOTAL_PONTOS, 2)}</strong></small></td>
                <td class="text-center">${badgeValidacao(item.TEM_PONTUACAO)}</td>
                <td class="text-center">${badgeValidacao(item.TEM_FAIXA)}</td>
                <td class="text-center">${badgeValidacao(item.TEM_VINCULO)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTableProdutividade();
}

/**
 * Renderiza a tabela de detalhamento dos apontamentos
 */
function renderizarTabelaApontamentos(dados) {
    // Destruir DataTable ANTES de alterar o DOM
    if (dataTableApontamentos) {
        dataTableApontamentos.destroy();
        dataTableApontamentos = null;
    }

    const tbody = document.getElementById('tabelaApontamentosBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">Nenhum apontamento encontrado</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const rowClass = (!item.TEM_PONTUACAO || !item.TEM_FAIXA || !item.TEM_VINCULO) ? 'table-warning' : '';
        
        html += `
            <tr class="${rowClass}">
                <td><small>${item.FUNCIONARIO}</small></td>
                <td><small><strong>${item.CODIGO_PRODUTO || ''}</strong> (${item.ID_ITEM || ''})</small><br><small>${item.PRODUTO || ''}</small><br><small class="text-muted" style="font-size:0.7rem">${item.MASCARA || ''}</small></td>
                <td><small>${item.CENTRO_TRAB || '-'}</small></td>
                <td><small>${item.OPERACAO || '-'}</small></td>
                <td><small>${item.RECURSO || '-'}</small></td>
                <td class="text-center">${formatarNumero(item.QUANTIDADE)}</td>
                <td class="text-end">${formatarNumero(item.PONTOS_UP, 4)}</td>
                <td class="text-end"><strong>${formatarNumero(item.TOTAL_PONTOS, 2)}</strong></td>
                <td class="text-center">${badgeValidacao(item.TEM_PONTUACAO)}</td>
                <td class="text-center">${badgeValidacao(item.TEM_FAIXA)}</td>
                <td class="text-center">${badgeValidacao(item.TEM_VINCULO)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTableApontamentos();
}


/**
 * Inicializa DataTable de produtividade
 */
function initDataTableProdutividade() {
    if (dataTableProdutividade) {
        dataTableProdutividade.destroy();
        dataTableProdutividade = null;
    }
    
    dataTableProdutividade = $('#tabelaProdutividade').DataTable({
        language: dtLanguagePtBr,
        lengthChange: false,
        pageLength: 20,
        order: [[5, 'desc']]
    });
}

/**
 * Inicializa DataTable de apontamentos
 */
function initDataTableApontamentos() {
    if (dataTableApontamentos) {
        dataTableApontamentos.destroy();
        dataTableApontamentos = null;
    }
    
    dataTableApontamentos = $('#tabelaApontamentos').DataTable({
        language: dtLanguagePtBr,
        lengthChange: false,
        pageLength: 20,
        order: [[0, 'asc']]
    });
}

/**
 * Formata número
 */
function formatarNumero(valor, casasDecimais = 0) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: casasDecimais,
        maximumFractionDigits: casasDecimais
    }).format(valor || 0);
}

/**
 * Exportar PDF
 */
function exportarPDF() {
    alert('Funcionalidade de exportação PDF será implementada');
}

/**
 * Exportar Excel - Exporta todas as tabelas em um único arquivo
 */
function exportarExcel() {
    let csvContent = '\uFEFF';
    
    // Seção: Produtividade por Funcionário
    csvContent += 'PRODUTIVIDADE POR FUNCIONÁRIO\n';
    csvContent += exportarTabelaCSV('tabelaProdutividade');
    csvContent += '\n\n';
    
    // Seção: Detalhamento de Apontamentos
    csvContent += 'DETALHAMENTO DE APONTAMENTOS\n';
    csvContent += exportarTabelaCSV('tabelaApontamentos');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    
    const dataInicio = document.getElementById('filtroDataInicio').value || new Date().toISOString().split('T')[0];
    const dataFim = document.getElementById('filtroDataFim').value || dataInicio;
    link.download = `Relatorio_Produtividade_${dataInicio}_a_${dataFim}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

/**
 * Exporta uma tabela para formato CSV string
 */
function exportarTabelaCSV(tabelaId) {
    const tabela = document.getElementById(tabelaId);
    if (!tabela) return '';
    
    let csv = '';
    
    // Headers
    const headers = [];
    tabela.querySelectorAll('thead th').forEach(th => {
        let texto = th.innerText.replace(/"/g, '""');
        headers.push('"' + texto + '"');
    });
    csv += headers.join(';') + '\n';
    
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
                csv += cols.join(';') + '\n';
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
            csv += cols.join(';') + '\n';
        });
    }
    
    return csv;
}

/**
 * Exporta tabela individual para Excel
 */
function exportarTabelaExcel(tabelaId, nomeArquivo) {
    const csv = exportarTabelaCSV(tabelaId);
    if (!csv) { 
        alert('Tabela não encontrada'); 
        return; 
    }
    
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nomeArquivo + '_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

/**
 * Exibe mensagem de erro
 */
function exibirMensagemErro(mensagem) {
    alert('❌ ' + mensagem);
}
