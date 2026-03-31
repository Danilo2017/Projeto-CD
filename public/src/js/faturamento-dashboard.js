/**
 * Dashboard de Faturamento Indústrias
 * JavaScript para carregamento e atualização de dados
 */

// Variável global para armazenar dados de faturamento
let dadosFaturamentoGlobal = null;

// ========== FUNÇÕES AUXILIARES ==========

/**
 * Formata número no padrão brasileiro (1.234,56)
 */
function formatarNumero(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return '-';
    
    let num;
    if (typeof valor === 'number') {
        num = valor;
    } else {
        let str = valor.toString();
        str = str.replace(/\s/g, '');
        str = str.replace(/\./g, '');
        str = str.replace(/,/g, '.');
        num = parseFloat(str);
    }
    
    if (isNaN(num)) return '-';
    
    num = Math.round(num * 100) / 100;
    
    const negativo = num < 0;
    if (negativo) num = -num;
    
    const numStr = num.toFixed(2);
    const partes = numStr.split('.');
    let inteiro = partes[0];
    const decimal = partes[1];
    
    let formatado = '';
    const len = inteiro.length;
    for (let i = 0; i < len; i++) {
        if (i > 0 && (len - i) % 3 === 0) {
            formatado += '.';
        }
        formatado += inteiro.charAt(i);
    }
    
    return (negativo ? '-' : '') + formatado + ',' + decimal;
}

/**
 * Formata número de forma compacta para tabela (sem R$, apenas valor)
 * Valores acima de 1M mostram em milhões (ex: 27,3M)
 */
function formatarNumeroCompacto(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return '-';
    
    let num = typeof valor === 'number' ? valor : parseFloat(valor.toString().replace(/\./g, '').replace(/,/g, '.'));
    
    if (isNaN(num)) return '-';
    
    const negativo = num < 0;
    if (negativo) num = -num;
    
    let resultado;
    if (num >= 1000000) {
        resultado = (num / 1000000).toFixed(1).replace('.', ',') + 'M';
    } else if (num >= 1000) {
        resultado = (num / 1000).toFixed(0) + 'K';
    } else {
        resultado = num.toFixed(0);
    }
    
    return (negativo ? '-' : '') + resultado;
}

/**
 * Formata valor para exibição em tabela (padrão brasileiro)
 * Recebe valores que podem vir como string do banco
 */
function formatarValorTabela(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return '-';
    
    let num;
    if (typeof valor === 'number') {
        num = valor;
    } else {
        // Remove espaços e converte para número
        let str = valor.toString().trim();
        // Se já tem ponto como separador de milhar e vírgula como decimal (BR)
        if (str.includes('.') && str.includes(',')) {
            str = str.replace(/\./g, '').replace(',', '.');
        } else if (str.includes(',') && !str.includes('.')) {
            // Só vírgula = decimal BR
            str = str.replace(',', '.');
        }
        num = parseFloat(str);
    }
    
    if (isNaN(num)) return '-';
    
    // Formata no padrão brasileiro
    return num.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Formata percentual (valores já vêm como percentual do banco)
 */
function formatarPercentual(valor) {
    if (valor === null || valor === undefined || valor === '' || valor === '-') return '-';
    
    let num;
    if (typeof valor === 'number') {
        num = valor;
    } else {
        // Remove % se existir e converte para número
        let str = valor.toString().replace('%', '').trim().replace(',', '.');
        num = parseFloat(str);
    }
    
    if (isNaN(num)) return '-';
    
    // Se valor é menor que 1, assume que é decimal (0.87 = 87%)
    // Se valor é maior que 1, assume que já é percentual (87.1 = 87,1%)
    if (num > 0 && num < 1) {
        num = num * 100;
    }
    
    return num.toFixed(1).replace('.', ',') + '%';
}

/**
 * Atualiza data/hora na tela
 */
function atualizarDataHora() {
    const agora = new Date();
    const dataFormatada = agora.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const elementoData = document.getElementById('dataHoraAtual');
    if (elementoData) {
        elementoData.textContent = dataFormatada;
    }
}

// ========== API CALLS ==========

/**
 * Buscar dados de faturamento mensal
 */
function buscarDadosBanco() {
    return new Promise(function(resolve, reject) {
        console.log('📊 Buscando dados de faturamento...');
        
        fetch('/faturamento-api-resumo')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('✅ Dados de faturamento recebidos:', data.total, 'registros');
                if (data.success && data.data && data.data.length > 0) {
                    resolve(data.data);
                } else {
                    console.warn('⚠️ Dados não válidos ou vazios');
                    resolve(null);
                }
            })
            .catch(error => {
                console.error('❌ Erro ao buscar faturamento:', error);
                resolve(null);
            });
    });
}

