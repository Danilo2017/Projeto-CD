<?= $render('header') ?>

<style>
	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
	}

	body {
		background: linear-gradient(135deg, #0066cc 0%, #003d7a 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100vh;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		padding: 20px;
		overflow-x: hidden;
	}

	.login-wrapper {
		width: 100%;
		max-width: 440px;
		animation: fadeInUp 0.6s ease-out;
	}

	@keyframes fadeInUp {
		from {
			opacity: 0;
			transform: translateY(30px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.login-container {
		background: #ffffff;
		padding: 48px 40px;
		border-radius: 20px;
		box-shadow: 0 20px 60px rgba(0, 61, 122, 0.3), 0 0 0 1px rgba(0, 102, 204, 0.1);
		width: 100%;
		display: flex;
		flex-direction: column;
		align-items: center;
		position: relative;
		backdrop-filter: blur(10px);
		border-top: 4px solid #0066cc;
	}

	.logo-container {
		width: 100%;
		display: flex;
		justify-content: center;
		align-items: center;
		margin-bottom: 32px;
		padding-bottom: 24px;
		border-bottom: 2px solid #e8f2ff;
	}

	.logo-container img {
		width: 200px;
		max-width: 100%;
		height: auto;
		object-fit: contain;
		transition: transform 0.3s ease;
	}

	.logo-container img:hover {
		transform: scale(1.05);
	}

	.login-title {
		color: #004080;
		font-size: 1.75rem;
		font-weight: 700;
		margin-bottom: 8px;
		text-align: center;
	}

	.login-subtitle {
		color: #718096;
		font-size: 0.95rem;
		margin-bottom: 32px;
		text-align: center;
	}

	.form-group {
		width: 100%;
		margin-bottom: 24px;
		position: relative;
	}

	.form-group label {
		display: block;
		font-weight: 600;
		color: #004080;
		margin-bottom: 8px;
		font-size: 0.9rem;
		letter-spacing: 0.3px;
	}

	.input-wrapper {
		position: relative;
		width: 100%;
	}

	.form-group input {
		width: 100%;
		padding: 14px 16px;
		border: 2px solid #e2e8f0;
		border-radius: 10px;
		font-size: 1rem;
		color: #2d3748;
		background: #f7fafc;
		transition: all 0.3s ease;
		outline: none;
	}

	.form-group input:focus {
		border-color: #0066cc;
		background: #f0f7ff;
		box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
	}

	.form-group input::placeholder {
		color: #a0aec0;
	}

	.btn-login {
		width: 100%;
		padding: 16px;
		background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
		color: #ffffff;
		border: none;
		border-radius: 10px;
		font-size: 1.05rem;
		font-weight: 700;
		cursor: pointer;
		transition: all 0.3s ease;
		box-shadow: 0 4px 15px rgba(0, 64, 128, 0.4);
		letter-spacing: 0.5px;
		text-transform: uppercase;
		margin-top: 8px;
	}

	.btn-login:hover {
		box-shadow: 0 6px 20px rgba(0, 102, 204, 0.6);
	}

	.btn-login:active {
		opacity: 0.9;
	}

	.btn-login:disabled {
		background: #cbd5e0;
		cursor: not-allowed;
		box-shadow: none;
		transform: none;
	}

	.login-error {
		width: 100%;
		background: #fed7d7;
		color: #c53030;
		padding: 12px 16px;
		border-radius: 8px;
		margin-bottom: 20px;
		font-size: 0.9rem;
		display: none;
		border-left: 4px solid #c53030;
		animation: shake 0.4s ease;
	}

	@keyframes shake {
		0%, 100% { transform: translateX(0); }
		25% { transform: translateX(-10px); }
		75% { transform: translateX(10px); }
	}

	/* Responsividade */
	@media (max-width: 768px) {
		body {
			padding: 16px;
		}

		.login-container {
			padding: 36px 28px;
			border-radius: 16px;
		}

		.logo-container img {
			width: 160px;
		}

		.login-title {
			font-size: 1.5rem;
		}

		.login-subtitle {
			font-size: 0.9rem;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.btn-login {
			padding: 14px;
			font-size: 1rem;
		}
	}

	@media (max-width: 480px) {
		.login-container {
			padding: 28px 20px;
		}

		.logo-container img {
			width: 140px;
		}

		.login-title {
			font-size: 1.35rem;
		}

		.form-group label {
			font-size: 0.85rem;
		}

		.form-group input {
			padding: 12px 14px;
			font-size: 0.95rem;
		}
	}

	@media (max-width: 360px) {
		body {
			padding: 12px;
		}

		.login-container {
			padding: 24px 16px;
		}

		.logo-container {
			margin-bottom: 24px;
		}
	}

	/* Loading state */
	.btn-login.loading {
		position: relative;
		color: transparent;
	}

	.btn-login.loading::after {
		content: '';
		position: absolute;
		width: 20px;
		height: 20px;
		top: 50%;
		left: 50%;
		margin-left: -10px;
		margin-top: -10px;
		border: 3px solid #ffffff;
		border-radius: 50%;
		border-top-color: transparent;
		animation: spin 0.8s linear infinite;
	}

	@keyframes spin {
		to { transform: rotate(360deg); }
	}

	/* Acessibilidade */
	@media (prefers-reduced-motion: reduce) {
		* {
			animation-duration: 0.01ms !important;
			animation-iteration-count: 1 !important;
			transition-duration: 0.01ms !important;
		}
	}
</style>

<div class="login-wrapper">
	<form class="login-container" id="formLogin">
		<div class="logo-container">
			<img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png" alt="Logo Gazin">
		</div>
		
		<h2 class="login-title">Bem-vindo</h2>
		<p class="login-subtitle">Faça login para continuar</p>
		
		<div class="login-error" id="loginError"></div>
		
		<div class="form-group">
			<label for="usuario">Usuário</label>
			<div class="input-wrapper">
				<input 
					type="text" 
					id="usuario" 
					name="usuario" 
					required 
					autofocus 
					autocomplete="username"
					placeholder="Digite seu usuário">
			</div>
		</div>
		
		<div class="form-group">
			<label for="senha">Senha</label>
			<div class="input-wrapper">
				<input 
					type="password" 
					id="senha" 
					name="senha" 
					required 
					autocomplete="current-password"
					placeholder="Digite sua senha">
			</div>
		</div>
		
		<button type="submit" class="btn-login">Entrar</button>
	</form>

	<!-- Modal de Seleção de Empresa -->
	<div class="empresa-modal" id="empresaModal" style="display: none;">
		<div class="empresa-modal-content">
			<div class="logo-container">
				<img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png" alt="Logo Gazin">
			</div>
			<h2 class="login-title">Selecione a Empresa</h2>
			<p class="login-subtitle">Escolha a empresa para trabalhar</p>
			
			<div class="form-group">
				<label for="empresaSelect">Empresa</label>
				<select id="empresaSelect" class="form-select" required>
					<option value="">Carregando empresas...</option>
				</select>
			</div>
			
			<button type="button" class="btn-login" id="btnSelecionarEmpresa">
				Continuar
			</button>
		</div>
	</div>
</div>

<style>
	/* Estilos do modal de empresa */
	.empresa-modal {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: linear-gradient(135deg, #0066cc 0%, #003d7a 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 1000;
		animation: fadeIn 0.3s ease;
	}
	
	@keyframes fadeIn {
		from { opacity: 0; }
		to { opacity: 1; }
	}
	
	.empresa-modal-content {
		background: #ffffff;
		padding: 48px 40px;
		border-radius: 20px;
		box-shadow: 0 20px 60px rgba(0, 61, 122, 0.3);
		width: 100%;
		max-width: 440px;
		display: flex;
		flex-direction: column;
		align-items: center;
		border-top: 4px solid #0066cc;
		animation: fadeInUp 0.6s ease-out;
	}
	
	.empresa-modal-content .logo-container {
		margin-bottom: 24px;
		padding-bottom: 16px;
		border-bottom: 2px solid #e8f2ff;
	}
	
	.form-select {
		width: 100%;
		padding: 14px 16px;
		border: 2px solid #e2e8f0;
		border-radius: 10px;
		font-size: 1rem;
		color: #2d3748;
		background: #f7fafc;
		transition: all 0.3s ease;
		outline: none;
		cursor: pointer;
	}
	
	.form-select:focus {
		border-color: #0066cc;
		background: #f0f7ff;
		box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
	}
</style>
<script>
	document.getElementById('formLogin').addEventListener('submit', async function(e) {
		e.preventDefault();
		
		const usuario = document.getElementById('usuario').value.trim();
		const senha = document.getElementById('senha').value;
		const errorDiv = document.getElementById('loginError');
		const submitBtn = this.querySelector('button[type="submit"]');
		
		// Limpar erros anteriores
		errorDiv.style.display = 'none';
		errorDiv.textContent = '';
		
		// Adicionar estado de loading
		submitBtn.classList.add('loading');
		submitBtn.disabled = true;
		
		try {
			const response = await fetch('/login', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ usuario, senha })
			});
			
			const result = await response.json();
			
			if (result.success) {
				// Armazenar permissões do usuário
				window.userPermissions = result.user || {};
				
				// Login bem-sucedido - mostrar modal de empresa
				await carregarEmpresas();
				document.getElementById('empresaModal').style.display = 'flex';
				document.getElementById('formLogin').parentElement.querySelector('.login-container').style.display = 'none';
			} else {
				// Mostrar erro
				errorDiv.textContent = result.error || 'Usuário ou senha inválidos.';
				errorDiv.style.display = 'block';
				
				// Remover estado de loading
				submitBtn.classList.remove('loading');
				submitBtn.disabled = false;
				
				// Focar no campo de senha para nova tentativa
				document.getElementById('senha').focus();
				document.getElementById('senha').select();
			}
		} catch (err) {
			// Erro de conexão
			errorDiv.textContent = 'Erro ao conectar ao servidor. Verifique sua conexão.';
			errorDiv.style.display = 'block';
			
			// Remover estado de loading
			submitBtn.classList.remove('loading');
			submitBtn.disabled = false;
		}
	});
	
	// Carregar empresas para o select
	async function carregarEmpresas() {
		try {
			console.log('Carregando empresas...');
			const response = await fetch('/comissao-api-empresas');
			console.log('Response status:', response.status);
			
			const text = await response.text();
			console.log('Response text:', text);
			
			let result;
			try {
				result = JSON.parse(text);
			} catch (parseErr) {
				console.error('Erro ao parsear JSON:', parseErr);
				console.error('Texto recebido:', text);
				return;
			}
			
			const select = document.getElementById('empresaSelect');
			select.innerHTML = '<option value="">Selecione uma empresa</option>';
			
			if (result.success && result.data) {
				console.log('Empresas encontradas:', result.data.length);
				result.data.forEach(empresa => {
					const option = document.createElement('option');
					option.value = empresa.ID;
					option.textContent = `${empresa.CODIGO} - ${empresa.NOME_FANTASIA || empresa.RAZAO_SOCIAL}`;
					select.appendChild(option);
				});
			} else {
				console.error('Erro na resposta:', result.error || 'Sem dados');
				select.innerHTML = '<option value="">Erro ao carregar empresas</option>';
			}
		} catch (err) {
			console.error('Erro ao carregar empresas:', err);
			document.getElementById('empresaSelect').innerHTML = '<option value="">Erro de conexão</option>';
		}
	}
	
	// Selecionar empresa e continuar
	document.getElementById('btnSelecionarEmpresa').addEventListener('click', async function() {
		const select = document.getElementById('empresaSelect');
		const emprId = select.value;
		
		if (!emprId) {
			alert('Selecione uma empresa para continuar');
			return;
		}
		
		this.classList.add('loading');
		this.disabled = true;
		
		try {
			const response = await fetch('/comissao-api-selecionar-empresa', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ empr_id: emprId })
			});
			
			const result = await response.json();
			
			if (result.success) {
				// Redirecionar baseado nas permissões do usuário
				const redirectUrl = getRedirectUrl();
				window.location.href = redirectUrl;
			} else {
				alert(result.error || 'Erro ao selecionar empresa');
				this.classList.remove('loading');
				this.disabled = false;
			}
		} catch (err) {
			alert('Erro ao conectar ao servidor');
			this.classList.remove('loading');
			this.disabled = false;
		}
	});
	
	// Determinar URL de redirecionamento baseado nas permissões
	function getRedirectUrl() {
		// Verificar permissões retornadas do login (armazenadas em variável global)
		if (window.userPermissions) {
			// Novo sistema de permissões
			if (window.userPermissions.is_admin === true) {
				return '/permissao';
			}
			
			// Verificar rotas permitidas
			const rotas = window.userPermissions.rotas_permitidas || [];
			if (rotas.includes('*')) {
				return '/permissao';
			} else if (rotas.includes('comissao')) {
				return '/comissao-relatorio';
			} else if (rotas.includes('cd')) {
				return '/cd-dashboard';
			} else if (rotas.includes('permissao')) {
				return '/permissao';
			}
		}
		// Fallback - tenta página de relatórios
		return '/comissao-relatorio';
	}
	
	// Limpar erro ao digitar
	['usuario', 'senha'].forEach(fieldId => {
		document.getElementById(fieldId).addEventListener('input', function() {
			const errorDiv = document.getElementById('loginError');
			if (errorDiv.style.display === 'block') {
				errorDiv.style.display = 'none';
			}
		});
	});
</script>

<?= $render('footer') ?>