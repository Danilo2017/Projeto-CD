<?php

namespace src\models\Comissao;

use core\Database;


class ApontamentoProducao
{
    /**
     * Cache estático de pontuações - evita múltiplas consultas
     */
    private static ?array $cachePontuacao = null;
    private static ?array $cacheFaixa = null;
    private static ?array $cacheVinculo = null;
    private static ?string $cacheDataRef = null;

    /**
     * Limpar cache - chamar quando dados são alterados
     */
    public static function limparCache(): void
    {
        self::$cachePontuacao = null;
        self::$cacheFaixa = null;
        self::$cacheVinculo = null;
        self::$cacheDataRef = null;
    }

    /**
     * Carregar cache de pontuações uma vez
     * Chave inclui ID_EMPR para garantir filtro por filial
     */
    private static function carregarCachePontuacao(): void
    {
        if (self::$cachePontuacao !== null) {
            return;
        }

        self::$cachePontuacao = [
            'por_item'    => [],
            'por_itempr'  => [],
            'por_mascara' => [],
        ];

        $hoje   = date('Y-m-d');
        $result = Database::switchParams('focco', ['hoje' => $hoje], 'comissao.apontamento.cache_pontuacao', true);

        $pontuacoes = is_array($result['retorno']) ? $result['retorno'] : [];

        foreach ($pontuacoes as $p) {
            $emprId   = $p['ID_EMPR']        ?? '0';
            $centroId = $p['ID_CENTRO_TRAB'] ?? '0';

            // Índice por ITEM_ID (sempre populado, independente de máscara)
            if (!empty($p['ITEM_ID'])) {
                $key = $p['ITEM_ID'] . '_' . $centroId . '_' . $emprId;
                if (!isset(self::$cachePontuacao['por_item'][$key])) {
                    self::$cachePontuacao['por_item'][$key] = $p;
                }
            }

            // Índice por ID_ITEMPR
            if (!empty($p['ID_ITEMPR'])) {
                $key = $p['ID_ITEMPR'] . '_' . $emprId;
                if (!isset(self::$cachePontuacao['por_itempr'][$key])) {
                    self::$cachePontuacao['por_itempr'][$key] = $p;
                }
            }

            // Índice por ID_MASCARA (só quando máscara existe)
            if (!empty($p['ID_MASCARA'])) {
                $key = $p['ID_MASCARA'] . '_' . $centroId . '_' . $emprId;
                if (!isset(self::$cachePontuacao['por_mascara'][$key])) {
                    self::$cachePontuacao['por_mascara'][$key] = $p;
                }
            }
        }
    }

    /**
     * Buscar pontuação no cache (O(1) ao invés de query)
     * Filtra por empresa/filial para não retornar pontuação de outra filial
     */
    private static function buscarPontuacaoCache(int $itemId, ?int $itemprId = null, ?int $mascaraId = null, ?int $centroTrabId = null, ?int $emprId = null): ?array
    {
        self::carregarCachePontuacao();

        $centroKey = $centroTrabId ?? '0';
        $emprKey = $emprId ?? '0';

        // Quando há máscara específica, prioriza por máscara (mais preciso)
        // antes de cair no genérico por item
        if ($mascaraId) {
            if ($emprId && $centroTrabId && isset(self::$cachePontuacao['por_mascara'][$mascaraId . '_' . $centroKey . '_' . $emprKey])) {
                return self::$cachePontuacao['por_mascara'][$mascaraId . '_' . $centroKey . '_' . $emprKey];
            }
            if ($centroTrabId && isset(self::$cachePontuacao['por_mascara'][$mascaraId . '_' . $centroKey . '_0'])) {
                return self::$cachePontuacao['por_mascara'][$mascaraId . '_' . $centroKey . '_0'];
            }
            if ($emprId && isset(self::$cachePontuacao['por_mascara'][$mascaraId . '_0_' . $emprKey])) {
                return self::$cachePontuacao['por_mascara'][$mascaraId . '_0_' . $emprKey];
            }
            if (isset(self::$cachePontuacao['por_mascara'][$mascaraId . '_0_0'])) {
                return self::$cachePontuacao['por_mascara'][$mascaraId . '_0_0'];
            }
        }

        // Fallback: busca por ITEM_ID (genérico, sem distinção de máscara)
        if ($emprId && $centroTrabId) {
            $key = $itemId . '_' . $centroKey . '_' . $emprKey;
            if (isset(self::$cachePontuacao['por_item'][$key])) {
                return self::$cachePontuacao['por_item'][$key];
            }
        }
        if ($centroTrabId) {
            $key = $itemId . '_' . $centroKey . '_0';
            if (isset(self::$cachePontuacao['por_item'][$key])) {
                return self::$cachePontuacao['por_item'][$key];
            }
        }
        if ($emprId) {
            $key = $itemId . '_0_' . $emprKey;
            if (isset(self::$cachePontuacao['por_item'][$key])) {
                return self::$cachePontuacao['por_item'][$key];
            }
        }
        $key = $itemId . '_0_0';
        if (isset(self::$cachePontuacao['por_item'][$key])) {
            return self::$cachePontuacao['por_item'][$key];
        }

        // Busca por ID_ITEMPR
        if ($itemprId) {
            if ($emprId && isset(self::$cachePontuacao['por_itempr'][$itemprId . '_' . $emprKey])) {
                return self::$cachePontuacao['por_itempr'][$itemprId . '_' . $emprKey];
            }
            if (isset(self::$cachePontuacao['por_itempr'][$itemprId . '_0'])) {
                return self::$cachePontuacao['por_itempr'][$itemprId . '_0'];
            }
        }

        return null;
    }

