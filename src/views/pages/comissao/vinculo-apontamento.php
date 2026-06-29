<?php
// Verificar permissão de acesso (dados injetados pelo Controller)
$acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoComissao) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Vincular Recurso em Apontamentos',
    'showNavbar' => true,
    'pageActive' => 'comissao-vinculo-apontamento',
    'customCSS' => ['src/css/comissao-dashboard.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid p-2">
    <div class="d-flex justify-content-end mb-2">
        <a href="<?= $base ?>comissao-cadastro" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <!-- Filtros Simples -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filtroCentro" class="form-label">Centro de Trabalho</label>
                    <select id="filtroCentro" class="form-select" style="width: 100%;">
                        <option value="">Selecione o Centro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filtroDataInicio" class="form-label">Data Início</label>
                    <input type="date" id="filtroDataInicio" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="filtroDataFim" class="form-label">Data Fim</label>
                    <input type="date" id="filtroDataFim" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="filtroRecurso" class="form-label">Recurso para Vínculo em Lote</label>
                    <select id="filtroRecurso" class="form-select" style="width: 100%;">
                        <option value="">Selecione o Recurso</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="buscarApontamentos()">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão Vincular em Lote -->
    <div class="mb-3 d-flex flex-wrap align-items-center gap-2" id="acoesLote" style="display: none;">
        <button type="button" class="btn btn-success btn-sm" onclick="vincularLote()">
            <i class="bi bi-link-45deg"></i> Vincular Selecionados (<span id="qtdSelecionados">0</span>)
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selecionarTodos()">
            <i class="bi bi-check2-square"></i> Selecionar Todos
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="desmarcarTodos()">
            <i class="bi bi-square"></i> Desmarcar Todos
        </button>
        <div class="ms-auto" style="min-width: 260px;">
            <input type="text" id="filtroAptIds" class="form-control form-control-sm"
                   placeholder="Filtrar por Apt. ID (separados por vírgula)..."
                   oninput="filtrarTabela()">
        </div>
        <div style="min-width: 280px;">
            <input type="text" id="filtroProduto" class="form-control form-control-sm"
                   placeholder="Filtrar por produto, código ou máscara..."
                   oninput="filtrarTabela()">
        </div>
    </div>

    <!-- Tabela de Apontamentos -->
    <div class="card">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <div>
                <strong><i class="bi bi-exclamation-triangle"></i> Apontamentos Sem Recurso</strong>
                <span id="totalRegistros" class="badge bg-dark ms-2">0</span>
            </div>
            <button type="button" class="btn btn-success btn-sm" onclick="downloadExcel()" title="Exportar para Excel">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="tabelaApontamentos">
                <thead class="table-dark">
                    <tr>
                        <th width="30"><input type="checkbox" id="checkTodos" onchange="toggleTodos()"></th>
                        <th>Apt. ID</th>
                        <th>Produto</th>
                        <th>Máscara</th>
                        <th>Centro</th>
                        <th>Data</th>
                        <th>Qtd</th>
                        <th width="200">Recurso</th>
                        <th width="70">Ação</th>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            Clique em "Buscar" para carregar apontamentos
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let apontamentos = [];
let recursos = [];
let centros = [];
let selecionados = [];
const emprId = '<?= $empresa['id'] ?? '' ?>';

document.addEventListener('DOMContentLoaded', function() {
    carregarCentrosTrabalho();
    carregarRecursos();
    definirDatas();
});

function definirDatas() {
    const hoje = new Date();
    const inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    document.getElementById('filtroDataInicio').value = inicio.toISOString().split('T')[0];
    document.getElementById('filtroDataFim').value = hoje.toISOString().split('T')[0];
}

function carregarCentrosTrabalho() {
    fetch(`/comissao-api-centros?empr_id=${emprId}`)
        .then(r => r.json())
        .then(result => {
            centros = result.data || result || [];
            inicializarSelect2Centro();
        });
}

function inicializarSelect2Centro() {
    if ($('#filtroCentro').data('select2')) {
        $('#filtroCentro').select2('destroy');
    }
    
    const dados = [{id: '', text: 'Selecione o Centro'}].concat(
        centros.map(c => ({
            id: c.ID,
            text: (c.COD_CENTRO || '') + ' - ' + (c.DESCRICAO || '')
        }))
    );
    
    $('#filtroCentro').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true,
        data: dados
    });
}

