-- =====================================================
-- Script SQL para Tabela de Datas de Apoio
-- Permite que um funcionário atue como APOIO em dias específicos
-- Tabela: FOCCO3I.TGAZIN_VINC_FUNC_DATA
-- =====================================================

-- 1. Criar tabela de datas de apoio
CREATE TABLE FOCCO3I.TGAZIN_VINC_FUNC_DATA (
    ID_VINCULO_DATA NUMBER(10) GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ID_VINCULO NUMBER(10) NOT NULL,
    DATA DATE NOT NULL,
    ID_CENTRO_TRAB_APOIO NUMBER(10),
    ATIVO CHAR(1) DEFAULT 'S' NOT NULL,
    DT_CADASTRO DATE DEFAULT SYSDATE NOT NULL,
    DT_ALTERACAO DATE,
    CONSTRAINT FK_VINC_DATA_VINCULO FOREIGN KEY (ID_VINCULO) 
        REFERENCES FOCCO3I.TGAZIN_VINC_FUNC(ID_VINCULO) ON DELETE CASCADE,
    CONSTRAINT FK_VINC_DATA_CENTRO FOREIGN KEY (ID_CENTRO_TRAB_APOIO) 
        REFERENCES FOCCO3I.TCENTROS_TRAB(ID),
    CONSTRAINT CK_VINC_DATA_ATIVO CHECK (ATIVO IN ('S', 'N'))
);

-- 2. Índices para performance
CREATE INDEX FOCCO3I.IX_VINC_DATA_VINCULO ON FOCCO3I.TGAZIN_VINC_FUNC_DATA(ID_VINCULO);
CREATE INDEX FOCCO3I.IX_VINC_DATA_DATA ON FOCCO3I.TGAZIN_VINC_FUNC_DATA(DATA);
CREATE INDEX FOCCO3I.IX_VINC_DATA_CENTRO ON FOCCO3I.TGAZIN_VINC_FUNC_DATA(ID_CENTRO_TRAB_APOIO);
-- Índice único para evitar datas duplicadas por vínculo (apenas ativos)
CREATE UNIQUE INDEX FOCCO3I.UIX_VINC_DATA_UNICO ON FOCCO3I.TGAZIN_VINC_FUNC_DATA(
    CASE WHEN ATIVO = 'S' THEN ID_VINCULO END, 
    CASE WHEN ATIVO = 'S' THEN DATA END
);

-- 3. Comentários
COMMENT ON TABLE FOCCO3I.TGAZIN_VINC_FUNC_DATA IS 'Datas específicas onde funcionário atua como APOIO';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.ID_VINCULO_DATA IS 'ID único do registro';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.ID_VINCULO IS 'FK para o vínculo principal do funcionário';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.DATA IS 'Data específica em que o funcionário atua como apoio';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.ID_CENTRO_TRAB_APOIO IS 'Centro de trabalho onde atua como apoio (pode ser diferente do principal)';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.ATIVO IS 'S=Sim, N=Não (inativado)';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.DT_CADASTRO IS 'Data de criação do registro';
COMMENT ON COLUMN FOCCO3I.TGAZIN_VINC_FUNC_DATA.DT_ALTERACAO IS 'Data da última alteração';

-- 4. Grant (ajustar conforme necessário)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON FOCCO3I.TGAZIN_VINC_FUNC_DATA TO <USUARIO>;

-- =====================================================
-- COMO USAR:
-- 
-- 1. Funcionário NORMAL dias normais, APOIO em dias específicos:
--    - Criar vínculo principal com TIPO_VINCULO = 'N' (Normal)
--    - Adicionar registros em TGAZIN_VINC_FUNC_DATA para os dias que atua como apoio
--
-- 2. No cálculo de comissão:
--    - Verificar se existe registro em TGAZIN_VINC_FUNC_DATA para o dia do apontamento
--    - Se existir: calcular como APOIO (100% dos pontos do centro)
--    - Se não existir: calcular como NORMAL (pontos do recurso específico)
--
-- Exemplo de consulta para verificar se funcionário é apoio no dia:
-- SELECT * FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
-- JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
-- WHERE v.ID_FUNCIONARIO = :id_funcionario
-- AND vd.DATA = :data_apontamento
-- AND vd.ATIVO = 'S';
-- =====================================================
