<?php
$acessoExpedicao = $is_admin || in_array('expedicao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoExpedicao) { header('Location: ' . $base . 'sem-acesso'); exit; }
$empresa = $_SESSION['empresa'] ?? [];
?>
<?= $render('header', [
    'pageTitle' => 'Equipes da Expedição',
    'showNavbar' => true,
    'pageActive' => 'expedicao-equipes',
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
                <button type="button" class="btn btn-sm btn-success" id="btnNova"><i class="bi bi-plus-circle"></i> Nova Equipe</button>
            </div>
        </div>
    </div>

    <div class="dashboard-section" style="width: 100%; max-width: 100%;">
        <table class="table table-striped table-hover" style="width: 100%;">
            <thead><tr><th>ID</th><th>Equipe</th><th>Tipo</th><th>Funcionários ativos</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody id="corpo"></tbody>
        </table>
    </div>

    <!-- Modal equipe -->
    <div class="modal fade" id="modalEquipe" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEquipeTitulo"><i class="bi bi-people"></i> Nova Equipe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eqId">
                    <div class="mb-3"><label class="form-label">Nome da equipe *</label><input id="eqNome" class="form-control" maxlength="100"></div>
                    <div class="row mb-3">
                        <div class="col-md-6"><label class="form-label">Tipo *</label>
                            <select id="eqTipo" class="form-select"><option value="E">Expedição</option><option value="S">Separação</option></select></div>
                        <div class="col-md-6"><label class="form-label">Status</label>
                            <select id="eqAtivo" class="form-select"><option value="S">Ativa</option><option value="N">Inativa</option></select></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSalvarEquipe"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal funcionários da equipe -->
    <div class="modal fade" id="modalMembros" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Funcionários: <span id="mbEquipe"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mbId">
                    <div class="row mb-3 align-items-end">
                        <div class="col-md-4"><label class="form-label">Funcionário *</label><select id="mbFunc" class="form-select" style="width:100%"></select></div>
                        <div class="col-md-2"><label class="form-label">Entrou em *</label><input type="date" id="mbIni" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">Saiu em</label><input type="date" id="mbFim" class="form-control"></div>
                        <div class="col-md-1" style="min-width:90px"><label class="form-label">Ativo</label>
                            <select id="mbAtivo" class="form-select px-1"><option value="S">Sim</option><option value="N">Não</option></select></div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="button" class="btn btn-primary btn-sm" id="btnSalvarMembro"><i class="bi bi-check-lg"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparMembro">Limpar</button>
                        </div>
                    </div>
                    <table class="table table-striped table-hover table-sm">
                        <thead><tr><th>Código</th><th>Funcionário</th><th>Entrou em</th><th>Saiu em</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody id="mbCorpo"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_comum.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $i = id => document.getElementById(id);
    let equipes = [], membros = [], equipeSel = null;

    async function carregar() {
        equipes = (await EX.post('/expedicao-api-equipes', { apenas_ativas: !$i('incluirInativas').checked })).rows || [];
        $i('corpo').innerHTML = equipes.length ? equipes.map(e => '<tr><td>' + e.ID_EQUIPE + '</td><td>' + EX.esc(e.NOME) + '</td><td>' + EX.tagTipo(e.TIPO) + '</td><td>' + e.QTD_MEMBROS + '</td><td>' + EX.status(e.ATIVO) +
            '</td><td><button class="btn btn-sm btn-outline-primary" data-a="edit" data-id="' + e.ID_EQUIPE + '"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-success" data-a="func" data-id="' + e.ID_EQUIPE + '"><i class="bi bi-people"></i> Funcionários</button> <button class="btn btn-sm btn-outline-danger" data-a="del" data-id="' + e.ID_EQUIPE + '" title="Excluir equipe"><i class="bi bi-trash"></i></button></td></tr>').join('')
            : '<tr><td colspan="6" class="text-center text-muted">Nenhuma equipe cadastrada.</td></tr>';
    }
    $i('btnFiltrar').onclick = () => EX.acao(carregar);
    $i('btnNova').onclick = () => { $i('eqId').value = ''; $i('eqNome').value = ''; $i('eqTipo').value = 'E'; $i('eqAtivo').value = 'S'; $i('modalEquipeTitulo').innerHTML = '<i class="bi bi-people"></i> Nova Equipe'; EX.modal('modalEquipe').show(); };
    $i('btnSalvarEquipe').onclick = () => EX.acao(async () => {
        await EX.post('/expedicao-api-equipe-salvar', { id: $i('eqId').value, nome: $i('eqNome').value, tipo: $i('eqTipo').value, ativo: $i('eqAtivo').value });
        EX.modal('modalEquipe').hide(); await carregar(); EX.aviso('Equipe salva com sucesso.', true);
    });

    function limparMembro() { $i('mbId').value = ''; $($i('mbFunc')).val('').trigger('change'); $i('mbFunc').disabled = false; $i('mbIni').value = EX.hoje(); $i('mbFim').value = ''; $i('mbAtivo').value = 'S'; }
    async function carregarMembros() {
        membros = (await EX.post('/expedicao-api-membros', { id_equipe: equipeSel.ID_EQUIPE })).rows || [];
        $i('mbCorpo').innerHTML = membros.length ? membros.map(m => '<tr><td>' + EX.esc(m.COD_FUNC) + '</td><td>' + EX.esc(m.FUNCIONARIO) + '</td><td>' + EX.dbr(m.DT_INI) + '</td><td>' + EX.dbr(m.DT_FIM) + '</td><td>' + EX.status(m.ATIVO) +
            '</td><td><button class="btn btn-sm btn-outline-primary" data-m="' + m.ID + '"><i class="bi bi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger" data-d="' + m.ID + '" title="Remover da equipe"><i class="bi bi-trash"></i></button></td></tr>').join('')
            : '<tr><td colspan="6" class="text-center text-muted">Nenhum funcionário nesta equipe.</td></tr>';
    }
    $i('corpo').addEventListener('click', ev => {
        const b = ev.target.closest('button'); if (!b) return;
        const e = equipes.find(x => String(x.ID_EQUIPE) === b.dataset.id); if (!e) return;
        if (b.dataset.a === 'del') {
            if (!confirm('Excluir a equipe "' + e.NOME + '"?

Serão excluídos também os funcionários e as regras de valor desta equipe. Essa ação não pode ser desfeita e muda o cálculo de períodos passados.')) return;
            EX.acao(async () => { await EX.post('/expedicao-api-equipe-excluir', { id: e.ID_EQUIPE }); await carregar(); EX.aviso('Equipe excluída.', true); });
        } else if (b.dataset.a === 'edit') {
            $i('eqId').value = e.ID_EQUIPE; $i('eqNome').value = e.NOME; $i('eqTipo').value = e.TIPO; $i('eqAtivo').value = e.ATIVO;
            $i('modalEquipeTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Equipe'; EX.modal('modalEquipe').show();
        } else {
            equipeSel = e; $i('mbEquipe').textContent = e.NOME + ' (' + EX.tipo(e.TIPO) + ')'; limparMembro(); EX.modal('modalMembros').show(); EX.acao(carregarMembros);
        }
    });
    $i('btnLimparMembro').onclick = limparMembro;
    $i('btnSalvarMembro').onclick = () => EX.acao(async () => {
        const id = $i('mbId').value;
        const fid = id ? (membros.find(m => String(m.ID) === id) || {}).ID_FUNCIONARIO : $i('mbFunc').value;
        if (!fid) throw new Error('Selecione o funcionário.');
        await EX.post('/expedicao-api-membro-salvar', { id, id_equipe: equipeSel.ID_EQUIPE, id_funcionario: fid, dt_ini: $i('mbIni').value, dt_fim: $i('mbFim').value, ativo: $i('mbAtivo').value });
        limparMembro(); await carregarMembros(); await carregar(); EX.aviso('Funcionário da equipe salvo.', true);
    });
    $i('mbCorpo').addEventListener('click', ev => {
        const b = ev.target.closest('button'); if (!b) return;
        if (b.dataset.d) {
            const x = membros.find(y => String(y.ID) === b.dataset.d); if (!x || !confirm('Remover ' + x.FUNCIONARIO + ' da equipe?')) return;
            EX.acao(async () => { await EX.post('/expedicao-api-membro-excluir', { id: x.ID }); limparMembro(); await carregarMembros(); await carregar(); EX.aviso('Funcionário removido da equipe.', true); });
            return;
        }
        const m = membros.find(x => String(x.ID) === b.dataset.m); if (!m) return;
        $i('mbId').value = m.ID; $($i('mbFunc')).val(m.ID_FUNCIONARIO).trigger('change'); $i('mbFunc').disabled = true;
        $i('mbIni').value = m.DT_INI || ''; $i('mbFim').value = m.DT_FIM || ''; $i('mbAtivo').value = m.ATIVO;
    });

    EX.acao(async () => { await EX.funcionarios($i('mbFunc'), 'modalMembros'); limparMembro(); await carregar(); });
});
</script>
<?= $render('footer') ?>
