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
            <div class="input-group input-group-sm" style="width:auto;">
                <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                <input type="date" id="inputDataFiltro" class="form-control form-control-sm" style="width:145px;">
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
                    <th>MOTORISTA</th>
                    <th class="text-center">PLACA</th>
                    <th class="text-center">AÇÕES</th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr><td colspan="13" class="text-center py-4">
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
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Motorista</label>
                        <input type="text" id="fldMotorista" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contato</label>
                        <input type="text" id="fldContato" class="form-control form-control-sm" placeholder="Telefone / WhatsApp">
                    </div>
                    <div class="col-md-12">
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

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastProjecao" class="toast align-items-center text-white border-0 bg-danger" role="alert">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= $base ?>src/js/projecao-carga.js"></script>
<?= $render('footer') ?>
