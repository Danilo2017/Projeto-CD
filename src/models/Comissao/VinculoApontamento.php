<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Vínculo de Apontamentos sem Recurso
 * Tabela: FOCCO3I.TGAZIN_VINC_APONTAMENTO
 */
class VinculoApontamento
{
    /**
     * Listar apontamentos sem recurso vinculado
     */
    public static function listarApontamentosSemRecurso($filtros = [])
    {
        $params = [];
        $params['filtro_empr'] = !empty($filtros['id_empr'])
            ? "AND O.EMPR_ID = " . intval($filtros['id_empr'])
            : '--';
        $params['filtro_dt_ini'] = !empty($filtros['dt_inicio'])
            ? "AND TM.DT_APONT >= TO_DATE('" . str_replace("'", "''", $filtros['dt_inicio']) . "', 'YYYY-MM-DD')"
            : '--';
        $params['filtro_dt_fim'] = !empty($filtros['dt_fim'])
            ? "AND TM.DT_APONT < TO_DATE('" . str_replace("'", "''", $filtros['dt_fim']) . "', 'YYYY-MM-DD') + 1"
            : '--';
        $params['filtro_centro'] = !empty($filtros['id_centro_trab'])
            ? "AND TR.CENTR_TRAB_ID = " . intval($filtros['id_centro_trab'])
            : '--';

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.listarSemRecurso', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Vincular recurso (máquina) ao apontamento
     */
    public static function vincularRecurso($apontamentoId, $recursoId)
    {
        // Verificar se já existe
        $paramsCheck = [];
        $paramsCheck['apt_id'] = intval($apontamentoId);
        $resultCheck = Database::switchParams('focco', $paramsCheck, 'comissao.vincApt.verificarRecursoExistente', true);
        if ((int)($resultCheck['retorno'][0]['TOTAL'] ?? 0) > 0) {
            throw new \Exception('Este apontamento já possui um recurso vinculado');
        }

        // Buscar próximo ID (MAX + 1)
        $resultMax = Database::switchParams('focco', [], 'comissao.vincApt.maxIdRecurso', true);
        $nextId = (int)($resultMax['retorno'][0]['NEXT_ID'] ?? 1);

        $params = [];
        $params['id'] = $nextId;
        $params['apt_id'] = intval($apontamentoId);
        $params['rec_id'] = intval($recursoId);

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.vincularRecurso', true);
        return !$result['error'];
    }

    /**
     * Vincular apontamento a funcionário
     */
    public static function vincular($dados)
    {
        if (self::verificarVinculoExistente($dados['id_apontamento'])) {
            throw new \Exception('Este apontamento já possui um vínculo');
        }

        $params = [];
        $params['id_empr'] = intval($dados['id_empr']);
        $params['id_apontamento'] = intval($dados['id_apontamento']);
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_recurso'] = intval($dados['id_recurso']);
        $params['id_usuario'] = intval($dados['id_usuario']);
        $params['observacao'] = isset($dados['observacao']) && $dados['observacao'] !== null
            ? "'" . str_replace("'", "''", $dados['observacao']) . "'"
            : 'NULL';

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Buscar o ID inserido
        $paramsId = [];
        $paramsId['id_apontamento'] = intval($dados['id_apontamento']);
        $resultId = Database::switchParams('focco', $paramsId, 'comissao.vincApt.buscarIdPorApt', true);
        $id = $resultId['retorno'][0]['ID_VINC_APT'] ?? null;

        self::registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Verificar se já existe vínculo para um apontamento
     */
    public static function verificarVinculoExistente($idApontamento)
    {
        $params = [];
        $params['id_apontamento'] = intval($idApontamento);
        $result = Database::switchParams('focco', $params, 'comissao.vincApt.verificarExistente', true);
        return (int)($result['retorno'][0]['TOTAL'] ?? 0) > 0;
    }

    /**
     * Buscar vínculo por ID
     */
    public static function buscarPorId($id)
    {
        $params = [];
        $params['id'] = intval($id);
        $result = Database::switchParams('focco', $params, 'comissao.vincApt.buscarPorId', true);
        return $result['retorno'][0] ?? null;
    }

    /**
     * Buscar vínculo por ID do apontamento
     */
    public static function buscarPorApontamento($idApontamento)
    {
        $params = [];
        $params['id_apontamento'] = intval($idApontamento);
        $result = Database::switchParams('focco', $params, 'comissao.vincApt.buscarPorApontamento', true);
        return $result['retorno'][0] ?? null;
    }

    /**
     * Listar vínculos existentes por período
     */
    public static function listarVinculos($filtros = [])
    {
        $params = [];
        $params['filtro_empr'] = !empty($filtros['id_empr'])
            ? "AND VA.ID_EMPR = " . intval($filtros['id_empr'])
            : '--';
        $params['filtro_dt_ini'] = !empty($filtros['dt_inicio'])
            ? "AND VA.DT_VINCULACAO >= TO_DATE('" . str_replace("'", "''", $filtros['dt_inicio']) . "', 'YYYY-MM-DD')"
            : '--';
        $params['filtro_dt_fim'] = !empty($filtros['dt_fim'])
            ? "AND VA.DT_VINCULACAO < TO_DATE('" . str_replace("'", "''", $filtros['dt_fim']) . "', 'YYYY-MM-DD') + 1"
            : '--';

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.listarVinculos', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Atualizar vínculo
     */
    public static function atualizar($id, $dados)
    {
        $dadosAnteriores = self::buscarPorId($id);

        if ($dadosAnteriores && $dadosAnteriores['FECHADO'] === 'S') {
            throw new \Exception('Este vínculo está fechado e não pode ser alterado');
        }

        $params = [];
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_recurso'] = intval($dados['id_recurso']);
        $params['observacao'] = isset($dados['observacao']) && $dados['observacao'] !== null
            ? "'" . str_replace("'", "''", $dados['observacao']) . "'"
            : 'NULL';
        $params['id_usuario'] = intval($dados['id_usuario']);
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.atualizar', true);

        self::registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return !$result['error'];
    }

    /**
     * Excluir vínculo
     */
    public static function excluir($id, $usuId)
    {
        $dadosAnteriores = self::buscarPorId($id);

        if ($dadosAnteriores && $dadosAnteriores['FECHADO'] === 'S') {
            throw new \Exception('Este vínculo está fechado e não pode ser excluído');
        }

        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.excluir', true);

        self::registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'D', $dadosAnteriores, null, $usuId);

        return !$result['error'];
    }

    /**
     * Fechar vínculo (não permite mais alterações)
     */
    public static function fechar($id, $usuId)
    {
        $params = [];
        $params['id_usuario'] = intval($usuId);
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.vincApt.fechar', true);
        return !$result['error'];
    }

    /**
     * Vincular múltiplos apontamentos de uma vez
     */
    public static function vincularEmLote($apontamentos, $funcId, $recursoId, $emprId, $usuId)
    {
        $sucesso = [];
        $erros = [];

        foreach ($apontamentos as $aptId) {
            try {
                $id = self::vincular([
                    'id_empr' => $emprId,
                    'id_apontamento' => $aptId,
                    'id_funcionario' => $funcId,
                    'id_recurso' => $recursoId,
                    'id_usuario' => $usuId
                ]);
                $sucesso[] = ['id_apontamento' => $aptId, 'id_vinculo' => $id];
            } catch (\Exception $e) {
                $erros[] = ['id_apontamento' => $aptId, 'erro' => $e->getMessage()];
            }
        }

        return [
            'sucesso' => $sucesso,
            'erros' => $erros,
            'total_sucesso' => count($sucesso),
            'total_erros' => count($erros)
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


