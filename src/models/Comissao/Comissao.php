<?php

namespace src\models\Comissao;

use core\Database;
use Exception;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Retrabalho;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\FaixaComissao;
use src\models\Comissao\VinculoData;

/**
 * Model para Cálculo e Gestão de Comissões
 * 
 * Este model calcula as comissões baseadas na pontuação dos funcionários
 * e nas faixas de comissão configuradas.
 * 
 * Tabela customizada: FOCCO3I.TGAZIN_COMISSAO_CALC
 * 
 * Estrutura da tabela:
 * - ID_COMISSAO (PK)
 * - FUNCIONARIO_ID (FK para TFUNCIONARIOS.ID)
 * - FAIXA_ID (FK para TGAZIN_FAIXA_COMISSAO.ID_FAIXA)
 * - DT_INICIO
 * - DT_FIM
 * - TOTAL_PONTOS
 * - VALOR_COMISSAO
 * - STATUS (P=Pendente, A=Aprovado, C=Cancelado)
 * - DT_PROCESSAMENTO
 * - ID_USUARIO_PROC
 * - DT_APROVACAO
 * - ID_USUARIO_APROV
 * - OBSERVACAO
 * 
 * ORDEM DE PROCESSAMENTO DO CÁLCULO:
 * 1. Definir período
 * 2. Verificar faltas por dia (comissão zerada se tiver falta)
 * 3. Buscar apontamentos válidos (apenas com vínculo)
 * 4. Buscar retrabalho
 * 5. Verificar regra específica do funcionário
 * 6. Aplicar cálculo
 * 7. Consolidar comissão final
 */
class Comissao
{
    const STATUS_PENDENTE = 'P';
    const STATUS_APROVADO = 'A';
    const STATUS_CANCELADO = 'C';

