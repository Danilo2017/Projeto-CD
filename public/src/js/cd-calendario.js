/**
 * CD Calendário - JavaScript
 * Funções para o Calendário de Recebimento (sem Bootstrap)
 */

// Variáveis globais
let anoAtual = new Date().getFullYear();
let recebimentos = [];
let recebimentoEditando = null;
let dataSelecionada = null;

// Inicialização
document.addEventListener('DOMContentLoaded', async function() {
    await carregarRecebimentos();
    renderizarCalendarioAnual();
    
    // Event listeners para navegação
    document.getElementById('btnAnoAnterior').addEventListener('click', () => navegarAno(-1));
    document.getElementById('btnProximoAno').addEventListener('click', () => navegarAno(1));
    document.getElementById('btnHoje').addEventListener('click', irParaHoje);
    
    // Event listeners para modal de recebimento
    document.getElementById('fecharModal').addEventListener('click', fecharModalRecebimento);
    document.getElementById('btnCancelar').addEventListener('click', fecharModalRecebimento);
    document.getElementById('btnExcluir').addEventListener('click', excluirRecebimento);
    document.getElementById('formRecebimento').addEventListener('submit', salvarRecebimento);
    
    // Event listeners para modal de lista do dia
    document.getElementById('fecharListaDia').addEventListener('click', fecharModalListaDia);
    document.getElementById('btnNovoRecebimento').addEventListener('click', function() {
        const ano = parseInt(this.dataset.ano);
        const mes = parseInt(this.dataset.mes);
        const dia = parseInt(this.dataset.dia);
        const dataReconstruida = new Date(ano, mes, dia);
        
        fecharModalListaDia();
        setTimeout(() => abrirModal(dataReconstruida), 300);
    });
    
    // Fechar modais ao clicar fora
    document.getElementById('modalRecebimento').addEventListener('click', function(e) {
        if (e.target === this) fecharModalRecebimento();
    });
    document.getElementById('modalListaDia').addEventListener('click', function(e) {
        if (e.target === this) fecharModalListaDia();
    });
    
    // Formatação de placa
    document.getElementById('placa').addEventListener('input', function() {
        let valor = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (valor.length > 3) {
            valor = valor.substring(0, 3) + '-' + valor.substring(3, 7);
        }
        this.value = valor;
    });
});

/**
 * Funções para abrir/fechar modais (sem Bootstrap)
 */
