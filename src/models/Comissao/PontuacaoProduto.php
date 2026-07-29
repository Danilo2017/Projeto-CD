<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Pontuação de Produto (UP)
 * Tabela customizada: FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
 */
class PontuacaoProduto
{
    /**
     * Cache para verificação de colunas
     */
    private static $columnCache = [];
    
    /**
     * Verifica se uma coluna existe na tabela TGAZIN_PONTUACAO_PRODUTO
     */
    private static function verificarColunaExiste($coluna)
    {
        if (isset(self::$columnCache[$coluna])) {
            return self::$columnCache[$coluna];
        }
        
        try {
            $params = [];
            $params['coluna'] = "'" . str_replace("'", "''", strtoupper($coluna)) . "'";

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.verificarColuna', true);
            if ($result['error']) {
                return true;
            }
            $exists = (int)($result['retorno'][0]['TOTAL'] ?? 0) > 0;
            self::$columnCache[$coluna] = $exists;
            return $exists;
        } catch (\Exception $e) {
            return true;
        }
    }
    
    private static function sqlListagem(): string
    {
        return "SELECT
            PP.ID_PONTUACAO,
            TE.COD_EMP,
            TI.COD_ITEM,
            TI.DESC_TECNICA    AS DESC_ITEM,
            PP.ID_MASCARA,
            TMI.MASCARA,
            TCT.COD_CENTRO,
            TCT.DESCRICAO      AS DESC_CENTRO,
            PP.ID_CENTRO_TRAB,
            PP.PONTOS_UP,
            TO_CHAR(PP.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
            TO_CHAR(PP.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
            PP.ATIVO,
            PP.ITEM_ID,
            PP.ID_ITEMPR
        FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
        LEFT JOIN TITENS         TI  ON TI.ID  = PP.ITEM_ID
        LEFT JOIN TMASC_ITEM     TMI ON TMI.ID = PP.ID_MASCARA
        LEFT JOIN TCENTROS_TRAB  TCT ON TCT.ID = PP.ID_CENTRO_TRAB
        LEFT JOIN TEMPRESAS      TE  ON TE.ID  = PP.ID_EMPR";
    }

    /**
     * Listar todas as pontuações ativas
     */
    public static function listarAtivas($emprId = null, $centroTrabId = null)
    {
        try {
            $filtroEmpr   = $emprId       ? "AND PP.ID_EMPR = " . intval($emprId) : '';
            $filtroCentro = $centroTrabId ? "AND (PP.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR PP.ID_CENTRO_TRAB IS NULL)" : '';

            $sql = self::sqlListagem() . "
            WHERE PP.ATIVO = 'S'
            $filtroEmpr
            $filtroCentro
            ORDER BY PP.ID_PONTUACAO DESC";

            $result = Database::switchParams('focco', [], null, true, true, null, $sql);
            if (!empty($result['error'])) throw new \Exception($result['error']);
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Listar todas as pontuações (ativas e inativas)
     */
    public static function listarTodas($emprId = null)
    {
        try {
            $filtroEmpr = $emprId ? "AND PP.ID_EMPR = " . intval($emprId) : '';

            $sql = self::sqlListagem() . "
            WHERE 1=1
            $filtroEmpr
            ORDER BY PP.ID_PONTUACAO DESC";

            $result = Database::switchParams('focco', [], null, true, true, null, $sql);
            if (!empty($result['error'])) throw new \Exception($result['error']);
            return is_array($result['retorno']) ? $result['retorno'] : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Buscar pontuação por ID
     */
    public static function buscarPorId($id)
    {
        try {
            $sql = self::sqlListagem() . "
            WHERE PP.ID_PONTUACAO = :id";

            $result = Database::switchParams('focco', ['id' => intval($id)], null, true, true, null, $sql);
            if (!empty($result['error'])) throw new \Exception($result['error']);
            return $result['retorno'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Buscar pontuação vigente por item e centro de trabalho
     */
    public static function buscarPontuacao($itemId, $centroTrabId = null, $dataReferencia = null)
    {
        try {
            $dataRef      = $dataReferencia ?? date('Y-m-d');
            $filtroCentro = $centroTrabId
                ? "AND (PP.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR PP.ID_CENTRO_TRAB IS NULL)"
                : '';

            $sql = "SELECT PP.ID_PONTUACAO, PP.PONTOS_UP, PP.ID_CENTRO_TRAB, PP.ID_MASCARA, PP.ITEM_ID, PP.ID_ITEMPR
            FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
            WHERE PP.ITEM_ID = :item_id
              AND PP.ATIVO = 'S'
              AND PP.DT_VIGENCIA_INI <= TO_DATE(:data_ref,  'YYYY-MM-DD')
              AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TO_DATE(:data_ref2, 'YYYY-MM-DD'))
              $filtroCentro
            ORDER BY PP.ID_CENTRO_TRAB NULLS LAST
            FETCH FIRST 1 ROW ONLY";

            $result = Database::switchParams('focco', [
                'item_id'   => intval($itemId),
                'data_ref'  => $dataRef,
                'data_ref2' => $dataRef,
            ], null, true, true, null, $sql);
            if (!empty($result['error'])) throw new \Exception($result['error']);
            return $result['retorno'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obter próximo ID (MAX+1)
     */
    private static function proximoIdPontuacao()
    {
        $sql = "SELECT NVL(MAX(ID_PONTUACAO), 0) + 1 AS ID FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO";
        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return (int)($result['retorno'][0]['ID'] ?? 1);
    }

    /**
     * Retorna mapa {ID_MASCARA => PONTOS_UP, "C:COD_ITEM" => PONTOS_UP} para enriquecimento em lote.
     * Usa todas as pontuações vigentes ativas da empresa.
     */
    public static function mapaVigentes(?int $emprId): array
    {
        $hoje       = date('Y-m-d');
        $filtroEmpr = $emprId ? "AND PP.ID_EMPR = " . $emprId : '';

        $sql = "SELECT PP.ID_MASCARA, PP.ITEM_ID, TI.COD_ITEM, PP.PONTOS_UP
        FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
        LEFT JOIN TITENS TI ON TI.ID = PP.ITEM_ID
        WHERE PP.ATIVO = 'S'
          AND PP.DT_VIGENCIA_INI <= TO_DATE(:hoje, 'YYYY-MM-DD')
          AND (PP.DT_VIGENCIA_FIM IS NULL OR PP.DT_VIGENCIA_FIM >= TO_DATE(:hoje2, 'YYYY-MM-DD'))
          $filtroEmpr";

        $result = Database::switchParams('focco', ['hoje' => $hoje, 'hoje2' => $hoje], null, true, true, null, $sql);
        if (!empty($result['error'])) return [];

        $mapa = [];
        foreach (($result['retorno'] ?? []) as $p) {
            // Mapa primário por ID_MASCARA
            if (!empty($p['ID_MASCARA'])) {
                $mapa['M:' . (int)$p['ID_MASCARA']] = (float)$p['PONTOS_UP'];
            }
            // Mapa secundário por COD_ITEM (apenas se não tiver máscara específica)
            if (!empty($p['COD_ITEM']) && empty($p['ID_MASCARA'])) {
                $key = 'C:' . $p['COD_ITEM'];
                if (!isset($mapa[$key])) {
                    $mapa[$key] = (float)$p['PONTOS_UP'];
                }
            }
        }
        return $mapa;
    }

    /**
     * Buscar pontuação duplicata (mesmo item + máscara + centro + empresa)
     */
    public static function buscarDuplicata($itemId, $emprId, $mascaraId = null, $centroTrabId = null)
    {
        try {
            $filtroMascara = $mascaraId !== null
                ? "AND PP.ID_MASCARA = " . intval($mascaraId)
                : "AND PP.ID_MASCARA IS NULL";
            $filtroCentro = $centroTrabId !== null
                ? "AND PP.ID_CENTRO_TRAB = " . intval($centroTrabId)
                : "AND PP.ID_CENTRO_TRAB IS NULL";

            $sql = "SELECT PP.ID_PONTUACAO
            FROM FOCCO3I.TGAZIN_PONTUACAO_PRODUTO PP
            WHERE PP.ITEM_ID  = :item_id
              AND PP.ID_EMPR  = :empr_id
              $filtroMascara
              $filtroCentro";

            $result = Database::switchParams('focco', [
                'item_id' => intval($itemId),
                'empr_id' => intval($emprId),
            ], null, true, true, null, $sql);
            if (!empty($result['error'])) return null;
            return $result['retorno'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Inserir nova pontuação
     */
    public static function inserir($dados)
    {
        $novoId      = self::proximoIdPontuacao();
        $emprId      = !empty($dados['empr_id'])       ? intval($dados['empr_id'])       : 'NULL';
        $itemId      = !empty($dados['item_id'])       ? intval($dados['item_id'])       : 'NULL';
        $itemprId    = !empty($dados['itempr_id'])     ? intval($dados['itempr_id'])     : 'NULL';
        $mascaraId   = !empty($dados['mascara_id'])    ? intval($dados['mascara_id'])    : 'NULL';
        $centroId    = !empty($dados['centro_trab_id'])? intval($dados['centro_trab_id']): 'NULL';
        $usuarioId   = !empty($dados['id_usuario'])    ? intval($dados['id_usuario'])    : 'NULL';
        $dtIni       = str_replace("'", "''", $dados['dt_vigencia_ini']);
        $dtFimFrag   = !empty($dados['dt_vigencia_fim'])
                     ? "TO_DATE('" . str_replace("'", "''", $dados['dt_vigencia_fim']) . "', 'YYYY-MM-DD')"
                     : 'NULL';
        $pontosUp    = floatval($dados['pontos_up']);

        $sql = "INSERT INTO FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
            (ID_PONTUACAO, ID_EMPR, ITEM_ID, ID_ITEMPR, ID_MASCARA, ID_CENTRO_TRAB,
             PONTOS_UP, DT_VIGENCIA_INI, DT_VIGENCIA_FIM, ATIVO)
        VALUES
            ($novoId, $emprId, $itemId, $itemprId, $mascaraId, $centroId,
             $pontosUp, TO_DATE('$dtIni', 'YYYY-MM-DD'), $dtFimFrag, 'S')";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return $novoId;
    }

    /**
     * Atualizar pontuação
     */
    public static function atualizar($id, $dados)
    {
        $dtFim     = !empty($dados['dt_vigencia_fim']) ? $dados['dt_vigencia_fim'] : null;
        $dtFimFrag = $dtFim
                   ? "TO_DATE('" . str_replace("'", "''", $dtFim) . "', 'YYYY-MM-DD')"
                   : 'NULL';
        $usuarioId = !empty($dados['id_usuario']) ? intval($dados['id_usuario']) : 'NULL';
        $dtIni     = str_replace("'", "''", $dados['dt_vigencia_ini']);

        $pontosUp = floatval($dados['pontos_up']);
        $idInt    = intval($id);

        $sql = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
        SET PONTOS_UP        = $pontosUp,
            DT_VIGENCIA_INI  = TO_DATE('$dtIni', 'YYYY-MM-DD'),
            DT_VIGENCIA_FIM  = $dtFimFrag
        WHERE ID_PONTUACAO = $idInt";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return true;
    }

    /**
     * Ativar/Desativar pontuação
     */
    public static function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $usuarioId = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $atvVal    = $ativo === 'S' ? 'S' : 'N';

        $sql = "UPDATE FOCCO3I.TGAZIN_PONTUACAO_PRODUTO
        SET ATIVO = '$atvVal'
        WHERE ID_PONTUACAO = :id";

        $result = Database::switchParams('focco', ['id' => intval($id)], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return true;
    }

    /**
     * Excluir pontuação (soft delete - desativa)
     */
    public static function excluir($id, $idUsuario = null)
    {
        return self::alterarStatus($id, 'N', $idUsuario);
    }

    /**
     * Relatório de itens fabricados com UEP.
     * Filtros opcionais: cod_item (múltiplos separados por vírgula) e id_mascara (inteiro).
     */
    public static function relatorioItens(int $emprId, ?string $codItens = null, ?int $idMascara = null): array
    {
        $filtroItem = '';
        if (!empty($codItens)) {
            $lista = array_filter(array_map('trim', explode(',', $codItens)));
            if (!empty($lista)) {
                $safe = array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $lista);
                $filtroItem = "AND TITENS.COD_ITEM IN (" . implode(',', $safe) . ")";
            }
        }
        $filtroMascara = $idMascara ? "AND TMASC_ITEM.ID = " . $idMascara : '';

        $sql = "SELECT DISTINCT
               TITENS_EMPR.EMPR_ID,
               TITENS.COD_ITEM,
               TITENS.DESC_TECNICA,
               TMASC_ITEM.MASCARA,
               TMASC_ITEM.ID TMASC_ITEM_ID,
               NVL(TITENS_PLAN_CONF.UEP, TITENS_PLANEJAMENTO.UEP) UEP,
               (SELECT DESCRICAO  FROM TTANQUES WHERE ID = NVL(TITENS_PLAN_CONF.TANQUE_ID, TITENS_PLANEJAMENTO.TANQUE_ID)) TANQUE,
               (SELECT COD_TANQUE FROM TTANQUES WHERE ID = NVL(TITENS_PLAN_CONF.TANQUE_ID, TITENS_PLANEJAMENTO.TANQUE_ID)) COD_TANQUE
          FROM TITENS_EMPR TITENS_EMPR,
               TITENS TITENS,
               TMASC_ITEM TMASC_ITEM,
               TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
               TITENS_PLAN_CONF TITENS_PLAN_CONF,
               TGRP_CLAS_ITE TGRP_CLAS_ITE,
               TITENS_COMERCIAL TITENS_COMERCIAL,
               TGRP_CLAS_ITE TGRP_CLAS_ITE1,
               TCLAS_AGRUP_METAS TCLAS_AGRUP_METAS,
               TAGRUP_METAS TAGRUP_METAS
         WHERE TITENS_EMPR.ID = TITENS_COMERCIAL.ITEMPR_ID
           AND TITENS_EMPR.ID = TITENS_PLANEJAMENTO.ITEMPR_ID
           AND TITENS.ID = TITENS_EMPR.ITEM_ID
           AND TMASC_ITEM.ID(+) = TITENS_PLAN_CONF.TMASC_ITEM_ID
           AND TITENS_PLANEJAMENTO.ID = TITENS_PLAN_CONF.ITPL_ID(+)
           AND TGRP_CLAS_ITE.ID = TITENS_PLANEJAMENTO.GRP_CLAS_ID
           AND TGRP_CLAS_ITE1.ID = TCLAS_AGRUP_METAS.GRP_CLAS_ID(+)
           AND TGRP_CLAS_ITE1.ID = TITENS_COMERCIAL.GRP_CLAS_ID
           AND TAGRUP_METAS.ID(+) = TCLAS_AGRUP_METAS.TAGRUP_MET_ID
           AND TITENS_EMPR.EMPR_ID = $emprId
           $filtroItem
           $filtroMascara
        ORDER BY TITENS.COD_ITEM DESC";

        $result = Database::switchParams('focco', [], null, true, true, null, $sql);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}