function carregarRecursos() {
    fetch(`/comissao-api-recursos-vinculados?empr_id=${emprId}`)
        .then(r => r.json())
        .then(result => {
            recursos = result.data || result || [];
            inicializarSelect2Recurso();
        });
}

function inicializarSelect2Recurso() {
    if ($('#filtroRecurso').data('select2')) {
        $('#filtroRecurso').select2('destroy');
    }
    
    const dados = [{id: '', text: 'Selecione o Recurso'}].concat(
        recursos.map(r => ({
            id: r.ID,
            text: (r.COD_MAQUINA || '') + ' - ' + (r.DESCRICAO || '')
        }))
    );
    
    $('#filtroRecurso').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true,
        data: dados
    });
}

function buscarApontamentos() {
    const centroId = document.getElementById('filtroCentro').value;
    const dataInicio = document.getElementById('filtroDataInicio').value;
    const dataFim = document.getElementById('filtroDataFim').value;
    
    const tbody = document.getElementById('tabelaBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="bi bi-hourglass-split"></i> Carregando...</td></tr>';
    
    let url = `/comissao-api-apontamentos-sem-recurso?empr_id=${emprId}&dt_inicio=${dataInicio}&dt_fim=${dataFim}`;
    if (centroId) url += `&centro_trab_id=${centroId}`;
    
    fetch(url)
        .then(r => r.json())
        .then(result => {
            apontamentos = result.data || result || [];
            selecionados = [];
            renderizarTabela();
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Erro ao carregar dados</td></tr>';
            console.error(err);
        });
}

function getListaFiltrada() {
    const filtroTxt   = (document.getElementById('filtroProduto')?.value || '').trim().toLowerCase();
    const filtroIdsRaw = (document.getElementById('filtroAptIds')?.value || '').trim();
    const filtroIds = filtroIdsRaw
        ? filtroIdsRaw.split(',').map(s => s.trim()).filter(Boolean)
        : [];

    let lista = apontamentos;
    if (filtroIds.length) {
        lista = lista.filter(ap => filtroIds.includes(String(ap.APONTAMENTO_ID)));
    }
    if (filtroTxt) {
        lista = lista.filter(ap => {
            const alvo = [
                ap.COD_ITEM,
                ap.DESC_ITEM,
                ap.MASCARA,
                ap.ID_MASCARA,
                ap.APONTAMENTO_ID
            ].map(v => (v ?? '').toString().toLowerCase()).join(' ');
            return alvo.indexOf(filtroTxt) !== -1;
        });
    }
    return lista;
}

function renderizarTabela() {
    const tbody = document.getElementById('tabelaBody');
    const lista = getListaFiltrada();
    const filtroAtivo = lista.length !== apontamentos.length;
    document.getElementById('totalRegistros').textContent = lista.length + (filtroAtivo ? ' / ' + apontamentos.length : '');
    document.getElementById('acoesLote').style.display = apontamentos.length > 0 ? 'flex' : 'none';

    if (!apontamentos.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> Nenhum apontamento sem recurso encontrado</td></tr>';
        return;
    }

    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-search"></i> Nenhum apontamento corresponde ao filtro</td></tr>';
        return;
    }

    let html = '';
    lista.forEach(ap => {
        const checked = selecionados.includes(ap.APONTAMENTO_ID) ? 'checked' : '';
        const dataFmt = ap.DT_APONT_FMT || formatarData(ap.DATA_APONTAMENTO);

        html += `
            <tr>
                <td><input type="checkbox" class="checkItem" value="${ap.APONTAMENTO_ID}" ${checked} onchange="toggleItem(${ap.APONTAMENTO_ID})"></td>
                <td><strong>${ap.APONTAMENTO_ID}</strong></td>
                <td>${ap.COD_ITEM || ''} - ${(ap.DESC_ITEM || '').substring(0, 40)}</td>
                <td>${ap.MASCARA || '-'} <small class="text-muted">(${ap.ID_MASCARA || '-'})</small></td>
                <td>${ap.COD_CENTRO || ''}</td>
                <td>${dataFmt}</td>
                <td class="text-center">${ap.QUANTIDADE}</td>
                <td style="min-width: 280px;">
                    <select class="form-select form-select-sm select-recurso" id="rec_${ap.APONTAMENTO_ID}" style="width: 100%;">
                        <option value="">Selecione</option>
                    </select>
                </td>
                <td>
                    <button class="btn btn-success btn-sm py-0 px-2" onclick="vincularIndividual(${ap.APONTAMENTO_ID})" title="Vincular">
                        <i class="bi bi-link-45deg"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    inicializarSelect2Tabela();
    atualizarContador();
}

function filtrarTabela() {
    renderizarTabela();
}

function inicializarSelect2Tabela() {
    $('.select-recurso').each(function() {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
        
        $(this).select2({
            theme: 'bootstrap-5',
            language: 'pt-BR',
            placeholder: 'Digite...',
            allowClear: true,
            data: [{id: '', text: 'Selecione'}].concat(
                recursos.map(r => ({
                    id: r.ID,
                    text: (r.COD_MAQUINA || '') + ' - ' + (r.DESCRICAO || '')
                }))
            )
        });
    });
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

function toggleItem(id) {
    const idx = selecionados.indexOf(id);
    if (idx > -1) {
        selecionados.splice(idx, 1);
    } else {
        selecionados.push(id);
    }
    atualizarContador();
}

function toggleTodos() {
    const checked = document.getElementById('checkTodos').checked;
    selecionados = checked ? getListaFiltrada().map(a => a.APONTAMENTO_ID) : [];
    document.querySelectorAll('.checkItem').forEach(cb => cb.checked = checked);
    atualizarContador();
}

function selecionarTodos() {
    document.getElementById('checkTodos').checked = true;
    toggleTodos();
}

function desmarcarTodos() {
    document.getElementById('checkTodos').checked = false;
    toggleTodos();
}

function atualizarContador() {
    document.getElementById('qtdSelecionados').textContent = selecionados.length;
}

function vincularIndividual(apontamentoId) {
    const recursoId = document.getElementById(`rec_${apontamentoId}`).value;
    if (!recursoId) {
        alert('Selecione um recurso');
        return;
    }
    
    fetch('/comissao-api-vincular-recurso', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ apontamento_id: apontamentoId, recurso_id: recursoId })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            // Remove da lista
            apontamentos = apontamentos.filter(a => a.APONTAMENTO_ID !== apontamentoId);
            selecionados = selecionados.filter(s => s !== apontamentoId);
            renderizarTabela();
        } else {
            alert(result.error || result.message || 'Erro ao vincular');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao vincular recurso');
    });
}

function downloadExcel() {
    if (!apontamentos.length) {
        alert('Nenhum dado para exportar.');
        return;
    }

    const cols = [
        ['APONTAMENTO_ID', 'APT. ID'],
        ['COD_ITEM',       'CÓD. ITEM'],
        ['DESC_ITEM',      'PRODUTO'],
        ['MASCARA',        'MÁSCARA'],
        ['ID_MASCARA',     'ID MÁSCARA'],
        ['COD_CENTRO',     'CENTRO'],
        ['DT_APONT',       'DATA'],
        ['QUANTIDADE',     'QTD'],
    ];

    const esc = v => {
        if (v === null || v === undefined || v === '') return '';
        const s = String(v).replaceAll('"', '""');
        return /[;"\n\r]/.test(s) ? `"${s}"` : s;
    };

    const linhas = [
        cols.map(([, label]) => esc(label)).join(';'),
        ...apontamentos.map(ap => cols.map(([key]) => {
            if (key === 'DT_APONT') return esc(ap.DT_APONT_FMT || formatarData(ap.DATA_APONTAMENTO));
            return esc(ap[key]);
        }).join(';')),
    ];

    const blob = new Blob(['﻿' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), {
        href:     url,
        download: `apontamentos_sem_recurso_${document.getElementById('filtroDataInicio').value || 'export'}.csv`,
    });
    a.click();
    URL.revokeObjectURL(url);
}

function vincularLote() {
    const recursoId = document.getElementById('filtroRecurso').value;
    if (!recursoId) {
        alert('Selecione um recurso no filtro para vínculo em lote');
        return;
    }
    
    if (!selecionados.length) {
        alert('Selecione ao menos um apontamento');
        return;
    }
    
    if (!confirm(`Vincular ${selecionados.length} apontamentos ao recurso selecionado?`)) return;
    
    let sucesso = 0;
    let erros = 0;
    
    const promises = selecionados.map(aptId => {
        return fetch('/comissao-api-vincular-recurso', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ apontamento_id: aptId, recurso_id: recursoId })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) sucesso++;
            else erros++;
        })
        .catch(() => erros++);
    });
    
    Promise.all(promises).then(() => {
        alert(`Vinculados: ${sucesso} | Erros: ${erros}`);
        buscarApontamentos();
    });
}
</script>

<?= $render('footer') ?>
