<?php
/**
 * @var bool     $is_admin
 * @var array    $rotas_permitidas
 * @var string   $base
 * @var callable $render
 */
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle'  => 'Programação de Pedidos',
    'showNavbar' => true,
    'pageActive' => 'faturamento-programacao',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin: 0; padding: 0;'
]) ?>

<style>
    .prog-container { padding: 12px; }
    .pivot-table { font-size: 13px; width: 100%; border-collapse: collapse; }
    .pivot-table th, .pivot-table td { padding: 6px 10px; border: 1px solid #dee2e6; white-space: nowrap; }
    .pivot-table thead th { background: #343a40; color: #fff; text-align: center; position: sticky; top: 0; z-index: 5; }
    .pivot-table thead th.col-agenda { background: #495057; }
    .pivot-table td.val { text-align: right; }
    .pivot-table td.pct { text-align: right; color: #6c757d; font-size: 12px; }
    .pivot-table tr.row-total { background: #f1f3f5; font-weight: 700; }
    .pivot-table tr.row-group { background: #d0ebff; font-weight: 700; color: #1864ab; }
    .pivot-table tr.row-group-total { background: #e7f5ff; font-weight: 600; color: #1864ab; }
    .pivot-table tr:hover:not(.row-total):not(.row-group):not(.row-group-total) { background: #f8f9fa; }
    .pivot-wrap { overflow-x: auto; max-height: 70vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; }
    .loading-overlay { text-align: center; padding: 60px 20px; color: #6c757d; }
    #conteudoOcupacao .pivot-wrap { max-height: none; overflow-y: visible; }
</style>

<div class="prog-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-calendar2-range"></i> Programação de Pedidos</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="carregarDados()">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button class="btn btn-sm btn-outline-success" id="btnExportarCSV" onclick="exportarCSV()" disabled>
                <i class="bi bi-download"></i> Exportar Resumo
            </button>
            <button class="btn btn-sm btn-success" id="btnExportarGeral" onclick="exportarGeral()" disabled>
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar Geral
            </button>
            <button class="btn btn-sm btn-primary" onclick="abrirModalIncluirCliente()">
                <i class="bi bi-person-plus"></i> Incluir Cliente
            </button>
        </div>
    </div>

<!-- Modal: Lista de clientes cadastrados -->
<div class="modal fade" id="modalIncluirCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people"></i> Clientes na Programação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="icListaCadastrados" style="min-height:200px;max-height:60vh;overflow-y:auto;">
                    <div class="text-center text-muted p-4">Carregando...</div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="icQtdCadastrados"></small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="abrirFormCliente(null)">
                    <i class="bi bi-person-plus"></i> Novo Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Formulário incluir/editar cliente -->
<div class="modal fade" id="modalFormCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="icFormTitulo"><i class="bi bi-person-plus"></i> Incluir Cliente</h5>
                <button type="button" class="btn-close" onclick="voltarLista()"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente</label>
                    <div class="input-group">
                        <input type="number" id="icCodCli" class="form-control" placeholder="Código do cliente" min="1">
                        <button class="btn btn-outline-secondary" type="button" onclick="buscarCliente()">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div id="icNomeCliente" class="form-text fw-semibold mt-1"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tipo</label>
                    <select id="icTipo" class="form-select">
                        <option value="">Selecione...</option>
                        <option value="3 - MERCADO">3 - MERCADO</option>
                        <option value="2 - REDE">2 - REDE</option>
                        <option value="4 - MERCADO">4 - MERCADO</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Canal</label>
                    <select id="icCanal" class="form-select">
                        <option value="">Selecione...</option>
                        <option value="3 - 100 PRINCIPAIS">3 - 100 PRINCIPAIS</option>
                        <option value="4 - PICADO">4 - PICADO</option>
                        <option value="2 - MAGAZINE LUIZA">2 - MAGAZINE LUIZA</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Programação</label>
                    <select id="icProgramacao" class="form-select">
                        <option value="">Selecione...</option>
                        <option value="Agendado">Agendado</option>
                    </select>
                </div>
                <div id="icErro" class="alert alert-danger py-2 d-none"></div>
                <div id="icSucesso" class="alert alert-success py-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="voltarLista()">
                    <i class="bi bi-arrow-left"></i> Voltar
                </button>
                <button type="button" class="btn btn-primary" id="btnSalvarCliente" onclick="salvarClienteProgramacao()">
                    <i class="bi bi-check-lg"></i> <span id="btnSalvarLabel">Salvar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let icClienteValido    = false;
let icClienteDescricao = '';
let icModoEdicao       = false;
let icListaCache       = [];

async function abrirModalIncluirCliente() {
    new bootstrap.Modal(document.getElementById('modalIncluirCliente')).show();
    await carregarListaCadastrados();
}

async function carregarListaCadastrados() {
    const lista = document.getElementById('icListaCadastrados');
    lista.innerHTML = '<div class="text-center text-muted p-4">Carregando...</div>';
    try {
        const r = await fetch(`<?= $base ?>faturamento-api-programacao-listar-clientes`);
        const d = await r.json();
        icListaCache = d.data || [];
        document.getElementById('icQtdCadastrados').textContent = icListaCache.length + ' cliente(s) cadastrado(s)';
        if (!icListaCache.length) {
            lista.innerHTML = '<div class="text-center text-muted p-4">Nenhum cliente cadastrado.</div>';
            return;
        }
        lista.innerHTML = `<table class="table table-sm table-hover mb-0">
            <thead class="table-dark sticky-top"><tr>
                <th style="width:70px">Cód</th>
                <th>Descrição</th>
                <th>Tipo</th>
                <th>Canal</th>
                <th>Programação</th>
                <th style="width:40px"></th>
            </tr></thead>
            <tbody>${icListaCache.map(c => `
                <tr style="cursor:pointer" onclick="abrirFormCliente(${c.COD_CLI})">
                    <td>${c.COD_CLI}</td>
                    <td>${c.DESCRICAO || ''}</td>
                    <td><span class="badge bg-secondary">${c.TIPO || ''}</span></td>
                    <td><span class="badge bg-info text-dark">${c.CANAL || ''}</span></td>
                    <td>${c.PROGRAMACAO || ''}</td>
                    <td><i class="bi bi-pencil-square text-primary"></i></td>
                </tr>`).join('')}
            </tbody></table>`;
    } catch (e) {
        lista.innerHTML = '<div class="text-center text-danger p-4">Erro ao carregar clientes.</div>';
    }
}

function abrirFormCliente(codCli) {
    // Fecha lista, abre form
    bootstrap.Modal.getInstance(document.getElementById('modalIncluirCliente'))?.hide();

    // Limpa form
    document.getElementById('icCodCli').value = '';
    document.getElementById('icNomeCliente').textContent = '';
    document.getElementById('icNomeCliente').className = 'form-text fw-semibold mt-1';
    document.getElementById('icTipo').value = '';
    document.getElementById('icCanal').value = '';
    document.getElementById('icProgramacao').value = '';
    document.getElementById('icErro').classList.add('d-none');
    document.getElementById('icSucesso').classList.add('d-none');
    icClienteValido    = false;
    icClienteDescricao = '';
    icModoEdicao       = false;

    if (codCli) {
        // Modo edição — preenche com dados da lista
        const c = icListaCache.find(x => x.COD_CLI == codCli);
        if (c) {
            document.getElementById('icCodCli').value = c.COD_CLI;
            document.getElementById('icCodCli').readOnly = true;
            document.getElementById('icNomeCliente').textContent = c.DESCRICAO || '';
            document.getElementById('icNomeCliente').className = 'form-text text-primary fw-semibold mt-1';
            document.getElementById('icTipo').value = c.TIPO || '';
            document.getElementById('icCanal').value = c.CANAL || '';
            document.getElementById('icProgramacao').value = c.PROGRAMACAO || '';
            icClienteValido    = true;
            icClienteDescricao = c.DESCRICAO || '';
            icModoEdicao       = true;
        }
        document.getElementById('icFormTitulo').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Cliente';
        document.getElementById('btnSalvarLabel').textContent = 'Alterar';
        document.getElementById('btnSalvarCliente').className = 'btn btn-warning';
    } else {
        document.getElementById('icCodCli').readOnly = false;
        document.getElementById('icFormTitulo').innerHTML = '<i class="bi bi-person-plus"></i> Incluir Cliente';
        document.getElementById('btnSalvarLabel').textContent = 'Salvar';
        document.getElementById('btnSalvarCliente').className = 'btn btn-primary';
    }

    new bootstrap.Modal(document.getElementById('modalFormCliente')).show();
}

function voltarLista() {
    bootstrap.Modal.getInstance(document.getElementById('modalFormCliente'))?.hide();
    document.getElementById('modalIncluirCliente').addEventListener('shown.bs.modal', () => {}, { once: true });
    new bootstrap.Modal(document.getElementById('modalIncluirCliente')).show();
}

async function buscarCliente() {
    const cod    = document.getElementById('icCodCli').value.trim();
    const nomeEl = document.getElementById('icNomeCliente');
    if (!cod) { nomeEl.textContent = ''; icClienteValido = false; return; }
    nomeEl.className = 'form-text text-muted fw-semibold mt-1';
    nomeEl.textContent = 'Buscando...';
    icClienteValido = false;
    try {
        const r = await fetch(`<?= $base ?>faturamento-api-programacao-buscar-cliente?cod=${encodeURIComponent(cod)}`);
        const d = await r.json();
        if (d.success && d.data) {
            icClienteDescricao = d.data.DESCRICAO;
            icClienteValido    = true;
            nomeEl.className   = 'form-text text-success fw-semibold mt-1';
            nomeEl.textContent = d.data.DESCRICAO;
        } else {
            nomeEl.className   = 'form-text text-danger fw-semibold mt-1';
            nomeEl.textContent = 'Cliente não encontrado.';
        }
    } catch (e) {
        nomeEl.className   = 'form-text text-danger fw-semibold mt-1';
        nomeEl.textContent = 'Erro ao buscar.';
    }
}

async function salvarClienteProgramacao() {
    const erroEl    = document.getElementById('icErro');
    const sucessoEl = document.getElementById('icSucesso');
    erroEl.classList.add('d-none');
    sucessoEl.classList.add('d-none');

    const cod  = document.getElementById('icCodCli').value.trim();
    const tipo = document.getElementById('icTipo').value;
    const canal= document.getElementById('icCanal').value;
    const prog = document.getElementById('icProgramacao').value;

    if (!icClienteValido) { erroEl.textContent = 'Busque e valide o cliente primeiro.'; erroEl.classList.remove('d-none'); return; }
    if (!tipo)  { erroEl.textContent = 'Selecione o Tipo.';        erroEl.classList.remove('d-none'); return; }
    if (!canal) { erroEl.textContent = 'Selecione o Canal.';       erroEl.classList.remove('d-none'); return; }
    if (!prog)  { erroEl.textContent = 'Selecione a Programação.'; erroEl.classList.remove('d-none'); return; }

    const btn = document.getElementById('btnSalvarCliente');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

    try {
        const r = await fetch(`<?= $base ?>faturamento-api-programacao-incluir-cliente`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cod_cli: cod, descricao: icClienteDescricao, tipo, canal, programacao: prog, editar: icModoEdicao }),
        });
        const d = await r.json();
        if (d.success) {
            sucessoEl.textContent = icModoEdicao ? 'Cliente alterado com sucesso!' : 'Cliente incluído com sucesso!';
            sucessoEl.classList.remove('d-none');
            setTimeout(voltarLista, 1200);
        } else {
            erroEl.textContent = d.error || 'Erro ao salvar.';
            erroEl.classList.remove('d-none');
        }
    } catch (e) {
        erroEl.textContent = 'Erro de rede.';
        erroEl.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-check-lg"></i> <span id="btnSalvarLabel">${icModoEdicao ? 'Alterar' : 'Salvar'}</span>`;
    }
}

let icBuscaTimer = null;
document.getElementById('icCodCli')?.addEventListener('input', function () {
    clearTimeout(icBuscaTimer);
    const cod = this.value.trim();
    if (!cod) {
        document.getElementById('icNomeCliente').textContent = '';
        icClienteValido = false;
        return;
    }
    icBuscaTimer = setTimeout(buscarCliente, 600);
});
document.getElementById('icCodCli')?.addEventListener('keydown', e => { if (e.key === 'Enter') { clearTimeout(icBuscaTimer); buscarCliente(); } });
</script>

    <!-- Resumo rápido -->
    <div class="row g-2 mb-3" id="resumoCards" style="display:none!important;">
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardTotal">-</div>
                <div class="text-muted" style="font-size:12px;">Total Carteira</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardSemAgenda">-</div>
                <div class="text-muted" style="font-size:12px;">Sem Programação</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardMesAtual">-</div>
                <div class="text-muted" style="font-size:12px;" id="labelMesAtual">Mês Atual</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-body py-2 px-3 text-center" style="min-width:130px;">
                <div class="fw-bold fs-5" id="cardProxMes">-</div>
                <div class="text-muted" style="font-size:12px;" id="labelProxMes">Próx. Mês</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="pivotTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEmpresa">
                <i class="bi bi-building"></i> Por Empresa
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTipo">
                <i class="bi bi-tags"></i> Por Tipo
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tabOcupacaoBtn" data-bs-toggle="tab" data-bs-target="#tabOcupacao">
                <i class="bi bi-speedometer2"></i> Taxa de Ocupação
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-0">
        <div class="tab-pane fade show active" id="tabEmpresa">
            <div id="conteudoEmpresa">
                <div class="loading-overlay">
                    <span class="spinner-border spinner-border-sm me-2"></span> Carregando dados...
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="tabTipo">
            <div id="conteudoTipo">
                <div class="loading-overlay text-muted">Aguardando carregamento...</div>
            </div>
        </div>
        <div class="tab-pane fade" id="tabOcupacao">
            <div id="conteudoOcupacao">
                <div class="loading-overlay text-muted">Clique na aba para carregar...</div>
            </div>
        </div>
    </div>
</div>

<?= $render('footer', [
    'customJS' => ['src/js/faturamento-programacao.js']
]) ?>
