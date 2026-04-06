/**
 * Comissão Extrato Analítico - JavaScript
 */

let dadosExtrato = null;

/**
 * Mostra overlay de loading na tela
 */
function mostrarLoading(mensagem = 'Gerando extrato...') {
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
    if (overlay) {
        overlay.remove();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    definirDatasPadrao();
    carregarCentrosTrabalho();
});

/**
 * Define datas padrão (mês atual)
 */
function definirDatasPadrao() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    document.getElementById('filtroDataInicio').value = formatarDataInput(primeiroDia);
    document.getElementById('filtroDataFim').value = formatarDataInput(ultimoDia);
}

function formatarDataInput(date) {
    const ano = date.getFullYear();
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const dia = String(date.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

/**
 * Carrega centros de trabalho para o select
 */
function carregarCentrosTrabalho() {
    fetch('/comissao-api-centros-vinculados')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroCentro');
                select.innerHTML = '<option value="">Selecione um centro...</option>';
                data.data.forEach(centro => {
                    select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                });
                inicializarSelect2Centro();
            }
        })
        .catch(error => console.error('Erro ao carregar centros:', error));
}

/**
 * Inicializa Select2 para Centro de Trabalho
 */
function inicializarSelect2Centro() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#filtroCentro').select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            placeholder: 'Digite código ou nome...',
            allowClear: true,
            width: '100%'
        });
    }
}

/**
 * Carrega o extrato analítico
 */
