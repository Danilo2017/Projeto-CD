<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;
use src\models\Comissao\FaltaFuncionario;
use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Retrabalho;
use src\models\Comissao\RegraFuncionario;
use src\models\Comissao\FaixaComissao;

/**
 * Model para Cálculo e Gestão de Comissões
 * 
 * Este model calcula as comissÃµes baseadas na pontuaÃ§Ã£o dos funcionÃ¡rios
 * e nas faixas de comissÃ£o configuradas.
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
 * ORDEM DE PROCESSAMENTO DO CÃLCULO:
 * 1. Definir perÃ­odo
 * 2. Verificar faltas por dia (comissÃ£o zerada se tiver falta)
 * 3. Buscar apontamentos vÃ¡lidos (apenas com vÃ­nculo)
 * 4. Buscar retrabalho
 * 5. Verificar regra especÃ­fica do funcionÃ¡rio
 * 6. Aplicar cÃ¡lculo
 * 7. Consolidar comissÃ£o final
 */
class Comissao
{
    const STATUS_PENDENTE = 'P';
    const STATUS_APROVADO = 'A';
    const STATUS_CANCELADO = 'C';

    /**
     * NOVO MÃ‰TODO DE CÃLCULO COM TODAS AS REGRAS
     * 
     * Processa comissÃ£o seguindo a ordem correta:
     * 1. Definir perÃ­odo
     * 2. Verificar faltas por dia
     * 3. Buscar apontamentos vÃ¡lidos
     * 4. Buscar retrabalho
     * 5. Verificar regra especÃ­fica do funcionÃ¡rio
     * 6. Aplicar cÃ¡lculo
     * 7. Consolidar comissÃ£o final
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
            // PASSO 1: Definir perÃ­odo e gerar dias
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
            // PASSO 5: Verificar regra especÃ­fica do funcionÃ¡rio
            // =============================================
            $regraModel = new RegraFuncionario();
            $regraEspecifica = $regraModel->buscarRegraAtiva($funcId, $centroTrabId, $periodoFim, $emprId);
            
            $valorComissaoBruto = 0;
            
            if ($regraEspecifica) {
                // Aplicar regra especÃ­fica
                $resultado['usa_regra_especifica'] = true;
                $resultado['regra_aplicada'] = [
                    'id' => $regraEspecifica['ID_REGRA'],
                    'descricao' => $regraEspecifica['DESCRICAO'],
                    'tipo' => $regraEspecifica['TIPO_COMISSAO'],
                    'valor' => $regraEspecifica['VALOR_COMISSAO']
                ];
                
                $valorComissaoBruto = $regraModel->calcularComissao(
                    $totalPontosAposFalta, 
                    $regraEspecifica
                );
            } else {
                // =============================================
                // PASSO 6: Aplicar cÃ¡lculo padrÃ£o (faixas)
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
            // PASSO 7: Consolidar comissÃ£o final
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
     * Calcular comissÃ£o para TODOS os funcionÃ¡rios com as novas regras
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function calcularComissaoTodosCompleta($periodoIni, $periodoFim, $emprId, $centroTrabId = null)
    {
        // Buscar todos os funcionÃ¡rios com apontamentos no perÃ­odo
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
     * Gerar array com todos os dias do perÃ­odo
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
     * Calcular comissÃ£o para um funcionÃ¡rio em um perÃ­odo
     * @param int $funcId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $centroTrabId
     * @return array
     * @deprecated Use calcularComissaoCompleta() para incluir todas as regras
     */
    public function calcularComissao($funcId, $periodoIni, $periodoFim, $centroTrabId = null)
    {
        // Buscar total de pontos do funcionÃ¡rio no perÃ­odo
        $apontamentoModel = new ApontamentoProducao();
        $resumo = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $centroTrabId);
        
