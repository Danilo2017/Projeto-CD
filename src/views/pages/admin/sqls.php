<?php
if (empty($is_admin)) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Gerenciar SQLs do Sistema',
    'showNavbar' => true,
    'pageActive' => 'admin-sqls',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <div class="dashboard-header" style="margin-bottom: 20px;">
        <h4><i class="bi bi-database-gear"></i> Gerenciar SQLs do Sistema</h4>
        <small class="text-muted">Tabela: FOCCO3I.GAZIN_SQLS</small>
    </div>

    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroBusca">Buscar (idsql ou conteúdo SQL)</label>
                <input type="text" id="filtroBusca" class="form-control" placeholder="Ex: comissao.vinculo...">
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary" onclick="carregarSqls()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSql" onclick="novoSql()">
                    <i class="bi bi-plus-circle"></i> Novo SQL
                </button>
            </div>
        </div>
    </div>

    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaSqls" style="width: 100%;">
            <thead>
                <tr>
                    <th>IDSQL</th>
                    <th>SQL (prévia)</th>
                    <th style="width: 120px;">Ações</th>
                </tr>
            </thead>
            <tbody id="tbodySqls">
                <tr><td colspan="3" class="text-center">Clique em Filtrar para carregar</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Editar/Novo SQL -->
<div class="modal fade" id="modalSql" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSqlTitulo">Novo SQL</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="inputIdsql" class="form-label fw-bold">IDSQL</label>
                    <input type="text" class="form-control" id="inputIdsql" placeholder="modulo.entidade.acao">
                    <small class="text-muted">Formato: modulo.entidade.acao (ex: comissao.vinculo.listar)</small>
                </div>
                <div class="mb-3">
                    <label for="inputSql" class="form-label fw-bold">SQL</label>
                    <textarea class="form-control font-monospace" id="inputSql" rows="12" placeholder="SELECT ..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarSql" onclick="salvarSql()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
let modoEdicao = false;
let idsqlOriginal = '';

function carregarSqls() {
    const busca = document.getElementById('filtroBusca').value;
    const tbody = document.getElementById('tbodySqls');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>';

    fetch(`${window.location.origin}/admin-api-sqls?busca=${encodeURIComponent(busca)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Nenhum SQL encontrado</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.map(row => {
                const previa = (row.SQL || '').substring(0, 120).replace(/</g, '&lt;');
                return `<tr>
                    <td><code>${row.IDSQL}</code></td>
                    <td><small class="text-muted font-monospace">${previa}...</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarSql('${row.IDSQL}')"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="excluirSql('${row.IDSQL}')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Erro ao carregar: ' + err.message + '</td></tr>';
        });
}

function novoSql() {
    modoEdicao = false;
    idsqlOriginal = '';
    document.getElementById('modalSqlTitulo').textContent = 'Novo SQL';
    document.getElementById('inputIdsql').value = '';
    document.getElementById('inputIdsql').readOnly = false;
    document.getElementById('inputSql').value = '';
}

function editarSql(idsql) {
    modoEdicao = true;
    idsqlOriginal = idsql;
    document.getElementById('modalSqlTitulo').textContent = 'Editar SQL: ' + idsql;
    document.getElementById('inputIdsql').value = idsql;
    document.getElementById('inputIdsql').readOnly = true;
    document.getElementById('inputSql').value = 'Carregando...';

    const modal = new bootstrap.Modal(document.getElementById('modalSql'));
    modal.show();

    fetch(`${window.location.origin}/admin-api-sql?idsql=${encodeURIComponent(idsql)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                document.getElementById('inputSql').value = res.data.SQL || '';
            } else {
                document.getElementById('inputSql').value = '-- Erro ao carregar';
            }
        });
}

function salvarSql() {
    const idsql = document.getElementById('inputIdsql').value.trim();
    const sql = document.getElementById('inputSql').value.trim();

    if (!idsql || !sql) {
        alert('Preencha todos os campos');
        return;
    }

    const url = modoEdicao ? '/admin-api-sql-atualizar' : '/admin-api-sql-salvar';
    const method = modoEdicao ? 'PUT' : 'POST';

    fetch(window.location.origin + url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ idsql, sql })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalSql')).hide();
            carregarSqls();
            alert(res.message || 'Salvo com sucesso');
        } else {
            alert('Erro: ' + (res.error || 'Erro desconhecido'));
        }
    })
    .catch(err => alert('Erro: ' + err.message));
}

function excluirSql(idsql) {
    if (!confirm('Tem certeza que deseja excluir o SQL: ' + idsql + '?')) return;

    fetch(window.location.origin + '/admin-api-sql-excluir', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ idsql })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            carregarSqls();
        } else {
            alert('Erro: ' + (res.error || 'Erro desconhecido'));
        }
    })
    .catch(err => alert('Erro: ' + err.message));
}

document.getElementById('filtroBusca').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') carregarSqls();
});
</script>

<?= $render('footer') ?>
