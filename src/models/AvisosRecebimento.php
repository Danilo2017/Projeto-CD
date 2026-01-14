<?php

namespace src\models;

use core\Database;
use PDO;

class AvisosRecebimento
{
    /**
     * Listar avisos de recebimento do dia atual
     * @return array
     */
    public function listarAvisosHoje()
    {
        $sql = "SELECT
            e.COD_EMP ||'-'|| e.RAZAO_SOCIAL AS EMPRESA,
            a.COD_ALMOX AS ALMOX,
            ar.PLACA_VEICULO AS PLACA,
            TO_CHAR(MIN(ar.DATA_HORA_CHEGADA), 'DD/MM/YYYY HH24:MI') AS CHEGADA,
            TO_CHAR(MIN(CASE
                WHEN lg.STATUS = 'INI'
                THEN lg.DATA_HORA
            END), 'DD/MM/YYYY HH24:MI') AS INICIO,
            TO_CHAR(MAX(CASE
                WHEN lg.STATUS = 'FIM'
                THEN lg.DATA_HORA
            END), 'DD/MM/YYYY HH24:MI') AS TERMINO,
            MAX(CASE
                WHEN s.MAIOR_ID LIKE 'INI%' OR s.MAIOR_ID LIKE 'REA%' THEN 'INICIADO'
                WHEN s.MAIOR_ID LIKE 'FIM%' THEN 'FINALIZADO'
                ELSE 'PENDENTE'
            END) AS STATUS,
            MAX(CASE
                WHEN a.COD_ALMOX = '9'
                THEN 'SIM ' || pdc.OBS
            END) AS CROSSDOCKING
        FROM TAVISOS_RECEB r
        JOIN TEMPRESAS e
            ON e.ID = r.EMPR_ID
        JOIN TGAZIN_AVISOS_RECEB ar
            ON ar.AVR_ID = r.ID
        JOIN TITENS_AVISO_RECEB it
            ON it.AVR_ID = r.ID
        JOIN TALMOXARIFADOS a
            ON a.ID = it.ALMOX_ID
        LEFT JOIN TGAZIN_LOG_CONF_AVR_WEB lg
            ON lg.AVR_ID = r.ID
        JOIN VGAZIN_AVRREC_STATUS s
            ON s.EMPRESA = e.ID
           AND s.PLACA_VEICULO = ar.PLACA_VEICULO
           AND s.DATA = TO_CHAR(ar.DATA_HORA_CHEGADA, 'DD/MM/YYYY')
        LEFT JOIN (
            SELECT
                itav.ID,
                LISTAGG(itpdc.OBS, ', ') WITHIN GROUP (ORDER BY itpdc.OBS) AS OBS
            FROM TITENS_AVISO_RECEB itav
            JOIN TPEDC_ITEM itpdc ON itpdc.ID = itav.PEDCITEM_ID
            JOIN TPED_COMPRA pdc ON pdc.ID = itpdc.TPEDC_ID
            GROUP BY itav.ID
        ) pdc
            ON pdc.ID = it.ID
        WHERE e.COD_EMP = 1
          AND a.COD_ALMOX IN ('1','9')
          AND r.id NOT IN (102355,102353)
          AND TRUNC(ar.DATA_HORA_CHEGADA, 'MM') = TRUNC(SYSDATE, 'MM')
        GROUP BY
            e.COD_EMP,
            e.RAZAO_SOCIAL,
            a.COD_ALMOX,
            ar.PLACA_VEICULO
        HAVING MAX(CASE
                WHEN s.MAIOR_ID LIKE 'INI%' OR s.MAIOR_ID LIKE 'REA%' THEN 'INICIADO'
                WHEN s.MAIOR_ID LIKE 'FIM%' THEN 'FINALIZADO'
                ELSE 'PENDENTE'
            END) IN ('PENDENTE', 'INICIADO')
        ORDER BY ar.PLACA_VEICULO";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os totais de avisos do mês atual
     * @return array ['total' => X, 'pendentes' => X, 'iniciados' => X, 'finalizados' => X]
     */
    public function getTotaisMes()
    {
        $sql = "SELECT
            COUNT(*) AS TOTAL,
            SUM(CASE WHEN STATUS = 'PENDENTE' THEN 1 ELSE 0 END) AS PENDENTES,
            SUM(CASE WHEN STATUS = 'INICIADO' THEN 1 ELSE 0 END) AS INICIADOS,
            SUM(CASE WHEN STATUS = 'FINALIZADO' THEN 1 ELSE 0 END) AS FINALIZADOS
        FROM (
            SELECT
                ar.PLACA_VEICULO,
                MAX(CASE
                    WHEN s.MAIOR_ID LIKE 'INI%' OR s.MAIOR_ID LIKE 'REA%' THEN 'INICIADO'
                    WHEN s.MAIOR_ID LIKE 'FIM%' THEN 'FINALIZADO'
                    ELSE 'PENDENTE'
                END) AS STATUS
            FROM TAVISOS_RECEB r
            JOIN TEMPRESAS e
                ON e.ID = r.EMPR_ID
            JOIN TGAZIN_AVISOS_RECEB ar
                ON ar.AVR_ID = r.ID
            JOIN TITENS_AVISO_RECEB it
                ON it.AVR_ID = r.ID
            JOIN TALMOXARIFADOS a
                ON a.ID = it.ALMOX_ID
            JOIN VGAZIN_AVRREC_STATUS s
                ON s.EMPRESA = e.ID
               AND s.PLACA_VEICULO = ar.PLACA_VEICULO
               AND s.DATA = TO_CHAR(ar.DATA_HORA_CHEGADA, 'DD/MM/YYYY')
            WHERE e.COD_EMP = 1
              AND a.COD_ALMOX IN ('1','9')
              AND r.id NOT IN (102355,102353)
              AND TRUNC(ar.DATA_HORA_CHEGADA, 'MM') = TRUNC(SYSDATE, 'MM')
            GROUP BY
                e.COD_EMP,
                e.RAZAO_SOCIAL,
                a.COD_ALMOX,
                ar.PLACA_VEICULO
        )";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total' => (int)($row['TOTAL'] ?? 0),
            'pendentes' => (int)($row['PENDENTES'] ?? 0),
            'iniciados' => (int)($row['INICIADOS'] ?? 0),
            'finalizados' => (int)($row['FINALIZADOS'] ?? 0)
        ];
    }
}
