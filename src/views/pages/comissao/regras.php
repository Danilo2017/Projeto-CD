<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Regras por Funcionário',
    'showNavbar' => true,
    'pageActive' => 'comissao-regras',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 5px 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroEmpresa">Empresa</label>
                <input type="text" id="filtroEmpresaDisplay" class="form-control" readonly style="background-color: #e9ecef;">
                <input type="hidden" id="filtroEmpresa" value="<?= $empresa['id'] ?? '' ?>">
            </div>
            <div class="filter-group" style="min-width: 280px;">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select" style="width: 100%;">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="form-check mt-4">
                    <input type="checkbox" id="incluirInativas" class="form-check-input">
                    <label for="incluirInativas" class="form-check-label">Incluir inativas</label>
                </div>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary mt-3" onclick="carregarRegras()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#modalRegra" onclick="novaRegra()">
                    <i class="bi bi-plus-circle"></i> Nova Regra
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Regras -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaRegras" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Funcionário</th>
                    <th>Tipo Regra</th>
                    <th>Valor Base</th>
                    <th>Detalhes</th>
                    <th>Prioridade</th>
                    <th>Vigência</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaRegrasBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalRegra" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegraTitulo">
                        <i class="bi bi-sliders"></i> Nova Regra por Funcionário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formRegra">
                        <input type="hidden" id="regraId">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="funcionarioId" class="form-label">Funcionário *</label>
                                <select id="funcionarioId" class="form-select" style="width: 100%;" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tipoRegra" class="form-label">Tipo de Comissão *</label>
                                <select id="tipoRegra" class="form-select" required onchange="atualizarCamposRegra()">
                                    <option value="">Selecione</option>
                                    <option value="P">Percentual sobre Pontos</option>
                                    <option value="V">Valor por UP</option>
                                    <option value="F">Valor Fixo Total</option>
                                    <option value="M">Valor Fixo + Valor por Ponto</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4" id="divValorFixoBase" style="display:none;">
                                <label for="valorFixoBase" class="form-label">Valor Fixo Base (R$) *</label>
                                <input type="number" id="valorFixoBase" class="form-control" step="0.01" min="0">
                                <small class="text-muted">Valor fixo garantido independente da produção</small>
                            </div>
                            <div class="col-md-4" id="divValorBase">
                                <label for="valorBase" class="form-label" id="lblValorBase">Valor por Ponto (R$)</label>
                                <input type="number" id="valorBase" class="form-control" step="0.01" min="0">
                                <small id="helpValorBase" class="text-muted">Valor fixo em R$ por unidade produzida</small>
                            </div>
                            <div class="col-md-4" id="divPercentual">
                                <label for="percentual" class="form-label">Percentual (%)</label>
                                <input type="number" id="percentual" class="form-control" step="0.01" min="0" max="1000">
                                <small id="helpPercentual" class="text-muted"></small>
                            </div>
                            <div class="col-md-4">
                                <label for="prioridade" class="form-label">Prioridade *</label>
                                <input type="number" id="prioridade" class="form-control" min="1" max="99" value="1" required>
                                <small class="text-muted">Menor número = maior prioridade</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dtVigenciaIni" class="form-label">Vigência Início *</label>
                                <input type="date" id="dtVigenciaIni" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="dtVigenciaFim" class="form-label">Vigência Fim</label>
                                <input type="date" id="dtVigenciaFim" class="form-control">
                                <small class="text-muted">Deixe em branco para vigência indeterminada</small>
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
                    <button type="button" class="btn btn-primary btn-sm" id="btnSalvarRegra" onclick="salvarRegra()">
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
});

function carregarEmpresas() {
    const empresaSessao = '<?= $empresa['id'] ?? '' ?>';
    const empresaNome = '<?= ($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '') ?>';
    
    // Exibir empresa travada no campo readonly
    document.getElementById('filtroEmpresaDisplay').value = empresaNome;
    document.getElementById('filtroEmpresa').value = empresaSessao;
    
    if (empresaSessao) {
        carregarFuncionarios(empresaSessao);
    }
}

let funcionariosCache = [];

function carregarFuncionarios(emprId) {
    if (!emprId) return;
    
    fetch(`/comissao-api-funcionarios?empr_id=${emprId}`)
        .then(response => response.json())
        .then(result => {
            funcionariosCache = result.data || result || [];
            inicializarSelect2Funcionarios();
        })
        .catch(error => console.error('Erro ao carregar funcionários:', error));
}

