<?php
/** @var bool     $is_admin */
/** @var array    $rotas_permitidas */
/** @var string   $base */
/** @var callable $render */
$acesso = $is_admin || in_array('manutencao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Liberação de Ordens de Manutenção',
    'showNavbar' => true,
    'pageActive' => 'manutencao-liberacao-ordens',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
/* ---- layout ---- */
.lib-wrap    { padding: 10px; display: flex; flex-direction: column; gap: 10px; height: calc(100vh - 56px); box-sizing: border-box; }
.lib-toolbar { display: flex; gap: 6px; align-items: center; background: #fff; border-radius: 8px;
               padding: 8px 14px; box-shadow: 0 1px 4px rgba(0,0,0,.08); flex-shrink: 0; flex-wrap: wrap; }
.lib-toolbar .badge-count { font-size: 12px; color: #555; margin-left: auto; }
.lib-panel        { display: flex; flex-direction: column; background: #fff; border-radius: 8px;
                    box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; flex: 1; min-height: 0; }
.lib-panel-header { background: #1565c0; color: #fff; padding: 8px 14px; font-size: 13px;
                    font-weight: 700; text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.lib-panel-body   { flex: 1; overflow-y: auto; min-height: 0; }

/* ---- tabela desktop ---- */
.lib-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.lib-table thead th { background: #1565c0; color: #fff; padding: 5px 8px; font-size: 11px;
                      font-weight: 600; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
.lib-table tbody tr { cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.lib-table tbody tr:hover   { background: #e3f2fd; }
.lib-table tbody tr.row-sel { background: #bbdefb; }
.lib-table td { padding: 4px 8px; vertical-align: middle; white-space: nowrap;
                overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.lib-table td.c { text-align: center; }
.lib-empty { text-align: center; color: #aaa; font-size: 13px; padding: 24px 8px; }

/* ---- cards mobile ---- */
.lib-cards { padding: 8px; display: flex; flex-direction: column; gap: 10px; }
.lib-card  { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
             box-shadow: 0 2px 6px rgba(0,0,0,.07); overflow: hidden; cursor: pointer; }
.lib-card.sel { border-color: #1565c0; background: #e3f2fd; }
.lib-card-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px;
                 background: #f5f9ff; border-bottom: 1px solid #e3eaf6; }
.lib-card-head input[type=checkbox] { width: 20px; height: 20px; cursor: pointer; flex-shrink: 0; }
.lib-card-ordem { font-weight: 700; font-size: 14px; color: #1565c0; flex: 1; }
.lib-card-body  { padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.lib-card-row   { display: flex; justify-content: space-between; align-items: center;
                  font-size: 12px; gap: 8px; }
.lib-card-label { color: #888; font-weight: 600; font-size: 11px; white-space: nowrap; }
.lib-card-value { color: #222; text-align: right; word-break: break-word; }
.lib-card-foot  { padding: 8px 12px; background: #fafafa; border-top: 1px solid #f0f0f0;
                  font-size: 11px; color: #666; }

/* ---- badges ---- */
.prio { display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 50%; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }
.prio-0 { background: #e53935; }
.prio-1 { background: #f9a825; color: #222; }
.prio-2 { background: #ef6c00; }
.prio-3 { background: #388e3c; }
.prio-9 { background: #212121; }
.atend-badge { color: #1565c0; font-weight: 700; font-size: 14px; }

/* ---- alerta ---- */
.lib-alert { display: none; background: #c62828; color: #fff; padding: 10px 14px;
             border-radius: 8px; font-size: 13px; font-weight: 700; text-align: center;
             flex-shrink: 0; animation: pulseFade 1.2s ease-in-out infinite; }
.lib-alert.show { display: block; }
@keyframes pulseFade { 0%,100%{opacity:1} 50%{opacity:.55} }

/* ---- mobile ajustes de wrap ---- */
@media (max-width: 640px) {
    .lib-wrap { height: auto; min-height: calc(100vh - 56px); }
}
</style>

<div class="lib-wrap">

    <div class="lib-alert" id="libAlert">⚠️ Há ordens pendentes de liberação!</div>

    <div class="lib-toolbar">
        <button class="btn btn-sm btn-primary"  id="btnAtender" onclick="acaoAtender()" disabled>
            <i class="bi bi-play-circle"></i> Atender
        </button>
        <button class="btn btn-sm btn-dark"     id="btnFechar"  onclick="acaoFechar()"  disabled>
            <i class="bi bi-lock"></i> Fechar
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="carregar()" title="Atualizar">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button class="btn btn-sm btn-warning" id="btnSom" onclick="ativarSom()" title="Alertas sonoros ligados">
            <i class="bi bi-bell-fill"></i> Som ON
        </button>
        <span class="badge-count" id="cntLib">—</span>
    </div>

    <div class="lib-panel">
        <div class="lib-panel-header">Ordens com OK Registrado — pendentes de liberação</div>
        <div class="lib-panel-body" id="libContent">
            <div class="lib-empty">Carregando...</div>
        </div>
    </div>

</div>

<div id="lib-app-data"
     data-base="<?= htmlspecialchars($base) ?>"
     data-empr="<?= (int) ($_SESSION['empresa']['id'] ?? 0) ?>"
     style="display:none"></div>
<script src="<?= $base ?>src/js/manutencao-liberacao-ordens.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