function carregarExtrato() {
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    const centroTrabId = document.getElementById('filtroCentro').value;
    
    if (!dataInicio || !dataFim) {
        exibirMensagemErro('Informe o período');
        return;
    }
    
    if (!centroTrabId) {
        exibirMensagemErro('Selecione um centro de trabalho');
        return;
    }
    
    mostrarLoading('Gerando extrato analítico...');
    
    const params = new URLSearchParams({
        dataInicio: dataInicio,
        dataFim: dataFim,
        centroTrabId: centroTrabId,
        _t: Date.now()
    });
    
    fetch(`/comissao-api-extrato-analitico?${params}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            
            if (data.success) {
                dadosExtrato = data;
                renderizarExtrato(data);
                atualizarResumo(data.resumo);
                habilitarExportacao(true);
            } else {
                exibirMensagemErro(data.message || 'Erro ao carregar extrato');
            }
        })
        .catch(error => {
            esconderLoading();
            console.error('Erro:', error);
            exibirMensagemErro('Erro de comunicação com o servidor');
        });
}

/**
 * Atualiza os cards de resumo
 */
function atualizarResumo(resumo) {
    document.getElementById('totalFuncionarios').textContent = resumo.total_funcionarios || 0;
    document.getElementById('totalPontos').textContent = formatarNumero(resumo.total_pontos || 0);
    document.getElementById('totalDiasNormais').textContent = resumo.total_dias_normais || 0;
    document.getElementById('totalDiasApoio').textContent = resumo.total_dias_apoio || 0;
    document.getElementById('totalDiasFalta').textContent = 
        (resumo.total_dias_falta_integral || 0) + (resumo.total_dias_falta_parcial || 0);
    document.getElementById('totalValorEstimado').textContent = formatarMoeda(resumo.total_valor_estimado || 0);
}

/**
 * Renderiza o extrato na tela
 */
function renderizarExtrato(data) {
    const container = document.getElementById('extratoContainer');
    
    if (!data.data || data.data.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-3">Nenhum funcionário com apontamentos no período e centro selecionados</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    data.data.forEach(func => {
        const funcionario = func.funcionario;
        const memoria = func.memoria_calculo || {};
        
        html += `
        <div class="funcionario-card">
            <div class="funcionario-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>${funcionario.codigo}</strong> - ${funcionario.nome}
                    ${func.tem_regra_especifica ? '<span class="badge bg-warning ms-2">Regra Específica</span>' : ''}
                </div>
                <div class="text-end">
                    <span class="me-3">Centro: ${funcionario.centro_trabalho}</span>
                    <span class="me-3">Recurso: ${funcionario.recurso || '-'}</span>
                    <span>Tipo: ${funcionario.tipo_vinculo === 'A' ? 'Apoio' : 'Normal'}</span>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-striped table-extrato mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia</th>
                            <th class="text-end">Pontos Brutos</th>
                            <th class="text-end">Pontos Aplicados</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Valor Ponto</th>
                            <th class="text-end">Valor Comissão</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        func.dias.forEach(dia => {
            const statusClass = getStatusClass(dia.status);
            const statusLabel = getStatusLabel(dia.status);
            let observacao = dia.motivo_falta || '';
            
            if (dia.tipo_falta === 'I') {
                observacao = 'Falta Integral';
            } else if (dia.tipo_falta === 'P') {
                observacao = 'Falta Parcial (50%)';
            } else if (dia.status === 'APOIO' && dia.tipo_calculo) {
                observacao = dia.tipo_calculo === 'M' ? 'Média do Centro' : 'Total do Centro';
            }
            
            html += `
                <tr>
                    <td>${dia.data_formatada}</td>
                    <td>${dia.dia_semana}</td>
                    <td class="text-end">${formatarNumero(dia.pontos_brutos)}</td>
                    <td class="text-end">${formatarNumero(dia.pontos_aplicados)}</td>
                    <td class="text-center">
                        <span class="badge badge-status ${statusClass}">${statusLabel}</span>
                    </td>
                    <td class="text-end">${formatarNumero4(dia.valor_ponto || 0)}</td>
                    <td class="text-end">${formatarMoeda(dia.valor_comissao || 0)}</td>
                    <td>${observacao}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
            <div class="funcionario-footer">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>Total Pontos:</strong> ${formatarNumero(func.total_pontos)}
                        ${func.dias_normais > 0 ? `<span class="ms-2 text-muted">(Normais: ${formatarNumero(func.pontos_normais || 0)} / ${func.dias_normais} dias)</span>` : ''}
                        ${func.dias_apoio > 0 ? `<span class="ms-2 text-info">(Apoio: ${formatarNumero(func.pontos_apoio || 0)} / ${func.dias_apoio} dias)</span>` : ''}
                    </div>
                    <div>
                        <strong>Valor Estimado:</strong> <span class="text-success fw-bold">${formatarMoeda(func.valor_estimado)}</span>
                    </div>
                </div>
                <div class="small text-muted border-top pt-2">
                    <strong>Memória de Cálculo:</strong>
                    ${memoria.faixa_normal ? `<span class="ms-2">Faixa Normal: ${memoria.faixa_normal} (R$ ${formatarNumero4(memoria.valor_ponto_normal)}/pt)</span>` : ''}
                    ${memoria.faixa_apoio && memoria.faixa_apoio !== memoria.faixa_normal ? `<span class="ms-2">| Faixa Apoio: ${memoria.faixa_apoio} (R$ ${formatarNumero4(memoria.valor_ponto_apoio)}/pt)</span>` : ''}
                    ${func.regra_descricao ? `<span class="ms-2 text-warning">Regra: ${func.regra_descricao}</span>` : ''}
                </div>
            </div>
        </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Retorna a classe CSS para o status
 */
function getStatusClass(status) {
    const classes = {
        'NORMAL': 'status-normal',
        'APOIO': 'status-apoio',
        'FALTA_INTEGRAL': 'status-falta-integral',
        'FALTA_PARCIAL': 'status-falta-parcial'
    };
    return classes[status] || 'bg-secondary';
}

/**
 * Retorna o label para o status
 */
function getStatusLabel(status) {
    const labels = {
        'NORMAL': 'Normal',
        'APOIO': 'Apoio',
        'FALTA_INTEGRAL': 'Falta Int.',
        'FALTA_PARCIAL': 'Falta Parc.'
    };
    return labels[status] || status;
}

/**
 * Formata número com 2 casas decimais
 */
function formatarNumero(valor) {
    return parseFloat(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Formata número com 4 casas decimais
 */
function formatarNumero4(valor) {
    return parseFloat(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
}

/**
 * Formata valor em moeda
 */
function formatarMoeda(valor) {
    return parseFloat(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

/**
 * Exibe mensagem de erro
 */
function exibirMensagemErro(mensagem) {
    alert(mensagem);
}

/**
 * Habilita/desabilita botões de exportação
 */
function habilitarExportacao(habilitar) {
    document.getElementById('btnExportarCSV').disabled = !habilitar;
    document.getElementById('btnExportarExcel').disabled = !habilitar;
}

/**
 * Exportar para CSV
 */
function exportarCSV() {
    if (!dadosExtrato || !dadosExtrato.data || dadosExtrato.data.length === 0) {
        exibirMensagemErro('Nenhum dado para exportar');
        return;
    }
    
    let csv = 'Data;Dia;Funcionario;Codigo;Centro Trabalho;Recurso;Pontos Brutos;Pontos Aplicados;Valor Ponto;Valor Comissao;Status;Tipo Calculo;Observacao\n';
    
    dadosExtrato.data.forEach(func => {
        const funcionario = func.funcionario;
        const memoria = func.memoria_calculo || {};
        
        func.dias.forEach(dia => {
            const status = getStatusLabel(dia.status);
            let tipoCalc = '';
            if (dia.status === 'APOIO' && dia.tipo_calculo) {
                tipoCalc = dia.tipo_calculo === 'M' ? 'Média' : 'Total';
            }
            let observacao = dia.motivo_falta || '';
            if (dia.tipo_falta === 'I') observacao = 'Falta Integral';
            else if (dia.tipo_falta === 'P') observacao = 'Falta Parcial (50%)';
            
            csv += `${dia.data_formatada};${dia.dia_semana};${funcionario.nome};${funcionario.codigo};${funcionario.centro_trabalho};${funcionario.recurso || ''};${dia.pontos_brutos};${dia.pontos_aplicados};${dia.valor_ponto || 0};${dia.valor_comissao || 0};${status};${tipoCalc};${observacao}\n`;
        });
        
        // Linha de total do funcionário
        csv += `;;TOTAL ${funcionario.nome};;Dias Normais: ${func.dias_normais || 0};Dias Apoio: ${func.dias_apoio || 0};;${func.total_pontos};;;R$ ${func.valor_estimado.toFixed(2)};;\n`;
    });
    
    // Download
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `extrato-analitico-${document.getElementById('filtroDataInicio').value}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Exportar para Excel
 */
function exportarExcel() {
    if (!dadosExtrato || !dadosExtrato.data || dadosExtrato.data.length === 0) {
        exibirMensagemErro('Nenhum dado para exportar');
        return;
    }
    
    if (typeof XLSX === 'undefined') {
        exibirMensagemErro('Biblioteca de exportação não carregada');
        return;
    }
    
    // Preparar dados
    const dados = [];
    
    // Cabeçalho
    dados.push(['Data', 'Dia', 'Código', 'Funcionário', 'Centro Trabalho', 'Recurso', 'Pontos Brutos', 'Pontos Aplicados', 'Valor Ponto', 'Valor Comissão', 'Status', 'Tipo Cálculo', 'Observação']);
    
    dadosExtrato.data.forEach(func => {
        const funcionario = func.funcionario;
        const memoria = func.memoria_calculo || {};
        
        func.dias.forEach(dia => {
            const status = getStatusLabel(dia.status);
            let tipoCalc = '';
            if (dia.status === 'APOIO' && dia.tipo_calculo) {
                tipoCalc = dia.tipo_calculo === 'M' ? 'Média' : 'Total';
            }
            let observacao = dia.motivo_falta || '';
            if (dia.tipo_falta === 'I') observacao = 'Falta Integral';
            else if (dia.tipo_falta === 'P') observacao = 'Falta Parcial (50%)';
            
            dados.push([
                dia.data_formatada,
                dia.dia_semana,
                funcionario.codigo,
                funcionario.nome,
                funcionario.centro_trabalho,
                funcionario.recurso || '',
                dia.pontos_brutos,
                dia.pontos_aplicados,
                dia.valor_ponto || 0,
                dia.valor_comissao || 0,
                status,
                tipoCalc,
                observacao
            ]);
        });
        
        // Linha de total do funcionário
        dados.push([
            '', '', '', `TOTAL ${funcionario.nome}`,
            `Dias Normais: ${func.dias_normais || 0}`,
            `Dias Apoio: ${func.dias_apoio || 0}`,
            '', func.total_pontos, '', func.valor_estimado,
            '', '', `Faixa: ${memoria.faixa_normal || 'N/A'}`
        ]);
        dados.push([]); // Linha em branco
    });
    
    // Criar workbook
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(dados);
    
    // Definir larguras das colunas
    ws['!cols'] = [
        { wch: 12 }, // Data
        { wch: 6 },  // Dia
        { wch: 10 }, // Código
        { wch: 35 }, // Funcionário
        { wch: 25 }, // Centro
        { wch: 20 }, // Recurso
        { wch: 14 }, // Pontos Brutos
        { wch: 16 }, // Pontos Aplicados
        { wch: 12 }, // Valor Ponto
        { wch: 14 }, // Valor Comissão
        { wch: 12 }, // Status
        { wch: 12 }, // Tipo Cálculo
        { wch: 25 }  // Observação
    ];
    
    XLSX.utils.book_append_sheet(wb, ws, 'Extrato Analítico');
    
    // Download
    XLSX.writeFile(wb, `extrato-analitico-${document.getElementById('filtroDataInicio').value}.xlsx`);
}
