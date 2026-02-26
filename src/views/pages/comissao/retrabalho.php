<?php
// Verificar permissão de acesso (novo sistema de perfis)
$rotasPermitidas = $_SESSION['user']['rotas_permitidas'] ?? [];
$isAdmin = $_SESSION['user']['is_admin'] ?? false;
$acessoComissao = $isAdmin || in_array('comissao', $rotasPermitidas) || in_array('*', $rotasPermitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Controle de Retrabalho',
    'showNavbar' => true,
    'pageActive' => 'comissao-retrabalho',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroEmpresa">Empresa</label>
                <select id="filtroEmpresa" class="form-select">
                    <option value="">Selecione</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filtroDataInicio">Data Início</label>
                <input type="date" id="filtroDataInicio" class="form-control">
            </div>
            <div class="filter-group">
                <label for="filtroDataFim">Data Fim</label>
                <input type="date" id="filtroDataFim" class="form-control">
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary mt-3" onclick="carregarRetrabalhos()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#modalRetrabalho" onclick="novoRetrabalho()">
                    <i class="bi bi-plus-circle"></i> Registrar Retrabalho
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Retrabalhos -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaRetrabalhos" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Ordem</th>
                    <th>Produto</th>
                    <th>Data</th>
                    <th>Quantidade</th>
                    <th>Impacto</th>
                    <th>Valor Impacto</th>
                    <th>Motivo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaRetrabalhosBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro -->
    <div class="modal fade" id="modalRetrabalho" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRetrabalhoTitulo">
                        <i class="bi bi-arrow-repeat"></i> Registrar Retrabalho
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formRetrabalho">
                        <input type="hidden" id="retrabalhoId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="funcionarioId" class="form-label">Funcionário *</label>
                                <select id="funcionarioId" class="form-select" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ordemId" class="form-label">Ordem de Produção</label>
                                <input type="number" id="ordemId" class="form-control" placeholder="ID da Ordem (opcional)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="itemId" class="form-label">Produto</label>
                                <select id="itemId" class="form-select">
                                    <option value="">Selecione (opcional)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="dataRetrabalho" class="form-label">Data do Retrabalho *</label>
                                <input type="date" id="dataRetrabalho" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="quantidade" class="form-label">Quantidade *</label>
                                <input type="number" id="quantidade" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label for="tipoImpacto" class="form-label">Tipo de Impacto *</label>
                                <select id="tipoImpacto" class="form-select" required onchange="atualizarCampoValor()">
                                    <option value="">Selecione</option>
                                    <option value="P">Percentual de Desconto</option>
                                    <option value="V">Valor Fixo de Desconto</option>
                                    <option value="Z">Zera Comissão do Dia</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="valorImpacto" class="form-label">Valor do Impacto</label>
                                <input type="number" id="valorImpacto" class="form-control" step="0.01" min="0">
                                <small id="valorImpactoHelp" class="text-muted"></small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="motivo" class="form-label">Motivo *</label>
                                <textarea id="motivo" class="form-control" rows="2" maxlength="500" required></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="observacao" class="form-label">Observação</label>
                                <textarea id="observacao" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarRetrabalho()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    carregarEmpresas();
    definirDatasIniciais();
});

function definirDatasIniciais() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = primeiroDia.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];
    document.getElementById('dataRetrabalho').value = hoje.toISOString().split('T')[0];
}

function carregarEmpresas() {
    fetch('/comissao-api-empresas')
        .then(response => response.json())
        .then(result => {
            const select = document.getElementById('filtroEmpresa');
            const empresas = result.data || result;
            empresas.forEach(empresa => {
                const option = document.createElement('option');
                option.value = empresa.ID;
                option.textContent = empresa.CODIGO + ' - ' + empresa.NOME_FANTASIA;
                select.appendChild(option);
            });
            // Auto-seleciona empresa da sessão se disponível
            const empresaSessao = '<?= $_SESSION['empresa']['id'] ?? '' ?>';
            if (empresaSessao) {
                select.value = empresaSessao;
                carregarFuncionarios(empresaSessao);
            }
        })
        .catch(error => console.error('Erro ao carregar empresas:', error));
}

function carregarFuncionarios(emprId) {
    if (!emprId) return;
    
    fetch(`/comissao-api-funcionarios?empr_id=${emprId}`)
        .then(response => response.json())
        .then(result => {
            const funcionarios = result.data || result;
            const selects = ['filtroFuncionario', 'funcionarioId'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Selecione</option>';
                funcionarios.forEach(func => {
                    const option = document.createElement('option');
                    option.value = func.ID;
                    option.textContent = func.COD_FUNC + ' - ' + func.NOME;
                    select.appendChild(option);
                });
            });
        })
        .catch(error => console.error('Erro ao carregar funcionários:', error));
}

document.getElementById('filtroEmpresa').addEventListener('change', function() {
    carregarFuncionarios(this.value);
});