    /**
     * Carregar cache de faixas
     */
    private static function carregarCacheFaixa(): void
    {
        if (self::$cacheFaixa !== null) {
            return;
        }

        $result = Database::switchParams('focco', [], 'comissao.apontamento.cacheFaixa', true);
        $faixas = $result['retorno'] ?? [];
        
        self::$cacheFaixa = [];
        foreach ($faixas as $f) {
            $centroId = $f['CENTRO_TRAB_ID'] ?? 0;
            if (!isset(self::$cacheFaixa[$centroId])) {
                self::$cacheFaixa[$centroId] = [];
            }
            self::$cacheFaixa[$centroId][] = $f;
        }
    }

    /**
     * Buscar faixa no cache
     */
    private static function buscarFaixaCache(?int $centroTrabId): ?array
    {
        self::carregarCacheFaixa();
        
        if ($centroTrabId && isset(self::$cacheFaixa[$centroTrabId])) {
            return self::$cacheFaixa[$centroTrabId][0] ?? null;
        }
        
        return self::$cacheFaixa[0][0] ?? null;
    }

    /**
     * Listar apontamentos de ordens de fabricação por período e máquina
     * Query baseada na estrutura real do FOCCO
     * 
     * @param string $dataInicio (DD/MM/YYYY)
     * @param string $dataFim (DD/MM/YYYY)
     * @param int $codMaquina
     * @param int $codEmp
     * @return array
     */
    public static function listarApontamentosPorMaquina($dataInicio, $dataFim, $codMaquina, $codEmp)
    {
        $params = [];
        $params['cod_maquina'] = intval($codMaquina);
        $params['cod_emp'] = intval($codEmp);
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.listarPorMaquina', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Listar apontamentos por período com filtros opcionais
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $maquinaId
     * @param int $funcId
     * @return array
     */
    public static function listarApontamentos($dataInicio, $dataFim, $emprId = null, $maquinaId = null, $funcId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_maquina'] = $maquinaId ? "AND TMAQUINAS.ID = " . intval($maquinaId) : '--';
        $params['filtro_func'] = $funcId ? "AND TFUNCIONARIOS.ID = " . intval($funcId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.listar', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Resumo de produtividade por funcionário (ULTRA-OTIMIZADO)
     * Usa CTE para evitar subquery correlacionada - muito mais rápido
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function resumoPorFuncionario($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoPorFunc', true);
        $dadosBrutos = $result['retorno'] ?? [];
        
        if (empty($dadosBrutos)) {
            return [];
        }
        
        self::carregarCachePontuacao();
        
        $resumoPorFunc = [];
        
        foreach ($dadosBrutos as $row) {
            $funcId = $row['FUNC_ID'];
            $centroTrabIdRow = $row['CENTRO_TRAB_ID'];
            $key = $funcId . '_' . $centroTrabIdRow;
            
            $pontuacao = self::buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $centroTrabIdRow ? (int)$centroTrabIdRow : null,
                $emprId ? (int)$emprId : null
            );
            
            $pontosUp = $pontuacao ? floatval($pontuacao['PONTOS_UP'] ?? 0) : 0;
            $quantidade = floatval($row['TOTAL_QUANTIDADE'] ?? 0);
            $totalPontosItem = $quantidade * $pontosUp;
            
            if (!isset($resumoPorFunc[$key])) {
                $resumoPorFunc[$key] = [
                    'FUNC_ID' => $funcId,
                    'COD_FUNC' => $row['COD_FUNC'],
                    'NOME_FUNC' => $row['NOME_FUNC'],
                    'QTD_APONTAMENTOS' => 0,
                    'TOTAL_QTD_BOA' => 0,
                    'TOTAL_QTD_REFUGO' => 0,
                    'TOTAL_PONTOS' => 0,
                    'CENTRO_TRAB_ID' => $centroTrabIdRow,
                    'COD_CENTRO' => $row['COD_CENTRO'],
                    'DESC_CENTRO' => $row['DESC_CENTRO'],
                    'TIPO_VINCULO' => $row['TIPO_VINCULO'] ?? 'N'
                ];
            }
            
            $resumoPorFunc[$key]['QTD_APONTAMENTOS'] += (int)$row['QTD_APONTAMENTOS'];
            $resumoPorFunc[$key]['TOTAL_QTD_BOA'] += $quantidade;
            $resumoPorFunc[$key]['TOTAL_PONTOS'] += $totalPontosItem;
        }
        
        usort($resumoPorFunc, fn($a, $b) => strcmp($a['NOME_FUNC'], $b['NOME_FUNC']));
        
        return array_values($resumoPorFunc);
    }

    /**
     * Resumo de produtividade por centro de trabalho
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND CT.ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoPorCentro', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Resumo de produtividade por recurso/máquina com filtro por centro
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function resumoPorRecurso($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_centro'] = $centroTrabId ? "AND CT.ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoPorRecurso', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Resumo de produtividade por máquina/recurso
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array
     */
    public static function resumoPorMaquina($dataInicio, $dataFim, $emprId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoPorMaquina', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Buscar pontos por dia de um funcionário específico
     * Usado para calcular comissão considerando faltas por dia
     * @param int $funcId
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function pontosPorDiaFuncionario($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['func_id'] = intval($funcId);
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.pontosPorDiaFunc', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Listar apontamentos vinculados ao funcionário via recurso/centro
     * Agrupado por item (código + máscara)
     * @param int $funcId ID do funcionário
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public static function listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['func_id'] = intval($funcId);
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.listarVinculados', true);
        return $result['retorno'] ?? [];
    }

    /**
     * @param string $data Data específica (YYYY-MM-DD)
     * @param int $emprId
     * @param int $maquinaId
     * @return array
     */
    public static function produtividadeDiaria($data, $emprId = null, $maquinaId = null, $centroTrabId = null, $dataFim = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $data) . "'";
        $params['filtro_data'] = $dataFim
            ? "AND TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE('" . str_replace("'", "''", $data) . "', 'YYYY-MM-DD') AND TO_DATE('" . str_replace("'", "''", $dataFim) . "', 'YYYY-MM-DD') + 0.99999"
            : "AND TRUNC(TORDENS_MOVTO.DT_APONT) = TO_DATE('" . str_replace("'", "''", $data) . "', 'YYYY-MM-DD')";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_maquina'] = $maquinaId ? "AND TMAQUINAS.ID = " . intval($maquinaId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.produtividadeDiaria', true);
        $apontamentos = $result['retorno'] ?? [];
        
        if (empty($apontamentos)) {
            return [];
        }
        
        // ==== ETAPA 2-4: Usar cache estático (carregado uma vez por request) ====
        self::carregarCachePontuacao();
        self::carregarCacheFaixa();
        
        // ==== ETAPA 5: Enriquecer apontamentos com dados do cache ====
        $dataRef = $dataFim ?: $data;
        $resultado = [];
        
        foreach ($apontamentos as $row) {
            $itemId = $row['ID_ITEM'];
            $centroTrabIdRow = $row['ID_CENTRO_TRAB'];
            $maquinaIdRow = $row['ID_MAQUINA'];
            $emprIdRow = $row['EMPR_ID'];
            $mascaraId = $row['ID_MASCARA'] ?? null;
            
            // Pontuação (do cache estático)
            $pontUp = 0;
            $idPontuacao = null;
            $temPontuacao = 'N';
            $pont = self::buscarPontuacaoCache($itemId, null, $mascaraId, $centroTrabIdRow, $emprIdRow ? (int)$emprIdRow : null);
            if ($pont) {
                $pontUp = floatval($pont['PONTOS_UP'] ?? 0);
                $idPontuacao = $pont['ID_PONTUACAO'];
                $temPontuacao = 'S';
            }
            
            // Faixa (do cache estático)
            $idFaixa = null;
            $descFaixa = null;
            $valorComissao = 0;
            $tipoFaixa = null;
            $temFaixa = 'N';
            $faixa = self::buscarFaixaCache($centroTrabIdRow);
            if ($faixa) {
                $idFaixa = $faixa['ID_FAIXA'];
                $descFaixa = $faixa['DESCRICAO'];
                $valorComissao = floatval($faixa['VALOR_COMISSAO'] ?? 0);
                $tipoFaixa = $faixa['TIPO'];
                $temFaixa = 'S';
            }
            
            // Montar registro com todos os campos esperados
            $row['PONTOS_UP'] = $pontUp;
            $row['ID_PONTUACAO'] = $idPontuacao;
            $row['TEM_PONTUACAO'] = $temPontuacao;
            $row['ID_FAIXA'] = $idFaixa;
            $row['DESC_FAIXA'] = $descFaixa;
            $row['VALOR_COMISSAO'] = $valorComissao;
            $row['TIPO_FAIXA'] = $tipoFaixa;
            $row['TEM_FAIXA'] = $temFaixa;
            $row['TEM_VINCULO'] = $row['ID_VINCULO'] ? 'S' : 'N';
            $row['QTD_VINCULADOS'] = 1; // Simplificado - não usado no cálculo principal
            
            $resultado[] = $row;
        }
        
        return $resultado;
    }

    /**
     * Resumo geral do período
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @return array
     */
    public static function resumoGeral($dataInicio, $dataFim, $emprId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoGeral', true);
        return $result['retorno'][0] ?? [];
    }

    /**
     * Evolução diária de pontos no período
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $funcId
     * @param int $maquinaId
     * @return array
     */
    public static function evolucaoDiaria($dataInicio, $dataFim, $emprId = null, $funcId = null, $maquinaId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';
        $params['filtro_func'] = $funcId ? "AND TFUNCIONARIOS.ID = " . intval($funcId) : '--';
        $params['filtro_maquina'] = $maquinaId ? "AND TMAQUINAS.ID = " . intval($maquinaId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.evolucaoDiaria', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Ranking de funcionários por pontuação
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $limite
     * @return array
     */
    public static function rankingFuncionarios($dataInicio, $dataFim, $emprId = null, $limite = 20)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['limite'] = intval($limite);
        $params['filtro_empr'] = $emprId ? "AND TORDENS.EMPR_ID = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.ranking', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Retorna pontos agrupados por dia para cálculo de comissão (otimizado)
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $funcionarioId ID do funcionário
     * @param int $emprId ID da empresa
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @return array
     */
    public static function pontosPorDia($dataInicio, $dataFim, $funcionarioId, $emprId, $centroTrabId = null)
    {
        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['empr_id'] = intval($emprId);
        $params['funcionario_id'] = intval($funcionarioId);
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.pontosPorDia', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Lista apontamentos com pontuação calculada para cálculo de comissão
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId ID da empresa
     * @return array
     */
    public static function listarApontamentosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['empr_id'] = intval($emprId);
        $params['in_func_ids'] = implode(',', array_map('intval', $funcionarioIds));

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.listarComPontuacao', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Lista apontamentos vinculados manualmente (sem recurso) com pontuação
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId ID da empresa
     * @return array
     */
    public static function listarApontamentosVinculadosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $params = [];
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['empr_id'] = intval($emprId);
        $params['in_func_ids'] = implode(',', array_map('intval', $funcionarioIds));

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.listarVincComPontuacao', true);
        return $result['retorno'] ?? [];
    }

    /**
     * MÉTODO ULTRA-OTIMIZADO - Buscar pontos por dia de MÚLTIPLOS funcionários
     * Usa query simples + cache PHP de pontuação (evita CTE complexa)
     * 
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId
     * @param int|null $centroTrabId
     * @return array Indexado por funcionario_id => [dias => [data => pontos]]
     */
    public static function pontosPorDiaBatch(string $dataInicio, string $dataFim, array $funcionarioIds, int $emprId, ?int $centroTrabId = null): array
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $params = [];
        $params['in_func_ids'] = implode(',', array_map('intval', $funcionarioIds));
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['empr_id'] = intval($emprId);
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.pontosPorDiaBatch', true);
        $dadosBrutos = $result['retorno'] ?? [];
        
        // Carregar cache de pontuação (carregado uma vez só por request)
        self::carregarCachePontuacao();
        
        // Agrupar por funcionário/dia e calcular pontos usando cache
        $pontosPorFuncDia = [];
        foreach ($funcionarioIds as $funcId) {
            $pontosPorFuncDia[$funcId] = [];
        }
        
        // Estrutura temporária para agregação
        $agregacao = [];
        
        foreach ($dadosBrutos as $row) {
            $funcId = $row['ID_FUNCIONARIO'];
            $data = $row['DATA_APONTAMENTO'];
            $key = $funcId . '_' . $data;
            
            // Buscar pontuação do cache (filtrado por empresa)
            $pontuacao = self::buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $row['CENTR_TRAB_ID'] ? (int)$row['CENTR_TRAB_ID'] : null,
                $emprId
            );
            
            $pontosUp = $pontuacao ? floatval($pontuacao['PONTOS_UP'] ?? 0) : 0;
            $quantidade = floatval($row['TOTAL_QUANTIDADE'] ?? 0);
            $pontosItem = $quantidade * $pontosUp;
            
            if (!isset($agregacao[$key])) {
                $agregacao[$key] = [
                    'func_id' => $funcId,
                    'data' => $data,
                    'total_pontos' => 0,
                    'qtd_apontamentos' => 0
                ];
            }
            
            $agregacao[$key]['total_pontos'] += $pontosItem;
            $agregacao[$key]['qtd_apontamentos'] += (int)$row['QTD_APONTAMENTOS'];
        }
        
        // Reorganizar por funcionário
        foreach ($agregacao as $item) {
            $funcId = $item['func_id'];
            $pontosPorFuncDia[$funcId][] = [
                'DATA_APONTAMENTO' => $item['data'],
                'TOTAL_PONTOS' => round($item['total_pontos'], 2),
                'QTD_APONTAMENTOS' => $item['qtd_apontamentos']
            ];
        }
        
        return $pontosPorFuncDia;
    }

    /**
     * MÉTODO ULTRA-OTIMIZADO - Resumo agregado de pontos por funcionário
     * Usa query simples + cache PHP de pontuação (elimina subquery correlacionada)
     * 
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários  
     * @param int $emprId
     * @param int|null $centroTrabId
     * @return array Indexado por funcionario_id => [total_pontos, qtd_apontamentos, dias_trabalhados]
     */
    public static function resumoPontosBatch(string $dataInicio, string $dataFim, array $funcionarioIds, int $emprId, ?int $centroTrabId = null): array
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $params = [];
        $params['in_func_ids'] = implode(',', array_map('intval', $funcionarioIds));
        $params['data_inicio'] = "'" . str_replace("'", "''", $dataInicio) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['empr_id'] = intval($emprId);
        $params['filtro_centro'] = $centroTrabId ? "AND TORDENS_ROT.CENTR_TRAB_ID = " . intval($centroTrabId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.apontamento.resumoPontosBatch', true);
        $dadosBrutos = $result['retorno'] ?? [];
        
        // Carregar cache de pontuação
        self::carregarCachePontuacao();
        
        // Inicializar resultado
        $pontosPorFunc = [];
        foreach ($funcionarioIds as $funcId) {
            $pontosPorFunc[$funcId] = [
                'TOTAL_PONTOS' => 0,
                'QTD_APONTAMENTOS' => 0,
                'DIAS_TRABALHADOS' => 0,
                '_dias_set' => [] // Conjunto de dias únicos
            ];
        }
        
        // Agregar em PHP usando cache de pontuação
        foreach ($dadosBrutos as $row) {
            $funcId = $row['ID_FUNCIONARIO'];
            
            // Buscar pontuação do cache (filtrado por empresa)
            $pontuacao = self::buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $row['CENTR_TRAB_ID'] ? (int)$row['CENTR_TRAB_ID'] : null,
                $emprId
            );
            
            $pontosUp = $pontuacao ? floatval($pontuacao['PONTOS_UP'] ?? 0) : 0;
            $quantidade = floatval($row['TOTAL_QUANTIDADE'] ?? 0);
            $pontosItem = round($quantidade * $pontosUp, 2);
            
            $pontosPorFunc[$funcId]['TOTAL_PONTOS'] += $pontosItem;
            $pontosPorFunc[$funcId]['QTD_APONTAMENTOS'] += (int)$row['QTD_APONTAMENTOS'];
            
            // Rastrear dias únicos
            $dia = $row['DT_APONT'];
            $pontosPorFunc[$funcId]['_dias_set'][$dia] = true;
        }
        
        // Calcular dias trabalhados e limpar _dias_set
        foreach ($pontosPorFunc as $funcId => &$dados) {
            $dados['DIAS_TRABALHADOS'] = count($dados['_dias_set']);
            $dados['TOTAL_PONTOS'] = round($dados['TOTAL_PONTOS'], 2);
            unset($dados['_dias_set']);
        }
        
        return $pontosPorFunc;
    }

