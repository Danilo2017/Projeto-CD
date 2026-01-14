<?php

namespace src\models;

use core\Database;
use PDO;

class ReciboDescarga
{
    /**
     * Obter próximo número de recibo
     * @return int
     */
    public function proximoNumeroRecibo()
    {
        $sql = "SELECT SEQ_RECIBO_DESCARGA.NEXTVAL AS PROXIMO FROM DUAL";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['PROXIMO'];
    }

    /**
     * Inserir novo recibo
     * @param array $dados
     * @return array
     */
    public function inserir($dados)
    {
        $numeroRecibo = $this->proximoNumeroRecibo();
        $sql = "INSERT INTO TGAZIN_RECIBO_DESCARGA (
            NUMERO_RECIBO,
            AGENDAMENTO_ID,
            DATA_EMISSAO,
            EMPRESA_PAGADORA,
            CNPJ_CPF,
            VALOR_PAGO,
            FORMA_PAGAMENTO,
            OBSERVACOES,
            USUARIO_EMISSAO
        ) VALUES (
            :numero_recibo,
            :agendamento_id,
            SYSDATE,
            :empresa_pagadora,
            :cnpj_cpf,
            :valor_pago,
            :forma_pagamento,
            :observacoes,
            :usuario_emissao
        )";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $agendamentoId = $dados['agendamento_id'];
        $empresaPagadora = $dados['empresa_pagadora'];
        $cnpjCpf = isset($dados['cnpj_cpf']) ? $dados['cnpj_cpf'] : null;
        $valorPago = floatval($dados['valor_pago']);
        $formaPagamento = isset($dados['forma_pagamento']) ? $dados['forma_pagamento'] : 'DINHEIRO';
        $observacoes = isset($dados['observacoes']) ? $dados['observacoes'] : null;
        $usuarioEmissao = isset($dados['usuario_emissao']) ? $dados['usuario_emissao'] : 'SISTEMA';

        $stmt->bindParam(':numero_recibo', $numeroRecibo);
        $stmt->bindParam(':agendamento_id', $agendamentoId);
        $stmt->bindParam(':empresa_pagadora', $empresaPagadora);
        $stmt->bindParam(':cnpj_cpf', $cnpjCpf);
        $stmt->bindParam(':valor_pago', $valorPago);
        $stmt->bindParam(':forma_pagamento', $formaPagamento);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':usuario_emissao', $usuarioEmissao);
        $stmt->execute();

        // Buscar o último ID gerado para esse número de recibo
        $sqlId = "SELECT ID FROM TGAZIN_RECIBO_DESCARGA WHERE NUMERO_RECIBO = :numero_recibo ORDER BY ID DESC FETCH FIRST 1 ROWS ONLY";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':numero_recibo', $numeroRecibo);
        $stmtId->execute();
        $id = $stmtId->fetchColumn();
        error_log('DEBUG RECIBO - ID gerado (busca): ' . print_r($id, true));
        return [
            'id' => $id,
            'numero_recibo' => $numeroRecibo
        ];
    }

    /**
     * Buscar recibo por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
            r.ID,
            r.NUMERO_RECIBO,
            r.AGENDAMENTO_ID,
            TO_CHAR(r.DATA_EMISSAO, 'DD/MM/YYYY') AS DATA_EMISSAO,
            TO_CHAR(r.DATA_EMISSAO, 'HH24:MI') AS HORA_EMISSAO,
            r.EMPRESA_PAGADORA,
            r.CNPJ_CPF,
            r.VALOR_PAGO,
            r.FORMA_PAGAMENTO,
            r.OBSERVACOES,
            r.USUARIO_EMISSAO,
            a.FORNECEDOR,
            a.PLACA_VEICULO AS PLACA,
            TO_CHAR(a.DATA_HORA_CHEGADA, 'DD/MM/YYYY') AS DATA_RECEBIMENTO,
            a.PESO,
            a.VOLUME
        FROM TGAZIN_RECIBO_DESCARGA r
        INNER JOIN TGAZIN_AGENDAMENTO_RECEBIMENTO a ON r.AGENDAMENTO_ID = a.ID
        WHERE r.ID = :id";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar recibo por número
     * @param int $numero
     * @return array|null
     */
    public function buscarPorNumero($numero)
    {
        $sql = "SELECT 
            r.ID,
            r.NUMERO_RECIBO,
            r.AGENDAMENTO_ID,
            TO_CHAR(r.DATA_EMISSAO, 'DD/MM/YYYY') AS DATA_EMISSAO,
            TO_CHAR(r.DATA_EMISSAO, 'HH24:MI') AS HORA_EMISSAO,
            r.EMPRESA_PAGADORA,
            r.CNPJ_CPF,
            r.VALOR_PAGO,
            r.FORMA_PAGAMENTO,
            r.OBSERVACOES,
            r.USUARIO_EMISSAO,
            a.FORNECEDOR,
            a.PLACA_VEICULO AS PLACA,
            TO_CHAR(a.DATA_HORA_CHEGADA, 'DD/MM/YYYY') AS DATA_RECEBIMENTO,
            a.PESO,
            a.VOLUME
        FROM TGAZIN_RECIBO_DESCARGA r
        INNER JOIN TGAZIN_AGENDAMENTO_RECEBIMENTO a ON r.AGENDAMENTO_ID = a.ID
        WHERE r.NUMERO_RECIBO = :numero";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numero', $numero);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar recibos por agendamento
     * @param int $agendamentoId
     * @return array
     */
    public function listarPorAgendamento($agendamentoId)
    {
        $sql = "SELECT 
            r.ID,
            r.NUMERO_RECIBO,
            TO_CHAR(r.DATA_EMISSAO, 'DD/MM/YYYY HH24:MI') AS DATA_EMISSAO,
            r.EMPRESA_PAGADORA,
            r.VALOR_PAGO,
            r.FORMA_PAGAMENTO
        FROM TGAZIN_RECIBO_DESCARGA r
        WHERE r.AGENDAMENTO_ID = :agendamento_id
        ORDER BY r.DATA_EMISSAO DESC";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':agendamento_id', $agendamentoId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos os recibos do mês atual
     * @return array
     */
    public function listarRecibosMes()
    {
        $sql = "SELECT 
            r.ID,
            r.NUMERO_RECIBO,
            TO_CHAR(r.DATA_EMISSAO, 'DD/MM/YYYY HH24:MI') AS DATA_EMISSAO,
            r.EMPRESA_PAGADORA,
            r.CNPJ_CPF,
            r.VALOR_PAGO,
            r.FORMA_PAGAMENTO,
            a.FORNECEDOR,
            a.PLACA_VEICULO AS PLACA
        FROM TGAZIN_RECIBO_DESCARGA r
        INNER JOIN TGAZIN_AGENDAMENTO_RECEBIMENTO a ON r.AGENDAMENTO_ID = a.ID
        WHERE TRUNC(r.DATA_EMISSAO, 'MM') = TRUNC(SYSDATE, 'MM')
        ORDER BY r.DATA_EMISSAO DESC";
        
        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
