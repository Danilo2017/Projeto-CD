// JS para tela de vínculo centro/recurso/funcionário

// Caches para Select2
let funcionariosCache = [];
let recursosCache = [];
let centrosCache = [];
let centrosCustoCache = [];
// Cache da última listagem (para exportação Excel)
let vinculosListaAtual = [];
// Dados pendentes para preencher o modal de edição após shown.bs.modal
let pendingEditData = null;

$(document).ready(function() {
    carregarFuncionarios();
    carregarRecursos();
    carregarCentros();
    carregarCentrosCusto();
    carregarVinculos();

    // Inicializar Select2 do modal quando abrir e aplicar dados de edição pendentes
    $('#modalVinculo').on('shown.bs.modal', function() {
        inicializarSelect2Modal();
        if (pendingEditData) {
            const d = pendingEditData;
            pendingEditData = null;
            select2EnsureOption('#funcionario_id', d.funcionarioId, d.funcionarioText);
            $('#funcionario_id').val(d.funcionarioId).trigger('change');
            if (d.recursoId) {
                select2EnsureOption('#recurso_id', d.recursoId, d.recursoText);
                $('#recurso_id').val(d.recursoId).trigger('change');
            } else {
                $('#recurso_id').val('').trigger('change');
            }
            select2EnsureOption('#centro_id', d.centroId, d.centroText);
            $('#centro_id').val(d.centroId).trigger('change');
            if (d.ccId) {
                select2EnsureOption('#cc_id', d.ccId, d.ccText);
                $('#cc_id').val(d.ccId).trigger('change');
            } else {
                $('#cc_id').val('').trigger('change');
            }
        }
    });

    // Event delegation — funciona mesmo após DataTables re-renderizar
    $(document).on('click', '.btn-editar-vinculo', function() {
        const b = $(this);
        editarVinculo(
            b.data('id'),
            b.data('func'),
            decodeURIComponent(b.attr('data-func-text') || ''),
            b.data('rec') || null,
            decodeURIComponent(b.attr('data-rec-text') || ''),
            b.data('centro'),
            decodeURIComponent(b.attr('data-centro-text') || ''),
            b.data('tipo'),
            b.data('cc') || null,
            decodeURIComponent(b.attr('data-cc-text') || '')
        );
    });

    $(document).on('click', '.btn-excluir-vinculo', function() {
        excluirVinculo($(this).data('id'));
    });

    $(document).on('click', '.btn-toggle-vinculo', function() {
        toggleVinculo($(this).data('id'), $(this).data('ativo'));
    });

    $(document).on('click', '.btn-apoio-vinculo', function() {
        const b = $(this);
        abrirDiasApoio(
            b.data('id'),
            decodeURIComponent(b.data('func-nome')),
            decodeURIComponent(b.data('centro-nome')),
            b.data('centro-id')
        );
    });
});

function inicializarSelect2Filtros() {
    // Select2 para filtro de funcionário
    if ($('#filtroFuncionario').data('select2')) {
        $('#filtroFuncionario').select2('destroy');
    }
    $('#filtroFuncionario').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite para buscar...',
        allowClear: true,
        data: [{id: '', text: 'Todos'}].concat(funcionariosCache.map(f => ({
            id: f.ID,
            text: (f.COD_FUNC ? f.COD_FUNC + ' - ' : '') + f.NOME
        })))
    });
    
    // Select2 para filtro de recurso
    if ($('#filtroRecurso').data('select2')) {
        $('#filtroRecurso').select2('destroy');
    }
    $('#filtroRecurso').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite para buscar...',
        allowClear: true,
        data: [{id: '', text: 'Todos'}].concat(recursosCache.map(r => ({
            id: r.ID,
            text: (r.COD_MAQUINA ? r.COD_MAQUINA + ' - ' : '') + r.DESCRICAO
        })))
    });
    
    // Select2 para filtro de centro
    if ($('#filtroCentro').data('select2')) {
        $('#filtroCentro').select2('destroy');
    }
    $('#filtroCentro').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite para buscar...',
        allowClear: true,
        data: [{id: '', text: 'Todos'}].concat(centrosCache.map(c => ({
            id: c.ID,
            text: (c.COD_CENTRO ? c.COD_CENTRO + ' - ' : '') + c.DESCRICAO
        })))
    });
}

