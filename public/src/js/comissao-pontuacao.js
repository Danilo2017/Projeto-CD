/**
 * Comissão Pontuação - JavaScript
 */

let dataTable = null;
let select2Produto = null;

document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados dos selects
    carregarCentrosTrabalho();
    inicializarSelect2Produto();
    
    // Carregar dados iniciais
    carregarPontuacoes();
    
    // Filtrar ao pressionar Enter no campo de busca
    document.getElementById('filtroItem')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            carregarPontuacoes();
        }
    });
    
    // Reinicializar Select2 quando modal abrir
    $('#modalPontuacao').on('shown.bs.modal', function() {
        if (select2Produto) {
            select2Produto.focus();
        }
    });
    
    // Limpar Select2 quando modal fechar
    $('#modalPontuacao').on('hidden.bs.modal', function() {
        limparFormulario();
    });
});

/**
 * Inicializa Select2 para busca de produtos com AJAX
 */
function inicializarSelect2Produto() {
    select2Produto = $('#itemprId').select2({
        theme: 'bootstrap-5',
        language: 'pt-BR',
        placeholder: 'Digite o código ou descrição do produto...',
        allowClear: true,
        minimumInputLength: 2,
        dropdownParent: $('#modalPontuacao'),
        ajax: {
            url: '/comissao-api-produtos-busca',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    term: params.term,
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                
                // Garantir que os dados extras sejam mantidos
                // id = TITENS.ID (ITEM_ID na tabela)
                // id_itempr = TITENS_EMPR.ID (ID_ITEMPR na tabela)
                // id_mascara = TMASC_ITEM.ID
                const results = data.results.map(function(item) {
                    return {
                        id: item.id,                  // TITENS.ID = ITEM_ID
                        text: item.text,
                        cod_item: item.cod_item,
                        descricao: item.descricao,
                        mascara: item.mascara,
                        id_mascara: item.id_mascara,  // TMASC_ITEM.ID
                        id_itempr: item.id_itempr,    // TITENS_EMPR.ID
                        id_empresa: item.id_empresa   // TEMPRESAS.ID
                    };
                });
                
                return {
                    results: results,
                    pagination: {
                        more: data.pagination && data.pagination.more
                    }
                };
            },
            cache: true
        },
        templateResult: formatarProduto,
        templateSelection: formatarProdutoSelecionado
    });
    
    // Evento para armazenar dados extras ao selecionar
    $('#itemprId').on('select2:select', function(e) {
        const data = e.params.data;
        console.log('Produto selecionado:', data);
        
        // Guardar dados extras no elemento
        $(this).data('selected-data', data);
        
        // Preencher campos hidden
        // id = TITENS.ID que vai para ITEM_ID na tabela
        // id_itempr = TITENS_EMPR.ID que vai para ID_ITEMPR na tabela
        $('#hiddenItemId').val(data.id || '');
        $('#hiddenItemprId').val(data.id_itempr || '');
        $('#hiddenMascaraId').val(data.id_mascara || '');
        
        console.log('Hidden fields:', {
            item_id: $('#hiddenItemId').val(),
            itempr_id: $('#hiddenItemprId').val(),
            mascara_id: $('#hiddenMascaraId').val()
        });
    });
    
    // Limpar dados ao limpar seleção
    $('#itemprId').on('select2:clear', function() {
        $(this).data('selected-data', null);
        $('#hiddenItemId').val('');
        $('#hiddenItemprId').val('');
        $('#hiddenMascaraId').val('');
    });
}

/**
 * Formata a exibição do produto na lista de opções
 */
function formatarProduto(produto) {
    if (produto.loading) {
        return $('<span>Buscando...</span>');
    }
    
    if (!produto.id) {
        return produto.text;
    }
    
    const $container = $('<div class="select2-result-produto">');
    const codigo = produto.cod_item || '';
    const descricao = produto.descricao || '';
    const mascara = produto.mascara || '';
    const idMascara = produto.id_mascara || '';
    
    let html = `<strong>${codigo}</strong> - ${idMascara} - ${descricao}`;
    if (mascara) {
        html += `<br><small class="text-muted"><i class="bi bi-tag"></i> ${mascara}</small>`;
    }
    
    $container.html(html);
    return $container;
}

/**
 * Formata a exibição do produto selecionado
 */
