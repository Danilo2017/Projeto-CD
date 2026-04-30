-- =============================================================================
-- Script: Criar tabela de log de alterações de SQLs + adicionar auditoria
-- Data: 2026-04-04
-- =============================================================================

-- 1. Criar tabela de log de alterações nos SQLs
CREATE TABLE FOCCO3I.TGAZIN_SQL_LOG (
    IDLOG          NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    IDSQL          VARCHAR2(100)  NOT NULL,
    ACAO           VARCHAR2(20)   NOT NULL,
    SQL_ANTERIOR   CLOB,
    SQL_NOVO       CLOB,
    USUARIO        VARCHAR2(50)   NOT NULL,
    DT_ALTERACAO   DATE           DEFAULT SYSDATE NOT NULL,
    OBSERVACAO     VARCHAR2(500)
);

-- Índice para busca por idsql
CREATE INDEX FOCCO3I.IDX_SQL_LOG_IDSQL ON FOCCO3I.TGAZIN_SQL_LOG (IDSQL);

-- Comentários
COMMENT ON TABLE FOCCO3I.TGAZIN_SQL_LOG IS 'Log de alterações nos SQLs do sistema';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.IDLOG IS 'ID único do log';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.IDSQL IS 'Identificador do SQL alterado';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.ACAO IS 'Tipo de ação: INSERT, UPDATE, DELETE';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.SQL_ANTERIOR IS 'SQL antes da alteração (NULL se inserção)';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.SQL_NOVO IS 'SQL após alteração';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.USUARIO IS 'Login do usuário que fez a alteração';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.DT_ALTERACAO IS 'Data/hora da alteração';
COMMENT ON COLUMN FOCCO3I.TGAZIN_SQL_LOG.OBSERVACAO IS 'Observação opcional';

-- Grants
GRANT SELECT, INSERT ON FOCCO3I.TGAZIN_SQL_LOG TO PUBLIC;

-- =============================================================================
-- 2. SQLs para migração (VinculoData.php)
-- =============================================================================

-- vinculodata.colunaExiste
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.tipoCalculo.colunaExiste',
'SELECT COUNT(*) AS EXISTE 
FROM ALL_TAB_COLUMNS 
WHERE OWNER = ''FOCCO3I'' 
AND TABLE_NAME = ''TGAZIN_VINC_FUNC_DATA'' 
AND COLUMN_NAME = ''TIPO_CALCULO'''
);

-- vinculodata.listarPorVinculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.vinculo.listarPorVinculo',
'SELECT 
    vd.ID_VINCULO_DATA,
    vd.ID_VINCULO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    TO_CHAR(vd.DATA, ''DD/MM/YYYY'') AS DATA_FORMATADA,
    vd.ID_CENTRO_TRAB_APOIO,
    ct.COD_CENTRO AS CENTRO_APOIO_COD,
    ct.DESCRICAO AS CENTRO_APOIO_DESCRICAO,
    vd.ATIVO,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
WHERE vd.ID_VINCULO = :id_vinculo
AND vd.ATIVO = ''S''
ORDER BY vd.DATA DESC'
);

-- vinculodata.listarPorFuncionario  
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.funcionario.listarPorFuncionario',
'SELECT 
    vd.ID_VINCULO_DATA,
    vd.ID_VINCULO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    TO_CHAR(vd.DATA, ''DD/MM/YYYY'') AS DATA_FORMATADA,
    vd.ID_CENTRO_TRAB_APOIO,
    ct.COD_CENTRO AS CENTRO_APOIO_COD,
    ct.DESCRICAO AS CENTRO_APOIO_DESCRICAO,
    vd.ATIVO,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
WHERE v.ID_FUNCIONARIO = :id_funcionario
AND v.ID_EMPR = :id_empr
AND vd.ATIVO = ''S''
:filtro_periodo
ORDER BY vd.DATA DESC'
);

-- vinculodata.buscarDatasApoioBatch
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarDatasApoioBatch',
'SELECT 
    v.ID_FUNCIONARIO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    vd.ID_CENTRO_TRAB_APOIO,
    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
WHERE v.ID_FUNCIONARIO IN (:func_ids)
AND v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
ORDER BY v.ID_FUNCIONARIO, vd.DATA'
);

-- vinculodata.buscarRegistroExistente
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.buscarExistente',
'SELECT ID_VINCULO_DATA, ATIVO 
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')
ORDER BY ID_VINCULO_DATA DESC
FETCH FIRST 1 ROW ONLY'
);

-- vinculodata.existeData
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.data.existe',
'SELECT COUNT(*) AS EXISTE 
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')
AND ATIVO = ''S'''
);

