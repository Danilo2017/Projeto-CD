-- =====================================================
-- ALTERAÇÃO: Alocação do Funcionário (Centro de Trabalho) no Vínculo
-- Data: 30/04/2026
-- Objetivo: Permitir vincular um Centro de Trabalho (TCENTROS_TRAB)
--           como "Alocação" do funcionário no cadastro de Vínculo.
--           NÃO impacta cálculos de comissão; serve apenas para
--           exibição em relatórios.
-- =====================================================

-- 1) Adicionar coluna ID_CENTRO_ALOCACAO (FK lógica para FOCCO3I.TCENTROS_TRAB.ID)
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC ADD (ID_CENTRO_ALOCACAO NUMBER(10));

COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC.ID_CENTRO_ALOCACAO IS
    'Centro de Trabalho (TCENTROS_TRAB.ID) usado como Alocação do funcionário em relatórios. Não influencia cálculos.';

-- Verificar
SELECT COLUMN_NAME, DATA_TYPE, NULLABLE
FROM USER_TAB_COLUMNS
WHERE TABLE_NAME = 'TGAZIN_VINC_FUNC'
ORDER BY COLUMN_ID;