function abrirModalRecebimento() {
    document.getElementById('modalRecebimento').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function fecharModalRecebimento() {
    document.getElementById('modalRecebimento').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function abrirModalListaDia() {
    document.getElementById('modalListaDia').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function fecharModalListaDia() {
    document.getElementById('modalListaDia').style.display = 'none';
    document.body.style.overflow = 'auto';
}

/**
 * Carregar recebimentos da API
 */
async function carregarRecebimentos() {
    try {
        const response = await fetch(BASE + 'cd-api-calendario');
        const data = await response.json();
        
        if (data.success) {
            recebimentos = data.data;
        } else {
            console.error('Erro ao carregar recebimentos:', data.error);
            recebimentos = [];
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        recebimentos = [];
    }
}

/**
 * Navegação de anos
 */
function navegarAno(direcao) {
    anoAtual += direcao;
    renderizarCalendarioAnual();
}

function irParaHoje() {
    anoAtual = new Date().getFullYear();
    renderizarCalendarioAnual();
}

/**
 * Renderizar calendário anual completo
 */
function renderizarCalendarioAnual() {
    document.getElementById('anoAtual').textContent = anoAtual;
    const calendarioAnual = document.getElementById('calendarioAnual');
    calendarioAnual.innerHTML = '';
    
    const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    // Renderizar os 12 meses
    for (let mes = 0; mes < 12; mes++) {
        const mesContainer = criarMes(mes, anoAtual, meses[mes]);
        calendarioAnual.appendChild(mesContainer);
    }
}

/**
 * Criar container de um mês
 */
function criarMes(mesNum, ano, nomeMes) {
    const container = document.createElement('div');
    container.className = 'mes-container';
    
    // Header do mês
    const header = document.createElement('div');
    header.className = 'mes-header';
    header.textContent = nomeMes + ' ' + ano;
    container.appendChild(header);
    
    // Dias da semana
    const diasSemana = document.createElement('div');
    diasSemana.className = 'dias-semana-mini';
    const nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    nomesDias.forEach(dia => {
        const diaEl = document.createElement('div');
        diaEl.className = 'dia-semana-mini';
        diaEl.textContent = dia;
        diasSemana.appendChild(diaEl);
    });
    container.appendChild(diasSemana);
    
    // Grid de dias
    const diasGrid = document.createElement('div');
    diasGrid.className = 'dias-grid-mini';
    
    // Primeiro dia do mês
    const primeiroDia = new Date(ano, mesNum, 1);
    const diaSemana = primeiroDia.getDay();
    
    // Último dia do mês
    const ultimoDia = new Date(ano, mesNum + 1, 0).getDate();
    
    // Dias do mês anterior
    const diasMesAnterior = new Date(ano, mesNum, 0).getDate();
    
    // Preencher dias do mês anterior
    for (let i = diaSemana - 1; i >= 0; i--) {
        const diaNum = diasMesAnterior - i;
        const data = new Date(ano, mesNum - 1, diaNum);
        const diaEl = criarDiaMini(diaNum, data, true);
        diasGrid.appendChild(diaEl);
    }
    
    // Dias do mês atual
    for (let dia = 1; dia <= ultimoDia; dia++) {
        const data = new Date(ano, mesNum, dia);
        const diaEl = criarDiaMini(dia, data, false);
        diasGrid.appendChild(diaEl);
    }
    
    // Preencher resto da grade
    const totalCelulas = diasGrid.children.length;
    const celulasRestantes = (Math.ceil(totalCelulas / 7) * 7) - totalCelulas;
    
    for (let i = 1; i <= celulasRestantes; i++) {
        const data = new Date(ano, mesNum + 1, i);
        const diaEl = criarDiaMini(i, data, true);
        diasGrid.appendChild(diaEl);
    }
    
    container.appendChild(diasGrid);
    return container;
}

/**
 * Criar elemento de dia no calendário mini
 */
function criarDiaMini(diaNum, data, outroMes) {
    const diaEl = document.createElement('div');
    diaEl.className = 'dia-mini';
    diaEl.textContent = diaNum;
    
    if (outroMes) {
        diaEl.classList.add('outro-mes');
    }
    
    // Verificar se é hoje
    const hoje = new Date();
    if (data.getDate() === hoje.getDate() &&
        data.getMonth() === hoje.getMonth() &&
        data.getFullYear() === hoje.getFullYear() &&
        !outroMes) {
        diaEl.classList.add('hoje');
    }
    
    // Contar recebimentos do dia
    const dataStr = formatarDataISO(data);
    const recebimentosDia = recebimentos.filter(r => r.data === dataStr);
    
    if (recebimentosDia.length > 0 && !outroMes) {
        diaEl.classList.add('tem-recebimento');
        
        const contador = document.createElement('span');
        contador.className = 'contador-mini';
        contador.textContent = recebimentosDia.length;
        diaEl.appendChild(contador);
    }
    
    // Evento de clique
    if (!outroMes) {
        diaEl.addEventListener('click', () => abrirListaDia(data));
    }
    
    return diaEl;
}

/**
 * Formatar data no padrão ISO (YYYY-MM-DD)
 */
function formatarDataISO(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

/**
 * Formatar data para exibição (DD/MM/YYYY)
 */
function formatarDataExibicao(data) {
    const dia = String(data.getDate()).padStart(2, '0');
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const ano = data.getFullYear();
    return `${dia}/${mes}/${ano}`;
}

/**
 * Abrir modal com lista de recebimentos do dia
 */
function abrirListaDia(data) {
    dataSelecionada = data;
    const dataStr = formatarDataISO(data);
    const recebimentosDia = recebimentos.filter(r => r.data === dataStr);
    
    document.getElementById('tituloListaDia').textContent = `Recebimentos - ${formatarDataExibicao(data)}`;
    
    // Guardar dados no botão de novo recebimento
    const btnNovo = document.getElementById('btnNovoRecebimento');
    btnNovo.dataset.ano = data.getFullYear();
    btnNovo.dataset.mes = data.getMonth();
    btnNovo.dataset.dia = data.getDate();
    
    const tbody = document.getElementById('tabelaRecebimentosDia');
    const tfoot = document.getElementById('tabelaTotais');
    
    if (recebimentosDia.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="sem-recebimentos">Nenhum recebimento agendado</td></tr>';
        tfoot.innerHTML = '';
    } else {
        // Calcular totais
        const totalRecebimentos = recebimentosDia.length;
        const totalPeso = recebimentosDia.reduce((sum, rec) => sum + (parseFloat(rec.peso) || 0), 0);
        const totalVolume = recebimentosDia.reduce((sum, rec) => sum + (parseFloat(rec.volume) || 0), 0);
        
        tbody.innerHTML = recebimentosDia.map(rec => {
            const badgeClass = rec.recebido ? 'badge-recebido' : 'badge-pendente';
            const statusText = rec.recebido ? 'Recebido' : 'Pendente';
            const pesoFormatado = rec.peso ? parseFloat(rec.peso).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-';
            const volumeFormatado = rec.volume ? parseFloat(rec.volume).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '-';
            const btnStatusClass = rec.recebido ? 'btn-pendente' : 'btn-recebido';
            const btnStatusText = rec.recebido ? 'Pendente' : 'Recebido';
            
            return `
                <tr>
                    <td>${rec.hora || '--:--'}</td>
                    <td>${rec.fornecedor || '-'}</td>
                    <td>${rec.placa || '-'}</td>
                    <td>${rec.descricao || '-'}</td>
                    <td>${pesoFormatado}</td>
                    <td>${volumeFormatado}</td>
                    <td><span class="${badgeClass}">${statusText}</span></td>
                    <td>
                        <div class="acoes-btns">
                            <button class="${btnStatusClass}" onclick="alterarStatus(${rec.id})">${btnStatusText}</button>
                            <button class="btn-recibo" onclick="abrirModalRecibo(${rec.id})">Recibo</button>
                            <button class="btn-editar" onclick="editarRecebimento(${rec.id})">Editar</button>
                            <button class="btn-deletar" onclick="confirmarExclusao(${rec.id})">Excluir</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        // Adicionar linha de totais
        tfoot.innerHTML = `
            <tr class="linha-totais">
                <td colspan="3"><strong>Total: ${totalRecebimentos} recebimento${totalRecebimentos !== 1 ? 's' : ''}</strong></td>
                <td></td>
                <td><strong>${totalPeso.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                <td><strong>${totalVolume.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                <td colspan="2"></td>
            </tr>
        `;
    }
    
    abrirModalListaDia();
}

/**
 * Abrir modal para novo recebimento
 */
function abrirModal(data) {
    recebimentoEditando = null;
    dataSelecionada = data;
    
    document.getElementById('modalTitulo').textContent = 'Novo Recebimento';
    document.getElementById('btnExcluir').style.display = 'none';
    
    document.getElementById('recebimentoId').value = '';
    document.getElementById('dataRecebimento').value = formatarDataISO(data);
    document.getElementById('dataExibicao').textContent = formatarDataExibicao(data);
    document.getElementById('horaRecebimento').value = '';
    document.getElementById('fornecedor').value = '';
    document.getElementById('placa').value = '';
    document.getElementById('descricao').value = '';
    document.getElementById('peso').value = '';
    document.getElementById('volume').value = '';
    
    abrirModalRecebimento();
}

/**
 * Editar recebimento existente
 */
function editarRecebimento(id) {
    const idNum = parseInt(id);
    const rec = recebimentos.find(r => parseInt(r.id) === idNum);
    if (!rec) {
        console.error('Recebimento não encontrado:', id);
        return;
    }
    
    recebimentoEditando = rec;
    
    document.getElementById('modalTitulo').textContent = 'Editar Recebimento';
    document.getElementById('btnExcluir').style.display = 'inline-block';
    
    document.getElementById('recebimentoId').value = rec.id;
    document.getElementById('dataRecebimento').value = rec.data;
    document.getElementById('dataExibicao').textContent = formatarDataExibicao(new Date(rec.data + 'T00:00:00'));
    document.getElementById('horaRecebimento').value = rec.hora || '';
    document.getElementById('fornecedor').value = rec.fornecedor || '';
    document.getElementById('placa').value = rec.placa || '';
    document.getElementById('descricao').value = rec.descricao || '';
    document.getElementById('peso').value = rec.peso || '';
    document.getElementById('volume').value = rec.volume || '';
    
    fecharModalListaDia();
    setTimeout(() => abrirModalRecebimento(), 300);
}

/**
 * Salvar recebimento (novo ou edição)
 */
async function salvarRecebimento(e) {
    e.preventDefault();
    
    const fornecedor = document.getElementById('fornecedor').value.trim();
    const descricao = document.getElementById('descricao').value.trim();
    
    if (!fornecedor || !descricao) {
        alert('Por favor, preencha os campos obrigatórios.');
        return;
    }
    
    const pesoVal = document.getElementById('peso').value;
    const volumeVal = document.getElementById('volume').value;
    
    const dados = {
        data: document.getElementById('dataRecebimento').value,
        hora: document.getElementById('horaRecebimento').value || '00:00',
        fornecedor: fornecedor,
        placa: document.getElementById('placa').value.trim(),
        descricao: descricao,
        peso: pesoVal ? parseFloat(pesoVal) : null,
        volume: volumeVal ? parseFloat(volumeVal) : null,
        recebido: false
    };
    
    const id = document.getElementById('recebimentoId').value;
    
    try {
        let url = BASE + 'cd-api-calendario';
        let method = 'POST';
        
        if (id) {
            dados.id = parseInt(id);
            method = 'PUT';
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(dados)
        });
        
        const result = await response.json();
        
        if (result.success) {
            fecharModalRecebimento();
            await carregarRecebimentos();
            renderizarCalendarioAnual();
            
            if (dataSelecionada) {
                setTimeout(() => abrirListaDia(dataSelecionada), 300);
            }
        } else {
            alert(result.error || 'Erro ao salvar recebimento');
        }
    } catch (error) {
        console.error('Erro ao salvar:', error);
        alert('Erro ao conectar com o servidor');
    }
}

/**
 * Confirmar exclusão de recebimento
 */
function confirmarExclusao(id) {
    if (confirm('Tem certeza que deseja excluir este recebimento?')) {
        excluirRecebimentoPorId(parseInt(id));
    }
}

/**
 * Excluir recebimento pelo botão do modal de edição
 */
async function excluirRecebimento() {
    const id = document.getElementById('recebimentoId').value;
    if (!id) return;
    
    if (confirm('Tem certeza que deseja excluir este recebimento?')) {
        await excluirRecebimentoPorId(parseInt(id));
        fecharModalRecebimento();
    }
}

/**
 * Excluir recebimento por ID
 */
async function excluirRecebimentoPorId(id) {
    try {
        const response = await fetch(BASE + 'cd-api-calendario', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await carregarRecebimentos();
            renderizarCalendarioAnual();
            
            if (dataSelecionada) {
                abrirListaDia(dataSelecionada);
            }
        } else {
            alert(result.error || 'Erro ao excluir recebimento');
        }
    } catch (error) {
        console.error('Erro ao excluir:', error);
        alert('Erro ao conectar com o servidor');
    }
}

/**
 * Alterar status do recebimento (Pendente <-> Recebido)
 */
async function alterarStatus(id) {
    try {
        const response = await fetch(BASE + 'cd-api-calendario-status', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await carregarRecebimentos();
            renderizarCalendarioAnual();
            
            if (dataSelecionada) {
                abrirListaDia(dataSelecionada);
            }
        } else {
            alert(result.error || 'Erro ao alterar status');
        }
    } catch (error) {
        console.error('Erro ao alterar status:', error);
        alert('Erro ao conectar com o servidor');
    }
}

// ========================================
// FUNÇÕES DE RECIBO DE DESCARGA
// ========================================

/**
 * Abrir modal para gerar recibo
 */
function abrirModalRecibo(agendamentoId) {
    // Converter para número para garantir comparação correta
    const id = parseInt(agendamentoId);
    const rec = recebimentos.find(r => parseInt(r.id) === id);
    if (!rec) {
        console.error('Recebimento não encontrado. ID:', id, 'Lista:', recebimentos);
        alert('Recebimento não encontrado');
        return;
    }
    
    // Preencher informações do agendamento
    document.getElementById('reciboAgendamentoId').value = id;
    document.getElementById('reciboFornecedor').textContent = rec.fornecedor || '-';
    document.getElementById('reciboData').textContent = formatarDataExibicao(new Date(rec.data + 'T00:00:00'));
    document.getElementById('reciboPlaca').textContent = rec.placa || '-';
    document.getElementById('reciboPeso').textContent = rec.peso ? parseFloat(rec.peso).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '0,00';
    document.getElementById('reciboVolume').textContent = rec.volume ? parseFloat(rec.volume).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '0,00';
    
    // Limpar campos do formulário
    document.getElementById('empresaPagadora').value = '';
    document.getElementById('cnpjCpf').value = '';
    document.getElementById('valorPago').value = '';
    document.getElementById('formaPagamento').value = 'DINHEIRO';
    document.getElementById('observacoesRecibo').value = '';
    
    document.getElementById('modalRecibo').style.display = 'flex';
}

/**
 * Fechar modal de recibo
 */
function fecharModalRecibo() {
    document.getElementById('modalRecibo').style.display = 'none';
}

/**
 * Fechar modal de visualização de recibo
 */
function fecharModalVisualizarRecibo() {
    document.getElementById('modalVisualizarRecibo').style.display = 'none';
}

/**
 * Salvar recibo
 */
async function salvarRecibo(event) {
    event.preventDefault();
    
    const dados = {
        agendamento_id: parseInt(document.getElementById('reciboAgendamentoId').value),
        empresa_pagadora: document.getElementById('empresaPagadora').value,
        cnpj_cpf: document.getElementById('cnpjCpf').value,
        valor_pago: parseFloat(document.getElementById('valorPago').value),
        forma_pagamento: document.getElementById('formaPagamento').value,
        observacoes: document.getElementById('observacoesRecibo').value
    };
    
    if (!dados.empresa_pagadora || !dados.valor_pago) {
        alert('Preencha os campos obrigatórios');
        return;
    }
    
    try {
        const response = await fetch(BASE + 'cd-api-recibo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(dados)
        });
        
        const result = await response.json();
        
        if (result.success) {
            fecharModalRecibo();
            // Buscar dados do recibo gerado e exibir
            await visualizarRecibo(result.data.id);
        } else {
            alert(result.error || 'Erro ao gerar recibo');
        }
    } catch (error) {
        console.error('Erro ao gerar recibo:', error);
        alert('Erro ao conectar com o servidor');
    }
}

/**
 * Visualizar recibo gerado
 */
async function visualizarRecibo(id) {
    try {
        const response = await fetch(BASE + 'cd-api-recibo?id=' + id);
        const result = await response.json();
        
        if (result.success) {
            const recibo = result.data;
            renderizarRecibo(recibo);
            document.getElementById('modalVisualizarRecibo').style.display = 'flex';
        } else {
            alert(result.error || 'Erro ao carregar recibo');
        }
    } catch (error) {
        console.error('Erro ao carregar recibo:', error);
        alert('Erro ao conectar com o servidor');
    }
}

/**
 * Renderizar conteúdo do recibo para impressão
 */
function renderizarRecibo(recibo) {
    const valorNumero = parseFloat(recibo.VALOR_PAGO);
    const valorFormatado = valorNumero.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
    
    // Converter valor para extenso
    const valorExtenso = valorPorExtenso(valorNumero);
    
    const pesoKg = recibo.PESO ? parseFloat(recibo.PESO).toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) : '0';
    
    const formasPagamento = {
        'DINHEIRO': 'Dinheiro',
        'PIX': 'PIX',
        'CARTAO_DEBITO': 'Cartão Débito',
        'CARTAO_CREDITO': 'Cartão Crédito',
        'TRANSFERENCIA': 'Transferência Bancária',
        'BOLETO': 'Boleto'
    };
    
    const formaPagamentoTexto = formasPagamento[recibo.FORMA_PAGAMENTO] || recibo.FORMA_PAGAMENTO;
    
    // Formatar data por extenso
    const dataEmissao = new Date();
    const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const dataExtenso = `Douradina, ${dataEmissao.getDate()} de ${meses[dataEmissao.getMonth()]} de ${dataEmissao.getFullYear()}`;
    
    // Montar descrição da descarga
    let descricaoDescarga = `A descarga de ${pesoKg} KG`;
    if (recibo.OBSERVACOES) {
        descricaoDescarga += ` - ${recibo.OBSERVACOES}`;
    }

    // Logo da Gazin
    const logoGazin = 'https://systemcolchoes.blob.core.windows.net/site-gazin-colchoes/prod/Logo_Gazin_6a2b1ee6aa.png';
    
    // Função para gerar uma via do recibo (sem linha de corte, layout limpo)
    function gerarVia(tipoVia) {
        return `
            <div class="recibo-via-container" style="margin-bottom: 32px;">
                <div class="recibo-modelo">
                    <div class="recibo-header-logo" style="justify-content: flex-end;">
                        <div class="recibo-via-label" style="font-size: 13px; color: #555; font-weight: 600; background: #fff; padding: 2px 10px; border-radius: 6px;">${tipoVia}</div>
                    </div>
                    <div class="recibo-topo" style="margin-bottom: 8px;">
                        <div class="recibo-titulo" style="display: flex; align-items: center; justify-content: flex-start; gap: 16px;">
                            <h1 style="font-size: 1.4rem; font-weight: 700; color: #004080; margin: 0;">RECIBO</h1>
                            <span class="recibo-numero" style="font-size: 1.1rem; color: #333; font-weight: 600;">${String(recibo.NUMERO_RECIBO).padStart(2, '0')}</span>
                        </div>
                        <div class="recibo-valor-box" style="display: flex; align-items: center; gap: 8px; font-size: 1.1rem; margin-bottom: 10px;">
                            <span class="label" style="font-weight: 600; color: #004080;">VALOR</span>
                            <span class="valor" style="font-weight: 700; color: #2c3e50;">R$ ${valorNumero.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    </div>
                    <div class="recibo-corpo" style="margin-bottom: 16px;">
                        <p class="recibo-texto" style="font-size: 1rem; margin-bottom: 8px;">
                            <strong>Recebí(emos)</strong> de <strong>${recibo.EMPRESA_PAGADORA}</strong>${recibo.CNPJ_CPF ? ` - CPF/CNPJ nº ${recibo.CNPJ_CPF}` : ''}, a importância de <strong>${valorExtenso}</strong> referente a(o) ${descricaoDescarga}. Pagamento: ${formaPagamentoTexto}
                        </p>
                        <p class="recibo-declaracao" style="font-size: 0.95rem; color: #555; margin-bottom: 8px;">
                            E, para maior clareza firmo o presente recibo para que produza os seus efeitos, dando plena, rasa e irrevogável quitação, pelo valor recebido.
                        </p>
                        <p class="recibo-data" style="font-size: 0.95rem; color: #333; margin-bottom: 32px;">${dataExtenso}</p>
                        <div class="recibo-assinatura" style="margin-top: 32px; text-align: center;">
                            <div class="linha-assinatura" style="border-bottom: 1.5px dashed #888; width: 180px; margin: 0 auto 6px auto;"></div>
                            <p class="assinatura-nome" style="font-size: 0.95rem; color: #333; margin: 0;">GAZIN INDÚSTRIA DE COLCHÕES</p>
                        </div>
                    </div>
                    <div class="recibo-rodape" style="font-size: 0.85rem; color: #666; text-align: center; margin-top: 10px;">
                        <p><strong>GAZIN INDÚSTRIA DE COLCHÕES</strong> - CPF/CNPJ: 28.411.905/0001-73 - Fone: (44) 3663-8000</p>
                        <p>RUA DEPUTADO ANTÔNIO LUSTOSA, 420 - Douradina - Paraná - CEP: 87485-000</p>
                    </div>
                </div>
            </div>
        `;
    }
    // Gerar duas vias, sem linha de corte, centralizado e com largura máxima
    const html = `
        <div class="recibos-container" style="padding-top: 0; display: flex; flex-direction: column; align-items: center; width: 100%;">
            <div style="width: 100%; max-width: 650px;">
                ${gerarVia('1ª Via - Pagador')}
                ${gerarVia('2ª Via - Empresa')}
            </div>
        </div>
    `;
    document.getElementById('reciboContent').innerHTML = html;
}

/**
 * Converter valor numérico para extenso
 */
function valorPorExtenso(valor) {
    const unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
    const especiais = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    const dezenas = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    const centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    
    if (valor === 0) return 'zero reais';
    if (valor === 100) return 'cem reais';
    
    const partes = valor.toFixed(2).split('.');
    const inteiro = parseInt(partes[0]);
    const centavos = parseInt(partes[1]);
    
    let extenso = '';
    
    if (inteiro > 0) {
        if (inteiro >= 1000) {
            const milhares = Math.floor(inteiro / 1000);
            if (milhares === 1) {
                extenso += 'mil';
            } else {
                extenso += converterCentena(milhares, unidades, especiais, dezenas, centenas) + ' mil';
            }
            const resto = inteiro % 1000;
            if (resto > 0) {
                extenso += ' e ' + converterCentena(resto, unidades, especiais, dezenas, centenas);
            }
        } else {
            extenso = converterCentena(inteiro, unidades, especiais, dezenas, centenas);
        }
        extenso += inteiro === 1 ? ' real' : ' reais';
    }
    
    if (centavos > 0) {
        if (inteiro > 0) extenso += ' e ';
        extenso += converterCentena(centavos, unidades, especiais, dezenas, centenas);
        extenso += centavos === 1 ? ' centavo' : ' centavos';
    }
    
    return extenso;
}

function converterCentena(num, unidades, especiais, dezenas, centenas) {
    if (num === 100) return 'cem';
    
    let resultado = '';
    
    if (num >= 100) {
        resultado += centenas[Math.floor(num / 100)];
        num = num % 100;
        if (num > 0) resultado += ' e ';
    }
    
    if (num >= 20) {
        resultado += dezenas[Math.floor(num / 10)];
        num = num % 10;
        if (num > 0) resultado += ' e ';
    } else if (num >= 10) {
        resultado += especiais[num - 10];
        return resultado;
    }
    
    if (num > 0) {
        resultado += unidades[num];
    }
    
    return resultado;
}

/**
 * Imprimir recibo
 */
function imprimirRecibo() {
    window.print();
}

// Event listeners para o modal de recibo
document.addEventListener('DOMContentLoaded', function() {
    // Fechar modal recibo
    const fecharModalReciboBtn = document.getElementById('fecharModalRecibo');
    if (fecharModalReciboBtn) {
        fecharModalReciboBtn.addEventListener('click', fecharModalRecibo);
    }
    
    const btnCancelarRecibo = document.getElementById('btnCancelarRecibo');
    if (btnCancelarRecibo) {
        btnCancelarRecibo.addEventListener('click', fecharModalRecibo);
    }
    
    // Fechar modal visualizar recibo
    const fecharVisualizarReciboBtn = document.getElementById('fecharVisualizarRecibo');
    if (fecharVisualizarReciboBtn) {
        fecharVisualizarReciboBtn.addEventListener('click', fecharModalVisualizarRecibo);
    }
    
    const btnFecharRecibo = document.getElementById('btnFecharRecibo');
    if (btnFecharRecibo) {
        btnFecharRecibo.addEventListener('click', fecharModalVisualizarRecibo);
    }
    
    // Imprimir recibo
    const btnImprimirRecibo = document.getElementById('btnImprimirRecibo');
    if (btnImprimirRecibo) {
        btnImprimirRecibo.addEventListener('click', imprimirRecibo);
    }
    
    // Submit do formulário de recibo
    const formRecibo = document.getElementById('formRecibo');
    if (formRecibo) {
        formRecibo.addEventListener('submit', salvarRecibo);
    }
});
