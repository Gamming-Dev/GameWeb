// MinerCore - Web3 Wallet Connection
// Suporte: MetaMask, Trust Wallet, Coinbase Wallet, etc.

class Web3Wallet {
    constructor() {
        this.provider = null;
        this.address = null;
        this.chainId = null;
        this.supportedChains = {
            'BSC': { chainId: '0x38', name: 'BNB Smart Chain' },
            'POL': { chainId: '0x89', name: 'Polygon' }
        };
    }

    // Detectar carteiras instaladas
    async detectWallets() {
        const wallets = [];
        
        // MetaMask
        if (window.ethereum && window.ethereum.isMetaMask) {
            wallets.push({ name: 'MetaMask', icon: '🦊', id: 'metamask' });
        }
        
        // Trust Wallet
        if (window.ethereum && window.ethereum.isTrust) {
            wallets.push({ name: 'Trust Wallet', icon: '🔵', id: 'trust' });
        }
        
        // Coinbase Wallet
        if (window.ethereum && window.ethereum.isCoinbaseWallet) {
            wallets.push({ name: 'Coinbase', icon: '🔵', id: 'coinbase' });
        }
        
        // WalletConnect (genérico)
        if (window.ethereum) {
            wallets.push({ name: 'Browser Wallet', icon: '💼', id: 'browser' });
        }
        
        return wallets;
    }

    // Conectar carteira
    async connect(chain = 'BSC') {
        try {
            if (!window.ethereum) {
                throw new Error('No wallet detected. Please install MetaMask or Trust Wallet.');
            }

            this.provider = window.ethereum;
            
            // Solicitar conexão
            const accounts = await this.provider.request({ 
                method: 'eth_requestAccounts' 
            });
            
            this.address = accounts[0];
            
            // Verificar chain
            const currentChainId = await this.provider.request({ method: 'eth_chainId' });
            const targetChain = this.supportedChains[chain];
            
            if (currentChainId !== targetChain.chainId) {
                await this.switchChain(chain);
            }
            
            // Verificar assinatura (provar propriedade)
            const signature = await this.signMessage();
            
            return {
                success: true,
                address: this.address,
                chain: chain,
                signature: signature
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    // Trocar rede
    async switchChain(chain) {
        const target = this.supportedChains[chain];
        
        try {
            await this.provider.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: target.chainId }]
            });
        } catch (switchError) {
            // Se rede não adicionada, adicionar
            if (switchError.code === 4902) {
                await this.addChain(chain);
            } else {
                throw switchError;
            }
        }
    }

    // Adicionar rede BSC/POL
    async addChain(chain) {
        const chains = {
            'BSC': {
                chainId: '0x38',
                chainName: 'BNB Smart Chain',
                nativeCurrency: { name: 'BNB', symbol: 'BNB', decimals: 18 },
                rpcUrls: ['https://bsc-dataseed.binance.org/'],
                blockExplorerUrls: ['https://bscscan.com']
            },
            'POL': {
                chainId: '0x89',
                chainName: 'Polygon Mainnet',
                nativeCurrency: { name: 'MATIC', symbol: 'MATIC', decimals: 18 },
                rpcUrls: ['https://polygon-rpc.com/'],
                blockExplorerUrls: ['https://polygonscan.com']
            }
        };
        
        await this.provider.request({
            method: 'wallet_addEthereumChain',
            params: [chains[chain]]
        });
    }

    // Assinar mensagem para verificação
    async signMessage() {
        const message = `MinerCore Wallet Verification\nAddress: ${this.address}\nTimestamp: ${Date.now()}\nNonce: ${Math.random().toString(36).substring(7)}`;
        
        const signature = await this.provider.request({
            method: 'personal_sign',
            params: [message, this.address]
        });
        
        return { message, signature };
    }

    // Desconectar (apenas limpar estado local)
    disconnect() {
        this.provider = null;
        this.address = null;
        this.chainId = null;
    }
}

// Instância global
const web3Wallet = new Web3Wallet();