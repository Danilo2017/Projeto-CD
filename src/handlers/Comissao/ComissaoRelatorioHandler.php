<?php

namespace src\handlers\Comissao;

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
 * Handler para lógica de negócio de Relatórios de Comissão
 * 
 * Responsabilidades:
 * - Chamar models para obter dados
 * - Aplicar regras de negócio (cálculos, faltas, descontos)
 * - Montar e formatar dados de resposta
 */
class ComissaoRelatorioHandler
{
    /**
     * Obter produtividade diária com validações de cadastro
     * 
     * @param string $data Data no formato Y-m-d
     * @param int $emprId ID da empresa
     * @param int|null $recursoId ID do recurso (opcional)
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados formatados
     */
    public static function getProdutividadeDiaria(string $data, int $emprId, ?int $recursoId = null, ?int $centroTrabId = null, ?string $dataFim = null): array
    {
        $dados = ApontamentoProducao::produtividadeDiaria($data, $emprId, $recursoId, $centroTrabId, $dataFim);

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

        // OTIMIZADO: Extrair IDs únicos de funcionários e buscar todas as faltas de uma vez
        $funcIds = array_unique(array_filter(array_map(fn($item) => $item['ID_FUNCIONARIO'] ?? null, $dados)));
        
        $faltasCache = []; // Indexado por funcId => [data => ['TIPO_FALTA' => ..., 'MOTIVO' => ...]]
        $funcTemFalta = []; // funcId => true se tem ao menos uma falta no período
        
        $dataFimReal = $dataFim ?: $data;
        
        if (!empty($funcIds)) {
            // Usa método batch para buscar todas as faltas em uma única query
            $faltasPorFunc = FaltaFuncionario::verificarFaltasPeriodoBatch(array_values($funcIds), $data, $dataFimReal, $emprId);
            
            // Mapear para cache indexado por funcId + data para desconto correto por dia
            foreach ($faltasPorFunc as $funcId => $faltas) {
                if (!empty($faltas)) {
                    $funcTemFalta[$funcId] = true;
                    foreach ($faltas as $falta) {
                        $dtFalta = substr($falta['DT_FALTA'] ?? '', 0, 10);
                        if ($dtFalta) {
                            $faltasCache[$funcId][$dtFalta] = [
                                'TIPO_FALTA' => $falta['TIPO_FALTA'] ?? 'I',
                                'MOTIVO' => $falta['MOTIVO'] ?? null
                            ];
                        }
                    }
                }
            }
        }

        foreach ($dados as $item) {
            $funcId = $item['ID_FUNCIONARIO'] ?? null;
            $quantidade = floatval($item['QUANTIDADE'] ?? 0);
            $pontosUp = floatval($item['PONTOS_UP'] ?? 0);
            $totalPontos = $quantidade * $pontosUp;
            
            // Verificar falta comparando a data do apontamento com as datas de falta
            $temFalta = false;
            $tipoFalta = null;
            if ($funcId && isset($faltasCache[$funcId])) {
                // Extrair data do apontamento (DT_APONT vem como DD-MON-RR do Oracle)
                $dtApont = $item['DT_APONT'] ?? null;
                if ($dtApont) {
                    $dtApontStr = date('Y-m-d', strtotime($dtApont));
                    if (isset($faltasCache[$funcId][$dtApontStr])) {
                        $temFalta = true;
                        $tipoFalta = $faltasCache[$funcId][$dtApontStr]['TIPO_FALTA'];
                    }
                }
            }
            
            // Validações
            $temPontuacao = ($item['TEM_PONTUACAO'] ?? 'N') === 'S';
            $temFaixa = ($item['TEM_FAIXA'] ?? 'N') === 'S';
            $temVinculo = ($item['TEM_VINCULO'] ?? 'N') === 'S';
            
            // Calcular pontos com desconto de falta
            $pontosComDesconto = self::calcularPontosComDesconto($totalPontos, $temFalta, $tipoFalta);
            
            // Mapeamento para apontamentos (detalhamento)
            $apontamentos[] = [
                'FUNCIONARIO' => $item['NOME_FUNCIONARIO'] ?? 'Sem vínculo',
                'FUNC_ID' => $funcId,
                'PRODUTO' => $item['DESC_PRODUTO'] ?? '',
                'CODIGO_PRODUTO' => $item['CODIGO_PRODUTO'] ?? '',
                'ID_ITEM' => $item['ID_MASCARA'] ?? '',
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
                // Verificar se o funcionário tem ao menos uma falta no período (para badge)
                $funcTemFaltaPeriodo = isset($funcTemFalta[$funcId]);
                $tipoFaltaFunc = null;
                if ($funcTemFaltaPeriodo && isset($faltasCache[$funcId])) {
                    // Pegar o tipo mais restritivo: I (integral) > P (parcial)
                    foreach ($faltasCache[$funcId] as $faltaDia) {
                        if ($faltaDia['TIPO_FALTA'] === 'I') {
                            $tipoFaltaFunc = 'I';
                            break;
                        }
                        $tipoFaltaFunc = $faltaDia['TIPO_FALTA'];
                    }
                }
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
                    'TEM_FALTA' => $funcTemFaltaPeriodo,
                    'TIPO_FALTA' => $tipoFaltaFunc
                ];
            }
            $produtividadePorFunc[$funcKey]['QTD_ITENS']++;
            $produtividadePorFunc[$funcKey]['QTD_PRODUZIDA'] += $quantidade;
            $produtividadePorFunc[$funcKey]['TOTAL_PONTOS'] += $pontosComDesconto;
            if (!$temPontuacao) $produtividadePorFunc[$funcKey]['TEM_PONTUACAO'] = false;
        }
        
