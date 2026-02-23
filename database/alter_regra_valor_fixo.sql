-- ============================================
-- Adicionar coluna VALOR_FIXO na tabela TGAZIN_REGRA_FUNC
-- Para suportar tipo Misto (M) = Valor fixo + Valor por ponto
-- ============================================

ALTER TABLE FOCCO3I.TGAZIN_REGRA_FUNC ADD (
    VALOR_FIXO NUMBER(10,4)  -- Valor fixo base para tipo Misto (M)
);

COMMENT ON COLUMN FOCCO3I.TGAZIN_REGRA_FUNC.VALOR_FIXO IS 'Valor fixo base para tipo Misto (M). Usado junto com VALOR_COMISSAO por ponto';

-- Atualizar comentário do tipo de comissão
COMMENT ON COLUMN FOCCO3I.TGAZIN_REGRA_FUNC.TIPO_COMISSAO IS 'P=Percentual sobre pontos, V=Valor por UP, F=Valor fixo total, M=Misto (fixo + por ponto)';
