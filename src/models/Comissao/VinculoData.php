<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Vínculo de Funcionário por Data Específica
 * Permite que um funcionário atue como APOIO em dias específicos,
 * mesmo sendo NORMAL em seu vínculo principal
 * 
 * Tabela: FOCCO3I.TGAZIN_VINC_FUNC_DATA
 */
class VinculoData
{
    /**
     * Verificar se a tabela de datas existe
     */
    public static function verificarTabelaExiste(): bool
    {
        $sql = "SELECT COUNT(*) AS EXISTE FROM USER_TABLES WHERE TABLE_NAME = 'TGAZIN_VINC_FUNC_DATA'";
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return ($result['retorno'][0]['EXISTE'] ?? 0) > 0;
    }

    /**
     * Listar datas de apoio de um vínculo
     * @param int $idVinculo ID do vínculo principal
     * @return array
     */
    public static function listarPorVinculo(int $idVinculo): array
    {
        $sql = "SELECT 
                    vd.ID_VINCULO_DATA,
                    vd.ID_VINCULO,
                    TO_CHAR(vd.DATA, 'YYYY-MM-DD') AS DATA,
                    TO_CHAR(vd.DATA, 'DD/MM/YYYY') AS DATA_FORMATADA,
                    vd.ID_CENTRO_TRAB_APOIO,
                    ct.COD_CENTRO AS CENTRO_APOIO_COD,
                    ct.DESCRICAO AS CENTRO_APOIO_DESCRICAO,
                    vd.ATIVO
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
                WHERE vd.ID_VINCULO = :id_vinculo
                AND vd.ATIVO = 'S'
                ORDER BY vd.DATA DESC";
        
        $params = ['id_vinculo' => intval($idVinculo)];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Listar datas de apoio de um funcionário em um mês específico
     * @param int $idFuncionario
     * @param int $idEmpr
     * @param string $mesAno Formato: 'YYYY-MM'
     * @return array
     */
    public static function listarPorFuncionarioMes(int $idFuncionario, int $idEmpr, string $mesAno): array
    {
        $sql = "SELECT 
                    vd.ID_VINCULO_DATA,
                    vd.ID_VINCULO,
                    TO_CHAR(vd.DATA, 'YYYY-MM-DD') AS DATA,
                    vd.ID_CENTRO_TRAB_APOIO,
                    ct.COD_CENTRO,
                    ct.DESCRICAO AS CENTRO_DESCRICAO,
                    v.ID_FUNCIONARIO,
                    f.NOME AS FUNCIONARIO_NOME
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
                INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
                LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
                WHERE v.ID_FUNCIONARIO = :id_funcionario
                AND v.ID_EMPR = :id_empr
                AND TO_CHAR(vd.DATA, 'YYYY-MM') = :mes_ano
                AND vd.ATIVO = 'S'
                AND v.ATIVO = 'S'
                ORDER BY vd.DATA";
        
        $params = [
            'id_funcionario' => intval($idFuncionario),
            'id_empr' => intval($idEmpr),
            'mes_ano' => "'" . $mesAno . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verificar se um funcionário atua como apoio em uma data específica
     * Retorna o centro de trabalho onde ele atua como apoio, ou null se não atua
     * 
     * @param int $idFuncionario
     * @param int $idEmpr
     * @param string $data Formato: 'YYYY-MM-DD'
     * @return array|null
     */
    public static function verificarApoioNaData(int $idFuncionario, int $idEmpr, string $data): ?array
    {
        $sql = "SELECT 
                    vd.ID_VINCULO_DATA,
                    vd.ID_VINCULO,
                    vd.ID_CENTRO_TRAB_APOIO,
                    ct.COD_CENTRO,
                    ct.DESCRICAO AS CENTRO_DESCRICAO,
                    v.ID_FUNCIONARIO,
                    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
                LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
                WHERE v.ID_FUNCIONARIO = :id_funcionario
                AND v.ID_EMPR = :id_empr
                AND vd.DATA = TO_DATE(:data, 'YYYY-MM-DD')
                AND vd.ATIVO = 'S'
                AND v.ATIVO = 'S'
                FETCH FIRST 1 ROW ONLY";
        
        $params = [
            'id_funcionario' => intval($idFuncionario),
            'id_empr' => intval($idEmpr),
            'data' => "'" . $data . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            return $result['retorno'][0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Inserir nova data de apoio
     * @param int $idVinculo ID do vínculo principal
     * @param string $data Formato: 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio Centro onde vai atuar como apoio (se null, usa o mesmo do vínculo)
     * @return bool
     */
    public static function inserir(int $idVinculo, string $data, ?int $idCentroTrabApoio = null): bool
    {
        // Se não especificou centro, busca do vínculo principal
        if ($idCentroTrabApoio === null) {
            $sqlVinculo = "SELECT ID_CENTRO_TRAB FROM FOCCO3I.TGAZIN_VINC_FUNC WHERE ID_VINCULO = :id_vinculo";
            $resultVinculo = Database::switchParams('focco', ['id_vinculo' => intval($idVinculo)], null, true, false, null, $sqlVinculo);
            $idCentroTrabApoio = $resultVinculo['retorno'][0]['ID_CENTRO_TRAB'] ?? null;
        }
        
        // Usar sintaxe direta para evitar problemas com bind variables de data
        $centroValue = $idCentroTrabApoio !== null ? intval($idCentroTrabApoio) : 'NULL';
        
        $sql = "INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC_DATA (
                    ID_VINCULO,
                    DATA,
                    ID_CENTRO_TRAB_APOIO,
                    ATIVO,
                    DT_CADASTRO
                ) VALUES (
                    " . intval($idVinculo) . ",
                    TO_DATE('" . $data . "', 'YYYY-MM-DD'),
                    " . $centroValue . ",
                    'S',
                    SYSDATE
                )";
        
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return !$result['error'];
    }

    /**
     * Inserir múltiplas datas de apoio de uma vez
     * @param int $idVinculo
     * @param array $datas Array de datas no formato 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio
     * @return int Quantidade de datas inseridas
     */
    public static function inserirMultiplas(int $idVinculo, array $datas, ?int $idCentroTrabApoio = null): int
    {
        $count = 0;
        foreach ($datas as $data) {
            // Verificar se já existe
            if (!self::existeData($idVinculo, $data)) {
                if (self::inserir($idVinculo, $data, $idCentroTrabApoio)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Verificar se já existe registro para a data
     */
    public static function existeData(int $idVinculo, string $data): bool
    {
        $sql = "SELECT COUNT(*) AS EXISTE 
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA 
                WHERE ID_VINCULO = " . intval($idVinculo) . " 
                AND DATA = TO_DATE('" . $data . "', 'YYYY-MM-DD')
                AND ATIVO = 'S'";
        
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return ($result['retorno'][0]['EXISTE'] ?? 0) > 0;
    }

    /**
     * Excluir data de apoio (inativa o registro)
     */
    public static function excluir(int $idVinculoData): bool
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
                SET ATIVO = 'N', DT_ALTERACAO = SYSDATE 
                WHERE ID_VINCULO_DATA = " . intval($idVinculoData);
        
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return !$result['error'];
    }

    /**
     * Excluir data de apoio por vínculo e data
     */
    public static function excluirPorData(int $idVinculo, string $data): bool
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
                SET ATIVO = 'N', DT_ALTERACAO = SYSDATE 
                WHERE ID_VINCULO = " . intval($idVinculo) . " 
                AND DATA = TO_DATE('" . $data . "', 'YYYY-MM-DD')";
        
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return !$result['error'];
    }

    /**
     * Excluir todas as datas de um vínculo
     */
    public static function excluirTodasPorVinculo(int $idVinculo): bool
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
                SET ATIVO = 'N', DT_ALTERACAO = SYSDATE 
                WHERE ID_VINCULO = " . intval($idVinculo);
        
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        return !$result['error'];
    }

    /**
     * Atualizar datas de apoio (remove antigas e adiciona novas)
     * @param int $idVinculo
     * @param array $datas Array de datas no formato 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio
     * @return bool
     */
    public static function atualizarDatas(int $idVinculo, array $datas, ?int $idCentroTrabApoio = null): bool
    {
        // Primeiro, inativa todas as datas existentes
        self::excluirTodasPorVinculo($idVinculo);
        
        // Depois, insere as novas
        foreach ($datas as $data) {
            self::inserir($idVinculo, $data, $idCentroTrabApoio);
        }
        
        return true;
    }

    /**
     * Buscar datas de apoio em batch para múltiplos funcionários em um período
     * @param array $funcIds IDs dos funcionários
     * @param int $emprId ID da empresa
     * @param string $periodoIni Data início (YYYY-MM-DD)
     * @param string $periodoFim Data fim (YYYY-MM-DD)
     * @return array Indexado por ID_FUNCIONARIO -> [DATA => centro_apoio_id]
     */
    public static function buscarDatasApoioBatch(array $funcIds, int $emprId, string $periodoIni, string $periodoFim): array
    {
        if (empty($funcIds)) {
            return [];
        }

        $inIds = implode(',', array_map('intval', $funcIds));
        
        $sql = "SELECT 
                    v.ID_FUNCIONARIO,
                    TO_CHAR(vd.DATA, 'YYYY-MM-DD') AS DATA,
                    vd.ID_CENTRO_TRAB_APOIO,
                    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
                WHERE v.ID_FUNCIONARIO IN ({$inIds})
                AND v.ID_EMPR = :id_empr
                AND vd.DATA BETWEEN TO_DATE(:periodo_ini, 'YYYY-MM-DD') AND TO_DATE(:periodo_fim, 'YYYY-MM-DD')
                AND vd.ATIVO = 'S'
                AND v.ATIVO = 'S'
                AND v.TIPO_VINCULO = 'N'
                ORDER BY v.ID_FUNCIONARIO, vd.DATA";
        
        $params = [
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            $rows = $result['retorno'] ?? [];
            
            // Indexar por funcionário e data
            $datasApoio = [];
            foreach ($rows as $row) {
                $funcId = (int)$row['ID_FUNCIONARIO'];
                $data = $row['DATA'];
                $centroApoio = $row['ID_CENTRO_TRAB_APOIO'] ?? $row['CENTRO_PRINCIPAL'];
                
                if (!isset($datasApoio[$funcId])) {
                    $datasApoio[$funcId] = [];
                }
                $datasApoio[$funcId][$data] = $centroApoio;
            }
            
            return $datasApoio;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Buscar todos os funcionários com datas de apoio configuradas em um período
     * Retorna dados básicos dos funcionários para incluir no relatório mesmo sem apontamentos
     * @param int $emprId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int|null $centroTrabId Filtrar por centro de trabalho (principal ou apoio)
     * @return array Lista de funcionários com suas datas de apoio
     */
    public static function buscarFuncionariosComApoioPeriodo(int $emprId, string $periodoIni, string $periodoFim, ?int $centroTrabId = null): array
    {
        $filtroCtId = $centroTrabId 
            ? "AND (v.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR vd.ID_CENTRO_TRAB_APOIO = " . intval($centroTrabId) . ")"
            : '';
        
        $sql = "SELECT DISTINCT
                    v.ID_FUNCIONARIO AS FUNC_ID,
                    f.COD_FUNC,
                    f.NOME AS NOME_FUNC,
                    v.ID_CENTRO_TRAB AS CENTRO_TRAB_ID,
                    ct.COD_CENTRO,
                    ct.DESCRICAO AS DESC_CENTRO,
                    v.TIPO_VINCULO
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
                INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
                LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = v.ID_CENTRO_TRAB
                WHERE v.ID_EMPR = :id_empr
                AND vd.DATA BETWEEN TO_DATE(:periodo_ini, 'YYYY-MM-DD') AND TO_DATE(:periodo_fim, 'YYYY-MM-DD')
                AND vd.ATIVO = 'S'
                AND v.ATIVO = 'S'
                {$filtroCtId}
                ORDER BY f.NOME";
        
        $params = [
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Buscar datas de apoio para todos os funcionários de um centro/empresa em um período
     * Usado para incluir funcionários que trabalham como apoio mas podem não ter apontamentos individuais
     * @param int $emprId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int|null $centroTrabId
     * @return array Indexado por ID_FUNCIONARIO -> [DATA => centro_apoio_id]
     */
    public static function buscarTodasDatasApoioPeriodo(int $emprId, string $periodoIni, string $periodoFim, ?int $centroTrabId = null): array
    {
        $filtroCtId = $centroTrabId 
            ? "AND (v.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR vd.ID_CENTRO_TRAB_APOIO = " . intval($centroTrabId) . ")"
            : '';
        
        $sql = "SELECT 
                    v.ID_FUNCIONARIO,
                    TO_CHAR(vd.DATA, 'YYYY-MM-DD') AS DATA,
                    vd.ID_CENTRO_TRAB_APOIO,
                    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL
                FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
                INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
                WHERE v.ID_EMPR = :id_empr
                AND vd.DATA BETWEEN TO_DATE(:periodo_ini, 'YYYY-MM-DD') AND TO_DATE(:periodo_fim, 'YYYY-MM-DD')
                AND vd.ATIVO = 'S'
                AND v.ATIVO = 'S'
                {$filtroCtId}
                ORDER BY v.ID_FUNCIONARIO, vd.DATA";
        
        $params = [
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
            $rows = $result['retorno'] ?? [];
            
            $datasApoio = [];
            foreach ($rows as $row) {
                $funcId = (int)$row['ID_FUNCIONARIO'];
                $data = $row['DATA'];
                $centroApoio = $row['ID_CENTRO_TRAB_APOIO'] ?? $row['CENTRO_PRINCIPAL'];
                
                if (!isset($datasApoio[$funcId])) {
                    $datasApoio[$funcId] = [];
                }
                $datasApoio[$funcId][$data] = (int)$centroApoio;
            }
            
            return $datasApoio;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
