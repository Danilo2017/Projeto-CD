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
        $sql = "SELECT TEMPRESAS.COD_EMP COD_EMP,
       TEMPRESAS.RAZAO_SOCIAL EMPRESA,
       TALMOXARIFADOS.COD_ALMOX ALMOX,
       TGAZIN_AVISOS_RECEB.PLACA_VEICULO PLACA,
       MIN(to_char(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA,'DD/MM/RRRR HH24:MM')) CHEGADA,
       MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
THEN TO_CHAR(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA,'DD/MM/RRRR HH24:MM')
ELSE ''
END) INICIO,
       MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM' 
THEN TO_CHAR(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA,'DD/MM/RRRR HH24:MM')
ELSE ''
END) TERMINO,
CASE
    WHEN MIN(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA) IS NULL
         THEN 'PENDENTE' -- só tem chegada (sem log de início)
    WHEN MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
                  THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA 
             END) IS NOT NULL
         AND MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM'
                       THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA
                  END) IS NULL
         THEN 'INICIADO' -- iniciou, mas não finalizou
    WHEN MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
                  THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA 
             END) IS NOT NULL
         AND MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM'
                       THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA
                  END) IS NOT NULL
         THEN 'FINALIZADO' -- chegou, iniciou e finalizou
    ELSE 'PENDENTE'
END AS STATUS,
       MAX(CASE WHEN VGAZIN_AVRREC_STATUS.ALMOX LIKE '%9%' THEN 'SIM ' ||(select wm_concat(itpdc.OBS)
                                                                           from TITENS_AVISO_RECEB itav
                                                                              ,tpedc_item itpdc
                                                                              ,tped_compra pdc
                                                                              where itav.PEDCITEM_ID = itpdc.id
                                                                              and itpdc.TPEDC_ID = pdc.id
                                                                              and itav.id = TITENS_AVISO_RECEB.id)
ELSE ''
END) CROSSDOCKING
  FROM VGAZIN_AVRREC_STATUS VGAZIN_AVRREC_STATUS,
       TEMPRESAS TEMPRESAS,
       TGAZIN_AVISOS_RECEB TGAZIN_AVISOS_RECEB,
       TAVISOS_RECEB TAVISOS_RECEB,
       TGAZIN_LOG_CONF_AVR_WEB TGAZIN_LOG_CONF_AVR_WEB,
       TITENS_AVISO_RECEB TITENS_AVISO_RECEB,
       TALMOXARIFADOS TALMOXARIFADOS
 WHERE TEMPRESAS.ID = TAVISOS_RECEB.EMPR_ID
   AND TAVISOS_RECEB.ID = TITENS_AVISO_RECEB.AVR_ID
   AND TAVISOS_RECEB.ID = TGAZIN_LOG_CONF_AVR_WEB.AVR_ID(+)
   AND TAVISOS_RECEB.ID = TGAZIN_AVISOS_RECEB.AVR_ID
   AND TALMOXARIFADOS.ID = TITENS_AVISO_RECEB.ALMOX_ID
   AND (( TEMPRESAS.COD_EMP in (1)))
   AND VGAZIN_AVRREC_STATUS.EMPRESA = TEMPRESAS.ID
   AND (( TALMOXARIFADOS.COD_ALMOX in ('1','9')))
   AND TRUNC(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA) >= TRUNC(SYSDATE, 'MM')
   AND TRUNC(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA) <= TRUNC(SYSDATE)
   AND VGAZIN_AVRREC_STATUS.PLACA_VEICULO =TGAZIN_AVISOS_RECEB.PLACA_VEICULO
   AND VGAZIN_AVRREC_STATUS.DATA =to_char(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA,'DD/MM/RRRR')
   AND VGAZIN_AVRREC_STATUS.NFE LIKE '%1%'
GROUP BY TEMPRESAS.COD_EMP,
         TEMPRESAS.RAZAO_SOCIAL,
         TALMOXARIFADOS.COD_ALMOX,
         TGAZIN_AVISOS_RECEB.PLACA_VEICULO";

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
            SELECT TEMPRESAS.COD_EMP COD_EMP,
       TEMPRESAS.RAZAO_SOCIAL EMPRESA,
       TALMOXARIFADOS.COD_ALMOX ALMOX,
       TGAZIN_AVISOS_RECEB.PLACA_VEICULO PLACA,
       MIN(to_char(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA,'DD/MM/RRRR HH24:MM')) CHEGADA,
       MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
