<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Controle de Faltas - Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-faltas',
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
        <div class="filter-row d-flex align-items-end flex-wrap">
            <div class="filter-group">
                <label>Empresa</label>
                <input type="text" class="form-control" readonly style="background-color: #e9ecef;" value="<?= htmlspecialchars(($empresa['codigo'] ?? '') . ' - ' . ($empresa['nome_fantasia'] ?? '')) ?>">
            </div>
            <div class="filter-group" style="min-width: 280px;">
                <label for="fFunc">Funcionário</label>
                <select id="fFunc" class="form-select" style="width:100%"><option value="">Todos</option></select>
            </div>
            <div class="filter-group"><label for="fIni">Data Início</label><input type="date" id="fIni" class="form-control"></div>
            <div class="filter-group"><label for="fFim">Data Fim</label><input type="date" id="fFim" class="form-control"></div>
            <div class="filter-group">
                <div class="form-check mt-4">
                    <input type="checkbox" id="incluirInativas" class="form-check-input">
                    <label for="incluirInativas" class="form-check-label">Incluir canceladas</label>
                </div>
            </div>
            <div class="filter-group"><button type="button" class="btn btn-sm btn-primary" id="btnFiltrar"><i class="bi bi-search"></i> Filtrar</button></div>
            <div class="filter-group"><button type="button" class="btn btn-sm btn-success" id="btnNova"><i class="bi bi-plus-circle"></i> Registrar Falta</button></div>
        </div>
        <div class="small text-muted mt-2">Cadastro de faltas exclusivo da Expedição (não usa o da comissão de produção). <strong>Integral:</strong> o funcionário não recebe nada no dia. <strong>Parcial:</strong> recebe metade (meio peso no rateio da equipe, 50% na regra individual). Para vários dias, informe a data inicial e a final.</div>
    </div>

    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" style="width: 100%;">
            <thead><tr><th>Data</th><th>Código</th><th>Funcionário</th><th>Tipo</th><th>Motivo</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody id="corpo"></tbody>
        </table>
    </div>

    <div class="modal fade" id="modalFalta" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo"><i class="bi bi-calendar-x"></i> Registrar Falta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="fId">
                    <div class="row mb-3">
                        <div class="col-md-8"><label class="form-label">Funcionário *</label><select id="mFunc" class="form-select" style="width:100%"></select></div>
                        <div class="col-md-4"><label class="form-label">Tipo de Falta *</label>
                            <select id="mTipo" class="form-select"><option value="I">Falta Integral</option><option value="P">Falta Parcial</option></select></div>
                    </div>
                    <div class="row mb-3" id="linhaDatas">
                        <div class="col-md-4"><label class="form-label">Data inicial *</label><input type="date" id="mIni" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Data final</label><input type="date" id="mFim" class="form-control"><small class="text-muted">Deixe em branco para um dia só (máx. 92 dias)</small></div>
                        <div class="col-md-4"><div class="form-check mt-4"><input type="checkbox" id="mFds" class="form-check-input"><label for="mFds" class="form-check-label">Não lançar sábado e domingo</label></div></div>
                    </div>
                    <div class="row mb-3" id="linhaStatus" style="display:none">
                        <div class="col-md-4"><label class="form-label">Status</label>
                            <select id="mAtivo" class="form-select"><option value="S">Ativa</option><option value="N">Cancelada</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Motivo / Observação</label><textarea id="mMotivo" class="form-control" rows="2" maxlength="500"></textarea></div>
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
    let faltas = [];
    const tipo = t => t === 'I' ? '<span class="badge bg-danger">Integral</span>' : '<span class="badge bg-warning text-dark">Parcial</span>';

    async function carregar() {
        faltas = (await EX.post('/expedicao-api-faltas', { id_funcionario: $i('fFunc').value, dt_ini: $i('fIni').value, dt_fim: $i('fFim').value, apenas_ativas: !$i('incluirInativas').checked })).rows || [];
        $i('corpo').innerHTML = faltas.length ? faltas.map(f => '<tr><td>' + EX.dbr(f.DT_FALTA) + '</td><td>' + EX.esc(f.COD_FUNC) + '</td><td>' + EX.esc(f.FUNCIONARIO) + '</td><td>' + tipo(f.TIPO_FALTA) + '</td><td>' + EX.esc(f.MOTIVO) + '</td><td>' +
            (f.ATIVO === 'S' ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Cancelada</span>') +
            '</td><td><button class="btn btn-sm btn-outline-primary" data-id="' + f.ID_FALTA + '"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" data-del="' + f.ID_FALTA + '" title="Excluir"><i class="bi bi-trash"></i></button></td></tr>').join('')
            : '<tr><td colspan="7" class="text-center text-muted">Nenhuma falta no período.</td></tr>';
    }
    function abrir(f) {
        $i('fId').value = f ? f.ID_FALTA : '';
        $($i('mFunc')).val(f ? f.ID_FUNCIONARIO : '').trigger('change'); $i('mFunc').disabled = !!f;
        $i('mTipo').value = f ? f.TIPO_FALTA : 'I';
        $i('mIni').value = f ? f.DT_FALTA : EX.hoje(); $i('mFim').value = ''; $i('mFds').checked = false; $i('mIni').disabled = !!f;
        $i('linhaDatas').style.display = f ? 'none' : ''; $i('linhaStatus').style.display = f ? '' : 'none';
        $i('mAtivo').value = f ? f.ATIVO : 'S'; $i('mMotivo').value = f ? (f.MOTIVO || '') : '';
        $i('modalTitulo').innerHTML = f ? '<i class="bi bi-pencil"></i> Editar Falta de ' + EX.dbr(f.DT_FALTA) : '<i class="bi bi-calendar-x"></i> Registrar Falta';
        EX.modal('modalFalta').show();
    }
    $i('btnFiltrar').onclick = () => EX.acao(carregar);
    $i('btnNova').onclick = () => abrir(null);
    $i('corpo').addEventListener('click', ev => {
        const b = ev.target.closest('button'); if (!b) return;
        if (b.dataset.del) {
            if (!confirm('Excluir esta falta? Essa ação não pode ser desfeita e muda o cálculo de períodos passados.')) return;
            return EX.acao(async () => { await EX.post('/expedicao-api-excluir', { tipo: 'falta', id: b.dataset.del }); await carregar(); EX.aviso('Excluído.', true); });
        }
        abrir(faltas.find(x => String(x.ID_FALTA) === b.dataset.id));
    });
    $i('btnSalvar').onclick = () => EX.acao(async () => {
        const id = $i('fId').value;
        const fid = id ? (faltas.find(x => String(x.ID_FALTA) === id) || {}).ID_FUNCIONARIO : $i('mFunc').value;
        if (!fid) throw new Error('Selecione o funcionário.');
        const r = await EX.post('/expedicao-api-falta-salvar', { id, id_funcionario: fid, tipo: $i('mTipo').value, dt_ini: $i('mIni').value, dt_fim: $i('mFim').value, pular_fim_semana: $i('mFds').checked, motivo: $i('mMotivo').value, ativo: $i('mAtivo').value });
        EX.modal('modalFalta').hide();
        const dI = $i('mIni').value; if (!id && dI && (dI < $i('fIni').value)) $i('fIni').value = dI;
        const dF = $i('mFim').value || dI; if (!id && dF && dF > $i('fFim').value) $i('fFim').value = dF;
        await carregar();
        EX.aviso(id ? 'Falta atualizada.' : (r.criadas + ' falta(s) registrada(s)' + (r.atualizadas ? ' e ' + r.atualizadas + ' já existente(s) atualizada(s)' : '') + '.'), true);
    });

    $i('fIni').value = EX.inicioMes(); $i('fFim').value = EX.hoje();
    EX.acao(async () => {
        const rows = await EX.funcionarios($i('mFunc'), 'modalFalta');
        $i('fFunc').innerHTML = '<option value="">Todos</option>' + rows.map(f => '<option value="' + f.ID + '">' + EX.esc(f.COD_FUNC + ' - ' + f.NOME) + '</option>').join('');
        if ($.fn.select2) $($i('fFunc')).select2({ width: '100%', theme: 'bootstrap-5' });
        await carregar();
    });
});
</script>
<?= $render('footer') ?>
