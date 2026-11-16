-- Projeção de Carga: sequência de carregamento (menor número carrega primeiro; vazio = segue a ordem padrão da tela).
-- Coluna nova na tabela do Portal. Não altera nenhuma coluna existente.
ALTER TABLE FOCCO3I.TGAZIN_CARGA_AGEND ADD (SEQ_CARREGAMENTO NUMBER(5));
COMMENT ON COLUMN FOCCO3I.TGAZIN_CARGA_AGEND.SEQ_CARREGAMENTO IS 'Sequência de carregamento: menor carrega primeiro; nulo = ordem padrão da Projeção de Carga';

-- Descrição só na tela: a TCARGAS (Focco) NÃO é alterada. A descrição digitada fica aqui e, quando preenchida, substitui a do Focco na listagem.
ALTER TABLE FOCCO3I.TGAZIN_CARGA_AGEND ADD (DESCRICAO_TELA VARCHAR2(70));
COMMENT ON COLUMN FOCCO3I.TGAZIN_CARGA_AGEND.DESCRICAO_TELA IS 'Descrição da carga alterada na Projeção de Carga; nulo = usa TCARGAS.DESCRICAO (original do Focco, nunca alterada)';