function formatarProdutoSelecionado(produto) {
    if (!produto.id) {
        return produto.text;
    }
    
    const codigo = produto.cod_item || '';
    const descricao = produto.descricao || '';
    
    return codigo ? `${codigo} - ${descricao}` : produto.text;
}

/**
 * Carrega centros de trabalho para o select
 */
function carregarCentrosTrabalho() {
    fetch('/comissao-api-centros')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const selects = ['filtroCentro', 'centroTrabId'];
                selects.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        const primeiraOpcao = id.startsWith('filtro') ? 'Todos' : 'Selecione';
                        select.innerHTML = `<option value="">${primeiraOpcao}</option>`;
                        data.data.forEach(centro => {
                            select.innerHTML += `<option value="${centro.ID}">${centro.COD_CENTRO} - ${centro.DESCRICAO}</option>`;
                        });
                    }
                });
            }
        })
        .catch(error => console.error('Erro ao carregar centros:', error));
}

/**
 * Carrega produtos para o select
 * A API já filtra pela empresa da sessão automaticamente
 */
function carregarProdutos() {
    fetch('/comissao-api-produtos', {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Pode ser 'produtoId' ou 'itemprId' dependendo do HTML
                const selectIds = ['produtoId', 'itemprId'];
                selectIds.forEach(id => {
                    const select = document.getElementById(id);
                    if (select) {
                        select.innerHTML = '<option value="">Selecione um produto</option>';
                        data.data.forEach(produto => {
                            const codigo = produto.COD_ITEM || produto.CODIGO || '';
                            const descricao = produto.DESCRICAO || produto.DESC_TECNICA || '';
                            const mascara = produto.MASCARA || '';
                            const idItem = produto.ID_ITEM || '';
                            const idItempr = produto.ITEMPR_ID || '';
                            const idMascara = produto.ID_MASCARA || '';
                            
                            // Formato: COD_ITEM - ID - DESCRIÇÃO - MÁSCARA
                            let texto = `${codigo} - ${idItempr} - ${descricao}`;
                            if (mascara) {
                                texto += ` - ${mascara}`;
                            }
                            
                            // Guardar todos os IDs como data attributes
                            const option = document.createElement('option');
                            option.value = idItem;
                            option.textContent = texto;
                            option.dataset.itemprId = idItempr;
                            option.dataset.mascaraId = idMascara;
                            option.dataset.mascara = mascara;
                            select.appendChild(option);
                        });
                    }
                });
                console.log('Produtos carregados:', data.total);
            } else {
                console.error('Erro ao carregar produtos:', data.error);
            }
        })
        .catch(error => console.error('Erro ao carregar produtos:', error));
}

/**
 * Carrega pontuações da API
 */
function carregarPontuacoes() {
    const filtros = {
        centroTrabId: document.getElementById('filtroCentro')?.value || '',
        incluirInativas: document.getElementById('incluirInativas')?.checked || false,
        busca: document.getElementById('filtroItem')?.value?.trim() || ''
    };
    
    const params = new URLSearchParams();
    Object.keys(filtros).forEach(key => {
        if (filtros[key]) params.append(key, filtros[key]);
    });
    
    fetch(`/comissao-api-pontuacao?${params.toString()}`, {
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                renderizarTabela(data.data);
            } else {
                exibirMensagemErro(data.error || data.message || 'Erro ao carregar pontuações');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar pontuações:', error);
            exibirMensagemErro('Erro ao carregar pontuações');
        });
}

/**
 * Renderiza a tabela de pontuações
 */
