<?php
/**
 * P5: Insere SQLs de acesso (PerfilAcesso), vinculo-cc e admin-histórico no GAZIN_SQLS.
 * Executar via: docker exec comissao_colchao php //var/www/html/exec2026-05-21-insert-sql-p5-acesso.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$sqls = [
    // ====== acesso.perfil ======
    'acesso.perfil.listar' =>
        "SELECT ID_PERFIL, NOME, DESCRICAO, ATIVO, DT_CADASTRO " .
        "FROM FOCCO3I.TGAZIN_PERFIL_ACESSO " .
        "ORDER BY NOME",

    'acesso.perfil.listarAtivos' =>
        "SELECT ID_PERFIL, NOME, DESCRICAO " .
        "FROM FOCCO3I.TGAZIN_PERFIL_ACESSO " .
        "WHERE ATIVO = 'S' " .
        "ORDER BY NOME",

    // ====== acesso.usuario (perfis) ======
    'acesso.usuario.listar' =>
        "SELECT UP.ID_USUARIO_PERFIL, UP.LOGIN_USUARIO, UP.PERFIL_ID, PA.NOME AS PERFIL_NOME, " .
        "PA.DESCRICAO AS PERFIL_DESCRICAO, UP.ATIVO, UP.DT_CADASTRO, UP.DT_ALTERACAO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID " .
        "WHERE 1=1 :filtro_login :filtro_ativo :filtro_perfil " .
        "ORDER BY UP.LOGIN_USUARIO, PA.NOME",

    'acesso.usuario.buscarPerfis' =>
        "SELECT UP.ID_USUARIO_PERFIL, UP.PERFIL_ID, PA.NOME AS PERFIL_NOME, " .
        "PA.DESCRICAO AS PERFIL_DESCRICAO, UP.ATIVO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID " .
        "WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login) " .
        "AND UP.ATIVO = 'S' AND PA.ATIVO = 'S'",

    'acesso.usuario.buscarPorId' =>
        "SELECT UP.ID_USUARIO_PERFIL, UP.LOGIN_USUARIO, UP.PERFIL_ID, PA.NOME AS PERFIL_NOME, " .
        "UP.ATIVO, UP.DT_CADASTRO, UP.DT_ALTERACAO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID " .
        "WHERE UP.ID_USUARIO_PERFIL = :id",

    'acesso.usuario.verificarPerfil' =>
        "SELECT ID_USUARIO_PERFIL, ATIVO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_PERFIL " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) AND PERFIL_ID = :perfil_id " .
        "ORDER BY ID_USUARIO_PERFIL DESC",

    'acesso.usuario.reativarPerfil' =>
        "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL " .
        "SET ATIVO = 'S', DT_ALTERACAO = SYSDATE " .
        "WHERE ID_USUARIO_PERFIL = :id",

    'acesso.usuario.inserirPerfil' =>
        "INSERT INTO FOCCO3I.TGAZIN_USUARIO_PERFIL (LOGIN_USUARIO, PERFIL_ID, ATIVO, DT_CADASTRO) " .
        "VALUES (UPPER(:login), :perfil_id, 'S', SYSDATE)",

    'acesso.usuario.buscarIdPerfil' =>
        "SELECT MAX(ID_USUARIO_PERFIL) AS ID " .
        "FROM FOCCO3I.TGAZIN_USUARIO_PERFIL " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) AND PERFIL_ID = :perfil_id",

    'acesso.usuario.inativarPerfis' =>
        "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL " .
        "SET ATIVO = 'N', DT_ALTERACAO = SYSDATE " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login)",

    'acesso.usuario.removerPerfil' =>
        "UPDATE FOCCO3I.TGAZIN_USUARIO_PERFIL " .
        "SET ATIVO = 'N', DT_ALTERACAO = SYSDATE " .
        "WHERE ID_USUARIO_PERFIL = :id",

    'acesso.usuario.isAdmin' =>
        "SELECT 1 AS ADMIN FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ROTA PR ON PR.PERFIL_ID = PA.ID_PERFIL " .
        "WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login) " .
        "AND UP.ATIVO = 'S' AND PA.ATIVO = 'S' AND PR.PREFIXO_ROTA = '*' " .
        "FETCH FIRST 1 ROW ONLY",

    'acesso.usuario.temAcessoModulo' =>
        "SELECT 1 AS TEM_ACESSO FROM FOCCO3I.TGAZIN_USUARIO_PERFIL UP " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ACESSO PA ON PA.ID_PERFIL = UP.PERFIL_ID " .
        "INNER JOIN FOCCO3I.TGAZIN_PERFIL_ROTA PR ON PR.PERFIL_ID = PA.ID_PERFIL " .
        "WHERE UPPER(UP.LOGIN_USUARIO) = UPPER(:login) " .
        "AND UP.ATIVO = 'S' AND PA.ATIVO = 'S' " .
        "AND (PR.PREFIXO_ROTA = :modulo OR PR.PREFIXO_ROTA = '*') " .
        "FETCH FIRST 1 ROW ONLY",

    // ====== acesso.empresa ======
    'acesso.empresa.listar' =>
        "SELECT ID, COD_EMP AS CODIGO, RAZAO_SOCIAL, NOME_FAN AS NOME_FANTASIA " .
        "FROM FOCCO3I.TEMPRESAS " .
        "ORDER BY COD_EMP",

    // ====== acesso.usuario (filiais) ======
    'acesso.usuario.buscarFiliais' =>
        "SELECT UF.ID_USUARIO_FILIAL, UF.EMPR_ID, E.COD_EMP AS CODIGO, E.RAZAO_SOCIAL, " .
        "E.NOME_FAN AS NOME_FANTASIA, UF.ATIVO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_FILIAL UF " .
        "INNER JOIN FOCCO3I.TEMPRESAS E ON E.ID = UF.EMPR_ID " .
        "WHERE UPPER(UF.LOGIN_USUARIO) = UPPER(:login) AND UF.ATIVO = 'S' " .
        "ORDER BY E.COD_EMP",

    'acesso.usuario.verificarFilial' =>
        "SELECT ID_USUARIO_FILIAL, ATIVO " .
        "FROM FOCCO3I.TGAZIN_USUARIO_FILIAL " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) AND EMPR_ID = :empr_id " .
        "ORDER BY ID_USUARIO_FILIAL DESC",

    'acesso.usuario.reativarFilial' =>
        "UPDATE FOCCO3I.TGAZIN_USUARIO_FILIAL " .
        "SET ATIVO = 'S', DT_ALTERACAO = SYSDATE " .
        "WHERE ID_USUARIO_FILIAL = :id",

    'acesso.usuario.inserirFilial' =>
        "INSERT INTO FOCCO3I.TGAZIN_USUARIO_FILIAL (LOGIN_USUARIO, EMPR_ID, ATIVO, DT_CADASTRO) " .
        "VALUES (UPPER(:login), :empr_id, 'S', SYSDATE)",

    'acesso.usuario.buscarIdFilial' =>
        "SELECT MAX(ID_USUARIO_FILIAL) AS ID " .
        "FROM FOCCO3I.TGAZIN_USUARIO_FILIAL " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login) AND EMPR_ID = :empr_id",

    'acesso.usuario.inativarFiliais' =>
        "UPDATE FOCCO3I.TGAZIN_USUARIO_FILIAL " .
        "SET ATIVO = 'N', DT_ALTERACAO = SYSDATE " .
        "WHERE UPPER(LOGIN_USUARIO) = UPPER(:login)",

    // ====== comissao.vinculo (adições) ======
    'comissao.vinculo.verificarColunaCc' =>
        "SELECT COUNT(*) AS EXISTE FROM ALL_TAB_COLUMNS " .
        "WHERE OWNER = 'FOCCO3I' AND TABLE_NAME = 'TGAZIN_VINC_FUNC' AND COLUMN_NAME = 'ID_EMP_CC'",

    'comissao.vinculo.listarCentrosCusto' =>
        "SELECT tt.ID, tt.EMPR_ID, tt.COD_CENTRO AS COD, tt.DESCRICAO " .
        "FROM FOCCO3I.TCENTROS_TRAB tt " .
        "WHERE 1=1 :filtro_empr " .
        "ORDER BY tt.COD_CENTRO",

    'comissao.vinculo.getAlocacaoPorFuncionario' =>
        "SELECT v.ID_FUNCIONARIO, v.ID_EMP_CC, ca.COD_CENTRO AS COD_CC, ca.DESCRICAO AS CC_DESCRICAO " .
        "FROM FOCCO3I.TGAZIN_VINC_FUNC v " .
        "LEFT JOIN FOCCO3I.TCENTROS_TRAB ca ON ca.ID = v.ID_EMP_CC " .
        "WHERE v.ATIVO = 'S' AND v.ID_EMP_CC IS NOT NULL :filtro_empr",

    // ====== admin.sqls (adições) ======
    'admin.sqls.listarHistorico' =>
        "SELECT IDLOG, IDSQL, ACAO, " .
        "DBMS_LOB.SUBSTR(SQL_ANTERIOR, 4000, 1) AS SQL_ANTERIOR, " .
        "DBMS_LOB.SUBSTR(SQL_NOVO, 4000, 1) AS SQL_NOVO, " .
        "USUARIO, TO_CHAR(DT_ALTERACAO, 'DD/MM/YYYY HH24:MI:SS') AS DATA_ALTERACAO, OBSERVACAO " .
        "FROM FOCCO3I.TGAZIN_SQL_LOG " .
        "WHERE IDSQL = :idsql " .
        "ORDER BY DT_ALTERACAO DESC",
];

$pdo = null;
try {
    $pdo = \core\Database::getInstance('focco');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    echo "Desabilitando trigger SQLS_TR...\n";
    $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR DISABLE");

    foreach ($sqls as $idsql => $sqlContent) {
        echo "\nProcessando '$idsql'...\n";

        $pdo->exec("DELETE FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL = '$idsql'");
        echo "  DELETE ok\n";

        $pdo->exec("INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL) VALUES ('$idsql', EMPTY_CLOB())");
        echo "  INSERT ok\n";

        $chunkSize = 2000;
        $chunks    = str_split($sqlContent, $chunkSize);
        $total     = count($chunks);

        foreach ($chunks as $i => $chunk) {
            $escaped = str_replace("'", "''", $chunk);
            if ($i === 0) {
                $pdo->exec("UPDATE FOCCO3I.GAZIN_SQLS SET SQL = TO_CLOB('$escaped') WHERE IDSQL = '$idsql'");
            } else {
                $pdo->exec("UPDATE FOCCO3I.GAZIN_SQLS SET SQL = SQL || TO_CLOB('$escaped') WHERE IDSQL = '$idsql'");
            }
            echo "  chunk " . ($i + 1) . "/$total\n";
        }

        $pdo->exec("COMMIT");
        echo "  OK: '$idsql' salvo!\n";
    }

    echo "\nReabilitando trigger SQLS_TR...\n";
    $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR ENABLE");

    echo "\nConcluído. " . count($sqls) . " chaves inseridas/atualizadas.\n";

} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    if ($pdo) {
        try { $pdo->exec("ALTER TRIGGER FOCCO3I.SQLS_TR ENABLE"); } catch (\Exception $e2) {}
    }
    exit(1);
}
