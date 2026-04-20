<?php
// Verificar permissão de admin (dado vem do controller via $viewData)
if (empty($is_admin)) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Gerenciar Permissões de Acesso',
    'showNavbar' => true,
    'pageActive' => 'permissao',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
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
                <label for="filtroPerfil">Perfil</label>
                <select id="filtroPerfil" class="form-select">
                    <option value="">Todos</option>
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
                    <th>Login</th>
                    <th>Perfis</th>
                    <th>Status</th>
                    <th>Dt. Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaPermissoesBody">
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
                        <input type="hidden" id="editandoLogin" value="">

                        <div class="mb-3">
                            <label for="login" class="form-label">Login do Usuário *</label>
                            <input type="text" class="form-control" id="login" name="login" required
                                   placeholder="Digite o login FOCCO do usuário" style="text-transform: uppercase;">
                            <small class="text-muted">O mesmo login usado para autenticar no sistema FOCCO</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Perfis de Acesso *</label>
                            <div class="card">
                                <div class="card-body" id="perfisCheckboxes" style="max-height: 200px; overflow-y: auto;">
                                    <p class="text-muted">Carregando perfis...</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Filiais Permitidas</label>
                            <small class="text-muted d-block mb-2">
                                <i class="bi bi-info-circle"></i> 
                                Se nenhuma filial for selecionada, o usuário terá acesso a todas.
                            </small>
                            <div class="card">
                                <div class="card-body" id="filiaisCheckboxes" style="max-height: 250px; overflow-y: auto;">
                                    <p class="text-muted">Carregando filiais...</p>
                                </div>
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
let perfisDisponiveis = [];
let empresasDisponiveis = [];

document.addEventListener('DOMContentLoaded', function() {
    Promise.all([
        carregarPerfisDisponiveis(),
        carregarEmpresasDisponiveis()
    ]).then(() => carregarPermissoes());
});

function carregarPerfisDisponiveis() {
    return fetch('/permissao-api-perfis')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                perfisDisponiveis = data.data;
                renderizarFiltroPerfis();
                renderizarCheckboxesPerfis();
            }
        })
        .catch(error => console.error('Erro ao carregar perfis:', error));
}

function carregarEmpresasDisponiveis() {
    return fetch('/permissao-api-empresas')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                empresasDisponiveis = data.data;
                renderizarCheckboxesFiliais();
            }
        })
        .catch(error => console.error('Erro ao carregar empresas:', error));
}

function renderizarFiltroPerfis() {
    const select = document.getElementById('filtroPerfil');
    select.innerHTML = '<option value="">Todos</option>';
    perfisDisponiveis.forEach(p => {
        select.innerHTML += `<option value="${p.ID_PERFIL}">${p.NOME}</option>`;
    });
}

function renderizarCheckboxesPerfis(selecionados = []) {
    const container = document.getElementById('perfisCheckboxes');
    if (!perfisDisponiveis.length) {
        container.innerHTML = '<p class="text-muted">Nenhum perfil cadastrado</p>';
        return;
    }

    // Converter selecionados para números para comparação correta
    const selecionadosNum = selecionados.map(Number);

    let html = '';
    perfisDisponiveis.forEach(p => {
        const checked = selecionadosNum.includes(Number(p.ID_PERFIL)) ? 'checked' : '';
        html += `
            <div class="form-check form-switch mb-2">
                <input class="form-check-input perfil-checkbox" type="checkbox" 
                       id="perfil_${p.ID_PERFIL}" value="${p.ID_PERFIL}" ${checked}>
                <label class="form-check-label" for="perfil_${p.ID_PERFIL}">
                    <strong>${p.NOME}</strong>
                    ${p.DESCRICAO ? '<small class="text-muted d-block">' + p.DESCRICAO + '</small>' : ''}
                </label>
            </div>`;
    });
    container.innerHTML = html;
}

function renderizarCheckboxesFiliais(selecionados = []) {
    const container = document.getElementById('filiaisCheckboxes');
    if (!empresasDisponiveis.length) {
        container.innerHTML = '<p class="text-muted">Nenhuma filial cadastrada</p>';
        return;
    }

    // Converter selecionados para números para comparação correta
    const selecionadosNum = selecionados.map(Number);

    let html = `
        <div class="mb-2">
            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="selecionarTodasFiliais()">
                <i class="bi bi-check-all"></i> Todas
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limparFiliais()">
                <i class="bi bi-x-circle"></i> Limpar
            </button>
        </div>
        <hr class="my-2">`;
    
    empresasDisponiveis.forEach(e => {
        const checked = selecionadosNum.includes(Number(e.ID)) ? 'checked' : '';
        html += `
            <div class="form-check mb-1">
                <input class="form-check-input filial-checkbox" type="checkbox" 
                       id="filial_${e.ID}" value="${e.ID}" ${checked}>
                <label class="form-check-label" for="filial_${e.ID}">
                    <strong>${e.CODIGO}</strong> - ${e.NOME_FANTASIA || e.RAZAO_SOCIAL}
                </label>
            </div>`;
    });
    container.innerHTML = html;
}