function inicializarSelect2Modal() {
    // Select2 para funcionário no modal
    if ($('#funcionario_id').data('select2')) {
        $('#funcionario_id').select2('destroy');
    }
    $('#funcionario_id').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou nome...',
        allowClear: true,
        dropdownParent: $('#modalVinculo'),
        data: [{id: '', text: 'Selecione'}].concat(funcionariosCache.map(f => ({
            id: f.ID,
            text: (f.COD_FUNC ? f.COD_FUNC + ' - ' : '') + f.NOME
        })))
    });
    
    // Select2 para recurso no modal
    if ($('#recurso_id').data('select2')) {
        $('#recurso_id').select2('destroy');
    }
    $('#recurso_id').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou descrição...',
        allowClear: true,
        dropdownParent: $('#modalVinculo'),
        data: [{id: '', text: 'Selecione'}].concat(recursosCache.map(r => ({
            id: r.ID,
            text: (r.COD_MAQUINA ? r.COD_MAQUINA + ' - ' : '') + r.DESCRICAO
        })))
    });
    
    // Select2 para centro no modal
    if ($('#centro_id').data('select2')) {
        $('#centro_id').select2('destroy');
    }
    $('#centro_id').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite código ou descrição...',
        allowClear: true,
        dropdownParent: $('#modalVinculo'),
        data: [{id: '', text: 'Selecione'}].concat(centrosCache.map(c => ({
            id: c.ID,
            text: (c.COD_CENTRO ? c.COD_CENTRO + ' - ' : '') + c.DESCRICAO
        })))
    });

    // Select2 para Centro de Custo no modal (opcional)
    if ($('#cc_id').data('select2')) {
        $('#cc_id').select2('destroy');
    }
    $('#cc_id').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Selecione (opcional)...',
        allowClear: true,
        dropdownParent: $('#modalVinculo'),
        data: [{id: '', text: 'Nenhum'}].concat(centrosCustoCache.map(c => ({
            id: c.ID,
            text: (c.COD ? c.COD + ' - ' : '') + c.DESCRICAO
        })))
    });
}

function carregarFuncionarios() {
    $.get('/comissao-api-funcionarios', function(res) {
        funcionariosCache = res.data || [];
        inicializarSelect2Filtros();
    });
}

function carregarRecursos() {
    $.get('/comissao-api-recursos', function(res) {
        recursosCache = res.data || [];
        inicializarSelect2Filtros();
    });
}

function carregarCentros() {
    $.get('/comissao-api-centros', function(res) {
        centrosCache = res.data || [];
        inicializarSelect2Filtros();
    });
}

function carregarCentrosCusto() {
    $.get('/comissao-api-centros-custo', function(res) {
        centrosCustoCache = (res && res.data) ? res.data : [];
    });
}

function novoVinculo() {
    $('#formVinculo')[0].reset();
    $('#vinculoId').val('');
    $('#tipo_vinculo').val('N');
    toggleRecursoObrigatorio();
    $('#modalVinculoTitulo').html('<i class="bi bi-link-45deg"></i> Novo Vínculo');
    
    // Limpar Select2 do modal
    if ($('#funcionario_id').data('select2')) {
        $('#funcionario_id').val('').trigger('change');
    }
    if ($('#recurso_id').data('select2')) {
        $('#recurso_id').val('').trigger('change');
    }
    if ($('#centro_id').data('select2')) {
        $('#centro_id').val('').trigger('change');
    }
    if ($('#cc_id').data('select2')) {
        $('#cc_id').val('').trigger('change');
    }
}

// Alterna obrigatoriedade do recurso com base no tipo de vínculo
function toggleRecursoObrigatorio() {
    const tipo = $('#tipo_vinculo').val();
    if (tipo === 'A') {
        // Apoio: recurso é opcional
        $('#recurso_id').prop('required', false);
        $('#recursoReq').hide();
        $('#recursoHelp').show();
    } else {
        // Normal: recurso é obrigatório
        $('#recurso_id').prop('required', true);
        $('#recursoReq').show();
        $('#recursoHelp').hide();
    }
}

