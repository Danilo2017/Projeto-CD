-- =====================================================================
-- Job que mantém o snapshot do Planejamento de Reposição CD atualizado.
-- A cada 30 min, das 06h às 20h, de segunda a sábado.
-- Executar como FOCCO3I. Separador de comando: linha com "/".
--
-- Para alterar o intervalo:
--   BEGIN DBMS_SCHEDULER.SET_ATTRIBUTE('JOB_GAZIN_PLA_REP_CD', 'repeat_interval',
--         'FREQ=HOURLY;BYDAY=MON,TUE,WED,THU,FRI,SAT;BYHOUR=6,7,8,9,10,11,12,13,14,15,16,17,18,19,20'); END;
-- Para pausar:  BEGIN DBMS_SCHEDULER.DISABLE('JOB_GAZIN_PLA_REP_CD'); END;
-- =====================================================================
BEGIN
    DBMS_SCHEDULER.CREATE_JOB(
        job_name        => 'JOB_GAZIN_PLA_REP_CD',
        job_type        => 'PLSQL_BLOCK',
        job_action      => 'BEGIN GAZIN_PLA_REP_CD.GERAR(''JOB''); END;',
        start_date      => SYSTIMESTAMP,
        repeat_interval => 'FREQ=MINUTELY;INTERVAL=30;BYDAY=MON,TUE,WED,THU,FRI,SAT;BYHOUR=6,7,8,9,10,11,12,13,14,15,16,17,18,19,20',
        enabled         => TRUE,
        comments        => 'Planejamento de Reposicao CD - snapshot TGAZIN_PLA_REP_CD (painel P&D)');
END;
/
