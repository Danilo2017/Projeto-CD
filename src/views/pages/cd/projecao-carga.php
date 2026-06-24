<?php
/** @var bool      $is_admin */
/** @var array     $rotas_permitidas */
/** @var string    $base */
/** @var callable  $render */
$acessoCarga = $is_admin || in_array('carga', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoCarga) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Projeção de Carga',
    'showNavbar' => true,
    'pageActive' => 'carga-projecao',
    'customCSS'  => ['src/css/comissao-dashboard.css', 'src/css/cd-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <!-- Cabeçalho -->
    <div class="dashboard-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-truck-flatbed"></i> Projeção de Carga</h4>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="input-group input-group-sm" style="width:260px;">
                <span class="input-group-text"><i class="bi bi-funnel"></i></span>
                <input type="text" id="inputFiltro" class="form-control form-control-sm"
                       placeholder="Carga, descrição, rota, data...">
                <button class="btn btn-sm btn-outline-secondary" id="btnLimparFiltro" title="Limpar filtro">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                <input type="date" id="inputDataInicio" class="form-control form-control-sm" style="width:145px;" title="Data início">
                <span class="input-group-text">até</span>
                <input type="date" id="inputDataFim" class="form-control form-control-sm" style="width:145px;" title="Data fim">
                <button id="btnAtualizar" class="btn btn-sm btn-primary" title="Buscar">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
            <span class="text-muted small">
                <strong id="totalCargas">0</strong> cargas
            </span>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fs-6">
                Pendente: <strong id="totalPendente">-</strong>
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 fs-6">
                Faturado: <strong id="totalFaturado">-</strong>
            </span>
            <button class="btn btn-sm btn-success" onclick="downloadExcel()" title="Exportar para Excel">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="dashboard-section" style="width:100%;overflow-x:auto;">
        <table class="table table-sm table-striped table-hover align-middle mb-0" style="min-width:900px;">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">Nº CARGA</th>
                    <th>DT CARGA</th>
                    <th>DESCRIÇÃO</th>
                    <th>ROTA</th>
                    <th class="text-end">CUBAGEM</th>
                    <th class="text-end">VLR PENDENTE</th>
                    <th class="text-end">VLR FATURADO</th>
                    <th class="text-center">DT CARREGAMENTO</th>
                    <th class="text-center">SITUAÇÃO</th>
                    <th class="text-center">EXPEDIÇÃO</th>
                    <th style="min-width:130px">MOTORISTA</th>
                    <th class="text-center" style="width:115px;min-width:115px">PLACA</th>
                    <th class="text-center">SIT. VEÍCULO</th>
                    <th class="text-center">DOCA</th>
                    <th class="text-center">AÇÕES</th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr><td colspan="15" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm"></div> Carregando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ Modal Editar ═══ -->
<div class="modal fade" id="modalCarga" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCargaTitulo"><i class="bi bi-pencil-fill"></i> Editar Carga</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fldNumCarga">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Data Carregamento</label>
                        <input type="date" id="fldDtCarregamento" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Situação</label>
                        <select id="fldSituacao" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Situação Carga</label>
                        <input type="text" id="fldSituacaoCarga" class="form-control form-control-sm" placeholder="Ex: Aguardando NF">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Frota</label>
                        <input type="text" id="fldFrota" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Placas</label>
                        <input type="text" id="fldPlacas" class="form-control form-control-sm" placeholder="AAA-0000">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Tipo Veículo</label>
                        <input type="text" id="fldTipoVeiculo" class="form-control form-control-sm" placeholder="Ex: Carreta, Truck">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Situação do Caminhão</label>
                        <select id="fldSituacaoCaminhao" class="form-select form-select-sm">
                            <option value="">— Selecione —</option>
                            <option>DISPONÍVEL</option>
                            <option>AGUARDANDO CARREGAMENTO</option>
                            <option>CARREGANDO</option>
                            <option>EM TRÂNSITO</option>
                            <option>EM ENTREGA</option>
                            <option>DESCARREGANDO</option>
                            <option>RETORNANDO</option>
                            <option>EM MANUTENÇÃO</option>
                            <option>AGUARDANDO DOCUMENTAÇÃO</option>
                            <option>FINALIZADO</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Motorista</label>
                        <input type="text" id="fldMotorista" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contato</label>
                        <input type="text" id="fldContato" class="form-control form-control-sm" placeholder="Telefone / WhatsApp">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Doca</label>
                        <input type="text" id="fldDoca" class="form-control form-control-sm" placeholder="Ex: D1, D2">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">Nº Documentos</label>
                        <input type="text" id="fldNumDocs" class="form-control form-control-sm" placeholder="Números separados por vírgula">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Observações</label>
                        <textarea id="fldObservacoes" class="form-control form-control-sm" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button id="btnSalvar" type="button" class="btn btn-primary btn-sm" onclick="salvarCarga()">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal Anexos ═══ -->
<div class="modal fade" id="modalAnexo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalAnexoTitulo"><i class="bi bi-paperclip"></i> Anexos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Adicionar arquivo</label>
                    <div class="input-group">
                        <input type="file" id="fldArquivo" class="form-control form-control-sm" multiple>
                        <button id="btnUpload" type="button" class="btn btn-sm btn-primary" onclick="uploadAnexo()">
                            <i class="bi bi-upload"></i> Enviar
                        </button>
                    </div>
                    <div id="uploadProgress" class="progress mt-2 d-none" style="height:5px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                    </div>
                </div>
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Arquivo</th>
                            <th class="text-end">Tamanho</th>
                            <th>Usuário</th>
                            <th>Data</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="anexoBody">
                        <tr><td colspan="5" class="text-center text-muted py-3">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal WhatsApp ═══ -->
<div class="modal fade" id="modalWhatsapp" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white border-0" style="background-color:#25D366;">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-whatsapp me-2"></i>Enviar via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-2">
                <input type="hidden" id="waNumCarga">

                <!-- Preview da mensagem -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">Mensagem</label>
                    <div class="position-relative">
                        <textarea id="waMensagem" rows="9"
                            class="form-control form-control-sm font-monospace"
                            style="font-size:0.82rem;resize:vertical;background:#f0fdf4;border-color:#86efac;line-height:1.5;"></textarea>
                    </div>
                    <div class="form-text">Edite o texto se necessário antes de enviar.</div>
                </div>

                <hr class="my-2">

                <!-- Enviar para número específico -->
                <div class="mb-1">
                    <label class="form-label fw-semibold small text-uppercase text-muted">
                        Número específico <span class="fw-normal text-muted">(opcional)</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone-fill text-success"></i></span>
                        <input type="tel" id="waTelefone" class="form-control"
                               placeholder="DDD + número  Ex: 44 99999-9999">
                        <button type="button" class="btn btn-success fw-semibold" onclick="enviarWhatsapp('numero')">
                            <i class="bi bi-send-fill me-1"></i>Enviar
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success fw-semibold" onclick="enviarWhatsapp('escolher')">
                    <i class="bi bi-whatsapp me-1"></i>Abrir WhatsApp Web
                    <span class="badge bg-white text-success ms-1" style="font-size:0.65rem">escolher contato</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal Log ═══ -->
<div class="modal fade" id="modalLog" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="logCargaTitulo"><i class="bi bi-clock-history"></i> Histórico</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Data / Hora</th>
                            <th>Usuário</th>
                            <th>Campo</th>
                            <th>Valor Anterior</th>
                            <th>Novo Valor</th>
                        </tr>
                    </thead>
                    <tbody id="logBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal Sequência de Rota ═══ -->
<div class="modal fade" id="modalRota" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header text-white" style="background:#0d6efd">
                <h5 class="modal-title" id="modalRotaTitulo"><i class="bi bi-geo-alt-fill"></i> Sequência da Rota</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" id="rotaLista">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                <button id="btnSalvarRota" type="button" class="btn btn-primary btn-sm" onclick="salvarSequenciaRota()">
                    <i class="bi bi-check-lg"></i> Salvar Sequência
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastProjecao" class="toast align-items-center text-white border-0 bg-danger" role="alert">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= $base ?>src/js/projecao-carga.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/src/js/projecao-carga.js') ?: date('YmdH') ?>"></script>
<?= $render('footer') ?>
