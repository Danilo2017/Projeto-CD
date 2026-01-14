<?= $render('header') ?>

<style>
	body {
		background: #f5f7fa;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 100vh;
	}
	.login-container {
		background: #fff;
		padding: 32px 28px 24px 28px;
		border-radius: 10px;
		box-shadow: 0 2px 16px rgba(0,0,0,0.08);
		width: 100%;
		max-width: 370px;
		display: flex;
		flex-direction: column;
		align-items: center;
		margin: 40px auto;
	}
	.login-container h2 {
		color: #004080;
		margin-bottom: 18px;
		font-size: 1.5rem;
	}
	.login-container label {
		font-weight: 600;
		color: #333;
		margin-bottom: 4px;
	}
	.login-container input {
		width: 100%;
		padding: 10px;
		margin-bottom: 16px;
		border: 1px solid #ccc;
		border-radius: 6px;
		font-size: 1rem;
	}
	.login-container button {
		width: 100%;
		padding: 10px;
		background: #004080;
		color: #fff;
		border: none;
		border-radius: 6px;
		font-size: 1.1rem;
		font-weight: 600;
		cursor: pointer;
		transition: background 0.2s;
	}
	.login-container button:hover {
		background: #0059b3;
	}
	.login-error {
		color: #e74c3c;
		margin-bottom: 10px;
		font-size: 0.98rem;
		display: none;
	}
</style>

<form class="login-container" id="formLogin">
	<img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png" alt="Logo Gazin" style="width: 180px; margin-bottom: 20px; margin-right: 100px;">
	<div class="login-error" id="loginError"></div>
	<label for="usuario">Usuário</label>
	<input type="text" id="usuario" name="usuario" required autofocus autocomplete="username">
	<label for="senha">Senha</label>
	<input type="password" id="senha" name="senha" required autocomplete="current-password">
	<button type="submit">Entrar</button>
</form>
<script>
	document.getElementById('formLogin').addEventListener('submit', async function(e) {
		e.preventDefault();
		const usuario = document.getElementById('usuario').value.trim();
		const senha = document.getElementById('senha').value;
		const errorDiv = document.getElementById('loginError');
		errorDiv.style.display = 'none';
		errorDiv.textContent = '';
		try {
			const response = await fetch('/login', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ usuario, senha })
			});
			const result = await response.json();
			if (result.success) {
				window.location.href = '/cd-dashboard';
			} else {
				errorDiv.textContent = result.error || 'Usuário ou senha inválidos.';
				errorDiv.style.display = 'block';
			}
		} catch (err) {
			errorDiv.textContent = 'Erro ao conectar ao servidor.';
			errorDiv.style.display = 'block';
		}
	});
</script>

<?= $render('footer') ?>