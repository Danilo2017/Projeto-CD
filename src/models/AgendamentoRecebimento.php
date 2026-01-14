<?php

namespace src\models;

use core\Database;
use PDO;

class AgendamentoRecebimento
{
    /**
     * Listar agendamentos pendentes (hoje ou anteriores)
     * @return array
     */
    public function listarPendentes()
    {
        $sql = "SELECT
            EMPRESA_NOME AS EMPRESA,
            ALMOXARIFADO AS ALMOX,
            PLACA_VEICULO AS PLACA,
            TO_CHAR(DATA_HORA_CHEGADA, 'DD/MM/YYYY HH24:MI') AS CHEGADA,
            FORNECEDOR,
            OBSERVACOES,
            STATUS
        FROM TGAZIN_AGENDAMENTO_RECEBIMENTO
        WHERE STATUS = 'PENDENTE'
          AND TRUNC(DATA_HORA_CHEGADA) <= TRUNC(SYSDATE)
        ORDER BY DATA_HORA_CHEGADA ASC";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos os recebimentos agendados
     * @return array
     */
    public function listarTodos()
    {
        $sql = "SELECT 
            ID,
            TO_CHAR(DATA_HORA_CHEGADA, 'YYYY-MM-DD') AS DATA,
            TO_CHAR(DATA_HORA_CHEGADA, 'HH24:MI') AS HORA,
            FORNECEDOR,
            PLACA_VEICULO AS PLACA,
            OBSERVACOES AS DESCRICAO,
            PESO,
            VOLUME,
            CASE WHEN STATUS = 'FINALIZADO' THEN 'S' ELSE 'N' END AS RECEBIDO
        FROM TGAZIN_AGENDAMENTO_RECEBIMENTO
        ORDER BY DATA_HORA_CHEGADA";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $recebimentos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recebimentos[] = [
                'id' => $row['ID'],
                'data' => $row['DATA'],
                'hora' => $row['HORA'],
                'fornecedor' => $row['FORNECEDOR'],
                'placa' => $row['PLACA'],
                'descricao' => $row['DESCRICAO'] ?: '',
                'peso' => $row['PESO'],
                'volume' => $row['VOLUME'],
                'recebido' => $row['RECEBIDO'] === 'S'
            ];
        }
        
        return $recebimentos;
    }

    /**
     * Verificar se existe um registro duplicado (mesmo fornecedor, data e hora)
     * @param array $input
     * @return bool
     */
    public function verificarDuplicataRecente($input)
    {
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;

        $sql = "SELECT COUNT(*) AS QTD FROM TGAZIN_AGENDAMENTO_RECEBIMENTO 
                WHERE DATA_HORA_CHEGADA = TO_DATE(:data_hora, 'YYYY-MM-DD HH24:MI')
                AND UPPER(TRIM(FORNECEDOR)) = UPPER(TRIM(:fornecedor))
                AND UPPER(TRIM(NVL(OBSERVACOES, ''))) = UPPER(TRIM(:descricao))
                AND NVL(UPPER(TRIM(PLACA_VEICULO)), 'VAZIO') = NVL(UPPER(TRIM(:placa)), 'VAZIO')";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $placaCheck = $placa !== null ? $placa : 'VAZIO';
        $stmt->bindParam(':data_hora', $data_hora);
        $stmt->bindParam(':fornecedor', $fornecedor);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':placa', $placaCheck);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row && $row['QTD'] > 0;
    }

    /**
     * Inserir novo agendamento
     * @param array $input
     * @return int ID do registro inserido
     */
    public function inserir($input)
    {
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $peso = isset($input['peso']) && $input['peso'] !== null ? floatval($input['peso']) : null;
        $volume = isset($input['volume']) && $input['volume'] !== null ? floatval($input['volume']) : null;
        $status = isset($input['recebido']) && $input['recebido'] ? 'FINALIZADO' : 'PENDENTE';

        $sql = "INSERT INTO TGAZIN_AGENDAMENTO_RECEBIMENTO (
            EMPRESA_COD,
            EMPRESA_NOME,
            ALMOXARIFADO,
            PLACA_VEICULO,
            DATA_HORA_CHEGADA,
            FORNECEDOR,
            OBSERVACOES,
            PESO,
            VOLUME,
            STATUS
        ) VALUES (
            '1',
            '1 - GAZIN INDUSTRIA E COMERCIO',
            '1',
            :placa,
            TO_DATE(:data_hora, 'YYYY-MM-DD HH24:MI'),
            :fornecedor,
            :descricao,
            :peso,
            :volume,
            :status
        ) RETURNING ID INTO :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $id = null;
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':data_hora', $data_hora);
        $stmt->bindParam(':fornecedor', $fornecedor);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':peso', $peso);
        $stmt->bindParam(':volume', $volume);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 32);
        $stmt->execute();
        
        return $id;
    }

    /**
     * Atualizar agendamento existente
     * @param array $input
     * @return bool
     */
    public function atualizar($input)
    {
        $id = $input['id'];
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $peso = isset($input['peso']) && $input['peso'] !== null ? floatval($input['peso']) : null;
        $volume = isset($input['volume']) && $input['volume'] !== null ? floatval($input['volume']) : null;
        $status = isset($input['recebido']) && $input['recebido'] ? 'FINALIZADO' : 'PENDENTE';

        $sql = "UPDATE TGAZIN_AGENDAMENTO_RECEBIMENTO 
                SET PLACA_VEICULO = :placa,
                    DATA_HORA_CHEGADA = TO_DATE(:data_hora, 'YYYY-MM-DD HH24:MI'),
                    FORNECEDOR = :fornecedor,
                    OBSERVACOES = :descricao,
                    PESO = :peso,
                    VOLUME = :volume,
                    STATUS = :status
                WHERE ID = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':data_hora', $data_hora);
        $stmt->bindParam(':fornecedor', $fornecedor);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':peso', $peso);
        $stmt->bindParam(':volume', $volume);
        $stmt->bindParam(':status', $status);
        
        return $stmt->execute();
    }

    /**
     * Excluir agendamento
     * @param int $id
     * @return bool
     */
    public function excluir($id)
    {
        $sql = "DELETE FROM TGAZIN_AGENDAMENTO_RECEBIMENTO WHERE ID = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    /**
     * Alternar status do agendamento (PENDENTE <-> FINALIZADO)
     * @param int $id
     * @return array Status atualizado
     */
    public function alterarStatus($id)
    {
        // Primeiro busca o status atual
        $sqlSelect = "SELECT STATUS FROM TGAZIN_AGENDAMENTO_RECEBIMENTO WHERE ID = :id";
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sqlSelect);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            throw new \Exception('Registro não encontrado');
        }
        
        // Alterna o status
        $novoStatus = ($row['STATUS'] === 'FINALIZADO') ? 'PENDENTE' : 'FINALIZADO';
        
        $sqlUpdate = "UPDATE TGAZIN_AGENDAMENTO_RECEBIMENTO SET STATUS = :status WHERE ID = :id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':status', $novoStatus);
        $stmtUpdate->bindParam(':id', $id);
        $stmtUpdate->execute();
        
        return [
            'id' => $id,
            'status' => $novoStatus,
            'recebido' => $novoStatus === 'FINALIZADO'
        ];
    }
}