/**
 * Buscar dados do painel de vendas
 */
function buscarDadosPainelVendas(callback) {
    console.log('📊 Buscando dados do painel de vendas...');
    
    fetch('/faturamento-api-painel?_=' + Date.now())
        .then(response => response.json())
        .then(data => {
            console.log('✅ Dados do painel recebidos:', data.total_registros, 'registros');
            callback(data);
        })
        .catch(error => {
            console.error('❌ Erro ao buscar painel:', error);
            callback({ sucesso: false, dados: [] });
        });
}

/**
 * Buscar dados de pedidos em carteira
 */
function buscarDadosPedidos() {
    return new Promise(function(resolve, reject) {
        console.log('📦 Buscando dados de pedidos...');
        
        fetch('/faturamento-api-pedidos')
            .then(response => response.json())
            .then(data => {
                console.log('✅ Dados de pedidos recebidos:', data);
                if (data.success && data.data) {
                    resolve(data.data);
                } else {
                    resolve(null);
                }
            })
            .catch(error => {
                console.error('❌ Erro ao buscar pedidos:', error);
                resolve(null);
            });
    });
}

/**
 * Buscar dados de pedidos planejados
 */
function buscarDadosPedidosPlanejado() {
    return new Promise(function(resolve, reject) {
        console.log('📦 Buscando dados de pedidos planejados...');
        
        fetch('/faturamento-api-pedidos-planejado')
            .then(response => response.json())
            .then(data => {
                console.log('✅ Dados de pedidos planejados recebidos:', data);
                if (data.success && data.data) {
                    resolve(data.data);
                } else {
                    resolve(null);
                }
            })
            .catch(error => {
                console.error('❌ Erro ao buscar pedidos planejados:', error);
                resolve(null);
            });
    });
}

// ========== ATUALIZAÇÃO DA TELA ==========

/**
 * Calcular valores agregados a partir dos dados
 */
function calcularValoresAgregados(dados) {
    console.log('🧮 Calculando valores agregados...');
    
    const primeiroRegistro = dados[0];
    
    // Parse seguro dos valores (formato americano: "98,318,858.19")
    function parseValor(valor) {
        if (!valor) return 0;
        return parseFloat(String(valor).replace(/,/g, '')) || 0;
    }
    
    const totalFaturamento = parseValor(primeiroRegistro.TOTAL_FATURAMENTO);
    const totalDevolucoes = Math.abs(parseValor(primeiroRegistro.TOTAL_DEVOLUCOES));
    const totalFatLiquido = parseValor(primeiroRegistro.TOTAL_FATURAMENTO_LI);
    const totalMeta = parseValor(primeiroRegistro.TOTAL_META);
    
    console.log('📊 Valores calculados:', { totalFaturamento, totalDevolucoes, totalFatLiquido, totalMeta });
    
    // Usa a data que vem do banco para evitar problemas de timezone
    let dia;
    if (primeiroRegistro.DATA_ATUAL) {
        // Data vem no formato "31-MAR-26" (DD-MON-YY)
        const dataStr = primeiroRegistro.DATA_ATUAL;
        if (dataStr.includes('-')) {
            // Extrai o dia (primeiro número antes do hífen)
            const partes = dataStr.split('-');
            dia = parseInt(partes[0], 10);
        } else {
            dia = new Date().getDate();
        }
    } else {
        dia = new Date().getDate();
    }
    const diasUteis = dia;
    
    console.log('📊 Dia:', dia);
    
    return {
        totalFaturamento,
        totalDevolucoes,
        totalFatLiquido,
        totalMeta,
        diasUteis,
        mediaDia: totalFatLiquido / diasUteis,
        metaDiaria: totalMeta / 23,
        metaAtualDiaria: (totalMeta - totalFatLiquido) / (23 - diasUteis),
        percMeta: (totalFatLiquido / totalMeta) * 100
    };
}

