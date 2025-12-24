require("@nomicfoundation/hardhat-toolbox");

/** @type import('hardhat/config').HardhatUserConfig */
module.exports = {
  solidity: "0.8.20",
  networks: {
    sepolia: {
      url: "https://sepolia.infura.io/v3/e3c8c679d881470c806ac54e9a362558",
      accounts: ["0x0bc072f843204f8bc779057593a7fd7ed94dd263a9d3d62ad96fad4649f1de27"]
    }
  },
  etherscan: {
    apiKey: "YOUR_ETHERSCAN_API_KEY" // Optional: for contract verification
  }
};