function selecionarTodasFiliais() {
    document.querySelectorAll('.filial-checkbox').forEach(cb => cb.checked = true);
}

function limparFiliais() {
    document.querySelectorAll('.filial-checkbox').forEach(cb => cb.checked = false);
}

function carregarPermissoes() {
    const login = document.getElementById('filtroLogin').value;
    const perfilId = document.getElementById('filtroPerfil').value;

    let url = '/permissao-api-listar?';
    if (login) url += `login=${encodeURIComponent(login)}&`;
    if (perfilId) url += `perfil_id=${perfilId}&`;

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
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhuma permissão cadastrada</td></tr>';
        return;
    }

    const cores = ['bg-primary', 'bg-info', 'bg-success', 'bg-warning text-dark', 'bg-danger', 'bg-secondary'];

    let html = '';
    dados.forEach(item => {
        const perfisHtml = (item.PERFIS || []).map((nome, i) => 
            `<span class="badge ${cores[i % cores.length]} me-1">${nome}</span>`
        ).join('');

        const badgeStatus = item.ATIVO === 'S'
            ? '<span class="badge bg-success">Ativo</span>'
            : '<span class="badge bg-danger">Inativo</span>';

        const dtCadastro = item.DT_CADASTRO ? formatarData(item.DT_CADASTRO) : '-';

        html += `
            <tr>
                <td><strong>${item.LOGIN_USUARIO}</strong></td>
                <td>${perfisHtml || '<span class="text-muted">Nenhum</span>'}</td>
                <td class="text-center">${badgeStatus}</td>
                <td>${dtCadastro}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editarPermissao('${item.LOGIN_USUARIO}')" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="excluirPermissao('${item.LOGIN_USUARIO}')" title="Remover">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
}

function formatarData(data) {
    if (!data) return '-';
    try {
        const d = new Date(data);
        return d.toLocaleDateString('pt-BR');
    } catch {
        return data;
    }
}

function novaPermissao() {
    document.getElementById('formPermissao').reset();
    document.getElementById('editandoLogin').value = '';
    document.getElementById('login').disabled = false;
    document.getElementById('modalPermissaoTitulo').innerHTML = '<i class="bi bi-shield-plus"></i> Nova Permissão';
    renderizarCheckboxesPerfis([]);
    renderizarCheckboxesFiliais([]);
}

function editarPermissao(login) {
    fetch(`/permissao-api-buscar?login=${encodeURIComponent(login)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const info = data.data;
                document.getElementById('editandoLogin').value = info.LOGIN_USUARIO;
                document.getElementById('login').value = info.LOGIN_USUARIO;
                document.getElementById('login').disabled = true;
                document.getElementById('modalPermissaoTitulo').innerHTML = '<i class="bi bi-shield"></i> Editar Permissão';

                const perfisIds = (info.PERFIS_IDS || []).map(Number);
                renderizarCheckboxesPerfis(perfisIds);

                const filiaisIds = (info.FILIAIS_IDS || []).map(Number);
                renderizarCheckboxesFiliais(filiaisIds);

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
    const editando = document.getElementById('editandoLogin').value;
    const login = document.getElementById('login').value.trim().toUpperCase();

    if (!login) {
        alert('Digite o login do usuário');
        return;
    }

    const perfis = [];
    document.querySelectorAll('.perfil-checkbox:checked').forEach(cb => {
        perfis.push(Number(cb.value));
    });

    if (perfis.length === 0) {
        alert('Selecione ao menos um perfil');
        return;
    }

    const filiais = [];
    document.querySelectorAll('.filial-checkbox:checked').forEach(cb => {
        filiais.push(Number(cb.value));
    });

    const url = editando ? '/permissao-api-atualizar' : '/permissao-api-salvar';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ login, perfis, filiais })
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

function excluirPermissao(login) {
    if (!confirm(`Deseja realmente remover todas as permissões de ${login}?`)) {
        return;
    }

    fetch('/permissao-api-excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ login })
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
