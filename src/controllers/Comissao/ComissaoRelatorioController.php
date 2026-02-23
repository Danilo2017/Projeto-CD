<?php

namespace src\controllers\Comissao;

use \core\Controller as ctrl;
use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\Recurso;
use src\models\Comissao\Funcionario;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\Vinculo;

/**
 * Controller de Relatórios do Sistema de Comissão
 * Gerencia relatórios de produtividade e comissões
 */
class ComissaoRelatorioController extends ctrl
{
    // ==================== PÁGINAS ====================

    /**
     * Página principal de relatórios
     */
    public function index()
    {
        $dados = [
            'titulo' => 'Relatórios - Sistema de Comissão',
            'pagina' => 'Relatórios'
        ];

        $this->render('comissao/relatorio', $dados);
    }

    /**
     * Página de relatório de produtividade diária
     */
    public function produtividadeDiariaIndex()
    {
        $dados = [
            'titulo' => 'Relatório de Produtividade Diária',
            'pagina' => 'Produtividade Diária'
        ];

        $this->render('comissao/relatorio-diario', $dados);
    }

    /**
     * Página de relatório de comissões
     */
    public function comissoesIndex()
    {
        $dados = [
            'titulo' => 'Relatório de Comissões',
            'pagina' => 'Comissões'
        ];

        $this->render('comissao/relatorio-comissoes', $dados);
    }

    /**
     * Página de relatório por funcionário
     */
    public function porFuncionarioIndex()
    {
        $dados = [
            'titulo' => 'Relatório por Funcionário',
            'pagina' => 'Por Funcionário'
        ];

        $this->render('comissao/relatorio-funcionario', $dados);
    }

    // ==================== API RELATÓRIOS ====================

