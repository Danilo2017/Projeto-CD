<?php
/**
 * Patch: cria o perfil 'Qualidade' em TGAZIN_PERFIL_ACESSO
 * e a rota 'qualidade' em TGAZIN_PERFIL_ROTA.
 */
require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \core\Database::getInstance('focco');

// Verifica se já existe
$chk = $pdo->query("SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'QUALIDADE'");
$row = $chk->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $id = (int)$row['ID_PERFIL'];
    // Verifica se a rota já existe
    $rotaChk = $pdo->query("SELECT ID_ROTA FROM TGAZIN_PERFIL_ROTA WHERE PERFIL_ID = $id AND PREFIXO_ROTA = 'qualidade'");
    $rota = $rotaChk->fetch(PDO::FETCH_ASSOC);
    if (!$rota) {
        $novoIdRota = (int)$pdo->query("SELECT NVL(MAX(ID_ROTA),0)+1 NX FROM TGAZIN_PERFIL_ROTA")->fetchColumn();
        $pdo->exec("INSERT INTO TGAZIN_PERFIL_ROTA (ID_ROTA, PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO) VALUES ($novoIdRota, $id, 'qualidade', SYSDATE)");
        $pdo->exec('COMMIT');
        echo json_encode(['ok' => true, 'msg' => "Perfil Qualidade (ID=$id) já existia — rota 'qualidade' adicionada."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['ok' => true, 'msg' => "Perfil Qualidade (ID=$id) e rota já existem. Nada a fazer."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

/* ID_PERFIL e ID_ROTA são colunas IDENTITY — Oracle gera automaticamente */
$pdo->exec("INSERT INTO TGAZIN_PERFIL_ACESSO (NOME, DESCRICAO, ATIVO, DT_CADASTRO)
            VALUES ('Qualidade', 'Acesso ao modulo Qualidade', 'S', SYSDATE)");

// Busca o ID gerado
$novoId = (int)$pdo->query("SELECT ID_PERFIL FROM TGAZIN_PERFIL_ACESSO WHERE UPPER(NOME) = 'QUALIDADE'")->fetchColumn();

$pdo->exec("INSERT INTO TGAZIN_PERFIL_ROTA (PERFIL_ID, PREFIXO_ROTA, DT_CADASTRO)
            VALUES ($novoId, 'qualidade', SYSDATE)");

$pdo->exec('COMMIT');

echo json_encode([
    'ok'        => true,
    'msg'       => "Perfil 'Qualidade' criado com ID=$novoId e rota 'qualidade' vinculada.",
    'id_perfil' => $novoId,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
