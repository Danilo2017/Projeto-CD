-- =====================================================================
-- Consulta Planej. Material por Filial — objetos NOVOS no FOCCO3I
-- Não altera nada do OTIMIZA nem tabelas existentes do Focco.
-- Executar como FOCCO3I. Separador de comando: linha com "/".
-- =====================================================================

-- Resultado da consulta, por geração (SESSAO). Mesmas colunas da FOCCO3I.TTEMP_PLA_MAT_FILIAL_OTM,
-- para a cópia da GERA_INTERFABRICA funcionar sem alteração de lógica.
CREATE TABLE TGAZIN_PMF (
    ID               NUMBER        NOT NULL,
    SESSAO           NUMBER        NOT NULL,
    COD_ITEM         VARCHAR2(90),
    DESC_ITEM        VARCHAR2(500),
    ITEM_ID          NUMBER,
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
    ALMOX_PADRAO     NUMBER,
    OUTROS_ALMOX     NUMBER,
    NECESSIDADE      NUMBER,
    SELECAO          NUMBER        DEFAULT 0,
    PESO             NUMBER,
    QTDE_ALT         NUMBER,
    PLANEJAMENTO_ID  NUMBER,
    DT_SESSAO        DATE,
    ESTOQUE          NUMBER,
    COD_GRP_INVENT   NUMBER,
    CONSTRAINT TGAZIN_PMF_PK PRIMARY KEY (ID)
)
/
CREATE INDEX TGAZIN_PMF_SESSAO_I ON TGAZIN_PMF (SESSAO)
/
CREATE SEQUENCE SEQ_TGAZIN_PMF START WITH 1 INCREMENT BY 1 CACHE 100
/
CREATE SEQUENCE SEQ_TGAZIN_PMF_SESSAO START WITH 1 INCREMENT BY 1 NOCACHE
/

-- Parâmetros de cada geração (substitui OTIMIZA.TPARPLA_MAT_FILIAL_OTM); 1 geração ativa por usuário
CREATE TABLE TGAZIN_PMF_PAR (
    SESSAO      NUMBER        NOT NULL,
    USUARIO     VARCHAR2(60)  NOT NULL,
    EMPRESAS    VARCHAR2(200),
    DT_INI      DATE,
    DT_FIM      DATE,
    DT_GERACAO  DATE,
    SEGUNDOS    NUMBER,
    LINHAS      NUMBER,
    CONSTRAINT TGAZIN_PMF_PAR_PK PRIMARY KEY (SESSAO)
)
/
CREATE INDEX TGAZIN_PMF_PAR_USU_I ON TGAZIN_PMF_PAR (USUARIO)
/

-- Mensagens da geração de interfábrica (substitui FOCCO3I.TMSG_OTM_INTERFABRICA para esta tela)
CREATE TABLE TGAZIN_PMF_MSG (
    SESSAO   NUMBER,
    TIPO     VARCHAR2(30),
    MSG      VARCHAR2(4000),
    NUMERO   NUMBER,
    EMPR_ID  NUMBER,
    DT_MSG   DATE
)
/
CREATE INDEX TGAZIN_PMF_MSG_SESSAO_I ON TGAZIN_PMF_MSG (SESSAO)
/

-- Parâmetros de almoxarifado por filial (cópia de OTIMIZA.TPARS_ALMOX_OTM; dados no script 02)
CREATE TABLE TGAZIN_PMF_PARS_ALMOX (
    ID                 NUMBER NOT NULL,
    EMPR_ID            NUMBER,
    ALMOX_PAD_IGUAL    VARCHAR2(100),
    ALMOX_PAD_DIF      VARCHAR2(100),
    OUTRS_ALMOX_IGUAL  VARCHAR2(100),
    OUTRS_ALMOX_DIF    VARCHAR2(100),
    CONSTRAINT TGAZIN_PMF_PARS_ALMOX_PK PRIMARY KEY (ID)
)
/