THEN TO_CHAR(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA,'DD/MM/RRRR HH24:MM')
ELSE ''
END) INICIO,
       MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM' 
THEN TO_CHAR(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA,'DD/MM/RRRR HH24:MM')
ELSE ''
END) TERMINO,
CASE
    WHEN MIN(TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA) IS NULL
         THEN 'PENDENTE' -- só tem chegada (sem log de início)
    WHEN MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
                  THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA 
             END) IS NOT NULL
         AND MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM'
                       THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA
                  END) IS NULL
         THEN 'INICIADO' -- iniciou, mas não finalizou
    WHEN MIN(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'INI' 
                  THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA 
             END) IS NOT NULL
         AND MAX(CASE WHEN TGAZIN_LOG_CONF_AVR_WEB.STATUS = 'FIM'
                       THEN TGAZIN_LOG_CONF_AVR_WEB.DATA_HORA
                  END) IS NOT NULL
         THEN 'FINALIZADO' -- chegou, iniciou e finalizou
    ELSE 'PENDENTE'
END AS STATUS,
       MAX(CASE WHEN VGAZIN_AVRREC_STATUS.ALMOX LIKE '%9%' THEN 'SIM ' ||(select wm_concat(itpdc.OBS)
                                                                           from TITENS_AVISO_RECEB itav
                                                                              ,tpedc_item itpdc
                                                                              ,tped_compra pdc
                                                                              where itav.PEDCITEM_ID = itpdc.id
                                                                              and itpdc.TPEDC_ID = pdc.id
                                                                              and itav.id = TITENS_AVISO_RECEB.id)
ELSE ''
END) CROSSDOCKING
  FROM VGAZIN_AVRREC_STATUS VGAZIN_AVRREC_STATUS,
       TEMPRESAS TEMPRESAS,
       TGAZIN_AVISOS_RECEB TGAZIN_AVISOS_RECEB,
       TAVISOS_RECEB TAVISOS_RECEB,
       TGAZIN_LOG_CONF_AVR_WEB TGAZIN_LOG_CONF_AVR_WEB,
       TITENS_AVISO_RECEB TITENS_AVISO_RECEB,
       TALMOXARIFADOS TALMOXARIFADOS
 WHERE TEMPRESAS.ID = TAVISOS_RECEB.EMPR_ID
   AND TAVISOS_RECEB.ID = TITENS_AVISO_RECEB.AVR_ID
   AND TAVISOS_RECEB.ID = TGAZIN_LOG_CONF_AVR_WEB.AVR_ID(+)
   AND TAVISOS_RECEB.ID = TGAZIN_AVISOS_RECEB.AVR_ID
   AND TALMOXARIFADOS.ID = TITENS_AVISO_RECEB.ALMOX_ID
   AND (( TEMPRESAS.COD_EMP in (1)))
   AND VGAZIN_AVRREC_STATUS.EMPRESA = TEMPRESAS.ID
   AND (( TALMOXARIFADOS.COD_ALMOX in ('1','9')))
   AND TRUNC(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA) >= TRUNC(SYSDATE, 'MM')
   AND TRUNC(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA) <= TRUNC(SYSDATE)
   AND VGAZIN_AVRREC_STATUS.PLACA_VEICULO =TGAZIN_AVISOS_RECEB.PLACA_VEICULO
   AND VGAZIN_AVRREC_STATUS.DATA =to_char(TGAZIN_AVISOS_RECEB.DATA_HORA_CHEGADA,'DD/MM/RRRR')
   --AND VGAZIN_AVRREC_STATUS.NFE LIKE '%1%'
GROUP BY TEMPRESAS.COD_EMP,
         TEMPRESAS.RAZAO_SOCIAL,
         TALMOXARIFADOS.COD_ALMOX,
         TGAZIN_AVISOS_RECEB.PLACA_VEICULO)";

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
