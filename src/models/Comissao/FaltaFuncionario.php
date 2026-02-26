<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Gestão de Faltas de Funcionários
 * 
 * Tabela: FOCCO3I.TGAZIN_FALTA_FUNC
 * 
 * Regras:
 * - Se o funcionário tiver falta registrada em determinado dia, comissão = 0
 * - Ignora todos os apontamentos do dia
 * - Regra é diária (não mensal)
 * - Funciona para períodos atuais e retroativos
 */
class FaltaFuncionario
{
    const TIPO_INTEGRAL = 'I';
    const TIPO_PARCIAL = 'P';

    /**
     * Registrar falta de funcionário
     * @param array $dados
     * @return int ID da falta inserida
     */
    public function registrar($dados)
    {
        // Validar se já existe falta para o mesmo dia
        if ($this->verificarFaltaExistente($dados['id_funcionario'], $dados['dt_falta'], $dados['id_empr'])) {
            throw new Exception('Já existe uma falta registrada para este funcionário nesta data');
        }

        $sql = "INSERT INTO FOCCO3I.TGAZIN_FALTA_FUNC (
                    ID_EMPR,
                    ID_FUNCIONARIO,
                    DT_FALTA,
                    MOTIVO,
                    TIPO_FALTA,
                    ATIVO,
                    DT_CADASTRO,
                    ID_USUARIO_CAD
                ) VALUES (
                    :id_empr,
                    :id_funcionario,
                    TO_DATE(:dt_falta, 'YYYY-MM-DD'),
                    :motivo,
                    :tipo_falta,
                    'S',
                    SYSDATE,
                    :id_usuario
                )";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmt->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindParam(':dt_falta', $dados['dt_falta'], PDO::PARAM_STR);
        $stmt->bindParam(':motivo', $dados['motivo'], PDO::PARAM_STR);
        $tipoFalta = $dados['tipo_falta'] ?? self::TIPO_INTEGRAL;
        $stmt->bindParam(':tipo_falta', $tipoFalta, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);

        $stmt->execute();

