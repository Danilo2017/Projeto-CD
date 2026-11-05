-- =====================================================================
-- Planejamento de Reposição CD — objetos NOVOS no FOCCO3I
-- Não altera nada do OTIMIZA nem tabelas existentes do Focco.
-- Executar como FOCCO3I. Separador de comando: linha com "/".
-- =====================================================================

-- Snapshot do planejamento (substitui TTEMP_PLA_REP_CD_OTM por sessão)
CREATE TABLE TGAZIN_PLA_REP_CD (
    ID               NUMBER        NOT NULL,
    ITEM_ID          NUMBER,
    COD_ITEM         VARCHAR2(20),
    DESC_ITEM        VARCHAR2(500),
    ITEM_EMPR_ID     NUMBER,
    EMPR_ID          NUMBER,
    COD_GRP_ITE      VARCHAR2(30),
    MASC_ID          NUMBER,
    MASCARA          VARCHAR2(4000),
    LOTE_MULT        NUMBER,
    ESTQ_SEG         NUMBER,
    MEDIA_TRIMESTRAL NUMBER,
    MEDIA_MENSAL     NUMBER,
    SALDO_CD         NUMBER,
    DIAS_EST         NUMBER,
    SALDO_FILIAL     NUMBER,
    PEDIDO           NUMBER,
    INCLUSAO         DATE,
    ENTREGA          DATE,
    TOTAL            NUMBER,
    PENDENTE         NUMBER,
    DEMANDA          NUMBER,
    PLANEJADOR       VARCHAR2(100),
    LOTE_MIN         NUMBER,
    TEMPO_REP        NUMBER,
    TIPO_PRODUTO     VARCHAR2(5),
    PLANEJAMENTO_ID  NUMBER,
    DT_GERACAO       DATE,
    CONSTRAINT TGAZIN_PLA_REP_CD_PK PRIMARY KEY (ID)
)
/
CREATE SEQUENCE SEQ_TGAZIN_PLA_REP_CD START WITH 1 INCREMENT BY 1 NOCACHE
/

-- Histórico de status / observações por item (substitui TOBS_PLA_REP_CD_OTM)
CREATE TABLE TGAZIN_PLA_REP_CD_OBS (
    ID            NUMBER        NOT NULL,
    ITEM_ID       NUMBER,
    ITEM_EMPR_ID  NUMBER,
    MASC_ID       NUMBER,
    STATUS        VARCHAR2(1),
    DATA          DATE,
    OBS           VARCHAR2(100),
    USUARIO       VARCHAR2(60),
    CONSTRAINT TGAZIN_PLA_REP_CD_OBS_PK PRIMARY KEY (ID)
)
/
CREATE INDEX TGAZIN_PLA_REP_CD_OBS_ITEM_I ON TGAZIN_PLA_REP_CD_OBS (ITEM_EMPR_ID, MASC_ID)
/
CREATE SEQUENCE SEQ_TGAZIN_PLA_REP_CD_OBS START WITH 100000 INCREMENT BY 1 NOCACHE
/

-- Controle da geração (1 linha): última execução, duração e trava contra execução simultânea
CREATE TABLE TGAZIN_PLA_REP_CD_CTRL (
    ID         NUMBER        NOT NULL,
    STATUS     VARCHAR2(10),
    DT_INICIO  DATE,
    DT_FIM     DATE,
    SEGUNDOS   NUMBER,
    LINHAS     NUMBER,
    USUARIO    VARCHAR2(60),
    ERRO       VARCHAR2(4000),
    CONSTRAINT TGAZIN_PLA_REP_CD_CTRL_PK PRIMARY KEY (ID)
)
/
INSERT INTO TGAZIN_PLA_REP_CD_CTRL (ID, STATUS) VALUES (1, 'NOVO')
/
COMMIT
/