/**
 * Atualizar dashboard com dados reais
 */
function atualizarDashboardComDadosReais(dados) {
    if (!dados || dados.length === 0) {
        console.error('❌ Nenhum dado recebido do banco');
        return;
    }
    
    console.log('📈 Atualizando dashboard com', dados.length, 'registros');
    
    const valores = calcularValoresAgregados(dados);
    
    // Atualizar cards de métricas
    document.getElementById('fatBruto').textContent = 
        'R$ ' + valores.totalFaturamento.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('devolucao').textContent = 
        '-R$ ' + valores.totalDevolucoes.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('fatLiquido').textContent = 
        'R$ ' + valores.totalFatLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('mediaDia').textContent = 
        'R$ ' + valores.mediaDia.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('metaDiaria').textContent = 
        'R$ ' + valores.metaDiaria.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('metaAtualDiaria').textContent = 
        'R$ ' + valores.metaAtualDiaria.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('percMeta').textContent = 
        valores.percMeta.toFixed(1) + '%';
    
    // Atualizar meta do header
    document.getElementById('metaGeral').textContent = 
        'R$ ' + valores.totalMeta.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Atualizar tabela FAT LIQ X META
    atualizarTabelaFatMeta(dados);
    
    console.log('✅ Dashboard atualizado com sucesso!');
}

/**
 * Atualizar tabela FAT LIQ X META
 */
