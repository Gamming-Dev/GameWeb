# 🛒 MinerCore Store - Installation Guide

## 📋 Overview

This document provides step-by-step instructions to install and configure the MinerCore Store system, which includes:
- 🎰 **6 Mining Slots** with 3 bonus levels
- ⚡ **6 Mining Machines** with different power levels
- 🔋 **3 Battery Types** for power storage
- ☀️ **3 Solar Panel Types** for cost reduction
- 💰 **MCT Token System** (MinerCore Token pegged to USD)

## 🗄️ Database Setup

### Step 1: Run the Store Setup SQL

Execute the store setup script in your MySQL database:

```bash
mysql -u your_username -p your_database_name < install/store_setup.sql
```

Or via phpMyAdmin:
1. Open phpMyAdmin
2. Select your database
3. Go to "Import" tab
4. Choose file `install/store_setup.sql`
5. Click "Go"

### Step 2: Verify Tables Created

The script creates the following tables:
- ✅ `store_items` - Store product catalog
- ✅ `user_slots` - User mining slots
- ✅ `user_items` - User inventory
- ✅ `store_transactions` - Purchase history

Verify with:
```sql
SHOW TABLES LIKE 'store%';
SHOW TABLES LIKE 'user_slots';
SHOW TABLES LIKE 'user_items';
```

### Step 3: Check Initial Data

Verify that initial products were inserted:

```sql
-- Check slots (should return 6 rows)
SELECT COUNT(*) FROM store_items WHERE category = 'slot';

-- Check miners (should return 6 rows)
SELECT COUNT(*) FROM store_items WHERE category = 'miner';

-- Check batteries (should return 3 rows)
SELECT COUNT(*) FROM store_items WHERE category = 'battery';

-- Check solar panels (should return 3 rows)
SELECT COUNT(*) FROM store_items WHERE category = 'solar_panel';
```

## 💰 Give Users Initial MCT Balance (Optional)

For testing purposes, you can give users initial MCT tokens:

```sql
-- Give 1000 MCT to all users
UPDATE users SET mct_balance = 1000.00;

-- Or give to specific user
UPDATE users SET mct_balance = 5000.00 WHERE id = 1;
```

## 🎮 Accessing the Store

1. **Navigate to Store Page**
   ```
   http://your-domain.com/store.php
   ```

2. **User Must Be Logged In**
   - Redirects to login if not authenticated
   - Uses existing session system

## 📊 Product Categories

### 🎰 Mining Slots (6 products)

| Product | Bonus Level | Price (MCT) | Rarity |
|---------|-------------|-------------|--------|
| Basic Slot | 0 (+0%) | 50.00 | Common |
| Basic Slot +1 | 1 (+10%) | 150.00 | Common |
| Enhanced Slot | 0 (+0%) | 200.00 | Rare |
| Enhanced Slot +2 | 2 (+20%) | 400.00 | Rare |
| Premium Slot | 0 (+0%) | 600.00 | Epic |
| Premium Slot +3 | 3 (+30%) | 1000.00 | Legendary |

### ⚡ Mining Machines (6 products)

| Product | Hashrate | Power | Efficiency | Price | Rarity |
|---------|----------|-------|------------|-------|--------|
| Nano Miner S1 | 5 TH/s | 250W | 50 W/TH | 75.00 | Common |
| CompactHash 3000 | 15 TH/s | 650W | 43.33 W/TH | 250.00 | Common |
| ProMiner X7 | 50 TH/s | 1500W | 30 W/TH | 600.00 | Rare |
| TitanHash Ultra | 150 TH/s | 3500W | 23.33 W/TH | 1500.00 | Epic |
| QuantumRig Elite | 400 TH/s | 7500W | 18.75 W/TH | 3500.00 | Epic |
| HyperForge Omega | 1200 TH/s | 18000W | 15 W/TH | 10000.00 | Legendary |

### 🔋 Batteries (3 products)

