/**
 * Meta Empresa - Gestão de Metas
 * JavaScript para CRUD de metas por empresa/mês
 */

// Variáveis globais
let empresas = [];
let modoEdicao = false;
let metaEmEdicao = null;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Meta Empresa - Inicializando...');
    
    initEventListeners();
    carregarEmpresas();
    carregarMetas();
    
    // Define mês atual no filtro
    const hoje = new Date();
    const mesAtual = hoje.toISOString().slice(0, 7);
    document.getElementById('filtro-mes').value = mesAtual;
});

/**
 * Inicializar event listeners
 */
function initEventListeners() {
    // Botões da toolbar
    document.getElementById('btn-filtrar').addEventListener('click', carregarMetas);
    document.getElementById('btn-limpar').addEventListener('click', limparFiltro);
    document.getElementById('btn-nova-meta').addEventListener('click', abrirModalNovaMeta);
    
    // Modal de cadastro/edição
    document.getElementById('btn-fechar-modal').addEventListener('click', fecharModal);
    document.getElementById('btn-cancelar').addEventListener('click', fecharModal);
    document.getElementById('form-meta').addEventListener('submit', salvarMeta);
    
    // Modal de exclusão
    document.getElementById('btn-fechar-modal-excluir').addEventListener('click', fecharModalExcluir);
    document.getElementById('btn-cancelar-excluir').addEventListener('click', fecharModalExcluir);
    document.getElementById('btn-confirmar-excluir').addEventListener('click', confirmarExcluir);
    
    // Máscaras de valor
    document.getElementById('meta').addEventListener('input', mascaraValor);
    document.getElementById('meta_estoque').addEventListener('input', mascaraValor);
    
    // Fechar modal ao clicar fora
    document.getElementById('modal-meta').addEventListener('click', function(e) {
        if (e.target === this) fecharModal();
    });
    document.getElementById('modal-excluir').addEventListener('click', function(e) {
        if (e.target === this) fecharModalExcluir();
    });
}

/**
 * Carregar lista de empresas
 */
async function carregarEmpresas() {
    try {
        const response = await fetch('meta-empresa-api-empresas');
        const resultado = await response.json();
        
        if (resultado.sucesso && resultado.dados) {
            empresas = resultado.dados;
            preencherSelectEmpresas();
        }
    } catch (error) {
        console.error('Erro ao carregar empresas:', error);
        mostrarToast('Erro ao carregar lista de empresas', 'error');
    }
}

/**
 * Preencher select de empresas
 */
function preencherSelectEmpresas() {
    const select = document.getElementById('empr_id');
    select.innerHTML = '<option value="">Selecione...</option>';
    
    empresas.forEach(emp => {
        const option = document.createElement('option');
        option.value = emp.EMPR_ID;
        option.textContent = emp.NOME_EMPRESA;
        select.appendChild(option);
    });
}

/**
 * Carregar metas
 */