function renderizarTabela(dados) {
    // Destruir DataTable existente antes de modificar o tbody
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
    
    // Também destruir se existir no DOM mas não na variável
    if ($.fn.DataTable.isDataTable('#tabelaPontuacoes')) {
        $('#tabelaPontuacoes').DataTable().destroy();
    }
    
    const tbody = document.getElementById('tabelaPontuacoesBody');
    
    if (!dados || dados.length === 0) {
        // Mensagem centralizada em toda a tabela (11 colunas)
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    Nenhuma pontuação cadastrada para esta empresa
                </td>
            </tr>
        `;
        // Não inicializar DataTable quando não há dados
        return;
    }
    
    let html = '';
    dados.forEach(item => {
        const statusClass = item.ATIVO === 'S' ? 'status-ativo' : 'status-inativo';
        const statusTexto = item.ATIVO === 'S' ? 'Ativo' : 'Inativo';
        
        // Campos retornados pelo model
        const idPontuacao = item.ID_PONTUACAO || item.ID || 0;
        const codEmpresa = item.COD_EMP || '-';
        const codigoItem = item.COD_ITEM || '-';
        const descricaoItem = item.DESC_ITEM || '-';
        const idMascara = item.ID_MASCARA || '-';
        const mascara = item.MASCARA || '-';
        const codCentro = item.COD_CENTRO || '';
        const descCentro = item.DESC_CENTRO || '';
        const centroDescricao = codCentro && descCentro ? `${codCentro} - ${descCentro}` : (descCentro || 'Todos');
        const pontosUp = item.PONTOS_UP || 0;
        const vigenciaIni = item.DT_VIGENCIA_INI || '-';
        const vigenciaFim = item.DT_VIGENCIA_FIM || '-';
        
        html += `
            <tr>
                <td>${idPontuacao}</td>
                <td>${codEmpresa}</td>
                <td>${codigoItem}</td>
                <td>${descricaoItem}</td>
                <td>${idMascara}</td>
                <td>${mascara}</td>
                <td>${centroDescricao}</td>
                <td class="text-end">${formatarNumero(pontosUp, 4)}</td>
                <td>${formatarData(vigenciaIni)}</td>
                <td>${formatarData(vigenciaFim)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" onclick="editarPontuacao(${idPontuacao})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="excluirPontuacao(${idPontuacao})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    initDataTable();
}

/**
 * Inicializa DataTable
 */
function initDataTable() {
    // Destruir se existir na variável
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
    
    // Também destruir se existir no DOM mas não na variável
    if ($.fn.DataTable.isDataTable('#tabelaPontuacoes')) {
        $('#tabelaPontuacoes').DataTable().destroy();
    }
    
    dataTable = $('#tabelaPontuacoes').DataTable({
        language: {
            emptyTable: "Nenhum registro encontrado",
            info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 até 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros no total)",
            lengthMenu: "Exibir _MENU_ resultados por página",
            loadingRecords: "Carregando...",
            processing: "Processando...",
            search: "Pesquisar:",
            zeroRecords: "Nenhum registro encontrado",
            paginate: {
                first: "Primeiro",
                last: "Último",
                next: "Próximo",
                previous: "Anterior"
            }
        },
        lengthChange: false,
        pageLength: 20,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [9] } // Coluna de ações (10ª coluna, index 9)
        ]
    });
}

/**
 * Abre modal para nova pontuação
 */
function novaPontuacao() {
    document.getElementById('modalPontuacaoTitulo').innerHTML = '<i class="bi bi-plus-circle"></i> Nova Pontuação';
    document.getElementById('formPontuacao').reset();
    document.getElementById('pontuacaoId').value = '';
    
    // Definir data de vigência como hoje
    document.getElementById('dtVigenciaIni').value = new Date().toISOString().split('T')[0];
}

/**
 * Carrega dados para edição
 */