function inicializarSelect2Funcionarios() {
    // Limpar opções existentes e popular com cache
    const selectFiltro = document.getElementById('filtroFuncionario');
    const selectModal = document.getElementById('funcionarioId');
    
    selectFiltro.innerHTML = '<option value="">Todos</option>';
    selectModal.innerHTML = '<option value="">Selecione</option>';
    
    funcionariosCache.forEach(f => {
        const texto = (f.COD_FUNC || '') + ' - ' + (f.NOME || '');
        selectFiltro.innerHTML += `<option value="${f.ID}">${texto}</option>`;
        selectModal.innerHTML += `<option value="${f.ID}">${texto}</option>`;
    });
    
    // Filtro
    if ($('#filtroFuncionario').data('select2')) {
        $('#filtroFuncionario').select2('destroy');
    }
    $('#filtroFuncionario').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true
    });
    
    // Modal - inicializar quando o modal abrir
    $('#modalRegra').off('shown.bs.modal').on('shown.bs.modal', function() {
        if ($('#funcionarioId').data('select2')) {
            $('#funcionarioId').select2('destroy');
        }
        $('#funcionarioId').select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            placeholder: 'Digite código ou nome...',
            allowClear: true,
            dropdownParent: $('#modalRegra .modal-content')
        });
    });
}

// Empresa travada - não precisa listener de change

function atualizarCamposRegra() {
    const tipo = document.getElementById('tipoRegra').value;
    const valorBase = document.getElementById('valorBase');
    const valorFixoBase = document.getElementById('valorFixoBase');
    const percentual = document.getElementById('percentual');
    const helpValorBase = document.getElementById('helpValorBase');
    const helpPercentual = document.getElementById('helpPercentual');
    const lblValorBase = document.getElementById('lblValorBase');
    const divValorBase = document.getElementById('divValorBase');
    const divValorFixoBase = document.getElementById('divValorFixoBase');
    const divPercentual = document.getElementById('divPercentual');
    
    // Reset campos
    valorBase.disabled = false;
    percentual.disabled = true;
    percentual.value = '';
    divValorFixoBase.style.display = 'none';
    divValorBase.style.display = 'block';
    divPercentual.style.display = 'block';
    valorFixoBase.value = '';
    
    switch(tipo) {
        case 'P':
            lblValorBase.textContent = 'Percentual (%)';
            helpValorBase.textContent = 'Percentual sobre os pontos produzidos';
            divPercentual.style.display = 'none';
            break;
        case 'V':
            lblValorBase.textContent = 'Valor por Ponto (R$)';
            helpValorBase.textContent = 'Valor em R$ multiplicado por cada ponto produzido';
            divPercentual.style.display = 'none';
            break;
        case 'F':
            lblValorBase.textContent = 'Valor Fixo Total (R$)';
            helpValorBase.textContent = 'Valor fixo da comissão (NÃO multiplica pela produção)';
            divPercentual.style.display = 'none';
            break;
        case 'M':
            // Tipo Misto: Valor Fixo + Valor por Ponto
            divValorFixoBase.style.display = 'block';
            lblValorBase.textContent = 'Valor por Ponto (R$)';
            helpValorBase.textContent = 'Valor adicional por cada ponto produzido';
            divPercentual.style.display = 'none';
            break;
        default:
            lblValorBase.textContent = 'Valor';
            helpValorBase.textContent = '';
            divPercentual.style.display = 'none';
    }
}

