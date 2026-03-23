<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para consulta de Centros de Trabalho no FOCCO
 * Tabela: FOCCO3I.TCENTROS_TRAB
 */
class CentroTrabalho
{
    public static function listarTodos($emprId = null)
    {
        $params = [
            'filtro_empr' => $emprId ? "AND CT.EMPR_ID = " . intval($emprId) : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.centroTrabalho.listarTodos', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'comissao.centroTrabalho.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function buscarPorCodigo($codCentro, $emprId = null)
    {
        $params = [
            'cod_centro' => "'" . str_replace("'", "''", $codCentro) . "'",
            'filtro_empr' => $emprId ? "AND CT.EMPR_ID = " . intval($emprId) : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.centroTrabalho.buscarPorCodigo', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function listarComRecursos($emprId = null)
    {
        $params = [
            'filtro_where' => $emprId ? "WHERE CT.EMPR_ID = " . intval($emprId) : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.centroTrabalho.listarComRecursos', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}