function atualizarCampoValor() {
    const tipo = document.getElementById('tipoImpacto').value;
    const valorInput = document.getElementById('valorImpacto');
    const help = document.getElementById('valorImpactoHelp');
    
    if (tipo === 'P') {
        valorInput.disabled = false;
        valorInput.placeholder = 'Ex: 10 para 10%';
        help.textContent = 'Percentual a descontar da comissão';
    } else if (tipo === 'V') {
        valorInput.disabled = false;
        valorInput.placeholder = 'Ex: 50.00';
        help.textContent = 'Valor fixo a descontar';
    } else if (tipo === 'Z') {
        valorInput.disabled = true;
        valorInput.value = '';
        help.textContent = 'Zera toda a comissão do dia';
    } else {
        valorInput.disabled = false;
        help.textContent = '';
    }
}

function carregarRetrabalhos() {
    const emprId = document.getElementById('filtroEmpresa').value;
    const funcId = document.getElementById('filtroFuncionario').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!emprId || !dataInicio || !dataFim) {
        alert('Preencha empresa e período para filtrar');
        return;
    }
    
    let url = `/comissao-api-retrabalhos?empr_id=${emprId}&dt_inicio=${dataInicio}&dt_fim=${dataFim}`;
    if (funcId) url += `&funcionario_id=${funcId}`;
    
    fetch(url)
        .then(response => response.json())
        .then(result => {
            const tbody = document.getElementById('tabelaRetrabalhosBody');
            tbody.innerHTML = '';
            
            const retrabalhos = result.data || result;
            if (!retrabalhos || retrabalhos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center">Nenhum retrabalho encontrado</td></tr>';
                return;
            }
            
            const tipoDesc = {
                'P': 'Percentual',
                'V': 'Valor Fixo', 
                'Z': 'Zera Dia'
            };
            
            retrabalhos.forEach(ret => {
                let valorImpactoFormatado = '-';
                if (ret.TIPO_IMPACTO === 'P') {
                    valorImpactoFormatado = ret.VALOR_IMPACTO + '%';
                } else if (ret.TIPO_IMPACTO === 'V') {
                    valorImpactoFormatado = 'R$ ' + parseFloat(ret.VALOR_IMPACTO).toFixed(2);
                }
                
                const statusBadge = ret.ATIVO === 'S' 
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-secondary">Inativo</span>';
                
                tbody.innerHTML += `
                    <tr>
                        <td>${ret.ID}</td>
                        <td>${ret.COD_FUNC} - ${ret.NOME_FUNC}</td>
                        <td>${ret.NR_ORDEM || '-'}</td>
                        <td>${ret.ITEM_DESC || '-'}</td>
                        <td>${formatarData(ret.DT_RETRABALHO)}</td>
                        <td>${ret.QUANTIDADE}</td>
                        <td>${tipoDesc[ret.TIPO_IMPACTO] || ret.TIPO_IMPACTO}</td>
                        <td>${valorImpactoFormatado}</td>
                        <td>${ret.MOTIVO}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarRetrabalho(${ret.ID})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="inativarRetrabalho(${ret.ID})" title="Inativar">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        })
        .catch(error => console.error('Erro ao carregar retrabalhos:', error));
}

function novoRetrabalho() {
    document.getElementById('formRetrabalho').reset();
    document.getElementById('retrabalhoId').value = '';
    document.getElementById('modalRetrabalhoTitulo').innerHTML = '<i class="bi bi-arrow-repeat"></i> Registrar Retrabalho';
    document.getElementById('valorImpacto').disabled = false;
    definirDatasIniciais();
}

function salvarRetrabalho() {
    const funcionarioId = document.getElementById('funcionarioId').value;
    const dataRetrabalho = document.getElementById('dataRetrabalho').value;
    const quantidade = document.getElementById('quantidade').value;
    const tipoImpacto = document.getElementById('tipoImpacto').value;
    const valorImpacto = document.getElementById('valorImpacto').value;
    const motivo = document.getElementById('motivo').value;
    const observacao = document.getElementById('observacao').value;
    const emprId = document.getElementById('filtroEmpresa').value;
    const ordemId = document.getElementById('ordemId').value;
    const itemId = document.getElementById('itemId').value;
    
    if (!funcionarioId || !dataRetrabalho || !tipoImpacto || !motivo) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }
    
    if (tipoImpacto !== 'Z' && !valorImpacto) {
        alert('Informe o valor do impacto');
        return;
    }
    
    const dados = {
        id_funcionario: funcionarioId,
        id_empr: emprId,
        id_ordem: ordemId || null,
        id_item: itemId || null,
        dt_retrabalho: dataRetrabalho,
        quantidade: quantidade,
        tipo_impacto: tipoImpacto,
        valor_impacto: tipoImpacto === 'Z' ? 0 : valorImpacto,
        motivo: motivo,
        observacao: observacao
    };
    
    fetch('/comissao-api-retrabalho', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalRetrabalho')).hide();
            alert('Retrabalho registrado com sucesso!');
            carregarRetrabalhos();
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar retrabalho');
    });
}

function inativarRetrabalho(id) {
    if (!confirm('Deseja realmente inativar este retrabalho?')) return;
    
    fetch(`/comissao-api-retrabalho?id=${id}`, { method: 'DELETE' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Retrabalho inativado com sucesso!');
                carregarRetrabalhos();
            } else {
                alert('Erro ao inativar: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao inativar retrabalho');
        });
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}
</script>

<?= $render('footer') ?>