-- vinculodata.excluirPorId
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.data.excluirPorId',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO_DATA = :id_vinculo_data'
);

-- vinculodata.excluirPorData
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.data.excluirPorData',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')'
);

-- vinculodata.excluirTodasPorVinculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.vinculo.excluirTodas',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO = :id_vinculo'
);

-- vinculodata.contarDiasApoio
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.contarDias',
'SELECT COUNT(*) AS TOTAL 
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
WHERE v.ID_FUNCIONARIO = :id_funcionario
AND v.ID_EMPR = :id_empr
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')'
);

-- vinculodata.buscarFuncionariosComApoioPeriodo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarFuncionariosPeriodo',
'SELECT DISTINCT
    v.ID_FUNCIONARIO AS FUNC_ID,
    f.COD_FUNC,
    f.NOME AS NOME_FUNC,
    v.ID_CENTRO_TRAB AS CENTRO_TRAB_ID,
    ct.COD_CENTRO,
    ct.DESCRICAO AS DESC_CENTRO,
    v.TIPO_VINCULO
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = v.ID_CENTRO_TRAB
WHERE v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
:filtro_centro
ORDER BY f.NOME'
);

-- vinculodata.buscarTodasDatasApoioPeriodo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarTodasDatasPeriodo',
'SELECT 
    v.ID_FUNCIONARIO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    vd.ID_CENTRO_TRAB_APOIO,
    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
WHERE v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
:filtro_centro
ORDER BY v.ID_FUNCIONARIO, vd.DATA'
);

-- =============================================================================
-- 3. SQLs para migração (PerfilAcesso.php)
-- =============================================================================

-- perfilacesso.listarPerfis
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.perfil.listarPerfis',
'SELECT 
    p.ID_PERFIL, 
    p.NOME, 
    p.DESCRICAO, 
    p.ATIVO,
    TO_CHAR(p.DT_CADASTRO, ''DD/MM/YYYY'') AS DT_CADASTRO,
    (SELECT COUNT(*) FROM FOCCO3I.TGAZIN_USUARIO_PERFIL up WHERE up.ID_PERFIL = p.ID_PERFIL AND up.ATIVO = ''S'') AS QTD_USUARIOS
FROM FOCCO3I.TGAZIN_PERFIL p
:filtro_ativo
ORDER BY p.NOME'
);

-- perfilacesso.listarPerfisAtivos
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.perfil.listarAtivos',
'SELECT 
    ID_PERFIL, 
    NOME, 
    DESCRICAO
FROM FOCCO3I.TGAZIN_PERFIL 
WHERE ATIVO = ''S''
ORDER BY NOME'
);

-- perfilacesso.buscarPerfisUsuario
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.perfil.buscarPorUsuario',
'SELECT 
    p.ID_PERFIL AS PERFIL_ID, 
    p.NOME AS PERFIL_NOME, 
    p.DESCRICAO AS PERFIL_DESCRICAO,
    up.ATIVO AS VINCULO_ATIVO,
    TO_CHAR(up.DT_CADASTRO, ''DD/MM/YYYY'') AS DT_VINCULO
FROM FOCCO3I.TGAZIN_USUARIO_PERFIL up
INNER JOIN FOCCO3I.TGAZIN_PERFIL p ON p.ID_PERFIL = up.ID_PERFIL
WHERE up.LOGIN_USUARIO = :login
AND up.ATIVO = ''S''
AND p.ATIVO = ''S''
ORDER BY p.NOME'
);

-- perfilacesso.listarAcessosPerfil
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.perfil.listarAcessos',
'SELECT 
    pa.ID_PERFIL_ACESSO,
    pa.ID_PERFIL,
    pa.PREFIXO_ROTA,
    pa.DESCRICAO,
    pa.ATIVO,
    TO_CHAR(pa.DT_CADASTRO, ''DD/MM/YYYY'') AS DT_CADASTRO