    /**
     * NOVO MÉTODO DE CÁLCULO COM TODAS AS REGRAS
     * 
     * Processa comissão seguindo a ordem correta:
     * 1. Definir período
     * 2. Verificar faltas por dia
     * 3. Buscar apontamentos válidos
     * 4. Buscar retrabalho
     * 5. Verificar regra específica do funcionário
     * 6. Aplicar cálculo
     * 7. Consolidar comissão final
     * 
     * @param int $funcId
     * @param string $periodoIni (YYYY-MM-DD)
     * @param string $periodoFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function calcularComissaoCompleta($funcId, $periodoIni, $periodoFim, $emprId, $centroTrabId = null)
    {
        $resultado = [
            'success' => true,
            'func_id' => $funcId,
            'periodo_ini' => $periodoIni,
            'periodo_fim' => $periodoFim,
            'dias_trabalhados' => 0,
            'dias_com_falta' => 0,
            'total_pontos_bruto' => 0,
            'total_pontos_apos_falta' => 0,
            'total_retrabalho' => 0,
            'desconto_retrabalho' => 0,
            'usa_regra_especifica' => false,
            'regra_aplicada' => null,
            'faixa_aplicada' => null,
            'valor_comissao_bruto' => 0,
            'valor_comissao_final' => 0,
            'detalhes_faltas' => [],
            'detalhes_retrabalho' => [],
            'bloqueado_falta' => false
        ];

        try {
            // =============================================
            // PASSO 1: Definir período e gerar dias
            // =============================================
            $diasPeriodo = self::gerarDiasPeriodo($periodoIni, $periodoFim);
            
            // =============================================
            // PASSO 2: Verificar faltas por dia
            // =============================================
            $faltas = FaltaFuncionario::verificarFaltasPeriodo($funcId, $periodoIni, $periodoFim, $emprId);
            
            // Criar array associativo de faltas por data com tipo
            $faltasPorData = [];
            foreach ($faltas as $falta) {
                $dataFalta = substr($falta['DT_FALTA'], 0, 10); // YYYY-MM-DD
                $faltasPorData[$dataFalta] = $falta['TIPO_FALTA'] ?? 'I';
            }
            
            $resultado['dias_com_falta'] = count($faltasPorData);
            $resultado['detalhes_faltas'] = $faltas;
            
            // =============================================
            // PASSO 3: Buscar pontos por dia (otimizado - agregado no Oracle)
            // Aplica desconto de falta: integral = 0%, parcial = 50%
            // =============================================
            $pontosDiarios = ApontamentoProducao::pontosPorDia(
                $periodoIni, $periodoFim, $funcId, $emprId, $centroTrabId
            );
            
            // Calcular totais aplicando desconto de falta por tipo
            $pontosPorDia = [];
            $totalPontosBruto = 0;
            $totalPontosAposFalta = 0;
            $diasTrabalhados = [];
            
            foreach ($pontosDiarios as $dia) {
                $dataApt = substr($dia['DATA_APONTAMENTO'], 0, 10); // YYYY-MM-DD
                $pontos = floatval($dia['TOTAL_PONTOS'] ?? 0);
                $totalPontosBruto += $pontos;
                
                // Verificar se o dia tem falta e qual tipo
                if (isset($faltasPorData[$dataApt])) {
                    $tipoFalta = $faltasPorData[$dataApt];
                    
                    if ($tipoFalta === 'I') {
                        // Falta integral: zera os pontos do dia
                        $pontosPorDia[$dataApt] = 0;
                        // Não conta como dia trabalhado
                    } else {
                        // Falta parcial (P): reduz 50% dos pontos
                        $pontosComDesconto = $pontos * 0.5;
                        $totalPontosAposFalta += $pontosComDesconto;
                        $diasTrabalhados[$dataApt] = true;
                        $pontosPorDia[$dataApt] = $pontosComDesconto;
                    }
                } else {
                    // Sem falta: pontos integrais
                    $totalPontosAposFalta += $pontos;
                    $diasTrabalhados[$dataApt] = true;
                    $pontosPorDia[$dataApt] = $pontos;
                }
            }
            
            $resultado['total_pontos_bruto'] = round($totalPontosBruto, 2);
            $resultado['total_pontos_apos_falta'] = round($totalPontosAposFalta, 2);
            $resultado['dias_trabalhados'] = count($diasTrabalhados);
            
            // Se todos os dias têm falta integral, comissão é zero
            if (count($diasTrabalhados) === 0 && count($pontosDiarios) > 0) {
                $resultado['bloqueado_falta'] = true;
                $resultado['valor_comissao_final'] = 0;
                $resultado['message'] = 'Comissão zerada: funcionário possui falta em todos os dias do período';
                return $resultado;
            }
            
            // =============================================
            // PASSO 4: Buscar retrabalho
            // =============================================
            $retrabalhos = Retrabalho::buscarPorFuncionariosPeriodo(
                [$funcId], $periodoIni, $periodoFim, $emprId
            );
            
            $totalPontosRetrabalho = 0;
            $detalhesRetrabalho = [];
            
            foreach ($retrabalhos as $ret) {
                $pontosRet = floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
                $totalPontosRetrabalho += $pontosRet;
                $detalhesRetrabalho[] = [
                    'id' => $ret['ID_RETRABALHO'],
                    'item' => $ret['COD_ITEM'],
                    'quantidade' => $ret['QUANTIDADE'],
                    'pontos' => $pontosRet,
                    'tipo_impacto' => $ret['TIPO_IMPACTO'],
                    'valor_impacto' => $ret['VALOR_IMPACTO']
                ];
            }
            
            $resultado['total_retrabalho'] = round($totalPontosRetrabalho, 2);
            $resultado['detalhes_retrabalho'] = $detalhesRetrabalho;
            
            // =============================================
            // PASSO 5: Verificar regra específica do funcionário
            // =============================================
            $regraEspecifica = RegraFuncionario::buscarRegraAtiva($funcId, $centroTrabId, $periodoFim, $emprId);
            
            $valorComissaoBruto = 0;
            
            if ($regraEspecifica) {
                // Aplicar regra específica
                $resultado['usa_regra_especifica'] = true;
                $resultado['regra_aplicada'] = [
                    'id' => $regraEspecifica['ID_REGRA'],
                    'descricao' => $regraEspecifica['DESCRICAO'],
                    'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                    'valor' => $regraEspecifica['VALOR_COMISSAO'],
                    'valor_fixo' => $regraEspecifica['VALOR_FIXO'] ?? null
                ];
                
                $valorComissaoBruto = RegraFuncionario::calcularComissao(
                    $totalPontosAposFalta, 
                    $regraEspecifica
                );
            } else {
                // =============================================
                // PASSO 6: Aplicar cálculo padrão (faixas)
                // =============================================
                $faixa = FaixaComissao::buscarFaixaAplicavel($totalPontosAposFalta, $centroTrabId, $periodoFim);
                
                if ($faixa) {
                    $resultado['faixa_aplicada'] = [
                        'id' => $faixa['ID_FAIXA'],
                        'descricao' => $faixa['DESCRICAO'],
                        'tipo' => $faixa['TIPO'],
                        'valor' => $faixa['VALOR_COMISSAO']
                    ];
                    
                    if ($faixa['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
                        $valorComissaoBruto = $totalPontosAposFalta * ($faixa['VALOR_COMISSAO'] / 100);
                    } elseif ($faixa['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
                        $valorComissaoBruto = $totalPontosAposFalta * floatval($faixa['VALOR_COMISSAO']);
                    } else {
                        $valorComissaoBruto = floatval($faixa['VALOR_COMISSAO']);
                    }
                }
            }
            
            $resultado['valor_comissao_bruto'] = round($valorComissaoBruto, 2);
            
            // =============================================
            // PASSO 7: Consolidar comissão final
            // Aplicar desconto de retrabalho
            // =============================================
            $descontoRetrabalho = 0;
            
            foreach ($retrabalhos as $ret) {
                $pontosRet = floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
                $impacto = Retrabalho::calcularImpacto(
                    $valorComissaoBruto,
                    $pontosRet,
                    $ret['TIPO_IMPACTO'],
                    $ret['VALOR_IMPACTO']
                );
                $descontoRetrabalho += $impacto['valor_desconto'];
            }
            
            $resultado['desconto_retrabalho'] = round($descontoRetrabalho, 2);
            $resultado['valor_comissao_final'] = round(max(0, $valorComissaoBruto - $descontoRetrabalho), 2);
            
        } catch (Exception $e) {
            $resultado['success'] = false;
            $resultado['error'] = $e->getMessage();
        }
        
        return $resultado;
    }

    /**
     * Calcular comissão usando totalPontosAposFalta já calculado externamente.
     * Evita re-buscar faltas e pontos que já foram obtidos pelo chamador.
     * Busca apenas: retrabalho, regra específica e faixa.
     *
     * @param int $funcId
     * @param string $periodoIni (YYYY-MM-DD)
     * @param string $periodoFim (YYYY-MM-DD)
     * @param int $emprId
     * @param float $totalPontosAposFalta Pontos já com desconto de falta
     * @param array $faltas Array de faltas já buscadas
     * @param int|null $centroTrabId
     * @return array
     */
    public static function calcularComissaoPreCalculada(
        int $funcId,
        string $periodoIni,
        string $periodoFim,
        int $emprId,
        float $totalPontosAposFalta,
        array $faltas = [],
        ?int $centroTrabId = null
    ): array {
        $resultado = [
            'success' => true,
            'func_id' => $funcId,
            'total_pontos_apos_falta' => round($totalPontosAposFalta, 2),
            'dias_com_falta' => count($faltas),
            'total_retrabalho' => 0,
            'desconto_retrabalho' => 0,
            'usa_regra_especifica' => false,
            'regra_aplicada' => null,
            'faixa_aplicada' => null,
            'valor_comissao_bruto' => 0,
            'valor_comissao_final' => 0,
        ];

        try {
            // PASSO 1: Buscar retrabalho
            $retrabalhos = Retrabalho::buscarPorFuncionariosPeriodo(
                [$funcId], $periodoIni, $periodoFim, $emprId
            );

            $totalPontosRetrabalho = 0;
            foreach ($retrabalhos as $ret) {
                $totalPontosRetrabalho += floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
            }
            $resultado['total_retrabalho'] = round($totalPontosRetrabalho, 2);

            // PASSO 2: Verificar regra específica do funcionário
            $regraEspecifica = RegraFuncionario::buscarRegraAtiva($funcId, $centroTrabId, $periodoFim, $emprId);

            $valorComissaoBruto = 0;

            if ($regraEspecifica) {
                $resultado['usa_regra_especifica'] = true;
                $resultado['regra_aplicada'] = [
                    'id' => $regraEspecifica['ID_REGRA'],
                    'descricao' => $regraEspecifica['DESCRICAO'],
                    'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                    'valor' => $regraEspecifica['VALOR_COMISSAO'],
                    'valor_fixo' => $regraEspecifica['VALOR_FIXO'] ?? null
                ];
                $valorComissaoBruto = RegraFuncionario::calcularComissao($totalPontosAposFalta, $regraEspecifica);
            } else {
                // PASSO 3: Aplicar cálculo padrão (faixas)
                $faixa = FaixaComissao::buscarFaixaAplicavel($totalPontosAposFalta, $centroTrabId, $periodoFim);

                if ($faixa) {
                    $resultado['faixa_aplicada'] = [
                        'id' => $faixa['ID_FAIXA'],
                        'descricao' => $faixa['DESCRICAO'],
                        'tipo' => $faixa['TIPO'],
                        'valor' => $faixa['VALOR_COMISSAO']
                    ];

                    if ($faixa['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
                        $valorComissaoBruto = $totalPontosAposFalta * ($faixa['VALOR_COMISSAO'] / 100);
                    } elseif ($faixa['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
                        $valorComissaoBruto = $totalPontosAposFalta * floatval($faixa['VALOR_COMISSAO']);
                    } else {
                        $valorComissaoBruto = floatval($faixa['VALOR_COMISSAO']);
                    }
                }
            }

            $resultado['valor_comissao_bruto'] = round($valorComissaoBruto, 2);

            // PASSO 4: Aplicar desconto de retrabalho
            $descontoRetrabalho = 0;
            foreach ($retrabalhos as $ret) {
                $pontosRet = floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
                $impacto = Retrabalho::calcularImpacto(
                    $valorComissaoBruto, $pontosRet, $ret['TIPO_IMPACTO'], $ret['VALOR_IMPACTO']
                );
                $descontoRetrabalho += $impacto['valor_desconto'];
            }

            $resultado['desconto_retrabalho'] = round($descontoRetrabalho, 2);
            $resultado['valor_comissao_final'] = round(max(0, $valorComissaoBruto - $descontoRetrabalho), 2);

        } catch (Exception $e) {
            $resultado['success'] = false;
            $resultado['error'] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Calcular comissão para TODOS os funcionários com as novas regras
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function calcularComissaoTodosCompleta($periodoIni, $periodoFim, $emprId, $centroTrabId = null)
    {
        // Buscar todos os funcionários com apontamentos no período
        $resumo = ApontamentoProducao::resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        $resultados = [];
        $totalGeral = 0;
        $totalComFalta = 0;
        $totalComRetrabalho = 0;
        $totalComRegraEspecifica = 0;
        
        foreach ($resumo as $func) {
            $calculo = self::calcularComissaoCompleta(
                $func['FUNC_ID'],
                $periodoIni,
                $periodoFim,
                $emprId,
                $centroTrabId
            );
            
            $calculo['cod_func'] = $func['COD_FUNC'];
            $calculo['nome_func'] = $func['NOME_FUNC'];
            $calculo['centro_trab_id'] = $func['CENTRO_TRAB_ID'] ?? null;
            $calculo['cod_centro'] = $func['COD_CENTRO'] ?? null;
            $calculo['desc_centro'] = $func['DESC_CENTRO'] ?? null;
            
            $resultados[] = $calculo;
            
            $totalGeral += $calculo['valor_comissao_final'];
            
            if ($calculo['dias_com_falta'] > 0) {
                $totalComFalta++;
            }
            if ($calculo['total_retrabalho'] > 0) {
                $totalComRetrabalho++;
            }
            if ($calculo['usa_regra_especifica']) {
                $totalComRegraEspecifica++;
            }
        }
        
        return [
            'success' => true,
            'periodo_ini' => $periodoIni,
            'periodo_fim' => $periodoFim,
            'empr_id' => $emprId,
            'centro_trab_id' => $centroTrabId,
            'resumo' => [
                'total_funcionarios' => count($resultados),
                'total_com_falta' => $totalComFalta,
                'total_com_retrabalho' => $totalComRetrabalho,
                'total_com_regra_especifica' => $totalComRegraEspecifica,
                'total_geral_comissao' => round($totalGeral, 2)
            ],
            'funcionarios' => $resultados
        ];
    }

    /**
     * Gerar array com todos os dias do período
     * @param string $dataIni (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @return array
     */
    private static function gerarDiasPeriodo($dataIni, $dataFim)
    {
        $dias = [];
        $atual = new \DateTime($dataIni);
        $fim = new \DateTime($dataFim);
        
        while ($atual <= $fim) {
            $dias[] = $atual->format('Y-m-d');
            $atual->modify('+1 day');
        }
        
        return $dias;
    }

    /**
     * Calcular comissão para um funcionário em um período
     * @param int $funcId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $centroTrabId
     * @return array
     * @deprecated Use calcularComissaoCompleta() para incluir todas as regras
     */
    public static function calcularComissao($funcId, $periodoIni, $periodoFim, $centroTrabId = null)
    {
        // Buscar total de pontos do funcionário no período
        $resumo = ApontamentoProducao::resumoPorFuncionario($periodoIni, $periodoFim, $centroTrabId);
        
        // Encontrar o funcionário específico no resumo
        $dadosFunc = null;
        foreach ($resumo as $item) {
            if ($item['FUNC_ID'] == $funcId) {
                $dadosFunc = $item;
                break;
            }
        }
        
        if (!$dadosFunc) {
            return [
                'success' => false,
                'message' => 'Funcionário não possui apontamentos no período',
                'total_pontos' => 0,
                'valor_comissao' => 0
            ];
        }
        
        $totalPontos = floatval($dadosFunc['TOTAL_PONTOS']);
        
        // Buscar faixa de comissão aplicável
        $faixa = FaixaComissao::buscarFaixaAplicavel($totalPontos, $centroTrabId, $periodoFim);
        
        if (!$faixa) {
            return [
                'success' => false,
                'message' => 'Não há faixa de comissão configurada para esta pontuação',
                'total_pontos' => $totalPontos,
                'valor_comissao' => 0
            ];
        }
        
        // Calcular valor da comissão
        $valorComissao = 0;
        if ($faixa['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
            // Valor é percentual sobre os pontos
            $valorComissao = $totalPontos * ($faixa['VALOR_COMISSAO'] / 100);
        } elseif ($faixa['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
            // Valor por ponto/quantidade
            $valorComissao = $totalPontos * floatval($faixa['VALOR_COMISSAO']);
        } else {
            // Valor fixo por faixa
            $valorComissao = floatval($faixa['VALOR_COMISSAO']);
        }
        
        return [
            'success' => true,
            'func_id' => $funcId,
            'nome_func' => $dadosFunc['NOME_FUNC'],
            'cod_func' => $dadosFunc['COD_FUNC'],
            'centro_trab_id' => $centroTrabId,
            'cod_centro' => $dadosFunc['COD_CENTRO'] ?? null,
            'desc_centro' => $dadosFunc['DESC_CENTRO'] ?? null,
            'periodo_ini' => $periodoIni,
            'periodo_fim' => $periodoFim,
            'total_pontos' => $totalPontos,
            'qtd_apontamentos' => $dadosFunc['QTD_APONTAMENTOS'],
            'total_qtd_boa' => $dadosFunc['TOTAL_QTD_BOA'],
            'faixa_id' => $faixa['ID_FAIXA'],
            'faixa_descricao' => $faixa['DESCRICAO'],
            'tipo_faixa' => $faixa['TIPO'],
            'valor_faixa' => $faixa['VALOR_COMISSAO'],
            'valor_comissao' => round($valorComissao, 2)
        ];
    }

    /**
     * Calcular comissão para todos os funcionários em um período
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function calcularComissaoTodos($periodoIni, $periodoFim, $emprId = null, $centroTrabId = null)
    {
        // Buscar resumo de todos os funcionários
        $resumo = ApontamentoProducao::resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        $resultados = [];
        
        foreach ($resumo as $dadosFunc) {
            $funcId = $dadosFunc['FUNC_ID'];
            $totalPontosBruto = floatval($dadosFunc['TOTAL_PONTOS']);
            
            // Verificar faltas do funcionário no período
            $faltas = FaltaFuncionario::verificarFaltasPeriodo($funcId, $periodoIni, $periodoFim, $emprId);
            $diasComFalta = count($faltas);
            $temFaltaIntegral = false;
            
            // Calcular pontos após faltas
            // Buscar pontos por dia para descontar dias com falta
            $apontamentosDiarios = ApontamentoProducao::pontosPorDiaFuncionario($funcId, $periodoIni, $periodoFim, $emprId, $centroTrabId);
            
            $totalPontosAposFalta = 0;
            $diasFalta = array_column($faltas, 'DT_FALTA');
            
            foreach ($apontamentosDiarios as $dia) {
                $dataApt = $dia['DATA_APONTAMENTO'];
                $pontosDia = floatval($dia['TOTAL_PONTOS']);
                
                // Verificar se é dia com falta
                $faltaDia = null;
                foreach ($faltas as $falta) {
                    if ($falta['DT_FALTA'] === $dataApt) {
                        $faltaDia = $falta;
                        break;
                    }
                }
                
                if ($faltaDia) {
                    if ($faltaDia['TIPO_FALTA'] === 'I') {
                        // Falta integral: zera pontos do dia
                        $temFaltaIntegral = true;
                    } else {
                        // Falta parcial: metade dos pontos
                        $totalPontosAposFalta += $pontosDia / 2;
                    }
                } else {
                    // Sem falta: conta todos os pontos
                    $totalPontosAposFalta += $pontosDia;
                }
            }
            
            $totalPontos = round($totalPontosAposFalta, 2);
            
            $valorComissao = 0;
            $faixaInfo = null;
            $regraInfo = null;
            $usaRegraEspecifica = false;
            
            // PASSO 1: Verificar se funcionário tem regra específica cadastrada
            $regraEspecifica = RegraFuncionario::buscarRegraAtiva(
                $funcId, 
                $dadosFunc['CENTRO_TRAB_ID'] ?? $centroTrabId, 
                $periodoFim, 
                $emprId
            );
            
            if ($regraEspecifica) {
                // Usar regra específica do funcionário (ignora faixa padrão)
                $usaRegraEspecifica = true;
                $regraInfo = $regraEspecifica;
                
                $valorComissao = RegraFuncionario::calcularComissao($totalPontos, $regraEspecifica);
            } else {
                // PASSO 2: Não tem regra específica - usar faixa padrão do centro de trabalho
                $faixa = FaixaComissao::buscarFaixaAplicavel(
                    $totalPontos, 
                    $dadosFunc['CENTRO_TRAB_ID'] ?? $centroTrabId, 
                    $periodoFim
                );
                
                if ($faixa) {
                    if ($faixa['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
                        $valorComissao = $totalPontos * ($faixa['VALOR_COMISSAO'] / 100);
                    } elseif ($faixa['TIPO'] == FaixaComissao::TIPO_QUANTIDADE) {
                        $valorComissao = $totalPontos * floatval($faixa['VALOR_COMISSAO']);
                    } else {
                        $valorComissao = floatval($faixa['VALOR_COMISSAO']);
                    }
                    $faixaInfo = $faixa;
                }
            }
            
            $resultados[] = [
                'func_id' => $funcId,
                'cod_func' => $dadosFunc['COD_FUNC'],
                'nome_func' => $dadosFunc['NOME_FUNC'],
                'centro_trab_id' => $dadosFunc['CENTRO_TRAB_ID'] ?? null,
                'cod_centro' => $dadosFunc['COD_CENTRO'] ?? null,
                'desc_centro' => $dadosFunc['DESC_CENTRO'] ?? null,
                'qtd_apontamentos' => $dadosFunc['QTD_APONTAMENTOS'],
                'total_qtd_boa' => $dadosFunc['TOTAL_QTD_BOA'],
                'total_qtd_refugo' => $dadosFunc['TOTAL_QTD_REFUGO'] ?? 0,
                'total_pontos_bruto' => $totalPontosBruto,
                'total_pontos' => $totalPontos,
                'dias_com_falta' => $diasComFalta,
                'tem_falta' => $diasComFalta > 0,
                'usa_regra_especifica' => $usaRegraEspecifica,
                'regra_id' => $regraInfo ? $regraInfo['ID_REGRA'] : null,
                'regra_descricao' => $regraInfo ? $regraInfo['DESCRICAO'] : null,
                'regra_tipo' => $regraInfo ? $regraInfo['TIPO_COMISSAO'] : null,
                'faixa_id' => $faixaInfo ? $faixaInfo['ID_FAIXA'] : null,
                                'faixa_descricao' => $usaRegraEspecifica 
                    ? 'Regra Específica' 
                    : ($faixaInfo ? $faixaInfo['DESCRICAO'] : 'Sem faixa'),
                'valor_comissao' => round($valorComissao, 2)
            ];
        }
        
        return [
            'success' => true,
            'periodo_ini' => $periodoIni,
            'periodo_fim' => $periodoFim,
            'empr_id' => $emprId,
            'centro_trab_id' => $centroTrabId,
            'total_funcionarios' => count($resultados),
            'total_geral_pontos' => array_sum(array_column($resultados, 'total_pontos')),
            'total_geral_comissao' => array_sum(array_column($resultados, 'valor_comissao')),
            'funcionarios' => $resultados
        ];
    }

    /**
     * Salvar cálculo de comissão no banco
     * @param array $dados
     * @return int ID inserido
     */
    public static function salvarCalculo($dados)
    {
        $obs = $dados['observacao'] ?? '';
        $params = [
            'funcionario_id' => intval($dados['funcionario_id']),
            'faixa_id' => intval($dados['faixa_id']),
            'dt_inicio' => "'" . str_replace("'", "''", $dados['dt_inicio']) . "'",
            'dt_fim' => "'" . str_replace("'", "''", $dados['dt_fim']) . "'",
            'total_pontos' => floatval($dados['total_pontos']),
            'valor_comissao' => floatval($dados['valor_comissao']),
            'id_usuario' => intval($dados['id_usuario']),
            'observacao' => $obs ? "'" . str_replace("'", "''", $obs) . "'" : 'NULL',
        ];

        $result = Database::switchParams('focco', $params, 'comissao.comissao.salvarCalculo', true);
        if ($result['error']) {
            throw new \Exception('Erro ao salvar cálculo: ' . $result['error']);
        }

        // Buscar o ID recém-inserido via sequence
        $resultId = Database::switchParams('focco', [], 'comissao.comissao.buscarCurrvalCalculo', true);
        return $resultId['retorno'][0]['CURRVAL'] ?? null;
    }

    /**
     * Aprovar cálculo de comissão
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public static function aprovar($id, $usuId)
    {
        $params = [
            'usu_id' => intval($usuId),
            'id' => intval($id),
        ];

        $result = Database::switchParams('focco', $params, 'comissao.comissao.aprovar', true);
        return !$result['error'];
    }

    /**
     * Cancelar cálculo de comissão
     * @param int $id
     * @param int $usuId
     * @param string $motivo
     * @return bool
     */
    public static function cancelar($id, $usuId, $motivo = null)
    {
        $params = [
            'usu_id' => intval($usuId),
            'motivo' => $motivo ? "'" . str_replace("'", "''", $motivo) . "'" : 'NULL',
            'id' => intval($id),
        ];

        $result = Database::switchParams('focco', $params, 'comissao.comissao.cancelar', true);
        return !$result['error'];
    }

    /**
     * Listar cálculos de comissão
     * @param string $status
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $funcionarioId
     * @return array
     */
    public static function listarCalculos($status = null, $periodoIni = null, $periodoFim = null, $funcionarioId = null)
    {
        $params = [
            'filtro_status' => $status ? "AND CC.STATUS = '" . ($status === 'A' ? 'A' : ($status === 'C' ? 'C' : 'P')) . "'" : '--',
            'filtro_periodo_ini' => $periodoIni ? "AND CC.DT_INICIO >= TO_DATE('" . str_replace("'", "''", $periodoIni) . "', 'YYYY-MM-DD')" : '--',
            'filtro_periodo_fim' => $periodoFim ? "AND CC.DT_FIM <= TO_DATE('" . str_replace("'", "''", $periodoFim) . "', 'YYYY-MM-DD')" : '--',
            'filtro_funcionario' => $funcionarioId ? "AND CC.FUNCIONARIO_ID = " . intval($funcionarioId) : '--',
        ];

        $result = Database::switchParams('focco', $params, 'comissao.comissao.listarCalculos', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Buscar cálculo por ID
     * @param int $id
     * @return array|null
     */
    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'comissao.comissao.buscarPorId', true);
        $rows = $result['retorno'] ?? [];
        return $rows[0] ?? null;
    }

    /**
     * Buscar cálculo por funcionário e período
     * @param int $funcId
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @return array|null
     */
    public static function buscarPorFuncPeriodo($funcId, $dataInicio, $dataFim)
    {
        $params = [
            'func_id' => intval($funcId),
            'dt_inicio' => "'" . str_replace("'", "''", $dataInicio) . "'",
            'dt_fim' => "'" . str_replace("'", "''", $dataFim) . "'",
        ];

        $result = Database::switchParams('focco', $params, 'comissao.comissao.buscarPorFuncPeriodo', true);
        $rows = $result['retorno'] ?? [];
        return $rows[0] ?? null;
    }

    /**
     * VERSÃO OTIMIZADA - Calcular comissão para TODOS os funcionários
     * 
     * OTIMIZAÇÕES APLICADAS:
     * 1. Busca todos os dados em consultas BATCH (evita N queries por funcionário)
     * 2. Pré-carrega faltas de todos os funcionários de uma vez
     * 3. Pré-carrega regras específicas de todos os funcionários de uma vez
     * 4. Pré-carrega retrabalhos de todos os funcionários de uma vez
     * 5. Processa tudo em memória (PHP)
     * 
     * Em vez de N*5 queries (onde N = número de funcionários), faz apenas ~6 queries totais
     * 
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $emprId
     * @param int|null $centroTrabId
     * @return array
     */
    public static function calcularComissaoTodosCompletaOtimizado(string $periodoIni, string $periodoFim, int $emprId, ?int $centroTrabId = null): array
    {
        $inicioProcessamento = microtime(true);
        
        // =============================================
        // PASSO 1: Buscar lista de funcionários com apontamentos (1 query)
        // =============================================
        $resumoFuncionarios = ApontamentoProducao::resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        // =============================================
        // PASSO 1.1: Buscar funcionários com datas de apoio no período (podem não ter apontamentos)
        // =============================================
        $funcComApoio = VinculoData::buscarFuncionariosComApoioPeriodo($emprId, $periodoIni, $periodoFim, $centroTrabId);
        $datasApoioTodas = VinculoData::buscarTodasDatasApoioPeriodo($emprId, $periodoIni, $periodoFim, $centroTrabId);
        
        // Mesclar funcionários de apontamentos com funcionários que só têm apoio
        $funcIdsApontamentos = array_map(fn($f) => (int)$f['FUNC_ID'], $resumoFuncionarios);
        $funcIdsApoio = array_map(fn($f) => (int)$f['FUNC_ID'], $funcComApoio);
        
        // Indexar resumo por funcionário para acesso rápido
        $dadosFuncionarios = [];
        foreach ($resumoFuncionarios as $func) {
            $dadosFuncionarios[(int)$func['FUNC_ID']] = $func;
        }
        
        // Adicionar funcionários com apoio que não têm apontamentos
        foreach ($funcComApoio as $func) {
            $fId = (int)$func['FUNC_ID'];
            if (!isset($dadosFuncionarios[$fId])) {
                $dadosFuncionarios[$fId] = $func;
                $dadosFuncionarios[$fId]['TOTAL_PONTOS'] = 0; // Não tem apontamentos individuais
            }
        }
        
        // Lista final de IDs para processar
        $funcIds = array_keys($dadosFuncionarios);
        
        if (empty($funcIds)) {
            return [
                'success' => true,
                'periodo_ini' => $periodoIni,
                'periodo_fim' => $periodoFim,
                'empr_id' => $emprId,
                'centro_trab_id' => $centroTrabId,
                'resumo' => [
                    'total_funcionarios' => 0,
                    'total_com_falta' => 0,
                    'total_com_retrabalho' => 0,
                    'total_com_regra_especifica' => 0,
                    'total_geral_comissao' => 0
                ],
                'funcionarios' => [],
                'tempo_processamento' => round((microtime(true) - $inicioProcessamento) * 1000, 2) . 'ms'
            ];
        }
        
        // Buscar TIPO_VINCULO direto da tabela de vínculos (garante valor correto)
        $inIds = implode(',', array_map('intval', $funcIds));
        $params = ['func_ids' => $inIds];
        $resultVinc = Database::switchParams('focco', $params, 'comissao.vinculo.buscarTipoVinculoBatch', true);
        $vinculoRows = $resultVinc['retorno'] ?? [];
        foreach ($vinculoRows as $rowVinc) {
            $fId = (int)$rowVinc['ID_FUNCIONARIO'];
            if (isset($dadosFuncionarios[$fId])) {
                $dadosFuncionarios[$fId]['TIPO_VINCULO'] = $rowVinc['TIPO_VINCULO'] ?? 'N';
            }
        }
        
        // =============================================
        // PASSO 1.5: Marcar DATAS DE APOIO para cada funcionário
        // =============================================
        foreach ($datasApoioTodas as $fId => $datas) {
            if (isset($dadosFuncionarios[$fId]) && !empty($datas)) {
                $dadosFuncionarios[$fId]['DATAS_APOIO'] = $datas;
            }
        }
        
        // =============================================
        // PASSO 2: Buscar TODAS as faltas em uma única query (1 query)
        // =============================================
        $faltasPorFunc = FaltaFuncionario::verificarFaltasPeriodoBatch($funcIds, $periodoIni, $periodoFim, $emprId);
        
        // =============================================
        // PASSO 3: Buscar pontos por dia de TODOS os funcionários (1 query)
        // =============================================
        $pontosPorDiaFunc = ApontamentoProducao::pontosPorDiaBatch($periodoIni, $periodoFim, $funcIds, $emprId, $centroTrabId);
        
        // =============================================
        // PASSO 3.5: Buscar pontos totais do centro para funcionários com datas de apoio
        // =============================================
        $pontosTotaisCentro = [];
        $recursosPorCentroDia = [];
        // Identificar todos os centros de trabalho que precisamos e se tem algum com tipo MÉDIA
        $centrosNecessarios = [];
        $temTipoMedia = false;
        foreach ($datasApoioTodas as $fId => $datas) {
            foreach ($datas as $data => $dadosApoio) {
                // Compatibilidade: se for valor simples (legado), converter para array
                if (!is_array($dadosApoio)) {
                    $centroId = (int)$dadosApoio;
                    $datasApoioTodas[$fId][$data] = ['centro' => $centroId, 'tipo_calculo' => 'T'];
                } else {
                    $centroId = (int)$dadosApoio['centro'];
                    if ($dadosApoio['tipo_calculo'] === 'M') {
                        $temTipoMedia = true;
                    }
                }
                $centrosNecessarios[$centroId] = true;
            }
        }
        // Também adicionar o centro principal para cálculos
        foreach ($dadosFuncionarios as $fId => $dadosFunc) {
            if (!empty($dadosFunc['CENTRO_TRAB_ID'])) {
                $centrosNecessarios[(int)$dadosFunc['CENTRO_TRAB_ID']] = true;
            }
        }
            
        if (!empty($centrosNecessarios)) {
            $pontosTotaisCentro = ApontamentoProducao::pontosTotaisCentroPorDia(
                $periodoIni, 
                $periodoFim, 
                array_keys($centrosNecessarios), 
                $emprId
            );
            
            // Se tem algum apoio com tipo MÉDIA, buscar quantidade de recursos
            if ($temTipoMedia) {
                $recursosPorCentroDia = ApontamentoProducao::contarRecursosPorCentroDia(
                    $periodoIni,
                    $periodoFim,
                    array_keys($centrosNecessarios),
                    $emprId
                );
            }
        }
        
        // =============================================
        // PASSO 4: Buscar TODOS os retrabalhos em uma única query (1 query)
        // =============================================
        $retrabalhosBrutos = Retrabalho::buscarPorFuncionariosPeriodo($funcIds, $periodoIni, $periodoFim, $emprId);
        
        // Indexar retrabalhos por funcionário
        $retrabalhosPorFunc = [];
        foreach ($funcIds as $funcId) {
            $retrabalhosPorFunc[$funcId] = [];
        }
        foreach ($retrabalhosBrutos as $ret) {
            $funcId = (int)$ret['ID_FUNCIONARIO'];
            if (isset($retrabalhosPorFunc[$funcId])) {
                $retrabalhosPorFunc[$funcId][] = $ret;
            }
        }
        
        // =============================================
        // PASSO 5: Buscar TODAS as regras específicas em uma única query (1 query)
        // =============================================
        $regrasPorFunc = RegraFuncionario::buscarRegraAtivaBatch($funcIds, $centroTrabId, $periodoFim, $emprId);
        
        // =============================================
        // PASSO 6: Carregar faixas de comissão (1 query - cache)
        // =============================================
        $faixas = FaixaComissao::listarAtivas($emprId, $centroTrabId);
        
        // =============================================
        // PASSO 7: Processar cada funcionário EM MEMÓRIA (sem queries)
        // CÁLCULO SEPARADO: dias normais vs dias de apoio
        // =============================================
        $resultados = [];
        $totalGeral = 0;
        $totalComFalta = 0;
        $totalComRetrabalho = 0;
        $totalComRegraEspecifica = 0;
        
        foreach ($funcIds as $funcId) {
            $dadosFunc = $dadosFuncionarios[$funcId];
            $faltas = $faltasPorFunc[$funcId] ?? [];
            $pontosDiarios = $pontosPorDiaFunc[$funcId] ?? [];
            $retrabalhos = $retrabalhosPorFunc[$funcId] ?? [];
            $regraEspecifica = $regrasPorFunc[$funcId] ?? null;
            $datasApoio = $dadosFunc['DATAS_APOIO'] ?? [];
            
            // Mapear faltas por data
            $faltasPorData = [];
            foreach ($faltas as $falta) {
                $dataFalta = substr($falta['DT_FALTA'], 0, 10);
                $faltasPorData[$dataFalta] = $falta['TIPO_FALTA'] ?? 'I';
            }
            
            // =============================================
            // SEPARAR CÁLCULO: DIAS NORMAIS vs DIAS DE APOIO
            // =============================================
            $totalPontosBruto = 0;
            $pontosNormais = 0;  // Pontos de dias NORMAIS
            $pontosApoio = 0;   // Pontos de dias de APOIO (do centro)
            $diasTrabalhados = 0;
            $diasNormais = 0;
            $diasApoioUsados = 0;
            $centroIdFuncionario = (int)($dadosFunc['CENTRO_TRAB_ID'] ?? 0);
            $datasProcessadas = []; // Para evitar duplicidade
            $centroApoioInfo = null; // Info do centro de apoio para exibição
            $tipoCalculoApoio = null; // T=Total, M=Média
            
            // Processar dias com apontamentos individuais
            foreach ($pontosDiarios as $dia) {
                $dataApt = $dia['DATA_APONTAMENTO'];
                $pontos = floatval($dia['TOTAL_PONTOS']);
                
                // Verificar se é dia de apoio para este funcionário
                $isDiaApoio = isset($datasApoio[$dataApt]);
                
                if ($isDiaApoio) {
                    // Dia de APOIO: usa pontos do centro (compatibilidade com formato novo e legado)
                    $dadosApoioDia = $datasApoio[$dataApt];
                    if (is_array($dadosApoioDia)) {
                        $centroApoioId = (int)$dadosApoioDia['centro'];
                        $tipoCalculo = $dadosApoioDia['tipo_calculo'] ?? 'T';
                    } else {
                        $centroApoioId = (int)$dadosApoioDia;
                        $tipoCalculo = 'T';
                    }
                    
                    // Guardar info do centro de apoio e tipo de cálculo
                    if ($centroApoioInfo === null) {
                        $centroApoioInfo = $centroApoioId;
                        $tipoCalculoApoio = $tipoCalculo;
                    }
                    
                    if (isset($pontosTotaisCentro[$centroApoioId][$dataApt])) {
                        $pontosTotaisDia = floatval($pontosTotaisCentro[$centroApoioId][$dataApt]);
                        
                        // Se tipo MÉDIA, divide pelos recursos que produziram no dia
                        if ($tipoCalculo === 'M' && isset($recursosPorCentroDia[$centroApoioId][$dataApt])) {
                            $qtdRecursos = max(1, (int)$recursosPorCentroDia[$centroApoioId][$dataApt]);
                            $pontosApoioDia = round($pontosTotaisDia / $qtdRecursos, 2);
                        } else {
                            $pontosApoioDia = $pontosTotaisDia;
                        }
                        
                        $pontosApoio += $pontosApoioDia;
                        $totalPontosBruto += $pontosApoioDia;
                    }
                    $diasApoioUsados++;
                    $diasTrabalhados++;
                    $datasProcessadas[$dataApt] = true;
                } else {
                    // Dia NORMAL: usa pontos individuais com desconto de falta
                    $totalPontosBruto += $pontos;
                    
                    if (isset($faltasPorData[$dataApt])) {
                        $tipoFalta = $faltasPorData[$dataApt];
                        if ($tipoFalta === 'I') {
                            // Falta integral: não soma pontos normais
                            continue;
                        } else {
                            // Falta parcial: 50%
                            $pontosNormais += $pontos * 0.5;
                            $diasNormais++;
                            $diasTrabalhados++;
                        }
                    } else {
                        $pontosNormais += $pontos;
                        $diasNormais++;
                        $diasTrabalhados++;
                    }
                    $datasProcessadas[$dataApt] = true;
                }
            }
            
            // Processar dias de apoio que NÃO têm apontamentos individuais
            // (funcionário pode ter trabalhado como apoio sem ter recurso individual)
            foreach ($datasApoio as $dataApoio => $dadosApoioDia) {
                if (isset($datasProcessadas[$dataApoio])) {
                    continue; // Já processado acima
                }
                
                // Compatibilidade: formato novo ou legado
                if (is_array($dadosApoioDia)) {
                    $centroApoioId = (int)$dadosApoioDia['centro'];
                    $tipoCalculo = $dadosApoioDia['tipo_calculo'] ?? 'T';
                } else {
                    $centroApoioId = (int)$dadosApoioDia;
                    $tipoCalculo = 'T';
                }
                
                // Guardar info do centro de apoio e tipo de cálculo
                if ($centroApoioInfo === null) {
                    $centroApoioInfo = $centroApoioId;
                    $tipoCalculoApoio = $tipoCalculo;
                }
                
                // Buscar pontos do centro para este dia
                if (isset($pontosTotaisCentro[$centroApoioId][$dataApoio])) {
                    $pontosTotaisDia = floatval($pontosTotaisCentro[$centroApoioId][$dataApoio]);
                    
                    // Se tipo MÉDIA, divide pelos recursos que produziram no dia
                    if ($tipoCalculo === 'M' && isset($recursosPorCentroDia[$centroApoioId][$dataApoio])) {
                        $qtdRecursos = max(1, (int)$recursosPorCentroDia[$centroApoioId][$dataApoio]);
                        $pontosApoioDia = round($pontosTotaisDia / $qtdRecursos, 2);
                    } else {
                        $pontosApoioDia = $pontosTotaisDia;
                    }
                    
                    $pontosApoio += $pontosApoioDia;
                    $totalPontosBruto += $pontosApoioDia;
                    $diasApoioUsados++;
                    $diasTrabalhados++;
                }
            }
            
            // =============================================
            // CALCULAR COMISSÃO SEPARADAMENTE: NORMAL + APOIO
            // =============================================
            $valorComissaoBruto = 0;
            $usaRegraEspecifica = false;
            $faixaAplicada = null;
            $faixaApoioAplicada = null;
            $regraAplicadaInfo = null;
            $tipoVinculoFunc = $dadosFunc['TIPO_VINCULO'] ?? 'N';
            
            if ($regraEspecifica) {
                // Usar regra específica (aplica sobre total de pontos)
                $totalPontosAposFalta = $pontosNormais + $pontosApoio;
                $usaRegraEspecifica = true;
                $regraAplicadaInfo = [
                    'id' => $regraEspecifica['ID_REGRA'],
                    'descricao' => $regraEspecifica['DESCRICAO'],
                    'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                    'valor' => $regraEspecifica['VALOR_COMISSAO'],
                    'valor_fixo' => $regraEspecifica['VALOR_FIXO'] ?? null
                ];
                $valorComissaoBruto = RegraFuncionario::calcularComissao($totalPontosAposFalta, $regraEspecifica);
            } else {
                // Calcular separadamente para dias normais e dias de apoio
                $valorComissaoNormal = 0;
                $valorComissaoApoio = 0;
                
                // Comissão de dias normais (faixa NORMAL)
                if ($pontosNormais > 0) {
                    $faixaNormal = self::buscarFaixaAplicavelEmMemoria($faixas, $pontosNormais, 'N');
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
                
                // Comissão de dias de apoio
                // Se tipo MÉDIA (M) -> usa faixa NORMAL
                // Se tipo TOTAL (T) -> usa faixa APOIO
                if ($pontosApoio > 0) {
                    $tipoFaixaApoio = ($tipoCalculoApoio === 'M') ? 'N' : 'A';
                    $faixaApoio = self::buscarFaixaAplicavelEmMemoria($faixas, $pontosApoio, $tipoFaixaApoio);
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
                        
                        // Se não tem faixa normal, usar faixa apoio como principal para exibição
                        if (!$faixaAplicada) {
                            $faixaAplicada = $faixaApoioAplicada;
                        }
                    }
                }
                
                $valorComissaoBruto = $valorComissaoNormal + $valorComissaoApoio;
            }
            
            // Total de pontos para exibição
            $totalPontosAposFalta = $pontosNormais + $pontosApoio;
            
            // Calcular desconto de retrabalho
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
            
            // Determinar tipo efetivo do vínculo para exibição
            // Se tem dias de apoio e não tem dias normais, considera como apoio
            $tipoVinculoEfetivo = $tipoVinculoFunc;
            if ($diasApoioUsados > 0 && $diasNormais === 0) {
                $tipoVinculoEfetivo = 'A';
            }
            
            // Se só teve dias de apoio, usar o centro de apoio para exibição
            $centroExibicao = $dadosFunc['CENTRO_TRAB_ID'] ?? null;
            $codCentroExibicao = $dadosFunc['COD_CENTRO'] ?? null;
            $descCentroExibicao = $dadosFunc['DESC_CENTRO'] ?? null;
            
            if ($diasApoioUsados > 0 && $diasNormais === 0 && $centroApoioInfo) {
                // Usar centro de apoio para exibição
                $centroExibicao = $centroApoioInfo;
                // Buscar info do centro de apoio em cache ou na lista de centros
                foreach ($dadosFuncionarios as $df) {
                    if (($df['CENTRO_TRAB_ID'] ?? 0) == $centroApoioInfo) {
                        $codCentroExibicao = $df['COD_CENTRO'] ?? null;
                        $descCentroExibicao = $df['DESC_CENTRO'] ?? null;
                        break;
                    }
                }
                // Se não encontrou, buscar direto
                if (!$codCentroExibicao && isset($centrosNecessarios[$centroApoioInfo])) {
                    try {
                        $paramsCentro = ['id_centro' => intval($centroApoioInfo)];
                        $resCentro = Database::switchParams('focco', $paramsCentro, 'comissao.centro.buscarPorId', true);
                        if (!empty($resCentro['retorno'][0])) {
                            $codCentroExibicao = $resCentro['retorno'][0]['COD_CENTRO'];
                            $descCentroExibicao = $resCentro['retorno'][0]['DESCRICAO'];
                        }
                    } catch (\Throwable $e) {}
                }
            }
            
            // Montar resultado do funcionário
            $resultado = [
                'func_id' => $funcId,
                'cod_func' => $dadosFunc['COD_FUNC'],
                'nome_func' => $dadosFunc['NOME_FUNC'],
                'centro_trab_id' => $centroExibicao,
                'cod_centro' => $codCentroExibicao,
                'desc_centro' => $descCentroExibicao,
                'centro_apoio_id' => $centroApoioInfo,
                'tipo_calculo_apoio' => $tipoCalculoApoio, // T=Total, M=Média
                'tipo_vinculo' => $tipoVinculoEfetivo,
                'periodo_ini' => $periodoIni,
                'periodo_fim' => $periodoFim,
                'dias_trabalhados' => $diasTrabalhados,
                'dias_normais' => $diasNormais,
                'dias_apoio' => $diasApoioUsados,
                'dias_com_falta' => count($faltas),
                'total_pontos_bruto' => round($totalPontosBruto, 2),
                'total_pontos_apos_falta' => round($totalPontosAposFalta, 2),
                'pontos_normais' => round($pontosNormais, 2),
                'pontos_apoio' => round($pontosApoio, 2),
                'total_retrabalho' => round($totalPontosRetrabalho, 2),
                'desconto_retrabalho' => round($descontoRetrabalho, 2),
                'usa_regra_especifica' => $usaRegraEspecifica,
                'regra_aplicada' => $regraAplicadaInfo,
                'faixa_aplicada' => $faixaAplicada,
                'faixa_apoio_aplicada' => $faixaApoioAplicada ?? null,
                'valor_comissao_bruto' => round($valorComissaoBruto, 2),
                'valor_comissao_final' => round($valorComissaoFinal, 2)
            ];
            
            $resultados[] = $resultado;
            $totalGeral += $valorComissaoFinal;
            
            if (count($faltas) > 0) $totalComFalta++;
            if ($totalPontosRetrabalho > 0) $totalComRetrabalho++;
            if ($usaRegraEspecifica) $totalComRegraEspecifica++;
        }
        
        $tempoProcessamento = round((microtime(true) - $inicioProcessamento) * 1000, 2);
        
        return [
            'success' => true,
            'periodo_ini' => $periodoIni,
            'periodo_fim' => $periodoFim,
            'empr_id' => $emprId,
            'centro_trab_id' => $centroTrabId,
            'resumo' => [
                'total_funcionarios' => count($resultados),
                'total_com_falta' => $totalComFalta,
                'total_com_retrabalho' => $totalComRetrabalho,
                'total_com_regra_especifica' => $totalComRegraEspecifica,
                'total_geral_comissao' => round($totalGeral, 2)
            ],
            'funcionarios' => $resultados,
            'tempo_processamento' => $tempoProcessamento . 'ms'
        ];
    }

    /**
     * Buscar faixa aplicável em memória (sem query)
     * @param array $faixas Lista de faixas já carregadas
     * @param float $pontos Total de pontos
     * @param string $tipoFuncionario Tipo do funcionário: N=Normal, A=Apoio (para filtrar faixas)
     * @return array|null
     */
    private static function buscarFaixaAplicavelEmMemoria(array $faixas, float $pontos, string $tipoFuncionario = 'N'): ?array
    {
        // Filtrar faixas pelo tipo de funcionário
        // N (Normal) -> faixas com TIPO_FUNCIONARIO IN ('N', 'T')
        // A (Apoio) -> faixas com TIPO_FUNCIONARIO IN ('A', 'T')
        $faixasFiltradas = array_filter($faixas, function($faixa) use ($tipoFuncionario) {
            $tipoFaixa = $faixa['TIPO_FUNCIONARIO'] ?? 'T';
            if ($tipoFuncionario === 'N') {
                return in_array($tipoFaixa, ['N', 'T']);
            } elseif ($tipoFuncionario === 'A') {
                return in_array($tipoFaixa, ['A', 'T']);
            }
            return true; // Se tipo desconhecido, aceita qualquer faixa
        });
        
        // Ordenar para priorizar faixas específicas (N ou A) sobre genéricas (T)
        usort($faixasFiltradas, function($a, $b) {
            $prioA = ($a['TIPO_FUNCIONARIO'] ?? 'T') === 'T' ? 1 : 0;
            $prioB = ($b['TIPO_FUNCIONARIO'] ?? 'T') === 'T' ? 1 : 0;
            return $prioA - $prioB;
        });
        
        foreach ($faixasFiltradas as $faixa) {
            $min = floatval($faixa['PONTO_INICIAL'] ?? 0);
            $max = floatval($faixa['PONTO_FINAL'] ?? PHP_FLOAT_MAX);
            
            if ($pontos >= $min && ($max == 0 || $pontos <= $max)) {
                return $faixa;
            }
        }
        return null;
    }
}


