// assets/js/core/mining.js
class MiningSimulator {
    constructor() {
        this.lastSync = Date.now();
        this.estimatedBalance = 0;
        this.tickRate = 1000; // Atualiza visual a cada 1s
        
        this.startVisualSimulation();
        this.syncWithServer(); // Sincroniza a cada 30s
    }
    
    startVisualSimulation() {
        setInterval(() => {
            // Animação local (estimativa)
            this.estimatedBalance += this.calculateTick();
            this.updateDisplay();
        }, this.tickRate);
    }
    
    async syncWithServer() {
        // Pega valor real do servidor
        const response = await fetch('api/mining/update.php');
        const data = await response.json();
        
        // Corrige estimativa com valor real
        this.estimatedBalance = data.real_balance;
        this.updateDisplay();
    }
}