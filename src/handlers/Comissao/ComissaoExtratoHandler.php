<?php

namespace src\handlers\Comissao;

use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\Vinculo;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\CentroTrabalho;
use src\models\Comissao\VinculoData;

/**
 * Handler para Extrato Analítico de Comissão
 * 
 * Gera relatório dia a dia por funcionário mostrando:
 * - Pontos do dia
 * - Tipo de ganho (Normal, Apoio, Falta)
 * - Centro de Trabalho
 * - Máquina/Recurso
 * - Valor do ponto da regra/faixa
 * - Valor da comissão por dia
 * 
 * FILTRO: Mostra apenas dias com apontamento ou apoio
 */
class ComissaoExtratoHandler
{
    /**
     * Obter extrato analítico por centro de trabalho
     * 
     * @param string $dataInicio Data início (Y-m-d)
     * @param string $dataFim Data fim (Y-m-d)
     * @param int $emprId ID da empresa
     * @param int $centroTrabId ID do centro de trabalho
     * @return array
     */
    public static function getExtratoAnalitico(string $dataInicio, string $dataFim, int $emprId, int $centroTrabId): array
    {
        // 1. Buscar funcionários vinculados ao centro de trabalho
        $vinculos = Vinculo::listar([
            'id_empr' => $emprId,
            'id_centro_trab' => $centroTrabId,
            'ativo' => 'S'
        ]);
        
        if (empty($vinculos)) {
            return [
                'success' => true,
                'data' => [],
                'resumo' => self::criarResumoVazio(),
                'message' => 'Nenhum funcionário vinculado ao centro de trabalho'
            ];
        }
        
        // Extrair IDs únicos de funcionários
        $funcIds = array_unique(array_map(fn($v) => $v['ID_FUNCIONARIO'], $vinculos));
        
        // Criar mapa de vínculo: funcId => dados do vínculo
        $mapaVinculos = [];
        foreach ($vinculos as $v) {
            $funcId = $v['ID_FUNCIONARIO'];
            if (!isset($mapaVinculos[$funcId])) {
                $mapaVinculos[$funcId] = [
                    'ID_FUNCIONARIO' => $funcId,
                    'COD_FUNCIONARIO' => $v['COD_FUNC'] ?? '',
                    'NOME_FUNCIONARIO' => $v['FUNCIONARIO_NOME'] ?? '',
                    'COD_CENTRO' => $v['COD_CENTRO'] ?? '',
                    'DESC_CENTRO' => $v['CENTRO_DESCRICAO'] ?? '',
                    'COD_RECURSO' => $v['COD_MAQUINA'] ?? '',
                    'DESC_RECURSO' => $v['RECURSO_DESCRICAO'] ?? '',
                    'TIPO_VINCULO' => $v['TIPO_VINCULO'] ?? 'N'
                ];
            }
        }
        
        // 2. Buscar pontos por dia para todos os funcionários (batch)
        $pontosPorFuncDia = ApontamentoProducao::pontosPorDiaBatch(
            $dataInicio, $dataFim, $funcIds, $emprId, $centroTrabId
        );
        
        // 3. Buscar faltas de todos os funcionários no período (batch)
        $faltasPorFunc = FaltaFuncionario::verificarFaltasPeriodoBatch(
            $funcIds, $dataInicio, $dataFim, $emprId
        );
        
        // Mapear faltas por funcId + data
        $mapaFaltas = [];
        foreach ($faltasPorFunc as $funcId => $faltas) {
            foreach ($faltas as $falta) {
                $dtFalta = substr($falta['DT_FALTA'] ?? '', 0, 10);
                if ($dtFalta) {
                    $mapaFaltas[$funcId][$dtFalta] = [
                        'TIPO_FALTA' => $falta['TIPO_FALTA'] ?? 'I',
                        'MOTIVO' => $falta['MOTIVO'] ?? null
                    ];
                }
            }
        }
        
        // 4. Buscar regras específicas por funcionário (batch)
        $regrasEspecificas = RegraFuncionario::buscarRegraAtivaBatch($funcIds, $centroTrabId, $dataFim, $emprId);
        
        // 5. Buscar faixas aplicáveis para o centro (Normal e Apoio)
        $faixasNormais = FaixaComissao::listarAtivas($emprId, $centroTrabId);
        
        // 6. Buscar dias de apoio para todos os funcionários (batch)
        $datasApoioPorFunc = VinculoData::buscarDatasApoioBatch($funcIds, $emprId, $dataInicio, $dataFim);
        
        // 7. Buscar pontos totais do centro por dia (para dias de apoio)
        $pontosTotaisCentro = ApontamentoProducao::pontosTotaisCentroPorDia(
            $dataInicio, $dataFim, [$centroTrabId], $emprId
        );
        
        // 8. Buscar quantidade de recursos por dia (para cálculo de média)
        $recursosPorCentroDia = ApontamentoProducao::contarRecursosPorCentroDia(
            $dataInicio, $dataFim, [$centroTrabId], $emprId
        );
        
        // 9. Montar extrato analítico
        $extrato = [];
        $resumo = [
            'total_funcionarios' => count($funcIds),
            'total_dias' => 0, // Será contado apenas dias com apontamento
            'total_pontos' => 0,
            'total_dias_normais' => 0,
            'total_dias_apoio' => 0,
            'total_dias_falta_integral' => 0,
            'total_dias_falta_parcial' => 0,
            'total_valor_estimado' => 0
        ];
        
        foreach ($funcIds as $funcId) {
            $vinculo = $mapaVinculos[$funcId] ?? null;
            if (!$vinculo) continue;
            
            $funcionario = [
                'id' => $funcId,
                'codigo' => $vinculo['COD_FUNCIONARIO'] ?? '',
                'nome' => $vinculo['NOME_FUNCIONARIO'] ?? '',
                'centro_trabalho' => ($vinculo['COD_CENTRO'] ?? '') . ' - ' . ($vinculo['DESC_CENTRO'] ?? ''),
                'recurso' => ($vinculo['COD_RECURSO'] ?? '') . ' - ' . ($vinculo['DESC_RECURSO'] ?? ''),
                'tipo_vinculo' => $vinculo['TIPO_VINCULO'] ?? 'N' // N=Normal, A=Apoio
            ];
            
            // Mapear pontos por data para este funcionário
            $pontosPorData = [];
            $pontosFunc = $pontosPorFuncDia[$funcId] ?? [];
            foreach ($pontosFunc as $p) {
                $data = substr($p['DATA_APONTAMENTO'] ?? '', 0, 10);
                if ($data) {
                    $pontosPorData[$data] = floatval($p['TOTAL_PONTOS'] ?? 0);
                }
            }
            
            // Buscar dias de apoio deste funcionário
            $datasApoio = $datasApoioPorFunc[$funcId] ?? [];
            
            // Verificar se tem regra específica
            $temRegraEspecifica = isset($regrasEspecificas[$funcId]);
            $regraFunc = $regrasEspecificas[$funcId] ?? null;
            
            // Determinar valor do ponto para memória de cálculo
            $valorPontoNormal = 0;
            $valorPontoApoio = 0;
            $faixaNormalDesc = null;
            $faixaApoioDesc = null;
            
            if ($temRegraEspecifica) {
                // Regra específica: pegar valor por ponto
                $valorPontoNormal = floatval($regraFunc['VALOR_COMISSAO'] ?? 0);
                $valorPontoApoio = $valorPontoNormal;
                $faixaNormalDesc = $regraFunc['DESCRICAO'] ?? 'Regra Específica';
                $faixaApoioDesc = $faixaNormalDesc;
            } else {
                // Buscar faixa para tipo Normal
                $faixaNormal = self::buscarFaixaPorTipo($faixasNormais, 'N');
                if ($faixaNormal) {
                    $valorPontoNormal = self::getValorPontoPorFaixa($faixaNormal);
                    $faixaNormalDesc = $faixaNormal['DESCRICAO'] ?? '';
                }
                
                // Buscar faixa para tipo Apoio
                $faixaApoio = self::buscarFaixaPorTipo($faixasNormais, 'A');
                if ($faixaApoio) {
                    $valorPontoApoio = self::getValorPontoPorFaixa($faixaApoio);
                    $faixaApoioDesc = $faixaApoio['DESCRICAO'] ?? '';
                } else {
                    // Se não tiver faixa de apoio, usa a normal
                    $valorPontoApoio = $valorPontoNormal;
                    $faixaApoioDesc = $faixaNormalDesc;
                }
            }
            
            $totalPontosFuncionario = 0;
            $totalPontosNormais = 0;
            $totalPontosApoio = 0;
            $diasDetalhe = [];
            $diasNormaisFunc = 0;
            $diasApoioFunc = 0;
            
            // Coletar todas as datas com apontamento ou apoio
            $datasComApontamento = array_keys($pontosPorData);
            $datasComApoio = array_keys($datasApoio);
            $todasDatas = array_unique(array_merge($datasComApontamento, $datasComApoio));
            sort($todasDatas);
            
            foreach ($todasDatas as $dia) {
                $pontosDia = $pontosPorData[$dia] ?? 0;
                $faltaDia = $mapaFaltas[$funcId][$dia] ?? null;
                $isDiaApoio = isset($datasApoio[$dia]);
                
                $status = 'NORMAL';
                $tipoFalta = null;
                $pontosAplicados = $pontosDia;
                $motivoFalta = null;
                $valorComissaoDia = 0;
                $valorPontoUsado = $valorPontoNormal;
                $tipoCalculo = null;
                
                // Verificar se é dia de apoio
                if ($isDiaApoio) {
                    $dadosApoioDia = $datasApoio[$dia];
                    $tipoCalculo = is_array($dadosApoioDia) ? ($dadosApoioDia['tipo_calculo'] ?? 'T') : 'T';
                    $centroApoioId = is_array($dadosApoioDia) ? ($dadosApoioDia['centro'] ?? $centroTrabId) : $centroTrabId;
                    
                    // Buscar pontos totais do centro para o dia
                    $pontosTotaisDia = floatval($pontosTotaisCentro[$centroApoioId][$dia] ?? 0);
                    
                    // Se tipo MÉDIA, divide pelos recursos que produziram no dia
                    if ($tipoCalculo === 'M' && isset($recursosPorCentroDia[$centroApoioId][$dia])) {
                        $qtdRecursos = max(1, (int)$recursosPorCentroDia[$centroApoioId][$dia]);
                        $pontosAplicados = round($pontosTotaisDia / $qtdRecursos, 2);
                    } else {
                        $pontosAplicados = $pontosTotaisDia;
                    }
                    
                    $status = 'APOIO';
                    $valorPontoUsado = $valorPontoApoio;
                    $diasApoioFunc++;
                    $resumo['total_dias_apoio']++;
                    $totalPontosApoio += $pontosAplicados;
                } else {
                    // Dia NORMAL
                    $diasNormaisFunc++;
                    $resumo['total_dias_normais']++;
                    $totalPontosNormais += $pontosAplicados;
                }
                
                // Verificar falta (aplica desconto mesmo em dias de apoio)
                if ($faltaDia) {
                    $tipoFalta = $faltaDia['TIPO_FALTA'];
                    $motivoFalta = $faltaDia['MOTIVO'];
                    
                    if ($tipoFalta === 'I') {
                        $status = 'FALTA_INTEGRAL';
                        $pontosAplicados = 0;
                        $resumo['total_dias_falta_integral']++;
                        // Descontar do resumo de dias normais/apoio
                        if ($isDiaApoio) {
                            $resumo['total_dias_apoio']--;
                            $diasApoioFunc--;
                        } else {
                            $resumo['total_dias_normais']--;
                            $diasNormaisFunc--;
                        }
                    } else {
                        $status = $isDiaApoio ? 'APOIO' : 'FALTA_PARCIAL';
                        $pontosAplicados = $pontosAplicados * 0.5;
                        $resumo['total_dias_falta_parcial']++;
                    }
                }
                
                $totalPontosFuncionario += $pontosAplicados;
                
                // Calcular valor da comissão do dia
                $valorComissaoDia = round($pontosAplicados * $valorPontoUsado, 2);
                
                $diasDetalhe[] = [
                    'data' => $dia,
                    'data_formatada' => date('d/m/Y', strtotime($dia)),
                    'dia_semana' => self::getDiaSemana($dia),
                    'pontos_brutos' => round($pontosDia, 2),
                    'pontos_aplicados' => round($pontosAplicados, 2),
                    'valor_ponto' => round($valorPontoUsado, 4),
                    'valor_comissao' => $valorComissaoDia,
                    'status' => $status,
                    'tipo_falta' => $tipoFalta,
                    'motivo_falta' => $motivoFalta,
                    'tipo_calculo' => $tipoCalculo // T=Total, M=Média (apenas para apoio)
                ];
                
                $resumo['total_dias']++;
            }
            
            // Calcular valor estimado total do funcionário
            $valorEstimado = 0;
            if ($temRegraEspecifica) {
                $valorEstimado = RegraFuncionario::calcularComissao($totalPontosFuncionario, $regraFunc);
            } else {
                // Calcular separado: dias normais e dias de apoio
                $valorNormal = $totalPontosNormais * $valorPontoNormal;
                $valorApoio = $totalPontosApoio * $valorPontoApoio;
                $valorEstimado = $valorNormal + $valorApoio;
            }
            
            $resumo['total_pontos'] += $totalPontosFuncionario;
            $resumo['total_valor_estimado'] += $valorEstimado;
            
            // Só adiciona funcionário se tiver dias com apontamento
            if (!empty($diasDetalhe)) {
                $extrato[] = [
                    'funcionario' => $funcionario,
                    'total_pontos' => round($totalPontosFuncionario, 2),
                    'pontos_normais' => round($totalPontosNormais, 2),
                    'pontos_apoio' => round($totalPontosApoio, 2),
                    'dias_normais' => $diasNormaisFunc,
                    'dias_apoio' => $diasApoioFunc,
                    'valor_estimado' => round($valorEstimado, 2),
                    'tem_regra_especifica' => $temRegraEspecifica,
                    'regra_descricao' => $regraFunc ? ($regraFunc['DESCRICAO'] ?? '') : null,
                    'memoria_calculo' => [
                        'valor_ponto_normal' => round($valorPontoNormal, 4),
                        'valor_ponto_apoio' => round($valorPontoApoio, 4),
                        'faixa_normal' => $faixaNormalDesc,
                        'faixa_apoio' => $faixaApoioDesc
                    ],
                    'dias' => $diasDetalhe
                ];
            }
        }
        
        // Ordenar por nome do funcionário
        usort($extrato, fn($a, $b) => strcmp($a['funcionario']['nome'], $b['funcionario']['nome']));
        
        return [
            'success' => true,
            'data' => $extrato,
            'resumo' => [
                'total_funcionarios' => count($extrato), // Apenas funcionários com apontamentos
                'total_dias' => $resumo['total_dias'],
                'total_pontos' => round($resumo['total_pontos'], 2),
                'total_dias_normais' => $resumo['total_dias_normais'],
                'total_dias_apoio' => $resumo['total_dias_apoio'],
                'total_dias_falta_integral' => $resumo['total_dias_falta_integral'],
                'total_dias_falta_parcial' => $resumo['total_dias_falta_parcial'],
                'total_valor_estimado' => round($resumo['total_valor_estimado'], 2)
            ],
            'periodo' => [
                'inicio' => $dataInicio,
                'fim' => $dataFim,
                'inicio_formatado' => date('d/m/Y', strtotime($dataInicio)),
                'fim_formatado' => date('d/m/Y', strtotime($dataFim))
            ]
        ];
    }
    
