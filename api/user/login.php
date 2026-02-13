<?php
// MinerCore - Landing Page
// public_html/index.php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MinerCore - Simulador de Mineração Virtual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --core-black: #0a0a0f;
            --core-dark: #12121a;
            --core-blue: #00d4ff;
            --core-blue-glow: rgba(0, 212, 255, 0.3);
            --core-orange: #ff6b35;
            --core-green: #00ff88;
            --core-text: #e0e0e0;
            --core-text-dim: #8892b0;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Background animado com grid */
        .bg-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: -1;
        }

        .bg-glow {
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--core-blue-glow) 0%, transparent 70%);
            top: -300px;
            right: -300px;
            pointer-events: none;
            z-index: -1;
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 20px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(10, 10, 15, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--core-blue), var(--core-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 0 20px var(--core-blue-glow);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--core-blue), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        nav a {
            color: var(--core-text-dim);
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
            position: relative;
        }

        nav a:hover {
            color: var(--core-blue);
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--core-blue);
            transition: width 0.3s;
        }

        nav a:hover::after {
            width: 100%;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--core-blue), #0099cc);
            color: var(--core-black);
            box-shadow: 0 4px 15px var(--core-blue-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px var(--core-blue-glow);
        }

        .btn-outline {
            background: transparent;
            color: var(--core-blue);
            border: 2px solid var(--core-blue);
        }

        .btn-outline:hover {
            background: var(--core-blue);
            color: var(--core-black);
        }

        .btn-large {
            padding: 18px 50px;
            font-size: 20px;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 150px 50px 100px;
            position: relative;
        }

        .hero-content {
            max-width: 800px;
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid var(--core-blue);
            border-radius: 20px;
            color: var(--core-blue);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 30px;
            animation: glow 2s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 5px var(--core-blue-glow); }
            50% { box-shadow: 0 0 20px var(--core-blue-glow); }
        }

        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 72px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #fff 0%, var(--core-blue) 50%, var(--core-orange) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 24px;
            color: var(--core-text-dim);
            margin-bottom: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 60px;
            padding: 30px;
            background: rgba(18, 18, 26, 0.8);
            border-radius: 20px;
            border: 1px solid rgba(0, 212, 255, 0.2);
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--core-blue);
            display: block;
        }

        .stat-label {
            font-size: 14px;
            color: var(--core-text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Features Section */
        .features {
            padding: 100px 50px;
            background: var(--core-dark);
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 48px;
            text-align: center;
            margin-bottom: 60px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: rgba(10, 10, 15, 0.8);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--core-blue), transparent);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--core-blue);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.1);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--core-blue), transparent);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }

        .feature-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .feature-desc {
            color: var(--core-text-dim);
            line-height: 1.8;
        }

        /* CTA Section */
        .cta {
            padding: 150px 50px;
            text-align: center;
            position: relative;
        }

        .cta-box {
            max-width: 800px;
            margin: 0 auto;
            padding: 60px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(255, 107, 53, 0.1));
            border-radius: 30px;
            border: 1px solid rgba(0, 212, 255, 0.3);
        }

        .cta h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 48px;
            margin-bottom: 20px;
        }

        .cta p {
            font-size: 20px;
            color: var(--core-text-dim);
            margin-bottom: 40px;
        }

        /* Footer */
        footer {
            padding: 50px;
            text-align: center;
            border-top: 1px solid rgba(0, 212, 255, 0.1);
            color: var(--core-text-dim);
        }

        /* Mobile */
        @media (max-width: 768px) {
            h1 { font-size: 42px; }
            header { padding: 15px 20px; }
            nav { display: none; }
            .hero { padding: 120px 20px 60px; }
            .hero-stats { flex-direction: column; gap: 30px; }
            .features-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>

    <header>
        <a href="index.php" class="logo">
            <div class="logo-icon">⛏️</div>
            <span class="logo-text">MINERCORE</span>
        </a>
        <nav>
            <a href="#features">Como Funciona</a>
            <a href="#rewards">Recompensas</a>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Entrar</a>
                <a href="register.php" class="btn btn-primary">Começar Agora</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <div class="badge">⚡ NOVO: Mineração Real em Breve</div>
            <h1>Mine Virtual.<br>Ganhe Real.</h1>
            <p class="hero-subtitle">
                O simulador de mineração mais avançado do Brasil. 
                Monte sua fazenda de miners, jogue para multiplicar seus ganhos e saque em criptomoedas reais.
            </p>
            
            <div style="margin-bottom: 40px;">
                <a href="register.php" class="btn btn-primary btn-large">
                    Criar Conta Grátis →
                </a>
            </div>

            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-value" id="total-users">2,847</span>
                    <span class="stat-label">Miners Ativos</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="total-hashrate">45.2</span>
                    <span class="stat-label">PH/s Network</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="total-paid">12.5</span>
                    <span class="stat-label">BTC Pagos</span>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <h2 class="section-title">Por que MinerCore?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3 class="feature-title">Games que Pagam</h3>
                <p class="feature-desc">
                    Jogue mini-games divertidos para ganhar boosts de hashrate temporários. 
                    Quanto melhor você joga, mais rápido minera.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⛏️</div>
                <h3 class="feature-title">Miners Únicos</h3>
                <p class="feature-desc">
                    Compre, venda e colecione miners raros. Cada máquina tem características únicas 
                    de eficiência e poder de processamento.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3 class="feature-title">Saques Reais</h3>
                <p class="feature-desc">
                    Converta seus ganhos virtuais em Bitcoin, Ethereum ou Dogecoin. 
                    Saque direto para sua carteira com taxas mínimas.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🤝</div>
                <h3 class="feature-title">Referências</h3>
                <p class="feature-desc">
                    Indique amigos e ganhe comissão em 3 níveis. 
                    10% do direto, 5% do indireto e 2.5% do terceiro nível.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3 class="feature-title">Blocos a Cada 10min</h3>
                <p class="feature-desc">
                    Sistema de recompensa justo: a cada 10 minutos um novo bloco é distribuído 
                    proporcionalmente entre todos os miners online.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3 class="feature-title">Seguro & Transparente</h3>
                <p class="feature-desc">
                    Código aberto, transações verificáveis na blockchain e proteção anti-cheat 
                    nos jogos. Fair play garantido.
                </p>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-box">
            <h2>Pronto para começar?</h2>
            <p>Junte-se a milhares de miners brasileiros. Crie sua conta gratuita em 30 segundos.</p>
            <a href="register.php" class="btn btn-primary btn-large">
                Criar Conta Grátis
            </a>
        </div>
    </section>

    <footer>
        <p>&copy; 2024 MinerCore. Todos os direitos reservados.</p>
        <p style="margin-top: 10px; font-size: 14px;">
            Simulador de mineração para fins de entretenimento. Criptomoedas envolvem riscos.
        </p>
    </footer>

    <script>
        // Animação dos números (contador)
        function animateValue(id, start, end, duration) {
            const obj = document.getElementById(id);
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                obj.innerHTML = value.toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animação suave ao scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>