async function carregarMetas() {
    const tbody = document.getElementById('tbody-metas');
    tbody.innerHTML = '<tr><td colspan="5" class="loading-cell">Carregando...</td></tr>';
    
    try {
        let url = 'meta-empresa-api-listar';
        const filtroMes = document.getElementById('filtro-mes').value;
        
        if (filtroMes) {
            url += '?mes_ano=' + filtroMes;
        }
        
        const response = await fetch(url);
        const resultado = await response.json();
        
        if (resultado.sucesso) {
            renderizarTabela(resultado.dados);
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="no-data-cell">Erro ao carregar dados</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar metas:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="no-data-cell">Erro de conexão</td></tr>';
    }
}

/**
 * Renderizar tabela de metas
 */
function renderizarTabela(dados) {
    const tbody = document.getElementById('tbody-metas');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="no-data-cell">Nenhuma meta cadastrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    
    dados.forEach(meta => {
        const tr = document.createElement('tr');
        
        const nomeEmpresa = getNomeEmpresa(meta.EMPR_ID);
        const mesAnoFormatado = formatarMesAno(meta.MES_ANO);
        const metaFormatada = formatarValor(meta.META);
        const metaEstoqueFormatada = formatarValor(meta.META_ESTOQUE);
        
        tr.innerHTML = `
            <td>${nomeEmpresa}</td>
            <td>${mesAnoFormatado}</td>
            <td class="valor">R$ ${metaFormatada}</td>
            <td class="valor">R$ ${metaEstoqueFormatada}</td>
            <td class="acoes">
                <button type="button" class="btn-primary btn-icon" onclick="editarMeta(${meta.EMPR_ID}, '${meta.MES_ANO}')" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn-danger btn-icon" onclick="excluirMeta(${meta.EMPR_ID}, '${meta.MES_ANO}', '${nomeEmpresa}')" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
    });
}

/**
 * Obter nome da empresa por ID
 */
function getNomeEmpresa(emprId) {
    const empresa = empresas.find(e => e.EMPR_ID == emprId);
    if (empresa) {
        return empresa.NOME_EMPRESA;
    }
    
    // Fallback com nomes conhecidos
    const nomes = {
        1: '1 - DOURADINA PR',
        2: '2 - VILHENA RO',
        3: '3 - CANDELÁRIA RS',
        4: '4 - F. SANTANA BA',
        5: '5 - JACIARA MT',
        6: '6 - COMPLEMENTO',
        7: '7 - ITATINGA CE',
        9: '9 - S. GUIOMARD AC',
        10: '10 - MOLAS DOURAD.',
        11: '11 - MOLAS CAND.',
        13: '13 - ELOI MENDES MG',
        14: '14 - ARAGUATINS TO',
        15: '15 - PATOS MINAS MG'
    };
    
    return nomes[emprId] || ('Empresa ' + emprId);
}

/**
 * Formatar mês/ano (YYYY-MM-DD -> MM/YYYY)
 */
function formatarMesAno(data) {
    if (!data) return '-';
    
    // Extrair ano e mês
    const partes = data.split('-');
    if (partes.length >= 2) {
        return partes[1] + '/' + partes[0];
    }
    
    return data;
}

/**
 * Formatar valor para exibição (pt-BR)
 */
function formatarValor(valor) {
    if (!valor) return '0,00';
    
    const num = parseFloat(String(valor).replace(/[^\d.-]/g, '')) || 0;
    return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Limpar filtro
 */
function limparFiltro() {
    document.getElementById('filtro-mes').value = '';
    carregarMetas();
}

/**
 * Abrir modal para nova meta
 */
function abrirModalNovaMeta() {
    modoEdicao = false;
    metaEmEdicao = null;
    
    document.getElementById('modal-titulo').textContent = 'Nova Meta';
    document.getElementById('form-meta').reset();
    document.getElementById('empr_id').disabled = false;
    document.getElementById('mes_ano').disabled = false;
    
    // Define mês atual
    const hoje = new Date();
    document.getElementById('mes_ano').value = hoje.toISOString().slice(0, 7);
    
    document.getElementById('modal-meta').style.display = 'flex';
}

/**
 * Editar meta existente
 */
async function editarMeta(emprId, mesAno) {
    modoEdicao = true;
    
    // Converter mesAno para formato YYYY-MM
    const mesAnoFormatado = mesAno.slice(0, 7);
    
    metaEmEdicao = { empr_id: emprId, mes_ano: mesAnoFormatado };
    
    try {
        const response = await fetch(`meta-empresa-api-buscar?empr_id=${emprId}&mes_ano=${mesAnoFormatado}`);
        const resultado = await response.json();
        
        if (resultado.sucesso && resultado.dados) {
            const meta = resultado.dados;
            
            document.getElementById('modal-titulo').textContent = 'Editar Meta';
            document.getElementById('empr_id').value = meta.EMPR_ID;
            document.getElementById('empr_id').disabled = true;
            document.getElementById('mes_ano').value = mesAnoFormatado;
            document.getElementById('mes_ano').disabled = true;
            document.getElementById('meta').value = formatarValor(meta.META);
            document.getElementById('meta_estoque').value = formatarValor(meta.META_ESTOQUE);
            
            document.getElementById('modal-meta').style.display = 'flex';
        } else {
            mostrarToast('Meta não encontrada', 'error');
        }
    } catch (error) {
        console.error('Erro ao buscar meta:', error);
        mostrarToast('Erro ao carregar dados da meta', 'error');
    }
}

/**
 * Fechar modal de cadastro/edição
 */
function fecharModal() {
    document.getElementById('modal-meta').style.display = 'none';
    document.getElementById('form-meta').reset();
}

/**
 * Salvar meta (criar ou atualizar)
 */
async function salvarMeta(e) {
    e.preventDefault();
    
    const btnSalvar = document.getElementById('btn-salvar');
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    
    try {
        const dados = {
            empr_id: modoEdicao ? metaEmEdicao.empr_id : document.getElementById('empr_id').value,
            mes_ano: modoEdicao ? metaEmEdicao.mes_ano : document.getElementById('mes_ano').value,
            meta: converterParaNumero(document.getElementById('meta').value),
            meta_estoque: converterParaNumero(document.getElementById('meta_estoque').value)
        };
        
        const response = await fetch('meta-empresa-api-salvar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(dados)
        });
        
        const resultado = await response.json();
        
        if (resultado.sucesso) {
            mostrarToast(resultado.mensagem, 'success');
            fecharModal();
            carregarMetas();
        } else {
            mostrarToast(resultado.mensagem || 'Erro ao salvar', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar meta:', error);
        mostrarToast('Erro de conexão', 'error');
    } finally {
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = '<i class="fas fa-save"></i> Salvar';
    }
}

/**
 * Abrir modal de exclusão
 */
function excluirMeta(emprId, mesAno, nomeEmpresa) {
    metaEmEdicao = { 
        empr_id: emprId, 
        mes_ano: mesAno.slice(0, 7)
    };
    
    document.getElementById('excluir-empresa').textContent = nomeEmpresa;
    document.getElementById('excluir-mes').textContent = formatarMesAno(mesAno);
    
    document.getElementById('modal-excluir').style.display = 'flex';
}

/**
 * Fechar modal de exclusão
 */
function fecharModalExcluir() {
    document.getElementById('modal-excluir').style.display = 'none';
    metaEmEdicao = null;
}

/**
 * Confirmar exclusão
 */
async function confirmarExcluir() {
    if (!metaEmEdicao) return;
    
    const btnExcluir = document.getElementById('btn-confirmar-excluir');
    btnExcluir.disabled = true;
    btnExcluir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
    
    try {
        const response = await fetch('meta-empresa-api-excluir', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(metaEmEdicao)
        });
        
        const resultado = await response.json();
        
        if (resultado.sucesso) {
            mostrarToast(resultado.mensagem, 'success');
            fecharModalExcluir();
            carregarMetas();
        } else {
            mostrarToast(resultado.mensagem || 'Erro ao excluir', 'error');
        }
    } catch (error) {
        console.error('Erro ao excluir meta:', error);
        mostrarToast('Erro de conexão', 'error');
    } finally {
        btnExcluir.disabled = false;
        btnExcluir.innerHTML = '<i class="fas fa-trash"></i> Excluir';
    }
}

/**
 * Máscara de valor monetário
 */
function mascaraValor(e) {
    let valor = e.target.value;
    
    // Remove tudo exceto números
    valor = valor.replace(/\D/g, '');
    
    // Converte para decimal
    valor = (parseInt(valor) / 100).toFixed(2);
    
    // Formata para pt-BR
    valor = parseFloat(valor).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    e.target.value = valor;
}

/**
 * Converter valor formatado para número
 */
function converterParaNumero(valor) {
    if (!valor) return 0;
    
    // Remove pontos de milhar e troca vírgula por ponto
    return parseFloat(
        String(valor)
            .replace(/\./g, '')
            .replace(',', '.')
    ) || 0;
}

/**
 * Mostrar notificação toast
 */
function mostrarToast(mensagem, tipo = 'success') {
    // Remove toasts anteriores
    const existentes = document.querySelectorAll('.toast-container');
    existentes.forEach(t => t.remove());
    
    const container = document.createElement('div');
    container.className = 'toast-container';
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = mensagem;
    
    container.appendChild(toast);
    document.body.appendChild(container);
    
    // Remove após 4 segundos
    setTimeout(() => {
        container.remove();
    }, 4000);
}

console.log('📊 Meta Empresa - Script carregado');
