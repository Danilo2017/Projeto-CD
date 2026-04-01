// JS para tela de vínculo centro/recurso/funcionário
$(document).ready(function() {
    carregarFuncionarios();
    carregarRecursos();
    carregarCentros();
    carregarVinculos();
});

function carregarFuncionarios() {
    $.get('/comissao-api-funcionarios', function(res) {
        let options = '<option value="">Selecione</option>';
        if (res && res.data) {
            res.data.forEach(f => {
                let label = f.COD_FUNC ? `${f.COD_FUNC} - ${f.NOME}` : f.NOME;
                options += `<option value="${f.ID}">${label}</option>`;
            });
        }
        $('#funcionario_id').html(options);
    });
    // Ativa select2 para busca dinâmica no filtro
    if ($.fn.select2) {
        $('#filtroFuncionario').select2({
            placeholder: 'Digite para buscar',
            minimumInputLength: 2,
            ajax: {
                url: '/comissao-api-funcionarios',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { busca: params.term };
                },
                processResults: function (data) {
                    return {
                        results: (data.data || []).map(f => ({
                            id: f.ID,
                            text: (f.COD_FUNC ? `${f.COD_FUNC} - ` : '') + f.NOME
                        }))
                    };
                },
                cache: true
            },
            allowClear: true
        });
    }
}

function carregarRecursos() {
    $.get('/comissao-api-recursos', function(res) {
        let options = '<option value="">Selecione</option>';
        if (res && res.data) {
            res.data.forEach(r => {
                let label = r.COD_MAQUINA ? `${r.COD_MAQUINA} - ${r.DESCRICAO}` : r.DESCRICAO;
                options += `<option value="${r.ID}">${label}</option>`;
            });
        }
        $('#recurso_id').html(options);
        $('#filtroRecurso').html('<option value="">Todos</option>' + options);
    });
}

function carregarCentros() {
    $.get('/comissao-api-centros', function(res) {
        let options = '<option value="">Selecione</option>';
        if (res && res.data) {
            res.data.forEach(c => {
                let label = c.COD_CENTRO ? `${c.COD_CENTRO} - ${c.DESCRICAO}` : c.DESCRICAO;
                options += `<option value="${c.ID}">${label}</option>`;
            });
        }
        $('#centro_id').html(options);
        $('#filtroCentro').html('<option value="">Todos</option>' + options);
    });
}

function novoVinculo() {
    $('#formVinculo')[0].reset();
    $('#vinculoId').val('');
    $('#tipo_vinculo').val('N');
    toggleRecursoObrigatorio();
    $('#modalVinculoTitulo').html('<i class="bi bi-link-45deg"></i> Novo Vínculo');
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
    const vinculoId = $('#vinculoId').val();
    const funcionario_id = $('#funcionario_id').val();
    const recurso_id = $('#recurso_id').val();
    const centro_id = $('#centro_id').val();
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
    
    const isEdit = vinculoId && vinculoId !== '';
    const payload = { funcionario_id, recurso_id: recurso_id || null, centro_id, tipo_vinculo };
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
        }
    });
}

