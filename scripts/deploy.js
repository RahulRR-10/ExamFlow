const hre = require("hardhat");

async function main() {
  console.log("Deploying CertificateNFT contract to Sepolia...");
  console.log("Deploying from wallet:", (await hre.ethers.getSigners())[0].address);

  // Deploy the contract
  const CertificateNFT = await hre.ethers.getContractFactory("CertificateNFT");
  const certificateNFT = await CertificateNFT.deploy();

  await certificateNFT.waitForDeployment();

  const contractAddress = await certificateNFT.getAddress();
  
  console.log("\n✅ Contract deployed successfully!");
  console.log("📝 Contract Address:", contractAddress);
  console.log("\n📋 Next Steps:");
  console.log("1. Copy the contract address above");
  console.log("2. Update your .env file:");
  console.log(`   NFT_CONTRACT_ADDRESS=${contractAddress}`);
  console.log("\n3. Verify on Etherscan:");
  console.log(`   https://sepolia.etherscan.io/address/${contractAddress}`);
  
  // Wait for a few block confirmations
  console.log("\n⏳ Waiting for block confirmations...");
  await certificateNFT.deploymentTransaction().wait(5);
  console.log("✅ Contract confirmed!");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