function atualizarTabelaFatMeta(dados) {
    const fatMetaTable = document.getElementById('fat-meta-table');
    if (!fatMetaTable) return;
    
    console.log('📋 Atualizando tabela FAT LIQ X META com', dados.length, 'registros');
    
    fatMetaTable.innerHTML = '';
    
    // Mapa de nomes das filiais
    const nomesFiliais = {
        1: '1 - DOURADINA PR',
        2: '2 - VILHENA RO',
        3: '3 - CANDELÁRIA RS',
        4: '4 - F. SANTANA BA',
        5: '5 - JACIARA MT',
        6: '6 - COMPLEMENTO',
        7: '7 - ITATINGA CE',
        8: '8 - FILIAL 8',
        9: '9 - S. GUIOMARD AC',
        10: '10 - MOLAS DOURAD.',
        11: '11 - MOLAS CAND.',
        13: '13 - ELOI MENDES MG',
        14: '14 - ARAGUATINS TO',
        15: '15 - PATOS MINAS MG'
    };
    
    // Parse seguro de valores
    function parseValor(valor) {
        if (!valor) return 0;
        return parseFloat(String(valor).replace(/,/g, '')) || 0;
    }
    
    let totalFatLiq = 0;
    let totalMeta = 0;
    
    for (let i = 0; i < dados.length; i++) {
        const item = dados[i];
        
        // Converter filial para número (vem como string do Oracle)
        const filialNum = parseInt(item.NUMERO_FILIAL, 10);
        const nomeFilial = nomesFiliais[filialNum] || (filialNum + ' - Filial ' + filialNum);
        
        const fatLiq = parseValor(item.FATURAMENTO_LIQUIDO);
        const meta = parseValor(item.VALOR_DA_META);
        const percAtingido = meta > 0 ? (fatLiq / meta) * 100 : 0;
        
        totalFatLiq += fatLiq;
        totalMeta += meta;
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${nomeFilial}</td>
            <td>R$ ${formatarNumero(fatLiq)}</td>
            <td>R$ ${formatarNumero(meta)}</td>
            <td>${percAtingido.toFixed(1).replace('.', ',')}%</td>
        `;
        fatMetaTable.appendChild(row);
    }
    
    // Linha de total
    const percTotalAtingido = totalMeta > 0 ? (totalFatLiq / totalMeta) * 100 : 0;
    const totalRow = document.createElement('tr');
    totalRow.className = 'row-total-fat-meta';
    totalRow.innerHTML = `
        <td>TOTAL</td>
        <td>R$ ${formatarNumero(totalFatLiq)}</td>
        <td>R$ ${formatarNumero(totalMeta)}</td>
        <td>${percTotalAtingido.toFixed(1).replace('.', ',')}%</td>
    `;
    fatMetaTable.appendChild(totalRow);
    
    console.log('✅ Tabela FAT LIQ X META atualizada!');
}

/**
 * Atualizar painel de vendas
 */
function atualizarPainelVendas(dados) {
    console.log('📊 Atualizando painel de vendas...');
    
    const tbody = document.getElementById('tabela-painel-vendas-body');
    if (!tbody) {
        console.error('tbody não encontrado!');
        return;
    }
    
    if (!dados || !dados.sucesso || !dados.dados || dados.dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11">Erro ao carregar</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    
    for (let i = 0; i < dados.dados.length; i++) {
        const row = dados.dados[i];
        const tr = document.createElement('tr');
        
        if (row.EMPR_ID === 'TOTAL') {
            tr.className = 'row-total';
        }
        
        tr.innerHTML = `
            <td>${row.EMPR_ID || '-'}</td>
            <td class="text-right">${row.META_FATURAMENTO || '-'}</td>
            <td class="text-right">${row.FATURAMENTO || '-'}</td>
            <td class="text-right">${row.PCT_ATINGIDO ? row.PCT_ATINGIDO.replace('.', ',') + '%' : '-'}</td>
            <td class="text-right">${row.PLANEJADO || '-'}</td>
            <td class="text-right">${row.FAT_PROJETADO || '-'}</td>
            <td class="text-right">${row.PCT_PROJETADO ? row.PCT_PROJETADO.replace('.', ',') + '%' : '-'}</td>
            <td class="text-right">${row.CARTEIRA || '-'}</td>
            <td class="text-right">${row.META_ESTOQUE || '-'}</td>
            <td class="text-right">${row.ESTOQUE_ATUAL || '-'}</td>
            <td class="text-right">${row.PCT_ESTOQUE ? row.PCT_ESTOQUE.replace('.', ',') + '%' : '-'}</td>
        `;
        
        tbody.appendChild(tr);
    }
    
    console.log('✅ Painel de vendas atualizado!');
}

/**
 * Atualizar dados de pedidos
 */
function atualizarDadosPedidos(dados) {
    if (!dados || dados.length === 0) {
        console.error('❌ Nenhum dado de pedidos recebido');
        return;
    }
    
    console.log('📦 Atualizando dados de pedidos:', dados[0]);
    
    const pedido = dados[0];
    
    const pedidosLiberados = parseFloat(pedido.PEDIDOS_LIBERADOS?.replace(/,/g, '') || '0');
    const pedidosEmCarga = parseFloat(pedido.PEDIDO_EM_CARGA?.replace(/,/g, '') || '0');
    const pedidosSemCarga = parseFloat(pedido.PEDIDOS_SEM_CARGA?.replace(/,/g, '') || '0');
    
    document.getElementById('pedidosLiberados').textContent = 
        'R$ ' + pedidosLiberados.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('pedidosEmCarga').textContent = 
        'R$ ' + pedidosEmCarga.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('pedidosSemCarga').textContent = 
        'R$ ' + pedidosSemCarga.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    console.log('✅ Dados de pedidos atualizados!');
}

/**
 * Atualizar dados de pedidos planejados
 */
function atualizarDadosPedidosPlanejado(dados, dadosFaturamento) {
    console.log('📦 Atualizando pedidos planejados...');
    
    if (!dados || dados.length === 0) {
        console.error('❌ Nenhum dado de pedidos planejados recebido');
        return;
    }
    
    const pedidoPlanejado = dados[0];
    const pedidosPlanejadoValor = parseFloat(pedidoPlanejado.PEDIDOS_PLANEJADO || '0');
    
    // Pegar faturamento líquido total
    let fatLiqTotal = 0;
    if (dadosFaturamento && dadosFaturamento.length > 0) {
        fatLiqTotal = parseFloat(dadosFaturamento[0].TOTAL_FATURAMENTO_LI?.replace(/,/g, '') || '0');
    }
    
    // Calcular PEDIDOS PLAN + FAT LIQ
    const pedidosPlanFatLiq = pedidosPlanejadoValor + fatLiqTotal;
    
    // Pegar meta total
    let metaTotal = 0;
    if (dadosFaturamento && dadosFaturamento.length > 0) {
        metaTotal = parseFloat(dadosFaturamento[0].TOTAL_META?.replace(/,/g, '') || '0');
    }
    
    // Calcular % da meta
    const percMeta = metaTotal > 0 ? (pedidosPlanFatLiq / metaTotal) * 100 : 0;
    
    document.getElementById('pedidosPlanejado').textContent = 
        'R$ ' + pedidosPlanejadoValor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('pedidosPlanFatLiq').textContent = 
        'R$ ' + pedidosPlanFatLiq.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('percMetaPedidos').textContent = 
        percMeta.toFixed(1) + '%';
    
    console.log('✅ Pedidos planejados atualizados!');
}

/**
 * Atualizar todos os dados
 */
function atualizarTodosDados() {
    // Atualizar timestamp
    const agora = new Date();
    document.getElementById('ultima-atualizacao').textContent = 
        agora.toLocaleTimeString('pt-BR');
    
    // Buscar painel de vendas
    buscarDadosPainelVendas(function(dados) {
        if (dados) {
            atualizarPainelVendas(dados);
        }
    });
    
    // Buscar faturamento
    buscarDadosBanco().then(function(dados) {
        if (dados) {
            dadosFaturamentoGlobal = dados;
            atualizarDashboardComDadosReais(dados);
            
            // Buscar pedidos planejados após faturamento
            buscarDadosPedidosPlanejado().then(function(dadosPlanejado) {
                if (dadosPlanejado) {
                    atualizarDadosPedidosPlanejado(dadosPlanejado, dadosFaturamentoGlobal);
                }
            });
        }
    });
    
    // Buscar pedidos
    buscarDadosPedidos().then(function(dados) {
        if (dados) {
            atualizarDadosPedidos(dados);
        }
    });
}

// ========== INICIALIZAÇÃO ==========

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando Dashboard de Faturamento...');
    
    // Atualizar data/hora
    atualizarDataHora();
    
    // Carregar todos os dados
    atualizarTodosDados();
    
    // Atualizar data/hora a cada segundo
    setInterval(atualizarDataHora, 1000);
    
    // Contador regressivo para próxima atualização
    let segundosRestantes = 300;
    setInterval(function() {
        segundosRestantes--;
        if (segundosRestantes <= 0) {
            segundosRestantes = 300;
        }
        const minutos = Math.floor(segundosRestantes / 60);
        let segundos = (segundosRestantes % 60).toString();
        if (segundos.length < 2) segundos = '0' + segundos;
        document.getElementById('proximo-update').textContent = minutos + ':' + segundos;
    }, 1000);
    
    // Atualizar dados a cada 5 minutos
    setInterval(function() {
        atualizarTodosDados();
        segundosRestantes = 300;
    }, 300000);
    
    console.log('✅ Dashboard inicializado!');
});
