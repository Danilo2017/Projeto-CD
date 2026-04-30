-- =====================================================
-- ALTERAÇÃO: Adicionar coluna TIPO_CALCULO na tabela de Datas de Apoio
-- Data: 02/04/2026
-- Descrição: Permite escolher entre TOTAL (pontos totais do centro) 
--            ou MEDIA (média de pontos por recurso produzindo no dia)
-- =====================================================

-- Adicionar coluna TIPO_CALCULO (T = Total, M = Média)
-- Padrão: 'T' (Total) para manter compatibilidade com registros existentes
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC_DATA ADD (
    TIPO_CALCULO VARCHAR2(1) DEFAULT 'T' NOT NULL
);

-- Adicionar constraint para validar valores permitidos
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC_DATA ADD CONSTRAINT CHK_TIPO_CALCULO 
    CHECK (TIPO_CALCULO IN ('T', 'M'));

-- Comentário na coluna
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.TIPO_CALCULO IS 'Tipo de cálculo para apoio: T=Total (pontos totais do centro), M=Média (pontos/recursos no dia)';

-- Verificar alteração
SELECT COLUMN_NAME, DATA_TYPE, DATA_DEFAULT, NULLABLE 
FROM USER_TAB_COLUMNS 
WHERE TABLE_NAME = 'TGAZIN_VINC_FUNC_DATA'
ORDER BY COLUMN_ID;
