<?php
// Verificar permissão de admin
$isAdmin = $_SESSION['user']['admin'] ?? 'N';
if ($isAdmin !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Gerenciar Permissões de Acesso',
    'showNavbar' => true,
    'pageActive' => 'permissao',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <!-- Header -->
    <div class="dashboard-header" style="margin-bottom: 20px;">
        <h4><i class="bi bi-shield-lock"></i> Gerenciar Permissões de Acesso</h4>
    </div>

    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroLogin">Login do Usuário</label>
                <input type="text" id="filtroLogin" class="form-control" placeholder="Digite o login...">
            </div>
            <div class="filter-group">
                <label for="filtroAtivo">Status</label>
                <select id="filtroAtivo" class="form-select">
                    <option value="">Todos</option>
                    <option value="S" selected>Ativos</option>
                    <option value="N">Inativos</option>
                </select>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary" onclick="carregarPermissoes()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPermissao" onclick="novaPermissao()">
                    <i class="bi bi-plus-circle"></i> Nova Permissão
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Permissões -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaPermissoes" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Login</th>
                    <th>Acesso CD</th>
                    <th>Acesso Comissão</th>
                    <th>Admin</th>
                    <th>Status</th>
                    <th>Dt. Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaPermissoesBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalPermissao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalPermissaoTitulo">
                        <i class="bi bi-shield-plus"></i> Nova Permissão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPermissao">
                        <input type="hidden" id="permissaoId">
                        
                        <div class="mb-3">
                            <label for="login" class="form-label">Login do Usuário *</label>
                            <input type="text" class="form-control" id="login" name="login" required 
                                   placeholder="Digite o login FOCCO do usuário" style="text-transform: uppercase;">
                            <small class="text-muted">O mesmo login usado para autenticar no sistema FOCCO</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissões de Acesso</label>
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="acesso_cd" name="acesso_cd">
                                        <label class="form-check-label" for="acesso_cd">
                                            <i class="bi bi-box-seam text-info"></i> Acesso ao Módulo CD
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="acesso_comissao" name="acesso_comissao">
                                        <label class="form-check-label" for="acesso_comissao">
                                            <i class="bi bi-cash-stack text-success"></i> Acesso ao Módulo Comissão
                                        </label>
                                    </div>
                                    <hr>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="admin" name="admin">
                                        <label class="form-check-label" for="admin">
                                            <i class="bi bi-person-gear text-danger"></i> Administrador
                                            <small class="text-muted d-block">Pode gerenciar permissões de outros usuários</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="statusGroup" style="display: none;">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                                <label class="form-check-label" for="ativo">Ativo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarPermissao()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    carregarPermissoes();
});

function carregarPermissoes() {
    const login = document.getElementById('filtroLogin').value;
    const ativo = document.getElementById('filtroAtivo').value;
    
    let url = '/permissao-api-listar?';
    if (login) url += `login=${encodeURIComponent(login)}&`;
    if (ativo) url += `ativo=${ativo}&`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarTabela(data.data);
            } else {
                alert('Erro ao carregar permissões: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar permissões');
        });
}

function renderizarTabela(dados) {
    const tbody = document.getElementById('tabelaPermissoesBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhuma permissão cadastrada</td></tr>';
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const badgeCd = item.ACESSO_CD === 'S' 
            ? '<span class="badge bg-info">Sim</span>' 
            : '<span class="badge bg-secondary">Não</span>';
        const badgeComissao = item.ACESSO_COMISSAO === 'S' 
            ? '<span class="badge bg-success">Sim</span>' 
            : '<span class="badge bg-secondary">Não</span>';
        const badgeAdmin = item.ADMIN === 'S' 
            ? '<span class="badge bg-danger">Sim</span>' 
            : '<span class="badge bg-secondary">Não</span>';
        const badgeStatus = item.ATIVO === 'S' 
            ? '<span class="badge bg-success">Ativo</span>' 
            : '<span class="badge bg-danger">Inativo</span>';
        
        const dtCadastro = item.DT_CADASTRO ? formatarData(item.DT_CADASTRO) : '-';
        
        html += `
            <tr>
                <td>${item.ID_ACESSO}</td>
                <td><strong>${item.LOGIN_USUARIO}</strong></td>
                <td class="text-center">${badgeCd}</td>
                <td class="text-center">${badgeComissao}</td>
                <td class="text-center">${badgeAdmin}</td>
                <td class="text-center">${badgeStatus}</td>
                <td>${dtCadastro}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editarPermissao(${item.ID_ACESSO})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="excluirPermissao(${item.ID_ACESSO})" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function formatarData(data) {
    if (!data) return '-';
    // Formato Oracle: DD-MON-YY ou YYYY-MM-DD
    try {
        const d = new Date(data);
        return d.toLocaleDateString('pt-BR');
    } catch {
        return data;
    }
}

function novaPermissao() {
    document.getElementById('formPermissao').reset();
    document.getElementById('permissaoId').value = '';
    document.getElementById('login').disabled = false;
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('modalPermissaoTitulo').innerHTML = '<i class="bi bi-shield-plus"></i> Nova Permissão';
}

function editarPermissao(id) {
    fetch(`/permissao-api-buscar?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.data;
                document.getElementById('permissaoId').value = p.ID_ACESSO;
                document.getElementById('login').value = p.LOGIN_USUARIO;
                document.getElementById('login').disabled = true;
                document.getElementById('acesso_cd').checked = p.ACESSO_CD === 'S';
                document.getElementById('acesso_comissao').checked = p.ACESSO_COMISSAO === 'S';
                document.getElementById('admin').checked = p.ADMIN === 'S';
                document.getElementById('ativo').checked = p.ATIVO === 'S';
                document.getElementById('statusGroup').style.display = 'block';
                document.getElementById('modalPermissaoTitulo').innerHTML = '<i class="bi bi-shield"></i> Editar Permissão';
                
                const modal = new bootstrap.Modal(document.getElementById('modalPermissao'));
                modal.show();
            } else {
                alert('Erro ao carregar permissão: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar permissão');
        });
}

function salvarPermissao() {
    const id = document.getElementById('permissaoId').value;
    const login = document.getElementById('login').value.trim().toUpperCase();
    const acessoCd = document.getElementById('acesso_cd').checked ? 'S' : 'N';
    const acessoComissao = document.getElementById('acesso_comissao').checked ? 'S' : 'N';
    const admin = document.getElementById('admin').checked ? 'S' : 'N';
    const ativo = document.getElementById('ativo').checked ? 'S' : 'N';
    
    if (!login) {
        alert('Digite o login do usuário');
        return;
    }
    
    const url = id ? '/permissao-api-atualizar' : '/permissao-api-salvar';
    const body = id 
        ? { id, acesso_cd: acessoCd, acesso_comissao: acessoComissao, admin, ativo }
        : { login, acesso_cd: acessoCd, acesso_comissao: acessoComissao, admin };
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('modalPermissao')).hide();
            carregarPermissoes();
        } else {
            alert('Erro: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar permissão');
    });
}

function excluirPermissao(id) {
    if (!confirm('Deseja realmente remover esta permissão?')) {
        return;
    }
    
    fetch('/permissao-api-excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            carregarPermissoes();
        } else {
            alert('Erro: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir permissão');
    });
}
</script>

<?= $render('footer') ?>
