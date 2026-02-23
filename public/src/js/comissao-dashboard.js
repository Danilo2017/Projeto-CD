/**
 * Comissão Dashboard - JavaScript
 */

let chartCentros = null;
let chartRanking = null;

document.addEventListener('DOMContentLoaded', function() {
    // Definir data padrão como o mês atual
    definirDatasPadrao();
    
    // Carregar filiais primeiro
    carregarFiliais();
    
    // NÃO carregar dados automaticamente - aguardar usuário selecionar filial e clicar em Filtrar
    // carregarCentrosTrabalho();
    // carregarRecursos();
    // carregarDashboard();
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
 * Carrega filiais/empresas para o select
 */
function carregarFiliais() {
    const select = document.getElementById('filtroFilial');
    select.innerHTML = '<option value="">Carregando filiais...</option>';
    
    fetch('/comissao-api-filiais', {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Selecione a Filial</option>';
                data.data.forEach(filial => {
                    const nome = filial.NOME_FANTASIA || filial.RAZAO_SOCIAL;
                    select.innerHTML += `<option value="${filial.ID}">${filial.CODIGO} - ${nome}</option>`;
                });
                // Pré-selecionar a empresa da sessão
                const sessaoEmpresaId = document.getElementById('sessaoEmpresaId')?.value;
                if (sessaoEmpresaId) {
                    select.value = sessaoEmpresaId;
                    atualizarFiltrosPorFilial();
                    carregarDashboard();
                }
            } else {
                select.innerHTML = '<option value="">Erro ao carregar</option>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar filiais:', error);
            select.innerHTML = '<option value="">Erro ao carregar filiais</option>';
        });
}

/**
 * Atualiza filtros quando filial é alterada
 */
function atualizarFiltrosPorFilial() {
    const emprId = document.getElementById('filtroFilial').value;
    
    if (!emprId) {
        // Se não tem empresa, limpa os selects
        document.getElementById('filtroCentro').innerHTML = '<option value="">Selecione a Filial primeiro</option>';
        document.getElementById('filtroRecurso').innerHTML = '<option value="">Selecione a Filial primeiro</option>';
        return;
    }
    
    carregarCentrosTrabalho(emprId);
    carregarRecursos(emprId);
}

/**
 * Carrega centros de trabalho para o select
 */
function carregarCentrosTrabalho(emprId = null) {
    if (!emprId) {
        document.getElementById('filtroCentro').innerHTML = '<option value="">Selecione a Filial primeiro</option>';
        return;
    }
    
    const select = document.getElementById('filtroCentro');
    select.innerHTML = '<option value="">Carregando...</option>';
    
    fetch(`/comissao-api-centros?empr_id=${emprId}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada ou erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Todos</option>';
                data.data.forEach(centro => {
                    select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                });
            } else {
                select.innerHTML = '<option value="">Erro: ' + (data.error || 'Falha') + '</option>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar centros:', error);
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

/**
 * Carrega recursos para o select
 */
function carregarRecursos(emprId = null) {
    if (!emprId) {
        document.getElementById('filtroRecurso').innerHTML = '<option value="">Selecione a Filial primeiro</option>';
        return;
    }
    
    const select = document.getElementById('filtroRecurso');
    select.innerHTML = '<option value="">Carregando...</option>';
    
    fetch(`/comissao-api-recursos?empr_id=${emprId}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada ou erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Todos</option>';
                data.data.forEach(recurso => {
                    select.innerHTML += `<option value="${recurso.ID}">${recurso.COD_MAQUINA} - ${recurso.DESCRICAO}</option>`;
                });
            } else {
                select.innerHTML = '<option value="">Erro: ' + (data.error || 'Falha') + '</option>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar recursos:', error);
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

/**
 * Carrega todos os dados do dashboard
 */
function carregarDashboard() {
    const filtros = obterFiltros();
    
    // Validar se filial foi selecionada
    if (!filtros.emprId) {
        alert('Por favor, selecione uma filial antes de filtrar.');
        return;
    }
    
    // Carregar resumo geral
    carregarResumoGeral(filtros);
    
    // Carregar resumo por centro
    carregarResumoPorCentro(filtros);
    
    // Carregar ranking de funcionários
    carregarRankingFuncionarios(filtros);
}

/**
 * Obtém os filtros selecionados
 */
function obterFiltros() {
    return {
        emprId: document.getElementById('filtroFilial').value,
        dataInicio: document.getElementById('filtroDataInicio').value,
        dataFim: document.getElementById('filtroDataFim').value,
        centroTrabId: document.getElementById('filtroCentro').value,
        recursoId: document.getElementById('filtroRecurso').value
    };
}

/**
 * Monta query string a partir dos filtros
 */
function montarQueryString(filtros) {
    const params = new URLSearchParams();
    Object.keys(filtros).forEach(key => {
        if (filtros[key]) {
            params.append(key, filtros[key]);
        }
    });
    return params.toString();
}

/**
 * Carrega o resumo geral
 */
function carregarResumoGeral(filtros) {
    fetch(`/comissao-api-resumo?${montarQueryString(filtros)}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Os dados estão em data.data.totais
                const totais = data.data.totais || data.data;
                document.getElementById('totalApontamentos').textContent = 
                    formatarNumero(totais.total_apontamentos || totais.TOTAL_APONTAMENTOS || 0);
                document.getElementById('totalPontos').textContent = 
                    formatarNumero(totais.total_pontos || totais.TOTAL_PONTOS || 0, 2);
                document.getElementById('totalFuncionarios').textContent = 
                    formatarNumero(totais.total_funcionarios || totais.TOTAL_FUNCIONARIOS || 0);
                document.getElementById('comissaoEstimada').textContent = 
                    formatarMoeda(totais.comissao_estimada || totais.COMISSAO_ESTIMADA || 0);
                
                console.log('Resumo carregado:', totais);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar resumo:', error);
            exibirMensagemErro('Erro ao carregar resumo geral');
        });
}

/**
 * Carrega resumo por centro de trabalho
 */
function carregarResumoPorCentro(filtros) {
    fetch(`/comissao-api-resumo-centro?${montarQueryString(filtros)}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderizarTabelaCentros(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar resumo por centro:', error);
        });
}

/**
 * Renderiza a tabela de resumo por centro
 */
function renderizarTabelaCentros(dados) {
    const tbody = document.getElementById('tabelaCentrosBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum dado encontrado</td></tr>';
        return;
    }
    
    console.log('Centros dados recebidos:', dados);
    
    // Calcular total geral para percentual
    const totalGeral = dados.reduce((sum, c) => sum + (parseFloat(c.TOTAL_QUANTIDADE || c.TOTAL_PONTOS) || 0), 0);
    
    let html = '';
    dados.forEach(centro => {
        // Adaptar para os nomes das colunas reais do banco
        const descricao = centro.DESC_CENTRO || centro.DESCRICAO || '-';
        const funcionarios = centro.QTD_FUNCIONARIOS || centro.TOTAL_FUNCIONARIOS || 0;
        const apontamentos = centro.QTD_APONTAMENTOS || centro.TOTAL_APONTAMENTOS || 0;
        const pontos = centro.TOTAL_PONTOS || 0;
        const quantidade = centro.TOTAL_QUANTIDADE || pontos || 0;
        
        const percentual = totalGeral > 0 
            ? (quantidade / totalGeral * 100).toFixed(1) 
            : 0;
        
        html += `
            <tr>
                <td>${descricao}</td>
                <td class="text-center">${funcionarios}</td>
                <td class="text-center">${formatarNumero(apontamentos)}</td>
                <td class="text-end">${formatarNumero(pontos, 2)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress-bar-custom flex-grow-1">
                            <div class="progress ${getProgressBarClass(percentual)}" 
                                 style="width: ${percentual}%"></div>
                        </div>
                        <span class="small">${percentual}%</span>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Carrega ranking de funcionários
 */
function carregarRankingFuncionarios(filtros) {
    fetch(`/comissao-api-ranking?${montarQueryString(filtros)}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok || response.redirected) {
                throw new Error('Sessão expirada');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderizarTabelaRanking(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar ranking:', error);
        });
}

/**
 * Renderiza a tabela de ranking
 */
function renderizarTabelaRanking(dados) {
    const tbody = document.getElementById('tabelaRankingBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum dado encontrado</td></tr>';
        return;
    }
    
    console.log('Ranking dados recebidos:', dados);
    
    let html = '';
    dados.forEach((func, index) => {
        const posicao = index + 1;
        const badgeClass = posicao === 1 ? 'gold' : posicao === 2 ? 'silver' : posicao === 3 ? 'bronze' : 'normal';
        
        // Adaptar para os nomes das colunas reais do banco
        const nome = func.NOME_FUNC || func.NOME || '-';
        const codigo = func.COD_FUNC || func.CODIGO || '-';
        const centro = func.DESC_CENTRO || func.CENTRO_TRABALHO || '-';
        const pontos = func.TOTAL_PONTOS || 0;
        const apontamentos = func.QTD_APONTAMENTOS || func.TOTAL_APONTAMENTOS || 0;
        const comissao = func.COMISSAO_ESTIMADA || 0;
        
        html += `
            <tr>
                <td><span class="ranking-badge ${badgeClass}">${posicao}</span></td>
                <td>
                    <strong>${nome}</strong>
                    <br><small class="text-muted">Cód: ${codigo}</small>
                </td>
                <td>${centro}</td>
                <td class="text-end">${formatarNumero(pontos, 2)}</td>
                <td class="text-center">${formatarNumero(apontamentos)}</td>
                <td class="text-end">${formatarMoeda(comissao)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Abre modal de simulação de comissões
 */
function abrirSimulacao() {
    // Carregar dados para simulação
    const filtros = obterFiltros();
    
    fetch(`/comissao-api-simular`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(filtros)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderizarSimulacao(data.data);
            const modal = new bootstrap.Modal(document.getElementById('modalSimulacao'));
            modal.show();
        } else {
            exibirMensagemErro(data.message || 'Erro ao simular comissões');
        }
    })
    .catch(error => {
        console.error('Erro ao simular:', error);
        exibirMensagemErro('Erro ao simular comissões');
    });
}

/**
 * Renderiza dados da simulação no modal
 */
function renderizarSimulacao(dados) {
    const container = document.getElementById('simulacaoBody');
    
    if (!dados || dados.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">Nenhum dado para simulação</p>';
        return;
    }
    
    let totalComissao = 0;
    let html = '<table class="table table-sm"><thead><tr><th>Funcionário</th><th>Pontos</th><th>Faixa</th><th>Valor</th></tr></thead><tbody>';
    
    dados.forEach(item => {
        totalComissao += parseFloat(item.VALOR_COMISSAO || 0);
        html += `
            <tr>
                <td>${item.NOME}</td>
                <td>${formatarNumero(item.TOTAL_PONTOS, 2)}</td>
                <td>${item.FAIXA_DESCRICAO || '-'}</td>
                <td>${formatarMoeda(item.VALOR_COMISSAO || 0)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    html += `<div class="simulacao-card"><div class="simulacao-result"><span class="label">Total de Comissões:</span><span class="value destaque">${formatarMoeda(totalComissao)}</span></div></div>`;
    
    container.innerHTML = html;
}

/**
 * Formata número com separadores de milhar
 */
function formatarNumero(valor, casasDecimais = 0) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: casasDecimais,
        maximumFractionDigits: casasDecimais
    }).format(valor || 0);
}

/**
 * Formata valor como moeda
 */
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor || 0);
}

/**
 * Retorna classe da barra de progresso baseado no percentual
 */
function getProgressBarClass(percentual) {
    if (percentual >= 80) return 'bg-success';
    if (percentual >= 50) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Exibe mensagem de erro
 */
function exibirMensagemErro(mensagem) {
    alert(mensagem);
    // TODO: Implementar sistema de toast/notificações
}

/**
 * Função para exportar dashboard como PDF
 */
function exportarPDF() {
    alert('Funcionalidade de exportação PDF será implementada');
}

/**
 * Função para exportar dashboard como Excel
 * @param {string} tipo - Tipo de exportação: 'centros', 'ranking', 'simulacao' ou 'todos'
 */
function exportarExcel(tipo = 'todos') {
    let csvContent = '\uFEFF';
    let nomeArquivo = 'Dashboard';
    
    switch(tipo) {
        case 'centros':
            csvContent += 'RESUMO POR CENTRO DE TRABALHO\n';
            csvContent += exportarTabelaCSV('tabelaCentros');
            nomeArquivo = 'Resumo_Centros';
            break;
        case 'ranking':
            csvContent += 'TOP 10 - RANKING FUNCIONÁRIOS\n';
            csvContent += exportarTabelaCSV('tabelaRanking');
            nomeArquivo = 'Ranking_Funcionarios';
            break;
        case 'simulacao':
            csvContent += 'SIMULAÇÃO DE COMISSÕES\n';
            csvContent += exportarTabelaCSV('tabelaSimulacao');
            nomeArquivo = 'Simulacao_Comissoes';
            break;
        default:
            // Exportar todas as tabelas
            csvContent += 'RESUMO POR CENTRO DE TRABALHO\n';
            csvContent += exportarTabelaCSV('tabelaCentros');
            csvContent += '\n\nTOP 10 - RANKING FUNCIONÁRIOS\n';
            csvContent += exportarTabelaCSV('tabelaRanking');
            nomeArquivo = 'Dashboard_Completo';
            break;
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${nomeArquivo}_${new Date().toISOString().split('T')[0]}.csv`;
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
 * Simula comissões com base nos filtros atuais
 */
function simularComissoes() {
    const filtros = obterFiltros();
    
    if (!filtros.dataInicio || !filtros.dataFim) {
        exibirMensagemErro('Informe o período para simular comissões');
        return;
    }
    
    if (!filtros.emprId) {
        exibirMensagemErro('Selecione uma filial para simular comissões');
        return;
    }
    
    // Mostrar loading
    const modalBody = document.getElementById('tabelaSimulacaoBody');
    modalBody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="bi bi-hourglass-split"></i> Calculando...</td></tr>';
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalSimulacao'));
    modal.show();
    
    // Buscar simulação
    const params = new URLSearchParams({
        data_inicio: filtros.dataInicio,
        data_fim: filtros.dataFim,
        empr_id: filtros.emprId
    });
    
    if (filtros.centroTrabId) {
        params.append('centro_trab_id', filtros.centroTrabId);
    }
    
    fetch(`/comissao-api-simular?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar resumo
                document.getElementById('simPeriodo').textContent = 
                    `${formatarData(filtros.dataInicio)} a ${formatarData(filtros.dataFim)}`;
                document.getElementById('simFuncionarios').textContent = 
                    formatarNumero(data.total_funcionarios || 0);
                document.getElementById('simPontos').textContent = 
                    formatarNumero(data.total_geral_pontos || 0, 2);
                document.getElementById('simComissao').textContent = 
                    formatarMoeda(data.total_geral_comissao || 0);
                
                // Preencher tabela
                let html = '';
                if (data.funcionarios && data.funcionarios.length > 0) {
                    data.funcionarios.forEach(func => {
                        html += `
                            <tr>
                                <td>${func.cod_func || '-'}</td>
                                <td>${func.nome_func || '-'}</td>
                                <td>${func.desc_centro || '-'}</td>
                                <td class="text-center">${formatarNumero(func.qtd_apontamentos || 0)}</td>
                                <td class="text-center">${formatarNumero(func.total_qtd_boa || 0)}</td>
                                <td class="text-center">${formatarNumero(func.total_pontos || 0, 2)}</td>
                                <td>${func.faixa_descricao || 'Sem faixa'}</td>
                                <td class="text-end">${formatarMoeda(func.valor_comissao || 0)}</td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="8" class="text-center">Nenhum funcionário encontrado no período</td></tr>';
                }
                
                modalBody.innerHTML = html;
            } else {
                throw new Error(data.error || 'Erro ao simular comissões');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            modalBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${error.message}</td></tr>`;
        });
}

/**
 * Processa e salva as comissões calculadas
 */
function processarComissoes() {
    const filtros = obterFiltros();
    
    if (!confirm('Deseja processar e salvar as comissões calculadas?')) {
        return;
    }
    
    const dados = {
        periodo_ini: filtros.dataInicio,
        periodo_fim: filtros.dataFim,
        empr_id: filtros.emprId || null,
        centro_trab_id: filtros.centroTrabId || null
    };
    
    fetch('/comissao-api-processar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Comissões processadas com sucesso!\n\nTotal processados: ${data.total_processados}\nValor total: ${formatarMoeda(data.resumo?.total_geral_comissao || 0)}`);
            
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalSimulacao'));
            modal.hide();
            
            // Recarregar dashboard
            carregarDashboard();
        } else {
            throw new Error(data.error || 'Erro ao processar comissões');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        exibirMensagemErro(error.message);
    });
}

/**
 * Formata data para exibição (DD/MM/YYYY)
 */
function formatarData(dataString) {
    if (!dataString) return '-';
    const partes = dataString.split('-');
    if (partes.length !== 3) return dataString;
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}