function salvarVinculo() {
    const btnSalvar = $('#btnSalvarVinculo');
    
    // Se já está salvando, não faz nada
    if (btnSalvar.prop('disabled')) {
        return;
    }
    
    const vinculoId = $('#vinculoId').val();
    const funcionario_id = $('#funcionario_id').val();
    const recurso_id = $('#recurso_id').val();
    const centro_id = $('#centro_id').val();
    const cc_id = $('#cc_id').val();
    const tipo_vinculo = $('#tipo_vinculo').val() || 'N';
    
    // Validação: recurso obrigatório apenas para vínculo Normal
    if (!funcionario_id || !centro_id) {
        alert('Preencha todos os campos obrigatórios!');
        return;
    }
    if (tipo_vinculo === 'N' && !recurso_id) {
        alert('Recurso é obrigatório para vínculo Normal!');
        return;
    }
    
    // Desabilitar botão e mostrar loading
    btnSalvar.prop('disabled', true);
    btnSalvar.html('<span class="spinner-border spinner-border-sm me-1"></span> Salvando...');
    
    const isEdit = vinculoId && vinculoId !== '';
    const payload = { funcionario_id, recurso_id: recurso_id || null, centro_id, tipo_vinculo, cc_id: cc_id || null };
    if (isEdit) payload.id = vinculoId;
    $.ajax({
        url: '/comissao-api-vinculo',
        method: isEdit ? 'PUT' : 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(resp) {
            if (resp.success) {
                $('#modalVinculo').modal('hide');
                carregarVinculos && carregarVinculos();
                alert(isEdit ? 'Vínculo atualizado com sucesso!' : 'Vínculo salvo com sucesso!');
            } else {
                alert(resp.error || 'Erro ao salvar vínculo!');
            }
        },
        error: function(xhr) {
            let msg = 'Erro ao salvar vínculo!';
            try {
                let resp = JSON.parse(xhr.responseText);
                if (resp.error) msg = resp.error;
            } catch(e) {}
            alert(msg);
        },
        complete: function() {
            // Reabilitar botão
            btnSalvar.prop('disabled', false);
            btnSalvar.html('<i class="bi bi-check-lg"></i> Salvar');
        }
    });
}

// Garante que o option com o ID existe no select antes de setá-lo no Select2
function select2EnsureOption(selector, id, text) {
    if (!id) return;
    const $s = $(selector);
    if ($s.find('option[value="' + id + '"]').length === 0) {
        $s.append(new Option(text || id, id, false, false));
    }
}

function editarVinculo(id, funcionarioId, funcionarioText, recursoId, recursoText, centroId, centroText, tipoVinculo, ccId, ccText) {
    $('#vinculoId').val(id);
    $('#tipo_vinculo').val(tipoVinculo || 'N');
    toggleRecursoObrigatorio();
    $('#modalVinculoTitulo').html('<i class="bi bi-pencil"></i> Editar Vínculo');

    pendingEditData = { funcionarioId, funcionarioText, recursoId, recursoText, centroId, centroText, ccId, ccText };

    $('#modalVinculo').modal('show');
}

function excluirVinculo(id) {
    if (!confirm('Tem certeza que deseja EXCLUIR este vínculo? Esta ação não pode ser desfeita.')) return;
    $.ajax({
        url: '/comissao-api-vinculo',
        method: 'DELETE',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        success: function(resp) {
            if (resp.success) {
                carregarVinculos();
                alert('Vínculo excluído com sucesso!');
            } else {
                alert(resp.error || 'Erro ao excluir vínculo');
            }
        },
        error: function() { alert('Erro ao excluir vínculo'); }
    });
}