FROM FOCCO3I.TGAZIN_PERFIL_ACESSO pa
WHERE pa.ID_PERFIL = :id_perfil
:filtro_ativo
ORDER BY pa.PREFIXO_ROTA'
);

-- perfilacesso.listarUsuariosAgrupados
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.usuario.listarComPerfis',
'SELECT 
    up.LOGIN_USUARIO,
    up.ATIVO,
    TO_CHAR(MIN(up.DT_CADASTRO), ''DD/MM/YYYY'') AS DT_CADASTRO,
    LISTAGG(p.NOME, '', '') WITHIN GROUP (ORDER BY p.NOME) AS PERFIS
FROM FOCCO3I.TGAZIN_USUARIO_PERFIL up
INNER JOIN FOCCO3I.TGAZIN_PERFIL p ON p.ID_PERFIL = up.ID_PERFIL AND p.ATIVO = ''S''
WHERE up.ATIVO = ''S''
:filtro_login
:filtro_perfil
GROUP BY up.LOGIN_USUARIO, up.ATIVO
ORDER BY up.LOGIN_USUARIO'
);

-- perfilacesso.usuarioTemPerfil
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.usuario.temPerfil',
'SELECT 1 
FROM FOCCO3I.TGAZIN_USUARIO_PERFIL 
WHERE LOGIN_USUARIO = :login
AND ID_PERFIL = :id_perfil
AND ATIVO = ''S'''
);

-- perfilacesso.perfilTemAcesso
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'auth.perfil.temAcesso',
'SELECT 1 
FROM FOCCO3I.TGAZIN_PERFIL_ACESSO 
WHERE ID_PERFIL = :id_perfil
AND PREFIXO_ROTA = :prefixo_rota
AND ATIVO = ''S'''
);

-- =============================================================================
-- 4. SQLs para migração (ApontamentoProducao.php)
-- =============================================================================

-- apontamento.pontosTotaisCentroPorDia
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'comissao.apontamento.pontosTotaisCentroPorDia',
'SELECT
    ct.ID AS CENTRO_TRAB_ID,
    TO_CHAR(om.DT_MOVTO, ''YYYY-MM-DD'') AS DATA,
    SUM(om.QTD_MOVTO * NVL(up.PONTOS_UP, 0)) AS TOTAL_PONTOS
FROM FOCCO3I.TORDENS_MOVTO om
INNER JOIN FOCCO3I.TITENS_ORDEM io ON io.ID = om.ID_ITEM_ORDEM
INNER JOIN FOCCO3I.TORDENS_PRODUCAO op ON op.ID = io.ID_ORDEM
INNER JOIN FOCCO3I.TPROCESSOS_PROD pp ON pp.ID = om.ID_PROCESSO
INNER JOIN FOCCO3I.TGRUPOS_MAQUINA gm ON gm.ID = pp.ID_GRUPO_MAQUINA
INNER JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = gm.ID_CENTRO_TRAB
INNER JOIN FOCCO3I.TINSUMOS ins ON ins.ID = io.ID_INSUMO
LEFT JOIN FOCCO3I.TGAZIN_PONTOS_UP up ON up.COD_PRODUTO = ins.COD_ITEM 
    AND up.ID_EMPR = :id_empr
    AND up.ATIVO = ''S''
    AND om.DT_MOVTO BETWEEN up.DT_INI_VIGENCIA AND NVL(up.DT_FIM_VIGENCIA, om.DT_MOVTO + 1)
WHERE op.ID_EMPR = :id_empr
AND om.DT_MOVTO BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND ct.ID IN (:centros_ids)
GROUP BY ct.ID, TO_CHAR(om.DT_MOVTO, ''YYYY-MM-DD'')
ORDER BY ct.ID, DATA'
);

-- apontamento.contarRecursosPorCentroDia
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'comissao.apontamento.contarRecursosPorCentroDia',
'SELECT
    ct.ID AS CENTRO_TRAB_ID,
    TO_CHAR(om.DT_MOVTO, ''YYYY-MM-DD'') AS DATA,
    (SELECT COUNT(*) 
     FROM FOCCO3I.TGAZIN_VINC_FUNC v2 
     WHERE v2.ID_CENTRO_TRAB = ct.ID 
     AND v2.ID_EMPR = :id_empr
     AND v2.ATIVO = ''S''
     AND v2.TIPO_VINCULO = ''N'') AS QTD_RECURSOS