    /**
     * Buscar pontos totais de um ou mais centros de trabalho por dia
     * Usado para calcular comissão de funcionários APOIO (que ganham sobre o total do centro)
     * 
     * @idsql comissao.apontamento.pontosTotaisCentroPorDia
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $centroIds Array de IDs de centros
     * @param int $emprId
     * @return array Indexado por centro_id => [data => total_pontos]
     */
    public static function pontosTotaisCentroPorDia(string $dataInicio, string $dataFim, array $centroIds, int $emprId): array
    {
        if (empty($centroIds)) {
            return [];
        }

        $inCentros = implode(',', array_map('intval', $centroIds));
        
        $params = [
            'data_inicio' => "'" . str_replace("'", "''", $dataInicio) . "'",
            'data_fim' => "'" . str_replace("'", "''", $dataFim) . "'",
            'empr_id' => intval($emprId),
            'centros_ids' => $inCentros
        ];

        $result = \core\Database::switchParams('focco', $params, 'comissao.apontamento.pontosTotaisCentroPorDia', true);
        $dadosBrutos = $result['retorno'] ?? [];
        
        // Carregar cache de pontuação
        self::carregarCachePontuacao();
        
        // Agregar por centro/dia usando cache de pontuação
        $pontosPorCentroDia = [];
        foreach ($centroIds as $centroId) {
            $pontosPorCentroDia[$centroId] = [];
        }
        
        // Estrutura temporária para agregação
        $agregacao = [];
        
        foreach ($dadosBrutos as $row) {
            $centroId = (int)$row['CENTR_TRAB_ID'];
            $data = $row['DATA_APONTAMENTO'];
            $key = $centroId . '_' . $data;
            
            // Buscar pontuação do cache
            $pontuacao = self::buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $centroId,
                $emprId
            );
            
            $pontosUp = $pontuacao ? floatval($pontuacao['PONTOS_UP'] ?? 0) : 0;
            $quantidade = floatval($row['TOTAL_QUANTIDADE'] ?? 0);
            $pontosItem = $quantidade * $pontosUp;
            
            if (!isset($agregacao[$key])) {
                $agregacao[$key] = [
                    'centro_id' => $centroId,
                    'data' => $data,
                    'total_pontos' => 0
                ];
            }
            
            $agregacao[$key]['total_pontos'] += $pontosItem;
        }
        
