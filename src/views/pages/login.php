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
		from { opacity: 0; transform: translateY(30px); }
		to { opacity: 1; transform: translateY(0); }
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
		margin-bottom: 20px;
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

	.form-group input,
	.form-group select {
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

	.form-group input:focus,
	.form-group select:focus {
		border-color: #0066cc;
		background: #f0f7ff;
		box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
	}

	.form-group input::placeholder {
		color: #a0aec0;
	}

	.form-group select {
		cursor: pointer;
		appearance: none;
		-webkit-appearance: none;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
		background-repeat: no-repeat;
		background-position: right 16px center;
		padding-right: 40px;
	}

	.form-group .select-loading {
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

	/* Responsividade */
	@media (max-width: 768px) {
		body { padding: 16px; }
		.login-container { padding: 36px 28px; border-radius: 16px; }
		.logo-container img { width: 160px; }
		.login-title { font-size: 1.5rem; }
		.login-subtitle { font-size: 0.9rem; }
		.form-group { margin-bottom: 16px; }
		.btn-login { padding: 14px; font-size: 1rem; }
	}

	@media (max-width: 480px) {
		.login-container { padding: 28px 20px; }
		.logo-container img { width: 140px; }
		.login-title { font-size: 1.35rem; }
		.form-group label { font-size: 0.85rem; }
		.form-group input, .form-group select { padding: 12px 14px; font-size: 0.95rem; }
	}

	@media (max-width: 360px) {
		body { padding: 12px; }
		.login-container { padding: 24px 16px; }
		.logo-container { margin-bottom: 24px; }
	}

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
		<p class="login-subtitle">Informe suas credenciais para continuar</p>
		
		<div class="login-error" id="loginError"></div>

		<div class="form-group">
			<label for="empresaSelect">Filial</label>
			<select id="empresaSelect" name="empresa" required>
				<option value="">Carregando filiais...</option>
			</select>
		</div>
		
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
</div>

<script>
	// Carregar empresas ao abrir a página
	document.addEventListener('DOMContentLoaded', carregarEmpresas);

	async function carregarEmpresas() {
		const select = document.getElementById('empresaSelect');
		try {
			const response = await fetch('/comissao-api-empresas');
			const text = await response.text();
			let result;
			try {
				result = JSON.parse(text);
			} catch (e) {
				select.innerHTML = '<option value="">Erro ao carregar filiais</option>';
				return;
			}
			
			select.innerHTML = '<option value="">Selecione a filial</option>';
			
			if (result.success && result.data) {
				result.data.forEach(empresa => {
					const option = document.createElement('option');
					option.value = empresa.ID;
					option.textContent = empresa.CODIGO + ' - ' + (empresa.NOME_FANTASIA || empresa.RAZAO_SOCIAL);
					select.appendChild(option);
				});
			} else {
				select.innerHTML = '<option value="">Erro ao carregar filiais</option>';
			}
		} catch (err) {
			select.innerHTML = '<option value="">Erro de conexão</option>';
		}
	}

	// Submit do formulário - login + selecionar empresa em sequência
	document.getElementById('formLogin').addEventListener('submit', async function(e) {
		e.preventDefault();
		
		const usuario = document.getElementById('usuario').value.trim();
		const senha = document.getElementById('senha').value;
		const emprId = document.getElementById('empresaSelect').value;
		const errorDiv = document.getElementById('loginError');
		const submitBtn = this.querySelector('button[type="submit"]');
		
		errorDiv.style.display = 'none';
		errorDiv.textContent = '';

		if (!emprId) {
			errorDiv.textContent = 'Selecione uma filial para continuar.';
			errorDiv.style.display = 'block';
			return;
		}
		
		submitBtn.classList.add('loading');
		submitBtn.disabled = true;
		
		try {
			// 1. Fazer login
			const loginResponse = await fetch('/login', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ usuario, senha })
			});
			
			const loginResult = await loginResponse.json();
			
			if (!loginResult.success) {
				errorDiv.textContent = loginResult.error || 'Usuário ou senha inválidos.';
				errorDiv.style.display = 'block';
				submitBtn.classList.remove('loading');
				submitBtn.disabled = false;
				document.getElementById('senha').focus();
				document.getElementById('senha').select();
				return;
			}

			// 2. Selecionar empresa
			const empresaResponse = await fetch('/comissao-api-selecionar-empresa', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ empr_id: emprId })
			});
			
			const empresaResult = await empresaResponse.json();
			
			if (empresaResult.success) {
				window.location.href = '/';
			} else {
				errorDiv.textContent = empresaResult.error || 'Erro ao selecionar filial.';
				errorDiv.style.display = 'block';
				submitBtn.classList.remove('loading');
				submitBtn.disabled = false;
			}
		} catch (err) {
			errorDiv.textContent = 'Erro ao conectar ao servidor. Verifique sua conexão.';
			errorDiv.style.display = 'block';
			submitBtn.classList.remove('loading');
			submitBtn.disabled = false;
		}
	});
	
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