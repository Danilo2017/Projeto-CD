<?php
/**
 * Script temporário — cria TGAZIN_PD_INATIV_PRECO e SEQ_TGAZIN_PD_INATIV no Oracle
 * Remover após execução bem-sucedida.
 */
require_once '../vendor/autoload.php';

$pdo = \core\Database::getInstance('focco');
$log = [];

function execDDL(\PDO $pdo, string $label, string $sql, array &$log): void
{
    try {
        $pdo->exec($sql);
        $log[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        // ORA-00955 = objeto já existe, ORA-02291 = sequence já existe
        // Não trata como erro fatal
        $log[] = ['ok' => false, 'label' => $label, 'msg' => $e->getMessage()];
    }
}

// ── 1. Cria a sequence ────────────────────────────────────────
execDDL($pdo, 'CREATE SEQUENCE SEQ_TGAZIN_PD_INATIV', "
CREATE SEQUENCE SEQ_TGAZIN_PD_INATIV
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE
", $log);

// ── 2. Cria a tabela ─────────────────────────────────────────
execDDL($pdo, 'CREATE TABLE TGAZIN_PD_INATIV_PRECO', "
CREATE TABLE TGAZIN_PD_INATIV_PRECO (
    ID            NUMBER        NOT NULL,
    EMPR_ID       NUMBER        NOT NULL,
    COD_ITEM      NUMBER        NOT NULL,
    TMASC_ITEM_ID NUMBER,
    DESC_TECNICA  VARCHAR2(200),
    MASCARA       VARCHAR2(100),
    DT_CADASTRO   DATE          DEFAULT SYSDATE,
    SIT           NUMBER(1)     DEFAULT 1 NOT NULL,
    CONSTRAINT PK_TGAZIN_PD_INATIV PRIMARY KEY (ID)
)
", $log);

// ── 3. Verifica se a tabela foi criada consultando-a ─────────
$existe = false;
try {
    $stmt = $pdo->query("SELECT COUNT(*) QTD FROM TGAZIN_PD_INATIV_PRECO WHERE ROWNUM = 1");
    $existe = $stmt !== false;
    $log[] = ['ok' => true, 'label' => 'SELECT teste na tabela', 'msg' => 'Tabela acessível'];
} catch (\PDOException $e) {
    $log[] = ['ok' => false, 'label' => 'SELECT teste na tabela', 'msg' => $e->getMessage()];
}

echo json_encode([
    'resultado' => $existe ? 'TABELA OK — pode remover este arquivo' : 'VERIFIQUE OS ERROS ABAIXO',
    'log'       => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