        // Encontrar o funcionÃ¡rio especÃ­fico no resumo
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
                'message' => 'FuncionÃ¡rio nÃ£o possui apontamentos no perÃ­odo',
                'total_pontos' => 0,
                'valor_comissao' => 0
            ];
        }
        
        $totalPontos = floatval($dadosFunc['TOTAL_PONTOS']);
        
        // Buscar faixa de comissÃ£o aplicÃ¡vel
        $faixaModel = new FaixaComissao();
        $faixa = $faixaModel->buscarFaixaAplicavel($totalPontos, $centroTrabId, $periodoFim);
        
        if (!$faixa) {
            return [
                'success' => false,
                'message' => 'NÃ£o hÃ¡ faixa de comissÃ£o configurada para esta pontuaÃ§Ã£o',
                'total_pontos' => $totalPontos,
                'valor_comissao' => 0
            ];
        }
        
        // Calcular valor da comissÃ£o
        $valorComissao = 0;
        if ($faixa['TIPO'] == FaixaComissao::TIPO_PERCENTUAL) {
            // Valor Ã© percentual sobre os pontos
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
     * Calcular comissÃ£o para todos os funcionÃ¡rios em um perÃ­odo
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function calcularComissaoTodos($periodoIni, $periodoFim, $emprId = null, $centroTrabId = null)
    {
        // Buscar resumo de todos os funcionÃ¡rios
        $apontamentoModel = new ApontamentoProducao();
        $resumo = $apontamentoModel->resumoPorFuncionario($periodoIni, $periodoFim, $emprId, $centroTrabId);
        
        $resultados = [];
        $faixaModel = new FaixaComissao();
        $faltaModel = new FaltaFuncionario();
        $regraModel = new RegraFuncionario();
        
        foreach ($resumo as $dadosFunc) {
            $funcId = $dadosFunc['FUNC_ID'];
            $totalPontosBruto = floatval($dadosFunc['TOTAL_PONTOS']);
            
            // Verificar faltas do funcionÃ¡rio no perÃ­odo
            $faltas = $faltaModel->verificarFaltasPeriodo($funcId, $periodoIni, $periodoFim, $emprId);
            $diasComFalta = count($faltas);
            $temFaltaIntegral = false;
            
            // Calcular pontos apÃ³s faltas
            // Buscar pontos por dia para descontar dias com falta
            $apontamentosDiarios = $apontamentoModel->pontosPorDiaFuncionario($funcId, $periodoIni, $periodoFim, $emprId, $centroTrabId);
            
            $totalPontosAposFalta = 0;
            $diasFalta = array_column($faltas, 'DT_FALTA');
            
            foreach ($apontamentosDiarios as $dia) {
                $dataApt = $dia['DATA_APONTAMENTO'];
                $pontosDia = floatval($dia['TOTAL_PONTOS']);
                
                // Verificar se Ã© dia com falta
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
            
            // PASSO 1: Verificar se funcionÃ¡rio tem regra especÃ­fica cadastrada
            $regraEspecifica = $regraModel->buscarRegraAtiva(
                $funcId, 
                $dadosFunc['CENTRO_TRAB_ID'] ?? $centroTrabId, 
                $periodoFim, 
                $emprId
            );
            
            if ($regraEspecifica) {
                // Usar regra especÃ­fica do funcionÃ¡rio (ignora faixa padrÃ£o)
                $usaRegraEspecifica = true;
                $regraInfo = $regraEspecifica;
                
                $valorComissao = $regraModel->calcularComissao($totalPontos, $regraEspecifica);
            } else {
                // PASSO 2: NÃ£o tem regra especÃ­fica - usar faixa padrÃ£o do centro de trabalho
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
                    ? 'Regra EspecÃ­fica' 
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
     * Salvar cÃ¡lculo de comissÃ£o no banco
     * @param array $dados
     * @return int ID inserido
     */
    public function salvarCalculo($dados)
    {
        $sql = "INSERT INTO FOCCO3I.TGAZIN_COMISSAO_CALC (
                    ID_COMISSAO,
                    FUNCIONARIO_ID,
                    FAIXA_ID,
                    DT_INICIO,
                    DT_FIM,
                    TOTAL_PONTOS,
                    VALOR_COMISSAO,
                    STATUS,
                    DT_PROCESSAMENTO,
                    ID_USUARIO_PROC,
                    OBSERVACAO
                ) VALUES (
                    FOCCO3I.SEQ_GAZIN_COMISSAO_CALC.NEXTVAL,
                    :funcionario_id,
                    :faixa_id,
                    TO_DATE(:dt_inicio, 'YYYY-MM-DD'),
                    TO_DATE(:dt_fim, 'YYYY-MM-DD'),
                    :total_pontos,
                    :valor_comissao,
                    'P',
                    SYSDATE,
                    :id_usuario,
                    :observacao
                )";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':funcionario_id', $dados['funcionario_id'], PDO::PARAM_INT);
        $stmt->bindParam(':faixa_id', $dados['faixa_id'], PDO::PARAM_INT);
        $stmt->bindParam(':dt_inicio', $dados['dt_inicio'], PDO::PARAM_STR);
        $stmt->bindParam(':dt_fim', $dados['dt_fim'], PDO::PARAM_STR);
        $stmt->bindParam(':total_pontos', $dados['total_pontos'], PDO::PARAM_STR);
        $stmt->bindParam(':valor_comissao', $dados['valor_comissao'], PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);
        $obs = $dados['observacao'] ?? null;
        $stmt->bindParam(':observacao', $obs, PDO::PARAM_STR);
        
        $stmt->execute();
        
        // Buscar o ID recÃ©m-inserido via sequence
        $stmtId = $pdo->query("SELECT FOCCO3I.SEQ_GAZIN_COMISSAO_CALC.CURRVAL FROM DUAL");
        $id = $stmtId->fetchColumn();
        
        return $id;
    }

    /**
     * Aprovar cÃ¡lculo de comissÃ£o
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public function aprovar($id, $usuId)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_COMISSAO_CALC
                SET STATUS = 'A',
                    ID_USUARIO_APROV = :usu_id,
                    DT_APROVACAO = SYSDATE
                WHERE ID_COMISSAO = :id AND STATUS = 'P'";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':usu_id', $usuId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Cancelar cÃ¡lculo de comissÃ£o
     * @param int $id
     * @param int $usuId
     * @param string $motivo
     * @return bool
     */
    public function cancelar($id, $usuId, $motivo = null)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_COMISSAO_CALC
                SET STATUS = 'C',
                    ID_USUARIO_APROV = :usu_id,
                    DT_APROVACAO = SYSDATE,
                    OBSERVACAO = NVL(:motivo, OBSERVACAO)
                WHERE ID_COMISSAO = :id AND STATUS = 'P'";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':usu_id', $usuId, PDO::PARAM_INT);
        $stmt->bindParam(':motivo', $motivo, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Listar cÃ¡lculos de comissÃ£o
     * @param string $status
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int $funcionarioId
     * @return array
     */
    public function listarCalculos($status = null, $periodoIni = null, $periodoFim = null, $funcionarioId = null)
    {
        $sql = "SELECT 
                    CC.ID_COMISSAO,
                    CC.FUNCIONARIO_ID,
                    CC.FAIXA_ID,
                    CC.DT_INICIO,
                    CC.DT_FIM,
                    CC.TOTAL_PONTOS,
                    CC.VALOR_COMISSAO,
                    CC.STATUS,
                    CASE CC.STATUS 
                        WHEN 'P' THEN 'Pendente' 
                        WHEN 'A' THEN 'Aprovado' 
                        WHEN 'C' THEN 'Cancelado' 
                    END AS DESC_STATUS,
                    CC.DT_PROCESSAMENTO,
                    CC.DT_APROVACAO,
                    CC.OBSERVACAO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNC,
                    FC.DESCRICAO AS DESC_FAIXA
                FROM FOCCO3I.TGAZIN_COMISSAO_CALC CC
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = CC.FUNCIONARIO_ID
                LEFT JOIN FOCCO3I.TGAZIN_FAIXA_COMISSAO FC ON FC.ID_FAIXA = CC.FAIXA_ID
                WHERE 1=1";
        
        if ($status) {
            $sql .= " AND CC.STATUS = :status";
        }
        
        if ($periodoIni) {
            $sql .= " AND CC.DT_INICIO >= TO_DATE(:periodo_ini, 'YYYY-MM-DD')";
        }
        
        if ($periodoFim) {
            $sql .= " AND CC.DT_FIM <= TO_DATE(:periodo_fim, 'YYYY-MM-DD')";
        }
        
        if ($funcionarioId) {
            $sql .= " AND CC.FUNCIONARIO_ID = :funcionario_id";
        }
        
        $sql .= " ORDER BY CC.DT_PROCESSAMENTO DESC, F.NOME";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        if ($status) {
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        }
        
        if ($periodoIni) {
            $stmt->bindParam(':periodo_ini', $periodoIni, PDO::PARAM_STR);
        }
        
        if ($periodoFim) {
            $stmt->bindParam(':periodo_fim', $periodoFim, PDO::PARAM_STR);
        }
        
        if ($funcionarioId) {
            $stmt->bindParam(':funcionario_id', $funcionarioId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar cÃ¡lculo por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    CC.ID_COMISSAO,
                    CC.FUNCIONARIO_ID,
                    CC.FAIXA_ID,
                    CC.DT_INICIO,
                    CC.DT_FIM,
                    CC.TOTAL_PONTOS,
                    CC.VALOR_COMISSAO,
                    CC.STATUS,
                    CC.DT_PROCESSAMENTO,
                    CC.DT_APROVACAO,
                    CC.OBSERVACAO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNC,
                    FC.DESCRICAO AS DESC_FAIXA
                FROM FOCCO3I.TGAZIN_COMISSAO_CALC CC
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = CC.FUNCIONARIO_ID
                LEFT JOIN FOCCO3I.TGAZIN_FAIXA_COMISSAO FC ON FC.ID_FAIXA = CC.FAIXA_ID
                WHERE CC.ID_COMISSAO = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar cÃ¡lculo por funcionÃ¡rio e perÃ­odo
     * @param int $funcId
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @return array|null
     */
    public function buscarPorFuncPeriodo($funcId, $dataInicio, $dataFim)
    {
        $sql = "SELECT 
                    CC.ID_COMISSAO,
                    CC.FUNCIONARIO_ID,
                    CC.FAIXA_ID,
                    CC.DT_INICIO,
                    CC.DT_FIM,
                    CC.TOTAL_PONTOS,
                    CC.VALOR_COMISSAO,
                    CC.STATUS
                FROM FOCCO3I.TGAZIN_COMISSAO_CALC CC
                WHERE CC.FUNCIONARIO_ID = :func_id
                  AND CC.DT_INICIO = TO_DATE(:dt_inicio, 'YYYY-MM-DD')
                  AND CC.DT_FIM = TO_DATE(:dt_fim, 'YYYY-MM-DD')
                  AND CC.STATUS = 'P'
                ORDER BY CC.ID_COMISSAO DESC
                FETCH FIRST 1 ROWS ONLY";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':dt_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':dt_fim', $dataFim, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}


