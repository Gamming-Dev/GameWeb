<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MinerCore - Virtual Crypto Mining Simulator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
    /* FUNDO CAVERNA PROFUNDO */
    --core-black: #1b0f14;
    --core-dark: #24161d;

    /* CRISTAL CIANO (principal da UI) */
    --core-blue: #2de2e6;
    --core-blue-glow: rgba(45, 226, 230, 0.35);

    /* ÂMBAR DO MASCOTE */
    --core-orange: #ff9f1c;

    /* VERDE CRISTAL (boost/positivo) */
    --core-green: #3cffb3;

    /* TEXPOGRAFIA */
    --core-text: #f5e6d3;
    --core-text-dim: #bfae9c;
}


        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--core-black);
            color: var(--core-text);
            overflow-x: hidden;
            line-height: 1.6;
        }

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

        footer {
            padding: 50px;
            text-align: center;
            border-top: 1px solid rgba(0, 212, 255, 0.1);
            color: var(--core-text-dim);
        }

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
            <a href="#features">How It Works</a>
            <a href="#rewards">Rewards</a>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <div class="badge">⚡ NEW: Real Mining Coming Soon</div>
            <h1>Mine Virtual.<br>Earn Real.</h1>
            <p class="hero-subtitle">
                The top cryptocurrency mining simulator.
                Build your mining farm, play to multiply your earnings, and withdraw real cryptocurrency.
            </p>
            
            <div style="margin-bottom: 40px;">
                <a href="register.php" class="btn btn-primary btn-large">
                    Create Free Account →
                </a>
            </div>

            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-value" id="total-users">2,847</span>
                    <span class="stat-label">Active Miners</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="total-hashrate">45.2</span>
                    <span class="stat-label">PH/s Network</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="total-paid">12.5</span>
                    <span class="stat-label">BTC Paid Out</span>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <h2 class="section-title">Why MinerCore?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3 class="feature-title">Games That Pay</h3>
                <p class="feature-desc">
                    Play fun mini-games to earn temporary hashrate boosts. 
                    The better you play, the faster you mine.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⛏️</div>
                <h3 class="feature-title">Unique Miners</h3>
                <p class="feature-desc">
                    Buy, sell and collect rare miners. Each machine has unique 
                    efficiency and processing power characteristics.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3 class="feature-title">Real Withdrawals</h3>
                <p class="feature-desc">
                    Convert your virtual earnings to Bitcoin, Ethereum or Dogecoin. 
                    Withdraw directly to your wallet with minimal fees.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🤝</div>
                <h3 class="feature-title">Referral Program</h3>
                <p class="feature-desc">
                    Invite friends and earn commission on 3 levels. 
                    10% direct, 5% indirect and 2.5% third level.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3 class="feature-title">10-Minute Blocks</h3>
                <p class="feature-desc">
                    Fair reward system: every 10 minutes a new block is distributed 
                    proportionally among all online miners.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3 class="feature-title">Safe & Transparent</h3>
                <p class="feature-desc">
                    Open source code, verifiable blockchain transactions and anti-cheat 
                    protection in games. Fair play guaranteed.
                </p>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-box">
            <h2>Ready to start?</h2>
            <p>Join thousands of miners around the world. Create your free account in 30 seconds.</p>
            <a href="register.php" class="btn btn-primary btn-large">
                Create Free Account
            </a>
        </div>
    </section>

    <footer>
        <p>&copy; 2026 MinerCore. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">
            Mining simulator for entertainment purposes. Cryptocurrencies involve risks.
        </p>
    </footer>

    <script>
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