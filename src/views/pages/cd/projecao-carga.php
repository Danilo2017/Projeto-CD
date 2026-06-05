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
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<div class="comissao-dashboard-container" style="width:100%;max-width:100%;padding:10px;margin:0;">

    <!-- Cabeçalho -->
    <div class="dashboard-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-truck-flatbed"></i> Projeção de Carga</h4>
        <div class="d-flex align-items-center gap-2">
            <button id="btnAtualizar" class="btn btn-sm btn-outline-primary" title="Atualizar">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <span class="text-muted small">
                Total: <strong id="totalCargas">0</strong> cargas
            </span>
        </div>
    </div>

    <!-- Tabela -->
    <div class="dashboard-section" style="width:100%;overflow-x:auto;">
        <table class="table table-sm table-striped table-hover align-middle mb-0" style="min-width:900px;">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">Nº CARGA</th>
                    <th>DT GERAÇÃO</th>
                    <th>DESCRIÇÃO</th>
                    <th class="text-end">CUBAGEM</th>
                    <th class="text-end">VALOR</th>
                    <th class="text-center">DT CARREGAMENTO</th>
                    <th class="text-center">SITUAÇÃO</th>
                    <th>MOTORISTA</th>
                    <th>FROTA / PLACAS</th>
                    <th class="text-center">AÇÕES</th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr><td colspan="10" class="text-center py-4">
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
