<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Empresas/Filiais
 * 
 * Tabela FOCCO: TEMPRESAS
 * Colunas: ID, COD_EMP, RAZAO_SOCIAL, NOME_FAN, CNPJ, EMPR_ID
 */
class Empresa
{
    public static function listarAtivas()
    {
        $result = Database::switchParams('focco', [], 'comissao.empresa.listarAtivas', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'comissao.empresa.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function listarParaSelect()
    {
        $result = Database::switchParams('focco', [], 'comissao.empresa.listarParaSelect', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}
