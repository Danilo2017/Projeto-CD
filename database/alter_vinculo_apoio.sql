-- ============================================
-- Script para adicionar suporte a funcionários de APOIO
-- Executar no banco Oracle FOCCO
-- ============================================

-- 1. Adicionar coluna TIPO_VINCULO na tabela TGAZIN_VINC_FUNC
-- N = Normal (vinculado ao recurso, regra padrão)
-- A = Apoio (ganha sobre produtividade total do centro, sem desconto de falta)
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC ADD TIPO_VINCULO CHAR(1) DEFAULT 'N' NOT NULL;

-- 2. Tornar ID_RECURSO opcional (para funcionários de apoio que não têm recurso específico)
ALTER TABLE FOCCO3I.TGAZIN_VINC_FUNC MODIFY ID_RECURSO NUMBER(10) NULL;

-- 3. Criar índice para o tipo de vínculo
CREATE INDEX FOCCO3I.IX_VINC_TIPO ON FOCCO3I.TGAZIN_VINC_FUNC(TIPO_VINCULO);

-- 4. Adicionar comentário sobre o campo
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC.TIPO_VINCULO IS 'N=Normal (vinculado ao recurso), A=Apoio (ganha sobre produtividade total do centro, sem desconto de falta, usa regra específica se cadastrada)';

-- ============================================
-- VERIFICAÇÃO (opcional)
-- ============================================
-- SELECT * FROM FOCCO3I.TGAZIN_VINC_FUNC WHERE TIPO_VINCULO = 'A';
