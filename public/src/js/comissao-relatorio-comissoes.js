/**
 * Comissão Relatório de Comissões - JavaScript
 */

let dataTableComissoes = null;
let comissaoAtualId = null;
let dadosComissoesCarregados = [];
let filtrosAtuais = {};

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
    
    // Carregar dados dos selects
    carregarCentrosTrabalho();
});

/**
 * Define as datas padrão para o período do mês atual
 */
function definirDatasPadrao() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = formatarDataInput(primeiroDia);
    document.getElementById('filtroDataFim').value = formatarDataInput(hoje);
}

/**
 * Formata data para input date (YYYY-MM-DD)
 */
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
                select.innerHTML = '<option value="">Todos</option>';
                data.data.forEach(centro => {
                    select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                });
                // Inicializar Select2 após carregar dados
                inicializarSelect2Centro();
                inicializarSelect2Status();
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
 * Inicializa Select2 para Status
 */
function inicializarSelect2Status() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#filtroStatus').select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            placeholder: 'Selecione...',
            allowClear: true,
            width: '100%'
        });
    }
}

/**
 * Carrega o relatório
 */
function carregarRelatorio() {
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!dataInicio || !dataFim) {
        exibirMensagemErro('Informe o período');
        return;
    }
    
    // Mostrar loading
    mostrarLoading('Gerando relatório de comissões...');
    
    const filtros = {
        dataInicio: dataInicio,
        dataFim: dataFim,
        centroTrabId: document.getElementById('filtroCentro').value,
        status: document.getElementById('filtroStatus').value
    };
    filtrosAtuais = filtros;
    
    const params = new URLSearchParams();
    Object.keys(filtros).forEach(key => {
        if (filtros[key]) params.append(key, filtros[key]);
    });
    params.append('_t', Date.now());
    
    // Destruir DataTable antes de recarregar
    if (dataTableComissoes) {
        dataTableComissoes.destroy();
        dataTableComissoes = null;
    }
    
    fetch(`/comissao-api-comissoes?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            if (data.success) {
                dadosComissoesCarregados = data.comissoes || [];
                renderizarResumo(data.resumo);
                renderizarTabelaCentros(data.porCentro);
                renderizarTabelaComissoes(data.comissoes);
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
 * Renderiza o resumo
 */
function renderizarResumo(resumo) {
    if (!resumo) return;
    
    document.getElementById('totalFuncionarios').textContent = formatarNumero(resumo.TOTAL_FUNCIONARIOS || 0);
    document.getElementById('totalPontos').textContent = formatarNumero(resumo.TOTAL_PONTOS || 0, 2);
    document.getElementById('totalComissao').textContent = formatarMoeda(resumo.TOTAL_COMISSAO || 0);
    document.getElementById('pendentesAprovacao').textContent = formatarNumero(resumo.PENDENTES || 0);
}

/**
 * Renderiza tabela de resumo por centro
 */
function renderizarTabelaCentros(dados) {
    const tbody = document.getElementById('tabelaCentrosBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum dado encontrado</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const media = item.TOTAL_FUNCIONARIOS > 0 
            ? item.TOTAL_COMISSAO / item.TOTAL_FUNCIONARIOS 
            : 0;
        
        html += `
            <tr>
                <td>${item.CENTRO_TRABALHO}</td>
                <td class="text-center">${formatarNumero(item.TOTAL_FUNCIONARIOS)}</td>
                <td class="text-end">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td class="text-end"><strong>${formatarMoeda(item.TOTAL_COMISSAO)}</strong></td>
                <td class="text-end">${formatarMoeda(media)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Renderiza tabela de comissões
 */
function renderizarTabelaComissoes(dados) {
    const tbody = document.getElementById('tabelaComissoesBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">Nenhuma comissão encontrada</td></tr>';
        if (dataTableComissoes) {
            dataTableComissoes.destroy();
            dataTableComissoes = null;
        }
        atualizarTotais(0, 0);
        return;
    }
    
    let totalPontos = 0;
    let totalComissao = 0;
    
    let html = '';
    dados.forEach((item, idx) => {
        const statusClass = getStatusClass(item.STATUS);
        const statusTexto = getStatusTexto(item.STATUS);
        
        // Verificar se tem falta
        const temFalta = item.TEM_FALTA || false;
        const diasComFalta = item.DIAS_COM_FALTA || 0;
        const faltaBadge = temFalta 
            ? `<span class="badge bg-warning text-dark ms-1" title="${diasComFalta} dia(s) com falta">⚠️ ${diasComFalta} falta(s)</span>` 
            : '';
        
        // Verificar se tem dias de apoio
        const temApoio = item.TEM_APOIO || false;
        const diasApoio = item.DIAS_APOIO || 0;
        const pontosApoio = item.PONTOS_APOIO || 0;
        const tipoCalculoApoio = item.TIPO_CALCULO_APOIO || 'T';
        const labelApoio = tipoCalculoApoio === 'M' ? 'MÉDIA' : 'TOTAL';
        const apoioBadge = temApoio 
            ? `<span class="badge bg-info text-white ms-1" title="Pontos (${labelApoio}): ${formatarNumero(pontosApoio, 2)} (${diasApoio} dia(s))">📊 ${labelApoio}</span>` 
            : '';
        
        const rowClass = temFalta ? 'table-warning' : '';
        
        totalPontos += parseFloat(item.TOTAL_PONTOS || 0);
        totalComissao += parseFloat(item.VALOR_COMISSAO || 0);
        
        html += `
            <tr class="${rowClass}">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input checkbox-comissao" 
                           value="${idx}" 
                           ${item.STATUS !== 'P' ? 'disabled' : ''}>
                </td>
                <td>${item.PERIODO}</td>
                <td>${item.CODIGO_FUNC}</td>
                <td>
                    <strong>${item.NOME_FUNC}</strong>${faltaBadge}${apoioBadge}
                </td>
                <td>${item.CENTRO_TRABALHO || '-'}</td>
                <td class="text-end">${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td>${item.FAIXA_DESCRICAO || '-'}</td>
                <td class="text-end"><strong>${formatarMoeda(item.VALOR_COMISSAO)}</strong></td>
                <td class="text-center"><span class="status-badge ${statusClass}">${statusTexto}</span></td>
                <td class="text-center">
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-info" onclick="verDetalhes(${idx})" title="Detalhes">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTable();
    atualizarTotais(totalPontos, totalComissao);
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
    if (dataTableComissoes) {
        dataTableComissoes.destroy();
    }
    
    dataTableComissoes = $('#tabelaComissoes').DataTable({
        language: {
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
        },
        lengthChange: false,
        pageLength: 20,
        order: [[7, 'desc']],
        columnDefs: [
            { orderable: false, targets: [0, 9] }
        ]
    });
}

/**
 * Toggle seleção de todos os checkboxes
 */
function toggleSelecaoTodos() {
    const selecionarTodos = document.getElementById('selecionarTodos').checked;
    document.querySelectorAll('.checkbox-comissao:not(:disabled)').forEach(cb => {
        cb.checked = selecionarTodos;
    });
}

/**
 * Obtém dados das comissões selecionadas
 */
function obterSelecionados() {
    const comissoes = [];
    document.querySelectorAll('.checkbox-comissao:checked').forEach(cb => {
        const idx = parseInt(cb.value);
        const item = dadosComissoesCarregados[idx];
        if (item) {
            comissoes.push({
                ID_COMISSAO: item.ID_COMISSAO || null,
                FUNC_ID: item.FUNC_ID || null,
                FAIXA_ID: item.FAIXA_ID || null,
                DATA_INICIO: filtrosAtuais.dataInicio,
                DATA_FIM: filtrosAtuais.dataFim,
                TOTAL_PONTOS: item.TOTAL_PONTOS || 0,
                VALOR_COMISSAO: item.VALOR_COMISSAO || 0
            });
        }
    });
    return comissoes;
}

/**
 * Processar comissões (calcular para o período)
 */
function processarComissoes() {
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!dataInicio || !dataFim) {
        exibirMensagemErro('Informe o período');
        return;
    }
    
    if (!confirm('Deseja processar as comissões para o período selecionado?')) return;
    
    document.getElementById('btnProcessar').disabled = true;
    document.getElementById('btnProcessar').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processando...';
    mostrarLoading('Processando comissões... Isso pode levar alguns minutos.');
    
    fetch('/comissao-api-processar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            dataInicio: dataInicio,
            dataFim: dataFim,
            centroTrabId: document.getElementById('filtroCentro').value
        })
    })
    .then(response => response.json())
    .then(data => {
        esconderLoading();
        document.getElementById('btnProcessar').disabled = false;
        document.getElementById('btnProcessar').innerHTML = '<i class="bi bi-calculator"></i> Processar';
        
        if (data.success) {
            exibirMensagemSucesso(`Processadas ${data.processadas} comissões`);
            carregarRelatorio();
        } else {
            exibirMensagemErro(data.message || 'Erro ao processar comissões');
        }
    })
    .catch(error => {
        esconderLoading();
        document.getElementById('btnProcessar').disabled = false;
        document.getElementById('btnProcessar').innerHTML = '<i class="bi bi-calculator"></i> Processar';
        console.error('Erro ao processar:', error);
        exibirMensagemErro('Erro ao processar comissões');
    });
}

/**
 * Ver detalhes de uma comissão
 */
function verDetalhes(idx) {
    const item = dadosComissoesCarregados[idx];
    if (!item) {
        exibirMensagemErro('Dados não encontrados');
        return;
    }
    comissaoAtualId = idx;

    // Mostrar loading
    mostrarLoading('Carregando detalhes...');

    // Buscar apontamentos do funcionário no período
    const params = new URLSearchParams({
        dataInicio: filtrosAtuais.dataInicio,
        dataFim: filtrosAtuais.dataFim,
        funcId: item.FUNC_ID,
        codigoFunc: item.CODIGO_FUNC,
        nomeFunc: item.NOME_FUNC,
        emprId: filtrosAtuais.emprId
    });
    if (filtrosAtuais.centroTrabId) params.append('centroTrabId', filtrosAtuais.centroTrabId);

    fetch(`/comissao-api-comissao-detalhes?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            esconderLoading();
            const detalhes = {
                NOME_FUNC: item.NOME_FUNC,
                CODIGO_FUNC: item.CODIGO_FUNC,
                CENTRO_TRABALHO: item.CENTRO_TRABALHO,
                VALOR_COMISSAO: item.VALOR_COMISSAO,
                TOTAL_PONTOS: item.TOTAL_PONTOS,
                STATUS: item.STATUS,
                PERIODO: item.PERIODO,
                FAIXA_DESCRICAO: item.FAIXA_DESCRICAO,
                apontamentos: data.success ? (data.apontamentos || []) : []
            };
            renderizarDetalhes(detalhes);
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            modal.show();
        })
        .catch(error => {
            esconderLoading();
            console.error('Erro ao carregar apontamentos:', error);
            // Mostrar modal mesmo sem apontamentos
            const detalhes = {
                NOME_FUNC: item.NOME_FUNC,
                CODIGO_FUNC: item.CODIGO_FUNC,
                CENTRO_TRABALHO: item.CENTRO_TRABALHO,
                VALOR_COMISSAO: item.VALOR_COMISSAO,
                TOTAL_PONTOS: item.TOTAL_PONTOS,
                STATUS: item.STATUS,
                PERIODO: item.PERIODO,
                FAIXA_DESCRICAO: item.FAIXA_DESCRICAO,
                apontamentos: []
            };
            renderizarDetalhes(detalhes);
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            modal.show();
        });
}

/**
 * Renderiza detalhes no modal
 */
function renderizarDetalhes(dados) {
    const container = document.getElementById('modalDetalhesBody');
    const btnAprovar = document.getElementById('btnAprovarComissao');
    const btnCancelar = document.getElementById('btnCancelarComissao');
    
    // Mostrar/ocultar botões baseado no status
    if (dados.STATUS === 'P') {
        btnAprovar.style.display = 'inline-block';
        btnCancelar.style.display = 'inline-block';
    } else {
        btnAprovar.style.display = 'none';
        btnCancelar.style.display = 'none';
    }
    
    const statusClass = getStatusClass(dados.STATUS);
    const statusTexto = getStatusTexto(dados.STATUS);
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bi bi-person"></i> Funcionário</h6>
                    <p><strong>${dados.NOME_FUNC}</strong><br>
                    Código: ${dados.CODIGO_FUNC}<br>
                    Centro: ${dados.CENTRO_TRABALHO || '-'}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="bi bi-cash"></i> Comissão</h6>
                    <p>
                        <strong style="font-size: 1.5rem; color: #198754;">${formatarMoeda(dados.VALOR_COMISSAO)}</strong><br>
                        Total Pontos: ${formatarNumero(dados.TOTAL_PONTOS, 2)}<br>
                        <span class="status-badge ${statusClass}">${statusTexto}</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <small class="text-muted">Período</small>
                <p class="mb-0">${dados.PERIODO}</p>
            </div>
            <div class="col-md-6">
                <small class="text-muted">Faixa Aplicada</small>
                <p class="mb-0">${dados.FAIXA_DESCRICAO || '-'}</p>
            </div>
        </div>
        
        <hr>
        
        <h6><i class="bi bi-list-task"></i> Apontamentos do Período</h6>
        <div class="table-responsive">
        <table class="table table-sm table-striped" style="font-size: 0.8rem;">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>Máscara</th>
                    <th class="text-center" style="white-space:nowrap;">Recurso</th>
                    <th class="text-center" style="white-space:nowrap;">Qtd</th>
                    <th class="text-end" style="white-space:nowrap;">Pts/Un</th>
                    <th class="text-end" style="white-space:nowrap;">Total Pts</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    if (dados.apontamentos && dados.apontamentos.length > 0) {
        dados.apontamentos.forEach(ap => {
            html += `
                <tr>
                    <td>${ap.CODIGO || '-'}</td>
                    <td>${ap.DESCRICAO || '-'}</td>
                    <td>${ap.MASCARA || '-'}</td>
                    <td class="text-center" style="white-space:nowrap;">${ap.RECURSO || '-'}</td>
                    <td class="text-center">${formatarNumero(ap.QUANTIDADE)}</td>
                    <td class="text-end">${formatarNumero(ap.PONTOS_UP, 2)}</td>
                    <td class="text-end">${formatarNumero(ap.PONTOS, 2)}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="7" class="text-center">Sem apontamentos detalhados</td></tr>';
    }
    
    html += '</tbody></table></div>';
    
    container.innerHTML = html;
}

/**
 * Aprovar comissão individual
 */
function aprovarComissao(idx) {
    if (!confirm('Deseja aprovar esta comissão?')) return;
    const item = dadosComissoesCarregados[idx];
    if (!item) return;
    const comissao = {
        ID_COMISSAO: item.ID_COMISSAO || null,
        FUNC_ID: item.FUNC_ID || null,
        FAIXA_ID: item.FAIXA_ID || null,
        DATA_INICIO: filtrosAtuais.dataInicio,
        DATA_FIM: filtrosAtuais.dataFim,
        TOTAL_PONTOS: item.TOTAL_PONTOS || 0,
        VALOR_COMISSAO: item.VALOR_COMISSAO || 0
    };
    executarAcaoComissao([comissao], 'aprovar');
}

/**
 * Cancelar comissão individual
 */
function cancelarComissao(idx) {
    if (!confirm('Deseja cancelar esta comissão?')) return;
    const item = dadosComissoesCarregados[idx];
    if (!item) return;
    const comissao = {
        ID_COMISSAO: item.ID_COMISSAO || null,
        FUNC_ID: item.FUNC_ID || null,
        FAIXA_ID: item.FAIXA_ID || null,
        DATA_INICIO: filtrosAtuais.dataInicio,
        DATA_FIM: filtrosAtuais.dataFim,
        TOTAL_PONTOS: item.TOTAL_PONTOS || 0,
        VALOR_COMISSAO: item.VALOR_COMISSAO || 0
    };
    executarAcaoComissao([comissao], 'cancelar');
}

/**
 * Aprovar comissão do modal
 */
function aprovarComissaoModal() {
    if (comissaoAtualId === null) return;
    if (!confirm('Deseja aprovar esta comissão?')) return;
    const item = dadosComissoesCarregados[comissaoAtualId];
    if (!item) return;
    const comissao = {
        ID_COMISSAO: item.ID_COMISSAO || null,
        FUNC_ID: item.FUNC_ID || null,
        FAIXA_ID: item.FAIXA_ID || null,
        DATA_INICIO: filtrosAtuais.dataInicio,
        DATA_FIM: filtrosAtuais.dataFim,
        TOTAL_PONTOS: item.TOTAL_PONTOS || 0,
        VALOR_COMISSAO: item.VALOR_COMISSAO || 0
    };
    executarAcaoComissao([comissao], 'aprovar', () => {
        bootstrap.Modal.getInstance(document.getElementById('modalDetalhes')).hide();
    });
}

/**
 * Cancelar comissão do modal
 */
function cancelarComissaoModal() {
    if (comissaoAtualId === null) return;
    if (!confirm('Deseja cancelar esta comissão?')) return;
    const item = dadosComissoesCarregados[comissaoAtualId];
    if (!item) return;
    const comissao = {
        ID_COMISSAO: item.ID_COMISSAO || null,
        FUNC_ID: item.FUNC_ID || null,
        FAIXA_ID: item.FAIXA_ID || null,
        DATA_INICIO: filtrosAtuais.dataInicio,
        DATA_FIM: filtrosAtuais.dataFim,
        TOTAL_PONTOS: item.TOTAL_PONTOS || 0,
        VALOR_COMISSAO: item.VALOR_COMISSAO || 0
    };
    executarAcaoComissao([comissao], 'cancelar', () => {
        bootstrap.Modal.getInstance(document.getElementById('modalDetalhes')).hide();
    });
}

/**
 * Aprovar selecionados
 */
function aprovarSelecionados() {
    const comissoes = obterSelecionados();
    if (comissoes.length === 0) {
        exibirMensagemErro('Selecione ao menos uma comissão');
        return;
    }
    if (!confirm(`Deseja aprovar ${comissoes.length} comissão(ões)?`)) return;
    executarAcaoComissao(comissoes, 'aprovar');
}

/**
 * Cancelar selecionados
 */
function cancelarSelecionados() {
    const comissoes = obterSelecionados();
    if (comissoes.length === 0) {
        exibirMensagemErro('Selecione ao menos uma comissão');
        return;
    }
    if (!confirm(`Deseja cancelar ${comissoes.length} comissão(ões)?`)) return;
    executarAcaoComissao(comissoes, 'cancelar');
}

/**
 * Executa ação de aprovar ou cancelar
 */
function executarAcaoComissao(comissoes, acao, callback) {
    const endpoint = acao === 'aprovar' ? '/comissao-api-aprovar' : '/comissao-api-cancelar';
    
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ comissoes: comissoes })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            exibirMensagemSucesso(data.message || `${comissoes.length} comissão(ões) ${acao === 'aprovar' ? 'aprovada(s)' : 'cancelada(s)'}`);
            carregarRelatorio();
            if (callback) callback();
        } else {
            exibirMensagemErro(data.message || `Erro ao ${acao} comissão(ões)`);
        }
    })
    .catch(error => {
        console.error(`Erro ao ${acao}:`, error);
        exibirMensagemErro(`Erro ao ${acao} comissão(ões)`);
    });
}

/**
 * Retorna classe CSS do status
 */
function getStatusClass(status) {
    switch (status) {
        case 'P': return 'status-pendente';
        case 'A': return 'status-aprovado';
        case 'C': return 'status-cancelado';
        default: return '';
    }
}

/**
 * Retorna texto do status
 */
function getStatusTexto(status) {
    switch (status) {
        case 'P': return 'Pendente';
        case 'A': return 'Aprovado';
        case 'C': return 'Cancelado';
        default: return status;
    }
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
 * Formata moeda
 */
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor || 0);
}

/**
 * Formata data
 */
function formatarData(data) {
    if (!data) return '-';
    // Adiciona T12:00:00 para evitar problemas de timezone
    if (typeof data === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data)) {
        data = data + 'T12:00:00';
    }
    return new Date(data).toLocaleDateString('pt-BR');
}

/**
 * Formata data e hora
 */
function formatarDataHora(data) {
    if (!data) return '-';
    return new Date(data).toLocaleString('pt-BR');
}

/**
 * Exportar PDF
 */
function exportarPDF() {
    alert('Funcionalidade de exportação PDF será implementada');
}

/**
 * Exportar Excel
 */
function exportarExcel() {
    let csvContent = '\uFEFF';
    
    // Seção: Resumo por Centro
    csvContent += 'RESUMO POR CENTRO DE TRABALHO\n';
    csvContent += exportarTabelaCSV('tabelaCentros');
    csvContent += '\n\n';
    
    // Seção: Detalhamento de Comissões
    csvContent += 'DETALHAMENTO DE COMISSÕES\n';
    csvContent += exportarTabelaCSV('tabelaComissoes');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    
    const dataInicio = document.getElementById('filtroDataInicio').value || '';
    const dataFim = document.getElementById('filtroDataFim').value || '';
    link.download = `Relatorio_Comissoes_${dataInicio}_a_${dataFim}.csv`;
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
    const headers = [];
    tabela.querySelectorAll('thead th').forEach(th => {
        let texto = th.innerText.replace(/"/g, '""');
        headers.push('"' + texto + '"');
    });
    csv += headers.join(';') + '\n';
    
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
 * Exibe mensagem de erro
 */
function exibirMensagemErro(mensagem) {
    alert('❌ ' + mensagem);
}

/**
 * Exibe mensagem de sucesso
 */
function exibirMensagemSucesso(mensagem) {
    alert('✅ ' + mensagem);
}