function editarVinculo(id, funcionarioId, recursoId, centroId, tipoVinculo) {
    $('#vinculoId').val(id);
    $('#funcionario_id').val(funcionarioId);
    $('#recurso_id').val(recursoId);
    $('#centro_id').val(centroId);
    $('#tipo_vinculo').val(tipoVinculo || 'N');
    toggleRecursoObrigatorio();
    $('#modalVinculoTitulo').html('<i class="bi bi-pencil"></i> Editar Vínculo');
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
        if (resp.success && resp.data && resp.data.length > 0) {
            resp.data.forEach(function(v) {
                let funcLabel = v.COD_FUNC ? v.COD_FUNC + ' - ' + v.FUNCIONARIO_NOME : v.FUNCIONARIO_NOME;
                let recLabel = v.ID_RECURSO ? (v.COD_MAQUINA ? v.COD_MAQUINA + ' - ' + v.RECURSO_DESCRICAO : v.RECURSO_DESCRICAO) : '<em class="text-muted">-</em>';
                let centroLabel = v.COD_CENTRO ? v.COD_CENTRO + ' - ' + v.CENTRO_DESCRICAO : v.CENTRO_DESCRICAO;
                let tipoVinculo = v.TIPO_VINCULO || 'N';
                let tipoBadge = tipoVinculo === 'A' 
                    ? '<span class="badge bg-info">Apoio</span>'
                    : '<span class="badge bg-secondary">Normal</span>';
                let statusBadge = v.ATIVO === 'S'
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-danger">Inativo</span>';
                let acoes = '<div class="d-flex gap-1 flex-wrap">';
                acoes += '<button class="btn btn-sm btn-primary" onclick="editarVinculo(' + v.ID_VINCULO + ', ' + v.ID_FUNCIONARIO + ', ' + (v.ID_RECURSO || 'null') + ', ' + v.ID_CENTRO_TRAB + ', \'' + tipoVinculo + '\')" title="Editar"><i class="bi bi-pencil"></i></button>';
                acoes += '<button class="btn btn-sm btn-danger" onclick="excluirVinculo(' + v.ID_VINCULO + ')" title="Excluir"><i class="bi bi-trash"></i></button>';
                if (v.ATIVO === 'S') {
                    acoes += '<button class="btn btn-sm btn-warning" onclick="toggleVinculo(' + v.ID_VINCULO + ', \'N\')" title="Inativar"><i class="bi bi-x-circle"></i></button>';
                } else {
                    acoes += '<button class="btn btn-sm btn-success" onclick="toggleVinculo(' + v.ID_VINCULO + ', \'S\')" title="Ativar"><i class="bi bi-check-circle"></i></button>';
                }
                // Botão de dias de apoio apenas para vínculos tipo Normal
                if (tipoVinculo === 'N') {
                    let funcNome = funcLabel.replace(/'/g, "\\'");
                    let centroNome = centroLabel.replace(/'/g, "\\'");
                    acoes += '<button class="btn btn-sm btn-info" onclick="abrirDiasApoio(' + v.ID_VINCULO + ', \'' + funcNome + '\', \'' + centroNome + '\', ' + v.ID_CENTRO_TRAB + ')" title="Configurar Dias de Apoio"><i class="bi bi-calendar-event"></i></button>';
                }
                acoes += '</div>';
                html += '<tr>';
                html += '<td>' + v.ID_VINCULO + '</td>';
                html += '<td>' + (v.ID_EMPR || '') + '</td>';
                html += '<td>' + (funcLabel || '') + '</td>';
                html += '<td>' + tipoBadge + '</td>';
                html += '<td>' + (recLabel || '') + '</td>';
                html += '<td>' + (centroLabel || '') + '</td>';
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
            pageLength: 10,
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
            pageLength: 10,
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
                html += '<tr>';
                html += '<td>' + dataFormatada + '</td>';
                html += '<td>' + centroApoioNome + '</td>';
                html += '<td><button class="btn btn-sm btn-danger" onclick="removerDataApoio(' + d.ID_VINCULO_DATA + ', ' + vinculoId + ')" title="Remover"><i class="bi bi-trash"></i></button></td>';
                html += '</tr>';
            });
        } else {
            html = '<tr><td colspan="3" class="text-center text-muted">Nenhuma data configurada</td></tr>';
        }
        $('#tabelaDiasApoioBody').html(html);
    }).fail(function(xhr) {
        console.error('Erro ao carregar dias de apoio:', xhr.responseText);
        $('#tabelaDiasApoioBody').html('<tr><td colspan="3" class="text-center text-danger">Erro ao carregar datas</td></tr>');
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
            centro_apoio_id: centroApoioId
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
