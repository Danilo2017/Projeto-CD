<?php
// Verificar permissão de acesso
$acessoComissao = $_SESSION['user']['acesso_comissao'] ?? 'N';
if ($acessoComissao !== 'S') {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Regras por Funcionário',
    'showNavbar' => true,
    'pageActive' => 'comissao-regras',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'background: #f0f0f0; margin: 0; padding: 0;'
]) ?>

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 5px 10px; margin: 0;">
    <!-- Filtros -->
    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="filtroEmpresa">Empresa</label>
                <input type="text" id="filtroEmpresaDisplay" class="form-control" readonly style="background-color: #e9ecef;">
                <input type="hidden" id="filtroEmpresa" value="<?= $_SESSION['empresa']['id'] ?? '' ?>">
            </div>
            <div class="filter-group">
                <label for="filtroFuncionario">Funcionário</label>
                <select id="filtroFuncionario" class="form-select">
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
                    <th>Percentual</th>
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
                                <select id="funcionarioId" class="form-select" required>
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
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarRegra()">
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
    const empresaSessao = '<?= $_SESSION['empresa']['id'] ?? '' ?>';
    const empresaNome = '<?= ($_SESSION['empresa']['codigo'] ?? '') . ' - ' . ($_SESSION['empresa']['nome_fantasia'] ?? '') ?>';
    
    // Exibir empresa travada no campo readonly
    document.getElementById('filtroEmpresaDisplay').value = empresaNome;
    document.getElementById('filtroEmpresa').value = empresaSessao;
    
    if (empresaSessao) {
        carregarFuncionarios(empresaSessao);
    }
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
                    'F': 'Valor Fixo Total'
                };
                return tipos[tipo] || tipo;
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
                        <td>R$ ${parseFloat(regra.VALOR_COMISSAO || 0).toFixed(2)}</td>
                        <td>-</td>
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
}

function editarRegra(id) {
    fetch(`/comissao-api-regra?id=${id}`)
        .then(response => response.json())
        .then(result => {
            const regra = result.data || result;
            console.log('Regra carregada:', regra);
            
            document.getElementById('regraId').value = regra.ID_REGRA || regra.ID;
            document.getElementById('funcionarioId').value = regra.ID_FUNCIONARIO || regra.FUNCIONARIO_ID;
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
        return;
    }
    
    // Validar campos conforme tipo
    if (tipoRegra === 'M' && (!valorFixoBase || !valorBase)) {
        alert('Para tipo Misto, informe o Valor Fixo Base e o Valor por Ponto');
        return;
    }
    
    // Para tipos P, V, F: valorBase pode ser 0 (ex: valor fixo de R$ 0)
    if (['P', 'V', 'F'].includes(tipoRegra) && valorBase === '' && valorBase !== '0') {
        alert('Informe o valor da comissão');
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