FROM FOCCO3I.TORDENS_MOVTO om
INNER JOIN FOCCO3I.TITENS_ORDEM io ON io.ID = om.ID_ITEM_ORDEM
INNER JOIN FOCCO3I.TORDENS_PRODUCAO op ON op.ID = io.ID_ORDEM
INNER JOIN FOCCO3I.TPROCESSOS_PROD pp ON pp.ID = om.ID_PROCESSO
INNER JOIN FOCCO3I.TGRUPOS_MAQUINA gm ON gm.ID = pp.ID_GRUPO_MAQUINA
INNER JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = gm.ID_CENTRO_TRAB
WHERE op.ID_EMPR = :id_empr
AND om.DT_MOVTO BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND ct.ID IN (:centros_ids)
GROUP BY ct.ID, TO_CHAR(om.DT_MOVTO, ''YYYY-MM-DD'')
ORDER BY ct.ID, DATA'
);

-- =============================================================================
-- 5. SQLs adicionais VinculoData.php
-- =============================================================================

-- vinculodata.tabelaExiste
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.tabela.existe',
'SELECT COUNT(*) AS EXISTE FROM USER_TABLES WHERE TABLE_NAME = ''TGAZIN_VINC_FUNC_DATA'''
);

-- vinculodata.colunaExiste (TIPO_CALCULO)
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.coluna.tipoCalculoExiste',
'SELECT COUNT(*) AS EXISTE FROM ALL_TAB_COLUMNS WHERE OWNER = ''FOCCO3I'' AND TABLE_NAME = ''TGAZIN_VINC_FUNC_DATA'' AND COLUMN_NAME = ''TIPO_CALCULO'''
);

-- vinculodata.listarPorVinculo (com placeholder para campo tipo_calculo)
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.vinculo.listarPorVinculo',
'SELECT 
    vd.ID_VINCULO_DATA,
    vd.ID_VINCULO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    TO_CHAR(vd.DATA, ''DD/MM/YYYY'') AS DATA_FORMATADA,
    vd.ID_CENTRO_TRAB_APOIO,
    ct.COD_CENTRO AS CENTRO_APOIO_COD,
    ct.DESCRICAO AS CENTRO_APOIO_DESCRICAO,
    vd.ATIVO,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
WHERE vd.ID_VINCULO = :id_vinculo
AND vd.ATIVO = ''S''
ORDER BY vd.DATA DESC'
);

-- vinculodata.listarPorFuncionarioMes
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.funcionario.listarPorMes',
'SELECT 
    vd.ID_VINCULO_DATA,
    vd.ID_VINCULO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    vd.ID_CENTRO_TRAB_APOIO,
    ct.COD_CENTRO,
    ct.DESCRICAO AS CENTRO_DESCRICAO,
    v.ID_FUNCIONARIO,
    f.NOME AS FUNCIONARIO_NOME
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
WHERE v.ID_FUNCIONARIO = :id_funcionario
AND v.ID_EMPR = :id_empr
AND TO_CHAR(vd.DATA, ''YYYY-MM'') = :mes_ano
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
ORDER BY vd.DATA'
);

-- vinculodata.verificarApoioNaData
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.verificarNaData',
'SELECT 
    vd.ID_VINCULO_DATA,
    vd.ID_VINCULO,
    vd.ID_CENTRO_TRAB_APOIO,
    ct.COD_CENTRO,
    ct.DESCRICAO AS CENTRO_DESCRICAO,
    v.ID_FUNCIONARIO,
    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = vd.ID_CENTRO_TRAB_APOIO
WHERE v.ID_FUNCIONARIO = :id_funcionario
AND v.ID_EMPR = :id_empr
AND vd.DATA = TO_DATE(:data, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
FETCH FIRST 1 ROW ONLY'
);

-- vinculodata.buscarCentroVinculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.vinculo.buscarCentro',
'SELECT ID_CENTRO_TRAB FROM FOCCO3I.TGAZIN_VINC_FUNC WHERE ID_VINCULO = :id_vinculo'
);

