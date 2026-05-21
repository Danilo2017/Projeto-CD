<?php
/**
 * Variáveis injetadas pelo Controller via extract():
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 */
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
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
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 10px; margin: 0;">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>comissao-cadastro" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroEmpresa">Empresa</label>
                <input type="text" id="filtroEmpresaNome" class="form-control" readonly 
                       value="<?= ($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? 'Não selecionada') ?>">
                <input type="hidden" id="filtroEmpresa" value="<?= $empresa['id'] ?? '' ?>">
            </div>
            <div class="filter-group" style="min-width: 250px;">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select select2-funcionario" style="width: 100%;">
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
                <button type="button" class="btn btn-sm btn-primary" onclick="carregarFaltas()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalFalta" onclick="novaFalta()">
                    <i class="bi bi-plus-circle"></i> Registrar Falta
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalFaltaImport" onclick="novaImportacao()">
                    <i class="bi bi-file-earmark-excel"></i> Importar Excel/CSV
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
                                <select id="funcionarioId" class="form-select select2-funcionario-modal" style="width: 100%;" required>
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

    <!-- Modal de Importação Excel/CSV -->
    <div class="modal fade" id="modalFaltaImport" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-excel"></i> Importar Faltas (Excel / CSV)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong>Colunas esperadas (na primeira linha):</strong>
                        <code>COD_FUNC</code>, <code>DT_FALTA</code>, <code>TIPO_FALTA</code> (I = Integral, P = Parcial), <code>MOTIVO</code> (opcional).<br>
                        Aceita formatos <code>.xlsx</code>, <code>.xls</code> e <code>.csv</code>.
                        Datas podem estar em <code>DD/MM/AAAA</code> ou <code>AAAA-MM-DD</code>.<br>
                        Faltas já cadastradas (mesmo funcionário+data) serão ignoradas automaticamente.
                        <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="baixarTemplateFaltas()">
                            <i class="bi bi-download"></i> Baixar modelo CSV
                        </button>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="arquivoImport" class="form-label">Arquivo *</label>
                            <input type="file" id="arquivoImport" class="form-control" accept=".xlsx,.xls,.csv" onchange="lerArquivoImport(event)">
                        </div>
                    </div>

                    <div id="resumoPreview" class="mb-3" style="display:none;">
                        <span class="badge bg-success me-2">Válidas: <span id="contValidas">0</span></span>
                        <span class="badge bg-danger me-2">Inválidas: <span id="contInvalidas">0</span></span>
                        <span class="badge bg-secondary">Total: <span id="contTotal">0</span></span>
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="tabelaPreview" style="display:none;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>COD_FUNC</th>
                                    <th>Funcionário</th>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Motivo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning btn-sm" id="btnConfirmarImport" onclick="confirmarImportacao()" disabled>
                        <i class="bi bi-cloud-upload"></i> Importar válidas
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SheetJS (leitura de XLSX/CSV no navegador) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/pt-BR.js"></script>
<script>
let funcionariosCache = [];

document.addEventListener('DOMContentLoaded', function() {
    carregarFuncionarios();
    definirDatasIniciais();
    
    // Carregar faltas automaticamente após a página carregar
    setTimeout(() => carregarFaltas(), 500);
    
    // Reinicializar Select2 do modal quando abrir
    $('#modalFalta').on('shown.bs.modal', function() {
        inicializarSelect2Modal();
    });
});

function definirDatasIniciais() {
    const hoje = new Date();
    const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    
    document.getElementById('filtroDataInicio').value = primeiroDia.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];
}

function inicializarSelect2Filtro() {
    $('#filtroFuncionario').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite para buscar...',
        allowClear: true,
        data: [{id: '', text: 'Todos'}].concat(funcionariosCache.map(f => ({
            id: f.ID,
            text: f.COD_FUNC + ' - ' + f.NOME
        })))
    });
}

