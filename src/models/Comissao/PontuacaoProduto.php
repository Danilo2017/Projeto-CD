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
    
    /**
     * Listar todas as pontuações ativas
     */
    public static function listarAtivas($emprId = null, $centroTrabId = null)
    {
        try {
            $params = [];
            $params['filtro_empr'] = $emprId ? "AND PP.ID_EMPR = " . intval($emprId) : '--';
            $params['filtro_centro'] = $centroTrabId ? "AND (PP.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR PP.ID_CENTRO_TRAB IS NULL)" : '--';

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.listarAtivas', true);
            if ($result['error']) {
                throw new \Exception($result['error']);
            }
            return $result['retorno'];
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
            $params = [];
            $params['filtro_empr'] = $emprId ? "AND PP.ID_EMPR = " . intval($emprId) : '--';

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.listarTodas', true);
            if ($result['error']) {
                throw new \Exception($result['error']);
            }
            return $result['retorno'];
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
            $params = [];
            $params['id'] = intval($id);

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.buscarPorId', true);
            if ($result['error']) {
                throw new \Exception($result['error']);
            }
            return $result['retorno'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Buscar pontuação por item e centro de trabalho
     */
    public static function buscarPontuacao($itemId, $centroTrabId = null, $dataReferencia = null)
    {
        try {
            $dataRef = $dataReferencia ?? date('Y-m-d');

            $params = [];
            $params['item_id'] = intval($itemId);
            $params['data_ref'] = "'" . str_replace("'", "''", $dataRef) . "'";
            $params['data_ref2'] = "'" . str_replace("'", "''", $dataRef) . "'";
            $params['filtro_centro'] = $centroTrabId
                ? "AND (PP.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR PP.ID_CENTRO_TRAB IS NULL)"
                : '--';

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.buscarPontuacao', true);
            if ($result['error']) {
                throw new \Exception($result['error']);
            }
            return $result['retorno'][0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obter próximo ID da sequência
     */
    private static function proximoIdPontuacao()
    {
        $result = Database::switchParams('focco', [], 'comissao.pontuacao.nextval', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0]['ID'] ?? null;
    }

    /**
     * Buscar pontuação duplicata (mesmo item + máscara + centro + empresa)
     */
    public static function buscarDuplicata($itemId, $emprId, $mascaraId = null, $centroTrabId = null)
    {
        try {
            $params = [];
            $params['item_id'] = intval($itemId);
            $params['empr_id'] = intval($emprId);
            $params['filtro_mascara'] = $mascaraId !== null
                ? "AND PP.ID_MASCARA = " . intval($mascaraId)
                : "AND PP.ID_MASCARA IS NULL";
            $params['filtro_centro'] = $centroTrabId !== null
                ? "AND PP.ID_CENTRO_TRAB = " . intval($centroTrabId)
                : "AND PP.ID_CENTRO_TRAB IS NULL";

            $result = Database::switchParams('focco', $params, 'comissao.pontuacao.buscarDuplicata', true);
            if ($result['error']) {
                return null;
            }
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
        $novoId = self::proximoIdPontuacao();

        $params = [];
        $params['id_pontuacao'] = intval($novoId);
        $params['empr_id'] = !empty($dados['empr_id']) ? intval($dados['empr_id']) : 'NULL';
        $params['item_id'] = !empty($dados['item_id']) ? intval($dados['item_id']) : 'NULL';
        $params['itempr_id'] = !empty($dados['itempr_id']) ? intval($dados['itempr_id']) : 'NULL';
        $params['mascara_id'] = !empty($dados['mascara_id']) ? intval($dados['mascara_id']) : 'NULL';
        $params['centro_trab_id'] = !empty($dados['centro_trab_id']) ? intval($dados['centro_trab_id']) : 'NULL';
        $params['pontos_up'] = floatval($dados['pontos_up']);
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $dtFim = !empty($dados['dt_vigencia_fim']) ? $dados['dt_vigencia_fim'] : null;
        $params['dt_vigencia_fim'] = $dtFim !== null ? "'" . str_replace("'", "''", $dtFim) . "'" : 'NULL';
        $params['id_usuario'] = !empty($dados['id_usuario']) ? intval($dados['id_usuario']) : 'NULL';

        $result = Database::switchParams('focco', $params, 'comissao.pontuacao.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $novoId;
    }

    /**
     * Atualizar pontuação
     */
    public static function atualizar($id, $dados)
    {
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $idUsuario = $dados['id_usuario'] ?? null;

        $params = [];
        $params['pontos_up'] = floatval($dados['pontos_up']);
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $params['dt_vigencia_fim'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.pontuacao.atualizar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Ativar/Desativar pontuação
     */
    public static function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $params = [];
        $params['ativo'] = "'" . str_replace("'", "''", $ativo) . "'";
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.pontuacao.alterarStatus', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Excluir pontuação (soft delete - desativa)
     */
    public static function excluir($id, $idUsuario = null)
    {
        return self::alterarStatus($id, 'N', $idUsuario);
    }
}


