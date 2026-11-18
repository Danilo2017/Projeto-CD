<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Regras por Funcionário - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-regras-funcionario',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="comissao-dashboard-container" style="width: 100%; max-width: 100%; padding: 5px 10px; margin: 0;">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>expedicao-cadastro" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
    <div id="exMsg" style="display:none"></div>

    <div class="dashboard-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label>Empresa</label>
                <input type="text" class="form-control" readonly style="background-color: #e9ecef;" value="<?= htmlspecialchars(($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '')) ?>">
            </div>
            <div class="filter-group">
                <div class="form-check mt-4">
                    <input type="checkbox" id="incluirInativas" class="form-check-input">
                    <label for="incluirInativas" class="form-check-label">Incluir inativas</label>
                </div>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-sm btn-primary" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button>
            </div>
            <div class="filter-group d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-sm btn-success" id="btnNova"><i class="bi bi-plus-circle"></i> Nova Regra</button>
            </div>
        </div>
    </div>

    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" style="width: 100%;">
            <thead><tr><th>ID</th><th>Código</th><th>Funcionário</th><th>Equipe</th><th>Valor fixo por dia</th><th>R$ por m³</th><th>Vigência</th><th>Observação</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody id="corpo"></tbody>
        </table>
    </div>

    <div class="modal fade" id="modalRegra" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo"><i class="bi bi-person-gear"></i> Nova Regra por Funcionário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rId">
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label">Funcionário *</label><select id="rFunc" class="form-select" style="width:100%"></select></div>
                        <div class="col-md-4"><label class="form-label">Equipe</label><select id="rEquipe" class="form-select"></select>
                            <small class="text-muted">Sem equipe = vale para qualquer equipe dele (uma vez por dia)</small></div>
                        <div class="col-md-2"><label class="form-label">Status</label>
                            <select id="rAtivo" class="form-select px-1"><option value="S">Ativa</option><option value="N">Inativa</option></select></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label">Valor fixo por dia - R$</label>
                            <input id="rFixo" class="form-control" placeholder="0,00"><small class="text-muted">Pago em cada dia com carga fechada em que ele trabalhou</small></div>
                        <div class="col-md-6"><label class="form-label">Valor por m³ - R$</label>
                            <input id="rM3" class="form-control" placeholder="0,00"><small class="text-muted">Sobre os m³ fechados no dia. A regra substitui o rateio da equipe</small></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label">Vigência Início *</label><input type="date" id="rIni" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Vigência Fim</label><input type="date" id="rFim" class="form-control"><small class="text-muted">Deixe em branco para vigência indeterminada</small></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Observação</label><textarea id="rObs" class="form-control" rows="2" maxlength="200"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSalvar"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    let regras = [];
    const vig = r => EX.dbr(r.DT_VIGENCIA_INI) + (r.DT_VIGENCIA_FIM ? ' a ' + EX.dbr(r.DT_VIGENCIA_FIM) : ' em diante');

    async function carregar() {
        regras = (await EX.post('/expedicao-api-regras-func', { apenas_ativas: !$i('incluirInativas').checked })).rows || [];
        $i('corpo').innerHTML = regras.length ? regras.map(r => '<tr><td>' + r.ID_REGRA + '</td><td>' + EX.esc(r.COD_FUNC) + '</td><td>' + EX.esc(r.FUNCIONARIO) + '</td><td>' + EX.esc(r.EQUIPE || 'Qualquer') + '</td><td>' + EX.val(r.VLR_FIXO) + '</td><td>' + EX.val(r.VLR_M3) + '</td><td>' + vig(r) + '</td><td>' + EX.esc(r.OBS) + '</td><td>' + EX.status(r.ATIVO) +
            '</td><td><button class="btn btn-sm btn-outline-primary" data-id="' + r.ID_REGRA + '"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" data-del="' + r.ID_REGRA + '" title="Excluir"><i class="bi bi-trash"></i></button></td></tr>').join('')
            : '<tr><td colspan="10" class="text-center text-muted">Nenhuma regra por funcionário cadastrada.</td></tr>';
    }
    function abrir(r) {
        $i('rId').value = r ? r.ID_REGRA : '';
        $($i('rFunc')).val(r ? r.ID_FUNCIONARIO : '').trigger('change'); $i('rFunc').disabled = !!r;
        $i('rEquipe').value = r ? (r.ID_EQUIPE || '') : '';
        $i('rFixo').value = r ? String(+r.VLR_FIXO).replace('.', ',') : ''; $i('rM3').value = r ? String(+r.VLR_M3).replace('.', ',') : '';
        $i('rIni').value = r ? r.DT_VIGENCIA_INI : EX.hoje(); $i('rFim').value = r ? (r.DT_VIGENCIA_FIM || '') : ''; $i('rObs').value = r ? (r.OBS || '') : ''; $i('rAtivo').value = r ? r.ATIVO : 'S';
        $i('modalTitulo').innerHTML = r ? '<i class="bi bi-pencil"></i> Editar Regra por Funcionário' : '<i class="bi bi-person-gear"></i> Nova Regra por Funcionário';
        EX.modal('modalRegra').show();
    }
    $i('btnFiltrar').onclick = () => EX.acao(carregar);
    $i('btnNova').onclick = () => abrir(null);
    $i('corpo').addEventListener('click', ev => {
        const b = ev.target.closest('button'); if (!b) return;
        if (b.dataset.del) {
            if (!confirm('Excluir a regra individual deste funcionário? Essa ação não pode ser desfeita e muda o cálculo de períodos passados.')) return;
            return EX.acao(async () => { await EX.post('/expedicao-api-excluir', { tipo: 'regraFunc', id: b.dataset.del }); await carregar(); EX.aviso('Excluído.', true); });
        }
        abrir(regras.find(x => String(x.ID_REGRA) === b.dataset.id));
    });
    $i('btnSalvar').onclick = () => EX.acao(async () => {
        const id = $i('rId').value;
        const fid = id ? (regras.find(x => String(x.ID_REGRA) === id) || {}).ID_FUNCIONARIO : $i('rFunc').value;
        if (!fid) throw new Error('Selecione o funcionário.');
        await EX.post('/expedicao-api-regra-func-salvar', { id, id_funcionario: fid, id_equipe: $i('rEquipe').value, vlr_fixo: EX.num($i('rFixo').value), vlr_m3: EX.num($i('rM3').value), dt_ini: $i('rIni').value, dt_fim: $i('rFim').value, obs: $i('rObs').value, ativo: $i('rAtivo').value });
        EX.modal('modalRegra').hide(); await carregar(); EX.aviso('Regra salva com sucesso.', true);
    });
    EX.acao(async () => { await EX.funcionarios($i('rFunc'), 'modalRegra'); await EX.equipes($i('rEquipe'), true, false); await carregar(); });
});
</script>
<?= $render('footer') ?>
