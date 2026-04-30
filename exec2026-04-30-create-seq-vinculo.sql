-- ===================================================================
-- Cria a sequence FOCCO3I.SEQ_TGAZIN_VINC_FUNC
-- Necessária para o INSERT em FOCCO3I.TGAZIN_VINC_FUNC (ID_VINCULO).
-- Causa do erro: ORA-02289 sequence does not exist.
-- ===================================================================

-- 1) Verifica se já existe (se aparecer linha, NÃO precisa criar)
SELECT SEQUENCE_NAME, MIN_VALUE, INCREMENT_BY, LAST_NUMBER
  FROM ALL_SEQUENCES
 WHERE SEQUENCE_OWNER = 'FOCCO3I'
   AND SEQUENCE_NAME  = 'SEQ_TGAZIN_VINC_FUNC';

-- 2) Cria a sequence iniciando do próximo ID livre da tabela
DECLARE
    v_max NUMBER;
BEGIN
    SELECT NVL(MAX(ID_VINCULO), 0) + 1 INTO v_max FROM FOCCO3I.TGAZIN_VINC_FUNC;
    EXECUTE IMMEDIATE
        'CREATE SEQUENCE FOCCO3I.SEQ_TGAZIN_VINC_FUNC '
        || 'START WITH ' || v_max
        || ' INCREMENT BY 1 NOCACHE NOCYCLE';
END;
/

-- 3) Confere o resultado
SELECT SEQUENCE_NAME, MIN_VALUE, INCREMENT_BY, LAST_NUMBER
  FROM ALL_SEQUENCES
 WHERE SEQUENCE_OWNER = 'FOCCO3I'
   AND SEQUENCE_NAME  = 'SEQ_TGAZIN_VINC_FUNC';

COMMIT;
