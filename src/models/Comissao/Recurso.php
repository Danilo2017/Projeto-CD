<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para consulta de Recursos/Máquinas no FOCCO
 * Tabela: FOCCO3I.TMAQUINAS
 */
class Recurso
{
    public static function listarAtivos($emprId = null, $centroTrabId = null)
    {
        $params = [
            'filtro_empr' => $emprId ? "AND M.EMPR_ID = " . intval($emprId) : '--',
            'filtro_centro' => $centroTrabId ? "AND M.CENTR_TRAB_ID = " . intval($centroTrabId) : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.recurso.listarAtivos', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'comissao.recurso.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function buscarPorCodigo($codMaquina, $emprId)
    {
        $params = [
            'cod_maquina' => "'" . str_replace("'", "''", $codMaquina) . "'",
            'empr_id' => intval($emprId)
        ];
        $result = Database::switchParams('focco', $params, 'comissao.recurso.buscarPorCodigo', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function listarPorCentroTrabalho($centroTrabId)
    {
        $params = ['centro_trab_id' => intval($centroTrabId)];
        $result = Database::switchParams('focco', $params, 'comissao.recurso.listarPorCentroTrabalho', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function listarPorFuncionario($funcId)
    {
        $params = ['func_id' => intval($funcId)];
        $result = Database::switchParams('focco', $params, 'comissao.recurso.listarPorFuncionario', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}
