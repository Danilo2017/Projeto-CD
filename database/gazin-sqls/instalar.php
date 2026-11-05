<?php
/**
 * Instala SQLs na FOCCO3I.GAZIN_SQLS a partir de um arquivo de registro (database/gazin-sqls/*.php).
 *
 * Uso (dentro do container, na raiz do projeto):
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-05-industrial.php            -> simulação
 *   php database/gazin-sqls/instalar.php database/gazin-sqls/2026-11-05-industrial.php --aplicar  -> grava
 *
 * Regras de segurança:
 *  - idsql que já existe com o MESMO SQL: ignora.
 *  - idsql que já existe com SQL DIFERENTE: não grava (CONFLITO) — mudar SQL existente só pela tela /admin-sqls.
 *  - antes de gravar, cada SQL é compilado no Oracle (envolvido em ROWNUM = 0, não traz dados).
 *  - cada inserção é registrada na FOCCO3I.TGAZIN_SQL_LOG (ACAO = INSERT).
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';

$arquivo = $argv[1] ?? '';
$aplicar = in_array('--aplicar', $argv, true);
$usuario = getenv('USUARIO_LOG') ?: 'DANILO.MOREIRA';
if (!is_file($arquivo)) { fwrite(STDERR, "Arquivo não encontrado: $arquivo\n"); exit(1); }
$lista = require $arquivo;

// valores de exemplo só para compilar o SQL (o resultado não é usado)
$exemplos = [':ano_mes' => "'2026-09'", ':anomes' => '202609'];

$pdo = core\Database::getInstance('focco');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$existe = $pdo->prepare("SELECT SQL FROM FOCCO3I.GAZIN_SQLS WHERE IDSQL = :id");

$novos = $iguais = $conflitos = $erros = 0;
foreach ($lista as $idsql => $item) {
    // [descrição, SQL, exemplos opcionais de parâmetros só para compilar]
    [$descricao, $sql] = $item;
    $exemplosSql = array_merge($exemplos, $item[2] ?? []);
    $sql = rtrim(str_replace("\r\n", "\n", $sql));
    if (!preg_match('/^[a-z][a-zA-Z0-9]*\.[a-z][a-zA-Z0-9]*\.[a-z][a-zA-Z0-9]*$/', $idsql)) {
        echo "ERRO      $idsql — fora da convenção {modulo}.{entidade}.{acao}\n"; $erros++; continue;
    }
    $existe->execute(['id' => $idsql]);
    $atual = $existe->fetchColumn();
    if ($atual !== false) {
        $atual = is_resource($atual) ? stream_get_contents($atual) : (string) $atual;
        if (rtrim(str_replace("\r\n", "\n", $atual)) === $sql) { echo "IGUAL     $idsql\n"; $iguais++; }
        else { echo "CONFLITO  $idsql — já existe com SQL diferente; não alterado\n"; $conflitos++; }
        continue;
    }
    // compila no Oracle sem trazer dados
    $teste = strtr($sql, $exemplosSql);
    if (preg_match('/:[a-zA-Z_]\w*/', preg_replace("/'[^']*'/", "''", $teste), $sobra)) {
        echo "ERRO      $idsql — parâmetro sem valor de exemplo: {$sobra[0]}\n"; $erros++; continue;
    }
    try {
        $pdo->query("SELECT COUNT(*) FROM ($teste) WHERE ROWNUM = 0")->fetchColumn();
    } catch (Throwable $e) {
        echo "ERRO      $idsql — não compila: " . trim(substr($e->getMessage(), 0, 200)) . "\n"; $erros++; continue;
    }
    if (!$aplicar) { echo "NOVO      $idsql (compila ok; simulação)\n"; $novos++; continue; }

    // Texto longo num único bind dá ORA-01460: envia em pedaços de 1000 caracteres, TO_CLOB(:p0) || TO_CLOB(:p1) ...
    $pedacos = mb_str_split($sql, 1000);
    $clob = implode(' || ', array_map(fn($i) => "TO_CLOB(:p$i)", array_keys($pedacos)));
    $obs  = 'Migração TASK-REFATORACAO (SQL saiu do PHP): ' . basename($arquivo);
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO FOCCO3I.GAZIN_SQLS (IDSQL, SQL, DESCRICAO, USUARIO) VALUES (:id, $clob, :descr, :usu)");
        $ins->bindValue(':id', $idsql); $ins->bindValue(':descr', mb_substr($descricao, 0, 200)); $ins->bindValue(':usu', $usuario);
        foreach ($pedacos as $i => $p) $ins->bindValue(":p$i", $p);
        $ins->execute();
        $log = $pdo->prepare("INSERT INTO FOCCO3I.TGAZIN_SQL_LOG (IDSQL, ACAO, SQL_ANTERIOR, SQL_NOVO, USUARIO, OBSERVACAO)
                              VALUES (:id, 'INSERT', NULL, $clob, :usu, :obs)");
        $log->bindValue(':id', $idsql); $log->bindValue(':usu', $usuario); $log->bindValue(':obs', $obs);
        foreach ($pedacos as $i => $p) $log->bindValue(":p$i", $p);
        $log->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "ERRO      $idsql — não gravou: " . trim(substr($e->getMessage(), 0, 200)) . "\n"; $erros++; continue;
    }
    // confere o que ficou gravado
    $existe->execute(['id' => $idsql]);
    $grav = $existe->fetchColumn(); $grav = is_resource($grav) ? stream_get_contents($grav) : (string) $grav;
    if (rtrim(str_replace("\r\n", "\n", $grav)) !== $sql) { echo "ERRO      $idsql — gravado diferente do arquivo\n"; $erros++; continue; }
    echo "GRAVADO   $idsql (" . count($pedacos) . " pedaço(s), conferido)\n"; $novos++;
}
printf("\n%s: %d novo(s), %d igual(is), %d conflito(s), %d erro(s)\n", $aplicar ? 'APLICADO' : 'SIMULAÇÃO', $novos, $iguais, $conflitos, $erros);
exit($erros || $conflitos ? 2 : 0);
