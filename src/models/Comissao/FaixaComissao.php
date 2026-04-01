<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Faixas de Comissão
 * Tabela customizada: FOCCO3I.TGAZIN_FAIXA_COMISSAO
 */
class FaixaComissao
{
    const TIPO_PERCENTUAL = 'P';
    const TIPO_QUANTIDADE = 'Q';

    /**
     * Listar todas as faixas ativas
     */
    public static function listarAtivas($emprId = null, $centroTrabId = null)
    {
        $params = [];
        $params['filtro_empr'] = $emprId ? "AND E.ID = " . intval($emprId) : '--';
        $params['filtro_centro'] = $centroTrabId ? "AND (FC.CENTRO_TRAB_ID = " . intval($centroTrabId) . " OR FC.CENTRO_TRAB_ID IS NULL)" : '--';

        $result = Database::switchParams('focco', $params, 'comissao.faixa.listarAtivas', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    /**
     * Buscar faixa por ID
     */
    public static function buscarPorId($id)
    {
        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.faixa.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Buscar faixa aplicável para determinada pontuação
     * @param float $pontuacao Pontuação a ser verificada
     * @param int|null $centroTrabId ID do centro de trabalho (opcional)
     * @param string|null $dataReferencia Data de referência (opcional, default = hoje)
     * @param string|null $tipoFuncionario Tipo do funcionário: N=Normal, A=Apoio (opcional, default = busca qualquer)
     */
    public static function buscarFaixaAplicavel($pontuacao, $centroTrabId = null, $dataReferencia = null, $tipoFuncionario = null)
    {
        $dataRef = $dataReferencia ?? date('Y-m-d');

        $params = [];
        $params['pontuacao'] = floatval($pontuacao);
        $params['pontuacao2'] = floatval($pontuacao);
        $params['data_ref'] = "'" . str_replace("'", "''", $dataRef) . "'";
        $params['data_ref2'] = "'" . str_replace("'", "''", $dataRef) . "'";
        $params['filtro_centro'] = $centroTrabId
            ? "AND (FC.CENTRO_TRAB_ID = " . intval($centroTrabId) . " OR FC.CENTRO_TRAB_ID IS NULL)"
            : "AND FC.CENTRO_TRAB_ID IS NULL";
        
        // Filtro por tipo de funcionário: N=Normal busca faixas N ou T, A=Apoio busca faixas A ou T
        if ($tipoFuncionario === 'N') {
            $params['filtro_tipo_func'] = "AND FC.TIPO_FUNCIONARIO IN ('N', 'T')";
        } elseif ($tipoFuncionario === 'A') {
            $params['filtro_tipo_func'] = "AND FC.TIPO_FUNCIONARIO IN ('A', 'T')";
        } else {
            $params['filtro_tipo_func'] = '--'; // Sem filtro, busca qualquer tipo
        }

        $result = Database::switchParams('focco', $params, 'comissao.faixa.buscarAplicavel', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Verificar se existe conflito de faixa
     */
    public static function verificarConflito($dados, $idExcluir = null)
    {
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $pontoInicial = $dados['ponto_inicial'];
        $pontoFinal = $dados['ponto_final'] ?? null;
        $dtVigenciaIni = $dados['dt_vigencia_ini'];
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $tipoFuncionario = $dados['tipo_funcionario'] ?? 'T';

        $params = [];
        $params['filtro_centro'] = $centroTrabId
            ? "AND FC.CENTRO_TRAB_ID = " . intval($centroTrabId)
            : "AND FC.CENTRO_TRAB_ID IS NULL";
        $params['tipo_funcionario'] = "'" . str_replace("'", "''", $tipoFuncionario) . "'";
        $params['tipo_funcionario2'] = "'" . str_replace("'", "''", $tipoFuncionario) . "'";
        $params['ponto_inicial'] = floatval($pontoInicial);
        $params['ponto_final'] = $pontoFinal !== null ? floatval($pontoFinal) : 'NULL';
        $params['ponto_final2'] = $pontoFinal !== null ? floatval($pontoFinal) : 'NULL';
        $params['dt_vig_ini'] = "'" . str_replace("'", "''", $dtVigenciaIni) . "'";
        $params['dt_vig_fim'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['dt_vig_fim2'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['filtro_excluir'] = $idExcluir ? "AND FC.ID_FAIXA != " . intval($idExcluir) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.faixa.verificarConflito', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Obter próximo ID da sequência
     */
    private static function proximoIdFaixa()
    {
        $result = Database::switchParams('focco', [], 'comissao.faixa.nextval', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0]['ID'] ?? null;
    }

    /**
     * Inserir nova faixa
     */
    public static function inserir($dados)
    {
        $novoId = self::proximoIdFaixa();

        $pontoFinal = $dados['ponto_final'] ?? null;
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $idUsuario = $dados['id_usuario'] ?? null;

        $tipoFuncionario = $dados['tipo_funcionario'] ?? 'T';

        $params = [];
        $params['id_faixa'] = intval($novoId);
        $params['descricao'] = "'" . str_replace("'", "''", $dados['descricao']) . "'";
        $params['tipo'] = "'" . str_replace("'", "''", $dados['tipo']) . "'";
        $params['ponto_inicial'] = floatval($dados['ponto_inicial']);
        $params['ponto_final'] = $pontoFinal !== null ? floatval($pontoFinal) : 'NULL';
        $params['valor_comissao'] = floatval($dados['valor_comissao']);
        $params['centro_trab_id'] = $centroTrabId !== null ? intval($centroTrabId) : 'NULL';
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $params['dt_vigencia_fim'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $params['tipo_funcionario'] = "'" . str_replace("'", "''", $tipoFuncionario) . "'";

        $result = Database::switchParams('focco', $params, 'comissao.faixa.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $novoId;
    }

    /**
     * Atualizar faixa
     */
    public static function atualizar($id, $dados)
    {
        $pontoFinal = $dados['ponto_final'] ?? null;
        $centroTrabId = $dados['centro_trab_id'] ?? null;
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        $idUsuario = $dados['id_usuario'] ?? null;
        $tipoFuncionario = $dados['tipo_funcionario'] ?? 'T';

        $params = [];
        $params['descricao'] = "'" . str_replace("'", "''", $dados['descricao']) . "'";
        $params['tipo'] = "'" . str_replace("'", "''", $dados['tipo']) . "'";
        $params['ponto_inicial'] = floatval($dados['ponto_inicial']);
        $params['ponto_final'] = $pontoFinal !== null ? floatval($pontoFinal) : 'NULL';
        $params['valor_comissao'] = floatval($dados['valor_comissao']);
        $params['centro_trab_id'] = $centroTrabId !== null ? intval($centroTrabId) : 'NULL';
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $params['dt_vigencia_fim'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $params['tipo_funcionario'] = "'" . str_replace("'", "''", $tipoFuncionario) . "'";
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.faixa.atualizar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Ativar/Desativar faixa
     */
    public static function alterarStatus($id, $ativo, $idUsuario = null)
    {
        $params = [];
        $params['ativo'] = "'" . str_replace("'", "''", $ativo) . "'";
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.faixa.alterarStatus', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Inativar faixa
     */
    public static function inativar($id, $idUsuario = null)
    {
        return self::alterarStatus($id, 'N', $idUsuario);
    }

    /**
     * Listar todas as faixas (incluindo inativas) para histórico
     */
    public static function listarTodas($emprId = null)
    {
        $params = [];
        $params['filtro_empr'] = $emprId ? "AND E.ID = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.faixa.listarTodas', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}


