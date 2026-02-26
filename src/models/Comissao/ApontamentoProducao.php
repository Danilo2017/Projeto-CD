<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;


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
     */
    private function carregarCachePontuacao(): void
    {
        if (self::$cachePontuacao !== null) {
            return;
        }

        $pdo = Database::getInstance('focco');
        
        // Query única para carregar todas as pontuações ativas
        $sql = "SELECT /*+ RESULT_CACHE */ 
                    ID_PONTUACAO, ITEM_ID, ID_ITEMPR, ID_MASCARA, ID_CENTRO_TRAB, ID_EMPR,
                    PONTOS_UP, DT_VIGENCIA_INI, DT_VIGENCIA_FIM
                FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO 
                WHERE ATIVO = 'S'
                  AND DT_VIGENCIA_INI <= SYSDATE
                  AND (DT_VIGENCIA_FIM IS NULL OR DT_VIGENCIA_FIM >= SYSDATE)
                ORDER BY ITEM_ID, 
                    CASE WHEN ID_CENTRO_TRAB IS NOT NULL THEN 1 ELSE 2 END,
                    DT_VIGENCIA_INI DESC";
        
        $stmt = $pdo->query($sql);
        $pontuacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexar por chaves para acesso O(1)
        self::$cachePontuacao = [
            'por_item' => [],
            'por_itempr' => [],
            'por_mascara' => []
        ];
        
        foreach ($pontuacoes as $p) {
            // Por ITEM_ID (mais específico)
            if ($p['ITEM_ID']) {
                $key = $p['ITEM_ID'] . '_' . ($p['ID_CENTRO_TRAB'] ?? '0');
                if (!isset(self::$cachePontuacao['por_item'][$key])) {
                    self::$cachePontuacao['por_item'][$key] = $p;
                }
                // Também indexar só por item (fallback)
                if (!isset(self::$cachePontuacao['por_item'][$p['ITEM_ID']])) {
                    self::$cachePontuacao['por_item'][$p['ITEM_ID']] = $p;
                }
            }
            // Por ID_ITEMPR
            if ($p['ID_ITEMPR']) {
                if (!isset(self::$cachePontuacao['por_itempr'][$p['ID_ITEMPR']])) {
                    self::$cachePontuacao['por_itempr'][$p['ID_ITEMPR']] = $p;
                }
            }
            // Por MASCARA
            if ($p['ID_MASCARA']) {
                if (!isset(self::$cachePontuacao['por_mascara'][$p['ID_MASCARA']])) {
                    self::$cachePontuacao['por_mascara'][$p['ID_MASCARA']] = $p;
                }
            }
        }
    }

    /**
     * Buscar pontuação no cache (O(1) ao invés de query)
     */
    private function buscarPontuacaoCache(int $itemId, ?int $itemprId = null, ?int $mascaraId = null, ?int $centroTrabId = null): ?array
    {
        $this->carregarCachePontuacao();
        
        // Prioridade: item+centro > item > itempr > mascara
        $keyComCentro = $itemId . '_' . ($centroTrabId ?? '0');
        
        if (isset(self::$cachePontuacao['por_item'][$keyComCentro])) {
            return self::$cachePontuacao['por_item'][$keyComCentro];
        }
        
        if (isset(self::$cachePontuacao['por_item'][$itemId])) {
            return self::$cachePontuacao['por_item'][$itemId];
        }
        
        if ($itemprId && isset(self::$cachePontuacao['por_itempr'][$itemprId])) {
            return self::$cachePontuacao['por_itempr'][$itemprId];
        }
        
        if ($mascaraId && isset(self::$cachePontuacao['por_mascara'][$mascaraId])) {
            return self::$cachePontuacao['por_mascara'][$mascaraId];
        }
        
        return null;
    }

    /**
     * Carregar cache de faixas
     */
    private function carregarCacheFaixa(): void
    {
        if (self::$cacheFaixa !== null) {
            return;
        }

        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT /*+ RESULT_CACHE */
                    ID_FAIXA, CENTRO_TRAB_ID, DESCRICAO, VALOR_COMISSAO, TIPO, 
                    PONTO_INICIAL, PONTO_FINAL
                FROM FOCCO3I.TGAZIN_FAIXA_COMISSAO 
                WHERE ATIVO = 'S'
                  AND DT_VIGENCIA_INI <= SYSDATE
                  AND (DT_VIGENCIA_FIM IS NULL OR DT_VIGENCIA_FIM >= SYSDATE)
                ORDER BY CENTRO_TRAB_ID, PONTO_INICIAL";
        
        $stmt = $pdo->query($sql);
        $faixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
    private function buscarFaixaCache(?int $centroTrabId): ?array
    {
        $this->carregarCacheFaixa();
        
        // Primeiro tenta com centro específico, depois genérico
        if ($centroTrabId && isset(self::$cacheFaixa[$centroTrabId])) {
            return self::$cacheFaixa[$centroTrabId][0] ?? null;
        }
        
        // Fallback para faixa sem centro
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
    public function listarApontamentosPorMaquina($dataInicio, $dataFim, $codMaquina, $codEmp)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TITENS.COD_ITEM AS CODIGO,
                    TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID AS MASC_ID,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS_ENGENHARIA TITENS_ENGENHARIA ON TITENS_EMPR.ID = TITENS_ENGENHARIA.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TITENS_ENG_CONF TITENS_ENG_CONF ON TMASC_ITEM.ID = TITENS_ENG_CONF.TMASC_ITEM_ID
                WHERE TMAQUINAS.ID = :cod_maquina
                AND TORDENS.EMPR_ID = :cod_emp
                AND TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
                GROUP BY 
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO,
                    TMAQUINAS.ID,
                    TFUNCIONARIOS.ID,
                    TFUNCIONARIOS.NOME,
                    TORDENS_MOVTO.DT_APONT
                ORDER BY TITENS.DESC_TECNICA ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cod_maquina', $codMaquina, PDO::PARAM_INT);
        $stmt->bindParam(':cod_emp', $codEmp, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function listarApontamentos($dataInicio, $dataFim, $emprId = null, $maquinaId = null, $funcId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TORDENS_MOVTO.ID AS ID_MOVTO,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    TO_CHAR(TORDENS_MOVTO.DT_APONT, 'HH24:MI') AS HORA_APONTAMENTO,
                    TORDENS_MOVTO.QUANTIDADE,
                    TORDENS.ID AS ID_ORDEM,
                    TORDENS.NUM_ORDEM,
                    TITENS.ID AS ID_ITEM,
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.MASCARA,
                    TOPERACAO.ID AS ID_OPERACAO,
                    TOPERACAO.DESCRICAO AS DESC_OPERACAO,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    (TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS PONTOS_CALCULADOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($maquinaId) {
            $sql .= " AND TMAQUINAS.ID = :maquina_id";
        }
        
        if ($funcId) {
            $sql .= " AND TFUNCIONARIOS.ID = :func_id";
        }
        
        $sql .= " ORDER BY TORDENS_MOVTO.DT_APONT DESC, TFUNCIONARIOS.NOME";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        
        if ($funcId) {
            $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function resumoPorFuncionario($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        // VERSÃO OTIMIZADA: usa LEFT JOIN com pontuação pré-calculada
        // ao invés de subquery correlacionada (que era executada N vezes)
        // 
        // A estratégia é:
        // 1. Buscar quantidades por funcionário/item (agregação rápida)
        // 2. Aplicar pontuação em PHP usando o cache estático
        
        $sql = "SELECT /*+ PARALLEL(4) */
                    TFUNCIONARIOS.ID AS FUNC_ID,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNC,
                    TITENS.ID AS ITEM_ID,
                    TITENS_EMPR.ID AS ITEMPR_ID,
                    TORDENS.TMASC_ITEM_ID AS MASCARA_ID,
                    TORDENS_ROT.CENTR_TRAB_ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    COUNT(TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";

        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        // Agrupar por funcionário + item para depois aplicar pontuação
        $sql .= " GROUP BY TFUNCIONARIOS.ID, TFUNCIONARIOS.COD_FUNC, TFUNCIONARIOS.NOME,
                          TITENS.ID, TITENS_EMPR.ID, TORDENS.TMASC_ITEM_ID,
                          TORDENS_ROT.CENTR_TRAB_ID, CT.COD_CENTRO, CT.DESCRICAO
                  ORDER BY TFUNCIONARIOS.NOME";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $dadosBrutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($dadosBrutos)) {
            return [];
        }
        
        // Carregar cache de pontuação (uma vez só)
        $this->carregarCachePontuacao();
        
        // Agrupar por funcionário + centro e calcular pontos usando cache
        $resumoPorFunc = [];
        
        foreach ($dadosBrutos as $row) {
            $funcId = $row['FUNC_ID'];
            $centroTrabIdRow = $row['CENTRO_TRAB_ID'];
            $key = $funcId . '_' . $centroTrabIdRow;
            
            // Buscar pontuação do cache (O(1))
            $pontuacao = $this->buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $centroTrabIdRow ? (int)$centroTrabIdRow : null
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
                    'DESC_CENTRO' => $row['DESC_CENTRO']
                ];
            }
            
            $resumoPorFunc[$key]['QTD_APONTAMENTOS'] += (int)$row['QTD_APONTAMENTOS'];
            $resumoPorFunc[$key]['TOTAL_QTD_BOA'] += $quantidade;
            $resumoPorFunc[$key]['TOTAL_PONTOS'] += $totalPontosItem;
        }
        
        // Ordenar por nome do funcionário
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
    public function resumoPorCentroTrabalho($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    CT.ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND CT.ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY 
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO
                  ORDER BY TOTAL_QUANTIDADE DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por recurso/máquina com filtro por centro
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @param int $centroTrabId
     * @return array
     */
    public function resumoPorRecurso($dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    CT.ID AS CENTRO_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                INNER JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($centroTrabId) {
            $sql .= " AND CT.ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY 
                    TMAQUINAS.ID,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO,
                    CT.ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO
                  ORDER BY TOTAL_QUANTIDADE DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumo de produtividade por máquina/recurso
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array
     */
    public function resumoPorMaquina($dataInicio, $dataFim, $emprId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                INNER JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                INNER JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $sql .= " GROUP BY 
                    TMAQUINAS.ID,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO
                  ORDER BY TOTAL_PONTOS DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function pontosPorDiaFuncionario($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD') AS DATA_APONTAMENTO,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS,
                    COUNT(*) AS QTD_APONTAMENTOS,
                    MAX(CT.DESCRICAO) AS CENTRO_TRABALHO,
                    MAX(TMAQUINAS.DESCRICAO) AS RECURSO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Vínculo funcionário/recurso/centro
                LEFT JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                -- Pontuação do produto
                LEFT JOIN (
                    SELECT PP_SUB.*, ROW_NUMBER() OVER (PARTITION BY PP_SUB.ITEM_ID ORDER BY PP_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP_SUB
                    WHERE PP_SUB.ATIVO = 'S'
                ) PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.RN = 1
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND VF.ID_FUNCIONARIO = :func_id
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TRUNC(TORDENS_MOVTO.DT_APONT)
                  ORDER BY 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function listarApontamentosVinculados($funcId, $dataInicio, $dataFim, $emprId = null, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.MASCARA,
                    MAX(TOPERACAO.DESCRICAO) AS DESC_OPERACAO,
                    MAX(TMAQUINAS.COD_MAQUINA) AS COD_MAQUINA,
                    MAX(TMAQUINAS.DESCRICAO) AS DESC_MAQUINA,
                    MAX(CT.COD_CENTRO) AS COD_CENTRO,
                    MAX(CT.DESCRICAO) AS DESC_CENTRO,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    MAX(NVL(PP.PONTOS_UP, 0)) AS PONTOS_UP,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Vínculo funcionário/recurso/centro
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                -- Pontuação do produto (vigente na data do apontamento)
                LEFT JOIN (
                    SELECT PP_SUB.*, ROW_NUMBER() OVER (PARTITION BY PP_SUB.ITEM_ID ORDER BY PP_SUB.DT_VIGENCIA_INI DESC) AS RN
                    FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP_SUB
                    WHERE PP_SUB.ATIVO = 'S'
                ) PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.RN = 1
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND VF.ID_FUNCIONARIO = :func_id
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TITENS.COD_ITEM, TITENS.DESC_TECNICA, TMASC_ITEM.MASCARA
                  ORDER BY TOTAL_PONTOS DESC, TITENS.DESC_TECNICA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $data Data específica (YYYY-MM-DD)
     * @param int $emprId
     * @param int $maquinaId
     * @return array
     */
    public function produtividadeDiaria($data, $emprId = null, $maquinaId = null, $centroTrabId = null, $dataFim = null)
    {
        $pdo = Database::getInstance('focco');
        
        // ==== ETAPA 1: Buscar apontamentos base (query simples e rápida) ====
        $sqlBase = "SELECT 
                    TRUNC(TORDENS_MOVTO.DT_APONT) AS DT_APONT,
                    TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                    TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ID_ITEM,
                    TITENS.COD_ITEM AS CODIGO_PRODUTO,
                    TITENS.DESC_TECNICA AS DESC_PRODUTO,
                    TMASC_ITEM.ID AS ID_MASCARA,
                    TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM,
                    TORDENS.EMPR_ID,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS QUANTIDADE,
                    TMAQUINAS.ID AS ID_MAQUINA,
                    TMAQUINAS.COD_MAQUINA,
                    TMAQUINAS.DESCRICAO AS DESC_MAQUINA,
                    TORDENS_ROT.CENTR_TRAB_ID AS ID_CENTRO_TRAB,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TOPERACAO.DESCRICAO AS DESC_OPERACAO,
                    VF.ID_VINCULO AS ID_VINCULO
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TOPERACAO TOPERACAO ON TOPERACAO.ID = TORDENS_ROT.OPERACAO_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON VF.ID_RECURSO = TMAQUINAS.ID
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1";
        
        if ($dataFim) {
            $sqlBase .= " AND TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE(:data_inicio, 'YYYY-MM-DD') AND TO_DATE(:data_fim, 'YYYY-MM-DD') + 0.99999";
        } else {
            $sqlBase .= " AND TRUNC(TORDENS_MOVTO.DT_APONT) = TO_DATE(:data_inicio, 'YYYY-MM-DD')";
        }
        
        if ($emprId) {
            $sqlBase .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        if ($maquinaId) {
            $sqlBase .= " AND TMAQUINAS.ID = :maquina_id";
        }
        if ($centroTrabId) {
            $sqlBase .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sqlBase .= " GROUP BY 
                    TRUNC(TORDENS_MOVTO.DT_APONT),
                    TFUNCIONARIOS.ID, TFUNCIONARIOS.COD_FUNC, TFUNCIONARIOS.NOME,
                    TITENS.ID, TITENS.COD_ITEM, TITENS.DESC_TECNICA,
                    TMASC_ITEM.ID, TMASC_ITEM.MASCARA,
                    TORDENS.NUM_ORDEM, TORDENS.EMPR_ID,
                    TMAQUINAS.ID, TMAQUINAS.COD_MAQUINA, TMAQUINAS.DESCRICAO,
                    TORDENS_ROT.CENTR_TRAB_ID, CT.COD_CENTRO, CT.DESCRICAO,
                    TOPERACAO.DESCRICAO, VF.ID_VINCULO
                ORDER BY TRUNC(TORDENS_MOVTO.DT_APONT), TFUNCIONARIOS.NOME, TITENS.DESC_TECNICA";
        
        $stmt = $pdo->prepare($sqlBase);
        $stmt->bindParam(':data_inicio', $data, PDO::PARAM_STR);
        if ($dataFim) {
            $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        }
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $apontamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($apontamentos)) {
            return [];
        }
        
        // ==== ETAPA 2-4: Usar cache estático (carregado uma vez por request) ====
        // Isso evita 3 queries repetidas a cada chamada
        $this->carregarCachePontuacao();
        $this->carregarCacheFaixa();
        
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
            $pont = $this->buscarPontuacaoCache($itemId, null, $mascaraId, $centroTrabIdRow);
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
            $faixa = $this->buscarFaixaCache($centroTrabIdRow);
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
    public function resumoGeral($dataInicio, $dataFim, $emprId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS TOTAL_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS TOTAL_FUNCIONARIOS,
                    COUNT(DISTINCT TMAQUINAS.ID) AS TOTAL_MAQUINAS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    0 AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
    public function evolucaoDiaria($dataInicio, $dataFim, $emprId = null, $funcId = null, $maquinaId = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT 
                    TRUNC(TORDENS_MOVTO.DT_APONT) AS DATA,
                    COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                    COUNT(DISTINCT TFUNCIONARIOS.ID) AS QTD_FUNCIONARIOS,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        if ($funcId) {
            $sql .= " AND TFUNCIONARIOS.ID = :func_id";
        }
        
        if ($maquinaId) {
            $sql .= " AND TMAQUINAS.ID = :maquina_id";
        }
        
        $sql .= " GROUP BY TRUNC(TORDENS_MOVTO.DT_APONT)
                  ORDER BY DATA";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        if ($funcId) {
            $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        }
        
        if ($maquinaId) {
            $stmt->bindParam(':maquina_id', $maquinaId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ranking de funcionários por pontuação
     * @param string $dataInicio
     * @param string $dataFim
     * @param int $emprId
     * @param int $limite
     * @return array
     */
    public function rankingFuncionarios($dataInicio, $dataFim, $emprId = null, $limite = 20)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "SELECT * FROM (
                    SELECT 
                        TFUNCIONARIOS.ID AS ID_FUNCIONARIO,
                        TFUNCIONARIOS.COD_FUNC AS COD_FUNCIONARIO,
                        TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                        COUNT(DISTINCT TORDENS_MOVTO.ID) AS QTD_APONTAMENTOS,
                        SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                        SUM(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)) AS TOTAL_PONTOS,
                        ROUND(AVG(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0)), 2) AS MEDIA_PONTOS
                    FROM FOCCO3I.TORDENS TORDENS
                    INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                    INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                    INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                    INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                    INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                    INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = TORDENS.FUNC_ID
                    LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON PP.ITEM_ID = TITENS.ID
                        AND PP.ATIVO = 'S'
                        AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                        AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    WHERE TORDENS_ROT.APONTAMENTO = 1
                    AND TORDENS_ROT.OBRIGATORIO = 1
                    AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                    AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1";
        
        if ($emprId) {
            $sql .= " AND TORDENS.EMPR_ID = :empr_id";
        }
        
        $sql .= " GROUP BY 
                        TFUNCIONARIOS.ID,
                        TFUNCIONARIOS.COD_FUNC,
                        TFUNCIONARIOS.NOME
                      ORDER BY TOTAL_PONTOS DESC
                  ) WHERE ROWNUM <= :limite";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        
        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function pontosPorDia($dataInicio, $dataFim, $funcionarioId, $emprId, $centroTrabId = null)
    {
        $pdo = Database::getInstance('focco');
        
        // Usa subquery escalar para pontuação (evita duplicação por múltiplos matches)
        $sql = "SELECT 
                    TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD') AS DATA_APONTAMENTO,
                    SUM(ROUND(TORDENS_MOVTO.QUANTIDADE * NVL((
                        SELECT PP.PONTOS_UP 
                        FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP 
                        WHERE PP.ATIVO = 'S'
                          AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                          AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                          AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                          AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                          AND (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                        ORDER BY 
                            CASE WHEN PP.ITEM_ID IS NOT NULL THEN 1 
                                 WHEN PP.ID_ITEMPR IS NOT NULL THEN 2 
                                 WHEN PP.ID_MASCARA IS NOT NULL THEN 3 
                                 ELSE 4 END,
                            CASE WHEN PP.ID_CENTRO_TRAB IS NOT NULL THEN 1 ELSE 2 END
                        FETCH FIRST 1 ROW ONLY
                    ), 0), 2)) AS TOTAL_PONTOS,
                    COUNT(*) AS QTD_APONTAMENTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                -- Buscar máquina/recurso
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                -- Buscar vínculo do funcionário com o recurso/centro (ou vínculo tipo ajudante sem recurso)
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                    AND VF.ID_FUNCIONARIO = :funcionario_id
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id";
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = :centro_trab_id";
        }
        
        $sql .= " GROUP BY TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD')
                ORDER BY TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        $stmt->bindParam(':funcionario_id', $funcionarioId, PDO::PARAM_INT);
        
        if ($centroTrabId) {
            $stmt->bindParam(':centro_trab_id', $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista apontamentos com pontuação calculada para cálculo de comissão
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId ID da empresa
     * @return array
     */
    public function listarApontamentosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        $pdo = Database::getInstance('focco');
        
        // Converte array de IDs para string para usar em IN clause
        $idsPlaceholders = [];
        foreach ($funcionarioIds as $index => $id) {
            $idsPlaceholders[] = ':func_id_' . $index;
        }
        $inClause = implode(',', $idsPlaceholders);
        
        $sql = "SELECT 
                    TORDENS_MOVTO.ID AS APONTAMENTO_ID,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    TFUNCIONARIOS.ID AS FUNCIONARIO_ID,
                    TFUNCIONARIOS.COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ITEM_ID,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESC_ITEM,
                    TORDENS_MOVTO.QUANTIDADE,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    ROUND(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0), 2) AS TOTAL_PONTOS,
                    TORDENS.EMPR_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TORDENS.ID AS ORDEM_ID,
                    TORDENS.NUM_ORDEM
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Buscar máquina/recurso
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                -- Buscar vínculo do funcionário com o recurso/centro (ou vínculo tipo ajudante sem recurso)
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                -- Buscar dados do funcionário vinculado
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VF.ID_FUNCIONARIO
                -- Pontuação do produto (por ITEM ou por ITEM_EMPR ou por MASCARA)
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON 
                    (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                    AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id
                AND VF.ID_FUNCIONARIO IN ({$inClause})
                ORDER BY TFUNCIONARIOS.ID, TORDENS_MOVTO.DT_APONT";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        
        foreach ($funcionarioIds as $index => $id) {
            $stmt->bindValue(':func_id_' . $index, $id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista apontamentos vinculados manualmente (sem recurso) com pontuação
     * @param string $dataInicio (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param array $funcionarioIds Array de IDs de funcionários
     * @param int $emprId ID da empresa
     * @return array
     */
    public function listarApontamentosVinculadosComPontuacao($dataInicio, $dataFim, $funcionarioIds, $emprId)
    {
        $pdo = Database::getInstance('focco');
        
        // Converte array de IDs para string para usar em IN clause
        $idsPlaceholders = [];
        foreach ($funcionarioIds as $index => $id) {
            $idsPlaceholders[] = ':func_id_' . $index;
        }
        $inClause = implode(',', $idsPlaceholders);
        
        $sql = "SELECT 
                    VA.ID AS VINCULO_ID,
                    VA.APONTAMENTO_ID,
                    TORDENS_MOVTO.DT_APONT AS DATA_APONTAMENTO,
                    VA.FUNCIONARIO_ID,
                    TFUNCIONARIOS.COD_FUNC,
                    TFUNCIONARIOS.NOME AS NOME_FUNCIONARIO,
                    TITENS.ID AS ITEM_ID,
                    TITENS.COD_ITEM,
                    TITENS.DESC_TECNICA AS DESC_ITEM,
                    TORDENS_MOVTO.QUANTIDADE,
                    NVL(PP.PONTOS_UP, 0) AS PONTOS_UP,
                    ROUND(TORDENS_MOVTO.QUANTIDADE * NVL(PP.PONTOS_UP, 0), 2) AS TOTAL_PONTOS,
                    TORDENS.EMPR_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO,
                    TORDENS.ID AS ORDEM_ID,
                    TORDENS.NUM_ORDEM
                FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO VA
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_MOVTO.ID = VA.APONTAMENTO_ID
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TORDENS TORDENS ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                INNER JOIN FOCCO3I.TFUNCIONARIOS TFUNCIONARIOS ON TFUNCIONARIOS.ID = VA.FUNCIONARIO_ID
                LEFT JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TORDENS_ROT.CENTR_TRAB_ID
                -- Pontuação do produto (por ITEM ou por ITEM_EMPR ou por MASCARA)
                LEFT JOIN FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP ON 
                    (PP.ITEM_ID = TITENS.ID OR PP.ID_ITEMPR = TITENS_EMPR.ID OR PP.ID_MASCARA = TORDENS.TMASC_ITEM_ID)
                    AND PP.ATIVO = 'S'
                    AND PP.DT_VIGENCIA_INI <= TORDENS_MOVTO.DT_APONT
                    AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TORDENS_MOVTO.DT_APONT)
                    AND (PP.ID_EMPR IS NULL OR PP.ID_EMPR = TORDENS.EMPR_ID)
                    AND (PP.ID_CENTRO_TRAB IS NULL OR PP.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID)
                WHERE VA.ATIVO = 'S'
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(:data_inicio, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(:data_fim, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = :empr_id
                AND VA.FUNCIONARIO_ID IN ({$inClause})
                ORDER BY VA.FUNCIONARIO_ID, TORDENS_MOVTO.DT_APONT";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':data_inicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindParam(':data_fim', $dataFim, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        
        foreach ($funcionarioIds as $index => $id) {
            $stmt->bindValue(':func_id_' . $index, $id, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    public function pontosPorDiaBatch(string $dataInicio, string $dataFim, array $funcionarioIds, int $emprId, ?int $centroTrabId = null): array
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $pdo = Database::getInstance('focco');
        
        $placeholders = implode(',', array_fill(0, count($funcionarioIds), '?'));
        
        // Query SIMPLES sem subquery correlacionada - busca quantidades por funcionário/dia/item
        $sql = "SELECT /*+ PARALLEL(4) */
                    VF.ID_FUNCIONARIO,
                    TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD') AS DATA_APONTAMENTO,
                    TITENS.ID AS ITEM_ID,
                    TITENS_EMPR.ID AS ITEMPR_ID,
                    TORDENS.TMASC_ITEM_ID AS MASCARA_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    COUNT(*) AS QTD_APONTAMENTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                    AND VF.ID_FUNCIONARIO IN ($placeholders)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(?, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(?, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = ?";
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = ?";
        }
        
        $sql .= " GROUP BY VF.ID_FUNCIONARIO, TO_CHAR(TRUNC(TORDENS_MOVTO.DT_APONT), 'YYYY-MM-DD'),
                          TITENS.ID, TITENS_EMPR.ID, TORDENS.TMASC_ITEM_ID, TORDENS_ROT.CENTR_TRAB_ID
                  ORDER BY VF.ID_FUNCIONARIO, DATA_APONTAMENTO";
        
        $stmt = $pdo->prepare($sql);
        
        $i = 1;
        // IDs dos funcionários
        foreach ($funcionarioIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        
        // Datas e empresa
        $stmt->bindValue($i++, $dataInicio, PDO::PARAM_STR);
        $stmt->bindValue($i++, $dataFim, PDO::PARAM_STR);
        $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        
        if ($centroTrabId) {
            $stmt->bindValue($i++, $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $dadosBrutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Carregar cache de pontuação (carregado uma vez só por request)
        $this->carregarCachePontuacao();
        
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
            
            // Buscar pontuação do cache
            $pontuacao = $this->buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $row['CENTR_TRAB_ID'] ? (int)$row['CENTR_TRAB_ID'] : null
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
    public function resumoPontosBatch(string $dataInicio, string $dataFim, array $funcionarioIds, int $emprId, ?int $centroTrabId = null): array
    {
        if (empty($funcionarioIds)) {
            return [];
        }

        $pdo = Database::getInstance('focco');
        
        $placeholders = implode(',', array_fill(0, count($funcionarioIds), '?'));
        
        // Query SIMPLES sem subquery correlacionada - busca dados brutos
        $sql = "SELECT /*+ PARALLEL(4) */
                    VF.ID_FUNCIONARIO,
                    TRUNC(TORDENS_MOVTO.DT_APONT) AS DT_APONT,
                    TITENS.ID AS ITEM_ID,
                    TITENS_EMPR.ID AS ITEMPR_ID,
                    TORDENS.TMASC_ITEM_ID AS MASCARA_ID,
                    TORDENS_ROT.CENTR_TRAB_ID,
                    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
                    COUNT(*) AS QTD_APONTAMENTOS
                FROM FOCCO3I.TORDENS TORDENS
                INNER JOIN FOCCO3I.TORDENS_ROT TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
                INNER JOIN FOCCO3I.TORDENS_MOVTO TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO ON TITENS_PLANEJAMENTO.ID = TORDENS.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR TITENS_EMPR ON TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
                LEFT JOIN FOCCO3I.TORD_MOV_FAB_MAQ TORD_MOV_FAB_MAQ ON TORDENS_MOVTO.ID = TORD_MOV_FAB_MAQ.ORDEM_MOVT_ID
                LEFT JOIN FOCCO3I.TMAQUINAS TMAQUINAS ON TMAQUINAS.ID = TORD_MOV_FAB_MAQ.MAQUINA_ID
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC VF ON 
                    (VF.ID_RECURSO = TMAQUINAS.ID OR (VF.ID_RECURSO IS NULL AND VF.TIPO_VINCULO = 'A'))
                    AND VF.ID_CENTRO_TRAB = TORDENS_ROT.CENTR_TRAB_ID
                    AND VF.ATIVO = 'S'
                    AND VF.ID_EMPR = TORDENS.EMPR_ID
                    AND VF.ID_FUNCIONARIO IN ($placeholders)
                WHERE TORDENS_ROT.APONTAMENTO = 1
                AND TORDENS_ROT.OBRIGATORIO = 1
                AND TORDENS_MOVTO.DT_APONT >= TO_DATE(?, 'YYYY-MM-DD')
                AND TORDENS_MOVTO.DT_APONT < TO_DATE(?, 'YYYY-MM-DD') + 1
                AND TORDENS.EMPR_ID = ?";
        
        if ($centroTrabId) {
            $sql .= " AND TORDENS_ROT.CENTR_TRAB_ID = ?";
        }
        
        $sql .= " GROUP BY VF.ID_FUNCIONARIO, TRUNC(TORDENS_MOVTO.DT_APONT),
                           TITENS.ID, TITENS_EMPR.ID, TORDENS.TMASC_ITEM_ID, TORDENS_ROT.CENTR_TRAB_ID";
        
        $stmt = $pdo->prepare($sql);
        
        $i = 1;
        foreach ($funcionarioIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        $stmt->bindValue($i++, $dataInicio, PDO::PARAM_STR);
        $stmt->bindValue($i++, $dataFim, PDO::PARAM_STR);
        $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        
        if ($centroTrabId) {
            $stmt->bindValue($i++, $centroTrabId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $dadosBrutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Carregar cache de pontuação
        $this->carregarCachePontuacao();
        
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
            
            // Buscar pontuação do cache
            $pontuacao = $this->buscarPontuacaoCache(
                (int)$row['ITEM_ID'],
                $row['ITEMPR_ID'] ? (int)$row['ITEMPR_ID'] : null,
                $row['MASCARA_ID'] ? (int)$row['MASCARA_ID'] : null,
                $row['CENTR_TRAB_ID'] ? (int)$row['CENTR_TRAB_ID'] : null
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
}


