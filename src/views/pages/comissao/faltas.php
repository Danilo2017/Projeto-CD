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
    'pageTitle' => 'Controle de Faltas',
    'showNavbar' => true,
    'pageActive' => 'comissao-faltas',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroEmpresa">Empresa</label>
                <input type="text" id="filtroEmpresaNome" class="form-control" readonly 
                       value="<?= ($_SESSION['empresa']['codigo'] ?? '') . ' - ' . ($_SESSION['empresa']['nome_fantasia'] ?? 'Não selecionada') ?>">
                <input type="hidden" id="filtroEmpresa" value="<?= $_SESSION['empresa']['id'] ?? '' ?>">
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
                <button type="button" class="btn btn-primary mt-3" onclick="carregarFaltas()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#modalFalta" onclick="novaFalta()">
                    <i class="bi bi-plus-circle"></i> Registrar Falta
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Faltas -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaFaltas" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Data Falta</th>
                    <th>Tipo</th>
                    <th>Observação</th>
                    <th>Cadastrado Por</th>
                    <th>Data Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaFaltasBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro -->
    <div class="modal fade" id="modalFalta" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFaltaTitulo">
                        <i class="bi bi-calendar-x"></i> Registrar Falta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formFalta">
                        <input type="hidden" id="faltaId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="funcionarioId" class="form-label">Funcionário *</label>
                                <select id="funcionarioId" class="form-select" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="dataFalta" class="form-label">Data da Falta *</label>
                                <input type="date" id="dataFalta" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipoFalta" class="form-label">Tipo de Falta *</label>
                                <select id="tipoFalta" class="form-select" required>
                                    <option value="">Selecione</option>
                                    <option value="I">Falta Integral</option>
                                    <option value="P">Falta Parcial</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="observacao" class="form-label">Observação</label>
                                <textarea id="observacao" class="form-control" rows="3" maxlength="500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarFalta()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    carregarFuncionarios();
    definirDatasIniciais();
});

function definirDatasIniciais() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = primeiroDia.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];
}

function carregarFuncionarios() {
    const emprId = document.getElementById('filtroEmpresa').value;
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

function carregarFaltas() {
    const emprId = document.getElementById('filtroEmpresa').value;
    const funcId = document.getElementById('filtroFuncionario').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    if (!emprId || !dataInicio || !dataFim) {
        alert('Preencha empresa e período para filtrar');
        return;
    }
    
    let url = `/comissao-api-faltas?empr_id=${emprId}&dt_inicio=${dataInicio}&dt_fim=${dataFim}`;
    if (funcId) url += `&funcionario_id=${funcId}`;
    
    fetch(url)
        .then(response => response.json())
        .then(result => {
            const tbody = document.getElementById('tabelaFaltasBody');
            tbody.innerHTML = '';
            
            const faltas = result.data || result;
            if (!faltas || faltas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhuma falta encontrada</td></tr>';
                return;
            }
            
            faltas.forEach(falta => {
                const tipoDesc = {
                    'I': 'Falta Integral',
                    'P': 'Falta Parcial'
                };
                
                tbody.innerHTML += `
                    <tr>
                        <td>${falta.ID_FALTA}</td>
                        <td>${falta.COD_FUNC} - ${falta.NOME_FUNCIONARIO}</td>
                        <td>${falta.DT_FALTA_FMT || formatarData(falta.DT_FALTA)}</td>
                        <td>${falta.DESC_TIPO_FALTA || tipoDesc[falta.TIPO_FALTA] || falta.TIPO_FALTA}</td>
                        <td>${falta.MOTIVO || '-'}</td>
                        <td>${falta.USUARIO_NOME || '-'}</td>
                        <td>${falta.DT_CADASTRO || '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="excluirFalta(${falta.ID_FALTA})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        })
        .catch(error => console.error('Erro ao carregar faltas:', error));
}

function novaFalta() {
    document.getElementById('formFalta').reset();
    document.getElementById('faltaId').value = '';
    document.getElementById('modalFaltaTitulo').innerHTML = '<i class="bi bi-calendar-x"></i> Registrar Falta';
}

function salvarFalta() {
    const funcionarioId = document.getElementById('funcionarioId').value;
    const dataFalta = document.getElementById('dataFalta').value;
    const tipoFalta = document.getElementById('tipoFalta').value;
    const observacao = document.getElementById('observacao').value;
    const emprId = document.getElementById('filtroEmpresa').value;
    
    if (!emprId) {
        alert('Selecione uma empresa primeiro');
        return;
    }
    
    if (!funcionarioId || !dataFalta || !tipoFalta) {
        alert('Preencha todos os campos obrigatórios');
        return;
    }
    
    const dados = {
        id_funcionario: funcionarioId,
        id_empr: emprId,
        dt_falta: dataFalta,
        tipo_falta: tipoFalta,
        motivo: observacao
    };
    
    fetch('/comissao-api-falta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalFalta')).hide();
            alert('Falta registrada com sucesso!');
            carregarFaltas();
        } else {
            alert('Erro ao salvar: ' + (data.error || data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar falta');
    });
}

function excluirFalta(id) {
    if (!confirm('Deseja realmente excluir esta falta?')) return;
    
    fetch(`/comissao-api-falta?id=${id}`, { method: 'DELETE' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Falta excluída com sucesso!');
                carregarFaltas();
            } else {
                alert('Erro ao excluir: ' + (data.error || data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir falta: ' + (error && error.message ? error.message : error));
        });
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

function formatarDataHora(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleString('pt-BR');
}
</script>

<?= $render('footer') ?>