function carregarVinculos() {
    const filtroFunc = $('#filtroFuncionario').val() || '';
    const filtroRec = $('#filtroRecurso').val() || '';
    const filtroCentro = $('#filtroCentro').val() || '';
    let params = [];
    if (filtroFunc) params.push('funcionario_id=' + filtroFunc);
    if (filtroRec) params.push('recurso_id=' + filtroRec);
    if (filtroCentro) params.push('centro_id=' + filtroCentro);
    let url = '/comissao-api-vinculos';
    if (params.length) url += '?' + params.join('&');

    $.get(url, function(resp) {
        if ($.fn.DataTable.isDataTable('#tabelaVinculos')) {
            $('#tabelaVinculos').DataTable().destroy();
        }
        let html = '';
        vinculosListaAtual = (resp.success && resp.data) ? resp.data : [];
        if (resp.success && resp.data && resp.data.length > 0) {
            resp.data.forEach(function(v) {
                let funcLabel = v.COD_FUNC ? v.COD_FUNC + ' - ' + v.FUNCIONARIO_NOME : v.FUNCIONARIO_NOME;
                let recLabel = v.ID_RECURSO ? (v.COD_MAQUINA ? v.COD_MAQUINA + ' - ' + v.RECURSO_DESCRICAO : v.RECURSO_DESCRICAO) : '<em class="text-muted">-</em>';
                let centroLabel = v.COD_CENTRO ? v.COD_CENTRO + ' - ' + v.CENTRO_DESCRICAO : v.CENTRO_DESCRICAO;
                let ccLabel = v.ID_EMP_CC && v.COD_CC ? (v.COD_CC + ' - ' + (v.CC_DESCRICAO || '')) : '<em class="text-muted">-</em>';
                let tipoVinculo = v.TIPO_VINCULO || 'N';
                let tipoBadge = tipoVinculo === 'A' 
                    ? '<span class="badge bg-info">Apoio</span>'
                    : '<span class="badge bg-secondary">Normal</span>';
                let statusBadge = v.ATIVO === 'S'
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-danger">Inativo</span>';
                let acoes = '<div class="d-flex gap-1 flex-wrap">';
                acoes += '<button class="btn btn-sm btn-primary btn-editar-vinculo"'
                    + ' data-id="' + v.ID_VINCULO + '"'
                    + ' data-func="' + v.ID_FUNCIONARIO + '"'
                    + ' data-func-text="' + encodeURIComponent(funcLabel) + '"'
                    + ' data-rec="' + (v.ID_RECURSO || '') + '"'
                    + ' data-rec-text="' + encodeURIComponent(v.ID_RECURSO ? (v.COD_MAQUINA ? v.COD_MAQUINA + ' - ' + v.RECURSO_DESCRICAO : v.RECURSO_DESCRICAO) : '') + '"'
                    + ' data-centro="' + v.ID_CENTRO_TRAB + '"'
                    + ' data-centro-text="' + encodeURIComponent(centroLabel) + '"'
                    + ' data-tipo="' + tipoVinculo + '"'
                    + ' data-cc="' + (v.ID_EMP_CC || '') + '"'
                    + ' data-cc-text="' + encodeURIComponent(v.ID_EMP_CC && v.COD_CC ? v.COD_CC + ' - ' + (v.CC_DESCRICAO || '') : '') + '"'
                    + ' title="Editar"><i class="bi bi-pencil"></i></button>';
                acoes += '<button class="btn btn-sm btn-danger btn-excluir-vinculo"'
                    + ' data-id="' + v.ID_VINCULO + '"'
                    + ' title="Excluir"><i class="bi bi-trash"></i></button>';
                if (v.ATIVO === 'S') {
                    acoes += '<button class="btn btn-sm btn-warning btn-toggle-vinculo"'
                        + ' data-id="' + v.ID_VINCULO + '" data-ativo="N"'
                        + ' title="Inativar"><i class="bi bi-x-circle"></i></button>';
                } else {
                    acoes += '<button class="btn btn-sm btn-success btn-toggle-vinculo"'
                        + ' data-id="' + v.ID_VINCULO + '" data-ativo="S"'
                        + ' title="Ativar"><i class="bi bi-check-circle"></i></button>';
                }
                if (tipoVinculo === 'N') {
                    acoes += '<button class="btn btn-sm btn-info btn-apoio-vinculo"'
                        + ' data-id="' + v.ID_VINCULO + '"'
                        + ' data-func-nome="' + encodeURIComponent(funcLabel) + '"'
                        + ' data-centro-nome="' + encodeURIComponent(centroLabel) + '"'
                        + ' data-centro-id="' + v.ID_CENTRO_TRAB + '"'
                        + ' title="Configurar Dias de Apoio"><i class="bi bi-calendar-event"></i></button>';
                }
                acoes += '</div>';
                html += '<tr>';
                html += '<td>' + v.ID_VINCULO + '</td>';
                html += '<td>' + (v.ID_EMPR || '') + '</td>';
                html += '<td>' + (funcLabel || '') + '</td>';
                html += '<td>' + tipoBadge + '</td>';
                html += '<td>' + (recLabel || '') + '</td>';
                html += '<td>' + (centroLabel || '') + '</td>';
                html += '<td>' + ccLabel + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td>' + acoes + '</td>';
                html += '</tr>';
            });
        }
        $('#tabelaVinculosBody').html(html);
        $('#tabelaVinculos').DataTable({
            language: {
                processing: "Processando...",
                search: "Pesquisar:",
                lengthMenu: "Exibir _MENU_ resultados por página",
                info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 até 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros no total)",
                loadingRecords: "Carregando...",
                zeroRecords: "Nenhum registro encontrado",
                emptyTable: "Nenhum dado disponível na tabela",
                paginate: {
                    first: "Primeiro",
                    previous: "Anterior",
                    next: "Próximo",
                    last: "Último"
                }
            },
            lengthChange: false,
            pageLength: 20,
            order: [[0, 'desc']]
        });
    }).fail(function(xhr) {
        console.error('Erro ao carregar vínculos:', xhr.status, xhr.responseText);
        let errMsg = 'Erro ao carregar vínculos';
        try { let r = JSON.parse(xhr.responseText); if(r.error) errMsg = r.error; } catch(e){}
        // Limpa tbody antes de inicializar DataTables (colspan causa erro)
        if ($.fn.DataTable.isDataTable('#tabelaVinculos')) {
            $('#tabelaVinculos').DataTable().destroy();
        }
        $('#tabelaVinculosBody').html('');
        $('#tabelaVinculos').DataTable({
            language: {
                processing: "Processando...",
                search: "Pesquisar:",
                lengthMenu: "Exibir _MENU_ resultados por página",
                info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 até 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros no total)",
                loadingRecords: "Carregando...",
                zeroRecords: "Nenhum registro encontrado",
                emptyTable: "Nenhum dado disponível na tabela",
                paginate: {
                    first: "Primeiro",
                    previous: "Anterior",
                    next: "Próximo",
                    last: "Último"
                }
            },
            lengthChange: false,
            pageLength: 20,
            order: [[0, 'desc']]
        });
        alert(errMsg);
    });
}