    /**
     * Buscar faixa por tipo de funcionário (N=Normal, A=Apoio, T=Todos)
     */
    private static function buscarFaixaPorTipo(array $faixas, string $tipo): ?array
    {
        // Primeiro busca faixa específica do tipo
        foreach ($faixas as $faixa) {
            $tipoFaixa = $faixa['TIPO_FUNCIONARIO'] ?? 'T';
            if ($tipoFaixa === $tipo) {
                return $faixa;
            }
        }
        // Se não encontrou, busca faixa "T" (Todos)
        foreach ($faixas as $faixa) {
            $tipoFaixa = $faixa['TIPO_FUNCIONARIO'] ?? 'T';
            if ($tipoFaixa === 'T') {
                return $faixa;
            }
        }
        // Retorna a primeira faixa disponível
        return $faixas[0] ?? null;
    }
    
    /**
     * Obter valor por ponto da faixa
     */
    private static function getValorPontoPorFaixa(array $faixa): float
    {
        $tipo = $faixa['TIPO'] ?? 'F';
        $valor = floatval($faixa['VALOR_COMISSAO'] ?? 0);
        
        // Tipo Q (Quantidade) = valor por ponto
        if ($tipo === 'Q') {
            return $valor;
        }
        
        // Outros tipos retornam o valor (será multiplicado pelo total de pontos)
        return $valor;
    }
    