        // Reorganizar por centro
        foreach ($agregacao as $item) {
            $centroId = $item['centro_id'];
            $data = $item['data'];
            $pontosPorCentroDia[$centroId][$data] = round($item['total_pontos'], 2);
        }
        
        return $pontosPorCentroDia;
    }

    /**
     * Contar quantidade de recursos (funcionários) vinculados a cada centro de trabalho
     * Usado para calcular média de pontos por recurso no modo de apoio "MÉDIA"
     * 
     * @idsql comissao.apontamento.contarRecursosPorCentroDia
     * @param string $dataInicio (YYYY-MM-DD) - não usado, mantido por compatibilidade
     * @param string $dataFim (YYYY-MM-DD) - não usado, mantido por compatibilidade
     * @param array $centroIds Array de IDs de centros
     * @param int $emprId
     * @return array Indexado por centro_id => [data => quantidade_recursos]
     */
    public static function contarRecursosPorCentroDia(string $dataInicio, string $dataFim, array $centroIds, int $emprId): array
    {
        if (empty($centroIds)) {
            return [];
        }

        $inCentros = implode(',', array_map('intval', $centroIds));
        
        $params = [
            'empr_id' => intval($emprId),
            'centros_ids' => $inCentros
        ];

        try {
            $result = \core\Database::switchParams('focco', $params, 'comissao.apontamento.contarRecursosPorCentroDia', true);
            $dados = $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            // Se der erro na query, retorna array vazio
            return [];
        }
        
        // Organizar por centro - como é contagem de vinculados, é o mesmo valor para todos os dias
        $recursosPorCentroDia = [];
        foreach ($centroIds as $centroId) {
            $recursosPorCentroDia[(int)$centroId] = [];
        }
        
        // Gerar lista de datas no período
        $datasNoPeriodo = [];
        $dataAtual = new \DateTime($dataInicio);
        $dataFimObj = new \DateTime($dataFim);
        while ($dataAtual <= $dataFimObj) {
            $datasNoPeriodo[] = $dataAtual->format('Y-m-d');
            $dataAtual->modify('+1 day');
        }
        
        if (!empty($dados) && is_array($dados)) {
            foreach ($dados as $row) {
                $centroId = (int)($row['CENTRO_TRAB_ID'] ?? 0);
                $qtdRecursos = (int)($row['QTD_RECURSOS'] ?? 0);
                
                if ($centroId && $qtdRecursos > 0) {
                    // Aplicar a mesma quantidade para todas as datas do período
                    foreach ($datasNoPeriodo as $data) {
                        $recursosPorCentroDia[$centroId][$data] = $qtdRecursos;
                    }
                }
            }
        }
        
        return $recursosPorCentroDia;
    }

    /**
     * Listar apontamentos por centro de trabalho (sem filtro de funcionário)
     * Útil para mostrar apontamentos do centro em dias de apoio/média
     * 
     * @param int $centroTrabId ID do centro de trabalho
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId ID da empresa
     * @return array Apontamentos com pontuação calculada
     */
    public static function listarApontamentosPorCentro(int $centroTrabId, string $dataInicio, string $dataFim, int $emprId): array
    {
        // Usar produtividadeDiaria que já busca por centro
        $apontamentos = self::produtividadeDiaria($dataInicio, $emprId, null, $centroTrabId, $dataFim);
        
        if (empty($apontamentos)) {
            return [];
        }
        
        // Agrupar por item similar a listarApontamentosVinculados
        $agrupado = [];
        foreach ($apontamentos as $apt) {
            $itemId = $apt['ID_ITEM'] ?? 0;
            $mascaraId = $apt['ID_MASCARA'] ?? 0;
            $key = $itemId . '_' . $mascaraId;
            
            if (!isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'ID_ITEM' => $itemId,
                    'COD_ITEM' => $apt['COD_ITEM'] ?? '-',
                    'DESC_ITEM' => $apt['DESC_ITEM'] ?? '-',
                    'ID_MASCARA' => $mascaraId ?: null,
                    'CENTRO_TRAB_ID' => $centroTrabId,
                    'DESC_CENTRO' => $apt['DESC_CENTRO'] ?? '-',
                    'RECURSO' => $apt['RECURSO'] ?? $apt['DESC_MAQUINA'] ?? '-',
                    'QTD_APONTAMENTOS' => 0,
                    'TOTAL_QUANTIDADE' => 0,
                    'PONTOS_UP' => floatval($apt['PONTOS_UP'] ?? 0),
                    'TOTAL_PONTOS' => 0,
                    'TEM_PONTUACAO' => $apt['TEM_PONTUACAO'] ?? 'N',
                    'ORIGEM' => 'CENTRO',
                    'SEM_VINCULO' => true
                ];
            }
            
            $agrupado[$key]['QTD_APONTAMENTOS'] += intval($apt['QTD_APONTAMENTOS'] ?? 1);
            $agrupado[$key]['TOTAL_QUANTIDADE'] += floatval($apt['QUANTIDADE'] ?? $apt['TOTAL_QUANTIDADE'] ?? 0);
        }
        
        // Calcular pontos totais
        foreach ($agrupado as &$item) {
            $item['TOTAL_PONTOS'] = round($item['TOTAL_QUANTIDADE'] * $item['PONTOS_UP'], 4);
        }
        unset($item);
        
        return array_values($agrupado);
    }

    /**
     * Listar apontamentos do centro por dias específicos (para dias de apoio)
     * 
     * @param int $centroTrabId ID do centro de trabalho
     * @param array $datas Array de datas YYYY-MM-DD
     * @param int $emprId ID da empresa
     * @return array Apontamentos agrupados por data
     */
    public static function listarApontamentosCentroPorDias(int $centroTrabId, array $datas, int $emprId): array
    {
        if (empty($datas)) {
            return [];
        }
        
        $resultado = [];
        foreach ($datas as $data) {
            $apontamentos = self::produtividadeDiaria($data, $emprId, null, $centroTrabId, null);
            
            if (!empty($apontamentos)) {
                // Agrupar por item
                $agrupado = [];
                foreach ($apontamentos as $apt) {
                    $itemId = $apt['ID_ITEM'] ?? 0;
                    $mascaraId = $apt['ID_MASCARA'] ?? 0;
                    $key = $itemId . '_' . $mascaraId;
                    
                    if (!isset($agrupado[$key])) {
                        $agrupado[$key] = [
                            'ID_ITEM' => $itemId,
                            'COD_ITEM' => $apt['COD_ITEM'] ?? '-',
                            'DESC_ITEM' => $apt['DESC_ITEM'] ?? '-',
                            'ID_MASCARA' => $mascaraId ?: null,
                            'CENTRO_TRAB_ID' => $centroTrabId,
                            'DESC_CENTRO' => $apt['DESC_CENTRO'] ?? '-',
                            'RECURSO' => $apt['RECURSO'] ?? $apt['DESC_MAQUINA'] ?? '-',
                            'QTD_APONTAMENTOS' => 0,
                            'TOTAL_QUANTIDADE' => 0,
                            'PONTOS_UP' => floatval($apt['PONTOS_UP'] ?? 0),
                            'TOTAL_PONTOS' => 0,
                            'TEM_PONTUACAO' => $apt['TEM_PONTUACAO'] ?? 'N',
                            'DATA_APONTAMENTO' => $data,
                            'ORIGEM' => 'CENTRO',
                            'SEM_VINCULO' => true
                        ];
                    }
                    
                    $agrupado[$key]['QTD_APONTAMENTOS'] += intval($apt['QTD_APONTAMENTOS'] ?? 1);
                    $agrupado[$key]['TOTAL_QUANTIDADE'] += floatval($apt['QUANTIDADE'] ?? $apt['TOTAL_QUANTIDADE'] ?? 0);
                }
                
                // Calcular pontos totais
                foreach ($agrupado as &$item) {
                    $item['TOTAL_PONTOS'] = round($item['TOTAL_QUANTIDADE'] * $item['PONTOS_UP'], 4);
                }
                unset($item);
                
                $resultado[$data] = array_values($agrupado);
            }
        }
        
        return $resultado;
    }
}