function carregarRegras() {
    const emprId = document.getElementById('filtroEmpresa').value;
    const funcId = document.getElementById('filtroFuncionario').value;
    const incluirInativas = document.getElementById('incluirInativas').checked;
    
    if (!emprId) {
        alert('Selecione uma empresa');
        return;
    }
    
    let url = `/comissao-api-regras?empr_id=${emprId}`;
    if (funcId) url += `&funcionario_id=${funcId}`;
    if (incluirInativas) url += `&incluir_inativas=1`;
    
    fetch(url)
        .then(response => response.json())
        .then(result => {
            const tbody = document.getElementById('tabelaRegrasBody');
            tbody.innerHTML = '';
            
            const regras = result.data || result;
            if (!regras || regras.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhuma regra encontrada</td></tr>';
                return;
            }
            
            // Função para traduzir tipo de comissão
            function getTipoComissaoDesc(tipo) {
                const tipos = {
                    'P': 'Percentual sobre Pontos',
                    'V': 'Valor por UP',
                    'F': 'Valor Fixo Total',
                    'M': 'Valor Fixo + Por Ponto'
                };
                return tipos[tipo] || tipo;
            }

            // Formatar valor com casas decimais adequadas
            function fmtValor(v) {
                if (v === 0) return '0,00';
                const s = v.toString();
                const dec = s.includes('.') ? s.split('.')[1].length : 0;
                return v.toLocaleString('pt-BR', { minimumFractionDigits: Math.max(2, dec), maximumFractionDigits: Math.max(2, dec) });
            }

            // Formatar valor base conforme tipo
            function formatarValorBase(regra) {
                const tipo = regra.TIPO_COMISSAO;
                const valor = parseFloat(regra.VALOR_COMISSAO || 0);
                const valorFixo = parseFloat(regra.VALOR_FIXO || 0);
                switch(tipo) {
                    case 'P':
                        return fmtValor(valor) + '%';
                    case 'V':
                        return 'R$ ' + fmtValor(valor) + '/pt';
                    case 'F':
                        return 'R$ ' + fmtValor(valor);
                    case 'M':
                        return 'Fixo: R$ ' + fmtValor(valorFixo) + ' + R$ ' + fmtValor(valor) + '/pt';
                    default:
                        return 'R$ ' + fmtValor(valor);
                }
            }
            
            regras.forEach(regra => {
                const statusBadge = regra.ATIVO === 'S' 
                    ? '<span class="badge bg-success">Ativa</span>'
                    : '<span class="badge bg-secondary">Inativa</span>';
                
                const vigenciaFim = regra.DT_VIGENCIA_FIM_FMT || regra.DT_VIGENCIA_FIM || 'Indeterminado';
                const tipoDesc = getTipoComissaoDesc(regra.TIPO_COMISSAO);
                
                tbody.innerHTML += `
                    <tr>
                        <td>${regra.ID_REGRA}</td>
                        <td>${regra.COD_FUNC} - ${regra.NOME_FUNCIONARIO}</td>
                        <td>${tipoDesc}</td>
                        <td>${formatarValorBase(regra)}</td>
                        <td>${regra.TIPO_COMISSAO === 'P' ? fmtValor(parseFloat(regra.VALOR_COMISSAO || 0)) + '%' : (regra.TIPO_COMISSAO === 'M' ? 'R$ ' + fmtValor(parseFloat(regra.VALOR_FIXO || 0)) : '-')}</td>
                        <td>${regra.PRIORIDADE}</td>
                        <td>${regra.DT_VIGENCIA_INI_FMT || regra.DT_VIGENCIA_INI} até ${vigenciaFim}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarRegra(${regra.ID_REGRA})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="excluirRegra(${regra.ID_REGRA})" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        })
        .catch(error => console.error('Erro ao carregar regras:', error));
}

function novaRegra() {
    document.getElementById('formRegra').reset();
    document.getElementById('regraId').value = '';
    document.getElementById('modalRegraTitulo').innerHTML = '<i class="bi bi-sliders"></i> Nova Regra por Funcionário';
    document.getElementById('prioridade').value = 1;
    
    // Define data vigência início como hoje
    document.getElementById('dtVigenciaIni').value = new Date().toISOString().split('T')[0];
    
    // Limpa data vigência fim
    document.getElementById('dtVigenciaFim').value = '';
    
    // Reset campos
    document.getElementById('valorBase').value = '';
    document.getElementById('valorBase').disabled = false;
    document.getElementById('percentual').value = '';
    document.getElementById('percentual').disabled = false;
    document.getElementById('observacao').value = '';
    
    // Limpar Select2 funcionário
    $('#funcionarioId').val('').trigger('change');
}

function editarRegra(id) {
    fetch(`/comissao-api-regra?id=${id}`)
        .then(response => response.json())
        .then(result => {
            const regra = result.data || result;
            console.log('Regra carregada:', regra);
            
            document.getElementById('regraId').value = regra.ID_REGRA || regra.ID;
            // Setar funcionário no Select2
            const funcId = regra.ID_FUNCIONARIO || regra.FUNCIONARIO_ID;
            $('#funcionarioId').val(funcId).trigger('change');
            
            document.getElementById('tipoRegra').value = regra.TIPO_COMISSAO || regra.TIPO_REGRA;
            document.getElementById('valorBase').value = regra.VALOR_COMISSAO || regra.VALOR_BASE || '';
            document.getElementById('valorFixoBase').value = regra.VALOR_FIXO || '';
            document.getElementById('percentual').value = regra.PERCENTUAL || '';
            document.getElementById('prioridade').value = regra.PRIORIDADE;
            
            // Usar campos formatados para datas (DT_VIGENCIA_INI_FMT está no formato YYYY-MM-DD)
            document.getElementById('dtVigenciaIni').value = regra.DT_VIGENCIA_INI_FMT || '';
            document.getElementById('dtVigenciaFim').value = regra.DT_VIGENCIA_FIM_FMT || '';
            
            document.getElementById('observacao').value = regra.DESCRICAO || regra.OBSERVACAO || '';
            
            document.getElementById('modalRegraTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Regra';
            atualizarCamposRegra();
            
            const modal = new bootstrap.Modal(document.getElementById('modalRegra'));
            modal.show();
        })
        .catch(error => {
            console.error('Erro ao carregar regra:', error);
            alert('Erro ao carregar regra');
        });
}

function salvarRegra() {
    const btnSalvar = document.getElementById('btnSalvarRegra');
    const textoOriginal = btnSalvar.innerHTML;
    
    // Desabilitar botão e mostrar spinner
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
    
    const regraId = document.getElementById('regraId').value;
    const funcionarioId = document.getElementById('funcionarioId').value;
    const tipoRegra = document.getElementById('tipoRegra').value;
    const valorBase = document.getElementById('valorBase').value;
    const valorFixoBase = document.getElementById('valorFixoBase').value;
    const percentual = document.getElementById('percentual').value;
    const prioridade = document.getElementById('prioridade').value;
    const dtVigenciaIni = document.getElementById('dtVigenciaIni').value;
    const dtVigenciaFim = document.getElementById('dtVigenciaFim').value;
    const observacao = document.getElementById('observacao').value;
    const emprId = document.getElementById('filtroEmpresa').value;
    
    console.log('=== SALVAR REGRA ===');
    console.log('funcionarioId:', funcionarioId);
    console.log('tipoRegra:', tipoRegra);
    console.log('valorBase:', valorBase);
    console.log('emprId:', emprId);
    
    if (!funcionarioId || !tipoRegra || !prioridade || !dtVigenciaIni) {
        alert('Preencha todos os campos obrigatórios');
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = textoOriginal;
        return;
    }
    
    // Validar campos conforme tipo
    if (tipoRegra === 'M' && (!valorFixoBase || !valorBase)) {
        alert('Para tipo Misto, informe o Valor Fixo Base e o Valor por Ponto');
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = textoOriginal;
        return;
    }
    
    // Para tipos P, V, F: valorBase pode ser 0 (ex: valor fixo de R$ 0)
    if (['P', 'V', 'F'].includes(tipoRegra) && valorBase === '' && valorBase !== '0') {
        alert('Informe o valor da comissão');
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = textoOriginal;
        return;
    }
    
    const dados = {
        id: regraId || null,
        id_funcionario: funcionarioId,
        id_empr: emprId,
        tipo_comissao: tipoRegra,
        // Corrigido: não usar || para evitar que "0" seja tratado como falsy
        valor_comissao: valorBase !== '' ? valorBase : (percentual !== '' ? percentual : 0),
        valor_fixo: tipoRegra === 'M' ? valorFixoBase : null,
        descricao: observacao,
        prioridade: prioridade,
        dt_vigencia_ini: dtVigenciaIni,
        dt_vigencia_fim: dtVigenciaFim || null
    };
    
    console.log('Dados a enviar:', JSON.stringify(dados, null, 2));
    
    const url = regraId ? `/comissao-api-regra?id=${regraId}` : '/comissao-api-regra';
    const method = regraId ? 'PUT' : 'POST';
    
    console.log('URL:', url, 'Method:', method);
    
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = textoOriginal;
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalRegra')).hide();
            alert(regraId ? 'Regra atualizada com sucesso!' : 'Regra cadastrada com sucesso!');
            carregarRegras();
        } else {
            alert('Erro ao salvar: ' + (data.error || data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = textoOriginal;
        alert('Erro ao salvar regra');
    });
}

function excluirRegra(id) {
    if (!confirm('Deseja realmente EXCLUIR esta regra? Esta ação não pode ser desfeita.')) return;
    
    fetch(`/comissao-api-regra?id=${id}`, { method: 'DELETE' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Regra excluída com sucesso!');
                carregarRegras();
            } else {
                alert('Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir regra');
        });
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}
</script>

<?= $render('footer') ?>