-- vinculodata.buscarRegistroExistente
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.buscarExistente',
'SELECT ID_VINCULO_DATA, ATIVO 
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')
ORDER BY ID_VINCULO_DATA DESC
FETCH FIRST 1 ROW ONLY'
);

-- vinculodata.reativarComTipoCalculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.reativarComTipo',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''S'', 
    ID_CENTRO_TRAB_APOIO = :centro_value,
    TIPO_CALCULO = :tipo_calculo,
    DT_ALTERACAO = SYSDATE
WHERE ID_VINCULO_DATA = :id_vinculo_data'
);

-- vinculodata.reativarSemTipoCalculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.reativarSemTipo',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''S'', 
    ID_CENTRO_TRAB_APOIO = :centro_value,
    DT_ALTERACAO = SYSDATE
WHERE ID_VINCULO_DATA = :id_vinculo_data'
);

-- vinculodata.inserirComTipoCalculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.inserirComTipo',
'INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC_DATA (
    ID_VINCULO,
    DATA,
    ID_CENTRO_TRAB_APOIO,
    ATIVO,
    DT_CADASTRO,
    TIPO_CALCULO
) VALUES (
    :id_vinculo,
    TO_DATE(:data, ''YYYY-MM-DD''),
    :centro_value,
    ''S'',
    SYSDATE,
    :tipo_calculo
)'
);

-- vinculodata.inserirSemTipoCalculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.inserirSemTipo',
'INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC_DATA (
    ID_VINCULO,
    DATA,
    ID_CENTRO_TRAB_APOIO,
    ATIVO,
    DT_CADASTRO
) VALUES (
    :id_vinculo,
    TO_DATE(:data, ''YYYY-MM-DD''),
    :centro_value,
    ''S'',
    SYSDATE
)'
);