    /**
     * Gerar array de dias entre duas datas
     */
    private static function gerarDiasPeriodo(string $dataInicio, string $dataFim): array
    {
        $dias = [];
        $current = strtotime($dataInicio);
        $end = strtotime($dataFim);
        
        while ($current <= $end) {
            $dias[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
        
        return $dias;
    }
    
    /**
     * Obter nome do dia da semana
     */
    private static function getDiaSemana(string $data): string
    {
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        return $dias[date('w', strtotime($data))];
    }
    
    /**
     * Criar resumo vazio
     */
    private static function criarResumoVazio(): array
    {
        return [
            'total_funcionarios' => 0,
            'total_dias' => 0,
            'total_pontos' => 0,
            'total_dias_normais' => 0,
            'total_dias_apoio' => 0,
            'total_dias_falta_integral' => 0,
            'total_dias_falta_parcial' => 0,
            'total_valor_estimado' => 0
        ];
    }
    
    /**
     * Buscar faixa aplicável para quantidade de pontos
     */
    private static function buscarFaixaAplicavel(float $pontos, array $faixas): ?array
    {
        foreach ($faixas as $faixa) {
            $min = floatval($faixa['QTD_MINIMA'] ?? 0);
            $max = floatval($faixa['QTD_MAXIMA'] ?? PHP_INT_MAX);
            
            if ($pontos >= $min && $pontos <= $max) {
                return $faixa;
            }
        }
        return null;
    }
    
    /**
     * Calcular valor baseado na faixa
     */
    private static function calcularValorFaixa(float $pontos, array $faixa): float
    {
        $tipo = $faixa['TIPO'] ?? 'F';
        $valor = floatval($faixa['VALOR_COMISSAO'] ?? 0);
        
        if ($tipo === 'P') { // Percentual
            return $pontos * ($valor / 100);
        } elseif ($tipo === 'Q') { // Por quantidade
            return $pontos * $valor;
        } else { // Fixo
            return $valor;
        }
    }
    
    /**
     * Exportar extrato para CSV
     */
    public static function exportarCSV(array $extrato): string
    {
        $output = "Data;Dia;Funcionario;Codigo;Centro Trabalho;Recurso;Pontos Brutos;Pontos Aplicados;Valor Ponto;Valor Comissao;Status;Tipo Calculo;Observacao\n";
        
        foreach ($extrato['data'] as $func) {
            $funcionario = $func['funcionario'];
            
            foreach ($func['dias'] as $dia) {
                $status = match($dia['status']) {
                    'NORMAL' => 'Normal',
                    'APOIO' => 'Apoio',
                    'FALTA_INTEGRAL' => 'Falta Integral',
                    'FALTA_PARCIAL' => 'Falta Parcial',
                    default => $dia['status']
                };
                
                $tipoCalc = '';
                if ($dia['status'] === 'APOIO' && isset($dia['tipo_calculo'])) {
                    $tipoCalc = $dia['tipo_calculo'] === 'M' ? 'Média' : 'Total';
                }
                
                $observacao = $dia['motivo_falta'] ?? '';
                if ($dia['tipo_falta'] === 'I') {
                    $observacao = 'Falta Integral';
                } elseif ($dia['tipo_falta'] === 'P') {
                    $observacao = 'Falta Parcial (50%)';
                }
                
                $output .= sprintf(
                    "%s;%s;%s;%s;%s;%s;%.2f;%.2f;%.4f;%.2f;%s;%s;%s\n",
                    $dia['data_formatada'],
                    $dia['dia_semana'],
                    $funcionario['nome'],
                    $funcionario['codigo'],
                    $funcionario['centro_trabalho'],
                    $funcionario['recurso'],
                    $dia['pontos_brutos'],
                    $dia['pontos_aplicados'],
                    $dia['valor_ponto'] ?? 0,
                    $dia['valor_comissao'] ?? 0,
                    $status,
                    $tipoCalc,
                    $observacao
                );
            }
            
            // Linha de total do funcionário
            $memoria = $func['memoria_calculo'] ?? [];
            $output .= sprintf(
                ";;TOTAL %s;;Dias Normais: %d;Dias Apoio: %d;%.2f;%.2f;;;R$ %.2f;;\n",
                $funcionario['nome'],
                $func['dias_normais'] ?? 0,
                $func['dias_apoio'] ?? 0,
                $func['total_pontos'] ?? 0,
                $func['total_pontos'] ?? 0,
                $func['valor_estimado'] ?? 0
            );
        }
        
        return $output;
    }
}