function toggleVinculo(id, ativo) {
    let msg = ativo === 'S' ? 'Deseja ATIVAR este vínculo?' : 'Deseja INATIVAR este vínculo?';
    if (!confirm(msg)) return;
    $.ajax({
        url: '/comissao-api-vinculo-status',
        method: 'PATCH',
        contentType: 'application/json',
        data: JSON.stringify({ id: id, ativo: ativo }),
        success: function(resp) {
            if (resp.success) {
                carregarVinculos();
            } else {
                alert(resp.error || 'Erro ao alterar status');
            }
        },
        error: function() { alert('Erro ao alterar status do vínculo'); }
    });
}

// ===================== FUNÇÕES DE DIAS DE APOIO =====================

var centroApoioAtual = null;

function abrirDiasApoio(vinculoId, funcNome, centroNome, idCentroTrab) {
    $('#diasApoioVinculoId').val(vinculoId);
    $('#diasApoioFuncNome').text(funcNome);
    $('#diasApoioCentroNome').text(centroNome);
    centroApoioAtual = idCentroTrab;
    
    // Carregar centros de trabalho no select
    carregarCentrosParaApoio(idCentroTrab);
    
    // Limpar campos de data
    $('#novaDataApoio').val('');
    $('#novaDataApoioFim').val('');
    
    // Carregar datas já configuradas
    carregarDiasApoio(vinculoId);
    
    // Abrir modal
    $('#modalDiasApoio').modal('show');
}

