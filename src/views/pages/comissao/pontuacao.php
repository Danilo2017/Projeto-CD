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
    'pageTitle' => 'Cadastro de Pontuação UEP',
    'showNavbar' => true,
    'pageActive' => 'comissao-pontuacao',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

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
                <label for="filtroItem">Produto</label>
                <input type="text" id="filtroItem" class="form-control" placeholder="Buscar por código ou descrição">
            </div>
            <div class="filter-group">
                <label for="filtroCentro">Centro de Trabalho</label>
                <select id="filtroCentro" class="form-select">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-primary btn-sm" onclick="carregarPontuacoes()">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group d-flex align-items-end">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalPontuacao">
                    <i class="bi bi-plus-lg"></i> Nova Pontuação
                </button>
                <button type="button" class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalImportacao">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importar
                </button>
                <button type="button" class="btn btn-outline-success btn-sm ms-2" onclick="exportarExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalRelatorioItens">
                    <i class="bi bi-table"></i> Relatório Itens
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Pontuações -->
    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" id="tabelaPontuacoes" style="width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Código Item</th>
                    <th>Descrição</th>
                    <th>ID Máscara</th>
                    <th>Máscara</th>
                    <th>Centro Trabalho</th>
                    <th>Pontuação UP</th>
                    <th>Vigência Início</th>
                    <th>Vigência Fim</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaPontuacoesBody">
                <!-- Dados serão carregados via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Cadastro/Edição -->
    <div class="modal fade" id="modalPontuacao" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPontuacaoTitulo">
                        <i class="bi bi-tag-fill"></i> Nova Pontuação UP
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPontuacao">
                        <input type="hidden" id="pontuacaoId">
                        <input type="hidden" id="hiddenItemId">
                        <input type="hidden" id="hiddenItemprId">
                        <input type="hidden" id="hiddenMascaraId">
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="itemprId" class="form-label">Produto *</label>
                                <select id="itemprId" class="form-select select2-produto" style="width: 100%;" required>
                                    <option value="">Digite o código ou descrição do produto...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="centroTrabId" class="form-label">Centro de Trabalho</label>
                                <select id="centroTrabId" class="form-select">
                                    <option value="">Todos os centros</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="pontuacaoUp" class="form-label">Pontuação UP *</label>
                                <input type="number" id="pontuacaoUp" class="form-control" step="0.01" min="0" required>
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
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="salvarPontuacao()">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Importação -->
    <div class="modal fade" id="modalImportacao" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-arrow-up"></i> Importar Pontuações
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Formato esperado:</strong> CSV com colunas:<br>
                        <code>COD_ITEM;ID_MASCARA;COD_CENTRO;PONTOS_UP;DT_VIGENCIA_INI;DT_VIGENCIA_FIM</code><br>
                        <small>Separador: TAB, ponto-e-vírgula (;) ou vírgula (,)</small>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="baixarModeloCSV()">
                            <i class="bi bi-download"></i> Baixar Modelo CSV
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Selecione o arquivo</label>
                        <input type="file" class="form-control" id="arquivoImportacao" accept=".csv,.txt,.xls,.xlsx">
                    </div>
                    <div id="importacaoPreview" style="display: none;">
                        <h6>Pré-visualização:</h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped" id="tabelaPreview">
                                <thead><tr></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="importacaoResumo" class="mt-2"></div>
                    </div>
                    <div id="importacaoResultado" style="display: none;" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-warning btn-sm" id="btnConfirmarImportacao" onclick="confirmarImportacao()" disabled>
                        <i class="bi bi-upload"></i> Importar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Modal Relatório de Itens -->
    <div class="modal fade" id="modalRelatorioItens" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-table"></i> Relatório de Itens Fabricados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Filtre por código(s) do item e/ou ID da máscara. Deixe em branco para gerar todos os itens da empresa.</p>
                    <div class="mb-3">
                        <label class="form-label">Código do Item <small class="text-muted">(separe múltiplos por vírgula)</small></label>
                        <input type="text" id="filtroRelCodItem" class="form-control" placeholder="Ex.: 16342, 700027, 800100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ID Máscara</label>
                        <input type="number" id="filtroRelIdMascara" class="form-control" placeholder="Ex.: 42" min="1">
                    </div>
                    <div id="relatorioItensStatus" class="mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnGerarRelatorio" onclick="gerarRelatorioItens()">
                        <i class="bi bi-file-earmark-excel"></i> Gerar Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- SheetJS para exportação Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<?= $render('footer', [
    'customJS' => ['src/js/comissao-pontuacao.js']
]) ?>
