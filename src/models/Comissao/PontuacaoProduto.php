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

            $result = Database::switchParams('focco', [
                'filtro_empr'   => $filtroEmpr,
                'filtro_centro' => $filtroCentro,
            ], 'comissao.pontuacao.listar_ativas', true);
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

            $result = Database::switchParams('focco', [
                'filtro_empr' => $filtroEmpr,
            ], 'comissao.pontuacao.listar_todas', true);
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
            $result = Database::switchParams('focco', ['id' => intval($id)], 'comissao.pontuacao.buscar_por_id', true);
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

            $result = Database::switchParams('focco', [
                'item_id'       => intval($itemId),
                'data_ref'      => $dataRef,
                'data_ref2'     => $dataRef,
                'filtro_centro' => $filtroCentro,
            ], 'comissao.pontuacao.buscar_vigente', true);
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
        $result = Database::switchParams('focco', [], 'comissao.pontuacao.proximo_id', true);
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

        $result = Database::switchParams('focco', [
            'hoje'        => $hoje,
            'hoje2'       => $hoje,
            'filtro_empr' => $filtroEmpr,
        ], 'comissao.pontuacao.mapa_vigentes', true);
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

            $result = Database::switchParams('focco', [
                'item_id'        => intval($itemId),
                'empr_id'        => intval($emprId),
                'filtro_mascara' => $filtroMascara,
                'filtro_centro'  => $filtroCentro,
            ], 'comissao.pontuacao.buscar_duplicata', true);
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
        $novoId    = self::proximoIdPontuacao();
        $emprId    = !empty($dados['empr_id'])        ? intval($dados['empr_id'])        : 'NULL';
        $itemId    = !empty($dados['item_id'])        ? intval($dados['item_id'])        : 'NULL';
        $itemprId  = !empty($dados['itempr_id'])      ? intval($dados['itempr_id'])      : 'NULL';
        $mascaraId = !empty($dados['mascara_id'])     ? intval($dados['mascara_id'])     : 'NULL';
        $centroId  = !empty($dados['centro_trab_id']) ? intval($dados['centro_trab_id']) : 'NULL';
        $dtIni     = $dados['dt_vigencia_ini'];
        $dtFimFrag = !empty($dados['dt_vigencia_fim'])
                   ? "TO_DATE('" . str_replace("'", "''", $dados['dt_vigencia_fim']) . "', 'YYYY-MM-DD')"
                   : 'NULL';
        $pontosUp  = floatval($dados['pontos_up']);

        $result = Database::switchParams('focco', [
            'novo_id'     => $novoId,
            'empr_id'     => $emprId,
            'item_id'     => $itemId,
            'itempr_id'   => $itemprId,
            'mascara_id'  => $mascaraId,
            'centro_id'   => $centroId,
            'pontos_up'   => $pontosUp,
            'dt_ini'      => $dtIni,
            'dt_fim_frag' => $dtFimFrag,
        ], 'comissao.pontuacao.inserir', true);
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
        $pontosUp  = floatval($dados['pontos_up']);

        $result = Database::switchParams('focco', [
            'pontos_up'   => $pontosUp,
            'dt_ini'      => $dados['dt_vigencia_ini'],
            'dt_fim_frag' => $dtFimFrag,
            'id'          => intval($id),
        ], 'comissao.pontuacao.atualizar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return true;
    }

    /**
     * Ativar/Desativar pontuação
     */
    public static function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $atvVal = $ativo === 'S' ? 'S' : 'N';

        $result = Database::switchParams('focco', [
            'ativo' => $atvVal,
            'id'    => intval($id),
        ], 'comissao.pontuacao.alterar_status', true);
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

        $result = Database::switchParams('focco', [
            'empr_id'        => $emprId,
            'filtro_item'    => $filtroItem,
            'filtro_mascara' => $filtroMascara,
        ], 'comissao.pontuacao.relatorio_itens', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }
}


