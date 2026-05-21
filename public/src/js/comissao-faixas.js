/**
 * Comissão Faixas - JavaScript
 */

let dataTable = null;

document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados dos selects
    carregarCentrosTrabalho();
    
    // Carregar dados iniciais
    carregarFaixas();

    // Impedir que Enter dentro do form do modal dispare salvamento implícito
    const formFaixa = document.getElementById('formFaixa');
    if (formFaixa) {
        formFaixa.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        });
    }
});

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
                        const primeiraOpcao = id.startsWith('filtro') ? 'Todos' : 'Todos os centros';
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
 * Carrega faixas da API
 */
function carregarFaixas() {
    const filtros = {
        centroTrabId: document.getElementById('filtroCentro')?.value || '',
        tipo: document.getElementById('filtroTipo')?.value || '',
        incluirInativas: document.getElementById('incluirInativas')?.checked || false
    };
    
    const params = new URLSearchParams();
    Object.keys(filtros).forEach(key => {
        if (filtros[key]) params.append(key, filtros[key]);
    });
    
    fetch(`/comissao-api-faixas?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarTabela(data.data);
            } else {
                exibirMensagemErro(data.message || 'Erro ao carregar faixas');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar faixas:', error);
            exibirMensagemErro('Erro ao carregar faixas');
        });
}

/**
 * Renderiza a tabela de faixas
 */
function renderizarTabela(dados) {
    // Destruir DataTable existente antes de modificar o tbody
    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
    
    // Também destruir se existir no DOM mas não na variável
    if ($.fn.DataTable.isDataTable('#tabelaFaixas')) {
        $('#tabelaFaixas').DataTable().destroy();
    }
    
    const tbody = document.getElementById('tabelaFaixasBody');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    Nenhuma faixa cadastrada para esta empresa
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
        const tipoClass = item.TIPO === 'P' ? 'tipo-percentual' : 'tipo-quantidade';
        const tipoTexto = item.TIPO === 'P' ? 'Percentual' : 'Quantidade';
        
        // Tipo de funcionário que a faixa atende
        const tipoFunc = item.TIPO_FUNCIONARIO || 'T';
        let tipoFuncTexto = 'Todos';
        let tipoFuncClass = 'bg-secondary';
        if (tipoFunc === 'N') {
            tipoFuncTexto = 'Normal';
            tipoFuncClass = 'bg-primary';
        } else if (tipoFunc === 'A') {
            tipoFuncTexto = 'Apoio';
            tipoFuncClass = 'bg-info';
        }
        
        let valorFormatado = '';
        if (item.TIPO === 'P') {
            valorFormatado = formatarNumero(item.VALOR_COMISSAO, 4) + '%';
        } else {
            valorFormatado = formatarMoeda(item.VALOR_COMISSAO);
        }
        
        const pontoFinal = item.PONTO_FINAL ? formatarNumero(item.PONTO_FINAL, 2) : '∞ (Sem limite)';
        const vigencia = `${formatarData(item.DT_VIGENCIA_INI)}${item.DT_VIGENCIA_FIM ? ' até ' + formatarData(item.DT_VIGENCIA_FIM) : ''}`;
        
        html += `
            <tr>
                <td>${item.ID_FAIXA}</td>
                <td>${item.COD_EMPRESA || '-'}</td>
                <td>${item.DESCRICAO}</td>
                <td><span class="tipo-badge ${tipoClass}">${tipoTexto}</span></td>
                <td><span class="badge ${tipoFuncClass}">${tipoFuncTexto}</span></td>
                <td class="text-end">${formatarNumero(item.PONTO_INICIAL, 2)}</td>
                <td class="text-end">${pontoFinal}</td>
                <td class="text-end"><strong>${valorFormatado}</strong></td>
                <td>${item.CENTRO_DESCRICAO || 'Todos'}</td>
                <td>${vigencia}</td>
                <td><span class="status-badge ${statusClass}">${statusTexto}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" onclick="editarFaixa(${item.ID_FAIXA})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="excluirFaixa(${item.ID_FAIXA})" title="Excluir">
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
    if ($.fn.DataTable.isDataTable('#tabelaFaixas')) {
        $('#tabelaFaixas').DataTable().destroy();
    }
    
    dataTable = $('#tabelaFaixas').DataTable({
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
        lengthChange: true,
        lengthMenu: [[10, 20, 50, 100, -1], [10, 20, 50, 100, 'Todos']],
        pageLength: 50,
        order: [[3, 'asc']], // Ordenar por ponto inicial
        columnDefs: [
            { orderable: false, targets: [9] }
        ]
    });
}

/**
 * Abre modal para nova faixa
 */
function novaFaixa() {
    document.getElementById('modalFaixaTitulo').innerHTML = '<i class="bi bi-layers-fill"></i> Nova Faixa de Comissão';
    document.getElementById('formFaixa').reset();
    document.getElementById('faixaId').value = '';
    
    // Definir data de vigência como hoje
    document.getElementById('dtVigenciaIni').value = new Date().toISOString().split('T')[0];
    
    // Definir tipo de funcionário como Todos por padrão
    document.getElementById('tipoFuncionario').value = 'T';
    
    // Resetar label do valor
    atualizarLabelValor();
}

/**
 * Atualiza o label e prefixo do campo de valor baseado no tipo
 */
function atualizarLabelValor() {
    const tipo = document.getElementById('tipoFaixa').value;
    const label = document.getElementById('labelValorComissao');
    const prefixo = document.getElementById('prefixoValor');
    const sufixo = document.getElementById('sufixoValor');
    
    if (tipo === 'P') {
        label.textContent = 'Percentual *';
        prefixo.style.display = 'none';
        sufixo.style.display = 'block';
    } else {
        label.textContent = 'Valor Fixo *';
        prefixo.style.display = 'block';
        sufixo.style.display = 'none';
    }
}

/**
 * Carrega dados para edição
 */
function editarFaixa(id) {
    fetch(`/comissao-api-faixas?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const item = data.data;
                document.getElementById('modalFaixaTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Faixa de Comissão';
                document.getElementById('faixaId').value = item.ID_FAIXA;
                document.getElementById('descricao').value = item.DESCRICAO;
                document.getElementById('tipoFaixa').value = item.TIPO;
                document.getElementById('tipoFuncionario').value = item.TIPO_FUNCIONARIO || 'T';
                document.getElementById('pontoInicial').value = item.PONTO_INICIAL;
                document.getElementById('pontoFinal').value = item.PONTO_FINAL || '';
                document.getElementById('valorComissao').value = item.VALOR_COMISSAO;
                document.getElementById('centroTrabId').value = item.CENTRO_TRAB_ID || '';
                document.getElementById('dtVigenciaIni').value = item.DT_VIGENCIA_INI;
                document.getElementById('dtVigenciaFim').value = item.DT_VIGENCIA_FIM || '';
                
                atualizarLabelValor();
                
                const modal = new bootstrap.Modal(document.getElementById('modalFaixa'));
                modal.show();
            } else {
                exibirMensagemErro('Faixa não encontrada');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar faixa:', error);
            exibirMensagemErro('Erro ao carregar faixa');
        });
}

/**
 * Salva faixa (criar ou atualizar)
 */
function salvarFaixa() {
    // Guarda contra duplo-clique / Enter repetido
    const btnGuard = document.getElementById('btnSalvarFaixa');
    if (btnGuard && btnGuard.disabled) {
        return;
    }
    const id = document.getElementById('faixaId').value;
    const dados = {
        descricao: document.getElementById('descricao').value,
        tipo: document.getElementById('tipoFaixa').value,
        tipoFuncionario: document.getElementById('tipoFuncionario').value || 'T',
        pontoInicial: document.getElementById('pontoInicial').value,
        pontoFinal: document.getElementById('pontoFinal').value,
        valorComissao: document.getElementById('valorComissao').value,
        centroTrabId: document.getElementById('centroTrabId').value,
        dtVigenciaIni: document.getElementById('dtVigenciaIni').value,
        dtVigenciaFim: document.getElementById('dtVigenciaFim').value
    };
    
    // Validação
    if (!dados.descricao) {
        exibirMensagemErro('Informe a descrição da faixa');
        return;
    }
    if (!dados.tipo) {
        exibirMensagemErro('Selecione o tipo de faixa');
        return;
    }
    if (dados.pontoInicial === '' || dados.pontoInicial < 0) {
        exibirMensagemErro('Informe o ponto inicial');
        return;
    }
    if (!dados.valorComissao || dados.valorComissao <= 0) {
        exibirMensagemErro('Informe o valor da comissão');
        return;
    }
    if (!dados.dtVigenciaIni) {
        exibirMensagemErro('Informe a data de vigência inicial');
        return;
    }
    
    // Validar limite máximo dos pontos
    if (parseFloat(dados.pontoInicial) > 999999) {
        exibirMensagemErro('O ponto inicial não pode ultrapassar 999.999');
        return;
    }
    if (dados.pontoFinal && parseFloat(dados.pontoFinal) > 999999) {
        exibirMensagemErro('O ponto final não pode ultrapassar 999.999');
        return;
    }

    // Validar range de pontos
    if (dados.pontoFinal && parseFloat(dados.pontoFinal) <= parseFloat(dados.pontoInicial)) {
        exibirMensagemErro('O ponto final deve ser maior que o ponto inicial');
        return;
    }
    
    // Desabilitar botão para evitar duplo clique
    const btnSalvar = document.getElementById('btnSalvarFaixa');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...';
    }

    const metodo = id ? 'PUT' : 'POST';
    if (id) dados.id = id;
    
    enviarSalvarFaixa(dados, metodo, btnSalvar, false);
}

/**
 * Envia o request de salvar faixa. Se houver conflito, pergunta ao usuário se
 * deseja sobrescrever (inativando a faixa conflitante) e reenvia.
 */
function enviarSalvarFaixa(dados, metodo, btnSalvar, sobrescrever) {
    const payload = Object.assign({}, dados, { sobrescrever: !!sobrescrever });
    const id = dados.id;

    fetch('/comissao-api-faixas', {
        method: metodo,
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json().then(data => ({ status: response.status, data })))
    .then(({ status, data }) => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalFaixa')).hide();
            carregarFaixas();
            exibirMensagemSucesso(id ? 'Faixa atualizada com sucesso!' : 'Faixa cadastrada com sucesso!');
            return;
        }

        if ((status === 409 || data.conflito) && !sobrescrever) {
            const c = data.conflito_faixa || {};
            const centroNome = c.DESC_CENTRO
                ? (c.COD_CENTRO ? c.COD_CENTRO + ' - ' + c.DESC_CENTRO : c.DESC_CENTRO)
                : 'Todos';
            const desc = c.DESCRICAO ? '"' + c.DESCRICAO + '"' : '';
            const pontos = (c.PONTO_INICIAL ?? '0') + ' a ' + (c.PONTO_FINAL ?? '∞');
            const msg =
                'Já existe uma faixa ativa conflitante:\n\n' +
                'Faixa: ' + desc + '\n' +
                'Centro: ' + centroNome + '\n' +
                'Pontos: ' + pontos + '\n\n' +
                'Deseja SOBRESCREVER (inativar a faixa existente e salvar esta)?';
            if (window.confirm(msg)) {
                enviarSalvarFaixa(dados, metodo, btnSalvar, true);
                return;
            }
        }

        exibirMensagemErro(data.message || data.error || 'Erro ao salvar faixa');
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-check"></i> Salvar';
        }
    })
    .catch(error => {
        console.error('Erro ao salvar:', error);
        exibirMensagemErro('Erro ao salvar faixa');
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-check"></i> Salvar';
        }
    });
}

/**
 * Exclui uma faixa
 */
function excluirFaixa(id) {
    if (!confirm('Deseja realmente excluir esta faixa de comissão?')) return;
    
    fetch('/comissao-api-faixas', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarFaixas();
            exibirMensagemSucesso('Faixa excluída com sucesso!');
        } else {
            exibirMensagemErro(data.message || 'Erro ao excluir faixa');
        }
    })
    .catch(error => {
        console.error('Erro ao excluir:', error);
        exibirMensagemErro('Erro ao excluir faixa');
    });
}

/**
 * Formata número
 */
function formatarNumero(valor, casasDecimais = 2) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: Math.min(casasDecimais, 2),
        maximumFractionDigits: casasDecimais
    }).format(valor || 0);
}

/**
 * Formata moeda (até 4 casas decimais para valores fracionados)
 */
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 4
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
