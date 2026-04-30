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
use src\models\Comissao\Retrabalho;
use src\models\Comissao\Vinculo;
use src\models\Comissao\VinculoData;

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
     * Cache de mapa de alocação por empresa, para evitar consultas repetidas dentro
     * de uma mesma requisição.
     */
    private static $cacheAlocacao = [];

    /**
     * Retorna o mapa funcId => alocação (cacheado por empresa).
     */
    private static function mapaAlocacao(int $emprId): array
    {
        if (!array_key_exists($emprId, self::$cacheAlocacao)) {
            self::$cacheAlocacao[$emprId] = Vinculo::getAlocacaoPorFuncionario($emprId);
        }
        return self::$cacheAlocacao[$emprId];
    }

    /**
     * Acrescenta os campos ALOCACAO_COD, ALOCACAO_DESC e ALOCACAO em cada linha.
     * Não altera valores existentes nem influencia cálculos.
     */
    private static function enriquecerComAlocacao(array $rows, int $emprId, string $funcIdKey = 'FUNC_ID'): array
    {
        $mapa = self::mapaAlocacao($emprId);
        foreach ($rows as &$r) {
            if (!is_array($r)) continue;
            $fid = $r[$funcIdKey] ?? null;
            $info = ($fid !== null && isset($mapa[$fid])) ? $mapa[$fid] : null;
            $cod = $info['COD_CC'] ?? null;
            $desc = $info['CC_DESCRICAO'] ?? null;
            $r['ALOCACAO_COD'] = $cod;
            $r['ALOCACAO_DESC'] = $desc;
            $r['ALOCACAO'] = ($cod || $desc) ? trim(($cod ?? '') . ' - ' . ($desc ?? ''), ' -') : null;
        }
        unset($r);
        return $rows;
    }

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

        $apontamentos = self::enriquecerComAlocacao($apontamentos, $emprId, 'FUNC_ID');
        $produtividadePorFunc = self::enriquecerComAlocacao(array_values($produtividadePorFunc), $emprId, 'FUNC_ID');

        return [
            'resumo' => $resumo,
            'produtividade' => $produtividadePorFunc,
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
        $resultado = Comissao::calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionarios = $resultado['funcionarios'] ?? [];
        $periodoFormatado = date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
        
        $comissoes = [];
        foreach ($funcionarios as $func) {
            $faixaDesc = self::formatarDescricaoFaixa($func);
            
            // Determinar se é apoio e tipo de cálculo
            $diasApoio = $func['dias_apoio'] ?? 0;
            $diasNormais = $func['dias_normais'] ?? 0;
            $isApoio = $diasApoio > 0;
            $tipoCalculoApoio = $func['tipo_calculo_apoio'] ?? null; // T=Total, M=Média
            
            $comissoes[] = [
                'FUNC_ID' => $func['func_id'] ?? null,
                'CODIGO_FUNC' => $func['cod_func'] ?? '',
                'COD_FUNC' => $func['cod_func'] ?? '',
                'NOME_FUNC' => $func['nome_func'] ?? '',
                'PERIODO' => $periodoFormatado,
                'CENTRO_TRAB_ID' => $func['centro_trab_id'] ?? null,
                'CENTRO_TRABALHO' => $func['cod_centro'] ? ($func['cod_centro'] . ' - ' . $func['desc_centro']) : 'SEM CENTRO',
                'TOTAL_PONTOS' => $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0,
                'PONTOS_NORMAIS' => $func['pontos_normais'] ?? 0,
                'PONTOS_APOIO' => $func['pontos_apoio'] ?? 0,
                'VALOR_COMISSAO' => $func['valor_comissao_final'] ?? 0,
                'FAIXA_ID' => $func['faixa_aplicada']['id'] ?? null,
                'FAIXA_DESCRICAO' => $faixaDesc,
                'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                'DIAS_NORMAIS' => $diasNormais,
                'DIAS_APOIO' => $diasApoio,
                'DIAS_COM_FALTA' => $func['dias_com_falta'] ?? 0,
                'TEM_FALTA' => ($func['dias_com_falta'] ?? 0) > 0,
                'TEM_APOIO' => $isApoio,
                'TIPO_CALCULO_APOIO' => $tipoCalculoApoio, // T=Total, M=Média
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

        $comissoes = self::enriquecerComAlocacao(array_values($comissoes), $emprId, 'FUNC_ID');

        return [
            'resumo' => $resumo,
            'porCentro' => array_values($porCentro),
            'comissoes' => $comissoes
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
        $resultado = Comissao::calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

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
                'id_usuario' => $usuId
            ];
            
            if (Comissao::salvarCalculo($dadosCalc)) {
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
        $resultado = Comissao::calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

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
                'id_usuario' => $usuId
            ];
            
            if (Comissao::salvarCalculo($dadosCalc)) {
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

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
                if (Comissao::aprovar($idComissao, $usuId)) {
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
                    'id_usuario' => $usuId
                ];
                
                $novoId = Comissao::salvarCalculo($dadosCalc);
                if ($novoId) {
                    // Aprovar a comissão recém-criada
                    Comissao::aprovar($novoId, $usuId);
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

        foreach ($comissoes as $comissao) {
            $idComissao = $comissao['ID_COMISSAO'] ?? $comissao['id_comissao'] ?? null;
            
            if ($idComissao) {
                if (Comissao::cancelar($idComissao, $usuId, $motivo)) {
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
        
        // =============================================
        // VERIFICAR SE FUNCIONÁRIO É DO TIPO APOIO PERMANENTE
        // É APOIO se:
        // 1. TIPO_VINCULO = 'A' (explicitamente marcado), OU
        // 2. ID_RECURSO IS NULL (vinculado ao centro SEM máquina específica)
        // =============================================
        $tipoVinculoExplicito = ($vinculo['TIPO_VINCULO'] ?? 'N') === 'A';
        $semMaquina = $vinculo && empty($vinculo['ID_RECURSO']);
        $isApoioPermanente = $vinculo && ($tipoVinculoExplicito || $semMaquina);
        
        // Para funcionário de apoio permanente, verificar se tem configuração de tipo de cálculo nas datas
        $tipoCalculoVinculo = 'T'; // Padrão: Total
        if ($isApoioPermanente) {
            // Buscar datas de apoio para ver o tipo de cálculo configurado
            $datasApoioCheck = VinculoData::buscarDatasApoioBatch([$funcionarioId], $emprIdApontamentos, $dataInicio, $dataFim);
            $datasApoioFuncCheck = $datasApoioCheck[$funcionarioId] ?? [];
            
            // Se tiver alguma data configurada, usar o tipo de cálculo dela
            if (!empty($datasApoioFuncCheck)) {
                $primeiraData = reset($datasApoioFuncCheck);
                if (is_array($primeiraData)) {
                    $tipoCalculoVinculo = $primeiraData['tipo_calculo'] ?? 'T';
                }
            }
        }
        
        $diario = [];
        
        // =============================================
        // BUSCAR FALTAS ANTES DE MONTAR O DIÁRIO
        // Para não incluir dias com falta integral no apoio
        // =============================================
        $faltasAntecipadas = FaltaFuncionario::verificarFaltasPeriodo($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos);
        $diasComFaltaAntecipada = self::mapearDiasComFalta($faltasAntecipadas);
        
        if ($isApoioPermanente && $centroTrabId) {
            // FUNCIONÁRIO DE APOIO: Buscar pontos TOTAIS do centro
            $pontosCentro = ApontamentoProducao::pontosTotaisCentroPorDia($dataInicio, $dataFim, [$centroTrabId], $emprIdApontamentos);
            $pontosCentroDia = $pontosCentro[$centroTrabId] ?? [];
            
            // Buscar quantidade de recursos para cálculo de média (se necessário)
            $recursosCentro = [];
            if ($tipoCalculoVinculo === 'M') {
                $recursosCentro = ApontamentoProducao::contarRecursosPorCentroDia($dataInicio, $dataFim, [$centroTrabId], $emprIdApontamentos);
            }
            
            // Montar diário com pontos do centro
            foreach ($pontosCentroDia as $data => $pontosTotais) {
                // VERIFICAR FALTA: Se tem falta INTEGRAL, não incluir o dia
                if (isset($diasComFaltaAntecipada[$data]) && $diasComFaltaAntecipada[$data]['tipo'] === 'I') {
                    // Falta integral - não incluir este dia (zera pontos)
                    $diario[] = [
                        'DATA_APONTAMENTO' => $data,
                        'DATA' => $data,
                        'TOTAL_PONTOS' => 0,
                        'TOTAL_PONTOS_ORIGINAL' => $pontosTotais,
                        'QTD_APONTAMENTOS' => 0,
                        'IS_APOIO' => true,
                        'TIPO_DIA' => 'APOIO',
                        'TIPO_CALCULO_APOIO' => 'TOTAL',
                        'CENTRO_APOIO_ID' => $centroTrabId,
                        'CENTRO_TRABALHO' => 'FALTA',
                        'RECURSO' => 'FALTA INTEGRAL',
                        'TEM_FALTA' => true,
                        'TIPO_FALTA' => 'I',
                        'MOTIVO_FALTA' => $diasComFaltaAntecipada[$data]['motivo'] ?? '-'
                    ];
                    continue;
                }
                
                $pontosDia = $pontosTotais;
                $tipoCalculoLabel = 'TOTAL';
                
                // Se tipo de cálculo for MÉDIA (M), divide pelos recursos
                if ($tipoCalculoVinculo === 'M') {
                    $qtdRecursos = $recursosCentro[$centroTrabId][$data] ?? 1;
                    $qtdRecursos = max($qtdRecursos, 1);
                    $pontosDia = round($pontosTotais / $qtdRecursos, 2);
                    $tipoCalculoLabel = 'MÉDIA';
                }
                
                // VERIFICAR FALTA PARCIAL: Desconta 50%
                $temFaltaParcial = isset($diasComFaltaAntecipada[$data]) && $diasComFaltaAntecipada[$data]['tipo'] !== 'I';
                if ($temFaltaParcial) {
                    $pontosDia = $pontosDia * 0.5;
                }
                
                $diario[] = [
                    'DATA_APONTAMENTO' => $data,
                    'DATA' => $data,
                    'TOTAL_PONTOS' => $pontosDia,
                    'TOTAL_PONTOS_ORIGINAL' => $pontosTotais,
                    'QTD_APONTAMENTOS' => 0,
                    'IS_APOIO' => true,
                    'TIPO_DIA' => 'APOIO',
                    'TIPO_CALCULO_APOIO' => $tipoCalculoLabel,
                    'CENTRO_APOIO_ID' => $centroTrabId,
                    'CENTRO_TRABALHO' => $tipoCalculoLabel,
                    'RECURSO' => 'APOIO - ' . $tipoCalculoLabel,
                    'TEM_FALTA' => $temFaltaParcial,
                    'TIPO_FALTA' => $temFaltaParcial ? $diasComFaltaAntecipada[$data]['tipo'] : null,
                    'MOTIVO_FALTA' => $temFaltaParcial ? ($diasComFaltaAntecipada[$data]['motivo'] ?? null) : null
                ];
            }
            
            // Ordenar por data (mais recente primeiro)
            usort($diario, function($a, $b) {
                return strcmp($b['DATA_APONTAMENTO'], $a['DATA_APONTAMENTO']);
            });
        } else {
            // FUNCIONÁRIO NORMAL: Buscar pontos por dia (apontamentos individuais)
            $diario = ApontamentoProducao::pontosPorDiaFuncionario($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);
        }

        // =============================================
        // BUSCAR DIAS DE APOIO ESPECÍFICOS (para funcionários normais com dias de apoio pontuais)
        // =============================================
        if (!$isApoioPermanente) {
            $datasApoio = VinculoData::buscarDatasApoioBatch([$funcionarioId], $emprIdApontamentos, $dataInicio, $dataFim);
            $datasApoioFunc = $datasApoio[$funcionarioId] ?? [];
            
            if (!empty($datasApoioFunc)) {
                // Extrair centros únicos dos dias de apoio (agora retorna objeto com centro e tipo_calculo)
                $centrosApoio = [];
                foreach ($datasApoioFunc as $dataApoio => $dadosApoio) {
                    // Compatibilidade: se for valor simples (legado), converter para array
                    if (!is_array($dadosApoio)) {
                        $datasApoioFunc[$dataApoio] = ['centro' => $dadosApoio, 'tipo_calculo' => 'T'];
                        $centrosApoio[] = $dadosApoio;
                    } else {
                        $centrosApoio[] = $dadosApoio['centro'];
                    }
                }
                $centrosApoio = array_unique($centrosApoio);
                
                // Buscar pontos totais do centro para os dias de apoio
                $pontosCentro = ApontamentoProducao::pontosTotaisCentroPorDia($dataInicio, $dataFim, $centrosApoio, $emprIdApontamentos);
                
                // Buscar quantidade de recursos por centro/dia (para cálculo de média)
                $recursosCentro = ApontamentoProducao::contarRecursosPorCentroDia($dataInicio, $dataFim, $centrosApoio, $emprIdApontamentos);
                
                // Mapear datas que já existem no diário
                $datasNoDiario = [];
                foreach ($diario as $dia) {
                    $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
                    if ($dataStr) {
                        $datasNoDiario[$dataStr] = true;
                    }
                }
                
                // Processar dias de apoio
                foreach ($datasApoioFunc as $dataApoio => $dadosApoio) {
                    $centroApoioId = $dadosApoio['centro'];
                    $tipoCalculo = $dadosApoio['tipo_calculo'] ?? 'T';
                    
                    $pontosTotaisDia = $pontosCentro[$centroApoioId][$dataApoio] ?? 0;
                    
                    // Se tipo de cálculo for MÉDIA (M), divide pelos recursos que produziram no dia
                    if ($tipoCalculo === 'M') {
                        $qtdRecursos = $recursosCentro[$centroApoioId][$dataApoio] ?? 1;
                        $qtdRecursos = max($qtdRecursos, 1); // Evita divisão por zero
                        $pontosDia = round($pontosTotaisDia / $qtdRecursos, 2);
                        $tipoCalculoLabel = 'MÉDIA';
                    } else {
                        $pontosDia = $pontosTotaisDia;
                        $tipoCalculoLabel = 'TOTAL';
                    }
                    
                    if (isset($datasNoDiario[$dataApoio])) {
                        // Dia já existe - atualizar para usar pontos do centro e marcar como apoio
                        foreach ($diario as &$dia) {
                            $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
                            if ($dataStr === $dataApoio) {
                                $dia['TOTAL_PONTOS'] = $pontosDia;
                                $dia['IS_APOIO'] = true;
                                $dia['TIPO_DIA'] = 'APOIO';
                                $dia['TIPO_CALCULO_APOIO'] = $tipoCalculoLabel;
                                $dia['CENTRO_APOIO_ID'] = $centroApoioId;
                                $dia['CENTRO_TRABALHO'] = $tipoCalculoLabel;
                                $dia['RECURSO'] = $tipoCalculoLabel;
                                break;
                            }
                        }
                        unset($dia);
                    } else {
                        // Dia não existe - adicionar como novo registro de apoio
                        $diario[] = [
                            'DATA_APONTAMENTO' => $dataApoio,
                            'DATA' => $dataApoio,
                            'TOTAL_PONTOS' => $pontosDia,
                            'QTD_APONTAMENTOS' => 0,
                            'IS_APOIO' => true,
                            'TIPO_DIA' => 'APOIO',
                            'TIPO_CALCULO_APOIO' => $tipoCalculoLabel,
                            'CENTRO_APOIO_ID' => $centroApoioId,
                            'CENTRO_TRABALHO' => $tipoCalculoLabel,
                            'RECURSO' => $tipoCalculoLabel
                        ];
                    }
                }
                
                // Reordenar por data (mais recente primeiro)
                usort($diario, function($a, $b) {
                    $dataA = $a['DATA_APONTAMENTO'] ?? $a['DATA'] ?? '';
                    $dataB = $b['DATA_APONTAMENTO'] ?? $b['DATA'] ?? '';
                    return strcmp($dataB, $dataA);
                });
            }
        } // Fim do if (!$isApoioPermanente)
        
        // Marcar dias normais (que não são apoio)
        foreach ($diario as &$dia) {
            if (!isset($dia['IS_APOIO'])) {
                $dia['IS_APOIO'] = false;
                $dia['TIPO_DIA'] = 'NORMAL';
            }
        }
        unset($dia);

        // Buscar apontamentos detalhados (vinculados ao funcionário)
        $apontamentos = ApontamentoProducao::listarApontamentosVinculados($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos, $centroTrabId);
        
        // Marcar apontamentos como vinculados ao funcionário
        foreach ($apontamentos as &$apt) {
            $apt['ORIGEM'] = 'FUNCIONARIO';
            $apt['SEM_VINCULO'] = false;
        }
        unset($apt);

        // =============================================
        // BUSCAR APONTAMENTOS DO CENTRO PARA DIAS DE APOIO
        // Para mostrar o que foi produzido no centro mesmo sem vínculo direto
        // =============================================
        $diasApoioArray = [];
        $centrosApoio = [];
        foreach ($diario as $dia) {
            if (($dia['IS_APOIO'] ?? false) === true) {
                $dataApoio = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
                $centroApoioId = $dia['CENTRO_APOIO_ID'] ?? $centroTrabId ?? null;
                if ($dataApoio && $centroApoioId) {
                    $diasApoioArray[] = $dataApoio;
                    $centrosApoio[$dataApoio] = $centroApoioId;
                }
            }
        }
        
        // Se existem dias de apoio, buscar apontamentos do centro
        $apontamentosCentro = [];
        if (!empty($diasApoioArray)) {
            // Buscar apontamentos do centro para cada dia de apoio
            foreach ($diasApoioArray as $dataApoio) {
                $centroId = $centrosApoio[$dataApoio] ?? $centroTrabId;
                if ($centroId) {
                    $aptsDia = ApontamentoProducao::produtividadeDiaria($dataApoio, $emprIdApontamentos, null, $centroId, null);
                    
                    if (!empty($aptsDia)) {
                        // Agrupar por item
                        $agrupadoDia = [];
                        foreach ($aptsDia as $apt) {
                            $itemId = $apt['ID_ITEM'] ?? 0;
                            $mascaraId = $apt['ID_MASCARA'] ?? 0;
                            $key = $itemId . '_' . $mascaraId;
                            
                            if (!isset($agrupadoDia[$key])) {
                                $agrupadoDia[$key] = [
                                    'ID_ITEM' => $itemId,
                                    'COD_ITEM' => $apt['COD_ITEM'] ?? '-',
                                    'DESC_ITEM' => $apt['DESC_ITEM'] ?? '-',
                                    'ID_MASCARA' => $mascaraId ?: null,
                                    'CENTRO_TRAB_ID' => $centroId,
                                    'DESC_CENTRO' => $apt['DESC_CENTRO'] ?? '-',
                                    'RECURSO' => $apt['RECURSO'] ?? $apt['DESC_MAQUINA'] ?? 'CENTRO',
                                    'QTD_APONTAMENTOS' => 0,
                                    'TOTAL_QUANTIDADE' => 0,
                                    'PONTOS_UP' => floatval($apt['PONTOS_UP'] ?? 0),
                                    'TOTAL_PONTOS' => 0,
                                    'TEM_PONTUACAO' => $apt['TEM_PONTUACAO'] ?? 'N',
                                    'DATA_APONTAMENTO' => $dataApoio,
                                    'ORIGEM' => 'CENTRO',
                                    'SEM_VINCULO' => true,
                                    'TIPO_VINCULO' => 'A'
                                ];
                            }
                            
                            $agrupadoDia[$key]['QTD_APONTAMENTOS'] += intval($apt['QTD_APONTAMENTOS'] ?? 1);
                            $agrupadoDia[$key]['TOTAL_QUANTIDADE'] += floatval($apt['QUANTIDADE'] ?? $apt['TOTAL_QUANTIDADE'] ?? 0);
                        }
                        
                        // Calcular pontos totais
                        foreach ($agrupadoDia as &$item) {
                            $item['TOTAL_PONTOS'] = round($item['TOTAL_QUANTIDADE'] * $item['PONTOS_UP'], 4);
                        }
                        unset($item);
                        
                        $apontamentosCentro = array_merge($apontamentosCentro, array_values($agrupadoDia));
                    }
                }
            }
        }

        // Verificar faltas
        $faltas = FaltaFuncionario::verificarFaltasPeriodo($funcionarioId, $dataInicio, $dataFim, $emprIdApontamentos);
        $diasComFalta = self::mapearDiasComFalta($faltas);

        // =============================================
        // SEPARAR PONTOS NORMAIS E PONTOS DE APOIO
        // =============================================
        $pontosNormais = 0;
        $pontosApoio = 0;
        $diasNormais = 0;
        $diasApoio = 0;
        
        foreach ($diario as $dia) {
            $dataStr = $dia['DATA_APONTAMENTO'] ?? $dia['DATA'] ?? null;
            $pontos = floatval($dia['TOTAL_PONTOS'] ?? 0);
            $isApoio = $dia['IS_APOIO'] ?? false;
            
            // Verificar se a falta já foi aplicada (para APOIO permanente)
            $faltaJaAplicada = $dia['TEM_FALTA'] ?? false;
            
            // Aplicar desconto de falta
            // APENAS se a falta ainda não foi aplicada no diário
            $multiplicador = 1.0;
            if (!$faltaJaAplicada && $dataStr && isset($diasComFalta[$dataStr])) {
                if ($diasComFalta[$dataStr]['tipo'] === 'I') {
                    // Falta integral: zera os pontos
                    $multiplicador = 0;
                } else {
                    // Falta parcial: 50% de desconto
                    $multiplicador = 0.5;
                }
            }
            $pontosComDesconto = $pontos * $multiplicador;
            
            if ($isApoio) {
                $pontosApoio += $pontosComDesconto;
                $diasApoio++;
            } else {
                $pontosNormais += $pontosComDesconto;
                $diasNormais++;
            }
        }
        
        $totalPontosAposFalta = $pontosNormais + $pontosApoio;

        // =============================================
        // CALCULAR COMISSÃO SEPARADAMENTE: NORMAL + APOIO
        // =============================================
        
        // Primeiro verificar se tem regra específica
        $regraEspecifica = RegraFuncionario::buscarRegraAtiva($funcionarioId, $centroTrabId, $dataFim, $emprIdApontamentos);
        
        $valorComissaoBruto = 0;
        $faixaAplicada = null;
        $faixaApoioAplicada = null;
        $regraAplicada = null;
        
        if ($regraEspecifica) {
            // Usar regra específica (aplica sobre total de pontos)
            $regraAplicada = [
                'id' => $regraEspecifica['ID_REGRA'],
                'descricao' => $regraEspecifica['DESCRICAO'],
                'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                'valor' => $regraEspecifica['VALOR_COMISSAO'],
                'valor_fixo' => $regraEspecifica['VALOR_FIXO'] ?? null
            ];
            $valorComissaoBruto = RegraFuncionario::calcularComissao($totalPontosAposFalta, $regraEspecifica);
        } else {
            // Calcular separadamente para dias normais e dias de apoio
            
            // Comissão de dias normais (faixa NORMAL)
            $valorComissaoNormal = 0;
            if ($pontosNormais > 0) {
                $faixaNormal = FaixaComissao::buscarFaixaAplicavel($pontosNormais, $centroTrabId, $dataFim, 'N');
                if ($faixaNormal) {
                    $faixaAplicada = [
                        'id' => $faixaNormal['ID_FAIXA'],
                        'descricao' => $faixaNormal['DESCRICAO'],
                        'tipo' => $faixaNormal['TIPO'],
                        'valor' => $faixaNormal['VALOR_COMISSAO']
                    ];
                    
                    if ($faixaNormal['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
                        $valorComissaoNormal = $pontosNormais * ($faixaNormal['VALOR_COMISSAO'] / 100);
                    } elseif ($faixaNormal['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
                        $valorComissaoNormal = $pontosNormais * floatval($faixaNormal['VALOR_COMISSAO']);
                    } else {
                        $valorComissaoNormal = floatval($faixaNormal['VALOR_COMISSAO']);
                    }
                }
            }
            
            // Comissão de dias de apoio (faixa APOIO)
            $valorComissaoApoio = 0;
            if ($pontosApoio > 0) {
                $faixaApoio = FaixaComissao::buscarFaixaAplicavel($pontosApoio, $centroTrabId, $dataFim, 'A');
                if ($faixaApoio) {
                    $faixaApoioAplicada = [
                        'id' => $faixaApoio['ID_FAIXA'],
                        'descricao' => $faixaApoio['DESCRICAO'],
                        'tipo' => $faixaApoio['TIPO'],
                        'valor' => $faixaApoio['VALOR_COMISSAO']
                    ];
                    
                    if ($faixaApoio['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
                        $valorComissaoApoio = $pontosApoio * ($faixaApoio['VALOR_COMISSAO'] / 100);
                    } elseif ($faixaApoio['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
                        $valorComissaoApoio = $pontosApoio * floatval($faixaApoio['VALOR_COMISSAO']);
                    } else {
                        $valorComissaoApoio = floatval($faixaApoio['VALOR_COMISSAO']);
                    }
                    
                    // Se não tem faixa normal, usar faixa apoio como principal
                    if (!$faixaAplicada) {
                        $faixaAplicada = $faixaApoioAplicada;
                    }
                }
            }
            
            $valorComissaoBruto = $valorComissaoNormal + $valorComissaoApoio;
        }
        
        // Calcular desconto de retrabalho
        $retrabalhos = Retrabalho::buscarPorFuncionariosPeriodo(
            [$funcionarioId], $dataInicio, $dataFim, $emprIdApontamentos
        );
        
        $descontoRetrabalho = 0;
        $totalPontosRetrabalho = 0;
        foreach ($retrabalhos as $ret) {
            $pontosRet = floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
            $totalPontosRetrabalho += $pontosRet;
            $impacto = Retrabalho::calcularImpacto(
                $valorComissaoBruto,
                $pontosRet,
                $ret['TIPO_IMPACTO'],
                $ret['VALOR_IMPACTO']
            );
            $descontoRetrabalho += $impacto['valor_desconto'];
        }
        
        $valorComissaoFinal = max(0, $valorComissaoBruto - $descontoRetrabalho);
        
        // Montar resultado similar ao calcularComissaoPreCalculada
        $resultadoComissao = [
            'valor_comissao_bruto' => round($valorComissaoBruto, 2),
            'valor_comissao_final' => round($valorComissaoFinal, 2),
            'desconto_retrabalho' => round($descontoRetrabalho, 2),
            'faixa_aplicada' => $faixaAplicada,
            'faixa_apoio_aplicada' => $faixaApoioAplicada,
            'regra_aplicada' => $regraAplicada,
            'usa_regra_especifica' => $regraEspecifica ? true : false,
            'pontos_normais' => round($pontosNormais, 2),
            'pontos_apoio' => round($pontosApoio, 2),
            'dias_normais' => $diasNormais,
            'dias_apoio' => $diasApoio
        ];
        
        $totalComissao = $resultadoComissao['valor_comissao_final'] ?? 0;
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
            'TOTAL_APONTAMENTOS_CENTRO' => count($apontamentosCentro),
            'TOTAL_PONTOS' => $totalPontos,
            'TOTAL_PONTOS_APOS_FALTA' => $totalPontosAposFalta,
            'PONTOS_NORMAIS' => $resultadoComissao['pontos_normais'] ?? $pontosNormais,
            'PONTOS_APOIO' => $resultadoComissao['pontos_apoio'] ?? $pontosApoio,
            'TOTAL_COMISSAO' => $totalComissao,
            'VALOR_COMISSAO_BRUTO' => $resultadoComissao['valor_comissao_bruto'] ?? 0,
            'DESCONTO_RETRABALHO' => $resultadoComissao['desconto_retrabalho'] ?? 0,
            'MEDIA_DIARIA' => count($diario) > 0 ? $totalPontos / count($diario) : 0,
            'DIAS_TRABALHADOS' => count($diario),
            'DIAS_NORMAIS' => $resultadoComissao['dias_normais'] ?? $diasNormais,
            'DIAS_APOIO' => $resultadoComissao['dias_apoio'] ?? $diasApoio,
            'DIAS_COM_FALTA' => count($faltas),
            'FAIXA_APLICADA' => $faixaAplicada,
            'FAIXA_APOIO_APLICADA' => $resultadoComissao['faixa_apoio_aplicada'] ?? null,
            'REGRA_APLICADA' => $regraAplicada,
            'USA_REGRA_ESPECIFICA' => $resultadoComissao['usa_regra_especifica'] ?? false,
            'VALOR_POR_PONTO' => $valorPorPonto,
            'VALOR_FIXO' => $valorFixo,
            'TIPO_REGRA' => $tipoRegra
        ];

        // Buscar comissões já salvas
        $comissoesSalvas = Comissao::listarCalculos(null, $dataInicio, $dataFim, $funcionarioId);

        // Alocação do funcionário (apenas para exibição no relatório)
        $mapaAloc = self::mapaAlocacao($emprId);
        $alocInfo = $mapaAloc[$funcionarioId] ?? null;
        $funcionario['ALOCACAO_COD'] = $alocInfo['COD_CC'] ?? null;
        $funcionario['ALOCACAO_DESC'] = $alocInfo['CC_DESCRICAO'] ?? null;
        $funcionario['ALOCACAO'] = $alocInfo
            ? trim(($alocInfo['COD_CC'] ?? '') . ' - ' . ($alocInfo['CC_DESCRICAO'] ?? ''), ' -')
            : null;

        return [
            'funcionario' => $funcionario,
            'resumo' => $resumo,
            'diario' => $diario,
            'apontamentos' => $apontamentos,
            'apontamentosCentro' => $apontamentosCentro,
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
        $resultado = Comissao::calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId, $centroTrabId);

        $funcionariosBatch = $resultado['funcionarios'] ?? [];
        
        $funcionarios = [];
        $totalPontos = 0;
        $totalComissao = 0;
        $totalComFalta = 0;

        foreach ($funcionariosBatch as $func) {
            $pontosFunc = $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0;
            $comissaoFunc = $func['valor_comissao_final'] ?? 0;
            $diasComFalta = $func['dias_com_falta'] ?? 0;
            $diasApoio = $func['dias_apoio'] ?? 0;

            $faixaDesc = self::formatarDescricaoFaixa($func);

            $funcionarios[] = [
                'FUNC_ID' => $func['func_id'] ?? null,
                'COD_FUNC' => $func['cod_func'] ?? '',
                'NOME_FUNC' => $func['nome_func'] ?? '',
                'TOTAL_PONTOS' => $pontosFunc,
                'PONTOS_NORMAIS' => $func['pontos_normais'] ?? $pontosFunc,
                'PONTOS_APOIO' => $func['pontos_apoio'] ?? 0,
                'VALOR_COMISSAO' => $comissaoFunc,
                'FAIXA_DESCRICAO' => $faixaDesc,
                'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                'DIAS_NORMAIS' => $func['dias_normais'] ?? $func['dias_trabalhados'] ?? 0,
                'DIAS_APOIO' => $diasApoio,
                'DIAS_COM_FALTA' => $diasComFalta,
                'TEM_FALTA' => $diasComFalta > 0,
                'TEM_APOIO' => $diasApoio > 0,
                'USA_REGRA_ESPECIFICA' => $func['usa_regra_especifica'] ?? false,
                'TIPO_VINCULO' => $func['tipo_vinculo'] ?? 'N'
            ];

            $totalPontos += $pontosFunc;
            $totalComissao += $comissaoFunc;
            if ($diasComFalta > 0) $totalComFalta++;
        }

        // Ordenar por nome
        usort($funcionarios, fn($a, $b) => strcmp($a['NOME_FUNC'], $b['NOME_FUNC']));

        $funcionarios = self::enriquecerComAlocacao($funcionarios, $emprId, 'FUNC_ID');

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
        $diasApoio = $func['dias_apoio'] ?? 0;
        $diasNormais = $func['dias_normais'] ?? 0;
        $isApoio = ($func['tipo_vinculo'] ?? 'N') === 'A';
        
        // Se tem faixa aplicada (normal ou apoio)
        $descricoes = [];
        
        if (!empty($func['faixa_aplicada']['descricao'])) {
            $descricoes[] = $func['faixa_aplicada']['descricao'];
        }
        
        // Se tem faixa de apoio diferente e tem dias de apoio
        if (!empty($func['faixa_apoio_aplicada']['descricao']) && $diasApoio > 0) {
            if (empty($descricoes) || $func['faixa_apoio_aplicada']['descricao'] !== ($func['faixa_aplicada']['descricao'] ?? '')) {
                $descricoes[] = $func['faixa_apoio_aplicada']['descricao'];
            }
        }
        
        if (!empty($descricoes)) {
            return implode(' + ', $descricoes);
        }
        
        if (!empty($func['regra_aplicada'])) {
            $tipoLabel = self::labelTipoComissao($func['regra_aplicada']['tipo'] ?? '');
            if ($isApoio || $diasApoio > 0) {
                return 'APOIO - ' . $tipoLabel;
            }
            return 'Regra: ' . ($func['regra_aplicada']['descricao'] ?? $tipoLabel);
        }
        
        if ($isApoio || $diasApoio > 0) {
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

    /**
     * Relatório de faltas por funcionário no período
     */
    public static function getRelatorioFaltas(string $dataInicio, string $dataFim, int $emprId, ?int $funcionarioId = null, ?string $tipoFalta = null): array
    {
        // Buscar faltas com filtros
        $filtros = [
            'id_empr' => $emprId,
            'dt_inicio' => $dataInicio,
            'dt_fim' => $dataFim
        ];
        
        if ($funcionarioId) {
            $filtros['id_funcionario'] = $funcionarioId;
        }
        
        $faltas = FaltaFuncionario::listar($filtros);
        
        // Filtrar por tipo se especificado
        if ($tipoFalta) {
            $faltas = array_filter($faltas, fn($f) => ($f['TIPO_FALTA'] ?? '') === $tipoFalta);
            $faltas = array_values($faltas);
        }
        
        // Agrupar por funcionário para resumo
        $funcionariosComFalta = [];
        $totalIntegral = 0;
        $totalParcial = 0;
        
        foreach ($faltas as $falta) {
            $funcId = $falta['ID_FUNCIONARIO'] ?? null;
            if (!isset($funcionariosComFalta[$funcId])) {
                $funcionariosComFalta[$funcId] = [
                    'nome' => $falta['NOME_FUNCIONARIO'] ?? $falta['NOME'] ?? '-',
                    'cod_func' => $falta['COD_FUNC'] ?? '-',
                    'total_faltas' => 0,
                    'faltas_integral' => 0,
                    'faltas_parcial' => 0
                ];
            }
            
            $funcionariosComFalta[$funcId]['total_faltas']++;
            
            if (($falta['TIPO_FALTA'] ?? '') === 'I') {
                $funcionariosComFalta[$funcId]['faltas_integral']++;
                $totalIntegral++;
            } else {
                $funcionariosComFalta[$funcId]['faltas_parcial']++;
                $totalParcial++;
            }
        }
        
        // Ordenar funcionários por total de faltas (decrescente)
        uasort($funcionariosComFalta, fn($a, $b) => $b['total_faltas'] - $a['total_faltas']);

        // Alocação por funcionário (apenas para exibição)
        $mapaAloc = self::mapaAlocacao($emprId);
        foreach ($faltas as &$f) {
            $fid = $f['ID_FUNCIONARIO'] ?? null;
            $info = $fid !== null ? ($mapaAloc[$fid] ?? null) : null;
            $f['ALOCACAO_COD'] = $info['COD_CC'] ?? null;
            $f['ALOCACAO_DESC'] = $info['CC_DESCRICAO'] ?? null;
            $f['ALOCACAO'] = $info ? trim(($info['COD_CC'] ?? '') . ' - ' . ($info['CC_DESCRICAO'] ?? ''), ' -') : null;
        }
        unset($f);
        foreach ($funcionariosComFalta as $fid => &$fc) {
            $info = $mapaAloc[$fid] ?? null;
            $fc['alocacao_cod'] = $info['COD_CC'] ?? null;
            $fc['alocacao_desc'] = $info['CC_DESCRICAO'] ?? null;
            $fc['alocacao'] = $info ? trim(($info['COD_CC'] ?? '') . ' - ' . ($info['CC_DESCRICAO'] ?? ''), ' -') : null;
        }
        unset($fc);
        
        return [
            'faltas' => $faltas,
            'resumo' => [
                'total_faltas' => count($faltas),
                'total_integral' => $totalIntegral,
                'total_parcial' => $totalParcial,
                'total_funcionarios' => count($funcionariosComFalta),
                'por_funcionario' => array_values($funcionariosComFalta)
            ]
        ];
    }
}
