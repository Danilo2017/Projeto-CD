<?php
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
if (!$acessoFaturamento) {
    header('Location: ' . $base . 'sem-acesso');
    exit;
}
?>
<?= $render('header', [
    'pageTitle' => 'Gestão de Metas - Faturamento Indústrias',
    'showNavbar' => true,
    'pageActive' => 'meta-empresa',
    'customCSS' => ['src/css/meta-empresa.css'],
    'bodyStyle' => 'margin: 0; padding: 0;'
]) ?>

<div class="meta-container">
    <!-- Filtros e Ações -->
    <div class="meta-toolbar">
        <div class="meta-filters">
            <div class="filter-group">
                <label for="filtro-mes">Mês/Ano:</label>
                <input type="month" id="filtro-mes" class="form-input">
            </div>
            <button type="button" id="btn-filtrar" class="btn-primary">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <button type="button" id="btn-limpar" class="btn-secondary">
                <i class="bi bi-eraser"></i> Limpar
            </button>
        </div>
        <div class="meta-actions">
            <button type="button" id="btn-nova-meta" class="btn-success">
                <i class="bi bi-plus-lg"></i> Nova Meta
            </button>
        </div>
    </div>

    <!-- Tabela de Metas -->
    <div class="meta-table-container">
        <table class="meta-table" id="tabela-metas">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Mês/Ano</th>
                    <th>Meta Faturamento</th>
                    <th>Meta Estoque</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tbody-metas">
                <tr>
                    <td colspan="5" class="loading-cell">Carregando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Cadastro/Edição -->
<div class="modal-overlay" id="modal-meta" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-titulo">Nova Meta</h2>
            <button type="button" class="modal-close" id="btn-fechar-modal">&times;</button>
        </div>
        <form id="form-meta">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="empr_id">Empresa *</label>
                        <select id="empr_id" name="empr_id" class="form-select" required>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mes_ano">Mês/Ano *</label>
                        <input type="month" id="mes_ano" name="mes_ano" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="meta">Meta Faturamento (R$) *</label>
                        <input type="text" id="meta" name="meta" class="form-input money-input" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label for="meta_estoque">Meta Estoque (R$) *</label>
                        <input type="text" id="meta_estoque" name="meta_estoque" class="form-input money-input" required placeholder="0,00">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btn-cancelar">Cancelar</button>
                <button type="submit" class="btn-success" id="btn-salvar">
                    <i class="bi bi-floppy"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal-overlay" id="modal-excluir" style="display: none;">
    <div class="modal-content modal-small">
        <div class="modal-header modal-header-danger">
            <h2>Confirmar Exclusão</h2>
            <button type="button" class="modal-close" id="btn-fechar-modal-excluir">&times;</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir esta meta?</p>
            <p class="meta-info-excluir">
                <strong>Empresa:</strong> <span id="excluir-empresa">-</span><br>
                <strong>Mês/Ano:</strong> <span id="excluir-mes">-</span>
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="btn-cancelar-excluir">Cancelar</button>
            <button type="button" class="btn-danger" id="btn-confirmar-excluir">
                <i class="bi bi-trash3"></i> Excluir
            </button>
        </div>
    </div>
</div>

<script src="src/js/meta-empresa.js?v=<?= time() ?>"></script>

<?= $render('footer') ?>
