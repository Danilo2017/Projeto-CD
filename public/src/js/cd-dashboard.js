/**
 * CD Dashboard - JavaScript
 * Funções para o Dashboard de Aviso de Recebimento
 */

// Carregar dados ao iniciar
document.addEventListener('DOMContentLoaded', function() {
    carregarDados();
    

    setInterval(carregarDados, 240000);
});

/**
 * Carregar todos os dados do dashboard
 */
async function carregarDados() {
    try {
        mostrarCarregando();
        
        // Carregar avisos de recebimento
        const response = await fetch(BASE + 'cd-api-avisos');
        const data = await response.json();

        if (data.success) {
            atualizarCards(data.resumo);
            atualizarTabela(data.data);
            
            // Atualizar horário da última atualização
            const agora = new Date();
            const hora = String(agora.getHours()).padStart(2, '0');
            const min = String(agora.getMinutes()).padStart(2, '0');
            const seg = String(agora.getSeconds()).padStart(2, '0');
            document.getElementById('ultima-atualizacao').textContent = `${hora}:${min}:${seg}`;
        } else {
            console.error('Erro ao carregar dados:', data.error);
            mostrarErro(data.error);
        }
        
        // Carregar agendamentos pendentes
        await carregarAgendamentosPendentes();
        
    } catch (error) {
        console.error('Erro na requisição:', error);
        mostrarErro('Erro ao conectar com o servidor');
    }
}

/**
 * Carregar agendamentos pendentes
 */
async function carregarAgendamentosPendentes() {
    try {
        mostrarCarregandoAgendamentos();
        
        const response = await fetch(BASE + 'cd-api-agendamentos');
        const data = await response.json();

        if (data.success) {
            atualizarTabelaAgendamentos(data.data);
        } else {
            console.error('Erro ao carregar agendamentos:', data.error);
            mostrarErroAgendamentos(data.error);
        }
    } catch (error) {
        console.error('Erro na requisição de agendamentos:', error);
        mostrarErroAgendamentos('Erro ao conectar com o servidor');
    }
}

/**
 * Atualizar cards de estatísticas
 */
function atualizarCards(resumo) {
    document.getElementById('totalAvisos').textContent = resumo.total || 0;
    document.getElementById('totalPendentes').textContent = resumo.pendentes || 0;
    document.getElementById('totalIniciados').textContent = resumo.iniciados || 0;
    document.getElementById('totalFinalizados').textContent = resumo.finalizados || 0;
}

/**
 * Atualizar tabela de avisos de recebimento
 */
function atualizarTabela(avisos) {
    const tbody = document.getElementById('tabelaBody');
    
    if (!avisos || avisos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="cd-loading">Nenhum aviso de recebimento encontrado para hoje</td></tr>';
        return;
    }

    tbody.innerHTML = avisos.map(aviso => {
        const status = aviso.STATUS || 'PENDENTE';
        let statusClass = 'cd-status-pendente';
        
        if (status === 'INICIADO') {
            statusClass = 'cd-status-iniciado';
        } else if (status === 'FINALIZADO') {
            statusClass = 'cd-status-finalizado';
        }

        // Formatar placa estilo Mercosul
        const placaHtml = aviso.PLACA ? 
            `<span class="cd-placa-carro">${aviso.PLACA}</span>` : '-';

        return `
            <tr>
                <td>${aviso.EMPRESA || '-'}</td>
                <td>${aviso.ALMOX || '-'}</td>
                <td>${placaHtml}</td>
                <td>${aviso.CHEGADA || '-'}</td>
                <td>${aviso.INICIO || '-'}</td>
                <td>${aviso.TERMINO || '-'}</td>
                <td><span class="cd-status-badge ${statusClass}">${status}</span></td>
                <td class="cd-crossdocking-cell">${aviso.CROSSDOCKING || '-'}</td>
            </tr>
        `;
    }).join('');
}

/**
 * Atualizar tabela de agendamentos
 */
function atualizarTabelaAgendamentos(agendamentos) {
    const tbody = document.getElementById('tabelaAgendamentosBody');
    
    if (!agendamentos || agendamentos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="cd-loading">Nenhum agendamento pendente</td></tr>';
        return;
    }

    tbody.innerHTML = agendamentos.map(agend => {
        return `
            <tr>
                <td>${agend.EMPRESA || '-'}</td>
                <td>${agend.CHEGADA || '-'}</td>
                <td>${agend.FORNECEDOR || '-'}</td>
                <td>${agend.OBSERVACOES || '-'}</td>
                <td><span class="cd-status-badge cd-status-pendente">${agend.STATUS || 'PENDENTE'}</span></td>
            </tr>
        `;
    }).join('');
}

/**
 * Mostrar estado de carregando na tabela de avisos
 */
function mostrarCarregando() {
    const tbody = document.getElementById('tabelaBody');
    tbody.innerHTML = '<tr><td colspan="8" class="cd-loading">⏳ Carregando dados...</td></tr>';
}

/**
 * Mostrar erro na tabela de avisos
 */
function mostrarErro(mensagem) {
    const tbody = document.getElementById('tabelaBody');
    tbody.innerHTML = `<tr><td colspan="8" class="cd-loading error">❌ ${mensagem || 'Erro ao carregar dados.'}</td></tr>`;
}

/**
 * Mostrar estado de carregando na tabela de agendamentos
 */
function mostrarCarregandoAgendamentos() {
    const tbody = document.getElementById('tabelaAgendamentosBody');
    tbody.innerHTML = '<tr><td colspan="5" class="cd-loading">⏳ Carregando dados...</td></tr>';
}

/**
 * Mostrar erro na tabela de agendamentos
 */
function mostrarErroAgendamentos(mensagem) {
    const tbody = document.getElementById('tabelaAgendamentosBody');
    tbody.innerHTML = `<tr><td colspan="5" class="cd-loading error">❌ ${mensagem || 'Erro ao carregar dados.'}</td></tr>`;
}