-- vinculodata.existeData
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.data.existe',
'SELECT COUNT(*) AS EXISTE 
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')
AND ATIVO = ''S'''
);

-- vinculodata.excluirPorId
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.excluirPorId',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO_DATA = :id_vinculo_data'
);

-- vinculodata.excluirPorData
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.registro.excluirPorData',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO = :id_vinculo
AND DATA = TO_DATE(:data, ''YYYY-MM-DD'')'
);

-- vinculodata.excluirTodasPorVinculo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.vinculo.excluirTodas',
'UPDATE FOCCO3I.TGAZIN_VINC_FUNC_DATA 
SET ATIVO = ''N'', DT_ALTERACAO = SYSDATE 
WHERE ID_VINCULO = :id_vinculo'
);

-- vinculodata.buscarDatasApoioBatch
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarDatasApoioBatch',
'SELECT 
    v.ID_FUNCIONARIO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    vd.ID_CENTRO_TRAB_APOIO,
    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
WHERE v.ID_FUNCIONARIO IN (:func_ids)
AND v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
AND v.TIPO_VINCULO = ''N''
ORDER BY v.ID_FUNCIONARIO, vd.DATA'
);

-- vinculodata.buscarFuncionariosComApoioPeriodo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarFuncionariosPeriodo',
'SELECT DISTINCT
    v.ID_FUNCIONARIO AS FUNC_ID,
    f.COD_FUNC,
    f.NOME AS NOME_FUNC,
    v.ID_CENTRO_TRAB AS CENTRO_TRAB_ID,
    ct.COD_CENTRO,
    ct.DESCRICAO AS DESC_CENTRO,
    v.TIPO_VINCULO
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO
LEFT JOIN FOCCO3I.TCENTROS_TRAB ct ON ct.ID = v.ID_CENTRO_TRAB
WHERE v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
:filtro_centro
ORDER BY f.NOME'
);

-- vinculodata.buscarTodasDatasApoioPeriodo
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'vinculodata.apoio.buscarTodasDatasPeriodo',
'SELECT 
    v.ID_FUNCIONARIO,
    TO_CHAR(vd.DATA, ''YYYY-MM-DD'') AS DATA,
    vd.ID_CENTRO_TRAB_APOIO,
    v.ID_CENTRO_TRAB AS CENTRO_PRINCIPAL,
    :campo_tipo_calculo
FROM FOCCO3I.TGAZIN_VINC_FUNC_DATA vd
INNER JOIN FOCCO3I.TGAZIN_VINC_FUNC v ON v.ID_VINCULO = vd.ID_VINCULO
WHERE v.ID_EMPR = :id_empr
AND vd.DATA BETWEEN TO_DATE(:periodo_ini, ''YYYY-MM-DD'') AND TO_DATE(:periodo_fim, ''YYYY-MM-DD'')
AND vd.ATIVO = ''S''
AND v.ATIVO = ''S''
:filtro_centro
ORDER BY v.ID_FUNCIONARIO, vd.DATA'
);

-- =============================================================================
-- 6. SQLs ApontamentoProducao.php
-- =============================================================================

-- apontamento.pontosTotaisCentroPorDia  
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'comissao.apontamento.pontosTotaisCentroPorDia',
'SELECT
    TORDENS_ROT.CENTR_TRAB_ID,
    TO_CHAR(TORDENS_MOVTO.DT_APONT, ''YYYY-MM-DD'') AS DATA_APONTAMENTO,
    TITENS.ID AS ITEM_ID,
    TITENS_EMPR.ID AS ITEMPR_ID,
    TMASC_ITEM.ID AS MASCARA_ID,
    SUM(TORDENS_MOVTO.QUANTIDADE) AS TOTAL_QUANTIDADE,
    COUNT(*) AS QTD_APONTAMENTOS
FROM FOCCO3I.TORDENS
INNER JOIN FOCCO3I.TORDENS_ROT ON TORDENS.ID = TORDENS_ROT.ORDEM_ID
INNER JOIN FOCCO3I.TORDENS_MOVTO ON TORDENS_ROT.ID = TORDENS_MOVTO.TORDEN_ROT_ID
INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TP ON TP.ID = TORDENS.ITPL_ID
INNER JOIN FOCCO3I.TITENS_EMPR ON TITENS_EMPR.ID = TP.ITEMPR_ID
INNER JOIN FOCCO3I.TITENS ON TITENS.ID = TITENS_EMPR.ITEM_ID
LEFT JOIN FOCCO3I.TMASC_ITEM ON TMASC_ITEM.ID = TORDENS.TMASC_ITEM_ID
WHERE TORDENS_MOVTO.DT_APONT BETWEEN TO_DATE(:data_inicio, ''YYYY-MM-DD'') AND TO_DATE(:data_fim, ''YYYY-MM-DD'') + 0.99999
AND TORDENS_ROT.CENTR_TRAB_ID IN (:centros_ids)
AND TORDENS.EMPR_ID = :empr_id
AND TORDENS_ROT.APONTAMENTO = 1
AND TORDENS_ROT.OBRIGATORIO = 1
GROUP BY TORDENS_ROT.CENTR_TRAB_ID, TO_CHAR(TORDENS_MOVTO.DT_APONT, ''YYYY-MM-DD''), 
         TITENS.ID, TITENS_EMPR.ID, TMASC_ITEM.ID'
);

-- apontamento.contarRecursosPorCentroDia
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'comissao.apontamento.contarRecursosPorCentroDia',
'SELECT
    v.ID_CENTRO_TRAB AS CENTRO_TRAB_ID,
    COUNT(DISTINCT v.ID_FUNCIONARIO) AS QTD_RECURSOS
FROM FOCCO3I.TGAZIN_VINC_FUNC v
WHERE v.ID_CENTRO_TRAB IN (:centros_ids)
AND v.ID_EMPR = :empr_id
AND v.ATIVO = ''S''
AND v.TIPO_VINCULO = ''N''
GROUP BY v.ID_CENTRO_TRAB'
);

-- =============================================================================
-- 7. SQLs Comissao.php
-- =============================================================================

-- comissao.centro.buscarPorId
INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES (
'comissao.centro.buscarPorId',
'SELECT COD_CENTRO, DESCRICAO FROM FOCCO3I.TCENTROS_TRAB WHERE ID = :id_centro'
);

COMMIT;

-- =============================================================================
-- Verificação
-- =============================================================================
SELECT COUNT(*) AS TOTAL_SQLS FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL LIKE 'vinculodata.%' OR IDSQL LIKE 'auth.%' OR IDSQL LIKE 'comissao.apontamento.%';
