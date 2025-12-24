# Deploy Your NFT Contract - Step by Step Guide

## Prerequisites

✅ Your Wallet Address: `0xB75A3ff17A9c8b730B0942F405454B0738e6eCFe`
✅ Make sure this wallet has Sepolia ETH (get from faucet if needed)

---

## Method 1: Deploy Using Remix IDE (Recommended - Easiest)

### Step 1: Open Remix

1. Go to https://remix.ethereum.org
2. You'll see the Remix IDE interface

### Step 2: Create Contract File

1. In the file explorer (left sidebar), click the "+" icon to create a new file
2. Name it: `CertificateNFT.sol`
3. Copy the contract code from `contracts/CertificateNFT.sol` and paste it into Remix

### Step 3: Compile the Contract

1. Click on the "Solidity Compiler" tab (left sidebar, 2nd icon)
2. Select compiler version: `0.8.20` or higher
3. Click "Compile CertificateNFT.sol"
4. Wait for green checkmark ✅

### Step 4: Deploy to Sepolia

1. Click on "Deploy & Run Transactions" tab (left sidebar, 3rd icon)
2. Change ENVIRONMENT to: **"Injected Provider - MetaMask"**
3. MetaMask will popup - connect your wallet
4. Make sure MetaMask is on **Sepolia Test Network**
5. Verify the account shown is: `0xB75A...eCFe`
6. Click **"Deploy"** button (orange)
7. MetaMask will popup - **Confirm the transaction**
8. Wait for deployment confirmation

### Step 5: Get Contract Address

1. After deployment, look in the "Deployed Contracts" section (bottom of left panel)
2. You'll see: `CERTIFICATENFT AT 0x...`
3. **COPY THIS ADDRESS** - this is your new contract address!

### Step 6: Update Your .env File

1. Open `c:\xampp\htdocs\Hackfest25-42\.env`
2. Update the contract address:

```
NFT_CONTRACT_ADDRESS=YOUR_NEW_CONTRACT_ADDRESS_HERE
```

---

## Method 2: Deploy Using Hardhat (Advanced)

### Step 1: Install Dependencies

```bash
cd c:\xampp\htdocs\Hackfest25-42
npm install --save-dev hardhat @openzeppelin/contracts
npm install --save-dev @nomicfoundation/hardhat-toolbox
```

### Step 2: Initialize Hardhat

```bash
npx hardhat init
# Select: Create a JavaScript project
# Use default settings
```

### Step 3: Configure Hardhat

Edit `hardhat.config.js` and add:

```javascript
require("@nomicfoundation/hardhat-toolbox");

module.exports = {
  solidity: "0.8.20",
  networks: {
    sepolia: {
      url: "https://sepolia.infura.io/v3/e3c8c679d881470c806ac54e9a362558",
      accounts: ["YOUR_PRIVATE_KEY_HERE"], // Add 0x prefix
    },
  },
};
```

### Step 4: Create Deployment Script

Create `scripts/deploy.js` (already created for you)

### Step 5: Deploy

```bash
npx hardhat run scripts/deploy.js --network sepolia
```

### Step 6: Copy Contract Address

The script will output: `Contract deployed to: 0x...`
Update your `.env` file with this address.

---

## After Deployment

### Verify Your Contract (Optional but Recommended)

1. Go to https://sepolia.etherscan.io
2. Search for your contract address
3. Click "Contract" tab
4. Click "Verify and Publish"
5. Follow the wizard

### Test Your Contract

1. Open `check_contract_owner.html` in your browser
2. Update the contract address to your new one
3. Click "Check Owner"
4. It should show: ✅ YES - You can mint!

---

## Quick Deployment Checklist

- [ ] Wallet has Sepolia ETH
- [ ] Opened Remix IDE
- [ ] Pasted contract code
- [ ] Compiled successfully
- [ ] Connected MetaMask
- [ ] Switched to Sepolia network
- [ ] Deployed contract
- [ ] Copied contract address
- [ ] Updated .env file
- [ ] Tested minting

---

## Need Help?

- Get Sepolia ETH: https://sepoliafaucet.com/
- Remix Tutorial: https://remix.ethereum.org/#tutorials
- Check transaction: https://sepolia.etherscan.io/