        // Buscar o ID inserido (tabela usa IDENTITY, não sequence)
        $sqlId = "SELECT MAX(ID_FALTA) FROM FOCCO3I.TGAZIN_FALTA_FUNC 
                  WHERE ID_FUNCIONARIO = :id_funcionario 
                  AND DT_FALTA = TO_DATE(:dt_falta, 'YYYY-MM-DD')
                  AND ID_EMPR = :id_empr";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmtId->bindParam(':dt_falta', $dados['dt_falta'], PDO::PARAM_STR);
        $stmtId->bindParam(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmtId->execute();
        $id = $stmtId->fetchColumn();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_FALTA_FUNC', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Verificar se já existe falta registrada para o funcionário na data
     * @param int $funcId
     * @param string $data (YYYY-MM-DD)
     * @param int $emprId
     * @return bool
     */
    public function verificarFaltaExistente($funcId, $data, $emprId)
    {
        $sql = "SELECT COUNT(*) FROM FOCCO3I.TGAZIN_FALTA_FUNC
                WHERE ID_FUNCIONARIO = :func_id
                AND DT_FALTA = TO_DATE(:dt_falta, 'YYYY-MM-DD')
                AND ID_EMPR = :empr_id
                AND ATIVO = 'S'";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':dt_falta', $data, PDO::PARAM_STR);
        $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar faltas de um funcionário em um período
     * Retorna array com as datas que possuem falta
     * @param int $funcId
     * @param string $dataIni (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array
     */
    public function verificarFaltasPeriodo($funcId, $dataIni, $dataFim, $emprId = null)
    {
        $sql = "SELECT 
                    TO_CHAR(DT_FALTA, 'YYYY-MM-DD') AS DT_FALTA,
                    TIPO_FALTA,
                    MOTIVO
                FROM FOCCO3I.TGAZIN_FALTA_FUNC
                WHERE ID_FUNCIONARIO = :func_id
                AND DT_FALTA >= TO_DATE(:dt_ini, 'YYYY-MM-DD')
                AND DT_FALTA <= TO_DATE(:dt_fim, 'YYYY-MM-DD')
                AND ATIVO = 'S'";

        if ($emprId) {
            $sql .= " AND ID_EMPR = :empr_id";
        }

        $sql .= " ORDER BY DT_FALTA";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':func_id', $funcId, PDO::PARAM_INT);
        $stmt->bindParam(':dt_ini', $dataIni, PDO::PARAM_STR);
        $stmt->bindParam(':dt_fim', $dataFim, PDO::PARAM_STR);

        if ($emprId) {
            $stmt->bindParam(':empr_id', $emprId, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar faltas com filtros
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $sql = "SELECT 
                    FF.ID_FALTA,
                    FF.ID_EMPR,
                    E.RAZAO_SOCIAL AS EMPRESA,
                    FF.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    TO_CHAR(FF.DT_FALTA, 'DD/MM/YYYY') AS DT_FALTA_FMT,
                    TO_CHAR(FF.DT_FALTA, 'YYYY-MM-DD') AS DT_FALTA,
                    FF.MOTIVO,
                    FF.TIPO_FALTA,
                    CASE FF.TIPO_FALTA 
                        WHEN 'I' THEN 'Integral' 
                        WHEN 'P' THEN 'Parcial' 
                    END AS DESC_TIPO_FALTA,
                    FF.ATIVO,
                    TO_CHAR(FF.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') AS DT_CADASTRO,
                    FF.ID_USUARIO_CAD
                FROM FOCCO3I.TGAZIN_FALTA_FUNC FF
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = FF.ID_FUNCIONARIO
                LEFT JOIN FOCCO3I.TEMPRESAS E ON E.ID = FF.ID_EMPR
                WHERE FF.ATIVO = 'S'";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND FF.ID_EMPR = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['id_funcionario'])) {
            $sql .= " AND FF.ID_FUNCIONARIO = :id_funcionario";
            $params[':id_funcionario'] = $filtros['id_funcionario'];
        }

        if (!empty($filtros['dt_inicio'])) {
            $sql .= " AND FF.DT_FALTA >= TO_DATE(:dt_inicio, 'YYYY-MM-DD')";
            $params[':dt_inicio'] = $filtros['dt_inicio'];
        }

        if (!empty($filtros['dt_fim'])) {
            $sql .= " AND FF.DT_FALTA <= TO_DATE(:dt_fim, 'YYYY-MM-DD')";
            $params[':dt_fim'] = $filtros['dt_fim'];
        }

        $sql .= " ORDER BY FF.DT_FALTA DESC, F.NOME";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar falta por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    FF.ID_FALTA,
                    FF.ID_EMPR,
                    FF.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    TO_CHAR(FF.DT_FALTA, 'YYYY-MM-DD') AS DT_FALTA,
                    FF.MOTIVO,
                    FF.TIPO_FALTA,
                    FF.ATIVO
                FROM FOCCO3I.TGAZIN_FALTA_FUNC FF
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = FF.ID_FUNCIONARIO
                WHERE FF.ID_FALTA = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar falta
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = $this->buscarPorId($id);

        $sql = "UPDATE FOCCO3I.TGAZIN_FALTA_FUNC SET
                    DT_FALTA = TO_DATE(:dt_falta, 'YYYY-MM-DD'),
                    MOTIVO = :motivo,
                    TIPO_FALTA = :tipo_falta,
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_FALTA = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':dt_falta', $dados['dt_falta'], PDO::PARAM_STR);
        $stmt->bindParam(':motivo', $dados['motivo'], PDO::PARAM_STR);
        $tipoFalta = $dados['tipo_falta'] ?? self::TIPO_INTEGRAL;
        $stmt->bindParam(':tipo_falta', $tipoFalta, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_FALTA_FUNC', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return $result;
    }

    /**
     * Excluir falta (exclusão lógica)
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public function excluir($id, $usuId)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = $this->buscarPorId($id);
        if (!$dadosAnteriores) {
            throw new \Exception('Falta não encontrada para exclusão');
        }

        $sql = "DELETE FROM FOCCO3I.TGAZIN_FALTA_FUNC WHERE ID_FALTA = :id";
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();
        if ($stmt->rowCount() === 0) {
            throw new \Exception('Nenhuma linha afetada ao excluir. ID pode estar incorreto.');
        }

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_FALTA_FUNC', $id, 'D', $dadosAnteriores, null, $usuId);

        return $result;
    }

    /**
     * Obter datas com falta para um array de funcionários em um período
     * Útil para processamento em lote
     * @param array $funcIds
     * @param string $dataIni (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int $emprId
     * @return array [func_id => [data1, data2, ...]]
     */
    public function obterFaltasPorFuncionarios($funcIds, $dataIni, $dataFim, $emprId = null)
    {
        if (empty($funcIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($funcIds), '?'));

        $sql = "SELECT 
                    ID_FUNCIONARIO,
                    TO_CHAR(DT_FALTA, 'YYYY-MM-DD') AS DT_FALTA,
                    TIPO_FALTA
                FROM FOCCO3I.TGAZIN_FALTA_FUNC
                WHERE ID_FUNCIONARIO IN ($placeholders)
                AND DT_FALTA >= TO_DATE(?, 'YYYY-MM-DD')
                AND DT_FALTA <= TO_DATE(?, 'YYYY-MM-DD')
                AND ATIVO = 'S'
                AND TIPO_FALTA = 'I'";

        if ($emprId) {
            $sql .= " AND ID_EMPR = ?";
        }

        $sql .= " ORDER BY ID_FUNCIONARIO, DT_FALTA";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $i = 1;
        foreach ($funcIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        $stmt->bindValue($i++, $dataIni, PDO::PARAM_STR);
        $stmt->bindValue($i++, $dataFim, PDO::PARAM_STR);

        if ($emprId) {
            $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organizar por funcionário
        $resultado = [];
        foreach ($rows as $row) {
            $funcId = $row['ID_FUNCIONARIO'];
            if (!isset($resultado[$funcId])) {
                $resultado[$funcId] = [];
            }
            $resultado[$funcId][] = $row['DT_FALTA'];
        }

        return $resultado;
    }

    /**
     * Registrar log de auditoria
     * @param string $tabela
     * @param int $idRegistro
     * @param string $operacao
     * @param array $dadosAnteriores
     * @param array $dadosNovos
     * @param int $usuId
     */
    private function registrarLog($tabela, $idRegistro, $operacao, $dadosAnteriores, $dadosNovos, $usuId)
    {
        try {
            $sql = "INSERT INTO FOCCO3I.TGAZIN_LOG_AUDITORIA (
                        ID_EMPR,
                        TABELA,
                        ID_REGISTRO,
                        OPERACAO,
                        DADOS_ANTERIORES,
                        DADOS_NOVOS,
                        DT_OPERACAO,
                        ID_USUARIO,
                        IP_USUARIO
                    ) VALUES (
                        :id_empr,
                        :tabela,
                        :id_registro,
                        :operacao,
                        :dados_anteriores,
                        :dados_novos,
                        SYSDATE,
                        :id_usuario,
                        :ip_usuario
                    )";

            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);

            $emprId = $_SESSION['empresa']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $dadosAntJson = $dadosAnteriores ? json_encode($dadosAnteriores) : null;
            $dadosNovJson = $dadosNovos ? json_encode($dadosNovos) : null;

            $stmt->bindParam(':id_empr', $emprId, PDO::PARAM_INT);
            $stmt->bindParam(':tabela', $tabela, PDO::PARAM_STR);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindParam(':operacao', $operacao, PDO::PARAM_STR);
            $stmt->bindParam(':dados_anteriores', $dadosAntJson, PDO::PARAM_STR);
            $stmt->bindParam(':dados_novos', $dadosNovJson, PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $usuId, PDO::PARAM_INT);
            $stmt->bindParam(':ip_usuario', $ip, PDO::PARAM_STR);

            $stmt->execute();
        } catch (\Exception $e) {
            // Log de erro silenciado - não interrompe a operação principal
        }
    }

    /**
     * MÉTODO OTIMIZADO - Verificar faltas de MÚLTIPLOS funcionários em um período
     * Evita N queries separadas fazendo uma única consulta batch
     * 
     * @param array $funcIds Array com IDs dos funcionários
     * @param string $dataIni (YYYY-MM-DD)
     * @param string $dataFim (YYYY-MM-DD)
     * @param int|null $emprId
     * @return array Indexado por ID do funcionário, cada um contendo array de faltas
     */
    public function verificarFaltasPeriodoBatch(array $funcIds, string $dataIni, string $dataFim, ?int $emprId = null): array
    {
        if (empty($funcIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($funcIds), '?'));

        $sql = "SELECT 
                    ID_FUNCIONARIO,
                    TO_CHAR(DT_FALTA, 'YYYY-MM-DD') AS DT_FALTA,
                    TIPO_FALTA,
                    MOTIVO
                FROM FOCCO3I.TGAZIN_FALTA_FUNC
                WHERE ID_FUNCIONARIO IN ($placeholders)
                AND DT_FALTA >= TO_DATE(?, 'YYYY-MM-DD')
                AND DT_FALTA <= TO_DATE(?, 'YYYY-MM-DD')
                AND ATIVO = 'S'";

        if ($emprId) {
            $sql .= " AND ID_EMPR = ?";
        }

        $sql .= " ORDER BY ID_FUNCIONARIO, DT_FALTA";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $i = 1;
        foreach ($funcIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        $stmt->bindValue($i++, $dataIni, PDO::PARAM_STR);
        $stmt->bindValue($i++, $dataFim, PDO::PARAM_STR);

        if ($emprId) {
            $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Indexar por funcionário para acesso rápido O(1)
        $faltasPorFunc = [];
        foreach ($funcIds as $funcId) {
            $faltasPorFunc[$funcId] = [];
        }
        
        foreach ($resultados as $falta) {
            $funcId = $falta['ID_FUNCIONARIO'];
            $faltasPorFunc[$funcId][] = $falta;
        }

        return $faltasPorFunc;
    }
}


