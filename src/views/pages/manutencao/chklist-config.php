<?php
/** @var bool $is_admin @var array $rotas_permitidas @var string $base @var callable $render */
$acesso = $is_admin || in_array('manutencao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acesso) { header('Location: ' . $base . 'sem-acesso'); exit; }
?>
<?= $render('header', [
    'pageTitle'  => 'Cadastro de Checklist',
    'showNavbar' => true,
    'pageActive' => 'manutencao-chklist-config',
    'customCSS'  => ['src/css/comissao-dashboard.css'],
    'bodyStyle'  => 'margin:0;padding:0;',
]) ?>

<style>
.cc-wrap  { padding: 14px; max-width: 680px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
.cc-card  { background: #fff; border-radius: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.1); padding: 18px; }
.cc-title { font-size: 14px; font-weight: 700; color: #1565c0; margin-bottom: 14px; }
.cc-select-wrap { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cc-select-wrap select { flex: 1; border: 1px solid #ccc; border-radius: 6px; padding: 7px 10px; font-size: 13px; }
.cc-add   { display: flex; gap: 8px; align-items: center; margin-top: 14px; flex-wrap: wrap; }
.cc-add input { flex: 1; border: 1px solid #ccc; border-radius: 6px; padding: 7px 10px; font-size: 13px; min-width: 180px; }
.cc-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 12px; }
.cc-table th { background: #1565c0; color: #fff; padding: 6px 10px; text-align: left; font-size: 12px; }
.cc-table td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.cc-table tr:hover td { background: #f5f9ff; }
.cc-empty { text-align: center; color: #aaa; padding: 16px; font-size: 13px; }
</style>

<div class="cc-wrap">
    <div class="cc-card">
        <div class="cc-title">Selecione a Máquina</div>
        <div class="cc-select-wrap" style="position:relative">
            <input type="text" id="buscarMaquina" placeholder="Clique para listar ou digite para filtrar..."
                   autocomplete="off" onfocus="abrirDropdown()" oninput="filtrarMaquinas()"
                   style="flex:1;border:1px solid #ccc;border-radius:6px;padding:7px 10px;font-size:13px">
            <button class="btn btn-sm btn-outline-secondary" onclick="carregarItens()" title="Atualizar">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <div id="acDropdown" style="display:none;position:absolute;top:38px;left:0;right:40px;background:#fff;border:1px solid #ccc;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:200;max-height:260px;overflow-y:auto"></div>
        </div>
        <div id="maqSelecionada" style="display:none;margin-top:8px;font-size:12px;color:#1565c0;font-weight:600"></div>
    </div>

    <div class="cc-card" id="cardItens" style="display:none">
        <div class="cc-title">Itens do Checklist</div>

        <div class="cc-add">
            <input type="text" id="novoItem" placeholder="Descrição do item de verificação..." maxlength="500"
                   onkeydown="if(event.key==='Enter') adicionarItem()">
            <button class="btn btn-sm btn-primary" onclick="adicionarItem()">
                <i class="bi bi-plus-lg"></i> Adicionar
            </button>
        </div>

        <table class="cc-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descrição</th>
                    <th style="width:60px;text-align:center">Ação</th>
                </tr>
            </thead>
            <tbody id="tbItens">
                <tr><td colspan="3" class="cc-empty">Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="cc-app-data"
     data-base="<?= htmlspecialchars($base) ?>"
     data-empr="<?= (int) ($_SESSION['empresa']['id'] ?? 0) ?>"
     style="display:none"></div>
<script src="<?= $base ?>src/js/manutencao-chklist-config.js?v=<?= time() ?>"></script>
<?= $render('footer') ?>