function editarPontuacao(id) {
    fetch(`/comissao-api-pontuacao?id=${id}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const item = data.data;
                document.getElementById('modalPontuacaoTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Pontuação';
                document.getElementById('pontuacaoId').value = item.ID_PONTUACAO;
                
                // Preencher campos hidden com os IDs
                document.getElementById('hiddenItemId').value = item.ITEM_ID || '';
                document.getElementById('hiddenItemprId').value = item.ID_ITEMPR || '';
                document.getElementById('hiddenMascaraId').value = item.ID_MASCARA || '';
                
                // Criar opção no Select2 com os dados do produto
                const $select = $('#itemprId');
                const textoOption = `${item.COD_ITEM || ''} - ${item.DESC_ITEM || ''}`;
                const newOption = new Option(textoOption, item.ITEM_ID, true, true);
                
                // Adicionar dados extras como propriedades
                $(newOption).data('id_itempr', item.ID_ITEMPR);
                $(newOption).data('id_mascara', item.ID_MASCARA);
                $(newOption).data('cod_item', item.COD_ITEM);
                $(newOption).data('descricao', item.DESC_ITEM);
                $(newOption).data('mascara', item.MASCARA);
                
                $select.append(newOption).trigger('change');
                
                const selectCentro = document.getElementById('centroTrabId');
                if (selectCentro) {
                    selectCentro.value = item.ID_CENTRO_TRAB || '';
                }
                
                document.getElementById('pontuacaoUp').value = item.PONTOS_UP;
                document.getElementById('dtVigenciaIni').value = item.DT_VIGENCIA_INI || '';
                document.getElementById('dtVigenciaFim').value = item.DT_VIGENCIA_FIM || '';
                
                const modal = new bootstrap.Modal(document.getElementById('modalPontuacao'));
                modal.show();
            } else {
                exibirMensagemErro('Pontuação não encontrada');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar pontuação:', error);
            exibirMensagemErro('Erro ao carregar pontuação');
        });
}

/**
 * Salva pontuação (criar ou atualizar)
 */
function salvarPontuacao() {
    const id = document.getElementById('pontuacaoId').value;
    
    // Obter dados do Select2 - primeiro tenta dados armazenados, depois fallback
    const $select = $('#itemprId');
    const storedData = $select.data('selected-data');
    const selectedData = storedData || $select.select2('data')[0];
    
    console.log('Stored Data:', storedData);
    console.log('Select2 Data:', $select.select2('data')[0]);
    console.log('Selected Data (usado):', selectedData);
    
    // Obter os IDs - primeiro dos campos hidden, depois dos dados do Select2
    // ITEM_ID = TITENS.ID (FK para tabela TITENS - vem no hiddenItemId)
    // ID_ITEMPR = TITENS_EMPR.ID (vem no hiddenItemprId)
    // ID_MASCARA = TMASC_ITEM.ID (vem no hiddenMascaraId)
    const hiddenItemId = $('#hiddenItemId').val();
    const hiddenItemprId = $('#hiddenItemprId').val();
    const hiddenMascaraId = $('#hiddenMascaraId').val();
    
    console.log('Hidden Fields:', { hiddenItemId, hiddenItemprId, hiddenMascaraId });
    
    // Usar campos hidden se disponíveis, senão usar dados do Select2
    const itemId = hiddenItemId || selectedData?.id || '';
    const itemprId = hiddenItemprId || selectedData?.id_itempr || '';
    const mascaraId = hiddenMascaraId || selectedData?.id_mascara || '';
    
    console.log('IDs extraídos:', { itemId, itemprId, mascaraId });
    
    const dados = {
        item_id: itemId,          // TITENS.ID vai para ITEM_ID (FK)
        itempr_id: itemprId,      // TITENS_EMPR.ID vai para ID_ITEMPR
        mascara_id: mascaraId,    // TMASC_ITEM.ID vai para ID_MASCARA
        centro_trab_id: document.getElementById('centroTrabId')?.value || '',
        pontuacao_up: document.getElementById('pontuacaoUp')?.value || '',
        dt_vigencia_ini: document.getElementById('dtVigenciaIni')?.value || '',
        dt_vigencia_fim: document.getElementById('dtVigenciaFim')?.value || ''
    };
    
    console.log('Dados a enviar:', dados);
    
    // Validação
    if (!dados.item_id) {
        exibirMensagemErro('Selecione um produto');
        return;
    }
    if (!dados.pontuacao_up || dados.pontuacao_up <= 0) {
        exibirMensagemErro('Informe os pontos por UP');
        return;
    }
    if (!dados.dt_vigencia_ini) {
        exibirMensagemErro('Informe a data de vigência inicial');
        return;
    }
    
    const metodo = id ? 'PUT' : 'POST';
    if (id) dados.id = id;
    
    fetch('/comissao-api-pontuacao', {
        method: metodo,
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalPontuacao')).hide();
            carregarPontuacoes();
            exibirMensagemSucesso(id ? 'Pontuação atualizada com sucesso!' : 'Pontuação cadastrada com sucesso!');
        } else {
            exibirMensagemErro(data.error || data.message || 'Erro ao salvar pontuação');
        }
    })
    .catch(error => {
        console.error('Erro ao salvar:', error);
        exibirMensagemErro('Erro ao salvar pontuação');
    });
}

/**
 * Exclui uma pontuação
 */
function excluirPontuacao(id) {
    if (!confirm('Deseja realmente excluir esta pontuação?')) return;
    
    fetch('/comissao-api-pontuacao', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarPontuacoes();
            exibirMensagemSucesso('Pontuação excluída com sucesso!');
        } else {
            exibirMensagemErro(data.message || 'Erro ao excluir pontuação');
        }
    })
    .catch(error => {
        console.error('Erro ao excluir:', error);
        exibirMensagemErro('Erro ao excluir pontuação');
    });
}

/**
 * Formata número
 */
function formatarNumero(valor, casasDecimais = 2) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: casasDecimais,
        maximumFractionDigits: casasDecimais
    }).format(valor || 0);
}

/**
 * Formata data
 */
function formatarData(data) {
    if (!data) return '-';
    // Adiciona T12:00:00 para evitar problemas de timezone
    if (typeof data === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data)) {
        data = data + 'T12:00:00';
    }
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

/**
 * Exibe mensagem de erro
 */
function exibirMensagemErro(mensagem) {
    alert('❌ ' + mensagem);
    // TODO: Implementar sistema de toast
}

/**
 * Exibe mensagem de sucesso
 */
function exibirMensagemSucesso(mensagem) {
    alert('✅ ' + mensagem);
    // TODO: Implementar sistema de toast
}
/**
 * Limpa o formulário do modal
 */
function limparFormulario() {
    document.getElementById('pontuacaoId').value = '';
    document.getElementById('hiddenItemId').value = '';
    document.getElementById('hiddenItemprId').value = '';
    document.getElementById('hiddenMascaraId').value = '';
    document.getElementById('pontuacaoUp').value = '';
    document.getElementById('dtVigenciaIni').value = '';
    document.getElementById('dtVigenciaFim').value = '';
    document.getElementById('centroTrabId').value = '';
    document.getElementById('modalPontuacaoTitulo').innerHTML = '<i class="bi bi-tag-fill"></i> Nova Pontuação UP';
    
    // Limpar Select2
    $('#itemprId').val(null).trigger('change');
    $('#itemprId').data('selected-data', null);
}

/**
 * Prepara o modal para nova pontuação
 */
function novaPontuacao() {
    limparFormulario();
    const modal = new bootstrap.Modal(document.getElementById('modalPontuacao'));
    modal.show();
}

// ==================== EXPORTAÇÃO EXCEL ====================

/**
 * Exporta os dados da tabela de pontuações para Excel (CSV)
 */
function exportarExcel() {
    exportarTabelaExcel('tabelaPontuacoes', 'Pontuacao_UEP');
}

function exportarTabelaExcel(tabelaId, nomeArquivo) {
    const tabela = document.getElementById(tabelaId);
    if (!tabela) { alert('Tabela não encontrada'); return; }

    let csvContent = '\uFEFF';

    // Headers (excluir coluna Ações)
    const headers = [];
    const thList = tabela.querySelectorAll('thead th');
    const totalCols = thList.length;
    thList.forEach((th, idx) => {
        if (idx < totalCols - 1) { // ignora última coluna (Ações)
            let texto = th.innerText.replace(/"/g, '""');
            headers.push('"' + texto + '"');
        }
    });
    csvContent += headers.join(';') + '\n';

    // Rows - usar DataTable API para pegar TODAS as linhas (incluindo paginadas)
    try {
        const dtInstance = $(tabela).DataTable();
        if (dtInstance && dtInstance.rows().count() > 0) {
            dtInstance.rows({ search: 'applied' }).every(function() {
                const row = this.node();
                const cols = [];
                const tdList = row.querySelectorAll('td');
                tdList.forEach((td, idx) => {
                    if (idx < totalCols - 1) {
                        let texto = td.innerText.replace(/"/g, '""');
                        cols.push('"' + texto + '"');
                    }
                });
                csvContent += cols.join(';') + '\n';
            });
        }
    } catch(e) {
        // Fallback DOM
        tabela.querySelectorAll('tbody tr').forEach(tr => {
            const cols = [];
            const tdList = tr.querySelectorAll('td');
            tdList.forEach((td, idx) => {
                if (idx < totalCols - 1) {
                    let texto = td.innerText.replace(/"/g, '""');
                    cols.push('"' + texto + '"');
                }
            });
            csvContent += cols.join(';') + '\n';
        });
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = nomeArquivo + '_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

// ==================== IMPORTAÇÃO ====================

let dadosImportacao = [];

// Evento de seleção de arquivo
document.addEventListener('DOMContentLoaded', function() {
    const inputArquivo = document.getElementById('arquivoImportacao');
    if (inputArquivo) {
        inputArquivo.addEventListener('change', function() {
            const arquivo = this.files[0];
            if (arquivo) {
                lerArquivoCSV(arquivo);
            }
        });
    }
});

/**
 * Lê e parseia o arquivo CSV
 */
function lerArquivoCSV(arquivo) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const conteudo = e.target.result;
        const linhas = conteudo.split(/\r?\n/).filter(l => l.trim() !== '');
        
        if (linhas.length < 2) {
            alert('Arquivo vazio ou sem dados');
            return;
        }
        
        // Detectar separador (tab do Excel, ponto-e-vírgula ou vírgula)
        const primeiraLinha = linhas[0];
        let separador = ',';
        if (primeiraLinha.includes('\t')) {
            separador = '\t';
        } else if (primeiraLinha.includes(';')) {
            separador = ';';
        }
        
        // Parse cabeçalho
        const colunas = primeiraLinha.split(separador).map(c => c.trim().replace(/^"|"$/g, '').toUpperCase());
        
        // Mapear colunas esperadas
        const colunasEsperadas = ['COD_ITEM', 'ID_MASCARA', 'COD_CENTRO', 'PONTOS_UP', 'DT_VIGENCIA_INI', 'DT_VIGENCIA_FIM'];
        
        // Parse dados
        dadosImportacao = [];
        for (let i = 1; i < linhas.length; i++) {
            const valores = linhas[i].split(separador).map(v => v.trim().replace(/^"|"$/g, ''));
            const registro = {};
            colunas.forEach((col, idx) => {
                // Mapear pela posição ou pelo nome da coluna
                const nomeCol = colunasEsperadas[idx] || col;
                registro[nomeCol] = valores[idx] || '';
            });
            dadosImportacao.push(registro);
        }
        
        // Mostrar preview
        mostrarPreview(colunasEsperadas, dadosImportacao);
    };
    reader.readAsText(arquivo, 'UTF-8');
}

/**
 * Mostrar preview dos dados
 */
function mostrarPreview(colunas, dados) {
    const preview = document.getElementById('importacaoPreview');
    const tabela = document.getElementById('tabelaPreview');
    
    // Cabeçalho
    let thHtml = '<tr>';
    colunas.forEach(col => { thHtml += '<th>' + col + '</th>'; });
    thHtml += '</tr>';
    tabela.querySelector('thead').innerHTML = thHtml;
    
    // Corpo (max 20 linhas no preview)
    let tbHtml = '';
    const maxPreview = Math.min(dados.length, 20);
    for (let i = 0; i < maxPreview; i++) {
        tbHtml += '<tr>';
        colunas.forEach(col => {
            tbHtml += '<td>' + (dados[i][col] || '') + '</td>';
        });
        tbHtml += '</tr>';
    }
    tabela.querySelector('tbody').innerHTML = tbHtml;
    
    // Resumo
    document.getElementById('importacaoResumo').innerHTML = 
        '<span class="badge bg-primary">' + dados.length + ' registros encontrados</span>' +
        (dados.length > 20 ? ' <small class="text-muted">(mostrando primeiros 20)</small>' : '');
    
    preview.style.display = 'block';
    document.getElementById('btnConfirmarImportacao').disabled = false;
    document.getElementById('importacaoResultado').style.display = 'none';
}

/**
 * Confirmar e enviar importação
 */
function confirmarImportacao() {
    if (dadosImportacao.length === 0) {
        alert('Nenhum dado para importar');
        return;
    }
    
    const btnImportar = document.getElementById('btnConfirmarImportacao');
    btnImportar.disabled = true;
    btnImportar.innerHTML = '<i class="bi bi-hourglass-split"></i> Importando...';
    
    const resultado = document.getElementById('importacaoResultado');
    resultado.style.display = 'block';
    resultado.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Processando ' + dadosImportacao.length + ' registros, aguarde...</div>';
    
    fetch('/comissao-api-pontuacao-importar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ linhas: dadosImportacao })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div class="alert alert-success">';
            html += '<i class="bi bi-check-circle"></i> <strong>Importação concluída!</strong><br>';
            html += 'Novos: <strong>' + data.importados + '</strong>';
            if (data.atualizados > 0) {
                html += ' | Atualizados: <strong>' + data.atualizados + '</strong>';
            }
            html += ' | Total processado: ' + data.total + ' registros';
            html += '</div>';
            
            if (data.erros && data.erros.length > 0) {
                html += '<div class="alert alert-warning" style="max-height: 300px; overflow-y: auto;">';
                html += '<strong><i class="bi bi-exclamation-triangle"></i> Erros encontrados (' + data.erros.length + '):</strong>';
                html += '<table class="table table-sm table-bordered mt-2 mb-0" style="font-size: 0.85em;">';
                html += '<thead><tr><th style="width:60px">#</th><th>Detalhe do Erro</th></tr></thead><tbody>';
                data.erros.forEach(function(erro, i) {
                    html += '<tr><td>' + (i + 1) + '</td><td>' + erro + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }
            
            resultado.innerHTML = html;
            carregarPontuacoes(); // Recarregar tabela
        } else {
            resultado.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Erro: ' + data.error + '</div>';
        }
        
        btnImportar.disabled = false;
        btnImportar.innerHTML = '<i class="bi bi-upload"></i> Importar';
    })
    .catch(error => {
        console.error('Erro na importação:', error);
        resultado.innerHTML = 
            '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Erro na importação: ' + error.message + '</div>';
        btnImportar.disabled = false;
        btnImportar.innerHTML = '<i class="bi bi-upload"></i> Importar';
    });
}

/**
 * Baixar modelo CSV
 */
function baixarModeloCSV() {
    const cabecalho = 'COD_ITEM;ID_MASCARA;COD_CENTRO;PONTOS_UP;DT_VIGENCIA_INI;DT_VIGENCIA_FIM';
    const exemplo = '700027;56062;11.012.1;1;01/01/2026;30/12/2099';
    const conteudo = '\uFEFF' + cabecalho + '\n' + exemplo + '\n';

    const blob = new Blob([conteudo], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'modelo_pontuacao.csv';
    link.click();
}

// ==================== RELAT\u00D3RIO DE ITENS ====================

function gerarRelatorioItens() {
    const codItem   = (document.getElementById('filtroRelCodItem')?.value   || '').trim();
    const idMascara = (document.getElementById('filtroRelIdMascara')?.value || '').trim();

    const status = document.getElementById('relatorioItensStatus');
    const btn    = document.getElementById('btnGerarRelatorio');

    status.style.display = 'block';
    status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="bi bi-hourglass-split"></i> Buscando dados, aguarde...</div>';
    btn.disabled = true;

    const params = new URLSearchParams();
    if (codItem)   params.append('cod_item',   codItem);
    if (idMascara) params.append('id_mascara', idMascara);

    const url = '/comissao-api-relatorio-itens' + (params.toString() ? '?' + params.toString() : '');

    fetch(url)
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) {
                status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + (resp.error || 'Erro ao buscar dados') + '</div>';
                return;
            }
            if (!resp.data || resp.data.length === 0) {
                status.innerHTML = '<div class="alert alert-warning py-2 mb-0">Nenhum item encontrado com os filtros informados.</div>';
                return;
            }
            status.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle"></i> ' + resp.total + ' registros encontrados. Gerando Excel...</div>';
            exportarRelatorioItensExcel(resp.data);
        })
        .catch(() => {
            status.innerHTML = '<div class="alert alert-danger py-2 mb-0">Erro de comunica\u00E7\u00E3o com o servidor.</div>';
        })
        .finally(() => {
            btn.disabled = false;
        });
}

function exportarRelatorioItensExcel(dados) {
    if (typeof XLSX === 'undefined') {
        alert('Biblioteca de exporta\u00E7\u00E3o Excel n\u00E3o carregou. Recarregue a p\u00E1gina.');
        return;
    }

    const colunas = [
        { key: 'EMPR_ID',       label: 'Empresa' },
        { key: 'COD_ITEM',      label: 'C\u00F3digo Item' },
        { key: 'DESC_TECNICA',  label: 'Descri\u00E7\u00E3o' },
        { key: 'MASCARA',       label: 'M\u00E1scara' },
        { key: 'TMASC_ITEM_ID', label: 'ID M\u00E1scara' },
        { key: 'UEP',           label: 'UEP' },
        { key: 'TANQUE',        label: 'Tanque' },
        { key: 'COD_TANQUE',    label: 'C\u00F3d. Tanque' },
    ];

    const rows = dados.map(d => {
        const row = {};
        colunas.forEach(c => {
            let val = d[c.key] ?? '';
            if (c.key === 'UEP' && val !== '') {
                const num = parseFloat(val);
                if (!isNaN(num)) val = num;
            }
            row[c.label] = val;
        });
        return row;
    });

    const ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = [
        {wch:8},{wch:14},{wch:45},{wch:25},{wch:12},{wch:12},{wch:30},{wch:14}
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Itens');

    const stamp = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    XLSX.writeFile(wb, 'relatorio_itens_' + stamp + '.xlsx');
}