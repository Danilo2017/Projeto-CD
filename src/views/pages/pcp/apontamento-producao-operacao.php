<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acesso = $is_admin || in_array('apontamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }

$sessao = $_SESSION['apont_sessao'] ?? [];
?>
<?= $render('header', [
    'pageTitle'  => 'Apontamento de Produção',
    'showNavbar' => false,
    'pageActive' => 'apontamento-producao',
]) ?>

<style>
*{box-sizing:border-box}
body{background:#1565c0;margin:0;padding:0;font-family:Arial,sans-serif;height:100vh;display:flex;flex-direction:column}
.ap-topbar{background:#0d47a1;color:#fff;padding:10px 14px;display:flex;align-items:center;gap:10px;flex-shrink:0}
.ap-back{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0;line-height:1}
.ap-topbar-title{font-size:15px;font-weight:600;flex:1}
.ap-topbar-actions{display:flex;gap:8px}
.ap-topbar-actions button{background:rgba(255,255,255,.15);border:none;color:#fff;padding:5px 10px;border-radius:5px;cursor:pointer;font-size:13px}
.ap-topbar-actions button:hover{background:rgba(255,255,255,.25)}
.ap-status{background:#1565c0;color:#fff;padding:8px 14px;font-size:12px;display:flex;gap:20px;flex-shrink:0}
.ap-status span{font-weight:600}
.ap-status small{opacity:.7;font-weight:400}
.ap-content{flex:1;display:flex;flex-direction:column;background:#f5f5f5;overflow:hidden}
.ap-scan-area{background:#fff;padding:14px;border-bottom:1px solid #ddd;flex-shrink:0}
.ap-scan-label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.ap-scan-input{width:100%;font-size:18px;padding:10px 14px;border:2px solid #1565c0;border-radius:8px;outline:none;text-align:center;letter-spacing:2px;background:#f8fbff}
.ap-scan-input:focus{border-color:#0d47a1;box-shadow:0 0 0 3px rgba(21,101,192,.15)}
.ap-scan-status{text-align:center;font-size:12px;color:#1565c0;margin-top:6px;height:16px}
.ap-feedback{padding:10px 14px;flex-shrink:0;min-height:54px}
.ap-feedback .fb-ok{background:#e8f5e9;border-left:4px solid #43a047;border-radius:4px;padding:8px 12px;color:#2e7d32;font-size:13px;display:flex;align-items:center;gap:8px}
.ap-feedback .fb-err{background:#ffebee;border-left:4px solid #e53935;border-radius:4px;padding:8px 12px;color:#c62828;font-size:13px;display:flex;align-items:center;gap:8px}
.ap-feedback .fb-info{background:#e3f2fd;border-left:4px solid #1565c0;border-radius:4px;padding:8px 12px;color:#0d47a1;font-size:13px}
.ap-tabs{display:flex;background:#fff;border-bottom:2px solid #e0e0e0;flex-shrink:0}
.ap-tab{flex:1;padding:10px;text-align:center;font-size:12px;font-weight:600;color:#888;cursor:pointer;text-transform:uppercase;letter-spacing:.5px;border-bottom:3px solid transparent;margin-bottom:-2px}
.ap-tab.active{color:#1565c0;border-bottom-color:#1565c0}
.ap-tab-content{flex:1;overflow-y:auto;padding:10px 14px}
.ap-tab-panel{display:none}
.ap-tab-panel.active{display:block}

/* Ordem card */
.ordem-card{background:#fff;border-radius:8px;padding:12px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.ordem-num{color:#1565c0;font-weight:700;font-size:15px}
.ordem-cod{color:#888;font-size:12px;margin-left:8px}
.ordem-desc{font-size:12px;color:#444;margin:4px 0}
.ordem-progress{margin-top:8px}
.progress-bar-wrap{height:8px;background:#e0e0e0;border-radius:4px;overflow:hidden;display:flex}
.pb-lido{background:#1565c0;height:100%}
.pb-pendente{background:#ef9a9a;height:100%}
.progress-labels{display:flex;justify-content:space-between;font-size:10px;color:#888;margin-top:3px}
.pend-badge{float:right;background:#fff3e0;color:#e65100;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600}

/* Leitura card */
.leitura-card{background:#fff;border-radius:8px;padding:10px 12px;margin-bottom:6px;display:flex;align-items:center;gap:10px;font-size:13px}
.leitura-card .lc-status{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.leitura-card .lc-ok{background:#43a047}
.leitura-card .lc-err{background:#e53935}
.leitura-card .lc-info{flex:1;min-width:0}
.leitura-card .lc-time{font-size:10px;color:#aaa;flex-shrink:0}
</style>

<div id="appWrap" style="height:100vh;display:flex;flex-direction:column">

<div class="ap-topbar">
    <button class="ap-back" onclick="encerrar()" title="Encerrar sessão">&#8592;</button>
    <span class="ap-topbar-title">Apontamento de Produção</span>
    <div class="ap-topbar-actions">
        <button onclick="recarregarOrdens()" title="Atualizar ordens"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
</div>

<div class="ap-status">
    <div><small>STATUS</small><br><span id="stStatus">EM PRODUÇÃO</span></div>
    <div><small>FUN</small><br><span id="stFunc"><?= htmlspecialchars($sessao['func_id'] ?? '-') ?></span></div>
    <div><small>OPE</small><br><span id="stOpe"><?= htmlspecialchars($sessao['operacao_id'] ?? '-') ?></span></div>
    <div><small>REC</small><br><span id="stRec"><?= htmlspecialchars($sessao['maquina_id'] ?? '-') ?></span></div>
    <div style="margin-left:auto;font-size:10px;opacity:.7;max-width:120px;text-align:right">
        <?= htmlspecialchars($sessao['func_nome'] ?? '') ?>
    </div>
</div>

<div class="ap-content">

    <div class="ap-scan-area">
        <div class="ap-scan-label">Leitura Apontamento</div>
        <input type="text" id="scanInput" class="ap-scan-input" placeholder="Aponte o coletor..." autocomplete="off" autocorrect="off" spellcheck="false" inputmode="none">
        <div class="ap-scan-status" id="scanStatus">&#128994; Scanner ativo</div>
    </div>

    <div class="ap-feedback" id="feedbackArea"></div>

    <div class="ap-tabs">
        <div class="ap-tab active" data-tab="ordens" onclick="trocarTab('ordens')">Por Ordem</div>
        <div class="ap-tab" data-tab="leituras" onclick="trocarTab('leituras')">Por Leitura</div>
    </div>

    <div class="ap-tab-content">
        <div id="tabOrdens" class="ap-tab-panel active">
            <div id="listaOrdens"><p style="text-align:center;color:#aaa;font-size:13px;padding:20px">Carregando...</p></div>
        </div>
        <div id="tabLeituras" class="ap-tab-panel">
            <div id="listaLeituras"><p style="text-align:center;color:#aaa;font-size:13px;padding:20px">Nenhum apontamento nesta sessão.</p></div>
        </div>
    </div>

</div>
</div>

<script src="<?= $base ?>src/js/pcp-apontamento-producao.js?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/src/js/pcp-apontamento-producao.js') ?: '1' ?>"></script>
<script>
const SESSAO  = <?= json_encode([
    'func_id'      => $sessao['func_id']       ?? 0,
    'func_nome'    => $sessao['func_nome']      ?? '',
    'operacao_id'  => $sessao['operacao_id']    ?? 0,
    'operacao_nome'=> $sessao['operacao_nome']  ?? '',
    'maquina_id'   => $sessao['maquina_id']     ?? 0,
    'maquina_nome' => $sessao['maquina_nome']   ?? '',
]) ?>;
</script>
<?= $render('footer') ?>
