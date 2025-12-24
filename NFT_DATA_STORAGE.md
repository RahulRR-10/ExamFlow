# NFT Certificate Data Storage - Complete Overview

## 📊 Data Stored in Your NFT System

Your NFT certificate system stores data in **three locations**: IPFS, Blockchain (Smart Contract), and Database.

---

## 🌐 1. IPFS Storage (Pinata)

IPFS stores the **actual certificate content** in a decentralized manner.

### A. Certificate Image (PNG file)
**Stored as**: Binary PNG file  
**Location**: `ipfs://{IpfsHash}`  
**Gateway URL**: `https://gateway.pinata.cloud/ipfs/{IpfsHash}`

**Pinata Metadata for Image**:
```json
{
  "name": "{studentID}-{examName}-{randomNumber}",
  "keyvalues": {
    "student": "Student Full Name",
    "student_id": "Student Username/ID",
    "exam": "Exam Name",
    "score": "85",
    "integrity_score": "95",
    "integrity_category": "High Integrity",
    "timestamp": "2025-12-24T07:17:34.137Z"
  }
}
```

### B. NFT Metadata (JSON file)
**Stored as**: JSON document  
**Location**: `ipfs://{MetadataHash}`  
**Gateway URL**: `https://gateway.pinata.cloud/ipfs/{MetadataHash}`

**Complete NFT Metadata Structure**:
```json
{
  "name": "{studentID}-{examName}-{randomNumber}",
  "description": "Certificate of completion for {Student Name} (ID: {studentID}) in {subject} with a score of {score}/{total} ({percentage}%) and integrity score of {integrity_score}/100 ({integrity_category})",
  "image": "ipfs://{ImageHash}",
  "image_url": "https://gateway.pinata.cloud/ipfs/{ImageHash}",
  "external_url": "https://gateway.pinata.cloud/ipfs/{ImageHash}",
  "attributes": [
    {
      "trait_type": "Student ID",
      "value": "student123"
    },
    {
      "trait_type": "Student Name",
      "value": "John Doe"
    },
    {
      "trait_type": "Exam",
      "value": "Chemistry Final Exam"
    },
    {
      "trait_type": "Subject",
      "value": "Chemistry"
    },
    {
      "trait_type": "Score",
      "value": 85
    },
    {
      "trait_type": "Total",
      "value": 100
    },
    {
      "trait_type": "Percentage",
      "value": 85
    },
    {
      "trait_type": "Marks",
      "value": "85/100 (85%)"
    },
    {
      "trait_type": "Integrity Score",
      "value": 95
    },
    {
      "trait_type": "Integrity Category",
      "value": "High Integrity"
    },
    {
      "trait_type": "Exam Integrity",
      "value": "95/100 (High Integrity)"
    },
    {
      "trait_type": "Completion Date",
      "value": "2025-12-24"
    }
  ]
}
```

---

## ⛓️ 2. Blockchain Storage (Smart Contract)

**Contract Address**: `0xdBF37882c5a1198ffDc16D7E36272Abf867b2162`  
**Network**: Sepolia Testnet  
**Contract Name**: CertificateNFT  
**Symbol**: CERT

### Data Stored On-Chain:

#### Per NFT Token:
```solidity
// ERC721 Standard Data
- tokenId: uint256           // Unique token number (1, 2, 3, ...)
- owner: address             // 0xB75A3ff17A9c8b730B0942F405454B0738e6eCFe
- tokenURI: string           // "ipfs://Qm..." (metadata IPFS hash)

// Contract State
- _tokenIdCounter: uint256   // Total number of minted NFTs
- contract owner: address    // 0xB75A3ff17A9c8b730B0942F405454B0738e6eCFe
```

#### What's NOT stored on blockchain:
- ❌ Student names
- ❌ Exam scores
- ❌ Certificate images
- ❌ Any personal data

**Only stored**: Token ownership and a pointer (URI) to the IPFS metadata

---

## 💾 3. Database Storage (MySQL)

