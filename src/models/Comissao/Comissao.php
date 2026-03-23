<?php

namespace src\models\Comissao;

use core\Database;
use Exception;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Retrabalho;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\FaixaComissao;

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
    public function calcularComissaoCompleta($funcId, $periodoIni, $periodoFim, $emprId, $centroTrabId = null)
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
            $diasPeriodo = $this->gerarDiasPeriodo($periodoIni, $periodoFim);
            
            // =============================================
            // PASSO 2: Verificar faltas por dia
            // =============================================
            $faltaModel = new FaltaFuncionario();
            $faltas = $faltaModel->verificarFaltasPeriodo($funcId, $periodoIni, $periodoFim, $emprId);
            
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
            $apontamentoModel = new ApontamentoProducao();
            $pontosDiarios = $apontamentoModel->pontosPorDia(
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
            $retrabalhoModel = new Retrabalho();
            $retrabalhos = $retrabalhoModel->buscarPorFuncionariosPeriodo(
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
            $regraModel = new RegraFuncionario();
            $regraEspecifica = $regraModel->buscarRegraAtiva($funcId, $centroTrabId, $periodoFim, $emprId);
            
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
                
                $valorComissaoBruto = $regraModel->calcularComissao(
                    $totalPontosAposFalta, 
                    $regraEspecifica
                );
            } else {
                // =============================================
                // PASSO 6: Aplicar cálculo padrão (faixas)
                // =============================================
                $faixaModel = new FaixaComissao();
                $faixa = $faixaModel->buscarFaixaAplicavel($totalPontosAposFalta, $centroTrabId, $periodoFim);
                
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
                $impacto = $retrabalhoModel->calcularImpacto(
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
    public function calcularComissaoPreCalculada(
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
            $retrabalhoModel = new Retrabalho();
            $retrabalhos = $retrabalhoModel->buscarPorFuncionariosPeriodo(
                [$funcId], $periodoIni, $periodoFim, $emprId
            );

            $totalPontosRetrabalho = 0;
            foreach ($retrabalhos as $ret) {
                $totalPontosRetrabalho += floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
            }
            $resultado['total_retrabalho'] = round($totalPontosRetrabalho, 2);

            // PASSO 2: Verificar regra específica do funcionário
            $regraModel = new RegraFuncionario();
            $regraEspecifica = $regraModel->buscarRegraAtiva($funcId, $centroTrabId, $periodoFim, $emprId);

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
                $valorComissaoBruto = $regraModel->calcularComissao($totalPontosAposFalta, $regraEspecifica);
            } else {
                // PASSO 3: Aplicar cálculo padrão (faixas)
                $faixaModel = new FaixaComissao();
                $faixa = $faixaModel->buscarFaixaAplicavel($totalPontosAposFalta, $centroTrabId, $periodoFim);

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
                $impacto = $retrabalhoModel->calcularImpacto(
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
    public function calcularComissaoTodosCompleta($periodoIni, $periodoFim, $emprId, $centroTrabId = null)
    {
        // Buscar todos os funcionários com apontamentos no período
        $apontamentoModel = new ApontamentoProducao();
        $resumo = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        $resultados = [];
        $totalGeral = 0;
        $totalComFalta = 0;
        $totalComRetrabalho = 0;
        $totalComRegraEspecifica = 0;
        
        foreach ($resumo as $func) {
            $calculo = $this->calcularComissaoCompleta(
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
    private function gerarDiasPeriodo($dataIni, $dataFim)
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
    public function calcularComissao($funcId, $periodoIni, $periodoFim, $centroTrabId = null)
    {
        // Buscar total de pontos do funcionário no período
        $apontamentoModel = new ApontamentoProducao();
        $resumo = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $centroTrabId);
        
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
        $faixaModel = new FaixaComissao();
        $faixa = $faixaModel->buscarFaixaAplicavel($totalPontos, $centroTrabId, $periodoFim);
        
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
    public function calcularComissaoTodos($periodoIni, $periodoFim, $emprId = null, $centroTrabId = null)
    {
        // Buscar resumo de todos os funcionários
        $apontamentoModel = new ApontamentoProducao();
        $resumo = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        $resultados = [];
        $faixaModel = new FaixaComissao();
        $faltaModel = new FaltaFuncionario();
        $regraModel = new RegraFuncionario();
        
        foreach ($resumo as $dadosFunc) {
            $funcId = $dadosFunc['FUNC_ID'];
            $totalPontosBruto = floatval($dadosFunc['TOTAL_PONTOS']);
            
            // Verificar faltas do funcionário no período
            $faltas = $faltaModel->verificarFaltasPeriodo($funcId, $periodoIni, $periodoFim, $emprId);
            $diasComFalta = count($faltas);
            $temFaltaIntegral = false;
            
            // Calcular pontos após faltas
            // Buscar pontos por dia para descontar dias com falta
            $apontamentosDiarios = $apontamentoModel->pontosPorDiaFuncionario($funcId, $periodoIni, $periodoFim, $emprId, $centroTrabId);
            
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
            $regraEspecifica = $regraModel->buscarRegraAtiva(
                $funcId, 
                $dadosFunc['CENTRO_TRAB_ID'] ?? $centroTrabId, 
                $periodoFim, 
                $emprId
            );
            
            if ($regraEspecifica) {
                // Usar regra específica do funcionário (ignora faixa padrão)
                $usaRegraEspecifica = true;
                $regraInfo = $regraEspecifica;
                
                $valorComissao = $regraModel->calcularComissao($totalPontos, $regraEspecifica);
            } else {
                // PASSO 2: Não tem regra específica - usar faixa padrão do centro de trabalho
                $faixa = $faixaModel->buscarFaixaAplicavel(
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
    public function salvarCalculo($dados)
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
    public function aprovar($id, $usuId)
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
    public function cancelar($id, $usuId, $motivo = null)
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
    public function listarCalculos($status = null, $periodoIni = null, $periodoFim = null, $funcionarioId = null)
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
    public function buscarPorId($id)
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
    public function buscarPorFuncPeriodo($funcId, $dataInicio, $dataFim)
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
    public function calcularComissaoTodosCompletaOtimizado(string $periodoIni, string $periodoFim, int $emprId, ?int $centroTrabId = null): array
    {
        $inicioProcessamento = microtime(true);
        
        // =============================================
        // PASSO 1: Buscar lista de funcionários com apontamentos (1 query)
        // =============================================
        $apontamentoModel = new ApontamentoProducao();
        $resumoFuncionarios = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        if (empty($resumoFuncionarios)) {
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
        
        // Extrair IDs dos funcionários
        $funcIds = array_map(fn($f) => (int)$f['FUNC_ID'], $resumoFuncionarios);
        
        // Indexar resumo por funcionário para acesso rápido
        $dadosFuncionarios = [];
        foreach ($resumoFuncionarios as $func) {
            $dadosFuncionarios[(int)$func['FUNC_ID']] = $func;
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
        // PASSO 2: Buscar TODAS as faltas em uma única query (1 query)
        // =============================================
        $faltaModel = new FaltaFuncionario();
        $faltasPorFunc = $faltaModel->verificarFaltasPeriodoBatch($funcIds, $periodoIni, $periodoFim, $emprId);
        
        // =============================================
        // PASSO 3: Buscar pontos por dia de TODOS os funcionários (1 query)
        // =============================================
        $pontosPorDiaFunc = $apontamentoModel->pontosPorDiaBatch($periodoIni, $periodoFim, $funcIds, $emprId, $centroTrabId);
        
        // =============================================
        // PASSO 4: Buscar TODOS os retrabalhos em uma única query (1 query)
        // =============================================
        $retrabalhoModel = new Retrabalho();
        $retrabalhosBrutos = $retrabalhoModel->buscarPorFuncionariosPeriodo($funcIds, $periodoIni, $periodoFim, $emprId);
        
        // Indexar retrabalhos por funcionário
        $retrabalhosPorFunc = [];
        foreach ($funcIds as $funcId) {
            $retrabalhosPorFunc[$funcId] = [];
        }
        foreach ($retrabalhosBrutos as $ret) {
            $funcId = (int)$ret['ID_FUNCIONARIO'];
            $retrabalhosPorFunc[$funcId][] = $ret;
        }
        
        // =============================================
        // PASSO 5: Buscar TODAS as regras específicas em uma única query (1 query)
        // =============================================
        $regraModel = new RegraFuncionario();
        $regrasPorFunc = $regraModel->buscarRegraAtivaBatch($funcIds, $centroTrabId, $periodoFim, $emprId);
        
        // =============================================
        // PASSO 6: Carregar faixas de comissão (1 query - cache)
        // =============================================
        $faixaModel = new FaixaComissao();
        $faixas = $faixaModel->listarAtivas(null, $centroTrabId);
        
        // =============================================
        // PASSO 7: Processar cada funcionário EM MEMÓRIA (sem queries)
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
            
            // Mapear faltas por data
            $faltasPorData = [];
            foreach ($faltas as $falta) {
                $dataFalta = substr($falta['DT_FALTA'], 0, 10);
                $faltasPorData[$dataFalta] = $falta['TIPO_FALTA'] ?? 'I';
            }
            
            // Calcular pontos com desconto de falta
            $totalPontosBruto = 0;
            $totalPontosAposFalta = 0;
            $diasTrabalhados = 0;
            
            foreach ($pontosDiarios as $dia) {
                $dataApt = $dia['DATA_APONTAMENTO'];
                $pontos = floatval($dia['TOTAL_PONTOS']);
                $totalPontosBruto += $pontos;
                
                if (isset($faltasPorData[$dataApt])) {
                    $tipoFalta = $faltasPorData[$dataApt];
                    if ($tipoFalta === 'I') {
                        // Falta integral: zera os pontos
                        continue;
                    } else {
                        // Falta parcial: 50%
                        $totalPontosAposFalta += $pontos * 0.5;
                        $diasTrabalhados++;
                    }
                } else {
                    $totalPontosAposFalta += $pontos;
                    $diasTrabalhados++;
                }
            }
            
            // Calcular comissão
            $valorComissaoBruto = 0;
            $usaRegraEspecifica = false;
            $faixaAplicada = null;
            $regraAplicadaInfo = null;
            
            if ($regraEspecifica) {
                // Usar regra específica
                $usaRegraEspecifica = true;
                $regraAplicadaInfo = [
                    'id' => $regraEspecifica['ID_REGRA'],
                    'descricao' => $regraEspecifica['DESCRICAO'],
                    'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                    'valor' => $regraEspecifica['VALOR_COMISSAO'],
                    'valor_fixo' => $regraEspecifica['VALOR_FIXO'] ?? null
                ];
                $valorComissaoBruto = $regraModel->calcularComissao($totalPontosAposFalta, $regraEspecifica);
            } else {
                // Buscar faixa aplicável
                $faixa = $this->buscarFaixaAplicavelEmMemoria($faixas, $totalPontosAposFalta);
                
                if ($faixa) {
                    $faixaAplicada = [
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
            
            // Calcular desconto de retrabalho
            $descontoRetrabalho = 0;
            $totalPontosRetrabalho = 0;
            
            foreach ($retrabalhos as $ret) {
                $pontosRet = floatval($ret['QUANTIDADE']) * floatval($ret['PONTOS_UP']);
                $totalPontosRetrabalho += $pontosRet;
                $impacto = $retrabalhoModel->calcularImpacto(
                    $valorComissaoBruto,
                    $pontosRet,
                    $ret['TIPO_IMPACTO'],
                    $ret['VALOR_IMPACTO']
                );
                $descontoRetrabalho += $impacto['valor_desconto'];
            }
            
            $valorComissaoFinal = max(0, $valorComissaoBruto - $descontoRetrabalho);
            
            // Montar resultado do funcionário
            $resultado = [
                'func_id' => $funcId,
                'cod_func' => $dadosFunc['COD_FUNC'],
                'nome_func' => $dadosFunc['NOME_FUNC'],
                'centro_trab_id' => $dadosFunc['CENTRO_TRAB_ID'] ?? null,
                'cod_centro' => $dadosFunc['COD_CENTRO'] ?? null,
                'desc_centro' => $dadosFunc['DESC_CENTRO'] ?? null,
                'tipo_vinculo' => $dadosFunc['TIPO_VINCULO'] ?? 'N',
                'periodo_ini' => $periodoIni,
                'periodo_fim' => $periodoFim,
                'dias_trabalhados' => $diasTrabalhados,
                'dias_com_falta' => count($faltas),
                'total_pontos_bruto' => round($totalPontosBruto, 2),
                'total_pontos_apos_falta' => round($totalPontosAposFalta, 2),
                'total_retrabalho' => round($totalPontosRetrabalho, 2),
                'desconto_retrabalho' => round($descontoRetrabalho, 2),
                'usa_regra_especifica' => $usaRegraEspecifica,
                'regra_aplicada' => $regraAplicadaInfo,
                'faixa_aplicada' => $faixaAplicada,
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
     * @return array|null
     */
    private function buscarFaixaAplicavelEmMemoria(array $faixas, float $pontos): ?array
    {
        foreach ($faixas as $faixa) {
            $min = floatval($faixa['PONTO_INICIAL'] ?? 0);
            $max = floatval($faixa['PONTO_FINAL'] ?? PHP_FLOAT_MAX);
            
            if ($pontos >= $min && ($max == 0 || $pontos <= $max)) {
                return $faixa;
            }
        }
        return null;
    }
}