    /**
     * API - Relatório de produtividade diária detalhado
     * Retorna dados agrupados com validações de cadastro (pontuação, faixa, vínculo)
     */
    public function getProdutividadeDiaria()
    {
        try {
            $data = $_GET['data'] ?? date('Y-m-d');
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? null;
            $recursoId = $_GET['recursoId'] ?? $_GET['recurso_id'] ?? null;

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $model = new ApontamentoProducao();
            $dados = $model->produtividadeDiaria($data, $emprId, $recursoId, $centroTrabId);

            // Processar dados para o formato esperado pelo frontend
            $apontamentos = [];
            $produtividadePorFunc = [];
            $funcionariosUnicos = [];
            
            $resumo = [
                'TOTAL_REGISTROS' => count($dados),
                'TOTAL_QTD_PRODUZIDA' => 0,
                'TOTAL_PONTOS' => 0,
                'TOTAL_FUNCIONARIOS' => 0,
                'TOTAL_SEM_PONTUACAO' => 0,
                'TOTAL_SEM_FAIXA' => 0,
                'TOTAL_SEM_VINCULO' => 0
            ];

            // Verificar faltas
            $faltaModel = new FaltaFuncionario();
            $faltasCache = [];

            foreach ($dados as $item) {
                $funcId = $item['ID_FUNCIONARIO'] ?? null;
                $quantidade = floatval($item['QUANTIDADE'] ?? 0);
                $pontosUp = floatval($item['PONTOS_UP'] ?? 0);
                $totalPontos = $quantidade * $pontosUp;
                
                // Verificar falta (cache)
                $temFalta = false;
                $tipoFalta = null;
                if ($funcId) {
                    if (!isset($faltasCache[$funcId])) {
                        $falta = $faltaModel->verificarFaltasPeriodo($funcId, $data, $data, $emprId);
                        $faltasCache[$funcId] = !empty($falta) ? $falta[0] : null;
                    }
                    if ($faltasCache[$funcId]) {
                        $temFalta = true;
                        $tipoFalta = $faltasCache[$funcId]['TIPO_FALTA'] ?? null;
                    }
                }
                
                // Validações
                $temPontuacao = ($item['TEM_PONTUACAO'] ?? 'N') === 'S';
                $temFaixa = ($item['TEM_FAIXA'] ?? 'N') === 'S';
                $temVinculo = ($item['TEM_VINCULO'] ?? 'N') === 'S';
                
                // Calcular pontos com desconto de falta
                $pontosComDesconto = $totalPontos;
                if ($temFalta) {
                    if ($tipoFalta === 'I') {
                        // Falta integral: zera os pontos
                        $pontosComDesconto = 0;
                    } else {
                        // Falta parcial: 50% dos pontos
                        $pontosComDesconto = $totalPontos * 0.5;
                    }
                }
                
                // Mapeamento para apontamentos (detalhamento)
                $apontamentos[] = [
                    'FUNCIONARIO' => $item['NOME_FUNCIONARIO'] ?? 'Sem vínculo',
                    'FUNC_ID' => $funcId,
                    'PRODUTO' => $item['DESC_PRODUTO'] ?? '',
                    'CODIGO_PRODUTO' => $item['CODIGO_PRODUTO'] ?? '',
                    'MASCARA' => $item['MASCARA'] ?? '',
                    'CENTRO_TRAB' => $item['COD_CENTRO'] ? ($item['COD_CENTRO'] . ' - ' . $item['DESC_CENTRO']) : '-',
                    'OPERACAO' => $item['DESC_OPERACAO'] ?? '-',
                    'RECURSO' => $item['COD_MAQUINA'] ? ($item['COD_MAQUINA'] . ' - ' . $item['DESC_MAQUINA']) : '-',
                    'QUANTIDADE' => $quantidade,
                    'PONTOS_UP' => $pontosUp,
                    'TOTAL_PONTOS' => $totalPontos,
                    'PONTOS_COM_DESCONTO' => $pontosComDesconto,
                    'TEM_PONTUACAO' => $temPontuacao,
                    'TEM_FAIXA' => $temFaixa,
                    'TEM_VINCULO' => $temVinculo,
                    'TEM_FALTA' => $temFalta,
                    'TIPO_FALTA' => $tipoFalta
                ];
                
                // Resumo
                $resumo['TOTAL_QTD_PRODUZIDA'] += $quantidade;
                $resumo['TOTAL_PONTOS'] += $pontosComDesconto;
                if (!$temPontuacao) $resumo['TOTAL_SEM_PONTUACAO']++;
                if (!$temFaixa) $resumo['TOTAL_SEM_FAIXA']++;
                if (!$temVinculo) $resumo['TOTAL_SEM_VINCULO']++;
                
                // Agrupar por funcionário para tabela de produtividade
                $funcKey = $funcId ?: 'sem_vinculo_' . ($item['ID_MAQUINA'] ?? 0) . '_' . ($item['ID_CENTRO_TRAB'] ?? 0);
                if (!isset($produtividadePorFunc[$funcKey])) {
                    $funcionariosUnicos[] = $funcKey;
                    $produtividadePorFunc[$funcKey] = [
                        'NOME' => $item['NOME_FUNCIONARIO'] ?? 'Sem vínculo',
                        'CODIGO' => $item['COD_FUNCIONARIO'] ?? '-',
                        'FUNC_ID' => $funcId,
                        'CENTRO_TRABALHO' => $item['COD_CENTRO'] ? ($item['COD_CENTRO'] . ' - ' . $item['DESC_CENTRO']) : '-',
                        'RECURSO' => $item['COD_MAQUINA'] ? ($item['COD_MAQUINA'] . ' - ' . $item['DESC_MAQUINA']) : '-',
                        'QTD_ITENS' => 0,
                        'QTD_PRODUZIDA' => 0,
                        'TOTAL_PONTOS' => 0,
                        'TEM_PONTUACAO' => true,
                        'TEM_FAIXA' => $temFaixa,
                        'TEM_VINCULO' => $temVinculo,
                        'TEM_FALTA' => $temFalta,
                        'TIPO_FALTA' => $tipoFalta
                    ];
                }
                $produtividadePorFunc[$funcKey]['QTD_ITENS']++;
                $produtividadePorFunc[$funcKey]['QTD_PRODUZIDA'] += $quantidade;
                $produtividadePorFunc[$funcKey]['TOTAL_PONTOS'] += $pontosComDesconto;
                if (!$temPontuacao) $produtividadePorFunc[$funcKey]['TEM_PONTUACAO'] = false;
            }
            
            $resumo['TOTAL_FUNCIONARIOS'] = count($funcionariosUnicos);

            self::response([
                'success' => true,
                'resumo' => $resumo,
                'produtividade' => array_values($produtividadePorFunc),
                'apontamentos' => $apontamentos
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Listar comissões calculadas
     */
    public function getComissoes()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? null;
            $status = $_GET['status'] ?? null;
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            // Calcular comissões
            $comissaoModel = new Comissao();
            $resultado = $comissaoModel->calcularComissaoTodosCompleta($dataInicio, $dataFim, $emprId, $centroTrabId);

            // O método retorna estrutura: { success, resumo, funcionarios }
            $funcionarios = $resultado['funcionarios'] ?? [];
            
            // Formatar período para exibição
            $periodoFormatado = date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
            
            // Mapear dados para o formato esperado pelo frontend
            $comissoes = [];
            foreach ($funcionarios as $func) {
                // Calcular descrição da faixa aplicada
                $faixaDesc = 'Sem faixa';
                if (!empty($func['faixa_aplicada']['descricao'])) {
                    $faixaDesc = $func['faixa_aplicada']['descricao'];
                } elseif (!empty($func['regra_aplicada']['descricao'])) {
                    $faixaDesc = 'Regra: ' . $func['regra_aplicada']['descricao'];
                }
                
                $comissoes[] = [
                    'FUNC_ID' => $func['func_id'] ?? null,
                    'CODIGO_FUNC' => $func['cod_func'] ?? '',
                    'COD_FUNC' => $func['cod_func'] ?? '',
                    'NOME_FUNC' => $func['nome_func'] ?? '',
                    'PERIODO' => $periodoFormatado,
                    'CENTRO_TRAB_ID' => $func['centro_trab_id'] ?? null,
                    'CENTRO_TRABALHO' => $func['cod_centro'] ? ($func['cod_centro'] . ' - ' . $func['desc_centro']) : 'SEM CENTRO',
                    'TOTAL_PONTOS' => $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0,
                    'VALOR_COMISSAO' => $func['valor_comissao_final'] ?? 0,
                    'FAIXA_DESCRICAO' => $faixaDesc,
                    'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                    'DIAS_COM_FALTA' => $func['dias_com_falta'] ?? 0,
                    'TEM_FALTA' => ($func['dias_com_falta'] ?? 0) > 0,
                    'USA_REGRA_ESPECIFICA' => $func['usa_regra_especifica'] ?? false,
                    'STATUS' => 'P' // Pendente por padrão (ainda não aprovado)
                ];
            }

            // Calcular resumo
            $resumo = [
                'TOTAL_FUNCIONARIOS' => count($comissoes),
                'TOTAL_PONTOS' => 0,
                'TOTAL_COMISSAO' => 0,
                'PENDENTES' => count($comissoes),
                'APROVADOS' => 0,
                'CANCELADOS' => 0
            ];

            $porCentro = [];
            foreach ($comissoes as $c) {
                $resumo['TOTAL_PONTOS'] += $c['TOTAL_PONTOS'];
                $resumo['TOTAL_COMISSAO'] += $c['VALOR_COMISSAO'];

                // Agrupar por centro
                $centro = $c['CENTRO_TRABALHO'];
                if (!isset($porCentro[$centro])) {
                    $porCentro[$centro] = [
                        'CENTRO_TRABALHO' => $centro,
                        'TOTAL_FUNCIONARIOS' => 0,
                        'TOTAL_PONTOS' => 0,
                        'TOTAL_COMISSAO' => 0
                    ];
                }
                $porCentro[$centro]['TOTAL_FUNCIONARIOS']++;
                $porCentro[$centro]['TOTAL_PONTOS'] += $c['TOTAL_PONTOS'];
                $porCentro[$centro]['TOTAL_COMISSAO'] += $c['VALOR_COMISSAO'];
            }

            // Filtrar por status se especificado
            if ($status) {
                $comissoes = array_filter($comissoes, function($c) use ($status) {
                    return ($c['STATUS'] ?? 'P') === $status;
                });
            }

            self::response([
                'success' => true,
                'resumo' => $resumo,
                'porCentro' => array_values($porCentro),
                'comissoes' => array_values($comissoes)
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Detalhes de uma comissão específica
     */
    public function getComissaoDetalhes()
    {
        try {
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $codigoFunc = $_GET['codigoFunc'] ?? $_GET['codigo_func'] ?? null;
            $nomeFunc = $_GET['nomeFunc'] ?? $_GET['nome_func'] ?? null;
            $funcId = $_GET['funcId'] ?? $_GET['func_id'] ?? null;
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? null;

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$funcId) {
                throw new \Exception('Funcionário é obrigatório');
            }

            // Buscar empresa do vínculo do funcionário
            $vinculos = Vinculo::listar(['id_funcionario' => $funcId, 'ativo' => 'S']);
            $emprIdApontamentos = null;
            if (!empty($vinculos)) {
                $emprIdApontamentos = $vinculos[0]['ID_EMPR'] ?? null;
            }

            // Buscar apontamentos agrupados por item
            $model = new ApontamentoProducao();
            $apontamentosBrutos = $model->listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);
            
            // Mapear para o formato esperado pelo JS
            $apontamentos = [];
            foreach ($apontamentosBrutos as $ap) {
                $apontamentos[] = [
                    'CODIGO' => $ap['CODIGO_PRODUTO'] ?? '-',
                    'DESCRICAO' => $ap['DESC_PRODUTO'] ?? '-',
                    'MASCARA' => $ap['MASCARA'] ?? '-',
                    'RECURSO' => $ap['DESC_MAQUINA'] ?? '-',
                    'QUANTIDADE' => $ap['QUANTIDADE'] ?? 0,
                    'PONTOS_UP' => $ap['PONTOS_UP'] ?? 0,
                    'PONTOS' => $ap['TOTAL_PONTOS'] ?? 0
                ];
            }

            self::response([
                'success' => true,
                'apontamentos' => $apontamentos
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Processar comissões (cálculo simples)
     */
    public function processarComissoes()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            $dataInicio = $dados['dataInicio'] ?? $dados['data_inicio'] ?? null;
            $dataFim = $dados['dataFim'] ?? $dados['data_fim'] ?? null;
            $centroTrabId = $dados['centroTrabId'] ?? $dados['centro_trab_id'] ?? null;
            $emprId = $dados['emprId'] ?? $dados['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $comissaoModel = new Comissao();
            $resultado = $comissaoModel->calcularComissaoTodos($dataInicio, $dataFim, $emprId, $centroTrabId);

            // Salvar cálculos
            $processadas = 0;
            $usuId = $_SESSION['usu']['id'] ?? null;
            
            foreach ($resultado as $calc) {
                $dadosCalc = [
                    'funcionario_id' => $calc['FUNC_ID'] ?? $calc['funcionario_id'],
                    'faixa_id' => $calc['FAIXA_ID'] ?? $calc['faixa_id'],
                    'dt_inicio' => $dataInicio,
                    'dt_fim' => $dataFim,
                    'total_pontos' => $calc['TOTAL_PONTOS'] ?? $calc['total_pontos'],
                    'valor_comissao' => $calc['VALOR_COMISSAO'] ?? $calc['valor_comissao'],
                    'id_usuario_proc' => $usuId
                ];
                
                if ($comissaoModel->salvarCalculo($dadosCalc)) {
                    $processadas++;
                }
            }

            self::response([
                'success' => true,
                'message' => 'Comissões processadas com sucesso',
                'processadas' => $processadas
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Processar comissões completo (com todas as regras)
     */
    public function processarComissoesCompleto()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            $dataInicio = $dados['dataInicio'] ?? $dados['data_inicio'] ?? null;
            $dataFim = $dados['dataFim'] ?? $dados['data_fim'] ?? null;
            $centroTrabId = $dados['centroTrabId'] ?? $dados['centro_trab_id'] ?? null;
            $emprId = $dados['emprId'] ?? $dados['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            $comissaoModel = new Comissao();
            $resultado = $comissaoModel->calcularComissaoTodosCompleta($dataInicio, $dataFim, $emprId, $centroTrabId);

            // Salvar cálculos
            $processadas = 0;
            $totais = [
                'total_funcionarios' => count($resultado),
                'total_com_falta' => 0,
                'total_com_retrabalho' => 0,
                'total_com_regra_especifica' => 0,
                'total_geral_comissao' => 0
            ];
            
            $usuId = $_SESSION['usu']['id'] ?? null;
            
            foreach ($resultado as $calc) {
                // Contabilizar totais
                if (!empty($calc['dias_com_falta']) && $calc['dias_com_falta'] > 0) {
                    $totais['total_com_falta']++;
                }
                if (!empty($calc['total_retrabalho']) && $calc['total_retrabalho'] > 0) {
                    $totais['total_com_retrabalho']++;
                }
                if (!empty($calc['usa_regra_especifica']) && $calc['usa_regra_especifica']) {
                    $totais['total_com_regra_especifica']++;
                }
                $totais['total_geral_comissao'] += $calc['valor_comissao_final'] ?? $calc['VALOR_COMISSAO'] ?? 0;

                // Salvar
                $dadosCalc = [
                    'funcionario_id' => $calc['func_id'] ?? $calc['FUNC_ID'],
                    'faixa_id' => $calc['faixa_aplicada']['ID_FAIXA'] ?? null,
                    'dt_inicio' => $dataInicio,
                    'dt_fim' => $dataFim,
                    'total_pontos' => $calc['total_pontos_apos_falta'] ?? $calc['TOTAL_PONTOS'],
                    'valor_comissao' => $calc['valor_comissao_final'] ?? $calc['VALOR_COMISSAO'],
                    'id_usuario_proc' => $usuId
                ];
                
                if ($comissaoModel->salvarCalculo($dadosCalc)) {
                    $processadas++;
                }
            }

            self::response([
                'success' => true,
                'message' => 'Comissões processadas com sucesso',
                'processadas' => $processadas,
                'resultado' => $totais
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Aprovar comissões
     */
    public function aprovarComissao()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            if (empty($dados['comissoes']) || !is_array($dados['comissoes'])) {
                throw new \Exception('Nenhuma comissão selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;
            if (!$usuId) {
                throw new \Exception('Usuário não autenticado');
            }

            $comissaoModel = new Comissao();
            $aprovadas = 0;

            foreach ($dados['comissoes'] as $comissao) {
                $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
                
                if ($idComissao) {
                    // Aprovar comissão existente
                    if ($comissaoModel->aprovar($idComissao, $usuId)) {
                        $aprovadas++;
                    }
                } else {
                    // Criar e aprovar nova comissão
                    $dadosCalc = [
                        'funcionario_id' => $comissao['FUNC_ID'] ?? $comissao['func_id'],
                        'faixa_id' => $comissao['FAIXA_ID'] ?? $comissao['faixa_id'],
                        'dt_inicio' => $comissao['DATA_INICIO'] ?? $comissao['data_inicio'],
                        'dt_fim' => $comissao['DATA_FIM'] ?? $comissao['data_fim'],
                        'total_pontos' => $comissao['TOTAL_PONTOS'] ?? $comissao['total_pontos'],
                        'valor_comissao' => $comissao['VALOR_COMISSAO'] ?? $comissao['valor_comissao'],
                        'id_usuario_proc' => $usuId,
                        'status' => Comissao::STATUS_APROVADO
                    ];
                    
                    if ($comissaoModel->salvarCalculo($dadosCalc)) {
                        $aprovadas++;
                    }
                }
            }

            self::response([
                'success' => true,
                'message' => "{$aprovadas} comissão(ões) aprovada(s)"
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Cancelar comissões
     */
    public function cancelarComissao()
    {
        try {
            $dados = json_decode(file_get_contents('php://input'), true);

            if (empty($dados['comissoes']) || !is_array($dados['comissoes'])) {
                throw new \Exception('Nenhuma comissão selecionada');
            }

            $usuId = $_SESSION['usu']['id'] ?? null;
            if (!$usuId) {
                throw new \Exception('Usuário não autenticado');
            }

            $motivo = $dados['motivo'] ?? null;

            $comissaoModel = new Comissao();
            $canceladas = 0;

            foreach ($dados['comissoes'] as $comissao) {
                $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
                
                if ($idComissao) {
                    if ($comissaoModel->cancelar($idComissao, $usuId, $motivo)) {
                        $canceladas++;
                    }
                }
            }

            self::response([
                'success' => true,
                'message' => "{$canceladas} comissão(ões) cancelada(s)"
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API - Relatório por funcionário
     */
    public function getRelatorioFuncionario()
    {
        try {
            $funcionarioId = $_GET['funcionarioId'] ?? $_GET['funcionario_id'] ?? $_GET['funcId'] ?? null;
            $dataInicio = $_GET['dataInicio'] ?? $_GET['data_inicio'] ?? null;
            $dataFim = $_GET['dataFim'] ?? $_GET['data_fim'] ?? null;
            $emprId = $_GET['emprId'] ?? $_GET['empr_id'] ?? ($_SESSION['empresa']['id'] ?? null);
            $centroTrabId = $_GET['centroTrabId'] ?? $_GET['centro_trab_id'] ?? null;

            if (!$funcionarioId) {
                throw new \Exception('Funcionário é obrigatório');
            }

            if (!$dataInicio || !$dataFim) {
                throw new \Exception('Período é obrigatório');
            }

            if (!$emprId) {
                throw new \Exception('Empresa não selecionada');
            }

            // Buscar dados do funcionário
            $funcModel = new Funcionario();
            $funcionario = $funcModel->buscarPorId($funcionarioId);

            if (!$funcionario) {
                throw new \Exception('Funcionário não encontrado');
            }

            // Buscar vínculo do funcionário para obter centro de trabalho e empresa
            $filtrosVinculo = ['id_funcionario' => $funcionarioId, 'ativo' => 'S'];
            $vinculos = Vinculo::listar($filtrosVinculo) ?: [];
            $vinculo = !empty($vinculos) ? $vinculos[0] : null;
            
            // Se não informou centro, usar o do vínculo
            if (!$centroTrabId && $vinculo) {
                $centroTrabId = $vinculo['ID_CENTRO_TRAB'];
            }
            
            // Usar a empresa do vínculo para buscar apontamentos (se existir vínculo)
            $emprIdApontamentos = $vinculo ? $vinculo['ID_EMPR'] : $emprId;

            // Buscar pontos por dia
            $apontModel = new ApontamentoProducao();
            $diario = $apontModel->pontosPorDiaFuncionario($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

            // Buscar apontamentos detalhados (vinculados ao funcionário via recurso)
            $apontamentos = $apontModel->listarApontamentosVinculados($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

            // Verificar faltas
            $faltaModel = new FaltaFuncionario();
            $faltas = $faltaModel->verificarFaltasPeriodo($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos);
            $diasComFalta = [];
            foreach ($faltas as $f) {
                $dtFalta = $f['DT_FALTA'] ?? null;
                if ($dtFalta) {
                    // Converter formato Oracle para YYYY-MM-DD se necessário
                    if (preg_match('/^\d{2}-[A-Z]{3}-\d{2}$/', $dtFalta)) {
                        $dtFalta = date('Y-m-d', strtotime($dtFalta));
                    }
                    $diasComFalta[$dtFalta] = [
                        'tipo' => $f['TIPO_FALTA'],
                        'motivo' => $f['MOTIVO'] ?? null
                    ];
                }
            }

            // Calcular comissão usando o modelo de comissão
            $comissaoModel = new Comissao();
            $resultadoComissao = $comissaoModel->calcularComissaoCompleta(
                $funcionarioId,
                $dataInicio,
                $dataFim,
                $emprIdApontamentos,
                $centroTrabId
            );
            
            $totalComissao = $resultadoComissao['valor_comissao_final'] ?? 0;
            $faixaAplicada = $resultadoComissao['faixa_aplicada'] ?? null;
            $regraAplicada = $resultadoComissao['regra_aplicada'] ?? null;
            $valorPorPonto = 0;
            
            // Determinar valor por ponto (da faixa ou da regra)
            if ($faixaAplicada) {
                $valorPorPonto = floatval($faixaAplicada['valor'] ?? 0);
            } elseif ($regraAplicada) {
                $valorPorPonto = floatval($regraAplicada['valor'] ?? 0);
            }

            // Adicionar info de falta e comissão ao diário
            $totalPontos = 0;
            $totalPontosAposFalta = 0;
            foreach ($diario as &$dia) {
                // Normalizar campo DATA (SQL retorna DATA_APONTAMENTO)
                $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
                $dia['DATA'] = $dataStr; // Manter compatibilidade com o frontend
                $pontosDia = floatval($dia['TOTAL_PONTOS'] ?? 0);
                $totalPontos += $pontosDia;
                
                // Verificar falta
                if ($dataStr && isset($diasComFalta[$dataStr])) {
                    $dia['TEM_FALTA'] = true;
                    $dia['TIPO_FALTA'] = $diasComFalta[$dataStr]['tipo'];
                    $dia['MOTIVO_FALTA'] = $diasComFalta[$dataStr]['motivo'];
                    
                    // Se tem falta integral, zera os pontos e comissão do dia
                    if ($diasComFalta[$dataStr]['tipo'] === 'I') {
                        $dia['PONTOS_COM_DESCONTO'] = 0;
                        $dia['COMISSAO_DIA'] = 0;
                        // Não soma ao totalPontosAposFalta
                    } else {
                        // Falta parcial - 50% dos pontos
                        $pontosComDesconto = $pontosDia * 0.5;
                        $dia['PONTOS_COM_DESCONTO'] = $pontosComDesconto;
                        $dia['COMISSAO_DIA'] = $pontosComDesconto * $valorPorPonto;
                        $totalPontosAposFalta += $pontosComDesconto;
                    }
                } else {
                    $dia['TEM_FALTA'] = false;
                    $dia['TIPO_FALTA'] = null;
                    $dia['MOTIVO_FALTA'] = null;
                    $dia['PONTOS_COM_DESCONTO'] = $pontosDia;
                    
                    // Calcular comissão proporcional do dia
                    $dia['COMISSAO_DIA'] = $pontosDia * $valorPorPonto;
                    $totalPontosAposFalta += $pontosDia;
                }
            }

            // Calcular resumo
            $resumo = [
                'TOTAL_APONTAMENTOS' => count($apontamentos),
                'TOTAL_PONTOS' => $totalPontos,
                'TOTAL_PONTOS_APOS_FALTA' => $totalPontosAposFalta,
                'TOTAL_COMISSAO' => $totalComissao,
                'VALOR_COMISSAO_BRUTO' => $resultadoComissao['valor_comissao_bruto'] ?? 0,
                'DESCONTO_RETRABALHO' => $resultadoComissao['desconto_retrabalho'] ?? 0,
                'MEDIA_DIARIA' => 0,
                'DIAS_TRABALHADOS' => count($diario),
                'DIAS_COM_FALTA' => count($faltas),
                'FAIXA_APLICADA' => $faixaAplicada,
                'REGRA_APLICADA' => $regraAplicada,
                'USA_REGRA_ESPECIFICA' => $resultadoComissao['usa_regra_especifica'] ?? false,
                'VALOR_POR_PONTO' => $valorPorPonto
            ];

            if ($resumo['DIAS_TRABALHADOS'] > 0) {
                $resumo['MEDIA_DIARIA'] = $resumo['TOTAL_PONTOS'] / $resumo['DIAS_TRABALHADOS'];
            }

            // Buscar comissões já salvas (histórico)
            $comissoesSalvas = $comissaoModel->listarCalculos(null, $dataInicio, $dataFim, $funcionarioId);

            self::response([
                'success' => true,
                'funcionario' => $funcionario,
                'resumo' => $resumo,
                'diario' => $diario,
                'apontamentos' => $apontamentos,
                'comissoes' => $comissoesSalvas,
                'vinculos' => $vinculos,
                'faltas' => $faltas
            ], 200);

        } catch (\Exception $e) {
            self::response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
