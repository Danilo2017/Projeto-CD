<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Gestão de Retrabalho
 * Tabela: FOCCO3I.TGAZIN_RETRABALHO
 */
class Retrabalho
{
    const TIPO_PERCENTUAL = 'P';
    const TIPO_VALOR_FIXO = 'V';
    const TIPO_ZERAR = 'Z';

    /**
     * Inserir retrabalho
     */
    public static function inserir($dados)
    {
        $tipoImpacto = $dados['tipo_impacto'] ?? self::TIPO_PERCENTUAL;
        $valorImpacto = $dados['valor_impacto'] ?? 0;

        $params = [];
        $params['id_empr'] = intval($dados['id_empr']);
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_recurso'] = intval($dados['id_recurso']);
        $params['id_item'] = intval($dados['id_item']);
        $params['id_mascara'] = intval($dados['id_mascara']);
        $params['id_ordem'] = intval($dados['id_ordem']);
        $params['dt_retrabalho'] = "'" . str_replace("'", "''", $dados['dt_retrabalho']) . "'";
        $params['quantidade'] = floatval($dados['quantidade']);
        $params['motivo'] = "'" . str_replace("'", "''", $dados['motivo']) . "'";
        $params['tipo_impacto'] = "'" . str_replace("'", "''", $tipoImpacto) . "'";
        $params['valor_impacto'] = floatval($valorImpacto);
        $params['id_usuario'] = intval($dados['id_usuario']);

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Buscar o ID inserido
        $paramsId = [];
        $paramsId['id_funcionario'] = intval($dados['id_funcionario']);
        $paramsId['dt_retrabalho'] = "'" . str_replace("'", "''", $dados['dt_retrabalho']) . "'";
        $paramsId['id_empr'] = intval($dados['id_empr']);

        $resultId = Database::switchParams('focco', $paramsId, 'comissao.retrabalho.buscarMaxId', true);
        $id = $resultId['retorno'][0]['ID'] ?? null;

        // Registrar log de auditoria
        self::registrarLog('TGAZIN_RETRABALHO', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Listar retrabalhos com filtros
     */
    public static function listar($filtros = [])
    {
        $params = [];
        $params['filtro_empr'] = !empty($filtros['id_empr'])
            ? "AND R.ID_EMPR = " . intval($filtros['id_empr'])
            : '--';
        $params['filtro_func'] = !empty($filtros['id_funcionario'])
            ? "AND R.ID_FUNCIONARIO = " . intval($filtros['id_funcionario'])
            : '--';
        $params['filtro_recurso'] = !empty($filtros['id_recurso'])
            ? "AND R.ID_RECURSO = " . intval($filtros['id_recurso'])
            : '--';
        $params['filtro_dt_ini'] = !empty($filtros['dt_inicio'])
            ? "AND R.DT_RETRABALHO >= TO_DATE('" . str_replace("'", "''", $filtros['dt_inicio']) . "', 'YYYY-MM-DD')"
            : '--';
        $params['filtro_dt_fim'] = !empty($filtros['dt_fim'])
            ? "AND R.DT_RETRABALHO <= TO_DATE('" . str_replace("'", "''", $filtros['dt_fim']) . "', 'YYYY-MM-DD')"
            : '--';

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.listar', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Buscar retrabalho por ID
     */
    public static function buscarPorId($id)
    {
        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.buscarPorId', true);
        return $result['retorno'][0] ?? null;
    }

    /**
     * Atualizar retrabalho
     */
    public static function atualizar($id, $dados)
    {
        $dadosAnteriores = self::buscarPorId($id);

        $params = [];
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_recurso'] = intval($dados['id_recurso']);
        $params['id_item'] = intval($dados['id_item']);
        $params['id_mascara'] = intval($dados['id_mascara']);
        $params['id_ordem'] = intval($dados['id_ordem']);
        $params['dt_retrabalho'] = "'" . str_replace("'", "''", $dados['dt_retrabalho']) . "'";
        $params['quantidade'] = floatval($dados['quantidade']);
        $params['motivo'] = "'" . str_replace("'", "''", $dados['motivo']) . "'";
        $params['tipo_impacto'] = "'" . str_replace("'", "''", $dados['tipo_impacto']) . "'";
        $params['valor_impacto'] = floatval($dados['valor_impacto']);
        $params['id_usuario'] = intval($dados['id_usuario']);
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.atualizar', true);

        self::registrarLog('TGAZIN_RETRABALHO', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return !$result['error'];
    }

    /**
     * Excluir retrabalho (exclusão lógica)
     */
    public static function excluir($id, $usuId)
    {
        $dadosAnteriores = self::buscarPorId($id);

        $params = [];
        $params['id_usuario'] = intval($usuId);
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.excluir', true);

        self::registrarLog('TGAZIN_RETRABALHO', $id, 'D', $dadosAnteriores, null, $usuId);

        return !$result['error'];
    }

    /**
     * Buscar retrabalhos de funcionários em um período
     */
    public static function buscarPorFuncionariosPeriodo($funcIds, $dataIni, $dataFim, $emprId = null)
    {
        if (empty($funcIds)) {
            return [];
        }

        $params = [];
        $params['in_func_ids'] = implode(',', array_map('intval', $funcIds));
        $params['data_ini'] = "'" . str_replace("'", "''", $dataIni) . "'";
        $params['data_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND R.ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.retrabalho.buscarPorFuncPeriodo', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Calcular impacto do retrabalho na comissão
     */
    public static function calcularImpacto($valorComissaoOriginal, $pontosRetrabalho, $tipoImpacto, $valorImpacto)
    {
        $desconto = 0;

        switch ($tipoImpacto) {
            case self::TIPO_PERCENTUAL:
                $desconto = $valorComissaoOriginal * ($valorImpacto / 100);
                break;

            case self::TIPO_VALOR_FIXO:
                $desconto = $pontosRetrabalho * $valorImpacto;
                break;

            case self::TIPO_ZERAR:
                $desconto = $valorComissaoOriginal;
                break;
        }

        $valorFinal = max(0, $valorComissaoOriginal - $desconto);

        return [
            'valor_desconto' => round($desconto, 2),
            'valor_final' => round($valorFinal, 2)
        ];
    }

    /**
     * Registrar log de auditoria
     */
    private static function registrarLog($tabela, $idRegistro, $operacao, $dadosAnteriores, $dadosNovos, $usuId)
    {
        try {
            $emprId = $_SESSION['empresa']['id'] ?? 0;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $dadosAntJson = $dadosAnteriores ? json_encode($dadosAnteriores) : '';
            $dadosNovJson = $dadosNovos ? json_encode($dadosNovos) : '';

            $params = [];
            $params['id_empr'] = intval($emprId);
            $params['tabela'] = "'" . str_replace("'", "''", $tabela) . "'";
            $params['id_registro'] = intval($idRegistro);
            $params['operacao'] = "'" . str_replace("'", "''", $operacao) . "'";
            $params['dados_anteriores'] = "'" . str_replace("'", "''", $dadosAntJson) . "'";
            $params['dados_novos'] = "'" . str_replace("'", "''", $dadosNovJson) . "'";
            $params['id_usuario'] = intval($usuId);
            $params['ip_usuario'] = "'" . str_replace("'", "''", $ip) . "'";

            Database::switchParams('focco', $params, 'util.auditoria.inserir', true);
        } catch (\Exception $e) {
            // Log silenciado
        }
    }
}