function inicializarSelect2Modal() {
    // Destruir se já existir
    if ($('#funcionarioId').hasClass('select2-hidden-accessible')) {
        $('#funcionarioId').select2('destroy');
    }
    
    $('#funcionarioId').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true,
        dropdownParent: $('#modalFalta'),
        data: [{id: '', text: 'Selecione'}].concat(funcionariosCache.map(f => ({
            id: f.ID,
            text: f.COD_FUNC + ' - ' + f.NOME
        })))
    });
}

function carregarFuncionarios() {
    const emprId = document.getElementById('filtroEmpresa').value;
    if (!emprId) return;
    
    fetch(`/comissao-api-funcionarios?empr_id=${emprId}`)
        .then(response => response.json())
        .then(result => {
            funcionariosCache = result.data || result;
            inicializarSelect2Filtro();
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
    
    // Limpar Select2 do modal
    if ($('#funcionarioId').hasClass('select2-hidden-accessible')) {
        $('#funcionarioId').val('').trigger('change');
    }
}

function salvarFalta() {
    const btnSalvar = document.querySelector('#modalFalta .btn-primary');
    const btnTextoOriginal = btnSalvar.innerHTML;
    
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
    
    // Desabilitar botão e mostrar loading
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';
    
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
    })
    .finally(() => {
        // Reabilitar botão
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = btnTextoOriginal;
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

// ========== Importação Excel/CSV ==========
let registrosImport = []; // [{ linha, cod_func, id_funcionario, nome, dt_falta, tipo_falta, motivo, valido, erro }]

function novaImportacao() {
    registrosImport = [];
    document.getElementById('arquivoImport').value = '';
    document.getElementById('tabelaPreview').style.display = 'none';
    document.getElementById('tabelaPreviewBody').innerHTML = '';
    document.getElementById('resumoPreview').style.display = 'none';
    document.getElementById('btnConfirmarImport').disabled = true;
}

function baixarTemplateFaltas() {
    const csv = 'COD_FUNC;DT_FALTA;TIPO_FALTA;MOTIVO\n' +
                '1234;01/05/2026;I;Atestado médico\n' +
                '1235;01/05/2026;P;Saída antecipada\n';
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'modelo_importacao_faltas.csv';
    link.click();
}

function lerArquivoImport(ev) {
    const file = ev.target.files[0];
    if (!file) return;
    if (typeof XLSX === 'undefined') {
        alert('Biblioteca de leitura de Excel não carregou. Recarregue a página.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array', cellDates: true });
            const firstSheetName = workbook.SheetNames[0];
            const sheet = workbook.Sheets[firstSheetName];
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false, defval: '' });
            processarLinhasImport(rows);
        } catch (err) {
            console.error(err);
            alert('Erro ao ler arquivo: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

function processarLinhasImport(rows) {
    if (!rows || rows.length < 2) {
        alert('Arquivo vazio ou sem dados.');
        return;
    }

    const header = rows[0].map(h => String(h || '').trim().toUpperCase());
    const idxCod = header.indexOf('COD_FUNC');
    const idxData = header.indexOf('DT_FALTA');
    const idxTipo = header.indexOf('TIPO_FALTA');
    const idxMot = header.indexOf('MOTIVO');

    if (idxCod === -1 || idxData === -1 || idxTipo === -1) {
        alert('Cabeçalho inválido. Esperado: COD_FUNC, DT_FALTA, TIPO_FALTA, MOTIVO');
        return;
    }

    const mapCod = {};
    funcionariosCache.forEach(f => { mapCod[String(f.COD_FUNC)] = f; });

    registrosImport = [];
    for (let i = 1; i < rows.length; i++) {
        const r = rows[i];
        if (!r || r.every(c => String(c || '').trim() === '')) continue;

        const codFunc = String(r[idxCod] || '').trim();
        const dataRaw = r[idxData];
        const tipoRaw = String(r[idxTipo] || 'I').trim().toUpperCase();
        const motivo = idxMot >= 0 ? String(r[idxMot] || '').trim() : '';

        let erro = '';
        const dtFalta = converterDataImport(dataRaw);
        const tipo = (tipoRaw === 'P' || tipoRaw === 'PARCIAL') ? 'P' : 'I';

        const func = mapCod[codFunc];
        if (!codFunc) erro = 'COD_FUNC vazio';
        else if (!func) erro = 'Funcionário não encontrado';
        else if (!dtFalta) erro = 'Data inválida';

        registrosImport.push({
            linha: i + 1,
            cod_func: codFunc,
            id_funcionario: func ? func.ID : null,
            nome: func ? func.NOME : '-',
            dt_falta: dtFalta,
            tipo_falta: tipo,
            motivo: motivo,
            valido: !erro,
            erro: erro
        });
    }

    renderizarPreviewImport();
}

function converterDataImport(valor) {
    if (!valor) return '';
    const s = String(valor).trim();
    let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (m) return m[1] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[3]).padStart(2, '0');
    m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (m) return m[3] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
    m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/);
    if (m) {
        const ano = parseInt(m[3], 10) < 50 ? '20' + m[3] : '19' + m[3];
        return ano + '-' + String(m[1]).padStart(2, '0') + '-' + String(m[2]).padStart(2, '0');
    }
    return '';
}

function renderizarPreviewImport() {
    const tbody = document.getElementById('tabelaPreviewBody');
    tbody.innerHTML = '';
    let validas = 0, invalidas = 0;

    registrosImport.forEach(r => {
        if (r.valido) validas++; else invalidas++;
        const status = r.valido
            ? '<span class="badge bg-success">OK</span>'
            : '<span class="badge bg-danger" title="' + r.erro + '">' + r.erro + '</span>';
        const tipoTxt = r.tipo_falta === 'P' ? 'Parcial' : 'Integral';
        tbody.innerHTML += `
            <tr class="${r.valido ? '' : 'table-danger'}">
                <td>${r.linha}</td>
                <td>${r.cod_func}</td>
                <td>${r.nome}</td>
                <td>${r.dt_falta || '-'}</td>
                <td>${tipoTxt}</td>
                <td>${r.motivo || '-'}</td>
                <td>${status}</td>
            </tr>
        `;
    });

    document.getElementById('contValidas').textContent = validas;
    document.getElementById('contInvalidas').textContent = invalidas;
    document.getElementById('contTotal').textContent = registrosImport.length;
    document.getElementById('resumoPreview').style.display = '';
    document.getElementById('tabelaPreview').style.display = '';
    document.getElementById('btnConfirmarImport').disabled = (validas === 0);
}

function confirmarImportacao() {
    const validos = registrosImport.filter(r => r.valido).map(r => ({
        linha: r.linha,
        id_funcionario: r.id_funcionario,
        dt_falta: r.dt_falta,
        tipo_falta: r.tipo_falta,
        motivo: r.motivo
    }));

    if (validos.length === 0) {
        alert('Nenhum registro válido para importar.');
        return;
    }

    if (!confirm('Importar ' + validos.length + ' registro(s)?')) return;

    const btn = document.getElementById('btnConfirmarImport');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importando...';

    const emprId = document.getElementById('filtroEmpresa').value;

    fetch('/comissao-api-faltas-import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_empr: emprId, registros: validos })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalFaltaImport')).hide();
            let msg = data.message || 'Concluído';
            if (data.resumo && data.resumo.erros && data.resumo.erros.length > 0) {
                msg += '\n\nErros:\n' + data.resumo.erros.slice(0, 20).map(e => '- Linha ' + e.linha + ' (Func ' + (e.id_funcionario || '?') + '): ' + e.mensagem).join('\n');
                if (data.resumo.erros.length > 20) msg += '\n... e mais ' + (data.resumo.erros.length - 20) + ' erro(s).';
            }
            alert(msg);
            carregarFaltas();
        } else {
            alert('Erro: ' + (data.error || data.message || 'Erro desconhecido'));
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        alert('Erro ao importar faltas');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
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