function carregarCentrosParaApoio(idCentroAtual) {
    $.get('/comissao-api-centros', function(res) {
        let options = '<option value="">Mesmo centro do vínculo</option>';
        if (res && res.data) {
            res.data.forEach(c => {
                let label = c.COD_CENTRO ? `${c.COD_CENTRO} - ${c.DESCRICAO}` : c.DESCRICAO;
                let selected = c.ID == idCentroAtual ? 'selected' : '';
                options += `<option value="${c.ID}" ${selected}>${label}</option>`;
            });
        }
        $('#centroApoioSelect').html(options);
    });
}

function carregarDiasApoio(vinculoId) {
    $.get('/comissao-api-vinculo-datas?vinculo_id=' + vinculoId, function(resp) {
        console.log('Resposta datas apoio:', resp);
        let html = '';
        if (resp.success && resp.data && resp.data.length > 0) {
            resp.data.forEach(function(d) {
                // Usar DATA_FORMATADA se disponível, senão formatar DATA
                let dataFormatada = d.DATA_FORMATADA || formatarData(d.DATA) || '-';
                let centroApoioNome = d.CENTRO_APOIO_DESCRICAO 
                    ? (d.CENTRO_APOIO_COD ? d.CENTRO_APOIO_COD + ' - ' : '') + d.CENTRO_APOIO_DESCRICAO 
                    : 'Mesmo centro do vínculo';
                let tipoCalculo = d.TIPO_CALCULO === 'M' ? '<span class="badge bg-info">Média</span>' : '<span class="badge bg-primary">Total</span>';
                html += '<tr>';
                html += '<td>' + dataFormatada + '</td>';
                html += '<td>' + centroApoioNome + '</td>';
                html += '<td>' + tipoCalculo + '</td>';
                html += '<td><button class="btn btn-sm btn-danger" onclick="removerDataApoio(' + d.ID_VINCULO_DATA + ', ' + vinculoId + ')" title="Remover"><i class="bi bi-trash"></i></button></td>';
                html += '</tr>';
            });
        } else {
            html = '<tr><td colspan="4" class="text-center text-muted">Nenhuma data configurada</td></tr>';
        }
        $('#tabelaDiasApoioBody').html(html);
    }).fail(function(xhr) {
        console.error('Erro ao carregar dias de apoio:', xhr.responseText);
        $('#tabelaDiasApoioBody').html('<tr><td colspan="4" class="text-center text-danger">Erro ao carregar datas</td></tr>');
    });
}

function formatarData(dataStr) {
    if (!dataStr) return '-';
    // Formatar YYYY-MM-DD para DD/MM/YYYY
    let partes = dataStr.split('-');
    if (partes.length === 3) {
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }
    return dataStr;
}

function adicionarDataApoio() {
    const vinculoId = $('#diasApoioVinculoId').val();
    const dataInicio = $('#novaDataApoio').val();
    const dataFim = $('#novaDataApoioFim').val();
    const centroApoioId = $('#centroApoioSelect').val() || centroApoioAtual;
    const tipoCalculo = $('#tipoCalculoApoio').val() || 'M';
    
    if (!dataInicio) {
        alert('Selecione a data inicial!');
        return;
    }
    
    // Se dataFim preenchida, enviar range de datas
    let datas = [];
    if (dataFim && dataFim >= dataInicio) {
        let dtAtual = new Date(dataInicio + 'T00:00:00');
        let dtFim = new Date(dataFim + 'T00:00:00');
        while (dtAtual <= dtFim) {
            datas.push(dtAtual.toISOString().split('T')[0]);
            dtAtual.setDate(dtAtual.getDate() + 1);
        }
    } else {
        datas = [dataInicio];
    }
    
    $.ajax({
        url: '/comissao-api-vinculo-datas',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            vinculo_id: vinculoId,
            datas: datas,
            centro_apoio_id: centroApoioId,
            tipo_calculo: tipoCalculo
        }),
        success: function(resp) {
            if (resp.success) {
                $('#novaDataApoio').val('');
                $('#novaDataApoioFim').val('');
                carregarDiasApoio(vinculoId);
            } else {
                alert(resp.error || 'Erro ao adicionar data de apoio!');
            }
        },
        error: function(xhr) {
            let msg = 'Erro ao adicionar data de apoio!';
            try {
                let resp = JSON.parse(xhr.responseText);
                if (resp.error) msg = resp.error;
            } catch(e) {}
            alert(msg);
        }
    });
}

