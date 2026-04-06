<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Relatório de Faltas por Funcionário',
    'showNavbar' => true,
    'pageActive' => 'comissao-relatorio-faltas',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid p-3">
    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Empresa</label>
                    <input type="text" class="form-control form-control-sm" readonly 
                           value="<?= ($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '') ?>">
                    <input type="hidden" id="filtroEmpresa" value="<?= $empresa['id'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label for="filtroFuncionario" class="form-label">Funcionário</label>
                    <select id="filtroFuncionario" class="form-select form-select-sm" style="width: 100%;">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtroDataInicio" class="form-label">Data Início</label>
                    <input type="date" id="filtroDataInicio" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label for="filtroDataFim" class="form-label">Data Fim</label>
                    <input type="date" id="filtroDataFim" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label for="filtroTipoFalta" class="form-label">Tipo</label>
                    <select id="filtroTipoFalta" class="form-select form-select-sm" style="min-width: 150px;">
                        <option value="">Todos</option>
                        <option value="I">Integral</option>
                        <option value="P">Parcial</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="carregarRelatorio()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Faltas -->
    <div class="card">
        <div class="card-header">
            <strong><i class="bi bi-calendar-x"></i> Faltas no Período</strong>
            <span id="totalRegistros" class="badge bg-secondary ms-2">0</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="tabelaFaltas">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Funcionário</th>
                        <th>Data da Falta</th>
                        <th>Tipo</th>
                        <th>Motivo/Observação</th>
                        <th>Cadastrado Por</th>
                        <th>Data Cadastro</th>
                    </tr>
                </thead>
                <tbody id="tabelaFaltasBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Clique em Filtrar para carregar</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let funcionariosCache = [];

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
            funcionariosCache = result.data || [];
            inicializarSelect2();
        })
        .catch(error => console.error('Erro ao carregar funcionários:', error));
}

function inicializarSelect2() {
    if ($('#filtroFuncionario').data('select2')) {
        $('#filtroFuncionario').select2('destroy');
    }
    
    $('#filtroFuncionario').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true,
        data: [{id: '', text: 'Todos'}].concat(funcionariosCache.map(f => ({
            id: f.ID,
            text: (f.COD_FUNC ? f.COD_FUNC + ' - ' : '') + f.NOME
        })))
    });
}

function carregarRelatorio() {
    const emprId = document.getElementById('filtroEmpresa').value;
    const funcId = document.getElementById('filtroFuncionario').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    const tipoFalta = document.getElementById('filtroTipoFalta').value;
    
    if (!emprId || !dataInicio || !dataFim) {
        alert('Preencha empresa e período para filtrar');
        return;
    }
    
    let url = `/comissao-api-relatorio-faltas?empr_id=${emprId}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
    if (funcId) url += `&funcionario_id=${funcId}`;
    if (tipoFalta) url += `&tipo_falta=${tipoFalta}`;
    
    const tbody = document.getElementById('tabelaFaltasBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Carregando...</td></tr>';
    
    fetch(url)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.error || 'Erro ao carregar relatório');
            }
            
            const faltas = result.data || [];
            const resumo = result.resumo || {};
            
            document.getElementById('totalRegistros').textContent = faltas.length;
            
            // Preencher tabela detalhada
            if (faltas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhuma falta encontrada no período</td></tr>';
            } else {
                tbody.innerHTML = '';
                faltas.forEach(falta => {
                    const tipoDesc = falta.TIPO_FALTA === 'I' ? 
                        '<span class="badge bg-danger">Integral</span>' : 
                        '<span class="badge bg-warning text-dark">Parcial</span>';
                    
                    tbody.innerHTML += `
                        <tr>
                            <td>${falta.COD_FUNC || '-'}</td>
                            <td>${falta.NOME_FUNCIONARIO || falta.NOME || '-'}</td>
                            <td>${falta.DT_FALTA_FMT || formatarData(falta.DT_FALTA)}</td>
                            <td>${tipoDesc}</td>
                            <td>${falta.MOTIVO || '-'}</td>
                            <td>${falta.USUARIO_NOME || '-'}</td>
                            <td>${falta.DT_CADASTRO || '-'}</td>
                        </tr>
                    `;
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Erro ao carregar relatório</td></tr>';
        });
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

function exportarExcel() {
    const tbody = document.getElementById('tabelaFaltasBody');
    const rows = tbody.querySelectorAll('tr');
    
    if (rows.length === 0 || (rows.length === 1 && rows[0].querySelectorAll('td').length === 1)) {
        alert('Carregue o relatório antes de exportar');
        return;
    }
    
    let csv = 'Código;Funcionário;Data da Falta;Tipo;Motivo;Cadastrado Por;Data Cadastro\n';
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 7) {
            const linha = [];
            cols.forEach(col => {
                let valor = col.textContent.trim();
                linha.push('"' + valor.replace(/"/g, '""') + '"');
            });
            csv += linha.join(';') + '\n';
        }
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    link.href = URL.createObjectURL(blob);
    link.download = `relatorio_faltas_${dataInicio}_${dataFim}.csv`;
    link.click();
}
</script>

<?= $render('footer') ?>
