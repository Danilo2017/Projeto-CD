<?php

namespace src\handlers\Comissao;

use src\models\Comissao\ApontamentoProducao;
use src\models\Comissao\Comissao;
use src\models\Comissao\Empresa;

/**
 * Handler do Dashboard do Sistema de Comissão
 * Gerencia visão geral e resumos
 */
class ComissaoDashboardHandler
{
    /**
     * Listar filiais ativas
     */
    public static function listarFiliais(): array
    {
        $filiais = Empresa::listarAtivas();
        
        return [
            'success' => true,
            'data' => $filiais,
            'total' => count($filiais)
        ];
    }

    /**
     * Buscar resumo geral do período
     */
    public static function getResumoGeral(string $dataInicio, string $dataFim, int $emprId): array
    {
        $resumo = ApontamentoProducao::resumoGeral($dataInicio, $dataFim, $emprId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Buscar ranking de funcionários
     */
    public static function getRankingFuncionarios(string $dataInicio, string $dataFim, int $emprId, int $limite = 10): array
    {
        $ranking = ApontamentoProducao::rankingFuncionarios($dataInicio, $dataFim, $emprId, $limite);

        return [
            'success' => true,
            'ranking' => $ranking
        ];
    }

    /**
     * Buscar resumo por centro de trabalho
     */
    public static function getResumoPorCentro(string $dataInicio, string $dataFim, int $emprId): array
    {
        $resumo = ApontamentoProducao::resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Buscar resumo por recurso
     */
    public static function getResumoPorRecurso(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        $resumo = ApontamentoProducao::resumoPorRecurso($dataInicio, $dataFim, $emprId, $centroTrabId);

        return [
            'success' => true,
            'resumo' => $resumo
        ];
    }

    /**
     * Simular comissões
     */
    public static function simularComissoes(string $dataInicio, string $dataFim, int $emprId, ?int $centroTrabId = null): array
    {
        $simulacao = Comissao::calcularComissaoTodos($dataInicio, $dataFim, $emprId, $centroTrabId);

        // Calcular totais
        $totais = [
            'total_funcionarios' => count($simulacao),
            'total_pontos' => array_sum(array_column($simulacao, 'TOTAL_PONTOS')),
            'total_comissao' => array_sum(array_column($simulacao, 'VALOR_COMISSAO'))
        ];

        return [
            'success' => true,
            'totais' => $totais,
            'simulacao' => $simulacao
        ];
    }

    /**
     * Dashboard completo com comissões calculadas
     */
    public static function getDashboardCompleto(string $dataInicio, string $dataFim, int $emprId): array
    {
        $resultado = Comissao::calcularComissaoTodosCompletaOtimizado($dataInicio, $dataFim, $emprId);
        $funcionarios = $resultado['funcionarios'] ?? [];

        // Cards de resumo
        $totalFuncionarios = count($funcionarios);
        $totalComissao = 0;
        $funcionariosComFalta = 0;
        $centrosUnicos = [];

        // Dados por funcionário
        $listaFuncionarios = [];
        // Comissão por centro
        $comissaoPorCentro = [];
        // Funcionários com falta
        $listaComFalta = [];
        // Ranking geral
        $rankingGeral = [];

        foreach ($funcionarios as $func) {
            $valorComissao = $func['valor_comissao_final'] ?? 0;
            $totalComissao += $valorComissao;

            $centroDesc = $func['cod_centro'] ? ($func['cod_centro'] . ' - ' . $func['desc_centro']) : 'SEM CENTRO';
            $centroId = $func['centro_trab_id'] ?? 0;
            $centrosUnicos[$centroId] = true;

            $diasFalta = $func['dias_com_falta'] ?? 0;
            if ($diasFalta > 0) {
                $funcionariosComFalta++;
            }

            // Lista de funcionários
            $dadosFunc = [
                'FUNC_ID' => $func['func_id'] ?? null,
                'COD_FUNC' => $func['cod_func'] ?? '',
                'NOME_FUNC' => $func['nome_func'] ?? '',
                'CENTRO_TRABALHO' => $centroDesc,
                'CENTRO_TRAB_ID' => $centroId,
                'TOTAL_PONTOS' => $func['total_pontos_apos_falta'] ?? $func['total_pontos_bruto'] ?? 0,
                'VALOR_COMISSAO' => $valorComissao,
                'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0,
                'DIAS_COM_FALTA' => $diasFalta,
                'TEM_FALTA' => $diasFalta > 0,
                'USA_REGRA_ESPECIFICA' => $func['usa_regra_especifica'] ?? false,
                'TIPO_VINCULO' => $func['tipo_vinculo'] ?? 'N'
            ];
            $listaFuncionarios[] = $dadosFunc;

            // Comissão por centro
            if (!isset($comissaoPorCentro[$centroId])) {
                $comissaoPorCentro[$centroId] = [
                    'CENTRO_TRABALHO' => $centroDesc,
                    'TOTAL_FUNCIONARIOS' => 0,
                    'TOTAL_PONTOS' => 0,
                    'TOTAL_COMISSAO' => 0,
                    'FUNCIONARIOS_COM_FALTA' => 0
                ];
            }
            $comissaoPorCentro[$centroId]['TOTAL_FUNCIONARIOS']++;
            $comissaoPorCentro[$centroId]['TOTAL_PONTOS'] += $dadosFunc['TOTAL_PONTOS'];
            $comissaoPorCentro[$centroId]['TOTAL_COMISSAO'] += $valorComissao;
            if ($diasFalta > 0) {
                $comissaoPorCentro[$centroId]['FUNCIONARIOS_COM_FALTA']++;
            }

            // Funcionários com falta
            if ($diasFalta > 0) {
                $listaComFalta[] = [
                    'COD_FUNC' => $func['cod_func'] ?? '',
                    'NOME_FUNC' => $func['nome_func'] ?? '',
                    'CENTRO_TRABALHO' => $centroDesc,
                    'DIAS_COM_FALTA' => $diasFalta,
                    'TOTAL_PONTOS' => $dadosFunc['TOTAL_PONTOS'],
                    'VALOR_COMISSAO' => $valorComissao
                ];
            }

            // Ranking (só quem tem comissão > 0)
            if ($valorComissao > 0) {
                $rankingGeral[] = [
                    'COD_FUNC' => $func['cod_func'] ?? '',
                    'NOME_FUNC' => $func['nome_func'] ?? '',
                    'CENTRO_TRABALHO' => $centroDesc,
                    'CENTRO_TRAB_ID' => $centroId,
                    'TOTAL_PONTOS' => $dadosFunc['TOTAL_PONTOS'],
                    'VALOR_COMISSAO' => $valorComissao,
                    'DIAS_TRABALHADOS' => $func['dias_trabalhados'] ?? 0
                ];
            }
        }

        // Ordenar ranking por valor de comissão (desc)
        usort($rankingGeral, fn($a, $b) => $b['VALOR_COMISSAO'] <=> $a['VALOR_COMISSAO']);

        // Ordenar comissão por centro (desc por total comissão)
        usort($comissaoPorCentro, fn($a, $b) => $b['TOTAL_COMISSAO'] <=> $a['TOTAL_COMISSAO']);

        // Ordenar faltas por dias (desc)
        usort($listaComFalta, fn($a, $b) => $b['DIAS_COM_FALTA'] <=> $a['DIAS_COM_FALTA']);

        return [
            'success' => true,
            'cards' => [
                'total_funcionarios' => $totalFuncionarios,
                'total_comissao' => $totalComissao,
                'funcionarios_com_falta' => $funcionariosComFalta,
                'total_centros' => count($centrosUnicos)
            ],
            'funcionarios' => $listaFuncionarios,
            'comissao_por_centro' => array_values($comissaoPorCentro),
            'funcionarios_com_falta' => $listaComFalta,
            'ranking' => $rankingGeral
        ];
    }
}