**Table**: `certificate_nfts`

```sql
CREATE TABLE certificate_nfts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT(11) NOT NULL,
    uname VARCHAR(100) NOT NULL,
    transaction_hash VARCHAR(255) NOT NULL,
    token_id VARCHAR(100) NOT NULL,
    contract_address VARCHAR(255) NOT NULL,
    metadata_url VARCHAR(255) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_demo TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY (attempt_id)
);
```

**Data Stored**:
- `attempt_id` - Links to the exam attempt
- `uname` - Student username
- `transaction_hash` - Ethereum transaction hash (0x1d4f5...)
- `token_id` - NFT token ID from blockchain
- `contract_address` - Smart contract address
- `metadata_url` - IPFS URL to metadata JSON
- `image_url` - IPFS URL to certificate PNG
- `is_demo` - Whether this is a demo/test mint
- `created_at` - Timestamp of minting

---

## 🔗 Data Flow

```
1. Student completes exam
   ↓
2. Generate certificate (HTML/CSS → PNG)
   ↓
3. Upload PNG to IPFS via Pinata
   → Returns: ipfs://bafkrei...
   ↓
4. Create metadata JSON with certificate data
   ↓
5. Upload metadata JSON to IPFS via Pinata
   → Returns: ipfs://Qma59Eu...
   ↓
6. Mint NFT on blockchain
   → Call: contract.mint("ipfs://Qma59Eu...")
   → Stores: tokenId + tokenURI on-chain
   ↓
7. Save record in database
   → transaction_hash, token_id, URLs, etc.
   ↓
8. ✅ NFT Certificate Complete!
```

---

## 🔍 How to View Your NFT Data

### View IPFS Data:
1. **Image**: `https://gateway.pinata.cloud/ipfs/{ImageHash}`
2. **Metadata**: `https://gateway.pinata.cloud/ipfs/{MetadataHash}`

### View Blockchain Data:
1. **Etherscan**: `https://sepolia.etherscan.io/address/0xdBF37882c5a1198ffDc16D7E36272Abf867b2162`
2. **Transaction**: `https://sepolia.etherscan.io/tx/{transaction_hash}`
3. **Token**: `https://sepolia.etherscan.io/nft/0xdBF37882c5a1198ffDc16D7E36272Abf867b2162/{tokenId}`

### View on NFT Marketplaces:
1. **Etherscan NFT Page**: `https://sepolia.etherscan.io/nft/0xdBF37882c5a1198ffDc16D7E36272Abf867b2162/{tokenId}`
2. **NFTScan Testnet**: `https://testnets.nftscan.com/0xdBF37882c5a1198ffDc16D7E36272Abf867b2162?module=NFTs&tokenId={tokenId}&chainId=11155111`
3. **Note**: OpenSea has discontinued testnet support as of 2024

---

## 🔒 Data Permanence

### IPFS (Pinata):
- ✅ **Permanent** as long as pinned
- Your Pinata account keeps files pinned
- Files are distributed across IPFS network
- Can be accessed via any IPFS gateway

### Blockchain:
- ✅ **Permanently immutable**
- Cannot be changed or deleted
- Exists as long as Ethereum exists
- Verified by thousands of nodes

### Database:
- ⚠️ **Application-specific**
- Can be backed up/restored
- Helps with quick lookups
- Not decentralized

---

## 📋 Summary

| Location | What's Stored | Why |
|----------|---------------|-----|
| **IPFS** | Certificate image + metadata JSON | Decentralized storage, permanent, accessible to anyone |
| **Blockchain** | Token ID + Owner + IPFS URI | Proof of ownership, immutable, trustless verification |
| **Database** | Transaction details + references | Fast queries, user dashboard, linking to exams |

**Your deployed contract**: [`0xdBF37882c5a1198ffDc16D7E36272Abf867b2162`](https://sepolia.etherscan.io/address/0xdBF37882c5a1198ffDc16D7E36272Abf867b2162)