        $resumo['TOTAL_FUNCIONARIOS'] = count($funcionariosUnicos);

        return [
            'resumo' => $resumo,
            'produtividade' => array_values($produtividadePorFunc),
            'apontamentos' => $apontamentos
        ];
    }

    /**
     * Obter comissões calculadas para um período
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param string|null $status Filtro de status (opcional)
     * @return array Dados formatados
     */
    public static function getComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?string $status = null): array
    {
        // OTIMIZADO: Usa versão batch que faz ~6 queries ao invés de N*5 queries
        $comissaoModel = new Comissao();
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $periodoFormatado = date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
        
        $comissoes = [];
        foreach ($funcionarios as $func) {
            $faixaDesc = self::formatarDescricaoFaixa($func);
            
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
                'STATUS' => 'P'
            ];
        }

        // Calcular resumo e agrupamento por centro
        $resumo = self::calcularResumoComissoes($comissoes);
        $porCentro = self::agruparPorCentro($comissoes);

        // Filtrar por status se especificado
        if ($status) {
            $comissoes = array_filter($comissoes, fn($c) => ($c['STATUS'] ?? 'P') === $status);
        }

        return [
            'resumo' => $resumo,
            'porCentro' => array_values($porCentro),
            'comissoes' => array_values($comissoes)
        ];
    }

    /**
     * Obter detalhes de uma comissão específica
     * 
     * @param int $funcId ID do funcionário
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados formatados
     */
    public static function getComissaoDetalhes(int $funcId, string $dataInicio, string $dataFim, ?int $centroTrabId = null): array
    {
        // Buscar empresa do vínculo do funcionário
        $vinculos = Vinculo::listar(['id_funcionario' => $funcId, 'ativo' => 'S']);
        $emprIdApontamentos = !empty($vinculos) ? ($vinculos[0]['ID_EMPR'] ?? null) : null;

        $apontamentosBrutos = ApontamentoProducao::listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);
        
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

        return ['apontamentos' => $apontamentos];
    }

    /**
     * Processar comissões (cálculo simples)
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param int|null $usuId ID do usuário que processou
     * @return array Resultado do processamento
     */
    public static function processarComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?int $usuId = null): array
    {
        // Usa versão batch otimizada (~6 queries ao invés de N*3)
        $comissaoModel = new Comissao();
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $processadas = 0;
        
        foreach ($funcionarios as $calc) {
            $dadosCalc = [
                'funcionario_id' => $calc['func_id'] ?? $calc['FUNC_ID'],
                'faixa_id' => isset($calc['faixa_aplicada']['id']) ? $calc['faixa_aplicada']['id'] : null,
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

        return [
            'message' => 'Comissões processadas com sucesso',
            'processadas' => $processadas
        ];
    }

    /**
     * Processar comissões completo (com todas as regras)
     * 
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param int|null $usuId ID do usuário que processou
     * @return array Resultado do processamento
     */
    public static function processarComissoesCompleto(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null, ?int $usuId = null): array
    {
        // OTIMIZADO: Usa versão batch que faz ~6 queries ao invés de N*5 queries
        $comissaoModel = new Comissao();
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $processadas = 0;
        $totais = [
            'total_funcionarios' => count($funcionarios),
            'total_com_falta' => 0,
            'total_com_retrabalho' => 0,
            'total_com_regra_especifica' => 0,
            'total_geral_comissao' => 0
        ];
        
        foreach ($funcionarios as $calc) {
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

        return [
            'message' => 'Comissões processadas com sucesso',
            'processadas' => $processadas,
            'resultado' => $totais
        ];
    }

    /**
     * Aprovar comissões
     * 
     * @param array $comissoes Array de comissões a aprovar
     * @param int $usuId ID do usuário
     * @return array Resultado
     */
    public static function aprovarComissoes(array $comissoes, int $usuId): array
    {
        $aprovadas = 0;
        $comissaoModel = new Comissao();

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
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

        return [
            'message' => "{$aprovadas} comissão(ões) aprovada(s)"
        ];
    }

    /**
     * Cancelar comissões
     * 
     * @param array $comissoes Array de comissões a cancelar
     * @param int $usuId ID do usuário
     * @param string|null $motivo Motivo do cancelamento
     * @return array Resultado
     */
    public static function cancelarComissoes(array $comissoes, int $usuId, ?string $motivo = null): array
    {
        $canceladas = 0;
        $comissaoModel = new Comissao();

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
                if ($comissaoModel->cancelar($idComissao, $usuId, $motivo)) {
                    $canceladas++;
                }
            }
        }

        return [
            'message' => "{$canceladas} comissão(ões) cancelada(s)"
        ];
    }

    /**
     * Obter relatório detalhado por funcionário
     * 
     * @param int $funcionarioId ID do funcionário
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array Dados completos do relatório
     */
    public static function getRelatorioFuncionario(int $funcionarioId, string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        // Buscar dados do funcionário
        $funcionario = Funcionario::buscarPorId($funcionarioId);

        if (!$funcionario) {
            throw new \Exception('Funcionário não encontrado');
        }

        // Buscar vínculo do funcionário
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
        $diario = ApontamentoProducao::pontosPorDiaFuncionario($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

        // Buscar apontamentos detalhados
        $apontamentos = ApontamentoProducao::listarApontamentosVinculados($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);

        // Verificar faltas
        $faltas = FaltaFuncionario::verificarFaltasPeriodo($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos);
        $diasComFalta = self::mapearDiasComFalta($faltas);

        // Calcular totalPontosAposFalta a partir dos dados já buscados (evita re-consultar)
        $totalPontosAposFalta = 0;
        foreach ($diario as $dia) {
            $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
            $pontos = floatval($dia['TOTAL_PONTOS'] ?? 0);
            if ($dataStr && isset($diasComFalta[$dataStr])) {
                if ($diasComFalta[$dataStr]['tipo'] !== 'I') {
                    $totalPontosAposFalta += $pontos * 0.5;
                }
            } else {
                $totalPontosAposFalta += $pontos;
            }
        }

        // Calcular comissão usando método otimizado (sem re-buscar faltas/pontos)
        $comissaoModel = new Comissao();
        $resultadoComissao = $comissaoModel->calcularComissaoPreCalculada(
            $funcionarioId,
            $dataInicio,
            $dataFim,
            $emprIdApontamentos,
            $totalPontosAposFalta,
            $faltas,
            $centroTrabId
        );
        
        $totalComissao = $resultadoComissao['valor_comissao_final'] ?? 0;
        $faixaAplicada = $resultadoComissao['faixa_aplicada'] ?? null;
        $regraAplicada = $resultadoComissao['regra_aplicada'] ?? null;
        $valorPorPonto = self::getValorPorPonto($faixaAplicada, $regraAplicada);

        // Processar diário com faltas e comissões
        $diario = self::processarDiarioComFaltas($diario, $diasComFalta, $valorPorPonto, $regraAplicada);

        // Calcular totais
        $totalPontos = array_sum(array_column($diario, 'TOTAL_PONTOS_ORIGINAL'));
        $totalPontosAposFalta = array_sum(array_column($diario, 'PONTOS_COM_DESCONTO'));

        // Para tipo M, extrair valor fixo da regra
        $valorFixo = 0;
        $tipoRegra = null;
        if ($regraAplicada) {
            $tipoRegra = $regraAplicada['tipo'] ?? null;
            $valorFixo = floatval($regraAplicada['valor_fixo'] ?? 0);
        }

        // Montar resumo
        $resumo = [
            'TOTAL_APONTAMENTOS' => count($apontamentos),
            'TOTAL_PONTOS' => $totalPontos,
            'TOTAL_PONTOS_APOS_FALTA' => $totalPontosAposFalta,
            'TOTAL_COMISSAO' => $totalComissao,
            'VALOR_COMISSAO_BRUTO' => $resultadoComissao['valor_comissao_bruto'] ?? 0,
            'DESCONTO_RETRABALHO' => $resultadoComissao['desconto_retrabalho'] ?? 0,
            'MEDIA_DIARIA' => count($diario) > 0 ? $totalPontos / count($diario) : 0,
            'DIAS_TRABALHADOS' => count($diario),
            'DIAS_COM_FALTA' => count($faltas),
            'FAIXA_APLICADA' => $faixaAplicada,
            'REGRA_APLICADA' => $regraAplicada,
            'USA_REGRA_ESPECIFICA' => $resultadoComissao['usa_regra_especifica'] ?? false,
            'VALOR_POR_PONTO' => $valorPorPonto,
            'VALOR_FIXO' => $valorFixo,
            'TIPO_REGRA' => $tipoRegra
        ];

        // Buscar comissões já salvas
        $comissoesSalvas = $comissaoModel->listarCalculos(null, $dataInicio, $dataFim, $funcionarioId);

        return [
            'funcionario' => $funcionario,
            'resumo' => $resumo,
            'diario' => $diario,
            'apontamentos' => $apontamentos,
            'comissoes' => $comissoesSalvas,
            'vinculos' => $vinculos,
            'faltas' => $faltas
        ];
    }

    // ==================== MÉTODOS AUXILIARES (privados) ====================

    /**
     * Obter relatório por centro de trabalho com todos os funcionários
     * 
     * @param int $centroTrabId ID do centro de trabalho
     * @param string $dataInicio Data início
     * @param string $dataFim Data fim
     * @param int $emprId ID da empresa
     * @return array Dados completos do relatório por centro
     */
    public static function getRelatorioCentroTrabalho(int $centroTrabId, string $dataInicio, string $dataFim, int $emprId): array
    {
        // Buscar dados do centro de trabalho
        $centro = CentroTrabalho::buscarPorId($centroTrabId);
        if (!$centro) {
            throw new \Exception('Centro de trabalho não encontrado');
        }

        // Buscar todos os funcionários vinculados a este centro
        $vinculos = Vinculo::listar([
            'id_centro_trab' => $centroTrabId,
            'ativo' => 'S'
        ]);

        if (empty($vinculos)) {
            return [
                'centro' => [
                    'ID' => $centro['ID'] ?? $centroTrabId,
                    'CODIGO' => $centro['COD_CENTRO'] ?? '-',
                    'DESCRICAO' => $centro['DESCRICAO'] ?? '-'
                ],
                'resumo' => [
                    'TOTAL_FUNCIONARIOS' => 0,
                    'TOTAL_PONTOS' => 0,
                    'TOTAL_COMISSAO' => 0,
                    'TOTAL_COM_FALTA' => 0
                ],
                'funcionarios' => []
            ];
        }

        // Usar cálculo batch otimizado para o centro
        $comissaoModel = new Comissao();
        $resultado = $comissaoModel->calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionariosBatch = $resultado['funcionarios'] ?? [];
        
        $funcionarios = [];
        $totalPontos = 0;
        $totalComissao = 0;
        $totalComFalta = 0;

        foreach ($funcionariosBatch as $func) {
            $pontosFunc = $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0;
            $comissaoFunc = $func['valor_comissao_final'] ?? 0;
            $diasComFalta = $func['dias_com_falta'] ?? 0;

            $faixaDesc = self::formatarDescricaoFaixa($func);

            $funcionarios[] = [
                'FUNC_ID' => $func['func_id'] ?? null,
                'COD_FUNC' => $func['cod_func'] ?? '',
                'NOME_FUNC' => $func['nome_func'] ?? '',
                'TOTAL_PONTOS' => $pontosFunc,
                'VALOR_COMISSAO' => $comissaoFunc,
                'FAIXA_DESCRICAO' => $faixaDesc,
                'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                'DIAS_COM_FALTA' => $diasComFalta,
                'TEM_FALTA' => $diasComFalta > 0,
                'USA_REGRA_ESPECIFICA' => $func['usa_regra_especifica'] ?? false,
                'TIPO_VINCULO' => $func['tipo_vinculo'] ?? 'N'
            ];

            $totalPontos += $pontosFunc;
            $totalComissao += $comissaoFunc;
            if ($diasComFalta > 0) $totalComFalta++;
        }

        // Ordenar por nome
        usort($funcionarios, fn($a, $b) => strcmp($a['NOME_FUNC'], $b['NOME_FUNC']));

        return [
            'centro' => [
                'ID' => $centro['ID'] ?? $centroTrabId,
                'CODIGO' => $centro['COD_CENTRO'] ?? '-',
                'DESCRICAO' => $centro['DESCRICAO'] ?? '-'
            ],
            'resumo' => [
                'TOTAL_FUNCIONARIOS' => count($funcionarios),
                'TOTAL_PONTOS' => $totalPontos,
                'TOTAL_COMISSAO' => $totalComissao,
                'TOTAL_COM_FALTA' => $totalComFalta
            ],
            'funcionarios' => $funcionarios
        ];
    }


    /**
     * Calcular pontos com desconto de falta
     */
    private static function calcularPontosComDesconto(float $totalPontos, bool $temFalta, ?string $tipoFalta): float
    {
        if (!$temFalta) {
            return $totalPontos;
        }

        if ($tipoFalta === 'I') {
            // Falta integral: zera os pontos
            return 0;
        }

        // Falta parcial: 50% dos pontos
        return $totalPontos * 0.5;
    }

    /**
     * Formatar descrição da faixa aplicada
     */
    private static function formatarDescricaoFaixa(array $func): string
    {
        $isApoio = ($func['tipo_vinculo'] ?? 'N') === 'A';

        if (!empty($func['faixa_aplicada']['descricao'])) {
            return $func['faixa_aplicada']['descricao'];
        }
        
        if (!empty($func['regra_aplicada'])) {
            $tipoLabel = self::labelTipoComissao($func['regra_aplicada']['tipo'] ?? '');
            if ($isApoio) {
                return 'APOIO - ' . $tipoLabel;
            }
            return 'Regra: ' . ($func['regra_aplicada']['descricao'] ?? $tipoLabel);
        }
        
        if ($isApoio) {
            return 'APOIO';
        }
        
        return 'Sem faixa';
    }

    /**
     * Converter código TIPO_COMISSAO em label legível
     */
    private static function labelTipoComissao(string $tipo): string
    {
        return match ($tipo) {
            'P' => 'PERCENTUAL',
            'V' => 'VALOR POR UP',
            'F' => 'FIXO',
            'M' => 'VALOR UP + FIXO',
            default => $tipo,
        };
    }

    /**
     * Calcular resumo das comissões
     */
    private static function calcularResumoComissoes(array $comissoes): array
    {
        $resumo = [
            'TOTAL_FUNCIONARIOS' => count($comissoes),
            'TOTAL_PONTOS' => 0,
            'TOTAL_COMISSAO' => 0,
            'PENDENTES' => count($comissoes),
            'APROVADOS' => 0,
            'CANCELADOS' => 0
        ];

        foreach ($comissoes as $c) {
            $resumo['TOTAL_PONTOS'] += $c['TOTAL_PONTOS'];
            $resumo['TOTAL_COMISSAO'] += $c['VALOR_COMISSAO'];
        }

        return $resumo;
    }

    /**
     * Agrupar comissões por centro de trabalho
     */
    private static function agruparPorCentro(array $comissoes): array
    {
        $porCentro = [];
        
        foreach ($comissoes as $c) {
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

        return $porCentro;
    }

    /**
     * Mapear dias com falta para array indexado por data
     */
    private static function mapearDiasComFalta(array $faltas): array
    {
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

        return $diasComFalta;
    }

    /**
     * Obter valor por ponto da faixa ou regra
     */
    private static function getValorPorPonto(?array $faixaAplicada, ?array $regraAplicada): float
    {
        if ($faixaAplicada) {
            return floatval($faixaAplicada['valor'] ?? 0);
        }
        
        if ($regraAplicada) {
            return floatval($regraAplicada['valor'] ?? 0);
        }

        return 0;
    }

    /**
     * Processar diário adicionando informações de falta e comissão
     */
    private static function processarDiarioComFaltas(array $diario, array $diasComFalta, float $valorPorPonto, ?array $regraAplicada = null): array
    {
        // Para tipo M (Misto), ratear valor fixo entre dias efetivamente trabalhados
        $tipoRegra = $regraAplicada['tipo'] ?? null;
        $valorFixo = floatval($regraAplicada['valor_fixo'] ?? 0);
        $valorFixoDiario = 0;
        if ($tipoRegra === 'M' && $valorFixo > 0 && count($diario) > 0) {
            // Contar dias sem falta integral
            $diasEfetivos = 0;
            foreach ($diario as $dia) {
                $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
                $temFaltaIntegral = $dataStr && isset($diasComFalta[$dataStr]) && $diasComFalta[$dataStr]['tipo'] === 'I';
                if (!$temFaltaIntegral) {
                    $diasEfetivos++;
                }
            }
            if ($diasEfetivos > 0) {
                $valorFixoDiario = $valorFixo / $diasEfetivos;
            }
        }

        foreach ($diario as &$dia) {
            // Normalizar campo DATA
            $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
            $dia['DATA'] = $dataStr;
            $pontosDia = floatval($dia['TOTAL_PONTOS'] ?? 0);
            $dia['TOTAL_PONTOS_ORIGINAL'] = $pontosDia;
            
            // Verificar falta
            if ($dataStr && isset($diasComFalta[$dataStr])) {
                $dia['TEM_FALTA'] = true;
                $dia['TIPO_FALTA'] = $diasComFalta[$dataStr]['tipo'];
                $dia['MOTIVO_FALTA'] = $diasComFalta[$dataStr]['motivo'];
                
                if ($diasComFalta[$dataStr]['tipo'] === 'I') {
                    $dia['PONTOS_COM_DESCONTO'] = 0;
                    $dia['COMISSAO_DIA'] = 0;
                } else {
                    $pontosComDesconto = $pontosDia * 0.5;
                    $dia['PONTOS_COM_DESCONTO'] = $pontosComDesconto;
                    $dia['COMISSAO_DIA'] = ($pontosComDesconto * $valorPorPonto) + $valorFixoDiario;
                }
            } else {
                $dia['TEM_FALTA'] = false;
                $dia['TIPO_FALTA'] = null;
                $dia['MOTIVO_FALTA'] = null;
                $dia['PONTOS_COM_DESCONTO'] = $pontosDia;
                $dia['COMISSAO_DIA'] = ($pontosDia * $valorPorPonto) + $valorFixoDiario;
            }
        }

        return $diario;
    }
}