| Product | Capacity | Efficiency | Charge Rate | Price | Rarity |
|---------|----------|------------|-------------|-------|--------|
| BasicCell 2000 | 10 kWh | 85% | 5 kW | 200.00 | Common |
| PowerVault Pro | 50 kWh | 92% | 15 kW | 750.00 | Rare |
| MegaStore X10 | 200 kWh | 97% | 50 kW | 2500.00 | Epic |

### ☀️ Solar Panels (3 products)

| Product | Power Output | Efficiency | Cost Reduction | Price | Rarity |
|---------|--------------|------------|----------------|-------|--------|
| EcoPanel 100 | 5 kW | 18% | -15% | 300.00 | Common |
| SolarMax 500 | 25 kW | 24% | -35% | 1200.00 | Rare |
| SunHarvester Titan | 100 kW | 32% | -60% | 4000.00 | Legendary |

## 🔧 Configuration

### MCT Exchange Rate

The MCT token is pegged 1:1 to USD by default. To change:

```sql
UPDATE settings SET config_value = '1.50' WHERE config_key = 'mct_usd_peg';
```

### Slot Limit

Maximum 6 slots per user. To change:

```sql
UPDATE settings SET config_value = '10' WHERE config_key = 'slot_unlock_limit';
```

## 🌐 API Endpoints

### Purchase Item
```
POST /api/store/purchase.php
Content-Type: application/json

{
  "item_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Successfully purchased Basic Slot!",
  "new_balance": 950.00,
  "item": {
    "id": 1,
    "name": "Basic Slot",
    "category": "slot"
  }
}
```

### Get Inventory
```
GET /api/store/inventory.php
```

**Response:**
```json
{
  "success": true,
  "data": {
    "mct_balance": 1000.00,
    "slots": [...],
    "inventory": [...],
    "stats": {
      "total_hashrate": 150.00,
      "total_battery_capacity": 50.00,
      "total_solar_output": 25.00,
      "unlocked_slots": 3
    }
  }
}
```

## 🎨 Design System

The store uses the same design system as the rest of MinerCore:

- **Primary Color**: Cyan (`#2de2e6`)
- **Secondary Color**: Green (`#3cffb3`)
- **Accent**: Orange/Yellow (`#ff9f1c` / `#ffbf3c`)
- **Background**: Dark purple-wine (`#1b0f14`)
- **Font**: Orbitron (headings), Rajdhani (body)

## 🔒 Security Features

- ✅ Session-based authentication
- ✅ SQL injection protection (prepared statements)
- ✅ CSRF protection
- ✅ Transaction atomicity (rollback on errors)
- ✅ Balance validation before purchase
- ✅ Stock management

## 🧪 Testing

### Test Purchase Flow

1. **Login as test user**
2. **Check initial balance**
   ```sql
   SELECT username, mct_balance FROM users WHERE id = 1;
   ```
3. **Purchase an item** through UI
4. **Verify balance deducted**
5. **Check inventory**
   ```sql
   SELECT * FROM user_items WHERE user_id = 1;
   ```
6. **Check transaction history**
   ```sql
   SELECT * FROM store_transactions WHERE user_id = 1;
   ```

### Test Insufficient Balance

1. Set user balance to low amount:
   ```sql
   UPDATE users SET mct_balance = 10.00 WHERE id = 1;
   ```
2. Try to purchase expensive item
3. Verify error message appears
4. Verify no balance change

## 🚀 Next Steps

1. **Integration with Mining System**
   - Connect slots with active miners
   - Apply bonus multipliers to hashrate
   - Calculate power consumption with batteries/solar

2. **Admin Panel**
   - Manage store items
   - Add/edit/disable products
   - View sales analytics

3. **Market System**
   - User-to-user trading
   - Auction system
   - Price discovery

4. **Achievements**
   - First purchase reward
   - Collector badges
   - Spending milestones

## 📞 Support

For issues or questions:
- Open an issue on GitHub
- Check documentation in README.md
- Review code comments in store.php

## 📄 Files Modified/Created

```
store.php                      # Main store page
install/store_setup.sql        # Database setup
api/store/purchase.php         # Purchase API
api/store/inventory.php        # Inventory API
```

---

**Installation Date**: 2026-02-15  
**Version**: 1.0.0  
**Status**: ✅ Ready for Production
