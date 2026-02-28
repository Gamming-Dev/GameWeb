# 🎮 MinerCore - Virtual Crypto Mining Simulator

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-active-success.svg)](https://github.com/Gamming-Dev/GameWeb)

## 📋 Sobre o Projeto

**MinerCore** é um simulador de mineração de criptomoedas virtual baseado em web, desenvolvido em PHP. A plataforma oferece uma experiência gamificada de mineração de criptomoedas, permitindo que usuários gerenciem miners virtuais, participem de blocos de mineração e acumulem recompensas.

### ✨ Características Principais

- 🔐 **Sistema de Autenticação Completo**: Registro, login e gerenciamento de sessões
- ⚡ **Sistema de Mineração em Tempo Real**: Participação em blocos com distribuição proporcional de recompensas
- 🎰 **Sistema de Miners**: Gerenciamento de equipamentos com diferentes raridades e eficiências
- 💰 **Carteira Virtual**: Sistema de saldo com pending e available balance
- 🔗 **Programa de Referência**: Sistema de indicação com códigos únicos
- 🎮 **Mini-Games**: Jogos integrados para ganhar bônus
- 📊 **Dashboard Interativo**: Painel com estatísticas e gerenciamento em tempo real
- 🛒 **Marketplace**: Compra e venda de miners
- 🌐 **Integração Web3**: Conexão com carteiras de criptomoedas

## 🏗️ Arquitetura do Projeto

```
GameWeb/
├── 📁 api/                    # API endpoints REST
│   ├── 📁 cron/              # Scripts de automação
│   ├── 📁 games/             # Endpoints de mini-games
│   ├── 📁 market/            # API do marketplace
│   ├── 📁 mining/            # Lógica de mineração
│   └── 📁 user/              # Gerenciamento de usuários
├── 📁 assets/                 # Assets estáticos (CSS, JS)
├── 📁 includes/               # Bibliotecas e configurações PHP
│   ├── 📁 classes/           # Classes PHP (OOP)
│   ├── auth.php              # Autenticação
│   ├── config.php            # Configurações globais
│   ├── db.php                # Conexão com banco de dados
│   └── functions.php         # Funções auxiliares
├── 📁 images/                 # Imagens e ícones
├── 📁 install/                # Scripts de instalação
│   ├── database.sql          # Schema do banco de dados
│   └── mode.sql              # Dados iniciais
├── 📁 storage/                # Armazenamento de arquivos
├── 📁 templates/              # Templates HTML/PHP
├── 📄 index.php               # Página inicial
├── 📄 dashboard.php           # Painel principal
├── 📄 login.php               # Página de login
├── 📄 register.php            # Página de registro
├── 📄 profile.php             # Perfil do usuário
├── 📄 wallet.php              # Carteira virtual
├── 📄 connect_wallet.php      # Integração Web3
└── 📄 .htaccess               # Configurações Apache

```

## 🗄️ Estrutura do Banco de Dados

### Principais Tabelas

| Tabela | Descrição |
|--------|-----------|
| `users` | Informações dos usuários e saldos |
| `miner_types` | Catálogo de tipos de miners disponíveis |
| `user_miners` | Inventário de miners dos usuários |
| `blocks` | Blocos de mineração (pool) |
| `block_participants` | Participação dos usuários nos blocos |
| `mining_history` | Histórico de transações e recompensas |
| `active_boosts` | Power-ups ativos dos usuários |

### Diagrama de Relacionamento

```
users (1) ──< (N) user_miners ──> (1) miner_types
  │
  ├──< (N) block_participants ──> (1) blocks
  │
  ├──< (N) mining_history
  │
  └──< (N) active_boosts
```

## 🚀 Instalação e Configuração

### Pré-requisitos

- **PHP** 7.4 ou superior
- **MySQL/MariaDB** 5.7 ou superior
- **Apache** com mod_rewrite habilitado
- **Composer** (opcional, para dependências)

### Passo a Passo

#### 1️⃣ Clone o Repositório

```bash
git clone https://github.com/Gamming-Dev/GameWeb.git
cd GameWeb
```

#### 2️⃣ Configure o Banco de Dados

```bash
# Acesse o MySQL
mysql -u root -p

# Crie o banco de dados
CREATE DATABASE minercore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minercore;

# Execute os scripts de instalação
SOURCE install/database.sql;
SOURCE install/mode.sql;
```

#### 3️⃣ Configure as Variáveis de Ambiente

Edite o arquivo `includes/config.php`:

```php
<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'minercore');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');

// Configurações da Aplicação
define('BASE_URL', 'http://localhost/GameWeb');
define('APP_NAME', 'MinerCore');

// Configurações de Segurança
define('SESSION_LIFETIME', 86400); // 24 horas
define('HASH_ALGORITHM', 'sha256');
?>
```

#### 4️⃣ Configure Permissões

```bash
# Permissões para diretórios de escrita
chmod 755 storage/
chmod 644 includes/config.php
```

#### 5️⃣ Configure o Apache

Certifique-se de que o `mod_rewrite` está habilitado:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 6️⃣ Acesse a Aplicação

Navegue para: `http://localhost/GameWeb` ou seu domínio configurado.

## 🎯 Funcionalidades Detalhadas

### Sistema de Mineração

- **Pool Mining**: Os usuários contribuem com hashrate para um pool coletivo
- **Distribuição Proporcional**: Recompensas distribuídas baseadas no hashrate contribuído
- **Sistema de Blocos**: Blocos gerados periodicamente com recompensas específicas
- **Eficiência de Miners**: Cada miner possui eficiência e durabilidade únicas

### Sistema de Economia

- **Dual Balance System**:
  - `balance_pending`: Saldo em mineração (não disponível)
  - `balance_available`: Saldo disponível para uso
- **Conversão**: Conversão de saldo pendente para disponível
- **Taxas**: Sistema de taxas configurável para transações

### Sistema de Raridade

| Raridade | Multiplicador | Cor |
|----------|--------------|-----|
| Common | 1.0x | Cinza |
| Rare | 1.5x | Azul |
| Epic | 2.0x | Roxo |
| Legendary | 3.0x | Dourado |

### Sistema de Power-ups

- **Hash Boost**: Aumenta temporariamente o hashrate
- **Luck Boost**: Aumenta chances de recompensas extras
- **Energy Save**: Reduz consumo de energia dos miners

## 🔧 Configuração Avançada

### Cron Jobs

Configure cron jobs para automação:

```bash
# Edite o crontab
crontab -e

# Adicione as seguintes linhas:
# Processa blocos a cada 5 minutos
*/5 * * * * php /path/to/GameWeb/api/cron/process_blocks.php

# Atualiza estatísticas a cada hora
0 * * * * php /path/to/GameWeb/api/cron/update_stats.php
```

### Variáveis de Configuração

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `BLOCK_TIME` | Tempo de geração de bloco (segundos) | 300 |
| `BASE_REWARD` | Recompensa base por bloco | 1.0 |
| `MIN_HASHRATE` | Hashrate mínimo para participação | 10 |
| `CONVERSION_FEE` | Taxa de conversão (%) | 0.5 |

## 📊 API Endpoints

### Autenticação

```
POST /api/user/login.php          # Login
POST /api/user/register.php       # Registro
POST /api/user/logout.php         # Logout
```

### Mineração

```
GET  /api/mining/status.php       # Status de mineração
POST /api/mining/start.php        # Iniciar mineração
POST /api/mining/stop.php         # Parar mineração
GET  /api/mining/history.php      # Histórico
```

### Carteira

```
GET  /api/user/balance.php        # Saldo atual
POST /api/convert.php             # Converter saldo
POST /api/user/withdraw.php       # Sacar fundos
```

### Marketplace

```
GET  /api/market/miners.php       # Lista de miners
POST /api/market/buy.php          # Comprar miner
POST /api/market/sell.php         # Vender miner
```

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Fontes**: Google Fonts (Orbitron, Rajdhani)
- **Web Server**: Apache com .htaccess
- **Segurança**: Session management, prepared statements, password hashing

## 🎨 Design System

### Paleta de Cores

```css
--core-black: #1b0f14;      /* Fundo caverna profundo */
--core-dark: #24161d;        /* Fundo secundário */
--core-blue: #2de2e6;        /* Cristal ciano (UI principal) */
--core-orange: #ff9f1c;      /* Âmbar do mascote */
--core-green: #3cffb3;       /* Verde cristal (boost/positivo) */
--core-text: #f5e6d3;        /* Texto principal */
--core-text-dim: #bfae9c;    /* Texto secundário */
```

### Tipografia

- **Headings**: Orbitron (400, 700, 900)
- **Body**: Rajdhani (300, 500, 700)

## 🧪 Testes

```bash
# Execute testes unitários (se implementados)
vendor/bin/phpunit tests/

# Teste de carga da API
ab -n 1000 -c 10 http://localhost/GameWeb/api/mining/status.php
```

## 📝 Processo de Desenvolvimento

### Workflow Git

```bash
# 1. Crie uma branch para sua feature
git checkout -b feature/nova-funcionalidade

# 2. Faça suas alterações e commit
git add .
git commit -m "feat: adiciona nova funcionalidade X"

# 3. Push para o repositório
git push origin feature/nova-funcionalidade

# 4. Crie um Pull Request no GitHub
```

### Convenção de Commits

Seguimos o padrão [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` Nova funcionalidade
- `fix:` Correção de bug
- `docs:` Documentação
- `style:` Formatação
- `refactor:` Refatoração
- `test:` Testes
- `chore:` Tarefas gerais

## 🤝 Contribuindo

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'feat: Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 👥 Autores

- **Gamming-Dev** - *Trabalho Inicial* - [GitHub](https://github.com/Gamming-Dev)

## 📞 Suporte

Para suporte, abra uma [issue](https://github.com/Gamming-Dev/GameWeb/issues) no GitHub.

## 🗺️ Roadmap

- [ ] Sistema de clans/grupos
- [ ] Torneios de mineração
- [ ] NFT integration
- [ ] Mobile app (React Native)
- [ ] Sistema de achievements
- [ ] Chat em tempo real
- [ ] API pública para desenvolvedores

## ⚠️ Avisos Importantes

- **Projeto Educacional**: Este é um simulador de mineração virtual. Não minera criptomoedas reais.
- **Segurança**: Sempre use HTTPS em produção e configure adequadamente as chaves de segurança.
- **Performance**: Configure cache e otimização de banco de dados para melhor performance.

---

<div align="center">
  <p>Desenvolvido com 💎 por <a href="https://github.com/Gamming-Dev">Gamming-Dev</a></p>
  <p>⭐ Se este projeto te ajudou, considere dar uma estrela!</p>
</div>