function removerDataApoio(idVinculoData, vinculoId) {
    if (!confirm('Deseja remover esta data de apoio?')) return;
    
    $.ajax({
        url: '/comissao-api-vinculo-data',
        method: 'DELETE',
        contentType: 'application/json',
        data: JSON.stringify({ id: idVinculoData }),
        success: function(resp) {
            if (resp.success) {
                carregarDiasApoio(vinculoId);
            } else {
                alert(resp.error || 'Erro ao remover data');
            }
        },
        error: function() { alert('Erro ao remover data de apoio'); }
    });
}

// Exporta a listagem atual de vínculos para um arquivo Excel (.xlsx)
function exportarVinculosExcel() {
    if (typeof XLSX === 'undefined') {
        alert('Biblioteca de exportação Excel não carregou. Recarregue a página.');
        return;
    }
    if (!vinculosListaAtual || vinculosListaAtual.length === 0) {
        alert('Não há dados para exportar. Aplique os filtros e tente novamente.');
        return;
    }

    const dados = vinculosListaAtual.map(function(v) {
        const tipoVinculo = v.TIPO_VINCULO === 'A' ? 'Apoio' : 'Normal';
        const recurso = v.ID_RECURSO
            ? (v.COD_MAQUINA ? v.COD_MAQUINA + ' - ' + v.RECURSO_DESCRICAO : v.RECURSO_DESCRICAO)
            : '';
        const centro = v.COD_CENTRO ? v.COD_CENTRO + ' - ' + v.CENTRO_DESCRICAO : (v.CENTRO_DESCRICAO || '');
        const funcionario = v.COD_FUNC ? v.COD_FUNC + ' - ' + v.FUNCIONARIO_NOME : (v.FUNCIONARIO_NOME || '');
        return {
            'ID': v.ID_VINCULO,
            'Empresa': v.ID_EMPR || '',
            'Cód. Funcionário': v.COD_FUNC || '',
            'Funcionário': v.FUNCIONARIO_NOME || '',
            'Tipo': tipoVinculo,
            'Cód. Recurso': v.COD_MAQUINA || '',
            'Recurso': v.RECURSO_DESCRICAO || '',
            'Cód. Centro': v.COD_CENTRO || '',
            'Centro de Trabalho': v.CENTRO_DESCRICAO || '',
            'Cód. Alocação': v.COD_CC || '',
            'Alocação': v.CC_DESCRICAO || '',
            'Status': v.ATIVO === 'S' ? 'Ativo' : 'Inativo'
        };
    });

    const ws = XLSX.utils.json_to_sheet(dados);
    // Ajuste de largura aproximada das colunas
    ws['!cols'] = [
        { wch: 6 },  { wch: 8 },  { wch: 14 }, { wch: 35 }, { wch: 10 },
        { wch: 12 }, { wch: 30 }, { wch: 12 }, { wch: 30 },
        { wch: 10 }, { wch: 30 }, { wch: 10 }
    ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Vínculos');

    const hoje = new Date();
    const stamp = hoje.getFullYear() + String(hoje.getMonth() + 1).padStart(2, '0') + String(hoje.getDate()).padStart(2, '0')
        + '_' + String(hoje.getHours()).padStart(2, '0') + String(hoje.getMinutes()).padStart(2, '0');
    XLSX.writeFile(wb, 'vinculos_' + stamp + '.xlsx');
}
